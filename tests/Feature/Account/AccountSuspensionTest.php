<?php

namespace Tests\Feature\Account;

use App\Enums\AuditAction;
use App\Enums\NotificationKind;
use App\Enums\SuspensionOutcome;
use App\Models\AuditEvent;
use App\Models\User;
use App\Notifications\AccountSuspended;
use App\Notifications\AccountSuspensionLifted;
use App\Notifications\ErrorReportReceived;
use App\Services\Account\AccountSuspension;
use App\Services\Notifications\SiteNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ActsAsRoles;
use Tests\TestCase;

/**
 * Suspension d'un compte par un administrateur : règles du modèle, politique, service, écran
 * d'administration, journal d'audit et notification du membre.
 */
class AccountSuspensionTest extends TestCase
{
    use ActsAsRoles;
    use RefreshDatabase;

    private const REASON = 'Contributions répétées sans source, malgré deux rappels.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withCookie('locale', 'fr');
    }

    private function suspendedMember(array $attributes = []): User
    {
        return $this->member('contributor', [
            'suspended_at' => now()->subDay(),
            'suspension_reason' => self::REASON,
            ...$attributes,
        ]);
    }

    // ---- Modèle -------------------------------------------------------------------------------

    public function test_a_suspension_without_end_lasts_until_lifted(): void
    {
        $this->assertTrue($this->suspendedMember(['suspended_until' => null])->isSuspended());
    }

    public function test_a_suspension_with_a_future_end_is_active_and_a_past_end_is_over(): void
    {
        $this->assertTrue($this->suspendedMember(['suspended_until' => now()->addDays(2)])->isSuspended());
        $this->assertFalse($this->suspendedMember(['suspended_until' => now()->subMinute()])->isSuspended());
        $this->assertFalse($this->member('contributor')->isSuspended());
    }

    public function test_scopes_follow_the_same_rule_as_the_method(): void
    {
        $open = $this->suspendedMember();
        $dated = $this->suspendedMember(['suspended_until' => now()->addDay()]);
        $over = $this->suspendedMember(['suspended_until' => now()->subHour()]);
        $free = $this->member('contributor');

        $this->assertEqualsCanonicalizing([$open->id, $dated->id], User::query()->suspended()->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$over->id, $free->id], User::query()->notSuspended()->pluck('id')->all());
    }

    public function test_a_suspended_second_admin_does_not_count_when_looking_for_the_last_admin(): void
    {
        $first = $this->member('admin');
        $second = $this->member('admin');
        $this->assertFalse($first->isLastAdmin());

        $second->forceFill(['suspended_at' => now()])->save();
        $this->assertTrue($first->fresh()->isLastAdmin(), 'le seul administrateur en mesure d’agir');
        $this->assertFalse($second->fresh()->isLastAdmin());

        $second->forceFill(['suspended_until' => now()->subMinute()])->save();
        $this->assertFalse($first->fresh()->isLastAdmin(), 'une suspension échue ne compte plus');
    }

    // ---- Politique ----------------------------------------------------------------------------

    public function test_an_admin_may_suspend_another_member(): void
    {
        $admin = $this->member('admin');
        $this->assertTrue(Gate::forUser($admin)->allows('suspend', $this->member('validator')));
        $this->assertTrue(Gate::forUser($admin)->allows('suspend', $this->member('admin')));
        $this->assertTrue(Gate::forUser($admin)->allows('lift', $this->suspendedMember()));
    }

    public function test_reviewers_and_contributors_cannot_suspend_or_list(): void
    {
        $target = $this->member('contributor');
        foreach (['visitor', 'contributor', 'validator'] as $role) {
            $user = $this->member($role);
            $this->assertTrue(Gate::forUser($user)->denies('suspend', $target), $role);
            $this->assertTrue(Gate::forUser($user)->denies('lift', $target), $role);
            $this->assertTrue(Gate::forUser($user)->denies('viewAny', User::class), $role);
        }
    }

    public function test_a_suspended_admin_cannot_act(): void
    {
        $admin = $this->member('admin', ['suspended_at' => now()]);
        $this->member('admin');
        $target = $this->member('contributor');

        $this->assertTrue(Gate::forUser($admin)->denies('suspend', $target));
        $this->assertTrue(Gate::forUser($admin)->denies('lift', $target));
        $this->assertTrue(Gate::forUser($admin)->denies('viewAny', User::class));
    }

    public function test_nobody_suspends_their_own_account_even_as_the_only_admin(): void
    {
        $alone = $this->member('admin');
        $response = Gate::forUser($alone)->inspect('suspend', $alone);
        $this->assertTrue($response->denied());
        $this->assertSame(__('moderation.errors.self'), $response->message());

        $this->member('admin');
        $this->assertSame(__('moderation.errors.self'), Gate::forUser($alone)->inspect('suspend', $alone)->message());
    }

    public function test_the_policy_never_lets_an_admin_suspend_the_last_active_admin(): void
    {
        // Le seul administrateur actif (`$active`) ne peut être visé que par un administrateur
        // actif, qui serait alors un second administrateur : la règle « dernier administrateur »
        // ne se déclenche donc jamais depuis l'interface, mais le service la garde (ci-dessous).
        $active = $this->member('admin');
        $suspended = $this->member('admin', ['suspended_at' => now()->subHour()]);

        $this->assertTrue(Gate::forUser($suspended)->denies('suspend', $active), 'suspendu : ne peut pas agir');
        $this->assertTrue(Gate::forUser($this->member('validator'))->denies('suspend', $active));
    }

    public function test_the_service_refuses_the_last_active_admin_whoever_asks(): void
    {
        Notification::fake();
        $onlyAdmin = $this->member('admin');
        $notAnAdmin = $this->member('validator');

        $outcome = app(AccountSuspension::class)->suspend($onlyAdmin, $notAnAdmin, self::REASON, null);

        $this->assertSame(SuspensionOutcome::LastAdmin, $outcome);
        $this->assertFalse($onlyAdmin->fresh()->isSuspended());
        $this->assertSame(0, AuditEvent::count());
    }

    public function test_two_admins_suspending_each_other_leave_one_able_to_act(): void
    {
        Notification::fake();
        $first = $this->member('admin');
        $second = $this->member('admin');
        $service = app(AccountSuspension::class);

        $this->assertSame(SuspensionOutcome::Done, $service->suspend($second, $first, self::REASON, null));
        $this->assertSame(SuspensionOutcome::ActorSuspended, $service->suspend($first, $second, self::REASON, null));

        $this->assertFalse($first->fresh()->isSuspended());
        $this->assertTrue($second->fresh()->isSuspended());
    }

    public function test_a_suspended_reviewer_is_not_told_that_something_awaits_review(): void
    {
        $active = $this->member('validator');
        $suspended = $this->member('validator', ['suspended_at' => now()]);
        $expired = $this->member('validator', ['suspended_at' => now()->subDays(3), 'suspended_until' => now()->subDay()]);

        app(SiteNotifier::class)->toReviewers(new ErrorReportReceived(1, 'Paris - Le Havre'));

        $this->assertSame(1, $active->notifications()->count());
        $this->assertSame(0, $suspended->notifications()->count());
        $this->assertSame(1, $expired->notifications()->count(), 'une suspension échue ne prive plus de notification');
    }

    // ---- Service ------------------------------------------------------------------------------

    public function test_the_service_sets_the_suspension_logs_it_and_notifies_the_member(): void
    {
        Notification::fake();
        $admin = $this->member('admin');
        $member = $this->member('contributor', ['name' => 'Camille Durand']);

        $outcome = app(AccountSuspension::class)->suspend($member, $admin, '  '.self::REASON.'  ', now()->addDays(3)->endOfDay());

        $this->assertSame(SuspensionOutcome::Done, $outcome);
        $member->refresh();
        $this->assertTrue($member->isSuspended());
        $this->assertSame(self::REASON, $member->suspension_reason);
        $this->assertSame($admin->id, $member->suspended_by);

        $event = AuditEvent::sole();
        $this->assertSame(AuditAction::AccountSuspended, $event->action);
        $this->assertSame($admin->id, $event->actor_id);
        $this->assertSame($member->id, $event->context['account_id']);
        $this->assertTrue($event->context['with_end_date']);
        $encoded = json_encode($event->context);
        $this->assertStringNotContainsString('Contributions répétées', $encoded);
        $this->assertStringNotContainsString('Camille', $encoded);

        Notification::assertSentTo($member, AccountSuspended::class, fn (AccountSuspended $n) => $n->reason === self::REASON && $n->until !== null);
    }

    public function test_a_second_suspension_is_refused_until_the_first_is_lifted(): void
    {
        Notification::fake();
        $admin = $this->member('admin');
        $member = $this->suspendedMember();

        $this->assertSame(SuspensionOutcome::AlreadySuspended, app(AccountSuspension::class)->suspend($member, $admin, self::REASON, null));
        $this->assertSame(0, AuditEvent::count());
        Notification::assertNothingSent();
    }

    public function test_an_expired_suspension_can_be_replaced_by_a_new_one(): void
    {
        Notification::fake();
        $admin = $this->member('admin');
        $member = $this->suspendedMember(['suspended_until' => now()->subDay()]);

        $outcome = app(AccountSuspension::class)->suspend($member, $admin, 'Nouvelle série de contributions sans source.', null);

        $this->assertSame(SuspensionOutcome::Done, $outcome);
        $this->assertTrue($member->fresh()->isSuspended());
        $this->assertSame('Nouvelle série de contributions sans source.', $member->fresh()->suspension_reason);
    }

    public function test_lifting_clears_every_field_logs_and_notifies(): void
    {
        Notification::fake();
        $admin = $this->member('admin');
        $member = $this->suspendedMember(['suspended_until' => now()->addWeek(), 'suspended_by' => $admin->id]);

        $this->assertSame(SuspensionOutcome::Done, app(AccountSuspension::class)->lift($member, $admin));

        $member->refresh();
        $this->assertFalse($member->isSuspended());
        $this->assertNull($member->suspended_at);
        $this->assertNull($member->suspended_until);
        $this->assertNull($member->suspension_reason);
        $this->assertNull($member->suspended_by);
        $event = AuditEvent::sole();
        $this->assertSame(AuditAction::AccountSuspensionLifted, $event->action);
        $this->assertSame(['account_id' => $member->id], $event->context);
        Notification::assertSentTo($member, AccountSuspensionLifted::class);
    }

    public function test_lifting_an_account_that_is_not_suspended_does_nothing(): void
    {
        Notification::fake();
        $admin = $this->member('admin');
        $member = $this->member('contributor');

        $this->assertSame(SuspensionOutcome::NotSuspended, app(AccountSuspension::class)->lift($member, $admin));
        $this->assertSame(0, AuditEvent::count());
        Notification::assertNothingSent();
    }

    public function test_lifting_an_expired_suspension_tidies_it_without_announcing_anything(): void
    {
        Notification::fake();
        $admin = $this->member('admin');
        $member = $this->suspendedMember(['suspended_until' => now()->subDay()]);

        $this->assertSame(SuspensionOutcome::NotSuspended, app(AccountSuspension::class)->lift($member, $admin));
        $this->assertNull($member->fresh()->suspended_at);
        $this->assertSame(0, AuditEvent::count());
        Notification::assertNothingSent();
    }

    // ---- Notification du membre ---------------------------------------------------------------

    public function test_the_site_notification_carries_the_reason_the_end_date_and_the_way_to_contest(): void
    {
        $admin = $this->member('admin');
        $member = $this->member('contributor');
        $until = now()->setDate(2026, 12, 24)->endOfDay();

        app(AccountSuspension::class)->suspend($member, $admin, self::REASON, $until);

        $data = $member->notifications()->sole()->data;
        $this->assertSame(NotificationKind::AccountSuspended->value, $data['kind']);
        $this->assertSame('Votre compte est suspendu', $data['title']);
        $this->assertSame(self::REASON, $data['reason']);
        $this->assertStringContainsString('24 décembre 2026 inclus', $data['body']);
        $this->assertStringContainsString('contact@vff-project.org', $data['body']);
        $this->assertSame('/profile', $data['url']);
        $this->assertStringNotContainsString($admin->name, json_encode($data));
    }

    public function test_the_notification_is_written_in_the_language_of_the_member(): void
    {
        $admin = $this->member('admin');
        $english = $this->member('contributor', ['locale_preference' => 'en']);
        $german = $this->member('contributor', ['locale_preference' => 'de']);

        app(AccountSuspension::class)->suspend($english, $admin, self::REASON, null);
        app(AccountSuspension::class)->suspend($german, $admin, self::REASON, null);

        $this->assertSame('Your account is suspended', $english->notifications()->sole()->data['title']);
        $this->assertSame('Ihr Konto ist gesperrt', $german->notifications()->sole()->data['title']);
    }

    public function test_the_mail_is_sent_and_lists_the_reason(): void
    {
        $member = $this->member('contributor');

        $mail = (new AccountSuspended(self::REASON, null))->toMail($member);

        $this->assertSame('Votre compte est suspendu', $mail->subject);
        $this->assertStringContainsString(self::REASON, implode(' ', $mail->introLines));
        $this->assertSame(route('profile.show'), $mail->actionUrl);
        $this->assertSame(['database', 'mail'], (new AccountSuspended(self::REASON, null))->via($member));
    }

    public function test_lifting_notifies_in_the_language_of_the_member(): void
    {
        $admin = $this->member('admin');
        $member = $this->suspendedMember(['locale_preference' => 'de']);

        app(AccountSuspension::class)->lift($member, $admin);

        $this->assertSame('Die Sperrung Ihres Kontos ist aufgehoben', $member->notifications()->sole()->data['title']);
    }

    // ---- Écran d'administration ---------------------------------------------------------------

    public function test_the_accounts_page_is_for_admins_only(): void
    {
        $this->get('/validate/accounts')->assertRedirect('/login');
        foreach (['visitor', 'contributor', 'validator'] as $role) {
            $this->actingAs($this->member($role))->get('/validate/accounts')->assertForbidden();
        }
        $this->actingAs($this->member('admin'))->get('/validate/accounts')->assertOk();
    }

    public function test_the_list_shows_accounts_with_their_suspension_and_why_some_cannot_be_suspended(): void
    {
        $admin = $this->member('admin', ['name' => 'Aline Admin']);
        $this->suspendedMember(['name' => 'Bruno Bloqué', 'suspended_until' => now()->addDay(), 'suspended_by' => $admin->id]);
        $this->member('contributor', ['name' => 'Chloé Contrib']);

        $this->actingAs($admin)->get('/validate/accounts')->assertInertia(fn (Assert $page) => $page
            ->component('Validate/Accounts')
            ->where('filter', 'all')
            ->where('counts.suspended', 1)
            ->has('accounts.data', 3)
            ->where('accounts.data.0.name', 'Aline Admin')
            ->where('accounts.data.0.can_suspend', false)
            ->where('accounts.data.0.blocked', __('moderation.errors.self'))
            ->where('accounts.data.1.name', 'Bruno Bloqué')
            ->where('accounts.data.1.suspension.reason', self::REASON)
            ->where('accounts.data.1.suspension.by', 'Aline Admin')
            ->where('accounts.data.2.can_suspend', true)
            ->where('accounts.data.2.suspension', null));
    }

    public function test_the_list_never_exposes_emails_or_password_hashes(): void
    {
        $admin = $this->member('admin');
        $this->member('contributor', ['email' => 'secret.adresse@exemple.fr']);

        $body = $this->actingAs($admin)->get('/validate/accounts')->getContent();

        $this->assertStringNotContainsString('secret.adresse@exemple.fr', html_entity_decode((string) $body));
        $this->assertStringNotContainsString('remember_token', (string) $body);
    }

    public function test_the_suspended_filter_lists_only_active_suspensions(): void
    {
        $admin = $this->member('admin');
        $this->suspendedMember(['name' => 'Actif']);
        $this->suspendedMember(['name' => 'Échu', 'suspended_until' => now()->subDay()]);

        $this->actingAs($admin)->get('/validate/accounts?filter=suspended')->assertInertia(fn (Assert $page) => $page
            ->where('filter', 'suspended')
            ->has('accounts.data', 1)
            ->where('accounts.data.0.name', 'Actif'));
    }

    public function test_search_matches_name_or_email_and_treats_wildcards_literally(): void
    {
        // Nom et adresse fixés : la fabrique tire des noms français, et un « Dupont » au hasard donnait deux résultats.
        $admin = $this->member('admin', ['name' => 'Administratrice', 'email' => 'admin@vff.test']);
        $this->member('contributor', ['name' => 'Jean Dupont', 'email' => 'jd@exemple.fr']);
        $this->member('contributor', ['name' => 'Marie Curie', 'email' => 'mc@labo.fr']);

        $this->actingAs($admin)->get('/validate/accounts?q=dupont')->assertInertia(fn (Assert $page) => $page
            ->has('accounts.data', 1)->where('accounts.data.0.name', 'Jean Dupont')->where('search', 'dupont'));
        $this->actingAs($admin)->get('/validate/accounts?q=LABO.fr')->assertInertia(fn (Assert $page) => $page
            ->has('accounts.data', 1)->where('accounts.data.0.name', 'Marie Curie'));
        $this->actingAs($admin)->get('/validate/accounts?q=%25')->assertInertia(fn (Assert $page) => $page
            ->has('accounts.data', 0));
        $this->actingAs($admin)->get('/validate/accounts?q=_')->assertInertia(fn (Assert $page) => $page
            ->has('accounts.data', 0));
    }

    public function test_the_list_is_paginated(): void
    {
        $admin = $this->member('admin');
        User::factory()->count(25)->create();

        $this->actingAs($admin)->get('/validate/accounts?page=2')->assertInertia(fn (Assert $page) => $page
            ->where('accounts.current_page', 2)->where('accounts.last_page', 2)->has('accounts.data', 6));
    }

    // ---- Suspension et levée par l'écran ------------------------------------------------------

    public function test_an_admin_suspends_an_account_with_a_reason_and_an_end_date(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 19)->setTime(10, 0));
        $admin = $this->member('admin');
        $member = $this->member('contributor', ['name' => 'Camille Durand']);

        $this->actingAs($admin)->from('/validate/accounts')
            ->post("/validate/accounts/{$member->id}/suspension", ['reason' => self::REASON, 'until' => '2026-12-24'])
            ->assertRedirect('/validate/accounts')
            ->assertSessionHas('success', fn (string $m) => str_contains($m, 'Camille Durand'));

        $member->refresh();
        $this->assertTrue($member->isSuspended());
        $this->assertSame('2026-12-24 23:59:59', $member->suspended_until->format('Y-m-d H:i:s'));
        $this->assertSame($admin->id, $member->suspended_by);
        $this->assertSame(1, $member->notifications()->count());
        $this->assertSame(AuditAction::AccountSuspended, AuditEvent::sole()->action);
    }

    public function test_the_end_date_is_optional(): void
    {
        $admin = $this->member('admin');
        $member = $this->member('contributor');

        $this->actingAs($admin)->post("/validate/accounts/{$member->id}/suspension", ['reason' => self::REASON, 'until' => ''])
            ->assertSessionHasNoErrors();

        $this->assertTrue($member->fresh()->isSuspended());
        $this->assertNull($member->fresh()->suspended_until);
    }

    public function test_the_reason_is_required_and_bounded(): void
    {
        $admin = $this->member('admin');
        $member = $this->member('contributor');
        $url = "/validate/accounts/{$member->id}/suspension";

        foreach ([[], ['reason' => ''], ['reason' => 'court'], ['reason' => '         ']] as $payload) {
            $this->actingAs($admin)->post($url, $payload)->assertSessionHasErrors('reason');
        }
        $this->actingAs($admin)->post($url, ['reason' => str_repeat('a', 2001)])->assertSessionHasErrors('reason');
        $this->assertFalse($member->fresh()->isSuspended());
        $this->assertSame(0, AuditEvent::count());
        $this->assertSame(0, $member->notifications()->count());

        $errors = session('errors')->get('reason');
        $this->assertSame(__('moderation.errors.reason_max', ['max' => 2000]), $errors[0]);
    }

    public function test_the_end_date_must_be_a_valid_future_date(): void
    {
        $admin = $this->member('admin');
        $member = $this->member('contributor');
        $url = "/validate/accounts/{$member->id}/suspension";

        $this->actingAs($admin)->post($url, ['reason' => self::REASON, 'until' => now()->toDateString()])->assertSessionHasErrors('until');
        $this->actingAs($admin)->post($url, ['reason' => self::REASON, 'until' => now()->subDay()->toDateString()])->assertSessionHasErrors('until');
        $this->actingAs($admin)->post($url, ['reason' => self::REASON, 'until' => '24/12/2030'])->assertSessionHasErrors('until');
        $this->actingAs($admin)->post($url, ['reason' => self::REASON, 'until' => 'demain'])->assertSessionHasErrors('until');
        $this->assertFalse($member->fresh()->isSuspended());

        $this->actingAs($admin)->post($url, ['reason' => self::REASON, 'until' => now()->addDay()->toDateString()])->assertSessionHasNoErrors();
        $this->assertTrue($member->fresh()->isSuspended());
    }

    public function test_an_admin_cannot_suspend_themselves(): void
    {
        $admin = $this->member('admin');
        $this->member('admin');

        $this->actingAs($admin)->post("/validate/accounts/{$admin->id}/suspension", ['reason' => self::REASON])
            ->assertForbidden()
            ->assertInertia(fn (Assert $page) => $page->component('Error')->where('detail', __('moderation.errors.self')));

        $this->assertFalse($admin->fresh()->isSuspended());
        $this->assertSame(0, AuditEvent::count());
    }

    public function test_a_suspended_admin_cannot_suspend_the_remaining_admin(): void
    {
        $active = $this->member('admin');
        $suspended = $this->member('admin', ['suspended_at' => now()->subHour()]);

        $this->actingAs($suspended)->post("/validate/accounts/{$active->id}/suspension", ['reason' => self::REASON])->assertForbidden();

        $this->assertFalse($active->fresh()->isSuspended());
    }

    public function test_only_admins_can_post_a_suspension(): void
    {
        $member = $this->member('contributor');
        $url = "/validate/accounts/{$member->id}/suspension";

        $this->post($url, ['reason' => self::REASON])->assertRedirect('/login');
        foreach (['contributor', 'validator'] as $role) {
            $this->actingAs($this->member($role))->post($url, ['reason' => self::REASON])->assertForbidden();
            $this->actingAs($this->member($role))->delete($url)->assertForbidden();
        }
        $this->assertFalse($member->fresh()->isSuspended());
    }

    public function test_an_unknown_or_non_numeric_account_is_a_404(): void
    {
        $admin = $this->member('admin');

        $this->actingAs($admin)->post('/validate/accounts/999999/suspension', ['reason' => self::REASON])->assertNotFound();
        $this->actingAs($admin)->post('/validate/accounts/abc/suspension', ['reason' => self::REASON])->assertNotFound();
        $this->actingAs($admin)->delete('/validate/accounts/999999/suspension')->assertNotFound();
    }

    public function test_an_already_suspended_account_is_not_suspended_twice(): void
    {
        $admin = $this->member('admin');
        $member = $this->suspendedMember();

        $this->actingAs($admin)->post("/validate/accounts/{$member->id}/suspension", ['reason' => 'Un autre motif tout aussi précis.'])
            ->assertSessionHas('error', __('moderation.errors.already_suspended'));

        $this->assertSame(self::REASON, $member->fresh()->suspension_reason);
        $this->assertSame(0, AuditEvent::count());
    }

    public function test_an_admin_lifts_a_suspension(): void
    {
        $admin = $this->member('admin');
        $member = $this->suspendedMember(['name' => 'Camille Durand']);

        $this->actingAs($admin)->delete("/validate/accounts/{$member->id}/suspension")
            ->assertSessionHas('success', fn (string $m) => str_contains($m, 'Camille Durand'));

        $this->assertFalse($member->fresh()->isSuspended());
        $this->assertSame(AuditAction::AccountSuspensionLifted, AuditEvent::sole()->action);
        $this->assertSame(NotificationKind::AccountSuspensionLifted->value, $member->notifications()->sole()->data['kind']);
    }

    public function test_lifting_an_account_that_is_not_suspended_shows_an_error(): void
    {
        $admin = $this->member('admin');
        $member = $this->member('contributor');

        $this->actingAs($admin)->delete("/validate/accounts/{$member->id}/suspension")
            ->assertSessionHas('error', __('moderation.errors.not_suspended'));
        $this->assertSame(0, AuditEvent::count());
    }

    public function test_a_suspension_by_an_admin_suspended_meanwhile_is_refused(): void
    {
        $admin = $this->member('admin', ['suspended_at' => now()->subMinute()]);
        $this->member('admin');
        $member = $this->member('contributor');

        $this->actingAs($admin)->post("/validate/accounts/{$member->id}/suspension", ['reason' => self::REASON])->assertForbidden();
        $this->assertFalse($member->fresh()->isSuspended());
    }

    // ---- Suppression du compte ----------------------------------------------------------------

    public function test_deleting_a_suspended_account_removes_its_id_from_the_audit_entries(): void
    {
        $admin = $this->member('admin');
        $member = $this->member('contributor', ['password' => 'secret-password']);
        $other = $this->member('contributor');
        app(AccountSuspension::class)->suspend($member, $admin, self::REASON, null);
        app(AccountSuspension::class)->suspend($other, $admin, self::REASON, null);
        app(AccountSuspension::class)->lift($member, $admin);

        $this->actingAs($member)->delete('/profile', ['password' => 'secret-password'])->assertRedirect('/');

        $this->assertModelMissing($member);
        $contexts = AuditEvent::query()
            ->whereIn('action', [AuditAction::AccountSuspended->value, AuditAction::AccountSuspensionLifted->value])
            ->orderBy('id')->get()->map(fn (AuditEvent $e) => $e->context['account_id'] ?? null)->all();
        $this->assertSame([null, $other->id, null], $contexts);
    }

    public function test_an_administrator_who_suspended_someone_can_delete_their_account(): void
    {
        $admin = $this->member('admin', ['password' => 'secret-password']);
        $this->member('admin');
        $member = $this->member('contributor');
        app(AccountSuspension::class)->suspend($member, $admin, self::REASON, null);

        $this->actingAs($admin)->delete('/profile', ['password' => 'secret-password'])->assertRedirect('/');

        $this->assertModelMissing($admin);
        $this->assertTrue($member->fresh()->isSuspended(), 'la suspension reste');
        $this->assertNull($member->fresh()->suspended_by);
    }
}

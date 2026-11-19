<?php

namespace Tests\Feature\Account;

use App\Enums\AuditAction;
use App\Enums\ContributionStatus;
use App\Models\AuditEvent;
use App\Models\Contribution;
use App\Models\User;
use App\Notifications\InactiveAccountWarning;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * « Un compte inutilisé pendant 3 ans est supprimé, après un avertissement envoyé par courriel
 * un mois avant » (politique de confidentialité) : suivi des visites, avertissement, suppression.
 */
class InactiveAccountsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 19)->setTime(5, 15));
    }

    private function memberLastSeen(?string $lastSeen, array $attributes = []): User
    {
        $user = User::factory()->create($attributes + ['created_at' => '2020-01-01 10:00:00']);
        $user->forceFill(['last_seen_at' => $lastSeen])->saveQuietly();

        return $user->fresh();
    }

    public function test_a_visit_records_the_day_once_and_cancels_a_warning(): void
    {
        $user = $this->memberLastSeen('2024-01-01 08:00:00', ['inactivity_warned_at' => null]);
        $user->forceFill(['inactivity_warned_at' => '2026-11-01 05:15:00'])->saveQuietly();
        $updatedAt = $user->fresh()->updated_at;

        $this->actingAs($user)->get('/profile')->assertOk();

        $user->refresh();
        $this->assertTrue($user->last_seen_at->isToday());
        $this->assertNull($user->inactivity_warned_at);
        $this->assertEquals($updatedAt, $user->updated_at, 'Une visite ne modifie pas le profil');

        // Deuxième visite le même jour : pas de nouvelle écriture.
        $this->travel(2)->hours();
        $this->actingAs($user)->get('/profile');
        $this->assertSame('2026-11-19 05:15:00', $user->fresh()->last_seen_at->format('Y-m-d H:i:s'));
    }

    public function test_login_counts_as_a_visit(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);
        $user->forceFill(['last_seen_at' => null])->saveQuietly();

        $this->post('/login', ['email' => $user->email, 'password' => 'secret-password'])->assertRedirect();

        $this->assertTrue($user->fresh()->last_seen_at->isToday());
    }

    public function test_dry_run_counts_without_sending_or_deleting(): void
    {
        Notification::fake();
        $this->memberLastSeen('2023-11-01 00:00:00');

        $this->artisan('vff:prune-inactive-accounts', ['--dry-run' => true])
            ->expectsOutputToContain('0 compte(s) à supprimer, 1 compte(s) à avertir')
            ->assertSuccessful();

        Notification::assertNothingSent();
        $this->assertSame(0, AuditEvent::count());
    }

    public function test_accounts_reaching_three_years_within_a_month_are_warned(): void
    {
        Notification::fake();
        $due = $this->memberLastSeen('2023-12-15 00:00:00');      // 3 ans le 15/12/2026
        $notYet = $this->memberLastSeen('2024-01-01 00:00:00');   // 3 ans le 01/01/2027
        $neverSeenOld = $this->memberLastSeen(null);               // créé en 2020, jamais revu
        $recent = $this->memberLastSeen('2026-11-01 00:00:00');

        $this->artisan('vff:prune-inactive-accounts')
            ->expectsOutputToContain('0 compte(s) supprimé(s), 2 averti(s)')
            ->assertSuccessful();

        Notification::assertSentTo($due, InactiveAccountWarning::class,
            fn (InactiveAccountWarning $n) => $n->deletionDate->format('Y-m-d') === '2026-12-19');
        Notification::assertSentTo($neverSeenOld, InactiveAccountWarning::class);
        Notification::assertNotSentTo([$notYet, $recent], InactiveAccountWarning::class);
        $this->assertNotNull($due->fresh()->inactivity_warned_at);
        $this->assertNull($notYet->fresh()->inactivity_warned_at);

        $event = AuditEvent::sole();
        $this->assertSame(AuditAction::InactivityWarningsSent, $event->action);
        $this->assertEquals(['warned' => 2, 'failed' => 0], $event->context, 'Contexte du journal (ordre des clés jsonb indifférent)');

        // Un second passage n'avertit pas deux fois.
        $this->artisan('vff:prune-inactive-accounts')->expectsOutputToContain('0 averti(s)');
        Notification::assertSentToTimes($due, InactiveAccountWarning::class, 1);
    }

    public function test_warned_accounts_are_deleted_one_month_later_unless_the_member_came_back(): void
    {
        Notification::fake();
        $gone = $this->memberLastSeen('2023-10-01 00:00:00');
        $back = $this->memberLastSeen('2023-10-01 00:00:00');
        $published = Contribution::factory()->for($gone)->create(['status' => ContributionStatus::Applied->value]);

        $this->artisan('vff:prune-inactive-accounts')->assertSuccessful();
        $this->assertNotNull($gone->fresh()->inactivity_warned_at);

        // Trois semaines plus tard : rien n'est supprimé, le second membre revient.
        $this->travel(21)->days();
        $this->actingAs($back)->get('/profile');
        $this->artisan('vff:prune-inactive-accounts')->expectsOutputToContain('0 compte(s) supprimé(s)');

        // Un mois après l'avertissement : seul le compte resté inutilisé disparaît.
        $this->travel(10)->days();
        $this->artisan('vff:prune-inactive-accounts')->expectsOutputToContain('1 compte(s) supprimé(s)');

        $this->assertModelMissing($gone);
        $this->assertModelExists($back);
        $this->assertNull($published->fresh()->user_id);
        $deleted = AuditEvent::where('action', AuditAction::AccountDeleted)->sole();
        $this->assertSame('inactivity', $deleted->context['reason']);
    }

    public function test_a_failed_email_does_not_start_the_notice_period(): void
    {
        // Serveur SMTP injoignable (port fermé) : l'envoi lève une exception.
        config([
            'mail.default' => 'unreachable',
            'mail.mailers.unreachable' => ['transport' => 'smtp', 'host' => '127.0.0.1', 'port' => 1, 'timeout' => 2],
        ]);
        $user = $this->memberLastSeen('2023-10-01 00:00:00');

        $this->artisan('vff:prune-inactive-accounts')
            ->expectsOutputToContain('1 avertissement(s) non envoyé(s)')
            ->assertFailed();

        $this->assertNull($user->fresh()->inactivity_warned_at);
        $this->assertEquals(['warned' => 0, 'failed' => 1], AuditEvent::sole()->context, 'Contexte du journal (ordre des clés jsonb indifférent)');
    }

    public function test_warning_email_is_written_in_the_member_language(): void
    {
        $user = User::factory()->make(['name' => 'Hans', 'locale_preference' => 'de']);
        $notification = new InactiveAccountWarning(now()->addMonth());

        // Laravel passe dans la langue du membre (preferredLocale) avant d'appeler toMail.
        $this->app->setLocale('de');
        $mail = $notification->toMail($user);

        $this->assertSame('Ihr Konto bei Französische Bahnstrecken wird am 19. Dezember 2026 gelöscht', $mail->subject);
        $this->assertSame('Guten Tag Hans,', $mail->greeting);
        $this->assertSame(route('login'), $mail->actionUrl);
        $this->assertSame('de', $user->preferredLocale());
    }

    public function test_the_task_is_scheduled_for_production_only(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('vff:prune-inactive-accounts');

        $event = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains((string) $e->command, 'vff:prune-inactive-accounts'));
        $this->assertNotNull($event);
        $this->assertSame(['production'], $event->environments);
        $this->assertFalse($event->runsInEnvironment('local'));
    }
}

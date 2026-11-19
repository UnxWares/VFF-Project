<?php

namespace Tests\Feature\Webhooks;

use App\Enums\AccountDeletionReason;
use App\Enums\AuditAction;
use App\Enums\ContributionStatus;
use App\Enums\NotificationKind;
use App\Enums\WebhookDeliveryStatus;
use App\Enums\WebhookEvent;
use App\Events\CurrentEraRefreshed;
use App\Http\Controllers\WebhookSubscriptionController;
use App\Jobs\DeliverWebhook;
use App\Models\Accident;
use App\Models\AuditEvent;
use App\Models\Contribution;
use App\Models\Line;
use App\Models\Material;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookSubscription;
use App\Services\Account\AccountDataExport;
use App\Services\Account\AccountDeletion;
use App\Services\Webhooks\HostResolver;
use App\Services\Webhooks\WebhookAttempt;
use App\Services\Webhooks\WebhookDispatcher;
use App\Services\Webhooks\WebhookSender;
use App\Services\Webhooks\WebhookSignature;
use App\Services\Webhooks\WebhookSubscriptionRefused;
use App\Services\Webhooks\WebhookSubscriptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\Support\ActsAsRoles;
use Tests\Support\FakeHostResolver;
use Tests\TestCase;

/**
 * Webhooks de l'API : abonnements depuis le profil (secret montré une fois, nombre maximal, compte
 * suspendu), événements annoncés, livraison signée par la file (essais espacés, désactivation après
 * N échecs avec notification), protection SSRF à chaque envoi, journal, export et suppression du compte.
 * Aucune requête réelle : DNS remplacé (FakeHostResolver) et Http::fake.
 */
class WebhooksTest extends TestCase
{
    use ActsAsRoles;
    use RefreshDatabase;

    private const URL = 'https://hooks.example.org/vff';

    private FakeHostResolver $dns;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withCookie('locale', 'fr');
        config([
            'webhooks.max_per_account' => 2,
            'webhooks.attempts' => 3,
            'webhooks.backoff' => [60, 300],
            'webhooks.disable_after' => 2,
            'webhooks.log_size' => 3,
        ]);
        $this->dns = new FakeHostResolver([
            'hooks.example.org' => ['93.184.215.14'],
            'other.example.org' => ['93.184.215.15'],
            'internal.example.org' => ['10.0.0.8'],
        ]);
        $this->app->instance(HostResolver::class, $this->dns);
        Http::preventStrayRequests();
    }

    /** @param  list<WebhookEvent>|null  $events */
    private function subscription(?User $user = null, ?array $events = null): WebhookSubscription
    {
        return app(WebhookSubscriptions::class)->create(
            $user ?? $this->member('contributor'),
            self::URL,
            $events ?? WebhookEvent::cases(),
        );
    }

    private function delivery(WebhookSubscription $subscription): WebhookDelivery
    {
        return WebhookDelivery::query()->create([
            'webhook_subscription_id' => $subscription->id,
            'event_id' => '0b8e5c7a-1d2f-4e3a-9b6c-5d4e3f2a1b0c',
            'event_type' => WebhookEvent::AccidentPublished->value,
            'payload' => [
                'id' => '0b8e5c7a-1d2f-4e3a-9b6c-5d4e3f2a1b0c',
                'type' => WebhookEvent::AccidentPublished->value,
                'created_at' => '2026-11-19T10:00:00+00:00',
                'data' => ['resource' => 'accident', 'id' => '1933-12-23-lagny', 'url' => 'https://vff-project.org/api/v1/accidents/1933-12-23-lagny'],
            ],
            'status' => WebhookDeliveryStatus::Pending,
        ]);
    }

    private function deliver(WebhookDelivery $delivery): DeliverWebhook
    {
        $job = (new DeliverWebhook($delivery->id))->withFakeQueueInteractions();
        $job->handle(app(WebhookSender::class), app(WebhookSubscriptions::class));

        return $job;
    }

    // ─── Profil : création, secret montré une fois, liste, suppression ──────

    public function test_member_adds_a_subscription_and_sees_the_secret_once(): void
    {
        $user = $this->member('contributor');

        $this->actingAs($user)->post('/profile/webhooks', [
            'webhook_url' => '  HTTPS://Hooks.Example.org/vff  ',
            'webhook_events' => ['contribution.applied', 'accident.published'],
        ])->assertRedirect()->assertSessionHas(WebhookSubscriptionController::FLASH);

        $subscription = WebhookSubscription::sole();
        $this->assertSame($user->id, $subscription->user_id);
        $this->assertSame(self::URL, $subscription->url);
        $this->assertSame(['contribution.applied', 'accident.published'], $subscription->events);

        $secret = session(WebhookSubscriptionController::FLASH)['secret'];
        $this->assertStringStartsWith(WebhookSignature::SECRET_PREFIX, $secret);
        $this->assertSame($secret, $subscription->secret);
        // Chiffré en base, jamais en clair.
        $raw = (string) DB::table('webhook_subscriptions')->value('secret');
        $this->assertStringNotContainsString($secret, $raw);
        $this->assertArrayNotHasKey('secret', $subscription->toArray());

        // Le profil montre le secret pendant la requête qui suit, puis plus jamais.
        $this->actingAs($user)->withSession([WebhookSubscriptionController::FLASH => ['id' => $subscription->id, 'secret' => $secret]])
            ->get('/profile')->assertInertia(fn (Assert $page) => $page
            ->where('webhooks.new_secret.secret', $secret)
            ->where('webhooks.subscriptions.0.url', self::URL)
            ->missing('webhooks.subscriptions.0.secret')
            ->where('webhooks.max', 2)
            ->where('webhooks.can_create', true)
            ->where('webhooks.events', WebhookEvent::values()));
        $this->actingAs($user)->get('/profile')
            ->assertInertia(fn (Assert $page) => $page->where('webhooks.new_secret', null));

        // Journal d'audit : numéro de l'abonnement seulement.
        $audit = AuditEvent::query()->where('action', AuditAction::WebhookCreated)->sole();
        $this->assertSame(['subscription_id' => $subscription->id], $audit->context);
    }

    public function test_events_and_address_are_validated(): void
    {
        $user = $this->member('contributor');

        $this->actingAs($user)->post('/profile/webhooks', ['webhook_url' => self::URL, 'webhook_events' => []])
            ->assertSessionHasErrors(['webhook_events' => 'Choisissez au moins un événement.']);
        $this->actingAs($user)->post('/profile/webhooks', ['webhook_url' => self::URL, 'webhook_events' => ['line.deleted']])
            ->assertSessionHasErrors('webhook_events.0');
        $this->actingAs($user)->post('/profile/webhooks', ['webhook_url' => 'http://hooks.example.org/', 'webhook_events' => ['accident.published']])
            ->assertSessionHasErrors(['webhook_url' => 'L’adresse doit commencer par https://.']);
        $this->actingAs($user)->post('/profile/webhooks', ['webhook_url' => 'https://internal.example.org/', 'webhook_events' => ['accident.published']])
            ->assertSessionHasErrors('webhook_url');
        $this->actingAs($user)->post('/profile/webhooks', ['webhook_url' => 'https://169.254.169.254/', 'webhook_events' => ['accident.published']])
            ->assertSessionHasErrors('webhook_url');

        $this->assertSame(0, WebhookSubscription::count());
    }

    public function test_number_of_subscriptions_is_limited_per_account(): void
    {
        $user = $this->member('contributor');
        $this->subscription($user);
        $this->subscription($user);

        $this->actingAs($user)->post('/profile/webhooks', ['webhook_url' => self::URL, 'webhook_events' => ['accident.published']])
            ->assertSessionHasErrors(['webhook_url' => 'Vous avez déjà 2 abonnements, le nombre maximal.']);
        $this->assertSame(2, WebhookSubscription::count());
        $this->actingAs($user)->get('/profile')->assertInertia(fn (Assert $page) => $page->where('webhooks.can_create', false));
    }

    public function test_suspended_account_cannot_create_nor_reactivate(): void
    {
        $user = $this->member('contributor');
        $subscription = $this->subscription($user);
        $subscription->forceFill(['disabled_at' => now(), 'disabled_reason' => 'failures'])->save();
        $user->forceFill(['suspended_at' => now(), 'suspended_until' => null])->save();

        $this->actingAs($user)->post('/profile/webhooks', ['webhook_url' => self::URL, 'webhook_events' => ['accident.published']]);
        $this->actingAs($user)->post("/profile/webhooks/{$subscription->id}/enable");
        $this->assertSame(1, WebhookSubscription::count());
        $this->assertNotNull($subscription->fresh()->disabled_at);

        $this->expectException(WebhookSubscriptionRefused::class);
        app(WebhookSubscriptions::class)->create($user->fresh(), self::URL, [WebhookEvent::AccidentPublished]);
    }

    public function test_member_deletes_and_reactivates_only_their_own_subscriptions(): void
    {
        $user = $this->member('contributor');
        $mine = $this->subscription($user);
        $theirs = $this->subscription($this->member('contributor'));
        $mine->forceFill(['disabled_at' => now(), 'disabled_reason' => 'failures', 'consecutive_failures' => 2])->save();

        $this->actingAs($user)->delete("/profile/webhooks/{$theirs->id}")->assertNotFound();
        $this->actingAs($user)->post("/profile/webhooks/{$theirs->id}/enable")->assertNotFound();

        $this->actingAs($user)->post("/profile/webhooks/{$mine->id}/enable")->assertRedirect();
        $mine->refresh();
        $this->assertTrue($mine->isActive());
        $this->assertSame(0, $mine->consecutive_failures);

        $this->delivery($mine);
        $this->actingAs($user)->delete("/profile/webhooks/{$mine->id}")->assertRedirect();
        $this->assertModelMissing($mine);
        $this->assertSame(0, WebhookDelivery::count());
        $this->assertModelExists($theirs);
        $this->assertSame(1, AuditEvent::query()->where('action', AuditAction::WebhookDeleted)->count());
    }

    // ─── Événements annoncés ──────

    public function test_applied_contribution_is_announced_without_personal_data(): void
    {
        Queue::fake();
        $author = $this->member('contributor', ['name' => 'Jeanne Contributrice', 'email' => 'jeanne@example.org']);
        $subscription = $this->subscription(events: [WebhookEvent::ContributionApplied]);
        $line = Line::factory()->create();
        $contribution = Contribution::factory()->create([
            'user_id' => $author->id,
            'target_type' => Line::class,
            'target_id' => $line->id,
        ]);

        $contribution->update(['status' => ContributionStatus::Approved->value]);
        $this->assertSame(0, WebhookDelivery::count());

        $contribution->update(['status' => ContributionStatus::Applied->value]);
        $delivery = WebhookDelivery::sole();
        $this->assertSame($subscription->id, $delivery->webhook_subscription_id);
        $this->assertSame('contribution.applied', $delivery->payload['type']);
        $this->assertSame([
            'resource' => 'line',
            'id' => $line->slug,
            'url' => route('api.lines.show', ['line' => $line->slug]),
        ], $delivery->orderedPayload()['data']);
        $this->assertSame(['id', 'type', 'created_at', 'data'], array_keys($delivery->orderedPayload()));

        $json = (string) json_encode($delivery->payload);
        foreach (['Jeanne', 'jeanne@example.org', '"contribution_id"', '"user'] as $personal) {
            $this->assertStringNotContainsString($personal, $json);
        }
        Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job) => $job->deliveryId === $delivery->id);
    }

    public function test_new_records_and_era_refresh_are_announced_to_followers_only(): void
    {
        Queue::fake();
        $all = $this->subscription();
        $accidentsOnly = $this->subscription(events: [WebhookEvent::AccidentPublished]);

        $accident = Accident::factory()->create();
        Accident::factory()->create(['kind' => 'person_struck']);
        $material = Material::factory()->create();
        CurrentEraRefreshed::dispatch(2030, '2026-11-19', 3, 1);

        $types = fn (WebhookSubscription $s) => WebhookDelivery::query()->where('webhook_subscription_id', $s->id)->orderBy('id')->pluck('event_type')->all();
        $this->assertSame(['accident.published', 'material.published', 'current_era.refreshed'], $types($all));
        $this->assertSame(['accident.published'], $types($accidentsOnly));

        $payloads = WebhookDelivery::query()->where('webhook_subscription_id', $all->id)->orderBy('id')->get()
            ->map(fn (WebhookDelivery $d): array => $d->orderedPayload());
        $this->assertSame(['resource' => 'accident', 'id' => $accident->slug, 'url' => route('api.accidents.show', ['accident' => $accident->slug])], $payloads[0]['data']);
        $this->assertSame(route('api.materials.show', ['material' => $material->slug]), $payloads[1]['data']['url']);
        $this->assertSame(['resource' => 'era', 'id' => 2030, 'url' => route('api.eras.show', ['year' => 2030]), 'as_of' => '2026-11-19', 'lines_changed' => 3, 'stations_changed' => 1], $payloads[2]['data']);
        // Un même événement porte le même identifiant pour tous les abonnés.
        $this->assertSame(
            WebhookDelivery::query()->where('webhook_subscription_id', $accidentsOnly->id)->value('event_id'),
            WebhookDelivery::query()->where('webhook_subscription_id', $all->id)->orderBy('id')->value('event_id'),
        );
    }

    public function test_disabled_subscriptions_and_suspended_accounts_receive_nothing(): void
    {
        Queue::fake();
        $disabled = $this->subscription();
        $disabled->forceFill(['disabled_at' => now()])->save();
        $suspendedUser = $this->member('contributor');
        $this->subscription($suspendedUser);
        $suspendedUser->forceFill(['suspended_at' => now(), 'suspended_until' => now()->addWeek()])->save();

        Accident::factory()->create();

        $this->assertSame(0, WebhookDelivery::count());
        Queue::assertNothingPushed();
    }

    public function test_the_log_keeps_only_the_latest_deliveries(): void
    {
        Queue::fake();
        $subscription = $this->subscription(events: [WebhookEvent::MaterialPublished]);
        Material::factory()->count(5)->create();

        $this->assertSame(3, $subscription->deliveries()->count());
    }

    public function test_dispatcher_never_breaks_the_caller(): void
    {
        Exceptions::fake();
        $this->subscription();
        Queue::fake();
        WebhookDelivery::creating(fn () => throw new RuntimeException('base indisponible'));

        app(WebhookDispatcher::class)->dispatch(WebhookEvent::MaterialPublished, 'material', 'bb-9200', 'https://vff-project.org/api/v1/materials/bb-9200');

        Exceptions::assertReported(RuntimeException::class);
        Queue::assertNothingPushed();
    }

    // ─── Livraison : signature, essais, désactivation ──────

    public function test_delivery_is_signed_and_recorded(): void
    {
        Http::fake([self::URL => Http::response('ok', 204)]);
        $subscription = $this->subscription();
        $subscription->forceFill(['consecutive_failures' => 1])->save();
        $delivery = $this->delivery($subscription);

        $this->deliver($delivery)->assertNotReleased();

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) use ($subscription, $delivery): bool {
            $body = $request->body();
            $this->assertSame($delivery->orderedPayload(), json_decode($body, true));
            $this->assertStringStartsWith('{"id":"', $body);
            $this->assertTrue(WebhookSignature::verify($subscription->secret, $request->header('VFF-Signature')[0], $body, now()->getTimestamp()));
            // Un corps modifié, un autre secret ou un horodatage trop ancien ne passent pas.
            $this->assertFalse(WebhookSignature::verify($subscription->secret, $request->header('VFF-Signature')[0], $body.' ', now()->getTimestamp()));
            $this->assertFalse(WebhookSignature::verify('whsec_autre', $request->header('VFF-Signature')[0], $body, now()->getTimestamp()));
            $this->assertFalse(WebhookSignature::verify($subscription->secret, $request->header('VFF-Signature')[0], $body, now()->getTimestamp() + 301));

            return $request->method() === 'POST'
                && $request->url() === self::URL
                && $request->header('Content-Type')[0] === 'application/json'
                && $request->header('VFF-Event')[0] === 'accident.published'
                && $request->header('VFF-Delivery')[0] === $delivery->event_id
                && str_starts_with($request->header('User-Agent')[0], 'VFF-Webhooks/');
        });

        $delivery->refresh();
        $this->assertSame(WebhookDeliveryStatus::Succeeded, $delivery->status);
        $this->assertSame(1, $delivery->attempts);
        $this->assertSame(204, $delivery->response_status);
        $this->assertNotNull($delivery->delivered_at);
        $this->assertSame(0, $subscription->fresh()->consecutive_failures);
    }

    public function test_signature_header_format(): void
    {
        $header = WebhookSignature::header('whsec_test', 1763546400, '{"a":1}');

        $this->assertSame('t=1763546400,v1='.hash_hmac('sha256', '1763546400.{"a":1}', 'whsec_test'), $header);
        $this->assertTrue(WebhookSignature::verify('whsec_test', $header, '{"a":1}', 1763546400 + 299));
        $this->assertFalse(WebhookSignature::verify('whsec_test', $header, '{"a":1}', 1763546400 - 301));
        $this->assertFalse(WebhookSignature::verify('whsec_test', 'v1=abc', '{"a":1}', 1763546400));
    }

    public function test_server_errors_are_retried_with_spaced_attempts_then_fail(): void
    {
        Http::fake([self::URL => Http::response('panne', 503)]);
        $subscription = $this->subscription();
        $delivery = $this->delivery($subscription);

        $this->deliver($delivery)->assertReleased(60);
        $this->assertSame(WebhookDeliveryStatus::Pending, $delivery->fresh()->status);
        $this->assertSame(1, $delivery->fresh()->attempts);
        $this->assertSame(503, $delivery->fresh()->response_status);

        $this->deliver($delivery)->assertReleased(300);
        $this->assertSame(0, $subscription->fresh()->consecutive_failures);

        // Troisième et dernier essai : échec définitif, compté pour la désactivation.
        $this->deliver($delivery)->assertNotReleased();
        $delivery->refresh();
        $this->assertSame(WebhookDeliveryStatus::Failed, $delivery->status);
        $this->assertSame(3, $delivery->attempts);
        $this->assertSame(WebhookAttempt::HTTP_STATUS, $delivery->error);
        $this->assertSame(1, $subscription->fresh()->consecutive_failures);
        $this->assertTrue($subscription->fresh()->isActive());
        Http::assertSentCount(3);
    }

    public function test_connection_errors_are_retried(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));
        $delivery = $this->delivery($this->subscription());

        $this->deliver($delivery)->assertReleased(60);
        $this->assertSame(WebhookAttempt::TIMEOUT, $delivery->fresh()->error);
    }

    public function test_client_errors_and_redirects_fail_without_retry(): void
    {
        Http::fake([
            self::URL => Http::response('', 404),
            'https://other.example.org/*' => Http::response('', 302, ['Location' => 'https://169.254.169.254/']),
        ]);
        $user = $this->member('contributor');
        $notFound = $this->delivery($this->subscription($user));
        $redirected = $this->delivery(app(WebhookSubscriptions::class)->create($user, 'https://other.example.org/hook', WebhookEvent::cases()));

        $this->deliver($notFound)->assertNotReleased();
        $this->deliver($redirected)->assertNotReleased();

        $this->assertSame(WebhookDeliveryStatus::Failed, $notFound->fresh()->status);
        $this->assertSame(404, $notFound->fresh()->response_status);
        $this->assertSame(WebhookAttempt::REDIRECT, $redirected->fresh()->error);
        // La redirection n'est jamais suivie.
        Http::assertSentCount(2);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '169.254'));
    }

    public function test_address_is_checked_again_before_each_send(): void
    {
        Http::fake();
        $delivery = $this->delivery($this->subscription());
        // Le nom mène désormais vers un réseau privé (DNS rebinding).
        $this->dns->answers['hooks.example.org'] = ['93.184.215.14', '192.168.0.10'];

        $this->deliver($delivery)->assertNotReleased();

        Http::assertNothingSent();
        $this->assertSame(WebhookAttempt::ADDRESS_REFUSED, $delivery->fresh()->error);
        $this->assertSame(WebhookDeliveryStatus::Failed, $delivery->fresh()->status);
        $this->assertSame(2, $this->dns->lookups['hooks.example.org']);
    }

    public function test_subscription_is_disabled_after_consecutive_failures_and_member_notified(): void
    {
        Http::fake([self::URL => Http::response('', 410)]);
        $user = $this->member('contributor');
        $subscription = $this->subscription($user);

        $this->deliver($this->delivery($subscription));
        $this->assertTrue($subscription->fresh()->isActive());
        $this->assertSame(0, $user->notifications()->count());

        $this->deliver($this->delivery($subscription));
        $subscription->refresh();
        $this->assertFalse($subscription->isActive());
        $this->assertSame(WebhookSubscription::DISABLED_FAILURES, $subscription->disabled_reason);
        $this->assertSame(2, $subscription->consecutive_failures);

        $notification = $user->notifications()->sole();
        $this->assertSame(NotificationKind::WebhookDisabled->value, $notification->data['kind']);
        $this->assertStringContainsString(self::URL, $notification->data['body']);
        $this->assertSame('/profile#webhooks-title', $notification->data['url']);

        // Désactivé : la livraison suivante n'est pas envoyée, et le membre n'est pas averti deux fois.
        $this->deliver($next = $this->delivery($subscription));
        $this->assertSame(WebhookDeliveryStatus::Skipped, $next->fresh()->status);
        Http::assertSentCount(2);
        $this->assertSame(1, $user->notifications()->count());

        $this->actingAs($user)->get('/notifications/'.$notification->id)->assertRedirect('/profile#webhooks-title');
    }

    public function test_nothing_is_sent_for_a_suspended_account(): void
    {
        Http::fake();
        $user = $this->member('contributor');
        $delivery = $this->delivery($this->subscription($user));
        $user->forceFill(['suspended_at' => now(), 'suspended_until' => null])->save();

        $this->deliver($delivery);

        Http::assertNothingSent();
        $this->assertSame(WebhookDeliveryStatus::Skipped, $delivery->fresh()->status);
    }

    public function test_profile_shows_the_latest_deliveries(): void
    {
        Http::fake([self::URL => Http::response('', 200)]);
        $user = $this->member('contributor');
        $subscription = $this->subscription($user);
        $this->deliver($this->delivery($subscription));

        $this->actingAs($user)->get('/profile')->assertInertia(fn (Assert $page) => $page
            ->where('webhooks.subscriptions.0.deliveries.0.status', 'succeeded')
            ->where('webhooks.subscriptions.0.deliveries.0.event_type', 'accident.published')
            ->where('webhooks.subscriptions.0.deliveries.0.response_status', 200)
            ->where('webhooks.subscriptions.0.deliveries.0.attempts', 1));
    }

    // ─── Données personnelles : export et suppression du compte ──────

    public function test_export_lists_subscriptions_without_the_secret(): void
    {
        Http::fake([self::URL => Http::response('', 200)]);
        $user = $this->member('contributor');
        $subscription = $this->subscription($user, [WebhookEvent::AccidentPublished]);
        $this->deliver($this->delivery($subscription));

        $export = app(AccountDataExport::class)->for($user);

        $this->assertSame(AccountDataExport::FORMAT_VERSION, $export['version']);
        $this->assertSame(self::URL, $export['webhooks'][0]['url']);
        $this->assertSame(['accident.published'], $export['webhooks'][0]['events']);
        $this->assertSame('succeeded', $export['webhooks'][0]['deliveries'][0]['status']);
        $this->assertArrayNotHasKey('secret', $export['webhooks'][0]);
        $this->assertStringNotContainsString($subscription->secret, (string) json_encode($export));
    }

    public function test_subscriptions_and_log_are_deleted_with_the_account(): void
    {
        $user = $this->member('contributor');
        $this->delivery($this->subscription($user));
        $other = $this->subscription($this->member('contributor'));

        app(AccountDeletion::class)->delete($user, AccountDeletionReason::Owner);

        $this->assertSame([$other->id], WebhookSubscription::query()->pluck('id')->all());
        $this->assertSame(0, WebhookDelivery::count());
    }
}

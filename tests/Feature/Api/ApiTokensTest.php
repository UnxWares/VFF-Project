<?php

namespace Tests\Feature\Api;

use App\Enums\AccountDeletionReason;
use App\Enums\AuditAction;
use App\Http\Controllers\ApiTokenController;
use App\Models\AuditEvent;
use App\Models\User;
use App\Services\Account\AccountDataExport;
use App\Services\Account\AccountDeletion;
use App\Services\Api\ApiTokenRefused;
use App\Services\Api\ApiTokens;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\ActsAsRoles;
use Tests\TestCase;

/**
 * Jetons d'API personnels (Sanctum, lecture seule) et quotas par profil : création et révocation
 * depuis le profil, secret montré une fois, nombre maximal, journal d'audit, compte suspendu,
 * limites anonyme / membre / partenaire communes à REST, GraphQL et MCP, en-têtes de limite.
 */
class ApiTokensTest extends TestCase
{
    use ActsAsRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withCookie('locale', 'fr');
        config([
            'api.rate_limits.anonymous' => 300,
            'api.rate_limits.member' => 1200,
            'api.rate_limits.partner' => 6000,
            'api.tokens.max_per_account' => 3,
        ]);
    }

    private function plainToken(User $user, string $name = 'Script'): string
    {
        return app(ApiTokens::class)->create($user, $name)->plainTextToken;
    }

    /**
     * @param  array<string, string>  $headers
     * @return TestResponse<Response>
     */
    private function mcpList(array $headers = []): TestResponse
    {
        return $this->withHeaders([
            'Accept' => 'application/json, text/event-stream',
            'MCP-Protocol-Version' => '2025-06-18',
            ...$headers,
        ])->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list']);
    }

    // ─── Profil : création, secret montré une fois, liste, révocation ──────

    public function test_member_creates_a_read_only_token_shown_once(): void
    {
        $user = $this->member('contributor');

        $this->actingAs($user)->post('/profile/api-tokens', ['token_name' => '  Mon script  '])
            ->assertRedirect()
            ->assertSessionHas(ApiTokenController::FLASH);

        $token = PersonalAccessToken::sole();
        $this->assertSame('Mon script', $token->name);
        $this->assertSame(['read'], $token->abilities);
        $this->assertSame($user->id, (int) $token->tokenable_id);

        $plain = session(ApiTokenController::FLASH)['plain'];
        $this->assertStringStartsWith($token->id.'|vff_', $plain);
        // Seule l'empreinte est en base, jamais le secret.
        $this->assertSame(hash('sha256', explode('|', $plain, 2)[1]), $token->token);

        // Première page du profil après la création : le secret, une fois.
        $this->actingAs($user)->get('/profile')->assertInertia(fn (Assert $page) => $page
            ->where('api_access.new_token.plain', $plain)
            ->where('api_access.tokens.0.name', 'Mon script')
            ->where('api_access.tokens.0.last_used_at', null)
            ->missing('api_access.tokens.0.token')
            ->where('api_access.profile', 'member')
            ->where('api_access.per_minute', 1200)
            ->where('api_access.max_tokens', 3)
            ->where('api_access.can_create', true));

        // Page suivante : plus de secret.
        $this->actingAs($user)->get('/profile')->assertInertia(fn (Assert $page) => $page
            ->where('api_access.new_token', null)
            ->has('api_access.tokens', 1));
    }

    public function test_token_name_is_required_and_bounded(): void
    {
        $user = $this->member('contributor');

        $this->actingAs($user)->post('/profile/api-tokens', ['token_name' => '   '])
            ->assertSessionHasErrors(['token_name' => __('api_tokens.errors.name_required')]);
        $this->actingAs($user)->post('/profile/api-tokens', ['token_name' => str_repeat('a', 61)])
            ->assertSessionHasErrors('token_name');
        $this->assertSame(0, PersonalAccessToken::count());
    }

    public function test_guest_cannot_create_a_token(): void
    {
        $this->post('/profile/api-tokens', ['token_name' => 'x'])->assertRedirect('/login');
        $this->assertSame(0, PersonalAccessToken::count());
    }

    public function test_number_of_tokens_per_account_is_limited(): void
    {
        $user = $this->member('contributor');
        foreach (range(1, 3) as $i) {
            $this->actingAs($user)->post('/profile/api-tokens', ['token_name' => "Jeton $i"])->assertSessionHasNoErrors();
        }

        $this->actingAs($user)->post('/profile/api-tokens', ['token_name' => 'De trop'])
            ->assertSessionHasErrors(['token_name' => trans_choice('api_tokens.errors.too_many', 3, ['max' => 3])]);
        $this->assertSame(3, $user->tokens()->count());

        $this->actingAs($user)->get('/profile')->assertInertia(fn (Assert $page) => $page
            ->where('api_access.can_create', false)
            ->has('api_access.tokens', 3));
    }

    public function test_creation_and_revocation_are_audited_without_name_or_secret(): void
    {
        $user = $this->member('contributor');
        $this->actingAs($user)->post('/profile/api-tokens', ['token_name' => 'Nom privé']);
        $id = PersonalAccessToken::sole()->id;

        $this->actingAs($user)->delete("/profile/api-tokens/{$id}")->assertRedirect()->assertSessionHas('success');
        $this->assertSame(0, PersonalAccessToken::count());

        $events = AuditEvent::query()->orderBy('id')->get();
        $this->assertSame([AuditAction::ApiTokenCreated, AuditAction::ApiTokenRevoked], $events->pluck('action')->all());
        foreach ($events as $event) {
            $this->assertSame($user->id, $event->actor_id);
            $this->assertSame(['token_id' => $id], $event->context);
            $this->assertStringNotContainsString('Nom privé', json_encode($event->toArray()));
        }
    }

    public function test_member_cannot_revoke_another_members_token(): void
    {
        $owner = $this->member('contributor');
        $this->plainToken($owner);
        $id = PersonalAccessToken::sole()->id;

        $this->actingAs($this->member('contributor'))->delete("/profile/api-tokens/{$id}")->assertNotFound();
        $this->assertSame(1, PersonalAccessToken::count());
        $this->assertSame(0, AuditEvent::where('action', AuditAction::ApiTokenRevoked->value)->count());
    }

    public function test_revoked_token_is_refused_by_the_api(): void
    {
        $user = $this->member('contributor');
        $plain = $this->plainToken($user);
        app(ApiTokens::class)->revoke($user, (int) PersonalAccessToken::sole()->id);

        $this->withToken($plain)->getJson('/api/v1/eras')
            ->assertUnauthorized()
            ->assertJsonPath('error', 'invalid_token');
    }

    // ─── Compte suspendu ───────────────────────────────────────────────────

    public function test_suspended_account_cannot_create_a_token_and_its_tokens_are_refused(): void
    {
        $user = $this->member('contributor');
        $plain = $this->plainToken($user);
        $user->forceFill(['suspended_at' => now(), 'suspended_until' => now()->addWeek(), 'suspension_reason' => 'Essai'])->save();

        $this->actingAs($user)->post('/profile/api-tokens', ['token_name' => 'Autre'])->assertForbidden();
        $this->assertSame(1, PersonalAccessToken::count());

        $this->actingAs($user)->get('/profile')->assertInertia(fn (Assert $page) => $page
            ->where('api_access.can_create', false)
            ->where('api_access.suspended', true));

        $this->app['auth']->forgetGuards();
        $this->withToken($plain)->getJson('/api/v1/eras')
            ->assertForbidden()
            ->assertJsonPath('error', 'account_suspended')
            ->assertJsonPath('suspended_until', $user->suspended_until->toIso8601String());
        $this->withToken($plain)->postJson('/graphql', ['query' => '{ eras { year } }'])->assertForbidden();
        $this->mcpList(['Authorization' => "Bearer {$plain}"])->assertForbidden();
        $this->withToken($plain)->getJson('/api/user')->assertForbidden();

        // Le service refuse aussi, sans passer par la route.
        $this->expectException(ApiTokenRefused::class);
        app(ApiTokens::class)->create($user->fresh(), 'Direct');
    }

    public function test_tokens_work_again_when_the_suspension_has_ended(): void
    {
        $user = $this->member('contributor');
        $plain = $this->plainToken($user);
        $user->forceFill(['suspended_at' => now()->subWeek(), 'suspended_until' => now()->subMinute(), 'suspension_reason' => 'Essai'])->save();

        $this->withToken($plain)->getJson('/api/v1/eras')->assertOk()->assertHeader('X-RateLimit-Limit', '1200');
    }

    // ─── Quotas par profil ─────────────────────────────────────────────────

    public function test_api_stays_open_without_token_with_the_anonymous_limit(): void
    {
        $this->getJson('/api/v1/eras')
            ->assertOk()
            ->assertHeader('X-RateLimit-Limit', '300')
            ->assertHeader('X-RateLimit-Remaining', '299');
    }

    public function test_site_session_without_token_keeps_the_anonymous_limit(): void
    {
        $this->actingAs($this->member('contributor', ['api_partner_at' => now()]))
            ->getJson('/api/v1/eras')
            ->assertOk()
            ->assertHeader('X-RateLimit-Limit', '300');
    }

    public function test_member_and_partner_tokens_raise_the_limit(): void
    {
        $member = $this->member('contributor');
        $this->withToken($this->plainToken($member))->getJson('/api/v1/eras')
            ->assertOk()->assertHeader('X-RateLimit-Limit', '1200');

        $partner = $this->member('contributor', ['api_partner_at' => now()]);
        $this->withToken($this->plainToken($partner))->getJson('/api/v1/eras')
            ->assertOk()->assertHeader('X-RateLimit-Limit', '6000');
    }

    public function test_counter_is_per_account_and_shared_by_rest_graphql_and_mcp(): void
    {
        $user = $this->member('contributor');
        $first = $this->plainToken($user, 'Un');
        $second = $this->plainToken($user, 'Deux');

        $this->withToken($first)->getJson('/api/v1/eras')->assertHeader('X-RateLimit-Remaining', '1199');
        $this->withToken($second)->postJson('/graphql', ['query' => '{ eras { year } }'])
            ->assertOk()->assertHeader('X-RateLimit-Remaining', '1198');
        $this->mcpList(['Authorization' => "Bearer {$first}"])
            ->assertOk()->assertHeader('X-RateLimit-Limit', '1200')->assertHeader('X-RateLimit-Remaining', '1197');

        // Le compteur anonyme de la même adresse IP n'a pas bougé.
        $this->withHeaders(['Authorization' => ''])->getJson('/api/v1/eras')->assertHeader('X-RateLimit-Remaining', '299');
    }

    public function test_over_the_limit_the_api_answers_429_with_retry_after(): void
    {
        config(['api.rate_limits.member' => 2]);
        $plain = $this->plainToken($this->member('contributor'));

        $this->withToken($plain)->getJson('/api/v1/eras')->assertOk();
        $this->withToken($plain)->getJson('/api/v1/eras')->assertOk()->assertHeader('X-RateLimit-Remaining', '0');
        $response = $this->withToken($plain)->getJson('/api/v1/eras')
            ->assertStatus(429)
            ->assertHeader('X-RateLimit-Limit', '2')
            ->assertHeader('X-RateLimit-Remaining', '0');
        $this->assertGreaterThan(0, (int) $response->headers->get('Retry-After'));
    }

    public function test_invalid_tokens_count_on_the_anonymous_ip_counter(): void
    {
        config(['api.rate_limits.anonymous' => 2]);

        $this->withToken('1|vff_inconnu')->getJson('/api/v1/eras')
            ->assertUnauthorized()
            ->assertHeader('WWW-Authenticate', 'Bearer error="invalid_token"')
            ->assertHeader('X-RateLimit-Remaining', '1');
        $this->withToken('pas-un-jeton')->getJson('/api/v1/eras')->assertUnauthorized();
        $this->withToken('2|vff_autre')->getJson('/api/v1/eras')
            ->assertStatus(429)
            ->assertJsonPath('message', 'Too Many Attempts.')
            ->assertHeader('Retry-After');

        // Même compteur que l'accès anonyme.
        $this->withHeaders(['Authorization' => ''])->getJson('/api/v1/eras')->assertStatus(429);
    }

    public function test_last_use_is_recorded_at_most_once_a_minute(): void
    {
        $plain = $this->plainToken($this->member('contributor'));
        $this->travelTo(now()->startOfMinute());

        $this->withToken($plain)->getJson('/api/v1/eras')->assertOk();
        $first = PersonalAccessToken::sole()->last_used_at;
        $this->assertNotNull($first);

        $this->travel(20)->seconds();
        $this->withToken($plain)->getJson('/api/v1/eras')->assertOk();
        $this->assertEquals($first, PersonalAccessToken::sole()->last_used_at);

        $this->travel(61)->seconds();
        $this->withToken($plain)->getJson('/api/v1/eras')->assertOk();
        $this->assertTrue(PersonalAccessToken::sole()->last_used_at->gt($first));
    }

    public function test_api_user_route_returns_the_token_account(): void
    {
        $user = $this->member('contributor');
        $this->withToken($this->plainToken($user))->getJson('/api/user')
            ->assertOk()->assertJsonPath('id', $user->id)->assertJsonMissingPath('password');
        $this->app['auth']->forgetGuards();
        $this->withHeaders(['Authorization' => ''])->getJson('/api/user')->assertUnauthorized();
    }

    // ─── Vie privée : export et suppression du compte ──────────────────────

    public function test_export_lists_token_metadata_without_the_secret(): void
    {
        $user = $this->member('contributor');
        $plain = $this->plainToken($user, 'Carte perso');
        $hash = PersonalAccessToken::sole()->token;

        $export = app(AccountDataExport::class)->for($user);
        $json = json_encode($export);

        $this->assertSame('Carte perso', $export['api_tokens'][0]['name']);
        $this->assertSame(['read'], $export['api_tokens'][0]['abilities']);
        $this->assertArrayHasKey('created_at', $export['api_tokens'][0]);
        $this->assertArrayHasKey('last_used_at', $export['api_tokens'][0]);
        $this->assertStringNotContainsString($hash, $json);
        $this->assertStringNotContainsString(explode('|', $plain, 2)[1], $json);
        $this->assertNull($export['profile']['api_partner_since']);
    }

    public function test_tokens_are_deleted_with_the_account(): void
    {
        $user = $this->member('contributor');
        $plain = $this->plainToken($user);
        $this->plainToken($this->member('contributor'));

        app(AccountDeletion::class)->delete($user, AccountDeletionReason::Owner);

        $this->assertSame(1, PersonalAccessToken::count());
        $this->withToken($plain)->getJson('/api/v1/eras')->assertUnauthorized();
    }

    // ─── Profil partenaire (console) ───────────────────────────────────────

    public function test_partner_profile_is_granted_and_revoked_by_command(): void
    {
        $user = $this->member('contributor', ['email' => 'Partenaire@Exemple.fr']);

        $this->artisan('vff:api-partner', ['account' => 'partenaire@exemple.fr'])->assertSuccessful();
        $this->assertTrue($user->fresh()->isApiPartner());
        $this->artisan('vff:api-partner', ['--list' => true])->expectsOutputToContain((string) $user->id)->assertSuccessful();

        $this->artisan('vff:api-partner', ['account' => (string) $user->id, '--revoke' => true])->assertSuccessful();
        $this->assertFalse($user->fresh()->isApiPartner());

        $this->assertSame(
            [AuditAction::ApiPartnerGranted, AuditAction::ApiPartnerRevoked],
            AuditEvent::query()->orderBy('id')->pluck('action')->all(),
        );
        $this->assertSame(['account_id' => $user->id], AuditEvent::query()->first()->context);

        $this->artisan('vff:api-partner', ['account' => 'personne@exemple.fr'])->assertFailed();
        $this->artisan('vff:api-partner')->assertExitCode(2);
    }

    public function test_partner_profile_is_not_mass_assignable(): void
    {
        $user = $this->member('contributor');
        $this->actingAs($user)->put('/profile', [
            'name' => $user->name, 'email' => $user->email, 'api_partner_at' => now()->toIso8601String(),
        ]);
        $this->assertFalse($user->fresh()->isApiPartner());
    }

    // ─── Documentation ─────────────────────────────────────────────────────

    public function test_api_page_shows_the_configured_limits(): void
    {
        $this->get('/api')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('ApiDocs')
            ->where('rate_limits', ['anonymous' => 300, 'member' => 1200, 'partner' => 6000]));
    }

    public function test_openapi_documents_optional_bearer_and_limit_responses(): void
    {
        $spec = $this->getJson('/docs/api.json')->assertOk()->json();

        $this->assertSame('bearer', $spec['components']['securitySchemes']['bearer']['scheme']);
        $this->assertEquals([[], ['bearer' => []]], $spec['security']);
        $this->assertStringContainsString('## Authentification et quotas', $spec['info']['description']);
        $this->assertStringContainsString('1 200 requêtes par minute', $spec['info']['description']);
        $this->assertStringNotContainsString('{limit.', $spec['info']['description']);

        foreach ($spec['paths'] as $path => $operations) {
            foreach ($operations as $method => $operation) {
                foreach (['401', '403', '429'] as $code) {
                    $this->assertArrayHasKey($code, $operation['responses'], "{$method} {$path} : réponse {$code}");
                }
                $this->assertArrayHasKey('Retry-After', $operation['responses']['429']['headers'], "{$method} {$path}");
                $this->assertArrayHasKey('X-RateLimit-Limit', $operation['responses']['200']['headers'] ?? ['X-RateLimit-Limit' => 1], "{$method} {$path}");
            }
        }
    }
}

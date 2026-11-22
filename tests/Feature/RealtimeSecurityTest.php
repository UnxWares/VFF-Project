<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Events\ContributionChanged;
use App\GraphQL\Subscriptions\ContributionSubscription;
use App\GraphQL\Subscriptions\SubscriptionRoutes;
use App\Http\Middleware\AuthenticateApiToken;
use App\Http\Middleware\EnsureRealtimeEnabled;
use App\Models\Contribution;
use App\Models\User;
use App\Services\Api\ApiTokens;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Mockery\MockInterface;
use Nuwave\Lighthouse\Subscriptions\BroadcastDriverManager;
use Nuwave\Lighthouse\Subscriptions\Contracts\StoresSubscriptions;
use Nuwave\Lighthouse\Support\Contracts\CreatesContext;
use Nuwave\Lighthouse\Support\Contracts\SerializesContext;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Nuwave\Lighthouse\Testing\TestsSubscriptions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Revue de sécurité du temps réel (storage/app/reports/agents/aprem-2111/revue-temps-reel.md) :
 * rien d'ouvert tant qu'il est désactivé, autorisation des canaux privés, aucune donnée personnelle
 * dans les canaux ni dans le contexte des abonnés gardé dans Valkey, limites des abonnements GraphQL,
 * configuration de Reverb et de nginx.
 */
class RealtimeSecurityTest extends TestCase
{
    use MakesGraphQLRequests;
    use RefreshDatabase;
    use TestsSubscriptions;

    private const SECRET = 'secret-du-serveur-reverb';

    private function enable(): void
    {
        config([
            'realtime.enabled' => true,
            'realtime.client.key' => 'cle-publique',
            'realtime.client.host' => 'temps-reel.vff.test',
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'cle-publique',
            'broadcasting.connections.reverb.secret' => self::SECRET,
            'broadcasting.connections.reverb.app_id' => 'vff',
            'broadcasting.connections.reverb.options.host' => '127.0.0.1',
        ]);
        $manager = app(BroadcastManager::class);
        $manager->purge();
        $manager->setDefaultDriver('reverb');
        require base_path('routes/channels.php');
        Event::fake([ContributionChanged::class]);
    }

    private function token(User $user): string
    {
        return app(ApiTokens::class)->create($user, 'Script')->plainTextToken;
    }

    /** @return list<string> champs des abonnements que le diffuseur espion a reçus */
    private function broadcastFields(): array
    {
        /** @var MockInterface $broadcaster */
        $broadcaster = app(BroadcastDriverManager::class)->driver('mock');
        $fields = [];
        try {
            $broadcaster->shouldHaveReceived('broadcast')->withArgs(function ($subscriber) use (&$fields): bool {
                $fields[] = $subscriber->fieldName;

                return true;
            });
        } catch (\Throwable) {
            // Aucun appel.
        }

        return $fields;
    }

    // 1. Désactivé : rien d'ouvert.

    #[Test]
    public function desactive_les_deux_routes_d_autorisation_sont_introuvables(): void
    {
        $validator = User::factory()->validator()->create();
        $body = ['channel_name' => 'private-lighthouse-abc-1', 'socket_id' => '1234.5678'];

        $this->actingAs($validator)->post('/broadcasting/auth', ['channel_name' => 'private-review-queue', 'socket_id' => '1234.5678'])->assertNotFound();
        $this->postJson('/graphql/subscriptions/auth', $body)->assertNotFound();
    }

    #[Test]
    public function desactive_avec_reverb_configure_rien_ne_part(): void
    {
        config(['realtime.enabled' => false, 'broadcasting.default' => 'reverb']);
        Event::fake([ContributionChanged::class]);

        $this->graphQL('subscription { contributionApplied { event } }')
            ->assertGraphQLErrorMessage('Unauthorized subscription request');
        $contribution = Contribution::factory()->create(['type' => 'line_segment']);
        $contribution->update(['status' => 'applied']);

        Event::assertNotDispatched(ContributionChanged::class);
        $this->assertSame([], $this->broadcastFields());
    }

    #[Test]
    public function desactive_la_csp_des_pages_n_admet_aucun_websocket_externe(): void
    {
        $response = $this->get('/');
        $csp = (string) ($response->headers->get('Content-Security-Policy') ?? $response->headers->get('Content-Security-Policy-Report-Only'));

        $this->assertNotSame('', $csp);
        $this->assertStringNotContainsString('wss://', $csp);
    }

    // 2. Autorisation des canaux privés.

    /** @return iterable<string, array{string}> */
    public static function invalidChannels(): iterable
    {
        yield 'ancien canal du modèle Laravel' => ['private-App.Models.User.{id}'];
        yield 'ancien canal, numéro suivi de lettres' => ['private-App.Models.User.{id}abc'];
        yield 'numéro suivi de lettres' => ['private-users.{id}abc.contributions'];
        yield 'numéro négatif' => ['private-users.-{id}.contributions'];
        yield 'numéro vide' => ['private-users..contributions'];
        yield 'numéro décimal' => ['private-users.{id}.0.contributions'];
        yield 'canal inconnu' => ['private-admin'];
    }

    #[Test]
    #[DataProvider('invalidChannels')]
    public function un_canal_mal_forme_ou_inconnu_est_refuse(string $channel): void
    {
        $this->enable();
        $me = User::factory()->create();

        $this->actingAs($me)
            ->post('/broadcasting/auth', ['channel_name' => str_replace('{id}', (string) $me->id, $channel), 'socket_id' => '1234.5678'])
            ->assertForbidden();
    }

    #[Test]
    public function un_contributeur_n_ecoute_ni_la_file_ni_les_contributions_d_un_autre(): void
    {
        $this->enable();
        $contributor = User::factory()->contributor()->create();
        $other = User::factory()->contributor()->create();

        foreach (['private-review-queue', 'presence-review-queue', "private-users.{$other->id}.contributions", "presence-users.{$other->id}.contributions"] as $channel) {
            $this->actingAs($contributor)
                ->post('/broadcasting/auth', ['channel_name' => $channel, 'socket_id' => '1234.5678'])
                ->assertForbidden();
        }
    }

    #[Test]
    public function la_signature_d_un_canal_ne_contient_pas_le_secret(): void
    {
        $this->enable();
        $me = User::factory()->create();

        $response = $this->actingAs($me)
            ->post('/broadcasting/auth', ['channel_name' => "private-users.{$me->id}.contributions", 'socket_id' => '1234.5678'])
            ->assertOk();

        $this->assertStringStartsWith('cle-publique:', (string) $response->json('auth'));
        $this->assertStringNotContainsString(self::SECRET, (string) $response->getContent());
    }

    // 2 bis. Abonnements GraphQL : droits vérifiés à chaque diffusion.

    #[Test]
    public function un_validateur_suspendu_ou_retrogradé_ne_recoit_plus_la_file(): void
    {
        $this->enable();
        $suspended = User::factory()->validator()->create();
        $demoted = User::factory()->validator()->create();
        $still = User::factory()->validator()->create();
        foreach ([$suspended, $demoted, $still] as $validator) {
            $this->graphQL('subscription { reviewQueueChanged { event } }', [], [], ['Authorization' => 'Bearer '.$this->token($validator)])
                ->assertJsonStructure(['extensions' => ['lighthouse_subscriptions' => ['channel']]]);
        }
        $suspended->forceFill(['suspended_at' => now()])->save();
        $demoted->forceFill(['role' => UserRole::Contributor->value])->save();

        Contribution::factory()->create(['type' => 'line_segment']);

        $this->assertSame(['reviewQueueChanged'], $this->broadcastFields());
    }

    #[Test]
    public function l_autorisation_du_canal_d_un_abonne_graphql_est_limitee_en_debit(): void
    {
        $route = Route::getRoutes()->getByName('lighthouse.subscriptions.auth');

        $this->assertNotNull($route);
        $middleware = $route->gatherMiddleware();
        $this->assertContains(EnsureRealtimeEnabled::class, $middleware);
        $this->assertContains('throttle:public-api', $middleware);
        $this->assertNull(Route::getRoutes()->getByName('lighthouse.subscriptions.webhook'));

        // Serveur compatible Pusher : pas de webhook sans signature non plus.
        $router = new Router(new Dispatcher);
        (new SubscriptionRoutes)->pusher($router);
        $this->assertSame(['graphql/subscriptions/auth'], array_map(fn ($r) => $r->uri(), $router->getRoutes()->getRoutes()));
    }

    #[Test]
    public function un_canal_d_abonne_inconnu_est_refuse(): void
    {
        $this->enable();

        $this->postJson('/graphql/subscriptions/auth', ['channel_name' => 'private-lighthouse-inconnu-1', 'socket_id' => '1234.5678'])
            ->assertForbidden();
    }

    // 3. Aucune donnée personnelle.

    #[Test]
    public function les_types_des_abonnements_ne_portent_aucune_donnee_personnelle(): void
    {
        $fields = [];
        foreach (['ContributionEvent', 'ContributionEventContribution', 'ContributionEventTarget'] as $type) {
            $names = $this->graphQL("{ __type(name: \"{$type}\") { fields { name } } }")->json('data.__type.fields.*.name');
            $fields[$type] = $names;
        }

        $this->assertSame([
            'ContributionEvent' => ['event', 'contribution', 'target', 'occurred_at'],
            'ContributionEventContribution' => ['id', 'type', 'status', 'approvals', 'approvals_required'],
            'ContributionEventTarget' => ['resource', 'slug', 'url'],
        ], $fields);
    }

    #[Test]
    public function le_contexte_d_un_abonne_ne_garde_ni_cookie_ni_ip_ni_compte_complet(): void
    {
        $user = User::factory()->create(['name' => 'Camille Exemple', 'email' => 'camille@vff.test']);
        $request = Request::create('https://vff.test/graphql', 'POST', [], ['vff_session' => 'valeur-de-session'], [], [
            'HTTP_COOKIE' => 'vff_session=valeur-de-session',
            'HTTP_X_XSRF_TOKEN' => 'jeton-csrf',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.9',
            'REMOTE_ADDR' => '198.51.100.7',
            'HTTP_USER_AGENT' => 'Navigateur-Personnel/1.0',
        ], '{"query":"subscription { myContributionChanged { event } }"}');
        $request->attributes->set(AuthenticateApiToken::ACCOUNT, $user);
        $request->setUserResolver(fn (): User => $user);

        $serialized = app(SerializesContext::class)->serialize(app(CreatesContext::class)->generate($request));

        foreach (['valeur-de-session', 'jeton-csrf', '203.0.113.9', '198.51.100.7', 'Navigateur-Personnel', 'camille@vff.test', 'Camille', (string) $user->password] as $secret) {
            $this->assertStringNotContainsString($secret, $serialized);
        }
        $restored = app(SerializesContext::class)->unserialize($serialized);
        $this->assertSame($user->id, $restored->user()?->getAuthIdentifier());
        $this->assertSame('vff.test', $restored->request()?->getHost());
    }

    #[Test]
    public function un_compte_supprime_n_empeche_pas_la_diffusion_aux_autres_abonnes(): void
    {
        $user = User::factory()->create();
        $request = Request::create('https://vff.test/graphql', 'POST');
        $request->setUserResolver(fn (): User => $user);
        $serialized = app(SerializesContext::class)->serialize(app(CreatesContext::class)->generate($request));
        $user->delete();

        $this->assertNull(app(SerializesContext::class)->unserialize($serialized)->user());
    }

    // 4. Limites des abonnements GraphQL.

    #[Test]
    public function un_abonne_ne_peut_pas_ouvrir_d_abonnements_sans_limite(): void
    {
        $this->enable();
        config(['realtime.subscriptions.max_per_day' => 2]);
        $query = 'subscription { contributionApplied { event } }';

        $this->graphQL($query)->assertJsonStructure(['extensions' => ['lighthouse_subscriptions' => ['channel']]]);
        $this->graphQL($query)->assertJsonStructure(['extensions' => ['lighthouse_subscriptions' => ['channel']]]);
        $this->graphQL($query)
            ->assertJsonPath('errors.0.extensions.code', ContributionSubscription::QUOTA_ERROR)
            ->assertJsonMissingPath('extensions.lighthouse_subscriptions');
        $this->assertCount(2, app(StoresSubscriptions::class)->subscribersByTopic('CONTRIBUTION_APPLIED'));

        // Un compte a son propre quota, séparé de celui de l'adresse IP.
        $this->graphQL($query, [], [], ['Authorization' => 'Bearer '.$this->token(User::factory()->create())])
            ->assertJsonStructure(['extensions' => ['lighthouse_subscriptions' => ['channel']]]);
    }

    #[Test]
    public function un_lot_d_abonnements_au_dela_du_plafond_est_refuse(): void
    {
        $this->enable();
        $operations = array_fill(0, (int) config('api.graphql_max_batch') + 1, ['query' => 'subscription { contributionApplied { event } }']);

        $this->postJson('/graphql', $operations)->assertStatus(400);
        $this->assertSame([], app(StoresSubscriptions::class)->subscribersByTopic('CONTRIBUTION_APPLIED')->all());
    }

    #[Test]
    public function un_abonnement_a_deux_champs_racines_est_refuse(): void
    {
        $this->enable();

        $this->graphQL('subscription { a: contributionApplied { event } b: contributionApplied { event } }')
            ->assertJsonPath('data', null)
            ->assertJsonMissingPath('extensions.lighthouse_subscriptions');
    }

    #[Test]
    public function la_complexite_s_applique_aux_abonnements(): void
    {
        $this->enable();
        // Profondeur : les types des abonnements ne descendent que d'un niveau sous le champ racine
        // (contribution { id }), la limite de 15 ne peut pas être atteinte ; la complexité, elle, compte
        // les alias.
        config(['lighthouse.security.max_query_complexity' => 3]);

        $this->graphQL('subscription { contributionApplied { event occurred_at contribution { id status } } }')
            ->assertJsonMissingPath('extensions.lighthouse_subscriptions')
            ->assertJsonPath('errors.0.extensions.code', 'query_too_complex');
        $this->assertSame([], app(StoresSubscriptions::class)->subscribersByTopic('CONTRIBUTION_APPLIED')->all());
    }

    // 5. Configuration de Reverb et de nginx ; secrets.

    #[Test]
    public function reverb_refuse_les_origines_etrangeres_et_les_messages_entre_navigateurs(): void
    {
        $app = config('reverb.apps.apps.0');

        $this->assertSame('none', $app['accept_client_events_from']);
        $this->assertNotContains('*', $app['allowed_origins']);
        $this->assertSame([parse_url((string) config('app.url'), PHP_URL_HOST)], $app['allowed_origins']);
        $this->assertTrue((bool) $app['rate_limiting']['enabled']);
        $this->assertSame('127.0.0.1', config('reverb.servers.reverb.host'));
    }

    #[Test]
    public function nginx_ne_publie_que_la_connexion_websocket_et_borne_les_connexions(): void
    {
        $snippet = (string) file_get_contents(base_path('deploy/nginx/snippets/vff-reverb.conf'));
        $site = (string) file_get_contents(base_path('deploy/nginx/vff-project.org.conf'));
        $locations = preg_match_all('/^\s*location\s+(.+?)\s*\{/m', $snippet, $m) ? $m[1] : [];

        $this->assertSame(['^~ /app/'], $locations);
        $this->assertStringContainsString('proxy_pass http://127.0.0.1:8080;', $snippet);
        $this->assertMatchesRegularExpression('/^\s*limit_conn\s+vff_websocket\s+\d+;/m', $snippet);
        $this->assertMatchesRegularExpression('/^limit_conn_zone\s+\$binary_remote_addr\s+zone=vff_websocket:/m', $site);
    }

    #[Test]
    public function la_page_de_validation_ne_contient_jamais_le_secret(): void
    {
        $this->enable();

        $html = (string) $this->actingAs(User::factory()->validator()->create())->get('/validate')->assertOk()->getContent();

        $this->assertStringContainsString('cle-publique', $html);
        $this->assertStringNotContainsString(self::SECRET, $html);
    }
}

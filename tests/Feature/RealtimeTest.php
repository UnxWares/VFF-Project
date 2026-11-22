<?php

namespace Tests\Feature;

use App\Enums\ContributionRealtimeEvent;
use App\Enums\ContributionStatus;
use App\Events\ContributionChanged;
use App\Models\Contribution;
use App\Models\Line;
use App\Models\User;
use App\Services\Api\ApiTokens;
use App\Support\Realtime;
use App\Support\Security\ContentSecurityPolicy;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia;
use Mockery\MockInterface;
use Nuwave\Lighthouse\Subscriptions\BroadcastDriverManager;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Nuwave\Lighthouse\Testing\TestsSubscriptions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Temps réel des contributions : désactivé par défaut, diffusion des changements d'état sans donnée
 * personnelle, autorisation des canaux privés et abonnements GraphQL.
 */
class RealtimeTest extends TestCase
{
    use MakesGraphQLRequests;
    use RefreshDatabase;
    use TestsSubscriptions;

    private function enable(): void
    {
        config([
            'realtime.enabled' => true,
            'realtime.client.key' => 'cle-publique',
            'realtime.client.host' => 'temps-reel.vff.test',
            'realtime.client.port' => 443,
            'realtime.client.scheme' => 'https',
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'cle-publique',
            'broadcasting.connections.reverb.secret' => 'secret-du-serveur',
            'broadcasting.connections.reverb.app_id' => 'vff',
            'broadcasting.connections.reverb.options.host' => '127.0.0.1',
        ]);
        // Les canaux sont enregistrés au démarrage sur le diffuseur par défaut (null en test) : on les
        // enregistre de nouveau sur Reverb.
        $manager = app(BroadcastManager::class);
        $manager->purge();
        $manager->setDefaultDriver('reverb');
        require base_path('routes/channels.php');
        // Aucun serveur Reverb en test : l'événement est intercepté (les abonnements GraphQL passent par
        // le diffuseur espion de TestsSubscriptions).
        Event::fake([ContributionChanged::class]);
    }

    private function line(): Line
    {
        return Line::query()->create(['code' => '570000', 'name' => 'Ligne de Paris-Austerlitz à Bordeaux-St-Jean', 'slug' => 'rfn-570000']);
    }

    #[Test]
    public function rien_ne_part_tant_que_le_temps_reel_est_desactive(): void
    {
        Event::fake([ContributionChanged::class]);

        Contribution::factory()->create(['type' => 'line_segment']);

        Event::assertNotDispatched(ContributionChanged::class);
        $this->assertFalse(Realtime::enabled());
        $this->assertNull(Realtime::clientConfig());
        $this->actingAs(User::factory()->validator()->create())
            ->post('/broadcasting/auth', ['channel_name' => 'private-review-queue', 'socket_id' => '1234.5678'])
            ->assertNotFound();
    }

    #[Test]
    public function activer_sans_serveur_websocket_ne_suffit_pas(): void
    {
        config(['realtime.enabled' => true, 'broadcasting.default' => 'log']);

        $this->assertFalse(Realtime::enabled());
    }

    #[Test]
    public function une_proposition_est_annoncee_aux_validateurs_et_a_son_auteur_seulement(): void
    {
        $this->enable();
        Event::fake([ContributionChanged::class]);
        $author = User::factory()->create(['name' => 'Camille Exemple', 'email' => 'camille@vff.test']);

        $contribution = Contribution::factory()->create(['type' => 'line_segment', 'user_id' => $author->id, 'payload' => ['notes' => 'texte privé']]);

        Event::assertDispatched(ContributionChanged::class, function (ContributionChanged $e) use ($author, $contribution): bool {
            $channels = array_map(fn ($c) => $c->name, $e->broadcastOn());

            return $e->kind === ContributionRealtimeEvent::Submitted
                && $e->broadcastAs() === 'contribution.submitted'
                && $channels === ['private-review-queue', "private-users.{$author->id}.contributions"]
                && $e->broadcastWith()['contribution'] === [
                    'id' => $contribution->id,
                    'type' => 'line_segment',
                    'status' => 'pending',
                    'approvals' => 0,
                    'approvals_required' => 2,
                ];
        });
    }

    #[Test]
    public function une_publication_part_aussi_sur_le_canal_public_sans_donnee_personnelle(): void
    {
        $this->enable();
        $line = $this->line();
        $author = User::factory()->create(['name' => 'Camille Exemple', 'email' => 'camille@vff.test']);
        $contribution = Contribution::factory()->create([
            'type' => 'line_segment', 'user_id' => $author->id, 'target_type' => Line::class, 'target_id' => $line->id,
            'payload' => ['notes' => 'texte privé'],
        ]);
        Event::fake([ContributionChanged::class]);

        $contribution->update(['status' => ContributionStatus::Applied, 'reviewed_by' => User::factory()->validator()->create()->id]);

        Event::assertDispatched(ContributionChanged::class, function (ContributionChanged $e): bool {
            $channels = array_map(fn ($c) => $c->name, $e->broadcastOn());
            $json = (string) json_encode($e->broadcastWith());

            return $e->kind === ContributionRealtimeEvent::Applied
                && in_array('contributions', $channels, true)
                && $e->broadcastWith()['target'] === ['resource' => 'line', 'slug' => 'rfn-570000', 'url' => route('api.lines.show', ['line' => 'rfn-570000'])]
                && ! str_contains($json, 'Camille') && ! str_contains($json, 'camille@') && ! str_contains($json, 'texte privé');
        });
    }

    #[Test]
    public function un_refus_reste_prive_et_ne_porte_pas_son_motif(): void
    {
        $this->enable();
        $contribution = Contribution::factory()->create(['type' => 'line_segment']);
        Event::fake([ContributionChanged::class]);

        $contribution->update(['status' => ContributionStatus::Rejected, 'review_notes' => 'Motif confidentiel du refus']);

        Event::assertDispatched(ContributionChanged::class, function (ContributionChanged $e): bool {
            $channels = array_map(fn ($c) => $c->name, $e->broadcastOn());

            return $e->kind === ContributionRealtimeEvent::Rejected
                && ! in_array('contributions', $channels, true)
                && ! str_contains((string) json_encode($e->broadcastWith()), 'Motif confidentiel');
        });
    }

    #[Test]
    public function une_transaction_annulee_n_annonce_rien(): void
    {
        $this->enable();
        $contribution = Contribution::factory()->create(['type' => 'line_segment']);
        Event::fake([ContributionChanged::class]);

        DB::beginTransaction();
        $contribution->update(['status' => ContributionStatus::Applied]);
        DB::rollBack();

        Event::assertNotDispatched(ContributionChanged::class);
    }

    #[Test]
    public function un_changement_sans_nouvel_etat_n_est_pas_annonce(): void
    {
        $this->enable();
        $contribution = Contribution::factory()->create(['type' => 'line_segment']);
        Event::fake([ContributionChanged::class]);

        $contribution->update(['payload' => ['notes' => 'autre']]);

        Event::assertNotDispatched(ContributionChanged::class);
    }

    #[Test]
    public function la_file_de_validation_est_reservee_aux_validateurs_non_suspendus(): void
    {
        $this->enable();
        $body = ['channel_name' => 'private-review-queue', 'socket_id' => '1234.5678'];

        $this->postJson('/broadcasting/auth', $body)->assertUnauthorized();
        $this->actingAs(User::factory()->create())->post('/broadcasting/auth', $body)->assertForbidden();
        $this->actingAs(User::factory()->validator()->create(['suspended_at' => now()]))->post('/broadcasting/auth', $body)->assertForbidden();
        $this->actingAs(User::factory()->validator()->create())->post('/broadcasting/auth', $body)
            ->assertOk()->assertJsonStructure(['auth']);
        $this->actingAs(User::factory()->admin()->create())->post('/broadcasting/auth', $body)->assertOk();
    }

    #[Test]
    public function le_canal_d_un_compte_n_est_ouvert_qu_a_ce_compte(): void
    {
        $this->enable();
        $me = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($me)
            ->post('/broadcasting/auth', ['channel_name' => "private-users.{$me->id}.contributions", 'socket_id' => '1234.5678'])
            ->assertOk()->assertJsonStructure(['auth']);
        $this->actingAs($me)
            ->post('/broadcasting/auth', ['channel_name' => "private-users.{$other->id}.contributions", 'socket_id' => '1234.5678'])
            ->assertForbidden();
    }

    #[Test]
    public function la_page_de_validation_recoit_la_configuration_publique_seulement_si_active(): void
    {
        $validator = User::factory()->validator()->create();

        $this->actingAs($validator)->get('/validate')
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Validate/Index')->where('realtime', null));

        $this->enable();
        $this->actingAs($validator)->get('/validate')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('realtime', [
                'key' => 'cle-publique',
                'host' => 'temps-reel.vff.test',
                'port' => 443,
                'scheme' => 'https',
                'auth_endpoint' => '/broadcasting/auth',
            ]));
        $this->assertStringNotContainsString('secret-du-serveur', (string) json_encode(Realtime::clientConfig()));
    }

    #[Test]
    public function la_csp_admet_le_serveur_websocket_seulement_si_active(): void
    {
        $request = Request::create('/');
        $this->assertStringNotContainsString('wss://', ContentSecurityPolicy::build($request, 'n'));

        $this->enable();
        $this->assertStringContainsString('wss://temps-reel.vff.test', ContentSecurityPolicy::build($request, 'n'));
        config(['realtime.client.port' => 8443]);
        $this->assertStringContainsString('wss://temps-reel.vff.test:8443', ContentSecurityPolicy::build($request, 'n'));
    }

    #[Test]
    public function le_schema_graphql_decrit_les_trois_abonnements(): void
    {
        $this->graphQL('{ __schema { subscriptionType { fields { name } } } }')
            ->assertJsonPath('data.__schema.subscriptionType.fields.*.name', ['contributionApplied', 'myContributionChanged', 'reviewQueueChanged']);
    }

    #[Test]
    public function un_abonnement_graphql_est_refuse_tant_que_le_temps_reel_est_desactive(): void
    {
        $this->graphQL('subscription { contributionApplied { event } }')
            ->assertGraphQLErrorMessage('Unauthorized subscription request');
    }

    #[Test]
    public function abonnements_graphql_selon_le_compte(): void
    {
        $this->enable();
        $query = fn (string $field) => "subscription { {$field} { event contribution { id status } target { slug } } }";

        // Publications : ouvert sans compte, un canal privé propre à l'abonnement.
        $this->graphQL($query('contributionApplied'))
            ->assertJsonPath('data.contributionApplied', null)
            ->assertJsonStructure(['extensions' => ['lighthouse_subscriptions' => ['channel']]]);

        // Mes contributions : refusé sans compte, accepté avec un jeton personnel.
        $this->graphQL($query('myContributionChanged'))->assertGraphQLErrorMessage('Unauthorized subscription request');
        $member = User::factory()->create();
        $this->graphQL($query('myContributionChanged'), [], [], ['Authorization' => 'Bearer '.$this->token($member)])
            ->assertJsonStructure(['extensions' => ['lighthouse_subscriptions' => ['channel']]]);

        // File de validation : refusée à un membre, acceptée pour un validateur.
        $this->graphQL($query('reviewQueueChanged'), [], [], ['Authorization' => 'Bearer '.$this->token($member)])
            ->assertGraphQLErrorMessage('Unauthorized subscription request');
        $this->graphQL($query('reviewQueueChanged'), [], [], ['Authorization' => 'Bearer '.$this->token(User::factory()->validator()->create())])
            ->assertJsonStructure(['extensions' => ['lighthouse_subscriptions' => ['channel']]]);
    }

    #[Test]
    public function une_publication_atteint_les_abonnes_graphql_de_la_fiche(): void
    {
        $this->enable();
        $line = $this->line();
        $author = User::factory()->create();
        $authorToken = $this->token($author);

        $this->graphQL('subscription { contributionApplied(resource: LINE, slug: "rfn-570000") { event target { slug } } }');
        $this->graphQL('subscription { contributionApplied(slug: "autre-fiche") { event } }');
        $this->graphQL('subscription { myContributionChanged { event contribution { status } } }', [], [], ['Authorization' => 'Bearer '.$authorToken]);
        $this->graphQL('subscription { myContributionChanged { event } }', [], [], ['Authorization' => 'Bearer '.$this->token(User::factory()->create())]);

        $contribution = Contribution::factory()->create(['type' => 'line_segment', 'user_id' => $author->id, 'target_type' => Line::class, 'target_id' => $line->id]);
        $contribution->update(['status' => ContributionStatus::Applied]);

        /** @var MockInterface $broadcaster */
        $broadcaster = app(BroadcastDriverManager::class)->driver('mock');
        $results = [];
        $broadcaster->shouldHaveReceived('broadcast')->withArgs(function ($subscriber, $data) use (&$results): bool {
            $results[] = [$subscriber->fieldName, $data['data'] ?? null];

            return true;
        });

        $this->assertContains(['contributionApplied', ['contributionApplied' => ['event' => 'APPLIED', 'target' => ['slug' => 'rfn-570000']]]], $results);
        $this->assertContains(['myContributionChanged', ['myContributionChanged' => ['event' => 'SUBMITTED', 'contribution' => ['status' => 'PENDING']]]], $results);
        $this->assertContains(['myContributionChanged', ['myContributionChanged' => ['event' => 'APPLIED', 'contribution' => ['status' => 'APPLIED']]]], $results);
        // Ni l'abonné d'une autre fiche, ni un autre compte.
        $this->assertCount(3, $results);
    }

    private function token(User $user): string
    {
        return app(ApiTokens::class)->create($user, 'Script')->plainTextToken;
    }
}

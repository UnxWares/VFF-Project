<?php

namespace Tests\Feature\Contributions;

use App\Enums\AuditAction;
use App\Enums\NotificationKind;
use App\Models\AuditEvent;
use App\Models\Contribution;
use App\Models\Line;
use App\Models\LineEvent;
use App\Models\User;
use App\Services\Contributions\EventContributions;
use App\Services\EraTimeline;
use App\Support\SourceRights;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ActsAsRoles;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * Proposer un événement daté d'une ligne (/contribute/event) : accès, règles de saisie (type, date à
 * sa précision, portion, source obligatoire avec licence pour un document), relecture à deux,
 * refus motivé, publication dans line_events avec sa provenance, sans recalcul du moteur.
 */
class EventContributionTest extends TestCase
{
    use ActsAsRoles;
    use BuildsRailNetwork;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRailNetwork();
        $this->withCookie('locale', 'fr');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function event(array $overrides = []): array
    {
        return [
            'line_slug' => $this->line->slug,
            'type' => 'closure_passenger',
            'precision' => 'day',
            'date' => '1938-05-15',
            'portion' => 'places',
            'from' => 'Saint-Cloud',
            'to' => 'Versailles-Rive-Droite',
            'description' => 'Remplacement par un service d’autocars.',
            'source_kind' => 'document',
            'source_title' => 'Rapport du conseil d’administration de la SNCF, 1938, p. 14',
            'source_url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k0000000',
            'source_license' => 'public_domain',
            'notes' => "Voir aussi le livret Chaix d'été 1938.",
            ...$overrides,
        ];
    }

    private function submit(User $author, array $overrides = []): Contribution
    {
        $this->actingAs($author)->post('/contribute/event', $this->event($overrides))->assertRedirect();

        return Contribution::query()->latest('id')->firstOrFail();
    }

    // ─── Accès ──────────────────────────────────────────────────────────────

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get('/contribute/event')->assertRedirect('/login');
        $this->post('/contribute/event', $this->event())->assertRedirect('/login');
        $this->assertSame(0, Contribution::count());
    }

    public function test_a_member_sees_the_form_with_the_line_preselected(): void
    {
        $this->actingAs($this->member())
            ->get('/contribute/event?line='.$this->line->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Contribute/ProposeEvent')
                ->where('preselectedLine.slug', $this->line->slug)
                ->where('eventTypes', EventContributions::eventTypes())
                ->where('precisions', ['day', 'month', 'year'])
                ->where('sourceKinds', ['web', 'document', 'print'])
                ->has('licenses', 7));
        $this->assertNotContains('accident', EventContributions::eventTypes());
        $this->assertContains('conversion', EventContributions::eventTypes());
    }

    public function test_a_suspended_account_cannot_propose(): void
    {
        $suspended = $this->member('contributor', ['suspended_at' => now()]);

        $this->actingAs($suspended)->get('/contribute/event')->assertForbidden();
        $this->actingAs($suspended)->post('/contribute/event', $this->event())->assertForbidden();
        $this->assertSame(0, Contribution::count());
    }

    // ─── Règles de saisie ───────────────────────────────────────────────────

    /** @return array<string, array{0: array<string, mixed>, 1: string}> */
    public static function invalidProposals(): array
    {
        return [
            'ligne inconnue' => [['line_slug' => 'ligne-inexistante'], 'line_slug'],
            'accident refusé' => [['type' => 'accident'], 'type'],
            'type inconnu' => [['type' => 'teleportation'], 'type'],
            'précision inconnue' => [['precision' => 'week'], 'precision'],
            'mois impossible' => [['precision' => 'month', 'date' => '1938-13'], 'date'],
            'jour impossible' => [['date' => '1938-02-30'], 'date'],
            'année trop ancienne' => [['precision' => 'year', 'date' => '1700'], 'date'],
            'jour manquant pour la précision jour' => [['date' => '1938-05'], 'date'],
            'extrémité manquante' => [['to' => ''], 'to'],
            'extrémités identiques' => [['to' => 'Saint-Cloud'], 'to'],
            'PK dans le désordre' => [['portion' => 'pk', 'pk_start_m' => 9000, 'pk_end_m' => 4000], 'pk_end_m'],
            'PK manquant' => [['portion' => 'pk', 'pk_start_m' => 1000], 'pk_end_m'],
            'portion inconnue' => [['portion' => 'partout'], 'portion'],
            'type autre sans précision' => [['type' => 'other', 'description' => ''], 'description'],
            'source sans titre' => [['source_title' => ''], 'source_title'],
            'titre trop court' => [['source_title' => 'JO'], 'source_title'],
            'page web sans adresse' => [['source_kind' => 'web', 'source_url' => ''], 'source_url'],
            'adresse non web' => [['source_url' => 'ftp://exemple.org/doc.pdf'], 'source_url'],
            'document sans licence' => [['source_license' => ''], 'source_license'],
            'licence inconnue' => [['source_license' => 'wtfpl'], 'source_license'],
            'nature de source inconnue' => [['source_kind' => 'rumeur'], 'source_kind'],
            'usage d’emprise inconnu' => [['type' => 'conversion', 'reuse' => 'parking'], 'reuse'],
        ];
    }

    /** @param  array<string, mixed>  $overrides */
    #[DataProvider('invalidProposals')]
    public function test_an_invalid_proposal_is_refused_with_the_field_in_error(array $overrides, string $field): void
    {
        $this->actingAs($this->member())
            ->from('/contribute/event')
            ->post('/contribute/event', $this->event($overrides))
            ->assertRedirect('/contribute/event')
            ->assertSessionHasErrors($field);
        $this->assertSame(0, Contribution::count());
    }

    public function test_a_printed_source_needs_no_address_and_ignores_the_licence(): void
    {
        $contribution = $this->submit($this->member(), [
            'source_kind' => 'print',
            'source_url' => '',
            'source_license' => 'cc-by-4.0',
            'source_title' => 'Histoire des chemins de fer de l’Ouest, tome 2, p. 211',
        ]);

        $this->assertEquals(['kind' => 'print', 'title' => 'Histoire des chemins de fer de l’Ouest, tome 2, p. 211', 'url' => null, 'license' => null], $contribution->payload['source']);
    }

    public function test_fields_of_another_portion_kind_are_dropped(): void
    {
        $contribution = $this->submit($this->member(), ['portion' => 'pk', 'pk_start_m' => '2500', 'pk_end_m' => '12345']);

        $this->assertEquals(['kind' => 'pk', 'pk_start_m' => 2500, 'pk_end_m' => 12345], $contribution->payload['portion']);
        $this->assertIsInt($contribution->payload['portion']['pk_start_m']);
    }

    // ─── Envoi ──────────────────────────────────────────────────────────────

    public function test_a_proposal_is_saved_pending_and_announced_to_the_validators(): void
    {
        $validator = $this->member('validator');
        $author = $this->member();

        $contribution = $this->submit($author, ['precision' => 'month', 'date' => '1938-05', 'source_title' => '  Rapport   1938  ']);

        $this->assertSame(EventContributions::TYPE, $contribution->type);
        $this->assertSame('pending', $contribution->status->value);
        $this->assertSame('create', $contribution->action->value);
        $this->assertSame([Line::class, $this->line->id], [$contribution->target_type, (int) $contribution->target_id]);
        $p = $contribution->payload;
        $this->assertSame('1938-05-01', $p['date'], 'une date au mois reste au premier du mois, avec sa précision');
        $this->assertSame('month', $p['precision']);
        $this->assertEquals(['kind' => 'places', 'from' => 'Saint-Cloud', 'to' => 'Versailles-Rive-Droite'], $p['portion']);
        $this->assertSame('Rapport 1938', $p['source']['title']);
        $this->assertSame([$this->line->slug, $this->line->name], [$p['line_slug'], $p['line_name']]);
        $this->assertSame(1, $validator->notifications()->where('data->kind', NotificationKind::AwaitingReview->value)->count());
        $this->assertSame(0, LineEvent::query()->where('source', EventContributions::SOURCE)->count(), 'rien de publié avant la relecture');

        $this->actingAs($author)->get("/contribute/thanks/{$contribution->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Contribute/Thanks')->where('contribution.type', 'line_event'));
    }

    // ─── Relecture ──────────────────────────────────────────────────────────

    public function test_two_approvals_publish_the_event_with_its_provenance_and_without_engine_recalculation(): void
    {
        $contribution = $this->submit($this->member());
        $segmentsBefore = DB::table('line_segments')->orderBy('id')->get()->toJson();
        [$first, $second] = [$this->member('validator'), $this->member('validator')];

        $this->actingAs($first)->post("/validate/{$contribution->id}/approve")->assertSessionHas('success');
        $this->assertSame('approved', $contribution->fresh()->status->value);
        $this->assertSame(0, LineEvent::query()->where('source', EventContributions::SOURCE)->count(), 'une seule approbation ne publie rien');

        $this->actingAs($second)->post("/validate/{$contribution->id}/approve")->assertSessionHas('success');

        $contribution->refresh();
        $this->assertSame('applied', $contribution->status->value);
        $event = LineEvent::query()->where('fingerprint', 'contribution:'.$contribution->id)->firstOrFail();
        $this->assertSame($contribution->payload['applied']['line_event_id'], $event->id);
        $this->assertSame($this->line->id, $event->line_id);
        $this->assertSame('closure_passenger', $event->getRawOriginal('type'));
        $this->assertSame('1938-05-15', $event->event_date->toDateString());
        $this->assertSame('day', $event->date_precision);
        $this->assertSame('contrib', $event->source);
        $this->assertSame('https://gallica.bnf.fr/ark:/12148/bpt6k0000000', $event->source_url);
        $this->assertSame('Rapport du conseil d’administration de la SNCF, 1938, p. 14 (licence : Public domain)', $event->source_quote);
        $this->assertSame(['Saint-Cloud', 'Versailles-Rive-Droite'], [$event->section_from, $event->section_to]);
        $this->assertSame('pending', $event->section_resolution, 'portion laissée à vff:resolve-event-sections');
        $this->assertNull($event->getAttribute('section_geom'));
        $this->assertSame('high', $event->confidence);
        $this->assertStringStartsWith('Fermeture aux voyageurs de Saint-Cloud à Versailles-Rive-Droite : Remplacement', $event->description);
        $this->assertStringEndsWith("(contribution n° {$contribution->id})", $event->description);
        $this->assertSame($segmentsBefore, DB::table('line_segments')->orderBy('id')->get()->toJson(), 'aucun tronçon recalculé');
        $this->assertTrue(AuditEvent::query()->where('action', AuditAction::ContributionApplied->value)->exists());
    }

    /** @return array<string, array{0: array<string, mixed>, 1: string, 2: string, 3: string}> */
    public static function citedSources(): array
    {
        return [
            'document de Gallica' => [[], 'high', 'contrib', 'gallica'],
            'ouvrage papier' => [['source_kind' => 'print', 'source_url' => null, 'source_license' => null], 'high', 'contrib', 'vff'],
            'Journal officiel' => [['source_kind' => 'web', 'source_url' => 'https://www.legifrance.gouv.fr/jorf/id/JORFTEXT000000000001'], 'high', 'contrib', 'jorf'],
            'article Wikipédia' => [['source_kind' => 'web', 'source_url' => 'https://fr.wikipedia.org/wiki/Ligne_de_Saint-Cloud'], 'medium', 'wikipedia', 'wikipedia'],
            'fiche lignes-oubliees' => [['source_kind' => 'web', 'source_url' => 'https://www.lignes-oubliees.com/ligne-123'], 'medium', 'lignes-oubliees', 'lignes-oubliees'],
            'Wikidata' => [['source_kind' => 'web', 'source_url' => 'https://www.wikidata.org/wiki/Q90'], 'low', 'wikidata', 'wikidata'],
            'blog inconnu' => [['source_kind' => 'web', 'source_url' => 'https://trains-d-autrefois.example.net/saint-cloud'], 'medium', 'contrib-web', 'vff'],
        ];
    }

    /**
     * La confiance suit la source citée : la colonne `confidence`, le rang dans le moteur
     * (EraTimeline::rankedSource) et le fournisseur annoncé par l'API (registre des droits, `contrib`
     * composite comme `manual`).
     *
     * @param  array<string, mixed>  $source
     */
    #[DataProvider('citedSources')]
    public function test_confidence_follows_the_cited_source(array $source, string $confidence, string $rankedAs, string $provider): void
    {
        $contribution = $this->submit($this->member(), $source);
        $event = LineEvent::query()->findOrFail(app(EventContributions::class)->apply($contribution)['line_event_id']);

        $this->assertSame([EventContributions::SOURCE, $confidence], [$event->source, $event->confidence]);
        $this->assertSame($rankedAs, EraTimeline::rankedSource($event->source, $event->source_url, $event->confidence));
        $this->assertSame($provider, app(SourceRights::class)->providerFor('line_events', $event->source, $event->source_url));
    }

    public function test_publishing_twice_keeps_a_single_event(): void
    {
        $contribution = $this->submit($this->member(), ['portion' => 'pk', 'pk_start_m' => 1000, 'pk_end_m' => 4500]);
        $events = app(EventContributions::class);

        $first = $events->apply($contribution);
        $second = $events->apply($contribution);

        $this->assertSame($first['line_event_id'], $second['line_event_id']);
        $this->assertSame(1, LineEvent::query()->where('fingerprint', 'contribution:'.$contribution->id)->count());
        $event = LineEvent::query()->findOrFail($first['line_event_id']);
        $this->assertSame([1000, 4500], [$event->section_pk_start_m, $event->section_pk_end_m]);
        $this->assertStringContainsString('du PK 1+000 au PK 4+500', $event->description);
    }

    public function test_a_rejection_needs_a_reason_sent_to_the_author_and_publishes_nothing(): void
    {
        $author = $this->member();
        $contribution = $this->submit($author);
        $validator = $this->member('validator');

        $this->actingAs($validator)->post("/validate/{$contribution->id}/reject", ['notes' => ''])->assertSessionHasErrors('notes');
        $this->assertSame('pending', $contribution->fresh()->status->value);

        $this->actingAs($validator)->post("/validate/{$contribution->id}/reject", ['notes' => 'La source date la décision, pas la fermeture.'])
            ->assertSessionHas('success');

        $contribution->refresh();
        $this->assertSame('rejected', $contribution->status->value);
        $this->assertSame('La source date la décision, pas la fermeture.', $contribution->review_notes);
        $this->assertSame(0, LineEvent::query()->where('source', EventContributions::SOURCE)->count());
        $this->assertSame(1, $author->notifications()->where('data->kind', NotificationKind::Rejected->value)->count());
    }

    public function test_the_author_cannot_review_their_own_proposal(): void
    {
        $validator = $this->member('validator');
        $contribution = $this->submit($validator);

        $this->actingAs($validator)->post("/validate/{$contribution->id}/approve")->assertSessionHas('error');

        $this->assertSame('pending', $contribution->fresh()->status->value);
        $this->assertSame(0, DB::table('contribution_approvals')->count());
    }

    public function test_a_line_removed_before_publication_fails_cleanly(): void
    {
        $contribution = $this->submit($this->member());
        $this->actingAs($this->member('validator'))->post("/validate/{$contribution->id}/approve");
        $contribution->update(['target_id' => Line::factory()->create()->id]);
        Line::query()->whereKey($contribution->target_id)->delete();

        $this->actingAs($this->member('validator'))->post("/validate/{$contribution->id}/approve")
            ->assertSessionHas('error', fn (string $message) => str_contains($message, 'n’existe plus'));

        $this->assertSame('approved', $contribution->fresh()->status->value, 'approbation annulée');
        $this->assertSame(0, LineEvent::query()->where('source', EventContributions::SOURCE)->count());
    }

    public function test_validators_see_the_events_of_the_same_kind_already_known(): void
    {
        $contribution = $this->submit($this->member(), ['type' => 'opening_passenger', 'precision' => 'year', 'date' => '1840']);

        $this->actingAs($this->member('validator'))
            ->get('/validate')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Validate/Index')
                ->where('contributions.0.id', $contribution->id)
                ->where('contributions.0.type', 'line_event')
                ->has('contributions.0.similar_events', 1)
                ->where('contributions.0.similar_events.0.type', 'opening')
                ->where('contributions.0.similar_events.0.date', '1839-08-02'));
    }

    public function test_the_author_sees_the_proposal_in_their_contributions(): void
    {
        $author = $this->member();
        $contribution = $this->submit($author);

        $this->actingAs($author)->get('/contribute/mine')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('contributions.0.id', $contribution->id)
                ->where('contributions.0.type', 'line_event')
                ->where('contributions.0.payload.event_type', 'closure_passenger'));
    }
}

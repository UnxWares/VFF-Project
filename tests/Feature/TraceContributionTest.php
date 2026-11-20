<?php

namespace Tests\Feature;

use App\Models\Contribution;
use App\Models\User;
use App\Services\TraceContributionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * Éditeur de tracé collaboratif : proposition (portions + événements datés),
 * modération à deux validateurs, écriture dans source_traces / line_events.
 */
class TraceContributionTest extends TestCase
{
    use BuildsRailNetwork;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRailNetwork();
    }

    /**
     * Le hash statique de UserFactory (coût 10) est refusé par le cast `hashed`
     * quand BCRYPT_ROUNDS=4 (phpunit.xml) : on fournit un mot de passe en clair.
     */
    private function member(?string $role = null): User
    {
        $factory = User::factory();
        if ($role) {
            $factory = $factory->{$role}();
        }

        return $factory->create(['password' => 'password']);
    }

    /** Ligne disparue fictive dans les Landes, en deux portions. */
    private function newLinePayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'kind' => 'new_line',
            'new_line_name' => 'Ligne de Saint-Sever à Hagetmau',
            'network' => 'secondaire',
            'basemap' => 'ign-scan50-1950',
            'trace_source' => 'Tracé relevé sur : IGN – Carte 1950 (SCAN 50 historique)',
            'notes' => 'Emprise encore visible sur les photos 1950-1965.',
            'portions' => [
                [
                    'key' => 'p1',
                    'label' => 'Saint-Sever – Audignon',
                    'from' => 'Saint-Sever',
                    'to' => 'Audignon',
                    'coordinates' => [[-0.5687, 43.7570], [-0.5900, 43.7300], [-0.6012, 43.7125]],
                    'events' => [
                        ['type' => 'opening', 'precision' => 'day', 'date' => '1890-06-15', 'source' => 'Journal officiel du 16/06/1890', 'source_url' => 'https://gallica.bnf.fr/ark:/12148/test'],
                        ['type' => 'closure_passenger', 'precision' => 'year', 'date' => '1938', 'source' => 'Chaix 1938'],
                    ],
                ],
                [
                    'key' => 'p2',
                    'label' => 'Audignon – Hagetmau',
                    'from' => 'Audignon',
                    'to' => 'Hagetmau',
                    'coordinates' => [[-0.6012, 43.7125], [-0.5920, 43.6560]],
                    'events' => [
                        ['type' => 'opening', 'precision' => 'month', 'date' => '1891-03', 'source' => 'Rapport du conseil général des Landes, 1891'],
                    ],
                ],
            ],
        ], $overrides);
    }

    public function test_guest_is_redirected_from_the_editor(): void
    {
        $this->get('/contribute/trace')->assertRedirect('/login');
        $this->post('/contribute/trace', $this->newLinePayload())->assertRedirect('/login');
    }

    public function test_editor_page_exposes_line_and_vocabularies(): void
    {
        $user = $this->member('contributor');

        $this->actingAs($user)
            ->get('/contribute/trace?line='.$this->line->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Contribute/Trace')
                ->where('line.slug', $this->line->slug)
                ->where('currentEra', 2030)
                ->where('networks', TraceContributionService::NETWORKS)
                ->has('eventTypes')
                ->where('precisions', ['day', 'month', 'year'])
            );
    }

    public function test_submission_creates_a_pending_line_trace_contribution(): void
    {
        $user = $this->member('contributor');

        $response = $this->actingAs($user)->post('/contribute/trace', $this->newLinePayload());

        $contribution = Contribution::where('type', 'line_trace')->sole();
        $response->assertRedirect(route('contribute.thanks', ['id' => $contribution->id]));

        $p = $contribution->payload;
        $this->assertSame('pending', $contribution->status->value);
        $this->assertSame('create', $contribution->action->value);
        $this->assertSame('new_line', $p['kind']);
        $this->assertCount(2, $p['portions']);
        $this->assertGreaterThan(3000, $p['portions'][0]['length_m']);
        // Dates normalisées selon leur précision (année → 1er janvier, mois → 1er du mois).
        $this->assertSame('1938-01-01', $p['portions'][0]['events'][1]['date']);
        $this->assertSame('1891-03-01', $p['portions'][1]['events'][0]['date']);
        $this->assertSame(3, $p['events_count']);
        $this->assertNull($p['before']);

        $this->actingAs($user)->get('/contribute/thanks/'.$contribution->id)->assertOk();
        $this->actingAs($this->member())->get('/contribute/thanks/'.$contribution->id)->assertNotFound();
    }

    public function test_events_require_a_cited_source_and_a_date_matching_its_precision(): void
    {
        $user = $this->member('contributor');
        $payload = $this->newLinePayload();
        $payload['portions'][0]['events'][0]['source'] = '';
        $payload['portions'][1]['events'][0]['date'] = '1891';  // précision « mois » attendue

        $this->actingAs($user)->post('/contribute/trace', $payload)
            ->assertSessionHasErrors(['portions.0.events.0.source']);

        $payload['portions'][0]['events'][0]['source'] = 'Journal officiel';
        $this->actingAs($user)->post('/contribute/trace', $payload)
            ->assertSessionHasErrors(['portions.1.events.0.date']);

        $this->assertSame(0, Contribution::count());
    }

    public function test_two_validators_publish_into_source_traces_and_line_events(): void
    {
        $author = $this->member('contributor');
        $v1 = $this->member('validator');
        $v2 = $this->member('validator');
        $this->actingAs($author)->post('/contribute/trace', $this->newLinePayload());
        $c = Contribution::sole();

        // L'auteur (même validateur) ne peut pas s'auto-approuver ; un simple membre est refusé.
        $this->actingAs($author)->post("/validate/{$c->id}/approve")->assertForbidden();

        $this->actingAs($v1)->get('/validate')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Validate/Index')
                ->where('contributions.0.type', 'line_trace')
                ->where('contributions.0.approval_count', 0)
                ->has('contributions.0.nearby')
            );

        $this->actingAs($v1)->post("/validate/{$c->id}/approve")->assertSessionHas('success');
        $this->assertSame('approved', $c->fresh()->status->value);
        $this->assertSame(0, DB::table('source_traces')->where('source', 'contrib')->count());

        // Toujours listée pour le second validateur (statut approved = 1/2).
        $this->actingAs($v2)->get('/validate')
            ->assertInertia(fn (Assert $page) => $page->where('contributions.0.approval_count', 1));
        $this->actingAs($v1)->post("/validate/{$c->id}/approve")->assertSessionHas('error');

        $this->actingAs($v2)->post("/validate/{$c->id}/approve")->assertSessionHas('success');
        $c->refresh();
        $this->assertSame('applied', $c->status->value);

        $line = DB::table('lines')->where('id', $c->payload['applied']['line_id'])->first();
        $this->assertSame('Ligne de Saint-Sever à Hagetmau', $line->name);
        $this->assertSame('secondaire', $line->network);
        $this->assertStringStartsWith('contrib-', $line->slug);

        $trace = DB::selectOne(
            "SELECT id, source_ref, layer, line_id, match_method, opened_year, closed_year,
                    ST_NumGeometries(geom) AS parts, GeometryType(geom) AS gtype
             FROM source_traces WHERE source = 'contrib'"
        );
        $this->assertSame((string) $c->id, $trace->source_ref);
        $this->assertSame('secondaire', $trace->layer);
        $this->assertSame($line->id, $trace->line_id);
        $this->assertSame('created', $trace->match_method);
        $this->assertSame(2, (int) $trace->parts);
        $this->assertSame('MULTILINESTRING', $trace->gtype);
        $this->assertSame(1890, (int) $trace->opened_year);
        $this->assertNull($trace->closed_year); // fermeture voyageurs seulement

        $events = DB::select(
            'SELECT type, event_date::text AS d, date_precision, source, source_quote, section_from, section_to,
                    section_resolution, source_trace_id, fingerprint, GeometryType(section_geom) AS g
             FROM line_events WHERE line_id = ? ORDER BY event_date',
            [$line->id],
        );
        $this->assertCount(3, $events);
        $this->assertSame(['1890-06-15', '1891-03-01', '1938-01-01'], array_column($events, 'd'));
        $this->assertSame(['day', 'month', 'year'], array_column($events, 'date_precision'));
        foreach ($events as $e) {
            $this->assertSame('contrib', $e->source);
            $this->assertSame('trace', $e->section_resolution);
            $this->assertSame('MULTILINESTRING', $e->g);
            $this->assertSame($trace->id, $e->source_trace_id);
            $this->assertStringStartsWith("contrib:{$c->id}:", $e->fingerprint);
        }
        $this->assertSame('Audignon', $events[1]->section_from);

        // Ré-application idempotente : aucun doublon.
        app(TraceContributionService::class)->apply($c->fresh());
        $this->assertSame(1, DB::table('source_traces')->where('source', 'contrib')->count());
        $this->assertSame(3, DB::table('line_events')->where('source', 'contrib')->count());
        $this->assertSame(1, DB::table('lines')->where('name', 'Ligne de Saint-Sever à Hagetmau')->count());

        // Plus d'approbation possible une fois publiée.
        $v3 = $this->member('validator');
        $this->actingAs($v3)->post("/validate/{$c->id}/approve")->assertSessionHas('error');
    }

    public function test_a_validator_cannot_approve_their_own_proposal(): void
    {
        $validator = $this->member('validator');
        $this->actingAs($validator)->post('/contribute/trace', $this->newLinePayload());
        $c = Contribution::sole();

        $this->actingAs($validator)->post("/validate/{$c->id}/approve")
            ->assertSessionHas('error', __('editor.validate.flash.own'));
        $this->assertSame(0, DB::table('contribution_approvals')->count());
        $this->actingAs($validator)->get('/validate')
            ->assertInertia(fn (Assert $page) => $page->where('contributions.0.is_mine', true));
    }

    public function test_correction_of_an_existing_line_keeps_a_before_snapshot(): void
    {
        $author = $this->member('contributor');
        $segmentId = DB::table('line_segments')->where('line_id', $this->line->id)->value('id');

        $parts = $this->actingAs($author)
            ->getJson("/contribute/trace/feature/segment/{$segmentId}")
            ->assertOk()
            ->assertJsonPath('line.slug', $this->line->slug)
            ->json('parts');
        $this->assertCount(3, $parts[0]);

        $this->actingAs($author)->post('/contribute/trace', [
            'kind' => 'correction',
            'line_slug' => $this->line->slug,
            'network' => 'secondaire',
            'trace_source' => 'Photographies aériennes IGN 1950-1965',
            'portions' => [[
                'key' => 'a',
                'coordinates' => [[2.3253, 48.8763], [2.2450, 48.8560], [2.1347, 48.8091]],
                'events' => [['type' => 'dismantling', 'precision' => 'year', 'date' => '1975', 'source' => 'Arrêté de déclassement 1975']],
            ]],
            'replaces' => ['line_segment_ids' => [$segmentId]],
        ])->assertRedirect();

        $c = Contribution::sole();
        $this->assertSame('update', $c->action->value);
        $this->assertSame($this->line->id, $c->target_id);
        $this->assertSame('MultiLineString', $c->payload['before']['type']);
        $this->assertSame([$segmentId], $c->payload['replaces']['line_segment_ids']);

        $result = app(TraceContributionService::class)->apply($c);
        $this->assertFalse($result['line_created']);
        $this->assertSame('secondaire', DB::table('lines')->where('id', $this->line->id)->value('network'));
        $trace = DB::table('source_traces')->where('source', 'contrib')->first();
        $this->assertSame('contrib', $trace->match_method);
        $this->assertSame(1975, (int) $trace->closed_year);
        $this->assertSame([$segmentId], json_decode($trace->properties, true)['supersedes']['line_segment_ids']);
    }

    public function test_reference_endpoint_serves_snap_data_only_at_close_zoom(): void
    {
        $user = $this->member();

        $this->actingAs($user)
            ->getJson('/contribute/trace/reference?bbox=2.1,48.78,2.35,48.9&zoom=12')
            ->assertOk()
            ->assertJsonPath('features.0.properties.kind', 'segment')
            ->assertJsonPath('features.0.properties.line_slug', $this->line->slug);

        $this->actingAs($user)
            ->getJson('/contribute/trace/reference?bbox=-5,42,8,51&zoom=6')
            ->assertOk()
            ->assertJsonPath('too_large', true)
            ->assertJsonCount(0, 'features');

        $this->actingAs($user)
            ->getJson('/contribute/trace/reference?bbox=2.1,48.78,2.35,48.9&zoom=12&segments=0&sources=')
            ->assertOk()
            ->assertJsonCount(0, 'features');

        $this->actingAs($user)
            ->getJson('/contribute/trace/lines/'.$this->line->slug)
            ->assertOk()
            ->assertJsonPath('line.id', $this->line->id)
            ->assertJsonCount(1, 'segments.features');
    }
}

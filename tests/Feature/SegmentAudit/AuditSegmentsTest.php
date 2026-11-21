<?php

namespace Tests\Feature\SegmentAudit;

use App\Models\Era;
use App\Models\Line;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ActsAsRoles;
use Tests\TestCase;

/**
 * Audit des tronçons : passe `vff:audit-segments` sur une ligne écrite à la main, page
 * d'administration /validate/audit/segments, API REST, GraphQL et serveur MCP.
 *
 *   ère 2030 :  A (en service, mixte, BD TOPO)  ──  B (fermé, forme SNCF, 20 m au nord de la voie OSM)
 *   ère 2020 :  A' (en service, « voyageurs » hors LGV)  ──  B' (en service alors que Wikipédia la dit fermée en 2015)
 */
class AuditSegmentsTest extends TestCase
{
    use ActsAsRoles;
    use RefreshDatabase;

    private Line $line;

    private Era $current;

    private Era $past;

    /** @var array<string, int> */
    private array $seg = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withCookie('locale', 'fr');
        $this->past = Era::factory()->create(['year' => 2020, 'label' => 'Années 2020']);
        $this->current = Era::factory()->create(['year' => 2030, 'label' => 'Aujourd’hui', 'is_current' => true]);
        $this->line = Line::factory()->create(['slug' => 'rfn-123456', 'code' => '123456', 'name' => 'Ligne d’Alpha à Bravo',
            'is_lgv' => false, 'network' => 'principal', 'length_official_m' => 2300]);

        $a = 'LINESTRING(2.0 48.0, 2.01 48.0, 2.02 48.0)';
        $b = 'LINESTRING(2.02 48.00018, 2.03 48.00018)';
        $this->seg['A'] = $this->segment($this->current, $a, 'in_service', 'mixed', 'bdtopo');
        $this->seg['B'] = $this->segment($this->current, $b, 'abandoned', 'unknown', 'sncf');
        $this->seg['A2'] = $this->segment($this->past, $a, 'in_service', 'passenger', 'bdtopo');
        $this->seg['B2'] = $this->segment($this->past, $b, 'in_service', 'mixed', 'sncf');

        // Voies réelles : BD TOPO exactement sous A, OSM à 1 m au nord de A, voie OSM désaffectée sous l'emplacement réel de B.
        $this->trace('bdtopo', 'Voie ferrée principale', 'LINESTRING(2.0 48.0, 2.02 48.0)', ['etat_de_l_objet' => 'En service', 'usage' => 'Voyageur et fret']);
        $this->trace('osm', 'rail', 'LINESTRING(2.0 48.000009, 2.02 48.000009)');
        $this->trace('osm', 'disused', 'LINESTRING(2.02 48.0, 2.03 48.0)');
        DB::table('sncf_line_geometries')->insert([
            ['code_ligne' => '123456', 'libelle' => 'Exploitée', 'idgaia' => 'g1', 'geom' => DB::raw("ST_GeomFromText('LINESTRING(2.0 48.0, 2.02 48.0)', 4326)")],
            ['code_ligne' => '123456', 'libelle' => 'Fermée', 'idgaia' => 'g2', 'geom' => DB::raw("ST_GeomFromText('LINESTRING(2.02 48.0, 2.03 48.0)', 4326)")],
        ]);

        // Wikipédia : ouverture de toute la ligne en 1900, fermeture de la portion de B en 2015.
        $this->event('opening', '1900-06-01', null);
        $this->event('closure', '2015-12-01', 'LINESTRING(2.02 48.0, 2.03 48.0)');
    }

    private function segment(Era $era, string $wkt, string $status, string $traffic, string $source): int
    {
        return (int) DB::table('line_segments')->insertGetId([
            'line_id' => $this->line->id, 'era_id' => $era->id, 'status' => $status, 'traffic_type' => $traffic, 'source' => $source,
            'geom' => DB::raw("ST_GeomFromText('{$wkt}', 4326)"),
            'length_meters' => (int) DB::selectOne('SELECT ST_Length(ST_GeomFromText(?, 4326)::geography) AS m', [$wkt])->m,
        ]);
    }

    /** @param  array<string, string>  $properties */
    private function trace(string $source, string $layer, string $wkt, array $properties = []): void
    {
        static $n = 0;
        DB::table('source_traces')->insert([
            'source' => $source, 'source_ref' => $source.'-'.(++$n), 'layer' => $layer, 'properties' => json_encode($properties),
            'line_id' => $this->line->id, 'seen_at' => now(), 'geom' => DB::raw("ST_Multi(ST_GeomFromText('{$wkt}', 4326))"),
        ]);
    }

    private function event(string $type, string $date, ?string $section): void
    {
        DB::table('line_events')->insert([
            'line_id' => $this->line->id, 'type' => $type, 'event_date' => $date, 'description' => $type, 'source' => 'wikipedia',
            'section_resolution' => $section === null ? 'whole_line' : 'trace',
            'section_from' => $section === null ? null : 'Bravo',
            'section_geom' => $section === null ? null : DB::raw("ST_Multi(ST_GeomFromText('{$section}', 4326))"),
        ]);
    }

    /** @return object{classification_grade: string, placement_grade: string, accuracy_grade: string, trace_grade: string, evidence: string} */
    private function audit(string $segment): object
    {
        return DB::table('segment_audits')->where('segment_id', $this->seg[$segment])->where('scope', 'segment')->sole();
    }

    private function runAudit(): void
    {
        $this->artisan('vff:audit-segments', ['--line' => ['123456']])->assertSuccessful();
    }

    public function test_the_pass_grades_each_segment_with_its_evidence(): void
    {
        $this->runAudit();

        $this->assertSame(2, DB::table('segment_audits')->where('scope', 'line')->count());
        $this->assertSame(4, DB::table('segment_audits')->where('scope', 'segment')->count());

        $a = $this->audit('A');
        $this->assertSame(['good', 'good', 'good'], [$a->classification_grade, $a->placement_grade, $a->trace_grade]);
        $evidence = json_decode($a->evidence, true);
        $this->assertSame('osm', $evidence['placement']['reference'], 'la BD TOPO est la source du tracé : la référence est OSM');
        $this->assertEqualsWithDelta(1.0, $evidence['placement']['median_m'], 0.2);
        $sources = array_column($evidence['classification']['verdicts'], 'agrees', 'source');
        $this->assertSame(['wikipedia' => true, 'sncf' => true, 'bdtopo' => true, 'osm' => true], $sources);

        $b = $this->audit('B');
        $this->assertSame('good', $b->classification_grade, 'Wikipédia et SNCF la disent fermée');
        $this->assertSame('poor', $b->placement_grade);
        $this->assertSame(['median_over_tolerance'], json_decode($b->evidence, true)['placement']['reasons']);
        $this->assertSame('poor', $b->trace_grade, 'forme SNCF généralisée alors que la voie OSM est connue');
        $this->assertNotNull(DB::table('segment_audits')->where('segment_id', $this->seg['B'])->value('geom'), 'géométrie recopiée');

        $a2 = $this->audit('A2');
        $this->assertSame('poor', $a2->classification_grade);
        $this->assertContains('rule_passenger_only_off_lgv', json_decode($a2->evidence, true)['classification']['reasons']);

        $b2 = json_decode($this->audit('B2')->evidence, true)['classification'];
        $this->assertSame(['source_contradicts'], $b2['reasons']);
        $this->assertEquals(['source' => 'wikipedia', 'agrees' => false, 'why' => 'expected_closed'],
            array_intersect_key($b2['verdicts'][0], ['source' => 0, 'agrees' => 0, 'why' => 0]));

        $summary = DB::table('segment_audits')->where('scope', 'line')->where('era_id', $this->current->id)->sole();
        $this->assertSame('poor', $summary->placement_grade, 'B fait plus de 10 % de la longueur');
        $this->assertGreaterThan(0, $summary->score);
    }

    public function test_dry_run_writes_nothing_and_a_new_pass_replaces_the_old_one(): void
    {
        $this->artisan('vff:audit-segments', ['--line' => ['rfn-123456'], '--dry-run' => true])->assertSuccessful();
        $this->assertDatabaseCount('segment_audits', 0);

        $this->runAudit();
        $this->runAudit();
        $this->assertDatabaseCount('segment_audits', 6);

        $this->artisan('vff:audit-segments', ['--line' => ['123456'], '--era' => [2030]])->assertSuccessful();
        $this->assertDatabaseCount('segment_audits', 6);
    }

    public function test_an_era_without_segments_loses_its_old_audit(): void
    {
        $this->runAudit();
        $this->assertSame(3, DB::table('segment_audits')->where('era_id', $this->past->id)->count());

        // La ligne perd tous ses tronçons de 2020 : la synthèse et les tronçons de 2020 disparaissent.
        DB::table('line_segments')->where('era_id', $this->past->id)->delete();
        $this->runAudit();
        $this->assertSame(0, DB::table('segment_audits')->where('era_id', $this->past->id)->count());
        $this->assertSame(3, DB::table('segment_audits')->where('era_id', $this->current->id)->count());

        // Plus aucun tronçon : la passe complète, qui ne visite plus la ligne, retire le reste.
        DB::table('line_segments')->where('line_id', $this->line->id)->delete();
        $this->artisan('vff:audit-segments')->assertSuccessful();
        $this->assertDatabaseCount('segment_audits', 0);
    }

    public function test_invalid_options_are_refused(): void
    {
        $this->artisan('vff:audit-segments', ['--shard' => '3/2'])->assertFailed();
        $this->artisan('vff:audit-segments', ['--line' => ['999999']])->assertFailed();
        $this->artisan('vff:audit-segments', ['--era' => [1855]])->assertFailed();
        $other = $this->line->id % 2 === 0 ? '2/2' : '1/2';
        $this->artisan('vff:audit-segments', ['--shard' => $other])->assertSuccessful();
        $this->assertSame(0, DB::table('segment_audits')->count(), 'la ligne est dans l’autre morceau');
    }

    public function test_the_admin_page_lists_lines_and_shows_one_with_its_map(): void
    {
        $this->runAudit();
        $admin = $this->member('admin');

        $this->get('/validate/audit/segments')->assertRedirect('/login');
        $this->actingAs($this->member('validator'))->get('/validate/audit/segments')->assertForbidden();

        $this->actingAs($admin)->get('/validate/audit/segments?era=2030&grade=poor&line=rfn-123456')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Validate/AuditSegments')
                ->where('year', 2030)
                ->where('filters.grade', 'poor')
                ->where('lines.total', 1)
                ->where('lines.items.0.slug', 'rfn-123456')
                ->where('summary.segments', 2)
                ->where('selected.line.slug', 'rfn-123456')
                ->where('selected.segments.0.segment_id', $this->seg['B'])
                ->where('selected.segments.0.geometry.type', 'LineString')
                ->has('regions')
                ->etc());

        $this->actingAs($admin)->get('/validate/audit/segments?era=2030&grade=good')
            ->assertInertia(fn (Assert $page) => $page->where('lines.total', 0)->where('selected', null)->etc());
        $this->actingAs($admin)->get('/validate/audit/segments?region=28&era=2030')
            ->assertInertia(fn (Assert $page) => $page->where('lines.total', 0)->etc());
        $this->actingAs($admin)->get('/validate/audit/segments?grade=terrible')->assertSessionHasErrors('grade');
    }

    public function test_csv_exports(): void
    {
        $this->runAudit();
        $admin = $this->member('admin');

        $lines = $this->actingAs($admin)->get('/validate/audit/segments/export.csv?era=2030')->assertOk();
        $this->assertStringContainsString('private', (string) $lines->headers->get('Cache-Control'));
        $rows = array_map('str_getcsv', array_filter(explode("\n", ltrim($lines->streamedContent(), "\u{FEFF}"))));
        $this->assertSame('line_code', $rows[0][0]);
        $this->assertSame('123456', $rows[1][0]);

        $segments = $this->actingAs($admin)->get('/validate/audit/segments/export.csv?era=2030&line=rfn-123456')->assertOk()->streamedContent();
        $this->assertStringContainsString('median_over_tolerance', $segments);
        $this->assertCount(3, array_filter(explode("\n", $segments)));

        $this->actingAs($this->member('contributor'))->get('/validate/audit/segments/export.csv')->assertForbidden();
    }

    public function test_rest_api(): void
    {
        $this->getJson('/api/v1/lines/rfn-123456/audit')->assertNotFound()->assertJsonPath('error', 'audit_not_computed');
        $this->runAudit();

        $this->getJson('/api/v1/lines/rfn-123456/audit')->assertOk()
            ->assertJsonPath('era_year', 2030)
            ->assertJsonPath('line.code', '123456')
            ->assertJsonPath('grades.placement', 'poor')
            ->assertJsonPath('segments.0.segment_id', $this->seg['B'])
            ->assertJsonPath('segments.0.evidence.trace.reasons.0', 'precise_trace_available')
            ->assertJsonMissingPath('segments.0.geometry');
        $this->assertCount(2, $this->getJson('/api/v1/lines/rfn-123456/audit?era=2020&grade=poor')->assertOk()->json('segments'));
        $this->assertSame([], $this->getJson('/api/v1/lines/rfn-123456/audit?era=2020&grade=good')->assertOk()->json('segments'));
        $this->getJson('/api/v1/lines/rfn-123456/audit?grade=bad')->assertUnprocessable()->assertJsonValidationErrors('grade');
        $this->getJson('/api/v1/lines/rfn-123456/audit?era=1700')->assertUnprocessable();
        $this->getJson('/api/v1/lines/rfn-999999/audit')->assertNotFound();
    }

    public function test_rest_api_pages_the_segments(): void
    {
        $this->runAudit();
        $all = $this->getJson('/api/v1/lines/rfn-123456/audit?era=2020')->assertOk()
            ->assertJsonPath('segments_total', 2)->assertJsonPath('limit', 50)->assertJsonPath('offset', 0)->json('segments');
        $this->assertCount(2, $all);

        $first = $this->getJson('/api/v1/lines/rfn-123456/audit?era=2020&limit=1')->assertOk()
            ->assertJsonPath('segments_total', 2)->assertJsonPath('limit', 1);
        $this->assertSame([$all[0]['segment_id']], array_column($first->json('segments'), 'segment_id'));
        $next = $this->getJson('/api/v1/lines/rfn-123456/audit?era=2020&limit=1&offset=1')->assertOk()->assertJsonPath('offset', 1);
        $this->assertSame([$all[1]['segment_id']], array_column($next->json('segments'), 'segment_id'));
        // Au-delà de la fin : aucune ligne, le total reste donné ; la synthèse porte sur tous les tronçons.
        $this->getJson('/api/v1/lines/rfn-123456/audit?era=2020&offset=5')->assertOk()
            ->assertJsonPath('segments', [])->assertJsonPath('segments_total', 2)->assertJsonPath('worst_grade', $first->json('worst_grade'));

        $this->getJson('/api/v1/lines/rfn-123456/audit?limit=0')->assertUnprocessable()->assertJsonValidationErrors('limit');
        $this->getJson('/api/v1/lines/rfn-123456/audit?limit=201')->assertUnprocessable()->assertJsonValidationErrors('limit');
        $this->getJson('/api/v1/lines/rfn-123456/audit?offset=-1')->assertUnprocessable()->assertJsonValidationErrors('offset');

        // Pagination documentée (Scramble lit les attributs du contrôleur).
        $operation = $this->getJson('/docs/api.json')->assertOk()->json('paths./lines/{line}/audit.get');
        /** @var array<string, array<string, mixed>> $parameters */
        $parameters = array_column((array) $operation['parameters'], null, 'name');
        $this->assertSame(50, $parameters['limit']['schema']['default'] ?? null);
        $this->assertSame(0, $parameters['offset']['schema']['default'] ?? null);
        $this->assertStringContainsString('segments_total', (string) $operation['description']);
    }

    public function test_graphql_and_mcp(): void
    {
        $query = fn (string $args = '') => ['query' => '{ line(slug: "rfn-123456") { audit'.$args.' { era_year worst_grade score grades { placement trace } segments(grade: POOR) { segment_id status worst_grade evidence } } } }'];
        $this->postJson('/graphql', $query())->assertOk()->assertJsonPath('data.line.audit', null);
        $this->runAudit();

        $audit = $this->postJson('/graphql', $query('(era: 2020)'))->assertOk()->json('data.line.audit');
        $this->assertSame(2020, $audit['era_year']);
        $this->assertSame('POOR', $audit['worst_grade']);
        $this->assertEqualsCanonicalizing([(string) $this->seg['A2'], (string) $this->seg['B2']], array_map('strval', array_column($audit['segments'], 'segment_id')));
        $this->assertArrayHasKey('classification', $audit['segments'][0]['evidence']);
        $this->assertNotEmpty($this->postJson('/graphql', $query('(era: 1855)'))->json('errors'));

        $call = fn (array $arguments) => $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'line_audit', 'arguments' => $arguments]])
            ->assertOk()->json('result');
        $result = $call(['line' => '123456', 'grade' => 'poor']);
        $this->assertFalse($result['isError']);
        $payload = json_decode($result['content'][0]['text'], true);
        $this->assertSame(1, $payload['segments_total']);
        $this->assertSame('poor', $payload['segments'][0]['worst_grade']);
        $this->assertArrayHasKey('attribution', $payload);

        $missing = $call(['line' => '123456', 'era' => 1990]);
        $this->assertTrue($missing['isError']);
        $this->assertStringContainsString('audit_not_computed', $missing['content'][0]['text']);
    }
}

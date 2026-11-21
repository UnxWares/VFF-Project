<?php

namespace Tests\Feature\StationAudit;

use App\Models\Line;
use App\Services\Materials\SourceCache;
use App\Services\StationAudit\BdtopoStationZones;
use App\Services\StationAudit\WikidataStationDates;
use App\Services\WikimediaClient;
use App\Support\StationNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\Support\ActsAsRoles;
use Tests\TestCase;

/**
 * Audit des gares : passe `vff:audit-stations`, import des dates Wikidata, page d'administration,
 * export CSV, API REST et GraphQL, sur trois gares écrites à la main le long d'une ligne
 * est-ouest (latitude 48°, 10 m ≈ 0,000134° de longitude) :
 *
 *   Doulon (2,000)   SNCF retenue ; OSM et Wikidata d'accord entre eux à 465 m : minoritaire
 *   Argentan (2,010) SNCF retenue ; OSM à 10 m ; liste par PK recopiée ; zone BD TOPO autour
 *   Messei (2,020)   SNCF seule, Wikidata à 2 km : désaccord
 */
class StationAuditTest extends TestCase
{
    use ActsAsRoles;
    use MakesGraphQLRequests;
    use RefreshDatabase;

    private const LON_PER_10_M = 0.000134;

    private string $cacheDir;

    /** @var array<string, int> */
    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withCookie('locale', 'fr');
        $this->cacheDir = sys_get_temp_dir().'/vff-audit-gares-test-'.getmypid();
        $this->app->instance(BdtopoStationZones::class, new BdtopoStationZones($this->cacheDir, 0));
        $this->app->instance(WikidataStationDates::class, new WikidataStationDates(new WikimediaClient, new SourceCache($this->cacheDir), 0));

        $line = Line::factory()->create(['code' => '405000', 'slug' => 'argentan-granville', 'name' => "Ligne d'Argentan à Granville"]);
        DB::insert("INSERT INTO line_reference_geometries (line_id, length_m, sources, geom, created_at, updated_at)
            VALUES (?, 1500, '[]', ST_Multi(ST_GeomFromText('LINESTRING(1.995 48, 2.025 48)', 4326)), now(), now())", [$line->id]);

        $this->ids['Doulon'] = $this->station('doulon', 'Doulon', 2.0, 'active', ['opened_at' => '1857-01-01', 'opened_precision' => 'year'], ['opened_at' => 'wikidata']);
        $this->ids['Argentan'] = $this->station('argentan', 'Argentan', 2.01, 'active',
            ['opened_at' => '1866-01-01', 'opened_precision' => 'year', 'wikidata_id' => 'Q1'], ['opened_at' => 'inferred:line_event']);
        $this->ids['Messei'] = $this->station('messei', 'Messei', 2.02, 'declassified',
            ['opened_at' => '1874-01-01', 'opened_precision' => 'year', 'closed_at' => '1938-01-01', 'closed_precision' => 'year'],
            ['opened_at' => 'wikipedia', 'closed_at' => 'inferred:line_event']);

        foreach ($this->ids as $id) {
            DB::table('station_lines')->insert(['station_id' => $id, 'line_id' => $line->id, 'method' => 'sncf_pk', 'first_year' => 1866,
                'last_year' => null, 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->source('sncf', 'D', $this->ids['Doulon'], 'Doulon', 2.0);
        $this->source('osm', 'n1', $this->ids['Doulon'], 'Doulon', 2.0 + 46.5 * self::LON_PER_10_M);
        $this->source('wikidata', 'Q9', $this->ids['Doulon'], 'Doulon', 2.0 + 46.1 * self::LON_PER_10_M);
        $this->source('sncf', 'A', $this->ids['Argentan'], 'Argentan', 2.01);
        $this->source('osm', 'n2', $this->ids['Argentan'], 'Argentan', 2.01 + self::LON_PER_10_M);
        $this->source('lignes-oubliees', 'lo-1', $this->ids['Argentan'], 'Argentan', 2.01, ['geocoding' => 'station', 'precision_m' => 0]);
        $this->source('sncf', 'M', $this->ids['Messei'], 'Messei', 2.02);
        $this->source('wikidata', 'Q8', $this->ids['Messei'], 'Messei', 2.02 + 0.027);
    }

    protected function tearDown(): void
    {
        try {
            parent::tearDown();
        } finally {
            File::deleteDirectory($this->cacheDir);
        }
    }

    private function station(string $slug, string $name, float $lon, string $status, array $attrs, array $provenance): int
    {
        return (int) DB::table('stations')->insertGetId([
            'slug' => $slug, 'name' => $name, 'type' => 'gare', 'status' => $status, 'department_code' => '61',
            'position_source' => 'sncf', 'provenance' => json_encode($provenance),
            'geom' => DB::raw(sprintf('ST_SetSRID(ST_MakePoint(%F, 48), 4326)', $lon)),
            'created_at' => now(), 'updated_at' => now(), ...$attrs,
        ]);
    }

    private function source(string $source, string $ref, int $stationId, string $name, float $lon, array $props = []): void
    {
        DB::table('station_sources')->insert([
            'source' => $source, 'source_ref' => $ref, 'station_id' => $stationId, 'name' => $name, 'name_norm' => StationNames::normalize($name),
            'kind' => 'gare', 'lifecycle' => 'unknown', 'properties' => json_encode($props), 'seen_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
            'geom' => DB::raw(sprintf('ST_SetSRID(ST_MakePoint(%F, 48), 4326)', $lon)),
        ]);
    }

    private function fakeSources(): void
    {
        $zone = ['type' => 'Feature', 'properties' => ['cleabs' => 'EQ1', 'nature' => 'Gare voyageurs et fret', 'toponyme' => "Gare d'Argentan", 'etat_de_l_objet' => 'En service'],
            'geometry' => ['type' => 'MultiPolygon', 'coordinates' => [[[[2.0099, 47.9999, -1000], [2.0101, 47.9999, -1000], [2.0101, 48.0001, -1000], [2.0099, 48.0001, -1000], [2.0099, 47.9999, -1000]]]]]];
        Http::fake([
            'data.geopf.fr/*' => Http::response(['type' => 'FeatureCollection', 'features' => [$zone]]),
            'www.wikidata.org/*' => Http::response(['entities' => ['Q1' => ['claims' => [
                'P1619' => [['id' => 'Q1$x', 'rank' => 'normal', 'mainsnak' => ['datavalue' => ['value' => ['time' => '+1866-05-21T00:00:00Z', 'precision' => 11]]]]],
            ]], 'Q8' => ['claims' => []], 'Q9' => ['claims' => []]]]),
        ]);
    }

    private function audit(): void
    {
        $this->fakeSources();
        $this->artisan('vff:import-station-wikidata-dates')->assertSuccessful();
        $this->artisan('vff:audit-stations', ['--line' => ['405000']])->assertSuccessful();
    }

    public function test_the_pass_grades_each_station_and_writes_nothing_else(): void
    {
        $before = DB::table('stations')->orderBy('id')->get(['id', 'opened_at', 'closed_at', 'geom'])->toJson();
        $this->audit();

        $a = DB::table('station_audits')->get()->keyBy('station_id');
        $this->assertCount(3, $a);
        $this->assertSame('agree', $a[$this->ids['Argentan']]->placement_grade);
        $this->assertSame(0, (int) $a[$this->ids['Argentan']]->nearest_agreeing_m);
        $this->assertSame('disagree', $a[$this->ids['Doulon']]->placement_grade);
        $this->assertSame('disagree', $a[$this->ids['Messei']]->placement_grade);
        $this->assertStringContainsString('retained_outvoted', (string) $a[$this->ids['Doulon']]->checks);
        $this->assertStringNotContainsString('retained_outvoted', (string) $a[$this->ids['Messei']]->checks);

        $positions = json_decode((string) $a[$this->ids['Argentan']]->positions, true);
        $this->assertSame(['bdtopo', 'lignes-oubliees', 'osm', 'sncf'], collect($positions)->pluck('source')->sort()->values()->all());
        $copy = collect($positions)->firstWhere('source', 'lignes-oubliees');
        $this->assertTrue($copy['derived']);
        // Familles comptées : SNCF, OSM, BD TOPO (la copie de liste ne compte pas).
        $this->assertSame(3, (int) $a[$this->ids['Argentan']]->families);
        $this->assertSame(0, (int) $a[$this->ids['Argentan']]->track_offset_m);

        $this->assertSame('estimated', $a[$this->ids['Argentan']]->opening_grade);
        $this->assertSame('not_applicable', $a[$this->ids['Argentan']]->closure_grade);
        $this->assertSame('estimated', $a[$this->ids['Messei']]->closure_grade);
        $this->assertSame('28', $a[$this->ids['Messei']]->region_code);
        $this->assertSame($before, DB::table('stations')->orderBy('id')->get(['id', 'opened_at', 'closed_at', 'geom'])->toJson());
    }

    public function test_wikidata_dates_are_compared_without_replacing_anything(): void
    {
        $this->fakeSources();
        $this->artisan('vff:import-station-wikidata-dates', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame(0, DB::table('station_date_candidates')->count());

        $this->artisan('vff:import-station-wikidata-dates')
            ->expectsOutputToContain('1 gare(s) changeraient')->assertSuccessful();
        $c = DB::table('station_date_candidates')->sole();
        $this->assertSame([$this->ids['Argentan'], 'opening', '1866-05-21', 'day', 'more_precise', '1866-01-01', 'inferred:line_event'],
            [(int) $c->station_id, $c->kind, (string) $c->date, $c->precision, $c->comparison, (string) $c->vff_date, $c->vff_source]);
        $this->assertSame('1866-01-01', (string) DB::table('stations')->where('id', $this->ids['Argentan'])->value('opened_at'));

        // Relance hors ligne : rien n'est redemandé, le résultat est le même.
        Http::fake(fn () => throw new \RuntimeException('aucune requête attendue'));
        $this->artisan('vff:import-station-wikidata-dates', ['--from-cache' => true])->assertSuccessful();
        $this->assertSame(1, DB::table('station_date_candidates')->count());
    }

    public function test_scope_options(): void
    {
        $this->fakeSources();
        $this->artisan('vff:audit-stations', ['--station' => ['messei'], '--dry-run' => true])->assertSuccessful();
        $this->assertSame(0, DB::table('station_audits')->count());
        $this->artisan('vff:audit-stations', ['--shard' => '0/1', '--no-bdtopo' => true])->assertSuccessful();
        $this->artisan('vff:audit-stations', ['--shard' => '3/2'])->assertExitCode(2);
        $this->artisan('vff:audit-stations', ['--station' => ['messei'], '--no-bdtopo' => true])->assertSuccessful();
        $this->assertTrue(DB::table('station_audits')->where('station_id', $this->ids['Messei'])->exists());
    }

    public function test_the_admin_page_is_for_administrators_only(): void
    {
        $this->audit();
        $this->get('/validate/audit/stations')->assertRedirect();
        foreach (['visitor', 'contributor', 'validator'] as $role) {
            $member = $this->member($role);
            $this->actingAs($member)->get('/validate/audit/stations')->assertForbidden();
            $this->actingAs($member)->getJson("/validate/audit/stations/{$this->ids['Doulon']}")->assertForbidden();
            $this->actingAs($member)->get('/validate/audit/stations/export.csv')->assertForbidden();
        }
    }

    public function test_the_review_queue_lists_disagreements_first_with_filters(): void
    {
        $this->audit();
        $admin = $this->member('admin');
        $this->actingAs($admin)->get('/validate/audit/stations')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Validate/AuditStations')
                ->where('filters.grade', 'disagree')
                ->where('stations.total', 2)
                ->where('stations.items.0.name', 'Messei')
                ->where('summary.placement.agree', 1)
                ->where('summary.checks.retained_outvoted', 1)
                ->where('thresholds.agree_m', 20));
        $this->actingAs($admin)->get('/validate/audit/stations?grade=all&check=retained_outvoted&grade=autre')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('filters.grade', 'disagree')->where('stations.total', 1)
                ->where('stations.items.0.slug', 'doulon'));
        $this->actingAs($admin)->get('/validate/audit/stations?grade=all&q=argen&line=405000')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('stations.total', 1));
    }

    public function test_detail_and_csv_export(): void
    {
        $this->audit();
        $admin = $this->member('admin');
        $this->actingAs($admin)->getJson("/validate/audit/stations/{$this->ids['Argentan']}")->assertOk()
            ->assertJsonPath('placement_grade', 'agree')
            ->assertJsonPath('lines.0.code', '405000')
            ->assertJsonPath('tracks.0.code', '405000')
            ->assertJsonPath('candidates.0.comparison', 'more_precise')
            ->assertJsonPath('candidates.0.would_replace', true)
            ->assertJsonCount(4, 'positions');
        $this->actingAs($admin)->getJson('/validate/audit/stations/999999')->assertNotFound();

        $response = $this->actingAs($admin)->get('/validate/audit/stations/export.csv?grade=all')->assertOk();
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $csv = $response->streamedContent();
        $lines = array_values(array_filter(explode("\n", $csv)));
        $this->assertCount(4, $lines);
        $this->assertStringContainsString('placement_grade', $lines[0]);
        $this->assertStringContainsString('doulon', $csv);
    }

    public function test_rest_and_graphql_expose_the_audit(): void
    {
        $this->getJson('/api/v1/stations/argentan/audit')->assertNotFound();
        $this->audit();
        $json = $this->getJson('/api/v1/stations/argentan/audit')->assertOk()
            ->assertJsonPath('data.placement.grade', 'agree')
            ->assertJsonPath('data.dates.opening_grade', 'estimated')
            ->assertJsonPath('data.date_candidates.0.date', '1866-05-21')
            ->assertJsonPath('data.date_candidates.0.provenance.provider', 'wikidata')
            ->json('data.placement.positions');
        $byName = collect($json)->keyBy('source');
        // lignes-oubliées n'est pas redistribuable : écart donné, coordonnées retirées.
        $this->assertNull($byName['lignes-oubliees']['lon']);
        $this->assertNotNull($byName['osm']['lon']);
        $this->assertSame('ign', $byName['bdtopo']['provenance']['provider']);

        $checks = $this->graphQL('{ station(slug: "doulon") { audit { placement { grade families positions { source distance_m } } checks { code } date_candidates { comparison } } } }')
            ->assertJsonMissingPath('errors')
            ->assertJsonPath('data.station.audit.placement.grade', 'disagree')
            ->json('data.station.audit.checks.*.code');
        // Ouverte en 1857, avant la première desserte connue de sa ligne (1866) ; position minoritaire.
        $this->assertSame(['opening_before_lines', 'retained_outvoted'], $checks);
    }
}

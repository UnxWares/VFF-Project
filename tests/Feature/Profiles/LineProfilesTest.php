<?php

namespace Tests\Feature\Profiles;

use App\Models\LineProfile;
use App\Services\Profiles\GeoplateformeClient;
use App\Services\Profiles\LineProfileBuilder;
use App\Services\Profiles\LineProfiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * Profil en long de bout en bout, services de l'IGN simulés (Http::fake) : calcul par
 * vff:compute-line-profiles (altitude de la voie BD TOPO, terrain RGE ALTI, tunnel interpolé,
 * cache disque, politesse), puis lecture par la fiche ligne, l'API REST, GraphQL et l'outil MCP.
 *
 * Ligne du mini-réseau : Paris-Saint-Lazare - Versailles-Rive-Droite (15,9 km sur le tracé,
 * trois sommets). BD TOPO simulée : voie au sol de Paris au deuxième sommet (altitude 30 à 60 m), puis
 * tunnel sans altitude jusqu'au milieu du second segment ; au-delà, terrain RGE ALTI à 120 m.
 */
class LineProfilesTest extends TestCase
{
    use BuildsRailNetwork;
    use RefreshDatabase;

    private string $cacheDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRailNetwork();
        // Commune fictive qui couvre l'ouest de Paris : tout le tracé est « en France ».
        DB::statement("INSERT INTO commune_contours (code_insee, nom, departement, geom) VALUES
            ('99999', 'Commune de test', '99', ST_GeomFromText('MULTIPOLYGON(((2.0 48.7, 2.5 48.7, 2.5 49.0, 2.0 49.0, 2.0 48.7)))', 4326))");

        $this->cacheDir = sys_get_temp_dir().'/vff-profil-test-'.bin2hex(random_bytes(4));
        $this->app->instance(GeoplateformeClient::class, new GeoplateformeClient($this->cacheDir, 0));
        // Services simulés : chaque test appelle fakeIgn() (les simulations de Http::fake s'ajoutent,
        // la première qui correspond répond : le test d'erreur pose la sienne seule).
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->cacheDir);
        parent::tearDown();
    }

    private function fakeIgn(): void
    {
        $mid = [2.1347 + (2.2400 - 2.1347) / 2, 48.8091 + (48.8530 - 48.8091) / 2];
        Http::fake([
            'data.geopf.fr/wfs/*' => Http::response(['type' => 'FeatureCollection', 'features' => [
                ['type' => 'Feature', 'properties' => ['position_par_rapport_au_sol' => '0'], 'geometry' => [
                    'type' => 'LineString', 'coordinates' => [[2.3253, 48.8763, 30.0], [2.2400, 48.8530, 60.0]],
                ]],
                ['type' => 'Feature', 'properties' => ['position_par_rapport_au_sol' => '-1'], 'geometry' => [
                    'type' => 'LineString', 'coordinates' => [[2.2400, 48.8530, -1000.0], [$mid[0], $mid[1], -1000.0]],
                ]],
            ]]),
            'data.geopf.fr/altimetrie/*' => fn (Request $request) => Http::response([
                'elevations' => array_fill(0, count(explode('|', (string) $request['lon'])), 120.0),
            ]),
        ]);
    }

    public function test_command_computes_the_profile_from_track_heights_terrain_and_an_interpolated_tunnel(): void
    {
        $this->fakeIgn();
        $this->artisan('vff:compute-line-profiles', ['--line' => [$this->line->slug]])
            ->expectsOutputToContain('points BD TOPO')
            ->assertSuccessful();

        $profile = LineProfile::where('line_id', $this->line->id)->firstOrFail();
        $this->assertSame('bdtopo-rge-alti', $profile->source);
        $this->assertSame($this->currentEra->id, $profile->era_id);
        $this->assertSame(100, $profile->step_m);
        $this->assertEqualsWithDelta(15_880, $profile->length_m, 100);
        $this->assertGreaterThan(0, $profile->altitude_sources['bdtopo']);
        $this->assertGreaterThan(0, $profile->altitude_sources['rge_alti']);
        $this->assertGreaterThan(0, $profile->altitude_sources['interpolated']);
        $this->assertSame(0, $profile->altitude_sources['missing']);
        $this->assertEquals(30, $profile->points[0][1]);
        $this->assertEquals(120, $profile->points[count($profile->points) - 1][1]);
        $this->assertSame('tunnel', $profile->structures[0]['kind']);
        $this->assertSame(30.0, $profile->min_altitude_m);
        $this->assertSame(120.0, $profile->max_altitude_m);
        // Les deux gares, dans l'ordre du parcours (de Paris, plus petit PK, vers Versailles).
        $this->assertSame(['paris-saint-lazare', 'versailles-rive-droite'], array_column($profile->stations, 'slug'));
        $this->assertSame([0, 17_250], array_column($profile->stations, 'pk_m'));
        $this->assertLessThan(50, $profile->stations[0]['distance_m']);

        Http::assertSent(fn (Request $r) => $r->header('User-Agent')[0] === GeoplateformeClient::USER_AGENT);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'altimetrie') && $r['resource'] === 'ign_rge_alti_wld');
        $this->assertFileExists($this->cacheDir.'/.nobackup');
    }

    public function test_a_trace_drawn_from_the_high_pk_end_is_still_read_from_the_lowest_pk(): void
    {
        // Tracé numérisé depuis Versailles (PK 17+250) et gares rangées dans l'ordre du tracé :
        // la première gare (position 1) a le plus grand PK.
        DB::table('line_segments')->where('line_id', $this->line->id)->update(['geom' => DB::raw('ST_Reverse(geom)')]);
        DB::table('line_stops')->where('line_id', $this->line->id)->where('station_id', $this->origin->id)->update(['position' => 3]);
        DB::table('line_stops')->where('line_id', $this->line->id)->where('station_id', $this->destination->id)->update(['position' => 1]);
        DB::table('line_stops')->where('line_id', $this->line->id)->where('station_id', $this->origin->id)->update(['position' => 2]);
        $this->fakeIgn();

        $this->artisan('vff:compute-line-profiles', ['--line' => [$this->line->slug]])->assertSuccessful();

        $profile = LineProfile::where('line_id', $this->line->id)->firstOrFail();
        $this->assertSame(['paris-saint-lazare', 'versailles-rive-droite'], array_column($profile->stations, 'slug'));
        $this->assertLessThan(50, $profile->stations[0]['distance_m']);
        $this->assertEquals(30, $profile->points[0][1]);
        $this->assertEquals(120, $profile->points[count($profile->points) - 1][1]);
        // De Paris (30 m) vers Versailles (120 m) : la déclivité la plus forte est une rampe.
        $this->assertSame('up', $profile->max_grade_direction);
    }

    public function test_a_profile_that_can_no_longer_be_computed_is_removed(): void
    {
        $this->fakeIgn();
        $this->artisan('vff:compute-line-profiles', ['--line' => [$this->line->slug]])->assertSuccessful();
        $this->assertSame(1, LineProfile::count());

        // Tracé retiré de toutes les ères : l'ancien profil ne doit plus être servi.
        DB::table('line_segments')->where('line_id', $this->line->id)->delete();
        $this->artisan('vff:compute-line-profiles', ['--line' => [$this->line->slug], '--force' => true])
            ->expectsOutputToContain('ancien profil supprimé')
            ->assertSuccessful();
        $this->assertSame(0, LineProfile::count());
        $this->getJson("/api/v1/lines/{$this->line->slug}/profile")->assertNotFound();

        // Sans profil enregistré, rien à signaler.
        $this->artisan('vff:compute-line-profiles', ['--line' => [$this->line->slug]])
            ->doesntExpectOutputToContain('ancien profil supprimé')
            ->assertSuccessful();
    }

    public function test_second_run_reads_the_disk_cache_and_dry_run_calls_nothing(): void
    {
        $this->fakeIgn();
        $this->artisan('vff:compute-line-profiles', ['--line' => [$this->line->slug]])->assertSuccessful();
        $calls = count(Http::recorded());
        $this->assertGreaterThan(0, $calls);

        // Nouveau client (donc nouveau compteur), même dossier de cache : aucun appel réseau.
        $this->app->instance(GeoplateformeClient::class, new GeoplateformeClient($this->cacheDir, 0));
        $this->app->forgetInstance(LineProfileBuilder::class);
        $this->artisan('vff:compute-line-profiles', ['--line' => [$this->line->slug]])
            ->expectsOutputToContain("Appels aux services de l'IGN : 0")
            ->assertSuccessful();
        $this->assertCount($calls, Http::recorded());

        LineProfile::query()->delete();
        $this->artisan('vff:compute-line-profiles', ['--line' => [$this->line->slug], '--dry-run' => true])
            ->expectsOutputToContain('pas 100 m')
            ->assertSuccessful();
        $this->assertSame(0, LineProfile::count());
        $this->assertCount($calls, Http::recorded());
    }

    public function test_command_without_line_picks_lines_without_profile(): void
    {
        $this->fakeIgn();
        $this->artisan('vff:compute-line-profiles', ['--limit' => 5])->assertSuccessful();
        $this->assertSame(1, LineProfile::count());

        $this->artisan('vff:compute-line-profiles', ['--limit' => 5])
            ->expectsOutput('Aucune ligne à traiter.')
            ->assertSuccessful();
        $this->artisan('vff:compute-line-profiles', ['--line' => ['ligne-inconnue']])
            ->expectsOutput('Ligne inconnue : ligne-inconnue');
    }

    public function test_service_error_is_reported_without_writing_a_profile(): void
    {
        Http::fake(['*' => Http::response('Service Unavailable', 503)]);

        $this->artisan('vff:compute-line-profiles', ['--line' => [$this->line->slug]])
            ->expectsOutputToContain('HTTP 503')
            ->assertFailed();
        $this->assertSame(0, LineProfile::count());
    }

    public function test_api_returns_404_before_computation_then_the_documented_profile(): void
    {
        $this->fakeIgn();
        $url = "/api/v1/lines/{$this->line->slug}/profile";
        $this->getJson($url)->assertNotFound()->assertJsonPath('error', 'profile_not_computed');

        $this->artisan('vff:compute-line-profiles', ['--line' => [$this->line->slug]])->assertSuccessful();

        $response = $this->getJson($url)->assertOk()
            ->assertJsonPath('line.slug', $this->line->slug)
            ->assertJsonPath('era_year', 2030)
            ->assertJsonPath('provenance.provider', 'ign')
            ->assertJsonPath('provenance.license_status', 'verified')
            ->assertJsonStructure([
                'step_m', 'length_m', 'trace_length_m',
                'summary' => ['min_altitude_m', 'max_altitude_m', 'ascent_m', 'descent_m', 'max_grade_permil', 'max_grade_from_m', 'max_grade_to_m', 'max_grade_direction'],
                'points', 'structures', 'altitude_sources', 'gaps', 'stations' => [['slug', 'name', 'distance_m', 'altitude_m', 'pk_m']], 'computed_at',
            ]);
        $this->assertGreaterThan(100, count($response->json('points')));

        $small = $this->getJson($url.'?max_points=20')->assertOk()->json('points');
        $this->assertLessThanOrEqual(20, count($small));
        $this->getJson($url.'?max_points=3')->assertUnprocessable()->assertJsonValidationErrors('max_points');
        $this->getJson('/api/v1/lines/rfn-999999/profile')->assertNotFound();
    }

    public function test_graphql_field_and_mcp_tool_expose_the_profile(): void
    {
        $this->fakeIgn();
        $query = ['query' => '{ line(slug: "'.$this->line->slug.'") { profile(max_points: 10) { era_year summary { max_altitude_m max_grade_direction } points { distance_m altitude_m } stations { slug pk_m } provenance { provider } } } }'];
        $this->postJson('/graphql', $query)->assertOk()->assertJsonPath('data.line.profile', null);

        $this->artisan('vff:compute-line-profiles', ['--line' => [$this->line->slug]])->assertSuccessful();

        $data = $this->postJson('/graphql', $query)->assertOk()->json('data.line.profile');
        $this->assertSame(2030, $data['era_year']);
        $this->assertSame(120.0, (float) $data['summary']['max_altitude_m']);
        $this->assertLessThanOrEqual(10, count($data['points']));
        $this->assertSame(0, $data['points'][0]['distance_m']);
        $this->assertSame('ign', $data['provenance']['provider']);

        $result = $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => [
                'name' => 'line_profile', 'arguments' => ['line' => $this->line->slug, 'max_points' => 10, 'lang' => 'en'],
            ]])->assertOk()->json('result');
        $this->assertFalse($result['isError']);
        $payload = json_decode($result['content'][0]['text'], true);
        $this->assertSame($this->line->slug, $payload['line']['slug']);
        $this->assertSame(['paris-saint-lazare', 'versailles-rive-droite'], array_column($payload['profile']['stations'], 'slug'));
        $this->assertStringContainsString('#profile', $payload['vff_url']);
        $this->assertArrayHasKey('attribution', $payload);
    }

    public function test_mcp_tool_reports_a_missing_profile(): void
    {
        $result = $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => [
                'name' => 'line_profile', 'arguments' => ['line' => $this->line->slug],
            ]])->assertOk()->json('result');

        $this->assertTrue($result['isError']);
        $this->assertSame('profile_not_computed', json_decode($result['content'][0]['text'], true)['error']);
    }

    public function test_line_sheet_receives_the_profile_only_once_computed(): void
    {
        $this->fakeIgn();
        $this->get('/lines/'.$this->line->slug)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('profile', null));

        $this->artisan('vff:compute-line-profiles', ['--line' => [$this->line->slug]])->assertSuccessful();

        $this->get('/lines/'.$this->line->slug)->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('profile.summary.max_altitude_m', 120)
                ->has('profile.stations', 2)
                ->where('profile.provenance.provider', 'ign'));

        // Profil d'une longue ligne (2 500 points) : la fiche en reçoit 600 au plus, l'API tous.
        $points = [];
        for ($i = 0; $i < 2500; $i++) {
            $points[] = [$i * 250, 100.0 + ($i % 50)];
        }
        LineProfile::where('line_id', $this->line->id)->update(['points' => json_encode($points), 'length_m' => 624_750]);
        $sheet = $this->get('/lines/'.$this->line->slug)->assertOk()->viewData('page')['props']['profile'];
        $this->assertLessThanOrEqual(LineProfiles::SHEET_MAX_POINTS, count($sheet['points']));
        $this->assertGreaterThan(500, count($sheet['points']));
        $this->assertEquals([0, 100], $sheet['points'][0]);
        $this->assertEquals([624_750, 149], $sheet['points'][count($sheet['points']) - 1]);
        $this->assertCount(2500, $this->getJson("/api/v1/lines/{$this->line->slug}/profile")->json('points'));
    }
}

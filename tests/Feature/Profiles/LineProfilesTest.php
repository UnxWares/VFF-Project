<?php

namespace Tests\Feature\Profiles;

use App\Models\LineProfile;
use App\Services\Profiles\GeoplateformeClient;
use App\Services\Profiles\LineProfileBuilder;
use App\Services\Profiles\LineProfiles;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * Profil en long de bout en bout, services de l'IGN simulés (Http::fake) : calcul par
 * vff:compute-line-profiles sur le tracé de référence (altitude de la voie BD TOPO, terrain RGE
 * ALTI, tunnel interpolé, cache par tuiles, politesse, points enregistrés par PK), puis lecture par
 * la fiche ligne, l'API REST, GraphQL et l'outil MCP.
 *
 * Ligne du mini-réseau : Paris-Saint-Lazare - Versailles-Rive-Droite (15,9 km sur le tracé,
 * trois sommets), tracé de référence = tracé de ses tronçons. BD TOPO simulée : voie au sol de Paris au deuxième sommet (altitude 30 à 60 m), puis
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
        DB::table('line_reference_parts')->insert([
            'line_id' => $this->line->id, 'source' => 'osm', 'source_trace_ids' => '[]', 'length_m' => 15_880,
            'geom' => DB::raw("(SELECT ST_Multi(geom) FROM line_segments WHERE line_id = {$this->line->id} LIMIT 1)"),
        ]);
        DB::table('station_lines')->insert([
            ['station_id' => $this->origin->id, 'line_id' => $this->line->id, 'method' => 'sncf_pk', 'pk_m' => 0, 'pk_source' => 'sncf'],
            ['station_id' => $this->destination->id, 'line_id' => $this->line->id, 'method' => 'sncf_pk', 'pk_m' => 17_250, 'pk_source' => 'sncf'],
        ]);
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
        try {
            parent::tearDown();
        } finally {
            (new Filesystem)->deleteDirectory($this->cacheDir);
        }
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
        $points = $profile->points()->get();
        $this->assertSame('bdtopo-rge-alti', $profile->source);
        $this->assertNull($profile->era_id);
        $this->assertSame(2, $profile->pk_anchors);
        $this->assertSame(100, $profile->step_m);
        $this->assertEqualsWithDelta(15_880, $profile->length_m, 100);
        $this->assertGreaterThan(0, $profile->altitude_sources['bdtopo']);
        $this->assertGreaterThan(0, $profile->altitude_sources['rge_alti']);
        $this->assertGreaterThan(0, $profile->altitude_sources['interpolated']);
        $this->assertSame(0, $profile->altitude_sources['missing']);
        $this->assertEquals(30, $points->first()->altitude_m);
        $this->assertEquals(120, $points->last()->altitude_m);
        $this->assertSame('bdtopo', $points->first()->source);
        $this->assertSame('rge_alti', $points->last()->source);
        $this->assertTrue($points->contains(fn ($p) => $p->interpolated && $p->structure === 'tunnel' && $p->source === null));
        // PK calé sur les deux gares (0 et 17 250 m pour 15,9 km de tracé) : il croît plus vite que la distance.
        $this->assertLessThan(60, abs($points->first()->pk_m));
        $this->assertEqualsWithDelta(17_250, $points->last()->pk_m, 60);
        $this->assertSame($profile->altitude_sources['bdtopo'], $points->where('source', 'bdtopo')->count());
        $this->assertSame('tunnel', $profile->structures[0]['kind']);
        $this->assertSame(30.0, $profile->min_altitude_m);
        $this->assertSame(120.0, $profile->max_altitude_m);
        // Les deux gares, dans l'ordre du parcours (de Paris, plus petit PK, vers Versailles).
        $this->assertSame(['paris-saint-lazare', 'versailles-rive-droite'], array_column($profile->stations, 'slug'));
        $this->assertSame([0, 17_250], array_column($profile->stations, 'pk_m'));
        $this->assertSame(['active', 'active'], array_column($profile->stations, 'status'));
        $this->assertLessThan(50, $profile->stations[0]['distance_m']);

        Http::assertSent(fn (Request $r) => $r->header('User-Agent')[0] === GeoplateformeClient::USER_AGENT);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'altimetrie') && $r['resource'] === 'ign_rge_alti_wld');
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'wfs') && $r['SORTBY'] === 'cleabs');
        $this->assertFileExists($this->cacheDir.'/.nobackup');
        // Cache par tuile fixe de 0,2° : Paris et Versailles tombent dans la tuile (11, 244).
        $this->assertFileExists($this->cacheDir.'/bdtopo-tuiles/11_244.json.gz');
        $this->assertNotEmpty(glob($this->cacheDir.'/rge-alti-noeuds/*.json.gz'));
    }

    public function test_pieces_of_the_reference_separated_by_a_long_gap_are_all_kept(): void
    {
        // Ancien prolongement déposé, 20 km plus loin vers l'ouest, absent des tronçons de l'ère
        // courante : il est dans le référentiel, donc dans le profil, après un trou.
        DB::table('line_reference_parts')->insert([
            'line_id' => $this->line->id, 'source' => 'archeoferroviaire', 'source_trace_ids' => '[]', 'length_m' => 7_300,
            'geom' => DB::raw("ST_GeomFromText('MULTILINESTRING((1.85 48.78, 1.75 48.78))', 4326)"),
        ]);
        $this->fakeIgn();

        $this->artisan('vff:compute-line-profiles', ['--line' => [$this->line->slug]])->assertSuccessful();

        $profile = LineProfile::where('line_id', $this->line->id)->firstOrFail();
        $this->assertSame(23_180, $profile->trace_length_m);
        $this->assertCount(1, $profile->gaps);
        $this->assertEqualsWithDelta(15_880 + 7_330, $profile->covered_m, 150);
        $this->assertGreaterThan(20_000, $profile->gaps[0]['to_m'] - $profile->gaps[0]['from_m']);
        $points = $profile->points()->get();
        $this->assertSame([0, 1], $points->pluck('piece')->unique()->values()->all());
        // Aucun point dans le trou : la courbe s'y interrompt.
        $gap = $profile->gaps[0];
        $this->assertSame(0, $points->filter(fn ($p) => $p->distance_m > $gap['from_m'] + 1 && $p->distance_m < $gap['to_m'] - 1)->count());

        $this->getJson("/api/v1/lines/{$this->line->slug}/profile")->assertOk()
            ->assertJsonPath('basis', 'reference')
            ->assertJsonPath('era_year', null)
            ->assertJsonCount(1, 'gaps');
    }

    public function test_a_trace_drawn_from_the_high_pk_end_is_still_read_from_the_lowest_pk(): void
    {
        // Tracé de référence numérisé depuis Versailles (PK 17+250).
        DB::table('line_reference_parts')->where('line_id', $this->line->id)->update(['geom' => DB::raw('ST_Reverse(geom)')]);
        $this->fakeIgn();

        $this->artisan('vff:compute-line-profiles', ['--line' => [$this->line->slug]])->assertSuccessful();

        $profile = LineProfile::where('line_id', $this->line->id)->firstOrFail();
        $points = $profile->points()->get();
        $this->assertSame(['paris-saint-lazare', 'versailles-rive-droite'], array_column($profile->stations, 'slug'));
        $this->assertLessThan(50, $profile->stations[0]['distance_m']);
        $this->assertEquals(30, $points->first()->altitude_m);
        $this->assertEquals(120, $points->last()->altitude_m);
        // De Paris (30 m) vers Versailles (120 m) : le parcours monte.
        $this->assertGreaterThan($points->first()->altitude_m, $points->last()->altitude_m);
    }

    public function test_projected_pk_do_not_give_the_direction(): void
    {
        // PK projetés sur le tracé, numérotés depuis Paris : ils suivent la géométrie, pas la ligne, le sens
        // d'assemblage (ici depuis Versailles) est gardé.
        DB::table('station_lines')->where('line_id', $this->line->id)->update(['pk_source' => 'projected_raw']);
        DB::table('station_lines')->where('station_id', $this->origin->id)->update(['pk_m' => 0]);
        DB::table('station_lines')->where('station_id', $this->destination->id)->update(['pk_m' => 17_250]);
        DB::table('lines')->where('id', $this->line->id)->update(['name' => 'Ligne sans destinations']);
        $this->fakeIgn();

        $this->artisan('vff:compute-line-profiles', ['--line' => [$this->line->slug]])->assertSuccessful();

        $profile = LineProfile::where('line_id', $this->line->id)->firstOrFail();
        $this->assertSame(['versailles-rive-droite', 'paris-saint-lazare'], array_column($profile->stations, 'slug'));
    }

    public function test_the_line_name_gives_the_direction_when_no_official_pk_exists(): void
    {
        DB::table('station_lines')->where('line_id', $this->line->id)->update(['pk_m' => null, 'pk_source' => null]);
        $this->fakeIgn();
        $names = DB::table('stations')->whereIn('id', [$this->origin->id, $this->destination->id])->pluck('name', 'id');
        DB::table('lines')->where('id', $this->line->id)->update(['name' => 'Ligne de '.$names[$this->destination->id].' à '.$names[$this->origin->id]]);

        $this->artisan('vff:compute-line-profiles', ['--line' => [$this->line->slug]])->assertSuccessful();

        $profile = LineProfile::where('line_id', $this->line->id)->firstOrFail();
        $this->assertSame(['versailles-rive-droite', 'paris-saint-lazare'], array_column($profile->stations, 'slug'));
        $this->assertEquals(120, $profile->points()->orderBy('seq')->first()->altitude_m);
    }

    public function test_without_pk_or_a_usable_name_the_assembly_direction_is_kept(): void
    {
        DB::table('station_lines')->where('line_id', $this->line->id)->update(['pk_m' => null, 'pk_source' => null]);
        DB::table('lines')->where('id', $this->line->id)->update(['name' => 'Ligne sans destinations']);
        $this->fakeIgn();

        $this->artisan('vff:compute-line-profiles', ['--line' => [$this->line->slug]])->assertSuccessful();

        $profile = LineProfile::where('line_id', $this->line->id)->firstOrFail();
        $this->assertSame(0, $profile->pk_anchors);
        $this->assertEquals(120, $profile->points()->orderBy('seq')->first()->altitude_m);
    }

    public function test_a_profile_that_can_no_longer_be_computed_is_removed(): void
    {
        $this->fakeIgn();
        $this->artisan('vff:compute-line-profiles', ['--line' => [$this->line->slug]])->assertSuccessful();
        $this->assertSame(1, LineProfile::count());

        // Tracé retiré du référentiel et de toutes les ères : l'ancien profil ne doit plus être servi.
        DB::table('line_reference_parts')->where('line_id', $this->line->id)->delete();
        DB::table('line_segments')->where('line_id', $this->line->id)->delete();
        $this->artisan('vff:compute-line-profiles', ['--line' => [$this->line->slug], '--force' => true])
            ->expectsOutputToContain('ancien profil supprimé')
            ->assertSuccessful();
        $this->assertSame(0, LineProfile::count());
        $this->assertSame(0, DB::table('line_profile_points')->count());
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
            ->assertJsonPath('basis', 'reference')
            ->assertJsonPath('pk_anchors', 2)
            ->assertJsonPath('provenance.provider', 'ign')
            ->assertJsonPath('provenance.license_status', 'verified')
            ->assertJsonStructure([
                'step_m', 'length_m', 'trace_length_m', 'covered_m', 'off_path_m', 'branches',
                'summary' => ['min_altitude_m', 'max_altitude_m', 'ascent_m', 'descent_m', 'max_grade_permil', 'max_grade_from_m', 'max_grade_to_m', 'max_grade_direction', 'max_grade_withheld'],
                'points', 'structures', 'altitude_sources', 'gaps', 'terrain_ranges', 'stations' => [['slug', 'name', 'distance_m', 'altitude_m', 'pk_m', 'status', 'offset_m']], 'computed_at',
            ]);
        $this->assertGreaterThan(100, count($response->json('points')));

        $small = $this->getJson($url.'?max_points=20')->assertOk()->json('points');
        $this->assertLessThanOrEqual(20, count($small));
        $this->getJson($url.'?max_points=3')->assertUnprocessable()->assertJsonValidationErrors('max_points');
        $this->getJson('/api/v1/lines/rfn-999999/profile')->assertNotFound();
        $this->assertCount(3, $response->json('points.0'));
    }

    public function test_points_and_altitude_at_a_pk_are_served_from_the_referential(): void
    {
        $this->fakeIgn();
        $base = "/api/v1/lines/{$this->line->slug}";
        $this->getJson("{$base}/profile/points")->assertNotFound()->assertJsonPath('error', 'profile_not_computed');
        $this->getJson("{$base}/altitude?pk_m=100")->assertNotFound()->assertJsonPath('error', 'profile_not_computed');

        $this->artisan('vff:compute-line-profiles', ['--line' => [$this->line->slug]])->assertSuccessful();

        $all = $this->getJson("{$base}/profile/points")->assertOk()
            ->assertJsonPath('provenance.provider', 'ign')
            ->assertJsonStructure(['points' => [['seq', 'distance_m', 'pk_m', 'altitude_m', 'source', 'interpolated', 'structure', 'piece', 'branch', 'lat', 'lng']]])
            ->json();
        $this->assertSame($all['count'], count($all['points']));
        $this->assertEqualsWithDelta(48.8763, $all['points'][0]['lat'], 0.001);

        $slice = $this->getJson("{$base}/profile/points?pk_from_m=1000&pk_to_m=2000")->assertOk()->json('points');
        $this->assertNotEmpty($slice);
        foreach ($slice as $p) {
            $this->assertGreaterThanOrEqual(1000, $p['pk_m']);
            $this->assertLessThanOrEqual(2000, $p['pk_m']);
        }
        $this->assertLessThanOrEqual(20, count($this->getJson("{$base}/profile/points?max_points=20")->json('points')));
        $this->getJson("{$base}/profile/points?pk_from_m=2000&pk_to_m=1000")->assertUnprocessable()->assertJsonValidationErrors('pk_to_m');

        // Au début de la ligne, voie au sol de 30 à 60 m (BD TOPO).
        $at = $this->getJson("{$base}/altitude?pk_m=500")->assertOk()->json();
        $this->assertSame(500, $at['pk_m']);
        $this->assertGreaterThan(29, $at['altitude_m']);
        $this->assertLessThan(40, $at['altitude_m']);
        $this->assertSame(['bdtopo'], $at['sources']);
        $this->getJson("{$base}/altitude?pk_m=900000")->assertNotFound()->assertJsonPath('error', 'altitude_unknown');
        $this->getJson("{$base}/altitude")->assertUnprocessable()->assertJsonValidationErrors('pk_m');
    }

    public function test_graphql_field_and_mcp_tool_expose_the_profile(): void
    {
        $this->fakeIgn();
        $query = ['query' => '{ line(slug: "'.$this->line->slug.'") { profile(max_points: 10) { basis era_year pk_anchors summary { max_altitude_m max_grade_direction } points { distance_m altitude_m pk_m } samples(pk_from_m: 1000, pk_to_m: 3000, max_points: 10) { seq pk_m altitude_m source interpolated piece lat lng } altitude_at(pk_m: 500) { altitude_m sources } stations { slug pk_m status } provenance { provider } } } }'];
        $this->postJson('/graphql', $query)->assertOk()->assertJsonPath('data.line.profile', null);

        $this->artisan('vff:compute-line-profiles', ['--line' => [$this->line->slug]])->assertSuccessful();

        $data = $this->postJson('/graphql', $query)->assertOk()->json('data.line.profile');
        $this->assertSame('reference', $data['basis']);
        $this->assertNull($data['era_year']);
        $this->assertNotEmpty($data['samples']);
        $this->assertLessThanOrEqual(10, count($data['samples']));
        $this->assertSame(['bdtopo'], $data['altitude_at']['sources']);
        $this->assertSame(120.0, (float) $data['summary']['max_altitude_m']);
        $this->assertLessThanOrEqual(10, count($data['points']));
        $this->assertSame(0, $data['points'][0]['distance_m']);
        $this->assertSame('ign', $data['provenance']['provider']);

        $result = $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => [
                'name' => 'line_profile', 'arguments' => ['line' => $this->line->slug, 'max_points' => 10, 'pk_m' => 500, 'lang' => 'en'],
            ]])->assertOk()->json('result');
        $this->assertFalse($result['isError']);
        $payload = json_decode($result['content'][0]['text'], true);
        $this->assertSame($this->line->slug, $payload['line']['slug']);
        $this->assertSame(['paris-saint-lazare', 'versailles-rive-droite'], array_column($payload['profile']['stations'], 'slug'));
        $this->assertStringContainsString('#profile', $payload['vff_url']);
        $this->assertSame(500, $payload['profile']['altitude_at_pk']['pk_m']);
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
        $profile = LineProfile::where('line_id', $this->line->id)->firstOrFail();
        DB::table('line_profile_points')->where('line_profile_id', $profile->id)->delete();
        $rows = [];
        for ($i = 0; $i < 2500; $i++) {
            $rows[] = ['line_profile_id' => $profile->id, 'line_id' => $this->line->id, 'seq' => $i, 'distance_m' => $i * 250, 'altitude_m' => 100.0 + ($i % 50)];
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('line_profile_points')->insert($chunk);
        }
        $profile->update(['length_m' => 624_750]);
        $sheet = $this->get('/lines/'.$this->line->slug)->assertOk()->viewData('page')['props']['profile'];
        $this->assertLessThanOrEqual(LineProfiles::SHEET_MAX_POINTS, count($sheet['points']));
        $this->assertGreaterThan(500, count($sheet['points']));
        $this->assertEquals([0, 100, null], $sheet['points'][0]);
        $this->assertEquals([624_750, 149, null], $sheet['points'][count($sheet['points']) - 1]);
        $this->assertCount(2500, $this->getJson("/api/v1/lines/{$this->line->slug}/profile")->json('points'));
    }

    public function test_stats_only_withholds_the_grade_when_only_the_terrain_is_known(): void
    {
        $this->fakeIgn();
        $this->artisan('vff:compute-line-profiles', ['--line' => [$this->line->slug]])->assertSuccessful();
        $profile = LineProfile::where('line_id', $this->line->id)->firstOrFail();
        $calls = count(Http::recorded());

        // Toute la ligne mesurée sur la voie : déclivité publiée.
        DB::table('line_profile_points')->where('line_profile_id', $profile->id)->whereNotNull('source')->update(['source' => 'bdtopo']);
        $this->artisan('vff:compute-line-profiles', ['--line' => [$this->line->slug], '--stats-only' => true])->assertSuccessful();
        $this->assertNotNull($profile->refresh()->max_grade_permil);

        // Comme une ligne déposée : altitudes du terrain seulement.
        DB::table('line_profile_points')->where('line_profile_id', $profile->id)->whereNotNull('source')->update(['source' => 'rge_alti']);
        $this->artisan('vff:compute-line-profiles', ['--line' => [$this->line->slug], '--stats-only' => true])
            ->expectsOutputToContain('déclivité non publiée pour 1')
            ->assertSuccessful();
        $this->assertCount($calls, Http::recorded());

        $profile->refresh();
        $this->assertNull($profile->max_grade_permil);
        $this->assertSame('terrain_only', $profile->max_grade_withheld);
        $this->getJson("/api/v1/lines/{$this->line->slug}/profile")->assertOk()
            ->assertJsonPath('summary.max_grade_permil', null)
            ->assertJsonPath('summary.max_grade_withheld', 'terrain_only')
            ->assertJsonPath('terrain_ranges.0.from_m', 0);
        $data = $this->postJson('/graphql', ['query' => '{ line(slug: "'.$this->line->slug.'") { profile { summary { max_grade_permil max_grade_withheld } terrain_ranges { from_m to_m } } } }'])
            ->assertOk()->json('data.line.profile');
        $this->assertNull($data['summary']['max_grade_permil']);
        $this->assertSame('terrain_only', $data['summary']['max_grade_withheld']);
    }

    public function test_positions_drawn_from_a_non_redistributable_trace_are_withheld_from_the_api(): void
    {
        // Tracé de référence d'une source tous droits réservés : la position des points ne sort pas.
        DB::table('line_reference_parts')->where('line_id', $this->line->id)->update(['source' => 'archeoferroviaire', 'matched_source' => null]);
        $this->fakeIgn();
        $this->artisan('vff:compute-line-profiles', ['--line' => [$this->line->slug]])->assertSuccessful();
        $base = "/api/v1/lines/{$this->line->slug}";

        $points = $this->getJson("{$base}/profile/points")->assertOk()->json('points');
        $this->assertNotEmpty($points);
        foreach ($points as $p) {
            $this->assertNull($p['lat']);
            $this->assertNull($p['lng']);
            $this->assertSame('licence_not_open', $p['position_withheld']);
            $this->assertNotNull($p['distance_m']);
        }
        $at = $this->getJson("{$base}/altitude?pk_m=500")->assertOk()->json();
        $this->assertNull($at['lat']);
        $this->assertSame('licence_not_open', $at['position_withheld']);
        $this->assertNotNull($at['altitude_m']);

        $data = $this->postJson('/graphql', ['query' => '{ line(slug: "'.$this->line->slug.'") { profile { samples(max_points: 10) { lat lng position_withheld } altitude_at(pk_m: 500) { lat position_withheld } } } }'])
            ->assertOk()->json('data.line.profile');
        $this->assertNull($data['samples'][0]['lat']);
        $this->assertSame('licence_not_open', $data['altitude_at']['position_withheld']);

        $result = $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => [
                'name' => 'line_profile', 'arguments' => ['line' => $this->line->slug, 'pk_m' => 500],
            ]])->assertOk()->json('result');
        $payload = json_decode($result['content'][0]['text'], true);
        $this->assertNull($payload['profile']['altitude_at_pk']['lat']);
        $this->assertStringNotContainsString('48.8', (string) json_encode($payload['profile']['points']));

        // Règle désactivée par la configuration : les positions reviennent.
        config(['api.withhold_unlicensed_geometry' => false]);
        $this->assertNotNull($this->getJson("{$base}/profile/points")->json('points.0.lat'));
    }

    public function test_a_line_entirely_outside_france_gets_no_profile_and_says_why(): void
    {
        // Comme les réseaux militaires du front belge : le tracé ne touche aucune commune française,
        // ni la BD TOPO ni le RGE ALTI n'y donnent d'altitude.
        DB::table('commune_contours')->where('code_insee', '99999')->delete();
        Http::fake([
            'data.geopf.fr/wfs/*' => Http::response(['type' => 'FeatureCollection', 'features' => []]),
            'data.geopf.fr/altimetrie/*' => Http::response(['elevations' => []]),
        ]);

        $this->artisan('vff:compute-line-profiles', ['--line' => [$this->line->slug]])
            ->expectsOutputToContain('tracé entièrement hors de France')
            ->assertSuccessful();
        $this->assertSame(0, LineProfile::count());
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'altimetrie'));
    }

    public function test_a_rack_railway_withholds_the_measured_grade_and_exposes_the_sourced_one(): void
    {
        // La ligne de test devient Chamonix - Montenvers, déclarée dans database/data/rack_railways.php.
        DB::table('lines')->where('id', $this->line->id)->update(['slug' => 'archeo-ligne-de-chamonix-a-montenvers']);
        $slug = 'archeo-ligne-de-chamonix-a-montenvers';
        $this->fakeIgn();
        $this->artisan('vff:compute-line-profiles', ['--line' => [$slug]])->assertSuccessful();

        $profile = LineProfile::where('line_id', $this->line->id)->firstOrFail();
        $this->assertNull($profile->max_grade_permil);
        $this->assertSame('rack', $profile->max_grade_withheld);
        // --stats-only garde le motif.
        $this->artisan('vff:compute-line-profiles', ['--line' => [$slug], '--stats-only' => true])->assertSuccessful();
        $this->assertSame('rack', $profile->refresh()->max_grade_withheld);

        $this->getJson("/api/v1/lines/{$slug}/profile")->assertOk()
            ->assertJsonPath('summary.max_grade_withheld', 'rack')
            ->assertJsonPath('summary.climb_method.method', 'hysteresis')
            ->assertJsonPath('summary.climb_method.threshold_m', 3)
            ->assertJsonPath('summary.climb_method.terrain_smoothing_m', 1000)
            ->assertJsonPath('rack.system', 'strub')
            ->assertJsonPath('rack.extent', 'full')
            ->assertJsonPath('rack.max_grade_permil', 220)
            ->assertJsonPath('rack.braking_only', false)
            ->assertJsonPath('rack.source.provider', 'wikipedia')
            ->assertJsonPath('rack.source.license', 'CC BY-SA 4.0');

        $data = $this->postJson('/graphql', ['query' => '{ line(slug: "'.$slug.'") { profile { summary { max_grade_withheld climb_method { method threshold_m terrain_smoothing_m } } rack { system extent max_grade_permil braking_only source { provider title url license } } } } }'])
            ->assertOk()->json('data.line.profile');
        $this->assertSame('rack', $data['summary']['max_grade_withheld']);
        $this->assertSame(1000.0, (float) $data['summary']['climb_method']['terrain_smoothing_m']);
        $this->assertSame('strub', $data['rack']['system']);
        $this->assertSame(220, $data['rack']['max_grade_permil']);
        $this->assertStringContainsString('oldid=', $data['rack']['source']['url']);

        $result = $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => [
                'name' => 'line_profile', 'arguments' => ['line' => $slug, 'max_points' => 10],
            ]])->assertOk()->json('result');
        $payload = json_decode($result['content'][0]['text'], true);
        $this->assertSame('strub', $payload['profile']['rack']['system']);
        $this->assertSame('rack', $payload['profile']['summary']['max_grade_withheld']);
        $this->assertStringContainsString('Wikip', json_encode($payload['attribution'], JSON_UNESCAPED_UNICODE) ?: '');

        // Une ligne à adhérence n'a pas de fiche de crémaillère.
        DB::table('lines')->where('id', $this->line->id)->update(['slug' => 'ligne-a-adherence']);
        $this->artisan('vff:compute-line-profiles', ['--line' => ['ligne-a-adherence'], '--stats-only' => true])->assertSuccessful();
        $this->getJson('/api/v1/lines/ligne-a-adherence/profile')->assertOk()->assertJsonPath('rack', null);
        $this->assertNotSame('rack', $profile->refresh()->max_grade_withheld);
    }
}

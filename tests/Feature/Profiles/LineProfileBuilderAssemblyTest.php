<?php

namespace Tests\Feature\Profiles;

use App\Models\Era;
use App\Models\Line;
use App\Models\LineProfile;
use App\Models\Station;
use App\Services\Profiles\GeoplateformeClient;
use App\Services\Profiles\LineProfileBuilder;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Assemblage du profil par LineProfileBuilder sur de petites lignes est-ouest fabriquées (latitude 48,5),
 * services de l'IGN simulés : parcours avec embranchement et trou, gares placées et calage du PK, sens,
 * ouvrages, arrondis, origines des altitudes. Les chiffres attendus ont été recalculés à la main.
 */
class LineProfileBuilderAssemblyTest extends TestCase
{
    use RefreshDatabase;

    private const LAT = 48.5;

    private string $cacheDir;

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement("INSERT INTO commune_contours (code_insee, nom, departement, geom) VALUES
            ('99998', 'Commune de test', '99', ST_GeomFromText('MULTIPOLYGON(((1.5 48.0, 2.6 48.0, 2.6 49.0, 1.5 49.0, 1.5 48.0)))', 4326))");
        $this->cacheDir = sys_get_temp_dir().'/vff-profil-asm-'.bin2hex(random_bytes(4));
        $this->app->instance(GeoplateformeClient::class, new GeoplateformeClient($this->cacheDir, 0));
    }

    protected function tearDown(): void
    {
        self::$spikeAt = null;
        try {
            parent::tearDown();
        } finally {
            (new Filesystem)->deleteDirectory($this->cacheDir);
        }
    }

    /** Altitude du terrain simulée (RGE ALTI) en fonction de la longitude : pente régulière, décimales variées. */
    private static function terrain(float $lon): float
    {
        $spike = self::$spikeAt !== null && abs($lon - self::$spikeAt) < 0.0003 ? 300.0 : 0.0;

        return 150.37 + 1234.567 * ($lon - 2.0) + $spike;
    }

    /** Longitude d'un point du terrain simulé qui s'envole de 300 m (aberration à effacer), ou null. */
    private static ?float $spikeAt = null;

    /**
     * @param  list<array{0: string, 1: list<array{0: float, 1: float, 2: float|null}>}>  $tracks  tronçons BD TOPO [position, sommets]
     */
    private function fakeIgn(array $tracks = []): void
    {
        Http::fake([
            'data.geopf.fr/wfs/*' => Http::response(['type' => 'FeatureCollection', 'features' => array_map(fn (array $t): array => [
                'type' => 'Feature',
                'properties' => ['position_par_rapport_au_sol' => $t[0]],
                'geometry' => ['type' => 'LineString', 'coordinates' => array_map(fn (array $c): array => $c[2] === null ? [$c[0], $c[1], -1000.0] : [$c[0], $c[1], $c[2]], $t[1])],
            ], $tracks)]),
            'data.geopf.fr/altimetrie/*' => fn (Request $request) => Http::response([
                'elevations' => array_map(fn (string $lon): float => self::terrain((float) $lon), explode('|', (string) $request['lon'])),
            ]),
        ]);
    }

    /**
     * @param  list<array{0: float, 1: float, 2: float}>  $parts  morceaux du référentiel [lon début, lon fin, lat]
     */
    private function lineOf(string $slug, string $name, array $parts, float $length = 0.0): Line
    {
        $line = Line::factory()->create(['slug' => $slug, 'name' => $name]);
        foreach ($parts as [$lon1, $lon2, $lat]) {
            DB::table('line_reference_parts')->insert([
                'line_id' => $line->id, 'source' => 'osm', 'source_trace_ids' => '[]', 'length_m' => $length,
                'geom' => DB::raw(sprintf("ST_Multi(ST_GeomFromText('LINESTRING(%F %F, %F %F)', 4326))", $lon1, $lat, $lon2, $lat)),
            ]);
        }

        return $line;
    }

    private function station(Line $line, string $slug, string $name, float $lon, float $lat, ?int $pk, ?string $pkSource = 'sncf', string $status = 'active'): Station
    {
        $station = Station::factory()->create(['slug' => $slug, 'name' => $name, 'status' => $status, 'geom' => DB::raw(sprintf('ST_SetSRID(ST_MakePoint(%F, %F), 4326)', $lon, $lat))]);
        DB::table('station_lines')->insert(['station_id' => $station->id, 'line_id' => $line->id, 'method' => 'sncf_pk', 'pk_m' => $pk, 'pk_source' => $pkSource]);

        return $station;
    }

    private function compute(Line $line): LineProfile
    {
        $profile = app(LineProfileBuilder::class)->compute($line);
        $this->assertNotNull($profile);

        return $profile;
    }

    private function assemblyLine(): Line
    {
        $line = $this->lineOf('ligne-a', 'Ligne de Aval à Amont', [[2.0, 2.02, self::LAT], [2.02, 2.04, self::LAT], [2.10, 2.11, self::LAT]], 1000);
        DB::table('line_reference_parts')->insert([
            'line_id' => $line->id, 'source' => 'osm', 'source_trace_ids' => '[]', 'length_m' => 1000,
            'geom' => DB::raw("ST_Multi(ST_GeomFromText('LINESTRING(2.02 48.5, 2.02 48.51)', 4326))"),
        ]);
        $this->station($line, 'aval', 'Aval', 2.0, self::LAT, 0);
        $this->station($line, 'amont', 'Amont', 2.04, self::LAT, 3000);
        $this->station($line, 'milieu', 'Milieu', 2.0113, self::LAT, null, 'projected_raw');
        $this->station($line, 'ferme', 'Fermee', 2.0291, self::LAT + 0.0001, null, null, 'declassified');
        // Loin du parcours (1,1 km) mais à une distance en deçà de la fin de la ligne principale : pas une ancre.
        $this->station($line, 'loin-du-trace', 'Loin du tracé', 2.015, self::LAT - 0.01, 800);
        // Dans le trou entre les deux morceaux : sur le segment qui les relie, aucune altitude, pas une ancre.
        $this->station($line, 'dans-le-trou', 'Dans le trou', 2.07, self::LAT, 4500);
        $this->station($line, 'apres-le-trou', 'Après le trou', 2.105, self::LAT, 6000, 'lignes-oubliees');
        // Sur l'embranchement : après la ligne principale, pas une ancre.
        $this->station($line, 'sur-l-epi', 'Sur l\'épi', 2.02, self::LAT + 0.008, 1500);
        $this->fakeIgn();

        return $line;
    }

    public function test_the_path_is_assembled_with_gaps_a_branch_and_stations_placed_on_it(): void
    {
        $line = $this->assemblyLine();
        $profile = $this->compute($line);

        $this->assertSame(9717, $profile->length_m);
        $this->assertSame(4000, $profile->trace_length_m);
        $this->assertSame(4796, $profile->covered_m);
        $this->assertSame(0, $profile->off_path_m);
        $this->assertSame(100, $profile->step_m);
        $this->assertNull($profile->era_id);
        $this->assertSame([['from_m' => 2947, 'to_m' => 7368], ['from_m' => 8105, 'to_m' => 8605]], $profile->gaps);
        $this->assertSame([['from_m' => 8605, 'to_m' => 9717]], $profile->branches);
        $this->assertSame(3, $profile->pk_anchors);
        $this->assertSame(['bdtopo' => 0, 'rge_alti' => 54, 'interpolated' => 0, 'missing' => 0], $profile->altitude_sources);
        $this->assertSame([], $profile->structures);

        $this->assertSame([
            ['slug' => 'aval', 'name' => 'Aval', 'distance_m' => 0, 'altitude_m' => 150.4, 'pk_m' => 0, 'status' => 'active', 'offset_m' => 0],
            ['slug' => 'milieu', 'name' => 'Milieu', 'distance_m' => 833, 'altitude_m' => 164.4, 'pk_m' => null, 'status' => 'active', 'offset_m' => 0],
            ['slug' => 'loin-du-trace', 'name' => 'Loin du tracé', 'distance_m' => 1105, 'altitude_m' => 168.9, 'pk_m' => 800, 'status' => 'active', 'offset_m' => 1112],
            ['slug' => 'ferme', 'name' => 'Fermee', 'distance_m' => 2144, 'altitude_m' => 186.3, 'pk_m' => null, 'status' => 'declassified', 'offset_m' => 11],
            ['slug' => 'amont', 'name' => 'Amont', 'distance_m' => 2947, 'altitude_m' => 199.8, 'pk_m' => 3000, 'status' => 'active', 'offset_m' => 0],
            ['slug' => 'dans-le-trou', 'name' => 'Dans le trou', 'distance_m' => 5158, 'altitude_m' => null, 'pk_m' => 4500, 'status' => 'active', 'offset_m' => 0],
            ['slug' => 'apres-le-trou', 'name' => 'Après le trou', 'distance_m' => 7736, 'altitude_m' => 280, 'pk_m' => 6000, 'status' => 'active', 'offset_m' => 0],
            ['slug' => 'sur-l-epi', 'name' => 'Sur l\'épi', 'distance_m' => 9494, 'altitude_m' => 175.1, 'pk_m' => 1500, 'status' => 'active', 'offset_m' => 0],
        ], $profile->stations);

        $rows = DB::select('SELECT seq, distance_m, pk_m, altitude_m, source, interpolated, structure, piece, branch, ST_X(geom) AS lon, ST_Y(geom) AS lat FROM line_profile_points ORDER BY seq');
        $this->assertCount(54, $rows);
        foreach ($rows as $i => $r) {
            $this->assertSame($i, $r->seq);
            $this->assertSame('rge_alti', $r->source);
            $this->assertFalse($r->interpolated);
            $this->assertNull($r->structure);
        }
        // Distances arrondies au mètre le plus proche (les bords des trous tombent entre deux mètres).
        $this->assertSame([0, 100, 200], array_map(fn ($r) => $r->distance_m, array_slice($rows, 0, 3)));
        $this->assertSame([2947, 7368, 7400], array_map(fn ($r) => $r->distance_m, array_slice($rows, 30, 3)));
        $this->assertSame([8105, 8605, 8700], array_map(fn ($r) => $r->distance_m, array_slice($rows, 40, 3)));
        $this->assertSame(9717, $rows[53]->distance_m);
        // Longitude et latitude dans le bon ordre.
        $this->assertEqualsWithDelta(2.00136, $rows[1]->lon, 1e-5);
        $this->assertEqualsWithDelta(self::LAT, $rows[1]->lat, 1e-6);
        // Altitude du terrain au dixième de mètre.
        foreach ([1, 7, 30, 31, 39] as $i) {
            $this->assertEqualsWithDelta(self::terrain($rows[$i]->lon), (float) $rows[$i]->altitude_m, 0.06, "altitude du point $i");
        }
        // Pièces : le trou sépare la ligne principale de son deuxième morceau, l'embranchement a la sienne.
        $this->assertSame([0, 0, 1, 1, 2, 2], array_map(fn ($i) => $rows[$i]->piece, [0, 30, 31, 40, 41, 53]));
        $this->assertSame([null, null, null, null, 1, 1], array_map(fn ($i) => $rows[$i]->branch, [0, 30, 31, 40, 41, 53]));
        // PK calé sur les trois ancres de la ligne principale, aucun sur l'embranchement.
        $this->assertSame([0, 102, 3000, 5769, 6368], array_map(fn ($i) => $rows[$i]->pk_m, [0, 1, 30, 31, 40]));
        for ($i = 41; $i <= 53; $i++) {
            $this->assertNull($rows[$i]->pk_m);
        }
        $this->assertSame('osm', DB::table('line_profile_points')->distinct()->value('trace_source'));
    }

    public function test_a_second_computation_replaces_the_points_and_resets_the_era(): void
    {
        $line = $this->assemblyLine();
        $first = $this->compute($line);
        $era = Era::factory()->create();
        DB::table('line_profiles')->where('id', $first->id)->update(['era_id' => $era->id]);

        $second = $this->compute($line);

        $this->assertSame($first->id, $second->id);
        $this->assertNull($second->refresh()->era_id);
        $this->assertSame(54, DB::table('line_profile_points')->where('line_profile_id', $first->id)->count());
    }

    public function test_tiles_asked_of_the_cache_cover_every_corner_of_the_margin(): void
    {
        // Point à 0,0004 degré à l'est de la tuile 9 / 10 et à 0,0004 degré au sud de 242 / 243.
        $line = $this->lineOf('ligne-bord', 'Ligne du bord', [[2.0004, 2.0014, 48.5996]], 74);

        $this->assertSame([[10, 242], [10, 243], [9, 242], [9, 243]], app(LineProfileBuilder::class)->tilesFor($line));
        $empty = Line::factory()->create(['slug' => 'ligne-vide']);
        $this->assertSame([], app(LineProfileBuilder::class)->tilesFor($empty));
    }

    public function test_only_the_sixteen_most_recent_tiles_stay_in_memory(): void
    {
        $this->fakeIgn();
        $builder = app(LineProfileBuilder::class);
        $tile = new \ReflectionMethod($builder, 'tile');
        foreach (range(0, 16) as $x) {
            $tile->invoke($builder, $x.'_5', $x, 5);
        }
        $kept = (new \ReflectionProperty($builder, 'tiles'))->getValue($builder);

        $this->assertCount(16, $kept);
        $this->assertArrayNotHasKey('0_5', $kept);
        $this->assertSame(array_map(fn (int $x): string => $x.'_5', range(1, 16)), array_keys($kept));
        // Un tuile relue passe en dernier et ne coûte pas de nouvelle entrée.
        $tile->invoke($builder, '1_5', 1, 5);
        $kept = (new \ReflectionProperty($builder, 'tiles'))->getValue($builder);
        $this->assertCount(16, $kept);
        $this->assertSame('1_5', array_key_last($kept));
    }

    public function test_grade_flags_tell_track_tunnel_and_interpolated_points_apart(): void
    {
        $flags = LineProfileBuilder::gradeFlags(
            [[0.0, 10.0], [100.0, 11.0], [200.0, 12.0], [300.0, null], [400.0, 13.0]],
            ['bdtopo', 'rge_alti', null, null],
            ['tunnel', 'ground', 'tunnel', null, 'bridge'],
        );

        $this->assertSame([
            ['track' => true, 'tunnel' => true, 'interpolated' => false],
            ['track' => false, 'tunnel' => false, 'interpolated' => false],
            ['track' => false, 'tunnel' => true, 'interpolated' => true],
            ['track' => false, 'tunnel' => false, 'interpolated' => false],
            ['track' => false, 'tunnel' => false, 'interpolated' => true],
        ], $flags);
    }

    /** Ligne ouest-est de 2,9 km avec ses deux gares extrêmes (PK 0 à l'ouest) : le parcours part de l'ouest. */
    private function straightLine(string $slug): Line
    {
        $line = $this->lineOf($slug, 'Ligne S', [[2.0, 2.04, self::LAT]], 2947);
        $this->station($line, $slug.'-ouest', 'Ouest', 2.0, self::LAT, 0);
        $this->station($line, $slug.'-est', 'Est', 2.04, self::LAT, 3000);

        return $line;
    }

    public function test_structures_are_ranges_bounded_by_the_neighbouring_points_and_clamped_to_the_path(): void
    {
        $line = $this->straightLine('ligne-ouvrages');
        $this->fakeIgn([
            ['-1', [[2.0, self::LAT, null], [2.003, self::LAT, null]]],
            ['1', [[2.0035, self::LAT, null], [2.007, self::LAT, null]]],
            ['0', [[2.012, self::LAT, 100.0], [2.020, self::LAT, 108.0]]],
            ['1', [[2.0385, self::LAT, null], [2.0401, self::LAT, null]]],
        ]);

        $profile = $this->compute($line);

        // Tunnel sur les trois premiers points, pont sur les trois suivants (un type différent ne prolonge pas
        // la suite), pont sur les deux derniers : bornes aux points voisins, repliées sur les bouts du parcours.
        $this->assertSame([
            ['from_m' => 0, 'to_m' => 300, 'kind' => 'tunnel'],
            ['from_m' => 200, 'to_m' => 600, 'kind' => 'bridge'],
            ['from_m' => 2800, 'to_m' => 2947, 'kind' => 'bridge'],
        ], $profile->structures);
        $this->assertSame(['bdtopo' => 6, 'rge_alti' => 15, 'interpolated' => 10, 'missing' => 0], $profile->altitude_sources);
        $rows = DB::select('SELECT seq, altitude_m, source, interpolated, structure FROM line_profile_points ORDER BY seq');
        $this->assertSame(['tunnel', 'tunnel', 'tunnel', 'bridge', 'bridge', 'bridge', null], array_map(fn ($r) => $r->structure, array_slice($rows, 0, 7)));
        $this->assertSame(['bridge', 'bridge'], array_map(fn ($r) => $r->structure, array_slice($rows, 29, 2)));
        // Sous un ouvrage sans altitude : valeur interpolée, jamais mesurée.
        foreach ([0, 1, 5, 29, 30] as $i) {
            $this->assertNull($rows[$i]->source);
            $this->assertTrue($rows[$i]->interpolated);
            $this->assertNotNull($rows[$i]->altitude_m);
        }
        // Altitude de la voie : source BD TOPO, mesurée.
        $this->assertSame(['bdtopo', false], [$rows[9]->source, $rows[9]->interpolated]);
        $this->assertEquals(100.2, $rows[9]->altitude_m);
        // Point sans altitude de voie (ni ouvrage) : terrain.
        $this->assertSame(['rge_alti', false], [$rows[20]->source, $rows[20]->interpolated]);
    }

    public function test_a_spike_in_the_terrain_is_erased_and_counted_as_interpolated(): void
    {
        $line = $this->straightLine('ligne-aberration');
        self::$spikeAt = 2.01357;
        $this->fakeIgn();

        $profile = $this->compute($line);

        $this->assertSame(['bdtopo' => 0, 'rge_alti' => 30, 'interpolated' => 1, 'missing' => 0], $profile->altitude_sources);
        $row = DB::selectOne('SELECT altitude_m, source, interpolated, ST_X(geom) AS lon FROM line_profile_points WHERE seq = 10');
        $this->assertNull($row->source);
        $this->assertTrue($row->interpolated);
        self::$spikeAt = null;
        $this->assertEqualsWithDelta(self::terrain($row->lon), (float) $row->altitude_m, 0.3);
    }

    public function test_points_outside_france_have_no_altitude_and_are_counted_as_missing(): void
    {
        // Les communes de test s'arrêtent à 2,6 degrés de longitude.
        $line = $this->lineOf('ligne-frontiere', 'Ligne F', [[2.55, 2.65, self::LAT]], 7447);
        $this->station($line, 'f-ouest', 'F ouest', 2.55, self::LAT, 0);
        $this->station($line, 'f-est', 'F est', 2.65, self::LAT, 7500);
        $this->fakeIgn();

        $profile = $this->compute($line);

        $rows = DB::select('SELECT altitude_m, source, interpolated, ST_X(geom) AS lon FROM line_profile_points ORDER BY seq');
        $missing = array_values(array_filter($rows, fn ($r) => $r->altitude_m === null));
        $this->assertGreaterThan(20, count($missing));
        $this->assertSame(count($missing), $profile->altitude_sources['missing']);
        $this->assertSame(count($rows) - count($missing), $profile->altitude_sources['rge_alti']);
        $this->assertSame(0, $profile->altitude_sources['interpolated']);
        foreach ($missing as $r) {
            $this->assertGreaterThan(2.6, $r->lon);
            $this->assertNull($r->source);
            $this->assertFalse($r->interpolated);
        }
    }

    public function test_a_profile_with_no_altitude_removes_the_old_one_and_gives_the_reason(): void
    {
        $line = $this->straightLine('ligne-sans-altitude');
        $this->fakeIgn();
        $builder = app(LineProfileBuilder::class);
        $this->assertNotNull($builder->compute($line));
        $this->assertNull($builder->skipReason());
        $this->assertSame(1, LineProfile::count());

        // Hors de France : plus aucun point en commune française.
        DB::table('commune_contours')->delete();
        $this->assertNull($builder->compute($line));
        $this->assertSame('outside_france', $builder->skipReason());
        $this->assertSame(0, LineProfile::count());
        $this->assertSame(0, DB::table('line_profile_points')->count());
    }

    public function test_a_service_without_altitudes_gives_no_profile_and_removes_the_old_one(): void
    {
        $line = $this->straightLine('ligne-service-muet');
        $this->fakeIgn();
        $builder = app(LineProfileBuilder::class);
        $this->assertNotNull($builder->compute($line));

        Http::swap(new Factory);
        Http::fake([
            'data.geopf.fr/wfs/*' => Http::response(['type' => 'FeatureCollection', 'features' => []]),
            'data.geopf.fr/altimetrie/*' => fn (Request $request) => Http::response([
                'elevations' => array_fill(0, count(explode('|', (string) $request['lon'])), -99999.0),
            ]),
        ]);
        $this->app->instance(GeoplateformeClient::class, new GeoplateformeClient($this->cacheDir.'-2', 0));
        $this->app->forgetInstance(LineProfileBuilder::class);
        $builder = app(LineProfileBuilder::class);
        $result = $builder->compute($line);

        $this->assertNull($result);
        $this->assertSame('no_altitude', $builder->skipReason());
        $this->assertSame(0, LineProfile::count());
        (new Filesystem)->deleteDirectory($this->cacheDir.'-2');
    }

    /** @return array{0: Line, 1: Line} */
    private function oppositeNamedLines(string $slug, bool $threeStops): array
    {
        $lines = [];
        foreach (['alpha-zeta' => 'Ligne de Alpha à Zeta', 'zeta-alpha' => 'Ligne de Zeta à Alpha'] as $key => $name) {
            $line = $this->lineOf($slug.'-'.$key, $name, [[2.0, 2.02, self::LAT]], 1474);
            $this->station($line, $slug.$key.'-zeta', 'Zeta', 2.0, self::LAT, null, null);
            if ($threeStops) {
                $this->station($line, $slug.$key.'-beta', 'Beta', 2.01, self::LAT, null, null);
            }
            $this->station($line, $slug.$key.'-alpha', 'Alpha', 2.02, self::LAT, null, null);
            $lines[] = $line;
        }

        return $lines;
    }

    public function test_the_line_name_gives_the_direction_with_two_stops_whatever_the_assembly_direction(): void
    {
        [$fromAlpha, $fromZeta] = $this->oppositeNamedLines('deux', false);
        $this->fakeIgn();

        $this->assertSame('Alpha', $this->compute($fromAlpha)->stations[0]['name']);
        $this->assertSame('Zeta', $this->compute($fromZeta)->stations[0]['name']);
    }

    public function test_the_line_name_is_read_against_the_first_and_last_stops_by_distance_not_by_name(): void
    {
        // Trois gares dont l'ordre alphabétique (Alpha, Beta, Zeta) n'est pas celui du parcours.
        [$fromAlpha, $fromZeta] = $this->oppositeNamedLines('trois', true);
        $this->fakeIgn();

        $this->assertSame(['Alpha', 'Beta', 'Zeta'], array_column($this->compute($fromAlpha)->stations, 'name'));
        $this->assertSame(['Zeta', 'Beta', 'Alpha'], array_column($this->compute($fromZeta)->stations, 'name'));
    }

    public function test_official_pk_decide_the_direction_before_the_line_name(): void
    {
        // PK 3000 à l'ouest et 0 à l'est : le parcours part de l'est, malgré « de Zeta à Alpha ».
        $line = $this->lineOf('ligne-pk-contre-nom', 'Ligne de Zeta à Alpha', [[2.0, 2.02, self::LAT]], 1474);
        $this->station($line, 'pk-zeta', 'Zeta', 2.0, self::LAT, 3000);
        $this->station($line, 'pk-alpha', 'Alpha', 2.02, self::LAT, 0);
        $this->fakeIgn();

        $profile = $this->compute($line);

        $this->assertSame(['Alpha', 'Zeta'], array_column($profile->stations, 'name'));
        $this->assertSame([0, 3000], array_column($profile->stations, 'pk_m'));
        $this->assertSame(2, $profile->pk_anchors);
    }

    public function test_the_trace_falls_back_to_the_segments_of_every_era_without_service_tracks(): void
    {
        $era = Era::factory()->create(['year' => 1990]);
        $other = Era::factory()->create(['year' => 2010]);
        $line = Line::factory()->create(['slug' => 'ligne-sans-referentiel', 'name' => 'Ligne sans référentiel']);
        foreach ([[$era->id, 'in_service', 2.0, 2.02], [$other->id, 'in_service', 2.0, 2.02], [$other->id, 'service_track', 2.20, 2.30]] as [$eraId, $status, $lon1, $lon2]) {
            DB::table('line_segments')->insert([
                'line_id' => $line->id, 'era_id' => $eraId, 'status' => $status, 'traffic_type' => 'mixed',
                'geom' => DB::raw(sprintf("ST_GeomFromText('LINESTRING(%F %F, %F %F)', 4326)", $lon1, self::LAT, $lon2, self::LAT)),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $this->fakeIgn();

        $plan = app(LineProfileBuilder::class)->plan($line);
        $profile = $this->compute($line);

        $this->assertNotNull($plan);
        // 0,02 degré de longitude à 48,5 degrés : 1 474 m, comptés une fois malgré les deux ères.
        $this->assertSame(1474, $profile->trace_length_m);
        $this->assertSame(1474, $profile->length_m);
        $this->assertSame([], $profile->gaps);
        $this->assertSame(0, $profile->off_path_m);
    }

    public function test_recomputing_the_figures_from_the_stored_points_keeps_gaps_and_tunnels(): void
    {
        // Voie à l'air libre à 5 pour mille, puis tunnel de la BD TOPO avec altitude à 40 pour mille, un trou, un second morceau.
        $line = $this->lineOf('ligne-recalcul', 'Ligne R', [[2.0, 2.04, self::LAT], [2.10, 2.13, self::LAT]], 2947);
        $this->station($line, 'r-ouest', 'R ouest', 2.0, self::LAT, 0);
        $this->station($line, 'r-est', 'R est', 2.13, self::LAT, 9000);
        $this->fakeIgn([
            ['0', [[1.99, self::LAT, 100.0], [2.03, self::LAT, 111.0]]],
            ['-1', [[2.03, self::LAT, 111.0], [2.04, self::LAT, 140.0]]],
            ['0', [[2.10, self::LAT, 140.0], [2.13, self::LAT, 143.0]]],
        ]);

        $profile = $this->compute($line);

        $this->assertCount(1, $profile->gaps);
        $this->assertSame([['from_m' => 2947, 'to_m' => 7368]], $profile->gaps);
        $expected = $profile->only(['min_altitude_m', 'max_altitude_m', 'ascent_m', 'descent_m', 'max_grade_permil', 'max_grade_from_m', 'max_grade_to_m', 'max_grade_direction', 'max_grade_withheld']);
        $this->assertNotNull($expected['max_grade_permil']);
        // Les fenêtres où le tunnel dépasse le quart des points sont écartées : la plus forte pente reste celle de la voie.
        $this->assertLessThan(16.0, abs($expected['max_grade_permil']));
        $this->assertSame(['tunnel'], array_values(array_unique(array_column($profile->structures, 'kind'))));

        DB::table('line_profiles')->where('id', $profile->id)->update(['max_grade_permil' => null, 'max_grade_from_m' => null, 'max_grade_to_m' => null, 'max_grade_direction' => null, 'ascent_m' => 0, 'descent_m' => 0, 'min_altitude_m' => 0, 'max_altitude_m' => 0]);
        $again = app(LineProfileBuilder::class)->recomputeStats($line);

        $this->assertNotNull($again);
        $this->assertEquals($expected, $again->refresh()->only(array_keys($expected)));
        $this->assertNull(app(LineProfileBuilder::class)->recomputeStats(Line::factory()->create(['slug' => 'ligne-jamais-calculee'])));
    }

    /**
     * Ligne ouest-est de 2,9 km (fin à la longitude $end) avec un embranchement de 556 m au nord du sommet 2,02 et
     * une courte antenne (moins de 100 m) au nord du sommet 2,01 : les longueurs ont des décimales qui départagent
     * l'arrondi au plus proche du plafond et du plancher. Pont sur la fin de la ligne principale, tunnel sur le
     * début de l'embranchement.
     */
    private function branchedLine(string $slug, float $end, float $spur): Line
    {
        $line = $this->lineOf($slug, 'Ligne B', [[2.0, 2.01, self::LAT], [2.01, 2.02, self::LAT], [2.02, $end, self::LAT]], 1000);
        foreach ([[2.02, self::LAT + 0.005, 2.02], [2.01, self::LAT + $spur, 2.01]] as [, $lat, $lon]) {
            DB::table('line_reference_parts')->insert([
                'line_id' => $line->id, 'source' => 'osm', 'source_trace_ids' => '[]', 'length_m' => 100,
                'geom' => DB::raw(sprintf("ST_Multi(ST_GeomFromText('LINESTRING(%F %F, %F %F)', 4326))", $lon, self::LAT, $lon, $lat)),
            ]);
        }
        $this->station($line, $slug.'-ouest', 'B ouest', 2.0, self::LAT, 0);
        $this->station($line, $slug.'-est', 'B est', $end, self::LAT, 3000);
        $this->fakeIgn([
            ['1', [[$end - 0.0015, self::LAT, null], [$end + 0.001, self::LAT, null]]],
            ['-1', [[2.02, self::LAT, null], [2.02, self::LAT + 0.0027, null]]],
        ]);

        return $line;
    }

    public function test_every_length_is_rounded_to_the_nearest_metre_whichever_way_the_decimals_fall(): void
    {
        // Décimales sous un demi : longueur 4 003,18 m, trou de 2 947,20 à 3 447,20, antenne de 61,16 m.
        $low = $this->compute($this->branchedLine('ligne-basse', 2.04, 0.00055));
        // Décimales au-dessus d'un demi : longueur 4 032,65 m, trou de 2 976,68 à 3 476,68, antenne de 77,84 m.
        $high = $this->compute($this->branchedLine('ligne-haute', 2.0404, 0.0007));

        $this->assertSame([4003, 3503, 61], [$low->length_m, $low->covered_m, $low->off_path_m]);
        $this->assertSame([['from_m' => 2947, 'to_m' => 3447]], $low->gaps);
        $this->assertSame([['from_m' => 3447, 'to_m' => 4003]], $low->branches);
        $this->assertSame([4033, 3533, 78], [$high->length_m, $high->covered_m, $high->off_path_m]);
        $this->assertSame([['from_m' => 2977, 'to_m' => 3477]], $high->gaps);
        $this->assertSame([['from_m' => 3477, 'to_m' => 4033]], $high->branches);
        // Ouvrages bornés par les points voisins, d'une ligne à l'autre de l'embranchement : bornes arrondies au plus proche.
        $this->assertSame([['from_m' => 2800, 'to_m' => 3447, 'kind' => 'bridge'], ['from_m' => 2947, 'to_m' => 3800, 'kind' => 'tunnel']], $low->structures);
        $this->assertSame([['from_m' => 2800, 'to_m' => 3477, 'kind' => 'bridge'], ['from_m' => 2977, 'to_m' => 3800, 'kind' => 'tunnel']], $high->structures);
    }

    public function test_tiles_are_asked_on_both_sides_of_the_margin_and_not_further(): void
    {
        $builder = app(LineProfileBuilder::class);
        // [longitude, latitude, tuiles attendues] : bords de tuile à 2,0 degrés de longitude et 48,6 de latitude.
        $cases = [
            'à cheval sur les deux bords' => [1.9996, 48.5996, [[9, 242], [9, 243], [10, 242], [10, 243]]],
            'à l\'ouest et au sud des bords, la marge ne les atteint pas' => [1.9993, 48.5993, [[9, 242]]],
            'à l\'est et au nord des bords, la marge ne les atteint pas' => [2.0007, 48.6007, [[10, 243]]],
            'point au nord du bord, sa marge au sud repasse dessous' => [2.0013, 48.6003, [[10, 242], [10, 243]]],
        ];
        foreach ($cases as $label => [$lon, $lat, $expected]) {
            $line = $this->lineOf('ligne-tuile-'.md5($label), 'Ligne T', [[$lon, $lon + 0.0001, $lat]], 7);
            $tiles = $builder->tilesFor($line);
            sort($tiles);
            $this->assertSame($expected, $tiles, $label);
        }
    }

    public function test_a_line_name_is_read_with_only_one_of_its_two_ends_among_the_stops(): void
    {
        // Seule la gare de départ ou seule celle d'arrivée figure dans le nom : chacune compte, dans les deux sens du tracé.
        $cases = [
            ['Ligne de Alpha à Beta', 'Alpha', 'Gamma'],
            ['Ligne de Delta à Beta', 'Gamma', 'Beta'],
        ];
        foreach ($cases as $n => [$name, $first, $second]) {
            foreach ([[2.0, 2.02], [2.02, 2.0]] as $k => [$lonFirst, $lonSecond]) {
                $line = $this->lineOf("ligne-partielle-$n-$k", $name, [[2.0, 2.02, self::LAT]], 1474);
                $this->station($line, "p$n-$k-a", $first, $lonFirst, self::LAT, null, null);
                $this->station($line, "p$n-$k-b", $second, $lonSecond, self::LAT, null, null);
                $this->fakeIgn();

                $this->assertSame($first, $this->compute($line)->stations[0]['name'], "$name, $first à l'ouest ou à l'est ($k)");
            }
        }
    }
}

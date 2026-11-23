<?php

namespace Tests\Feature\Profiles;

use App\Models\Line;
use App\Models\LineProfile;
use App\Services\Profiles\GeoplateformeClient;
use App\Services\Profiles\LineProfileBuilder;
use App\Services\Profiles\LineProfiles;
use App\Services\Profiles\TrackFromTerrain;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Altitude de la voie estimée d'après le terrain (LineProfileBuilder::estimateTrack) sur une ligne
 * est-ouest fabriquée, posée sur un remblai simulé (crête plate de 8 m, 6 m au-dessus d'un versant) ;
 * le tracé de référence passe à 6 m de l'axe, sur le talus, comme un tracé ancien un peu décalé.
 * Sans l'option, le profil garde l'altitude du terrain sous le tracé.
 */
class LineProfileTrackEstimateTest extends TestCase
{
    use RefreshDatabase;

    private const LAT = 48.5;

    /** Décalage du tracé au nord de l'axe du remblai, en mètres. */
    private const TRACE_OFFSET_M = 6.0;

    private string $cacheDir;

    /** @var list<int> points demandés par requête d'altitude */
    private array $batches = [];

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement("INSERT INTO commune_contours (code_insee, nom, departement, geom) VALUES
            ('99997', 'Commune de test', '99', ST_GeomFromText('MULTIPOLYGON(((1.5 48.0, 2.6 48.0, 2.6 49.0, 1.5 49.0, 1.5 48.0)))', 4326))");
        $this->cacheDir = sys_get_temp_dir().'/vff-profil-voie-'.bin2hex(random_bytes(4));
        $this->app->instance(GeoplateformeClient::class, new GeoplateformeClient($this->cacheDir, 0));
        Http::fake([
            'data.geopf.fr/wfs/*' => Http::response(['type' => 'FeatureCollection', 'features' => []]),
            'data.geopf.fr/altimetrie/*' => function (Request $request) {
                $lons = explode('|', (string) $request['lon']);
                $lats = explode('|', (string) $request['lat']);
                $this->batches[] = count($lons);

                return Http::response(['elevations' => array_map(
                    fn (string $lon, string $lat): float => self::terrain((float) $lon, (float) $lat),
                    $lons,
                    $lats,
                )]);
            },
        ]);
    }

    protected function tearDown(): void
    {
        try {
            parent::tearDown();
        } finally {
            (new Filesystem)->deleteDirectory($this->cacheDir);
        }
    }

    /**
     * Versant qui monte de 10 % vers le nord (aucune bande plate), remblai à crête horizontale
     * centré sur la latitude LAT, talus à 2/3 ; le tout en pente régulière vers l'est.
     */
    private static function terrain(float $lon, float $lat): float
    {
        $y = ($lat - self::LAT) * 110_574.0;
        $hill = 200.0 + 500.0 * ($lon - 2.0) + 0.1 * $y;
        $bank = self::crest($lon) - max(0.0, abs($y) - 4.0) * 1.5;

        return round(max($hill, $bank), 2);
    }

    private static function crest(float $lon): float
    {
        return 206.0 + 500.0 * ($lon - 2.0);
    }

    private function line(): Line
    {
        $line = Line::factory()->create(['slug' => 'ligne-remblai', 'name' => 'Ligne de Ouest à Est']);
        $lat = self::LAT + self::TRACE_OFFSET_M / 110_574.0;
        DB::table('line_reference_parts')->insert([
            'line_id' => $line->id, 'source' => 'osm', 'source_trace_ids' => '[]', 'length_m' => 1500,
            'geom' => DB::raw(sprintf("ST_Multi(ST_GeomFromText('LINESTRING(2.0 %.9F, 2.02 %.9F)', 4326))", $lat, $lat)),
        ]);

        return $line;
    }

    public function test_the_estimate_finds_the_crest_of_the_embankment_and_is_served_with_its_error(): void
    {
        $line = $this->line();
        $profile = app(LineProfileBuilder::class)->estimateTrack()->compute($line);
        $this->assertNotNull($profile);

        $rows = DB::select('SELECT altitude_m, source, ST_X(geom) AS lon FROM line_profile_points WHERE line_profile_id = ? ORDER BY seq', [$profile->id]);
        $this->assertCount(16, $rows);
        foreach ($rows as $r) {
            $this->assertSame(TrackFromTerrain::SOURCE, $r->source);
            // La crête, et non le talus (3 m plus bas) où passe le tracé.
            $this->assertEqualsWithDelta(self::crest((float) $r->lon), $r->altitude_m, 0.15);
        }
        $this->assertSame(['bdtopo' => 0, 'rge_alti' => 0, 'rge_track' => 16, 'interpolated' => 0, 'missing' => 0], $profile->altitude_sources);
        // Coupe de 31 points par point, en une requête ; deux points de coupe sur le même nœud de la
        // grille fine (1,1 m en latitude pour 1 m de pas) ne sont demandés qu'une fois.
        $this->assertCount(1, $this->batches);
        $this->assertGreaterThan(16 * 25, $this->batches[0]);
        $this->assertLessThanOrEqual(16 * (2 * TrackFromTerrain::HALF_M + 1), $this->batches[0]);
        $this->assertNotEmpty(glob($this->cacheDir.'/'.GeoplateformeClient::FINE_NODES_DIR.'/*.json.gz'));
        // Déclivité toujours retenue : la voie estimée compte comme le terrain.
        $this->assertSame('terrain_only', $profile->max_grade_withheld);

        $served = app(LineProfiles::class)->present($profile->fresh());
        $this->assertSame([['from_m' => 0, 'to_m' => 1474]], $served['track_estimate_ranges']);
        $this->assertSame([], $served['terrain_ranges']);
        $this->assertSame(TrackFromTerrain::method(), $served['track_estimate']);
        $this->assertSame(16, $served['altitude_sources']['rge_track']);

        // Le cache sert un second calcul sans appel réseau.
        app(LineProfileBuilder::class)->estimateTrack()->compute($line);
        $this->assertCount(1, $this->batches);
    }

    public function test_without_the_option_the_terrain_under_the_trace_is_kept_and_served_as_before(): void
    {
        $profile = app(LineProfileBuilder::class)->compute($this->line());
        $this->assertNotNull($profile);

        $rows = DB::select('SELECT altitude_m, source, ST_X(geom) AS lon FROM line_profile_points WHERE line_profile_id = ? ORDER BY seq', [$profile->id]);
        foreach ($rows as $r) {
            $this->assertSame('rge_alti', $r->source);
            $this->assertLessThan(self::crest((float) $r->lon) - 1.0, $r->altitude_m);
        }
        $this->assertSame(['bdtopo' => 0, 'rge_alti' => 16, 'interpolated' => 0, 'missing' => 0], $profile->altitude_sources);

        $served = app(LineProfiles::class)->present($profile->fresh());
        $this->assertNull($served['track_estimate']);
        $this->assertSame([], $served['track_estimate_ranges']);
        $this->assertSame(0, $served['altitude_sources']['rge_track']);
    }

    public function test_the_terrain_only_option_picks_the_lines_that_still_have_terrain_points_and_runs_out(): void
    {
        $line = $this->line();
        app(LineProfileBuilder::class)->compute($line);
        // Une seconde ligne dont le profil n'a aucun point à l'altitude du terrain n'est jamais choisie.
        $other = Line::factory()->create(['slug' => 'ligne-sans-terrain', 'name' => 'Ligne de Nord à Sud']);
        $profile = LineProfile::query()->create([
            'line_id' => $other->id, 'source' => LineProfileBuilder::SOURCE, 'resource' => GeoplateformeClient::RESOURCE, 'step_m' => 100,
            'length_m' => 100, 'trace_length_m' => 100, 'covered_m' => 100, 'off_path_m' => 0, 'pk_anchors' => 0, 'structures' => [],
            'altitude_sources' => [], 'gaps' => [], 'branches' => [], 'stations' => [], 'min_altitude_m' => 10, 'max_altitude_m' => 10, 'ascent_m' => 0, 'descent_m' => 0, 'computed_at' => now(),
        ]);
        DB::table('line_profile_points')->insert(['line_profile_id' => $profile->id, 'line_id' => $other->id, 'seq' => 0, 'distance_m' => 0, 'altitude_m' => 10, 'source' => 'bdtopo', 'interpolated' => false, 'piece' => 0]);

        $this->artisan('vff:compute-line-profiles', ['--terrain-only' => true, '--estimate-track' => true, '--limit' => 5])
            ->expectsOutputToContain('ligne-remblai')
            ->doesntExpectOutputToContain('ligne-sans-terrain')
            ->assertSuccessful();
        $this->assertSame(['rge_track'], DB::table('line_profile_points')->where('line_id', $line->id)->distinct()->pluck('source')->all());
        $this->assertSame(['bdtopo'], DB::table('line_profile_points')->where('line_id', $other->id)->pluck('source')->all());

        // Plus aucun point du terrain : la commande n'a plus rien à faire.
        $this->artisan('vff:compute-line-profiles', ['--terrain-only' => true, '--estimate-track' => true])
            ->expectsOutputToContain('Aucune ligne à traiter.')
            ->assertSuccessful();
    }
}

<?php

namespace Tests\Feature;

use App\Models\Line;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Trous du tracé de référence comblés par une trace rattachée à la ligne en second (vff:build-line-references,
 * étape 1 bis) : la section d'une ligne à code portée en principal par une autre ligne VFF (Saint-Georges-Motel -
 * Grand-Quevilly et Dreux - Acquigny, 23/11/2026) est reprise du dessin de cette autre ligne, au bit près ; le
 * prolongement de la trace au-delà du trou, la trace d'un tramway et un tracé déjà continu ne sont jamais pris.
 *
 * Réseau fabriqué en Lambert-93 (autour de x = 650 000, y = 6 860 000) : la ligne A est dessinée de x = 0 à 10 km
 * et de 30 à 40 km (BD TOPO), trou de 20 km entre les deux.
 */
class SecondaryBridgeTest extends TestCase
{
    use RefreshDatabase;

    private const X0 = 650000;

    private const Y0 = 6860000;

    private Line $a;

    private Line $b;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = Line::factory()->create(['code' => '370000', 'name' => 'Ligne de Saint-Georges-Motel à Grand-Quevilly', 'network' => 'principal']);
        $this->b = Line::factory()->create(['code' => null, 'name' => 'Ligne de Dreux à Acquigny', 'network' => 'principal']);
        $this->trace($this->a, 'bdtopo', [[0, 0], [10000, 0]]);
        $this->trace($this->a, 'bdtopo', [[30000, 0], [40000, 0]]);
    }

    private function trace(?Line $line, string $source, array $pts): int
    {
        $layer = ['bdtopo' => 'Voie ferrée principale', 'osm' => 'rail', 'archeoferroviaire' => 'principal'][$source] ?? $source;
        $wkt = 'LINESTRING('.implode(',', array_map(fn ($p) => (self::X0 + $p[0]).' '.(self::Y0 + $p[1]), $pts)).')';

        return DB::table('source_traces')->insertGetId([
            'source' => $source, 'source_ref' => $source.'-'.uniqid('', true), 'layer' => $layer,
            'line_id' => $line?->id, 'seen_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            'geom' => DB::raw("ST_Multi(ST_Transform(ST_GeomFromText('{$wkt}', 2154), 4326))"),
        ]);
    }

    private function secondary(int $traceId, Line $line): void
    {
        DB::table('source_trace_lines')->insert(['source_trace_id' => $traceId, 'line_id' => $line->id, 'role' => 'secondary',
            'method' => 'sncf_overlap', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function build(Line $line, array $options = []): void
    {
        $this->artisan('vff:build-line-references', ['--line' => [(string) $line->id], '--no-agreement' => true] + $options)->assertSuccessful();
    }

    /** Longueur (m) du tracé de la ligne dans la bande x ∈ [x0, x1], |y| <= 50 m, et au sud de y = -100 m. */
    private function drawn(Line $line, float $x0, float $x1): array
    {
        $r = DB::selectOne(<<<'SQL'
            SELECT COALESCE(SUM(ST_Length(ST_Intersection(g, ST_MakeEnvelope(:x0, :ya, :x1, :yb, 2154)))), 0) AS band,
                   COALESCE(SUM(ST_Length(ST_Intersection(g, ST_MakeEnvelope(:x2, :yc, :x3, :yd, 2154)))), 0) AS south
            FROM (SELECT ST_Transform(geom, 2154) AS g FROM line_reference_parts WHERE line_id = :line) p
            SQL, ['line' => $line->id, 'x0' => self::X0 + $x0, 'x1' => self::X0 + $x1, 'ya' => self::Y0 - 50, 'yb' => self::Y0 + 50,
            'x2' => self::X0 - 1000, 'x3' => self::X0 + 50000, 'yc' => self::Y0 - 20000, 'yd' => self::Y0 - 100]);

        return [(float) $r->band, (float) $r->south];
    }

    public function test_the_hole_is_taken_from_the_drawing_of_the_line_that_holds_the_section(): void
    {
        // Dreux - Acquigny : la section du trou, puis la suite vers le sud (vers Dreux), qui n'est pas la ligne A.
        $t = $this->trace($this->b, 'archeoferroviaire', [[10000, 0], [30000, 0], [30500, -300], [31000, -10000]]);
        $this->secondary($t, $this->a);
        $this->build($this->b);

        $this->build($this->a, ['--no-secondary-bridge' => true]);
        [$hole] = $this->drawn($this->a, 11000, 29000);
        $this->assertEqualsWithDelta(0, $hole, 1, 'sans la passe, le trou reste');

        $this->build($this->a);
        [$hole, $south] = $this->drawn($this->a, 11000, 29000);
        $this->assertEqualsWithDelta(18000, $hole, 50);
        $this->assertEqualsWithDelta(0, $south, 1, 'le prolongement de la trace vers Dreux n\'est pas pris');
        // Même géométrie que le dessin de B, au bit près (voie partagée, masquée ensuite).
        $gap = DB::selectOne(<<<'SQL'
            SELECT MAX(ST_Distance(d.geom, ST_Transform(b.geom, 2154))) AS m
            FROM line_reference_parts a, LATERAL ST_DumpPoints(ST_Transform(a.geom, 2154)) d, line_reference_geometries b
            WHERE a.line_id = :a AND b.line_id = :b AND ST_X(d.geom) BETWEEN :x0 AND :x1
            SQL, ['a' => $this->a->id, 'b' => $this->b->id, 'x0' => self::X0 + 11000, 'x1' => self::X0 + 29000])->m;
        $this->assertLessThan(0.01, (float) $gap);
    }

    public function test_a_tramway_trace_never_fills_the_hole(): void
    {
        $tram = Line::factory()->create(['code' => null, 'name' => 'Tramway de Tourcoing à Halluin', 'network' => 'secondaire']);
        $t = $this->trace($tram, 'archeoferroviaire', [[10000, 0], [30000, 0]]);
        $this->secondary($t, $this->a);
        $this->build($tram);

        $this->build($this->a);

        [$hole] = $this->drawn($this->a, 11000, 29000);
        $this->assertEqualsWithDelta(0, $hole, 1);
    }

    public function test_an_other_route_between_two_points_of_a_continuous_trace_is_not_added(): void
    {
        // Tracé continu de 0 à 40 km : la trace en second fait un détour au nord entre 12 et 28 km.
        $this->trace($this->a, 'bdtopo', [[10000, 0], [30000, 0]]);
        $t = $this->trace($this->b, 'archeoferroviaire', [[12000, 0], [12500, 3000], [27500, 3000], [28000, 0]]);
        $this->secondary($t, $this->a);
        $this->build($this->b);

        $this->build($this->a);

        $north = (float) DB::selectOne(<<<'SQL'
            SELECT COALESCE(SUM(ST_Length(ST_Intersection(ST_Transform(geom, 2154), ST_MakeEnvelope(:x0, :y0, :x1, :y1, 2154)))), 0) AS m
            FROM line_reference_parts WHERE line_id = :line
            SQL, ['line' => $this->a->id, 'x0' => self::X0, 'x1' => self::X0 + 40000, 'y0' => self::Y0 + 500, 'y1' => self::Y0 + 5000])->m;
        $this->assertEqualsWithDelta(0, $north, 1);
    }
}

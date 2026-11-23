<?php

namespace Tests\Feature;

use App\Models\Line;
use App\Services\LineReferenceExclusions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Trous du tracé de référence comblés par une trace rattachée à la ligne en second (vff:build-line-references,
 * étape 1 bis) : la section d'une ligne à code portée en principal par une autre ligne VFF (Saint-Georges-Motel -
 * Grand-Quevilly et Dreux - Acquigny, 23/11/2026) est reprise du dessin de cette autre ligne, au bit près ; le
 * prolongement de la trace au-delà du trou, la trace d'un tramway et un tracé déjà continu ne sont jamais pris.
 *
 * Suite (23/11/2026, rapport trous-trace-2.md) : les morceaux d'une trace qui se recoupe sont jugés ensemble (Ascq -
 * Tourcoing), une trace qui relie deux morceaux du tracé loin de leurs extrémités libres comble le trou, un bout
 * arrêté sur la voie d'une autre ligne est prolongé le long de son dessin (Courty - Pont-de-Dore), et une voie
 * partagée désignée à la main (kind « shared ») comble le trou sans rattachement en second (Saint-Julien-les-Fumades).
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

    public function test_a_self_crossing_trace_fills_the_hole_as_one_group(): void
    {
        // Raccordement en triangle dessiné en aller et retour (Ascq) : la trace part du bout de A, monte au nœud
        // (11 km, +1 km), redescend vers A à 9,5 km, remonte au nœud et file vers le bout de 30 km. Le nœud coupe la
        // partie hors du tracé en trois morceaux, dont aucun n'a ses deux bouts au tracé.
        $t = $this->trace($this->b, 'archeoferroviaire', [[10000, 0], [11000, 1000], [9500, 40], [11000, 1000], [30000, 0]]);
        $this->secondary($t, $this->a);
        $this->build($this->b);

        $this->build($this->a);

        [$hole] = $this->drawnNear($this->a, [[11000, 1000], [30000, 0]], 14000, 26000);
        $this->assertGreaterThan(11000, $hole, 'le trou est comblé par le groupe de morceaux');
    }

    public function test_a_trace_joining_two_pieces_away_from_their_free_ends_fills_the_hole(): void
    {
        // La trace quitte A à 8 km et le rejoint à 32 km : à 2 km de chaque extrémité libre, mais elle relie les deux
        // morceaux distincts du tracé (Ascq - Tourcoing sur Somain - Halluin).
        $t = $this->trace($this->b, 'archeoferroviaire', [[8000, 0], [9000, 2000], [31000, 2000], [32000, 0]]);
        $this->secondary($t, $this->a);
        $this->build($this->b);

        $this->build($this->a);

        $north = (float) DB::selectOne(<<<'SQL'
            SELECT COALESCE(SUM(ST_Length(ST_Intersection(ST_Transform(geom, 2154), ST_MakeEnvelope(:x0, :y0, :x1, :y1, 2154)))), 0) AS m
            FROM line_reference_parts WHERE line_id = :line
            SQL, ['line' => $this->a->id, 'x0' => self::X0 + 12000, 'x1' => self::X0 + 28000, 'y0' => self::Y0 + 1900, 'y1' => self::Y0 + 2100])->m;
        $this->assertEqualsWithDelta(16000, $north, 50);
    }

    public function test_an_end_stopped_on_an_other_line_is_carried_along_its_drawing(): void
    {
        // La trace s'arrête à 26 km sur la voie de C (Clermont - Saint-Just à Courty), qui rejoint A à 30 km puis file
        // au sud : A prend le dessin de C de 26 à 30 km, jamais sa suite vers le sud.
        $c = Line::factory()->create(['code' => '784000', 'name' => 'Ligne de Clermont-Ferrand à St-Just-sur-Loire', 'network' => 'principal']);
        $this->trace($c, 'bdtopo', [[26000, 0], [30000, 0], [30000, -6000]]);
        $this->build($c);
        $t = $this->trace($this->b, 'archeoferroviaire', [[10000, 0], [26000, 0]]);
        $this->secondary($t, $this->a);
        $this->build($this->b);

        $this->build($this->a, ['--no-secondary-bridge' => true]);
        [$carried] = $this->drawn($this->a, 26500, 29500);
        $this->assertEqualsWithDelta(0, $carried, 1, 'sans la passe, le trou reste');

        $this->build($this->a);
        [$hole, $south] = $this->drawn($this->a, 11000, 25000);
        [$carried] = $this->drawn($this->a, 26500, 29500);
        $this->assertEqualsWithDelta(14000, $hole, 50);
        $this->assertEqualsWithDelta(3000, $carried, 50, 'le dessin de C est repris de 26 à 30 km');
        $this->assertEqualsWithDelta(0, $south, 1, 'la suite de C vers le sud n\'est pas prise');
    }

    public function test_a_shared_entry_fills_the_hole_without_a_secondary_link(): void
    {
        // Le Martinet - Beaucaire sur Le Teil - Alès : la trace de la section appartient à B, jamais rattachée à A, et
        // continue au-delà du trou des deux côtés.
        $t = $this->trace($this->b, 'archeoferroviaire', [[0, 3000], [10000, 0], [30000, 0], [40000, 3000]]);
        $ref = (string) DB::table('source_traces')->where('id', $t)->value('source_ref');
        $this->build($this->b);
        $this->build($this->a);
        [$hole] = $this->drawn($this->a, 11000, 29000);
        $this->assertEqualsWithDelta(0, $hole, 1, 'sans entrée, le trou reste');

        $center = DB::selectOne('SELECT ST_X(p) AS lon, ST_Y(p) AS lat FROM ST_Transform(ST_SetSRID(ST_MakePoint(?, ?), 2154), 4326) p', [self::X0 + 20000, self::Y0]);
        $entry = ['kind' => 'shared', 'line' => '370000', 'over' => (string) $this->b->slug, 'source_name' => 'archeoferroviaire', 'source_ref' => $ref,
            'reason' => 'essai', 'source' => 'https://fr.wikipedia.org/', 'reviewed_at' => '2026-11-23'];
        // Disque de 5 km autour du milieu du trou : la portion gardée ne rejoint pas le tracé, rien n'est pris.
        $this->app->instance(LineReferenceExclusions::class, new LineReferenceExclusions([$entry + [
            'near' => [round((float) $center->lon, 6), round((float) $center->lat, 6)], 'radius_m' => 5000]]));
        $this->build($this->a);
        [$inside] = $this->drawn($this->a, 16000, 24000);
        $this->assertEqualsWithDelta(0, $inside, 1, 'disque de 5 km : rien n\'est pris');

        $this->app->instance(LineReferenceExclusions::class, new LineReferenceExclusions([$entry]));
        $this->build($this->a);
        [$hole] = $this->drawn($this->a, 11000, 29000);
        $outside = (float) DB::selectOne(<<<'SQL'
            SELECT COALESCE(SUM(ST_Length(ST_Intersection(ST_Transform(geom, 2154), ST_MakeEnvelope(:x0, :y0, :x1, :y1, 2154)))), 0) AS m
            FROM line_reference_parts WHERE line_id = :line
            SQL, ['line' => $this->a->id, 'x0' => self::X0 - 1000, 'x1' => self::X0 + 41000, 'y0' => self::Y0 + 500, 'y1' => self::Y0 + 4000])->m;
        $this->assertEqualsWithDelta(18000, $hole, 50);
        $this->assertEqualsWithDelta(0, $outside, 1, 'les suites de la trace de B hors du trou ne sont pas prises');
    }

    /** Longueur (m) du tracé de la ligne à moins de 50 m de la polyligne $pts, entre x0 et x1. */
    private function drawnNear(Line $line, array $pts, float $x0, float $x1): array
    {
        $wkt = 'LINESTRING('.implode(',', array_map(fn ($p) => (self::X0 + $p[0]).' '.(self::Y0 + $p[1]), $pts)).')';
        $m = DB::selectOne(<<<SQL
            SELECT COALESCE(SUM(ST_Length(ST_Intersection(g, ST_Intersection(ST_Buffer(ST_GeomFromText('{$wkt}', 2154), 50), ST_MakeEnvelope(:x0, :ya, :x1, :yb, 2154))))), 0) AS m
            FROM (SELECT ST_Transform(geom, 2154) AS g FROM line_reference_parts WHERE line_id = :line) p
            SQL, ['line' => $line->id, 'x0' => self::X0 + $x0, 'x1' => self::X0 + $x1, 'ya' => self::Y0 - 5000, 'yb' => self::Y0 + 5000])->m;

        return [(float) $m];
    }
}

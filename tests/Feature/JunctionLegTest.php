<?php

namespace Tests\Feature;

use App\Models\Line;
use App\Services\JunctionRepair;
use App\Services\TraceConflation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Arrivée d'un tracé imprécis sur une portion précise (JunctionRepair, règle 7) : la fin coudée
 * (marche d'escalier, raccord à angle vif) est refaite par la voie réelle quand elle relie deux
 * portions précises, sinon par une courbe lissée de rayon plausible, marquée « raccord-lisse ».
 *
 * Réseaux fabriqués en Lambert-93 (autour de x = 650 000, y = 6 860 000), coordonnées relatives en m.
 */
class JunctionLegTest extends TestCase
{
    use RefreshDatabase;

    private const X0 = 650000;

    private const Y0 = 6860000;

    private Line $line;

    protected function setUp(): void
    {
        parent::setUp();
        $this->line = Line::factory()->create(['code' => '420000', 'network' => 'principal', 'gauge_mm_default' => 1435]);
    }

    private function wkt(array $pts): string
    {
        return 'LINESTRING('.implode(',', array_map(fn ($p) => (self::X0 + $p[0]).' '.(self::Y0 + $p[1]), $pts)).')';
    }

    private function trace(?Line $line, string $source, array $pts): int
    {
        $layer = ['bdtopo' => 'Voie ferrée principale', 'osm' => 'rail', 'archeoferroviaire' => 'principal'][$source] ?? $source;

        return DB::table('source_traces')->insertGetId([
            'source' => $source, 'source_ref' => $source.'-'.uniqid('', true), 'layer' => $layer,
            'line_id' => $line?->id, 'seen_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            'geom' => DB::raw("ST_Multi(ST_Transform(ST_GeomFromText('".$this->wkt($pts)."', 2154), 4326))"),
        ]);
    }

    private function part(string $source, array $pts, array $ids = [], ?string $matched = null): int
    {
        return DB::table('line_reference_parts')->insertGetId([
            'line_id' => $this->line->id, 'source' => $source, 'source_trace_ids' => json_encode($ids), 'matched_source' => $matched,
            'length_m' => 0, 'created_at' => now(), 'updated_at' => now(),
            'geom' => DB::raw("ST_Multi(ST_Transform(ST_GeomFromText('".$this->wkt($pts)."', 2154), 4326))"),
        ]);
    }

    private function repair(): array
    {
        (new TraceConflation)->preparePool();
        $r = new JunctionRepair;
        $r->debug = (bool) getenv('JR_DEBUG');
        $out = $r->repair($this->line->id);
        if ($r->debug) {
            fwrite(STDERR, json_encode($r->log)."\n");
            foreach (DB::select('SELECT id, source, matched_source, ST_AsText(ST_Translate(ST_Transform(geom, 2154), -650000, -6860000)) AS g FROM line_reference_parts WHERE line_id = ? ORDER BY id', [$this->line->id]) as $p) {
                fwrite(STDERR, "{$p->id} {$p->source} {$p->matched_source} ".substr($p->g, 0, 300).' ... '.substr($p->g, -120)."\n");
            }
        }

        return $out;
    }

    /** Plus grande déviation (°) le long du tracé fusionné (sommets, sur ± 10 m). */
    private function maxKink(): float
    {
        return (float) DB::selectOne(<<<'SQL'
            WITH m AS (SELECT (ST_Dump(ST_LineMerge(ST_Collect(ST_Transform(geom, 2154))))).geom AS g FROM line_reference_parts WHERE line_id = ?)
            SELECT COALESCE(MAX(degrees(abs(atan2(sin(a2 - a1), cos(a2 - a1))))), 0) AS k
            FROM m, LATERAL ST_DumpPoints(m.g) v,
                 LATERAL (SELECT ST_LineLocatePoint(m.g, v.geom) AS f, ST_Length(m.g) AS l) f,
                 LATERAL (SELECT ST_Azimuth(ST_LineInterpolatePoint(m.g, GREATEST(0, f.f - 10 / f.l)), v.geom) AS a1,
                                 ST_Azimuth(v.geom, ST_LineInterpolatePoint(m.g, LEAST(1, f.f + 10 / f.l))) AS a2) a
            WHERE f.f * f.l >= 10 AND (1 - f.f) * f.l >= 10
            SQL, [$this->line->id])->k;
    }

    /** Nombre de polylignes après fusion, au millimètre près (1 = tracé continu ; le nettoyage final recolle au bit près). */
    private function pieces(): int
    {
        return (int) DB::selectOne('SELECT ST_NumGeometries(ST_LineMerge(ST_Collect(ST_SnapToGrid(ST_Transform(geom, 2154), 0.001)))) AS n FROM line_reference_parts WHERE line_id = ?', [$this->line->id])->n;
    }

    private function smoothed(): ?object
    {
        return DB::table('line_reference_parts')->where('line_id', $this->line->id)->where('matched_source', JunctionRepair::SMOOTHED)->first();
    }

    /** Rayon de courbure minimal (m) de la courbe lissée, mesuré sur ses sommets. */
    private function minRadius(object $part): float
    {
        return (float) DB::selectOne(<<<'SQL'
            WITH p AS (SELECT (ST_DumpPoints(ST_Transform(geom, 2154))).* FROM line_reference_parts WHERE id = ?),
            t AS (SELECT path[2] AS i, ST_X(geom) AS x, ST_Y(geom) AS y FROM p),
            r AS (
                SELECT (sqrt((b.x - a.x) ^ 2 + (b.y - a.y) ^ 2) * sqrt((c.x - b.x) ^ 2 + (c.y - b.y) ^ 2) * sqrt((a.x - c.x) ^ 2 + (a.y - c.y) ^ 2))
                       / NULLIF(2 * abs((b.x - a.x) * (c.y - a.y) - (b.y - a.y) * (c.x - a.x)), 0) AS r
                FROM t a JOIN t b ON b.i = a.i + 1 JOIN t c ON c.i = a.i + 2
            )
            SELECT MIN(r) AS r FROM r
            SQL, [$part->id])->r;
    }

    /** Escalier : le tracé archeo longe la voie à 25 m, puis un raccord droit à 51° rejoint la portion BD TOPO de la ligne. */
    private function stair(): void
    {
        $q = $this->trace($this->line, 'bdtopo', [[300, 0], [2000, 0]]);
        $this->part('bdtopo', [[300, 0], [2000, 0]], [$q]);
        $a = $this->trace($this->line, 'archeoferroviaire', [[-1000, 25], [280, 25]]);
        $this->part('archeoferroviaire', [[-1000, 25], [280, 25]], [$a]);
        $this->part('archeoferroviaire', [[280, 25], [300, 0]], [$a], 'connector');
    }

    public function test_stair_arrival_where_another_line_ends_is_kept(): void
    {
        // 438 (2.79238, 48.73316) : la branche de 214 finit sur le raccord droit de l'arrivée ; la courbe lissée la
        // laissait à 3,9 m (rejeu du 21/11/2026). L'arrivée reste telle quelle, la jonction à 0 m.
        $this->stair();
        $other = Line::factory()->create(['code' => '421000', 'network' => 'principal', 'gauge_mm_default' => 1435]);
        DB::table('line_reference_parts')->insert([
            'line_id' => $other->id, 'source' => 'bdtopo', 'source_trace_ids' => '[]', 'length_m' => 0, 'created_at' => now(), 'updated_at' => now(),
            'geom' => DB::raw("ST_Multi(ST_Transform(ST_GeomFromText('".$this->wkt([[290, 400], [290, 12.5]])."', 2154), 4326))"),
        ]);

        $stats = $this->repair();

        $this->assertSame(0, $stats['leg_smoothed']);
        $this->assertNull($this->smoothed());
        $this->assertLessThan(0.01, (float) DB::selectOne(<<<'SQL'
            SELECT ST_Distance(ST_SetSRID(ST_MakePoint(?, ?), 2154), ST_Collect(ST_Transform(geom, 2154))) AS d FROM line_reference_parts WHERE line_id = ?
            SQL, [self::X0 + 290, self::Y0 + 12.5, $this->line->id])->d);
    }

    public function test_stair_arrival_is_replaced_by_a_smoothed_curve_marked_as_estimated(): void
    {
        $this->stair();
        $this->assertGreaterThan(45, $this->maxKink());

        $stats = $this->repair();

        $this->assertSame(1, $stats['leg_smoothed']);
        $this->assertSame(0, $stats['leg_rerouted']);
        $this->assertLessThan(JunctionRepair::LEG_KINK_DEG, $this->maxKink());
        $this->assertSame(1, $this->pieces());
        $curve = $this->smoothed();
        $this->assertNotNull($curve);
        // Géométrie estimée : jamais présentée comme précise (source = tracé d'origine, matched_source dédiée).
        $this->assertSame('archeoferroviaire', $curve->source);
        $this->assertSame('raccord-lisse', $curve->matched_source);
        // Rayon plausible d'une ligne principale (aiguillage tg 0,11).
        $this->assertGreaterThanOrEqual(190 * 0.95, $this->minRadius($curve));
        // Plus de raccord droit.
        $this->assertSame(0, DB::table('line_reference_parts')->where('line_id', $this->line->id)->where('matched_source', 'connector')->count());
    }

    public function test_smoothing_is_idempotent(): void
    {
        $this->stair();
        $this->repair();
        $before = DB::table('line_reference_parts')->where('line_id', $this->line->id)->count();

        $stats = $this->repair();

        $this->assertSame(0, $stats['leg_smoothed']);
        $this->assertSame($before, DB::table('line_reference_parts')->where('line_id', $this->line->id)->count());
    }

    public function test_aligned_arrival_is_left_untouched(): void
    {
        $q = $this->trace($this->line, 'bdtopo', [[300, 0], [2000, 0]]);
        $this->part('bdtopo', [[300, 0], [2000, 0]], [$q]);
        $a = $this->trace($this->line, 'archeoferroviaire', [[-1000, 30], [0, 10], [300, 0]]);
        $this->part('archeoferroviaire', [[-1000, 30], [0, 10], [300, 0]], [$a]);

        $stats = $this->repair();

        $this->assertSame(0, $stats['leg_smoothed']);
        $this->assertNull($this->smoothed());
    }

    public function test_imprecise_bridge_between_two_precise_portions_follows_the_real_track(): void
    {
        // Voie propre (OSM) à 30 m au nord, puis tracé archeo + raccord à angle vif jusqu'à la portion BD TOPO.
        $p1 = $this->trace($this->line, 'osm', [[-1000, 30], [0, 30]]);
        $this->part('osm', [[-1000, 30], [0, 30]], [$p1]);
        $a = $this->trace($this->line, 'archeoferroviaire', [[0, 30], [150, 30]]);
        $this->part('archeoferroviaire', [[0, 30], [150, 30]], [$a]);
        $this->part('archeoferroviaire', [[150, 30], [170, 0]], [$a], 'connector');
        $q = $this->trace($this->line, 'bdtopo', [[170, 0], [230, 0], [2000, 0]]);
        $this->part('bdtopo', [[170, 0], [2000, 0]], [$q]);
        // Voie de raccordement réelle (BD TOPO, rattachée à aucune ligne), tangente aux deux bouts.
        $yard = $this->trace(null, 'bdtopo', [[0, 30], [80, 27], [150, 18], [200, 6], [230, 0]]);

        $stats = $this->repair();

        $this->assertSame(1, $stats['leg_rerouted']);
        $this->assertSame(0, $stats['leg_smoothed']);
        $this->assertNull($this->smoothed());
        $this->assertSame(1, $this->pieces());
        $this->assertLessThan(JunctionRepair::LEG_KINK_DEG, $this->maxKink());
        $added = DB::table('line_reference_parts')->where('line_id', $this->line->id)->orderByDesc('id')->first();
        $this->assertContains($yard, json_decode($added->source_trace_ids, true));
        $this->assertSame(0, DB::table('line_reference_parts')->where('line_id', $this->line->id)->where('source', 'archeoferroviaire')->count());
    }

    /** Marche : archeo parallèle à 30 m au nord, raccord perpendiculaire en (0, 0) vers la portion BD TOPO (vers l'est). */
    private function step(float $archeoLen, float $qLen): void
    {
        $q = $this->trace($this->line, 'bdtopo', [[0, 0], [$qLen, 0]]);
        $this->part('bdtopo', [[0, 0], [$qLen, 0]], [$q]);
        $a = $this->trace($this->line, 'archeoferroviaire', [[-$archeoLen, 30], [0, 30]]);
        $this->part('archeoferroviaire', [[-$archeoLen, 30], [0, 30]], [$a]);
        $this->part('archeoferroviaire', [[0, 30], [0, 0]], [$a], 'connector');
    }

    private function length(object $part): float
    {
        return (float) DB::selectOne('SELECT ST_Length(ST_Transform(geom, 2154)) AS m FROM line_reference_parts WHERE id = ?', [$part->id])->m;
    }

    public function test_curve_radius_follows_the_network(): void
    {
        $this->step(600, 600);
        $this->repair();
        $principal = $this->smoothed();
        $this->assertNotNull($principal);
        $rPrincipal = $this->minRadius($principal);
        $lenPrincipal = $this->length($principal);

        DB::table('line_reference_parts')->delete();
        DB::table('source_traces')->delete();
        $this->line->update(['network' => 'secondaire', 'gauge_mm_default' => 1000]);
        $this->step(600, 600);
        $this->repair();
        $metric = $this->smoothed();
        $this->assertNotNull($metric);

        $this->assertGreaterThanOrEqual(190 * 0.95, $rPrincipal);
        $this->assertGreaterThanOrEqual(60 * 0.95, $this->minRadius($metric));
        // Voie métrique : courbe plus serrée, donc plus courte.
        $this->assertLessThan($lenPrincipal * 0.8, $this->length($metric));
        $this->assertSame(1, $this->pieces());
    }

    public function test_no_curve_when_the_radius_cannot_fit(): void
    {
        // 40 m d'archeo et 40 m de portion précise : une marche de 30 m en rayon 190 m n'y tient pas.
        $this->step(40, 40);

        $stats = $this->repair();

        $this->assertSame(0, $stats['leg_smoothed']);
        $this->assertNull($this->smoothed());
    }

    public function test_hole_without_precise_track_is_bridged_by_a_smoothed_curve(): void
    {
        // Voie déposée : deux morceaux archeo face à face, trou de 57 m, aucune voie précise.
        $a = $this->trace($this->line, 'archeoferroviaire', [[-1000, 0], [0, 0]]);
        $this->part('archeoferroviaire', [[-1000, 0], [0, 0]], [$a]);
        $this->part('archeoferroviaire', [[57, 2], [1000, 30]], [$a]);

        $stats = $this->repair();

        $this->assertSame(1, $stats['hole_smoothed']);
        $this->assertSame(0, $stats['hole_filled']);
        $this->assertSame(1, $this->pieces());
        $this->assertNotNull($this->smoothed());
    }

    public function test_hole_on_a_precise_track_is_filled_by_the_track_not_smoothed(): void
    {
        $t = $this->trace(null, 'bdtopo', [[-1000, 0], [1000, 0]]);
        $this->part('bdtopo', [[-1000, 0], [0, 0]], [$t]);
        $this->part('bdtopo', [[57, 0], [1000, 0]], [$t]);

        $stats = $this->repair();

        $this->assertSame(1, $stats['hole_filled']);
        $this->assertSame(0, $stats['hole_smoothed']);
        $this->assertNull($this->smoothed());
    }

    /** Ligne B (BD TOPO, est-ouest en y = 0) ; tracé archeo de la ligne qui arrive du nord-ouest et s'arrête à $stop m de B. */
    private function endShort(float $stop, ?Line $other = null, bool $converging = true): Line
    {
        $other ??= Line::factory()->create(['code' => '421000', 'network' => 'principal', 'gauge_mm_default' => 1435]);
        $this->trace($other, 'bdtopo', [[-2000, 0], [2000, 0]]);
        $pts = $converging ? [[-1500, 600], [-300, 200], [0, $stop]] : [[-1500, $stop], [0, $stop]];
        $a = $this->trace($this->line, 'archeoferroviaire', $pts);
        $this->part('archeoferroviaire', $pts, [$a]);

        return $other;
    }

    public function test_imprecise_end_stopping_short_of_another_line_is_joined_to_its_track(): void
    {
        $this->endShort(25);

        $stats = $this->repair();

        $this->assertSame(1, $stats['end_joined']);
        $curve = $this->smoothed();
        $this->assertNotNull($curve);
        // La courbe finit SUR la voie de l'autre ligne (y = 0), tangente à elle.
        $end = DB::selectOne('SELECT ST_Y(ST_EndPoint(ST_GeometryN(ST_Transform(geom, 2154), 1))) - 6860000 AS y FROM line_reference_parts WHERE id = ?', [$curve->id]);
        $this->assertEqualsWithDelta(0, (float) $end->y, 0.5);
        $this->assertSame(1, $this->pieces());
        $this->assertLessThan(JunctionRepair::LEG_KINK_DEG, $this->maxKink());
    }

    public function test_parallel_end_or_incompatible_network_is_not_joined(): void
    {
        // Tracé parallèle à 25 m qui s'arrête : il ne converge pas vers la voie.
        $this->endShort(25, converging: false);
        $this->assertSame(0, $this->repair()['end_joined']);

        DB::table('line_reference_parts')->delete();
        DB::table('source_traces')->delete();
        // Ligne secondaire d'écartement inconnu vers une ligne principale : garde-fou, pas de raccord.
        $this->line->update(['network' => 'secondaire', 'gauge_mm_default' => null]);
        $this->endShort(25);
        $this->assertSame(0, $this->repair()['end_joined']);
    }

    public function test_min_radius_by_network_and_gauge(): void
    {
        $this->assertSame(190.0, JunctionRepair::minRadius('principal', 1435));
        $this->assertSame(190.0, JunctionRepair::minRadius('principal', null));
        $this->assertSame(150.0, JunctionRepair::minRadius('secondaire', 1435));
        $this->assertSame(150.0, JunctionRepair::minRadius('particulier', null));
        $this->assertSame(60.0, JunctionRepair::minRadius('secondaire', 1000));
        $this->assertSame(60.0, JunctionRepair::minRadius('secondaire', null));
        $this->assertSame(25.0, JunctionRepair::minRadius('militaire', 600));
        $this->assertSame(25.0, JunctionRepair::minRadius('militaire', null));
        $this->assertSame(60.0, JunctionRepair::minRadius('principal', 1000));
    }
}

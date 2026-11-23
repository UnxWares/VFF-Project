<?php

namespace Tests\Feature;

use App\Models\Line;
use App\Models\Station;
use App\Services\JunctionRepair;
use App\Services\SingleAxis;
use App\Services\TraceConflation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Continuité du tracé de référence sur les voies réelles (App\Services\JunctionRepair) :
 * trous comblés par la voie précise (tout droit avant « un seul axe »), sauts entre voies
 * parallèles remplacés par l'aiguillage réel ou retirés avec la voie qu'ils desservaient,
 * embranchement prolongé jusqu'à la voie d'une autre ligne, dépassement d'un tracé grossier
 * coupé au croisement ; jamais de géométrie inventée, jamais de trou créé, jamais de doublon.
 *
 * Réseaux fabriqués en Lambert-93 (autour de x = 650 000, y = 6 860 000), coordonnées relatives en m.
 */
class JunctionRepairTest extends TestCase
{
    use RefreshDatabase;

    private const X0 = 650000;

    private const Y0 = 6860000;

    private Line $line;

    protected function setUp(): void
    {
        parent::setUp();
        $this->line = Line::factory()->create(['code' => '420000', 'gauge_mm_default' => 1435]);
    }

    private function wkt(array $pts): string
    {
        return 'LINESTRING('.implode(',', array_map(fn ($p) => (self::X0 + $p[0]).' '.(self::Y0 + $p[1]), $pts)).')';
    }

    /** Voie (source_traces) ; $line null = voie non rattachée. */
    private function trace(?Line $line, string $source, array $pts, array $extra = []): int
    {
        $layer = ['bdtopo' => 'Voie ferrée principale', 'osm' => 'rail', 'archeoferroviaire' => 'principal'][$source] ?? $source;

        return DB::table('source_traces')->insertGetId(array_merge([
            'source' => $source, 'source_ref' => $source.'-'.uniqid('', true), 'layer' => $layer,
            'line_id' => $line?->id, 'seen_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            'geom' => DB::raw("ST_Multi(ST_Transform(ST_GeomFromText('".$this->wkt($pts)."', 2154), 4326))"),
        ], $extra));
    }

    /** Morceau du tracé de référence de la ligne (une ou plusieurs polylignes). */
    private function part(string $source, array $lines, array $ids = [], ?string $matched = null, ?Line $line = null): int
    {
        $wkt = 'MULTILINESTRING('.implode(',', array_map(fn ($l) => substr($this->wkt($l), strlen('LINESTRING')), $lines)).')';

        return DB::table('line_reference_parts')->insertGetId([
            'line_id' => ($line ?? $this->line)->id, 'source' => $source, 'source_trace_ids' => json_encode($ids), 'matched_source' => $matched,
            'length_m' => 0, 'created_at' => now(), 'updated_at' => now(),
            'geom' => DB::raw("ST_Transform(ST_GeomFromText('{$wkt}', 2154), 4326)"),
        ]);
    }

    private function connector(array $pts, string $source = 'bdtopo'): int
    {
        return $this->part($source, [$pts], [], 'connector');
    }

    private function repair(bool $straight = false): array
    {
        (new TraceConflation)->preparePool();
        $r = new JunctionRepair;
        $r->debug = (bool) getenv('JR_DEBUG');
        $out = $straight ? $r->fillStraightHoles($this->line->id) : $r->repair($this->line->id);
        if ($r->debug) {
            fwrite(STDERR, json_encode($r->log)."\n");
        }

        return $out;
    }

    private function totalLength(?string $matched = 'any'): float
    {
        $q = 'SELECT COALESCE(SUM(ST_Length(ST_Transform(geom, 2154))), 0) AS m FROM line_reference_parts WHERE line_id = ?';
        if ($matched === 'connector') {
            $q .= " AND matched_source = 'connector'";
        }

        return (float) DB::selectOne($q, [$this->line->id])->m;
    }

    /** Extrémités libres (ne touchant aucune autre polyligne de la ligne à 0,5 m près). */
    private function freeEnds(): array
    {
        return array_map(fn ($r) => [round($r->x - self::X0, 1), round($r->y - self::Y0, 1)], DB::select(<<<'SQL'
            WITH l AS (SELECT row_number() OVER () AS i, d.geom AS g FROM line_reference_parts p, LATERAL ST_Dump(ST_Transform(p.geom, 2154)) d WHERE p.line_id = ?),
            e AS (SELECT i, ST_StartPoint(g) AS p FROM l UNION ALL SELECT i, ST_EndPoint(g) FROM l)
            SELECT ST_X(e.p) AS x, ST_Y(e.p) AS y FROM e WHERE NOT EXISTS (SELECT 1 FROM l WHERE l.i <> e.i AND ST_DWithin(l.g, e.p, 0.5))
            ORDER BY 1, 2
            SQL, [$this->line->id]));
    }

    /** Plus grande déviation (°) le long du tracé (sommets, sur ± 10 m) après fusion des morceaux. */
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

    // ─── Trous ───────────────────────────────────────────────────────────

    public function test_hole_at_a_bridge_is_filled_by_the_unattached_precise_track(): void
    {
        $t1 = $this->trace($this->line, 'bdtopo', [[0, 0], [1000, 0]]);
        $t2 = $this->trace($this->line, 'bdtopo', [[1040, 0], [2000, 0]]);
        // Tablier du pont : morceau BD TOPO court, rattaché à aucune ligne.
        $bridge = $this->trace(null, 'bdtopo', [[1000, 0], [1040, 0]]);
        $this->part('bdtopo', [[[0, 0], [1000, 0]], [[1040, 0], [2000, 0]]], [$t1, $t2]);

        $stats = $this->repair(straight: true);

        $this->assertSame(1, $stats['hole_filled']);
        $this->assertEqualsWithDelta(40, $stats['hole_filled_m'], 0.5);
        $this->assertSame([[0.0, 0.0], [2000.0, 0.0]], $this->freeEnds());
        $this->assertEqualsWithDelta(2000, $this->totalLength(), 0.5);
        // Voie BD TOPO rattachée à aucune ligne : géométrie BD TOPO de la ligne (pas un recalage), ids du tablier.
        $added = DB::table('line_reference_parts')->where('line_id', $this->line->id)->orderByDesc('id')->first();
        $this->assertSame('bdtopo', $added->source);
        $this->assertNull($added->matched_source);
        $this->assertContains($bridge, json_decode($added->source_trace_ids, true));
    }

    public function test_long_hole_on_a_precise_track_the_route_cannot_follow_is_bridged_along_it(): void
    {
        // Trou de 150 m : les deux bouts sont à 4 m de la voie qui le traverse (au-delà de SNAP_M, l'étape 2 ne la prend pas).
        $this->part('osm', [[[0, 0], [1000, 0]], [[1150, 8], [2000, 8]]]);
        $this->trace(null, 'bdtopo', [[900, 4], [1300, 4]]);

        $stats = $this->repair();

        $this->assertSame(0, $stats['hole_filled']);
        $this->assertSame(1, $stats['hole_smoothed']);
        $this->assertSame([[0.0, 0.0], [2000.0, 8.0]], $this->freeEnds());
        $this->assertLessThan(10, $this->maxKink());
    }

    public function test_long_hole_away_from_any_precise_track_stays_open(): void
    {
        $this->part('osm', [[[0, 0], [1000, 0]], [[1150, 8], [2000, 8]]]);
        $this->trace(null, 'bdtopo', [[900, 40], [1300, 40]]);

        $stats = $this->repair();

        $this->assertSame(0, $stats['hole_smoothed']);
        $this->assertCount(4, $this->freeEnds());
    }

    public function test_hole_without_precise_track_is_left_open(): void
    {
        $this->part('bdtopo', [[[0, 0], [1000, 0]], [[1040, 0], [2000, 0]]]);
        // Seule une voie OSM d'un AUTRE tracé, à 40 m : rien ne traverse le trou.
        $this->trace(null, 'osm', [[900, 40], [1200, 40]]);

        $stats = $this->repair(straight: true);

        $this->assertSame(0, $stats['hole_filled']);
        $this->assertCount(4, $this->freeEnds());
        $this->assertEqualsWithDelta(1960, $this->totalLength(), 0.5);
    }

    public function test_hole_filled_only_without_detour(): void
    {
        $this->part('bdtopo', [[[0, 0], [1000, 0]], [[1100, 0], [2000, 0]]]);
        // Voie qui part tout droit puis fait une bosse de 80 m : 197 m pour un trou de 100 m (> 1,5 × 100 + 30).
        $this->trace(null, 'bdtopo', [[1000, 0], [1008, 0], [1050, 80], [1092, 0], [1100, 0]]);

        $stats = $this->repair();
        $this->assertSame(0, $stats['hole_filled']);
        // Pas de voie réelle sans détour : le trou est comblé par une courbe ESTIMÉE (règle 7 d, « raccord-lisse »), pas par la bosse.
        $this->assertSame(1, $stats['hole_smoothed']);
        $this->assertCount(2, $this->freeEnds());
        $this->assertEqualsWithDelta(2000, $this->totalLength(), 1);
    }

    public function test_straight_fill_never_changes_track_but_the_repair_after_single_axis_may(): void
    {
        // Aiguillages aux sommets partagés (comme dans la BD TOPO et OSM).
        $a = $this->trace($this->line, 'bdtopo', [[0, 0], [1100, 0], [2000, 0]]);
        $b = $this->trace($this->line, 'bdtopo', [[1200, 12], [2000, 12]]);
        // Communication de la voie 1 (y = 0) vers la voie 2 (y = 12) : aiguillage à x = 1 100.
        $this->trace(null, 'bdtopo', [[1100, 0], [1200, 12]], ['layer' => 'Voie de service']);
        $this->part('bdtopo', [[[0, 0], [1000, 0]], [[1200, 12], [2000, 12]]], [$a, $b]);

        $this->assertSame(0, $this->repair(straight: true)['hole_filled'], 'tout droit, la voie 1 ne mène pas au bout de la voie 2');

        $stats = $this->repair();
        $this->assertSame(1, $stats['hole_filled']);
        $this->assertEqualsWithDelta(100 + hypot(100, 12), $stats['hole_filled_m'], 0.5);
        $this->assertSame([[0.0, 0.0], [2000.0, 12.0]], $this->freeEnds());
        $this->assertLessThan(10, $this->maxKink());
    }

    public function test_fill_after_single_axis_never_doubles_another_portion_of_the_line(): void
    {
        $t = $this->trace($this->line, 'bdtopo', [[0, 0], [2000, 0]]);
        $this->part('bdtopo', [[[0, 0], [1000, 0]], [[1100, 0], [2000, 0]]], [$t]);
        // Portion gardée (voie parallèle à 10 m) le long du trou : le combler ferait deux voies côte à côte.
        $this->part('osm', [[[900, 10], [1200, 10]]]);

        $this->assertSame(0, $this->repair()['hole_filled']);
        // Avant « un seul axe », la voie continue est rétablie (« un seul axe » retire ensuite le doublon).
        $this->assertSame(1, $this->repair(straight: true)['hole_filled']);
    }

    // ─── Sauts entre voies parallèles ───────────────────────────────────

    /** Voie 1 (y = 0, 0 → 1 500) et voie 2 (y = 15, 800 → 2 000) de la ligne ; tracé : voie 1 → saut à x = 1 000 → voie 2. */
    private function jogNetwork(bool $crossover): void
    {
        $a = $this->trace($this->line, 'bdtopo', [[0, 0], [1200, 0], [1500, 0]]);
        $b = $this->trace($this->line, 'bdtopo', [[800, 15], [1320, 15], [2000, 15]]);
        if ($crossover) {
            $this->trace(null, 'bdtopo', [[1200, 0], [1320, 15]], ['layer' => 'Voie de service']);
        }
        $this->part('bdtopo', [[[0, 0], [1000, 0]], [[1000, 15], [2000, 15]]], [$a, $b]);
        $this->connector([[1000, 0], [1000, 15]]);
    }

    public function test_jog_between_parallel_tracks_is_replaced_by_the_real_crossover(): void
    {
        $this->jogNetwork(crossover: true);

        $stats = $this->repair();

        $this->assertSame(1, $stats['jog_removed']);
        $this->assertSame(0, $stats['jog_restored']);
        $this->assertSame(1, $stats['rerouted']);
        $this->assertEqualsWithDelta(200 + hypot(120, 15), $stats['rerouted_m'], 0.5);
        // Le début de la voie 2 (1 000 → 1 320), qui longe le nouveau chemin, est retiré.
        $this->assertEqualsWithDelta(320, $stats['stub_trimmed_m'], 0.5);
        $this->assertEqualsWithDelta(0, $this->totalLength('connector'), 0.01);
        $this->assertSame([[0.0, 0.0], [2000.0, 15.0]], $this->freeEnds());
        $this->assertLessThan(10, $this->maxKink());
        $this->assertEqualsWithDelta(1000 + 200 + hypot(120, 15) + 680, $this->totalLength(), 0.5);
    }

    public function test_jog_without_real_crossover_becomes_a_smoothed_track_change(): void
    {
        $this->jogNetwork(crossover: false);

        $stats = $this->repair();

        // Aucune communication réelle : le saut est remis, puis la marche d'escalier devient un S tangent aux deux voies.
        $this->assertSame(0, $stats['jog_removed']);
        $this->assertSame(1, $stats['jog_restored']);
        $this->assertSame(1, $stats['jog_smoothed']);
        $this->assertEqualsWithDelta(0, $this->totalLength('connector'), 0.01);
        $this->assertSame([[0.0, 0.0], [2000.0, 15.0]], $this->freeEnds());
        $this->assertLessThan(10, $this->maxKink());
        $curve = DB::table('line_reference_parts')->where('line_id', $this->line->id)->where('matched_source', JunctionRepair::SMOOTHED)->first();
        $this->assertNotNull($curve);
        // Recul de chaque côté : moitié de √(6 × 15 m × rayon minimal) ; rien n'est inventé au-delà.
        $t = sqrt(6 * 15 * JunctionRepair::minRadius($this->line->network, 1435)) / 2;
        $this->assertEqualsWithDelta(2 * (1000 - $t), $this->totalLength() - (float) DB::selectOne(
            'SELECT ST_Length(ST_Transform(geom, 2154)) AS m FROM line_reference_parts WHERE id = ?', [$curve->id])->m, 0.5);
    }

    public function test_jog_where_another_line_ends_is_not_smoothed(): void
    {
        // 438 (2.79238, 48.73316) : la branche de 214 finit sur le raccord du saut ; la courbe en S retirait ce raccord et
        // laissait la jonction à 3,9 m (rejeu du 21/11/2026). Le saut reste droit, la jonction à 0 m.
        $this->jogNetwork(crossover: false);
        $other = Line::factory()->create(['code' => '421000']);
        $this->part('bdtopo', [[[1000, 400], [1000, 7.5]]], [], null, $other);

        $stats = $this->repair();

        $this->assertSame(0, $stats['jog_smoothed']);
        $this->assertLessThan(0.01, (float) DB::selectOne(<<<'SQL'
            SELECT ST_Distance(ST_SetSRID(ST_MakePoint(?, ?), 2154), ST_Collect(ST_Transform(geom, 2154))) AS d FROM line_reference_parts WHERE line_id = ?
            SQL, [self::X0 + 1000, self::Y0 + 7.5, $this->line->id])->d);
    }

    public function test_staircase_through_a_short_parallel_piece_becomes_a_straight_track(): void
    {
        $a = $this->trace($this->line, 'bdtopo', [[0, 0], [2000, 0]]);
        $b = $this->trace($this->line, 'osm', [[900, 12], [1300, 12]]);
        // Voie 1 → saut → 140 m sur la voie 2 → saut → voie 1 (escalier).
        $this->part('bdtopo', [[[0, 0], [1000, 0]], [[1140, 0], [2000, 0]]], [$a]);
        $this->part('osm', [[[1000, 12], [1140, 12]]], [$b]);
        $this->connector([[1000, 0], [1000, 12]]);
        $this->connector([[1140, 12], [1140, 0]]);

        $stats = $this->repair();

        $this->assertSame(2, $stats['jog_removed']);
        $this->assertSame(1, $stats['hole_filled']);
        $this->assertEqualsWithDelta(140, $stats['remnant_removed_m'], 0.5);
        $this->assertSame([[0.0, 0.0], [2000.0, 0.0]], $this->freeEnds());
        $this->assertEqualsWithDelta(2000, $this->totalLength(), 0.5);
        $this->assertEqualsWithDelta(0, (float) DB::selectOne('SELECT COALESCE(MAX(ST_Y(ST_Transform(d.geom, 2154))), 0) - ? AS y FROM line_reference_parts p, LATERAL ST_DumpPoints(p.geom) d WHERE p.line_id = ?', [self::Y0, $this->line->id])->y, 0.01);
    }

    // ─── 8 bis. Raccords coudés qui ferment une boucle ──────────────────

    /** Distance (m) d'un point au tracé de référence d'une ligne. */
    private function distToLine(Line $l, float $x, float $y): float
    {
        return (float) DB::selectOne(<<<'SQL'
            SELECT ST_Distance(ST_SetSRID(ST_MakePoint(?, ?), 2154), ST_Collect(ST_Transform(geom, 2154))) AS d FROM line_reference_parts WHERE line_id = ?
            SQL, [self::X0 + $x, self::Y0 + $y, $l->id])->d;
    }

    /**
     * Ligne 30 (4.58601, 48.71751, coude de 90° en 2030) : la voie du haut finit, une courbe lissée la relie à la voie du
     * bas 40 m plus loin, mais le raccord droit de 15 m qu'elle remplaçait est resté, avec le début de la voie du bas :
     * triangle. Le raccord part (ses deux bouts restent reliés par la courbe), puis le bout de voie qu'il tenait, qui
     * longe la courbe.
     */
    private function triangle(float $reach = 40): void
    {
        $a = $this->trace($this->line, 'bdtopo', [[-1000, 15], [0, 15]]);
        $b = $this->trace($this->line, 'bdtopo', [[0, 0], [1000, 0]]);
        $this->part('bdtopo', [[[-1000, 15], [0, 15]], [[0, 0], [1000, 0]]], [$a, $b]);
        $this->connector([[0, 0], [0, 15]]);
        $curve = [];
        for ($i = 0; $i <= 8; $i++) {
            $t = $i / 8;
            $curve[] = [$reach * $t, 15 * (2 * $t ** 3 - 3 * $t ** 2 + 1)];
        }
        $this->part('bdtopo', [$curve], [$a, $b], JunctionRepair::SMOOTHED);
    }

    public function test_straight_jog_left_beside_the_curve_that_replaced_it_is_removed(): void
    {
        $this->triangle();

        $stats = $this->repair();

        $this->assertSame(1, $stats['loop_jog_removed']);
        $this->assertEqualsWithDelta(15, $stats['loop_jog_removed_m'], 0.1);
        $this->assertEqualsWithDelta(0, $this->totalLength('connector'), 0.01);
        // Le début de la voie du bas, qui ne tenait qu'au raccord, part avec lui jusqu'à la courbe.
        $this->assertEqualsWithDelta(40, $stats['stub_trimmed_m'], 0.5);
        $this->assertSame([[-1000.0, 15.0], [1000.0, 0.0]], $this->freeEnds());
        $this->assertLessThan(35, $this->maxKink());
        // Rien n'est ajouté : seuls le raccord et le bout de voie sous la courbe partent.
        $this->assertEqualsWithDelta(1000 + 960 + 40.0, $this->totalLength(), 5);
        // Second passage sans effet.
        $again = $this->repair();
        $this->assertSame(0, $again['loop_jog_removed']);
    }

    public function test_straight_jog_whose_freed_stub_would_be_long_is_kept(): void
    {
        // Ligne 49 (-0.61746, 45.74936) : le retrait du raccord laissait pendre un bout de 118 m (« dépasse » au détecteur).
        // Au-delà de LOOP_STUB_MAX_M, la boucle reste telle quelle : pas de bout libre créé.
        $this->triangle(JunctionRepair::LOOP_STUB_MAX_M + 50);

        $stats = $this->repair();

        $this->assertSame(0, $stats['loop_jog_removed']);
        $this->assertSame([[-1000.0, 15.0], [1000.0, 0.0]], $this->freeEnds());
    }

    public function test_straight_jog_whose_end_is_the_only_contact_with_another_line_is_kept(): void
    {
        // Ligne 462 (3.12058, 45.88845) : le raccord de 11 m qui fermait une boucle posait aussi la ligne sur la voie de 856,
        // qui passe là (sans y finir) ; le retirer laissait 462 à 6 m de 856. Le contact à 0 m est gardé.
        $this->triangle();
        $other = Line::factory()->create(['code' => '421000']);
        $this->part('bdtopo', [[[-200, -200], [200, 200]]], [], null, $other);

        $stats = $this->repair();

        $this->assertSame(0, $stats['loop_jog_removed']);
        $this->assertLessThan(0.01, $this->distToLine($this->line, 0, 0));
    }

    public function test_straight_jog_in_a_loop_where_another_line_ends_is_kept(): void
    {
        // Même triangle, mais une autre ligne finit au milieu du raccord : il reste, sa jonction aussi (0 m).
        $this->triangle();
        $other = Line::factory()->create(['code' => '421000']);
        $this->part('bdtopo', [[[-400, 7.5], [0, 7.5]]], [], null, $other);

        $stats = $this->repair();

        $this->assertSame(0, $stats['loop_jog_removed']);
        $this->assertLessThan(0.01, $this->distToLine($this->line, 0, 7.5));
    }

    /**
     * Ligne 30 (2.41773, 48.89873, coudes de 90° en 2030) : boîte de deux sauts de 23 m entre deux voies parallèles qui se
     * recouvrent sur 28 m. Un des deux raccords part (le premier, à angle égal) ; l'autre redevient un saut seul, traité
     * par la règle 1 bis : le bout sans issue qui dépasse son pied est coupé.
     */
    public function test_box_of_two_jogs_loses_one_jog_and_its_doubled_arm(): void
    {
        $a = $this->trace($this->line, 'bdtopo', [[0, 0], [1000, 0]]);
        $b = $this->trace($this->line, 'bdtopo', [[-1000, -23], [28, -23]]);
        $this->part('bdtopo', [[[0, 0], [1000, 0]], [[-1000, -23], [28, -23]]], [$a, $b]);
        $this->connector([[0, 0], [0, -23]]);
        $this->connector([[28, -23], [28, 0]]);

        $stats = $this->repair();

        $this->assertSame(1, $stats['loop_jog_removed']);
        $this->assertSame([[-1000.0, -23.0], [1000.0, 0.0]], $this->freeEnds());
        // Plus de doublon : nulle part deux morceaux de la ligne côte à côte sur les 28 m de la boîte.
        $this->assertLessThan(23 + 28 + 2, (float) DB::selectOne(<<<'SQL'
            SELECT COALESCE(SUM(ST_Length(ST_Intersection(ST_Transform(geom, 2154), ST_MakeEnvelope(?, ?, ?, ?, 2154)))), 0) AS m
            FROM line_reference_parts WHERE line_id = ?
            SQL, [self::X0 - 0.5, self::Y0 - 23.5, self::X0 + 28.5, self::Y0 + 0.5, $this->line->id])->m);
    }

    public function test_doubled_straight_jog_keeps_a_single_copy(): void
    {
        // Ligne 5 (7.11871, 49.03137, crochet de 175° en 2030) : le même raccord de 15 m posé deux fois entre la fin d'un
        // tracé et la voie BD TOPO. Une copie part (l'autre relie déjà ses deux bouts), l'autre reste : rien ne se détache.
        $main = $this->trace($this->line, 'bdtopo', [[-1000, 15], [1000, 15]]);
        $this->part('bdtopo', [[[-1000, 15], [1000, 15]]], [$main]);
        $this->part('archeoferroviaire', [[[0, -1000], [0, 0]]]);
        $this->connector([[0, 0], [-5, 15]], 'archeoferroviaire');
        $this->connector([[0, 0], [-5, 15]], 'archeoferroviaire');

        $stats = $this->repair();

        $this->assertSame(1, $stats['loop_jog_removed']);
        $this->assertEqualsWithDelta(hypot(5, 15), $this->totalLength('connector'), 0.1);
        $this->assertSame([[-1000.0, 15.0], [0.0, -1000.0], [1000.0, 15.0]], $this->freeEnds());
    }

    public function test_tee_jog_to_a_long_kept_parallel_track_is_restored(): void
    {
        $this->part('bdtopo', [[[0, 0], [2000, 0]]]);
        // Voie parallèle de 1 km gardée (tracé distinct), tenue par un raccord en T : trop longue pour un reste.
        $this->part('osm', [[[500, 20], [1500, 20]]]);
        $this->connector([[500, 20], [500, 0]]);

        $stats = $this->repair();

        // Raccord en T vers la voie qui continue : la voie parallèle reste, son arrivée devient une courbe tangente.
        $this->assertSame(1, $stats['jog_restored']);
        $this->assertSame(1, $stats['jog_smoothed']);
        $this->assertEqualsWithDelta(0, $stats['remnant_removed_m'], 0.01);
        $this->assertEqualsWithDelta(0, $this->totalLength('connector'), 0.01);
        $this->assertEqualsWithDelta(2000, (float) DB::selectOne("SELECT SUM(ST_Length(ST_Transform(geom, 2154))) AS m FROM line_reference_parts WHERE line_id = ? AND source = 'bdtopo' AND matched_source IS NULL", [$this->line->id])->m, 0.5);
        $this->assertLessThan(10, $this->maxKink());
    }

    public function test_tee_jog_onto_a_track_passing_it_by_a_short_dead_end_is_cut_like_an_end(): void
    {
        // Pré-en-Pail (3283), Lyon (3786) : la voie 2 commence 15 m avant le pied du saut ; gardée entière, ce bout et la voie
        // jusqu'au point de raccord J doublaient la courbe en S (tronçons en double).
        $this->part('bdtopo', [[[0, 0], [1000, 0]]]);
        $this->part('osm', [[[985, 12], [2000, 12]]]);
        $this->connector([[1000, 0], [1000, 12]]);

        $stats = $this->repair();

        $this->assertSame(1, $stats['jog_smoothed']);
        $this->assertSame([[0.0, 0.0], [2000.0, 12.0]], $this->freeEnds());
        $this->assertLessThan(10, $this->maxKink());
        // Rien ne longe la courbe : hors de ses 5 premiers et derniers mètres, aucun autre morceau à moins de 5 m.
        $this->assertSame(0, (int) DB::selectOne(<<<'SQL'
            WITH c AS (SELECT ST_Transform(geom, 2154) AS g FROM line_reference_parts WHERE line_id = ? AND matched_source = ?),
            inner_c AS (SELECT ST_LineSubstring(g, 5 / ST_Length(g), 1 - 5 / ST_Length(g)) AS g FROM c)
            SELECT COUNT(*) AS n FROM line_reference_parts p, inner_c
            WHERE p.line_id = ? AND p.matched_source IS DISTINCT FROM ? AND ST_Length(ST_Intersection(ST_Transform(p.geom, 2154), ST_Buffer(inner_c.g, 5))) > 1
            SQL, [$this->line->id, JunctionRepair::SMOOTHED, $this->line->id, JunctionRepair::SMOOTHED])->n);
    }

    public function test_dead_end_stub_past_a_jog_foot_is_cut_even_when_the_s_curve_is_refused(): void
    {
        // Strasbourg (523, au nord de la gare, état du 21/11/2026 relatif au point 7.73709, 48.58848) : la ligne suit la
        // voie OSM A jusqu'à son bout, saute de 4,4 m sur la voie B, qui commence 20 m plus tôt (bout sans issue le long
        // de A). La courbe en S est refusée (ses bouts longent les portions voisines) ; le saut reste droit, mais le
        // bout de B est coupé : sinon le nettoyage final recolle A sur B et laisse un V de 20 m en double.
        DB::table('lines')->where('id', $this->line->id)->update(['network' => 'principal']);
        $this->part('bdtopo', [[[-185.3, -380.8], [-12.1, -20.7]]]);
        $this->part('osm', [[[-12.1, -20.7], [-9.4, -21.7], [-6.5, -14.0], [-5.4, -11.3], [-4.6, -9.5], [-4.1, -8.2], [-2.1, -4.1], [2.8, 5.9],
            [4.4, 9.6], [7.1, 15.3], [11.6, 24.0]]]);
        $this->connector([[11.6, 24.0], [7.7, 26.0]], 'osm');
        $this->part('osm', [[[-1.3, 8.4], [6.8, 24.2], [18.0, 46.4], [26.6, 61.8]]]);
        $this->part('osm', [[[26.6, 61.8], [49.7, 104.4]]]);
        $this->part('bdtopo', [[[49.7, 104.4], [191.2, 377.8]]]);

        $stats = $this->repair();

        $this->assertSame(0, $stats['jog_smoothed']);
        $this->assertEqualsWithDelta(19.75, $stats['stub_trimmed_m'], 0.5);
        $this->assertSame([[-185.3, -380.8], [191.2, 377.8]], $this->freeEnds());
    }

    public function test_jog_where_the_line_turns_back_keeps_the_dead_end_past_its_foot(): void
    {
        // Revigny (891, 019000 ; état du 21/11/2026 relatif au point 4.985808, 48.823967) : deux morceaux recalés sur la
        // BD TOPO (1,2 km et 1 km), à 12 m l'un de l'autre, arrivent de l'ouest ; le second finit sur un saut de 13 m vers
        // le premier, qui le dépasse de 11 m jusqu'à un bout libre. La ligne fait demi-tour sur le saut (refus « sens ») :
        // le bout continue le sens d'arrivée et ne longe aucune voie, il reste.
        DB::table('lines')->where('id', $this->line->id)->update(['network' => 'principal']);
        $this->part('archeoferroviaire', [[[-1050.3, -568.4], [-1046.1, -565.4], [-1021.9, -550.0], [-917.8, -488.8], [-886.1, -469.4], [-877.1, -463.9],
            [-813.1, -424.5], [-771.6, -397.4], [-723.9, -368.4], [-676.9, -345.6], [-610.2, -313.1], [-539.0, -282.3], [-492.0, -260.2], [-449.2, -239.5],
            [-385.2, -203.3], [-328.6, -170.2], [-327.9, -169.7], [-294.7, -149.4], [-233.6, -113.6], [-188.6, -87.2], [-157.6, -71.8], [-134.1, -60.0],
            [-101.0, -44.2], [-90.2, -39.1], [-73.2, -30.9], [-17.6, -7.6], [0.0, 0.0], [10.4, 4.6]]], [], 'bdtopo');
        $this->part('archeoferroviaire', [[[-900.3, -420.6], [-880.4, -406.3], [-863.2, -394.0], [-817.0, -364.5], [-786.7, -345.3], [-744.6, -320.3],
            [-696.6, -296.2], [-652.9, -275.2], [-615.6, -258.4], [-570.8, -237.4], [-504.7, -208.9], [-432.1, -176.5], [-387.5, -155.9], [-323.0, -125.7],
            [-257.2, -97.2], [-243.8, -91.1], [-198.8, -70.8], [-169.9, -58.1], [-167.4, -57.0], [-141.0, -45.4], [-106.8, -31.4], [-85.7, -22.7],
            [-6.3, 12.1], [-5.4, 12.4]]], [], 'bdtopo');
        $this->connector([[-5.4, 12.4], [0.0, 0.0]], 'archeoferroviaire');

        $stats = $this->repair();

        $this->assertSame(0, $stats['jog_smoothed']);
        $this->assertSame(0.0, $stats['stub_trimmed_m']);
        $this->assertContains([10.4, 4.6], $this->freeEnds());
    }

    public function test_jog_between_diverging_tracks_keeps_the_dead_end_past_its_foot(): void
    {
        // Saint-Denis (554 ; état du 21/11/2026 relatif au point 2.353591, 48.910094) : la ligne arrive du sud-est sur un
        // tracé OHM recalé sur OSM, saute de 20 m vers une voie BD TOPO qui part vers le nord et la dépasse de 14 m vers le
        // sud jusqu'à un bout libre. Les deux voies divergent de plus de 30° (refus « cap ») : le bout ne longe pas l'autre
        // voie, il reste.
        DB::table('lines')->where('id', $this->line->id)->update(['network' => 'principal']);
        $this->part('bdtopo', [[[-67.6, 103.7], [-55.3, 73.5], [-51.5, 64.4], [-43.2, 59.1], [-39.6, 56.8], [-37.2, 55.3], [-34.9, 53.6], [-32.7, 51.8],
            [-18.5, 39.9]]]);
        $this->connector([[-18.5, 39.9], [-4.5, 19.4]]);
        $this->part('bdtopo', [[[-4.5, 19.4], [-0.6, 3.0], [0.0, 0.0], [2.8, -13.7]]]);
        $this->connector([[19.2, 3.9], [0.0, 0.0]], 'ohm');
        $this->part('ohm', [[[102.3, -171.0], [100.8, -160.3], [97.4, -144.1], [94.1, -132.4], [90.9, -121.9], [84.1, -102.5], [80.1, -92.2],
            [76.0, -82.0], [71.9, -72.5], [69.4, -67.0], [65.4, -58.2], [59.0, -46.7], [56.6, -42.8], [53.8, -38.5], [49.0, -31.7], [42.4, -23.1],
            [41.0, -21.2], [39.4, -19.2], [30.4, -8.4], [23.2, -0.2], [19.2, 3.9]]], [], 'osm');

        $stats = $this->repair();

        $this->assertSame(0, $stats['jog_smoothed']);
        $this->assertSame(0.0, $stats['stub_trimmed_m']);
        $this->assertContains([2.8, -13.7], $this->freeEnds());
    }

    /**
     * Après la réparation puis le nettoyage final : longueur (m) de tracé qui longe à moins de 5 m un autre morceau de
     * la ligne, hors des 5 premiers et derniers mètres de chaque morceau (raccords en Y), et morceaux restants.
     *
     * @return array{0: float, 1: int}
     */
    private function doubledAfterTidy(): array
    {
        (new SingleAxis)->tidy($this->line->id);
        $r = DB::selectOne(<<<'SQL'
            WITH s AS (SELECT row_number() OVER () AS i, d.geom AS g FROM line_reference_parts p, LATERAL ST_Dump(ST_Transform(p.geom, 2154)) d WHERE p.line_id = ?),
            inner_s AS (SELECT i, ST_LineSubstring(g, LEAST(0.5, 5 / ST_Length(g)), GREATEST(0.5, 1 - 5 / ST_Length(g))) AS g FROM s)
            SELECT COALESCE(SUM(ST_Length(ST_Intersection(a.g, ST_Buffer(b.g, 5)))), 0) AS m, (SELECT COUNT(*) FROM s) AS n
            FROM inner_s a JOIN s b ON b.i <> a.i AND ST_DWithin(a.g, b.g, 5)
            SQL, [$this->line->id]);

        return [(float) $r->m, (int) $r->n];
    }

    public function test_two_jog_loop_at_batignolles_loses_its_doubled_arm(): void
    {
        // Batignolles (802, 334900 ; état du 21/11/2026 relatif au point 2.3096, 48.8905) : deux voies BD TOPO parallèles à
        // 5 m. A finit sur un saut de 5 m vers le milieu de B ; la tête de B revient 65 m le long de A et n'est tenue que par
        // un second saut de 6 m posé sur A. La courbe en S de A vers B était posée, B gardée entière : le nettoyage final
        // retirait la courbe (à moins de 5 m de B) et laissait un triangle de 27 m en double (18 paires de tronçons).
        DB::table('lines')->where('id', $this->line->id)->update(['network' => 'principal']);
        $this->part('bdtopo', [[[-180.6, 172.2], [-163.4, 156.5], [-133.4, 129.7], [-103.8, 103.1], [-86.4, 87.5], [-71.4, 74.0], [-62.4, 66.0],
            [-51.0, 56.3], [-38.1, 45.1], [-25.4, 33.7], [-16.2, 25.6], [-2.5, 13.6], [19.3, -6.2], [45.0, -27.9]]]);
        $this->part('bdtopo', [[[-7.8, 9.9], [43.4, -33.2], [74.7, -60.6], [104.1, -86.1], [131.7, -109.1], [182.0, -152.3], [190.8, -159.7]]]);
        $this->connector([[45.0, -27.9], [41.8, -31.8]]);
        $this->connector([[-7.8, 9.9], [-3.6, 14.7]]);

        $stats = $this->repair();

        $this->assertSame(1, $stats['jog_smoothed']);
        $this->assertEqualsWithDelta(0, $this->totalLength('connector'), 0.01);
        $this->assertSame([[-180.6, 172.2], [190.8, -159.7]], $this->freeEnds());
        $this->assertLessThan(10, $this->maxKink());
        [$doubled, $n] = $this->doubledAfterTidy();
        $this->assertLessThan(1, $doubled);
        $this->assertSame(1, DB::table('line_reference_parts')->where('line_id', $this->line->id)->where('matched_source', JunctionRepair::SMOOTHED)->count());
        $this->assertSame(3, $n);
    }

    public function test_two_jog_loop_at_dercy_versigny_loses_its_doubled_arm(): void
    {
        // Dercy - Versigny (3614 ; état du 21/11/2026 relatif au point 3.5810, 49.6868) : tracé archeo recalé sur OSM (A) puis
        // sur la BD TOPO (B), à 3-4 m ; même boucle de deux sauts (3 et 4 m), bras de 35 m (16 paires de tronçons en double).
        $this->part('archeoferroviaire', [[[-196.9, 31.2], [-173.5, 27.8], [19.2, -5.1]]], [], 'osm');
        $this->part('archeoferroviaire', [[[-14.1, 5.1], [-6.2, 3.3], [67.2, -12.3], [180.2, -31.0], [196.7, -33.6]]], [], 'bdtopo');
        $this->connector([[19.2, -5.1], [19.8, -2.3]], 'archeoferroviaire');
        $this->connector([[-14.1, 5.1], [-14.8, 0.7]], 'archeoferroviaire');

        $stats = $this->repair();

        $this->assertSame(1, $stats['jog_smoothed']);
        $this->assertEqualsWithDelta(0, $this->totalLength('connector'), 0.01);
        $this->assertSame([[-196.9, 31.2], [196.7, -33.6]], $this->freeEnds());
        [$doubled, $n] = $this->doubledAfterTidy();
        $this->assertLessThan(1, $doubled);
        $this->assertSame(3, $n);
    }

    public function test_two_jog_loop_whose_arm_holds_another_line_is_kept(): void
    {
        // Même boucle, mais une autre ligne finit sur le bras : il reste, avec son raccord.
        DB::table('lines')->where('id', $this->line->id)->update(['network' => 'principal']);
        $this->part('bdtopo', [[[-180.6, 172.2], [-103.8, 103.1], [-51.0, 56.3], [-16.2, 25.6], [-2.5, 13.6], [19.3, -6.2], [45.0, -27.9]]]);
        $this->part('bdtopo', [[[-7.8, 9.9], [43.4, -33.2], [104.1, -86.1], [190.8, -159.7]]]);
        $this->connector([[45.0, -27.9], [41.8, -31.8]]);
        $this->connector([[-7.8, 9.9], [-3.6, 14.7]]);
        $other = Line::factory()->create(['code' => '421000', 'gauge_mm_default' => 1435]);
        $this->part('bdtopo', [[[17.8, -11.6], [300, 100]]], [], null, $other);

        $this->repair();

        $this->assertGreaterThan(5, $this->totalLength('connector'));
        $this->assertNotContains([-7.8, 9.9], $this->freeEnds());
        $this->assertLessThan(0.1, (float) DB::selectOne(<<<'SQL'
            SELECT ST_Distance(ST_SetSRID(ST_MakePoint(?, ?), 2154), ST_Collect(ST_Transform(geom, 2154))) AS d FROM line_reference_parts WHERE line_id = ?
            SQL, [self::X0 + 17.8, self::Y0 - 11.6, $this->line->id])->d);
    }

    /** La voie 2 part-elle toujours de (985, 12) et va-t-elle jusqu'au bout (x > 1 900) ? */
    private function secondTrackStartsAtTheStub(): bool
    {
        return (int) DB::selectOne(<<<'SQL'
            SELECT COUNT(*) AS n FROM line_reference_parts p, LATERAL ST_Dump(ST_Transform(p.geom, 2154)) d
            WHERE p.line_id = ? AND p.source = 'osm' AND ST_DWithin(ST_StartPoint(d.geom), ST_SetSRID(ST_MakePoint(?, ?), 2154), 0.5) AND ST_X(ST_EndPoint(d.geom)) > ?
            SQL, [$this->line->id, self::X0 + 985, self::Y0 + 12, self::X0 + 1900])->n === 1;
    }

    public function test_tee_jog_onto_a_short_overshoot_that_holds_another_portion_keeps_the_through_track(): void
    {
        // Même saut, mais une autre portion de la ligne part du bout de la voie 2 : ce n'est pas un bout sans issue.
        $this->part('bdtopo', [[[0, 0], [1000, 0]]]);
        $this->part('osm', [[[985, 12], [2000, 12]]]);
        $this->part('osm', [[[985, 12], [985, 400]]]);
        $this->connector([[1000, 0], [1000, 12]]);

        $this->repair();

        $this->assertContains([985.0, 400.0], $this->freeEnds());
        $this->assertNotContains([985.0, 12.0], $this->freeEnds());
        $this->assertTrue($this->secondTrackStartsAtTheStub());
    }

    public function test_tee_jog_onto_a_short_overshoot_where_another_line_ends_keeps_the_through_track(): void
    {
        // Le bout de la voie 2 porte la jonction d'une autre ligne : le couper la laisserait en l'air.
        $this->part('bdtopo', [[[0, 0], [1000, 0]]]);
        $this->part('osm', [[[985, 12], [2000, 12]]]);
        $this->connector([[1000, 0], [1000, 12]]);
        $other = Line::factory()->create(['code' => '421000', 'gauge_mm_default' => 1435]);
        $this->part('osm', [[[990, 12], [990, 600]]], [], null, $other);

        $stats = $this->repair();

        // Raccord en T : la courbe se pose sur la voie 2, gardée entière.
        $this->assertSame(1, $stats['jog_smoothed']);
        $this->assertTrue($this->secondTrackStartsAtTheStub());
    }

    // ─── Allers-retours ─────────────────────────────────────────────────

    public function test_dead_end_spur_running_along_the_line_from_a_node_is_removed(): void
    {
        // La Ferté-Macé (434000) : la ligne est dessinée sur sa voie et, depuis l'aiguille, sur l'autre voie du faisceau
        // qui s'en écarte doucement jusqu'à 25 m puis s'arrête : un aller-retour en V à l'écran.
        $this->part('bdtopo', [[[0, 0], [1000, 0]], [[1000, 0], [2000, 0]]]);
        $this->part('osm', [[[1000, 0], [1200, 15], [1400, 25]]]);

        $stats = $this->repair();

        $this->assertSame(1, $stats['spur_removed']);
        $this->assertEqualsWithDelta(hypot(200, 15) + hypot(200, 10), $stats['spur_removed_m'], 0.5);
        $this->assertEqualsWithDelta(2000, $this->totalLength(), 0.5);
    }

    public function test_parallel_dead_end_track_continuing_a_portion_past_a_fork_is_cut_at_the_fork(): void
    {
        // La voie est de La Ferté-Macé : la portion part de la gare (terminus), passe l'aiguille puis continue sur la voie
        // de faisceau jusqu'à un butoir ; la voie de la ligne part de l'aiguille, raccordée au milieu de cette portion.
        $this->part('osm', [[[0, 0], [200, 0], [400, 15], [650, 25]]]);
        $this->part('osm', [[[200, 0], [2000, 0]]]);

        $stats = $this->repair();

        $this->assertSame(1, $stats['spur_removed']);
        $this->assertEqualsWithDelta(hypot(200, 15) + hypot(250, 10), $stats['spur_removed_m'], 0.5);
        $this->assertEqualsWithDelta(2000, $this->totalLength(), 0.5);
        $this->assertSame([[0.0, 0.0], [2000.0, 0.0]], $this->freeEnds());
    }

    public function test_dead_end_branch_that_leaves_the_line_is_kept(): void
    {
        $this->part('bdtopo', [[[0, 0], [1000, 0]], [[1000, 0], [2000, 0]]]);
        // 20° : à 30 m de la ligne dès 90 m, ce n'est pas une voie du même faisceau.
        $this->part('osm', [[[1000, 0], [1400, 400 * tan(deg2rad(20))]]]);
        // 40° : trop ouvert pour un éperon.
        $this->part('osm', [[[500, 0], [700, 200 * tan(deg2rad(40))]]]);

        $stats = $this->repair();

        $this->assertSame(0, $stats['spur_removed']);
    }

    public function test_hairpin_coming_back_along_the_same_portion_is_cut(): void
    {
        // Le tracé dépasse la gare de 300 m puis revient 200 m en arrière à 10 m de lui-même et s'arrête.
        $this->part('osm', [[[0, 0], [1300, 0], [1100, 10]]]);

        $stats = $this->repair();

        $this->assertSame(1, $stats['spur_removed']);
        $this->assertEqualsWithDelta(1300, $this->totalLength(), 1.0);
        $this->assertSame([[0.0, 0.0], [1300.0, 0.0]], $this->freeEnds());
    }

    public function test_hairpin_branch_holding_another_portion_of_the_line_is_kept(): void
    {
        // Même aller-retour, mais une autre portion de la ligne part de la branche de retour (x = 1 200) vers le nord :
        // retirer la branche la laisserait détachée (règle 8 a, revue du 21/11/2026).
        $this->part('osm', [[[0, 0], [1300, 0], [1100, 10]]]);
        $this->part('bdtopo', [[[1200, 5], [1200, 500]]]);
        $before = $this->totalLength();

        $stats = $this->repair();

        $this->assertSame(0, $stats['spur_removed']);
        $this->assertEqualsWithDelta($before, $this->totalLength(), 0.01);
        $this->assertSame([[0.0, 0.0], [1100.0, 10.0], [1200.0, 500.0]], $this->freeEnds());
    }

    // ─── Raccords pendants (8 ter) ──────────────────────────────────────

    /** Ligne 472 (111000) à 7.37770, 48.74410 : portion recalée sur OSM, raccord droit de 37 m qui ne touche plus rien. */
    private function danglingConnector(): void
    {
        $this->part('osm', [[[262.1, -53.6], [152.6, 19.6], [127.9, 36.2], [85.8, 64.5], [62.7, 79.8], [57.1, 83.5], [44.6, 91.8]]], [], 'osm');
        $this->connector([[57.1, 83.5], [36.8, 52.7]]);
    }

    public function test_straight_connector_whose_far_end_touches_nothing_is_removed(): void
    {
        $this->danglingConnector();

        $stats = $this->repair();

        $this->assertSame(1, $stats['dangling_connector_removed']);
        $this->assertEqualsWithDelta(36.9, $stats['dangling_connector_removed_m'], 0.1);
        $this->assertEqualsWithDelta(0.0, $this->totalLength('connector'), 0.01);
        $this->assertSame([[44.6, 91.8], [262.1, -53.6]], $this->freeEnds());

        // Second passage sans effet.
        $this->assertSame(0, $this->repair()['dangling_connector_removed']);
    }

    public function test_dangling_connector_where_another_line_ends_is_kept(): void
    {
        $this->danglingConnector();
        $other = Line::factory()->create(['code' => '421000', 'gauge_mm_default' => 1435]);
        // L'autre ligne arrive au milieu du raccord : le retirer laisserait sa jonction en l'air.
        $this->part('osm', [[[-300, -200], [46.9, 68.1]]], [], null, $other);

        $stats = $this->repair();

        $this->assertSame(0, $stats['dangling_connector_removed']);
        $this->assertEqualsWithDelta(36.9, $this->totalLength('connector'), 0.1);
    }

    public function test_dangling_connector_whose_free_end_lies_on_another_line_is_kept(): void
    {
        $this->danglingConnector();
        $other = Line::factory()->create(['code' => '421000', 'gauge_mm_default' => 1435]);
        // Le dessin d'une autre ligne passe par le bout libre : contact à 0 m que le retrait ferait perdre.
        $this->part('osm', [[[-400, 52.7], [36.8, 52.7], [500, 52.7]]], [], null, $other);

        $stats = $this->repair();

        $this->assertSame(0, $stats['dangling_connector_removed']);
    }

    public function test_dangling_connector_that_nearly_reaches_another_portion_of_the_line_is_kept(): void
    {
        // Forme de la ligne 341116 au Havre (49.50357, 0.19200) : raccord de 15 m qui part du bout d'une voie et manque de
        // 4,3 m la voie suivante de la même ligne. Le retirer ouvrirait un trou de 16 m sur la voie (lines.reference_holes).
        $this->part('osm', [[[-500, 0], [0, 0]]], [], 'osm');
        $this->part('bdtopo', [[[18.5, 4.5], [600, 4.5]]], [], 'bdtopo');
        $this->connector([[0, 0], [14.5, 3.5]]);

        $stats = $this->repair();

        $this->assertSame(0, $stats['dangling_connector_removed']);
        $this->assertEqualsWithDelta(14.9, $this->totalLength('connector'), 0.1);
    }

    public function test_connector_held_at_both_ends_is_not_a_dangling_connector(): void
    {
        $this->danglingConnector();
        $this->part('osm', [[[36.8, 52.7], [-200, -150]]], [], 'osm');

        $stats = $this->repair();

        $this->assertSame(0, $stats['dangling_connector_removed']);
    }

    public function test_real_reversal_whose_branches_part_is_kept(): void
    {
        // Rebroussement : la seconde branche repart à 153° et s'écarte de la première (gare en impasse).
        $this->part('osm', [[[0, 0], [1000, 0], [700, 150]]]);
        $before = $this->totalLength();

        $stats = $this->repair();

        $this->assertSame(0, $stats['spur_removed']);
        $this->assertEqualsWithDelta($before, $this->totalLength(), 0.01);
    }

    public function test_long_bubble_held_by_two_jogs_is_removed(): void
    {
        // Épône-est : la ligne passe 520 m sur la voie voisine (y = 25) entre deux sauts perpendiculaires, alors que sa voie
        // (y = 0) continue sous la boucle ; aucune communication réelle.
        $a = $this->trace($this->line, 'bdtopo', [[0, 0], [2000, 0]]);
        $b = $this->trace($this->line, 'osm', [[500, 25], [1020, 25]]);
        $this->part('bdtopo', [[[0, 0], [2000, 0]]], [$a]);
        $this->part('osm', [[[500, 25], [1020, 25]]], [$b]);
        $this->connector([[500, 0], [500, 25]]);
        $this->connector([[1020, 25], [1020, 0]]);

        $stats = $this->repair();

        $this->assertSame(2, $stats['jog_removed']);
        $this->assertEqualsWithDelta(520, $stats['remnant_removed_m'], 0.5);
        $this->assertEqualsWithDelta(2000, $this->totalLength(), 0.5);
    }

    public function test_long_parallel_track_held_by_a_single_jog_is_not_a_bubble(): void
    {
        $this->part('bdtopo', [[[0, 0], [2000, 0]]]);
        $this->part('osm', [[[500, 20], [1300, 20]]]);
        $this->connector([[500, 20], [500, 0]]);

        $stats = $this->repair();

        $this->assertEqualsWithDelta(0, $stats['remnant_removed_m'], 0.01);
    }

    public function test_short_kept_distinct_alignment_is_not_a_remnant(): void
    {
        $this->part('bdtopo', [[[0, 0], [1000, 0]], [[1200, 0], [2000, 0]]]);
        $this->part('archeoferroviaire', [[[1000, 15], [1200, 15]]], [], 'osm');
        $this->connector([[1000, 0], [1000, 15]]);
        $this->connector([[1200, 15], [1200, 0]]);
        // « Un seul axe » a gardé cette portion comme ancien tracé daté.
        DB::table('line_reference_duplicates')->insert([
            'line_id' => $this->line->id, 'action' => 'kept_distinct', 'reason' => 'ancien tracé daté', 'source' => 'archeoferroviaire',
            'trace_ids' => '[]', 'kept_trace_ids' => '[]', 'length_m' => 200, 'gap_median_m' => 15, 'created_at' => now(), 'updated_at' => now(),
            'geom' => DB::raw("ST_Transform(ST_GeomFromText('".$this->wkt([[1000, 15], [1200, 15]])."', 2154), 4326)"),
        ]);

        $stats = $this->repair();

        $this->assertEqualsWithDelta(0, $stats['remnant_removed_m'], 0.01);
        $this->assertSame(2, $stats['jog_restored']);
    }

    public function test_straight_connector_is_not_a_jog(): void
    {
        $this->part('bdtopo', [[[0, 0], [1000, 0]], [[1015, 0], [2000, 0]]]);
        $this->connector([[1000, 0], [1015, 0]]);

        $stats = $this->repair();

        $this->assertSame(0, $stats['jog_removed']);
        $this->assertSame(0, $stats['jog_restored']);
        $this->assertEqualsWithDelta(15, $this->totalLength('connector'), 0.01);
    }

    public function test_kinked_connector_to_a_coarse_trace_is_left_alone(): void
    {
        // Le tracé grossier n'est pas une voie : le raccord vers lui n'est pas un saut entre voies.
        $this->trace($this->line, 'bdtopo', [[0, 0], [1200, 0], [1500, 0]]);
        $this->trace(null, 'bdtopo', [[1200, 0], [1320, 15]], ['layer' => 'Voie de service']);
        $this->part('bdtopo', [[[0, 0], [1000, 0]]]);
        $this->part('archeoferroviaire', [[[1000, 15], [2000, 15]]]);
        $this->connector([[1000, 0], [1000, 15]]);

        $stats = $this->repair();

        $this->assertSame(0, $stats['jog_removed'] + $stats['jog_restored']);
        $this->assertSame(0, $stats['rerouted']);
        // Mais la marche (raccord à angle droit vers le tracé grossier) est lissée par la règle 7 (courbe estimée).
        $this->assertSame(1, $stats['leg_smoothed']);
        $this->assertEqualsWithDelta(0, $this->totalLength('connector'), 0.01);
    }

    public function test_jog_is_rerouted_from_the_far_side_when_only_that_side_reaches_a_crossover(): void
    {
        // Voie 2 (y = 15) jusqu'à x = 1 000 puis voie 1 (y = 0) : la communication est AVANT le saut, vue depuis la voie 1.
        $a = $this->trace($this->line, 'bdtopo', [[500, 0], [880, 0], [2000, 0]]);
        $b = $this->trace($this->line, 'bdtopo', [[0, 15], [760, 15], [1000, 15]]);
        $this->trace(null, 'bdtopo', [[760, 15], [880, 0]], ['layer' => 'Voie de service']);
        $this->part('bdtopo', [[[0, 15], [1000, 15]], [[1000, 0], [2000, 0]]], [$a, $b]);
        $this->connector([[1000, 15], [1000, 0]]);

        $stats = $this->repair();

        $this->assertSame(1, $stats['rerouted']);
        $this->assertEqualsWithDelta(0, $this->totalLength('connector'), 0.01);
        $this->assertSame([[0.0, 15.0], [2000.0, 0.0]], $this->freeEnds());
        $this->assertLessThan(10, $this->maxKink());
    }

    public function test_crossover_whose_stub_does_not_run_along_the_new_path_is_refused(): void
    {
        // La voie 2 part vers le nord dès le saut : son début ne longe pas le chemin, on ne la raccourcit pas.
        $a = $this->trace($this->line, 'bdtopo', [[0, 0], [1200, 0], [1500, 0]]);
        $b = $this->trace($this->line, 'bdtopo', [[1000, 15], [1100, 200], [1320, 15], [2000, 15]]);
        $this->trace(null, 'bdtopo', [[1200, 0], [1320, 15]], ['layer' => 'Voie de service']);
        $this->part('bdtopo', [[[0, 0], [1000, 0]], [[1000, 15], [1100, 200], [1320, 15], [2000, 15]]], [$a, $b]);
        $this->connector([[1000, 0], [1000, 15]]);

        $stats = $this->repair();

        $this->assertSame(0, $stats['rerouted']);
        $this->assertSame(1, $stats['jog_restored']);
    }

    public function test_diverging_piece_held_by_jogs_is_kept_with_its_jogs(): void
    {
        $a = $this->trace($this->line, 'bdtopo', [[0, 0], [2000, 0]]);
        // Après le saut, la portion s'écarte (embranchement) : ce n'est pas un reste parallèle.
        $this->part('bdtopo', [[[0, 0], [1000, 0]], [[1140, 0], [2000, 0]]], [$a]);
        $this->part('osm', [[[1000, 12], [1060, 55], [1140, 55]]]);
        $this->connector([[1000, 0], [1000, 12]]);
        $this->connector([[1140, 55], [1140, 0]]);

        $stats = $this->repair();

        $this->assertSame(1, $stats['hole_filled']);
        $this->assertEqualsWithDelta(0, $stats['remnant_removed_m'], 0.01);
        $this->assertSame(2, $stats['jog_restored']);
    }

    public function test_fill_on_another_line_track_is_a_matched_part(): void
    {
        $other = Line::factory()->create(['code' => '430000', 'gauge_mm_default' => 1435]);
        $own = $this->trace($this->line, 'bdtopo', [[0, 0], [1000, 0]]);
        $shared = $this->trace($other, 'bdtopo', [[1000, 0], [1100, 0]]);
        $this->part('bdtopo', [[[0, 0], [1000, 0]], [[1100, 0], [2000, 0]]], [$own]);

        $stats = $this->repair();

        $this->assertSame(1, $stats['hole_filled']);
        $added = DB::table('line_reference_parts')->where('line_id', $this->line->id)->orderByDesc('id')->first();
        $this->assertSame('bdtopo', $added->source);
        $this->assertSame('bdtopo', $added->matched_source, 'voie d\'une autre ligne : recalage (tronc commun), pas une voie de la ligne');
        $this->assertSame([$shared], json_decode($added->matched_trace_ids, true));
        $this->assertSame([$own], json_decode($added->source_trace_ids, true));
    }

    public function test_repair_is_idempotent(): void
    {
        $this->jogNetwork(crossover: true);
        $this->repair();
        $sig = fn () => DB::selectOne('SELECT md5(string_agg(encode(ST_AsEWKB(geom), \'hex\'), \',\' ORDER BY id)) AS s FROM line_reference_parts WHERE line_id = ?', [$this->line->id])->s;
        $before = $sig();

        $stats = $this->repair();

        $this->assertSame([], array_filter($stats));
        $this->assertSame($before, $sig());
    }

    // ─── Longs trous sur les voies abandonnées ──────────────────────────

    /** Ligne disparue dessinée de 0 à 1 000 m et de 4 000 à 5 000 m (y = 0) : trou de 3 km. */
    private function longGap(): void
    {
        $this->part('archeoferroviaire', [[[0, 0], [1000, 0]], [[4000, 0], [5000, 0]]], [], 'osm');
    }

    /** Voie OSM abandonnée, rattachée à aucune ligne. */
    private function abandoned(array $pts, string $layer = 'abandoned'): int
    {
        return $this->trace(null, 'osm', $pts, ['layer' => $layer]);
    }

    /** Gare de la ligne en ($x, $y), PK en m (source SNCF) ou sans PK. */
    private function station(float $x, float $y, ?int $pk): void
    {
        $id = Station::factory()->create()->id;
        DB::update('UPDATE stations SET geom = ST_Transform(ST_SetSRID(ST_MakePoint(?, ?), 2154), 4326) WHERE id = ?', [self::X0 + $x, self::Y0 + $y, $id]);
        DB::table('station_lines')->insert(['station_id' => $id, 'line_id' => $this->line->id, 'method' => 'sncf_pk', 'pk_m' => $pk,
            'pk_source' => $pk !== null ? 'sncf' : null, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_long_gap_is_followed_on_the_abandoned_track(): void
    {
        $this->longGap();
        $id = $this->abandoned([[1000, 0], [2000, 150], [3000, 150], [4000, 0]]);

        $stats = $this->repair();

        $this->assertSame(1, $stats['long_gap_filled']);
        $this->assertSame(0, $stats['long_gap_partial']);
        $this->assertSame([[0.0, 0.0], [5000.0, 0.0]], $this->freeEnds());
        // Dessin de la voie abandonnée, définition de la portion prolongée.
        $added = DB::table('line_reference_parts')->where('line_id', $this->line->id)->orderByDesc('id')->first();
        $this->assertSame('archeoferroviaire', $added->source);
        $this->assertSame('osm', $added->matched_source);
        $this->assertSame([$id], json_decode($added->matched_trace_ids, true));
    }

    public function test_long_gap_path_stopping_short_leaves_the_last_stretch_visible(): void
    {
        // 371000 entre Gravigny et Hondouville : la voie abandonnée s'arrête 600 m avant l'autre bout.
        $this->longGap();
        $this->abandoned([[1000, 0], [2000, 100], [3000, 100], [3400, 0]], 'razed');

        $stats = $this->repair();

        $this->assertSame(1, $stats['long_gap_filled']);
        $this->assertSame(1, $stats['long_gap_partial']);
        $this->assertEqualsWithDelta(600, $stats['long_gap_left_m'], 1);
        $this->assertSame([[0.0, 0.0], [3400.0, 0.0], [4000.0, 0.0], [5000.0, 0.0]], $this->freeEnds());
    }

    public function test_long_gap_path_crossing_a_short_missing_piece_is_followed(): void
    {
        $this->longGap();
        // Morceau rasé non dessiné de 20 m au milieu, puis BD TOPO « Sans objet » jusqu'au bout.
        $this->abandoned([[1000, 0], [2500, 0]]);
        $this->trace(null, 'bdtopo', [[2520, 0], [4000, 0]], ['layer' => 'Sans objet']);

        $stats = $this->repair();

        $this->assertSame(1, $stats['long_gap_filled']);
        $this->assertSame([[0.0, 0.0], [5000.0, 0.0]], $this->freeEnds());
    }

    public function test_long_gap_path_stopping_too_far_is_refused(): void
    {
        $this->longGap();
        $this->abandoned([[1000, 0], [2000, 0]]);

        $stats = $this->repair();

        $this->assertSame(0, $stats['long_gap_filled']);
        $this->assertGreaterThanOrEqual(1, $stats['long_gap_refused']);
        $this->assertCount(4, $this->freeEnds());
    }

    public function test_long_gap_path_on_another_line_is_refused(): void
    {
        $this->longGap();
        $this->abandoned([[1000, 0], [2000, 150], [3000, 150], [4000, 0]]);
        // Une autre ligne est déjà dessinée sur cette voie : ce sont ses rails, pas ceux de la ligne.
        $other = Line::factory()->create(['code' => '421000']);
        $this->part('osm', [[[1500, 75], [2000, 150], [3000, 150], [3500, 75]]], [], null, $other);

        $stats = $this->repair();

        $this->assertSame(0, $stats['long_gap_filled']);
        $this->assertGreaterThanOrEqual(1, $stats['long_gap_refused']);
    }

    public function test_long_gap_path_missing_a_station_of_the_line_is_refused(): void
    {
        $this->longGap();
        $this->abandoned([[1000, 0], [2000, 150], [3000, 150], [4000, 0]]);
        // Gare de la ligne au milieu du trou, à 900 m de cette voie : la ligne passait ailleurs.
        $this->station(2500, -750, null);

        $this->assertSame(0, $this->repair()['long_gap_filled']);
    }

    public function test_long_gap_path_with_stations_in_the_wrong_order_is_refused(): void
    {
        $this->longGap();
        $this->abandoned([[1000, 0], [2000, 150], [3000, 150], [4000, 0]]);
        $this->station(900, 20, 10000);
        $this->station(4100, 20, 13100);
        // Le long de la voie, les PK reculent : ce n'est pas la ligne.
        $this->station(2000, 170, 12500);
        $this->station(3000, 170, 11200);

        $this->assertSame(0, $this->repair()['long_gap_filled']);
    }

    public function test_long_gap_path_too_long_for_the_pk_gap_is_refused(): void
    {
        $this->longGap();
        $this->abandoned([[1000, 0], [2000, 150], [3000, 150], [4000, 0]]);
        // 3,1 km de voie pour 1,2 km entre les PK des deux bouts.
        $this->station(900, 20, 10000);
        $this->station(4100, 20, 11200);

        $this->assertSame(0, $this->repair()['long_gap_filled']);
    }

    public function test_long_gap_path_with_stations_in_order_is_followed(): void
    {
        $this->longGap();
        $this->abandoned([[1000, 0], [2000, 150], [3000, 150], [4000, 0]]);
        $this->station(900, 20, 10000);
        $this->station(4100, 20, 13300);
        $this->station(2000, 170, 11300);
        $this->station(3000, 170, 12300);

        $this->assertSame(1, $this->repair()['long_gap_filled']);
    }

    public function test_long_gap_path_along_a_track_in_service_is_refused(): void
    {
        // Revue du 21/11/2026 : l'ancien tracé abandonné passe à 200 m au plus d'une voie BD TOPO en service, non
        // rattachée, qui traverse le trou tout droit. La ligne passait sans doute sur cette voie : pas de comblement.
        $this->longGap();
        $this->abandoned([[1000, 0], [2000, 150], [3000, 150], [4000, 0]]);
        $this->trace(null, 'bdtopo', [[0, -50], [5000, -50]]);

        $stats = $this->repair();

        $this->assertSame(0, $stats['long_gap_filled']);
        $this->assertGreaterThanOrEqual(1, $stats['long_gap_refused']);
        $this->assertCount(4, $this->freeEnds());
    }

    public function test_long_gap_path_far_from_any_track_in_service_is_followed(): void
    {
        // Même trou, voie en service à 450 m au moins du chemin : elle ne dit rien du tracé de la ligne.
        $this->longGap();
        $this->abandoned([[1000, 0], [2000, 150], [3000, 150], [4000, 0]]);
        $this->trace(null, 'bdtopo', [[0, -400], [5000, -400]]);

        $this->assertSame(1, $this->repair()['long_gap_filled']);
        $this->assertSame([[0.0, 0.0], [5000.0, 0.0]], $this->freeEnds());
    }

    // ─── Embranchement arrêté avant la jonction ─────────────────────────

    /** Ligne B : voie BD TOPO est-ouest (y = 0) ; la ligne arrive du nord et s'arrête à 100 m. */
    private function branchNetwork(?int $gaugeB = 1435, bool $track = true): Line
    {
        $b = Line::factory()->create(['code' => '430000', 'gauge_mm_default' => $gaugeB]);
        $this->trace($b, 'bdtopo', [[0, 0], [1060, 0], [3000, 0]]);
        $own = $this->trace($this->line, 'bdtopo', [[1000, 1000], [1000, 100]]);
        $this->part('bdtopo', [[[1000, 1000], [1000, 100]]], [$own]);
        if ($track) {
            // Voie de raccordement réelle (BD TOPO non rattachée) qui rejoint B par un aiguillage (22°).
            $this->trace(null, 'bdtopo', [[1000, 100], [1001, 75], [1008, 50], [1022, 25], [1040, 8], [1060, 0]]);
        }

        return $b;
    }

    public function test_branch_stopping_before_the_junction_is_extended_along_the_real_track(): void
    {
        $this->branchNetwork();

        $stats = $this->repair();

        $this->assertSame(1, $stats['extended']);
        $this->assertEqualsWithDelta(126, $stats['extended_m'], 2);
        $this->assertSame([[1000.0, 1000.0], [1060.0, 0.0]], $this->freeEnds());
        $added = DB::table('line_reference_parts')->where('line_id', $this->line->id)->orderByDesc('id')->first();
        $this->assertSame('bdtopo', $added->source);
        $this->assertNull($added->matched_source, 'voie non rattachée : la voie de B n\'est pas parcourue');
    }

    public function test_branch_is_not_extended_onto_a_line_of_another_gauge(): void
    {
        $this->branchNetwork(gaugeB: 1000);

        $this->assertSame(0, $this->repair()['extended']);
        $this->assertSame([[1000.0, 100.0], [1000.0, 1000.0]], $this->freeEnds());
    }

    public function test_branch_is_not_extended_without_a_real_track(): void
    {
        $this->branchNetwork(track: false);

        $this->assertSame(0, $this->repair()['extended']);
        $this->assertSame([[1000.0, 100.0], [1000.0, 1000.0]], $this->freeEnds());
    }

    public function test_branch_not_pointing_at_the_other_line_is_not_extended(): void
    {
        // Voie de B parallèle à la ligne (à l'est) : le bout libre pointe vers le sud, pas vers elle.
        $b = Line::factory()->create(['code' => '430000', 'gauge_mm_default' => 1435]);
        $this->trace($b, 'bdtopo', [[1080, 1000], [1080, 0], [1080, -1000]]);
        $own = $this->trace($this->line, 'bdtopo', [[1000, 1000], [1000, 100]]);
        $this->part('bdtopo', [[[1000, 1000], [1000, 100]]], [$own]);
        $this->trace(null, 'bdtopo', [[1000, 100], [1002, 60], [1020, 20], [1080, 0]]);

        $this->assertSame(0, $this->repair()['extended']);
    }

    // ─── Bout posé sur la voie d'une autre ligne dessinée à côté (règle 5 bis) ─

    /**
     * Pont-Cardinet (état de vff du 21/11/2026, relatif au point 2.315820, 48.886665) : 170 (971000) finit, par un morceau
     * OSM de 18 m, exactement sur la voie OSM de 455 (973000) ; 455 est dessinée là sur la BD TOPO, parallèle, à 3,8 m.
     * $onTrack false : même dessin, mais aucune voie de 455 sous le bout (terminus le long d'une autre ligne).
     */
    private function pontCardinet(bool $onTrack = true, int $gaugeL = 1435): Line
    {
        DB::table('lines')->where('id', $this->line->id)->update(['network' => 'principal']);
        $l = Line::factory()->create(['code' => '973000', 'gauge_mm_default' => $gaugeL, 'network' => 'principal']);
        if ($onTrack) {
            $this->trace($l, 'osm', [[32.8, -40.5], [11.3, -13.0], [0.0, 0.0], [-12.1, 12.6], [-26.0, 26.1], [-37.8, 36.9], [-50.0, 47.1],
                [-64.6, 58.5], [-81.8, 71.3], [-100.1, 83.4]]);
        }
        $bd = [[-3.9, 8.2], [9.6, -3.1], [25.8, -25.3], [54.7, -65.9], [110.2, -148.5], [129.1, -177.2]];
        $this->trace($l, 'bdtopo', [[-35.2, 35.4], [-32.6, 33.3], [-10.2, 13.5], ...array_slice($bd, 1)]);
        $this->part('bdtopo', [$bd], [], null, $l);
        $this->part('osm', [[[-38.5, 50.0], [-31.7, 42.7], [-20.2, 30.6], [-7.9, 16.3], [-2.6, 9.7], [-3.9, 8.2]]], [], null, $l);
        $this->part('osm', [[[-135.7, 95.6], [-119.3, 87.8], [-102.0, 78.7], [-91.9, 72.5], [-78.5, 63.6], [-67.7, 56.3], [-40.4, 34.3], [-14.1, 10.9]]]);
        $this->part('osm', [[[-14.1, 10.9], [0.0, 0.0]]]);

        return $l;
    }

    /** Distance (m) du point (relatif) au dessin de la ligne $l. */
    private function distToLineDrawing(Line $l, float $x, float $y): float
    {
        return (float) DB::selectOne(<<<'SQL'
            SELECT ST_Distance(ST_SetSRID(ST_MakePoint(?, ?), 2154), ST_Collect(ST_Transform(geom, 2154))) AS d FROM line_reference_parts WHERE line_id = ?
            SQL, [self::X0 + $x, self::Y0 + $y, $l->id])->d;
    }

    public function test_end_on_another_line_track_drawn_alongside_joins_its_drawing(): void
    {
        $l = $this->pontCardinet();

        $stats = $this->repair();

        $this->assertSame(1, $stats['drawing_joined']);
        $ends = $this->freeEnds();
        $this->assertCount(2, $ends);
        $this->assertContains([-135.7, 95.6], $ends);
        // Nouveau bout : sur le dessin de 455 (0 m), devant l'ancien, dans le sens de la ligne (vers le sud-est).
        $end = array_values(array_filter($ends, fn ($p) => $p !== [-135.7, 95.6]))[0];
        $this->assertGreaterThan(10, $end[0]);
        $this->assertLessThan(0.05, $this->distToLineDrawing($l, $end[0], $end[1]));
        // Arrivée tangente, courbe estimée, à moins de 5 m du dessin de 455 (tolérance du projet ; écart de 3,8 m au bout).
        $this->assertLessThan(10, $this->maxKink());
        $curve = DB::table('line_reference_parts')->where('line_id', $this->line->id)->where('matched_source', JunctionRepair::SMOOTHED)->get();
        $this->assertCount(1, $curve);
        $this->assertLessThanOrEqual(5.0, (float) DB::selectOne(<<<'SQL'
            SELECT MAX(ST_Distance(v.geom, (SELECT ST_Collect(ST_Transform(geom, 2154)) FROM line_reference_parts WHERE line_id = ?))) AS d
            FROM line_reference_parts c, LATERAL ST_DumpPoints(ST_Transform(c.geom, 2154)) v WHERE c.id = ?
            SQL, [$l->id, $curve[0]->id])->d);
        $this->assertSame(0, $this->repair()['drawing_joined'], 'idempotent');
    }

    public function test_end_beside_another_line_without_its_track_under_it_is_left(): void
    {
        // Terminus le long d'une autre ligne : rien dans la source ne dit que les deux voies se rejoignent.
        $this->pontCardinet(onTrack: false);

        $this->assertSame(0, $this->repair()['drawing_joined']);
        $this->assertContains([0.0, 0.0], $this->freeEnds());
    }

    public function test_end_on_the_track_of_a_line_of_another_gauge_is_left(): void
    {
        $this->pontCardinet(gaugeL: 1000);

        $this->assertSame(0, $this->repair()['drawing_joined']);
        $this->assertContains([0.0, 0.0], $this->freeEnds());
    }

    public function test_end_on_another_line_track_never_recoils_under_a_third_line_junction(): void
    {
        // Une troisième ligne finit sur les derniers mètres de la ligne : la courbe part du bout lui-même (pas de recul).
        $l = $this->pontCardinet();
        $m = Line::factory()->create(['code' => '975000', 'gauge_mm_default' => 1435]);
        $this->part('bdtopo', [[[-5.6, 4.3], [-60.0, -40.0]]], [], null, $m);

        $stats = $this->repair();

        $this->assertSame(1, $stats['drawing_joined']);
        $this->assertLessThan(0.05, $this->distToDrawing(-5.6, 4.3));
        $this->assertNotContains([0.0, 0.0], $this->freeEnds());
        $end = array_values(array_filter($this->freeEnds(), fn ($p) => $p !== [-135.7, 95.6]))[0];
        $this->assertLessThan(0.05, $this->distToLineDrawing($l, $end[0], $end[1]));
    }

    // ─── Dépassements ───────────────────────────────────────────────────

    private function crossing(string $source, float $over, float $endX = 1000): void
    {
        $b = Line::factory()->create(['code' => '430000']);
        $this->trace($b, 'bdtopo', [[0, 0], [6000, 0]]);
        $this->part($source, [[[1000, 500], [$endX, -$over]]]);
    }

    public function test_coarse_trace_overshooting_a_crossing_is_cut_at_the_crossing(): void
    {
        $this->crossing('archeoferroviaire', 30);

        $stats = $this->repair();

        $this->assertEqualsWithDelta(30, $stats['overshoot_trimmed_m'], 0.5);
        $this->assertSame([[1000.0, 0.0], [1000.0, 500.0]], $this->freeEnds());
    }

    public function test_precise_track_beyond_a_crossing_is_kept(): void
    {
        $this->crossing('bdtopo', 30);

        $this->assertEqualsWithDelta(0, $this->repair()['overshoot_trimmed_m'], 0.01);
        $this->assertEqualsWithDelta(530, $this->totalLength(), 0.5);
    }

    public function test_long_continuation_beyond_a_crossing_is_kept(): void
    {
        $this->crossing('archeoferroviaire', 100);

        $this->assertEqualsWithDelta(0, $this->repair()['overshoot_trimmed_m'], 0.01);
    }

    public function test_overshoot_is_kept_when_too_little_would_remain(): void
    {
        $b = Line::factory()->create(['code' => '430000']);
        $this->trace($b, 'bdtopo', [[0, 0], [6000, 0]]);
        $this->part('archeoferroviaire', [[[1000, 40], [1000, -30]]]);

        $this->assertEqualsWithDelta(0, $this->repair()['overshoot_trimmed_m'], 0.01);
    }

    public function test_overshoot_tail_holding_another_portion_is_kept(): void
    {
        $this->crossing('archeoferroviaire', 30);
        // Une autre portion de la ligne part du bout qui dépasse.
        $this->part('archeoferroviaire', [[[1000, -20], [1500, -200]]]);

        $this->assertEqualsWithDelta(0, $this->repair()['overshoot_trimmed_m'], 0.01);
    }

    public function test_shallow_crossing_is_not_a_junction_overshoot(): void
    {
        // Croisement à ~ 8° : le tracé longe la voie, ce n'est pas un dépassement de jonction.
        $this->crossing('archeoferroviaire', 4, 4560);

        $this->assertEqualsWithDelta(0, $this->repair()['overshoot_trimmed_m'], 0.01);
    }

    // ─── Commande ───────────────────────────────────────────────────────

    public function test_build_line_references_fills_the_bridge_hole(): void
    {
        $this->trace($this->line, 'bdtopo', [[0, 0], [1000, 0]]);
        $this->trace($this->line, 'bdtopo', [[1040, 0], [2000, 0]]);
        $this->trace(null, 'bdtopo', [[1000, 0], [1040, 0]]);

        $this->artisan('vff:build-line-references', ['--line' => [(string) $this->line->id]])
            ->expectsOutputToContain('1 trous comblés tout droit')
            ->assertSuccessful();

        $this->assertSame([[0.0, 0.0], [2000.0, 0.0]], $this->freeEnds());
        $this->assertEqualsWithDelta(2000, (float) DB::table('line_reference_geometries')->where('line_id', $this->line->id)->value('length_m'), 1);
    }

    public function test_build_line_references_without_the_step_keeps_the_hole(): void
    {
        $this->trace($this->line, 'bdtopo', [[0, 0], [1000, 0]]);
        $this->trace($this->line, 'bdtopo', [[1040, 0], [2000, 0]]);
        $this->trace(null, 'bdtopo', [[1000, 0], [1040, 0]]);

        $this->artisan('vff:build-line-references', ['--line' => [(string) $this->line->id], '--no-junction-repair' => true])->assertSuccessful();

        $this->assertCount(4, $this->freeEnds());
    }

    // ─── Arrondi des sommets ────────────────────────────────────────────

    /** Courbe OSM échantillonnée tous les 50 m, qui tourne de 8° à chaque sommet (voie de montagne). */
    private function polygonalCurve(): array
    {
        $c = [[0.0, 0.0]];
        $az = 0.0;
        for ($i = 0; $i < 10; $i++) {
            $c[] = [round(end($c)[0] + 50 * cos($az), 3), round(end($c)[1] + 50 * sin($az), 3)];
            $az += deg2rad(8);
        }

        return $c;
    }

    /** Sommets du tracé de référence d'une ligne. */
    private function vertexCount(Line $line): int
    {
        return (int) DB::selectOne('SELECT SUM(ST_NPoints(geom)) AS n FROM line_reference_parts WHERE line_id = ?', [$line->id])->n;
    }

    public function test_build_line_references_rounds_curve_vertices_but_not_where_another_line_joins(): void
    {
        $curve = $this->polygonalCurve();
        $this->trace($this->line, 'osm', $curve);
        // Une autre ligne se raccorde sur le 4e sommet : il ne bouge pas (jonction à 0 m).
        $other = Line::factory()->create(['code' => '430000', 'gauge_mm_default' => 1435]);
        $joint = $curve[4];
        $this->part('osm', [[[$joint[0], $joint[1] - 500], $joint]], [], null, $other);

        $this->artisan('vff:build-line-references', ['--line' => [(string) $this->line->id]])
            ->expectsOutputToContain('arrondi des sommets : 8 sommets arrondis')
            ->assertSuccessful();

        $this->assertGreaterThan(count($curve), $this->vertexCount($this->line));
        $this->assertSame([[0.0, 0.0], [round(end($curve)[0], 1), round(end($curve)[1], 1)]], $this->freeEnds());
        $this->assertEqualsWithDelta(0.0, (float) DB::selectOne(
            'SELECT MIN(ST_Distance(ST_Transform(d.geom, 2154), ST_SetSRID(ST_MakePoint(?, ?), 2154))) AS d FROM line_reference_parts p, LATERAL ST_DumpPoints(p.geom) d WHERE p.line_id = ?',
            [self::X0 + $joint[0], self::Y0 + $joint[1], $this->line->id])->d, 0.01);
        // Plus aucune cassure visible : 3° au plus entre deux segments (cordes de ± 10 m : quelques pas d'arc).
        $this->assertLessThan(10, $this->maxKink());
    }

    public function test_build_line_references_without_rounding_keeps_the_source_vertices(): void
    {
        $curve = $this->polygonalCurve();
        $this->trace($this->line, 'osm', $curve);

        $this->artisan('vff:build-line-references', ['--line' => [(string) $this->line->id], '--no-rounding' => true])->assertSuccessful();

        $this->assertSame(count($curve), $this->vertexCount($this->line));
    }

    // ─── Revue du 21/11/2026 : jonctions des autres lignes, longs trous ──

    /** Distance (m) du point ($x, $y) au dessin de la ligne étudiée. */
    private function distToDrawing(float $x, float $y): float
    {
        return (float) DB::selectOne(
            'SELECT MIN(ST_Distance(ST_Transform(geom, 2154), ST_SetSRID(ST_MakePoint(?, ?), 2154))) AS d FROM line_reference_parts WHERE line_id = ?',
            [self::X0 + $x, self::Y0 + $y, $this->line->id],
        )->d;
    }

    public function test_s_curve_recoil_never_cuts_under_another_line_junction_on_a_dead_end_stub(): void
    {
        // La voie 1 dépasse le pied du saut de 15 m (bout sans issue) ; une autre ligne finit dessus 5 m avant le pied,
        // dans la partie que la courbe en S retirerait : la jonction reste à 0 m, le saut reste un raccord droit.
        $this->part('bdtopo', [[[0, 0], [1015, 0]]]);
        $this->part('osm', [[[1000, 12], [2000, 12]]]);
        $this->connector([[1000, 0], [1000, 12]]);
        $other = Line::factory()->create(['code' => '421000', 'gauge_mm_default' => 1435]);
        $this->part('osm', [[[995, 0], [995, -600]]], [], null, $other);

        $stats = $this->repair();

        $this->assertSame(0, $stats['jog_smoothed']);
        $this->assertLessThanOrEqual(0.5, $this->distToDrawing(995, 0));
    }

    public function test_s_curve_recoil_never_cuts_under_another_line_junction_at_the_jog_foot(): void
    {
        $this->part('bdtopo', [[[0, 0], [1000, 0]]]);
        $this->part('osm', [[[1000, 12], [2000, 12]]]);
        $this->connector([[1000, 0], [1000, 12]]);
        $other = Line::factory()->create(['code' => '421000', 'gauge_mm_default' => 1435]);
        $this->part('osm', [[[1000, 0], [1000, -600]]], [], null, $other);

        $this->repair();

        $this->assertLessThanOrEqual(0.5, $this->distToDrawing(1000, 0));
    }

    public function test_long_gap_path_arriving_sideways_on_the_far_end_is_refused(): void
    {
        // Bout d'en face (3 000, 1 000) : début d'une portion qui monte vers le nord. La voie abandonnée y arrive par
        // l'ouest : raccordée, elle ferait un coude à angle droit que rien ne lisse ensuite.
        $this->part('archeoferroviaire', [[[0, 0], [1000, 0]], [[3000, 1000], [3000, 2000]]], [], 'osm');
        $this->abandoned([[1000, 0], [2000, 0], [2500, 500], [2800, 1000], [3000, 1000]]);

        $stats = $this->repair();

        $this->assertSame(0, $stats['long_gap_filled']);
        $this->assertGreaterThanOrEqual(1, $stats['long_gap_refused']);
    }

    public function test_long_gap_never_joins_two_ends_of_the_same_connected_drawing(): void
    {
        // Y déjà relié (tronc et deux branches) : une voie abandonnée entre les bouts des branches n'est pas un trou.
        $this->part('osm', [[[0, 0], [1000, 0]], [[1000, 0], [3000, 1000]], [[1000, 0], [3000, -1000]]]);
        $this->abandoned([[3000, 1000], [3400, 1200], [3700, 0], [3400, -1200], [3000, -1000]]);

        $stats = $this->repair();

        $this->assertSame(0, $stats['long_gap_filled']);
    }

    public function test_long_gap_closing_chord_never_strays_more_than_five_metres_from_the_track(): void
    {
        // La voie abandonnée s'arrête 25 m à côté du bout d'en face : la raccorder demanderait une corde de 200 m,
        // étiquetée « osm », à 25 m de toute voie réelle. Le trou reste ouvert (ou visible) plutôt qu'inventé.
        $this->longGap();
        $this->abandoned([[1000, 0], [2500, 10], [4000, 25]]);

        $stats = $this->repair();

        if ($stats['long_gap_filled'] > 0) {
            $dev = (float) DB::selectOne(<<<'SQL'
                SELECT MAX((SELECT MIN(ST_Distance(ST_Transform(st.geom, 2154), v.geom)) FROM source_traces st WHERE st.line_id IS NULL)) AS dev
                FROM line_reference_parts p, LATERAL ST_DumpPoints(ST_Transform(p.geom, 2154)) v
                WHERE p.id = (SELECT MAX(id) FROM line_reference_parts WHERE line_id = ?)
                SQL, [$this->line->id])->dev;
            $this->assertLessThanOrEqual(5.0, $dev);
        }
        $this->assertSame(0, $stats['long_gap_filled'] - $stats['long_gap_partial'], 'aucun raccord complet au bout d\'en face');
    }

    // ─── 7 e. Arrivée en T d'un tracé imprécis ──────────────────────────

    /**
     * Ligne 123000 près de Bollwiller (7.509793, 47.815288), forme relevée le 22/11/2026 : le tracé archeo d'une branche
     * arrive en biais (cap nord-ouest) et finit à 30 m de la voie BD TOPO, raccordé au milieu de celle-ci par un raccord
     * droit à 90°. Tronc vers le nord (x = 0), tracé archeo depuis le sud-est.
     */
    private function teeArrival(): void
    {
        $this->line->update(['network' => 'principal']);
        $this->part('bdtopo', [[[0, -1000], [0, 1000]]]);
        $this->part('archeoferroviaire', [[[400, -250], [30, 0]]]);
        $this->connector([[30, 0], [0, 0]], 'archeoferroviaire');
    }

    /** Distance (m) du point au dessin de la ligne, hors courbes lissées (les voies d'origine). */
    private function distToKeptTrack(float $x, float $y): float
    {
        return (float) DB::selectOne(<<<'SQL'
            SELECT ST_Distance(ST_SetSRID(ST_MakePoint(?, ?), 2154), ST_Collect(ST_Transform(geom, 2154))) AS d
            FROM line_reference_parts WHERE line_id = ? AND matched_source IS DISTINCT FROM ?
            SQL, [self::X0 + $x, self::Y0 + $y, $this->line->id, JunctionRepair::SMOOTHED])->d;
    }

    public function test_rough_branch_arriving_square_on_the_middle_of_a_precise_track_is_eased_into_it(): void
    {
        $this->teeArrival();

        $stats = $this->repair();

        // Le raccord à 90° et la fin du tracé archeo sont remplacés par une courbe tangente, prise dans le sens de marche.
        $this->assertSame(1, $stats['tee_smoothed']);
        $this->assertEqualsWithDelta(0, $this->totalLength('connector'), 0.01);
        $this->assertLessThan(JunctionRepair::KINK_DEG, $this->maxKink());
        $curve = DB::selectOne(<<<'SQL'
            SELECT ST_X(ST_EndPoint(g)) AS x, ST_Y(ST_EndPoint(g)) AS y, ST_X(ST_StartPoint(g)) AS x0, ST_Y(ST_StartPoint(g)) AS y0
            FROM (SELECT (ST_Dump(ST_Transform(geom, 2154))).geom AS g FROM line_reference_parts WHERE line_id = ? AND matched_source = ?) c
            SQL, [$this->line->id, JunctionRepair::SMOOTHED]);
        $this->assertNotNull($curve);
        // Jonction sur la voie précise à 0 m, au nord du pied du raccord (cap d'arrivée nord-ouest), voie entière gardée.
        $this->assertLessThan(0.01, abs($curve->x - self::X0));
        $this->assertGreaterThan(0, $curve->y - self::Y0);
        $this->assertEqualsWithDelta(2000, (float) DB::selectOne(
            "SELECT SUM(ST_Length(ST_Transform(geom, 2154))) AS m FROM line_reference_parts WHERE line_id = ? AND source = 'bdtopo'", [$this->line->id])->m, 0.01);
        // Départ de la courbe sur le tracé archeo d'origine, qui garde son autre bout.
        $this->assertLessThan(0.01, $this->distToKeptTrack($curve->x0 - self::X0, $curve->y0 - self::Y0));
        $this->assertSame([[0.0, -1000.0], [0.0, 1000.0], [400.0, -250.0]], $this->freeEnds());

        $this->assertSame(0, $this->repair()['tee_smoothed'], 'second passage sans effet');
    }

    public function test_tee_arrival_where_another_line_ends_on_the_connector_is_kept(): void
    {
        $this->teeArrival();
        $other = Line::factory()->create(['code' => '421000']);
        $this->part('bdtopo', [[[15, 300], [15, 0]]], [], null, $other);

        $stats = $this->repair();

        $this->assertSame(0, $stats['tee_smoothed']);
        $this->assertEqualsWithDelta(30, $this->totalLength('connector'), 0.01);
    }

    public function test_tee_arrival_keeps_the_junction_of_another_portion_of_the_line_on_the_rough_trace(): void
    {
        // Une autre portion de la ligne se raccorde au tracé archeo à 20 m de son bout : la courbe ne peut pas partir
        // au-delà, et cette jonction reste sur le dessin.
        $this->teeArrival();
        $u = [-370 / hypot(370, 250), 250 / hypot(370, 250)];
        $p = [30 - 20 * $u[0], -20 * $u[1]];
        $this->part('osm', [[[$p[0], $p[1]], [$p[0] + 200, $p[1] + 300]]]);

        $this->repair();

        $this->assertLessThan(0.5, (float) DB::selectOne(<<<'SQL'
            SELECT ST_Distance(ST_SetSRID(ST_MakePoint(?, ?), 2154), ST_Collect(ST_Transform(geom, 2154))) AS d
            FROM line_reference_parts WHERE line_id = ? AND source <> 'osm'
            SQL, [self::X0 + $p[0], self::Y0 + $p[1], $this->line->id])->d);
    }

    public function test_tee_arrival_is_kept_when_the_curve_would_cross_another_track(): void
    {
        // Une voie d'une autre ligne passe dans l'angle entre le raccord et le tronc, sans toucher le tracé remplacé : la
        // courbe la couperait (ligne 439000 près de Laval, 22/11/2026 : courbe passée par-dessus la voie qu'elle rejoignait).
        $this->teeArrival();
        $other = Line::factory()->create(['code' => '421000']);
        $this->part('bdtopo', [[[1, 30], [200, 30]]], [], null, $other);

        $this->assertSame(0, $this->repair()['tee_smoothed']);
        $this->assertEqualsWithDelta(30, $this->totalLength('connector'), 0.01);
    }

    public function test_tee_arrival_keeps_the_contact_of_the_rough_trace_with_another_line(): void
    {
        // Le tracé archeo passe sur le dessin d'une autre ligne 40 m avant son bout (jonction à 0 m) : la courbe qui
        // partirait plus loin en arrière laisserait cette ligne à l'écart. L'arrivée reste telle quelle.
        $this->teeArrival();
        $u = [-370 / hypot(370, 250), 250 / hypot(370, 250)];
        $p = [30 - 40 * $u[0], -40 * $u[1]];
        $other = Line::factory()->create(['code' => '421000']);
        $this->part('bdtopo', [[[$p[0] + 300 * $u[1], $p[1] - 300 * $u[0]], [$p[0] - 300 * $u[1], $p[1] + 300 * $u[0]]]], [], null, $other);

        $this->repair();

        $this->assertLessThan(0.5, (float) DB::selectOne(<<<'SQL'
            SELECT ST_Distance(ST_SetSRID(ST_MakePoint(?, ?), 2154), ST_Collect(ST_Transform(geom, 2154))) AS d FROM line_reference_parts WHERE line_id = ?
            SQL, [self::X0 + $p[0], self::Y0 + $p[1], $this->line->id])->d);
    }

    public function test_tee_arrival_is_kept_when_the_precise_track_folds_back_near_the_foot(): void
    {
        $this->line->update(['network' => 'principal']);
        // Voie précise avec une pointe (aller-retour de 8 m) à 20 m au nord du pied du raccord : la courbe passerait de l'autre côté.
        $this->part('bdtopo', [[[0, -1000], [0, 20], [0, 12], [0, 1000]]]);
        $this->part('archeoferroviaire', [[[400, -250], [30, 0]]]);
        $this->connector([[30, 0], [0, 0]], 'archeoferroviaire');

        $stats = $this->repair();

        $this->assertSame(0, $stats['tee_smoothed']);
        $this->assertEqualsWithDelta(30, $this->totalLength('connector'), 0.01);
    }

    public function test_precise_branch_arriving_square_on_a_precise_track_is_not_a_tee_arrival(): void
    {
        // La règle 7 e ne remplace que la fin d'un tracé imprécis : une branche précise (OSM) reste telle quelle.
        $this->line->update(['network' => 'principal']);
        $this->part('bdtopo', [[[0, -1000], [0, 1000]]]);
        $this->part('osm', [[[400, -250], [30, 0]]]);
        $this->connector([[30, 0], [0, 0]], 'osm');

        $this->assertSame(0, $this->repair()['tee_smoothed']);
    }
}

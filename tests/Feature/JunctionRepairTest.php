<?php

namespace Tests\Feature;

use App\Models\Line;
use App\Models\Station;
use App\Services\JunctionRepair;
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
}

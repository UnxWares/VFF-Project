<?php

namespace Tests\Feature;

use App\Models\Line;
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

    public function test_jog_without_real_crossover_is_kept(): void
    {
        $this->jogNetwork(crossover: false);
        $before = $this->totalLength();

        $stats = $this->repair();

        $this->assertSame(0, $stats['jog_removed']);
        $this->assertSame(1, $stats['jog_restored']);
        $this->assertEqualsWithDelta(15, $this->totalLength('connector'), 0.01);
        $this->assertEqualsWithDelta($before, $this->totalLength(), 0.01);
        $this->assertSame([[0.0, 0.0], [2000.0, 15.0]], $this->freeEnds());
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

        $this->assertSame(1, $stats['jog_restored']);
        $this->assertEqualsWithDelta(0, $stats['remnant_removed_m'], 0.01);
        $this->assertEqualsWithDelta(3020, $this->totalLength(), 0.5);
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
}

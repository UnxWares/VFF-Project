<?php

namespace Tests\Unit;

use App\Services\SectionResolver;
use App\Services\SingleAxis;
use App\Services\TraceConflation;
use PHPUnit\Framework\TestCase;

/**
 * Briques géométriques pures des services de tracés, appelées par
 * réflexion (méthodes privées, sans base) :
 *   - SingleAxis : intervalles gardés / retirés, séries d'échantillons,
 *     croisements, tracés réellement distincts ;
 *   - SectionResolver : calage des PK (sous-suite croissante, points
 *     aberrants, conversions mesure ⇄ PK, découpe de l'axe) ;
 *   - TraceConflation : abscisses, flèche admissible, corridors, ajustement
 *     de corde, Viterbi et regroupement en portions.
 */
class GeometryHelpersTest extends TestCase
{
    private function call(object $object, string $method, mixed ...$args): mixed
    {
        return (new \ReflectionMethod($object, $method))->invoke($object, ...$args);
    }

    private function bare(string $class): object
    {
        return (new \ReflectionClass($class))->newInstanceWithoutConstructor();
    }

    // ─── SingleAxis ──────────────────────────────────────────────────────

    public function test_overlapping_and_touching_intervals_are_merged(): void
    {
        $axis = $this->bare(SingleAxis::class);

        $this->assertSame([[0, 30], [50, 80]], $this->call($axis, 'mergeIntervals', [[50, 60], [0, 10], [5, 30], [55, 80]]));
        // Bords qui se touchent à 1 cm près : un seul intervalle.
        $this->assertSame([[0, 20.0]], $this->call($axis, 'mergeIntervals', [[0, 10.0], [10.005, 20.0]]));
        $this->assertSame([], $this->call($axis, 'mergeIntervals', []));
    }

    public function test_kept_intervals_are_the_complement_of_the_removed_ones(): void
    {
        $axis = $this->bare(SingleAxis::class);

        $this->assertSame([[0.0, 100], [200, 1000.0]], $this->call($axis, 'complement', [[100, 200]], 1000.0));
        // Retrait au début et à la fin : seul le milieu reste.
        $this->assertSame([[50, 900]], $this->call($axis, 'complement', [[0, 50], [900, 1000]], 1000.0));
        // Restes de moins d'1 m : ignorés.
        $this->assertSame([], $this->call($axis, 'complement', [[0.5, 999.5]], 1000.0));
        $this->assertSame([[0.0, 1000.0]], $this->call($axis, 'complement', [], 1000.0));
    }

    public function test_position_in_intervals_includes_bounds(): void
    {
        $axis = $this->bare(SingleAxis::class);

        $this->assertTrue($this->call($axis, 'inIntervals', [[10, 20], [40, 50]], 10.0));
        $this->assertTrue($this->call($axis, 'inIntervals', [[10, 20], [40, 50]], 50.0));
        $this->assertFalse($this->call($axis, 'inIntervals', [[10, 20], [40, 50]], 30.0));
        $this->assertFalse($this->call($axis, 'inIntervals', [], 0.0));
    }

    public function test_runs_bridge_gaps_of_up_to_three_samples(): void
    {
        $axis = $this->bare(SingleAxis::class);
        $s = [5.0, 1, 9];
        $runs = fn (array $shadow, ?array $distinct = null) => $this->call($axis, 'runs', $shadow, $distinct ?? array_fill(0, count($shadow), null));

        $this->assertSame([[0, 4]], $runs([$s, null, null, null, $s]));
        $this->assertSame([[0, 0], [5, 5]], $runs([$s, null, null, null, null, $s]));
        $this->assertSame([[1, 2]], $runs([null, $s, $s, null]));
        $this->assertSame([], $runs([null, null]));
        // Un échantillon de tracé distinct dans le trou empêche la jonction.
        $this->assertSame([[0, 0], [3, 3]], $runs([$s, null, null, $s], [null, true, null, null]));
    }

    public function test_crossing_requires_the_same_neighbour_on_both_sides(): void
    {
        $axis = $this->bare(SingleAxis::class);

        // Voisine 9 à gauche (+1) puis à droite (-1), écarts ≥ 10 m : croisement en biais.
        $this->assertTrue($this->call($axis, 'crossing', [[15.0, 1, 9], [2.0, -1, 9], [15.0, -1, 9]], 0, 2));
        // Une voisine de chaque côté (axe central entre deux axes gardés) : doublon, pas un croisement.
        $this->assertFalse($this->call($axis, 'crossing', [[15.0, 1, 9], [15.0, -1, 8]], 0, 1));
        // Changement de côté sous 10 m d'écart : ignoré.
        $this->assertFalse($this->call($axis, 'crossing', [[15.0, 1, 9], [5.0, -1, 9]], 0, 1));
        $this->assertFalse($this->call($axis, 'crossing', [null, [15.0, 1, 9]], 0, 1));
    }

    public function test_distinct_alignment_rules(): void
    {
        $axis = $this->bare(SingleAxis::class);
        $track = fn (bool $hist, string $sig = '', string $life = 'active') => ['hist' => $hist, 'sig' => $sig, 'life' => $life];

        // Moins de 3 m : toujours la même voie.
        $this->assertFalse($this->call($axis, 'distinct', $track(true, '1866'), $track(false), 2.9));
        // Ancien tracé DATÉ le long de la voie actuelle : distinct.
        $this->assertTrue($this->call($axis, 'distinct', $track(true, '1866'), $track(false), 3.0));
        // Ancien tracé non daté : distinct seulement si son état diffère, à 8 m au moins.
        $this->assertFalse($this->call($axis, 'distinct', $track(true), $track(false), 20.0));
        $this->assertFalse($this->call($axis, 'distinct', $track(true, '', 'gone'), $track(false), 7.9));
        $this->assertTrue($this->call($axis, 'distinct', $track(true, '', 'gone'), $track(false), 8.0));
        // Deux anciens tracés datés différemment : distincts.
        $this->assertTrue($this->call($axis, 'distinct', $track(true, '1866'), $track(true, '1901'), 4.0));
        $this->assertFalse($this->call($axis, 'distinct', $track(true, '1866'), $track(true, '1866'), 4.0));
        // Voie déposée le long d'une voie en service (deux tracés précis).
        $this->assertTrue($this->call($axis, 'distinct', $track(false, '', 'gone'), $track(false), 9.0));
    }

    // ─── SectionResolver : calage des PK ────────────────────────────────

    public function test_longest_increasing_subsequence_of_control_points(): void
    {
        $resolver = $this->bare(SectionResolver::class);
        // [position (m), PK (m), clé] triés par position ; la gare homonyme (PK 90 000) est écartée.
        $pts = [[0, 1000, 'a'], [5000, 6000, 'b'], [6000, 90_000, 'x'], [9000, 10_000, 'c'], [12_000, 13_000, 'd']];

        $this->assertSame(['a', 'b', 'c', 'd'], array_column($this->call($resolver, 'longestIncreasing', $pts), 2));
        $this->assertSame([], $this->call($resolver, 'longestIncreasing', []));
        // PK égaux : strictement croissant, un seul gardé.
        $this->assertCount(1, $this->call($resolver, 'longestIncreasing', [[0, 500, 'a'], [100, 500, 'b']]));
    }

    public function test_isolated_slope_outlier_is_dropped(): void
    {
        $resolver = $this->bare(SectionResolver::class);
        // Pente normale ~1 m/m ; le point « c » est décalé de 8 km le long de l'axe.
        $pts = [[0, 0, 'a'], [10_000, 10_000, 'b'], [10_500, 18_000, 'c'], [20_000, 20_000, 'd'], [30_000, 30_000, 'e']];

        $this->assertSame(['a', 'b', 'd', 'e'], array_column($this->call($resolver, 'dropSlopeOutliers', $pts), 2));
        // Trois points ou moins : rien n'est retiré (pas assez de voisins pour juger).
        $three = [[0, 0, 'a'], [100, 9000, 'b'], [10_000, 10_000, 'c']];
        $this->assertSame($three, $this->call($resolver, 'dropSlopeOutliers', $three));
    }

    public function test_measure_and_pk_conversions_are_inverse(): void
    {
        $resolver = $this->bare(SectionResolver::class);
        $calib = ['points' => [[0.0, 1000], [5000.0, 6200], [9000.0, 10_100]]];
        (new \ReflectionProperty($resolver, 'calib'))->setValue($resolver, $calib);

        // Interpolation entre points, prolongement 1 m/m au-delà.
        $this->assertEqualsWithDelta(3600.0, $this->call($resolver, 'measureToPkWith', $calib, 2500.0), 1e-6);
        $this->assertEqualsWithDelta(500.0, $this->call($resolver, 'measureToPkWith', $calib, -500.0), 1e-6);
        $this->assertEqualsWithDelta(10_600.0, $this->call($resolver, 'measureToPkWith', $calib, 9500.0), 1e-6);

        foreach ([-400.0, 0.0, 1234.5, 5000.0, 7777.0, 9000.0, 9800.0] as $m) {
            $pk = $this->call($resolver, 'measureToPkWith', $calib, $m);
            $this->assertEqualsWithDelta($m, $this->call($resolver, 'pkToMeasure', (int) round($pk)), 1.0, "mesure {$m}");
        }
    }

    public function test_axis_slice_skips_gaps_and_follows_reversed_pieces(): void
    {
        $resolver = $this->bare(SectionResolver::class);
        $pieces = [
            ['part' => 1, 'f1' => 0.0, 'f2' => 1.0, 'len' => 1000.0],
            ['gap' => 200.0],
            ['part' => 2, 'f1' => 1.0, 'f2' => 0.0, 'len' => 500.0], // parcourue à l'envers
        ];

        $slice = $this->call($resolver, 'slicePieces', $pieces, 500.0, 1500.0);

        $this->assertCount(2, $slice);
        $this->assertEqualsWithDelta(['part' => 1, 'f1' => 0.5, 'f2' => 1.0, 'len' => 500.0], $slice[0], 1e-9);
        $this->assertEqualsWithDelta(['part' => 2, 'f1' => 1.0, 'f2' => 0.4, 'len' => 300.0], $slice[1], 1e-9);
        $this->assertSame([], $this->call($resolver, 'slicePieces', $pieces, 1000.0, 1200.0), 'tranche entièrement dans le trou');
    }

    public function test_haversine_and_km_label(): void
    {
        $resolver = $this->bare(SectionResolver::class);

        // 1° de latitude ≈ 111,19 km (rayon 6 371 km).
        $this->assertEqualsWithDelta(111_195, $this->call($resolver, 'haversine', [2.0, 48.0], [2.0, 49.0]), 1);
        $this->assertSame(0.0, $this->call($resolver, 'haversine', [2.0, 48.0], [2.0, 48.0]));
        $this->assertSame('54,320', $this->call($resolver, 'km', 54_320));
        $this->assertSame('0,005', $this->call($resolver, 'km', 5));
    }

    // ─── TraceConflation ─────────────────────────────────────────────────

    public function test_cumulative_positions_of_vertices(): void
    {
        $this->assertSame([0.0, 5.0, 11.0], $this->call(new TraceConflation, 'positions', [[0, 0], [3, 4], [3, 10]]));
        $this->assertSame([0.0], $this->call(new TraceConflation, 'positions', [[7, 7]]));
    }

    public function test_admissible_sagitta_of_a_chord(): void
    {
        $tc = new TraceConflation;
        $archeo = TraceConflation::PROFILES['archeoferroviaire'];

        // c² / 8R : corde de 100 m sur un rayon de 250 m → 5 m.
        $this->assertEqualsWithDelta(5.0, $this->call($tc, 'sagitta', 100.0, $archeo), 1e-9);
        // Plafonnée (80 m) pour les très longues cordes.
        $this->assertSame(80.0, $this->call($tc, 'sagitta', 1000.0, $archeo));
        // SNCF : pas de flèche (corridor large fixe).
        $this->assertSame(0.0, $this->call($tc, 'sagitta', 1000.0, TraceConflation::PROFILES['sncf']));
    }

    public function test_corridor_widens_in_the_middle_of_long_chords_only(): void
    {
        $tc = new TraceConflation;
        $archeo = TraceConflation::PROFILES['archeoferroviaire'];

        [$w, $sigma] = $this->call($tc, 'corridors', [0.0, 1000.0], 40, 25.0, $archeo);

        $this->assertGreaterThan($w[0], $w[20]);
        $this->assertGreaterThan($w[39], $w[20]);
        $this->assertLessThanOrEqual($archeo['w0'] + $archeo['sag_cap'], max($w));
        $this->assertGreaterThan($archeo['w0'] + $archeo['sag_cap'] - 1, $w[20]);
        $this->assertEqualsWithDelta($w[20] - $archeo['w0'], $sigma[20] - $archeo['sigma0'], 1e-9);

        // Profil SNCF : corridor constant.
        [$wSncf] = $this->call($tc, 'corridors', [0.0, 1000.0], 40, 25.0, TraceConflation::PROFILES['sncf']);
        $this->assertSame(array_fill(0, 40, 250.0), $wSncf);
    }

    public function test_chord_fit_recovers_vertex_offsets_and_sagitta(): void
    {
        $tc = new TraceConflation;
        $pts = [];
        foreach ([0, 0.2, 0.35, 0.5, 0.8, 1.0] as $t) {
            $pts[] = [$t, 2.0 * (1 - $t) - 3.0 * $t + 5.0 * 4 * $t * (1 - $t)];
        }

        [$a, $b, $h, $res] = $this->call($tc, 'fitChord', $pts);

        $this->assertEqualsWithDelta(2.0, $a, 1e-6);
        $this->assertEqualsWithDelta(-3.0, $b, 1e-6);
        $this->assertEqualsWithDelta(5.0, $h, 1e-6);
        $this->assertEqualsWithDelta(0.0, $res, 1e-6);
        // Un seul point : système dégénéré, pas de modèle.
        $this->assertSame([0.0, 0.0, 0.0, INF], $this->call($tc, 'fitChord', [[0.5, 3.0]]));
    }

    /**
     * @param  list<array<int, float>>  $distances  par observation : arête → écart (m)
     * @param  list<float>|null  $fm  abscisse (0..1) de la projection sur l'arête, par observation
     */
    private function matching(array $distances, ?array $fm = null, float $sigma = 10.0): array
    {
        $n = count($distances);
        $chunks = [];
        foreach ($distances as $i => $byEdge) {
            $cands = [];
            foreach ($byEdge as $eid => $d) {
                $cands[$eid] = ['d' => $d, 'theta' => 0.0, 'fm' => $fm[$i] ?? $i * 0.025, 'px' => 0.0, 'py' => 0.0, 'qx' => 0.0, 'qy' => 0.0];
            }
            $chunks[$i] = ['cands' => $cands];
        }
        $line = ['n' => $n, 'clen' => 25.0, 'sigma' => array_fill(0, $n, $sigma), 'c_none' => 2.0];
        $edges = [7 => ['prio' => 0.0, 'len' => 1000.0], 8 => ['prio' => 0.0, 'len' => 1000.0]];

        return [$chunks, $line, $edges];
    }

    public function test_viterbi_follows_a_close_precise_track(): void
    {
        [$chunks, $line, $edges] = $this->matching([[7 => 2.0], [7 => 3.0], [7 => 1.0], [7 => 2.0]]);

        $this->assertSame([7, 7, 7, 7], $this->call(new TraceConflation, 'viterbi', $chunks, $line, $edges, []));
    }

    public function test_viterbi_keeps_the_original_trace_without_close_candidate(): void
    {
        [$chunks, $line, $edges] = $this->matching([[], [7 => 60.0], [7 => 70.0], []]);

        $this->assertSame([0, 0, 0, 0], $this->call(new TraceConflation, 'viterbi', $chunks, $line, $edges, []));
    }

    public function test_viterbi_stays_on_one_track_of_a_double_track_line(): void
    {
        // Deux voies parallèles à 3-4 m : sans coût de changement, l'appariement zigzaguerait.
        [$chunks, $line, $edges] = $this->matching([
            [7 => 3.0, 8 => 4.0], [7 => 4.0, 8 => 3.0], [7 => 3.0, 8 => 4.0], [7 => 3.5, 8 => 3.0],
        ]);

        $this->assertSame([7, 7, 7, 7], $this->call(new TraceConflation, 'viterbi', $chunks, $line, $edges, []));
    }

    public function test_short_isolated_precise_run_is_dropped_and_none_runs_are_merged(): void
    {
        $tc = new TraceConflation;
        [$chunks, $line, $edges] = $this->matching(array_fill(0, 5, [7 => 2.0]));

        // 25 m de voie précise entre deux portions sans géométrie précise : ignorés (< 75 m).
        $this->assertSame([['s' => 0, 'i0' => 0, 'i1' => 4]], $this->call($tc, 'runs', [0, 0, 7, 0, 0], $chunks, $line, $edges));
        // 100 m : gardés.
        $this->assertSame([['s' => 7, 'i0' => 0, 'i1' => 3], ['s' => 0, 'i0' => 4, 'i1' => 4]],
            $this->call($tc, 'runs', [7, 7, 7, 7, 0], $chunks, $line, $edges));
    }

    public function test_jump_along_the_same_track_opens_a_new_run(): void
    {
        $tc = new TraceConflation;
        // Même voie, mais la projection saute de 375 m (lacet) entre la 3e et la 4e observation.
        [$chunks, $line, $edges] = $this->matching(array_fill(0, 6, [7 => 2.0]), [0.100, 0.125, 0.150, 0.525, 0.550, 0.575]);

        $runs = $this->call($tc, 'runs', [7, 7, 7, 7, 7, 7], $chunks, $line, $edges);

        $this->assertSame([[0, 2], [3, 5]], array_map(fn ($r) => [$r['i0'], $r['i1']], $runs));
    }
}

<?php

namespace Tests\Unit\Profiles;

use App\Services\Profiles\TrackFromTerrain;
use PHPUnit\Framework\TestCase;

/** Cas limites de l'altitude de la voie estimée d'après le terrain (coupes fabriquées, valeurs écrites en dur). */
class TrackFromTerrainBoundaryTest extends TestCase
{
    public function test_constants_keep_their_calibrated_values(): void
    {
        $this->assertSame(15, TrackFromTerrain::HALF_M);
        $this->assertSame(4, TrackFromTerrain::MIN_WIDTH_M);
        $this->assertSame(14, TrackFromTerrain::MAX_WIDTH_M);
        $this->assertSame(0.30, TrackFromTerrain::FLAT_TOLERANCE_M);
        $this->assertSame(0.12, TrackFromTerrain::MAX_GRADE);
        $this->assertSame(6, TrackFromTerrain::ROBUST_ITERATIONS);
        $this->assertSame('rge_track', TrackFromTerrain::SOURCE);
    }

    public function test_a_cross_section_without_altitude_under_the_trace_has_no_platform_even_with_a_flat_band_elsewhere(): void
    {
        $cross = array_fill(0, 31, 120.0);
        $cross[15] = null;

        $this->assertNull(TrackFromTerrain::platform($cross));
    }

    public function test_a_flat_band_that_ends_on_the_last_point_is_found(): void
    {
        // Pente de 50 cm par mètre jusqu'à 25, puis cinq points plats (26 à 30) : seule bande plate, à 999 m.
        $cross = [];
        for ($i = 0; $i <= 30; $i++) {
            $cross[] = $i <= 25 ? 100.0 + 0.5 * $i : 999.0;
        }

        $this->assertSame(999.0, TrackFromTerrain::platform($cross));
    }

    public function test_a_flat_band_that_starts_on_the_first_point_is_found(): void
    {
        $cross = [];
        for ($i = 0; $i <= 30; $i++) {
            $cross[] = $i >= 5 ? 100.0 + 0.5 * $i : 500.0;
        }

        $this->assertSame(500.0, TrackFromTerrain::platform($cross));
    }

    public function test_a_band_is_limited_to_the_maximum_width(): void
    {
        // Tout est plat sur 31 points : la bande retenue fait au plus 15 points (largeur 14), mais l'altitude reste celle du plat.
        $this->assertSame(77.0, TrackFromTerrain::platform(array_fill(0, 31, 77.0)));
        // Plat de 77 sur 0 à 20 puis marche de 5 m : bande 0 à 14 (le tracé est à 15, tout contre).
        $cross = array_merge(array_fill(0, 21, 77.0), array_fill(0, 10, 82.0));
        $this->assertSame(77.0, TrackFromTerrain::platform($cross));
    }

    public function test_a_band_just_over_the_flat_tolerance_is_not_flat(): void
    {
        // Pente douce de 0,1 m par mètre : cinq points couvrent 0,4 m, au-dessus de 0,30 m : aucune bande plate.
        $slope = array_map(fn (int $i): float => 200.0 + 0.1 * $i, range(0, 30));
        $this->assertEqualsWithDelta(201.5, TrackFromTerrain::platform($slope), 1e-9);
        // Pente de 0,07 m par mètre : cinq points couvrent 0,28 m, sous 0,30 m : une bande plate, sa médiane.
        $gentle = array_map(fn (int $i): float => 200.0 + 0.07 * $i, range(0, 30));
        $z = TrackFromTerrain::platform($gentle);
        $this->assertNotNull($z);
        $this->assertGreaterThan(200.0, $z);
        $this->assertLessThan(202.2, $z);
    }

    public function test_the_step_between_smoothed_points_never_exceeds_twelve_per_hundred(): void
    {
        $z = [10.0, 10.0, 10.0, 10.0, 10.0, 90.0, 90.0, 90.0, 90.0, 90.0];
        $smoothed = TrackFromTerrain::smooth($z, 100.0);
        $max = 0.0;
        for ($i = 1; $i < count($smoothed); $i++) {
            $max = max($max, abs($smoothed[$i] - $smoothed[$i - 1]));
        }
        $this->assertLessThanOrEqual(12.0 + 1e-9, $max, '120 ‰ sur 100 m : 12 m');
        $this->assertGreaterThan(11.9, $max, 'la marche est raboutée au plus raide permis, pas lissée en deçà');
        // Au pas de 50 m : 6 m.
        $half = TrackFromTerrain::smooth($z, 50.0);
        $max = 0.0;
        for ($i = 1; $i < count($half); $i++) {
            $max = max($max, abs($half[$i] - $half[$i - 1]));
        }
        $this->assertLessThanOrEqual(6.0 + 1e-9, $max);
    }

    public function test_smoothing_needs_five_known_altitudes(): void
    {
        $four = [1.0, 2.0, 40.0, 3.0];
        $this->assertSame($four, TrackFromTerrain::smooth($four, 100.0), 'quatre altitudes : rendues telles quelles');
        $fourWithHoles = [1.0, null, 2.0, null, 40.0, 3.0];
        $this->assertSame($fourWithHoles, TrackFromTerrain::smooth($fourWithHoles, 100.0), 'quatre connues parmi six : rendues telles quelles');
        $five = [1.0, 2.0, 40.0, 3.0, 4.0];
        $smoothed = TrackFromTerrain::smooth($five, 100.0);
        $this->assertNotSame($five, $smoothed, 'cinq altitudes : lissées');
        $this->assertLessThan(40.0, $smoothed[2]);
        $this->assertCount(5, $smoothed);
    }

    public function test_smoothing_returns_the_input_for_empty_and_non_positive_steps(): void
    {
        $this->assertSame([], TrackFromTerrain::smooth([], 100.0));
        $this->assertSame([null, null, null, null, null], TrackFromTerrain::smooth([null, null, null, null, null], 100.0));
        $z = [1.0, 2.0, 3.0, 4.0, 5.0];
        $this->assertSame($z, TrackFromTerrain::smooth($z, -20.0));
        $this->assertSame($z, TrackFromTerrain::smooth($z, 0.0));
    }

    public function test_smoothing_keeps_a_straight_regular_grade_and_a_flat_line(): void
    {
        $flat = array_fill(0, 12, 55.0);
        foreach (TrackFromTerrain::smooth($flat, 20.0) as $i => $v) {
            $this->assertEqualsWithDelta(55.0, $v, 1e-6, "point {$i}");
        }
        $grade = array_map(fn (int $i): float => 10.0 + 0.05 * 20 * $i, range(0, 11));
        foreach (TrackFromTerrain::smooth($grade, 20.0) as $i => $v) {
            $this->assertEqualsWithDelta($grade[$i], $v, 0.05, "point {$i}");
        }
    }

    public function test_cross_section_at_the_poles_and_with_a_reversed_path(): void
    {
        $north = TrackFromTerrain::crossSection([2.0, 48.0], [2.0, 48.001], [2.0, 48.002]);
        $south = TrackFromTerrain::crossSection([2.0, 48.002], [2.0, 48.001], [2.0, 48.0]);
        $this->assertEqualsWithDelta($north[0][0], $south[30][0], 1e-9, 'sens inverse : coupe retournée');
        $this->assertEqualsWithDelta($north[30][0], $south[0][0], 1e-9);
        // Parcours vers l'est : la droite est au sud.
        $east = TrackFromTerrain::crossSection([2.0, 48.0], [2.001, 48.0], [2.002, 48.0]);
        $this->assertLessThan($east[15][1], $east[0][1], 'à droite (début de la coupe) : au sud');
        $this->assertGreaterThan($east[15][1], $east[30][1]);
        $this->assertCount(31, $east);
    }
}

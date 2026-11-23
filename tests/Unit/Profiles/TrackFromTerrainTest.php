<?php

namespace Tests\Unit\Profiles;

use App\Services\Profiles\TrackFromTerrain;
use PHPUnit\Framework\TestCase;

/**
 * Altitude de la voie estimée d'après le terrain : coupes réelles du RGE ALTI (fixture
 * `tests/Fixtures/profiles/voie-depuis-terrain.json`, extraits du jeu d'étalonnage du 22/11/2026 :
 * LGV Sud-Est, Maurienne, Champagne, abords du viaduc de Morlaix), comparées aux valeurs du script
 * d'étalonnage et au MNT LiDAR HD sous la voie ; cas limites fabriqués.
 */
class TrackFromTerrainTest extends TestCase
{
    /** @return array<string, array{step_m: int, kind: list<string>, cross: list<list<float|null>>, lidar: list<float|null>, bdtopo: list<float|null>, expected_platform: list<float|null>, expected_smooth: list<float|null>}> */
    private static function stretches(): array
    {
        $json = json_decode((string) file_get_contents(__DIR__.'/../../Fixtures/profiles/voie-depuis-terrain.json'), true);

        return $json['stretches'];
    }

    public function test_platform_matches_the_calibration_script_on_real_cross_sections(): void
    {
        foreach (self::stretches() as $name => $s) {
            foreach ($s['cross'] as $i => $cross) {
                $this->assertEqualsWithDelta($s['expected_platform'][$i], TrackFromTerrain::platform($cross), 1e-3, "{$name} point {$i}");
            }
        }
    }

    public function test_smoothing_matches_the_calibration_script_and_stays_within_a_metre_of_the_lidar(): void
    {
        foreach (self::stretches() as $name => $s) {
            // Ponts et tunnels hors du lissage, comme dans le calcul du profil.
            $platform = array_map(
                fn (array $cross, string $kind): ?float => $kind === 'ground' ? TrackFromTerrain::platform($cross) : null,
                $s['cross'],
                $s['kind'],
            );
            $smoothed = TrackFromTerrain::smooth($platform, (float) $s['step_m']);
            foreach ($smoothed as $i => $z) {
                $this->assertEqualsWithDelta($s['expected_smooth'][$i], $z, 2e-3, "{$name} point {$i}");
                if ($z !== null && $s['lidar'][$i] !== null) {
                    $this->assertLessThan(1.0, abs($z - $s['lidar'][$i]), "{$name} point {$i} : écart au LiDAR");
                }
            }
        }
    }

    public function test_the_platform_of_an_embankment_is_its_crest_and_of_a_cutting_its_floor(): void
    {
        // Remblai : talus à 2/3, crête plate de 8 m à 120 m, champ à 114 m.
        $bank = [];
        $cut = [];
        for ($s = -15; $s <= 15; $s++) {
            $bank[] = abs($s) <= 4 ? 120.0 : max(114.0, 120.0 - (abs($s) - 4) * 1.5);
            $cut[] = abs($s) <= 4 ? 80.0 : min(86.0, 80.0 + (abs($s) - 4) * 1.5);
        }

        $this->assertSame(120.0, TrackFromTerrain::platform($bank));
        $this->assertSame(80.0, TrackFromTerrain::platform($cut));
    }

    public function test_the_nearest_flat_band_wins_and_without_one_the_terrain_under_the_trace_is_kept(): void
    {
        // Tracé décalé de 6 m sur le talus : la crête (à gauche) l'emporte sur le champ (à droite, plus loin).
        $cross = [];
        for ($s = -15; $s <= 15; $s++) {
            $cross[] = match (true) {
                $s <= -6 => 50.0,
                $s >= 10 => 45.0,
                default => 50.0 - ($s + 6) * 0.3125,
            };
        }
        $this->assertSame(50.0, TrackFromTerrain::platform($cross));

        // Versant régulier à 10 % : aucune bande plate, altitude du terrain au point du tracé.
        $slope = array_map(fn (int $s): float => 300.0 + 0.1 * $s, range(-15, 15));
        $this->assertSame(300.0, TrackFromTerrain::platform($slope));

        $this->assertNull(TrackFromTerrain::platform(array_fill(0, 31, null)));
        $this->assertNull(TrackFromTerrain::platform([]));
    }

    public function test_smoothing_removes_an_outlier_keeps_a_regular_grade_and_the_missing_points(): void
    {
        /** @var list<float|null> $z */
        $z = array_map(fn (int $i): float => 100.0 + 0.012 * 100 * $i, range(0, 29));
        $z[12] += 6.0;
        $z[20] = null;

        $smoothed = TrackFromTerrain::smooth($z, 100.0);

        $this->assertNull($smoothed[20]);
        $this->assertEqualsWithDelta(100.0 + 1.2 * 12, $smoothed[12], 0.3);
        foreach ([0, 5, 25, 29] as $i) {
            $this->assertEqualsWithDelta(100.0 + 1.2 * $i, $smoothed[$i], 0.3);
        }
    }

    public function test_smoothing_bounds_the_grade_and_leaves_short_runs_untouched(): void
    {
        // Marche de 40 m entre deux points à 100 m : 400 ‰, ramenée à MAX_GRADE au plus.
        $z = [10.0, 10.0, 10.0, 10.0, 10.0, 50.0, 50.0, 50.0, 50.0, 50.0];
        $smoothed = TrackFromTerrain::smooth($z, 100.0);
        for ($i = 1; $i < count($smoothed); $i++) {
            $this->assertLessThanOrEqual(TrackFromTerrain::MAX_GRADE * 100 + 1e-9, abs($smoothed[$i] - $smoothed[$i - 1]));
        }

        $this->assertSame([1.0, null, 3.0, 4.0], TrackFromTerrain::smooth([1.0, null, 3.0, 4.0], 100.0));
        $this->assertSame([1.0, 2.0, 3.0, 4.0, 5.0], TrackFromTerrain::smooth([1.0, 2.0, 3.0, 4.0, 5.0], 0.0));
    }

    public function test_cross_section_is_perpendicular_to_the_path_and_one_metre_apart(): void
    {
        // Parcours vers le nord : coupe de l'est (à droite) vers l'ouest (à gauche).
        $points = TrackFromTerrain::crossSection([2.0, 48.0], [2.0, 48.001], [2.0, 48.002]);

        $this->assertCount(2 * TrackFromTerrain::HALF_M + 1, $points);
        $this->assertEqualsWithDelta([2.0, 48.001], $points[TrackFromTerrain::HALF_M], 1e-9);
        $kx = cos(deg2rad(48.001)) * 111_320.0;
        $this->assertEqualsWithDelta(15.0, ($points[0][0] - 2.0) * $kx, 1e-6);
        $this->assertEqualsWithDelta(48.001, $points[0][1], 1e-9);
        $this->assertEqualsWithDelta(-1.0, ($points[16][0] - $points[15][0]) * $kx, 1e-6);

        // Points confondus : coupe est-ouest par défaut.
        $flat = TrackFromTerrain::crossSection([2.0, 48.0], [2.0, 48.0], [2.0, 48.0]);
        $this->assertEqualsWithDelta(48.0, $flat[0][1], 1e-12);
    }

    public function test_method_serves_the_measured_error_by_trace_offset(): void
    {
        $method = TrackFromTerrain::method();

        $this->assertSame('platform_cross_section', $method['method']);
        $this->assertSame([5, 10, 20], array_column($method['accuracy'], 'trace_offset_m'));
        $this->assertGreaterThanOrEqual(0.9, $method['accuracy'][0]['under_1m_share']);
        $this->assertLessThan(1.0, $method['accuracy'][0]['grade_1km_p90_permil']);
    }
}

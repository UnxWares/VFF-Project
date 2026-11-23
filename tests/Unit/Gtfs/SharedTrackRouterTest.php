<?php

namespace Tests\Unit\Gtfs;

use App\Services\Gtfs\SharedTrackRouter;
use PHPUnit\Framework\TestCase;

/**
 * Chemin le long des voies d'autres lignes (prolongement des formes GTFS) : suit les voies sommet
 * par sommet, ne rebrousse jamais, refuse ce qui est trop loin.
 *
 * Coordonnées autour de (1°, 47°) : 0,001° de longitude fait environ 76 m, 0,001° de latitude 111 m.
 */
class SharedTrackRouterTest extends TestCase
{
    private const FROM = [1.0, 47.0];

    /** Point de la forme 30 m avant le bout : la ligne arrive de l'ouest. */
    private const BEFORE = [0.9996, 47.0];

    private function router(float $maxM = 3_000.0): SharedTrackRouter
    {
        return new SharedTrackRouter(joinM: 10.0, reachM: 50.0, maxM: $maxM, turnDeg: 60.0);
    }

    /** Distance (m) approximative entre deux points. */
    private function metres(array $a, array $b): float
    {
        return hypot(($a[0] - $b[0]) * 111_320 * cos(deg2rad(47.0)), ($a[1] - $b[1]) * 111_320);
    }

    public function test_follows_the_track_that_continues_the_line_up_to_the_station(): void
    {
        // Voie de l'autre ligne : passe par le bout et file vers l'est en courbe douce.
        $track = [[0.99, 47.0], [1.0, 47.0], [1.002, 47.0002], [1.004, 47.0006], [1.006, 47.0012]];

        $path = $this->router()->route([$track], self::FROM, self::BEFORE, [1.006, 47.0013]);

        $this->assertNotNull($path);
        $this->assertLessThan(0.01, $this->metres($path[0], self::FROM), 'part du bout');
        $this->assertSame([1.002, 47.0002], array_map(fn (float $v): float => round($v, 7), $path[1]), 'sommets de la voie, pas de droite');
        $this->assertLessThan(12, $this->metres(end($path), [1.006, 47.0013]));
        // Chaque pas avance vers l'est : aucun retour.
        for ($i = 1; $i < count($path); $i++) {
            $this->assertGreaterThan($path[$i - 1][0], $path[$i][0]);
        }
    }

    public function test_chains_tracks_of_several_lines_across_a_small_gap_and_a_t_junction(): void
    {
        // Voie 1 jusqu'à 1.003 ; voie 2 reprend 1,5 m plus loin (bouts presque jointifs) ; voie 3 part
        // en T du milieu de la voie 2 vers le nord-est, et mène à la gare.
        $one = [[1.0, 47.0], [1.003, 47.0]];
        $two = [[1.00302, 47.0], [1.006, 47.0], [1.009, 47.0]];
        $three = [[1.007, 47.00001], [1.008, 47.0004], [1.009, 47.001]];

        $path = $this->router()->route([$one, $two, $three], self::FROM, self::BEFORE, [1.009, 47.001]);

        $this->assertNotNull($path);
        $this->assertLessThan(1, $this->metres(end($path), [1.009, 47.001]));
        $length = 0.0;
        for ($i = 1; $i < count($path); $i++) {
            $length += $this->metres($path[$i - 1], $path[$i]);
        }
        $this->assertEqualsWithDelta(530 + 190, $length, 30, 'le long des voies, par la bifurcation');
    }

    public function test_a_two_metre_step_between_two_sections_is_not_a_turn(): void
    {
        // Pas latéral de 2,1 m au raccord de deux tronçons (vu à Libourne, BD TOPO) : chemin accepté,
        // le cap se lit sur les 15 derniers mètres.
        $one = [[1.0, 47.0], [1.002, 47.0]];
        $two = [[1.002, 47.0], [1.002, 47.000019], [1.005, 47.000019]];

        $path = $this->router()->route([$one, $two], self::FROM, self::BEFORE, [1.005, 47.0001]);

        $this->assertNotNull($path);
    }

    public function test_never_goes_back_along_the_line(): void
    {
        // La seule voie vers la gare repart vers l'ouest depuis le bout : rebroussement refusé.
        $track = [[1.0, 47.0], [0.998, 47.0], [0.998, 47.002]];

        $this->assertNull($this->router()->route([$track], self::FROM, self::BEFORE, [0.998, 47.002]));
    }

    public function test_never_reverses_at_a_junction(): void
    {
        // Voie vers l'est, puis demi-tour sur une voie parallèle pour revenir à une gare derrière : refusé.
        $out = [[1.0, 47.0], [1.004, 47.0]];
        $back = [[1.004, 47.0], [1.0005, 47.0012]];

        $this->assertNull($this->router()->route([$out, $back], self::FROM, self::BEFORE, [1.0005, 47.0012]));
    }

    public function test_track_too_far_from_the_end_or_from_the_station_gives_nothing(): void
    {
        // Voie à 15 m au nord du bout (au-delà des 10 m de raccord).
        $far = [[1.0, 47.000135], [1.005, 47.000135]];
        $this->assertNull($this->router()->route([$far], self::FROM, self::BEFORE, [1.005, 47.000135]));

        // Voie qui passe à 80 m de la gare seulement.
        $track = [[1.0, 47.0], [1.005, 47.0]];
        $this->assertNull($this->router()->route([$track], self::FROM, self::BEFORE, [1.005, 47.00072]));
    }

    public function test_path_longer_than_the_limit_is_refused(): void
    {
        $track = [[1.0, 47.0], [1.01, 47.0]];

        $this->assertNotNull($this->router(1_000.0)->route([$track], self::FROM, self::BEFORE, [1.01, 47.0]));
        $this->assertNull($this->router(500.0)->route([$track], self::FROM, self::BEFORE, [1.01, 47.0]));
    }

    public function test_no_track_gives_nothing(): void
    {
        $this->assertNull($this->router()->route([], self::FROM, self::BEFORE, [1.005, 47.0]));
    }
}

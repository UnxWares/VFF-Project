<?php

namespace Tests\Unit\Profiles;

use App\Services\Profiles\ProfilePath;
use PHPUnit\Framework\TestCase;

/**
 * Parcours du profil en long : distances géodésiques, pas d'échantillonnage selon la longueur,
 * points sans échantillon dans les lacunes, interpolation et projection d'une gare.
 */
class ProfilePathTest extends TestCase
{
    /** Un degré de latitude ≈ 111,2 km sur la sphère de 6 371 km. */
    public function test_measures_geodesic_distances_along_the_path(): void
    {
        $path = new ProfilePath([[2.0, 45.0], [2.0, 45.5], [2.0, 46.0]]);

        $this->assertEqualsWithDelta(111_195, $path->length(), 50);
        $this->assertEqualsWithDelta(55_597, $path->cumulative[1], 30);
    }

    public function test_step_grows_with_length_between_100_and_250_metres(): void
    {
        $this->assertSame(100, ProfilePath::stepFor(5_000));
        $this->assertSame(100, ProfilePath::stepFor(200_000));
        $this->assertSame(150, ProfilePath::stepFor(275_000));
        $this->assertSame(250, ProfilePath::stepFor(622_000));
        $this->assertSame(250, ProfilePath::stepFor(2_000_000));
    }

    public function test_samples_every_step_with_both_ends_and_gap_edges_but_nothing_inside_a_gap(): void
    {
        // 1 000 m vers le nord (0,008993° de latitude ≈ 1 000 m), lacune de 400 à 700 m.
        $path = new ProfilePath([[2.0, 45.0], [2.0, 45.008993]], [[400.0, 700.0]]);
        $samples = array_map(fn (float $d): int => (int) round($d), $path->sampleDistances(250));

        $this->assertSame([0, 250, 400, 700, 750, 1000], $samples);
        $this->assertTrue($path->inGap(500));
        $this->assertFalse($path->inGap(400));
        $this->assertTrue($path->gapBetween(250, 750));
        $this->assertFalse($path->gapBetween(0, 400));
    }

    public function test_interpolates_a_point_and_locates_a_station_beside_the_path(): void
    {
        $path = new ProfilePath([[2.0, 45.0], [2.0, 46.0]]);
        $middle = $path->pointAt($path->length() / 2);
        $this->assertEqualsWithDelta(45.5, $middle[1], 1e-6);

        // Gare à 0,001° de longitude à l'est (≈ 79 m) du milieu du parcours.
        $at = $path->locate([2.001, 45.5]);
        $this->assertEqualsWithDelta($path->length() / 2, $at['distance'], 5);
        $this->assertEqualsWithDelta(78.6, $at['offset'], 1);
        $this->assertSame(0, $at['segment']);
        $this->assertEqualsWithDelta(0.5, $at['t'], 1e-3);
    }

    public function test_reversed_path_mirrors_gaps(): void
    {
        $path = new ProfilePath([[2.0, 45.0], [2.0, 45.008993]], [[100.0, 300.0]]);
        $back = $path->reversed();

        $this->assertSame([2.0, 45.008993], $back->coords[0]);
        $this->assertEqualsWithDelta($path->length() - 300, $back->gaps[0][0], 1e-6);
        $this->assertEqualsWithDelta($path->length() - 100, $back->gaps[0][1], 1e-6);
    }

    public function test_imposed_jump_lengths_survive_reversal(): void
    {
        // Saut imposé de 500 m vers le troisième sommet, au lieu des 11,1 km à vol d'oiseau.
        $path = new ProfilePath([[2.0, 45.0], [2.0, 45.01], [2.0, 45.11], [2.0, 45.12]], [], [2 => 500.0]);

        $this->assertEqualsWithDelta(1_112 + 500 + 1_112, $path->length(), 2);
        $reversed = $path->reversed();
        $this->assertEqualsWithDelta($path->length(), $reversed->length(), 0.01);
        $this->assertEqualsWithDelta(1_112 + 500, $reversed->cumulative[2], 2);
    }
}

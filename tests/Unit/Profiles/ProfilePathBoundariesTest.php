<?php

namespace Tests\Unit\Profiles;

use App\Services\Profiles\ProfilePath;
use PHPUnit\Framework\TestCase;

/**
 * Pas d'échantillonnage, trous et bornes de ProfilePath : tests ajoutés après une passe de mutation
 * (Infection, tests/Mutation/Profiles).
 */
class ProfilePathBoundariesTest extends TestCase
{
    public function test_the_step_grows_by_fifty_metres_every_hundred_kilometres_between_100_and_250_m(): void
    {
        $this->assertSame(100, ProfilePath::stepFor(0.0));
        $this->assertSame(100, ProfilePath::stepFor(200_000.0));
        // Dès que la longueur dépasse 200 km : palier suivant (arrondi au palier supérieur, jamais au plus proche).
        $this->assertSame(150, ProfilePath::stepFor(200_100.0));
        $this->assertSame(150, ProfilePath::stepFor(210_000.0));
        $this->assertSame(150, ProfilePath::stepFor(300_000.0));
        $this->assertSame(200, ProfilePath::stepFor(400_000.0));
        $this->assertSame(250, ProfilePath::stepFor(400_100.0));
        $this->assertSame(250, ProfilePath::stepFor(2_000_000.0));
    }

    public function test_the_gaps_of_a_reversed_path_are_mirrored_and_stay_in_order(): void
    {
        $path = new ProfilePath([[2.0, 48.0], [2.05, 48.0]], [[100.0, 200.0], [400.0, 500.0]]);
        $length = $path->length();

        $reversed = $path->reversed();

        $this->assertEqualsWithDelta([[$length - 500.0, $length - 400.0], [$length - 200.0, $length - 100.0]], $reversed->gaps, 1e-6);
        $this->assertSame(array_reverse($path->coords), $reversed->coords);
    }

    public function test_samples_closer_than_one_metre_collapse_but_exactly_one_metre_apart_both_stay(): void
    {
        $path = new ProfilePath([[2.0, 48.0], [2.05, 48.0]], [[101.0, 201.0], [300.4, 400.0]]);

        $samples = $path->sampleDistances(100);

        // 100 et 101 (borne du trou) : un mètre exactement, deux points. 300 et 300,4 : un seul.
        $this->assertContains(100.0, $samples);
        $this->assertContains(101.0, $samples);
        $this->assertContains(300.0, $samples);
        $this->assertNotContains(300.4, $samples);
        $this->assertContains(400.0, $samples);
        $this->assertSame(0.0, $samples[0]);
        $this->assertSame($path->length(), $samples[count($samples) - 1]);
        $this->assertSame(1, count(array_keys($samples, 300.0, true)));
    }

    public function test_a_distance_is_inside_a_gap_only_beyond_half_a_metre_from_its_edges(): void
    {
        $path = new ProfilePath([[2.0, 48.0], [2.05, 48.0]], [[100.0, 200.0]]);

        $this->assertFalse($path->inGap(100.5));
        $this->assertTrue($path->inGap(100.51));
        $this->assertTrue($path->inGap(199.49));
        $this->assertFalse($path->inGap(199.5));
        $this->assertFalse($path->inGap(50.0));
        $this->assertFalse($path->inGap(250.0));
    }

    public function test_a_stretch_crosses_a_gap_only_by_more_than_half_a_metre_on_each_side_whichever_the_order(): void
    {
        $path = new ProfilePath([[2.0, 48.0], [2.05, 48.0]], [[100.0, 200.0]]);

        $this->assertTrue($path->gapBetween(0.0, 300.0));
        $this->assertTrue($path->gapBetween(300.0, 0.0));
        $this->assertFalse($path->gapBetween(0.0, 100.5));
        $this->assertTrue($path->gapBetween(0.0, 100.51));
        $this->assertFalse($path->gapBetween(199.5, 300.0));
        $this->assertTrue($path->gapBetween(199.49, 300.0));
        $this->assertFalse($path->gapBetween(210.0, 300.0));
        $this->assertFalse($path->gapBetween(0.0, 50.0));
    }
}

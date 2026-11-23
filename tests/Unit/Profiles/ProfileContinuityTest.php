<?php

namespace Tests\Unit\Profiles;

use App\Services\Profiles\ProfileContinuity;
use PHPUnit\Framework\TestCase;

/**
 * Vrais trous du tracé de référence sous un profil (ProfileContinuity) : les trous conventionnels
 * posés devant chaque embranchement (leur fin est le début de l'embranchement) ne comptent pas.
 */
class ProfileContinuityTest extends TestCase
{
    public function test_the_conventional_gaps_before_branches_are_not_holes(): void
    {
        $gaps = [['from_m' => 40_000, 'to_m' => 40_500], ['from_m' => 12_000, 'to_m' => 15_000], ['from_m' => 20_000, 'to_m' => 20_000]];
        $branches = [['from_m' => 40_500, 'to_m' => 44_000]];

        $this->assertSame([['from_m' => 12_000, 'to_m' => 15_000]], ProfileContinuity::holes($gaps, $branches));
        $this->assertSame([], ProfileContinuity::holes($gaps, $branches, 5_000));
        $this->assertSame([], ProfileContinuity::holes([], []));
    }

    public function test_the_summary_counts_the_holes_and_their_share_of_the_route(): void
    {
        $gaps = [['from_m' => 30_000, 'to_m' => 32_000], ['from_m' => 10_000, 'to_m' => 13_000]];

        $summary = ProfileContinuity::summary($gaps, [], 45_000);

        $this->assertSame(2, $summary['count']);
        $this->assertSame(5_000, $summary['length_m']);
        // 5 km de trous pour 45 km connus : 10 % du parcours.
        $this->assertSame(0.1, $summary['share']);
        $this->assertSame([10_000, 30_000], array_column($summary['ranges'], 'from_m'));
        // Profil ancien sans longueur connue : la part n'est pas inventée.
        $this->assertNull(ProfileContinuity::summary($gaps, [], null)['share']);
        $this->assertSame(['count' => 0, 'length_m' => 0, 'share' => 0.0, 'ranges' => []], ProfileContinuity::summary([], [], 45_000));
    }
}

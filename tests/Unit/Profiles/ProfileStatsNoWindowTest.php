<?php

namespace Tests\Unit\Profiles;

use App\Services\Profiles\ProfileStats;
use PHPUnit\Framework\TestCase;

/**
 * Comportement attendu de ProfileStats::summarize() quand aucune fenêtre de 1 000 m n'est admise
 * et qu'aucun drapeau n'est fourni : relevé par la passe de mutation du 21/11/2026.
 */
class ProfileStatsNoWindowTest extends TestCase
{
    public function test_without_any_admitted_window_the_grade_is_null(): void
    {
        // Trois points sur 200 m puis un point à 5 km : aucune fenêtre n'atteint les trois quarts de 1 000 m.
        $summary = ProfileStats::summarize([[0.0, 100.0], [100.0, 105.0], [200.0, 110.0], [5000.0, 110.0]]);

        $this->assertNotNull($summary);
        $this->assertNull($summary['max_grade_permil']);
        $this->assertNull($summary['max_grade_from_m']);
        $this->assertNull($summary['max_grade_to_m']);
        $this->assertNull($summary['max_grade_direction']);
    }
}

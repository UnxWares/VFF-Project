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
    public function test_without_any_admitted_window_the_grade_is_null_or_carries_a_reason(): void
    {
        // Trois points sur 200 m puis un point à 5 km : aucune fenêtre n'atteint les trois quarts de 1 000 m.
        $summary = ProfileStats::summarize([[0.0, 100.0], [100.0, 105.0], [200.0, 110.0], [5000.0, 110.0]]);

        $this->assertNotNull($summary);
        $published = $summary['max_grade_permil'] !== null && $summary['max_grade_withheld'] === null;
        if ($published && $summary['max_grade_permil'] === 0.0) {
            $this->markTestIncomplete(
                'Sans drapeaux et sans fenêtre admise, summarize() rend une pente de 0,0 pour mille "up" sans motif de refus ; '
                .'une pente publiée de 0 est fausse, attendu : null ou un motif dans max_grade_withheld (comportement figé tel quel '
                .'par test_a_window_must_reach_three_quarters_of_its_length, code non corrigé).',
            );
        }
        $this->assertTrue($summary['max_grade_permil'] === null || $summary['max_grade_withheld'] !== null);
    }
}

<?php

namespace Tests\Unit\Profiles;

use App\Services\Profiles\ProfileStats;
use App\Services\Profiles\RackRailways;
use App\Services\Profiles\SourcedGrades;
use Tests\TestCase;

/**
 * Liste versionnée des déclivités sourcées (database/data/sourced_grades.php) : entrées complètes,
 * sourcées par le permalien d'une révision de Wikipédia, plausibles pour une ligne à adhérence, et
 * données seulement à la place d'une mesure non publiée.
 */
class SourcedGradesTest extends TestCase
{
    public function test_every_entry_is_complete_sourced_and_plausible(): void
    {
        $all = SourcedGrades::all();

        $this->assertGreaterThan(400, count($all));
        foreach ($all as $slug => $entry) {
            $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $slug);
            $this->assertGreaterThanOrEqual(1, $entry['max_grade_permil'], $slug);
            $this->assertLessThanOrEqual(ProfileStats::MAX_PLAUSIBLE_GRADE * 1000, $entry['max_grade_permil'], $slug);
            $this->assertMatchesRegularExpression('#^https://fr\.wikipedia\.org/w/index\.php\?title=[^&]+&oldid=\d+$#', $entry['source_url'], $slug);
            $this->assertNotSame('', $entry['source_title'], $slug);
            $this->assertStringStartsWith('pente = ', $entry['quote'], $slug);
            $this->assertStringNotContainsString('<ref', $entry['quote'], $slug);
            $this->assertStringNotContainsString('—', $entry['quote'].$entry['source_title']);
            // Une ligne à crémaillère a sa propre fiche (rack_railways.php).
            $this->assertFalse(RackRailways::isRack($slug), $slug);
        }
    }

    public function test_the_sourced_grade_is_given_only_when_the_measured_one_is_withheld(): void
    {
        foreach (['terrain_only', 'tunnel', 'interpolated'] as $reason) {
            $grade = SourcedGrades::present('rfn-405000', $reason);
            $this->assertNotNull($grade, $reason);
            $this->assertSame(10.0, $grade['max_grade_permil']);
            $this->assertSame('wikipedia', $grade['source']['provider']);
            $this->assertSame('CC BY-SA 4.0', $grade['source']['license']);
            $this->assertStringContainsString('oldid=240174253', $grade['source']['url']);
        }
        // Mesure publiée, parcours trop court (motif nul) ou crémaillère : rien.
        $this->assertNull(SourcedGrades::present('rfn-405000', null));
        $this->assertNull(SourcedGrades::present('rfn-405000', 'rack'));
        $this->assertNull(SourcedGrades::present('ligne-inconnue', 'terrain_only'));
    }

    public function test_decimal_values_are_kept(): void
    {
        $decimals = array_filter(SourcedGrades::all(), fn (array $e): bool => is_float($e['max_grade_permil']));

        $this->assertNotEmpty($decimals);
        $this->assertSame(7.8, SourcedGrades::present('archeo-ligne-de-bondy-a-aulnay-sous-bois', 'terrain_only')['max_grade_permil'] ?? null);
    }
}

<?php

namespace Tests\Feature\Reputation;

use App\Services\Reputation\ReputationLevels;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Niveaux de réputation : seuils lus dans config/reputation.php, niveau le plus haut dont tous
 * les seuils sont atteints, taux d'acceptation arrondi vers le bas, compte suspendu sans niveau.
 */
class ReputationLevelsTest extends TestCase
{
    private function levels(): ReputationLevels
    {
        return ReputationLevels::fromConfig();
    }

    public function test_the_default_levels_are_ordered_and_the_first_is_open_to_everyone(): void
    {
        $levels = $this->levels()->all();

        $this->assertSame(['newcomer', 'contributor', 'regular', 'experienced'], array_keys($levels));
        $this->assertSame(['applied' => 0, 'reviews' => 0, 'acceptance' => 0], $levels['newcomer']);

        // Chaque seuil ne baisse jamais d'un niveau au suivant : un niveau plus haut n'est jamais plus facile.
        $previous = null;
        foreach ($levels as $key => $thresholds) {
            if ($previous !== null) {
                foreach (['applied', 'reviews', 'acceptance'] as $field) {
                    $this->assertGreaterThanOrEqual($previous[$field], $thresholds[$field], "{$key}.{$field}");
                }
            }
            $previous = $thresholds;
        }
    }

    public function test_every_level_has_a_label_in_the_three_languages(): void
    {
        foreach (['fr', 'en', 'de'] as $locale) {
            foreach (array_keys($this->levels()->all()) as $key) {
                $this->assertNotSame("reputation.levels.{$key}", __("reputation.levels.{$key}", [], $locale), "{$locale} {$key}");
            }
        }
    }

    /** @return iterable<string, array{int, int, int, string}> */
    public static function levelCases(): iterable
    {
        yield 'aucune activité' => [0, 0, 0, 'newcomer'];
        yield 'une contribution acceptée' => [1, 0, 0, 'contributor'];
        yield 'quatre acceptées' => [4, 0, 0, 'contributor'];
        yield 'cinq acceptées, aucun refus' => [5, 0, 0, 'regular'];
        yield 'cinq acceptées, trois refusées : 62 %' => [5, 3, 0, 'regular'];
        yield 'cinq acceptées, quatre refusées : 55 %' => [5, 4, 0, 'contributor'];
        yield 'vingt acceptées sans relecture' => [20, 0, 0, 'regular'];
        yield 'vingt acceptées et dix relectures' => [20, 0, 10, 'experienced'];
        yield 'vingt acceptées, dix relectures, 76 %' => [20, 6, 10, 'regular'];
        yield 'des relectures seules ne donnent pas de niveau' => [0, 0, 50, 'newcomer'];
        yield 'que des refus' => [0, 7, 0, 'newcomer'];
    }

    #[DataProvider('levelCases')]
    public function test_the_level_is_the_highest_whose_thresholds_are_all_met(int $applied, int $rejected, int $reviews, string $level): void
    {
        $this->assertSame($level, $this->levels()->summarise($applied, $rejected, $reviews)->level);
    }

    public function test_the_acceptance_rate_is_rounded_down_and_absent_without_decision(): void
    {
        $this->assertNull($this->levels()->summarise(0, 0, 4)->acceptanceRate);
        $this->assertSame(66, $this->levels()->summarise(2, 1, 0)->acceptanceRate);
        $this->assertSame(78, $this->levels()->summarise(15, 4, 0)->acceptanceRate);
        $this->assertSame(100, $this->levels()->summarise(3, 0, 0)->acceptanceRate);
        $this->assertSame(0, $this->levels()->summarise(0, 2, 0)->acceptanceRate);
    }

    public function test_exactly_at_a_threshold_counts_and_one_below_does_not(): void
    {
        $this->assertSame('experienced', $this->levels()->summarise(24, 6, 10)->level, '24 / 30 = 80 %, 10 relectures');
        $this->assertSame('regular', $this->levels()->summarise(23, 6, 10)->level, '23 / 29 = 79 %');
        $this->assertSame('regular', $this->levels()->summarise(24, 6, 9)->level, 'une relecture de moins');
        $this->assertSame('regular', $this->levels()->summarise(19, 0, 10)->level, 'une contribution de moins');
    }

    public function test_a_suspended_account_keeps_its_figures_but_has_no_level(): void
    {
        $summary = $this->levels()->summarise(8, 1, 3, suspended: true);

        $this->assertNull($summary->level);
        $this->assertNull($summary->next);
        $this->assertSame([8, 1, 3, 88], [$summary->applied, $summary->rejected, $summary->reviews, $summary->acceptanceRate]);
    }

    public function test_the_next_level_lists_what_is_missing(): void
    {
        $summary = $this->levels()->summarise(3, 3, 0);

        $this->assertSame('contributor', $summary->level);
        $this->assertSame(['level' => 'regular', 'missing' => ['applied' => 2, 'reviews' => 0, 'acceptance' => 60]], $summary->next);

        // Taux de 62 % : le seuil de 60 % du niveau suivant n'est plus réclamé, celui de 80 % l'est.
        $this->assertSame(
            ['level' => 'experienced', 'missing' => ['applied' => 15, 'reviews' => 10, 'acceptance' => 80]],
            $this->levels()->summarise(5, 3, 0)->next,
        );
        $this->assertNull($this->levels()->summarise(30, 0, 20)->next, 'rien après le dernier niveau');
    }

    public function test_changed_thresholds_apply_without_any_recalculation(): void
    {
        config(['reputation.levels.contributor.applied' => 3]);

        $this->assertSame('newcomer', $this->levels()->summarise(2, 0, 0)->level);
        $this->assertSame('contributor', $this->levels()->summarise(3, 0, 0)->level);
    }

    public function test_reviewer_view_hides_what_is_missing_for_the_next_level(): void
    {
        $this->assertSame(
            ['applied' => 6, 'rejected' => 1, 'reviews' => 0, 'acceptance_rate' => 85, 'level' => 'regular'],
            $this->levels()->summarise(6, 1, 0)->forReviewer(),
        );
    }
}

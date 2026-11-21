<?php

namespace Tests\Unit\StationAudit;

use App\Services\StationAudit\DateComparison;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Date VFF de précision « range » (fermeture bornée par les ères, point 6 de matin-2111/tests-nuit.md).
 * VFF ne garde que la borne haute : la fin de l'ère (1960-12-31 pour « entre 1951 et 1960 »), ou le
 * jour du calcul. DateComparison la compare à l'intervalle des ères, borne basse comprise : d'accord
 * dedans, proche à moins d'un an, différente au-delà.
 */
class DateComparisonRangeTest extends TestCase
{
    /** @return iterable<string, array{0: string, 1: string, 2: string, 3: string, 4: int}> */
    public static function rangeCases(): iterable
    {
        // [candidate, précision, borne haute VFF, comparaison, écart en jours à l'intervalle]
        yield '1955, dans l’intervalle 1951-1960' => ['1955-06-01', 'year', '1960-12-31', 'more_precise', 0];
        yield '1951, première année' => ['1951-01-01', 'year', '1960-12-31', 'more_precise', 0];
        yield '1960, dernière année' => ['1960-01-01', 'year', '1960-12-31', 'more_precise', 0];
        yield 'jour dans l’intervalle' => ['1953-05-14', 'day', '1960-12-31', 'more_precise', 0];
        yield 'mois de décembre 1950 : la veille' => ['1950-12-01', 'month', '1960-12-31', 'close', -1];
        yield '1950, l’année d’avant' => ['1950-01-01', 'year', '1960-12-31', 'close', -1];
        yield '1961, l’année d’après' => ['1961-06-01', 'year', '1960-12-31', 'close', 1];
        yield '15 janvier 1962, plus d’un an après' => ['1962-01-15', 'day', '1960-12-31', 'differs', 380];
        yield '1969, après l’intervalle' => ['1969-06-01', 'year', '1960-12-31', 'differs', 2_923];
        yield '1945, avant l’intervalle' => ['1945-01-01', 'year', '1960-12-31', 'differs', -1_827];
        yield 'décennie 1950 : chevauche' => ['1950-01-01', 'decade', '1960-12-31', 'same', 0];
        yield 'décennie 1940 : à plus d’un an' => ['1940-01-01', 'decade', '1960-12-31', 'differs', -366];
        yield 'XIXe siècle : plus grossier' => ['1801-01-01', 'century', '1860-12-31', 'less_precise', 0];
        yield 'fermeture non datée, borne au jour du calcul' => ['2023-03-01', 'year', '2026-11-21', 'more_precise', 0];
        yield 'fermeture non datée, candidate trop ancienne' => ['1950-01-01', 'decade', '2026-11-21', 'differs', -22_282];
    }

    #[DataProvider('rangeCases')]
    public function test_a_range_is_the_interval_of_the_eras(string $date, string $precision, string $upper, string $comparison, int $delta): void
    {
        $this->assertSame(['comparison' => $comparison, 'delta_days' => $delta], DateComparison::compare($date, $precision, $upper, 'range'));
    }

    public function test_an_explicit_lower_bound_wins(): void
    {
        // Ère précédente plus ancienne que la décennie : « entre 1941 et 1960 ».
        $this->assertSame('more_precise', DateComparison::compare('1945-06-01', 'year', '1960-12-31', 'range', '1941-01-01')['comparison']);
        $this->assertSame('close', DateComparison::compare('1940-06-01', 'month', '1960-12-31', 'range', '1941-01-01')['comparison']);
    }

    public function test_other_precisions_are_unchanged(): void
    {
        $this->assertSame(['comparison' => 'differs', 'delta_days' => 245], DateComparison::compare('1867-01-01', 'year', '1866-05-01', 'day'));
        $this->assertSame('same', DateComparison::compare('1950-01-01', 'decade', '1955-01-01', 'decade')['comparison']);
        $this->assertNotSame('close', DateComparison::compare('1867-01-01', 'year', '1866-12-31', 'day')['comparison'], 'proche : réservé aux intervalles des ères');
    }
}

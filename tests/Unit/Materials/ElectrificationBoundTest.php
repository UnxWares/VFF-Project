<?php

namespace Tests\Unit\Materials;

use App\Services\Materials\ElectrificationBound;
use PHPUnit\Framework\TestCase;

/**
 * Une date sourcée ne se déplace jamais : la borne d'électrification ne ramène que les périodes sans source, et
 * une période sourcée plus ancienne que la borne est un conflit (électrification plus ancienne absente de VFF).
 */
class ElectrificationBoundTest extends TestCase
{
    private const BOUND = ['date' => '2012-01-01', 'precision' => 'year'];

    public function test_a_sourced_period_keeps_its_date_and_is_a_conflict(): void
    {
        // BB 25500 sur Grenoble - Montmélian : « depuis septembre 1985 », électrification connue en 2012.
        $periods = [['start_date' => '1985-09-01', 'start_precision' => 'month', 'source' => 'wikipedia', 'source_url' => 'https://fr.wikipedia.org/w/index.php?oldid=1']];

        $this->assertSame($periods, ElectrificationBound::clampPeriods($periods, self::BOUND));
        $this->assertTrue(ElectrificationBound::conflicts($periods, self::BOUND));
    }

    public function test_a_period_without_source_is_brought_back_to_the_bound(): void
    {
        $periods = [['start_date' => '1985-09-01', 'start_precision' => 'month', 'source' => null, 'source_url' => null]];

        $clamped = ElectrificationBound::clampPeriods($periods, self::BOUND);

        $this->assertSame('2012-01-01', $clamped[0]['start_date']);
        $this->assertSame('year', $clamped[0]['start_precision']);
        $this->assertSame('electrification', $clamped[0]['start_bounded_by']);
        $this->assertFalse(ElectrificationBound::conflicts($clamped, self::BOUND));
    }

    public function test_a_period_after_the_bound_or_without_start_is_left_alone(): void
    {
        $periods = [
            ['start_date' => '2013-01-01', 'start_precision' => 'year', 'source' => 'wikipedia', 'source_url' => 'https://fr.wikipedia.org/w/index.php?oldid=1'],
            ['start_date' => null, 'end_date' => '2020-01-01', 'source' => null, 'source_url' => null],
        ];

        $this->assertSame($periods, ElectrificationBound::clampPeriods($periods, self::BOUND));
        $this->assertFalse(ElectrificationBound::conflicts($periods, self::BOUND));
    }

    public function test_a_year_or_a_month_is_compared_at_its_precision(): void
    {
        // Thionville - Apach : BB 12000 « en 1960 », ligne électrifiée le 12 mai 1960 : pas de conflit.
        $bound = ['date' => '1960-05-12', 'precision' => 'day'];
        $source = ['source' => 'wikipedia', 'source_url' => 'https://fr.wikipedia.org/w/index.php?oldid=1'];

        $this->assertFalse(ElectrificationBound::conflicts([['start_date' => '1960-01-01', 'start_precision' => 'year'] + $source], $bound));
        $this->assertFalse(ElectrificationBound::conflicts([['start_date' => '1960-05-01', 'start_precision' => 'month'] + $source], $bound));
        $this->assertTrue(ElectrificationBound::conflicts([['start_date' => '1960-04-01', 'start_precision' => 'month'] + $source], $bound));
        $this->assertTrue(ElectrificationBound::conflicts([['start_date' => '1959-01-01', 'start_precision' => 'year'] + $source], $bound));
    }
}

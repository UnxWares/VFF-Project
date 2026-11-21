<?php

namespace Tests\Unit\StationAudit;

use App\Services\StationAudit\DateComparison;
use App\Services\StationAudit\SourceRank;
use PHPUnit\Framework\TestCase;

class DateComparisonTest extends TestCase
{
    public function test_comparison_at_the_coarser_precision(): void
    {
        $this->assertSame('same', DateComparison::compare('1866-05-01', 'day', '1866-05-01', 'day')['comparison']);
        $this->assertSame('more_precise', DateComparison::compare('1866-05-01', 'day', '1866-01-01', 'year')['comparison']);
        $this->assertSame('less_precise', DateComparison::compare('1866-01-01', 'year', '1866-05-01', 'day')['comparison']);
        $r = DateComparison::compare('1867-01-01', 'year', '1866-05-01', 'day');
        $this->assertSame('differs', $r['comparison']);
        $this->assertSame(245, $r['delta_days']);
        $this->assertSame('new', DateComparison::compare('1866-01-01', 'year', null, null)['comparison']);
        // Intervalle des ères (range) : comparé à la décennie.
        $this->assertSame('more_precise', DateComparison::compare('1953-06-01', 'day', '1959-12-31', 'range')['comparison']);
        $this->assertSame('same', DateComparison::compare('1950-01-01', 'decade', '1955-01-01', 'decade')['comparison']);
    }

    public function test_proposed_rank_rule(): void
    {
        $this->assertTrue(SourceRank::wouldReplace('new', 'wikidata', null));
        $this->assertTrue(SourceRank::wouldReplace('differs', 'wikidata', 'inferred:line_event'));
        $this->assertTrue(SourceRank::wouldReplace('more_precise', 'wikidata', 'wikipedia'));
        $this->assertFalse(SourceRank::wouldReplace('differs', 'wikidata', 'wikipedia'));
        $this->assertFalse(SourceRank::wouldReplace('differs', 'wikidata', 'correction'));
        $this->assertFalse(SourceRank::wouldReplace('same', 'wikidata', 'inferred:line_event'));
        $this->assertFalse(SourceRank::wouldReplace('less_precise', 'wikidata', 'vff:eras'));
        $this->assertSame(4, SourceRank::of('correction'));
        $this->assertSame(2, SourceRank::of('lignes-oubliees'));
        $this->assertSame(1, SourceRank::of('vff:eras'));
    }
}

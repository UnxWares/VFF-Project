<?php

namespace Tests\Unit\StationService;

use App\Services\StationService\Ymd;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Dates GTFS en entiers AAAAMMJJ (Ymd) : lecture stricte, calendrier réel (années bissextiles,
 * changements de mois et d'année), durée inclusive.
 */
class YmdTest extends TestCase
{
    public function test_parse_accepts_compact_and_iso_forms(): void
    {
        $this->assertSame(20261118, Ymd::parse('20261118'));
        $this->assertSame(20261118, Ymd::parse('2026-11-18'));
        $this->assertSame(20261118, Ymd::parse(' 20261118 '));
    }

    /** @return array<string, array{string}> */
    public static function invalidDates(): array
    {
        return [
            'vide' => [''],
            'trop court' => ['2026118'],
            'trop long' => ['202611180'],
            'lettres' => ['2026-1A-18'],
            'jour du mois' => ['18/11/2026'],
        ];
    }

    #[DataProvider('invalidDates')]
    public function test_parse_rejects_anything_else(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        Ymd::parse($value);
    }

    public function test_to_date_and_back(): void
    {
        $date = Ymd::toDate(20260228);

        $this->assertSame('2026-02-28 00:00:00', $date->format('Y-m-d H:i:s'));
        $this->assertSame(20260228, Ymd::fromDate($date));
        $this->assertSame('2026-02-28', Ymd::iso(20260228));
    }

    public function test_to_date_rejects_a_value_that_is_not_a_date(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Ymd::toDate(0);
    }

    public function test_from_date_ignores_the_time_of_day(): void
    {
        $this->assertSame(20261118, Ymd::fromDate(new DateTimeImmutable('2026-11-18 23:59:59')));
    }

    /** @return array<string, array{int, int, int}> */
    public static function additions(): array
    {
        return [
            'dans le mois' => [20261118, 5, 20261123],
            'fin de mois' => [20261130, 1, 20261201],
            'fin d\'année' => [20261231, 1, 20270101],
            'février d\'une année bissextile' => [20240228, 1, 20240229],
            'février ordinaire' => [20260228, 1, 20260301],
            'retour en arrière' => [20260301, -1, 20260228],
            'zéro jour' => [20261118, 0, 20261118],
            'un an' => [20260101, 365, 20270101],
        ];
    }

    #[DataProvider('additions')]
    public function test_add_days(int $from, int $days, int $expected): void
    {
        $this->assertSame($expected, Ymd::addDays($from, $days));
    }

    public function test_span_counts_both_ends(): void
    {
        $this->assertSame(1, Ymd::span(20261118, 20261118));
        $this->assertSame(30, Ymd::span(20261116, 20261215));
        $this->assertSame(366, Ymd::span(20240101, 20241231));
    }

    public function test_span_of_an_inverted_range_is_zero(): void
    {
        $this->assertSame(0, Ymd::span(20261119, 20261118));
    }
}

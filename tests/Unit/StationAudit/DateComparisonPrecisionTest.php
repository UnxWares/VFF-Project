<?php

namespace Tests\Unit\StationAudit;

use App\Services\StationAudit\DateComparison;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

/**
 * Étendues et troncatures de DateComparison à chaque précision (siècle, décennie, année, mois, jour),
 * bornes de l'intervalle « range » et début d'intervalle déduit des ères. Tue les mutants de
 * tests de mutation du 21/11/2026 : les cas existants ne touchaient que l'année et le jour.
 */
class DateComparisonPrecisionTest extends TestCase
{
    /** Jours entre deux dates (a moins b), calculés sans passer par la classe testée. */
    private static function diff(string $a, string $b): int
    {
        $utc = new DateTimeZone('UTC');

        return (int) (new DateTimeImmutable($b, $utc))->diff(new DateTimeImmutable($a, $utc))->format('%r%a');
    }

    /** @return array{string, ?int} */
    private static function cmp(string $date, string $precision, string $vff, string $vffPrecision, ?string $from = null): array
    {
        $r = DateComparison::compare($date, $precision, $vff, $vffPrecision, $from);

        return [$r['comparison'], $r['delta_days']];
    }

    public function test_same_precision_compares_at_each_level(): void
    {
        // Siècle (rangé à sa première année : 1801 pour le XIXe, mais 1900 est encore le XIXe).
        $this->assertSame('same', self::cmp('1801-01-01', 'century', '1900-06-01', 'century')[0]);
        $this->assertSame('differs', self::cmp('1801-01-01', 'century', '1901-01-01', 'century')[0]);
        $this->assertSame('differs', self::cmp('1800-01-01', 'century', '1801-01-01', 'century')[0]);
        // Décennie.
        $this->assertSame('same', self::cmp('1950-01-01', 'decade', '1959-12-31', 'decade')[0]);
        $this->assertSame('differs', self::cmp('1959-12-31', 'decade', '1960-01-01', 'decade')[0]);
        // Année.
        $this->assertSame('same', self::cmp('1955-01-01', 'year', '1955-12-31', 'year')[0]);
        $this->assertSame('differs', self::cmp('1955-12-31', 'year', '1956-01-01', 'year')[0]);
        // Mois.
        $this->assertSame('same', self::cmp('1955-03-01', 'month', '1955-03-31', 'month')[0]);
        $this->assertSame('differs', self::cmp('1955-03-31', 'month', '1955-04-01', 'month')[0]);
        $this->assertSame('differs', self::cmp('1955-03-01', 'month', '1956-03-01', 'month')[0]);
        // Jour.
        $this->assertSame(['same', 0], self::cmp('1955-03-01', 'day', '1955-03-01', 'day'));
        $this->assertSame(['differs', 1], self::cmp('1955-03-02', 'day', '1955-03-01', 'day'));
        $this->assertSame(['differs', -366], self::cmp('1954-03-01', 'day', '1955-03-02', 'day'));
    }

    public function test_the_coarser_precision_decides_and_the_finer_one_is_named(): void
    {
        $this->assertSame('more_precise', self::cmp('1955-03-01', 'month', '1955-01-01', 'year')[0]);
        $this->assertSame('less_precise', self::cmp('1955-01-01', 'year', '1955-03-01', 'month')[0]);
        $this->assertSame('more_precise', self::cmp('1953-03-01', 'day', '1950-01-01', 'decade')[0]);
        $this->assertSame('less_precise', self::cmp('1950-01-01', 'decade', '1953-03-01', 'day')[0]);
        $this->assertSame('more_precise', self::cmp('1850-03-01', 'decade', '1801-01-01', 'century')[0]);
        $this->assertSame('differs', self::cmp('1950-03-01', 'decade', '1801-01-01', 'century')[0]);
        // Une précision inconnue vaut l'année, des deux côtés.
        $this->assertSame('differs', self::cmp('1955-01-01', 'foo', '1958-01-01', 'year')[0]);
        $this->assertSame('less_precise', self::cmp('1955-03-01', 'foo', '1955-04-01', 'month')[0]);
        $this->assertSame('differs', self::cmp('1955-03-01', 'year', '1956-04-01', 'foo')[0]);
        $this->assertSame('more_precise', self::cmp('1955-03-01', 'month', '1955-04-01', 'foo')[0]);
        // Sans précision côté VFF : le jour.
        $this->assertSame('differs', DateComparison::compare('1955-03-01', 'day', '1955-03-02', null)['comparison']);
    }

    public function test_range_edges_in_days(): void
    {
        $range = fn (string $date, string $precision): array => self::cmp($date, $precision, '1960-12-31', 'range');
        // Bornes du jour : intervalle du 1951-01-01 au 1960-12-31, « proche » à moins de 366 jours.
        $this->assertSame(['more_precise', 0], $range('1951-01-01', 'day'));
        $this->assertSame(['more_precise', 0], $range('1960-12-31', 'day'));
        $this->assertSame(['close', -1], $range('1950-12-31', 'day'));
        $this->assertSame(['close', -365], $range('1950-01-01', 'day'));
        $this->assertSame(['differs', -366], $range('1949-12-31', 'day'));
        $this->assertSame(['close', 1], $range('1961-01-01', 'day'));
        $this->assertSame(['close', 365], $range('1961-12-31', 'day'));
        $this->assertSame(['differs', 366], $range('1962-01-01', 'day'));
    }

    public function test_range_spans_by_precision(): void
    {
        $range = fn (string $date, string $precision): array => self::cmp($date, $precision, '1960-12-31', 'range');
        // Mois : étendue du premier au dernier jour.
        $this->assertSame(['close', -1], $range('1950-12-15', 'month'));
        $this->assertSame(['close', 1], $range('1961-01-15', 'month'));
        $this->assertSame(['more_precise', 0], $range('1951-01-15', 'month'));
        $this->assertSame(['close', self::diff('1950-02-28', '1951-01-01')], $range('1950-02-10', 'month'));
        // Année.
        $this->assertSame(['close', -1], $range('1950-06-01', 'year'));
        $this->assertSame(['close', 1], $range('1961-06-01', 'year'));
        $this->assertSame(['more_precise', 0], $range('1955-06-01', 'year'));
        $this->assertSame(['differs', self::diff('1948-12-31', '1951-01-01')], $range('1948-06-01', 'year'));
        $this->assertSame(['differs', self::diff('1963-01-01', '1960-12-31')], $range('1963-06-01', 'year'));
        // Décennie : même rang que l'intervalle.
        $this->assertSame(['same', 0], $range('1950-06-01', 'decade'));
        $this->assertSame(['same', 0], $range('1960-06-01', 'decade'));
        $this->assertSame(['differs', self::diff('1949-12-31', '1951-01-01')], $range('1940-01-01', 'decade'));
        $this->assertSame(['differs', self::diff('1980-01-01', '1960-12-31')], $range('1985-05-05', 'decade'));
        // Siècle : 1801 est le XIXe (1801 à 1900), 1901 le XXe.
        $this->assertSame(['less_precise', 0], $range('1901-01-01', 'century'));
        $this->assertSame(['differs', self::diff('1900-12-31', '1951-01-01')], $range('1801-01-01', 'century'));
        $this->assertSame(['differs', self::diff('1800-12-31', '1951-01-01')], $range('1701-01-01', 'century'));
        $this->assertSame(['differs', self::diff('2001-01-01', '1960-12-31')], $range('2001-01-01', 'century'));
        // Une année ronde reste dans le siècle qui finit (1900 : XIXe) ; 2000 finit le XXe.
        $this->assertSame(['differs', self::diff('1900-12-31', '1951-01-01')], $range('1900-01-01', 'century'));
        $this->assertSame(['less_precise', 0], $range('2000-01-01', 'century'));
    }

    public function test_range_start_is_deduced_from_the_decadal_eras_or_given(): void
    {
        // Ère décennale : « entre 1951 et 1960 » commence le 1951-01-01, pas le 1941 ni le 1961.
        $this->assertSame(['close', -1], self::cmp('1950-12-31', 'day', '1960-12-31', 'range'));
        $this->assertSame(['more_precise', 0], self::cmp('1951-01-01', 'day', '1960-12-31', 'range'));
        $this->assertSame(['close', -1], self::cmp('1960-12-31', 'day', '1961-12-31', 'range'));
        $this->assertSame(['more_precise', 0], self::cmp('1961-01-01', 'day', '1961-12-31', 'range'));
        $this->assertSame(['close', -1], self::cmp('1940-12-31', 'day', '1950-12-31', 'range'));
        // Jour du calcul (ère 2030) : l'intervalle commence le 2021-01-01.
        $this->assertSame(['close', -1], self::cmp('2020-12-31', 'day', '2026-11-21', 'range'));
        $this->assertSame(['more_precise', 0], self::cmp('2021-01-01', 'day', '2026-11-21', 'range'));
        // Borne basse donnée : elle remplace la déduction.
        $this->assertSame(['more_precise', 0], self::cmp('1956-01-01', 'day', '1960-12-31', 'range', '1956-01-01'));
        $this->assertSame(['close', -1], self::cmp('1955-12-31', 'day', '1960-12-31', 'range', '1956-01-01'));
        $this->assertSame(['differs', self::diff('1954-12-31', '1956-01-01')], self::cmp('1954-12-31', 'day', '1960-12-31', 'range', '1956-01-01'));
    }

    public function test_no_vff_date_is_new(): void
    {
        $this->assertSame(['new', null], self::cmp('1955-03-01', 'day', '', 'day'));
        $this->assertSame(['new', null], [DateComparison::compare('1955-03-01', 'day', null, null)['comparison'], DateComparison::compare('1955-03-01', 'day', null, null)['delta_days']]);
    }
}

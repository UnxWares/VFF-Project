<?php

namespace Tests\Unit\Search;

use App\Support\Search\PkQuery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Lecture d'une position sur une ligne dans une requête : « 570000 PK 120 », « Paris - Bordeaux PK 300 »… */
class PkQueryTest extends TestCase
{
    /** @return iterable<string, array{string, string, int}> */
    public static function positions(): iterable
    {
        yield 'pk entier' => ['570000 PK 120', '570000', 120_000];
        yield 'km décimal virgule' => ['570000 km 120,5', '570000', 120_500];
        yield 'km décimal point' => ['570000 km 120.5', '570000', 120_500];
        yield 'notation ferroviaire' => ['ligne 570000 120+500', '570000', 120_500];
        yield 'nom de ligne' => ['Paris - Bordeaux PK 300', 'Paris - Bordeaux', 300_000];
        yield 'pk collé' => ['570000 pk120', '570000', 120_000];
        yield 'p.k.' => ['570000 P.K. 31,336', '570000', 31_336];
        yield 'pk avant la ligne' => ['pk 12 570000', '570000', 12_000];
        yield 'décimales courtes' => ['570000 pk 120,05', '570000', 120_050];
        yield 'km + mètres avec mot-clé' => ['570000 km 120+500', '570000', 120_500];
        yield 'anglais' => ['line 570000 km 5', '570000', 5_000];
        yield 'zéro' => ['570000 pk 0', '570000', 0];
    }

    #[DataProvider('positions')]
    public function test_reads_a_position(string $query, string $text, int $pk): void
    {
        $this->assertSame(['text' => $text, 'pk_m' => $pk], PkQuery::parse($query));
    }

    /** @return iterable<string, array{string}> */
    public static function notPositions(): iterable
    {
        yield 'sans ligne' => ['pk 120'];
        yield 'sans ligne, ferroviaire' => ['120+500'];
        yield 'nom de gare' => ['Saint-Hilaire-de-Chaléons'];
        yield 'code seul' => ['570000'];
        yield 'date' => ['12/07/2013'];
        yield 'mot avec pk dedans' => ['Chapka 12'];
        yield 'pk trop grand' => ['570000 pk 99999'];
        yield 'vide' => [''];
    }

    #[DataProvider('notPositions')]
    public function test_other_queries_are_not_positions(string $query): void
    {
        $this->assertNull(PkQuery::parse($query));
    }

    public function test_reads_a_bare_value(): void
    {
        $this->assertSame(120_500, PkQuery::parseValue('120+500'));
        $this->assertSame(120_500, PkQuery::parseValue('120,5'));
        $this->assertSame(120_000, PkQuery::parseValue('120'));
        $this->assertNull(PkQuery::parseValue('abc'));
        $this->assertNull(PkQuery::parseValue('120 km'));
    }
}

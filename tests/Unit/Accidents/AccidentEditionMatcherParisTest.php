<?php

namespace Tests\Unit\Accidents;

use App\Services\Accidents\AccidentEditionMatcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Les lieux que les listes anglaises et allemandes donnent à Paris sans nommer la commune (noms relevés dans les listes lues). */
class AccidentEditionMatcherParisTest extends TestCase
{
    /** @return iterable<string, array{string, bool}> */
    public static function places(): iterable
    {
        yield 'gare' => ['Gare Montparnasse', true];
        yield 'gare Paris-' => ['Paris-Gare de Lyon', true];
        yield 'gare apostrophe' => ["Gare d'Austerlitz", true];
        yield 'station' => ['Auber station', true];
        yield 'tunnel' => ['Batignolles tunnel', true];
        yield 'metro' => ['Couronnes', true];
        yield 'gare de Lyon-Perrache n\'est pas à Paris' => ['Gare de Lyon-Perrache', false];
        yield 'commune' => ['Melun', false];
        yield 'liste de gares' => ['List of SNCF stations in Rhône-Alpes#Ain', false];
        yield 'vide' => ['', false];
    }

    #[DataProvider('places')]
    public function test_a_place_is_in_paris_only_when_it_names_a_station_or_a_tunnel_of_the_city(string $name, bool $expected): void
    {
        $this->assertSame($expected, AccidentEditionMatcher::isParisPlace($name));
    }
}

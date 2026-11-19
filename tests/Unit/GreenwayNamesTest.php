<?php

namespace Tests\Unit;

use App\Services\Greenways\GreenwayNames;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Nom de voie verte présenté : un itinéraire, jamais un lieu, un type de voie ou une rue. La règle
 * est écrite (GreenwayNames::GENERIC et ROAD_WORDS) : un nom hors de ces deux listes est gardé.
 */
class GreenwayNamesTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function placesAndWayTypes(): iterable
    {
        foreach (['Camping', 'camping', ' Camping ', 'Le Lac', 'Les musées', 'La Gare', 'L’école', 'Voie Verte', 'voie verte', 'Piste cyclable', 'Véloroute', 'Itinéraire', 'Boucle'] as $name) {
            yield $name => [$name];
        }
    }

    #[DataProvider('placesAndWayTypes')]
    public function test_places_and_way_types_are_not_itinerary_names(string $name): void
    {
        $this->assertNull(GreenwayNames::relationName($name));
        $this->assertNull(GreenwayNames::wayName($name));
    }

    /** @return iterable<string, array{string}> */
    public static function itineraries(): iterable
    {
        foreach (['Via Ardèche', 'Voie Verte de Mayenne à Javron', 'Voie verte Flers-Domfront', 'Véloroute 6 Camaret - Saint-Méen-le-Grand', 'La Rochelle',
            'Camping des Pins', 'Le Lac de Der', 'Chemin des Roses', 'castres', 'Avenue Verte London ↔ Paris, tronçon France', 'Chemin Vert du Petit Caux', 'Rues vertes'] as $name) {
            yield $name => [$name];
        }
    }

    #[DataProvider('itineraries')]
    public function test_other_names_are_kept_untouched(string $name): void
    {
        $this->assertSame($name, GreenwayNames::relationName($name));
        $this->assertSame($name, GreenwayNames::wayName($name));
    }

    /** @return iterable<string, array{string}> */
    public static function streets(): iterable
    {
        foreach (['Rue du Denier', 'Boulevard des Océanides', 'Route de Saint-Mesmin', 'Allée Paul Prieto', 'Avenue Léon Jouhaux', 'Place Viarme', 'Impasse de la Gare', 'Quai de la Loire'] as $name) {
            yield $name => [$name];
        }
    }

    #[DataProvider('streets')]
    public function test_a_street_name_is_not_a_way_name(string $name): void
    {
        $this->assertNull(GreenwayNames::wayName($name));
    }

    public function test_a_relation_called_like_a_street_is_still_the_itinerary_of_its_ways(): void
    {
        // Un nom de relation est l'itinéraire par construction ; seul un lieu ou un type de voie est écarté.
        $this->assertSame('Route des Vins', GreenwayNames::relationName('Route des Vins'));
        $this->assertNull(GreenwayNames::wayName('Route des Vins'));
    }

    public function test_empty_names_are_null(): void
    {
        foreach ([null, '', '   '] as $name) {
            $this->assertNull(GreenwayNames::relationName($name));
            $this->assertNull(GreenwayNames::wayName($name));
        }
    }
}

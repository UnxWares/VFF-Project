<?php

namespace Tests\Unit\Structures;

use App\Services\Structures\ProfileLocator;
use App\Services\Structures\WikidataStructures;
use PHPUnit\Framework\TestCase;

/**
 * Lecture des éléments Wikidata (type, dates et précision, longueur et unité, protection, image, article)
 * et position sur le profil en long.
 */
class WikidataStructuresTest extends TestCase
{
    /** @param  array<string, string>  $values */
    private static function row(array $values): array
    {
        return array_map(fn (string $v): array => ['value' => $v], $values + ['item' => 'http://www.wikidata.org/entity/Q1333442', 'coords' => 'Point(3.1736 44.9775)']);
    }

    public function test_a_railway_viaduct_with_dates_heritage_image_and_article(): void
    {
        $item = WikidataStructures::normalize(self::row([
            'labelFr' => 'viaduc de Garabit',
            'lengths' => '565|http://www.wikidata.org/entity/Q11573',
            'inception' => '1880-01-01T00:00:00Z|9',
            'opened' => '1888-11-10T00:00:00Z|11',
            'heritage' => 'http://www.wikidata.org/entity/Q10387575|http://www.wikidata.org/entity/Q10387684|http://www.wikidata.org/entity/Q916475',
            'merimee' => 'PA00093681',
            'image' => 'http://commons.wikimedia.org/wiki/Special:FilePath/Viaduc%20de%20Garabit.jpg',
            'article' => 'https://fr.wikipedia.org/wiki/Viaduc_de_Garabit',
        ]), ['Q1210334', 'Q181348']);

        $this->assertSame('viaduct', $item['kind'], 'pont ferroviaire et viaduc : viaduc');
        $this->assertTrue($item['rail_class']);
        $this->assertSame(565, $item['length_m']);
        $this->assertSame(['date' => '1880-01-01', 'precision' => 'year'], $item['built']);
        $this->assertSame(['date' => '1888-11-10', 'precision' => 'day'], $item['opened']);
        $this->assertSame('classified', $item['heritage'], 'Q10387684 classé l\'emporte sur Q10387575 inscrit');
        $this->assertSame('Viaduc de Garabit.jpg', $item['commons_file']);
        $this->assertSame('Viaduc de Garabit', $item['wikipedia_title']);
        $this->assertSame(3.1736, $item['lon']);
    }

    public function test_generic_classes_units_and_rejected_values(): void
    {
        $bridge = WikidataStructures::normalize(self::row(['labelEn' => 'Viaduc des Fades', 'lengths' => '0.47|http://www.wikidata.org/entity/Q828224']), ['Q12280']);
        $this->assertSame('viaduct', $bridge['kind'], 'nom en « Viaduc » : viaduc');
        $this->assertFalse($bridge['rail_class']);
        $this->assertSame(470, $bridge['length_m'], 'kilomètres convertis');

        $this->assertNull(WikidataStructures::length('12|http://www.wikidata.org/entity/Q174789'), 'autre unité');
        $this->assertSame('protected', WikidataStructures::normalize(self::row(['merimee' => 'PA00092403']), ['Q1210334'])['heritage'], 'notice PA sans statut');
        $registered = self::row(['heritage' => 'http://www.wikidata.org/entity/Q10387575|http://www.wikidata.org/entity/Q916475']);
        $this->assertSame('registered', WikidataStructures::normalize($registered, ['Q1210334'])['heritage'], 'Q10387575 : inscrit');
        $generic = self::row(['heritage' => 'http://www.wikidata.org/entity/Q916475']);
        $this->assertSame('protected', WikidataStructures::normalize($generic, ['Q1210334'])['heritage'], 'Q916475 : protégé sans précision');
        $this->assertNull(WikidataStructures::normalize(self::row(['merimee' => 'IA01000433']), ['Q1210334'])['heritage'], 'inventaire seulement');
        $this->assertNull(WikidataStructures::date('1880-01-01T00:00:00Z|8'), 'décennie : pas gardée');
        $this->assertSame(['date' => '1884-03-01', 'precision' => 'month'], WikidataStructures::date('1884-03-01T00:00:00Z|10'));
        $this->assertNull(WikidataStructures::normalize(self::row(['coords' => 'pas un point']), ['Q1311958']));
        $this->assertNull(WikidataStructures::normalize(self::row([]), ['Q5']), 'aucune classe d\'ouvrage');
        $this->assertSame('tunnel', WikidataStructures::normalize(self::row([]), ['Q1311958'])['kind']);
        $this->assertNull(WikidataStructures::normalize(self::row([]), ['Q1311958'])['name'], 'sans libellé : pas de nom');
    }

    public function test_profile_position_interpolates_distance_and_pk(): void
    {
        $paths = [[
            'coords' => [[3.0, 45.0], [3.01, 45.0], [3.02, 45.0]],
            'distance' => [0, 786, 1572],
            'pk' => [100000, 100786, null],
        ]];

        $this->assertSame(['distance' => 393, 'pk' => 100393], ProfileLocator::at($paths, [3.005, 45.0001]));
        $this->assertNull(ProfileLocator::at($paths, [3.015, 45.0])['pk'], 'un point voisin sans PK : pas de PK');
        $this->assertNull(ProfileLocator::at($paths, [3.005, 45.01]), 'à plus de 80 m du profil');
    }
}

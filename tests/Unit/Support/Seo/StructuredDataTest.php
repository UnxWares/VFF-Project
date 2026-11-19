<?php

namespace Tests\Unit\Support\Seo;

use App\Support\Seo\StructuredData;
use PHPUnit\Framework\TestCase;

/**
 * Données structurées côté serveur : mêmes règles que resources/js/lib/structuredData.js (tests/js/
 * structuredData.test.js) : rien d'inventé, valeurs vides retirées, JSON sans balise fermante.
 */
class StructuredDataTest extends TestCase
{
    private const ORIGIN = 'https://vff-project.org';

    public function test_compact_removes_empty_values_recursively_but_keeps_zero(): void
    {
        $this->assertSame(
            ['a' => 1, 'g' => ['x'], 'h' => 0, 'i' => false],
            StructuredData::compact(['a' => 1, 'b' => null, 'c' => '', 'd' => [], 'e' => ['f' => null], 'g' => [null, 'x'], 'h' => 0, 'i' => false]),
        );
    }

    public function test_serialize_escapes_the_opening_bracket_so_no_text_can_close_the_script(): void
    {
        $json = StructuredData::serialize(['name' => '</script><script>alert(1)</script> é/']);

        $this->assertStringNotContainsString('<', $json);
        $this->assertSame('</script><script>alert(1)</script> é/', json_decode($json, true)['name']);
        $this->assertStringContainsString('é/', $json, 'accents et barres gardés tels quels, comme JSON.stringify');
    }

    public function test_urls(): void
    {
        $this->assertSame('https://vff-project.org/lines/rfn-956000', StructuredData::absoluteUrl(self::ORIGIN.'/', '/lines/rfn-956000'));
        $this->assertSame('https://example.org/a.png', StructuredData::absoluteUrl(self::ORIGIN, 'https://example.org/a.png'));
        $this->assertSame('https://www.wikidata.org/wiki/Q3239771', StructuredData::wikidataUrl('Q3239771'));
        $this->assertNull(StructuredData::wikidataUrl('3239771'));
        $this->assertNull(StructuredData::wikidataUrl(null));
        $this->assertSame(
            'https://fr.wikipedia.org/wiki/Ligne_de_Paris-Bastille_%C3%A0_Marles-en-Brie',
            StructuredData::wikipediaUrl('Ligne de Paris-Bastille à Marles-en-Brie'),
        );
        $this->assertNull(StructuredData::wikipediaUrl(null));
    }

    public function test_shorten_cuts_on_a_word_with_an_ellipsis(): void
    {
        $this->assertSame('Ligne de Vincennes', StructuredData::shorten("Ligne  de\nVincennes"));
        $out = StructuredData::shorten(str_repeat('mot ', 100), 50);
        $this->assertLessThanOrEqual(50, mb_strlen($out));
        $this->assertStringEndsWith('mot…', $out);
        $this->assertNull(StructuredData::shorten(null));
    }

    public function test_og_locale(): void
    {
        $this->assertSame(['fr_FR', 'en_GB', 'de_DE', 'fr_FR'], array_map(StructuredData::ogLocale(...), ['fr', 'en', 'de', 'it']));
    }

    public function test_website_carries_the_search_action_and_the_publisher(): void
    {
        $graph = StructuredData::website(self::ORIGIN, 'Voies Ferrées de France', 'VFF', 'Encyclopédie.', 'fr')['@graph'];

        $this->assertSame('WebSite', $graph[0]['@type']);
        $this->assertSame('https://vff-project.org/lines?q={search_term_string}', $graph[0]['potentialAction']['target']['urlTemplate']);
        $this->assertSame(['@id' => 'https://vff-project.org/#publisher'], $graph[0]['publisher']);
        $this->assertEquals(['@id' => 'https://vff-project.org/#publisher', '@type' => 'Organization', 'name' => 'UnxWares', 'legalName' => 'UnxWares SAS', 'url' => 'https://www.unxwares.com'], $graph[1]);
    }

    public function test_breadcrumb_positions_and_absolute_items(): void
    {
        $list = StructuredData::breadcrumb(self::ORIGIN, [['name' => 'Accueil', 'path' => '/'], ['name' => 'Lignes', 'path' => '/lines']]);

        $this->assertSame(2, $list['itemListElement'][1]['position']);
        $this->assertSame('https://vff-project.org/lines', $list['itemListElement'][1]['item']);
    }

    public function test_image_object_keeps_author_and_license_as_the_database_gives_them(): void
    {
        $media = [
            'title' => 'Paris-vincennes 1859 jms', 'caption' => 'Plan', 'thumb_url' => 'https://t/500.png', 'display_url' => 'https://t/1280.png',
            'source_url' => 'https://commons.wikimedia.org/wiki/File:x.png', 'width' => 7176, 'height' => 6056, 'author' => 'ThePromenader',
            'attribution' => 'ThePromenader, Public domain, via Wikimedia Commons', 'license' => 'Public domain', 'license_url' => null,
        ];

        $image = StructuredData::imageObject($media);

        $this->assertSame('https://t/1280.png', $image['contentUrl']);
        $this->assertSame('https://t/500.png', $image['thumbnailUrl']);
        $this->assertSame(['@type' => 'Person', 'name' => 'ThePromenader'], $image['creator']);
        $this->assertSame('Public domain', $image['copyrightNotice'], 'licence sans adresse : mention, jamais un lien deviné');
        $this->assertNull($image['license']);
        $this->assertNull(StructuredData::imageObject(['title' => 'sans image']));
        $this->assertNull(StructuredData::imageObject(null));
    }

    public function test_line_sheet(): void
    {
        $schema = StructuredData::line(self::ORIGIN, [
            'slug' => 'rfn-610000', 'name' => 'Ligne de Limoges à Angoulême', 'code' => '610000', 'alt_names' => ['Ligne de Limoges à Angoulême', 'Limoges-Angoulême'],
            'wikidata' => 'Q3239771', 'wikipedia_title' => 'Ligne de Limoges-Bénédictins à Angoulême', 'regions' => ['Nouvelle-Aquitaine'],
        ], 'Une ligne.', []);

        $this->assertSame('Place', $schema['@type']);
        $this->assertSame('https://vff-project.org/lines/rfn-610000#line', $schema['@id']);
        $this->assertSame(['Limoges-Angoulême'], $schema['alternateName']);
        $this->assertSame('610000', $schema['identifier']['value']);
        $this->assertCount(2, $schema['sameAs']);
        $this->assertSame([['@type' => 'AdministrativeArea', 'name' => 'Nouvelle-Aquitaine']], $schema['containedInPlace']);
        $this->assertArrayNotHasKey('image', StructuredData::compact($schema), 'sans média, pas de propriété image');
    }

    public function test_station_gives_no_coordinates_when_the_position_is_flagged(): void
    {
        $station = ['slug' => 'bondy', 'name' => 'Bondy', 'lat' => 48.9, 'lng' => 2.48, 'city' => 'Bondy', 'uic_code' => 87113407, 'wikidata_id' => 'Q1', 'alt_names' => [['name' => 'Bondy-gare'], 'Bondy']];

        $sure = StructuredData::station(self::ORIGIN, $station, null);
        $this->assertSame(['@type' => 'GeoCoordinates', 'latitude' => 48.9, 'longitude' => 2.48], $sure['geo']);
        $this->assertSame('87113407', $sure['identifier']['value']);
        $this->assertSame(['Bondy-gare'], $sure['alternateName']);

        $this->assertNull(StructuredData::station(self::ORIGIN, ['position_flagged' => true] + $station, null)['geo']);
    }

    public function test_material_sheet(): void
    {
        $schema = StructuredData::material(self::ORIGIN, [
            'slug' => 'bb-15000', 'name' => 'BB 15000', 'designations' => ['BB 15000', 'Nez cassé'], 'type_label' => 'Locomotive électrique',
            'builders' => [['name' => 'Alsthom', 'wikidata_id' => 'Q123']], 'wikidata_url' => 'https://www.wikidata.org/wiki/Q746572',
            'wikipedia' => ['fr' => ['title' => 'BB 15000', 'url' => 'https://fr.wikipedia.org/wiki/BB_15000'], 'en' => null],
            'image' => ['display_url' => 'https://t/bb.jpg', 'author' => 'Quelqu\'un', 'license' => 'CC BY-SA 4.0', 'license_url' => 'https://creativecommons.org/licenses/by-sa/4.0/'],
        ], 'Série de locomotives.');

        // Thing et non Product : Google exigerait d'un Product un prix ou des avis.
        $this->assertSame('Thing', $schema['@type']);
        $this->assertSame(['Nez cassé'], $schema['alternateName']);
        $this->assertArrayNotHasKey('manufacturer', $schema);
        $this->assertSame('https://t/bb.jpg', $schema['image']['contentUrl']);
        $this->assertSame(['https://www.wikidata.org/wiki/Q746572', 'https://fr.wikipedia.org/wiki/BB_15000'], $schema['sameAs']);
    }

    public function test_accident_keeps_the_date_precision_and_no_coordinates_from_a_commune_centre(): void
    {
        $accident = [
            'slug' => '1842-05-08-meudon', 'name' => ['fr' => 'Accident ferroviaire de Meudon', 'en' => 'Versailles rail accident'], 'summary' => ['fr' => 'Un déraillement.'],
            'date' => '1842-05-08', 'date_precision' => 'day', 'place' => ['commune' => 'Meudon', 'lat' => 48.8, 'lng' => 2.2, 'precision' => 'commune'],
            'wikipedia' => (object) ['fr' => ['url' => 'https://fr.wikipedia.org/wiki/Accident_ferroviaire_de_Meudon']],
        ];

        $event = StructuredData::accident(self::ORIGIN, $accident, 'en', 'Meudon (Hauts-de-Seine)');
        $this->assertSame('Versailles rail accident', $event['name']);
        $this->assertSame('Un déraillement.', $event['description'], 'repli sur le français');
        $this->assertSame('1842-05-08', $event['startDate']);
        $this->assertNull($event['location']['geo'], 'centre de la commune : pas des coordonnées de l\'accident');
        $this->assertSame(['https://fr.wikipedia.org/wiki/Accident_ferroviaire_de_Meudon'], $event['sameAs']);

        $year = StructuredData::accident(self::ORIGIN, ['date_precision' => 'year', 'date' => '1842-01-01', 'place' => ['lat' => 48.8, 'lng' => 2.2, 'precision' => 'exact']] + $accident, 'fr', null);
        $this->assertSame('1842', $year['startDate']);
        $this->assertSame(48.8, $year['location']['geo']['latitude']);
    }
}

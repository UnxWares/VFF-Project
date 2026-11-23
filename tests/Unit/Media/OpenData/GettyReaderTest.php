<?php

namespace Tests\Unit\Media\OpenData;

use App\Services\Media\OpenData\GettyReader;
use PHPUnit\Framework\TestCase;

/**
 * Lecture d'une notice Linked Art du Getty (extrait réel de data.getty.edu, objet 84.XO.401.34 « Tarascon. Viaduc », sans image)
 * et de la requête SPARQL qui choisit les objets.
 */
class GettyReaderTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function object(): array
    {
        return json_decode((string) file_get_contents(__DIR__.'/../../../Fixtures/OpenData/getty-objet-tarascon.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_a_linked_art_object_becomes_a_flat_row(): void
    {
        $row = GettyReader::row(self::object(), 'Tarascon. Viaduc (84.XO.401.34)');

        $this->assertSame('84.XO.401.34', $row['accession']);
        $this->assertSame('Tarascon. Viaduc', $row['title']);
        $this->assertSame('Édouard Baldus', $row['author']);
        $this->assertSame('about 1861', $row['date']);
        $this->assertSame('Tarascon, France', $row['place']);
        $this->assertSame('French', $row['culture']);
        $this->assertSame('Chemin de Fer de Paris a Lyon et le Méditeranné', $row['group_title']);
        $this->assertSame('Chemins de Fer de Paris à Lyon et à Le Méditerranée. [spine title]', $row['album']);
        $this->assertSame('https://www.getty.edu/art/collection/object/107PP8', $row['homepage']);
    }

    public function test_the_rights_are_read_from_the_object_not_assumed(): void
    {
        $row = GettyReader::row(self::object(), 'x');

        $this->assertSame('http://creativecommons.org/publicdomain/zero/1.0/', $row['rights_uri']);
        $this->assertSame('Public Domain', $row['rights_display']);

        // Sans droit déclaré sur l'objet, la ligne n'a ni licence ni affichage : le normaliseur la refusera.
        $bare = self::object();
        unset($bare['subject_to']);
        $row = GettyReader::row($bare, 'x');
        $this->assertNull($row['rights_uri']);
        $this->assertNull($row['rights_display']);
    }

    public function test_the_image_addresses_are_composed_from_the_iiif_identifier_without_any_download(): void
    {
        $row = GettyReader::row(self::object(), 'x');

        $this->assertSame('https://media.getty.edu/iiif/image/c3a8230b-cb04-4639-96a3-ff15422ffa1d/full/1200,/0/default.jpg', $row['image']);
        $this->assertSame('https://media.getty.edu/iiif/image/c3a8230b-cb04-4639-96a3-ff15422ffa1d/full/400,/0/default.jpg', $row['thumb']);
    }

    public function test_the_sparql_query_selects_photographs_of_the_listed_albums_and_french_keywords(): void
    {
        $q = GettyReader::query(['albums' => ['84.XO.401', '84.XM.407'], 'keywords' => 'viaduc|gare']);

        $this->assertStringContainsString('<http://vocab.getty.edu/aat/300046300>', $q);
        $this->assertStringContainsString('84\\\\.XO\\\\.401|84\\\\.XM\\\\.407', $q);
        $this->assertStringContainsString('\\\\b(viaduc|gare)\\\\b', $q);
        $this->assertStringNotContainsString('DELETE', $q);
    }
}

<?php

namespace Tests\Unit\Media\OpenData;

use App\Services\Media\OpenData\DublinCoreReader;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Lecture des notices Dublin Core de Rosalis (ark btv1b105741306, Eugène Trutat) et de Pireneas (ark btv1b10562970w, tramway de Pau),
 * extraits réels de /services/OAIRecord : titre, auteur, sujets, établissement et cote, licence de la notice, adresses IIIF composées.
 */
class DublinCoreReaderTest extends TestCase
{
    private static function xml(string $file): string
    {
        return (string) file_get_contents(__DIR__.'/../../../Fixtures/OpenData/'.$file);
    }

    /** @return array<string, mixed> */
    private static function dataset(string $host): array
    {
        return ['record' => 'https://'.$host.'/services/OAIRecord?ark={ark}', 'image_sizes' => [1600, 400]];
    }

    public function test_a_rosalis_record_becomes_a_flat_row(): void
    {
        $row = DublinCoreReader::row(self::xml('rosalis-oairecord-foix.xml'), 'btv1b105741306', self::dataset('rosalis.bibliotheque.toulouse.fr'));

        $this->assertSame('A la gare, Foix, septembre 1882', $row['title']);
        $this->assertSame('Eugène Trutat', $row['author']);
        $this->assertSame('1882', $row['date']);
        $this->assertSame('Foix', $row['commune']);
        $this->assertSame('Association des Toulousains de Toulouse, Musée du Vieux-Toulouse', $row['source']);
        $this->assertSame('Association des Toulousains de Toulouse, Musée du Vieux-Toulouse', $row['repository']);
        $this->assertNull($row['inventory']);
        $this->assertSame('domaine public | public domain', $row['rights']);
        $this->assertSame('https://rosalis.bibliotheque.toulouse.fr/ark:/12148/btv1b105741306', $row['link']);
        $this->assertStringContainsString('Gare (Foix)', $row['subjects']);
    }

    public function test_image_addresses_are_iiif_sizes_of_the_site_never_the_full_file(): void
    {
        $row = DublinCoreReader::row(self::xml('rosalis-oairecord-foix.xml'), 'btv1b105741306', self::dataset('rosalis.bibliotheque.toulouse.fr'));

        $this->assertSame('https://rosalis.bibliotheque.toulouse.fr/iiif/ark:/12148/btv1b105741306/f1/full/1600,/0/native.jpg', $row['image']);
        $this->assertSame('https://rosalis.bibliotheque.toulouse.fr/iiif/ark:/12148/btv1b105741306/f1/full/400,/0/native.jpg', $row['thumb']);
    }

    public function test_a_pireneas_record_splits_the_shelfmark_from_the_repository_and_reads_the_commune_from_a_subject_heading(): void
    {
        $row = DublinCoreReader::row(self::xml('pireneas-oairecord-tramway.xml'), 'btv1b10562970w', self::dataset('www.pireneas.fr'));

        $this->assertSame('Pau : Tramway de la Gare', $row['title']);
        $this->assertSame('Bibliothèque patrimoniale de Pau', $row['repository']);
        $this->assertSame('8-060-3', $row['inventory']);
        $this->assertSame('Pau', $row['commune']);
        $this->assertNull($row['author']);
        $this->assertStringStartsWith('https://www.pireneas.fr/iiif/ark:/12148/btv1b10562970w/', $row['image']);
    }

    public function test_the_commune_is_the_gare_heading_then_a_place_with_a_department_never_a_region(): void
    {
        $this->assertSame('Foix', DublinCoreReader::commune(['Pyrénées (France)', 'Gare (Foix)', 'Foix (Ariège, France)']));
        $this->assertSame('Foix', DublinCoreReader::commune(['Pyrénées (France)', 'Foix (Ariège, France)']));
        $this->assertSame('Oloron-Sainte-Marie', DublinCoreReader::commune(['Gares -- Oloron-Sainte-Marie (Pyrénées-Atlantiques) -- 20e siècle -- Cartes postales']));
        $this->assertNull(DublinCoreReader::commune(['Pyrénées (France)', 'Ariège (France ; vallée)', 'Monte-Carlo (Monaco)']));
    }

    public function test_only_a_person_who_made_the_image_is_an_author(): void
    {
        $this->assertSame('Eugène Trutat', DublinCoreReader::author(['Trutat, Eugène (1840-1910). Photographe']));
        $this->assertSame('Marie Bruneton', DublinCoreReader::author(['Bruneton, Marie (1860-1940). Photographe']));
        $this->assertNull(DublinCoreReader::author(['Neurdein frères (Paris). Éditeur']));
        $this->assertNull(DublinCoreReader::author(['Dupont, Jean (1850-1920). Éditeur scientifique']));
    }

    public function test_a_shelfmark_needs_a_digit_and_is_not_a_second_institution(): void
    {
        $this->assertSame('8FI001-128-00213', DublinCoreReader::shelfmark('Archives départementales des Pyrénées-Atlantiques, 8FI001-128-00213'));
        $this->assertSame('60 J 67/19', DublinCoreReader::shelfmark('Centre d\'étude du protestantisme béarnais, 60 J 67/19'));
        $this->assertNull(DublinCoreReader::shelfmark('Association des Toulousains de Toulouse, Musée du Vieux-Toulouse'));
        $this->assertNull(DublinCoreReader::shelfmark('Archives municipales de Toulouse'));
        $this->assertSame('Archives départementales des Pyrénées-Atlantiques', DublinCoreReader::repository('Archives départementales des Pyrénées-Atlantiques, 8FI001-128-00213'));
    }

    public function test_an_unreadable_or_untitled_record_is_an_error(): void
    {
        $this->expectException(RuntimeException::class);
        DublinCoreReader::row('<html>pas du XML', 'btv1b1', self::dataset('rosalis.bibliotheque.toulouse.fr'));
    }
}

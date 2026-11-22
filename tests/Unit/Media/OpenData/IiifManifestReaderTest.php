<?php

namespace Tests\Unit\Media\OpenData;

use App\Services\Media\OpenData\IiifManifestReader;
use PHPUnit\Framework\TestCase;

/**
 * Lecture des manifestes IIIF de marque blanche Gallica (extraits réels : École des ponts, ark btv1b104831371 ; Numistral, ark
 * btv1b102164183) et du plan du site.
 */
class IiifManifestReaderTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function manifest(string $file): array
    {
        return json_decode((string) file_get_contents(__DIR__.'/../../../Fixtures/OpenData/'.$file), true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_an_ecole_des_ponts_manifest_becomes_a_flat_row(): void
    {
        $row = IiifManifestReader::row(self::manifest('enpc-manifeste-murat.json'), 'btv1b104831371', ['image_sizes' => [1600, 400]]);

        $this->assertSame("Gare de Murat. Ligne du Cantal. Compagnie d'Orléans", $row['title']);
        $this->assertSame("Gare de Murat. Ligne du Cantal. Compagnie d'Orléans / Berthaud ; Ministère des travaux publics", $row['statement']);
        $this->assertSame('1873', $row['date']);
        $this->assertSame('Michel Berthaud', $row['author']);
        $this->assertSame('Ecole nationale des ponts et chaussées', $row['repository']);
        $this->assertSame('PH 125(10) A.8', $row['inventory']);
        $this->assertSame('image', $row['type']);
        $this->assertSame('https://heritage.ecoledesponts.fr/ark:/12148/btv1b104831371', $row['source']);
    }

    public function test_image_addresses_are_iiif_sizes_of_the_service_never_the_full_file(): void
    {
        $row = IiifManifestReader::row(self::manifest('enpc-manifeste-murat.json'), 'btv1b104831371', ['image_sizes' => [1600, 400]]);

        $this->assertSame('https://heritage.ecoledesponts.fr/iiif/ark:/12148/btv1b104831371/f1/full/1600,/0/native.jpg', $row['image']);
        $this->assertSame('https://heritage.ecoledesponts.fr/iiif/ark:/12148/btv1b104831371/f1/full/400,/0/native.jpg', $row['thumb']);
        $this->assertStringNotContainsString('/full/full/', (string) $row['image']);
    }

    public function test_a_numistral_manifest_keeps_its_repository_for_the_licence_filter(): void
    {
        $row = IiifManifestReader::row(self::manifest('numistral-manifeste-schlestadt.json'), 'btv1b102164183', []);

        $this->assertSame('Station du chemin de fer à Schléstadt', $row['title']);
        $this->assertSame('Bibliothèque nationale et universitaire de Strasbourg', $row['repository']);
        $this->assertSame('NIM28871', $row['inventory']);
        $this->assertSame('1842', $row['date']);
        $this->assertNull($row['author']);
    }

    public function test_only_a_photographer_is_an_author(): void
    {
        $this->assertSame('Michel Berthaud', IiifManifestReader::author(['Berthaud, Michel (1845-1912 ; photographe). Auteur du texte', 'France. Ministère des travaux publics. Éditeur scientifique']));
        $this->assertNull(IiifManifestReader::author(['France. Ministère des travaux publics. Éditeur scientifique']));
        $this->assertNull(IiifManifestReader::author(['Garin, Jean (18..-18.. ; ingénieur). Auteur du texte']));
    }

    public function test_the_sitemap_gives_the_ark_of_images_only_without_duplicates(): void
    {
        $sitemap = "https://heritage.ecoledesponts.fr/enpc/content/accueil-fr\nhttps://heritage.ecoledesponts.fr/ark:/12148/btv1b104831371\nhttps://heritage.ecoledesponts.fr/ark:/12148/bpt6k1234567\nhttps://heritage.ecoledesponts.fr/ark:/12148/btv1b104831371\nhttps://heritage.ecoledesponts.fr/ark:/12148/cb34437522m\nhttps://heritage.ecoledesponts.fr/ark:/12148/btv1b10483050b\n";

        $this->assertSame(['btv1b104831371', 'btv1b10483050b'], IiifManifestReader::arks($sitemap, 'btv'));
    }
}

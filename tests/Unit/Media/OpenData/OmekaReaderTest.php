<?php

namespace Tests\Unit\Media\OpenData;

use App\Services\Media\OpenData\OmekaReader;
use PHPUnit\Framework\TestCase;

/**
 * Lecture des items de la Bibliothèque numérique du Limousin (BFM de Limoges, Omeka S) : extraits réels de /api/items
 * (item 94 d'Audiguet sous « Licence ouverte » et « Domaine public », items 6568 et 6773 du fonds Boudeau « Droits réservés », item 1141).
 */
class OmekaReaderTest extends TestCase
{
    private const DATASET = ['resource' => 'https://bnl-bfm.limoges.fr/api/items', 'site_slug' => 'bibliotheque-virtuelle'];

    /** @return array<int, array<string, mixed>> */
    private static function items(): array
    {
        $items = json_decode((string) file_get_contents(__DIR__.'/../../../Fixtures/OpenData/bnl-limousin-items-extrait.json'), true);
        $byId = [];
        foreach ($items as $item) {
            $byId[$item['o:id']] = $item;
        }

        return $byId;
    }

    public function test_an_open_licence_item_becomes_a_flat_row_with_its_licence_and_direct_image_addresses(): void
    {
        $row = OmekaReader::row(self::items()[94], self::DATASET);

        $this->assertSame('94', $row['id']);
        $this->assertSame('La Vienne près du Pont de chemin de fer, près de la carrière Ardant', $row['title']);
        $this->assertSame('Jean-Baptiste Audiguet', $row['author']);
        $this->assertSame('Vers 1870-1875', $row['date']);
        $this->assertSame('Licence ouverte | Domaine public', $row['rights']);
        $this->assertSame('9Fi502/39', $row['inventory']);
        $this->assertSame('https://bnl-bfm.limoges.fr/files/large/d604fe2b4212440c28e1cf3a638d2c4b96dc8093.jpg', $row['image']);
        $this->assertSame('https://bnl-bfm.limoges.fr/files/medium/d604fe2b4212440c28e1cf3a638d2c4b96dc8093.jpg', $row['thumb']);
        $this->assertSame('https://bnl-bfm.limoges.fr/s/bibliotheque-virtuelle/item/94', $row['link']);
        $this->assertStringContainsString('pont', $row['subjects']);
    }

    public function test_a_reserved_rights_item_keeps_its_reservation_in_the_rights_column(): void
    {
        $row = OmekaReader::row(self::items()[6773], self::DATASET);

        $this->assertStringContainsString('Droits réservés', $row['rights']);
        $this->assertSame('Jean-Baptiste Boudeau', $row['author']);
        $this->assertSame('B26-n°619', $row['inventory']);
        $this->assertSame('Royères', $row['commune']);
    }

    public function test_author_and_place_helpers(): void
    {
        $this->assertSame('Jean-Baptiste Boudeau', OmekaReader::author(['Boudeau, Jean-Baptiste (1881-1959)']));
        $this->assertNull(OmekaReader::author(['Éditeur anonyme']));
        $this->assertSame('Saint-Priest-Taurion', OmekaReader::commune(['Saint-Priest-Taurion, Haute-Vienne']));
        $this->assertSame('Royères', OmekaReader::commune(['Royères (Haute-Vienne, France)']));
        $this->assertNull(OmekaReader::commune([]));
        $this->assertNull(OmekaReader::shelfmark('Sans cote', null, ''));
    }
}

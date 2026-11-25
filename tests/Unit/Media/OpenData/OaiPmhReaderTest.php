<?php

namespace Tests\Unit\Media\OpenData;

use App\Services\Media\OpenData\OaiPmhReader;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Lecture de l'entrepôt OAI-PMH de L'Empreinte (médiathèques de Valence Romans Agglo) : extrait réel de
 * `ListRecords&set=theme:gares-et-chemins-de-fer`, dont les textes sont de l'UTF-8 encodé deux fois.
 */
class OaiPmhReaderTest extends TestCase
{
    private const DATASET = [
        'image_template' => 'https://www.lempreinte.valenceromansagglo.fr/media/docs/1000/{id}/{id}_R.jpg',
        'thumb_template' => 'https://www.lempreinte.valenceromansagglo.fr/media/docs/340/{id}/{id}_R.jpg',
    ];

    private static function xml(string $file): string
    {
        return (string) file_get_contents(__DIR__.'/../../../Fixtures/OpenData/'.$file);
    }

    public function test_records_become_flat_rows_with_composed_image_addresses_and_the_resumption_token(): void
    {
        [$rows, $token] = OaiPmhReader::parse(self::xml('lempreinte-oai-extrait.xml'), self::DATASET);

        $this->assertCount(4, $rows);
        $this->assertSame('1795466800988', $token);
        $row = $rows[0];
        $this->assertSame('B263626101_CP1560', $row['id']);
        $this->assertSame('Valence - Avenue de la Gare', $row['title']);
        $this->assertSame('1908', $row['date']);
        $this->assertSame('Domaine Public', $row['rights']);
        $this->assertSame('Médiathèque Publique et Universitaire de Valence', $row['repository']);
        $this->assertSame('Valence', $row['commune']);
        $this->assertSame('https://www.lempreinte.valenceromansagglo.fr/media/docs/1000/B263626101_CP1560/B263626101_CP1560_R.jpg', $row['image']);
        $this->assertSame('https://www.lempreinte.valenceromansagglo.fr/media/docs/340/B263626101_CP1560/B263626101_CP1560_R.jpg', $row['thumb']);
        $this->assertSame('https://www.lempreinte.valenceromansagglo.fr/valence-avenue-de-la-gare,B263626101_CP1560.htm', $row['link']);
    }

    public function test_the_last_page_has_no_token_and_a_double_encoded_text_is_repaired(): void
    {
        [$rows, $token] = OaiPmhReader::parse(self::xml('lempreinte-oai-extrait-fin.xml'), self::DATASET);

        $this->assertCount(1, $rows);
        $this->assertNull($token);
        $this->assertSame('Médiathèque Publique et Universitaire de Valence', $rows[0]['repository']);
        $this->assertSame('Médiathèque', OaiPmhReader::repair('MÃ©diathÃ¨que'));
        $this->assertSame('Médiathèque', OaiPmhReader::repair('Médiathèque'));
        $this->assertSame('Ã seul', OaiPmhReader::repair('Ã seul'));
    }

    public function test_an_empty_set_is_not_an_error_but_a_protocol_error_is(): void
    {
        $empty = '<?xml version="1.0"?><OAI-PMH xmlns="http://www.openarchives.org/OAI/2.0/"><error code="noRecordsMatch"/></OAI-PMH>';
        $this->assertSame([[], null], OaiPmhReader::parse($empty, self::DATASET));

        $this->expectException(RuntimeException::class);
        OaiPmhReader::parse('<?xml version="1.0"?><OAI-PMH xmlns="http://www.openarchives.org/OAI/2.0/"><error code="badArgument"/></OAI-PMH>', self::DATASET);
    }
}

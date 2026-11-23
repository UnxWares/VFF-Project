<?php

namespace Tests\Unit\Bibliography;

use App\Services\Bibliography\BibliographyRecord;
use App\Services\Bibliography\BnfBookParser;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Lecture des notices de livres de l'API SRU du catalogue général de la BnF (extrait réel : « Causses »).
 */
class BnfBookParserTest extends TestCase
{
    /** @return array{total: int, records: list<BibliographyRecord>} */
    private function parsed(): array
    {
        return (new BnfBookParser)->parse((string) file_get_contents(__DIR__.'/../../Fixtures/Bibliography/bnf-causses.xml'));
    }

    public function test_it_reads_the_notices_of_the_extract(): void
    {
        $parsed = $this->parsed();

        $this->assertSame(8, $parsed['total']);
        $this->assertGreaterThanOrEqual(3, count($parsed['records']));
    }

    public function test_it_reads_authors_title_imprint_year_and_isbn(): void
    {
        $first = $this->parsed()['records'][0];

        $this->assertSame('book', $first->kind);
        $this->assertStringStartsWith("L'épopée de la ligne des Causses : histoire du chemin de fer de Graissessac à Béziers", $first->title);
        $this->assertSame(['Jean-Michel Biscarat', 'Gilles Degenève', 'Philippe Marassé'], $first->authors);
        $this->assertSame('Les éditions du Cabri', $first->publisher);
        $this->assertSame('Breil-sur-Roya', $first->place);
        $this->assertSame(2025, $first->year);
        $this->assertSame('9782914603928', $first->isbn);
        $this->assertSame('bnf', $first->provider);
        $this->assertMatchesRegularExpression('#^https://catalogue\.bnf\.fr/ark:/12148/cb[a-z0-9]+$#', (string) $first->url);
        $this->assertSame('isbn:9782914603928', $first->dedupKey());
    }

    public function test_it_adds_the_volume_to_the_title_and_cleans_the_imprint(): void
    {
        $byTitle = [];
        foreach ($this->parsed()['records'] as $r) {
            $byTitle[$r->title] = $r;
        }

        $this->assertArrayHasKey('Entre Causses & Cévennes. Tome 1', $byTitle);
        $r = $byTitle['Entre Causses & Cévennes. Tome 1'];
        $this->assertSame('Montpellier', $r->place, 'les crochets de la notice sont retirés');
        $this->assertSame('Association Marco Polo créations', $r->publisher);
        $this->assertSame(2010, $r->year);
        $this->assertSame(['Luigi De Zarlo'], $r->authors);
    }

    public function test_it_skips_a_notice_without_a_type_in_its_leader(): void
    {
        $titles = array_map(fn (BibliographyRecord $r): string => $r->title, $this->parsed()['records']);

        $this->assertNotContains('Les chemins de fer des Causses : du Quercy et du Rouergue : bénédiction ou malédiction ?', $titles);
    }

    public function test_it_refuses_a_sru_diagnostic_and_a_page_that_is_not_sru(): void
    {
        $this->expectException(RuntimeException::class);
        (new BnfBookParser)->parse('<html><body>Maintenance</body></html>');
    }
}

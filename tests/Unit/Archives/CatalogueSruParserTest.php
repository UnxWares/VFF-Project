<?php

namespace Tests\Unit\Archives;

use App\Services\Archives\ArchiveRecord;
use App\Services\Archives\CatalogueSruParser;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Lecture des réponses de l'API SRU du catalogue général de la BnF (UNIMARC), sur des réponses réelles
 * enregistrées (tests/Fixtures/Bnf, Licence Ouverte) : seules les métadonnées reprises par VFF sont lues.
 */
class CatalogueSruParserTest extends TestCase
{
    private function fixture(string $name): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2).'/Fixtures/Bnf/'.$name);
    }

    /** Notice de la réponse dont le titre commence ainsi. */
    private function record(string $fixture, string $titleStart): ArchiveRecord
    {
        foreach ((new CatalogueSruParser)->parse($this->fixture($fixture))['records'] as $r) {
            if (str_starts_with($r->title, $titleStart)) {
                return $r;
            }
        }
        $this->fail("Aucune notice « {$titleStart}… » dans {$fixture}");
    }

    public function test_a_printed_monograph_gives_its_arks_title_author_date_and_shelfmark(): void
    {
        $parsed = (new CatalogueSruParser)->parse($this->fixture('sru-angouleme-limoges.xml'));

        $this->assertSame(1, $parsed['total']);
        $this->assertCount(1, $parsed['records']);
        $r = $parsed['records'][0];
        $this->assertSame('ark:/12148/bpt6k1192344q', $r->gallicaArk);
        $this->assertSame('ark:/12148/cb315821610', $r->catalogueArk);
        $this->assertStringStartsWith('Utilité d\'un chemin de fer de Rochefort à Limoges par Angoulême', $r->title);
        $this->assertSame('monograph', $r->docType);
        $this->assertSame(['Villemandy, Alfred de'], $r->authors);
        $this->assertSame(1860, $r->date->year);
        $this->assertSame('year', $r->date->precision);
        $this->assertSame('1860', $r->date->label);
        // Cote de l'original à Tolbiac, pas celle du document numérisé (NUMM-1192344).
        $this->assertSame('V-54984', $r->shelfmark);
        $this->assertSame('2025-06-16', $r->recordUpdatedOn);
    }

    public function test_maps_and_images_are_typed_and_the_digitised_copy_shelfmark_is_skipped(): void
    {
        $this->assertCount(6, (new CatalogueSruParser)->parse($this->fixture('sru-paris-le-havre.xml'))['records']);
        $map = $this->record('sru-paris-le-havre.xml', 'Chemin de fer de Paris au Hâvre');
        $this->assertSame('map', $map->docType);
        $this->assertSame('GE DL 1838-38', $map->shelfmark);
        $this->assertSame(1838, $map->date->year);

        $tram = $this->record('sru-paris-le-havre.xml', '[Tramway n° 17');
        $this->assertSame('image', $tram->docType);
        // Seule cote : celle de l'image numérisée (IFN-…), jamais servie comme cote de l'original.
        $this->assertNull($tram->shelfmark);

        // Sans date dans la notice (zone 100 « u ») : précision inconnue, jamais une année inventée.
        $bridge = $this->record('sru-paris-le-havre.xml', 'Pont sur la ligne');
        $this->assertNull($bridge->date->year);
        $this->assertSame('unknown', $bridge->date->precision);
    }

    public function test_the_match_text_keeps_the_title_complements_but_not_the_statement_of_responsibility(): void
    {
        $examen = $this->record('sru-paris-le-havre.xml', 'Examen des résultats');

        $this->assertStringContainsString('Havre', $examen->matchText);
        $this->assertStringNotContainsString("\u{0098}", $examen->title);
    }

    public function test_a_record_without_a_gallica_link_is_ignored(): void
    {
        $xml = str_replace('gallica.bnf.fr/ark:/12148/bpt6k1192344q', 'example.org/ailleurs', $this->fixture('sru-angouleme-limoges.xml'));

        $this->assertSame([], (new CatalogueSruParser)->parse($xml)['records']);
    }

    public function test_an_sru_diagnostic_or_broken_xml_is_an_error_never_an_empty_answer(): void
    {
        $diagnostic = '<?xml version="1.0"?><srw:searchRetrieveResponse xmlns:srw="http://www.loc.gov/zing/srw/">'
            .'<srw:numberOfRecords>0</srw:numberOfRecords><srw:diagnostics><diag:diagnostic xmlns:diag="http://www.loc.gov/zing/srw/diagnostic/">'
            .'<diag:uri>info:srw/diagnostic/1/10</diag:uri><diag:message>Query syntax error</diag:message></diag:diagnostic></srw:diagnostics>'
            .'</srw:searchRetrieveResponse>';

        try {
            (new CatalogueSruParser)->parse($diagnostic);
            $this->fail('Un diagnostic SRU doit lever une exception.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Query syntax error', $e->getMessage());
        }

        $this->expectException(RuntimeException::class);
        (new CatalogueSruParser)->parse('<html>Attention Required! | Cloudflare');
    }
}

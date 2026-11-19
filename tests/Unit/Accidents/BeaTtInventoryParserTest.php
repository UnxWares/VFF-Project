<?php

namespace Tests\Unit\Accidents;

use App\Services\Accidents\BeaTtAccidentCollector;
use App\Services\Accidents\BeaTtInventoryParser;
use PHPUnit\Framework\TestCase;

/**
 * Bilan des enquêtes du BEA-TT converti par pdftohtml : rangées reconstituées par ordonnée,
 * intitulé séparé de la date, exposant collé, lien absent ou renvoyé à la ligne suivante.
 * Extrait réduit de la vraie conversion (bilan au 09/11/2026).
 */
class BeaTtInventoryParserTest extends TestCase
{
    private const XML = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <!DOCTYPE pdf2xml SYSTEM "pdf2xml.dtd">
        <pdf2xml producer="poppler" version="26.01.0">
        <page number="1" position="absolute" top="0" left="0" height="892" width="1262">
        <text top="83" left="851" width="28" height="15" font="1"><b>Mode</b></text>
        <text top="130" left="298" width="220" height="18" font="2"><b>TOTAL ENQUETES FERROVIAIRES</b></text>
        <text top="164" left="35" width="438" height="15" font="3">11/09/2026 Déraillement d’un train express régional à proximité du PN n° 33 à Cléon (76)</text>
        <text top="164" left="726" width="100" height="15" font="5"><a href="https://www.bea-tt.developpement-durable.gouv.fr/IMG/pdf/cleon.pdf">fiche de présentation</a></text>
        <text top="164" left="863" width="7" height="15" font="3">F</text>
        <text top="164" left="918" width="6" height="15" font="3">0</text>
        <text top="190" left="35" width="54" height="15" font="3">07/01/2026</text>
        <text top="190" left="101" width="456" height="15" font="3">« Quasi réception » d’un train de voyageurs sur une voie déjà occupée à Cambo-les-Bains (64)</text>
        <text top="190" left="726" width="100" height="15" font="5"><a href="https://www.bea-tt.developpement-durable.gouv.fr/IMG/pdf/cambo.pdf">fiche de présentation</a></text>
        <text top="190" left="863" width="7" height="15" font="3">F</text>
        <text top="190" left="918" width="6" height="15" font="3">0</text>
        <text top="635" left="35" width="271" height="15" font="3">08/11/2021 Heurt d’un piéton par un train à Héricy (77)</text>
        <text top="635" left="759" width="34" height="15" font="3">rapport</text>
        <text top="635" left="863" width="7" height="15" font="3">F</text>
        <text top="636" left="918" width="6" height="15" font="3">0</text>
        </page>
        <page number="2" position="absolute" top="0" left="0" height="892" width="1262">
        <text top="554" left="36" width="54" height="15" font="3">23/08/2015</text>
        <text top="554" left="103" width="366" height="15" font="3">Déraillement d’une rame de tramway suite à la collision avec un VL à Lyon 8</text>
        <text top="555" left="470" width="3" height="9" font="9">e</text>
        <text top="554" left="473" width="22" height="15" font="3"> (69)</text>
        <text top="554" left="862" width="9" height="15" font="3">TG</text>
        <text top="554" left="918" width="6" height="15" font="3">0</text>
        <text top="567" left="760" width="34" height="15" font="5"><a href="https://www.bea-tt.developpement-durable.gouv.fr/lyon-69-23-08-2015-collision-entre-un-tramway-et-r230.html">rapport</a></text>
        <text top="600" left="36" width="300" height="15" font="3">12/07/2013 Déraillement d’un train Intercités à Brétigny-sur-Orge (91)</text>
        <text top="600" left="759" width="34" height="15" font="5"><a href="https://www.bea-tt.developpement-durable.gouv.fr/IMG/pdf/RapportBEATT-2013-007.pdf">rapport</a></text>
        <text top="600" left="863" width="7" height="15" font="3">F</text>
        <text top="600" left="919" width="6" height="15" font="3">7</text>
        <text top="600" left="971" width="51" height="15" font="3">septembre 2015</text>
        <text top="640" left="36" width="300" height="15" font="3">24/02/2016 Collision entre un PL et un VUL sur la RN 79 à Montbeugny (03)</text>
        <text top="640" left="862" width="8" height="15" font="3">R</text>
        <text top="640" left="916" width="12" height="15" font="6">12</text>
        </page>
        </pdf2xml>
        XML;

    public function test_rows_are_rebuilt_from_positioned_text(): void
    {
        $rows = (new BeaTtInventoryParser)->parse(self::XML);

        $this->assertCount(6, $rows);
        $this->assertSame([
            'date' => '2026-09-11',
            'title' => 'Déraillement d’un train express régional à proximité du PN n° 33 à Cléon (76)',
            'mode' => 'F', 'deaths' => 0, 'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/IMG/pdf/cleon.pdf',
        ], $rows[0]);
        // Date et intitulé dans deux morceaux.
        $this->assertSame('2026-01-07', $rows[1]['date']);
        $this->assertStringStartsWith('« Quasi réception »', $rows[1]['title']);
        // « rapport » sans lien : ni intitulé ni adresse.
        $this->assertSame('Heurt d’un piéton par un train à Héricy (77)', $rows[2]['title']);
        $this->assertNull($rows[2]['url']);
        $this->assertSame(0, $rows[2]['deaths']);
        // Exposant collé, lien renvoyé sur la ligne suivante.
        $this->assertSame('Déraillement d’une rame de tramway suite à la collision avec un VL à Lyon 8e (69)', $rows[3]['title']);
        $this->assertSame('TG', $rows[3]['mode']);
        $this->assertStringContainsString('lyon-69-23-08-2015', (string) $rows[3]['url']);
        $this->assertSame(7, $rows[4]['deaths']);
        $this->assertSame('R', $rows[5]['mode']);
    }

    public function test_unreadable_input_gives_no_row(): void
    {
        $parser = new BeaTtInventoryParser;

        $this->assertSame([], $parser->parse(''));
        $this->assertSame([], $parser->parse('<pdf2xml><page><text top="1" left="1">pas une date</text></page></pdf2xml>'));
    }

    public function test_commune_and_department_are_read_from_report_address(): void
    {
        $this->assertSame(['solaize', '69'], BeaTtAccidentCollector::placeFromUrl('https://www.bea-tt.developpement-durable.gouv.fr/solaize-69-13-03-2017-deraillement-d-un-train-de-r259.html'));
        $this->assertSame(['saint florentin', '89'], BeaTtAccidentCollector::placeFromUrl('https://x/saint-florentin-89-17-01-2024-deraillement-d-un-r477.html'));
        $this->assertSame(['ajaccio', '2A'], BeaTtAccidentCollector::placeFromUrl('https://x/ajaccio-2a-01-02-2020-x-r1.html'));
        $this->assertNull(BeaTtAccidentCollector::placeFromUrl('https://x/IMG/pdf/RapportBEATT-2013-007.pdf'));
        $this->assertNull(BeaTtAccidentCollector::placeFromUrl(null));
    }
}

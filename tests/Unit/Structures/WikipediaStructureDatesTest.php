<?php

namespace Tests\Unit\Structures;

use App\Services\Structures\WikipediaStructureDates;
use PHPUnit\Framework\TestCase;

/** Dates des infobox Pont et Tunnel de Wikipédia : formes écrites, modèles, liens, références. */
class WikipediaStructureDatesTest extends TestCase
{
    public function test_bridge_infobox_with_a_construction_period_and_a_date_template(): void
    {
        $dates = WikipediaStructureDates::fromWikitext(<<<'WIKI'
            {{Infobox Pont
             | longueur          = 564.69
             | dates             = 1880-1884
             | inauguration      =
             | mise en service   = {{Date|27|mai|1888|dans les chemins de fer}}
            }}
            WIKI);

        $this->assertSame(['date' => '1884-01-01', 'precision' => 'year'], $dates['built'], 'achèvement : dernière année');
        $this->assertSame(['date' => '1888-05-27', 'precision' => 'day'], $dates['opened']);
        $this->assertNull($dates['closed']);
    }

    public function test_links_references_and_fallback_fields(): void
    {
        $morlaix = WikipediaStructureDates::fromWikitext("| mise en service = [[26 avril dans les chemins de fer|26 avril]] [[1865 dans les chemins de fer|1865]]\n");
        $this->assertSame(['date' => '1865-04-26', 'precision' => 'day'], $morlaix['opened']);

        $tunnel = WikipediaStructureDates::fromWikitext("| début = \n| fin = \n| ouverture = 1857 <ref>http://example.org/1912.pdf</ref>\n| fermeture = juin 1970\n");
        $this->assertNull($tunnel['built']);
        $this->assertSame(['date' => '1857-01-01', 'precision' => 'year'], $tunnel['opened'], 'la référence ne compte pas');
        $this->assertSame(['date' => '1970-06-01', 'precision' => 'month'], $tunnel['closed']);

        $this->assertSame(['date' => '1902-10-01', 'precision' => 'day'], WikipediaStructureDates::date('1er octobre 1902'));
        $this->assertNull(WikipediaStructureDates::date('XIXe siècle'));
        $this->assertSame(['date' => '1896-01-01', 'precision' => 'year'], WikipediaStructureDates::fromWikitext("| fin = 1896\n")['built']);
    }
}

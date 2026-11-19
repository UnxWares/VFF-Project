<?php

namespace Tests\Unit\Accidents;

use App\Services\Accidents\WikipediaEditionListReader;
use PHPUnit\Framework\TestCase;

/**
 * Lecture des listes d'accidents des Wikipédia en anglais et en allemand : date, lieux nommés comme lieu de
 * l'accident, référence propre à l'entrée. Aucun texte n'est gardé.
 */
class WikipediaEditionListReaderTest extends TestCase
{
    private WikipediaEditionListReader $reader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reader = new WikipediaEditionListReader;
    }

    public function test_english_bullet_takes_the_year_from_the_heading_and_keeps_places_introduced_as_places(): void
    {
        $text = "== 1951 ==\n"
            ."* August 24 – ''France'' – At [[Sanry-sur-Nied]], a [[Frankfurt]] to [[Paris]] express is stopped by signals.{{sfn|Semmens|1994|p=28}}\n"
            ."* [[File:Photo.jpg|thumb|Une légende avec [[Lien]]]] July 3 – ''France'' – Near [[Châteaubourg, Ardèche|Châteaubourg]], a collision.\n"
            ."* July 4 – ''France'' – Collision between [[Mauves]] and [[Saint-Péray]] kills 35.\n"
            ."* July 5 – ''Germany'' – At [[Berlin]], not in France.\n";

        $entries = $this->reader->readEnglish($text);

        $this->assertCount(3, $entries);
        $this->assertSame('1951-08-24', $entries[0]['date']);
        $this->assertSame([['name' => 'Sanry-sur-Nied', 'qualifier' => null]], $entries[0]['places'], '« from X to Y » ne désigne pas un lieu');
        $this->assertTrue($entries[0]['referenced']);
        $this->assertSame(['Semmens 1994'], $entries[0]['references']);
        $this->assertSame('1951', $entries[0]['anchor']);
        $this->assertSame([['name' => 'Châteaubourg', 'qualifier' => 'Ardèche'], ['name' => 'Châteaubourg', 'qualifier' => 'Ardèche']], $entries[1]['places']);
        $this->assertFalse($entries[1]['referenced']);
        $this->assertSame([], $entries[2]['places'], '« between X and Y » : entre deux gares, aucun lieu');
    }

    public function test_english_bullets_before_a_year_heading_are_ignored(): void
    {
        $this->assertSame([], $this->reader->readEnglish("* August 24 – ''France'' – At [[Sanry-sur-Nied]], a collision.\n"));
    }

    public function test_english_table_row_reads_the_location_column_and_its_reference(): void
    {
        $text = "{|\n!Accident\n! Date\n!Location\n!Killed\n!Injured\n!Description\n!Ref.\n|-\n"
            ."|[[Annot derailment]]\n|8 February 2014\n|[[Annot]], [[Alpes-de-Haute-Provence]]\n|2\n|20\n|A rock hit a train.\n|<ref>{{Cite news|url=https://www.bbc.com/news/x}}</ref>\n|-\n"
            ."|[[Autre]]\n|1 March 2015\n|Between [[Annot]] and [[Saint-Benoît, Alpes-de-Haute-Provence]]\n|0\n|1\n|Texte.\n|\n|}\n";

        $entries = $this->reader->readEnglishTable($text);

        $this->assertCount(2, $entries);
        $this->assertSame('2014-02-08', $entries[0]['date']);
        $this->assertSame('Annot', $entries[0]['places'][0]['name']);
        $this->assertTrue($entries[0]['referenced']);
        $this->assertSame(['bbc.com'], $entries[0]['references']);
        $this->assertSame([], $entries[1]['places'], 'cellule « Between » : aucun lieu');
        $this->assertFalse($entries[1]['referenced']);
    }

    public function test_german_bullet_reads_the_leading_place_and_the_reference(): void
    {
        $text = "== 1914–1918 ==\n"
            ."* Épinal ({{FRA-1871}}) – Zusammenstoß: Am ''23. August 1914'' kollidierten zwei Züge.<ref>Quelle</ref>\n"
            ."* [[Sarry (Marne)|Sarry]] ({{FRA-1871}}) – Absturz nach Brückensprengung: ''17. September 1914'' – Ein Zug stürzte.\n"
            ."* Lothiers (?) ({{FRA-1871}}) – Zusammenstoß: ''7. Dezember 1918'' – Ein Zug.\n"
            ."* Berlin ({{DEU-1871}}) – Unfall: ''1. Mai 1900'' – nicht in Frankreich.\n";

        $entries = $this->reader->readGerman($text);

        $this->assertCount(3, $entries);
        $this->assertSame('1914-08-23', $entries[0]['date']);
        $this->assertSame([['name' => 'Épinal', 'qualifier' => null]], $entries[0]['places']);
        $this->assertTrue($entries[0]['referenced']);
        $this->assertSame('1914–1918', $entries[0]['anchor']);
        $this->assertSame('Sarry', $entries[1]['places'][0]['name']);
        $this->assertFalse($entries[1]['referenced']);
        $this->assertSame([['name' => 'Lothiers', 'qualifier' => null]], $entries[2]['places']);
    }

    public function test_impossible_dates_are_dropped(): void
    {
        $this->assertSame([], $this->reader->readEnglish("== 1951 ==\n* February 30 – ''France'' – At [[Millas]], impossible.\n"));
    }
}

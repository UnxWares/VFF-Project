<?php

namespace Tests\Unit\Accidents;

use App\Services\Accidents\WikipediaEditionArticleReader;
use PHPUnit\Framework\TestCase;

/**
 * Lecture des articles dédiés à un accident dans les Wikipédia en anglais et en allemand (extraits réels de
 * l'introduction) : date, lieux, références propres. Aucun texte n'est gardé.
 */
class WikipediaEditionArticleReaderTest extends TestCase
{
    private WikipediaEditionArticleReader $reader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reader = new WikipediaEditionArticleReader;
    }

    public function test_english_infobox_with_start_date_template_and_linked_location(): void
    {
        $text = "{{Short description|1846 railway incident in France}}\n{{Infobox rail accident\n | image       = Catastrophe de Fampoux.jpg\n"
            ." | date        = {{start date|1846|07|08}}\n | location    = Near the viaduct over the [[Scarpe (river)|Scarpe]], [[Fampoux]], Pas-de-Calais\n"
            ." | country     = France\n | deaths      = 14\n}}\n\nThe '''Fampoux rail accident''' took place in northern [[France]].<ref>Livre</ref>\n";

        $read = $this->reader->readEnglish($text);

        $this->assertSame('1846-07-08', $read['date']);
        $this->assertSame([['name' => 'Scarpe', 'qualifier' => 'river'], ['name' => 'Fampoux', 'qualifier' => null]], $read['places']);
        $this->assertTrue($read['referenced']);
    }

    public function test_english_infobox_whose_first_parameter_is_the_date(): void
    {
        $read = $this->reader->readEnglish("{{Infobox rail accident\n | date = July 8, 1846\n | location = [[Fampoux]]\n}}\n");

        $this->assertSame('1846-07-08', $read['date']);
        $this->assertSame([['name' => 'Fampoux', 'qualifier' => null]], $read['places']);
    }

    public function test_english_infobox_between_two_places_names_no_place(): void
    {
        $text = "{{Infobox public transit accident\n| name              = Annot derailment\n<!-- Details        -->\n| date              = 8 February 2014\n"
            ."| time              = \n| location          = Between [[Annot]] and [[Saint-Benoît, Alpes-de-Haute-Provence]]\n| country           = France\n}}\n\nText.\n";

        $read = $this->reader->readEnglish($text);

        $this->assertSame('2014-02-08', $read['date']);
        $this->assertSame([], $read['places']);
        $this->assertFalse($read['referenced']);
    }

    public function test_english_article_without_a_day_date_or_infobox_gives_nothing(): void
    {
        $this->assertNull($this->reader->readEnglish("{{Infobox rail accident\n | date = 1846\n | location = [[Fampoux]]\n}}\n"));
        $this->assertNull($this->reader->readEnglish("'''Someone''' was a person.\n"));
    }

    public function test_german_lead_gives_the_date_and_the_place_after_bei(): void
    {
        $text = "Bei dem '''Eisenbahnunfall von Sarry''' stürzte am 17. September 1914 bei [[Sarry (Marne)|Sarry]], [[Frankreich]], ein [[Krankentransportzug|Lazarettzug]] von einer [[Brücke|Eisenbahnbrücke]] über die [[Marne (Fluss)|Marne]].<ref>Quelle</ref>\n";

        $read = $this->reader->readGerman('Eisenbahnunfall von Sarry', $text);

        $this->assertSame('1914-09-17', $read['date']);
        $this->assertSame([['name' => 'Sarry', 'qualifier' => null], ['name' => 'Sarry', 'qualifier' => 'Marne']], $read['places']);
        $this->assertTrue($read['referenced']);
    }

    public function test_german_lead_skips_the_image_and_reads_the_date_after_ereignete_sich(): void
    {
        $text = "[[Datei:Catastrophe de Lagny.jpg|mini|hochkant=1.2|Die Lokomotive [[Est 241 und État 241|Est 241.017]] des aufgefahrenen Zuges]]\n\n"
            ."Der '''Eisenbahnunfall von Lagny''' ereignete sich am 23. Dezember 1933, als ein [[Schnellzug]] von [[Paris]] nach [[Straßburg]] auf einen anderen auffuhr.\n";

        $read = $this->reader->readGerman('Eisenbahnunfall von Lagny', $text);

        $this->assertSame('1933-12-23', $read['date']);
        $this->assertSame([['name' => 'Lagny', 'qualifier' => null]], $read['places'], 'les villes du trajet ne sont pas des lieux');
        $this->assertFalse($read['referenced']);
    }

    public function test_german_article_without_a_day_date_gives_nothing(): void
    {
        $this->assertNull($this->reader->readGerman('Eisenbahnunfall von X', "Der '''Eisenbahnunfall von X''' ereignete sich im Jahr 1900.\n"));
    }
}

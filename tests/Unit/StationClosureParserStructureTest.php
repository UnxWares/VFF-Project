<?php

namespace Tests\Unit;

use App\Services\StationClosureParser;
use App\Services\WikipediaLineParser;
use PHPUnit\Framework\TestCase;

/**
 * StationClosureParser : doublons, infobox retirée du texte (accolades imbriquées, avant et après), événements de
 * même date pour des gares différentes.
 */
class StationClosureParserStructureTest extends TestCase
{
    private function parser(): StationClosureParser
    {
        return new StationClosureParser(new WikipediaLineParser);
    }

    /** @param  list<array<string, mixed>>  $events  @return list<string> */
    private function brief(array $events): array
    {
        return array_map(fn ($e) => "{$e['type']} {$e['date']} {$e['precision']} {$e['origin']}", $events);
    }

    public function test_the_same_sentence_twice_gives_one_event(): void
    {
        $events = $this->parser()->fromStationArticle("La '''gare''' est une gare.\nLa gare est fermée en 1960.\nLa gare est fermée en 1960.", 'Gare de Test');

        $this->assertSame(['closure 1960-01-01 year texte'], $this->brief($events));
    }

    public function test_the_same_closure_from_the_infobox_and_a_sentence_is_kept_once_with_the_infobox_first(): void
    {
        $events = $this->parser()->fromStationArticle("{{Infobox Gare\n | fermeture = 1960\n}}\nLa gare est fermée en 1960.", 'Gare de Test');

        $this->assertSame(['closure 1960-01-01 year infobox'], $this->brief($events));
    }

    public function test_the_same_date_for_two_different_stations_gives_two_events(): void
    {
        $events = $this->parser()->fromLineArticle(
            "== T ==\nLa gare de Caen est fermée en 1960.\nLa gare de Bayeux est fermée en 1960.\nLa gare de Caen est fermée en 1960.",
            'Ligne de Test',
        );

        $this->assertSame([['Caen'], ['Bayeux']], array_column($events, 'names'));
    }

    public function test_the_text_around_the_infobox_is_read_but_not_the_infobox(): void
    {
        $wikitext = "La '''gare''' est une gare.\nLa gare est fermée en 1938.\n"
            ."{{Infobox Gare\n | nom = X\n | note = La gare est fermée en 1999.\n | ouverture = {{date|1 mai 1900}}\n | fermeture = {{date|3 mars 1960}}\n}}\n"
            .'La gare est fermée en 1950.';

        $events = $this->parser()->fromStationArticle($wikitext, 'Gare de Test');

        $this->assertSame(
            ['closure 1960-03-03 day infobox', 'closure 1938-01-01 year texte', 'closure 1950-01-01 year texte'],
            $this->brief($events),
        );
    }

    public function test_an_infobox_never_closed_leaves_the_text_as_is(): void
    {
        $events = $this->parser()->fromStationArticle("La '''gare''' est une gare.\n{{Infobox Gare\n | fermeture = 1960\nLa gare est fermée en 1950.", 'Gare de Test');

        $this->assertSame(['closure 1960-01-01 year infobox', 'closure 1950-01-01 year texte'], $this->brief($events));
    }
}

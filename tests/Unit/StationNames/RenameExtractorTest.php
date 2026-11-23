<?php

namespace Tests\Unit\StationNames;

use App\Services\StationNames\RenameExtractor;
use App\Services\StationNames\StationNameMerger;
use App\Services\StationNames\StationNameTimeline;
use App\Services\StationNames\WikidataNames;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RenameExtractorTest extends TestCase
{
    public function test_a_quoted_rename_with_the_initial_name_gives_both_names_and_the_year(): void
    {
        $events = RenameExtractor::extract("Elle porte d'abord le nom de « La Glacière-Gentilly » avant d'être renommée « Parc de Montsouris » en [[1900]].");

        $this->assertCount(1, $events);
        $this->assertSame(['La Glacière-Gentilly', 'Parc de Montsouris', 1900], [$events[0]['old'], $events[0]['new'], $events[0]['year']]);
        $this->assertTrue($events[0]['initial']);
    }

    public function test_an_unquoted_name_is_read_after_an_explicit_verb(): void
    {
        $events = RenameExtractor::extract('Le {{date|6|juillet|2008}}, la gare est renommée Ravezies en cohérence avec la station de tramway.');

        $this->assertSame('Ravezies', $events[0]['new']);
        $this->assertSame(['2008-07-06', 'day'], [$events[0]['date'], $events[0]['precision']]);
    }

    public function test_a_succession_of_names_is_read_with_decades(): void
    {
        $events = RenameExtractor::extract("Elle est donc d'abord nommée Grigny, puis Grigny (Essonne) dans les années 1970, puis enfin Grigny-Val-de-Seine en 1974.");

        $this->assertSame(['Grigny', 'Grigny (Essonne)', 1970, 'decade'], [$events[0]['old'], $events[0]['new'], $events[0]['year'], $events[0]['precision']]);
        $this->assertSame(['Grigny (Essonne)', 'Grigny-Val-de-Seine', 1974], [$events[1]['old'], $events[1]['new'], $events[1]['year']]);
    }

    public function test_a_period_gives_the_end_of_a_german_name(): void
    {
        $events = RenameExtractor::extract("Après l'annexion, la Reichsbahn gère la gare, renommée « Gunderchingen », pendant la Seconde Guerre mondiale, du 1er juillet 1940 jusqu'à la Libération (en 1944 \u{2013} 1945).");

        $this->assertSame(['Gunderchingen', 1944], [$events[0]['new'], $events[0]['to_year']]);
        $entry = StationNameTimeline::fromEvents($events, 'Gondrexange', 1850, 'https://fr.wikipedia.org/w/index.php?oldid=1')[0];
        $this->assertSame(['de', 1940, 1944], [$entry['lang'], $entry['from'], $entry['to']]);
    }

    /** @return iterable<string, array{string}> */
    public static function rejected(): iterable
    {
        yield 'sans année' => ['La gare est renommée « Ravezies » par la suite.'];
        yield 'rue' => ['La rue de la gare est renommée « Avenue Foch » en 1950.'];
        yield 'commune' => ['La commune est rebaptisée « Saint-Cyr » en 1920 et la gare aussi.'];
        yield 'bâtiment' => ["Depuis 1996, l'ancien bâtiment voyageur a été renommé « La Gare »."];
        yield 'vœu' => ['En 1878, le conseil adopte le vœu de renommer la station « Les Mazes » en « Les-Mazes-le-Crès ».'];
        yield 'compagnie' => ['La compagnie est renommée « Lyon à la Méditerranée » en 1852.'];
        yield 'homonyme' => ["Il ne faut pas la confondre avec l'ancienne gare La Châtaigneraie, renommée en La Celle en 1983."];
        yield 'sujet inconnu' => ['Baptisé « La Gare », il est renommé « Andia » en 2021.'];
    }

    #[DataProvider('rejected')]
    public function test_what_is_not_an_explicit_station_rename_is_left_out(string $text): void
    {
        $this->assertSame([], RenameExtractor::extract($text));
    }

    public function test_the_timeline_writes_neither_the_current_name_nor_a_spelling_variant(): void
    {
        $events = RenameExtractor::extract('Rebaptisée « gare de Saint-Étienne-Châteaucreux » en 1900, elle est renommée « Gare de Châteaucreux » en 1950.');
        $entries = array_filter(StationNameTimeline::fromEvents($events, 'St-Étienne-Châteaucreux', null, 'u'), fn ($e) => ! isset($e['renamed']));

        $this->assertSame(['Châteaucreux'], array_column($entries, 'name'));
    }

    public function test_the_initial_name_starts_at_the_opening_year(): void
    {
        $events = RenameExtractor::extract("Elle est d'abord baptisée « Orléans-Ceinture » avant d'être renommée « Masséna » en 1970.");
        $entries = array_values(array_filter(StationNameTimeline::fromEvents($events, 'Masséna', 1867, 'u'), fn ($e) => ! isset($e['renamed'])));

        $this->assertSame([['Orléans-Ceinture', 1867, 1970]], array_map(fn ($e) => [$e['name'], $e['from'], $e['to']], $entries));
    }

    public function test_wikidata_names_keep_their_dates_and_skip_undated_spellings_of_the_current_name(): void
    {
        $claims = ['P1448' => [
            ['rank' => 'normal', 'mainsnak' => ['datavalue' => ['value' => ['text' => 'Berlin', 'language' => 'fr']]],
                'qualifiers' => ['P580' => [['datavalue' => ['value' => ['time' => '+1900-00-00T00:00:00Z', 'precision' => 9]]]], 'P582' => [['datavalue' => ['value' => ['time' => '+1914-08-04T00:00:00Z', 'precision' => 11]]]]]],
            ['rank' => 'normal', 'mainsnak' => ['datavalue' => ['value' => ['text' => "Gare de l'Est", 'language' => 'fr']]]],
            ['rank' => 'deprecated', 'mainsnak' => ['datavalue' => ['value' => ['text' => 'Faux', 'language' => 'fr']]]],
            ['rank' => 'normal', 'mainsnak' => ['datavalue' => ['value' => ['text' => 'Ost', 'language' => 'en']]]],
        ]];
        $names = WikidataNames::parse('Q1', $claims, 'Est');

        $this->assertSame([['Berlin', 1900, 1914, '1914-08-04']], array_map(fn ($n) => [$n['name'], $n['from'], $n['to'], $n['to_date']], $names));
    }

    public function test_the_merge_replaces_only_its_own_sources_and_never_doubles_a_period(): void
    {
        $existing = [['name' => 'Nancy', 'source' => 'sncf_voyageurs'], ['name' => 'Ancien', 'from' => 1900, 'to' => 1950, 'source' => 'wikipedia_history']];
        $dated = ['name' => 'Chevaleret', 'from' => 1900, 'to' => 1919, 'source' => 'wikipedia_history'];
        $same = ['name' => 'Chevaleret', 'from' => 1900, 'to' => 1919, 'source' => 'wikidata_names'];
        $undated = ['name' => 'Nancy', 'from' => null, 'to' => null, 'source' => 'wikidata_names'];

        $merged = StationNameMerger::merge($existing, [$dated, $same, $undated], 'Nancy-Ville');

        $this->assertSame(['Nancy', 'Chevaleret'], array_column($merged, 'name'));
        $this->assertSame($merged, StationNameMerger::merge($merged, [$dated, $same, $undated], 'Nancy-Ville'));
    }

    public function test_a_rename_to_the_current_name_is_kept_as_a_dated_name_unless_it_is_the_opening_name(): void
    {
        $events = RenameExtractor::extract('En 1914, la gare est renommée « Cravant - Bazarnes ».');

        $entry = StationNameTimeline::fromEvents($events, 'Cravant - Bazarnes', 1851, 'u')[0];
        $this->assertSame(['Cravant - Bazarnes', 1914, true], [$entry['name'], $entry['from'], $entry['renamed']]);
        $this->assertSame([], StationNameTimeline::fromEvents($events, 'Cravant - Bazarnes', 1914, 'u'));
        $this->assertSame([], StationNameTimeline::fromEvents(RenameExtractor::extract('En 1903, la gare prend le nom de « Aulnay-sous-Bois ».'), 'Aulnay-sous-Bois', null, 'u'));
    }

    public function test_the_former_name_is_read_from_a_sentence_that_does_not_give_the_new_one(): void
    {
        $events = RenameExtractor::extract('Elle portait antérieurement le nom de "Gare de Plourivo-Lézardrieux", le changement de nom étant survenu en 1911.');
        $this->assertSame(['Plourivo-Lézardrieux', '', 1911], [$events[0]['old'], $events[0]['new'], $events[0]['year']]);

        $entry = StationNameTimeline::fromEvents($events, 'Lancerf', null, 'u')[0];
        $this->assertSame(['Plourivo-Lézardrieux', null, 1911], [$entry['name'], $entry['from'], $entry['to']]);
    }

    public function test_an_unquoted_original_name_and_a_name_given_before_the_verb(): void
    {
        $a = RenameExtractor::extract("Une première gare dénommée Conflans-Andrésy fut ouverte le 15 octobre 1877 puis rebaptisée Conflans Fin d'Oise en 1891.");
        $this->assertSame(['Conflans-Andrésy', "Conflans Fin d'Oise", 1891], [$a[0]['old'], $a[0]['new'], $a[0]['year']]);

        $b = RenameExtractor::extract('Une nouvelle gare dite La Garenne-Bezons, qui est renommée La Garenne-Colombes en 1985.');
        $this->assertSame(['La Garenne-Bezons', 'La Garenne-Colombes', 1985], [$b[0]['old'], $b[0]['new'], $b[0]['year']]);
    }
}

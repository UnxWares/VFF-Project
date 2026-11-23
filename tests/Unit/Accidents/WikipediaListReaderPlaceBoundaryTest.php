<?php

namespace Tests\Unit\Accidents;

use App\Services\Accidents\AccidentKindClassifier;
use App\Services\Accidents\AccidentPlaceTable;
use App\Services\Accidents\WikipediaListReader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Cas limites de la lecture des lieux d'une puce de la liste Wikipédia : gare « lieu même » ou seulement voisine,
 * faux lieux (objets, personnes, régions, débuts de titres), lieux de tête, plafond de quatre lieux, entrées hostiles.
 */
class WikipediaListReaderPlaceBoundaryTest extends TestCase
{
    private WikipediaListReader $reader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reader = new WikipediaListReader(new AccidentKindClassifier);
    }

    /** @return array<string, mixed> */
    private function entry(string $text): array
    {
        $entries = $this->reader->read('* {{date|2|juillet|1871}} - '.$text);
        $this->assertCount(1, $entries, $text);

        return $entries[0];
    }

    /** @return iterable<string, array{string}> */
    public static function nearWords(): iterable
    {
        foreach (['à l\'approche de', 'à la sortie de', 'aux abords de', 'aux alentours de', 'aux environs de', 'à proximité de', 'près de',
            'proche de', 'vers', 'entre', 'après', 'avant', 'dans la banlieue de', 'dans le faubourg de', 'dans la zone de', 'à côté de',
            'en aval de', 'en amont de', 'derrière', 'devant', 'à hauteur de', 'à portée de', 'au début de', 'à la fin de', 'au bout de',
            'à la limite de', 'à 300 mètres de', 'à 2 km de', 'à trois kilomètres de', 'à deux lieues de', 'chez le chef de gare de',
            'dans les ateliers de', 'au dépôt de', 'au triage de', 'arrivant à', 'à l\'entrée de', 'à l\'entrée en'] as $phrase) {
            yield $phrase => [$phrase];
        }
    }

    #[DataProvider('nearWords')]
    public function test_a_station_next_to_a_near_word_is_not_the_place(string $phrase): void
    {
        $entry = $this->entry("Un train déraille {$phrase} la [[Gare de Surgères|gare de Surgères]].");

        $this->assertNull($entry['station'], $phrase);
        $this->assertSame('Surgères', $entry['listed']['station']);
        $this->assertTrue($entry['approximate'], $phrase);
    }

    /** @return iterable<string, array{string}> */
    public static function terminalWords(): iterable
    {
        foreach (['venant de', 'provenant de', 'arrivant de', 'partant de', 'parti de', 'quittant', 'allant à', 'reliant', 'desservant',
            'en relation avec', 'à destination de', 'en direction de', 'depuis', 'pour', 'sur la ligne de', 'jusqu\'à', 'se rendant à',
            'rejoignant', 'en route vers'] as $phrase) {
            yield $phrase => [$phrase];
        }
    }

    #[DataProvider('terminalWords')]
    public function test_a_station_that_is_the_origin_or_destination_of_the_train_is_not_the_place(string $phrase): void
    {
        $entry = $this->entry("Un train {$phrase} [[Gare de Surgères|Surgères]] heurte un camion.");

        $this->assertNull($entry['station'], $phrase);
        $this->assertSame('Surgères', $entry['listed']['station']);
    }

    /** @return iterable<string, array{string}> */
    public static function inStationPhrases(): iterable
    {
        foreach (['en', 'à', 'dans', 'dans la', 'à la', 'au', 'en gare de', 'sur le quai de', 'sur les voies de', 'dans la gare de'] as $phrase) {
            yield $phrase => [$phrase];
        }
    }

    #[DataProvider('inStationPhrases')]
    public function test_the_in_station_phrases_make_the_station_the_place(string $phrase): void
    {
        $entry = $this->entry("Un voyageur chute {$phrase} [[Gare de Surgères|Surgères]].");

        $this->assertSame('Surgères', $entry['station'], $phrase);
        $this->assertFalse($entry['approximate']);
    }

    public function test_a_station_without_any_context_word_is_not_the_place(): void
    {
        $entry = $this->entry('Collision. [[Gare de Surgères|Surgères]] est le terminus.');

        $this->assertNull($entry['station']);
        $this->assertSame('Surgères', $entry['listed']['station']);
    }

    public function test_the_first_station_in_station_wins_and_a_later_near_one_stays_listed(): void
    {
        $entry = $this->entry('Collision en [[Gare de Niort|gare de Niort]], un train venant de [[Gare de Surgères|Surgères]] heurte un convoi.');

        $this->assertSame('Niort', $entry['station']);
        $this->assertSame('Niort', $entry['listed']['station']);
        $this->assertFalse($entry['approximate']);
    }

    public function test_a_near_station_first_then_an_in_station_one_keeps_the_second_as_the_place(): void
    {
        $entry = $this->entry('Un train venant de [[Gare de Surgères|Surgères]] déraille en [[Gare de Niort|gare de Niort]].');

        $this->assertSame('Niort', $entry['station']);
        $this->assertSame('Surgères', $entry['listed']['station']);
    }

    public function test_station_name_forms_and_parentheses(): void
    {
        $this->assertSame('Paris-Saint-Lazare', $this->entry('Un choc en [[Gare de Paris-Saint-Lazare|gare]].')['station']);
        $this->assertSame('Dax', $this->entry("Un choc en [[Gare d'Dax (Landes)|gare de Dax]].")['station']);
        $this->assertSame('Orléans', $this->entry("Un choc en [[Gare d'Orléans (Loiret)|gare]].")['station']);
        $this->assertSame('Le Mans', $this->entry('Un choc en [[Gare du Le Mans|gare]].')['station']);
        $this->assertSame('Aubrais', $this->entry('Un choc en [[Gare des Aubrais (Loiret)|gare]].')['station']);
        $this->assertSame('Roche', $this->entry('Un choc en [[Gare du Roche|gare]].')['station']);
        $this->assertNull($this->entry('Un choc en [[Gare Montparnasse|gare]].')['station']);
        $this->assertSame('Dax', $this->entry('Un choc en [[gare de Dax|gare]].')['station']);
    }

    public function test_a_head_station_is_in_station_even_with_a_leading_space_or_colon(): void
    {
        $entry = $this->entry(': [[Gare de Nangis]] : un train déraille.');

        $this->assertSame('Nangis', $entry['station']);
    }

    /** @return iterable<string, array{string}> */
    public static function fakeTitles(): iterable
    {
        foreach (['Citroën 2 CV', 'Renault ADP', 'Route nationale 5', 'Rue de Rivoli', 'Place de la Concorde', 'Avenue Foch', 'Boulevard Haussmann',
            'Liste des accidents', 'Sabotage de 1944', 'Accident de Meudon', 'Ancien tramway de Pau', 'CFD Ouest', 'Régie autonome', 'Voies ferrées du Nord',
            'Corps expéditionnaire', 'Triage et ateliers de Paris', 'Guerre de 1870', 'Déroulement de l\'accident',
            'Organisation Todt', 'Conseil général', 'Armée de l\'air', 'Labourd Atlantique',
            'Alsace', 'Auvergne', 'Espagne', 'France', 'Genève', 'Bruxelles', 'Tar', 'Orb',
            'Port-Bou', 'Monaco - Monte-Carlo', 'Le Siècle', 'Sud Express', 'Pullman', 'Talgo'] as $title) {
            yield $title => [$title];
        }
    }

    #[DataProvider('fakeTitles')]
    public function test_fake_places_are_dropped_from_places_but_kept_in_listed(string $title): void
    {
        $entry = $this->entry("Un [[{$title}]] est en cause à [[Pontmort]], le train déraille.");

        $this->assertSame([['Pontmort', null]], $entry['places'], $title);
        $this->assertContains($title, array_column($entry['listed']['places'], 0), $title);
    }

    /** @return iterable<string, array{string}> */
    public static function droppedEarlyTitles(): iterable
    {
        foreach (['Chemin de fer du Nord', 'Ligne de Paris à Lille', 'LGV Atlantique', 'Gare de Pau', 'Tunnel de Blaisy', 'Pont Neuf', 'TEOZ', 'RER B', 'A 380', '1871',
            'Catégorie:Accident', 'Fichier:Train.jpg', 'Locomotive 232', 'Poids lourd', 'Passage à niveau de X'] as $t) {
            yield $t => [$t];
        }
    }

    /** Un titre refusé dès la lecture des liens n'apparaît ni dans les lieux ni dans les lieux cités. */
    #[DataProvider('droppedEarlyTitles')]
    public function test_titles_that_are_never_places_are_in_neither_list(string $title): void
    {
        $entry = $this->entry("Un [[{$title}|truc]] à [[Pontmort]], le train déraille.");

        $this->assertNotContains($title, array_column($entry['places'], 0));
        $this->assertNotContains($title, array_column($entry['listed']['places'], 0));
    }

    /** Les noms de départements sont lus comme département d'un lieu, pas comme lieux. */
    public function test_a_department_name_is_not_a_place(): void
    {
        $entry = $this->entry('Un train déraille à [[Pontmort]] ([[Marne]]) et près du [[Lot]], du [[Cher]], de l\'[[Yonne]].');

        $this->assertSame([['Pontmort', '51']], array_slice($entry['places'], 0, 1));
        $this->assertNotContains('Marne', array_column($entry['listed']['places'], 0));
        $this->assertNotContains('Lot', array_column($entry['listed']['places'], 0));
    }

    public function test_fake_places_are_recognised_through_case_accents_and_a_trailing_parenthesis(): void
    {
        $entry = $this->entry('Un [[CELLULE (prison)]] et un [[Bale]] et un [[bâle]] et un [[Port Bou]] à [[Pontmort]].');

        $this->assertSame([['Pontmort', null]], $entry['places']);
    }

    public function test_a_real_place_whose_name_starts_like_a_fake_one_is_kept(): void
    {
        // « Rue » (commune de la Somme), « Corpslet » ne sont pas des débuts de titre exclus : seuls « rue » + espace le sont.
        $entry = $this->entry('Un train déraille à [[Rue (Somme)]] puis à [[Corps (Isère)]] et à [[Guerres]].');

        $this->assertEqualsCanonicalizing(['Rue', 'Corps', 'Guerres'], array_column($entry['places'], 0));
    }

    public function test_the_lowercase_and_the_excluded_prefix_titles_are_not_places(): void
    {
        $entry = $this->entry('Un [[serre-frein]], un [[Tunnel de Blaisy]], un [[TGV Atlantique]], le [[12 mai]], le [[SNCF]] à [[Pontmort]].');

        $this->assertSame([['Pontmort', null]], $entry['places']);
    }

    public function test_the_head_place_without_link_is_dropped_when_it_is_a_fake_place(): void
    {
        $entry = $this->entry('Cellule : un détenu est tué par un train à [[Pontmort]].');

        $this->assertSame([['Pontmort', null]], $entry['places']);
    }

    public function test_the_head_place_without_link_needs_a_capital_a_short_head_and_a_known_department(): void
    {
        $this->assertSame([['Meudon', null]], $this->entry('Meudon : un train déraille.')['places']);
        $this->assertSame([], $this->entry('un train déraille : le mécanicien est blessé.')['places']);
        $this->assertSame([], $this->entry(str_repeat('Long', 20).' : un train déraille.')['places']);
        $this->assertSame([], $this->entry('Meudon (Atlantide) : un train déraille.')['places']);
        $this->assertSame([['Meudon', '92']], $this->entry('Meudon (Hauts-de-Seine) : un train déraille.')['places']);
        $this->assertSame([['Œuilly', null]], $this->entry('Œuilly : un train déraille.')['places']);
    }

    public function test_at_most_four_distinct_places_are_kept_and_duplicates_are_merged(): void
    {
        $entry = $this->entry('[[Alençon]], [[Briouze]], [[Carrouges]], [[Domfront]], [[Écouché]], [[Alençon]] puis [[Briouze (Orne)|Briouze]].');

        $this->assertCount(4, $entry['places']);
        $this->assertSame('Alençon', $entry['places'][0][0]);
    }

    public function test_a_station_linked_twice_with_different_contexts_is_read_once(): void
    {
        $entry = $this->entry('Entre [[Gare de Niort|Niort]] et [[Gare de Niort|Niort]], en [[Gare de Niort|gare de Niort]].');

        $this->assertSame('Niort', $entry['station']);
        $this->assertCount(1, $entry['listed']['places']);
    }

    public function test_empty_hostile_and_very_long_inputs_do_not_fail(): void
    {
        $this->assertSame([], $this->reader->read(''));
        $this->assertSame([], $this->reader->read("\n\n"));
        $this->assertSame([], $this->reader->read('* '));
        $this->assertSame([], $this->reader->read('*'));
        $this->assertSame([], $this->reader->read('** {{date|2|mai|1900}} - sous-puce'));
        $this->assertSame([], $this->reader->read('{{date|2|mai|1900}} - sans astérisque'));
        $entry = $this->entry('[[ ]] [[|]] [[Gare de ]] [[Gare de |x]] [[#ancre]] <b>x</b> à [[Pontmort]].');
        $this->assertContains('Pontmort', array_column($entry['places'], 0));
        $long = $this->entry(str_repeat('[[Gare de Niort|Niort]] ', 3000).'en [[Gare de Surgères]].');
        $this->assertSame('Niort', $long['listed']['station']);
        $unicode = $this->entry('En [[Gare de Köln|gare de Köln]], 東京 [[大阪]] 😀 « déraillement ».');
        $this->assertSame('Köln', $unicode['station']);
    }

    public function test_the_approximate_flag_is_set_for_a_weak_or_ranked_place_and_not_for_a_head_place(): void
    {
        $this->assertTrue($this->entry('Un train déraille près de [[Pontmort]].')['approximate']);
        $this->assertTrue($this->entry('Un train déraille vers [[Pontmort]].')['approximate']);
        $this->assertTrue($this->entry('Un train déraille entre [[Pontmort]].')['approximate']);
        $this->assertFalse($this->entry('Un train déraille à [[Pontmort]].')['approximate']);
        $this->assertFalse($this->entry('[[Pontmort]] : un train déraille.')['approximate']);
        $this->assertFalse($this->entry('Un train déraille.')['approximate']);
        $this->assertFalse($this->entry('Un train déraille en [[Gare de Niort|gare de Niort]].')['approximate']);
    }

    public function test_a_commune_after_a_station_context_is_not_a_station(): void
    {
        $entry = $this->entry('Un train déraille en [[Surgères]].');

        $this->assertNull($entry['station']);
        $this->assertSame([['Surgères', null]], $entry['places']);
    }

    public function test_the_place_table_replaces_places_and_clears_the_station(): void
    {
        $entry = $this->entry('Un train déraille en [[Gare de Niort|gare de Niort]].');
        $this->assertSame('Niort', $entry['station']);
        $this->assertNull($entry['place_basis']);
    }

    public function test_a_link_in_the_first_two_characters_is_the_head_of_the_bullet(): void
    {
        // Position 2 : tête de puce, donc le lieu même ; position 3 sans mot de contexte : non.
        $this->assertSame('Niort', $this->entry('Un[[Gare de Niort|Niort]] déraille.')['station']);
        $this->assertNull($this->entry('Un [[Gare de Niort|Niort]] déraille.')['station']);
        $this->assertNull($this->entry('Une [[Gare de Niort|Niort]] déraille.')['station']);
    }

    public function test_the_head_place_is_limited_to_60_characters(): void
    {
        $name = 'Saint-'.str_repeat('a', 54); // 60 caractères
        $this->assertSame([[$name, null]], $this->entry("{$name} : un train déraille.")['places']);
        $this->assertSame([], $this->entry("{$name}a : un train déraille.")['places']);
    }

    public function test_a_destination_introduced_by_a_is_not_the_place(): void
    {
        foreach (['Un train allant à', 'Un train se rendant à', 'Un train se dirigeant vers', 'Un train allant au'] as $head) {
            $entry = $this->entry("{$head} [[Gare de Niort|Niort]] heurte un camion.");
            $this->assertNull($entry['station'], $head);
        }
    }

    public function test_the_rank_of_a_head_link_is_zero_and_the_third_character_is_not_a_head(): void
    {
        // Position 2 : tête de puce (rang 0), avant un lieu rang 1 (« à »).
        $this->assertSame([['Alpha', null], ['Beta', null]], $this->entry('Un[[Alpha]] puis à [[Beta]].')['places']);
        // Position 3 : rang 2, après un lieu rang 1.
        $this->assertSame([['Beta', null], ['Alpha', null]], $this->entry('Un [[Alpha]] puis à [[Beta]].')['places']);
    }

    public function test_a_context_word_far_before_the_link_is_still_read(): void
    {
        // « reliant » est à plus de 24 caractères du lien : la fenêtre lue doit aller jusqu'à 60.
        $entry = $this->entry('Un TGV reliant Dunkerque et Valenciennes par Lille à [[Gare de Paris-Nord|Paris-Nord]] percute un véhicule.');

        $this->assertNull($entry['station']);
        $this->assertSame('Paris-Nord', $entry['listed']['station']);
    }

    public function test_places_differing_only_by_case_or_accent_are_one_place(): void
    {
        $entry = $this->entry('À [[Orléans]] puis à [[Orleans]] puis à [[ORLÉANS]].');

        $this->assertCount(1, $entry['places']);
    }

    public function test_the_first_in_station_station_is_kept_when_two_are_named(): void
    {
        $entry = $this->entry('Collision en [[Gare de Niort|gare de Niort]] puis en [[Gare de Surgères|gare de Surgères]].');

        $this->assertSame('Niort', $entry['station']);
        $this->assertSame('Niort', $entry['listed']['station']);
    }

    public function test_a_neutral_place_is_not_approximate(): void
    {
        $this->assertFalse($this->entry('Collision. [[Pontmort]] est le lieu.')['approximate']);
    }

    /** @return iterable<string, array{string}> */
    public static function nearWithPreposition(): iterable
    {
        foreach (['à l\'arrivée en', 'en arrivant à', 'en entrant en', 'à l\'entrée dans la', 'à l\'entrée en', 'à l\'arrivée à la'] as $p) {
            yield $p => [$p];
        }
    }

    /** Ces débuts se terminent comme « en gare de » : seul le mot d'approche écarte la gare. */
    #[DataProvider('nearWithPreposition')]
    public function test_an_arrival_or_entry_phrase_that_ends_like_in_station_is_still_near(string $phrase): void
    {
        $entry = $this->entry("Un train déraille {$phrase} [[Gare de Niort|gare de Niort]].");

        $this->assertNull($entry['station'], $phrase);
        $this->assertTrue($entry['approximate'], $phrase);
    }

    public function test_the_place_table_wins_over_the_reading_and_clears_the_station(): void
    {
        $table = new AccidentPlaceTable([['date' => '1871-07-02', 'needle' => 'en gare de niort', 'commune' => 'Poitiers', 'department' => '86', 'basis' => 'texte']]);
        $reader = new WikipediaListReader(new AccidentKindClassifier, $table);

        $entry = $reader->read('* {{date|2|juillet|1871}} - Un train déraille en [[Gare de Niort|gare de Niort]].')[0];

        $this->assertNull($entry['station']);
        $this->assertNull($entry['listed']['station']);
        $this->assertSame([['Poitiers', '86']], $entry['places']);
        $this->assertSame([['Poitiers', '86']], $entry['listed']['places']);
        $this->assertSame('texte', $entry['place_basis']);
        $this->assertFalse($entry['approximate']);
    }
}

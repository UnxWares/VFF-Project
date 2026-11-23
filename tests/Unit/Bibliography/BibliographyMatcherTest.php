<?php

namespace Tests\Unit\Bibliography;

use App\Services\Archives\LinePlaceIndex;
use App\Services\Bibliography\BibliographyMatcher;
use App\Services\Bibliography\BibliographyRecord;
use App\Services\Bibliography\LineAliases;
use App\Services\Bibliography\StationNames;
use App\Services\Bibliography\WikitextReferences;
use PHPUnit\Framework\TestCase;

/**
 * Rattachement prudent d'une référence à une ligne ou à une gare : jamais un ouvrage général sur les chemins de fer,
 * jamais un texte officiel, jamais un article de presse tiré d'une note.
 */
class BibliographyMatcherTest extends TestCase
{
    private LinePlaceIndex $index;

    private LineAliases $aliases;

    private BibliographyMatcher $matcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->index = new LinePlaceIndex([
            1 => LinePlaceIndex::linePlaces([
                'id' => 1, 'slug' => 'rfn-722000', 'name' => 'Ligne de Béziers à Neussargues',
                'alt_names' => ['names' => [['name' => 'ligne des Causses']]],
                'stations' => [['name' => 'Béziers', 'city' => 'Béziers', 'pk_m' => 0], ['name' => 'Millau', 'city' => 'Millau', 'pk_m' => 150000],
                    ['name' => 'Neussargues', 'city' => 'Neussargues', 'pk_m' => 300000]],
                'first_year' => 1888, 'open_today' => true,
            ]),
            2 => LinePlaceIndex::linePlaces([
                'id' => 2, 'slug' => 'rfn-669000', 'name' => 'Ligne de Villefranche-Vernet-les-Bains à Latour-de-Carol',
                'alt_names' => ['names' => [['name' => 'Ligne de Cerdagne']]],
                'stations' => [['name' => 'Villefranche-Vernet-les-Bains', 'city' => 'Villefranche-de-Conflent', 'pk_m' => 0], ['name' => 'Mont-Louis', 'city' => 'Mont-Louis', 'pk_m' => 20000],
                    ['name' => 'Latour-de-Carol', 'city' => 'Latour-de-Carol', 'pk_m' => 63000]],
                'first_year' => 1910, 'open_today' => true,
            ]),
            3 => LinePlaceIndex::linePlaces([
                'id' => 3, 'slug' => 'rfn-340000', 'name' => 'Ligne de Paris-St-Lazare au Havre',
                'stations' => [['name' => 'Paris-St-Lazare', 'city' => 'Paris', 'pk_m' => 0], ['name' => 'Rouen-Rive-Droite', 'city' => 'Rouen', 'pk_m' => 140000],
                    ['name' => 'Le Havre', 'city' => 'Le Havre', 'pk_m' => 228000]],
                'first_year' => 1843, 'open_today' => true,
            ]),
        ]);
        $this->aliases = new LineAliases([
            ['id' => 1, 'name' => 'Ligne de Béziers à Neussargues', 'alt_names' => ['names' => [['name' => 'ligne des Causses']]]],
            ['id' => 2, 'name' => 'Ligne de Villefranche-Vernet-les-Bains à Latour-de-Carol', 'alt_names' => ['names' => [['name' => 'Ligne de Cerdagne']]]],
            ['id' => 3, 'name' => 'Ligne de Paris-St-Lazare au Havre', 'alt_names' => null],
        ], $this->index->keys());
        $this->matcher = new BibliographyMatcher;
    }

    private function book(string $title, ?int $year = 2000, string $journal = '', string $kind = 'book'): BibliographyRecord
    {
        return new BibliographyRecord($kind, $title, [], null, null, $year, null, $journal === '' ? null : $journal, 'bnf', 'https://catalogue.bnf.fr/ark:/12148/cb1');
    }

    /** @return list<int> */
    private function bnfLineIds(BibliographyRecord $r): array
    {
        return array_map(fn (array $m): int => $m['line_id'], $this->matcher->bnfLines($r, $this->index, $this->aliases));
    }

    public function test_a_title_naming_the_ends_and_the_railway_is_attached_by_score(): void
    {
        $matches = $this->matcher->bnfLines($this->book('Le chemin de fer de Béziers à Neussargues : histoire d\'une ligne', 1998), $this->index, $this->aliases);

        $this->assertSame([1], array_column($matches, 'line_id'));
        $this->assertSame('title', $matches[0]['basis']);
        $this->assertGreaterThanOrEqual(9, $matches[0]['score']);
    }

    public function test_a_general_book_on_railways_is_attached_to_no_line(): void
    {
        $this->assertSame([], $this->bnfLineIds($this->book('Histoire des chemins de fer en France', 1996)));
        $this->assertSame([], $this->bnfLineIds($this->book('Les chemins de fer français : atlas', 2012)));
    }

    public function test_a_book_naming_a_line_by_its_usage_name_is_attached_by_alias(): void
    {
        $matches = $this->matcher->bnfLines($this->book('L\'épopée de la ligne des Causses', 2025), $this->index, $this->aliases);

        $this->assertSame([1], array_column($matches, 'line_id'));
        $this->assertSame('title_alias', $matches[0]['basis']);
        $this->assertNull($matches[0]['score']);
    }

    public function test_the_usage_name_needs_the_whole_name_and_a_railway_word(): void
    {
        $this->assertSame([], $this->bnfLineIds($this->book('Entre Causses et Cévennes', 2010)), 'pas de mot du rail');
        $this->assertSame([], $this->bnfLineIds($this->book('Les chemins de fer des Causses du Quercy', 2026)), 'le nom entier « ligne des Causses » manque');
        $this->assertSame([2], $this->bnfLineIds($this->book('La ligne de Cerdagne : le train jaune', 1999)));
    }

    public function test_a_route_by_a_place_the_line_does_not_serve_is_another_line(): void
    {
        // Rouen est sur la ligne du Havre, pas Millau ; Béziers à Neussargues par Millau est bien la ligne des Causses.
        $this->assertSame([3], $this->bnfLineIds($this->book('Chemin de fer de Paris au Havre par Rouen', 1850)));
        $this->assertSame([], $this->bnfLineIds($this->book('Chemin de fer de Paris au Havre par Neussargues', 1850)));
        $this->assertSame([1], $this->bnfLineIds($this->book('Chemin de fer de Béziers à Neussargues par Millau', 1890)));
    }

    public function test_a_parliamentary_report_or_a_law_is_not_a_reading(): void
    {
        $this->assertSame([], $this->bnfLineIds($this->book('Rapport fait au nom de la commission : chemin de fer de Béziers à Neussargues', 1881)));
        $this->assertSame([], $this->bnfLineIds($this->book('Projet de loi : chemin de fer de Paris au Havre', 1881)));
    }

    public function test_an_alias_is_ignored_when_it_names_a_known_place_or_is_shared_by_many_lines(): void
    {
        $aliases = new LineAliases([
            ['id' => 1, 'name' => 'Ligne de Paris', 'alt_names' => null],
            ['id' => 1, 'name' => 'Ligne de Neussargues', 'alt_names' => null],
            ['id' => 5, 'name' => 'Ligne du Midi', 'alt_names' => null], ['id' => 6, 'name' => 'Ligne du Midi', 'alt_names' => null], ['id' => 7, 'name' => 'Ligne du Midi', 'alt_names' => null],
        ], $this->index->keys());

        $this->assertSame([], $aliases->for(1), '« Neussargues » est une gare connue : pas un nom d\'usage');
        $this->assertSame([], $aliases->for(5), 'trois lignes portent ce nom');
    }

    public function test_an_article_reference_must_name_the_line_without_naming_foreign_places(): void
    {
        $line = $this->index->line(3);
        $this->assertNotNull($line);
        $bib = WikitextReferences::BIBLIOGRAPHY;

        $ok = $this->matcher->articleLine($this->book('Le chemin de fer de Paris au Havre', 1947), $bib, $line, $this->index, []);
        $this->assertSame('article_bibliography', $ok['basis'] ?? null);
        // Paris - Rouen - Le Havre : trois lieux de la ligne.
        $this->assertNotNull($this->matcher->articleLine($this->book('Paris, Rouen, Le Havre : trois villes, une ligne', 1990), $bib, $line, $this->index, []));
        // Un seul lieu de la ligne et un lieu étranger : l'ouvrage parle d'une autre ligne.
        $this->assertNull($this->matcher->articleLine($this->book('Le chemin de fer de Paris à Neussargues', 1990), $bib, $line, $this->index, []));
        // Aucun lieu : ouvrage général.
        $this->assertNull($this->matcher->articleLine($this->book('Histoire des chemins de fer en France', 1996), $bib, $line, $this->index, []));
    }

    public function test_a_reference_from_a_note_needs_a_railway_word_and_an_article_needs_a_periodical(): void
    {
        $line = $this->index->line(3);
        $this->assertNotNull($line);
        $ref = WikitextReferences::REFERENCE;

        $this->assertNull($this->matcher->articleLine($this->book('Le Havre au XIXe siècle', 1990), $ref, $line, $this->index, []));
        $this->assertNotNull($this->matcher->articleLine($this->book('La ligne du Havre à Paris', 1990), $ref, $line, $this->index, []));
        // Article d'une note : retenu d'une revue ferroviaire, pas d'un quotidien.
        $this->assertNotNull($this->matcher->articleLine($this->book('La ligne du Havre à Paris', 1990, 'La Vie du rail', 'article'), $ref, $line, $this->index, []));
        $this->assertNull($this->matcher->articleLine($this->book('La ligne du Havre à Paris menacée', 2017, 'Ouest-France', 'article'), $ref, $line, $this->index, []));
    }

    public function test_official_texts_cited_by_an_article_are_dropped(): void
    {
        $line = $this->index->line(3);
        $this->assertNotNull($line);

        $this->assertNull($this->matcher->articleLine($this->book('N° 4264 - Décret qui autorise le chemin de fer de Paris au Havre', 1875), WikitextReferences::BIBLIOGRAPHY, $line, $this->index, []));
        $this->assertNull($this->matcher->articleLine($this->book("7880 \u{2014} Loi qui déclare d'utilité publique le chemin de fer du Havre à Paris", 1878), WikitextReferences::REFERENCE, $line, $this->index, []));
        $this->assertNull($this->matcher->articleLine($this->book('Chemin de fer du Havre à Paris', 1878, 'Bulletin des lois de la République française', 'article'), WikitextReferences::BIBLIOGRAPHY, $line, $this->index, []));
    }

    public function test_a_station_reference_must_name_the_station_and_a_shared_name_is_never_attached(): void
    {
        $stations = new StationNames([
            ['id' => 10, 'name' => 'Limoges-Bénédictins', 'city' => 'Limoges'],
            ['id' => 11, 'name' => 'Paris-Austerlitz', 'city' => 'Paris'],
            ['id' => 12, 'name' => 'Saint-Denis', 'city' => 'Saint-Denis'],
            ['id' => 13, 'name' => 'Saint-Denis', 'city' => 'Saint-Denis'],
        ]);
        $bib = WikitextReferences::BIBLIOGRAPHY;

        $this->assertNotNull($this->matcher->articleStation($this->book('La gare de Limoges-Bénédictins', 1999), $bib, 10, $stations));
        $this->assertNotNull($this->matcher->articleStation($this->book('La gare d\'Austerlitz', 1985), $bib, 11, $stations), 'le nom après la commune');
        $this->assertNull($this->matcher->articleStation($this->book('Histoire de Limoges', 1999), $bib, 10, $stations), 'la commune n\'est pas la gare');
        $this->assertNull($this->matcher->articleStation($this->book('La gare de Saint-Denis', 1999), $bib, 12, $stations), 'deux gares portent ce nom');
        // D'une note : un livre sans ISBN (tract, communiqué) n'est pas retenu, un livre à ISBN oui.
        $note = WikitextReferences::REFERENCE;
        $this->assertNull($this->matcher->articleStation($this->book('La gare de Limoges-Bénédictins : communiqué', 2009), $note, 10, $stations));
        $withIsbn = new BibliographyRecord('book', 'La gare de Limoges-Bénédictins', [], null, null, 1999, '9782914603928', null, 'wikipedia', 'https://fr.wikipedia.org/w/index.php?title=X&oldid=1');
        $this->assertNotNull($this->matcher->articleStation($withIsbn, $note, 10, $stations));
        $this->assertSame([10], $this->matcher->bnfStations($this->book('La gare de Limoges-Bénédictins et son quartier', 2001), $stations));
        $this->assertSame([], $this->matcher->bnfStations($this->book('Le quartier de Limoges-Bénédictins', 2001), $stations), '« gare » doit précéder le nom');
    }

    public function test_a_notice_found_by_isbn_must_carry_the_title_of_the_reference(): void
    {
        $this->assertTrue(BibliographyMatcher::titlesAgree('La ligne des Causses et de l\'Aubrac : Béziers-Neussargues', 'L\'épopée de la ligne des Causses : histoire du chemin de fer de Béziers à Neussargues'));
        $this->assertFalse(BibliographyMatcher::titlesAgree('Le Train Jaune de Cerdagne', 'Croix et calvaires : canton de Sarrebourg'));
        $this->assertFalse(BibliographyMatcher::titlesAgree('', 'Croix et calvaires'));
    }
}

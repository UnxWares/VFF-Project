<?php

namespace Tests\Unit\Bibliography;

use App\Services\Bibliography\BibliographyRecord;
use App\Services\Bibliography\WikitextReferences;
use PHPUnit\Framework\TestCase;

/**
 * Références d'un article de Wikipédia : modèles {{Ouvrage}} et {{Article}}, lignes de la section « Bibliographie »,
 * emplacement (bibliographie ou note), valeurs nettoyées.
 */
class WikitextReferencesTest extends TestCase
{
    private const ORIGIN = 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Cerdagne&oldid=1';

    private const TEXT = <<<'WIKI'
        La ligne fut ouverte en 1910.<ref>{{Ouvrage|auteur1=Pierre Cazenove|titre=Le Train Jaune de Cerdagne|éditeur=[[Editions du Cabri]]|lieu=Breil-sur-Roya|année=1992|isbn=2-914603-92-4}}</ref>
        Elle est électrifiée.<ref>{{Article|auteur=Jean Dupont|titre=Les ponts de la Cerdagne|périodique=''Revue de géographie alpine''|année=1934|pages=12-30}}</ref>
        Un décret.<ref>{{Lien web|url=https://example.org|titre=Décret du 1er mai}}</ref>

        == Bibliographie ==
        * {{Ouvrage|nom1=Marassé|prénom1=Philippe|titre=Les trains des Pyrénées|sous-titre=histoire d'une ligne|éditeur=Cabri|année=2010}}
        * Jacques Chapuis, ''Le chemin de fer de Cormeilles'', Paris, Éditions de l'Ouest, 1967, ISBN 978-2-9535807-1-6.
        * le viaduc connu sous le nom de ''Vecchio''
        * Marc Gabriel, ''Le Petit Train de Lunéville''.

        == Notes et références ==
        <references />
        WIKI;

    /** @return array<string, array{record: BibliographyRecord, where: string}> par titre */
    private function byTitle(): array
    {
        $out = [];
        foreach ((new WikitextReferences)->extract(self::TEXT, self::ORIGIN) as $r) {
            $out[$r['record']->title] = $r;
        }

        return $out;
    }

    public function test_it_reads_a_book_template_in_a_note(): void
    {
        $r = $this->byTitle()['Le Train Jaune de Cerdagne'];

        $this->assertSame('reference', $r['where']);
        $this->assertSame(['Pierre Cazenove'], $r['record']->authors);
        $this->assertSame('Editions du Cabri', $r['record']->publisher);
        $this->assertSame('Breil-sur-Roya', $r['record']->place);
        $this->assertSame(1992, $r['record']->year);
        $this->assertSame('9782914603928', $r['record']->isbn);
        $this->assertSame(self::ORIGIN, $r['record']->url);
        $this->assertSame('wikipedia', $r['record']->provider);
    }

    public function test_it_reads_an_article_template_with_its_journal(): void
    {
        $r = $this->byTitle()['Les ponts de la Cerdagne'];

        $this->assertSame('article', $r['record']->kind);
        $this->assertSame('Revue de géographie alpine', $r['record']->journal);
        $this->assertSame(1934, $r['record']->year);
    }

    public function test_it_ignores_web_links(): void
    {
        $this->assertArrayNotHasKey('Décret du 1er mai', $this->byTitle());
    }

    public function test_it_places_a_template_of_the_bibliography_section_in_the_bibliography(): void
    {
        $r = $this->byTitle()["Les trains des Pyrénées : histoire d'une ligne"];

        $this->assertSame('bibliography', $r['where']);
        $this->assertSame(['Philippe Marassé'], $r['record']->authors);
    }

    public function test_it_splits_a_free_text_bibliography_line(): void
    {
        $r = $this->byTitle()['Le chemin de fer de Cormeilles'];

        $this->assertSame('bibliography', $r['where']);
        $this->assertSame(['Jacques Chapuis'], $r['record']->authors);
        $this->assertSame('Paris', $r['record']->place);
        $this->assertSame("Éditions de l'Ouest", $r['record']->publisher);
        $this->assertSame(1967, $r['record']->year);
        $this->assertSame('9782953580716', $r['record']->isbn);
    }

    public function test_it_skips_lines_that_do_not_split_safely(): void
    {
        $titles = array_keys($this->byTitle());

        $this->assertNotContains('Vecchio', $titles, 'phrase sans auteur ni édition');
        $this->assertNotContains('Le Petit Train de Lunéville', $titles, 'ni année, ni éditeur, ni ISBN : rien ne dit que c\'est un ouvrage');
    }

    public function test_it_reads_nothing_from_a_text_without_references(): void
    {
        $this->assertSame([], (new WikitextReferences)->extract("Un article sans sources.\n== Histoire ==\nTexte.", self::ORIGIN));
    }

    public function test_it_cleans_wiki_markup(): void
    {
        $this->assertSame('Le Petit train', WikitextReferences::plain("''[[Le Petit train|Le Petit train]]''<ref>note</ref>"));
        $this->assertSame('Texte brut', WikitextReferences::plain('{{lang|fr|Texte}} {{nobr|brut}}'));
    }
}

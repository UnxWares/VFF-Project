<?php

namespace Tests\Unit\Materials;

use App\Services\Materials\WikipediaMaterialSections;
use PHPUnit\Framework\TestCase;

/**
 * Lecture du wikitexte : sections utiles, liens internes et phrase qui les porte, prudence sur
 * le futur, le conditionnel et la négation.
 */
class WikipediaMaterialSectionsTest extends TestCase
{
    private const LINE_ARTICLE = <<<'WIKI'
        {{Infobox Ligne ferroviaire|nom=Ligne de Lunéville à Saint-Dié}}
        La ligne relie [[Lunéville]] à [[Saint-Dié-des-Vosges]].
        == Histoire ==
        Les [[BB 16500]] sont citées ici, mais dans l'histoire.
        == Trafic ==
        === Matériel roulant ===
        Rames [[Voiture Corail|Corail]] tractées par des [[BB 15000]]<ref>Note</ref>, les trains ne circulent plus.
        * [[X 73500|Autorails X 73500]] de 2000 à 2015
        [[Fichier:X 4750 Saint-Dié.jpg|vignette|Un [[X 4750]] en gare de Saint-Dié.]]
        Des [[Régiolis]] devraient remplacer ces autorails.
        Les [[CC 72000]] n'ont jamais circulé sur la ligne.
        === Projets ===
        Le [[TER 2N NG]] arrivera en 2030.
        == Notes et références ==
        [[Catégorie:Ligne ferroviaire dans les Vosges]]
        WIKI;

    public function test_only_matching_sections_and_their_subsections_are_kept(): void
    {
        $sections = WikipediaMaterialSections::sections(self::LINE_ARTICLE, WikipediaMaterialSections::LINE_SECTIONS);

        $this->assertSame(['Trafic', 'Trafic > Matériel roulant', 'Trafic > Projets'], array_column($sections, 'title'));
        $this->assertSame([2, 3, 3], array_column($sections, 'level'));
    }

    public function test_sections_about_projects_are_doubtful(): void
    {
        $this->assertTrue(WikipediaMaterialSections::isDoubtfulSection('Trafic > Projets'));
        $this->assertTrue(WikipediaMaterialSections::isDoubtfulSection('Dessertes réalisées ou prévues'));
        $this->assertFalse(WikipediaMaterialSections::isDoubtfulSection('Trafic > Matériel roulant'));
    }

    public function test_links_carry_their_sentence_and_doubtful_sentences_are_dropped(): void
    {
        $sections = WikipediaMaterialSections::sections(self::LINE_ARTICLE, WikipediaMaterialSections::LINE_SECTIONS);
        $links = WikipediaMaterialSections::links($sections[1]['text']);

        $this->assertSame(['Voiture Corail', 'BB 15000', 'X 73500', 'X 4750'], array_column($links, 'target'));
        $this->assertSame('Rames Corail tractées par des BB 15000, les trains ne circulent plus.', $links[1]['quote'], 'référence retirée');
        $this->assertSame('Autorails X 73500 de 2000 à 2015', $links[2]['quote'], 'puce de liste retirée');
        $this->assertSame('Un X 4750 en gare de Saint-Dié.', $links[3]['quote'], 'légende de l\'image');
    }

    public function test_links_to_files_categories_and_other_namespaces_are_ignored(): void
    {
        $links = WikipediaMaterialSections::links('Voir [[Catégorie:Autorail]], [[:en:BB 15000]], [[Modèle:Lien]] et [[Wikipédia:Accueil]].');

        $this->assertSame([], $links);
    }

    public function test_titles_are_normalized_like_mediawiki(): void
    {
        $this->assertSame('BB 15000', WikipediaMaterialSections::normalizeTitle('BB_15000'));
        $this->assertSame('Ligne de Paris à Lyon', WikipediaMaterialSections::normalizeTitle('ligne de Paris&nbsp;à Lyon'));
        $this->assertSame('X 73500', WikipediaMaterialSections::normalizeTitle('  X  73500 '));
    }

    public function test_plain_text_keeps_inline_templates_and_drops_the_others(): void
    {
        $this->assertSame(
            'Le 2 mai 1981, à 160 km/h, sur la ligne de Paris à Lyon.',
            WikipediaMaterialSections::plain('Le {{date-|2 mai 1981}}, à {{unité|160|km/h}}{{refnec}}, sur la [[Ligne de Paris à Lyon|ligne de Paris à Lyon]] .'),
        );
        $this->assertSame('Le tram-train Avanto', WikipediaMaterialSections::plain("Le '''tram-train''' {{Lien|langue=en|fr=Avanto|trad=Siemens Avanto}}"));
        $this->assertSame('Section A - B', WikipediaMaterialSections::plain("Section A \u{2013} B"));
    }

    public function test_material_article_sections(): void
    {
        $wiki = "== Conception ==\n[[Ligne de Paris à Lyon]]\n== Services effectués ==\n=== TER ===\n* [[Ligne de Lunéville à Saint-Dié]]\n== Préservation ==\n[[Ligne de Tours à Vierzon]]";
        $sections = WikipediaMaterialSections::sections($wiki, WikipediaMaterialSections::MATERIAL_SECTIONS);

        $this->assertSame(['Services effectués', 'Services effectués > TER'], array_column($sections, 'title'));
        $this->assertSame([['target' => 'Ligne de Lunéville à Saint-Dié', 'quote' => 'Ligne de Lunéville à Saint-Dié']], WikipediaMaterialSections::links($sections[1]['text']));
    }
}

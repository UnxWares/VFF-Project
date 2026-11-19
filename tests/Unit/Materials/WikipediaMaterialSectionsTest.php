<?php

namespace Tests\Unit\Materials;

use App\Services\Materials\WikipediaMaterialSections;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function test_a_sentence_about_the_replacement_of_another_train_is_flagged_and_quoted_with_its_subject(): void
    {
        // Ligne d'Épinal à Bussang : le TGV est cité comme remplaçant du train Corail, pas comme sujet de la phrase.
        $links = WikipediaMaterialSections::links(
            "Avant l'électrification, il existait un train [[Voiture Corail|Corail]] qui reliait Paris à Remiremont. Ce train a été remplacé par le [[TGV]] après la mise en service de la [[LGV Est européenne]].\n"
            ."Les [[Z 27500]] assurent le service. Elles ont depuis été remplacées par des [[Regio 2N]].\n"
            .'* Ils sont remplacés par des [[X 73500]] à partir de 2002.'
        );

        $this->assertSame(['Voiture Corail', 'TGV', 'LGV Est européenne', 'Z 27500', 'Regio 2N', 'X 73500'], array_column($links, 'target'));
        $this->assertSame([false, true, true, false, true, true], array_column($links, 'refers_back'));
        $this->assertSame(
            "Avant l'électrification, il existait un train Corail qui reliait Paris à Remiremont. Ce train a été remplacé par le TGV après la mise en service de la LGV Est européenne.",
            $links[1]['quote'],
            'la phrase précédente donne son sujet à « Ce train »',
        );
        $this->assertSame('Les Z 27500 assurent le service. Elles ont depuis été remplacées par des Regio 2N.', $links[4]['quote']);
        $this->assertSame('Ils sont remplacés par des X 73500 à partir de 2002.', $links[5]['quote'], 'début de ligne : pas de phrase précédente à citer');
    }

    public function test_a_replacement_sentence_with_its_own_subject_is_not_flagged(): void
    {
        $links = WikipediaMaterialSections::links("Les [[Régiolis]] ont remplacé les [[X 4750]] en 2016.\nCes locomotives ont remplacé, en 2010, les [[BB 16000]] affectées à la ligne.");

        $this->assertSame(['Régiolis', 'X 4750', 'BB 16000'], array_column($links, 'target'));
        $this->assertSame([false, false, false], array_column($links, 'refers_back'), 'la série citée est celle qui a circulé : lien direct');
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
        $this->assertSame([['target' => 'Ligne de Lunéville à Saint-Dié', 'quote' => 'Ligne de Lunéville à Saint-Dié', 'refers_back' => false]], WikipediaMaterialSections::links($sections[1]['text']));
    }

    /** Chemin de fer du Blanc-Argent, « Matériel roulant > Matériel actuel » (révision 238401038), tel que lu par l'import. */
    private const BLANC_ARGENT = <<<'WIKI'
        [[Fichier:Salbris gare 6.jpg|vignette|X 74501 et X 74505 à [[Gare de Salbris|Salbris]] (2009).]]
        En {{date|2003|dans les chemins de fer}}, la desserte était assurée par cinq [[autorail]]s neufs construits par [[Soulé|CFD]] à [[Bagnères-de-Bigorre]], numérotés [[X 74500|X 74501 à 74505]]. Leur acquisition traduit la volonté du [[Conseil régional du Centre-Val de Loire|conseil régional du Centre]] de maintenir la ligne en service. Ce nouveau matériel offre un confort similaire aux autorails [[X 73500]], avec notamment la [[climatisation]].
        WIKI;

    public function test_a_series_cited_as_a_comparison_is_not_linked(): void
    {
        $targets = array_column(WikipediaMaterialSections::links(self::BLANC_ARGENT), 'target');

        $this->assertContains('X 74500', $targets, 'les autorails de la ligne');
        $this->assertNotContains('X 73500', $targets, '« un confort similaire aux autorails X 73500 » : une comparaison, pas une circulation');
        $this->assertContains('Climatisation', $targets, 'la suite de la phrase, après la virgule, reste lue');
    }

    public function test_linked_sentences_flag_the_compared_series(): void
    {
        $compared = [];
        foreach (WikipediaMaterialSections::linkedSentences(self::BLANC_ARGENT) as $sentence) {
            foreach ($sentence['links'] as $link) {
                $compared[$link['target']] = $link['compared'];
            }
        }

        $this->assertTrue($compared['X 73500']);
        $this->assertFalse($compared['X 74500']);
    }

    /** @return iterable<string, array{string, bool}> */
    public static function comparisons(): iterable
    {
        // Texte en clair qui précède le lien.
        yield 'similaire aux' => ['Ce nouveau matériel offre un confort similaire aux autorails', true];
        yield 'identique au (Meaux à Dammartin)' => ['Type NK (identique au', true];
        yield 'comparable aux' => ['Des performances comparables aux', true];
        yield 'à l\'image des' => ['Une livrée à l\'image des', true];
        yield 'à l\'instar des' => ['Climatisées à l’instar des', true];
        yield 'plus confortable que' => ['Ces autorails sont plus confortables que les', true];
        yield 'dérivés des' => ['Des engins dérivés des', true];
        yield 'sur le modèle des' => ['Construits sur le modèle des', true];
        yield 'exemple (872000)' => ['La fréquentation a augmenté de 89 % entre 2002 et 2008 coïncidant avec l\'arrivée de nouveaux matériels comme les ATER', false];
        yield 'appartenance (359000)' => ['Ces deux engins, achetés par la région Haute-Normandie affectés au dépôt de Sotteville-lès-Rouen, sont issus respectivement des familles des', false];
        yield 'restriction' => ['Il ne circule plus guère que des', false];
        yield 'énumération' => ['La desserte est assurée aussi bien par des X 72500 que par des', false];
        yield 'comparaison close par une virgule' => ['Un confort similaire aux trains Corail, la desserte est assurée par des', false];
        yield 'comparaison dans une autre phrase' => ['Un confort similaire aux trains Corail. Les', false];
    }

    #[DataProvider('comparisons')]
    public function test_comparison_markers(string $before, bool $compared): void
    {
        $this->assertSame($compared, WikipediaMaterialSections::isCompared($before));
    }
}

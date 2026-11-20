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
        $this->assertSame([['target' => 'Ligne de Lunéville à Saint-Dié', 'quote' => 'Ligne de Lunéville à Saint-Dié', 'refers_back' => false, 'doubt' => null]], WikipediaMaterialSections::links($sections[1]['text']));
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
        // Revue des PR #19 et #20 : « ne … plus <mot> que » est une restriction, la série citée est la seule à rouler.
        yield 'restriction : n\'est plus desservie que' => ['La ligne n\'est plus desservie que par des', false];
        yield 'restriction : n’est plus parcourue que' => ['Depuis 1990, la ligne n’est plus parcourue que par les autorails', false];
        yield 'restriction : ne circule plus désormais que' => ['Il ne circule plus désormais que des', false];
        yield 'restriction : n\'y a plus que' => ['Sur la ligne, il n\'y a plus que des', false];
        yield 'comparaison niée : ne sont pas plus confortables que' => ['Ces autorails ne sont pas plus confortables que les', true];
        yield 'comparaison : moins rapides que' => ['Ces autorails sont moins rapides que les', true];
        yield 'énumération' => ['La desserte est assurée aussi bien par des X 72500 que par des', false];
        yield 'comparaison close par une virgule' => ['Un confort similaire aux trains Corail, la desserte est assurée par des', false];
        yield 'comparaison dans une autre phrase' => ['Un confort similaire aux trains Corail. Les', false];
    }

    /** Revue des PR #19 et #20 : la restriction garde le lien et la période (compared faux). */
    public function test_a_restriction_keeps_the_link_and_a_comparison_does_not(): void
    {
        foreach ([
            "La ligne n'est plus desservie que par des [[X 73500]].",
            'Depuis 1990, la ligne n’est plus parcourue que par les autorails [[X 73500]] de la région.',
            'Il ne circule plus désormais que des [[X 73500]] sur la ligne.',
        ] as $sentence) {
            $this->assertSame(['X 73500'], array_column(WikipediaMaterialSections::links($sentence), 'target'), $sentence);
            $compared = array_column(array_merge(...array_column(WikipediaMaterialSections::linkedSentences($sentence), 'links')), 'compared', 'target');
            $this->assertSame(['X 73500' => false], $compared, $sentence);
        }
        $comparison = 'Ces autorails sont plus confortables que les [[X 2800]].';
        $this->assertSame([], array_column(WikipediaMaterialSections::links($comparison), 'target'));
    }

    #[DataProvider('comparisons')]
    public function test_comparison_markers(string $before, bool $compared): void
    {
        $this->assertSame($compared, WikipediaMaterialSections::isCompared($before));
    }

    /**
     * Phrases réelles (wikitexte des révisions lues par l'import, 19/11/2026) : famille de doute attendue
     * pour chaque article cité. null : la phrase ne met pas en doute la circulation.
     *
     * @return iterable<string, array{string, string, ?string}>
     */
    public static function doubts(): iterable
    {
        // 896000, « Matériel roulant > Matériel Z 890 » (révision 240212251).
        $z890 = 'Le {{date|14 juin 2019}}, les TMR et la SNCF ont signé avec Stadler un contrat portant sur l’acquisition de 7 nouvelles rames, immatriculées [[Z 890]] et destinées à remplacer les Z 800.';
        yield 'commande : Z 890' => [$z890, 'Z 890', 'order'];
        // 340506, Voies du port de Rouen (révision 240886765) : rame présentée au public, « destinée au réseau ».
        yield 'commande : Omneo Premium destinée au réseau' => ["la ligne a été utilisée le {{Date|9 février 2020}} pour présenter aux Rouennais, face à l'[[Caserne Jeanne d'Arc|hôtel de région]], une nouvelle rame [[Regio 2N|{{nobr|Omneo Premium}} ({{nobr|Z 56600}})]] destinée au réseau TER Normandie.", 'Regio 2N', 'order'];
        // 420000, « Trafic » (révision 239547071).
        yield 'essai : Velaro D' => ["C'est ainsi qu'en {{date|octobre 2011}}, des essais de shuntage ont été effectués sur la ligne par des [[DB 407|Velaro D]], la rame stationnant à la [[Gare de Plouaret-Trégor|gare de Plouaret]] pour repos entre différents essais.", 'DB 407', 'trial'];
        // 909000, « Matériel ayant circulé sur la ligne » (révision 239895345) : les essais valent pour toute la proposition.
        yield 'essai au-delà de la virgule : X 2100' => ['** Autorail X 2101 (de la série [[X 2100]]) avec deux remorques [[XR 6000]], venu en essais sur Grenoble - Montmélian le 16/06/1980.', 'X 2100', 'trial'];
        yield 'renfort : A1AA1A 68500' => ["** [[A1AA1A 68500]] du dépôt de Chalindrey venues en renfort pour les Jeux Olympiques d'hiver de Grenoble en {{date|février 1968}}.", 'A1AA1A 68500', 'one_off'];
        // 538000, « Trafic > TER » (révision 235342687) : seule la série employée « très exceptionnellement ».
        $rochelle = 'Ces trains sont normalement assurés avec du matériel [[B 82500]] et [[Régiolis|B 84500]] en traction électrique, des [[B 81500]] ont très exceptionnellement été employés lors de manques de disponibilité des B 82500 et des B 84500 ; pour autant, il n’est pas rare de les voir circuler en traction diesel.';
        yield 'exceptionnel : B 81500' => [$rochelle, 'B 81500', 'one_off'];
        yield 'exceptionnel : pas les B 82500 de la même phrase' => [$rochelle, 'B 82500', null];
        yield 'exceptionnel : pas les B 84500 de la même phrase' => [$rochelle, 'Régiolis', null];
        // 900000, « Matériel roulant » : légende d'une locomotive préservée.
        yield 'préservation : 2CC2 3402' => ['[[Fichier:Dépôt-de-Chambéry - Rotonde - 2CC2-3402 - 20131103 143741.jpg|thumb|Locomotive [[2CC2 3402]] préservée sous la rotonde de Chambéry.]]', '2CC2 3402', 'preserved'];
        // 972000 (révision 241186256) : « Musée de Sèvres » est une station du T2, pas un musée.
        yield 'station « Musée de Sèvres »' => ["[[Fichier:T2 MuséeSèvres.JPG|thumb|La rame [[Alstom Citadis#Modèles|Citadis 302]] {{n°}}433 à ''Musée de Sèvres'']]", 'Alstom Citadis', null];
        // Article « Autorail Pauline », « Services effectués > Alsace-Lorraine » (révision 241246803).
        yield 'possibilité : Pauline' => ["Dès {{date|octobre 1933}}, l'AL considère la possibilité d'exploiter la section entre [[Sainte-Marie-aux-Mines]] et [[Sélestat]] de la [[ligne de Sélestat à Lesseux - Frapelle]] avec des autorails.", 'Ligne de Sélestat à Lesseux - Frapelle', 'possible'];
        // Article « TGV Atlantique », « Service » (révision 240399439) : « y permettant ainsi la circulation » est
        // dans une autre partie de la phrase que les lignes citées (le filtre des périodes, lui, lit la proposition).
        yield 'possibilité hors de la partie de phrase : TVM-300' => ['Les [[LGV Sud Europe Atlantique]] et [[LGV Bretagne-Pays de la Loire|Bretagne-Pays de la Loire]], qui ont été mises en service en 2017, sont équipées de la TVM-300, y permettant ainsi la circulation de toutes ces rames.', 'LGV Bretagne-Pays de la Loire', null];
        // 145000, « Exploitation » (révision 238408373).
        yield 'acheminement : TGV vers Bischheim' => ['Elle est également utilisée pour les acheminements de rames [[TGV]] au [[technicentre de Bischheim]].', 'TGV', 'positioning'];
        // 441000, « Exploitation » (révision 240838961) : la parenthèse qui énumère les séries lit la partie qui l'entoure.
        $malo = "Le matériel engagé est surtout composé d'automotrices ([[Z 21500|Z TER]], [[Autorail grande capacité|AGC]] électriques, diesels et bimodes-bitension, [[TGV]] Sud-Est, Atlantiques et Duplex, [[X 2100]]) mais on retrouve également des [[BB 25500|BB 25500 + RRR]], quelques [[BB 63500]], [[BB 69400]] et [[BB 60000]] sur les dessertes Fret et Infra, ainsi que des acheminements de matériels bretons engagés sur la ligne Dol-Dinan, et bas et haut-normands vers Rennes pour le reprofilage de leurs essieux ([[X 72500]], [[X 73500]], [[X 4750]]).";
        yield 'reprofilage : X 73500' => [$malo, 'X 73500', 'positioning'];
        yield 'reprofilage : pas les BB 60000 du fret' => [$malo, 'BB 60000', null];
        yield 'reprofilage : pas les X 2100 du service' => [$malo, 'X 2100', null];
        // 908000, « Trafic et liaisons > Liaisons spéciales » (révision 241124486) : trains détournés par Lyon.
        $valence = "Par suite de la coupure totale de la ligne, en 2007, 2008, 2009 et 2013, les trains de pèlerinages circulent entièrement en traction électrique, tractés par une [[BB 22200]] transitant par Lyon (raccordement de [[Saint-Fons]]) et [[Vienne (Isère)|Vienne]] (diocèse) jusqu'à [[gare de Toulouse-Matabiau|Toulouse Matabiau]] (rebroussement et changement de machine par une [[BB 9300]] ou [[BB 7200]] apte à circuler sous caténaire 1500 Volts continu).";
        yield 'coupure : BB 22200' => [$valence, 'BB 22200', 'diverted'];
        yield 'coupure : BB 7200' => [$valence, 'BB 7200', 'diverted'];
        // 569000, « Exploitation » (révision 212104765).
        $orleans = "Les trains n'arrivent plus dans le cul de sac d'Orléans pour changer de locomotive : ce sont les voyageurs qui changent de rame, les rames Corail (remplacés progressivement par des [[Régio2N]]) étant réservées aux relations Paris - Orléans (sauf [[Interloire]]) tandis que les matériels récents ([[Z 21500|Z21500]] et [[Régiolis|Regiolis]]) sont réservés à la ligne Orléans-Tours.";
        yield 'réservé à une autre ligne : Z 21500' => [$orleans, 'Z 21500', 'restricted'];
        yield 'réservé à une autre ligne : Régiolis' => [$orleans, 'Régiolis', 'restricted'];
        yield 'réservé à une autre ligne : pas les Régio2N' => [$orleans, 'Régio2N', null];
        // 896000, « Matériel roulant > Matériel de déneigement » (révision 240212251).
        yield 'pièce reprise : moteur de BB 8100' => ['Il est équipé de deux turbines, entourées d’un carter de tôle, entraînées par un moteur de [[BB 8100]].', 'BB 8100', 'component'];
        // Article « Z 4900 », « Service » (révision 225161840) : les lignes sont citées dans une note.
        yield 'note : Z 4900' => ['Si ces automotrices circulent sur toutes les lignes électrifiées en {{unité|12|kV}} {{unité|16⅔|Hz}}{{Note|group=N|Le réseau comprend les lignes [[Ligne de Perpignan à Villefranche - Vernet-les-Bains|Perpignan - Villefranche-de-Conflent]], [[Ligne de Toulouse à Bayonne|Montréjeau - Pau]].}}, les {{nobr|14 exemplaires}} devenus {{nobr|Z 4900}} restent cantonnées à la seule ligne [[Perpignan]] - [[Villefranche-de-Conflent|Villefranche]].', 'Ligne de Toulouse à Bayonne', 'footnote'];
        // Ligne de Nice à Digne (révision 240211690) : les AMP 800 de Provence font partie de la famille AMG 800
        // (Wikidata Q2819261, alias AMP 800) ; « reprennent les mêmes principes que » n'est pas une comparaison écartée.
        yield 'famille, pas comparaison : AMG 800' => ['Ces autorails reprennent les mêmes principes que les autorails [[AMG 800]] de la Corse ou de la Tunisie, tout en étant adaptés aux besoins spécifiques du réseau des Chemins de fer de Provence.', 'AMG 800', null];
    }

    /**
     * Revue des PR #19 et #20 : mots de doute trop larges. « Conservée » au sens de « gardée en service »,
     * « considérablement », « commande centralisée » et « poste de commande » ne mettent pas en doute la
     * circulation ; les preuves réelles de l'audit gardent leur famille (doubts()).
     *
     * @return iterable<string, array{string, string, ?string}>
     */
    public static function narrowDoubts(): iterable
    {
        yield 'conservées en service' => ["Les [[BB 67400]] ont été conservées sur la ligne jusqu'en 2010.", 'BB 67400', null];
        yield 'considérablement' => ['Le trafic a augmenté considérablement avec les [[X 73500]].', 'X 73500', null];
        yield 'considérable' => ['Les [[X 73500]] apportent un gain de temps considérable.', 'X 73500', null];
        yield 'commande centralisée' => ['La ligne est exploitée en commande centralisée avec des [[X 73500]].', 'X 73500', null];
        yield 'poste de commande' => ['Le poste de commande de Rennes suit les [[X 73500]] en continu.', 'X 73500', null];
        yield 'conservée au musée' => ['Une locomotive [[BB 67400]] conservée au musée de Mulhouse.', 'BB 67400', 'preserved'];
        yield 'conservée par une association' => ['La [[BB 67400]] 67401 est conservée par une association.', 'BB 67400', 'preserved'];
        // Preuves réelles en base (audit du 19/11/2026) : familles inchangées.
        yield 'audit : commande des automoteurs (Saint-Germain-des-Fossés à Nîmes)' => ['La région a pu rouler, mais sans suite du fait de la commande des automoteurs [[X 72500]] aptes à circuler à 160 km/h ;', 'X 72500', 'order'];
        yield 'audit : rames commandées (LGV Rhin-Rhône)' => ["30 rames tricourants [[TGV 2N2|Duplex nouvelle génération (TGV 2N2)]] ont été commandées par la SNCF pour l'exploitation de la ligne dès sa mise en service.", 'TGV 2N2', 'order'];
        yield 'audit : commandés neufs (Saint-Benoît à La Rochelle)' => ['Ces engins ont ensuite été cédés à la Région des Pays de la Loire en échange de quatre unités [[B 82500]] - AGC bimode bicourant commandés neufs par cette dernière.', 'B 82500', 'order'];
        yield 'audit : acquisition (Saint-Cloud à Saint-Nom-la-Bretèche)' => ["Le 11 décembre 2013, le STIF a validé l'acquisition de dix-neuf rames [[Z 50000]] (Francilien), pour équiper les lignes de Versailles-Rive-Droite.", 'Z 50000', 'order'];
        yield 'audit : considère la possibilité (Pauline)' => ["Dès {{date|octobre 1933}}, l'AL considère d'exploiter la section avec des autorails de la [[ligne de Sélestat à Lesseux - Frapelle]].", 'Ligne de Sélestat à Lesseux - Frapelle', 'possible'];
    }

    #[DataProvider('narrowDoubts')]
    public function test_doubt_words_are_not_too_broad(string $wikitext, string $target, ?string $doubt): void
    {
        $this->test_doubt_families_on_real_sentences($wikitext, $target, $doubt);
    }

    #[DataProvider('doubts')]
    public function test_doubt_families_on_real_sentences(string $wikitext, string $target, ?string $doubt): void
    {
        $links = array_column(WikipediaMaterialSections::links($wikitext), 'doubt', 'target');

        $this->assertArrayHasKey($target, $links);
        $this->assertSame($doubt, $links[$target]);
    }

    public function test_a_line_cited_for_the_locomotives_derived_from_the_class_is_not_linked(): void
    {
        // Article « 030 T Ouest 3531 à 3602 », « Utilisation et service » (révision 195379106).
        $wiki = "Elles ont été surnommées ''[[Boër (locomotive)|Boer]]'', du fait que les locomotives [[Ligne de Petite Ceinture|Ceinture]] n° '''21 à 35''' qui en sont dérivées, ont été construites en [[1899 dans les chemins de fer|1899]] pendant la [[guerre des Boers]].";

        $this->assertNotContains('Ligne de Petite Ceinture', array_column(WikipediaMaterialSections::links($wiki), 'target'));
        $compared = [];
        foreach (WikipediaMaterialSections::linkedSentences($wiki) as $sentence) {
            foreach ($sentence['links'] as $link) {
                $compared[$link['target']] = $link['compared'];
            }
        }
        $this->assertTrue($compared['Ligne de Petite Ceinture'], 'aucune période non plus');
        $this->assertFalse($compared['Guerre des Boers']);
    }

    public function test_a_sentence_left_empty_by_its_templates_gives_no_evidence(): void
    {
        // 669000, « Exploitation > Matériel roulant » : la phrase entière est dans {{refnec|…}}, la citation valait « . ».
        $wiki = '{{refnec|Un défaut de conception sur les [[Z 150]], entraînant des déformations de la voie, a forcé le retrait précoce de ces matériels de la circulation}}.';

        $this->assertSame([], WikipediaMaterialSections::links($wiki));
    }

    public function test_a_template_opening_the_next_sentence_ends_the_quote(): void
    {
        // 908000, « Matériel roulant » (révision 241124486) : la phrase suivante, non sourcée, n'est pas citée.
        $wiki = "* Locomotives diesel [[BB 67300]] de la SNCF du dépôt de Chambéry, toujours en service, au service annuel 2014 sur des trains Saint Marcellin-Grenoble-Gières-Chambéry. {{refnec|Cependant, en raison d'avaries fréquentes de ces machines, assurant des RRR sur cette liaison, ces TER sont supprimés}}";

        $this->assertSame('Locomotives diesel BB 67300 de la SNCF du dépôt de Chambéry, toujours en service, au service annuel 2014 sur des trains Saint Marcellin-Grenoble-Gières-Chambéry.', WikipediaMaterialSections::links($wiki)[0]['quote']);
    }

    public function test_sections_about_trials_records_or_preservation_cast_doubt(): void
    {
        $this->assertSame('section', WikipediaMaterialSections::uncertainSection("Trafic > Trains d'essai"));
        $this->assertSame('section', WikipediaMaterialSections::uncertainSection('Carrière > Records'));
        $this->assertSame('section', WikipediaMaterialSections::uncertainSection('Exploitation > Festivités sur la ligne des Alpes'));
        $this->assertSame('section', WikipediaMaterialSections::uncertainSection('Matériels préservés'));
        $this->assertNull(WikipediaMaterialSections::uncertainSection('Matériel roulant ayant circulé sur la ligne'));
    }

    public function test_a_number_without_a_link_never_names_a_series(): void
    {
        // Famille « série confondue par un numéro » : seul un lien posé désigne une série.
        $this->assertSame([], WikipediaMaterialSections::links('La ligne accueille environ 2100 voyageurs par jour, et 73500 par an.'));
    }
}

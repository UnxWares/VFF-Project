<?php

namespace Tests\Unit;

use App\Services\WikipediaLineParser;
use PHPUnit\Framework\TestCase;

/**
 * Mises en service et fermetures par étapes dans une même phrase : une date par portion nommée, au lieu d'une
 * seule date par mot-clé. Les phrases sont tirées d'articles réels du cache (ligne 838000 Troyes - Gray et
 * autres). Une phrase ambiguë ne donne rien de plus : chaque cas négatif vient d'un faux positif vu pendant la
 * mesure sur les 870 articles.
 */
class WikipediaLineStagedChronologyTest extends TestCase
{
    /**
     * Événements du texte (hors infobox), sous la forme « type date départ>arrivée ».
     *
     * @return list<string>
     */
    private function events(string $title, string $text, string $infobox = ''): array
    {
        $wikitext = ($infobox !== '' ? "{{Infobox Ligne ferroviaire\n{$infobox}\n}}\n" : '')."== Histoire ==\n{$text}\n";

        return array_map(
            fn (array $e) => "{$e['type']} {$e['date']} {$e['section_from']}>{$e['section_to']}".($e['origin'] === 'infobox' ? ' (infobox)' : ''),
            (new WikipediaLineParser)->parseChronology($wikitext, $title),
        );
    }

    private const TROYES_GRAY = 'Ligne de Saint-Julien (Troyes) à Gray';

    public function test_troyes_gray_four_stage_openings(): void
    {
        $events = $this->events(self::TROYES_GRAY, 'La ligne est mise en service en plusieurs étapes : Troyes - Bar-sur-Seine le 20 juillet 1862, '
            .'Bar-sur-Seine - Châtillon-sur-Seine le 19 octobre 1868, Châtillon-sur-Seine - Is-sur-Tille le 9 décembre 1882 et enfin la section '
            .'située entre Is-sur-Tille et Gray est mise en service le 27 octobre 1888.');

        $this->assertSame([
            'opening 1862-07-20 Troyes>Bar-sur-Seine',
            'opening 1868-10-19 Bar-sur-Seine>Châtillon-sur-Seine',
            'opening 1882-12-09 Châtillon-sur-Seine>Is-sur-Tille',
            'opening 1888-10-27 Is-sur-Tille>Gray',
        ], $events);
    }

    public function test_troyes_gray_passenger_closures_per_section_with_present_tense_verb(): void
    {
        $events = $this->events(self::TROYES_GRAY, 'La ligne ferme au service voyageurs le 2 mars 1969 pour la section Châtillon-sur-Seine - Gray '
            .'et le 31 mai 1980 pour la section Troyes - Châtillon-sur-Seine.');

        $this->assertSame([
            'closure_passenger 1969-03-02 Châtillon-sur-Seine>Gray',
            'closure_passenger 1980-05-31 Troyes>Châtillon-sur-Seine',
        ], $events);
    }

    public function test_troyes_gray_freight_end_shared_by_two_between_sections(): void
    {
        $events = $this->events(self::TROYES_GRAY, 'Le trafic marchandises cesse entre Villars et Poinson et entre Mirebeau-sur-Bèze et '
            .'Champagne-sur-Vingeanne le 30 mai 1975. Ces tronçons sont toutefois utilisés pour des échanges de matériel.');

        $this->assertSame([
            'closure_freight 1975-05-30 Villars>Poinson',
            'closure_freight 1975-05-30 Mirebeau-sur-Bèze>Champagne-sur-Vingeanne',
        ], $events);
    }

    public function test_date_then_section_enumeration_keeps_the_traffic(): void
    {
        $events = $this->events('Ligne de Breuil-Barret à Velluire', 'Elle a été fermée au service des marchandises le 24 septembre 1989 '
            .'entre Breuil-Barret et La Châtaigneraie, le 8 avril 1955 entre La Châtaigneraie et Fontenay-le-Comte , le 1 février 1971 '
            .'entre Fontaine-Vendée et Velluire, 1 avril 1987 entre la Châtaigneraie et Breuil-Barret.');

        $this->assertContains('closure_freight 1955-04-08 La Châtaigneraie>Fontenay-le-Comte', $events);
        $this->assertContains('closure_freight 1971-02-01 Fontaine-Vendée>Velluire', $events);
        $this->assertContains('closure_freight 1987-04-01 Châtaigneraie>Breuil-Barret', $events);
        $this->assertCount(4, $events);
    }

    public function test_stages_after_a_colon_in_a_departmental_network(): void
    {
        $events = $this->events('Chemins de fer départementaux de la Somme', 'Cette ligne de 42.348 km fut ouverte en plusieurs tronçons : '
            .'le 15 novembre 1888 de Doullens à Beauval, le 14 février 1889 de Beauval à Beauquesne et enfin le 3 août 1891 de Beauquesne à Albert.');

        $this->assertContains('opening 1889-02-14 Beauval>Beauquesne', $events);
        $this->assertContains('opening 1888-11-15 Doullens>Beauval', $events);
    }

    public function test_electrification_stages_with_month_dates(): void
    {
        $events = $this->events('Ligne de Bordeaux-Saint-Jean à Irun', 'Entre 1926 et 1929, la ligne est électrifiée par caténaire en courant '
            .'continu 1 500 volts : entre Dax et Hendaye en 1926, entre Morcenx et Dax en février 1927, entre Bordeaux et Morcenx en juin 1927, '
            .'puis jusqu\'à Irun en 1929, formant à partir de 1938 le plus long axe électrifié de France.');

        $this->assertContains('electrification 1927-02-01 Morcenx>Dax', $events);
        $this->assertContains('electrification 1927-06-01 Bordeaux>Morcenx', $events);
    }

    public function test_section_then_date_with_between_sections(): void
    {
        $events = $this->events('Ligne de Vitré à Pontorson', 'La ligne est mise en service le 1 octobre 1867 par la Compagnie du chemin de fer '
            .'de Vitré à Fougères, entre Vitré et Fougères, le 26 janvier 1872 entre Fougères et Saint-Brice-en-Coglès et le 10 octobre 1872 '
            .'entre Saint-Brice-en-Coglès et Pontorson.');

        $this->assertContains('opening 1872-01-26 Fougères>Saint-Brice-en-Coglès', $events);
        $this->assertContains('opening 1872-10-10 Saint-Brice-en-Coglès>Pontorson', $events);
    }

    public function test_leading_zero_day_and_comma_enumeration(): void
    {
        $events = $this->events('Ligne d\'Étampes à Beaune-la-Rolande', 'La ligne est fermée au trafic voyageurs le 15 mai 1939 de Pithiviers '
            .'à Beaune-la-Rolande, le 04 novembre 1969 d\'Étampes à Pithiviers.');

        $this->assertSame([
            'closure_passenger 1939-05-15 Pithiviers>Beaune-la-Rolande',
            'closure_passenger 1969-11-04 Étampes>Pithiviers',
        ], $events);
    }

    public function test_shared_date_after_de_a_sections(): void
    {
        $events = $this->events('Ligne d\'Aubigné-Racan à Sablé', 'La ligne est déclassée entre Verron et Louailles et entre Luché-Pringé et '
            .'Le Lude en 1970. La section entre Aubigné-Racan et le Lude est déclassée en 1991 et entre Le Lude et La Flèche en 2003.');

        $this->assertContains('declassification 1970-01-01 Verron>Louailles', $events);
        $this->assertContains('declassification 1970-01-01 Luché-Pringé>Le Lude', $events);
        $this->assertContains('declassification 2003-01-01 Le Lude>La Flèche', $events);
    }

    public function test_followed_by_stage_keeps_its_section(): void
    {
        $events = $this->events('Ligne de Port-de-Piles à Argenton-sur-Creuse', 'Le tronçon du Blanc à Argenton-sur-Creuse a été fermé au '
            .'service des voyageurs, le 22 juin 1940, suivi par celui de Port-de-Piles au Blanc, le 27 juin 1940.');

        $this->assertContains('closure_passenger 1940-06-27 Port-de-Piles>Le Blanc', $events);
        $this->assertContains('closure_passenger 1940-06-22 Le Blanc>Argenton-sur-Creuse', $events);
    }

    public function test_present_tense_opening_and_closure_verbs(): void
    {
        $this->assertContains('opening 1867-09-16 Digoin>Paray-le-Monial', $this->events('Ligne de Moulins à Mâcon',
            'La section reliant Moulins à Paray-le-Monial ouvre en deux étapes : le tronçon de Digoin à Paray-le-Monial ouvre le 16 septembre '
            .'1867, tandis que le tronçon de Moulins à Digoin ouvre le 10 mai 1869.'));
        $this->assertContains('closure 1959-05-31 Uzès>Montaren', $this->events('Ligne du Martinet à Beaucaire',
            'La section Uzès - Montaren ferme le 31 mai 1959 et est déclassée le 13 février 1964.'));
        $this->assertContains('opening_passenger 1886-10-18 Villeneuve-Saint-Georges>Massy', $this->events('Ligne de Choisy-le-Roi à Massy - Verrières',
            'La section de Villeneuve-Saint-Georges à Massy est déclarée d\'utilité publique le 22 mars 1880. Elle est construite par le '
            .'service des Ponts et chaussées, puis concédée au Syndicat du Chemin de fer de grande ceinture de Paris le 1 juillet 1885. '
            .'Elle ouvre au trafic des voyageurs le 18 octobre 1886.'));
    }

    public function test_celui_de_is_not_part_of_the_place_name(): void
    {
        $this->assertSame(['closure 1989-07-31 Gisors-Boisgeloup>Gasny'], $this->events('Ligne de Gisors-Boisgeloup à Pacy-sur-Eure',
            'Celui de Gisors-Boisgeloup à Gasny ferme le 31 juillet 1989.'));
    }

    public function test_respectively_pairs_sections_and_dates_in_order(): void
    {
        $events = $this->events('Ligne de Chaugey à Lons-le-Saunier', 'Les sections de Chaussin à Pleure et de Pleure à Bletterans sont '
            .'fermées respectivement le 1 mars 1969 et le 2 juin 1971.');

        $this->assertSame(['closure 1969-03-01 Chaussin>Pleure', 'closure 1971-06-02 Pleure>Bletterans'], $events);
    }

    // ------------------------------------------------------------------
    // Phrases ambiguës : rien de plus (faux positifs vus pendant la mesure)
    // ------------------------------------------------------------------

    public function test_another_verb_is_not_a_stage(): void
    {
        $events = $this->events('Ligne de Bourg-en-Bresse à Bellegarde', 'La portion La Cluse - Bellegarde ayant été fermée en 1990, seule la '
            .'portion Bourg - Brion est exploitée en 2005.');

        $this->assertNotContains('closure 2005-01-01 Bourg>Brion', $events);
    }

    public function test_sections_in_an_example_parenthesis_are_not_split(): void
    {
        $events = $this->events('Chemin de fer de Cormeilles à Glos-Montfort et extensions', 'La ligne, comprenant une part importante '
            .'d\'infrastructure hors chaussées ou accotements routiers (notamment entre Cormeilles et Lieurey et entre Saint-Georges-du-Bièvre et '
            .'Glos-Montfort), a été ouverte le 3 août 1902 et déclassée le 29 novembre 1948.');

        $this->assertNotContains('opening 1902-08-03 Saint-Georges-du-Bièvre>Glos-Montfort', $events);
    }

    public function test_present_tense_verb_needs_a_full_date_right_after(): void
    {
        // « ferme le 24 août » sans année : la date suivante (réouverture) ne date pas la fermeture.
        $this->assertSame([], array_filter($this->events('Ligne de Raon-l\'Étape à Raon-sur-Plaine',
            'Elle ferme le 24 août et ne rouvre partiellement le 1 février 1915 jusqu\'à Celles-sur-Plaine.'),
            fn ($e) => str_starts_with($e, 'closure')));
        // « s'ouvre » : un verbe pronominal, ici une autre ligne citée en contexte.
        $this->assertSame([], $this->events('CEVA', 'Alors que s\'ouvre en 1880 la ligne Bellegarde-sur-Valserine - Annemasse - '
            .'Thonon-les-Bains, Genève se trouve contournée.'));
    }

    public function test_present_tense_verb_of_a_linked_other_line_is_ignored(): void
    {
        $this->assertSame([], $this->events('Ligne de Saint-Cloud à Saint-Nom-la-Bretèche - Forêt-de-Marly', 'Une ligne de rocade est à son '
            .'tour mise en œuvre, la [[Ligne de la grande ceinture de Paris|ligne de la grande ceinture de Paris]] dont le tronçon occidental '
            .'ouvre le 4 septembre 1882 entre Achères et Versailles-Chantiers.'));
    }

    public function test_surviving_train_still_gives_the_end_of_passenger_service(): void
    {
        $events = $this->events('Ligne d\'Annecy à Albertville', 'La ligne ferme au trafic voyageurs le 5 ou 15 mai 1938, à l\'exception '
            .'d\'un train en partance d\'Albertville pour les ouvriers d\'Ugine qui perdure jusqu\'en 1953.');

        $this->assertSame(['closure_passenger 1953-01-01 >'], $events);
    }

    public function test_verb_after_the_sections_keeps_the_old_reading(): void
    {
        $events = $this->events('Ligne de Rochy-Condé à Soissons', 'Le service voyageurs est supprimé le 15 mai 1939 sur la plupart des lignes. '
            .'Le service voyageurs entre Beauvais et Estrées-Saint-Denis, entre La Rue-Saint-Pierre et Saint-Just-en-Chaussée et entre '
            .'Verberie et Estrées cesse à ce moment.');

        $this->assertNotContains('closure 1939-05-15 Verberie>Estrées', $events);
    }

    public function test_date_before_two_between_sections_is_not_shared(): void
    {
        $events = $this->events('Ligne de Frouard à Novéant', "Cette ligne a été électrifiée en trois étapes :\n"
            .'* le 22 avril 1959 entre Pagny-sur-Moselle et Novéant et entre Pagny-sur-Moselle et Vandières (projet Conflans-Jarny - Vandières) ;',
            "| électrification = 1959\n| électrification2 = 1960");

        $this->assertContains('electrification 1960-01-01 > (infobox)', $events);
    }

    public function test_added_stage_does_not_hide_other_infobox_dates(): void
    {
        $events = $this->events('Ligne de Cavaillon à Saint-Maime - Dauphin', 'Créée en 1857, la compagnie des chemins de fer de Paris à Lyon '
            .'et à la Méditerranée (PLM) ouvre en 1877 cet embranchement à voie unique de Cavaillon à Apt, sous-préfecture du Vaucluse.',
            "| mise en service = 1877\n| mise en service2 = 1890");

        $this->assertContains('opening 1877-01-01 Cavaillon>Apt', $events);
        $this->assertContains('opening 1890-01-01 > (infobox)', $events);
        $this->assertNotContains('opening 1877-01-01 > (infobox)', $events, 'même année que la phrase : doublon retiré');
    }

    public function test_no_internal_marker_leaks_into_the_events(): void
    {
        $text = "== Histoire ==\nLa ligne ferme au service voyageurs le 2 mars 1969 pour la section Châtillon-sur-Seine - Gray et le 31 mai 1980 "
            ."pour la section Troyes - Châtillon-sur-Seine.\n";
        foreach ((new WikipediaLineParser)->parseChronology($text, self::TROYES_GRAY) as $e) {
            $this->assertSame(['type', 'date', 'year', 'precision', 'section_from', 'section_to', 'pk_start_m', 'pk_end_m', 'portion_unknown',
                'origin', 'heading', 'description', 'confidence', 'source_quote'], array_keys($e));
        }
    }
}

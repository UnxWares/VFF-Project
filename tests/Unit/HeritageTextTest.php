<?php

namespace Tests\Unit;

use App\Services\EraTimeline;
use App\Services\HeritageText;
use PHPUnit\Framework\TestCase;

/**
 * Exploitations associatives (vff:import-heritage-lines) : lecture des
 * sources, dates, rapprochement des noms, et effet sur la carte décennale.
 */
class HeritageTextTest extends TestCase
{
    public function test_kind_of_an_operation_label(): void
    {
        $this->assertSame(['velorail'], HeritageText::kindsOf('inexploitée (vélo-rail entre Landos et Pradelles)'));
        $this->assertSame(['velorail'], HeritageText::kindsOf('Pédalorail du Grand Pays de Salers'));
        $this->assertSame(['tourist_train', 'velorail'], HeritageText::kindsOf('Train Touristique et Vélorail d\'Étretat-Pays de Caux'));
        $this->assertSame(['tourist_train', 'museum'], HeritageText::kindsOf('Train Touristique, Musée'));
        $this->assertSame([], HeritageText::kindsOf('Funiculaire'));
        // Un train touristique circule : la voie est en service, il l'emporte sur le vélorail.
        $this->assertSame('tourist_train', HeritageText::mainKind(['velorail', 'tourist_train']));
        $this->assertSame('velorail', HeritageText::mainKind(['velorail']));
    }

    public function test_french_dates_keep_their_precision(): void
    {
        $this->assertSame(['date' => '1982-06-03', 'precision' => 'day', 'year' => 1982], HeritageText::parseDate('3 juin 1982'));
        $this->assertSame('1969-06-01', HeritageText::parseDate('Juin 1969')['date']);
        $this->assertSame('month', HeritageText::parseDate('Juin 1969')['precision']);
        $this->assertSame(['date' => '1982-01-01', 'precision' => 'year', 'year' => 1982], HeritageText::parseDate('[[1982 dans les chemins de fer|1982]]'));
        $this->assertSame('2017-07-01', HeritageText::parseDate('1er juillet 2017')['date']);
        $this->assertNull(HeritageText::parseDate('inconnue'));
    }

    public function test_dates_of_an_operation_in_a_sentence(): void
    {
        $this->assertSame('1982-06-03', HeritageText::datesInSentence('train touristique (depuis le 3 juin 1982)')['start']['date']);

        $range = HeritageText::datesInSentence('Un train touristique (Autorail Touristique du Minervois) a circulé sur la ligne entre 1983 et 2003.');
        $this->assertSame(['1983-01-01', '2003-01-01'], [$range['start']['date'], $range['end']['date']]);

        // Années antérieures aux chemins de fer touristiques : ouverture de la ligne, pas de l'exploitation.
        $this->assertSame([], HeritageText::datesInSentence('La ligne est ouverte en 1909.'));
    }

    public function test_dates_in_an_article_stay_close_to_the_operation_and_skip_line_history(): void
    {
        $text = 'Le vélorail du Bourbonnais utilise une partie des voies désaffectées et fermées depuis 1980. '
            .'Cet équipement de vélorail est opéré depuis 2008 et une vingtaine de draisines y circulent.';
        $d = HeritageText::datesInText($text, ['velorail', 'bourbonnais']);
        $this->assertSame('2008-01-01', $d['start']['date']);

        // Voie verte inaugurée à côté d'un vélorail : ce n'est pas la date du vélorail.
        $green = HeritageText::datesInText('Une voie verte est aménagée et inaugurée en 2022 le long de la section exploitée par un vélo-rail.', ['velo rail']);
        $this->assertArrayNotHasKey('start', $green);
    }

    public function test_interruption_then_restart(): void
    {
        $i = HeritageText::interruptionsInText("Après avoir été interrompues à l'été 2008, à la suite de difficultés financières, "
            .'les circulations ont repris entre la gare de Tournon-Saint-Jean et Lamastre le 2 juillet 2013.');
        $this->assertCount(1, $i);
        $this->assertSame('2008-01-01', $i[0]['end']['date']);
        $this->assertSame('2013-07-02', $i[0]['restart']['date']);
    }

    public function test_names_of_the_same_operation_across_sources(): void
    {
        // Même exploitation : IGN « Train Touristique des Cévennes », Wikipédia « Train à vapeur des Cévennes ».
        $this->assertSame(1.0, HeritageText::nameSimilarity('Train Touristique des Cévennes', 'Train à vapeur des Cévennes'));
        $this->assertGreaterThanOrEqual(0.66, HeritageText::nameSimilarity('Chemin de Fer Touristique du Vivarais', 'Chemin de fer du Vivarais, Le vélo-rail des gorges du Doux'));
        // Deux exploitations voisines ne partagent qu'un mot.
        $this->assertLessThan(0.55, HeritageText::nameSimilarity('Chemin de Fer Touristique du Livradois-Forez', 'Chemin de fer du Haut-Forez'));
        $this->assertSame(0.0, HeritageText::nameSimilarity('Pédalorail des Lacs', 'Pédalorail du Grand Pays de Salers'));
    }

    public function test_wikipedia_list_rows_with_rowspan(): void
    {
        $wiki = <<<'WIKI'
            == [[Occitanie]] ==
            {| class="wikitable sortable"
            !Chemin de fer touristique
            !Type
            !Gare dépôt
            !Ligne
            !Section
            !Écartement
            !Département
            !Statut
            |-
            | rowspan="2" |[[Train touristique de l'Ardèche méridionale]] (Viaduc 07)
            | rowspan="2" |Train Touristique
            | rowspan="2" |[[Gare de Vogüé|Vogüé]]
            |[[Ligne du Teil à Alès|Teil à Alès]]
            |[[Saint-Jean-le-Centenier]] - [[Gare de Vogüé]]
            | rowspan="2" |1,435 (voie normale)
            | rowspan="2" |[[Ardèche (département)|Ardèche]]
            | rowspan="2" |Disparu
            |-
            |[[Ligne de Vogüé à Lalevade-d'Ardèche|Vogüé à Lalevade-d'Ardèche]]
            |[[Gare de Vogüé]] - km 693,9
            |-
            |[[Vélorail du Cézallier]]
            |[[Cyclo-draisine|Vélo-rail]]
            |[[Landeyrat]]
            |[[Ligne de Bort-les-Orgues à Neussargues|Bort-les-Orgues à Neussargues]]
            |[[Gare de Lugarde - Marchastel]] - km 522,xxx (Moissac)
            |1,435 (voie normale)
            |[[Cantal (département)|Cantal]]
            |En service
            |}
            WIKI;
        $rows = HeritageText::parseWikiList($wiki);

        $this->assertCount(3, $rows);
        $this->assertSame("Train touristique de l'Ardèche méridionale", $rows[0]['article']);
        $this->assertSame('ended', $rows[0]['status']);
        $this->assertSame('Ligne du Teil à Alès', $rows[0]['lines'][0]['link']);
        $this->assertSame(['Saint-Jean-le-Centenier', 'Gare de Vogüé'], [$rows[0]['lines'][0]['from'], $rows[0]['lines'][0]['to']]);
        // Deuxième ligne de la même exploitation (rowspan) : statut hérité, borne en PK.
        $this->assertSame('ended', $rows[1]['status']);
        $this->assertSame("Ligne de Vogüé à Lalevade-d'Ardèche", $rows[1]['lines'][0]['link']);
        $this->assertSame(693900, $rows[1]['lines'][0]['pk_to']);
        // Un lien « Gare de Lugarde - Marchastel » est UN lieu, pas deux.
        $this->assertSame(['velorail'], $rows[2]['kinds']);
        $this->assertSame('active', $rows[2]['status']);
        $this->assertSame('Gare de Lugarde - Marchastel', $rows[2]['lines'][0]['from']);
        $this->assertSame(522000, $rows[2]['lines'][0]['pk_to']);
    }

    public function test_lignes_oubliees_current_state_and_notes(): void
    {
        $html = <<<'HTML'
            <dl class="historique">
            	<dt>État actuel de la ligne :</dt>
            		<dd><span>Lézan - Anduze :</span>inexploitée</dd>
            		<dd><span>Anduze - Saint-Jean-du-Gard :</span>train touristique (depuis le 3 juin 1982)</dd>
            		<dd><span>Saint-Lô - Condé-sur-Vire :</span>inexploitée (vélorail entre Gourfaleur et Condé-sur-Vire)</dd>
            </dl>
            <ol><li>Depuis 2001, "Les Vélorails du Larzac" exploitent la section La Bastide - L'Hospitalet.</li></ol>
            <h2>Les gares</h2>
            HTML;
        $rows = HeritageText::parseLoFiche($html, 2026);

        $this->assertCount(3, $rows);
        $this->assertSame(['tourist_train', 'Anduze', 'Saint-Jean-du-Gard', '1982-06-03'], [$rows[0]['kind'], $rows[0]['from'], $rows[0]['to'], $rows[0]['start']['date']]);
        // Sous-portion précisée entre parenthèses.
        $this->assertSame(['velorail', 'Gourfaleur', 'Condé-sur-Vire'], [$rows[1]['kind'], $rows[1]['from'], $rows[1]['to']]);
        $this->assertNull($rows[1]['start']);
        // Note datée : nom de l'association, portion, année.
        $this->assertSame(['velorail', 'Les Vélorails du Larzac', 'La Bastide', "L'Hospitalet", '2001-01-01'],
            [$rows[2]['kind'], $rows[2]['name'], $rows[2]['from'], $rows[2]['to'], $rows[2]['start']['date']]);
    }

    public function test_infobox_of_a_tourist_railway(): void
    {
        $facts = HeritageText::infoboxFacts(['nom' => 'Train à Vapeur des Cévennes (Sàrl CITEV)', 'date création' => '1982', 'date fin' => ''], 2026);
        $this->assertSame('1982-01-01', $facts['start']['date']);
        $this->assertArrayNotHasKey('end', $facts);
        $this->assertSame('Sàrl CITEV', $facts['operator']);
    }

    /**
     * Exemple de la demande : ligne fermée en 1969, portion exploitée par une
     * association depuis 1982 → « exploitée par une association » en 1990 et
     * en 2030, fermée (voie en place) en 1970 et 1980.
     */
    public function test_era_maps_show_the_association_only_after_its_start(): void
    {
        $portion = new EraTimeline([
            ['type' => 'opening', 'date' => '1909-05-26', 'source' => 'lignes-oubliees'],
            ['type' => 'closure', 'date' => '1969-12-01', 'source' => 'lignes-oubliees'],
            ['type' => 'heritage_start', 'date' => '1982-06-03', 'source' => 'heritage', 'heritage_kind' => 'tourist_train'],
        ]);
        $this->assertSame(['status' => 'abandoned', 'traffic' => 'unknown'], $this->state($portion, 1970));
        $this->assertSame(['status' => 'abandoned', 'traffic' => 'unknown'], $this->state($portion, 1980));
        $this->assertSame('tourist_train', $this->state($portion, 1990)['heritage']);
        $this->assertSame('in_service', $this->state($portion, 1990)['status']);
        $this->assertSame('tourist_train', $this->state($portion, 2026, true)['heritage']);

        // Vélorail : voie en place, plus de trains → « abandoned », mais exploitée par une association.
        $velorail = new EraTimeline([
            ['type' => 'opening', 'date' => '1896-08-25'],
            ['type' => 'closure', 'date' => '1955-05-22'],
            ['type' => 'heritage_start', 'date' => '2001-01-01', 'source' => 'heritage', 'heritage_kind' => 'velorail'],
        ]);
        $this->assertSame(['status' => 'abandoned', 'traffic' => 'unknown', 'heritage' => 'velorail'], $this->state($velorail, 2010));

        // Interruption 2008 - 2013 (Chemin de fer du Vivarais) : fermée sur la carte de 2010.
        $vivarais = new EraTimeline([
            ['type' => 'opening', 'date' => '1891-01-01'],
            ['type' => 'closure', 'date' => '1968-10-31'],
            ['type' => 'heritage_start', 'date' => '1969-06-14', 'source' => 'heritage', 'heritage_kind' => 'tourist_train'],
            ['type' => 'heritage_end', 'date' => '2008-01-01', 'source' => 'heritage', 'heritage_kind' => 'tourist_train'],
            ['type' => 'heritage_start', 'date' => '2013-07-02', 'source' => 'heritage', 'heritage_kind' => 'tourist_train'],
        ]);
        $this->assertSame('tourist_train', $this->state($vivarais, 2000)['heritage']);
        $this->assertSame(['status' => 'abandoned', 'traffic' => 'unknown'], $this->state($vivarais, 2010));
        $this->assertSame('tourist_train', $this->state($vivarais, 2020)['heritage']);
    }

    /**
     * @return array<string, string>
     */
    private function state(EraTimeline $timeline, int $year, bool $isCurrent = false): array
    {
        return $timeline->stateAt($year, $isCurrent) ?? [];
    }
}

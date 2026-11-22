<?php

namespace Tests\Unit\Media\OpenData;

use App\Services\Media\OpenData\OpenDataMatcher;
use PHPUnit\Framework\TestCase;

/**
 * Rattachement par le titre seul (École des ponts, Getty, Numistral : ni point ni commune fiable) : gare nommée, extrémités
 * d'une ligne, doute laissé sans rattachement. Les titres viennent de notices réelles de ces fonds.
 */
class OpenDataTitleMatcherTest extends TestCase
{
    private OpenDataMatcher $m;

    protected function setUp(): void
    {
        $this->m = new OpenDataMatcher(false);
        $this->m->add(1, 'Murat', 'Murat', 'gare', 'active', 45.11, 2.87);
        $this->m->add(2, 'Vichy', 'Vichy', 'gare', 'active', 46.12, 3.42);
        $this->m->add(3, 'Lyon-Perrache', 'Lyon', 'gare', 'active', 45.7486, 4.8260);
        $this->m->add(4, 'Montargis', 'Montargis', 'gare', 'active', 48.0, 2.74);
        $this->m->add(5, 'Saint-Germain', 'Saint-Germain-en-Laye', 'gare', 'active', 48.9, 2.1);
        $this->m->add(6, 'Saint-Germain', 'Saint-Germain-des-Fossés', 'gare', 'active', 46.2, 3.4);
        $this->m->add(7, 'Nice-Ville', 'Nice', 'gare', 'active', 43.7041, 7.2619);
        $this->m->add(8, 'Nice-Riquier', 'Nice', 'gare', 'active', 43.7, 7.29);
        $this->m->add(9, 'Chateaudun', 'Châteaudun', 'gare', 'active', 48.07, 1.33);
        $this->m->add(40, 'Strasbourg-Ville', 'Strasbourg', 'gare', 'active', 48.585, 7.735);
        $this->m->add(41, 'Strasbourg-Roethig', 'Strasbourg', 'halte', 'active', 48.56, 7.77);
        $this->m->add(42, 'Thann-Saint-Jacques', 'Thann', 'gare', 'active', 47.8, 7.1);
        $this->m->add(43, 'Thann', 'Thann', 'gare', 'active', 47.81, 7.09);
        $this->m->add(50, 'Marchandises', 'Strasbourg', 'gare', 'declassified', 48.59, 7.75);
        $this->m->addLine(10, 'Ligne de Montluçon-Ville à Eygurande-Merlines');
        $this->m->addLine(11, 'Ligne de Moret-Veneux-les-Sablons à Lyon-Perrache');
        $this->m->addLine(12, 'Ligne de Lyon-Perrache à Marseille-Saint-Charles');
        $this->m->addLine(13, 'Ligne de Lyon-Perrache à Marseille-Prado');
        $this->m->addLine(14, 'Ligne de Nevers à Chagny');
        $this->m->addLine(16, 'Ligne de Cahors à Monsempron-Libos');
        $this->m->add(30, 'Pau', 'Pau', 'gare', 'active', 43.29, -0.37);
        $this->m->add(31, 'Oloron-Sainte-Marie', 'Oloron-Sainte-Marie', 'gare', 'active', 43.19, -0.6);
        $this->m->addLine(17, 'Ligne de Pau à Canfranc (frontière)');
        $this->m->addLine(18, 'Ligne de Pau à Bayonne');
        $this->m->addServing(30, 17);
        $this->m->addServing(30, 18);
        $this->m->addServing(31, 17);
        // « Saint-Étienne » débute les noms de gares de deux communes : aucune ville ne s'impose, aucune ligne n'est déduite.
        $this->m->add(32, 'Saint-Étienne-Châteaucreux', 'Saint-Étienne', 'gare', 'active', 45.44, 4.4);
        $this->m->add(33, 'Saint-Étienne-de-Montluc', 'Saint-Étienne-de-Montluc', 'gare', 'active', 47.27, -1.78);
        $this->m->add(34, 'Lyon-Brotteaux', 'Lyon', 'gare', 'active', 45.76, 4.86);
        $this->m->addLine(19, 'Ligne de Moret-Veneux-les-Sablons à Lyon-Perrache');
        $this->m->addServing(32, 19);
        $this->m->addServing(34, 19);
        $this->m->addLine(15, 'Ligne de Paris à Strasbourg (LGV)');
    }

    /** @return array<string, mixed> */
    private static function c(string $title): array
    {
        return ['commune' => null, 'title' => $title, 'title_alt' => null, 'station_hint' => null, 'caption' => null, 'themes' => null, 'subjects' => null, 'lat' => null, 'lng' => null, 'point_precise' => false];
    }

    public function test_a_named_station_is_found_by_its_exact_name(): void
    {
        $r = $this->m->matchByTitle(self::c("Gare de Murat. Ligne du Cantal. Compagnie d'Orléans"));

        $this->assertSame(['station', 1, 'nom-titre'], [$r['kind'], $r['id'], $r['method']]);
    }

    public function test_a_parenthesis_completes_the_station_name(): void
    {
        $r = $this->m->matchByTitle(self::c('Gare de Lyon (Perrache)'));

        $this->assertSame([3, 'nom-titre'], [$r['id'], $r['method']]);
    }

    public function test_the_getty_gare_de_vichy_is_found_and_a_station_in_a_longer_name_is_not(): void
    {
        $this->assertSame(2, $this->m->matchByTitle(self::c('Gare de Vichy, Façade sur La Cour'))['id']);
        // « Saint-Étienne » n'est pas une gare de la liste : aucun rapprochement par morceau de nom.
        $this->assertSame('gare_inconnue', $this->m->matchByTitle(self::c('Gare de Saint-Étienne'))['reason']);
    }

    public function test_the_main_station_of_a_town_is_its_ville_station_when_alone(): void
    {
        $r = $this->m->matchByTitle(self::c('Gare de Nice'));
        $this->assertSame([7, 'nom-titre-ville', 'basse'], [$r['id'], $r['method'], $r['confidence']]);

        // Deux « Lyon-… » : pas de « Lyon-Ville », rien n'est choisi.
        $this->assertSame('gare_inconnue', $this->m->matchByTitle(self::c('Gare de Lyon'))['reason']);
        // Un titre déjà nettoyé (« S. T. [Gare de Chateaudun] » devient « Gare de Chateaudun »).
        $this->assertSame(9, $this->m->matchByTitle(self::c('Gare de Chateaudun'))['id']);
    }

    public function test_a_station_cited_as_a_viewpoint_or_in_parentheses_is_not_the_subject(): void
    {
        $this->m->add(20, 'Orléans', 'Orléans', 'gare', 'active', 47.9, 1.9);
        // Titre réel de l'École des ponts (btv1b104843374) : le viaduc d'Austerlitz vu de la gare d'Orléans (Paris-Austerlitz).
        $r = $this->m->matchByTitle(self::c("Construction du chemin de fer métropolitain municipal de Paris. Viaduc d'Austerlitz. Vue d'ensemble (prise des combles de la gare d'Orléans)"));
        $this->assertNull($r['kind']);

        $this->assertSame(20, $this->m->matchByTitle(self::c('Gare d\'Orléans'))['id']);
        // La gare d'Orléans de Paris est Paris-Austerlitz : un titre sur Paris ou son métropolitain ne désigne pas la ville d'Orléans.
        $r = $this->m->matchByTitle(self::c('Construction du chemin de fer métropolitain municipal de Paris. Traversée des dépendances de la gare d\'Orléans'));
        $this->assertSame('gare_de_paris', $r['reason']);
        $this->assertNull($this->m->matchByTitle(self::c('Vue prise de la gare d\'Orléans'))['kind']);
    }

    public function test_a_hand_entered_alias_names_a_station_the_fund_does_not_name_like_vff(): void
    {
        $aliases = ['/\bStrasbourg\b.*\bgare centrale\b/iu' => 'Strasbourg-Ville'];
        // Titre réel de Numistral (btv1b10222170s) : la gare actuelle de Strasbourg.
        $r = $this->m->match(['title_aliases' => $aliases] + self::c('Strasbourg - La Gare centrale'), ['title_match' => true, 'title_aliases' => $aliases]);

        $this->assertSame([40, 'alias-titre'], [$r['id'], $r['method']]);
        // Sans l'alias, le nom seul ne dit pas quelle gare de Strasbourg.
        $this->assertNull($this->m->matchByTitle(self::c('Strasbourg - La Gare centrale'))['kind']);
    }

    public function test_the_station_name_after_chemin_de_fer_a_is_read_unless_it_is_only_a_viewpoint(): void
    {
        // Titres réels de Numistral : « Station de chemin de fer à Thann » ; « vis à vis de la station du chemin de fer à ... ».
        $this->assertSame('Thann', $this->m->matchByTitle(self::c('Station de chemin de fer à Thann'))['name']);
        $r = $this->m->matchByTitle(self::c("Vue de la chaine des Vosges prise de l'hôtel de l'Europe, vis à vis de la station du chemin de fer à Thann"));
        $this->assertNull($r['kind']);
    }

    public function test_a_parenthesis_alone_never_names_a_station(): void
    {
        // Titre réel de l'École des ponts : « (marchandises) » n'est pas la gare nommée « Marchandises » de Strasbourg.
        $r = $this->m->matchByTitle(self::c('Raccordement avec la gare des Batignolles (marchandises). Passage inférieur sur l\'avenue de Clichy'));

        $this->assertNull($r['kind']);
    }

    public function test_two_stations_with_the_same_name_stay_unattached(): void
    {
        $this->assertSame('noms_ambigus', $this->m->matchByTitle(self::c('Gare de Saint-Germain'))['reason']);
    }

    public function test_a_line_is_found_by_its_two_ends_in_either_order(): void
    {
        $r = $this->m->matchByTitle(self::c('Chemin de fer de Montluçon à Eygurande. Viaduc de la Tardes'));
        $this->assertSame(['line', 10, 'ligne-titre-debut', 'basse'], [$r['kind'], $r['id'], $r['method'], $r['confidence']]);

        $r = $this->m->matchByTitle(self::c('Ligne de Chagny à Nevers. Viaduc'));
        $this->assertSame(['line', 14, 'ligne-titre', 'moyenne'], [$r['kind'], $r['id'], $r['method'], $r['confidence']]);
    }

    public function test_a_high_speed_line_never_takes_the_views_of_an_old_line_of_the_same_towns(): void
    {
        // Titre réel de Numistral (btv1b10108449w) : l'inauguration de 1852 n'est pas la LGV Est.
        $r = $this->m->matchByTitle(self::c('Inauguration du chemin de fer de Paris à Strasbourg, le 18 juillet 1852'));
        $this->assertNull($r['kind']);
        $this->assertSame('ligne_inconnue', $r['reason']);

        $r = $this->m->matchByTitle(self::c('Ligne de Paris à Strasbourg (LGV) : mise en service'));
        $this->assertSame(15, $r['id']);
    }

    public function test_an_end_that_the_line_name_contains_is_read_as_a_last_resort(): void
    {
        // Titre réel de l'École des ponts : « Pont de Luzech. Chemin de fer de Cahors à Libos » (la ligne de VFF finit à Monsempron-Libos).
        $r = $this->m->matchByTitle(self::c('Pont de Luzech. Chemin de fer de Cahors à Libos'));

        $this->assertSame(['line', 16, 'ligne-titre-contenu', 'basse'], [$r['kind'], $r['id'], $r['method'], $r['confidence']]);
    }

    public function test_the_only_line_serving_both_named_towns_is_taken_when_no_line_has_them_for_ends(): void
    {
        // Titre réel de l'École des ponts : « Chemin de fer de Pau à Oloron » (ligne de Pau à Canfranc).
        $r = $this->m->matchByTitle(self::c('Chemin de fer de Pau à Oloron. Souterrain de Bélair'));

        $this->assertSame(['line', 17, 'ligne-titre-gares', 'basse'], [$r['kind'], $r['id'], $r['method'], $r['confidence']]);
        // Titre réel du Getty : « Chemin de Fer de Saint-Étienne à Lyon » ne désigne pas une ligne qui dessert deux gares quelconques.
        $r = $this->m->matchByTitle(self::c('Tête du Souterrain de Rive-de-Gier, de Chemin de Fer de Saint-Étienne à Lyon'));
        $this->assertNull($r['kind']);
        // Tarbes n'est pas une gare connue ici : rien n'est choisi.
        $this->assertSame('ligne_inconnue', $this->m->matchByTitle(self::c('Chemin de fer de Pau à Tarbes'))['reason']);
    }

    public function test_ends_that_fit_several_lines_stay_unattached(): void
    {
        // Lyon-Perrache à Marseille : deux lignes de VFF commencent ainsi, aucune ne s'impose.
        $r = $this->m->matchByTitle(self::c('Ligne de Lyon à Marseille. Viaduc de la Voulte'));

        $this->assertNull($r['kind']);
        $this->assertSame('lignes_multiples', $r['reason']);
    }

    public function test_a_viaduct_without_line_or_station_is_not_attached(): void
    {
        $r = $this->m->matchByTitle(self::c('Viaduc de la Villatte'));

        $this->assertNull($r['kind']);
        $this->assertSame('sans_nom_dans_le_titre', $r['reason']);
    }

    public function test_a_title_that_starts_with_gare_never_falls_through_to_a_line(): void
    {
        $r = $this->m->matchByTitle(self::c('Gare de Pont-de-L\'Ane'));

        $this->assertNull($r['kind']);
        $this->assertSame('gare_inconnue', $r['reason']);
    }

    public function test_the_title_match_is_only_a_fallback_after_the_commune_rules_and_only_when_asked(): void
    {
        // Avec commune : les règles de commune passent d'abord (nom de gare lu dans le texte).
        $r = $this->m->match(self::c('Gare de Vichy') + [], ['title_match' => true]);
        $this->assertSame('nom-titre', $r['method']);

        $r = $this->m->match(['commune' => 'Vichy'] + self::c('Vue de la Gare'), ['title_match' => true]);
        $this->assertSame('nom-commune', $r['method']);

        $r = $this->m->match(self::c('Gare de Vichy'), []);
        $this->assertNull($r['kind']);
    }
}

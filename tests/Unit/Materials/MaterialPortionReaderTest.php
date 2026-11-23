<?php

namespace Tests\Unit\Materials;

use App\Services\Materials\MaterialPortionReader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Relations et portions nommées par la phrase qui date une période, lues sur des extraits réels des articles
 * Wikipédia (texte brut, comme `line_material_periods.source_excerpt`), révision citée pour chacun.
 */
class MaterialPortionReaderTest extends TestCase
{
    /** @return array<string, array{0: string, 1: list<list<string>>}> */
    public static function excerpts(): array
    {
        return [
            // Regio 2N, révision 241123224, « Dessertes prévues et commandes » : la relation à la barre oblique
            // (Cherbourg ou Trouville - Deauville) est écartée, Paris - Rouen - Le Havre reste.
            'Omneo Premium, Normandie' => ['Commandées à 40 exemplaires en 2016, les Z 56600 sont progressivement mises en service, depuis le 17 février 2020, sur les lignes Paris - Caen - Cherbourg / Trouville - Deauville et Paris - Rouen - Le Havre',
                [['Paris', 'Rouen', 'Le Havre']]],
            // Regio 2N, même révision : une ligne de Transilien n'est pas une suite de gares.
            'Z 2N remplacées sur la ligne R' => ['En décembre 2014, le STIF annonce une commande de matériel Regio 2N qui aura pour but de remplacer l\'intégralité des automotrices de type Z 2N (Z 5600 et Z 20500) affectées à la ligne R du Transilien ainsi que les Z 5300 restantes.', []],
            // Ligne de Mantes-la-Jolie à Cherbourg, révision 241344631 : une vitesse, pas un parcours.
            'Omneo Premium à 200 km/h' => ['Commandées à 40 exemplaires, ces rames Omneo Premium (Z 56600) permettent, elles-aussi, d\'atteindre une vitesse commerciale de 200 km/h sur les portions de voies le permettant.', []],
            // Ligne de Lisieux à Trouville - Deauville, révision 235342270.
            'Omneo Premium, vitesse entre Bernay et Lisieux' => ['Depuis le retrait des voitures Corail, les dessertes à destination de Trouville-Deaville sont assurées avec des Omneo Premium, version grande ligne de la plateforme Régio2N, apte à 200 km/h entre Bernay et Lisieux.', []],
            // BB 22200, révision 241405558, « Lignes desservies ».
            'BB 22200, Lyon - Genève' => ['Lyon - Ambérieu - Culoz - Bellegarde - Genève (en service international, jusqu\'au 10 décembre 2023)',
                [['Lyon', 'Ambérieu', 'Culoz', 'Bellegarde', 'Genève']]],
            // BB 22200, même révision, « Services effectués » : noms à tiret collé, ce sont des liens vers des lignes.
            'BB 22200, Paris-Nantes collé' => ['Elles ont circulé à partir de 1983 sur Paris-Nantes et Paris-Bordeaux ; ces services ayant pris fin en 1989 et 1990 à la suite de la mise en service du TGV Atlantique.', []],
            'BB 22200, de Marseille à Nice et Vintimille' => ['Leur mise en service est intervenue en 1977 à Marseille, pour service de Marseille à Nice et Vintimille, ainsi qu\'à Dijon.',
                [['Marseille', 'Nice', 'Vintimille']]],
            // X 73500, révision 240915381, « Relations effectuées > TER > Normandie ».
            'X 73500, Le Havre - Rolleville' => ['Le Havre – Rolleville (jusqu\'en septembre 2024)', [['Le Havre', 'Rolleville']]],
            // X 73500, même révision, « Service » : un nom officiel à tiret entouré d'espaces reste à réunir (MaterialPeriodPortion).
            'X 73500, Chambéry - Challes-les-Eaux' => ['Chambéry - Challes-les-Eaux – Ambérieu-en-Bugey', [['Chambéry', 'Challes-les-Eaux', 'Ambérieu-en-Bugey']]],
            // Z 5300, révision 236187024 : arrêt intermédiaire « via ».
            'Z 5300, via Moret' => ['(1972 - 2011) : Paris - Montereau via Moret-Veneux-les-Sablons,', [['Paris', 'Moret-Veneux-les-Sablons', 'Montereau']]],
            // Z 3700, révision 231423841.
            'Z 3700, entre Versailles et Juvisy' => ['À partir de 1969, elles sont engagées sur le tronçon sud de la ligne de la grande ceinture de Paris entre Versailles-Chantiers et Juvisy',
                [['Versailles-Chantiers', 'Juvisy']]],
            // Ligne des Aubrais - Orléans à Orléans, révision 212104765 : deux portions, dans l'ordre.
            'Z 5300, deux navettes' => ['Jusqu\'en décembre 2011, des navettes composées d\'automotrices inox 3 caisses (Z 5300) circulaient entre Orléans et Les Aubrais ainsi qu\'entre Tours et Saint-Pierre.',
                [['Orléans', 'Les Aubrais'], ['Tours', 'Saint-Pierre']]],
            // 1CC1 3700, révision 239232409 : le nom d'une compagnie, pas un parcours.
            '1CC1 3700, compagnie du PO' => ['De 1943 à 1945, quatre locomotives sont temporairement détachées au dépôt de Limoges pour appuyer le parc de la Compagnie du chemin de fer de Paris à Orléans', []],
            // Phrases réduites : dépôt, autre terminus au choix, dates, sigle de service.
            'dépôt' => ['A1AA1A 68000 du dépôt de Chambéry, utilisées sur Grenoble - Chambéry (de 1963 à 1969).', [['Grenoble', 'Chambéry']]],
            'autre terminus' => ['trains Express et Grandes Lignes entre Paris et Toulouse ou Bordeaux', []],
            'dates seules' => ['Les X 2400 circulent de 1951 à 1983, entre 1962 et 1990 sur certains trains.', []],
            'sigle de service' => ['TER Paris - Rouen - Dieppe jusqu\'en 2014', [['Paris', 'Rouen', 'Dieppe']]],
        ];
    }

    /** @param list<list<string>> $expected */
    #[DataProvider('excerpts')]
    public function test_reads_the_relations_named_by_the_sentence(string $text, array $expected): void
    {
        $this->assertSame($expected, (new MaterialPortionReader)->relations($text));
    }
}

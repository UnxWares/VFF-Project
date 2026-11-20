<?php

namespace Tests\Unit;

use App\Services\SecondaryNetworkParser;
use App\Services\WikipediaLineParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tableaux wiki des articles de réseaux secondaires (SecondaryNetworkParser) :
 * type de chaque colonne d'après son en-tête, dépliage des rowspan /
 * colspan, séparateurs de cellules hors liens et modèles, attributs de
 * cellule, et fiches datées produites. Aucune base, aucun réseau.
 */
class SecondaryNetworkTablesTest extends TestCase
{
    private SecondaryNetworkParser $p;

    protected function setUp(): void
    {
        $this->p = new SecondaryNetworkParser(new WikipediaLineParser);
    }

    private function invoke(string $method, mixed ...$args): mixed
    {
        return (new \ReflectionMethod(SecondaryNetworkParser::class, $method))->invoke($this->p, ...$args);
    }

    /** Fiches réduites à [lieux, origine, km, [[type, date, précision, confiance, plage]…]]. */
    private function records(string $wikitext, string $title = 'Chemins de fer départementaux de la Somme'): array
    {
        return array_map(fn ($r) => [
            array_column($r['places'], 'label'), $r['origin'], $r['km'],
            array_map(fn ($e) => [$e['type'], $e['date'], $e['precision'], $e['confidence'], $e['range']], $r['events']),
        ], $this->p->parse($wikitext, $title)['records']);
    }

    // ─── type de colonne ─────────────────────────────────────────────────

    /** @return array<string, array{0: string, 1: string}> */
    public static function headers(): array
    {
        return [
            'vide' => ['  ', 'ignore'],
            'concession' => ['Concession', 'ignore'],
            'DUP' => ['D.U.P.', 'ignore'],
            'décret' => ['Décret', 'ignore'],
            'numéro' => ['N°', 'ignore'],
            'exploitant' => ['Exploitant', 'ignore'],
            'écartement' => ['Écartement', 'ignore'],
            'gares' => ['Gares desservies', 'ignore'],
            'longueur' => ['Longueur (km)', 'km'],
            'km' => ['km', 'km'],
            'distance' => ['Distance', 'km'],
            'km ouverts' => ['km ouverts', 'opening'],
            'réouverture' => ['Réouverture', 'reopening'],
            'réouverture voyageurs' => ['Réouverture aux voyageurs', 'reopening_passenger'],
            'remise en service fret' => ['Remise en service (fret)', 'reopening_freight'],
            'réouverture mixte' => ['Réouverture voyageurs et marchandises', 'reopening'],
            'ouverture' => ['Date d\'ouverture', 'opening'],
            'mise en service' => ['Mise en service', 'opening'],
            'mise en exploitation' => ['Mise en exploitation', 'opening'],
            'inauguration' => ['Inauguration', 'opening'],
            'mise en circulation' => ['Mise en circulation', 'opening'],
            'ouverture voyageurs' => ['Ouverture aux voyageurs', 'opening_passenger'],
            'ouverture PV' => ['Ouverture PV', 'opening_freight'],
            'ouverture passagers et fret' => ['Ouverture passagers / fret', 'opening'],
            'déclassement' => ['Déclassement', 'declassification'],
            'dépose' => ['Dépose des voies', 'dismantling'],
            'déferrement' => ['Déferrement', 'dismantling'],
            'fermeture' => ['Fermeture', 'closure'],
            'fermeture voyageurs' => ['Fermeture  aux\nvoyageurs', 'closure_passenger'],
            'fermeture marchandises' => ['Fermeture aux marchandises', 'closure_freight'],
            'fermeture et déclassement' => ['Fermeture et déclassement', 'closure'],
            'suppression' => ['Suppression', 'closure'],
            'fin d’exploitation' => ['Fin d\'exploitation', 'closure'],
            'fin du service' => ['Fin du service', 'closure'],
            'arrêt' => ['Arrêt de l\'exploitation', 'closure'],
            'période' => ['Période', 'range'],
            'exploitation' => ['Exploitation', 'range'],
            'en service' => ['En service', 'range'],
            'dates' => ['Dates', 'range'],
            'années' => ['Années', 'range'],
            'ligne' => ['Ligne', 'route'],
            'section' => ['Section', 'route'],
            'tronçon' => ['Tronçon', 'route'],
            'parcours' => ['Parcours', 'route'],
            'itinéraire' => ['Itinéraire', 'route'],
            'relation' => ['Relation', 'route'],
            'trajet' => ['Trajet', 'route'],
            'nom' => ['Nom', 'route'],
            'origine' => ['Origine', 'route'],
            'liaison' => ['Liaison', 'route'],
            'desserte' => ['Desserte', 'route'],
            'autre' => ['Photographie', 'ignore'],
        ];
    }

    #[DataProvider('headers')]
    public function test_column_type_from_its_header(string $header, string $expected): void
    {
        $this->assertSame($expected, $this->invoke('columnType', str_replace('\n', "\n", $header)));
    }

    // ─── lignes de tableau ───────────────────────────────────────────────

    public function test_rows_unfold_rowspan_and_colspan(): void
    {
        $rows = $this->invoke('tableRows', explode("\n", <<<'WT'
            {| class="wikitable"
            |+ Légende
            ! rowspan="2" | Ligne !! colspan="2" | Fermeture
            |-
            ! aux voyageurs !! aux marchandises
            |-
            | A - B || rowspan=2 | 1937 || 1950
            |-
            | C - D || 1951
            |-
            | style="text-align:right" | E - F
            suite de la cellule
            || 1938
            |}
            | ignoré
            WT));

        $this->assertSame([
            ['header' => true, 'cells' => [0 => ' Ligne ', 1 => ' Fermeture', 2 => ' Fermeture']],
            ['header' => true, 'cells' => [0 => ' Ligne ', 1 => ' aux voyageurs ', 2 => ' aux marchandises']],
            ['header' => false, 'cells' => [0 => ' A - B ', 1 => ' 1937 ', 2 => ' 1950']],
            ['header' => false, 'cells' => [0 => ' C - D ', 1 => ' 1937 ', 2 => ' 1951']],
            ['header' => false, 'cells' => [0 => " E - F\nsuite de la cellule", 1 => ' 1938']],
        ], $rows);
    }

    public function test_spans_are_bounded(): void
    {
        $rows = $this->invoke('tableRows', ['{|', '! colspan="99" | X', '|-', '| rowspan="0" | a || b', '|}']);

        $this->assertCount(12, $rows[0]['cells'], 'colspan plafonné à 12');
        $this->assertSame([0 => ' a ', 1 => ' b'], $rows[1]['cells'], 'rowspan nul = 1');
    }

    public function test_row_header_cell_makes_a_data_row(): void
    {
        $rows = $this->invoke('tableRows', ['{|', '! Ligne !! Ouverture', '|-', '! scope="row" | A - B', '| 1900', '|}']);

        $this->assertTrue($rows[0]['header']);
        $this->assertFalse($rows[1]['header']);
        $this->assertSame([0 => ' A - B', 1 => ' 1900'], $rows[1]['cells']);
    }

    public function test_cells_are_split_outside_links_and_templates(): void
    {
        $this->assertSame([' [[Albert|Albert]] - Ham ', ' {{date|1|mai|1900|en}} ', ' x'],
            $this->invoke('splitCells', ' [[Albert|Albert]] - Ham || {{date|1|mai|1900|en}} || x', false));
        $this->assertSame([' a !! b'], $this->invoke('splitCells', ' a !! b', false), '« !! » ne sépare que les en-têtes');
        $this->assertSame([' a ', ' b'], $this->invoke('splitCells', ' a !! b', true));
        $this->assertSame(['a}} ', ' b'], $this->invoke('splitCells', 'a}} || b', false), 'accolades orphelines sans effet');
        $this->assertSame(['a]]', 'b'], $this->invoke('splitCells', 'a]]||b', false));
    }

    public function test_cell_attributes_are_separated_from_content(): void
    {
        $this->assertSame(['style="color:red" ', ' 1937'], $this->invoke('splitCellAttributes', 'style="color:red" | 1937'));
        $this->assertSame(['rowspan=2 align=\'left\' ', ' x'], $this->invoke('splitCellAttributes', 'rowspan=2 align=\'left\' | x'));
        $this->assertSame(['nowrap ', ' x'], $this->invoke('splitCellAttributes', 'nowrap | x'));
        $this->assertSame(['', ' [[A|B]] - C'], $this->invoke('splitCellAttributes', ' [[A|B]] - C'));
        $this->assertSame(['', ' {{nobr|a|b}}'], $this->invoke('splitCellAttributes', ' {{nobr|a|b}}'));
        $this->assertSame(['', 'Ligne de A | B'], $this->invoke('splitCellAttributes', 'Ligne de A | B'), 'pas un attribut');
    }

    // ─── fiches d'un tableau ─────────────────────────────────────────────

    public function test_table_rows_become_dated_records(): void
    {
        $w = <<<'WT'
            == Les lignes ==
            {| class="wikitable sortable"
            ! rowspan="2" | Ligne !! rowspan="2" | Longueur !! rowspan="2" | Ouverture !! colspan="2" | Fermeture !! rowspan="2" | Concession
            |-
            ! aux voyageurs !! aux marchandises
            |-
            | [[Abbeville]] - Dompierre || 34,5 km || 15 novembre 1892 || 1 avril 1937 || 1947 || 1888
            |-
            | Albert - Ham || 40 || 1889-1890 || rowspan="2" | 1949 || 1951 ||
            |-
            | Doullens - Beauval || 12 || 1889 || 1938 (voyageurs), 1948 (marchandises) || 1885
            |-
            | Rue - Berck || || || || ||
            |-
            | Gare d'Amiens || || 1890 || || ||
            |}
            WT;

        $this->assertSame([
            [['Abbeville', 'Dompierre'], 'tableau', 34.5, [
                ['opening', '1892-11-15', 'day', 'high', false],
                ['closure_passenger', '1937-04-01', 'day', 'high', false],
                ['closure_freight', '1947-01-01', 'year', 'medium', false],
            ]],
            [['Albert', 'Ham'], 'tableau', 40.0, [
                ['opening', '1889-01-01', 'year', 'low', true],
                ['closure_passenger', '1949-01-01', 'year', 'medium', false],
                ['closure_freight', '1951-01-01', 'year', 'medium', false],
            ]],
            [['Doullens', 'Beauval'], 'tableau', 12.0, [
                ['opening', '1889-01-01', 'year', 'medium', false],
                ['closure_passenger', '1949-01-01', 'year', 'medium', false],
                ['closure_passenger', '1938-01-01', 'year', 'medium', false],
                ['closure_freight', '1948-01-01', 'year', 'medium', false],
            ]],
        ], $this->records($w));

        $rec = $this->p->parse($w, 'Chemins de fer départementaux de la Somme')['records'][0];
        $this->assertSame('Abbeville', $rec['places'][0]['target']);
        $this->assertSame('Les lignes', $rec['heading']);
        $this->assertSame('Ligne Ligne : Abbeville - Dompierre | Longueur Longueur : 34,5 km | Ouverture Ouverture : 15 novembre 1892 | Fermeture aux voyageurs : 1 avril 1937 | Fermeture aux marchandises : 1947 | Concession Concession : 1888', $rec['quote']);
        $this->assertSame('Fermeture aux voyageurs : 1 avril 1937', $rec['events'][1]['quote']);
        $this->assertSame('tableau', $rec['events'][1]['origin']);
    }

    public function test_cell_qualifiers_and_ranges(): void
    {
        $w = <<<'WT'
            {| class="wikitable"
            ! Section !! Fermeture !! Ouverture aux voyageurs !! Période d'exploitation
            |-
            | Corbigny - Alligny || 1939 aux voyageurs || 1901-1903 ||
            |-
            | Saint-Laurent - Saint-Trivier || 1936 || || 1897 - 1936
            |}
            WT;

        $this->assertSame([
            [['Corbigny', 'Alligny'], 'tableau', null, [
                ['closure_passenger', '1939-01-01', 'year', 'medium', false],
                ['opening_passenger', '1901-01-01', 'year', 'low', true],
            ]],
            [['Saint-Laurent', 'Saint-Trivier'], 'tableau', null, [
                ['closure', '1936-01-01', 'year', 'medium', false],
                ['opening', '1897-01-01', 'year', 'low', true],
                ['closure', '1936-01-01', 'year', 'low', true],
            ]],
        ], $this->records($w));
    }

    public function test_route_found_without_a_route_column(): void
    {
        $w = "{| class=\"wikitable\"\n! N° !! Desservie !! Ouverture\n|-\n| 3 || Rue - Berck, 13 km || 1909\n|}";

        $this->assertSame([[['Rue', 'Berck'], 'tableau', 13.0, [['opening', '1909-01-01', 'year', 'medium', false]]]], $this->records($w));
    }

    public function test_tables_without_header_dates_or_rows_give_nothing(): void
    {
        $this->assertSame([], $this->records("{| class=\"wikitable\"\n| Rue - Berck || 1909\n|-\n| Albert - Ham || 1910\n|}"), 'sans en-tête');
        $this->assertSame([], $this->records("{| class=\"wikitable\"\n! Ligne !! Longueur !! Exploitant\n|-\n| Rue - Berck || 13 || CFS\n|}"), 'sans colonne de date');
        $this->assertSame([], $this->records("{| class=\"wikitable\"\n! Ligne !! Ouverture\n|}"), 'en-tête seul');
        $this->assertSame([], $this->records("{| class=\"wikitable\"\n! Ligne !! Ouverture\n|-\n| la ligne ouverte en || 1909\n|}"), 'pas un itinéraire');
    }
}

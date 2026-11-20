<?php

namespace Tests\Unit;

use App\Services\RouteDiagramStations;
use App\Services\SectionResolver;
use App\Services\WikipediaLineParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Gares des schémas de ligne Wikipédia (RouteDiagramStations) et segments
 * déformés d'un calage de schéma (SectionResolver::distortedPkRanges), règles
 * pures : quelles lignes d'un schéma sont des gares, lecture des PK, séries de
 * PK cohérentes, gares d'un article qui décrit plusieurs lignes (une par code).
 */
class RouteDiagramStationsTest extends TestCase
{
    private function diagrams(): RouteDiagramStations
    {
        return new RouteDiagramStations(new WikipediaLineParser);
    }

    /** @param  list<array{0: string, 1: ?int}>  $stations */
    private static function rows(array $stations): array
    {
        return array_map(fn ($s, $i) => ['name' => $s[0], 'pk_m' => $s[1], 'icon' => 'BHF', 'row' => $i], $stations, array_keys($stations));
    }

    // ─── parse ───────────────────────────────────────────────────────────

    public function test_stations_and_halts_are_kept_with_their_pk_in_diagram_order(): void
    {
        $wikitext = <<<'WT'
{{BS-début|Ligne de Alpha à Gamma}}
{{BS|KBHFa|0,0|[[Gare d'Alpha|Alpha]]|}}
{{BS|BHF|5,2|Beta (Orne)}}
{{BS|exHST|9,75|Halte de Delta}}
{{BS|DST|11,0|Poste de Zeta}}
{{BS|ABZgl|12,0|Bifurcation d'Epsilon}}
{{BS|BHF|14,0|Vers Omega}}
{{BS|eBHF|16,4|Tunnel de Kappa}}
{{BS|HST|18,0|Pont-l'Abbé}}
{{BS|BHF|19,0|Bifurcation 2}}
{{BS|KBHFe|20,500|Gamma}}
{{BS-fin}}
WT;

        $tables = $this->diagrams()->parse($wikitext);

        $this->assertCount(1, $tables);
        $this->assertSame(
            [['Alpha', 0], ['Beta', 5200], ['Halte de Delta', 9750], ['Pont-l\'Abbé', 18000], ['Gamma', 20500]],
            array_map(fn ($s) => [$s['name'], $s['pk_m']], $tables[0]['stations']),
        );
    }

    public function test_multi_column_rows_read_the_pk_and_name_after_the_icons(): void
    {
        $wikitext = "{{BS-début}}\n{{BS3|STR|BHF|STR|12,8|Lambda|}}\n{{BS3|STR|HST|STR|15,1|Mu}}\n{{BS-fin}}";

        $stations = $this->diagrams()->parse($wikitext)[0]['stations'];

        $this->assertSame([['Lambda', 12800], ['Mu', 15100]], array_map(fn ($s) => [$s['name'], $s['pk_m']], $stations));
    }

    public function test_each_diagram_of_an_article_is_a_separate_table(): void
    {
        $wikitext = "{{BS-début}}\n{{BS|BHF|0|Alpha}}\n{{BS|BHF|3|Beta}}\n{{BS-fin}}\n"
            ."Texte.\n{{BS-début}}\n{{BS|BHF|0|Gamma}}\n{{BS|BHF|4|Delta}}\n{{BS-fin}}";

        $tables = $this->diagrams()->parse($wikitext);

        $this->assertSame([1, 2], array_column($tables, 'table'));
        $this->assertSame(['Gamma', 'Delta'], array_column($tables[1]['stations'], 'name'));
    }

    public function test_commented_rows_are_ignored(): void
    {
        $wikitext = "{{BS-début}}\n<!-- {{BS|BHF|1|Fantôme}} -->\n{{BS|BHF|2|Réelle}}\n{{BS-fin}}";

        $this->assertSame(['Réelle'], array_column($this->diagrams()->parse($wikitext)[0]['stations'], 'name'));
    }

    // ─── pk ──────────────────────────────────────────────────────────────

    /** @return array<string, array{0: string, 1: ?int}> */
    public static function pkProvider(): array
    {
        return [
            'virgule décimale' => ['12,8', 12800],
            'point décimal' => ['7.742', 7742],
            'trois décimales' => ['0,375', 375],
            'entier' => ['45', 45000],
            'deux PK, le dernier est celui de la ligne' => ['393,21 -00,0', 0],
            'modèle BSkm, le dernier' => ['{{BSkm|102,468|0,000}}', 0],
            'gras' => ["'''15,7'''", 15700],
            'vide' => ['', null],
            'texte sans nombre' => ['fermée', null],
        ];
    }

    #[DataProvider('pkProvider')]
    public function test_pk_of_a_diagram_cell(string $cell, ?int $expected): void
    {
        $this->assertSame($expected, RouteDiagramStations::pk($cell));
    }

    // ─── runs ────────────────────────────────────────────────────────────

    public function test_bskm_at_the_far_terminus_keeps_the_pk_equal_to_the_line_length(): void
    {
        // Ligne de Montsecret - Vassy aux Maures : « longueur = 19.6 » ; Les Maures donne son PK
        // sur cette ligne (19,6xx) PUIS celui sur la ligne de Vire à Romagny (17,525).
        $wikitext = <<<'WT'
{{Infobox Ligne ferroviaire
| nomligne = Montsecret - Vassy aux Maures
| longueur              = 19.6
}}
{{BS-début}}
{{BS1ebis|eBHF|{{BSkm|56,770|0,000}}|'''[[Gare de Montsecret - Vassy|Montsecret - Vassy]]'''|''(146m)''}}
{{BS1ebis|exHST|14,4xx|Le Paillot}}
{{BS1ebis|exBHF|{{BSkm|19,6xx|17,525}}|'''[[Chaulieu (Manche)|Les Maures]]'''|''(301m)''}}
{{BS-fin}}
WT;

        $stations = $this->diagrams()->parse($wikitext)[0]['stations'];

        $this->assertSame([['Montsecret - Vassy', 0], ['Le Paillot', 14400], ['Les Maures', 19600]],
            array_map(fn ($s) => [$s['name'], $s['pk_m']], $stations));
        $this->assertSame(19600, RouteDiagramStations::lineLengthM($wikitext));
        $line = (new WikipediaLineParser)->parseRouteDiagram(str_replace(['19,6xx', '14,4xx'], ['19,600', '14,400'], $wikitext));
        $this->assertSame([0, 14400, 19600], array_column($line, 'pk_m'));
    }

    public function test_bskm_choice_keeps_the_last_value_otherwise(): void
    {
        $this->assertSame(0, RouteDiagramStations::bskmChoice([56770, 0], 19600));
        $this->assertSame(17525, RouteDiagramStations::bskmChoice([12000, 17525], null));
        $this->assertSame(17525, RouteDiagramStations::bskmChoice([12000, 17525], 40000));
        // Les deux valeurs proches de la longueur : la dernière.
        $this->assertSame(19700, RouteDiagramStations::bskmChoice([19500, 19700], 19600));
        $this->assertNull(RouteDiagramStations::lineLengthM('| longueur = '));
    }

    public function test_a_new_run_starts_when_the_pk_goes_back_by_more_than_500_m(): void
    {
        $runs = RouteDiagramStations::runs(self::rows([['A', 0], ['B', 5000], ['C', 10000], ['D', 3000], ['E', 4000]]));

        $this->assertSame([['A', 'B', 'C'], ['D', 'E']], array_map(fn ($r) => array_column($r, 'name'), $runs));
    }

    public function test_a_small_backward_step_and_stations_without_pk_stay_in_the_run(): void
    {
        $runs = RouteDiagramStations::runs(self::rows([['A', 0], ['B', 10000], ['C', 9800], ['D', null], ['E', 12000]]));

        $this->assertSame([['A', 'B', 'C', 'D', 'E']], array_map(fn ($r) => array_column($r, 'name'), $runs));
    }

    public function test_decreasing_pk_form_one_run(): void
    {
        $runs = RouteDiagramStations::runs(self::rows([['A', 30000], ['B', 20000], ['C', 5000]]));

        $this->assertCount(1, $runs);
    }

    // ─── forCode ─────────────────────────────────────────────────────────

    private const COUTERNE = <<<'WT'
{{Infobox Ligne ferroviaire
 | nom = Ligne de Briouze à Couterne
 | numéro = 433000 de Couterne à La Ferté-Macé<br>434000 de Briouze à La Ferté-Macé<ref>RFF</ref>
 | longueur = 29,4
}}
WT;

    /** @return list<array{name: string, pk_m: ?int, icon: string, row: int}> */
    private static function couterneRows(): array
    {
        return self::rows([['Briouze', 0], ['Lonlay-le-Tesson', 7742], ['La Ferté-Macé', 15700], ['Bagnoles-de-l\'Orne', 7000], ['Couterne', null]]);
    }

    public function test_article_of_two_lines_keeps_the_stations_of_each_code(): void
    {
        $p = new WikipediaLineParser;

        $this->assertSame(['La Ferté-Macé', 'Bagnoles-de-l\'Orne', 'Couterne'],
            array_column(RouteDiagramStations::forCode(self::couterneRows(), self::COUTERNE, '433000', $p), 'name'));
        $this->assertSame(['Briouze', 'Lonlay-le-Tesson', 'La Ferté-Macé'],
            array_column(RouteDiagramStations::forCode(self::couterneRows(), self::COUTERNE, '434000', $p), 'name'));
    }

    public function test_stations_are_unchanged_when_the_article_does_not_split_by_code(): void
    {
        $p = new WikipediaLineParser;
        $single = "{{Infobox Ligne ferroviaire\n | numéro = 434000\n}}";

        $this->assertCount(5, RouteDiagramStations::forCode(self::couterneRows(), $single, '434000', $p), 'un seul code');
        $this->assertCount(5, RouteDiagramStations::forCode(self::couterneRows(), self::COUTERNE, '999000', $p), 'code absent de l\'infobox');
        $this->assertCount(5, RouteDiagramStations::forCode(self::couterneRows(), self::COUTERNE, null, $p), 'ligne sans code');
        $this->assertCount(2, RouteDiagramStations::forCode(array_slice(self::couterneRows(), 0, 2), self::COUTERNE, '433000', $p),
            'extrémité absente du schéma');
    }

    // ─── SectionResolver::distortedPkRanges ──────────────────────────────

    public function test_segment_much_longer_or_shorter_than_its_pk_gap_is_distorted(): void
    {
        // [mesure sur le tracé, PK] : 0-10 km cohérent ; 10-12 km de PK pour 8 km de tracé (×4) ;
        // 12-22 km de PK pour 2 km de tracé (÷5) ; 22-30 km cohérent.
        $points = [[0.0, 0], [10000.0, 10000], [18000.0, 12000], [20000.0, 22000], [28000.0, 30000]];

        $this->assertSame([[10000, 12000], [12000, 22000]], SectionResolver::distortedPkRanges($points));
    }

    public function test_small_absolute_differences_are_never_distorted(): void
    {
        // 200 m de PK pour 450 m de tracé : rapport > 1,5 mais écart < 300 m.
        $this->assertSame([], SectionResolver::distortedPkRanges([[0.0, 0], [450.0, 200], [10450.0, 10200]]));
    }
}

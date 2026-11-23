<?php

namespace Tests\Unit\Signalling;

use App\Services\Signalling\SignallingHistoryExtractor;
use App\Services\Signalling\SignallingHistoryState;
use App\Services\Signalling\SignallingInfoboxReader;
use App\Services\Signalling\SignallingSystems;
use App\Services\WikipediaLineParser;
use PHPUnit\Framework\TestCase;

/**
 * Historique de la signalisation, deuxième passe : infobox, tableaux, TVM des LGV, portion propre à chaque
 * système, défauts relevés par la revue du soir du 19/11/2026 (numéros de la revue entre parenthèses), et
 * faits en vigueur à une ère (carte par époque).
 */
class SignallingSecondPassTest extends TestCase
{
    private const TITLE = 'Ligne de Lyon à Marseille';

    private SignallingHistoryExtractor $extractor;

    protected function setUp(): void
    {
        $this->extractor = new SignallingHistoryExtractor(new WikipediaLineParser);
    }

    /** @return list<array<string, mixed>> */
    private function extract(string $wikitext, bool $lgv = false, string $title = self::TITLE): array
    {
        return $this->extractor->extract($wikitext, $title, new \DateTimeImmutable('2026-11-19'), $lgv);
    }

    /** @return list<array<string, mixed>> */
    private function text(string $body): array
    {
        return $this->extract("== Histoire ==\n".$body);
    }

    // ── Revue du soir ────────────────────────────────────────────────────

    public function test_the_date_of_the_electrification_is_not_given_to_the_block_laid_before_it(): void
    {
        // (1) id 43, rfn-314000 : « après pose du BAL » date la pose d'avant mai 1994, pas de mai 1994.
        $facts = $this->extract("== Histoire ==\nL'électrification de la ligne Boulogne - Calais est tardive : elle n'intervient qu'en mai 1994, après pose du BAL de Boulogne-Ville à Hesdigneul.", title: 'Ligne de Boulogne-Ville à Calais-Maritime');

        $this->assertSame([], array_filter($facts, fn (array $f) => $f['date'] === '1994-05-01'));
        $this->assertSame([], array_filter($facts, fn (array $f) => $f['scope'] === 'whole_line'));
    }

    public function test_each_system_keeps_the_section_written_after_it(): void
    {
        // (2) id 5, 1 et 54.
        $angers = $this->text("Entre Angers et Saint-Nazaire, c'est du block automatique lumineux (BAL) mis en service entre Angers et La Possonnière en 1975.");
        $this->assertSame(['Angers', 'La Possonnière'], [$angers[0]['section_from'], $angers[0]['section_to']]);

        $cambrai = $this->text("La signalisation sur la section de Cambrai à Douai a été modernisée avec l'installation du block manuel à voie unique (BMVU) entre Cambrai et Sin-le-Noble en 1980.");
        $this->assertCount(1, $cambrai, 'le nom et le sigle du même système ne font qu’un fait');
        $this->assertSame(['Cambrai', 'Sin-le-Noble'], [$cambrai[0]['section_from'], $cambrai[0]['section_to']]);

        $somain = $this->text('Le block automatique lumineux est installé de Douai à Lourches en 1954, puis en 1956 entre Douai et Somain, le block manuel unifié étant déployé de Somain à Beuvrages.');
        $bm = array_values(array_filter($somain, fn (array $f) => $f['system'] === 'BM'));
        $this->assertSame(['Somain', 'Beuvrages'], [$bm[0]['section_from'], $bm[0]['section_to']]);
        $this->assertSame('low', $bm[0]['confidence'], 'la date 1956 est portée par l’autre portion : douteuse pour le BM');
    }

    public function test_a_system_acronym_never_enters_a_place_name(): void
    {
        // (3) id 52 et 53 ; ancien test trompeur « du BAL de Lyon à Ambérieu ».
        $facts = $this->extract("== Chronologie ==\n* 21 novembre 2010 : mise en service du BAPR de Sainte-Pazanne à Saint-Gilles-Croix-de-Vie.\n* 16 janvier 2011 : mise en service du BAPR de Rezé à Sainte-Pazanne.\n* 1980 : mise en service du BAL de Lyon à Ambérieu.");

        $this->assertSame(['Sainte-Pazanne', 'Rezé', 'Lyon'], array_column($facts, 'section_from'));
        $this->assertSame(['Saint-Gilles-Croix-de-Vie', 'Sainte-Pazanne', 'Ambérieu'], array_column($facts, 'section_to'));
    }

    public function test_an_approximate_date_or_a_period_in_a_parent_bullet_stays_low(): void
    {
        // (5) « Vers 1937 : » et « De 1968 à 1984 : » en puce parente.
        foreach (['Vers 1937 :', 'De 1968 à 1984 :', 'Avant 1950 :'] as $parent) {
            $facts = $this->extract("== Chronologie ==\n* {$parent}\n** mise en service du block automatique lumineux (BAL).");
            $this->assertCount(1, $facts, $parent);
            $this->assertSame('low', $facts[0]['confidence'], $parent);
        }
        $sure = $this->extract("== Chronologie ==\n* 1937 :\n** mise en service du block automatique lumineux (BAL).");
        $this->assertSame('medium', $sure[0]['confidence']);
    }

    public function test_etcs_keeps_its_level(): void
    {
        // (7)
        $this->assertSame('ETCS_L2', SignallingSystems::mentions('ETCS L2')[0]['system']);
        $this->assertSame('ETCS_L1', SignallingSystems::mentions('ERTMS/ETCS L1')[0]['system']);
        $this->assertSame('ETCS_L1', SignallingSystems::mentions('ETCS (niveau 1)')[0]['system']);
        $this->assertSame('ETCS', SignallingSystems::mentions("l'ERTMS 2 ans plus tard")[0]['system'], 'un nombre d’années n’est pas un niveau');
    }

    public function test_the_imperfect_marks_the_former_system_only(): void
    {
        // (11) Phrases avec un verbe de mise en service : seule la règle de l'imparfait écarte le BM.
        $served = $this->text('La ligne, que desservait le BM, est équipée du BAL en 1980.');
        $this->assertSame(['BAL'], array_column($served, 'system'));

        $until = $this->text("La ligne, qu'assurait jusque-là le BM, est équipée du BAL en 1980.");
        $this->assertSame(['BAL'], array_column($until, 'system'));

        // « jusque-là » vise le BM : le BAL qui le remplace n'est pas un ancien système.
        $replaced = $this->text('En 1980, le BM, qui équipait jusque-là la ligne, est remplacé par le BAL entre Lyon et Vienne.');
        $this->assertSame(['BAL'], array_column($replaced, 'system'));
        $this->assertSame('1980-01-01', $replaced[0]['date']);
    }

    public function test_a_date_taken_from_the_previous_sentence_is_medium_even_to_the_day(): void
    {
        // (12) « À cette occasion » : la date du jour est reprise, la fiabilité reste medium (doc alignée).
        $facts = $this->text('Le 11 décembre 2005, la ligne est rouverte. À cette occasion, le cantonnement téléphonique est remplacé par un block automatique à permissivité restreinte (BAPR) entre Montreuil-sur-Ille et Saint-Malo.');

        $bapr = array_values(array_filter($facts, fn (array $f) => $f['system'] === 'BAPR'));
        $this->assertSame('2005-12-11', $bapr[0]['date']);
        $this->assertSame('day', $bapr[0]['precision']);
        $this->assertSame('medium', $bapr[0]['confidence']);
    }

    // ── Infobox ──────────────────────────────────────────────────────────

    public function test_dated_items_of_the_infobox_give_facts_by_section(): void
    {
        $facts = $this->extract(<<<'WIKI'
{{Infobox Ligne ferroviaire
| nomligne = Ligne de Capdenac à Rodez
| signalisation = {{nobr|Capdenac - Viviez : [[Block manuel|BMU]] <small>(depuis 1985)</small><br>Viviez - Rodez : [[Block manuel|BMVU]] <small>(depuis 1988)</small>}}
| mise en service = 1858
}}
WIKI, title: 'Ligne de Capdenac à Rodez');

        $this->assertCount(2, $facts);
        $this->assertSame(['BM', 'BM'], array_column($facts, 'system'));
        $this->assertSame(['1985-01-01', '1988-01-01'], array_column($facts, 'date'));
        $this->assertSame(['Capdenac', 'Viviez'], array_column($facts, 'section_from'));
        $this->assertSame(['Viviez', 'Rodez'], array_column($facts, 'section_to'));
        $this->assertSame(['infobox', 'infobox'], array_column($facts, 'origin'));
        $this->assertSame(['medium', 'medium'], array_column($facts, 'confidence'));
    }

    public function test_undated_or_out_of_service_infobox_items_give_nothing(): void
    {
        $facts = $this->extract(<<<'WIKI'
{{Infobox Ligne ferroviaire
| signalisation = [[Block Manuel|BM]] <small>(HS, marche à vue depuis 2016) jusqu'à Volvic</small><br>[[BAPR]] <small>au delà</small><br>[[Système européen de contrôle des trains|ETCS]] <small>(mise en service reportée)</small>
| mise en service = 1881
}}
WIKI);
        $this->assertSame([], $facts);
    }

    public function test_the_cab_signalling_of_a_high_speed_line_is_dated_from_its_opening(): void
    {
        $infobox = fn (string $signalling, string $openings) => "{{Infobox Ligne ferroviaire\n| signalisation = {$signalling}\n{$openings}\n}}\n";

        $nord = $this->extract($infobox('[[Transmission voie-machine|TVM 430]]', '| mise en service = 1993'), lgv: true, title: 'LGV Nord');
        $this->assertSame([['TVM-430', 'cab', '1993-01-01', 'year', 'whole_line', 'medium', 'opening']],
            array_map(fn (array $f) => [$f['system'], $f['role'], $f['date'], $f['precision'], $f['scope'], $f['confidence'], $f['origin']], $nord));

        $bpl = $this->extract($infobox('[[Transmission voie-machine|TVM 300]] / [[Système européen de contrôle des trains|ETCS niveau 2]]', '| mise en service = 2 juillet 2017'), lgv: true, title: 'LGV Bretagne-Pays de la Loire');
        $this->assertSame(['TVM-300'], array_column($bpl, 'system'), 'l’ETCS n’est jamais daté de l’ouverture');
        $this->assertSame('2017-07-02', $bpl[0]['date']);
        $this->assertSame('medium', $bpl[0]['confidence'], 'date déduite : jamais high');

        $sudEst = $this->extract($infobox('[[Transmission voie-machine|TVM 300]] <small>(TVM 260 puis 270 à l\'origine)</small><br>ETCS 2', "| mise en service = 1981\n| mise en service2 = 1983"), lgv: true, title: 'LGV Sud-Est');
        $this->assertSame(['TVM'], array_column($sudEst, 'system'), 'une autre version à l’origine : version non dite');
        $this->assertSame('unspecified', $sudEst[0]['scope'], 'ouverte en deux fois : portion inconnue');
        $this->assertSame('1981-01-01', $sudEst[0]['date']);

        $two = $this->extract($infobox('[[Transmission voie-machine|TVM 300 / 430]]', '| mise en service = 1994'), lgv: true, title: 'LGV Interconnexion Est');
        $this->assertSame(['TVM'], array_column($two, 'system'));

        $this->assertSame([], $this->extract($infobox('[[Transmission voie-machine|TVM 430]]', '| mise en service = 1993'), lgv: false), 'pas une LGV : rien');
        $this->assertSame([], $this->extract($infobox('BAL et ETCS (niveau 1)', '| mise en service = 2017'), lgv: true), 'sans TVM : rien');
    }

    public function test_the_raw_infobox_field_is_read_over_several_lines(): void
    {
        $this->assertSame("A<br>\nB", SignallingInfoboxReader::rawField("{{Infobox\n| signalisation = A<br>\nB\n| longueur = 3\n}}", 'signalisation'));
        $this->assertNull(SignallingInfoboxReader::rawField("{{Infobox\n| longueur = 3\n}}", 'signalisation'));
    }

    // ── Tableaux ─────────────────────────────────────────────────────────

    public function test_a_table_row_with_one_system_and_one_date_gives_a_fact(): void
    {
        $facts = $this->extract(<<<'WIKI'
== Signalisation ==
{| class="wikitable"
! Section !! Système !! Mise en service
|-
| [[Gare de Grenoble|Grenoble]] - [[Vif]] || [[Block automatique lumineux|BAL]] || {{date|12|mai|1994}}
|-
| style="text-align:left" | de Vif à Clelles
| [[Block manuel|BM]]
| 1954
|-
| Clelles - Lus || BAL et BAPR || 1990
|-
| Lus - Veynes || BAL || prévu en 2030
|}
WIKI);
        $byFrom = [];
        foreach ($facts as $f) {
            $byFrom[$f['section_from'].'/'.$f['system']] = $f;
        }
        $this->assertSame('1994-05-12', $byFrom['Grenoble/BAL']['date']);
        $this->assertSame('high', $byFrom['Grenoble/BAL']['confidence']);
        $this->assertSame('Vif', $byFrom['Grenoble/BAL']['section_to']);
        $this->assertSame('table', $byFrom['Grenoble/BAL']['origin']);
        $this->assertSame(['1954-01-01', 'medium', 'Clelles'], [$byFrom['Vif/BM']['date'], $byFrom['Vif/BM']['confidence'], $byFrom['Vif/BM']['section_to']]);
        $this->assertSame('low', $byFrom['Clelles/BAL']['confidence'], 'deux systèmes sur une ligne du tableau : douteux');
        $this->assertArrayNotHasKey('Lus/BAL', $byFrom, 'un projet ne donne rien');
    }

    public function test_a_table_in_a_projects_section_is_skipped(): void
    {
        $this->assertSame([], $this->extract("== Projets ==\n{| class=\"wikitable\"\n! Section !! Système !! Date\n|-\n| A - B || BAL || 2020\n|}"));
    }

    // ── Faits en vigueur à une ère ───────────────────────────────────────

    /** @return array<string, mixed> */
    private static function fact(string $system, string $role, string $date, string $scope = 'unspecified', ?int $start = null, ?int $end = null): array
    {
        return ['system' => $system, 'role' => $role, 'date' => $date, 'scope' => $scope,
            'section' => $scope === 'section' ? ['pk_start_m' => $start, 'pk_end_m' => $end] : null];
    }

    public function test_only_facts_dated_before_the_end_of_the_era_are_in_force(): void
    {
        $facts = [self::fact('BM', 'block', '1954-01-01', 'section', 0, 10_000), self::fact('BAL', 'block', '1994-01-01', 'section', 2_000, 8_000)];

        $this->assertSame([], SignallingHistoryState::inForce($facts, SignallingHistoryState::until(1950, false)));
        $this->assertSame(['BM'], array_column(SignallingHistoryState::inForce($facts, SignallingHistoryState::until(1990, false)), 'system'));
        // Le BAL ne couvre qu'une partie de la portion du BM : les deux sont dessinés, le plus récent dessus.
        $this->assertSame(['BM', 'BAL'], array_column(SignallingHistoryState::inForce($facts, SignallingHistoryState::until(2000, false)), 'system'));
    }

    public function test_a_later_block_covering_the_section_replaces_the_earlier_one(): void
    {
        $facts = [
            self::fact('CT', 'block', '1946-01-01', 'section', 10_000, 50_000),
            self::fact('BM', 'block', '1957-01-01', 'section', 9_900, 50_100),
            self::fact('BAL', 'block', '1990-01-01', 'whole_line'),
            self::fact('KVB', 'speed_control', '1993-01-01'),
        ];

        $this->assertSame(['BM'], array_column(SignallingHistoryState::inForce($facts, '1960-12-31'), 'system'));
        $this->assertSame(['BAL', 'KVB'], array_column(SignallingHistoryState::inForce($facts, '2000-12-31'), 'system'), 'toute la ligne remplace tout le cantonnement ; le KVB est à part');
    }

    public function test_remote_control_is_its_own_group_and_never_replaces_nor_is_replaced_by_a_block_system(): void
    {
        $facts = [
            self::fact('BAPR', 'block', '1980-01-01', 'whole_line'),
            self::fact('CC', 'remote_control', '1982-01-01', 'section', 0, 10_000),
            self::fact('BAL', 'block', '1990-01-01', 'whole_line'),
            self::fact('CC', 'remote_control', '2010-01-01', 'section', 0, 12_000),
        ];

        $this->assertSame(['BAPR', 'CC'], array_column(SignallingHistoryState::inForce($facts, '1985-12-31'), 'system'), 'la commande centralisée s’ajoute au cantonnement');
        $this->assertSame(['CC', 'BAL'], array_column(SignallingHistoryState::inForce($facts, '2000-12-31'), 'system'), 'le BAL de toute la ligne remplace le BAPR, pas la commande centralisée');
        $this->assertSame(['BAL', 'CC'], array_column(SignallingHistoryState::inForce($facts, '2020-12-31'), 'system'), 'une commande centralisée plus récente remplace la plus ancienne');
        $this->assertSame('CC', SignallingHistoryState::group(['system' => 'CC', 'role' => 'remote_control']));
    }

    public function test_a_fact_without_a_known_section_replaces_nothing_and_cab_systems_add_up(): void
    {
        $facts = [
            self::fact('BAPR', 'block', '1980-01-01', 'section', 0, 5_000),
            self::fact('BAL', 'block', '1990-01-01'),
            self::fact('TVM', 'cab', '1981-01-01'),
            self::fact('ETCS', 'cab', '2024-11-12'),
        ];

        $this->assertSame(['BAPR', 'TVM', 'BAL'], array_column(SignallingHistoryState::inForce($facts, '2020-12-31'), 'system'));
        $this->assertSame(['BAPR', 'TVM', 'BAL', 'ETCS'], array_column(SignallingHistoryState::inForce($facts, '2026-11-19'), 'system'), 'l’ETCS s’ajoute à la TVM');
        $this->assertSame('2026-11-19', SignallingHistoryState::until(2030, true, new \DateTimeImmutable('2026-11-19')));
    }
}

<?php

namespace Tests\Unit\Mutation;

use App\Services\Materials\MaterialGaugeRule;
use App\Services\Materials\MaterialTractionRule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Seuils exacts et motifs rédigés des règles d'écartement et d'électrification des liens
 * ligne × matériel (MaterialGaugeRule, MaterialTractionRule), en complément des cas réels de
 * tests/Unit/Materials : une part égale au seuil garde le lien, les ères hors service ne comptent
 * pas, le motif donne les bonnes valeurs.
 */
class MaterialRulesBoundariesTest extends TestCase
{
    // ─── Écartement ─────────────────────────────────────────────────────────

    private function gauge(array $profile, int $seriesGauge = 1435, array $exceptions = []): MaterialGaugeRule
    {
        return new MaterialGaugeRule([1 => $profile], [10 => $seriesGauge], $exceptions, ['ligne 999 inconnue']);
    }

    public function test_a_quarter_of_the_line_at_the_series_gauge_is_enough(): void
    {
        $this->assertNull($this->gauge([1435 => 250.0, 1000 => 750.0])->conflict(1, 10));
        $this->assertNotNull($this->gauge([1435 => 249.0, 1000 => 751.0])->conflict(1, 10));
    }

    public function test_the_gauge_refusal_names_the_main_gauge_and_the_share(): void
    {
        $rule = $this->gauge([1435 => 60.0, 600 => 100.0, 1000 => 1179.0]);

        $this->assertSame('série à 1 435 mm, ligne surtout à 1 000 mm (4 % de sa longueur à 1 435 mm)', $rule->conflict(1, 10));
    }

    public function test_a_line_without_any_length_at_the_series_gauge_is_refused(): void
    {
        $this->assertSame(['series_mm' => 1435, 'line_mm' => 1000, 'share' => 0], $this->gauge([1000 => 3.0])->details(1, 10));
        $this->assertNull($this->gauge([1435 => 3.0])->details(1, 10));
    }

    public function test_the_gauge_share_is_rounded_to_the_nearest_percent(): void
    {
        // 23,6 % : arrondi à 24, pas tronqué à 23.
        $this->assertStringContainsString('(24 % de sa longueur', (string) $this->gauge([1435 => 236.0, 1000 => 764.0])->conflict(1, 10));
    }

    public function test_a_line_without_length_is_not_judged(): void
    {
        $this->assertNull($this->gauge([1000 => 0.0])->conflict(1, 10));
        $this->assertNull($this->gauge([])->conflict(1, 10));
        $this->assertNull($this->gauge([1000 => 500.0])->conflict(2, 10), 'ligne sans profil');
    }

    public function test_a_gauge_exception_is_read_by_line_and_series(): void
    {
        $rule = $this->gauge([1000 => 500.0], 1435, ['1:10' => 'relu à la main']);

        $this->assertNull($rule->conflict(1, 10));
        $this->assertSame(['ligne 999 inconnue'], $rule->unresolvedEntries());
    }

    public function test_gauge_evidence_without_a_quote_does_not_keep_the_link(): void
    {
        $rule = $this->gauge([1000 => 500.0]);

        $this->assertNotNull($rule->conflict(1, 10, [['quote' => null], ['url' => 'voie normale'], ['quote' => 'la voie métrique']]));
        $this->assertNull($rule->conflict(1, 10, [['quote' => 'la voie métrique'], ['quote' => 'sur la section à voie normale']]));
    }

    /** @return iterable<string, array{string, int, bool}> */
    public static function gaugeNames(): iterable
    {
        yield 'point des milliers' => ['rails de 1.435 mm', 1435, true];
        yield 'sans espace avant mm' => ['écartement de 1435mm', 1435, true];
        yield 'nombre plus long' => ['21435 mm de rails', 1435, false];
        yield 'voie de 60' => ['la voie de 60 du réseau', 600, true];
        yield 'voie de 0,60' => ['une voie de 0,60 m', 600, true];
        yield '600 mm' => ['écartement de 600 mm', 600, true];
        yield 'majuscules' => ['VOIE NORMALE', 1435, true];
        yield 'autre écartement sans nom' => ['voie de 1 067 mm', 1067, true];
        yield 'nom d\'un autre écartement' => ['voie normale', 1000, false];
        yield 'nombre sans unité' => ['les 1435 voyageurs', 1435, false];
    }

    #[DataProvider('gaugeNames')]
    public function test_gauge_names(string $text, int $gauge, bool $named): void
    {
        $this->assertSame($named, MaterialGaugeRule::namesGauge($text, $gauge));
    }

    // ─── Électrification ────────────────────────────────────────────────────

    /**
     * @param  array<int, array{total: float, volts: array<int, float>}>  $eras
     * @param  array{volts: list<int>, from: ?int, to: ?int}  $series
     */
    private function traction(array $eras, array $series = ['volts' => [1500], 'from' => null, 'to' => null], array $exceptions = []): MaterialTractionRule
    {
        return new MaterialTractionRule([1 => $eras], [10 => $series], $exceptions, ['série 999 inconnue']);
    }

    public function test_five_percent_under_a_compatible_current_is_enough(): void
    {
        $this->assertNull($this->traction([2030 => ['total' => 1000.0, 'volts' => [1500 => 50.0]]])->conflict(1, 10));
        $this->assertSame(
            'série à 1 500 V, ligne au plus à 5 % de sa longueur sous un courant compatible',
            $this->traction([2030 => ['total' => 1000.0, 'volts' => [1500 => 49.0]]])->conflict(1, 10),
        );
    }

    /** @return iterable<string, array{float, int}> */
    public static function tractionShares(): iterable
    {
        yield '4,5 % arrondi à 5' => [45.0, 5];
        yield '4,49 % arrondi à 4' => [44.9, 4];
        yield '4,4 % arrondi à 4' => [44.0, 4];
    }

    #[DataProvider('tractionShares')]
    public function test_the_traction_share_is_rounded_to_the_nearest_percent(float $meters, int $share): void
    {
        $details = $this->traction([2030 => ['total' => 1000.0, 'volts' => [1500 => $meters]]])->details(1, 10);

        $this->assertSame(['volts' => [1500], 'share' => $share, 'partial' => true], $details);
    }

    public function test_the_best_era_decides(): void
    {
        $rule = $this->traction([
            1990 => ['total' => 1000.0, 'volts' => [1500 => 10.0]],
            2030 => ['total' => 1000.0, 'volts' => [1500 => 30.0]],
            2010 => ['total' => 1000.0, 'volts' => [1500 => 20.0]],
        ]);

        $this->assertStringContainsString('au plus à 3 %', (string) $rule->conflict(1, 10));
    }

    public function test_a_line_never_electrified_in_a_compatible_system(): void
    {
        $rule = $this->traction([2030 => ['total' => 1000.0, 'volts' => [25000 => 1000.0]]], ['volts' => [1500, 3000], 'from' => null, 'to' => null]);

        $this->assertSame('série à 1 500 V, 3 000 V, ligne jamais électrifiée dans un système compatible', $rule->conflict(1, 10));
    }

    public function test_an_electrified_section_of_unknown_voltage_counts_as_compatible(): void
    {
        $this->assertNull($this->traction([2030 => ['total' => 1000.0, 'volts' => [0 => 100.0]]])->conflict(1, 10));
    }

    public function test_eras_without_known_length_are_not_judged(): void
    {
        $this->assertNull($this->traction([2030 => ['total' => 0.0, 'volts' => []]])->conflict(1, 10));
        $this->assertNull($this->traction([])->conflict(1, 10));
    }

    public function test_the_service_period_bounds_are_inclusive(): void
    {
        $eras = [
            1970 => ['total' => 1000.0, 'volts' => [1500 => 1000.0]],
            1990 => ['total' => 1000.0, 'volts' => []],
        ];
        // Service à partir de 1970 : l'ère 1970 compte.
        $this->assertNull($this->traction($eras, ['volts' => [1500], 'from' => 1970, 'to' => null])->conflict(1, 10));
        // À partir de 1971 : seule l'ère 1990 reste, jamais électrifiée.
        $this->assertNotNull($this->traction($eras, ['volts' => [1500], 'from' => 1971, 'to' => null])->conflict(1, 10));

        $late = [
            1970 => ['total' => 1000.0, 'volts' => []],
            1990 => ['total' => 1000.0, 'volts' => [1500 => 1000.0]],
        ];
        // Fin de service en 1981 : l'ère 1990 (1990 - 10 < 1981) compte encore.
        $this->assertNull($this->traction($late, ['volts' => [1500], 'from' => null, 'to' => 1981])->conflict(1, 10));
        // Fin de service en 1980 : l'ère 1990 ne compte plus.
        $this->assertNotNull($this->traction($late, ['volts' => [1500], 'from' => null, 'to' => 1980])->conflict(1, 10));
    }

    public function test_a_traction_exception_is_read_by_line_and_series(): void
    {
        $rule = $this->traction([2030 => ['total' => 1000.0, 'volts' => []]], exceptions: ['1:10' => 'relu']);

        $this->assertNull($rule->conflict(1, 10));
        $this->assertNull($rule->conflict(1, 11), 'série inconnue');
        $this->assertSame(['série 999 inconnue'], $rule->unresolvedEntries());
    }

    public function test_doubtful_evidence_does_not_keep_the_link(): void
    {
        $rule = $this->traction([2030 => ['total' => 1000.0, 'volts' => []]]);

        $this->assertNotNull($rule->conflict(1, 10, [['quote' => 'sous 1 500 V', 'doubt' => 'tension non vérifiée'], ['quote' => 42]]));
        $this->assertNull($rule->conflict(1, 10, [['quote' => 'sous 1 500 V', 'doubt' => null]]));
    }

    /** @return iterable<string, array{list<int>, string}> */
    public static function writtenSystems(): iterable
    {
        yield 'kilovolts entiers' => [[25000], 'série à 25 kV'];
        yield 'kilovolts décimaux' => [[16700], 'série à 16,7 kV'];
        yield 'seuil des kilovolts' => [[10000], 'série à 10 kV'];
        yield 'volts sous le seuil' => [[9999], 'série à 9 999 V'];
        yield 'petite tension' => [[750], 'série à 750 V'];
        yield 'dixième arrondi' => [[16750], 'série à 16,8 kV'];
        yield 'dixième arrondi vers le bas' => [[10049], 'série à 10 kV'];
        yield 'dixième arrondi vers le haut' => [[10051], 'série à 10,1 kV'];
    }

    /** @param  list<int>  $volts */
    #[DataProvider('writtenSystems')]
    public function test_systems_are_written_in_volts_or_kilovolts(array $volts, string $start): void
    {
        $rule = $this->traction([2030 => ['total' => 1000.0, 'volts' => []]], ['volts' => $volts, 'from' => null, 'to' => null]);

        $this->assertStringStartsWith($start.', ligne', (string) $rule->conflict(1, 10));
    }

    public function test_voltages_of_wikidata_systems(): void
    {
        $this->assertSame([1500, 25000, 750, 15000, 16700, 4350, 1005, 600], MaterialTractionRule::voltages([
            ['label' => 'électrification 1,5 kV continu'],
            ['label' => '25 kV 50 Hz'],
            ['label' => '1500 V continu'],
            ['label' => '750 V troisième rail'],
            ['label' => "15\u{00A0}kV 16,7 Hz"],
            ['label' => '16,7 kV'],
            ['label' => '4,35 kV'],
            ['label' => '1,005 kV'],
            ['label' => '0,6004 kV'],
            ['label' => 'courant inconnu'],
            ['id' => 'Q1'],
            'pas un tableau',
        ]));
        $this->assertSame([], MaterialTractionRule::voltages([]));
    }

    /** @return iterable<string, array{int, int, bool}> */
    public static function tolerances(): iterable
    {
        yield 'écart de 20 % exactement' => [1000, 1250, true];
        yield 'au-delà de 20 %' => [1000, 1251, false];
        yield 'ordre inversé' => [1250, 1000, true];
    }

    #[DataProvider('tolerances')]
    public function test_compatible_voltages_within_twenty_percent(int $a, int $b, bool $compatible): void
    {
        $this->assertSame($compatible, MaterialTractionRule::compatible($a, $b));
    }

    /** @return iterable<string, array{string, list<int>, bool}> */
    public static function namedVoltages(): iterable
    {
        yield 'kilovolts décimaux' => ['sous 1,5 kV', [1500], true];
        yield 'kilovolts en toutes lettres' => ['25 kilovolts', [25000], true];
        yield 'milliers séparés' => ['25 000 V', [25000], true];
        yield 'milliers avec un point' => ['1.500 volts', [1500], true];
        yield 'zéro volt' => ['0 V', [1500], false];
        yield 'nombre collé à une virgule' => ['x,1500 V', [1500], false];
        yield 'autre tension' => ['3 000 V', [1500], false];
        yield 'deuxième tension citée' => ['3 000 V puis 1 500 V', [1500], true];
        yield 'kilovolts en majuscules' => ['25 KV', [25000], true];
        yield 'borne basse de la tolérance' => ['25 kV', [20000], true];
        yield 'borne haute de la tolérance' => ['25 kV', [31250], true];
        yield 'dixièmes de kilovolt, borne basse' => ['16,7 kV', [13360], true];
        yield 'centièmes de kilovolt, borne haute' => ['4,35 kV', [5437], true];
        yield 'arrondi au volt le plus proche, vers le haut' => ['1,005 kV', [1256], true];
        yield 'arrondi au volt le plus proche, vers le bas' => ['0,7504 kV', [600], true];
    }

    /** @param  list<int>  $volts */
    #[DataProvider('namedVoltages')]
    public function test_named_voltages(string $text, array $volts, bool $named): void
    {
        $this->assertSame($named, MaterialTractionRule::namesVoltage($text, $volts));
    }
}

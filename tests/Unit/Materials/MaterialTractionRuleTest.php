<?php

namespace Tests\Unit\Materials;

use App\Services\Materials\MaterialTractionRule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Règle d'électrification des liens ligne × matériel : profils réels résumés (longueurs en mètres, par ère).
 */
class MaterialTractionRuleTest extends TestCase
{
    private const VALENCE_MOIRANS = 473;

    private const DARSAC = 785;

    private const MOULINEAUX = 565;

    private const LATE_LINE = 7;

    private const UNKNOWN_LINE = 9;

    private const BB_7200 = 32;

    private const BB_9300 = 49;

    private const BB_22200 = 34;

    private const BB_26000 = 31;

    private const Z_1200 = 477;

    private const OLD_DC = 5;

    /** Valence - Moirans telle qu'elle est vraiment : 25 kV, sauf 2,2 km de 1 500 V côté Valence-Ville. */
    private const VALENCE_MOIRANS_PROFILE = [
        1990 => ['total' => 79_000.0, 'volts' => []],
        2030 => ['total' => 79_000.0, 'volts' => [1500 => 2_200.0, 25000 => 76_800.0]],
    ];

    private function rule(array $exceptions = []): MaterialTractionRule
    {
        return new MaterialTractionRule(
            [
                self::VALENCE_MOIRANS => self::VALENCE_MOIRANS_PROFILE,
                // 785000 : seule la section Saint-Germain-des-Fossés - Vichy est électrifiée (11 km sur 146).
                self::DARSAC => [2030 => ['total' => 146_000.0, 'volts' => [1500 => 11_000.0]]],
                // 972000 : VFF ne connaît pas le troisième rail en 750 V, déposé depuis.
                self::MOULINEAUX => [1950 => ['total' => 10_000.0, 'volts' => []], 2030 => ['total' => 9_400.0, 'volts' => []]],
                // Ligne électrifiée en 1 500 V en 1995 seulement.
                self::LATE_LINE => [1970 => ['total' => 50_000.0, 'volts' => []], 2000 => ['total' => 50_000.0, 'volts' => [1500 => 50_000.0]]],
            ],
            [
                self::BB_7200 => ['volts' => [1500], 'from' => 1976, 'to' => null],
                self::BB_9300 => ['volts' => [1500], 'from' => 1967, 'to' => 2014],
                self::BB_22200 => ['volts' => [1500, 25000], 'from' => 1976, 'to' => null],
                self::BB_26000 => ['volts' => [1500, 25000], 'from' => null, 'to' => null],
                self::Z_1200 => ['volts' => [750], 'from' => null, 'to' => null],
                self::OLD_DC => ['volts' => [1500], 'from' => 1950, 'to' => 1980],
            ],
            $exceptions,
        );
    }

    public function test_a_direct_current_locomotive_is_refused_on_an_alternating_current_line(): void
    {
        $quote = [['kind' => 'wikipedia_line_article', 'doubt' => 'diverted', 'quote' => 'Par suite de la coupure totale de la ligne, … changement de machine par une BB 9300 ou BB 7200 apte à circuler sous caténaire 1500 Volts continu']];

        $this->assertSame('série à 1 500 V, ligne au plus à 3 % de sa longueur sous un courant compatible', $this->rule()->conflict(self::VALENCE_MOIRANS, self::BB_7200, $quote), 'le bout de gare en 1 500 V ne suffit pas, et la phrase en doute ne compte pas');
        $this->assertNotNull($this->rule()->conflict(self::VALENCE_MOIRANS, self::BB_9300));
    }

    public function test_a_dual_current_locomotive_is_accepted(): void
    {
        $this->assertNull($this->rule()->conflict(self::VALENCE_MOIRANS, self::BB_22200));
    }

    public function test_a_partly_electrified_line_keeps_its_electric_trains(): void
    {
        $this->assertNull($this->rule()->conflict(self::DARSAC, self::BB_26000), '8 % de la ligne sous caténaire suffit');
    }

    public function test_only_eras_of_the_service_period_count(): void
    {
        $this->assertSame('série à 1 500 V, ligne jamais électrifiée dans un système compatible', $this->rule()->conflict(self::LATE_LINE, self::OLD_DC), 'série retirée en 1980, ligne électrifiée en 1995');
        $this->assertNull($this->rule()->conflict(self::LATE_LINE, self::BB_7200), 'série encore en service après l\'électrification');
    }

    public function test_unknown_data_is_not_judged(): void
    {
        $this->assertNull($this->rule()->conflict(self::UNKNOWN_LINE, self::BB_7200), 'ligne sans électrification connue');
        $this->assertNull($this->rule()->conflict(self::VALENCE_MOIRANS, 999), 'série non jugée (thermique, bimode ou sans système connu)');

        $unknownVoltage = new MaterialTractionRule([1 => [2030 => ['total' => 10_000.0, 'volts' => [0 => 10_000.0]]]], [2 => ['volts' => [750], 'from' => null, 'to' => null]]);
        $this->assertNull($unknownVoltage->conflict(1, 2), 'tronçon électrifié de tension inconnue');
    }

    public function test_an_explicit_source_keeps_the_link(): void
    {
        $this->assertNotNull($this->rule()->conflict(self::MOULINEAUX, self::Z_1200));

        $rule = $this->rule([self::MOULINEAUX.':'.self::Z_1200 => 'https://fr.wikipedia.org/w/index.php?oldid=241186256']);
        $this->assertNull($rule->conflict(self::MOULINEAUX, self::Z_1200), 'exception relue');

        $named = [['kind' => 'wikipedia_line_article', 'quote' => 'La ligne est alimentée par troisième rail en 750 volts et desservie par des Z 1200.']];
        $this->assertNull($this->rule()->conflict(self::MOULINEAUX, self::Z_1200, $named), 'la phrase nomme la tension de la série');

        $other = [['kind' => 'wikipedia_line_article', 'quote' => 'Les Z 1200 sont remplacées par des rames sous caténaire 25 kV.']];
        $this->assertNotNull($this->rule()->conflict(self::MOULINEAUX, self::Z_1200, $other), 'la tension nommée doit être celle de la série');
    }

    public function test_voltages_are_read_from_wikidata_labels(): void
    {
        $this->assertSame([1500, 25000, 15000], MaterialTractionRule::voltages([
            ['label' => 'électrification ferroviaire 1500 V continu', 'wikidata_id' => 'Q21253457'],
            ['label' => 'électrification ferroviaire 25 kV 50 Hz', 'wikidata_id' => 'Q106530439'],
            ['label' => 'électrification ferroviaire 15 kV 16,7 Hz', 'wikidata_id' => 'Q1415234'],
            ['label' => 'sans tension', 'wikidata_id' => null],
        ]));
    }

    /** @return iterable<string, array{int, int, bool}> */
    public static function compatibilities(): iterable
    {
        yield '20 et 25 kV 50 Hz' => [20000, 25000, true];
        yield '12 et 15 kV 16,7 Hz' => [12000, 15000, true];
        yield '750 et 850 V' => [750, 850, true];
        yield '1 500 et 3 000 V' => [1500, 3000, false];
        yield '1 500 V et 25 kV' => [1500, 25000, false];
        yield '600 et 1 500 V' => [600, 1500, false];
    }

    #[DataProvider('compatibilities')]
    public function test_compatible_voltages(int $a, int $b, bool $compatible): void
    {
        $this->assertSame($compatible, MaterialTractionRule::compatible($a, $b));
        $this->assertSame($compatible, MaterialTractionRule::compatible($b, $a));
    }

    /** @return iterable<string, array{string, list<int>, bool}> */
    public static function voltageNames(): iterable
    {
        yield 'volts collés' => ['sous caténaire 1500 Volts continu', [1500], true];
        yield 'espace des milliers' => ['électrifiée en 1 500 V', [1500], true];
        yield 'kilovolts' => ['électrifiée en 25 kV 50 Hz', [25000], true];
        yield 'volts en entier' => ['en 25 000 V alternatif', [25000], true];
        yield 'virgule' => ['ancien réseau en 1,5 kV', [1500], true];
        yield 'autre tension' => ['électrifiée en 25 kV', [1500], false];
        yield 'vitesse' => ['à 160 km/h', [1500], false];
        yield 'nombre dans un autre nombre' => ['21 500 V', [1500], false];
        yield 'série seule' => ['les BB 7200', [1500], false];
        yield 'milliers à l’anglaise' => ['electrified at 1,500 V DC', [1500], true];
        yield 'décimale à l’anglaise' => ['the 1.5 kV network', [1500], true];
        yield 'milliers à l’allemande' => ['mit 1.500 V Gleichstrom', [1500], true];
        yield 'kilovolt allemand' => ['mit 15 Kilovolt Wechselstrom', [15000], true];
    }

    #[DataProvider('voltageNames')]
    public function test_voltage_names(string $text, array $volts, bool $named): void
    {
        $this->assertSame($named, MaterialTractionRule::namesVoltage($text, $volts));
    }
}

<?php

namespace Tests\Unit\Materials;

use App\Services\Materials\MaterialGaugeRule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Règle d'écartement des liens ligne × matériel : profils réels résumés (longueurs en mètres, toutes ères).
 */
class MaterialGaugeRuleTest extends TestCase
{
    private const BLANC_ARGENT = 645;

    private const SALBRIS_ARGENT = 4068;

    private const GUINGAMP_CARHAIX = 485;

    private const UNKNOWN_LINE = 9;

    private const X_73500 = 1;

    private const X_74500 = 2;

    private const X_97150 = 3;

    private const NO_GAUGE = 4;

    private function rule(array $exceptions = []): MaterialGaugeRule
    {
        return new MaterialGaugeRule(
            [
                // 600000 : 334 tronçons métriques (1 179 km) et 13 raccordements à voie normale (60 km).
                self::BLANC_ARGENT => [1000 => 1_179_000.0, 1435 => 60_000.0],
                self::SALBRIS_ARGENT => [1000 => 306_000.0],
                // 485000 : métrique jusqu'en 1960, voie normale ensuite.
                self::GUINGAMP_CARHAIX => [1000 => 377_000.0, 1435 => 377_000.0],
            ],
            [self::X_73500 => 1435, self::X_74500 => 1000, self::X_97150 => 1435],
            $exceptions,
        );
    }

    public function test_a_standard_gauge_railcar_is_refused_on_a_metre_gauge_line(): void
    {
        $quote = [['kind' => 'wikipedia_line_article', 'quote' => 'Ce nouveau matériel offre un confort similaire aux autorails X 73500, avec notamment la climatisation.']];

        $this->assertSame('série à 1 435 mm, ligne surtout à 1 000 mm (0 % de sa longueur à 1 435 mm)', $this->rule()->conflict(self::SALBRIS_ARGENT, self::X_73500, $quote));
        $this->assertSame('série à 1 435 mm, ligne surtout à 1 000 mm (5 % de sa longueur à 1 435 mm)', $this->rule()->conflict(self::BLANC_ARGENT, self::X_73500, $quote), 'les raccordements à voie normale ne suffisent pas');
    }

    public function test_a_series_of_the_line_gauge_is_accepted(): void
    {
        $this->assertNull($this->rule()->conflict(self::BLANC_ARGENT, self::X_74500));
        $this->assertNull($this->rule()->conflict(self::SALBRIS_ARGENT, self::X_74500));
    }

    public function test_a_regauged_line_accepts_both_gauges(): void
    {
        $this->assertNull($this->rule()->conflict(self::GUINGAMP_CARHAIX, self::X_97150));
        $this->assertNull($this->rule()->conflict(self::GUINGAMP_CARHAIX, self::X_74500));
    }

    public function test_unknown_gauges_are_not_judged(): void
    {
        $this->assertNull($this->rule()->conflict(self::UNKNOWN_LINE, self::X_73500), 'ligne sans écartement connu');
        $this->assertNull($this->rule()->conflict(self::SALBRIS_ARGENT, self::NO_GAUGE), 'série sans écartement');
    }

    public function test_an_explicit_source_keeps_the_link(): void
    {
        $explicit = [['kind' => 'wikipedia_line_article', 'quote' => 'Les X 73500 desservent la section à voie normale de Salbris.']];
        $this->assertNull($this->rule()->conflict(self::BLANC_ARGENT, self::X_73500, $explicit), 'la phrase nomme l\'écartement de la série');

        $metric = [['kind' => 'wikipedia_line_article', 'quote' => 'Les X 73500 parcourent la ligne à voie métrique.']];
        $this->assertNotNull($this->rule()->conflict(self::BLANC_ARGENT, self::X_73500, $metric), 'l\'écartement nommé doit être celui de la série');

        $rule = $this->rule([self::BLANC_ARGENT.':'.self::X_73500 => 'https://fr.wikipedia.org/w/index.php?oldid=1']);
        $this->assertNull($rule->conflict(self::BLANC_ARGENT, self::X_73500), 'exception relue');
        $this->assertNotNull($rule->conflict(self::SALBRIS_ARGENT, self::X_73500), 'l\'exception vaut pour une ligne seulement');
    }

    /** @return iterable<string, array{string, int, bool}> */
    public static function gaugeNames(): iterable
    {
        yield 'voie normale' => ['sur la section à voie normale', 1435, true];
        yield 'écartement standard' => ['à écartement standard', 1435, true];
        yield 'millimètres' => ['voie de 1 435 mm', 1435, true];
        yield 'millimètres collés' => ['voie de 1435 mm', 1435, true];
        yield 'voie métrique' => ['le réseau à voie métrique', 1000, true];
        yield 'mètre en millimètres' => ['écartement de 1 000 mm', 1000, true];
        yield 'voie de 60' => ['un réseau à voie de 60', 600, true];
        yield 'autre écartement' => ['la ligne à voie métrique', 1435, false];
        yield 'nombre dans un autre nombre' => ['21 435 mm', 1435, false];
        yield 'série seule' => ['les autorails X 73500', 1435, false];
        yield 'anglais' => ['laid to standard gauge in 1960', 1435, true];
        yield 'anglais, voie métrique' => ['the metre-gauge network', 1000, true];
        yield 'anglais, milliers' => ['a 1,435 mm track', 1435, true];
        yield 'allemand' => ['auf Normalspur umgebaut', 1435, true];
        yield 'allemand, mot composé' => ['das Meterspurgleis', 1000, true];
        yield 'voie ibérique' => ['en voie ibérique', 1668, true];
    }

    public function test_a_corrected_gauge_is_judged_with_the_new_value(): void
    {
        $rule = $this->rule()->withMaterialGauge(self::X_74500, 1435);

        $this->assertNotNull($rule->conflict(self::SALBRIS_ARGENT, self::X_74500), 'série passée à 1 435 mm sur une ligne métrique');
        $this->assertNull($this->rule()->conflict(self::SALBRIS_ARGENT, self::X_74500), 'la règle d’origine ne change pas');
        $this->assertNull($rule->withMaterialGauge(self::X_74500, null)->conflict(self::SALBRIS_ARGENT, self::X_74500), 'écartement inconnu : pas jugé');
    }

    public function test_mentions_give_their_position(): void
    {
        // Position en octets (« à » en compte deux) et longueur de « voie normale ».
        $this->assertSame([[16, 12]], MaterialGaugeRule::gaugeMentions('la ligne est à voie normale', 1435));
        $this->assertSame([], MaterialGaugeRule::gaugeMentions('voie métrique', 1435));
    }

    #[DataProvider('gaugeNames')]
    public function test_gauge_names(string $text, int $gauge, bool $named): void
    {
        $this->assertSame($named, MaterialGaugeRule::namesGauge($text, $gauge));
    }
}

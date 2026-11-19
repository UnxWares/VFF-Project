<?php

namespace Tests\Unit\Materials;

use App\Enums\MaterialType;
use App\Services\Materials\WikidataMaterialRules;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Règles de database/data/materiel_wikidata.php : catégorie d'après les classes Wikidata,
 * exclusions (métro, réseaux hors de France), repli sur les catégories Wikipédia, unités.
 */
class WikidataMaterialRulesTest extends TestCase
{
    private WikidataMaterialRules $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rules = WikidataMaterialRules::default();
    }

    /** @return array<string, array{list<string>, MaterialType}> */
    public static function ancestors(): array
    {
        return [
            'locomotive électrique' => [['Q625151'], MaterialType::LocomotiveElectrique],
            'vapeur avant tout le reste' => [['Q171043', 'Q93301'], MaterialType::LocomotiveVapeur],
            'locotracteur' => [['Q1079190'], MaterialType::LocomotiveDiesel],
            'rame TGV avant automotrice' => [['Q483373', 'Q13402959'], MaterialType::RameTgv],
            // Un bimode descend des rames électriques ET thermiques : automotrice.
            'bimode' => [['Q1567915', 'Q483373', 'Q3588091'], MaterialType::Automotrice],
            'rame diesel avant rame générique' => [['Q1567915', 'Q3959904'], MaterialType::Autorail],
            'rame générique' => [['Q3959904'], MaterialType::Automotrice],
            'modèle de tramway' => [['Q76154857'], MaterialType::Tramway],
            'auscultation' => [['Q1967218'], MaterialType::EnginService],
            'voiture' => [['Q16814621'], MaterialType::Voiture],
            'wagon' => [['Q9166501'], MaterialType::Wagon],
        ];
    }

    /** @param list<string> $ancestors */
    #[DataProvider('ancestors')]
    public function test_the_first_matching_rule_decides_the_category(array $ancestors, MaterialType $expected): void
    {
        $this->assertSame($expected, $this->rules->typeFor($ancestors)[0] ?? null);
    }

    public function test_a_series_without_known_class_has_no_category(): void
    {
        $this->assertNull($this->rules->typeFor(['Q1414135']));
        $this->assertNull($this->rules->typeFor([]));
    }

    public function test_the_deciding_class_is_returned_for_provenance(): void
    {
        $this->assertSame([MaterialType::LocomotiveElectrique, 'Q625151'], $this->rules->typeFor(['Q625151']));
    }

    public function test_metro_rolling_stock_is_excluded(): void
    {
        $this->assertSame('Q1144784', $this->rules->excludedRoot(['Q483373', 'Q1144784']));
        $this->assertNull($this->rules->excludedRoot(['Q483373']));
    }

    public function test_only_series_run_exclusively_abroad_are_excluded(): void
    {
        $this->assertTrue($this->rules->onlyForeignOperators(['Q3359730', 'Q20726274']));
        $this->assertFalse($this->rules->onlyForeignOperators(['Q3359730', 'Q13646']), 'aussi exploitée par la SNCF');
        $this->assertFalse($this->rules->onlyForeignOperators([]), 'sans exploitant : rattachée à la France par P17');
    }

    public function test_wikipedia_categories_are_a_fallback_and_can_exclude_metro(): void
    {
        $this->assertSame([MaterialType::Automotrice, 'Automotrice SNCF'], $this->rules->typeFromCategories(['Bombardier Transport', 'Automotrice SNCF']));
        $this->assertSame([MaterialType::LocomotiveDiesel, 'Locomotive Diesel SNCF'], $this->rules->typeFromCategories(['Locomotive Diesel SNCF']));
        $this->assertSame([null, 'Matériel roulant du métro de Lyon'], $this->rules->typeFromCategories(['Matériel roulant du métro de Lyon']));
        $this->assertNull($this->rules->typeFromCategories(['Matériel roulant Transilien']));
    }

    public function test_speeds_and_powers_are_converted_and_bounded(): void
    {
        $this->assertSame(160, $this->rules->speedKmh(160, 'Q180154'));
        $this->assertSame(161, $this->rules->speedKmh(100, 'Q211256'), 'mi/h');
        $this->assertNull($this->rules->speedKmh(160, 'Q11573'), 'unité inconnue');
        $this->assertNull($this->rules->speedKmh(5000, 'Q180154'), 'valeur absurde');

        $this->assertSame(4620, $this->rules->powerKw(4620, 'Q3320608'));
        $this->assertSame(1471, $this->rules->powerKw(2000, 'Q160857'), 'cheval-vapeur métrique');
        $this->assertSame(4400, $this->rules->powerKw(4.4, 'Q6982035'), 'mégawatt');
        $this->assertNull($this->rules->powerKw(0, 'Q3320608'));
    }
}

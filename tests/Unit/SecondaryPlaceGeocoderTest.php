<?php

namespace Tests\Unit;

use App\Services\SecondaryPlaceGeocoder;
use App\Services\Traction\ElectrificationText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Parties pures du géocodeur hors ligne des réseaux secondaires (sans les
 * tables communes / stations) : départements cités dans un titre, variantes
 * d'un libellé de gare, distance approchée. Plus deux lectures de texte
 * d'électrification non couvertes ailleurs (fréquence, troisième rail).
 */
class SecondaryPlaceGeocoderTest extends TestCase
{
    /** @return array<string, array{0: string, 1: bool, 2: list<string>}> */
    public static function departementsProvider(): array
    {
        return [
            'nom composé' => ['Chemins de fer départementaux de la Haute-Vienne', true, ['87']],
            'Haute-Vienne n\'est pas Vienne' => ['Tramways de la Haute-Vienne', false, ['87']],
            'deux départements' => ['Tramways de l\'Ain et de la Vienne', true, ['01', '86']],
            'ville homonyme (strict)' => ['Ligne de Vienne à Lyon', true, []],
            'ville homonyme (souple)' => ['Ligne de Vienne à Lyon', false, ['86']],
            'Nord avec article' => ['Réseau du Nord et du Loir-et-Cher', true, ['41', '59']],
            'Corse-du-Sud' => ['Chemins de fer de la Corse-du-Sud', false, ['2A']],
            'ancien nom' => ['Tramways des Côtes-du-Nord', false, ['22']],
            'ancien nom (Basses-Alpes)' => ['Chemins de fer des Basses-Alpes', false, ['04']],
            'aucun' => ['Ligne de Carhaix à Morlaix', true, []],
        ];
    }

    #[DataProvider('departementsProvider')]
    public function test_departements_named_in_a_title(string $text, bool $strict, array $expected): void
    {
        $this->assertEqualsCanonicalizing($expected, SecondaryPlaceGeocoder::departementsIn($text, $strict));
    }

    public function test_departement_codes_are_insee_codes(): void
    {
        foreach (SecondaryPlaceGeocoder::DEPARTEMENTS as $name => $code) {
            $this->assertMatchesRegularExpression('/^(?:0[1-9]|[1-8]\d|9[0-5]|2A|2B|97[1-6])$/', $code, $name);
        }
    }

    /** @return array<string, array{0: string, 1: list<string>}> */
    public static function variantsProvider(): array
    {
        return [
            'compagnie' => ['Bourg-en-Bresse ESE', ['Bourg-en-Bresse ESE', 'Bourg-en-Bresse']],
            'ville' => ['Concarneau Ville', ['Concarneau Ville', 'Concarneau']],
            'réseau collé' => ['Châteaulin-PO', ['Châteaulin-PO', 'Châteaulin']],
            'deux suffixes' => ['Quimper Ville Gare', ['Quimper Ville Gare', 'Quimper Ville', 'Quimper']],
            'gare de' => ['Gare de La Brohinière', ['Gare de La Brohinière', 'La Brohinière']],
            'qualificatif entre parenthèses' => ['Cerdon (Ain)', ['Cerdon']],
            'nom simple' => ['Saint-Martin', ['Saint-Martin']],
        ];
    }

    #[DataProvider('variantsProvider')]
    public function test_variants_of_a_station_label(string $label, array $expected): void
    {
        $this->assertSame($expected, (new SecondaryPlaceGeocoder)->variants($label));
    }

    /** @return array<string, array{0: string, 1: list<string>}> */
    public static function articleVariantsProvider(): array
    {
        return [
            // « Grand-Lucé (?) - Saint-Calais » (Tramways de la Sarthe) : la commune est Le Grand-Lucé.
            'article manquant' => ['Grand-Lucé', ['Le Grand-Lucé', 'La Grand-Lucé', 'Les Grand-Lucé']],
            'voyelle : élision' => ['Isle-Adam', ["L'Isle-Adam"]],
            // « Les Errues » (Territoire de Belfort) : « Ligne des Errues à Rougemont » ne garde que « Errues ».
            'article retiré' => ['Les Errues', ['Errues']],
            'élision retirée' => ["L'Aber-Wrac'h", ["Aber-Wrac'h"]],
            'minuscule : rien' => ['gare du Nord', []],
        ];
    }

    #[DataProvider('articleVariantsProvider')]
    public function test_article_variants(string $label, array $expected): void
    {
        $this->assertSame($expected, SecondaryPlaceGeocoder::articleVariants($label));
    }

    public function test_meters_is_a_symmetric_local_distance(): void
    {
        $g = new SecondaryPlaceGeocoder;

        $this->assertSame(0.0, $g->meters([2.35, 48.85], [2.35, 48.85]));
        $this->assertEqualsWithDelta(733, $g->meters([2.35, 48.85], [2.36, 48.85]), 2);
        $this->assertEqualsWithDelta(1105, $g->meters([2.35, 48.85], [2.35, 48.86]), 2);
        $this->assertSame($g->meters([2.35, 48.85], [4.83, 45.76]), $g->meters([4.83, 45.76], [2.35, 48.85]));
        // Paris - Lyon à vol d'oiseau : environ 392 km.
        $this->assertEqualsWithDelta(392_000, $g->meters([2.35, 48.85], [4.83, 45.76]), 5_000);
    }

    // ─── Texte d'électrification ─────────────────────────────────────────

    /** @return array<string, array{0: string, 1: ?float}> */
    public static function frequencyProvider(): array
    {
        return [
            '50 Hz' => ['25 kV 50 Hz', 50.0],
            '16,7 Hz' => ['15 kV 16,7 Hz', 16.7],
            '16 2/3 Hz' => ['15 kV 16 2/3 Hz', 16.7],
            'continu' => ['1500 V continu', 0.0],
            'cc' => ['1 500 V cc', 0.0],
            'muet' => ['caténaire', null],
        ];
    }

    #[DataProvider('frequencyProvider')]
    public function test_electrification_frequency(string $text, ?float $expected): void
    {
        $this->assertSame($expected, ElectrificationText::frequency($text));
    }

    public function test_third_rail(): void
    {
        $this->assertTrue(ElectrificationText::thirdRail('750 V par troisième rail'));
        $this->assertTrue(ElectrificationText::thirdRail('1500 V par 3e rail'));
        $this->assertTrue(ElectrificationText::thirdRail('rail de contact latéral'));
        $this->assertFalse(ElectrificationText::thirdRail('caténaire 1500 V'));
    }
}

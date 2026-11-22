<?php

namespace Tests\Unit\Media\OpenData;

use App\Services\Media\OpenData\OpenDataText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Pliage des noms, jetons de gares, dates françaises et noms de rues écartés (sans base ni réseau). */
class OpenDataTextTest extends TestCase
{
    public function test_fold_removes_accents_case_and_punctuation_and_expands_saint(): void
    {
        $this->assertSame('saint cloud', OpenDataText::fold('St-Cloud'));
        $this->assertSame('sainte marie a py', OpenDataText::fold('Ste Marie-à-Py'));
        $this->assertSame('fere champenoise', OpenDataText::fold('Fère-Champenoise'));
        $this->assertSame('coeur', OpenDataText::fold('Cœur'));
    }

    public function test_tokens_drop_stop_words_and_the_word_gare(): void
    {
        $this->assertSame(['becon', 'bruyeres'], OpenDataText::tokens('Gare de Bécon-les-Bruyères'));
        $this->assertSame(['chaville', 'velizy'], OpenDataText::tokens('Chaville-Vélizy'));
        $this->assertSame([], OpenDataText::tokens('La Gare'));
    }

    /** @return array<string, array{0: mixed, 1: bool, 2: array{captured_at: ?string, precision: ?string, year: ?int, year_to: ?int}}> */
    public static function dates(): array
    {
        return [
            'jour' => ['8 avril 1924', false, ['captured_at' => '1924-04-08', 'precision' => 'day', 'year' => 1924, 'year_to' => null]],
            'premier du mois' => ['1 mai 1920', false, ['captured_at' => '1920-05-01', 'precision' => 'day', 'year' => 1920, 'year_to' => null]],
            'mois approximatif' => ['juin 1912 (?)', false, ['captured_at' => '1912-06-01', 'precision' => 'circa', 'year' => 1912, 'year_to' => null]],
            'mois' => ['mars 1920', false, ['captured_at' => '1920-03-01', 'precision' => 'month', 'year' => 1920, 'year_to' => null]],
            'année' => [1917, false, ['captured_at' => null, 'precision' => 'year', 'year' => 1917, 'year_to' => null]],
            'année ronde de carte postale' => [1900, true, ['captured_at' => null, 'precision' => 'circa', 'year' => 1900, 'year_to' => null]],
            'intervalle' => ['1895 - 1914', false, ['captured_at' => null, 'precision' => 'range', 'year' => 1895, 'year_to' => 1914]],
            'même année deux fois' => ['1907 - 1907', false, ['captured_at' => null, 'precision' => 'year', 'year' => 1907, 'year_to' => null]],
            'avant' => ['avant 1920', false, ['captured_at' => null, 'precision' => 'before', 'year' => 1920, 'year_to' => null]],
            'jour impossible : le mois reste' => ['31 février 1920', false, ['captured_at' => '1920-02-01', 'precision' => 'month', 'year' => 1920, 'year_to' => null]],
            'collée AAAAMMJJ' => ['20220612', false, ['captured_at' => '2022-06-12', 'precision' => 'day', 'year' => 2022, 'year_to' => null]],
            'vide' => [null, false, ['captured_at' => null, 'precision' => null, 'year' => null, 'year_to' => null]],
            'étoile' => ['*', false, ['captured_at' => null, 'precision' => null, 'year' => null, 'year_to' => null]],
        ];
    }

    /** @param  array{captured_at: ?string, precision: ?string, year: ?int, year_to: ?int}  $expected */
    #[DataProvider('dates')]
    public function test_french_dates(mixed $raw, bool $round, array $expected): void
    {
        $this->assertSame($expected, OpenDataText::date($raw, $round));
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function streets(): array
    {
        return [
            'rue de la gare' => ['Rue de la Gare et Aubriet', 'et Aubriet'],
            'boulevard des trois gares' => ['Boulevard des Trois-Gares', ''],
            'avenue des 2 gares' => ["Angle de l'Avenue des 2 Gares", "Angle de l'"],
            'rue du chemin de fer' => ['La Rue du Chemin de fer', 'La'],
            'quartier' => ['Panorama sur le Quartier de la Gare', 'Panorama sur le'],
            'café de la gare' => ['Café-Restaurant de la Gare Chaville-Vélizy', 'Chaville-Vélizy'],
            'gare du funiculaire' => ['Bas-Meudon - Gare du Funiculaire', 'Bas-Meudon -'],
            'hôtel avec nom propre' => ['Génelard. Hôtel Courtois de la gare.', 'Génelard. .'],
            'environs de la gare' => ['Les restaurants aux Environs de la Gare', 'Les restaurants aux'],
            'une vraie gare reste' => ['La Gare de Fontenay-aux-Roses', 'La Gare de Fontenay-aux-Roses'],
            'place de la gare reste' => ['La Place de la Gare', 'La Place de la Gare'],
        ];
    }

    #[DataProvider('streets')]
    public function test_street_and_business_names_are_not_stations(string $title, string $expected): void
    {
        $this->assertSame($expected, trim((string) preg_replace('/\s+/', ' ', OpenDataText::withoutStreets($title, true))));
    }

    public function test_a_funicular_station_keeps_its_railway_words_for_the_rail_filter(): void
    {
        $this->assertSame('Bas-Meudon - Gare du Funiculaire', OpenDataText::withoutStreets('Bas-Meudon - Gare du Funiculaire'));
    }

    public function test_a_csv_read_as_windows_1250_is_put_back_to_windows_1252(): void
    {
        // Texte réel de la ressource « Cartes postales » de Saône-et-Loire servie par l'API tabulaire de data.gouv.fr.
        $row = OpenDataText::repairCp1250(['Date' => 'Aprčs 1903', 'Période historique' => 'Troisičme République', 'Titre' => 'Autun. Théâtre et avenue de la Gare.', 'Nom image' => '6FI_57.jpg', 'n' => 3]);

        $this->assertSame('Après 1903', $row['Date']);
        $this->assertSame('Troisième République', $row['Période historique']);
        $this->assertSame('Autun. Théâtre et avenue de la Gare.', $row['Titre']);
        $this->assertSame('6FI_57.jpg', $row['Nom image']);
        $this->assertSame(3, $row['n']);
    }

    public function test_english_dates_of_the_getty_are_read(): void
    {
        $this->assertSame(['captured_at' => null, 'precision' => 'circa', 'year' => 1861, 'year_to' => null], OpenDataText::date('about 1861'));
        $this->assertSame(['captured_at' => '1866-06-01', 'precision' => 'month', 'year' => 1866, 'year_to' => null], OpenDataText::date('June 1866'));
        $this->assertSame(['captured_at' => '1865-07-01', 'precision' => 'month', 'year' => 1865, 'year_to' => null], OpenDataText::date('July 1865'));
        $this->assertSame('after', OpenDataText::date('Après 1903')['precision']);
    }

    public static function manifestTitles(): array
    {
        // Titres réels de l'École des ponts (btv1b10483104f, btv1b104834464, btv1b10484741c, btv1b104820161).
        return [
            'série après deux-points' => ['Gare de Nice : Ministère des travaux publics. Photographies. Alpes-Maritimes', 'Gare de Nice', 'Ministère des travaux publics. Photographies. Alpes-Maritimes'],
            'sans titre et crochets' => ['S. T. [Gare de Chateaudun] : Photographies provenant de la Bibliothèque de Mr Sc', 'Gare de Chateaudun', 'Photographies provenant de la Bibliothèque de Mr Sc'],
            'titre simple' => ['Canal du Nivernais. Tranchée du bief de partage.', 'Canal du Nivernais. Tranchée du bief de partage.', null],
            'entité HTML' => ['Plan, Side Elevation &amp; End Views of LargeGarnskirk Locomotive : George Stephenson', 'Plan, Side Elevation & End Views of LargeGarnskirk Locomotive', 'George Stephenson'],
            'crochets seuls' => ['[Phare de la Corne]', 'Phare de la Corne', null],
        ];
    }

    #[DataProvider('manifestTitles')]
    public function test_a_library_title_loses_its_series_and_brackets(string $raw, string $title, ?string $rest): void
    {
        $this->assertSame([$title, $rest], OpenDataText::cleanTitle($raw));
    }

    public function test_clean_title_uses_the_site_hyphen_instead_of_long_dashes(): void
    {
        $this->assertSame(['Chemin de fer du Righi - gare de Vitznau', null], OpenDataText::cleanTitle("Chemin de fer du Righi \u{2013} gare de Vitznau"));
        $this->assertSame(['Gare de Vichy - la cour', null], OpenDataText::cleanTitle("Gare de Vichy \u{2014} la cour"));
    }
}

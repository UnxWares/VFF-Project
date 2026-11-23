<?php

namespace Tests\Unit\Traction;

use App\Services\Traction\LineElectrificationHistory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Cas limites de la lecture prudente de l'électrification : dates (1900, 1951, aujourd'hui), tensions (20 000 V),
 * fourchettes, états, sources, portée, extrait, fusion des tranches (50 m), résumé.
 */
class LineElectrificationHistoryBoundaryTest extends TestCase
{
    private const TODAY = '2026-11-23';

    /**
     * @param  array<string, mixed>  $over
     * @return array<string, mixed>
     */
    private function event(string $quote, string $date, string $precision = 'year', array $over = []): array
    {
        return $over + [
            'event_date' => $date, 'date_precision' => $precision, 'description' => 'Électrification (Wikipédia, texte, « Histoire »)',
            'source' => 'wikipedia', 'source_quote' => $quote, 'source_url' => 'https://fr.wikipedia.org/w/index.php?title=X&oldid=1',
            'confidence' => 'medium', 'section_from' => null, 'section_to' => null,
            'section_pk_start_m' => null, 'section_pk_end_m' => null, 'section_resolution' => 'whole_line',
        ];
    }

    /** @return array<string, mixed>|null */
    private function read(string $quote, string $date, string $precision = 'year', array $over = []): ?array
    {
        return LineElectrificationHistory::read($this->event($quote, $date, $precision, $over), self::TODAY);
    }

    public function test_the_first_year_is_1900_inclusive(): void
    {
        $this->assertNull($this->read('La ligne a été électrifiée en 1899 en 600 V continu', '1899-12-31'));
        $this->assertNotNull($this->read('La ligne a été électrifiée en 1900 en 600 V continu', '1900-01-01'));
    }

    public function test_alternating_current_is_possible_from_1951_inclusive(): void
    {
        $this->assertNull($this->read('La ligne a été électrifiée en 25 kV en 1950', '1950-01-01'));
        $this->assertNotNull($this->read('La ligne a été électrifiée en 25 kV en 1951', '1951-01-01'));
        // 20 000 V : seuil inclusif de l'alternatif ; 19 999 n'est pas une tension de traction citée.
        $this->assertNull($this->read('La ligne a été électrifiée en 20 kV en 1950', '1950-01-01'));
        // 15 kV (16,7 Hz) ou 12 kV avant 1951 : continu ou basse tension, permis.
        $this->assertNotNull($this->read('La ligne a été électrifiée en 15 kV en 1930', '1930-01-01'));
        $this->assertNotNull($this->read('La ligne a été électrifiée en 1500 V continu en 1950', '1950-01-01'));
    }

    public function test_a_deelectrification_before_1951_is_not_blocked_by_a_voltage(): void
    {
        $fact = $this->read('La ligne de 25 kV a été dés-électrifiée en 1950', '1950-01-01');

        $this->assertSame('deelectrification', $fact['kind'] ?? null);
    }

    public function test_today_is_allowed_and_tomorrow_is_not(): void
    {
        $this->assertNotNull($this->read('La ligne a été électrifiée en 25 kV le 23 novembre 2026', '2026-11-23', 'day'));
        $this->assertNull($this->read('La ligne a été électrifiée en 25 kV le 24 novembre 2026', '2026-11-24', 'day'));
    }

    public function test_without_an_explicit_today_the_real_clock_is_used(): void
    {
        $future = date('Y-m-d', strtotime('+2 days'));
        $year = substr($future, 0, 4);

        $this->assertNull(LineElectrificationHistory::read($this->event("La ligne a été électrifiée en 25 kV en {$year}", $future)));
        $this->assertNotNull(LineElectrificationHistory::read($this->event("La ligne a été électrifiée en 25 kV en {$year}", date('Y-m-d', strtotime('-2 days')))));
        $this->assertNotNull(LineElectrificationHistory::read($this->event('La ligne a été électrifiée en 25 kV en 1960', '1960-01-01')));
    }

    /** @return iterable<string, array{string, string}> */
    public static function ranges(): iterable
    {
        yield 'entre et' => ['La ligne est électrifiée entre 1926 et 1938 en 1500 V continu', '1926-01-01'];
        yield 'de à' => ['La ligne est électrifiée de 1926 à 1938 en 1500 V continu', '1926-01-01'];
        yield 'depuis et' => ['La ligne est électrifiée depuis 1926 et 1938 en 1500 V continu', '1926-01-01'];
        yield 'tiret' => ['La ligne est électrifiée en 1926-1938 en 1500 V continu', '1926-01-01'];
        yield 'tiret demi-cadratin' => ["La ligne est électrifiée en 1926\u{2013}1938 en 1500 V continu", '1926-01-01'];
        yield 'tiret entouré d\'espaces' => ['La ligne est électrifiée en 1926 - 1938 en 1500 V continu', '1926-01-01'];
        yield 'siècle 20' => ['La ligne est électrifiée de 2001 à 2005 en 25 kV', '2001-01-01'];
    }

    #[DataProvider('ranges')]
    public function test_a_range_never_dates_a_commissioning(string $quote, string $date): void
    {
        $this->assertNull($this->read($quote, $date));
    }

    public function test_a_range_with_the_year_also_alone_outside_is_kept(): void
    {
        $this->assertNotNull($this->read('La ligne est électrifiée en 1500 V continu en 1926, puis étendue entre 1926 et 1938', '1926-01-01'));
    }

    public function test_the_year_must_appear_as_a_whole_number_in_the_sentence(): void
    {
        $this->assertNull($this->read('La ligne a été électrifiée en 19260 en 1500 V continu', '1926-01-01'));
        $this->assertNull($this->read('La ligne a été électrifiée en 11926 en 1500 V continu', '1926-01-01'));
        $this->assertNotNull($this->read('La ligne a été électrifiée en 1926 en 1500 V continu', '1926-01-01'));
        $this->assertNull($this->read('La ligne a été électrifiée le 3 mars en 25 kV', '1960-03-03', 'year'));
        $this->assertNotNull($this->read('La ligne a été électrifiée le 3 mars 1960 en 25 kV', '1960-03-03', 'day'));
    }

    public function test_a_future_or_project_sentence_is_ignored_in_every_form(): void
    {
        foreach (['sera électrifiée', 'devrait être électrifiée', 'doit être électrifiée', 'est prévue pour être électrifiée', 'est programmée et électrifiée', 'est envisagée et électrifiée',
            'est projetée et électrifiée', 'est mise en chantier et électrifiée', 'est mise en œuvre et électrifiée'] as $verb) {
            $this->assertNull($this->read("La ligne {$verb} en 1960 en 25 kV", '1960-01-01'), $verb);
        }
    }

    public function test_a_state_sentence_is_ignored_but_only_around_the_year(): void
    {
        $this->assertNull($this->read('Depuis 1938 le plus long axe électrifié de France', '1938-01-01'));
        // L'état est dans une autre proposition que l'année : le fait est gardé.
        $this->assertNotNull($this->read('La ligne a été électrifiée en 1500 V continu en 1938 ; plus long axe électrifié de France', '1938-01-01'));
    }

    public function test_an_infobox_needs_its_year_in_the_quote(): void
    {
        $this->assertNull($this->read('Infobox : électrification = 1954', '1958-01-01'));
        $this->assertNotNull($this->read('Infobox : électrification = 1958', '1958-01-01'));
        $this->assertSame('unspecified', $this->read('Infobox : électrification = 1958', '1958-01-01')['scope']);
    }

    public function test_the_precision_gives_the_reliability_and_structured_sources_stay_medium(): void
    {
        $text = 'Le 5 mars 1960, électrification en 25 kV du triage';
        $this->assertSame('high', $this->read($text, '1960-03-05', 'day')['reliability']);
        $this->assertSame('high', $this->read($text, '1960-03-01', 'month')['reliability']);
        $this->assertSame('medium', $this->read('La ligne est électrifiée en 25 kV en 1960', '1960-01-01', 'year')['reliability']);
        $this->assertSame('medium', $this->read('Électrification', '1960-03-05', 'day', ['source' => 'manual', 'description' => 'Électrification de la ligne en 25 kV'])['reliability']);
        $this->assertSame('medium', $this->read('Électrification', '1960-03-05', 'day', ['source' => 'wikidata', 'description' => 'Électrification de la ligne en 25 kV'])['reliability']);
    }

    public function test_a_missing_precision_defaults_to_year(): void
    {
        $e = $this->event('La ligne est électrifiée en 25 kV en 1960', '1960-01-01');
        unset($e['date_precision']);

        $this->assertSame('year', LineElectrificationHistory::read($e, self::TODAY)['date_precision']);
    }

    public function test_the_excerpt_is_kept_only_for_wikipedia_cut_at_255_characters_by_characters(): void
    {
        $quote = 'La ligne a été électrifiée en 25 kV en 1960 '.str_repeat('é', 400);
        $fact = $this->read($quote, '1960-01-01');

        $this->assertSame(255, mb_strlen($fact['source']['excerpt']));
        $this->assertSame(mb_substr($quote, 0, 255), $fact['source']['excerpt']);
        $this->assertNull($this->read('x', '1960-01-01', 'year', ['source' => 'wikidata', 'description' => 'Électrification en 25 kV'])['source']['excerpt']);
    }

    public function test_an_empty_quote_falls_back_to_the_description_and_a_blank_one_gives_a_null_excerpt(): void
    {
        $fact = $this->read('   ', '1960-01-01', 'year', ['source' => 'wikipedia', 'description' => 'Électrification en 25 kV de la ligne, 1960']);

        $this->assertSame('Électrification en 25 kV de la ligne, 1960', $fact['source']['excerpt']);
        // Une source structurée n'a pas besoin de phrase : le fait est la date, sans tension ni extrait.
        $bare = $this->read('', '1960-01-01', 'year', ['source' => 'manual', 'description' => '', 'section_resolution' => null]);
        $this->assertSame('electrification', $bare['kind']);
        $this->assertNull($bare['voltage_v']);
        $this->assertNull($bare['system']);
        $this->assertNull($bare['source']['excerpt']);
        // Une phrase vide d'une source non structurée ne dit rien : écartée.
        $this->assertNull($this->read('', '1960-01-01', 'year', ['source' => 'wikipedia', 'description' => '']));
    }

    public function test_the_source_url_is_passed_through_or_null(): void
    {
        $this->assertSame('https://fr.wikipedia.org/w/index.php?title=X&oldid=1', $this->read('La ligne est électrifiée en 25 kV en 1960', '1960-01-01')['source']['url']);
        $this->assertNull($this->read('La ligne est électrifiée en 25 kV en 1960', '1960-01-01', 'year', ['source_url' => null])['source']['url']);
    }

    public function test_the_scope_is_section_when_names_or_kilometre_points_are_given(): void
    {
        $quote = 'La ligne est électrifiée en 25 kV en 1960';

        $this->assertSame('section', $this->read($quote, '1960-01-01', 'year', ['section_from' => 'A', 'section_to' => 'B'])['scope']);
        $this->assertSame('whole_line', $this->read($quote, '1960-01-01', 'year', ['section_from' => 'A'])['scope']);
        $this->assertSame('whole_line', $this->read($quote, '1960-01-01', 'year', ['section_to' => 'B'])['scope']);
        $this->assertSame('section', $this->read($quote, '1960-01-01', 'year', ['section_pk_start_m' => 1000, 'section_pk_end_m' => 5000])['scope']);
        $this->assertSame('whole_line', $this->read($quote, '1960-01-01', 'year', ['section_pk_start_m' => 1000])['scope']);
        $this->assertSame('unspecified', $this->read($quote, '1960-01-01', 'year', ['section_resolution' => 'places'])['scope']);
        $this->assertSame('unspecified', $this->read($quote, '1960-01-01', 'year', ['section_resolution' => null])['scope']);
    }

    public function test_pk_zero_counts_as_given_and_a_reversed_pair_is_ordered(): void
    {
        $fact = $this->read('La ligne est électrifiée en 25 kV en 1960', '1960-01-01', 'year', ['section_pk_start_m' => 9000, 'section_pk_end_m' => 0]);

        $this->assertSame('section', $fact['scope']);
        $this->assertSame(['from' => null, 'to' => null, 'pk_start_m' => 0, 'pk_end_m' => 9000], $fact['section']);
    }

    public function test_a_whole_line_date_needs_a_year_in_the_text_and_no_named_section_pair(): void
    {
        // Deux noms propres reliés par un tiret demi-cadratin : une portion, pas toute la ligne.
        $this->assertSame('unspecified', $this->read("La section Dax \u{2013} Hendaye est électrifiée en 1500 V en 1926", '1926-01-01')['scope']);
        $this->assertSame('whole_line', $this->read('La ligne est électrifiée en 1500 V en 1926', '1926-01-01')['scope']);
    }

    public function test_the_deelectrification_and_conversion_kinds(): void
    {
        $this->assertSame('deelectrification', $this->read('La caténaire a été déposée en 1992', '1992-01-01')['kind']);
        $this->assertSame('deelectrification', $this->read('La ligne est mise hors tension en 1992', '1992-01-01')['kind']);
        $this->assertSame('conversion', $this->read('La ligne électrifiée est convertie en 25 kV en 1960', '1960-01-01')['kind']);
        $this->assertSame('conversion', $this->read('Passage en 25 kV en 1960 de la ligne électrifiée', '1960-01-01')['kind']);
        $this->assertSame('electrification', $this->read('La ligne est électrifiée en 25 kV en 1960', '1960-01-01')['kind']);
        // Conversion sans tension citée : seule « ré-électrifiée » suffit.
        $this->assertSame('electrification', $this->read('La ligne est électrifiée par caténaire en 1960, remplacement du ballast', '1960-01-01')['kind']);
    }

    public function test_systems_by_voltage_text_and_boundary(): void
    {
        $this->assertSame('dc', LineElectrificationHistory::systemOf(9999, ''));
        $this->assertSame('ac', LineElectrificationHistory::systemOf(10000, ''));
        $this->assertSame('dc', LineElectrificationHistory::systemOf(1500, 'continu'));
        $this->assertSame('third_rail', LineElectrificationHistory::systemOf(750, 'par troisième rail'));
        $this->assertSame('third_rail', LineElectrificationHistory::systemOf(750, 'Troisieme Rail'));
        $this->assertSame('third_rail', LineElectrificationHistory::systemOf(25000, 'rail latéral'));
        $this->assertSame('third_rail', LineElectrificationHistory::systemOf(750, 'rail lateral'));
    }

    public function test_system_from_the_sncf_columns(): void
    {
        $this->assertSame('third_rail', LineElectrificationHistory::system(true, null));
        $this->assertSame('third_rail', LineElectrificationHistory::system(true, 50.0));
        $this->assertSame('unknown', LineElectrificationHistory::system(false, null));
        $this->assertSame('dc', LineElectrificationHistory::system(false, 0.0));
        $this->assertSame('ac', LineElectrificationHistory::system(false, 0.1));
        $this->assertSame('dc', LineElectrificationHistory::system(false, -1.0));
        $this->assertSame('ac', LineElectrificationHistory::system(false, 16.7));
    }

    public function test_the_merge_gap_is_50_metres_inclusive(): void
    {
        $row = fn (int $from, int $to, ?int $v = 1500, string $system = 'dc'): array => ['from' => $from, 'to' => $to, 'v' => $v, 'system' => $system];

        $this->assertCount(1, LineElectrificationHistory::merge([$row(0, 1000), $row(1050, 2000)]));
        $this->assertCount(2, LineElectrificationHistory::merge([$row(0, 1000), $row(1051, 2000)]));
        $this->assertCount(1, LineElectrificationHistory::merge([$row(0, 1000), $row(1000, 2000)]));
        $this->assertCount(1, LineElectrificationHistory::merge([$row(0, 1000), $row(900, 2000)]));
        $this->assertSame(50, LineElectrificationHistory::MERGE_GAP_M);
    }

    public function test_the_merge_keeps_the_widest_end_and_separates_voltage_system_and_null(): void
    {
        $row = fn (int $from, int $to, ?int $v = 1500, string $system = 'dc'): array => ['from' => $from, 'to' => $to, 'v' => $v, 'system' => $system];

        $this->assertSame([$row(0, 5000)], LineElectrificationHistory::merge([$row(0, 5000), $row(100, 200)]));
        $this->assertCount(2, LineElectrificationHistory::merge([$row(0, 1000), $row(1000, 2000, 750)]));
        $this->assertCount(2, LineElectrificationHistory::merge([$row(0, 1000), $row(1000, 2000, 1500, 'third_rail')]));
        $this->assertCount(2, LineElectrificationHistory::merge([$row(0, 1000), $row(1000, 2000, null)]));
        $this->assertCount(1, LineElectrificationHistory::merge([$row(0, 1000, null, 'unknown'), $row(1000, 2000, null, 'unknown')]));
        $this->assertSame([], LineElectrificationHistory::merge([]));
        $this->assertCount(3, LineElectrificationHistory::merge([$row(0, 1000), $row(2000, 3000), $row(4000, 5000)]));
    }

    public function test_the_summary_of_nothing_and_of_unknown_voltage(): void
    {
        $this->assertSame(['line_electrified' => false, 'line_voltage_v' => null, 'electrified_m' => 0, 'by_voltage' => []], LineElectrificationHistory::summary([]));
        $s = LineElectrificationHistory::summary([
            // Tranches à la suite : une tranche recouverte n'est comptée qu'une fois (LineElectrificationHistoryReadTest).
            ['pk_start_m' => 0, 'length_m' => 1000, 'voltage_v' => null, 'system' => 'unknown'],
            ['pk_start_m' => 1000, 'length_m' => 1000, 'voltage_v' => 1500, 'system' => 'dc'],
            ['pk_start_m' => 2000, 'length_m' => 2500, 'voltage_v' => 25000, 'system' => 'ac'],
            ['pk_start_m' => 4500, 'length_m' => 500, 'voltage_v' => 25000, 'system' => 'ac'],
        ]);

        $this->assertSame(5000, $s['electrified_m']);
        $this->assertSame([25000, null, 1500], array_column($s['by_voltage'], 'voltage_v'));
        $this->assertSame(3000, $s['by_voltage'][0]['length_m']);
    }

    public function test_the_same_voltage_in_two_systems_stays_apart_in_the_summary(): void
    {
        $s = LineElectrificationHistory::summary([
            ['pk_start_m' => 0, 'length_m' => 1000, 'voltage_v' => 750, 'system' => 'dc'],
            ['pk_start_m' => 0, 'length_m' => 3000, 'voltage_v' => 750, 'system' => 'third_rail'],
        ]);

        $this->assertSame(['third_rail', 'dc'], array_column($s['by_voltage'], 'system'));
    }

    public function test_provenance_and_constants(): void
    {
        $p = LineElectrificationHistory::provenance();

        $this->assertSame('ODbL-1.0', $p['ranges']['license']);
        $this->assertStringContainsString('CC0', $p['history']['license']);
        $this->assertSame(['wikipedia', 'wikidata', 'manual'], LineElectrificationHistory::PROVIDERS);
        $this->assertNotContains('lignes-oubliees', LineElectrificationHistory::PROVIDERS);
    }

    public function test_a_travaux_start_is_ignored_even_when_the_rest_reads_like_a_commissioning(): void
    {
        $this->assertNull($this->read('Début des travaux en 1960, la ligne est électrifiée en 25 kV', '1960-01-01'));
        $this->assertNotNull($this->read('La ligne est électrifiée en 25 kV en 1960', '1960-01-01'));
    }

    public function test_a_structured_source_takes_a_missing_voltage_from_its_description_but_wikipedia_does_not(): void
    {
        $structured = $this->read('Wikidata Q1 · P930 = électrification', '1958-01-01', 'year', ['source' => 'wikidata', 'description' => 'Électrification en 25 kV']);
        $this->assertSame(25000, $structured['voltage_v']);
        $manual = $this->read('', '1958-01-01', 'year', ['source' => 'manual', 'description' => 'Électrification en 1500 V']);
        $this->assertSame(1500, $manual['voltage_v']);
        $this->assertSame('dc', $manual['system']);

        $wikipedia = $this->read('La ligne est électrifiée en 1958', '1958-01-01', 'year', ['description' => 'Électrification en 1500 V']);
        $this->assertNull($wikipedia['voltage_v']);
        $this->assertNull($wikipedia['system']);
    }

    public function test_the_noun_form_needs_a_voltage_or_a_full_date(): void
    {
        $text = "L'électrification de la ligne est achevée le 3 mars 1960";
        $other = ['description' => 'Événement de la ligne'];

        $this->assertNotNull($this->read($text, '1960-03-03', 'day', $other));
        $this->assertNotNull($this->read($text, '1960-03-01', 'month', $other));
        $this->assertNull($this->read($text, '1960-01-01', 'year', $other));
        $this->assertNotNull($this->read("L'électrification en 1500 V de la ligne est achevée en 1960", '1960-01-01', 'year', $other));
    }

    public function test_a_full_date_with_a_description_that_starts_with_electrification_needs_no_verb(): void
    {
        $text = 'La section est achevée le 3 mars 1960';

        $this->assertNotNull($this->read($text, '1960-03-03', 'day'));
        $this->assertNull($this->read($text, '1960-03-03', 'day', ['description' => 'Événement de la ligne']));
        $this->assertNull($this->read($text, '1960-01-01', 'year'));
    }

    public function test_a_range_blocks_even_a_commissioning_confidence_when_the_year_is_only_in_the_range(): void
    {
        $this->assertNull($this->read('La ligne est mise sous tension entre 1960 et 1962 en 25 kV', '1960-01-01'));
        $this->assertNotNull($this->read('La ligne est mise sous tension en 25 kV en 1960', '1960-01-01'));
    }

    public function test_a_commissioning_without_the_year_in_the_text_needs_a_sure_confidence_and_stays_unspecified(): void
    {
        $text = 'La section est mise sous tension';

        $medium = $this->read($text, '1960-01-01', 'year', ['confidence' => 'medium']);
        $this->assertNotNull($medium);
        $this->assertSame('unspecified', $medium['scope']);
        $this->assertNotNull($this->read($text, '1960-01-01', 'year', ['confidence' => 'high']));
        $this->assertNull($this->read($text, '1960-01-01', 'year', ['confidence' => 'low']));
        $unknown = $this->event($text, '1960-01-01');
        unset($unknown['confidence']);
        $this->assertNull(LineElectrificationHistory::read($unknown, self::TODAY));
    }

    public function test_a_sentence_without_the_year_needs_a_precise_date_and_a_voltage(): void
    {
        $text = 'La ligne est électrifiée en 25 kV';

        $this->assertNotNull($this->read($text, '1960-03-03', 'day'));
        $this->assertNotNull($this->read($text, '1960-03-01', 'month'));
        $this->assertNull($this->read($text, '1960-01-01', 'year'));
        $this->assertNull($this->read('La ligne est électrifiée', '1960-03-03', 'day'));
    }
}

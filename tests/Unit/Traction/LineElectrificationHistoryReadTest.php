<?php

namespace Tests\Unit\Traction;

use App\Services\Traction\LineElectrificationHistory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Lecture prudente d'un événement d'électrification : seuls les faits sûrs sont rendus, les cas ambigus sont écartés.
 */
class LineElectrificationHistoryReadTest extends TestCase
{
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

    public function test_two_overlapping_routes_are_electrified_once(): void
    {
        // 890000 (SNCF Réseau) : itinéraire 1 sous 1 500 V de 8+120 à 131+281, itinéraire 2 sous 25 kV de 60+215 à 75+559.
        $range = fn (int $from, int $to, int $v, string $system): array => ['pk_start_m' => $from, 'pk_end_m' => $to,
            'length_m' => $to - $from, 'voltage_v' => $v, 'system' => $system];
        $summary = LineElectrificationHistory::summary([
            $range(8120, 131281, 1500, 'dc'), $range(60215, 75559, 25000, 'ac'), $range(131281, 152328, 25000, 'ac'),
        ]);

        $this->assertSame(144208, $summary['electrified_m'], 'réunion des tranches, recouvrement compté une fois');
        $this->assertSame([[1500, 123161], [25000, 36391]], array_map(fn (array $b): array => [$b['voltage_v'], $b['length_m']], $summary['by_voltage']));
    }

    public function test_a_sure_sentence_gives_the_date_the_voltage_and_the_system(): void
    {
        $fact = LineElectrificationHistory::read($this->event(
            "La section entre Paris-Montparnasse et Le Mans a été électrifiée en 1500 volts continu, en 1937, sous l'impulsion de Raoul Dautry",
            '1937-01-01', 'year', ['section_from' => 'Paris-Montparnasse', 'section_to' => 'Le Mans', 'section_resolution' => 'places'],
        ));

        $this->assertSame('electrification', $fact['kind']);
        $this->assertSame(1500, $fact['voltage_v']);
        $this->assertSame('dc', $fact['system']);
        $this->assertSame('1937-01-01', $fact['date']);
        $this->assertSame('medium', $fact['reliability']);
        $this->assertSame('section', $fact['scope']);
        $this->assertSame('Le Mans', $fact['section']['to']);
    }

    public function test_a_full_date_is_highly_reliable(): void
    {
        $fact = LineElectrificationHistory::read($this->event('Le 28 février 1967, électrification en 25 kV-50 Hz du triage de Sotteville à Motteville', '1967-02-28', 'day'));

        $this->assertSame('high', $fact['reliability']);
        $this->assertSame(25000, $fact['voltage_v']);
        $this->assertSame('ac', $fact['system']);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function ambiguousCases(): array
    {
        return [
            'travaux commencés' => ['Les travaux de ré-électrification sont engagés en 1974 et imposent de nombreuses modifications de la ligne', '1974-01-01', 'year'],
            'décision, pas une mise sous tension' => ["En 1934, les autorités militaires françaises autorisèrent l'électrification du tronçon de ligne central", '1934-01-01', 'year'],
            'projet inscrit au contrat' => ["L'électrification de la section entre Persan et Beauvais est inscrite au contrat de plan État-Région 1995-2000", '1995-01-01', 'year'],
            'fourchette de dates' => ['La ligne est entièrement électrifiée par caténaire en courant continu 1500 volts entre 1926 et 1938.', '1926-01-01', 'year'],
            'fourchette avec tiret' => ['Elle est entièrement électrifiée depuis 1987-1989 avec la mise en place du TGV', '1987-01-01', 'year'],
            'fait futur' => ['La ligne sera électrifiée en 2030 en 25 kV', '2030-01-01', 'year'],
            'état, pas un fait daté' => ["jusqu'à Irun en 1929, formant à partir de 1938 le plus long axe électrifié de France", '1938-01-01', 'year'],
            'travaux mis en œuvre' => ["En janvier 1957, l'électrification du tronçon de Beuvrages à Somain est mise en œuvre", '1957-01-01', 'month'],
            'année absente de la phrase' => ['La section de Paris à Mitry et Crépy-en-Valois est électrifiée', '1963-01-01', 'year'],
            'avant la première traction électrique' => ['La ligne a été électrifiée en 1885 par un système expérimental', '1885-01-01', 'year'],
            '50 Hz avant 1951' => ['La ligne a été électrifiée en 25 kV en 1938', '1938-01-01', 'year'],
            'phrase sans mise sous tension' => ['Des relèvements de vitesse à 200 km/h ont pris fin en 1996 de Bueil à Cherbourg', '1996-01-01', 'year'],
        ];
    }

    #[DataProvider('ambiguousCases')]
    public function test_an_ambiguous_sentence_gives_no_fact(string $quote, string $date, string $precision): void
    {
        $this->assertNull(LineElectrificationHistory::read($this->event($quote, $date, $precision)));
    }

    public function test_a_future_date_gives_no_fact(): void
    {
        $this->assertNull(LineElectrificationHistory::read($this->event('La ligne est électrifiée en 2031', '2031-01-01'), '2026-11-23'));
    }

    public function test_a_year_inside_a_range_is_kept_when_it_also_stands_alone(): void
    {
        $quote = 'Entre 1926 et 1929, la ligne est électrifiée en courant continu 1 500 volts : entre Dax et Hendaye en 1926, entre Morcenx et Dax en février 1927';

        $this->assertNotNull(LineElectrificationHistory::read($this->event($quote, '1926-01-01')));
        $feb = LineElectrificationHistory::read($this->event($quote, '1927-02-01', 'month'));
        $this->assertSame('high', $feb['reliability']);
        $this->assertSame(1500, $feb['voltage_v']);
    }

    public function test_a_deelectrification_is_a_fact_without_voltage(): void
    {
        $fact = LineElectrificationHistory::read($this->event('La section Oloron-Sainte-Marie - Canfranc a été dés-électrifiée en 1992,', '1992-01-01'));

        $this->assertSame('deelectrification', $fact['kind']);
        $this->assertNull($fact['voltage_v']);
        $this->assertNull($fact['system']);
    }

    public function test_a_re_electrification_is_a_conversion_even_without_voltage(): void
    {
        $fact = LineElectrificationHistory::read($this->event('la section de Paris à Juvisy ré-électrifiée par caténaire en 1927.', '1927-01-01'));

        $this->assertSame('conversion', $fact['kind']);
    }

    public function test_the_third_rail_is_named_when_the_sentence_says_so(): void
    {
        $fact = LineElectrificationHistory::read($this->event('Elle est électrifiée par troisième rail en 1904, en 750 V continu', '1904-01-01'));

        $this->assertSame('third_rail', $fact['system']);
        $this->assertSame(750, $fact['voltage_v']);
    }

    public function test_an_infobox_year_is_kept_without_naming_the_section(): void
    {
        $fact = LineElectrificationHistory::read($this->event('Infobox : électrification = 1954 ; électrification2 = 1958', '1958-01-01'));

        $this->assertSame('unspecified', $fact['scope']);
        $this->assertNull($fact['section']);
    }

    public function test_a_wikidata_date_needs_no_sentence_and_takes_its_voltage_from_the_label(): void
    {
        $fact = LineElectrificationHistory::read($this->event('Wikidata Q1 · P930 = électrification ferroviaire 25 kV 50 Hz', '1958-01-01', 'year', [
            'source' => 'wikidata', 'description' => 'Électrification de la ligne en 25 kV 50 Hz',
        ]));

        $this->assertSame(25000, $fact['voltage_v']);
        $this->assertSame('medium', $fact['reliability']);
        $this->assertNull($fact['source']['excerpt']);
    }

    public function test_adjacent_ranges_of_the_same_voltage_are_merged(): void
    {
        $merged = LineElectrificationHistory::merge([
            ['from' => 0, 'to' => 1000, 'v' => 1500, 'system' => 'dc'],
            ['from' => 1020, 'to' => 3000, 'v' => 1500, 'system' => 'dc'],
            ['from' => 3000, 'to' => 4000, 'v' => 25000, 'system' => 'ac'],
            ['from' => 4000, 'to' => 5000, 'v' => 25000, 'system' => 'ac'],
        ]);

        $this->assertSame([['from' => 0, 'to' => 3000, 'v' => 1500, 'system' => 'dc'], ['from' => 3000, 'to' => 5000, 'v' => 25000, 'system' => 'ac']], $merged);
        $this->assertSame('third_rail', LineElectrificationHistory::system(true, 0.0));
        $this->assertSame('ac', LineElectrificationHistory::system(false, 50.0));
        $this->assertSame('dc', LineElectrificationHistory::system(false, 0.0));
        $this->assertSame('unknown', LineElectrificationHistory::system(false, null));
    }
}

<?php

namespace Tests\Unit;

use App\Services\StationClosureParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Fermetures de gares : outils statiques du lecteur (type de trafic, bornes de siècle, noms de
 * gares, texte libre OSM) et lecture des déclarations Wikidata (P582, P31, P5817, P793, P3999,
 * P576), avec leurs précisions et leurs garde-fous (rang déprécié, année hors bornes).
 */
class StationClosureParserHelpersTest extends TestCase
{
    // ── Type de trafic ──────────────────────────────────────────────────────

    /** @return array<string, array{0: string, 1: ?string}> */
    public static function trafficProvider(): array
    {
        return [
            'voyageurs' => ['au trafic des voyageurs', 'closure_passenger'],
            'omnibus' => ['aux trains omnibus', 'closure_passenger'],
            'marchandises' => ['aux marchandises', 'closure_freight'],
            'fret' => ['au fret', 'closure_freight'],
            'petite vitesse' => ['au service de la petite vitesse', 'closure_freight'],
            'les deux = tout trafic' => ['aux voyageurs et aux marchandises', 'closure'],
            'tout trafic' => ['à tout trafic', 'closure'],
            'toute circulation' => ['à toute circulation', 'closure'],
            'définitivement' => ['définitivement', 'closure'],
            'bâtiment voyageurs : pas un trafic' => ['le bâtiment voyageurs est fermé', null],
            'halle aux marchandises : pas un trafic' => ['la halle aux marchandises', null],
            'sans précision' => ['en 1972', null],
            'vide' => ['', null],
        ];
    }

    #[DataProvider('trafficProvider')]
    public function test_traffic_type(string $text, ?string $expected): void
    {
        $this->assertSame($expected, StationClosureParser::trafficType($text));
    }

    // ── Bornes de siècle ────────────────────────────────────────────────────

    /** @return array<string, array{0: string, 1: ?int}> */
    public static function centuryProvider(): array
    {
        return [
            'XXe siècle' => ['fermée au XXe siècle', 1900],
            'deuxième moitié' => ['fermée dans la deuxième moitié du XXe siècle', 1950],
            'seconde moitié' => ['fermée dans la seconde moitié du XIXe siècle', 1850],
            'fin du siècle' => ['fermée à la fin du XXe siècle', 1970],
            'milieu du siècle' => ['fermée au milieu du XXe siècle', 1940],
            'XIXe : 1827 est le plancher, pas 1800' => ['fermée au XIXe siècle', null],
            'XXIe siècle : 2000 est dépassé ou non selon la date du jour' => ['fermée au XXIe siècle', 2000],
            'pas de siècle' => ['fermée en 1972', null],
            'siècle hors bornes' => ['fermée au XVIIIe siècle', null],
        ];
    }

    #[DataProvider('centuryProvider')]
    public function test_century_lower_bound(string $text, ?int $expected): void
    {
        $this->assertSame($expected, StationClosureParser::centuryLowerBound($text));
    }

    public function test_the_late_nineteenth_century_is_a_valid_lower_bound(): void
    {
        $this->assertSame(1870, StationClosureParser::centuryLowerBound('fermée à la fin du XIXe siècle'));
    }

    public function test_century_templates_become_readable_text(): void
    {
        $this->assertSame('au XXe siècle', StationClosureParser::expandCenturies('au {{XXe siècle}}'));
        $this->assertSame('la 2e moitié', StationClosureParser::expandCenturies('la {{2e|moitié}}'));
        $this->assertSame('au XIXe siècle', StationClosureParser::expandCenturies('au {{s|XIX}}'));
        $this->assertSame('rien à changer', StationClosureParser::expandCenturies('rien à changer'));
    }

    // ── Noms de gares ───────────────────────────────────────────────────────

    public function test_split_names_cuts_lists_and_strips_prepositions(): void
    {
        $this->assertSame(
            ['Tourgéville', 'Gonneville', 'Saint-Vaast'],
            StationClosureParser::splitNames('de Tourgéville, de Gonneville et de Saint-Vaast'),
        );
    }

    public function test_split_names_drops_what_is_not_a_name(): void
    {
        $this->assertSame([], StationClosureParser::splitNames('de la ligne'));
        $this->assertSame([], StationClosureParser::splitNames('ab'));
        $this->assertSame(['Dijon'], StationClosureParser::splitNames('Dijon, Dijon'));
    }

    // ── Texte libre ─────────────────────────────────────────────────────────

    public function test_free_text_reads_a_closure_year(): void
    {
        $events = StationClosureParser::fromFreeText('Gare fermée en 1972');

        $this->assertCount(1, $events);
        $this->assertSame('closure', $events[0]['type']);
        $this->assertSame('1972-01-01', $events[0]['date']);
        $this->assertSame('year', $events[0]['precision']);
    }

    public function test_free_text_about_passengers_is_a_passenger_closure(): void
    {
        $this->assertSame('closure_passenger', StationClosureParser::fromFreeText('Fermée aux voyageurs en 1938')[0]['type']);
    }

    public function test_free_text_ignores_years_outside_the_railway_era(): void
    {
        $this->assertSame([], StationClosureParser::fromFreeText('Gare fermée en 1750'));
        $this->assertSame([], StationClosureParser::fromFreeText('Gare fermée en '.((int) date('Y') + 5)));
        $this->assertSame([], StationClosureParser::fromFreeText('   '));
    }

    public function test_free_text_quote_is_truncated(): void
    {
        $events = StationClosureParser::fromFreeText('Gare fermée en 1972. '.str_repeat('Texte long. ', 40));

        $this->assertLessThanOrEqual(200, mb_strlen($events[0]['quote']));
    }

    // ── Wikidata ────────────────────────────────────────────────────────────

    /** Valeur « temps » Wikidata. */
    private static function time(string $date, int $precision): array
    {
        return ['time' => '+'.$date.'T00:00:00Z', 'precision' => $precision];
    }

    private static function claim(array $value, array $qualifiers = [], string $rank = 'normal'): array
    {
        return ['rank' => $rank, 'mainsnak' => ['snaktype' => 'value', 'datavalue' => ['value' => $value]], 'qualifiers' => $qualifiers];
    }

    private static function qualifier(array $value): array
    {
        return [['datavalue' => ['value' => $value]]];
    }

    /** @return list<string> « type date précision propriété » */
    private function brief(array $events): array
    {
        return array_map(fn ($e) => "{$e['type']} {$e['date']} {$e['precision']} {$e['prop']}", $events);
    }

    public function test_wikidata_end_date_gives_a_closure_with_its_precision(): void
    {
        foreach ([[11, 'day', '1972-05-14'], [10, 'month', '1972-05-01'], [9, 'year', '1972-01-01']] as [$p, $label, $date]) {
            $events = StationClosureParser::fromWikidata(['claims' => ['P582' => [self::claim(self::time('1972-05-14', $p))]]]);

            $this->assertSame(["closure {$date} {$label} P582"], $this->brief($events));
        }
    }

    public function test_wikidata_decade_precision_is_rounded_to_the_decade(): void
    {
        $events = StationClosureParser::fromWikidata(['claims' => ['P582' => [self::claim(self::time('1973-00-00', 8))]]]);

        $this->assertSame(['closure 1970-01-01 decade P582'], $this->brief($events));
    }

    public function test_wikidata_dates_coarser_than_a_decade_are_ignored(): void
    {
        $this->assertSame([], StationClosureParser::fromWikidata(['claims' => ['P582' => [self::claim(self::time('1900-00-00', 7))]]]));
    }

    public function test_wikidata_unknown_month_and_day_default_to_the_first(): void
    {
        $events = StationClosureParser::fromWikidata(['claims' => ['P582' => [self::claim(self::time('1972-00-00', 11))]]]);

        $this->assertSame('1972-01-01', $events[0]['date']);
    }

    public function test_wikidata_dates_before_the_first_railway_or_in_the_future_are_ignored(): void
    {
        $future = (int) date('Y') + 3;
        $claims = ['P582' => [self::claim(self::time('1700-01-01', 9)), self::claim(self::time($future.'-01-01', 9))]];

        $this->assertSame([], StationClosureParser::fromWikidata(['claims' => $claims]));
    }

    public function test_wikidata_deprecated_statements_are_ignored(): void
    {
        $claims = ['P582' => [self::claim(self::time('1972-05-14', 11), [], 'deprecated')]];

        $this->assertSame([], StationClosureParser::fromWikidata(['claims' => $claims]));
    }

    public function test_wikidata_statements_without_a_value_are_ignored(): void
    {
        $claims = ['P582' => [['rank' => 'normal', 'mainsnak' => ['snaktype' => 'somevalue']]]];

        $this->assertSame([], StationClosureParser::fromWikidata(['claims' => $claims]));
    }

    public function test_wikidata_station_class_ended_gives_a_closure_only_without_an_open_class(): void
    {
        $ended = self::claim(['id' => 'Q55488'], ['P582' => self::qualifier(self::time('1980-03-02', 11))]);

        $this->assertSame(['closure 1980-03-02 day P31'], $this->brief(StationClosureParser::fromWikidata(['claims' => ['P31' => [$ended]]])));

        // Une autre déclaration « gare » sans fin : la gare existe toujours, aucune fermeture.
        $open = self::claim(['id' => 'Q55678']);
        $this->assertSame([], StationClosureParser::fromWikidata(['claims' => ['P31' => [$ended, $open]]]));
    }

    public function test_wikidata_closed_usage_state_gives_a_closure_and_demolition_gives_a_demolition(): void
    {
        $closed = self::claim(['id' => 'Q11639308'], ['P580' => self::qualifier(self::time('1991-07-01', 11))]);
        $razed = self::claim(['id' => 'Q56556915'], ['P580' => self::qualifier(self::time('1995-01-01', 9))]);

        $events = StationClosureParser::fromWikidata(['claims' => ['P5817' => [$closed, $razed]]]);

        $this->assertSame(['closure 1991-07-01 day P5817', 'demolition 1995-01-01 year P5817'], $this->brief($events));
    }

    public function test_wikidata_usage_state_of_another_part_is_ignored(): void
    {
        // Gare routière (Q55488 absent des parties) : l'état fermé ne concerne pas la gare ferroviaire.
        $closed = self::claim(['id' => 'Q11639308'], [
            'P580' => self::qualifier(self::time('1991-07-01', 11)),
            'P518' => self::qualifier(['id' => 'Q494829']),
        ]);

        $this->assertSame([], StationClosureParser::fromWikidata(['claims' => ['P5817' => [$closed]]]));
    }

    public function test_wikidata_in_use_state_with_an_end_date_gives_a_closure(): void
    {
        $inUse = self::claim(['id' => 'Q55654238'], ['P582' => self::qualifier(self::time('2004-12-12', 11))]);

        $this->assertSame(['closure 2004-12-12 day P5817'], $this->brief(StationClosureParser::fromWikidata(['claims' => ['P5817' => [$inUse]]])));
    }

    public function test_wikidata_key_event_demolition_and_closure(): void
    {
        $events = StationClosureParser::fromWikidata(['claims' => ['P793' => [
            self::claim(['id' => 'Q331483'], ['P585' => self::qualifier(self::time('1960-01-01', 9))]),
            self::claim(['id' => 'Q5135520'], ['P585' => self::qualifier(self::time('1950-06-01', 10))]),
            self::claim(['id' => 'Q123'], ['P585' => self::qualifier(self::time('1940-06-01', 10))]),
        ]]]);

        $this->assertSame(['demolition 1960-01-01 year P793', 'closure 1950-06-01 month P793'], $this->brief($events));
    }

    public function test_wikidata_official_closure_date_is_kept_only_at_decade_precision_unless_asked(): void
    {
        $claims = ['P3999' => [self::claim(self::time('1969-09-27', 11))]];

        $this->assertSame([], StationClosureParser::fromWikidata(['claims' => $claims]));
        $this->assertSame(['closure 1969-09-27 day P3999'], $this->brief(StationClosureParser::fromWikidata(['claims' => $claims], withMain: true)));
    }

    public function test_wikidata_official_closure_for_passengers_is_a_passenger_closure(): void
    {
        $claims = ['P3999' => [self::claim(self::time('1969-09-27', 11), ['P518' => self::qualifier(['id' => 'Q1433891'])])]];

        $this->assertSame(
            ['closure_passenger 1969-09-27 day P3999'],
            $this->brief(StationClosureParser::fromWikidata(['claims' => $claims], withMain: true)),
        );
    }

    public function test_wikidata_the_same_event_declared_twice_is_kept_once(): void
    {
        $claims = [
            'P582' => [self::claim(self::time('1972-05-14', 11))],
            'P5817' => [self::claim(['id' => 'Q55654238'], ['P582' => self::qualifier(self::time('1972-05-14', 11))])],
        ];

        $this->assertCount(1, StationClosureParser::fromWikidata(['claims' => $claims]));
    }

    public function test_wikidata_entity_without_claims_gives_nothing(): void
    {
        $this->assertSame([], StationClosureParser::fromWikidata([]));
    }
}

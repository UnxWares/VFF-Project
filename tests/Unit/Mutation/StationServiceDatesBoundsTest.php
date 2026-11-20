<?php

namespace Tests\Unit\Mutation;

use App\Services\StationServiceDates;
use DateTimeImmutable;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Règles de StationServiceDates::bounds() sur des lignes de requête construites à la main, sans base :
 * quand la date d'un événement de ligne vaut pour la desserte d'une gare, et quand seules les années
 * restent. Complète tests/Feature/StationServiceDatesTest.php (cas réel de Saint-Calais), écrit pour
 * les tests de mutation (tests/Mutation/Critical).
 */
class StationServiceDatesBoundsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-20 10:00:00');
    }

    protected function tearDown(): void
    {
        try {
            parent::tearDown();
        } finally {
            Carbon::setTestNow();
        }
    }

    /** Ligne de requête : gare desservie de 1879 à 1938 par une ligne datée au jour, sans date propre. */
    private function row(array $overrides = []): object
    {
        return (object) array_replace([
            'first_year' => 1879,
            'last_year' => 1938,
            'years_source' => 'events',
            'first_event_date' => '1879-03-31',
            'first_event_precision' => 'day',
            'first_event_id' => '12',
            'last_event_date' => '1938-05-15',
            'last_event_precision' => 'day',
            'last_event_id' => '34',
            'station_opened_at' => null,
            'station_opened_precision' => null,
            'station_opened_source' => null,
            'station_closed_at' => null,
            'station_closed_precision' => null,
            'station_closed_source' => null,
        ], $overrides);
    }

    public function test_dated_events_give_both_bounds_with_their_event(): void
    {
        $this->assertSame([
            'start' => ['date' => '1879-03-31', 'precision' => 'day', 'event_id' => 12],
            'end' => ['date' => '1938-05-15', 'precision' => 'day', 'event_id' => 34],
        ], StationServiceDates::bounds($this->row(), 2026));
    }

    public function test_a_line_that_never_served_the_station_gives_nothing(): void
    {
        $this->assertSame(['start' => null, 'end' => null],
            StationServiceDates::bounds($this->row(['years_source' => 'never_served']), 2026));
    }

    public function test_an_event_of_another_year_does_not_date_the_period(): void
    {
        $bounds = StationServiceDates::bounds($this->row(['first_year' => 1880, 'last_year' => 1937]), 2026);

        $this->assertSame(['start' => null, 'end' => null], $bounds);
    }

    public function test_without_years_nothing_is_dated(): void
    {
        $bounds = StationServiceDates::bounds($this->row(['first_year' => null, 'last_year' => null]), 2026);

        $this->assertSame(['start' => null, 'end' => null], $bounds);
    }

    public function test_years_given_as_strings_are_compared_as_numbers(): void
    {
        $bounds = StationServiceDates::bounds($this->row(['first_year' => '1879', 'last_year' => '1938']), 2026);

        $this->assertSame('1879-03-31', $bounds['start']['date'] ?? null);
        $this->assertSame('1938-05-15', $bounds['end']['date'] ?? null);
    }

    /** @return iterable<string, array{?string, bool}> */
    public static function precisions(): iterable
    {
        yield 'jour' => ['day', true];
        yield 'mois' => ['month', true];
        yield 'année' => ['year', true];
        yield 'intervalle' => ['range', false];
        yield 'décennie' => ['decade', false];
        yield 'inconnue' => [null, false];
    }

    #[DataProvider('precisions')]
    public function test_only_day_month_or_year_precisions_are_kept(?string $precision, bool $kept): void
    {
        $bounds = StationServiceDates::bounds($this->row(['first_event_precision' => $precision, 'last_event_precision' => $precision]), 2026);

        $this->assertSame($kept, $bounds['start'] !== null);
        $this->assertSame($kept, $bounds['end'] !== null);
        if ($kept) {
            $this->assertSame($precision, $bounds['start']['precision']);
        }
    }

    public function test_a_missing_event_id_stays_null(): void
    {
        $bounds = StationServiceDates::bounds($this->row(['first_event_id' => null, 'last_event_id' => null]), 2026);

        $this->assertSame(['date' => '1879-03-31', 'precision' => 'day', 'event_id' => null], $bounds['start']);
        $this->assertSame(['date' => '1938-05-15', 'precision' => 'day', 'event_id' => null], $bounds['end']);
    }

    public function test_dates_are_read_from_timestamps_and_date_objects(): void
    {
        $bounds = StationServiceDates::bounds($this->row([
            'first_event_date' => '1879-03-31 00:00:00',
            'last_event_date' => new DateTimeImmutable('1938-05-15 12:30:00'),
        ]), 2026);

        $this->assertSame('1879-03-31', $bounds['start']['date'] ?? null);
        $this->assertSame('1938-05-15', $bounds['end']['date'] ?? null);
    }

    public function test_an_empty_date_is_no_date(): void
    {
        $bounds = StationServiceDates::bounds($this->row(['first_event_date' => '', 'last_event_date' => '']), 2026);

        $this->assertSame(['start' => null, 'end' => null], $bounds);
    }

    // ─── Ouverture de la gare la même année ─────────────────────────────────

    public function test_a_station_opened_later_the_same_year_hides_the_line_opening_day(): void
    {
        $bounds = StationServiceDates::bounds($this->row([
            'station_opened_at' => '1879-06-01', 'station_opened_precision' => 'day', 'station_opened_source' => 'wikidata',
        ]), 2026);

        $this->assertNull($bounds['start']);
        $this->assertNotNull($bounds['end']);
    }

    public function test_a_station_opened_the_same_day_keeps_the_line_opening_day(): void
    {
        $bounds = StationServiceDates::bounds($this->row([
            'station_opened_at' => '1879-03-31', 'station_opened_precision' => 'day', 'station_opened_source' => 'wikidata',
        ]), 2026);

        $this->assertSame('1879-03-31', $bounds['start']['date'] ?? null);
    }

    public function test_a_station_opened_another_year_does_not_contradict_the_line(): void
    {
        $bounds = StationServiceDates::bounds($this->row([
            'station_opened_at' => '1878-06-01', 'station_opened_precision' => 'day', 'station_opened_source' => 'wikidata',
        ]), 2026);

        $this->assertSame('1879-03-31', $bounds['start']['date'] ?? null);
    }

    public function test_a_station_date_inferred_from_its_lines_contradicts_nothing(): void
    {
        $bounds = StationServiceDates::bounds($this->row([
            'station_opened_at' => '1879-06-01', 'station_opened_precision' => 'day', 'station_opened_source' => 'inferred:lines',
            'station_closed_at' => '1938-01-01', 'station_closed_precision' => 'day', 'station_closed_source' => 'inferred:lines',
        ]), 2026);

        $this->assertSame('1879-03-31', $bounds['start']['date'] ?? null);
        $this->assertSame('1938-05-15', $bounds['end']['date'] ?? null);
    }

    public function test_a_station_date_without_source_still_counts(): void
    {
        $bounds = StationServiceDates::bounds($this->row([
            'station_opened_at' => '1879-06-01', 'station_opened_precision' => 'day',
        ]), 2026);

        $this->assertNull($bounds['start']);
    }

    // ─── Fermeture de la gare la même année ─────────────────────────────────

    public function test_a_station_closed_earlier_the_same_year_hides_the_line_closure_day(): void
    {
        $bounds = StationServiceDates::bounds($this->row([
            'station_closed_at' => '1938-02-01', 'station_closed_precision' => 'day', 'station_closed_source' => 'wikidata',
        ]), 2026);

        $this->assertNotNull($bounds['start']);
        $this->assertNull($bounds['end']);
    }

    public function test_a_station_closed_after_the_line_keeps_the_line_closure_day(): void
    {
        $bounds = StationServiceDates::bounds($this->row([
            'station_closed_at' => '1938-05-15', 'station_closed_precision' => 'day', 'station_closed_source' => 'wikidata',
        ]), 2026);

        $this->assertSame(['date' => '1938-05-15', 'precision' => 'day', 'event_id' => 34], $bounds['end']);
    }

    public function test_a_closure_dated_today_is_already_a_closure(): void
    {
        $bounds = StationServiceDates::bounds($this->row([
            'last_year' => 2026, 'last_event_date' => '2026-12-13', 'last_event_precision' => 'day',
            'station_closed_at' => '2026-11-20', 'station_closed_precision' => 'day', 'station_closed_source' => 'sncf',
        ]), 2026);

        $this->assertNull($bounds['end']);
    }

    public function test_a_planned_station_closure_does_not_contradict_the_line(): void
    {
        $bounds = StationServiceDates::bounds($this->row([
            'last_year' => 2027, 'last_event_date' => '2027-06-01', 'last_event_precision' => 'day',
            'station_closed_at' => '2027-03-01', 'station_closed_precision' => 'day', 'station_closed_source' => 'sncf',
        ]), 2026);

        $this->assertSame('2027-06-01', $bounds['end']['date'] ?? null);
    }

    public function test_a_station_closure_of_another_year_does_not_contradict_the_line(): void
    {
        $bounds = StationServiceDates::bounds($this->row([
            'station_closed_at' => '1939-02-01', 'station_closed_precision' => 'day', 'station_closed_source' => 'wikidata',
        ]), 2026);

        $this->assertSame('1938-05-15', $bounds['end']['date'] ?? null);
    }

    public function test_an_upper_bound_is_never_written_as_a_closure_day(): void
    {
        $bounds = StationServiceDates::bounds($this->row([
            'station_closed_at' => '1938-12-31', 'station_closed_precision' => 'range', 'station_closed_source' => 'wikidata',
        ]), 2026);

        $this->assertNull($bounds['end']);
        $this->assertNotNull($bounds['start']);
    }

    public function test_the_current_year_defaults_to_today(): void
    {
        // Borne haute de l'année en cours : fin non datée, jamais écrite au jour.
        $row = $this->row([
            'last_year' => 2026, 'last_event_date' => '2026-03-01', 'last_event_precision' => 'day',
            'station_closed_at' => '2026-12-31', 'station_closed_precision' => 'range', 'station_closed_source' => 'wikidata',
        ]);

        $this->assertNull(StationServiceDates::bounds($row)['end']);
    }

    // ─── Comparaison de dates à précision variable ──────────────────────────

    /** @return iterable<string, array{string, ?string, string, ?string, bool}> */
    public static function comparisons(): iterable
    {
        yield 'jour avant jour' => ['1879-03-30', 'day', '1879-03-31', 'day', true];
        yield 'précision absente = jour' => ['1879-03-31', null, '1879-03-31', null, true];
        yield 'précision absente, jour après' => ['1879-04-01', null, '1879-03-31', null, false];
        yield 'jour puis mois, même mois' => ['1879-03-01', 'day', '1879-03-31', 'month', false];
        yield 'jour puis mois suivant' => ['1879-03-31', 'day', '1879-04-01', 'month', true];
        yield 'mois puis mois suivant' => ['1879-03-01', 'month', '1879-04-01', 'month', true];
        yield 'mois après' => ['1879-05-01', 'month', '1879-04-30', 'day', false];
        yield 'année à droite' => ['1879-01-01', 'day', '1879-12-31', 'year', false];
    }

    #[DataProvider('comparisons')]
    public function test_not_after_compares_at_a_shared_precision(string $a, ?string $ap, string $b, ?string $bp, bool $expected): void
    {
        $this->assertSame($expected, StationServiceDates::notAfter($a, $ap, $b, $bp));
    }

    public function test_written_bounds_keep_their_precision(): void
    {
        $this->assertNull(StationServiceDates::written(null, 'fr'));
        $this->assertSame(
            ['date' => '1879-03-01', 'precision' => 'month', 'when' => 'mars 1879', 'event_id' => 7],
            StationServiceDates::written(['date' => '1879-03-01', 'precision' => 'month', 'event_id' => 7], 'fr'),
        );
        $this->assertSame('1879', StationServiceDates::written(['date' => '1879-03-01', 'precision' => 'year', 'event_id' => null], 'en')['when'] ?? null);
    }
}

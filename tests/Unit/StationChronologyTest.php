<?php

namespace Tests\Unit;

use App\Services\StationChronology;
use PHPUnit\Framework\TestCase;

/**
 * Chronologie de la fiche gare (StationChronology::build) : regroupement des dates d'un même événement,
 * date retenue par la fiche, dessertes des lignes, noms datés, accidents, ordre.
 */
class StationChronologyTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function event(string $type, string $date, string $precision, string $source, bool $inferred = false, ?string $note = null): array
    {
        return ['type' => $type, 'date' => $date, 'precision' => $precision, 'source' => $source,
            'source_url' => null, 'inferred' => $inferred, 'note' => $note, 'note_lang' => null];
    }

    /** @return array<string, mixed> */
    private static function station(array $extra = []): array
    {
        return $extra + ['name' => 'Le Point-du-Jour', 'alt_names' => [], 'opened_at' => null, 'opened_precision' => null,
            'opened_after' => null, 'closed_at' => null, 'closed_precision' => null, 'closed_after' => null, 'provenance' => []];
    }

    /** @return array<string, mixed> */
    private static function line(string $slug, ?int $first, ?int $last, array $extra = []): array
    {
        return $extra + ['slug' => $slug, 'code' => null, 'name' => "Ligne {$slug}", 'first_year' => $first, 'last_year' => $last,
            'last_year_kind' => $last !== null ? 'year' : null, 'years_source' => 'line_events', 'never_served' => null,
            'service_start' => null, 'service_end' => null];
    }

    public function test_dates_of_the_same_event_that_overlap_make_one_entry_with_the_most_precise_first(): void
    {
        $entries = StationChronology::build(self::station(['closed_at' => '1934-07-23', 'closed_precision' => 'day']), [
            self::event('closure', '1934-01-01', 'year', 'wikidata'),
            self::event('closure', '1934-01-01', 'year', 'wikipedia', note: '« fermée en 1934 »'),
            self::event('closure', '1934-07-23', 'day', 'wikipedia'),
            self::event('closure', '1934-07-23', 'day', 'ohm'),
            self::event('closure_passenger', '1934-07-23', 'day', 'wikipedia'),
        ], []);

        $this->assertCount(2, $entries);
        $this->assertSame(['closure_passenger', 'closure'], array_column($entries, 'type'));
        $closure = $entries[1];
        $this->assertSame(['1934-07-23', 'day', 'wikipedia', true], [$closure['date'], $closure['precision'], $closure['source'], $closure['retained']]);
        // Autres sources, une fois chacune, avec leur propre date et leur note.
        $this->assertSame(['ohm', 'wikidata'], array_column($closure['also'], 'source'));
        $this->assertSame('year', $closure['also'][1]['precision']);
        $this->assertNull($closure['note']);
    }

    public function test_a_date_that_contradicts_the_retained_one_stays_visible_but_not_retained(): void
    {
        $entries = StationChronology::build(self::station(['opened_at' => '1895-12-01', 'opened_precision' => 'day']), [
            self::event('opening', '1890-08-08', 'day', 'ohm'),
            self::event('opening', '1895-12-01', 'day', 'correction'),
        ], []);

        $this->assertSame([['1890-08-08', false], ['1895-12-01', true]],
            array_map(fn ($e) => [$e['date'], $e['retained']], $entries));
    }

    public function test_a_retained_date_without_any_event_is_added_with_the_source_of_the_sheet(): void
    {
        $entries = StationChronology::build(self::station([
            'opened_at' => '1860-12-31', 'opened_precision' => 'range', 'opened_after' => 1846,
            'provenance' => ['opened_at' => 'inferred:line_event'],
        ]), [], []);

        $this->assertCount(1, $entries);
        $this->assertSame(['event', 'opening', 'range', 1846, 'inferred:line_event', true],
            [$entries[0]['kind'], $entries[0]['type'], $entries[0]['precision'], $entries[0]['after'], $entries[0]['source'], $entries[0]['estimated']]);
    }

    public function test_an_inferred_opening_inside_a_bracketed_opening_reads_as_the_bracket(): void
    {
        $entries = StationChronology::build(self::station(['opened_at' => '1860-12-31', 'opened_precision' => 'range', 'opened_after' => 1846]), [
            self::event('opening', '1847-01-01', 'year', 'inferred:line_event', true),
        ], []);

        $this->assertCount(1, $entries);
        $this->assertSame(['1860-12-31', 'range', 1846], [$entries[0]['date'], $entries[0]['precision'], $entries[0]['after']]);
    }

    public function test_lines_join_the_opening_and_closure_of_the_same_date_or_make_their_own_entries(): void
    {
        $entries = StationChronology::build(self::station(['opened_at' => '1867-02-25', 'opened_precision' => 'day']), [
            self::event('opening', '1867-02-25', 'day', 'ohm'),
            self::event('closure', '1934-07-23', 'day', 'wikipedia'),
        ], [
            self::line('a', 1867, 1934),
            self::line('b', 1869, 1934, ['service_start' => ['date' => '1869-03-25', 'precision' => 'day', 'event_id' => 42]]),
            // Années tirées des dates de la gare : rien de plus que l'ouverture.
            self::line('c', 1867, null, ['years_source' => 'station']),
            self::line('d', 1990, 2000, ['never_served' => 'line_closed']),
            // Fin non datée : pas de fin de desserte.
            self::line('e', null, 2026, ['last_year_kind' => 'undated']),
        ]);

        $this->assertSame(['opening', 'line_start', 'closure'], array_map(fn ($e) => $e['type'] ?? $e['kind'], $entries));
        $this->assertSame([['slug' => 'a', 'code' => null, 'name' => 'Ligne a', 'role' => 'start']], $entries[0]['lines']);
        $this->assertSame(['1869-03-25', 'day', 42, 'line_events'], [$entries[1]['date'], $entries[1]['precision'], $entries[1]['event_id'], $entries[1]['source']]);
        $this->assertSame(['a', 'b'], array_column($entries[2]['lines'], 'slug'));
        $this->assertSame(['end', 'end'], array_column($entries[2]['lines'], 'role'));
    }

    public function test_an_upper_bound_end_of_service_reads_as_at_the_latest(): void
    {
        $entries = StationChronology::build(self::station(), [], [self::line('a', null, 1989, ['last_year_kind' => 'upper_bound'])]);

        $this->assertSame(['line_end', '1989-12-31', 'range', null], [$entries[0]['kind'], $entries[0]['date'], $entries[0]['precision'], $entries[0]['after']]);
    }

    public function test_dated_names_come_after_the_opening_of_the_same_year_and_spelling_variants_are_left_out(): void
    {
        $entries = StationChronology::build(self::station([
            'name' => 'Vernosc-lès-Annonay', 'opened_at' => '1869-10-29', 'opened_precision' => 'day',
            'alt_names' => [
                ['name' => 'Vénosc-lès-Annonay', 'from' => '1869', 'to' => '1987', 'source' => 'ohm'],
                ['name' => 'Gare de Vernosc-lès-Annonay', 'from' => '1900', 'source' => 'ohm'],
                ['name' => 'Sans date', 'source' => 'wikidata'],
                'Ancien nom',
            ],
        ]), [self::event('opening', '1869-10-29', 'day', 'ohm')], []);

        $this->assertSame(['opening', 'name'], array_map(fn ($e) => $e['type'] ?? $e['kind'], $entries));
        $this->assertSame(['name' => 'Vénosc-lès-Annonay', 'from' => 1869, 'to' => 1987], $entries[1]['name']);
        $this->assertSame(['1869-01-01', 'year', 'ohm'], [$entries[1]['date'], $entries[1]['precision'], $entries[1]['source']]);
    }

    public function test_a_name_known_only_by_its_end_or_by_a_decade_is_placed_with_its_own_precision(): void
    {
        $entries = StationChronology::build(self::station([
            'name' => 'Masséna',
            'alt_names' => [
                ['name' => 'Orléans-Ceinture', 'from' => null, 'to' => 1970, 'to_date' => '1970', 'to_precision' => 'decade', 'source' => 'wikipedia_history', 'source_url' => 'https://fr.wikipedia.org/w/index.php?oldid=1'],
                ['name' => 'Gunderchingen', 'from' => 1940, 'to' => 1944, 'from_date' => '1940-07-01', 'from_precision' => 'day', 'source' => 'wikipedia_history'],
            ],
        ]), [], []);

        $this->assertSame(['1940-07-01', 'day', null], [$entries[0]['date'], $entries[0]['precision'], $entries[0]['source_url']]);
        $this->assertSame(['1970-01-01', 'decade', 'https://fr.wikipedia.org/w/index.php?oldid=1'], [$entries[1]['date'], $entries[1]['precision'], $entries[1]['source_url']]);
        $this->assertSame(['name' => 'Orléans-Ceinture', 'from' => null, 'to' => 1970], $entries[1]['name']);
    }

    public function test_accidents_take_their_place_with_a_link_to_their_record(): void
    {
        $accident = ['slug' => '1925-04-09-paris', 'name' => 'Collision à Paris', 'date' => '1925-04-09', 'date_precision' => 'day',
            'kind' => 'collision', 'deaths' => 2, 'injuries' => null, 'distance_m' => 0, 'location_precision' => 'station', 'to_review' => true];
        $entries = StationChronology::build(self::station(), [
            self::event('opening', '1846-06-20', 'day', 'wikidata'),
            self::event('closure', '1990-01-01', 'year', 'wikidata'),
        ], [], [$accident]);

        $this->assertSame(['opening', 'accident', 'closure'], array_map(fn ($e) => $e['type'] ?? $e['kind'], $entries));
        $this->assertSame($accident, $entries[1]['accident']);
        $this->assertSame(['vff_accidents', '/accidents/1925-04-09-paris'], [$entries[1]['source'], $entries[1]['source_url']]);
        // Chaque entrée a toutes les clés, même vides.
        $this->assertSame(array_keys($entries[0]), array_keys($entries[1]));
    }

    public function test_within_one_year_a_date_known_to_the_year_follows_the_life_of_a_station(): void
    {
        $entries = StationChronology::build(self::station(), [
            self::event('demolition', '1949-01-01', 'year', 'wikidata'),
            self::event('closure', '1949-12-31', 'day', 'wikipedia'),
            self::event('opening', '1901-04-06', 'day', 'correction'),
            self::event('closure_passenger', '1938-05-15', 'day', 'wikipedia'),
            self::event('closure_freight', '1938-01-01', 'year', 'wikidata'),
        ], []);

        $this->assertSame(['opening', 'closure_passenger', 'closure_freight', 'closure', 'demolition'], array_column($entries, 'type'));
    }

    public function test_span_covers_the_date_at_its_precision(): void
    {
        $this->assertSame(['1934-07-23', '1934-07-23'], StationChronology::span('1934-07-23', 'day'));
        $this->assertSame(['1934-07-01', '1934-07-31'], StationChronology::span('1934-07-01', 'month'));
        $this->assertSame(['1934-01-01', '1934-12-31'], StationChronology::span('1934-01-01', 'year'));
        $this->assertSame(['1950-01-01', '1959-12-31'], StationChronology::span('1954-01-01', 'decade'));
        $this->assertNull(StationChronology::span('1934-01-01', 'after'));
    }
}

<?php

namespace Tests\Unit;

use App\Enums\StationStatus;
use App\Services\StationLineCoverage as C;
use App\Services\StationTimeline as T;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Gares par ère : chronologie propre (StationTimeline::statusFromEvents),
 * plafond par les lignes (capByLines, règle R2 « une gare ne peut pas être
 * plus active que ses lignes ») et gares isolées ou non plafonnées
 * (StationLineCoverage::isolationVerdict / exemption).
 */
class StationCoverageRulesTest extends TestCase
{
    // ─── statusFromEvents ────────────────────────────────────────────────

    public function test_station_without_any_event_does_not_exist(): void
    {
        $this->assertNull(T::statusFromEvents([], 1900, true));
    }

    public function test_events_of_the_era_year_apply_to_that_era(): void
    {
        // État au 31/12 : ouverte le 31 décembre 1880 → visible en 1880.
        $events = [['type' => 'opening', 'date' => '1880-12-31']];

        $this->assertNull(T::statusFromEvents($events, 1879, false));
        $this->assertSame(T::ACTIVE, T::statusFromEvents($events, 1880, false));
    }

    public function test_events_are_replayed_in_date_order_whatever_the_input_order(): void
    {
        $events = [['type' => 'closure', 'date' => '1938-10-02'], ['type' => 'opening', 'date' => '1881-06-01']];

        $this->assertSame(T::ACTIVE, T::statusFromEvents($events, 1900, false));
        // Fermée, même disparue (règle du 22/11/2026 : la carte ne la garde qu'à la première ère fermée, BuildStationEras::keepClosedOnlyAtClosingEra).
        $this->assertSame(T::CLOSED, T::statusFromEvents($events, 1940, false));
    }

    public function test_station_still_standing_today_is_closed_rather_than_gone(): void
    {
        $events = [['type' => 'opening', 'date' => '1881-06-01'], ['type' => 'closure', 'date' => '1938-10-02']];

        $this->assertSame(T::CLOSED, T::statusFromEvents($events, 1950, true));
    }

    public function test_demolition_ends_the_station_even_if_the_site_is_listed_today(): void
    {
        $events = [['type' => 'opening', 'date' => '1880-01-01'], ['type' => 'demolition', 'date' => '1950-01-01']];

        $this->assertSame(T::ACTIVE, T::statusFromEvents($events, 1940, true));
        // Démolie : fermée, quoi qu'en disent les sources du jour (règle du 22/11/2026 : la carte ne la garde qu'à la première ère fermée, BuildStationEras::keepClosedOnlyAtClosingEra).
        $this->assertSame(T::CLOSED, T::statusFromEvents($events, 1960, true));
    }

    public function test_freight_closure_alone_keeps_passengers(): void
    {
        $events = [['type' => 'opening', 'date' => '1880-01-01'], ['type' => 'closure_freight', 'date' => '1950-01-01']];

        $this->assertSame(T::ACTIVE, T::statusFromEvents($events, 1960, false));
    }

    public function test_passenger_then_freight_closure_gives_a_closed_station_still_standing(): void
    {
        $events = [
            ['type' => 'opening', 'date' => '1880-01-01'],
            ['type' => 'closure_passenger', 'date' => '1938-01-01'],
            ['type' => 'closure_freight', 'date' => '1970-01-01'],
        ];

        $this->assertSame(T::FREIGHT, T::statusFromEvents($events, 1950, false), 'une suite est connue : fret seul');
        $this->assertSame(T::CLOSED, T::statusFromEvents($events, 1980, true));
    }

    public function test_reopened_station_vanished_today_is_closed_in_between(): void
    {
        $events = [
            ['type' => 'opening', 'date' => '1870-05-01'],
            ['type' => 'closure', 'date' => '1939-01-01'],
            ['type' => 'reopening', 'date' => '2007-12-09'],
        ];

        $this->assertSame(T::CLOSED, T::statusFromEvents($events, 1960, false), 'rouverte plus tard : elle existait');
        $this->assertSame(T::ACTIVE, T::statusFromEvents($events, 2010, false));
    }

    public function test_unknown_opening_is_assumed_at_the_start_of_the_closure_decade(): void
    {
        // Fermée en 1930 : supposée ouverte en 1920 (décennie qui précède l'ère de la fermeture).
        $events = [['type' => 'closure', 'date' => '1930-06-01']];

        $this->assertNull(T::statusFromEvents($events, 1910, false));
        $this->assertSame(T::ACTIVE, T::statusFromEvents($events, 1920, false));
        $this->assertSame(T::CLOSED, T::statusFromEvents($events, 1930, false));
    }

    public function test_unknown_opening_with_a_passenger_closure(): void
    {
        $events = [['type' => 'closure_passenger', 'date' => '1955-01-01'], ['type' => 'closure_freight', 'date' => '1970-01-01']];

        $this->assertSame(T::ACTIVE, T::statusFromEvents($events, 1950, false));
        $this->assertSame(T::FREIGHT, T::statusFromEvents($events, 1960, false));
    }

    public function test_only_non_closure_events_without_opening_give_no_station(): void
    {
        $this->assertNull(T::statusFromEvents([['type' => 'closure_freight', 'date' => '1950-01-01']], 1950, true));
    }

    // ─── capByLines : matrice complète ──────────────────────────────────

    /** @return array<string, array{0: ?string, 1: int, 2: bool, 3: ?string}> */
    public static function capProvider(): array
    {
        $cases = [];
        $expected = [
            // [statut gare, desserte, uniquement touristique] => statut retenu
            [T::ACTIVE, T::LINES_PASSENGER, false, T::ACTIVE],
            [T::ACTIVE, T::LINES_PASSENGER, true, T::TOURIST],
            [T::ACTIVE, T::LINES_FREIGHT, false, T::FREIGHT],
            [T::ACTIVE, T::LINES_FREIGHT, true, T::FREIGHT],
            [T::ACTIVE, T::LINES_CLOSED, false, T::CLOSED],
            [T::TOURIST, T::LINES_PASSENGER, false, T::TOURIST],
            [T::TOURIST, T::LINES_PASSENGER, true, T::TOURIST],
            [T::TOURIST, T::LINES_FREIGHT, false, T::FREIGHT],
            [T::TOURIST, T::LINES_CLOSED, true, T::CLOSED],
            [T::FREIGHT, T::LINES_PASSENGER, false, T::FREIGHT],
            [T::FREIGHT, T::LINES_PASSENGER, true, T::FREIGHT],
            [T::FREIGHT, T::LINES_FREIGHT, false, T::FREIGHT],
            [T::FREIGHT, T::LINES_CLOSED, false, T::CLOSED],
            [T::CLOSED, T::LINES_PASSENGER, false, T::CLOSED],
            [T::CLOSED, T::LINES_FREIGHT, false, T::CLOSED],
            [T::CLOSED, T::LINES_CLOSED, false, T::CLOSED],
            [null, T::LINES_CLOSED, false, null],
        ];
        foreach ($expected as [$status, $lines, $heritage, $out]) {
            $cases[sprintf('%s / lignes %d%s', $status ?? 'inexistante', $lines, $heritage ? ' (touristiques)' : '')] = [$status, $lines, $heritage, $out];
        }

        return $cases;
    }

    #[DataProvider('capProvider')]
    public function test_station_is_capped_by_its_lines(?string $status, int $lines, bool $heritageOnly, ?string $expected): void
    {
        $this->assertSame($expected, T::capByLines($status, $lines, $heritageOnly));
    }

    public function test_capping_never_makes_a_station_more_active(): void
    {
        $rank = [T::CLOSED => 0, T::FREIGHT => 1, T::ACTIVE => 2, T::TOURIST => 2];
        foreach (array_keys($rank) as $status) {
            foreach ([T::LINES_CLOSED, T::LINES_FREIGHT, T::LINES_PASSENGER] as $lines) {
                foreach ([false, true] as $heritage) {
                    $capped = T::capByLines($status, $lines, $heritage);
                    $this->assertLessThanOrEqual($rank[$status], $rank[$capped], "{$status} / {$lines}");
                    // Idempotent : plafonner deux fois ne change rien.
                    $this->assertSame($capped, T::capByLines($capped, $lines, $heritage));
                }
            }
        }
    }

    public function test_station_timeline_codes_are_the_station_status_enum(): void
    {
        $this->assertEqualsCanonicalizing(
            array_map(fn ($c) => $c->value, StationStatus::cases()),
            [T::ACTIVE, T::TOURIST, T::FREIGHT, T::CLOSED],
        );
    }

    // ─── isolationVerdict ────────────────────────────────────────────────

    /** @return array<string, array{0: array<int, bool>, 1: int, 2: bool, 3: bool, 4: string}> */
    public static function isolationProvider(): array
    {
        return [
            'dernière voie fermée, plus rien ensuite' => [[1950 => false], 1970, false, false, C::ISOLATED_CLOSED],
            'dernière voie en service, plus rien ensuite' => [[1950 => true], 1970, false, false, C::ISOLATED_UNCERTAIN],
            'historique non trié : la plus récente compte' => [[1950 => false, 1940 => true], 1970, false, false, C::ISOLATED_CLOSED],
            'voie en service puis fermée' => [[1940 => true, 1950 => false], 1970, true, false, C::ISOLATED_CLOSED],
            'voie seulement plus tard, gare déduite des lignes' => [[1960 => true], 1950, false, true, C::ISOLATED_ABSENT],
            'voie seulement plus tard, ère courante' => [[2030 => true], 2020, true, true, C::ISOLATED_UNCERTAIN],
            'voie seulement plus tard, ouverture propre' => [[1960 => true], 1950, false, false, C::ISOLATED_UNCERTAIN],
            'trou entre deux décennies' => [[1940 => true, 1960 => true], 1950, false, true, C::ISOLATED_UNCERTAIN],
            'aucune voie à aucune ère' => [[], 1950, false, true, C::ISOLATED_UNCERTAIN],
        ];
    }

    /** @param  array<int, bool>  $history */
    #[DataProvider('isolationProvider')]
    public function test_isolated_station_verdict(array $history, int $year, bool $current, bool $inferred, string $expected): void
    {
        $this->assertSame($expected, C::isolationVerdict($history, $year, $current, $inferred));
    }

    // ─── exemption ───────────────────────────────────────────────────────

    public function test_unmodeled_network_is_exempt_unless_the_status_comes_from_sncf(): void
    {
        $this->assertSame(C::NOTE_UNMODELED, C::exemption(false, false, false, 'osm', false));
        $this->assertSame(C::NOTE_UNMODELED, C::exemption(true, false, false, 'wikidata', false));
        $this->assertNull(C::exemption(false, false, false, 'sncf', false));
        $this->assertNull(C::exemption(false, false, false, null, false), 'provenance inconnue = liste SNCF');
    }

    public function test_undated_lines_exempt_only_historical_eras_without_level(): void
    {
        $this->assertSame(C::NOTE_UNDATED, C::exemption(false, false, true, 'sncf', true));
        $this->assertNull(C::exemption(true, false, true, 'sncf', true), 'ère courante : R2 stricte');
        $this->assertNull(C::exemption(false, true, true, 'sncf', true), 'desserte calculée : R2 stricte');
    }

    public function test_station_whose_lines_are_gone_follows_its_lines(): void
    {
        // Haltes de Templeuve - Don-Sainghin, « en service » selon Wikidata : la ligne a figuré sur
        // la carte puis a disparu, aucune voie actuelle à côté → R2 (gare fermée).
        $this->assertNull(C::exemption(true, false, false, 'wikidata', false, linesGone: true, liveTrack: false));
        $this->assertNull(C::exemption(false, false, false, 'osm', false, linesGone: true, liveTrack: false));
        // Neuilly-Plaisance (RER A de la RATP, absent de VFF) rattachée à une ancienne voie militaire :
        // la voie actuelle hors VFF garde l'exception « réseau non modélisé ».
        $this->assertSame(C::NOTE_UNMODELED, C::exemption(true, false, false, 'osm', false, linesGone: true, liveTrack: true));
        // Jamais de ligne VFF : réseau non modélisé, comme avant.
        $this->assertSame(C::NOTE_UNMODELED, C::exemption(true, false, false, 'osm', false, linesGone: false, liveTrack: false));
    }

    public function test_tourist_railway_station_is_never_shown_active(): void
    {
        $this->assertSame(T::TOURIST, C::touristCap(T::ACTIVE, true));
        $this->assertSame(T::TOURIST, C::touristCap(T::FREIGHT, true));
        $this->assertSame(T::CLOSED, C::touristCap(T::CLOSED, true));
        $this->assertSame(T::ACTIVE, C::touristCap(T::ACTIVE, false));
    }

    public function test_tourist_track_detection(): void
    {
        $this->assertTrue(C::isTouristTrack('narrow_gauge', 'tourism', null, null, 'Le p’tit train de Saint-Trojan', null), 'Gatseau');
        $this->assertTrue(C::isTouristTrack('rail', null, 'Chemin de fer de la Baie de Somme', null, null, null), 'CFBS');
        $this->assertTrue(C::isTouristTrack('rail', null, null, null, 'Disneyland Railroad', null));
        $this->assertTrue(C::isTouristTrack('Voie ferrée principale', 'Vélo-rail', null, null, null, null), 'BD TOPO');
        $this->assertFalse(C::isTouristTrack('rail', 'main', 'RATP', 'RER', 'RER A', null), 'RER A');
        $this->assertFalse(C::isTouristTrack('rail', 'main', 'GetLink', null, null, null), 'tunnel sous la Manche');
    }

    public function test_station_with_a_level_is_always_capped(): void
    {
        foreach ([[true, true], [false, false], [true, false]] as [$modeled, $undated]) {
            $this->assertNull(C::exemption(false, true, $modeled, 'osm', $undated));
        }
    }
}

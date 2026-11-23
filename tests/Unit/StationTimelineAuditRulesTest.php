<?php

namespace Tests\Unit;

use App\Services\StationTimeline as T;
use PHPUnit\Framework\TestCase;

/**
 * Règles G3 et G6 de l'audit des gares (StationTimeline::openingBoundedByMap et closureFromLinePassengerEnds),
 * appliquées par vff:build-station-eras avec --rules=g3,g6.
 */
class StationTimelineAuditRulesTest extends TestCase
{
    private const ERA_YEARS = [1870, 1880, 1890, 1900, 1910, 1920];

    public function test_g3_bounds_an_opening_older_than_the_first_served_era(): void
    {
        // Balsièges-Bourg : ligne datée de 1884, gare desservie sur la carte à partir de 1910.
        $eras = [1890 => T::CLOSED, 1900 => T::CLOSED, 1910 => T::ACTIVE, 1920 => T::ACTIVE, 2030 => T::ACTIVE];

        $this->assertSame(['date' => '1910-12-31', 'precision' => T::RANGE, 'after' => 1883, 'first_open' => 1910],
            T::openingBoundedByMap('1884-01-01', $eras, self::ERA_YEARS, 2030, '2026-11-22'));
        // Libellé de la fiche, même convention que les fermetures en intervalle.
        $this->assertSame('entre 1884 et 1910', T::closureLabel('1910-12-31', T::RANGE, 1883));
    }

    public function test_g3_counts_freight_only_eras_as_served_and_absent_eras_as_gaps(): void
    {
        $this->assertSame(1900, T::openingBoundedByMap('1884-06-01', [1900 => T::FREIGHT], self::ERA_YEARS, 2030, '2026-11-22')['first_open'] ?? null);
    }

    public function test_g3_keeps_an_opening_the_map_agrees_with(): void
    {
        // Ouverte en 1884, montrée à l'ère 1890 : rien à borner.
        $this->assertNull(T::openingBoundedByMap('1884-01-01', [1890 => T::ACTIVE], self::ERA_YEARS, 2030, '2026-11-22'));
        // Ouverte en 1890 (au 31/12 de l'ère 1890 elle l'est déjà) : idem.
        $this->assertNull(T::openingBoundedByMap('1890-05-01', [1890 => T::ACTIVE], self::ERA_YEARS, 2030, '2026-11-22'));
        // Jamais desservie sur la carte : pas de borne (la date de fermeture s'en charge).
        $this->assertNull(T::openingBoundedByMap('1884-01-01', [1890 => T::CLOSED], self::ERA_YEARS, 2030, '2026-11-22'));
    }

    public function test_g3_does_not_trust_a_map_with_a_long_hole_or_a_station_already_closed(): void
    {
        // Quatre ères sans desserte (1880 à 1910) : trou de carte, la date reste.
        $this->assertNull(T::openingBoundedByMap('1874-01-01', [1920 => T::ACTIVE], self::ERA_YEARS, 2030, '2026-11-22'));
        // Trois ères : la règle s'applique encore.
        $this->assertSame(1910, T::openingBoundedByMap('1884-01-01', [1910 => T::ACTIVE], self::ERA_YEARS, 2030, '2026-11-22')['first_open'] ?? null);
        // Breuilpont : fermée aux voyageurs avant la première ère desservie (fret seul) : elle était déjà ouverte.
        $this->assertNull(T::openingBoundedByMap('1884-01-01', [1910 => T::FREIGHT], self::ERA_YEARS, 2030, '2026-11-22', '1905-03-01'));
        $this->assertNull(T::openingBoundedByMap('1884-01-01', [1910 => T::FREIGHT], self::ERA_YEARS, 2030, '2026-11-22', '1910-06-01'));
        $this->assertNotNull(T::openingBoundedByMap('1884-01-01', [1910 => T::ACTIVE], self::ERA_YEARS, 2030, '2026-11-22', '1955-06-01'));
    }

    public function test_g3_upper_bound_is_today_when_only_the_current_era_is_served(): void
    {
        $this->assertSame(['date' => '2026-11-22', 'precision' => T::RANGE, 'after' => 1909, 'first_open' => 2030],
            T::openingBoundedByMap('1910-01-01', [1920 => T::CLOSED, 2030 => T::ACTIVE], self::ERA_YEARS, 2030, '2026-11-22'));
    }

    public function test_g6_dates_the_closure_from_the_passenger_end_of_every_line(): void
    {
        // Samoussy : voyageurs sûrs jusqu'en 1960, fret seul non confirmé ensuite, fermée aujourd'hui.
        $eras = [1960 => ['status' => T::ACTIVE, 'uncertain' => false], 1970 => ['status' => T::FREIGHT, 'uncertain' => true],
            2030 => ['status' => T::CLOSED]];

        $this->assertSame(['date' => '1969-09-28', 'precision' => 'day'],
            T::closureFromLinePassengerEnds($eras, 2030, '2026-11-22', '2026-11-22', [['date' => '1969-09-28', 'precision' => 'day']]));
        // Deux lignes : la plus tardive des deux fins.
        $this->assertSame(['date' => '1971-02-01', 'precision' => 'month'],
            T::closureFromLinePassengerEnds($eras, 2030, '2026-11-22', '2026-11-22',
                [['date' => '1969-09-28', 'precision' => 'day'], ['date' => '1971-02-01', 'precision' => 'month']]));
    }

    public function test_g6_needs_an_end_on_every_line(): void
    {
        $eras = [1960 => ['status' => T::ACTIVE, 'uncertain' => false], 2030 => ['status' => T::CLOSED]];

        // Événement sans PK (« toute la ligne » ou géométrie seule) : la ligne n'a pas de fin, rien n'est daté.
        $this->assertNull(T::closureFromLinePassengerEnds($eras, 2030, '2026-11-22', '2026-11-22',
            [['date' => '1969-09-28', 'precision' => 'day'], ['date' => null, 'precision' => null]]));
        $this->assertNull(T::closureFromLinePassengerEnds($eras, 2030, '2026-11-22', '2026-11-22', []));
        // Talmay : voyageurs fermés en 1938, la ligne ferme au fret à la gare en 1971 : le fret a continué.
        $this->assertNull(T::closureFromLinePassengerEnds($eras, 2030, '2026-11-22', '2026-11-22',
            [['date' => '1969-09-28', 'precision' => 'day', 'freight_after' => true]]));
    }

    public function test_g6_never_contradicts_a_sure_era_or_a_reopening(): void
    {
        // Pont-de-Roide : fin de 1938 à son PK, mais desservie de façon sûre en 2020.
        $sure = [2020 => ['status' => T::ACTIVE, 'uncertain' => false], 2030 => ['status' => T::CLOSED]];
        $this->assertNull(T::closureFromLinePassengerEnds($sure, 2030, '2026-11-22', '2026-11-22', [['date' => '1938-12-05', 'precision' => 'day']]));
        // Fin la même année qu'une ère sûre (état au 31/12) : contredite aussi.
        $sameYear = [1960 => ['status' => T::FREIGHT, 'uncertain' => false], 2030 => ['status' => T::CLOSED]];
        $this->assertNull(T::closureFromLinePassengerEnds($sameYear, 2030, '2026-11-22', '2026-11-22', [['date' => '1960-05-01', 'precision' => 'day']]));
        // Gare rouverte après la fin des voyageurs.
        $uncertain = [1960 => ['status' => T::ACTIVE, 'uncertain' => false], 1970 => ['status' => T::ACTIVE, 'uncertain' => true]];
        $this->assertNull(T::closureFromLinePassengerEnds($uncertain, 2030, '2026-11-22', '2026-11-22',
            [['date' => '1965-01-01', 'precision' => 'year']], '1975-06-01'));
        // Au-delà de la borne haute des ères (gare déjà fermée à l'ère 1970) ou dans le futur.
        $this->assertNull(T::closureFromLinePassengerEnds($uncertain, 2030, '1970-12-31', '2026-11-22', [['date' => '1972-01-01', 'precision' => 'day']]));
        $this->assertNull(T::closureFromLinePassengerEnds($uncertain, 2030, '2026-11-22', '2026-11-22', [['date' => '2027-12-12', 'precision' => 'day']]));
    }

    public function test_g6_without_any_sure_era_takes_the_line_end(): void
    {
        $eras = [1960 => ['status' => T::ACTIVE, 'uncertain' => true], 2030 => ['status' => T::CLOSED]];

        $this->assertSame(['date' => '1969-09-28', 'precision' => 'day'],
            T::closureFromLinePassengerEnds($eras, 2030, '2026-11-22', '2026-11-22', [['date' => '1969-09-28', 'precision' => 'weird']]));
    }

    public function test_g7_closes_a_passenger_era_after_2014_for_a_station_absent_from_the_attendance_series(): void
    {
        $this->assertSame(T::CLOSED, T::closedByAttendance(2020, false, T::ACTIVE, T::CLOSED, true));
        // Fret seul aujourd'hui : la gare reste desservie par le fret, seuls les voyageurs ont disparu.
        $this->assertSame(T::FREIGHT, T::closedByAttendance(2020, false, T::ACTIVE, T::FREIGHT, true));
        // Avant 2015 la série ne dit rien ; l'ère courante garde son état du jour ; gare présente dans la série ou sans UIC ;
        // fret historique (l'absence de voyageurs ne prouve rien du fret) ; gare ouverte aujourd'hui.
        $this->assertNull(T::closedByAttendance(2010, false, T::ACTIVE, T::CLOSED, true));
        $this->assertNull(T::closedByAttendance(2030, true, T::ACTIVE, T::CLOSED, true));
        $this->assertNull(T::closedByAttendance(2020, false, T::ACTIVE, T::CLOSED, false));
        $this->assertNull(T::closedByAttendance(2020, false, T::FREIGHT, T::CLOSED, true));
        $this->assertNull(T::closedByAttendance(2020, false, T::ACTIVE, null, true));
        $this->assertContains(T::RULE_NO_ATTENDANCE, T::ERA_RULES);
    }
}

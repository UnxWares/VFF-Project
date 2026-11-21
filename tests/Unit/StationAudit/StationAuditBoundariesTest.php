<?php

namespace Tests\Unit\StationAudit;

use App\Services\StationAudit\DateAuditor;
use App\Services\StationAudit\DateGrade;
use App\Services\StationAudit\PlacementGrade;
use App\Services\StationAudit\PlacementGrader;
use App\Services\StationAudit\SourcePosition;
use PHPUnit\Framework\TestCase;

/**
 * Frontières des seuils de l'audit des gares (docs/AUDIT-GARES.md) : placement (20 m, 100 m, 1 km, listes par PK
 * à 50 m, deux sources d'accord contre la position retenue) et contrôles des dates (un an de marge, une ère, année
 * en cours). Complète PlacementGraderTest et DateAuditorTest, qui couvrent les cas typiques.
 */
class StationAuditBoundariesTest extends TestCase
{
    private static function at(string $source, ?int $distance, float $lon = 2.0, ?int $precision = null, bool $derived = false): SourcePosition
    {
        return new SourcePosition($source, $source.'-ref', $lon, 48.0, $distance, precisionM: $precision, derived: $derived);
    }

    public function test_placement_thresholds_are_inclusive(): void
    {
        $grade = fn (int $m): PlacementGrade => PlacementGrader::grade([self::at('sncf', 0), self::at('osm', $m)], 'sncf')->grade;
        $this->assertSame(PlacementGrade::Agree, $grade(PlacementGrader::AGREE_M));
        $this->assertSame(PlacementGrade::Close, $grade(PlacementGrader::AGREE_M + 1));
        $this->assertSame(PlacementGrade::Close, $grade(PlacementGrader::CLOSE_M));
        $this->assertSame(PlacementGrade::Disagree, $grade(PlacementGrader::CLOSE_M + 1));
        // Source peu précise (Wikidata) : désaccord seulement au-delà de 1 km, sinon isolée.
        $coarse = fn (int $m): PlacementGrade => PlacementGrader::grade([self::at('sncf', 0), self::at('wikidata', $m)], 'sncf')->grade;
        $this->assertSame(PlacementGrade::Isolated, $coarse(PlacementGrader::DISAGREE_COARSE_M));
        $this->assertSame(PlacementGrade::Disagree, $coarse(PlacementGrader::DISAGREE_COARSE_M + 1));
        $this->assertSame(PlacementGrade::Close, $coarse(PlacementGrader::CLOSE_M));
    }

    public function test_point_lists_are_precise_up_to_fifty_metres_only(): void
    {
        $this->assertTrue(PlacementGrader::isPrecise('lignes-oubliees', PlacementGrader::PRECISE_LIST_M));
        $this->assertFalse(PlacementGrader::isPrecise('lignes-oubliees', PlacementGrader::PRECISE_LIST_M + 1));
        $this->assertFalse(PlacementGrader::isPrecise('wikipedia_schema', null));
        $this->assertTrue(PlacementGrader::isPrecise('sncf_voyageurs'));
        $this->assertFalse(PlacementGrader::isPrecise('wikidata'));
        // Source inconnue : sa propre famille, peu précise.
        $this->assertSame('autre', PlacementGrader::family('autre'));
        $this->assertSame('', PlacementGrader::family(null));
        $this->assertFalse(PlacementGrader::isPrecise('autre'));
    }

    public function test_each_family_is_represented_by_its_nearest_position(): void
    {
        // Deux positions OSM : la plus proche représente la famille (l'autre est souvent un homonyme).
        $r = PlacementGrader::grade([self::at('sncf', 0), self::at('osm', 900), self::at('osm', 10)], 'sncf');
        $this->assertSame([PlacementGrade::Agree, 2, 10, 10], [$r->grade, $r->families, $r->nearestAgreeingM, $r->farthestPreciseM]);
        // Positions recopiées et positions de la famille retenue ne votent pas.
        $alone = PlacementGrader::grade([self::at('sncf', 0), self::at('sncf_voyageurs', 500), self::at('osm', 3, derived: true)], 'sncf');
        $this->assertSame([PlacementGrade::Isolated, 1, null], [$alone->grade, $alone->families, $alone->nearestAgreeingM]);
        $this->assertSame(PlacementGrade::None, PlacementGrader::grade([self::at('osm', 3, derived: true)], 'sncf')->grade);
        // Distance inconnue : la position compte comme famille, pas comme avis.
        $unknown = PlacementGrader::grade([self::at('sncf', 0), self::at('osm', null)], 'sncf');
        $this->assertSame([PlacementGrade::Isolated, 2], [$unknown->grade, $unknown->families]);
    }

    public function test_outvoted_needs_two_far_sources_close_to_each_other_and_more_than_the_near_ones(): void
    {
        // Un degré de longitude à 48° de latitude : environ 74,5 km ; 0,001° ≈ 74 m.
        $far = fn (string $source, float $lon): SourcePosition => self::at($source, 500, $lon);
        $two = PlacementGrader::grade([self::at('sncf', 0), $far('osm', 2.0), $far('bdtopo', 2.001)], 'sncf');
        $this->assertTrue($two->outvoted);
        // Les deux sources lointaines sont à plus de 100 m l'une de l'autre : pas d'accord entre elles.
        $this->assertFalse(PlacementGrader::grade([self::at('sncf', 0), $far('osm', 2.0), $far('bdtopo', 2.002)], 'sncf')->outvoted);
        // Autant de sources proches que lointaines : la position retenue n'est pas mise en minorité.
        $tie = PlacementGrader::grade([self::at('sncf', 0), $far('osm', 2.0), $far('bdtopo', 2.001), self::at('ohm', 5), self::at('correction', 2)], 'sncf');
        $this->assertFalse($tie->outvoted);
        // Les listes par PK ne votent pas.
        $this->assertFalse(PlacementGrader::grade([self::at('sncf', 0), $far('osm', 2.0), $far('lignes-oubliees', 2.0001)], 'sncf')->outvoted);
        // Une seule source lointaine : jamais mise en minorité.
        $this->assertFalse(PlacementGrader::grade([self::at('sncf', 0), $far('osm', 2.0)], 'sncf')->outvoted);
        $this->assertEqualsWithDelta(74.5, PlacementGrader::metres(self::at('a', 0, 2.0), self::at('b', 0, 2.001)), 0.5);
    }

    /** @return array<string, mixed> */
    private static function facts(array $overrides = []): array
    {
        return array_merge([
            'status' => 'declassified',
            'opened_at' => '1866-05-01', 'opened_precision' => 'day', 'opened_source' => 'wikidata',
            'closed_at' => '1938-01-01', 'closed_precision' => 'year', 'closed_source' => 'wikipedia',
            'events' => [],
            'lines' => ['count' => 1, 'first_year' => 1866, 'last_year' => 1938, 'open' => 0],
            'freight_flag' => null,
            'candidates' => [],
            'track_offset_m' => 4,
            'today_year' => 2026,
        ], $overrides);
    }

    /** @return list<string> */
    private static function codes(array $overrides): array
    {
        return array_column(DateAuditor::audit(self::facts($overrides))->checks, 'code');
    }

    public function test_one_year_margins_are_kept(): void
    {
        // Ouverture un an avant la première desserte : tolérée ; deux ans : signalée.
        $this->assertSame([], self::codes(['opened_at' => '1865-01-01', 'lines' => ['count' => 1, 'first_year' => 1866, 'last_year' => 1938, 'open' => 0]]));
        $this->assertSame(['opening_before_lines'], self::codes(['opened_at' => '1864-12-31', 'lines' => ['count' => 1, 'first_year' => 1866, 'last_year' => 1938, 'open' => 0]]));
        // Fin des voyageurs l'année qui suit la fermeture totale : tolérée.
        $passenger = fn (string $date): array => ['events' => [['type' => 'closure_passenger', 'date' => $date, 'precision' => 'year', 'source' => 'wikipedia', 'inferred' => false]]];
        $this->assertSame([], self::codes($passenger('1939-06-01')));
        $this->assertSame(['passenger_after_closure'], self::codes($passenger('1940-01-01')));
        // Écart Wikidata : 366 jours tolérés, 367 non ; les autres types de date ne comptent pas.
        $candidate = fn (int $days, string $kind = 'opening'): array => ['candidates' => [['kind' => $kind, 'comparison' => 'differs', 'delta_days' => $days, 'date' => '1867-05-01', 'precision' => 'day']]];
        $this->assertSame([], self::codes($candidate(366)));
        $this->assertSame(['wikidata_differs'], self::codes($candidate(-367)));
        $this->assertSame([], self::codes($candidate(4000, 'closure_freight')));
        // Voie à 50 m : tolérée ; 51 m : loin.
        $this->assertSame([], self::codes(['track_offset_m' => PlacementGrader::TRACK_FAR_M]));
        $this->assertSame(['far_from_track'], self::codes(['track_offset_m' => PlacementGrader::TRACK_FAR_M + 1]));
        $this->assertSame([], self::codes(['track_offset_m' => null]));
    }

    public function test_open_station_on_ended_lines_uses_the_current_year(): void
    {
        $open = ['status' => 'active', 'closed_at' => null, 'closed_source' => null];
        $lines = fn (int $last, int $stillOpen = 0): array => ['lines' => ['count' => 2, 'first_year' => 1866, 'last_year' => $last, 'open' => $stillOpen]];
        $this->assertSame([], self::codes($open + $lines(2025)));
        $this->assertSame(['open_after_lines'], self::codes($open + $lines(2024)));
        $this->assertSame([], self::codes($open + $lines(1990, 1)));
        $this->assertSame([], self::codes($open + ['lines' => ['count' => 0, 'first_year' => null, 'last_year' => null, 'open' => 0]]));
    }

    public function test_undated_closure_is_a_range_that_reaches_the_current_year(): void
    {
        $range = fn (string $date): array => ['closed_at' => $date, 'closed_precision' => 'range', 'closed_source' => 'vff:eras'];
        $now = DateAuditor::audit(self::facts($range('2026-11-21')));
        $this->assertSame(DateGrade::Unknown, $now->closure);
        $this->assertContains('closed_without_date', array_column($now->checks, 'code'));
        // L'année d'avant reste un intervalle connu : estimée, pas inconnue.
        $before = DateAuditor::audit(self::facts($range('2025-12-31')));
        $this->assertSame(DateGrade::Estimated, $before->closure);
        $this->assertNotContains('closed_without_date', array_column($before->checks, 'code'));
    }

    public function test_opening_before_the_map_needs_a_whole_era(): void
    {
        $eras = fn (int $first): array => ['eras' => [['year' => $first, 'status' => 'active'], ['year' => $first + 10, 'status' => 'declassified']]];
        $this->assertSame(['opening_before_map'], self::codes(['opened_at' => '1870-01-01'] + $eras(1880)));
        $this->assertSame([], self::codes(['opened_at' => '1871-01-01'] + $eras(1880)));
        // Aucune ère ouverte : rien à comparer.
        $this->assertNotContains('opening_before_map', self::codes(['opened_at' => '1800-01-01', 'eras' => [['year' => 1880, 'status' => 'declassified']]]));
    }

    public function test_era_passenger_mismatch_at_the_end_of_the_era_year(): void
    {
        $end = ['events' => [['type' => 'closure_passenger', 'date' => '1970-06-01', 'precision' => 'day', 'source' => 'wikipedia', 'inferred' => false]]];
        // Instantané au 31/12/1970 : voyageurs finis en juin, « ouverte aux voyageurs » est faux.
        $this->assertContains('era_passenger_mismatch', self::codes($end + ['eras' => [['year' => 1970, 'status' => 'active']]]));
        $this->assertNotContains('era_passenger_mismatch', self::codes($end + ['eras' => [['year' => 1960, 'status' => 'active']]]));
        // « Fret seul » en 1970 avec des voyageurs jusqu'en 1970 : juste (fini avant le 31/12).
        $this->assertNotContains('era_passenger_mismatch', self::codes($end + ['eras' => [['year' => 1970, 'status' => 'no_commercial']]]));
        $this->assertContains('era_passenger_mismatch', self::codes($end + ['eras' => [['year' => 1960, 'status' => 'no_commercial']]]));
        // Une réouverture l'année de la fin, ou après : la suite des ères est légitime.
        $reopened = $end;
        $reopened['events'][] = ['type' => 'reopening', 'date' => '1970-09-01', 'precision' => 'day', 'source' => 'wikipedia', 'inferred' => false];
        $this->assertNotContains('era_passenger_mismatch', self::codes($reopened + ['eras' => [['year' => 1980, 'status' => 'active']]]));
        // Fin des voyageurs déduite seulement (1950) : elle ne date rien ; la fermeture totale sourcée (1938) la
        // remplace, et une gare « ouverte aux voyageurs » en 1940 est contredite.
        $inferred = ['events' => [['type' => 'closure_passenger', 'date' => '1950-01-01', 'precision' => 'year', 'source' => 'vff:eras', 'inferred' => true]]];
        $this->assertContains('era_passenger_mismatch', self::codes($inferred + ['eras' => [['year' => 1940, 'status' => 'active']]]));
        $this->assertNotContains('era_passenger_mismatch', self::codes($inferred + ['eras' => [['year' => 1930, 'status' => 'active']]]));
        // Ère de l'année en cours : jamais contredite (l'état du jour n'est pas encore un instantané passé).
        $this->assertNotContains('era_passenger_mismatch', self::codes($end + ['today_year' => 1970, 'eras' => [['year' => 1970, 'status' => 'active']]]));
    }

    public function test_services_of_a_closed_station_fall_back_on_the_total_closure(): void
    {
        $r = DateAuditor::audit(self::facts(['closed_source' => 'inferred:line_event']));
        $this->assertSame(['closed', '1938-01-01', 'year', DateGrade::Estimated->value],
            [$r->services['passenger']['state'], $r->services['passenger']['closed_on'], $r->services['passenger']['closed_precision'], $r->services['passenger']['grade']]);
        $this->assertSame('closed', $r->services['freight']['state']);
        // Événement sourcé préféré à un événement déduit plus ancien.
        $events = ['events' => [
            ['type' => 'closure_freight', 'date' => '1930-01-01', 'precision' => 'year', 'source' => 'vff:eras', 'inferred' => true],
            ['type' => 'closure_freight', 'date' => '1935-03-01', 'precision' => 'month', 'source' => 'wikipedia', 'inferred' => false],
        ]];
        $freight = DateAuditor::audit(self::facts($events))->services['freight'];
        $this->assertSame(['1935-03-01', 'wikipedia', DateGrade::Sourced->value], [$freight['closed_on'], $freight['source'], $freight['grade']]);
        // Gare ouverte sans drapeau fret : service fret inconnu.
        $open = DateAuditor::audit(self::facts(['status' => 'active', 'closed_at' => null, 'closed_source' => null]))->services['freight'];
        $this->assertSame(['unknown', DateGrade::Unknown->value], [$open['state'], $open['grade']]);
        $tourist = DateAuditor::audit(self::facts(['status' => 'tourist', 'closed_at' => null, 'closed_source' => null, 'freight_flag' => true]))->services;
        $this->assertSame(['open', 'open'], [$tourist['passenger']['state'], $tourist['freight']['state']]);
    }
}

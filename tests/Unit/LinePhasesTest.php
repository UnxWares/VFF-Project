<?php

namespace Tests\Unit;

use App\Services\LinePhases;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Phases d'une ligne (App\Services\LinePhases::compute, calcul pur) : changements datés
 * par les vrais événements, ancrés sur les états par décennie du moteur, état complet
 * de la ligne à chaque phase, phase « au meilleur », décennies.
 */
class LinePhasesTest extends TestCase
{
    private const TODAY = '2026-10-19';

    /** @return list<array{year: int, is_current: bool}> */
    private static function eras(int ...$years): array
    {
        $max = max($years);

        return array_map(fn ($y) => ['year' => $y, 'is_current' => $y === $max], $years);
    }

    private static function ev(int $id, string $type, string $date, string $scope = 'localized', array $extra = []): array
    {
        return ['id' => $id, 'type' => $type, 'date' => $date, 'precision' => strlen($date) === 4 ? 'year' : 'day',
            'source' => 'wikipedia', 'confidence' => 'medium', 'scope' => $scope] + $extra;
    }

    /** @param  array<int, list<string>>  $cover */
    private static function compute(array $eras, array $portions, array $events, array $cover = [], array $adjacency = []): array
    {
        $byId = [];
        foreach ($events as $e) {
            $byId[$e['id']] = $e;
        }

        return LinePhases::compute($eras, $portions, $byId, $cover, $adjacency, self::TODAY);
    }

    // ── Catégories ───────────────────────────────────────────────────────

    public static function categories(): array
    {
        return [
            'mixte' => ['in_service', 'mixed', false, null, false, 'mixed'],
            'voie unique fret' => ['single_track', 'freight', false, null, false, 'freight'],
            'LGV par la ligne' => ['in_service', 'mixed', false, null, true, 'lgv'],
            'voyageurs seuls = LGV' => ['in_service', 'passenger', false, null, false, 'lgv'],
            'train touristique' => ['in_service', 'unknown', true, 'tourist_train', false, 'heritage'],
            'vélorail' => ['abandoned', 'unknown', true, 'velorail', false, 'velorail'],
            'fermée' => ['abandoned', 'unknown', false, null, false, 'closed'],
            'déposée' => ['dismantled', 'unknown', false, null, false, 'dismantled'],
            'rasée' => ['razed', 'unknown', false, null, false, 'razed'],
            'trafic inconnu' => ['in_service', 'unknown', false, null, false, 'unknown'],
        ];
    }

    #[DataProvider('categories')]
    public function test_category(string $status, string $traffic, bool $heritage, ?string $kind, bool $lgv, string $expected): void
    {
        $this->assertSame($expected, LinePhases::category($status, $traffic, $heritage, $kind, $lgv));
    }

    public function test_explaining_types(): void
    {
        $this->assertSame(LinePhases::OPENINGS, LinePhases::explainingTypes('none', 'mixed'));
        $this->assertSame('closure_passenger', LinePhases::explainingTypes('mixed', 'freight')[0]);
        $this->assertSame('closure', LinePhases::explainingTypes('freight', 'closed')[0]);
        // Voie fermée puis déposée : seule une dépose (ou reconversion) la date.
        $this->assertSame(['dismantling', 'conversion'], LinePhases::explainingTypes('closed', 'dismantled'));
        $this->assertContains('closure', LinePhases::explainingTypes('mixed', 'dismantled'));
        $this->assertSame('heritage_start', LinePhases::explainingTypes('closed', 'velorail')[0]);
        $this->assertSame([], LinePhases::explainingTypes('dismantled', 'closed'), 'voie disparue qui réapparaît fermée');
        $this->assertSame([], LinePhases::explainingTypes('mixed', 'mixed'));
        $this->assertTrue(LinePhases::isInconsistent('dismantled', 'closed'));
        $this->assertFalse(LinePhases::isInconsistent('closed', 'dismantled'));
        $this->assertFalse(LinePhases::isInconsistent('dismantled', 'mixed'), 'une réouverture est possible');
    }

    // ── Datation des changements ─────────────────────────────────────────

    public function test_openings_are_dated_by_localized_events_portion_by_portion(): void
    {
        // Portion A ouverte le 06/12/1869, portion B le 15/03/1881 (Briouze - La Ferté-Macé - Couterne).
        $r = self::compute(self::eras(1860, 1870, 1880, 1890, 2030), [
            ['key' => 'A', 'length_m' => 13800, 'states' => [1870 => 'mixed', 1880 => 'mixed', 1890 => 'mixed', 2030 => 'mixed']],
            ['key' => 'B', 'length_m' => 15200, 'states' => [1890 => 'mixed', 2030 => 'mixed']],
        ], [
            self::ev(1, 'opening', '1869-12-06'),
            self::ev(2, 'opening', '1881-03-15'),
            self::ev(3, 'opening', '1873', 'whole'),
        ], [1 => ['A'], 2 => ['B']]);

        $this->assertCount(2, $r['phases']);
        [$p0, $p1] = $r['phases'];
        $this->assertSame(['1869-12-06', 'day'], [$p0['date'], $p0['precision']]);
        $this->assertSame([['none', 'mixed', 1, 'localized']], array_map(fn ($c) => [$c['from'], $c['to'], $c['event_id'], $c['mode']], $p0['changes']));
        $this->assertSame(['mixed', 'none'], $p0['state']);
        $this->assertSame('1881-03-15', $p1['date']);
        $this->assertSame(['mixed', 'mixed'], $p1['state']);
        $this->assertSame(1, $r['best'], 'plus grand développement : quand la ligne est complète');
        $this->assertSame(29000, $p1['active_m']);
        $this->assertSame('growth', $p1['kind']);
        $this->assertSame([1, 2], $r['used_events']);
    }

    public function test_neighbor_portion_gives_its_date_before_whole_line_events(): void
    {
        // A est datée par un événement localisé ; B, contiguë, change de la même façon.
        $r = self::compute(self::eras(1980, 1990, 2030), [
            ['key' => 'A', 'length_m' => 10000, 'states' => [1990 => 'lgv', 2030 => 'lgv']],
            ['key' => 'B', 'length_m' => 3000, 'states' => [1990 => 'lgv', 2030 => 'lgv']],
        ], [
            self::ev(1, 'opening', '1983-09-25'),
            self::ev(2, 'opening', '1981-09-27', 'whole'),
        ], [1 => ['A']], ['A' => ['B'], 'B' => ['A']]);

        $this->assertCount(1, $r['phases']);
        $modes = array_column($r['phases'][0]['changes'], 'mode', 'length_m');
        $this->assertSame(['localized', 'neighbor'], [$modes[10000], $modes[3000]]);
        $this->assertSame('1983-09-25', $r['phases'][0]['date']);
    }

    public function test_whole_line_event_dates_a_change_without_localized_event(): void
    {
        $r = self::compute(self::eras(1930, 1940, 2030), [
            ['key' => 'A', 'length_m' => 5000, 'states' => [1930 => 'mixed', 1940 => 'freight', 2030 => 'freight']],
        ], [self::ev(1, 'opening', '1900-01-01'), self::ev(2, 'closure_passenger', '1938-05-15', 'whole')]);

        $last = end($r['phases']);
        $this->assertSame(['1938-05-15', 'whole_line'], [$last['date'], $last['changes'][0]['mode']]);
        $this->assertSame('traffic', $last['kind']);
    }

    public function test_undated_change_is_a_decade_range(): void
    {
        $r = self::compute(self::eras(1940, 1950, 2030), [
            ['key' => 'A', 'length_m' => 7000, 'states' => [1940 => 'closed', 1950 => 'dismantled']],
        ], [
            // Une fermeture aux voyageurs ne date pas une dépose.
            self::ev(1, 'closure_passenger', '1941-02-17'),
        ], [1 => ['A']]);

        // Voie connue d'abord fermée : premier état connu, pas une phase (aucune « fermeture » inventée).
        $this->assertCount(1, $r['phases']);
        $removal = $r['phases'][0];
        $this->assertSame(['1950-12-31', 'range', 1941, 1950], [$removal['date'], $removal['precision'], $removal['from_year'], $removal['to_year']]);
        $this->assertNull($removal['changes'][0]['event_id']);
        $this->assertSame('removal', $removal['kind']);
        // La voie déposée reste déposée ensuite (le moteur ne la dessine plus en 2030).
        $this->assertSame(['dismantled'], $removal['state']);
        $this->assertSame(['dismantled' => 7000], end($r['decades'])['length_by_category']);
    }

    public function test_passenger_closure_then_total_closure_in_the_same_decade_gives_two_phases(): void
    {
        $r = self::compute(self::eras(1960, 1970, 2030), [
            ['key' => 'A', 'length_m' => 8000, 'states' => [1960 => 'mixed', 1970 => 'closed', 2030 => 'closed']],
        ], [
            self::ev(1, 'opening', '1900-01-01'),
            self::ev(2, 'closure_passenger', '1962-06-01'),
            self::ev(3, 'closure', '1968-01-01'),
        ], [1 => ['A'], 2 => ['A'], 3 => ['A']]);

        $dates = array_column($r['phases'], 'date');
        $this->assertSame(['1962-06-01', '1968-01-01'], array_slice($dates, -2));
        $this->assertSame(['mixed', 'freight'], [$r['phases'][1]['changes'][0]['from'], $r['phases'][1]['changes'][0]['to']]);
        $this->assertSame(['freight', 'closed'], [$r['phases'][2]['changes'][0]['from'], $r['phases'][2]['changes'][0]['to']]);
    }

    public function test_line_closed_and_lifted_in_one_decade_gives_closure_then_removal(): void
    {
        $r = self::compute(self::eras(1960, 1970, 2030), [
            ['key' => 'A', 'length_m' => 8000, 'states' => [1960 => 'mixed', 1970 => 'dismantled']],
        ], [self::ev(1, 'closure', '1963-01-01'), self::ev(2, 'dismantling', '1966-04-01')], [1 => ['A'], 2 => ['A']]);

        $steps = array_map(fn ($p) => [$p['date'], $p['changes'][0]['from'], $p['changes'][0]['to']], $r['phases']);
        $this->assertSame([['1963-01-01', 'mixed', 'closed'], ['1966-04-01', 'closed', 'dismantled']], array_slice($steps, -2));
    }

    public function test_undated_two_step_path_is_a_single_change(): void
    {
        $r = self::compute(self::eras(2020, 2030), [
            ['key' => 'A', 'length_m' => 4500, 'states' => [2020 => 'mixed']],
        ], [self::ev(1, 'opening', '1880-01-01')], [1 => ['A']]);

        // « Depuis 2021 » sans événement : pas une phase, un écart rendu à part.
        $this->assertSame(['1880-01-01'], array_column($r['phases'], 'date'));
        $this->assertSame([['from' => 'mixed', 'to' => 'gone', 'portions' => [0], 'length_m' => 4500]], $r['unexplained']);
        $this->assertSame(['mixed'], end($r['phases'])['state'], 'l’état des phases reste celui reconstitué');
    }

    public function test_events_after_today_are_ignored_for_the_current_era(): void
    {
        $r = self::compute(self::eras(2020, 2030), [
            ['key' => 'A', 'length_m' => 5000, 'states' => [2020 => 'closed', 2030 => 'mixed']],
        ], [self::ev(1, 'reopening', '2027-01-01'), self::ev(2, 'opening', '1900-01-01')], [1 => ['A'], 2 => ['A']]);

        $this->assertSame([], array_filter($r['phases'], fn ($p) => $p['is_current']), 'une réouverture future ne date pas l’état du jour');
        $this->assertSame(['closed', 'mixed'], [$r['unexplained'][0]['from'], $r['unexplained'][0]['to']]);
    }

    public function test_reappearing_track_is_flagged(): void
    {
        $r = self::compute(self::eras(1950, 1960, 2030), [
            ['key' => 'A', 'length_m' => 7000, 'states' => [1950 => 'dismantled', 1960 => 'closed', 2030 => 'closed']],
        ], []);
        $this->assertSame([1951, 1960], [$r['phases'][0]['from_year'], $r['phases'][0]['to_year']], 'premier état connu (déposée) : pas une phase');
        $this->assertTrue($r['phases'][0]['changes'][0]['inconsistent']);
    }

    public function test_tiny_changes_are_merged_into_the_next_phase(): void
    {
        $r = self::compute(self::eras(1870, 1880, 1890, 2030), [
            ['key' => 'A', 'length_m' => 13800, 'states' => [1870 => 'mixed', 1880 => 'mixed', 1890 => 'mixed', 2030 => 'mixed']],
            ['key' => 'T', 'length_m' => 300, 'states' => [1880 => 'mixed', 1890 => 'mixed', 2030 => 'mixed']],
            ['key' => 'B', 'length_m' => 15000, 'states' => [1890 => 'mixed', 2030 => 'mixed']],
        ], [
            self::ev(1, 'opening', '1869-12-06'),
            self::ev(2, 'opening', '1873', 'whole'),
            self::ev(3, 'opening', '1881-03-15'),
        ], [1 => ['A'], 3 => ['B']]);

        $this->assertSame(['1869-12-06', '1881-03-15'], array_column($r['phases'], 'date'), 'le bout de 300 m daté « toute la ligne » n’est pas une phase');
        $minor = array_values(array_filter($r['phases'][1]['changes'], fn ($c) => $c['minor'] ?? false));
        $this->assertSame(300, $minor[0]['length_m']);
        $this->assertSame(['mixed', 'mixed', 'mixed'], $r['phases'][1]['state'], 'état final exact');
    }

    public function test_decades_count_every_portion_with_carry_forward(): void
    {
        $r = self::compute(self::eras(1900, 1910, 1920, 2030), [
            ['key' => 'A', 'length_m' => 1000, 'states' => [1900 => 'mixed', 1910 => 'dismantled']],
            ['key' => 'B', 'length_m' => 2000, 'states' => [1900 => 'freight', 1910 => 'freight', 1920 => 'freight', 2030 => 'freight']],
        ], []);

        $byYear = array_column($r['decades'], 'length_by_category', 'year');
        $this->assertSame(['freight' => 2000, 'mixed' => 1000], $byYear[1900]);
        $this->assertSame(['dismantled' => 1000, 'freight' => 2000], $byYear[1920]);
        $this->assertSame(['dismantled' => 1000, 'freight' => 2000], $byYear[2030]);
        $this->assertSame(2000, array_column($r['decades'], 'active_m', 'year')[2030]);
    }

    public function test_no_portion_no_phase(): void
    {
        $this->assertSame(['phases' => [], 'best' => null, 'used_events' => [], 'decades' => [], 'unexplained' => []], LinePhases::compute(self::eras(2030), [], [], [], [], self::TODAY));
    }

    public function test_best_source_and_confidence_win_for_the_same_portion(): void
    {
        $r = self::compute(self::eras(1850, 1860, 2030), [
            ['key' => 'A', 'length_m' => 9000, 'states' => [1860 => 'mixed', 2030 => 'mixed']],
        ], [
            self::ev(1, 'opening', '1858-01-01', 'localized', ['source' => 'wikidata']),
            self::ev(2, 'opening', '1857-05-01', 'localized', ['source' => 'lignes-oubliees']),
        ], [1 => ['A'], 2 => ['A']]);

        $this->assertSame(2, $r['phases'][0]['changes'][0]['event_id'], 'lignes-oubliees (priorité 2) avant Wikidata (8)');
    }

    // ── Phases fantômes (contrôle qualité du 2026-10-20) ─────────────────

    /** Points témoins de $k tous à moins de RETRACE_TOL_M de $others. */
    private static function near(array $map): array
    {
        $out = [];
        foreach ($map as $k => $others) {
            $out[$k] = array_fill(0, count(LinePhases::SAMPLE_FRACTIONS), $others);
        }

        return $out;
    }

    public function test_retraced_geometry_is_not_a_phase(): void
    {
        // Paris - Bordeaux : la reconstruction de 2030 trace 1,3 km autrement (B remplace A).
        $r = LinePhases::compute(self::eras(2010, 2020, 2030), [
            ['key' => 'A', 'length_m' => 1300, 'states' => [2010 => 'mixed', 2020 => 'mixed']],
            ['key' => 'B', 'length_m' => 1290, 'states' => [2030 => 'mixed']],
            ['key' => 'C', 'length_m' => 90000, 'states' => [2010 => 'mixed', 2020 => 'mixed', 2030 => 'mixed']],
        ], [1 => self::ev(1, 'opening', '1840-09-20')], [1 => ['A', 'C']], [], self::TODAY, self::near(['A' => ['B'], 'B' => ['A']]));

        $this->assertSame(['1840-09-20'], array_column($r['phases'], 'date'), 'ni « disparition » ni « ouverture » en 2021-2030');
        $this->assertSame([], $r['unexplained']);
        $byYear = array_column($r['decades'], 'length_by_category', 'year');
        $this->assertSame(['mixed' => 91290], $byYear[2030], 'pas de « voie disparue » comptée');
    }

    public function test_retraced_geometry_keeps_a_real_traffic_change(): void
    {
        // La nouvelle géométrie B (1960) est en fret là où A était mixte : seul le changement de trafic reste.
        $r = LinePhases::compute(self::eras(1950, 1960, 2030), [
            ['key' => 'A', 'length_m' => 5000, 'states' => [1950 => 'mixed']],
            ['key' => 'B', 'length_m' => 5000, 'states' => [1960 => 'freight', 2030 => 'freight']],
        ], [1 => self::ev(1, 'opening', '1900-01-01')], [1 => ['A']], [], self::TODAY, self::near(['A' => ['B'], 'B' => ['A']]));

        $change = $r['phases'][1]['changes'][0];
        $this->assertSame(['mixed', 'freight', 'range'], [$change['from'], $change['to'], $r['phases'][1]['precision']]);
        $this->assertCount(1, $r['phases'][1]['changes'], 'pas de « disparition » de A');
        $this->assertSame(['none', 'freight'], $r['phases'][1]['state'], 'A retirée, B en fret');
    }

    public function test_track_first_known_closed_is_not_a_closure_unless_an_event_dates_it(): void
    {
        $portions = [['key' => 'A', 'length_m' => 6500, 'states' => [1920 => 'closed', 2030 => 'closed']]];
        $r = self::compute(self::eras(1910, 1920, 2030), $portions, []);
        $this->assertSame([], $r['phases'], 'connue fermée en 1920 : rien n’est daté');

        $r = self::compute(self::eras(1910, 1920, 2030), $portions, [self::ev(1, 'closure', '1918-01-01')], [1 => ['A']]);
        $this->assertSame(['1918-01-01'], array_column($r['phases'], 'date'), 'fermeture datée par une source : vraie phase');
    }

    public function test_new_geometry_dated_by_its_own_opening_stays_a_phase(): void
    {
        // Ouverture OHM d'une portion nouvelle à côté de l'ancienne voie : un fait daté et sourcé.
        $r = LinePhases::compute(self::eras(2010, 2020, 2030), [
            ['key' => 'A', 'length_m' => 14000, 'states' => [2010 => 'mixed', 2020 => 'mixed', 2030 => 'mixed']],
            ['key' => 'B', 'length_m' => 1300, 'states' => [2030 => 'mixed']],
        ], [1 => self::ev(1, 'opening', '1880-01-01'), 2 => self::ev(2, 'opening', '2022-01-01')], [1 => ['A'], 2 => ['B']], [], self::TODAY,
            self::near(['B' => ['A']]));

        $this->assertSame(['1880-01-01', '2022-01-01'], array_column($r['phases'], 'date'));
    }

    public function test_loose_continuity_dates_a_detached_piece_changing_the_same_way(): void
    {
        // Angoulême - Limoges : bouts de 0,1 à 0,6 km accolés à la portion fermée le 13/03/2018.
        $r = LinePhases::compute(self::eras(2010, 2020, 2030), [
            ['key' => 'A', 'length_m' => 73000, 'states' => [2010 => 'mixed', 2020 => 'closed', 2030 => 'closed']],
            ['key' => 'P', 'length_m' => 400, 'states' => [2010 => 'mixed', 2020 => 'closed', 2030 => 'closed']],
        ], [1 => self::ev(1, 'opening', '1875-04-26', 'whole'), 2 => self::ev(2, 'closure', '2018-03-13')], [2 => ['A']], [], self::TODAY,
            [], ['P' => ['A'], 'A' => ['P']]);

        $this->assertSame(['1875-04-26', '2018-03-13'], array_column($r['phases'], 'date'), 'plus de « fermeture entre 2011 et 2020 »');
        $this->assertSame('neighbor', $r['phases'][1]['changes'][1]['mode']);
    }

    public function test_touching_portions(): void
    {
        $adj = LinePhases::touching([
            ['A', 0.0, 45.0], ['A', 0.1, 45.0],
            ['B', 0.10001, 45.0], ['B', 0.2, 45.0],
            ['C', 0.5, 45.0], ['C', 0.6, 45.0],
        ], 0.00003);
        $this->assertSame(['B'], $adj['A']);
        $this->assertSame(['A'], $adj['B']);
        $this->assertArrayNotHasKey('C', $adj);
    }

    public function test_merge_minor_keeps_a_lone_minor_phase(): void
    {
        $out = LinePhases::mergeMinor([['date' => '1900-01-01', 'precision' => 'day', 'changes' => [['length_m' => 50, 'mode' => 'localized']]]]);
        $this->assertCount(1, $out);
    }
}

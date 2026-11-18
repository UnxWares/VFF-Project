<?php

namespace Tests\Unit;

use App\Services\EraContinuity;
use PHPUnit\Framework\TestCase;

class EraContinuityTest extends TestCase
{
    private const ACTIVE = 'in_service/mixed//;in_service/mixed//';

    private const CLOSED = 'abandoned/unknown//;abandoned/unknown//';

    private const ABSENT = '-;-';

    public function test_reference_pieces_are_chained_by_their_nearest_ends(): void
    {
        // A (0→1000), B parcouru à l'envers (2000→1015 : début à 2000), C (2010→3000).
        $chains = EraContinuity::chains([
            [0, 0, 1000, 0],
            [2000, 0, 1015, 0],
            [2010, 5, 3000, 0],
        ], 30);

        $this->assertSame([[[0, true], [1, false], [2, true]]], $chains);
    }

    public function test_only_two_ends_are_linked_at_a_junction(): void
    {
        $chains = EraContinuity::chains([
            [0, 0, 1000, 0],
            [1005, 0, 2000, 0],   // le plus proche de la fin de A
            [1020, 0, 1020, 900], // embranchement
        ], 30);

        $this->assertCount(2, $chains);
        $this->assertSame([[0, true], [1, true]], $chains[0]);
    }

    public function test_tiny_duplicates_at_a_node_do_not_break_the_line(): void
    {
        // Rennes : deux tronçons BD TOPO de 7 m superposés (sens opposés) entre deux morceaux.
        $chains = EraContinuity::chains([
            [0, 0, 1000, 0],      // 0 : morceau principal amont
            [1007, 0, 3000, 0],   // 1 : morceau principal aval
            [1000, 0, 1007, 0],   // 2 : doublon de 7 m
            [1007, 0, 1000, 0],   // 3 : le même, à l'envers
        ], 30);

        $this->assertContains([[0, true], [1, true]], $chains);
    }

    public function test_short_piece_between_identical_neighbours_is_absorbed(): void
    {
        // 100 m « fermés » au milieu d'une portion en service (bord de section d'événement).
        $src = EraContinuity::smooth([500, 100, 500], [self::ACTIVE, self::CLOSED, self::ACTIVE], 200, 500);

        $this->assertSame([0, 0, 2], $src);
    }

    public function test_short_boundary_piece_goes_to_the_longer_neighbour(): void
    {
        $src = EraContinuity::smooth([300, 100, 900], [self::ACTIVE, self::ABSENT, self::CLOSED], 200, 500);

        $this->assertSame(2, $src[1]);
    }

    public function test_short_piece_with_its_own_event_is_kept(): void
    {
        $src = EraContinuity::smooth([500, 150, 500], [self::ACTIVE, self::CLOSED, self::ACTIVE], 200, 500,
            fn (array $run, array $around) => $run === [1]);

        $this->assertSame([0, 1, 2], $src);
    }

    public function test_small_hole_in_an_active_portion_is_stitched(): void
    {
        $src = EraContinuity::smooth([800, 100, 100, 100, 800], [self::ACTIVE, self::ABSENT, self::ABSENT, self::ABSENT, self::ACTIVE], 200, 500);

        $this->assertSame([0, 0, 0, 0, 4], $src);
    }

    public function test_long_gap_and_more_active_piece_are_left_alone(): void
    {
        // 700 m sans date : ce n'est plus un « micro-trou ».
        $long = EraContinuity::smooth([800, 700, 800], [self::ACTIVE, self::ABSENT, self::ACTIVE], 200, 500);
        // 300 m en service entre deux portions fermées : ce n'est pas un trou.
        $active = EraContinuity::smooth([800, 300, 800], [self::CLOSED, self::ACTIVE, self::CLOSED], 200, 500);

        $this->assertSame([0, 1, 2], $long);
        $this->assertSame([0, 1, 2], $active);
    }

    public function test_hole_is_never_more_present_than_its_surroundings(): void
    {
        $this->assertTrue(EraContinuity::isHole('-;abandoned/unknown//', 'in_service/mixed//;in_service/freight//'));
        $this->assertFalse(EraContinuity::isHole('in_service/mixed//;-', 'abandoned/unknown//;in_service/mixed//'));
        $this->assertFalse(EraContinuity::isHole(self::ACTIVE, self::ACTIVE));
    }

    public function test_opening_taken_from_the_nearest_dated_portion_stays_within_the_whole_line_dates(): void
    {
        // Ouvertures globales 1856 (première section) et 1871 (achèvement).
        $this->assertSame('1862-05-01', EraContinuity::neighbourOpening('1862-05-01', '1856-01-01', '1871-01-01', null));
        $this->assertSame('1856-01-01', EraContinuity::neighbourOpening('1850-01-01', '1856-01-01', '1871-01-01', null));
        $this->assertSame('1871-01-01', EraContinuity::neighbourOpening('1880-01-01', '1856-01-01', '1871-01-01', null));
        // Une seule ouverture globale : pas de borne haute.
        $this->assertSame('1880-01-01', EraContinuity::neighbourOpening('1880-01-01', '1856-01-01', null, null));
        // Jamais après la première fermeture de la portion.
        $this->assertNull(EraContinuity::neighbourOpening('1880-01-01', '1856-01-01', null, '1870-01-01'));
    }

    public function test_undated_junction_opens_with_the_high_speed_line_it_serves(): void
    {
        // Raccordement de Lyon-Saint-Clair : ligne de Lyon (1856) et LGV Sud-Est, touchée par
        // trois extrémités dont l'une là où la LGV est ouverte en 1981 (les autres : 1992).
        $this->assertSame('1981-09-27', EraContinuity::junctionOpening([
            [317, '1856-01-01', false], [831, '1992-12-13', true], [831, '1981-09-27', true], [831, '1992-12-13', true],
        ]));
        // Entre deux lignes classiques : la plus récente n'est qu'une borne trop ancienne (RER D).
        $this->assertNull(EraContinuity::junctionOpening([[30, '1846-06-20', false], [422, '1849-07-05', false]]));
        // Une extrémité ne touche aucune ligne datée, ou une seule extrémité : pas de date.
        $this->assertNull(EraContinuity::junctionOpening([[317, '1856-01-01', false], [null, null, false]]));
        $this->assertNull(EraContinuity::junctionOpening([[317, '1856-01-01', false], [831, null, true]]));
        $this->assertNull(EraContinuity::junctionOpening([[831, '1981-09-27', true]]));
    }

    public function test_nearest_dated_point_is_found_beyond_the_first_ring_and_within_the_limit(): void
    {
        $cell = 2000.0;
        $grid = [];
        foreach ([[1000, 1000, '1862-03-15'], [9000, 1000, '1856-10-20'], [60000, 0, '1871-10-16']] as [$x, $y, $d]) {
            $grid[(int) floor($x / $cell)][(int) floor($y / $cell)][] = [$x, $y, ['date' => $d]];
        }

        $this->assertSame('1856-10-20', EraContinuity::nearest($grid, 8000, 1000, $cell, 100000)['date']);
        $this->assertSame('1871-10-16', EraContinuity::nearest($grid, 50000, 0, $cell, 100000)['date']);
        $this->assertNull(EraContinuity::nearest($grid, 30000, 0, $cell, 5000));
    }

    public function test_late_or_undated_stretch_between_two_dated_ones_takes_the_later_neighbour(): void
    {
        // Paris - Brest, Laval - Vitré : 3 km « ouverts en 1965 » entre deux portions de 1855 et 1857.
        $late = EraContinuity::sandwichedOpenings([5000, 3000, 5000], ['1855-08-14', '1965-07-01', '1857-05-01'], 20000, 20);
        // 2 km jamais datés entre deux portions datées.
        $undated = EraContinuity::sandwichedOpenings([5000, 2000, 5000], ['1855-08-14', null, '1857-05-01'], 20000, 20);
        // Écart de quelques années, portion trop longue, ou à une extrémité : rien.
        $close = EraContinuity::sandwichedOpenings([5000, 3000, 5000], ['1855-08-14', '1862-01-01', '1857-05-01'], 20000, 20);
        $long = EraContinuity::sandwichedOpenings([5000, 30000, 5000], ['1855-08-14', null, '1857-05-01'], 20000, 20);
        $end = EraContinuity::sandwichedOpenings([5000, 3000], ['1855-08-14', null], 20000, 20);

        $this->assertSame([1 => ['date' => '1857-05-01', 'from' => 2]], $late);
        $this->assertSame([1 => ['date' => '1857-05-01', 'from' => 2]], $undated);
        $this->assertSame([], $close);
        $this->assertSame([], $long);
        $this->assertSame([], $end);
    }

    public function test_whole_line_closure_still_applies_to_portions_closed_today(): void
    {
        // Hirondelles : fermeture « 2017 » sur toute la ligne, Andelot - Saint-Claude exploitée.
        $this->assertTrue(EraContinuity::wholeLineClosureContradicted(true, false));   // Andelot - Saint-Claude
        $this->assertFalse(EraContinuity::wholeLineClosureContradicted(true, true));   // Saint-Claude - Oyonnax, neutralisée
        $this->assertFalse(EraContinuity::wholeLineClosureContradicted(false, false)); // rien d'exploité : la fermeture vaut
    }

    public function test_closed_portion_without_closure_date_takes_its_neighbours_closure(): void
    {
        $neighbour = ['date' => '1994-10-17', 'source' => 'lignes-oubliees'];
        $orphans = [['date' => '1970-05-09', 'source' => 'wikidata'], ['date' => '2001-01-01', 'source' => 'wikipedia']];

        // Fermée aux voyageurs en 1970, sans date de fin du fret : déclassement voisin de 1994.
        $this->assertSame($neighbour, EraContinuity::neighbourClosure($neighbour, $orphans, '1970-05-09'));
        // Encore desservie en 1995 : la voisine (1994) ne dit rien, fermeture non localisée suivante.
        $this->assertSame('2001-01-01', EraContinuity::neighbourClosure($neighbour, $orphans, '1995-01-01')['date']);
        $this->assertNull(EraContinuity::neighbourClosure(null, $orphans, '2005-01-01'));
    }

    public function test_passenger_and_freight_ends_of_a_portion_make_a_final_closure_for_its_neighbours(): void
    {
        // 4194 Le Mans - La Chartre : voyageurs et marchandises fermés le même jour.
        $ev = fn ($type, $date, $whole = false) => ['type' => $type, 'date' => $date, 'source' => 'wikipedia-reseaux', 'whole' => $whole];

        $this->assertSame(['date' => '1947-03-01', 'source' => 'wikipedia-reseaux'], EraContinuity::pairedServiceEnd([
            $ev('opening', '1882-05-25'), $ev('closure_passenger', '1947-03-01'), $ev('closure_freight', '1947-03-01'),
        ]));
        // Fin du fret après celle des voyageurs : la plus tardive.
        $this->assertSame('1975-06-01', EraContinuity::pairedServiceEnd([
            $ev('closure_passenger', '1938-05-15'), $ev('closure_freight', '1975-06-01'),
        ])['date']);
    }

    public function test_a_single_service_end_or_a_later_reopening_is_not_a_final_closure(): void
    {
        $ev = fn ($type, $date, $whole = false) => ['type' => $type, 'date' => $date, 'source' => 'lignes-oubliees', 'whole' => $whole];

        $this->assertNull(EraContinuity::pairedServiceEnd([$ev('closure_passenger', '1938-05-15')]), 'le fret continue');
        $this->assertNull(EraContinuity::pairedServiceEnd([$ev('closure_passenger', '1938-05-15'), $ev('closure_freight', '1970-01-01', true)]),
            'une fin « toute la ligne » ne date pas la portion pour ses voisines');
        $this->assertNull(EraContinuity::pairedServiceEnd([
            $ev('closure_passenger', '1938-05-15'), $ev('closure_freight', '1970-01-01'), $ev('reopening_freight', '1975-01-01'),
        ]), 'réouverte après');
    }

    public function test_nearest_can_skip_values_that_cannot_date_the_portion(): void
    {
        // Voisine à 100 m fermée en 1959, voisine à 900 m fermée en 1994 ; la portion est
        // encore desservie en 1970 : seule la seconde peut la dater.
        $grid = [];
        foreach ([[100.0, 0.0, '1959-06-28'], [900.0, 0.0, '1994-10-17']] as [$px, $py, $date]) {
            $grid[(int) floor($px / 500)][(int) floor($py / 500)][] = [$px, $py, ['date' => $date]];
        }

        $this->assertSame('1959-06-28', EraContinuity::nearest($grid, 0, 0, 500, 5000)['date']);
        $this->assertSame('1994-10-17', EraContinuity::nearest($grid, 0, 0, 500, 5000, fn ($v) => $v['date'] > '1970-05-09')['date']);
        $this->assertNull(EraContinuity::nearest($grid, 0, 0, 500, 5000, fn ($v) => $v['date'] > '2000-01-01'));
    }
}

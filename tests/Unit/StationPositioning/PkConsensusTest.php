<?php

namespace Tests\Unit\StationPositioning;

use App\Services\StationPositioning\AnchorKind;
use App\Services\StationPositioning\PkAnchor;
use App\Services\StationPositioning\PkConsensus;
use App\Services\StationPositioning\PkFit;
use PHPUnit\Framework\TestCase;

/**
 * Calage robuste des PK d'une liste de gares (PkConsensus, PkFit) : règle « PK = distance
 * le long de la voie », points d'appui faux écartés, ambiguïtés refusées.
 */
class PkConsensusTest extends TestCase
{
    private static function station(int $pk, float $m, string $name = 'G'): PkAnchor
    {
        return new PkAnchor($pk, $m, AnchorKind::Station, $name);
    }

    private static function place(int $pk, float $m, string $name = 'L'): PkAnchor
    {
        return new PkAnchor($pk, $m, AnchorKind::Place, $name);
    }

    /**
     * Ligne de Montsecret - Vassy aux Maures (3836) : PK 0 à Montsecret - Vassy (abscisse 0),
     * Les Maures au PK 17,525 de la ligne de Vire à Romagny mais à 19,44 km le long de la voie ;
     * lieux-dits BD TOPO projetés sur la voie (la Mottette, Tinchebray, Sept Fours, le Paillot).
     *
     * @return list<PkAnchor>
     */
    private static function montsecret(): array
    {
        return [
            self::station(0, 0, 'Montsecret - Vassy'), self::station(17525, 19442, 'Les Maures'),
            self::place(2000, 2345), self::place(7400, 7486), self::place(9900, 10298), self::place(14400, 14325),
        ];
    }

    public function test_a_station_whose_pk_belongs_to_another_line_is_rejected_by_the_places(): void
    {
        $fit = PkConsensus::fit(self::montsecret());

        $this->assertNotNull($fit);
        $this->assertSame(1, $fit->direction);
        $this->assertSame(['Les Maures'], array_map(fn (PkAnchor $a) => $a->label, $fit->outliers));
        $this->assertSame(['Montsecret - Vassy'], array_map(fn (PkAnchor $a) => $a->label, $fit->stations()));
        $this->assertSame(2 + 4, $fit->weight);
    }

    public function test_halts_are_placed_at_pk_equal_distance_from_the_reliable_station(): void
    {
        $fit = PkConsensus::fit(self::montsecret());

        // Avant : interpolation linéaire Montsecret → Les Maures, pente 19 442 / 17 525 = 1,109 :
        // le Paillot (PK 14,4) à l'abscisse 15 975, à 1,5 km de son lieu-dit.
        $paillot = $fit->place(14400);
        $this->assertEqualsWithDelta(14400, $paillot->measure, 0.01);
        $this->assertFalse($paillot->bracketed);
        $this->assertSame(14400, $paillot->gapM);
        $this->assertSame(PkFit::PRECISION_STATION_M + (int) round(0.03 * 14400), $paillot->precisionM);
        $this->assertStringContainsString('Montsecret - Vassy', $paillot->how);
    }

    public function test_two_stations_that_disagree_without_any_other_evidence_are_ambiguous(): void
    {
        $this->assertNull(PkConsensus::fit([self::station(0, 0), self::station(17525, 19442)]));
    }

    public function test_two_consistent_stations_are_enough_and_interpolate_between_them(): void
    {
        // Tracé 2 % plus long que le kilométrage : l'interpolation absorbe l'écart.
        $fit = PkConsensus::fit([self::station(0, 0, 'A'), self::station(10000, 10200, 'B')]);

        $this->assertNotNull($fit);
        $mid = $fit->place(5000);
        $this->assertEqualsWithDelta(5100, $mid->measure, 0.01);
        $this->assertTrue($mid->bracketed);
        $this->assertSame(PkFit::PRECISION_STATION_M + 50, $mid->precisionM);
    }

    public function test_reversed_axis_gives_direction_minus_one(): void
    {
        // Abscisses décroissantes quand les PK croissent (axe dessiné de B vers A).
        $fit = PkConsensus::fit([self::station(0, 20000), self::station(8000, 12050), self::station(15000, 5000)]);

        $this->assertSame(-1, $fit->direction);
        $this->assertEqualsWithDelta(2000, $fit->place(18000)->measure, 0.01); // pente 1 au-delà
    }

    public function test_a_single_wrong_station_among_three_is_dropped(): void
    {
        $fit = PkConsensus::fit([self::station(0, 0, 'A'), self::station(5000, 5050, 'B'), self::station(9000, 12500, 'X'), self::station(12000, 12100, 'C')]);

        $this->assertSame(['X'], array_map(fn (PkAnchor $a) => $a->label, $fit->outliers));
        $this->assertEqualsWithDelta(5050 + 4000 * (12100 - 5050) / 7000, $fit->place(9000)->measure, 0.01);
    }

    public function test_places_alone_need_three_concordant_votes(): void
    {
        $this->assertNull(PkConsensus::fit([self::place(0, 0), self::place(4000, 4100)]));
        $fit = PkConsensus::fit([self::place(0, 0), self::place(4000, 4100), self::place(9000, 8800)]);
        $this->assertNotNull($fit);
        $p = $fit->place(6000);
        $this->assertFalse($p->bracketed);
        $this->assertSame(PkFit::PRECISION_PLACE_M + 20, $p->precisionM);
    }

    public function test_one_station_and_one_place_suffice_when_they_agree(): void
    {
        $fit = PkConsensus::fit([self::station(0, 100), self::place(3000, 3300)]);

        $this->assertNotNull($fit);
        $this->assertEqualsWithDelta(1100, $fit->place(1000)->measure, 0.01);
    }

    public function test_equal_weight_rivals_are_ambiguous(): void
    {
        // Deux gares concordantes contre deux autres, toutes aussi lourdes : aucun calage.
        $this->assertNull(PkConsensus::fit([
            self::station(0, 0), self::station(3000, 3000), self::station(6000, 9000), self::station(9000, 12000),
        ]));
    }

    public function test_no_placement_beyond_the_maximum_extrapolation(): void
    {
        $fit = PkConsensus::fit([self::station(0, 0), self::station(1000, 1000)]);

        $this->assertNotNull($fit->place(1000 + PkFit::MAX_EXTRAPOLATION_M));
        $this->assertNull($fit->place(1001 + PkFit::MAX_EXTRAPOLATION_M));
    }

    public function test_beyond_the_stations_a_place_inlier_brackets_the_far_halts(): void
    {
        // Gare d'appui à 0 seulement, lieux-dits au-delà de 15 km : interpolation entre lieux-dits.
        $fit = PkConsensus::fit([self::station(0, 0), self::place(14400, 14325), self::place(16700, 16850)]);

        $p = $fit->place(16000);
        $this->assertEqualsWithDelta(14325 + 1600 * (16850 - 14325) / 2300, $p->measure, 0.01);
        $this->assertStringContainsString('interpolé', $p->how);
    }

    public function test_an_anchor_agrees_when_within_its_tolerance(): void
    {
        $fit = PkConsensus::fit(self::montsecret());

        $this->assertTrue($fit->agrees(self::place(9900, 10298)));
        $this->assertFalse($fit->agrees(self::place(9900, 11500)));
        $this->assertFalse($fit->agrees(self::station(17525, 19442)));
    }
}

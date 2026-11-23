<?php

namespace Tests\Unit;

use App\Services\TraceConflation;
use PHPUnit\Framework\TestCase;

/**
 * Priorité d'un morceau fusionné précis dans l'appariement (TraceConflation::emissionPrio, décision de Baptiste du
 * 23/11/2026) : devant une voie que la BD TOPO ne confirme pas (OSM abandonnée, voie de service, photo), FUSED_LEAD_PRIO ;
 * à côté d'une voie BD TOPO ou d'une contribution (à FUSED_CONFIRM_M), et au-delà des bouts du morceau, la priorité de
 * CANDIDATES (après la BD TOPO et OSM).
 */
class TraceConflationFusedLeadTest extends TestCase
{
    /** Arêtes : 1 fusionnée, 2 OSM, 3 BD TOPO principale, 4 BD TOPO de service, 5 contribution. */
    private const EDGES = [
        1 => ['family' => 'fusion', 'cls' => 'fused', 'prio' => 0.5, 'len' => 500.0],
        2 => ['family' => 'osm', 'cls' => 'main', 'prio' => 0.3, 'len' => 500.0],
        3 => ['family' => 'bdtopo', 'cls' => 'main', 'prio' => 0.0, 'len' => 500.0],
        4 => ['family' => 'bdtopo', 'cls' => 'service', 'prio' => 1.0, 'len' => 500.0],
        5 => ['family' => 'contrib', 'cls' => 'main', 'prio' => -0.3, 'len' => 500.0],
    ];

    /** Candidat dont la projection (fin d'observation) est en ($x, 0), à la fraction $fm de son arête. */
    private function cand(float $x, float $fm = 0.5): array
    {
        return ['d' => 2.0, 'theta' => 0.0, 'fm' => $fm, 'fs' => $fm, 'fe' => $fm, 'qx' => $x, 'qy' => 0.0, 'px' => $x, 'py' => 0.0];
    }

    private function prio(array $cands): float
    {
        return TraceConflation::emissionPrio(1, $cands[1], $cands, self::EDGES);
    }

    public function test_fused_piece_leads_an_unconfirmed_osm_track(): void
    {
        $this->assertSame(TraceConflation::FUSED_LEAD_PRIO, $this->prio([1 => $this->cand(0.0), 2 => $this->cand(1.0)]));
        // Une voie de service BD TOPO ne confirme pas : elle n'est pas la voie de la ligne.
        $this->assertSame(TraceConflation::FUSED_LEAD_PRIO, $this->prio([1 => $this->cand(0.0), 4 => $this->cand(0.5)]));
        // BD TOPO à plus de FUSED_CONFIRM_M : la voie OSM reste non confirmée.
        $this->assertSame(TraceConflation::FUSED_LEAD_PRIO, $this->prio([1 => $this->cand(0.0), 2 => $this->cand(0.5), 3 => $this->cand(3.2)]));
    }

    public function test_bd_topo_or_a_contribution_within_three_metres_keeps_the_ordinary_rank(): void
    {
        $this->assertSame(0.5, $this->prio([1 => $this->cand(0.0), 3 => $this->cand(2.9)]));
        $this->assertSame(0.5, $this->prio([1 => $this->cand(0.0), 5 => $this->cand(-1.0)]));
    }

    public function test_no_lead_beyond_the_ends_of_the_fused_piece(): void
    {
        $this->assertSame(0.5, $this->prio([1 => $this->cand(0.0, 1.0), 2 => $this->cand(1.0)]));
        $this->assertSame(0.5, $this->prio([1 => $this->cand(0.0, 0.0), 2 => $this->cand(1.0)]));
    }

    public function test_other_edges_keep_their_rank(): void
    {
        $cands = [2 => $this->cand(0.0), 3 => $this->cand(10.0)];
        $this->assertSame(0.3, TraceConflation::emissionPrio(2, $cands[2], $cands, self::EDGES));
        $this->assertSame(0.0, TraceConflation::emissionPrio(3, $cands[3], $cands, self::EDGES));
    }
}

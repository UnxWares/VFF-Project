<?php

namespace Tests\Unit;

use App\Services\NetworkGraph\StationGraph;
use App\Services\PathfindingService;
use PHPUnit\Framework\TestCase;

/**
 * Graphe par décennie : couche gare-à-gare (StationGraph) et itinéraires
 * (PathfindingService, fonctions pures).
 */
class NetworkGraphTest extends TestCase
{
    private function piece(int $id, int $from, int $to, int $len, int $line, array $flags = []): array
    {
        return ['id' => $id, 'from' => $from, 'to' => $to, 'length_m' => $len, 'line_id' => $line] + $flags;
    }

    /** @return array<string, array<string, mixed>> arêtes indexées « from-to » (ids de gares) */
    private function byPair(array $edges): array
    {
        $out = [];
        foreach ($edges as $e) {
            $out[$e['from_station_id'].'-'.$e['to_station_id']] = $e;
        }

        return $out;
    }

    /**
     *   A(1) ──10── D(2, fermée) ──5── J(3) ──20── B(4)        ligne 100
     *                                  └──30── C(5)            ligne 200
     */
    public function test_station_edges_stop_at_open_stations_and_skip_closed_ones(): void
    {
        $graph = new StationGraph([
            $this->piece(1, 1, 2, 10_000, 100),
            $this->piece(2, 2, 3, 5_000, 100),
            $this->piece(3, 3, 4, 20_000, 100),
            $this->piece(4, 3, 5, 30_000, 200),
        ], [1 => 11, 4 => 14, 5 => 15], [2 => true]);

        $edges = $this->byPair($graph->stationEdges());

        $this->assertSame(['11-14', '11-15', '14-15'], array_keys($edges));
        $this->assertSame(35_000, $edges['11-14']['length_m']);
        $this->assertSame(1, $edges['11-14']['skipped_count'], 'gare fermée D traversée');
        $this->assertSame([100], $edges['11-14']['line_ids']);
        $this->assertSame(45_000, $edges['11-15']['length_m']);
        $this->assertSame([100, 200], $edges['11-15']['line_ids'], 'lignes dans le sens from → to');
        $this->assertSame([15_000, 30_000], $edges['11-15']['line_lengths']);
        $this->assertSame(200, $edges['11-15']['line_id'], 'ligne dominante = la plus longue');
        $this->assertSame([1, 2, 4], $edges['11-15']['piece_ids']);
        $this->assertSame(0, $edges['14-15']['skipped_count']);
    }

    public function test_passenger_track_is_preferred_over_shorter_freight_only_track(): void
    {
        // Deux voies entre A et B : 10 km fret seul, 15 km voyageurs.
        $pieces = [
            $this->piece(1, 1, 2, 10_000, 100, ['non_passenger' => true]),
            $this->piece(2, 1, 3, 7_000, 200),
            $this->piece(3, 3, 2, 8_000, 200),
        ];

        $passenger = $this->byPair((new StationGraph($pieces, [1 => 11, 2 => 12], [], 4.0))->stationEdges());
        $this->assertSame(15_000, $passenger['11-12']['length_m']);
        $this->assertSame(0, $passenger['11-12']['non_passenger_m']);

        $any = $this->byPair((new StationGraph($pieces, [1 => 11, 2 => 12], [], 1.0))->stationEdges());
        $this->assertSame(10_000, $any['11-12']['length_m']);
        $this->assertSame(10_000, $any['11-12']['non_passenger_m']);
    }

    public function test_heritage_edges_are_added_only_when_needed_and_flagged(): void
    {
        // A-B commercial ; B-C seulement par une ligne touristique ; A-B aussi par une voie touristique plus courte.
        $graph = new StationGraph([
            $this->piece(1, 1, 2, 20_000, 100),
            $this->piece(2, 1, 2, 12_000, 300, ['heritage' => true]),
            $this->piece(3, 2, 3, 9_000, 300, ['heritage' => true]),
        ], [1 => 11, 2 => 12, 3 => 13]);

        $edges = $this->byPair($graph->stationEdges());

        $this->assertSame(20_000, $edges['11-12']['length_m'], 'la liaison commerciale existante est gardée');
        $this->assertSame(0, $edges['11-12']['heritage_m']);
        $this->assertSame(9_000, $edges['12-13']['heritage_m']);
        $this->assertCount(2, $edges);
    }

    public function test_gap_connectors_are_counted_and_not_listed_as_lines(): void
    {
        $graph = new StationGraph([
            $this->piece(1, 1, 2, 5_000, 100),
            $this->piece(2, 2, 3, 40, 0, ['connector' => true]),
            $this->piece(3, 3, 4, 6_000, 200, ['gauge_mm' => 1000]),
        ], [1 => 11, 4 => 14]);

        $edge = $graph->stationEdges()[0];

        $this->assertSame(1, $edge['gap_joins']);
        $this->assertSame([100, 200], $edge['line_ids']);
        $this->assertSame([1000], $edge['gauges']);
    }

    public function test_components_count_isolated_stations(): void
    {
        $edges = [
            ['from_station_id' => 1, 'to_station_id' => 2],
            ['from_station_id' => 2, 'to_station_id' => 3],
            ['from_station_id' => 7, 'to_station_id' => 8],
        ];

        $this->assertSame(
            ['components' => 3, 'largest' => 3, 'isolated' => 1],
            StationGraph::components($edges, [1, 2, 3, 7, 8, 9]),
        );
    }

    public function test_requested_year_maps_to_the_decade_snapshot(): void
    {
        $years = [1830, 1840, 1900, 1930, 1940, 2020, 2030];

        $this->assertSame(1930, PathfindingService::eraYearFor($years, 1930, 2030, 2026));
        $this->assertSame(1930, PathfindingService::eraYearFor($years, 1937, 2030, 2026), 'état au 31/12/1930');
        $this->assertSame(2020, PathfindingService::eraYearFor($years, 2024, 2030, 2026));
        $this->assertSame(2030, PathfindingService::eraYearFor($years, 2026, 2030, 2026), 'année en cours = réseau actuel');
        $this->assertSame(1840, PathfindingService::eraYearFor($years, 1899, 2030, 2026));
        $this->assertNull(PathfindingService::eraYearFor($years, 1820, 2030, 2026));
    }

    public function test_dijkstra_from_several_departure_stations(): void
    {
        // Deux gares de départ (1, 2), arrivée 5 : le meilleur départ est retenu.
        $adj = [];
        foreach ([[1, 3, 50.0, 10], [2, 4, 5.0, 11], [3, 5, 10.0, 12], [4, 5, 20.0, 13]] as [$a, $b, $w, $e]) {
            $adj[$a][] = [$b, $w, $e];
            $adj[$b][] = [$a, $w, $e];
        }

        $path = PathfindingService::dijkstra($adj, [1, 2], [5]);

        $this->assertSame([2, 4, 5], $path['stations']);
        $this->assertSame([11, 13], $path['edges']);
        $this->assertSame(25.0, $path['cost']);
        $this->assertNull(PathfindingService::dijkstra($adj, [1], [99]));
    }

    public function test_legs_merge_short_pieces_and_consecutive_lines(): void
    {
        $legs = PathfindingService::legs([
            [[100, 30_000]],                        // gare 0 → 1
            [[100, 5_000], [900, 300], [200, 40_000]], // gare 1 → 2 : raccordement de 300 m
            [[200, 12_000]],                        // gare 2 → 3
        ]);

        $this->assertSame([
            ['line_id' => 100, 'length_m' => 35_300, 'from_index' => 0, 'to_index' => null],
            ['line_id' => 200, 'length_m' => 52_000, 'from_index' => null, 'to_index' => 3],
        ], $legs);
    }

    public function test_gauge_changes_are_reported_at_the_shared_station(): void
    {
        $this->assertSame(
            [['index' => 2, 'from_mm' => 1435, 'to_mm' => 1000]],
            PathfindingService::gaugeChanges([[1435], [1435], [1000], [], [1000, 1435]]),
        );
    }
}

<?php

namespace Tests\Unit;

use App\Services\ConsistencyChecker;
use App\Services\NetworkGraph\EraGraphBuilder;
use App\Services\NetworkGraph\RouteException;
use App\Services\NetworkGraph\StationGraph;
use App\Services\PathfindingService;
use PHPUnit\Framework\TestCase;

/**
 * Itinéraires historiques, fonctions pures : couche gare-à-gare
 * (StationGraph), Dijkstra multi-départs, découpage en lignes empruntées,
 * changements d'écartement et choix de l'ère. Complète NetworkGraphTest.
 */
class RouteAlgorithmsTest extends TestCase
{
    private function piece(int $id, int $from, int $to, int $len, int $line, array $flags = []): array
    {
        return ['id' => $id, 'from' => $from, 'to' => $to, 'length_m' => $len, 'line_id' => $line] + $flags;
    }

    /** @return array<string, array<string, mixed>> */
    private function byPair(array $edges): array
    {
        $out = [];
        foreach ($edges as $e) {
            $out[$e['from_station_id'].'-'.$e['to_station_id']] = $e;
        }
        ksort($out);

        return $out;
    }

    // ─── StationGraph ────────────────────────────────────────────────────

    public function test_search_stops_at_the_first_open_station(): void
    {
        // A(1) ── B(2) ── C(3), toutes ouvertes : A-B et B-C, jamais A-C.
        $graph = new StationGraph([
            $this->piece(1, 1, 2, 4_000, 100),
            $this->piece(2, 2, 3, 6_000, 100),
        ], [1 => 11, 2 => 12, 3 => 13]);

        $this->assertSame(['11-12', '12-13'], array_keys($this->byPair($graph->stationEdges())));
    }

    public function test_degenerate_pieces_are_ignored(): void
    {
        $graph = new StationGraph([
            $this->piece(1, 1, 1, 500, 100),           // boucle sur un seul nœud
            $this->piece(2, 1, 2, -300, 100),          // longueur négative ramenée à 0
        ], [1 => 11, 2 => 12]);

        $edges = $graph->stationEdges();

        $this->assertCount(1, $edges);
        $this->assertSame(0, $edges[0]['length_m']);
        $this->assertSame([2], $edges[0]['piece_ids']);
    }

    public function test_station_without_track_has_no_edge(): void
    {
        $graph = new StationGraph([$this->piece(1, 1, 2, 4_000, 100)], [1 => 11, 2 => 12, 9 => 19]);

        $this->assertSame(['11-12'], array_keys($this->byPair($graph->stationEdges())));
        $this->assertSame([], (new StationGraph([], [1 => 11]))->stationEdges());
    }

    public function test_shortest_of_two_parallel_tracks_is_kept(): void
    {
        $graph = new StationGraph([
            $this->piece(1, 1, 2, 9_000, 100),
            $this->piece(2, 1, 2, 7_000, 200),
        ], [1 => 11, 2 => 12]);

        $edge = $graph->stationEdges()[0];

        $this->assertSame(7_000, $edge['length_m']);
        $this->assertSame(200, $edge['line_id']);
    }

    public function test_edge_is_described_from_the_smaller_station_id(): void
    {
        // Nœuds 1 → 2 → 3 ; la gare d'id le plus petit est au nœud 3 : pièces dans le sens 3 → 1.
        $graph = new StationGraph([
            $this->piece(1, 1, 2, 2_000, 100),
            $this->piece(2, 2, 3, 3_000, 200),
        ], [1 => 20, 3 => 10]);

        $edge = $graph->stationEdges()[0];

        $this->assertSame(10, $edge['from_station_id']);
        $this->assertSame(20, $edge['to_station_id']);
        $this->assertSame([2, 1], $edge['piece_ids']);
        $this->assertSame([200, 100], $edge['line_ids']);
        $this->assertSame([3_000, 2_000], $edge['line_lengths']);
    }

    public function test_closed_stations_crossed_are_all_counted(): void
    {
        $graph = new StationGraph([
            $this->piece(1, 1, 2, 1_000, 100),
            $this->piece(2, 2, 3, 1_000, 100),
            $this->piece(3, 3, 4, 1_000, 100),
        ], [1 => 11, 4 => 14], [2 => true, 3 => true]);

        $this->assertSame(2, $graph->stationEdges()[0]['skipped_count']);
    }

    public function test_heritage_only_network_is_reached_in_the_second_pass(): void
    {
        $graph = new StationGraph([
            $this->piece(1, 1, 2, 8_000, 300, ['heritage' => true]),
        ], [1 => 11, 2 => 12]);

        $edges = $graph->stationEdges();

        $this->assertCount(1, $edges);
        $this->assertSame(8_000, $edges[0]['heritage_m']);
    }

    public function test_freight_only_metres_are_reported_along_a_mixed_path(): void
    {
        $graph = new StationGraph([
            $this->piece(1, 1, 2, 3_000, 100),
            $this->piece(2, 2, 3, 2_000, 500, ['non_passenger' => true, 'gauge_mm' => 1435]),
            $this->piece(3, 3, 4, 1_000, 100, ['gauge_mm' => 1435]),
        ], [1 => 11, 4 => 14]);

        $edge = $graph->stationEdges()[0];

        $this->assertSame(6_000, $edge['length_m']);
        $this->assertSame(2_000, $edge['non_passenger_m']);
        $this->assertSame([100, 500, 100], $edge['line_ids'], 'ligne reprise après le raccord : nouvelle suite');
        $this->assertSame(100, $edge['line_id'], 'dominante : 4 km sur la ligne 100');
        $this->assertSame([1435], $edge['gauges']);
    }

    public function test_components_of_an_empty_graph(): void
    {
        $this->assertSame(['components' => 0, 'largest' => 0, 'isolated' => 0], StationGraph::components([], []));
        // Gares des arêtes absentes de la liste : comptées quand même.
        $this->assertSame(['components' => 1, 'largest' => 2, 'isolated' => 0],
            StationGraph::components([['from_station_id' => 5, 'to_station_id' => 6]], []));
    }

    // ─── Dijkstra ────────────────────────────────────────────────────────

    public function test_departure_that_is_also_an_arrival_costs_nothing(): void
    {
        $this->assertSame(['cost' => 0.0, 'stations' => [1], 'edges' => []], PathfindingService::dijkstra([1 => [[2, 5.0, 10]]], [1], [1]));
    }

    public function test_no_departure_gives_no_path(): void
    {
        $this->assertNull(PathfindingService::dijkstra([1 => [[2, 5.0, 10]]], [], [2]));
    }

    public function test_adjacency_is_followed_in_its_own_direction(): void
    {
        $adj = [1 => [[2, 5.0, 10]]];

        $this->assertSame([1, 2], PathfindingService::dijkstra($adj, [1], [2])['stations']);
        $this->assertNull(PathfindingService::dijkstra($adj, [2], [1]));
    }

    public function test_nearest_of_several_arrival_stations_is_reached(): void
    {
        // Paris (1) vers « Lyon » = deux gares (5, 6) : la plus proche par le coût.
        $adj = [1 => [[2, 10.0, 100], [3, 1.0, 101]], 2 => [[5, 1.0, 102]], 3 => [[6, 50.0, 103]]];

        $path = PathfindingService::dijkstra($adj, [1], [5, 6]);

        $this->assertSame([1, 2, 5], $path['stations']);
        $this->assertSame(11.0, $path['cost']);
    }

    public function test_longer_path_with_fewer_hops_loses_to_cheaper_path(): void
    {
        $adj = [];
        foreach ([[1, 4, 100.0, 1], [1, 2, 10.0, 2], [2, 3, 10.0, 3], [3, 4, 10.0, 4]] as [$a, $b, $w, $e]) {
            $adj[$a][] = [$b, $w, $e];
            $adj[$b][] = [$a, $w, $e];
        }

        $path = PathfindingService::dijkstra($adj, [1], [4]);

        $this->assertSame([1, 2, 3, 4], $path['stations']);
        $this->assertSame([2, 3, 4], $path['edges']);
        $this->assertSame(30.0, $path['cost']);
    }

    // ─── legs ────────────────────────────────────────────────────────────

    public function test_no_step_gives_no_leg(): void
    {
        $this->assertSame([], PathfindingService::legs([]));
    }

    public function test_short_first_piece_is_merged_into_the_next_line(): void
    {
        $this->assertSame(
            [['line_id' => 100, 'length_m' => 30_300, 'from_index' => 0, 'to_index' => 1]],
            PathfindingService::legs([[[900, 300], [100, 30_000]]]),
        );
    }

    public function test_route_made_only_of_short_pieces_is_one_leg(): void
    {
        $this->assertSame(
            [['line_id' => 1, 'length_m' => 500, 'from_index' => 0, 'to_index' => 2]],
            PathfindingService::legs([[[1, 200]], [[2, 300]]]),
        );
    }

    public function test_same_line_taken_again_later_is_a_new_leg(): void
    {
        $legs = PathfindingService::legs([[[100, 10_000]], [[200, 10_000]], [[100, 10_000]]]);

        $this->assertSame([100, 200, 100], array_column($legs, 'line_id'));
        $this->assertSame([[0, 1], [1, 2], [2, 3]], array_map(fn ($l) => [$l['from_index'], $l['to_index']], $legs));
    }

    public function test_same_line_over_several_steps_is_one_leg(): void
    {
        $this->assertSame(
            [['line_id' => 100, 'length_m' => 25_000, 'from_index' => 0, 'to_index' => 3]],
            PathfindingService::legs([[[100, 10_000]], [[100, 10_000]], [[100, 5_000]]]),
        );
    }

    public function test_short_pieces_are_kept_when_the_threshold_is_zero(): void
    {
        $legs = PathfindingService::legs([[[100, 5_000], [900, 300], [200, 4_000]]], minLegM: 0);

        $this->assertSame([100, 900, 200], array_column($legs, 'line_id'));
    }

    public function test_total_length_is_preserved_by_merging(): void
    {
        $steps = [[[1, 700], [2, 12_000]], [[2, 400], [3, 900], [4, 30_000]], [[4, 50]]];

        $legs = PathfindingService::legs($steps);

        $this->assertSame(44_050, array_sum(array_column($legs, 'length_m')));
        $this->assertSame(0, $legs[0]['from_index']);
        $this->assertSame(3, end($legs)['to_index']);
    }

    // ─── écartement et ère ──────────────────────────────────────────────

    public function test_gauge_change_needs_a_single_known_gauge_on_both_sides(): void
    {
        $this->assertSame([], PathfindingService::gaugeChanges([]));
        $this->assertSame([], PathfindingService::gaugeChanges([[1435]]));
        $this->assertSame([['index' => 1, 'from_mm' => 1000, 'to_mm' => 1435]], PathfindingService::gaugeChanges([[1000], [1435]]));
        // Écartement inconnu entre les deux : rien de signalé.
        $this->assertSame([], PathfindingService::gaugeChanges([[1000], [], [1435]]));
        // Voie à double écartement (1000 + 1435) : pas de rupture certaine.
        $this->assertSame([], PathfindingService::gaugeChanges([[1000], [1000, 1435], [1435]]));
    }

    public function test_year_between_today_and_the_current_era_is_the_current_network(): void
    {
        $years = [1900, 1910, 2020, 2030];

        $this->assertSame(2030, PathfindingService::eraYearFor($years, 2029, 2030, 2026));
        $this->assertSame(2030, PathfindingService::eraYearFor($years, 2030, 2030, 2026));
        $this->assertSame(2020, PathfindingService::eraYearFor($years, 2025, 2030, 2026));
        $this->assertSame(1910, PathfindingService::eraYearFor($years, 1919, 2030, 2026));
        $this->assertNull(PathfindingService::eraYearFor([], 1950, 2030, 2026));
        $this->assertSame(2030, PathfindingService::eraYearFor([], 2050, 2030, 2026));
    }

    // ─── paramètres du graphe ───────────────────────────────────────────

    public function test_graph_parameters_fall_back_to_defaults(): void
    {
        $params = fn (EraGraphBuilder $b) => (new \ReflectionProperty($b, 'params'))->getValue($b);

        $this->assertSame(EraGraphBuilder::DEFAULTS, $params(new EraGraphBuilder));
        $custom = $params(new EraGraphBuilder(['snap_m' => '20', 'gap_any_m' => '', 'attach_near_m' => null, 'inconnu' => 5]));
        $this->assertSame(20.0, $custom['snap_m'], 'option CLI (chaîne) convertie');
        $this->assertSame(EraGraphBuilder::DEFAULTS['gap_any_m'], $custom['gap_any_m'], 'option vide : défaut');
        $this->assertSame(EraGraphBuilder::DEFAULTS['attach_near_m'], $custom['attach_near_m']);
        $this->assertArrayNotHasKey('inconnu', $custom);
    }

    public function test_graph_and_router_share_their_definitions(): void
    {
        // Même pénalité fret seul pour la construction du graphe et le calcul d'itinéraire.
        $this->assertSame(PathfindingService::NON_PASSENGER_FACTOR, EraGraphBuilder::DEFAULTS['non_passenger_factor']);
        // Mêmes gares « ouvertes » que les contrôles de cohérence.
        $open = (new \ReflectionClass(EraGraphBuilder::class))->getConstant('OPEN_STATUSES');
        $this->assertSame("('".implode("', '", ConsistencyChecker::OPEN_STATIONS)."')", $open);
    }

    public function test_route_error_carries_a_stable_code_and_status(): void
    {
        $e = new RouteException('no_path', 404, 'Aucun itinéraire.', ['era_year' => 1930]);

        $this->assertSame('no_path', $e->errorCode);
        $this->assertSame(404, $e->status);
        $this->assertSame('Aucun itinéraire.', $e->getMessage());
        $this->assertSame(['era_year' => 1930], $e->details);
    }
}

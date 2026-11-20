<?php

namespace App\Services\Schematic;

use App\Services\NetworkGraph\ConnectedComponents;

/**
 * Plan schématique d'une ère (« vue ferroviaire » de /maps) : le réseau en service dessiné comme un
 * plan de transport, axes redressés à 0, 45 ou 90 degrés, nœuds sur une grille, gares en traits.
 * Calcul pur (aucune lecture en base), fait côté serveur et gardé en cache par ère (SchematicMap).
 *
 * Étapes :
 *   1. Axes : les chaînes de voies entre deux nœuds du graphe qui ne sont pas de simple passage
 *      (jonction, extrémité) ; une antenne sans gare de moins de `spurM` est retirée (bout de voie,
 *      faisceau, tiroir).
 *   2. Nœuds du plan : les extrémités d'axes très proches (moins de `mergeCells` pas) sont fondues,
 *      ainsi que celles qui tombent sur le même point de la grille et sont reliées par un axe court :
 *      un complexe de bifurcations devient un seul nœud. Les gares de ces axes intérieurs restent au
 *      nœud.
 *   3. Axes doubles : deux axes entre les mêmes nœuds et de longueur voisine (deux lignes tracées
 *      côte à côte sur la même voie) n'en font qu'un ; la catégorie la plus active est dessinée.
 *   4. Un nœud où ne se rejoignent que deux axes n'est plus un nœud : les deux axes sont mis bout à
 *      bout (moins de coudes) ; une boucle revenant à son nœud est coupée en son point le plus éloigné.
 *   5. Chaque nœud prend le point de grille le plus proche ; s'il est pris, un point libre à moins de
 *      `placeRadius` pas (les nœuds les plus importants d'abord).
 *   6. Routage des axes, les plus importants d'abord (LGV, réseau mixte, puis les plus longs) :
 *      OctilinearRouter.
 *   7. Gares intermédiaires réparties le long de l'axe, entre leur position réelle et un espacement
 *      régulier (`evenSpacing`), dans leur ordre ; les changements de ligne et de catégorie suivent.
 *
 * Aucune coordonnée du plan n'est une position réelle : les nœuds sont arrondis à la grille (un pas
 * de 6 km Web Mercator par défaut, environ 4 km sur le terrain en France) et parfois déplacés, les
 * gares intermédiaires sont posées sur des segments redressés. Le tracé réel des voies ne sert qu'à
 * guider le routage, il n'est jamais recopié.
 */
final class SchematicLayout
{
    public function __construct(private readonly SchematicOptions $options = new SchematicOptions) {}

    public function layout(SchematicGraph $graph): SchematicPlan
    {
        $started = microtime(true);
        $cell = $this->options->cellM;
        $edges = array_values(array_filter(
            $graph->edges,
            fn (array $e): bool => $e['u'] !== $e['v'] && isset($graph->nodes[$e['u']], $graph->nodes[$e['v']]),
        ));

        $graph = $this->magnify($graph);
        $alive = $this->pruneSpurs($graph, $edges);
        $chains = $this->chains($edges, $alive);
        [$placeOf, $placePos] = $this->places($graph, $chains);

        [$corridors, $placeStations] = $this->placeCorridors($graph, $edges, $chains, $placeOf);
        [$corridors, $placePos, $placeStations] = $this->splitLoops($corridors, $placePos, $placeStations);
        $corridors = $this->bundle($corridors, $graph);
        [$corridors, $placePos, $placeStations] = $this->fuse($corridors, $placePos, $placeStations, $graph);

        $degree = [];
        foreach ($corridors as $c) {
            $degree[$c['a']] = ($degree[$c['a']] ?? 0) + 1;
            $degree[$c['b']] = ($degree[$c['b']] ?? 0) + 1;
        }
        $router = new OctilinearRouter($this->options);
        $points = $this->assignPoints($degree, $placePos, $placeStations, $graph, $router);

        usort($corridors, fn (array $x, array $y): int => [$y['rank'], $y['length']] <=> [$x['rank'], $x['length']]);

        $pieces = [];
        $stations = [];
        $endAngle = [];
        foreach ($corridors as $c) {
            $from = $points[$c['a']];
            $to = $points[$c['b']];
            $guide = $this->guide($c, $from, $to);
            [$startBearing, $endBearing] = self::bearings($guide);
            $corners = $router->route($from, $to, $guide, $startBearing, $endBearing);
            $path = array_map(fn (array $p): array => [$p[0] * $cell, $p[1] * $cell], $corners);
            if (count($path) < 2) {
                continue;
            }
            $endAngle[$c['a']] ??= self::angle($path[0], $path[1]);
            $endAngle[$c['b']] ??= self::angle($path[count($path) - 1], $path[count($path) - 2]);
            [$cPieces, $cStations] = $this->draw($c, $path);
            array_push($pieces, ...$cPieces);
            array_push($stations, ...$cStations);
        }

        $placeRows = [];
        foreach ($degree as $place => $n) {
            foreach ($placeStations[$place] ?? [] as $k => $station) {
                $placeRows[] = [
                    'station' => $station,
                    'x' => $points[$place][0] * $cell,
                    'y' => $points[$place][1] * $cell,
                    'angle' => $n === 1 ? (int) round($endAngle[$place] ?? 0) : 0,
                    'kind' => $n === 1 ? 'end' : 'hub',
                    'main' => $k === 0,
                    'degree' => $n,
                ];
            }
        }
        // Une gare n'apparaît qu'une fois : au nœud si elle y est, sinon sur son axe.
        $seen = [];
        $allStations = [];
        foreach ([...$placeRows, ...$stations] as $row) {
            if (! isset($seen[$row['station']])) {
                $seen[$row['station']] = true;
                $allStations[] = $row;
            }
        }

        return new SchematicPlan($cell, $pieces, $allStations, [
            'nodes' => count($graph->nodes),
            'edges' => count($edges),
            'axes' => count($corridors),
            'places' => count($degree),
            'stations' => count($allStations),
            'fallbacks' => $router->fallbacks,
            'overlaps' => $router->overlaps(),
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
        ]);
    }

    /** Positions des nœuds passées par les loupes des grandes agglomérations (SchematicOptions::LENSES). */
    private function magnify(SchematicGraph $graph): SchematicGraph
    {
        if ($this->options->lenses === []) {
            return $graph;
        }
        $lenses = array_map(fn (array $l): array => [...Mercator::project($l[0], $l[1]), $l[2], min(3.0, $l[3])], $this->options->lenses);
        $nodes = [];
        foreach ($graph->nodes as $id => [$x, $y]) {
            $nx = $x;
            $ny = $y;
            foreach ($lenses as [$cx, $cy, $radius, $gain]) {
                $dx = $x - $cx;
                $dy = $y - $cy;
                $factor = ($gain - 1) * exp(-($dx * $dx + $dy * $dy) / ($radius * $radius));
                $nx += $dx * $factor;
                $ny += $dy * $factor;
            }
            $nodes[$id] = [$nx, $ny];
        }

        return new SchematicGraph($nodes, $graph->edges, $graph->nodeStations, $graph->stationWeights);
    }

    /**
     * Retire les antennes courtes sans gare, jusqu'à ce qu'il n'y en ait plus.
     *
     * @param  list<array{u: int, v: int, length: float, line: ?int, category: string}>  $edges
     * @return array<int, true> arêtes gardées
     */
    private function pruneSpurs(SchematicGraph $graph, array $edges): array
    {
        $alive = array_fill_keys(array_keys($edges), true);
        for ($round = 0; $round < 8; $round++) {
            $degree = [];
            foreach (array_keys($alive) as $e) {
                $degree[$edges[$e]['u']] = ($degree[$edges[$e]['u']] ?? 0) + 1;
                $degree[$edges[$e]['v']] = ($degree[$edges[$e]['v']] ?? 0) + 1;
            }
            $removed = 0;
            foreach ($this->chains($edges, $alive) as $chain) {
                if ($chain['length'] >= $this->options->spurM) {
                    continue;
                }
                $first = $chain['nodes'][0];
                $last = $chain['nodes'][count($chain['nodes']) - 1];
                $freeFirst = $degree[$first] === 1;
                $freeLast = $degree[$last] === 1;
                if (! $freeFirst && ! $freeLast) {
                    continue;
                }
                // Gares de l'antenne : intérieures et bout libre (pas la jonction où elle se branche).
                $inner = array_slice($chain['nodes'], 1, -1);
                $checked = [...$inner, ...($freeFirst ? [$first] : []), ...($freeLast ? [$last] : [])];
                if (array_any($checked, fn (int $n): bool => isset($graph->nodeStations[$n]))) {
                    continue;
                }
                foreach ($chain['edges'] as $e) {
                    unset($alive[$e]);
                }
                $removed++;
            }
            if ($removed === 0) {
                break;
            }
        }

        return $alive;
    }

    /**
     * Chaînes d'arêtes entre deux nœuds qui ne sont pas de simple passage (degré différent de 2) ;
     * un anneau sans tel nœud part d'un de ses nœuds.
     *
     * @param  list<array{u: int, v: int, length: float, line: ?int, category: string}>  $edges
     * @param  array<int, true>  $alive
     * @return list<array{nodes: list<int>, edges: list<int>, length: float}>
     */
    private function chains(array $edges, array $alive): array
    {
        $adj = [];
        foreach (array_keys($alive) as $e) {
            $adj[$edges[$e]['u']][] = $e;
            $adj[$edges[$e]['v']][] = $e;
        }
        $used = [];
        $out = [];
        $walk = function (int $start, int $edge) use (&$used, $adj, $edges): array {
            $nodes = [$start];
            $list = [];
            $length = 0.0;
            $cur = $start;
            while (true) {
                $used[$edge] = true;
                $list[] = $edge;
                $length += $edges[$edge]['length'];
                $cur = $edges[$edge]['u'] === $cur ? $edges[$edge]['v'] : $edges[$edge]['u'];
                $nodes[] = $cur;
                if ($cur === $start || count($adj[$cur]) !== 2) {
                    break;
                }
                $next = $adj[$cur][0] === $edge ? $adj[$cur][1] : $adj[$cur][0];
                if (isset($used[$next])) {
                    break;
                }
                $edge = $next;
            }

            return ['nodes' => $nodes, 'edges' => $list, 'length' => $length];
        };
        foreach ($adj as $node => $list) {
            if (count($list) === 2) {
                continue;
            }
            foreach ($list as $e) {
                if (! isset($used[$e])) {
                    $out[] = $walk($node, $e);
                }
            }
        }
        foreach (array_keys($alive) as $e) {
            if (! isset($used[$e])) {
                $out[] = $walk($edges[$e]['u'], $e);
            }
        }

        return $out;
    }

    /**
     * Nœuds du plan : extrémités de chaînes fondues (étape 2).
     *
     * @param  list<array{nodes: list<int>, edges: list<int>, length: float}>  $chains
     * @return array{0: array<int, int>, 1: array<int, array{0: float, 1: float}>} nœud du plan de
     *                                                                             chaque extrémité, position de chaque nœud du plan
     */
    private function places(SchematicGraph $graph, array $chains): array
    {
        $cell = $this->options->cellM;
        $uf = new ConnectedComponents;
        $ends = [];
        foreach ($chains as $chain) {
            foreach ([$chain['nodes'][0], $chain['nodes'][count($chain['nodes']) - 1]] as $end) {
                $uf->add($end);
                $ends[$end] = true;
            }
        }
        $ends = array_keys($ends);
        $positions = [];
        for ($round = 0; $round < 6; $round++) {
            $positions = $this->placePositions($graph, $uf);
            $changed = false;
            foreach ($chains as $chain) {
                $ra = $uf->find($chain['nodes'][0]);
                $rb = $uf->find($chain['nodes'][count($chain['nodes']) - 1]);
                if ($ra === $rb) {
                    continue;
                }
                $length = self::planLength($graph, $chain);
                $short = $length < $this->options->mergeCells * $cell;
                $samePoint = $length < $this->options->mergeSameCells * $cell
                    && self::gridPoint($positions[$ra], $cell) === self::gridPoint($positions[$rb], $cell);
                if ($short || $samePoint) {
                    $uf->link($ra, $rb);
                    $changed = true;
                }
            }
            if (! $changed) {
                break;
            }
        }
        $positions = $this->placePositions($graph, $uf);
        $placeOf = [];
        foreach ($ends as $node) {
            $placeOf[$node] = $uf->find($node);
        }

        return [$placeOf, $positions];
    }

    /**
     * Longueur d'une chaîne sur le plan (positions passées par les loupes), pour les décisions de
     * fusion : à l'échelle du plan, pas du terrain.
     *
     * @param  array{nodes: list<int>, edges: list<int>, length: float}  $chain
     */
    private static function planLength(SchematicGraph $graph, array $chain): float
    {
        $length = 0.0;
        $nodes = $chain['nodes'];
        $n = count($nodes);
        for ($k = 1; $k < $n; $k++) {
            [$ax, $ay] = $graph->nodes[$nodes[$k - 1]];
            [$bx, $by] = $graph->nodes[$nodes[$k]];
            $length += hypot($bx - $ax, $by - $ay);
        }

        return $length;
    }

    /** @return array<int, array{0: float, 1: float}> position moyenne de chaque nœud du plan */
    private function placePositions(SchematicGraph $graph, ConnectedComponents $uf): array
    {
        $positions = [];
        foreach ($uf->groups() as $root => $nodes) {
            $x = 0.0;
            $y = 0.0;
            foreach ($nodes as $node) {
                $x += $graph->nodes[$node][0];
                $y += $graph->nodes[$node][1];
            }
            $positions[$root] = [$x / count($nodes), $y / count($nodes)];
        }

        return $positions;
    }

    /**
     * Axes entre nœuds du plan, gares de chaque nœud (étape 2).
     *
     * @param  list<array{u: int, v: int, length: float, line: ?int, category: string}>  $edges
     * @param  list<array{nodes: list<int>, edges: list<int>, length: float}>  $chains
     * @param  array<int, int>  $placeOf
     * @return array{0: list<array<string, mixed>>, 1: array<int, list<int>>}
     */
    private function placeCorridors(SchematicGraph $graph, array $edges, array $chains, array $placeOf): array
    {
        $stationsAt = [];
        foreach ($placeOf as $node => $place) {
            if (isset($graph->nodeStations[$node])) {
                $stationsAt[$place][] = $graph->nodeStations[$node];
            }
        }
        $corridors = [];
        foreach ($chains as $chain) {
            $nodes = $chain['nodes'];
            $a = $placeOf[$nodes[0]];
            $b = $placeOf[$nodes[count($nodes) - 1]];
            $pts = [];
            $segs = [];
            $d = 0.0;
            foreach ($chain['edges'] as $k => $e) {
                $len = $edges[$e]['length'];
                $segs[] = ['d0' => $d, 'd1' => $d + $len, 'line' => $edges[$e]['line'], 'category' => $edges[$e]['category']];
                $d += $len;
                if ($k < count($chain['edges']) - 1) {
                    $node = $nodes[$k + 1];
                    $station = $graph->nodeStations[$node] ?? null;
                    $pts[] = ['x' => $graph->nodes[$node][0], 'y' => $graph->nodes[$node][1], 'd' => $d, 'stations' => $station === null ? [] : [$station]];
                }
            }
            if ($a === $b && self::planLength($graph, $chain) < 2 * $this->options->mergeSameCells * $this->options->cellM) {
                // Axe intérieur à un nœud (bifurcations fondues) : ses gares restent au nœud.
                $stationsAt[$a] ??= [];
                foreach ($pts as $pt) {
                    foreach ($pt['stations'] as $s) {
                        $stationsAt[$a][] = $s;
                    }
                }

                continue;
            }
            $corridors[] = ['a' => $a, 'b' => $b, 'length' => $chain['length'], 'pts' => $pts, 'segs' => $segs];
        }
        foreach ($stationsAt as $place => $list) {
            $list = array_values(array_unique($list));
            usort($list, fn (int $x, int $y): int => [$graph->stationWeights[$y] ?? 0, $x] <=> [$graph->stationWeights[$x] ?? 0, $y]);
            $stationsAt[$place] = $list;
        }

        return [$corridors, $stationsAt];
    }

    /**
     * Axes doubles fondus (étape 3).
     *
     * @param  list<array<string, mixed>>  $corridors
     * @return list<array<string, mixed>>
     */
    private function bundle(array $corridors, SchematicGraph $graph): array
    {
        $groups = [];
        foreach ($corridors as $c) {
            $groups[min($c['a'], $c['b']).':'.max($c['a'], $c['b'])][] = $c;
        }
        $out = [];
        foreach ($groups as $group) {
            usort($group, fn (array $x, array $y): int => $x['length'] <=> $y['length']);
            $bundles = [];
            foreach ($group as $c) {
                $joined = false;
                foreach ($bundles as $k => $bundle) {
                    $short = min($bundle['length'], $c['length']);
                    $long = max($bundle['length'], $c['length']);
                    if ($c['a'] !== $c['b'] && $long <= 1.35 * max($short, 1.0)) {
                        $bundles[$k] = $this->mergeParallel($bundle, $c, $graph);
                        $joined = true;
                        break;
                    }
                }
                if (! $joined) {
                    $bundles[] = $c;
                }
            }
            array_push($out, ...$bundles);
        }

        return $out;
    }

    /**
     * Deux axes parallèles : le plus actif est gardé, les gares de l'autre y sont reportées à la même
     * fraction de longueur.
     *
     * @param  array<string, mixed>  $x
     * @param  array<string, mixed>  $y
     * @return array<string, mixed>
     */
    private function mergeParallel(array $x, array $y, SchematicGraph $graph): array
    {
        [$keep, $other] = self::rank($y) > self::rank($x) ? [$y, $x] : [$x, $y];
        if ($other['a'] !== $keep['a']) {
            $other = self::reverse($other);
        }
        $known = [];
        foreach ($keep['pts'] as $pt) {
            foreach ($pt['stations'] as $s) {
                $known[$s] = true;
            }
        }
        $scale = $other['length'] > 0 ? $keep['length'] / $other['length'] : 0.0;
        foreach ($other['pts'] as $pt) {
            $new = array_values(array_filter($pt['stations'], fn (int $s): bool => ! isset($known[$s])));
            if ($new !== []) {
                $keep['pts'][] = ['x' => $pt['x'], 'y' => $pt['y'], 'd' => $pt['d'] * $scale, 'stations' => $new];
            }
        }
        usort($keep['pts'], fn (array $p, array $q): int => $p['d'] <=> $q['d']);

        return $keep;
    }

    /**
     * Nœuds de passage retirés, boucles coupées (étape 4).
     *
     * @param  list<array<string, mixed>>  $corridors
     * @param  array<int, array{0: float, 1: float}>  $placePos
     * @param  array<int, list<int>>  $placeStations
     * @return array{0: list<array<string, mixed>>, 1: array<int, array{0: float, 1: float}>, 2: array<int, list<int>>}
     */
    private function fuse(array $corridors, array $placePos, array $placeStations, SchematicGraph $graph): array
    {
        $incident = [];
        foreach ($corridors as $id => $c) {
            $incident[$c['a']][$id] = true;
            $incident[$c['b']][$id] = true;
        }
        $nextId = count($corridors);
        $queue = array_keys(array_filter($incident, fn (array $list): bool => count($list) === 2));
        while ($queue !== []) {
            $p = array_pop($queue);
            $ids = array_keys($incident[$p] ?? []);
            if (count($ids) !== 2) {
                continue;
            }
            [$c1, $c2] = [$corridors[$ids[0]], $corridors[$ids[1]]];
            if ($c1['a'] === $c1['b'] || $c2['a'] === $c2['b']) {
                continue;
            }
            $first = $c1['b'] === $p ? $c1 : self::reverse($c1);
            $second = $c2['a'] === $p ? $c2 : self::reverse($c2);
            [$px, $py] = $placePos[$p];
            $shift = $first['length'];
            $fused = [
                'a' => $first['a'],
                'b' => $second['b'],
                'length' => $first['length'] + $second['length'],
                'pts' => [
                    ...$first['pts'],
                    ['x' => $px, 'y' => $py, 'd' => $shift, 'stations' => $placeStations[$p] ?? []],
                    ...array_map(fn (array $pt): array => ['d' => $pt['d'] + $shift] + $pt, $second['pts']),
                ],
                'segs' => [
                    ...$first['segs'],
                    ...array_map(fn (array $s): array => ['d0' => $s['d0'] + $shift, 'd1' => $s['d1'] + $shift] + $s, $second['segs']),
                ],
            ];
            unset($corridors[$ids[0]], $corridors[$ids[1]], $incident[$p], $placeStations[$p]);
            foreach ([$first['a'], $second['b']] as $end) {
                unset($incident[$end][$ids[0]], $incident[$end][$ids[1]]);
            }
            $id = $nextId++;
            $corridors[$id] = $fused;
            $incident[$fused['a']][$id] = true;
            $incident[$fused['b']][$id] = true;
            foreach ([$fused['a'], $fused['b']] as $end) {
                if (count($incident[$end]) === 2) {
                    $queue[] = $end;
                }
            }
        }

        [$out, $placePos, $placeStations] = $this->splitLoops(array_values($corridors), $placePos, $placeStations);
        foreach ($out as $k => $c) {
            $out[$k]['rank'] = self::rank($c);
        }

        return [$out, $placePos, $placeStations];
    }

    /**
     * Boucles (axe qui revient à son nœud, voie double en impasse) : coupées en leur point le plus
     * éloigné du nœud, qui devient un nœud du plan.
     *
     * @param  list<array<string, mixed>>  $corridors
     * @param  array<int, array{0: float, 1: float}>  $placePos
     * @param  array<int, list<int>>  $placeStations
     * @return array{0: list<array<string, mixed>>, 1: array<int, array{0: float, 1: float}>, 2: array<int, list<int>>}
     */
    private function splitLoops(array $corridors, array $placePos, array $placeStations): array
    {
        $newPlace = max([0, ...array_keys($placePos)]) + 1;
        $out = [];
        foreach ($corridors as $c) {
            if ($c['a'] !== $c['b']) {
                $out[] = $c;

                continue;
            }
            if ($c['pts'] === []) {
                continue;
            }
            [$ox, $oy] = $placePos[$c['a']];
            $far = 0;
            foreach ($c['pts'] as $k => $pt) {
                if (hypot($pt['x'] - $ox, $pt['y'] - $oy) > hypot($c['pts'][$far]['x'] - $ox, $c['pts'][$far]['y'] - $oy)) {
                    $far = $k;
                }
            }
            $cut = $c['pts'][$far];
            $place = $newPlace++;
            $placePos[$place] = [$cut['x'], $cut['y']];
            $placeStations[$place] = $cut['stations'];
            $out[] = self::slice($c, 0.0, $cut['d'], $c['a'], $place);
            $out[] = self::slice($c, $cut['d'], $c['length'], $place, $c['a']);
        }

        return [$out, $placePos, $placeStations];
    }

    /**
     * Point de grille de chaque nœud du plan (étape 5).
     *
     * @param  array<int, int>  $degree
     * @param  array<int, array{0: float, 1: float}>  $placePos
     * @param  array<int, list<int>>  $placeStations
     * @return array<int, array{0: int, 1: int}>
     */
    private function assignPoints(array $degree, array $placePos, array $placeStations, SchematicGraph $graph, OctilinearRouter $router): array
    {
        $cell = $this->options->cellM;
        $weight = fn (int $p): float => max([0.0, ...array_map(fn (int $s): float => $graph->stationWeights[$s] ?? 0.0, $placeStations[$p] ?? [])]);
        $order = array_keys($degree);
        usort($order, fn (int $x, int $y): int => [$degree[$y], $weight($y), $x] <=> [$degree[$x], $weight($x), $y]);
        $points = [];
        foreach ($order as $place) {
            $fx = $placePos[$place][0] / $cell;
            $fy = $placePos[$place][1] / $cell;
            $ci = (int) round($fx);
            $cj = (int) round($fy);
            $chosen = [$ci, $cj];
            for ($r = 0; $r <= $this->options->placeRadius; $r++) {
                $ring = [];
                for ($di = -$r; $di <= $r; $di++) {
                    for ($dj = -$r; $dj <= $r; $dj++) {
                        if (max(abs($di), abs($dj)) === $r && $router->placeAt($ci + $di, $cj + $dj) === null) {
                            $ring[] = [$ci + $di, $cj + $dj, hypot($ci + $di - $fx, $cj + $dj - $fy)];
                        }
                    }
                }
                if ($ring !== []) {
                    usort($ring, fn (array $p, array $q): int => [$p[2], $p[0], $p[1]] <=> [$q[2], $q[0], $q[1]]);
                    $chosen = [$ring[0][0], $ring[0][1]];
                    break;
                }
            }
            $points[$place] = $chosen;
            $router->occupy($chosen[0], $chosen[1], $place);
        }

        return $points;
    }

    /**
     * Tracé réel simplifié d'un axe, en unités de grille, accroché aux points de ses nœuds.
     *
     * @param  array<string, mixed>  $c
     * @param  array{0: int, 1: int}  $from
     * @param  array{0: int, 1: int}  $to
     * @return list<array{0: float, 1: float}>
     */
    private function guide(array $c, array $from, array $to): array
    {
        $cell = $this->options->cellM;
        $line = [[(float) $from[0], (float) $from[1]]];
        foreach ($c['pts'] as $pt) {
            $line[] = [$pt['x'] / $cell, $pt['y'] / $cell];
        }
        $line[] = [(float) $to[0], (float) $to[1]];

        return self::simplify($line, $this->options->guideTolerance);
    }

    /**
     * Directions du tracé réel au départ et à l'arrivée (sur un pas et demi au plus).
     *
     * @param  list<array{0: float, 1: float}>  $guide
     * @return array{0: float, 1: float}
     */
    private static function bearings(array $guide): array
    {
        $total = 0.0;
        $n = count($guide);
        for ($k = 1; $k < $n; $k++) {
            $total += hypot($guide[$k][0] - $guide[$k - 1][0], $guide[$k][1] - $guide[$k - 1][1]);
        }
        $reach = min(1.5, $total / 2);
        $start = self::pointAlong($guide, $reach);
        $end = self::pointAlong($guide, $total - $reach);

        return [self::angle($guide[0], $start), self::angle($end, $guide[$n - 1])];
    }

    /**
     * @param  list<array{0: float, 1: float}>  $line
     * @return array{0: float, 1: float}
     */
    private static function pointAlong(array $line, float $at): array
    {
        $n = count($line);
        for ($k = 1; $k < $n; $k++) {
            $len = hypot($line[$k][0] - $line[$k - 1][0], $line[$k][1] - $line[$k - 1][1]);
            if ($at <= $len && $len > 0) {
                $t = $at / $len;

                return [$line[$k - 1][0] + $t * ($line[$k][0] - $line[$k - 1][0]), $line[$k - 1][1] + $t * ($line[$k][1] - $line[$k - 1][1])];
            }
            $at -= $len;
        }

        return $line[$n - 1];
    }

    /**
     * Morceaux et gares d'un axe routé (étape 7).
     *
     * @param  array<string, mixed>  $c
     * @param  list<array{0: float, 1: float}>  $path  sommets du chemin, en mètres
     * @return array{0: list<array{line: ?int, category: string, coords: list<array{0: float, 1: float}>}>, 1: list<array{station: int, x: float, y: float, angle: int, kind: string, main: bool, degree: int}>}
     */
    private function draw(array $c, array $path): array
    {
        $length = max($c['length'], 1e-9);
        $w = $this->options->evenSpacing;
        $stops = array_values(array_filter($c['pts'], fn (array $pt): bool => $pt['stations'] !== []));
        $n = count($stops);
        $anchors = [[0.0, 0.0]];
        foreach ($stops as $k => $pt) {
            $t = $w * (($k + 1) / ($n + 1)) + (1 - $w) * ($pt['d'] / $length);
            $anchors[] = [$pt['d'], max($t, $anchors[count($anchors) - 1][1])];
        }
        $anchors[] = [$length, 1.0];
        $f = function (float $d) use ($anchors): float {
            $count = count($anchors);
            for ($k = 1; $k < $count; $k++) {
                [$d1, $t1] = $anchors[$k];
                if ($d <= $d1 || $k === $count - 1) {
                    [$d0, $t0] = $anchors[$k - 1];

                    return $d1 > $d0 ? $t0 + ($t1 - $t0) * max(0.0, min(1.0, ($d - $d0) / ($d1 - $d0))) : $t1;
                }
            }

            return 1.0;
        };

        // Morceaux : runs de même ligne et même catégorie ; un raccord prend la ligne de son voisin.
        $runs = [];
        foreach ($c['segs'] as $s) {
            if ($s['category'] === 'connector') {
                if ($runs !== []) {
                    $runs[count($runs) - 1]['d1'] = $s['d1'];
                } else {
                    $runs[] = $s;
                }

                continue;
            }
            $last = count($runs) - 1;
            if ($last >= 0 && $runs[$last]['category'] === 'connector') {
                $runs[$last] = ['d0' => $runs[$last]['d0']] + $s;

                continue;
            }
            if ($last >= 0 && $runs[$last]['line'] === $s['line'] && $runs[$last]['category'] === $s['category']) {
                $runs[$last]['d1'] = $s['d1'];
            } else {
                $runs[] = $s;
            }
        }
        $pieces = [];
        foreach ($runs as $run) {
            $coords = self::cut($path, $f($run['d0']), $f($run['d1']));
            if (count($coords) >= 2) {
                $category = $run['category'] === 'connector' ? 'unknown' : $run['category'];
                $pieces[] = ['line' => $run['line'], 'category' => $category, 'coords' => $coords];
            }
        }

        $stations = [];
        foreach ($stops as $pt) {
            [$x, $y, $angle] = self::at($path, $f($pt['d']));
            foreach ($pt['stations'] as $k => $station) {
                $stations[] = ['station' => $station, 'x' => $x, 'y' => $y, 'angle' => $angle, 'kind' => 'stop', 'main' => $k === 0, 'degree' => 0];
            }
        }

        return [$pieces, $stations];
    }

    /**
     * Point d'un chemin à la fraction `$t` de sa longueur, avec la direction du chemin (degrés ; à un
     * coude, la bissectrice des deux directions).
     *
     * @param  list<array{0: float, 1: float}>  $path
     * @return array{0: float, 1: float, 2: int}
     */
    private static function at(array $path, float $t): array
    {
        $lengths = self::lengths($path);
        $total = $lengths[count($lengths) - 1];
        $at = $t * $total;
        $n = count($path);
        for ($k = 1; $k < $n; $k++) {
            if ($at <= $lengths[$k] || $k === $n - 1) {
                $len = $lengths[$k] - $lengths[$k - 1];
                $u = $len > 0 ? max(0.0, min(1.0, ($at - $lengths[$k - 1]) / $len)) : 0.0;
                $x = $path[$k - 1][0] + $u * ($path[$k][0] - $path[$k - 1][0]);
                $y = $path[$k - 1][1] + $u * ($path[$k][1] - $path[$k - 1][1]);
                $angle = self::angle($path[$k - 1], $path[$k]);
                $near = 0.02 * $total;
                if ($k < $n - 1 && $near > $lengths[$k] - $at) {
                    $angle = self::bisector($angle, self::angle($path[$k], $path[$k + 1]));
                } elseif ($k > 1 && $at - $lengths[$k - 1] < $near) {
                    $angle = self::bisector(self::angle($path[$k - 2], $path[$k - 1]), $angle);
                }

                return [$x, $y, (int) round($angle)];
            }
        }

        return [$path[$n - 1][0], $path[$n - 1][1], 0];
    }

    /**
     * Partie d'un chemin entre les fractions $t0 et $t1 de sa longueur.
     *
     * @param  list<array{0: float, 1: float}>  $path
     * @return list<array{0: float, 1: float}>
     */
    private static function cut(array $path, float $t0, float $t1): array
    {
        if ($t1 - $t0 < 1e-6) {
            return [];
        }
        $lengths = self::lengths($path);
        $total = $lengths[count($lengths) - 1];
        $from = $t0 * $total;
        $to = $t1 * $total;
        [$sx, $sy] = self::at($path, $t0);
        $out = [[$sx, $sy]];
        $n = count($path);
        for ($k = 1; $k < $n - 1; $k++) {
            if ($lengths[$k] > $from && $lengths[$k] < $to) {
                $out[] = $path[$k];
            }
        }
        [$ex, $ey] = self::at($path, $t1);
        $out[] = [$ex, $ey];

        return $out;
    }

    /**
     * @param  list<array{0: float, 1: float}>  $path
     * @return list<float> longueur cumulée à chaque sommet
     */
    private static function lengths(array $path): array
    {
        $out = [0.0];
        $n = count($path);
        for ($k = 1; $k < $n; $k++) {
            $out[] = $out[$k - 1] + hypot($path[$k][0] - $path[$k - 1][0], $path[$k][1] - $path[$k - 1][1]);
        }

        return $out;
    }

    /**
     * Partie d'un axe entre deux distances, rattachée à deux nœuds.
     *
     * @param  array<string, mixed>  $c
     * @return array<string, mixed>
     */
    private static function slice(array $c, float $d0, float $d1, int $a, int $b): array
    {
        $pts = [];
        foreach ($c['pts'] as $pt) {
            if ($pt['d'] > $d0 && $pt['d'] < $d1) {
                $pts[] = ['d' => $pt['d'] - $d0] + $pt;
            }
        }
        $segs = [];
        foreach ($c['segs'] as $s) {
            if ($s['d1'] > $d0 && $s['d0'] < $d1) {
                $segs[] = ['d0' => max($s['d0'], $d0) - $d0, 'd1' => min($s['d1'], $d1) - $d0] + $s;
            }
        }

        return ['a' => $a, 'b' => $b, 'length' => $d1 - $d0, 'pts' => $pts, 'segs' => $segs];
    }

    /**
     * Axe parcouru dans l'autre sens.
     *
     * @param  array<string, mixed>  $c
     * @return array<string, mixed>
     */
    private static function reverse(array $c): array
    {
        $length = $c['length'];

        return [
            'a' => $c['b'],
            'b' => $c['a'],
            'length' => $length,
            'pts' => array_reverse(array_map(fn (array $pt): array => ['d' => $length - $pt['d']] + $pt, $c['pts'])),
            'segs' => array_reverse(array_map(fn (array $s): array => ['d0' => $length - $s['d1'], 'd1' => $length - $s['d0']] + $s, $c['segs'])),
        ] + $c;
    }

    /**
     * Importance d'un axe pour l'ordre de routage : catégorie dominante (en longueur), la plus active
     * en cas d'égalité.
     *
     * @param  array<string, mixed>  $c
     */
    private static function rank(array $c): int
    {
        $byCategory = [];
        foreach ($c['segs'] as $s) {
            if ($s['category'] !== 'connector') {
                $byCategory[$s['category']] = ($byCategory[$s['category']] ?? 0.0) + ($s['d1'] - $s['d0']);
            }
        }
        $best = 0;
        $bestLength = -1.0;
        foreach ($byCategory as $category => $length) {
            $rank = SchematicGraph::CATEGORY_RANK[$category] ?? 0;
            if ($length > $bestLength || ($length === $bestLength && $rank > $best)) {
                $best = $rank;
                $bestLength = $length;
            }
        }

        return $best;
    }

    /**
     * @param  array{0: float, 1: float}  $position
     * @return array{0: int, 1: int}
     */
    private static function gridPoint(array $position, float $cell): array
    {
        return [(int) round($position[0] / $cell), (int) round($position[1] / $cell)];
    }

    /**
     * @param  array{0: float, 1: float}|array{0: int, 1: int}  $a
     * @param  array{0: float, 1: float}|array{0: int, 1: int}  $b
     */
    private static function angle(array $a, array $b): float
    {
        $deg = rad2deg(atan2($b[1] - $a[1], $b[0] - $a[0]));

        return $deg < 0 ? $deg + 360.0 : $deg;
    }

    private static function bisector(float $a, float $b): float
    {
        $diff = fmod($b - $a + 540.0, 360.0) - 180.0;

        return fmod($a + $diff / 2 + 360.0, 360.0);
    }

    /**
     * Douglas-Peucker.
     *
     * @param  list<array{0: float, 1: float}>  $line
     * @return list<array{0: float, 1: float}>
     */
    private static function simplify(array $line, float $tolerance): array
    {
        $n = count($line);
        if ($n <= 2) {
            return $line;
        }
        $keep = array_fill(0, $n, false);
        $keep[0] = $keep[$n - 1] = true;
        $stack = [[0, $n - 1]];
        while ($stack !== []) {
            [$first, $last] = array_pop($stack);
            $worst = -1.0;
            $index = -1;
            for ($k = $first + 1; $k < $last; $k++) {
                $d = OctilinearRouter::distanceToPolyline($line[$k][0], $line[$k][1], [$line[$first], $line[$last]]);
                if ($d > $worst) {
                    $worst = $d;
                    $index = $k;
                }
            }
            if ($index >= 0 && $worst > $tolerance) {
                $keep[$index] = true;
                $stack[] = [$first, $index];
                $stack[] = [$index, $last];
            }
        }

        return array_values(array_filter($line, fn (int $k): bool => $keep[$k], ARRAY_FILTER_USE_KEY));
    }
}

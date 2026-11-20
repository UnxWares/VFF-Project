<?php

namespace App\Services\NetworkGraph;

use SplPriorityQueue;

/**
 * Couche gare-à-gare dérivée de la topologie d'une ère (algorithme pur, sans
 * base de données : testé unitairement).
 *
 * Entrées :
 *   - tronçons élémentaires (« pièces ») : id, nœud A, nœud B, longueur (m),
 *     ligne, drapeaux (sans voyageurs, associatif, raccord de lacune), écartement ;
 *   - nœuds-gares : nœud → gare OUVERTE à l'ère ;
 *   - nœuds de gares FERMÉES à l'ère : traversées sans arrêt (comptées).
 *
 * Pour chaque gare ouverte G, un Dijkstra borné part du nœud de G et s'arrête,
 * dans chaque direction, à la première gare ouverte rencontrée : une arête
 * G-G' par gare voisine, de longueur = plus court chemin sans gare ouverte
 * intermédiaire. Le coût d'une pièce sans service voyageurs est multiplié par
 * `nonPassengerFactor` : entre deux gares, on préfère la voie voyageurs.
 *
 * Deux passes :
 *   1. sans les pièces associatives (réseau commercial) ;
 *   2. avec elles, en ne gardant que les arêtes qui en empruntent et qui
 *      n'existent pas déjà en passe 1 (signalées `heritage_m` > 0).
 */
final class StationGraph
{
    /** @var array<int, array<int, array{0:int,1:int}>> nœud => [[voisin, index pièce], …] */
    private array $adj = [];

    /** @var array<int, int> */
    private array $len = [];

    /** @var array<int, float> */
    private array $cost = [];

    /** @var array<int, int> */
    private array $line = [];

    /** @var array<int, bool> */
    private array $heritage = [];

    /** @var array<int, bool> */
    private array $nonPassenger = [];

    /** @var array<int, bool> */
    private array $connector = [];

    /** @var array<int, int|null> */
    private array $gauge = [];

    /** @var array<int, int> index => id de la pièce */
    private array $pieceId = [];

    /**
     * @param  iterable<array{id:int, from:int, to:int, length_m:int, line_id:?int, heritage?:bool, non_passenger?:bool, connector?:bool, gauge_mm?:?int}>  $pieces
     * @param  array<int, int>  $stationNodes  nœud => station_id (gares ouvertes)
     * @param  array<int, bool>  $closedNodes  nœud => true (gares fermées à l'ère)
     */
    public function __construct(
        iterable $pieces,
        private readonly array $stationNodes,
        private readonly array $closedNodes = [],
        private readonly float $nonPassengerFactor = 4.0,
    ) {
        $i = 0;
        foreach ($pieces as $p) {
            $a = (int) $p['from'];
            $b = (int) $p['to'];
            if ($a === $b) {
                continue;
            }
            $this->pieceId[$i] = (int) $p['id'];
            $this->len[$i] = max(0, (int) $p['length_m']);
            $this->line[$i] = (int) ($p['line_id'] ?? 0);
            $this->heritage[$i] = (bool) ($p['heritage'] ?? false);
            $this->nonPassenger[$i] = (bool) ($p['non_passenger'] ?? false);
            $this->connector[$i] = (bool) ($p['connector'] ?? false);
            $this->gauge[$i] = isset($p['gauge_mm']) ? (int) $p['gauge_mm'] : null;
            $this->cost[$i] = $this->len[$i] * ($this->nonPassenger[$i] ? $this->nonPassengerFactor : 1.0);
            $this->adj[$a][] = [$b, $i];
            $this->adj[$b][] = [$a, $i];
            $i++;
        }
    }

    /**
     * Arêtes gare-à-gare (from_station_id < to_station_id), passes 1 puis 2.
     *
     * @return list<array{from_station_id:int, to_station_id:int, length_m:int, non_passenger_m:int, heritage_m:int,
     *     skipped_count:int, gap_joins:int, line_id:?int, line_ids:list<int>, line_lengths:list<int>, gauges:list<int>, piece_ids:list<int>}>
     */
    public function stationEdges(): array
    {
        $edges = [];
        $seen = [];
        foreach ([false, true] as $withHeritage) {
            if ($withHeritage && ! in_array(true, $this->heritage, true)) {
                break;
            }
            foreach ($this->stationNodes as $node => $station) {
                foreach ($this->searchFrom((int) $node, $withHeritage) as $target => [$path]) {
                    $other = $this->stationNodes[$target];
                    if ($station >= $other) {
                        continue; // chaque paire une seule fois, dans le sens from < to
                    }
                    $key = $station.':'.$other;
                    if (isset($seen[$key])) {
                        continue;
                    }
                    $edge = $this->describe($station, $other, (int) $node, $path);
                    if ($withHeritage && $edge['heritage_m'] === 0) {
                        continue; // passe 2 : seulement les liaisons qui doivent passer par l'associatif
                    }
                    $seen[$key] = true;
                    $edges[] = $edge;
                }
            }
        }

        return $edges;
    }

    /**
     * Dijkstra borné depuis un nœud-gare : nœud-gare atteint => [chemin (pièces, du départ à la cible), coût].
     *
     * @return array<int, array{0: list<int>, 1: float}>
     */
    private function searchFrom(int $source, bool $withHeritage): array
    {
        $dist = [$source => 0.0];
        $pred = []; // nœud => [nœud précédent, pièce]
        $found = [];
        $pq = new SplPriorityQueue;
        $pq->setExtractFlags(SplPriorityQueue::EXTR_BOTH);
        $pq->insert($source, 0.0);

        while (! $pq->isEmpty()) {
            $item = $pq->extract();
            $u = $item['data'];
            $du = -$item['priority'];
            if ($du > ($dist[$u] ?? INF)) {
                continue;
            }
            if ($u !== $source && isset($this->stationNodes[$u])) {
                $found[$u] = $du; // première gare dans cette direction : on ne la traverse pas

                continue;
            }
            foreach ($this->adj[$u] ?? [] as [$v, $i]) {
                if (! $withHeritage && $this->heritage[$i]) {
                    continue;
                }
                $alt = $du + $this->cost[$i];
                if ($alt < ($dist[$v] ?? INF)) {
                    $dist[$v] = $alt;
                    $pred[$v] = [$u, $i];
                    $pq->insert($v, -$alt);
                }
            }
        }

        $out = [];
        foreach ($found as $target => $cost) {
            $path = [];
            for ($n = $target; $n !== $source; $n = $pred[$n][0]) {
                $path[] = $pred[$n][1];
            }
            $out[$target] = [array_reverse($path), $cost];
        }

        return $out;
    }

    /**
     * @param  list<int>  $path  indices de pièces, du départ à l'arrivée
     */
    private function describe(int $from, int $to, int $node, array $path): array
    {
        $length = $nonPassenger = $heritage = $gaps = 0;
        $runs = []; // [[line_id, longueur], …] le long du chemin
        $gauges = [];
        $byLine = [];
        $skipped = 0;
        foreach ($path as $i) {
            $l = $this->len[$i];
            $length += $l;
            if ($this->nonPassenger[$i]) {
                $nonPassenger += $l;
            }
            if ($this->heritage[$i]) {
                $heritage += $l;
            }
            if ($this->connector[$i]) {
                $gaps++;
            } else {
                $lineId = $this->line[$i];
                if ($runs && $runs[count($runs) - 1][0] === $lineId) {
                    $runs[count($runs) - 1][1] += $l;
                } else {
                    $runs[] = [$lineId, $l];
                }
                $byLine[$lineId] = ($byLine[$lineId] ?? 0) + $l;
            }
            if ($this->gauge[$i] !== null) {
                $gauges[$this->gauge[$i]] = true;
            }
            // Nœud suivant le long du chemin : gare fermée traversée ?
            $node = $this->otherEnd($i, $node);
            if ($node !== null && isset($this->closedNodes[$node])) {
                $skipped++;
            }
        }
        arsort($byLine);
        $dominant = array_key_first($byLine);

        return [
            'from_station_id' => $from,
            'to_station_id' => $to,
            'length_m' => $length,
            'non_passenger_m' => $nonPassenger,
            'heritage_m' => $heritage,
            'skipped_count' => $skipped,
            'gap_joins' => $gaps,
            'line_id' => $dominant ?: null,
            'line_ids' => array_map(fn ($r) => $r[0], $runs),
            'line_lengths' => array_map(fn ($r) => $r[1], $runs),
            'gauges' => array_map('intval', array_keys($gauges)),
            'piece_ids' => array_map(fn ($i) => $this->pieceId[$i], $path),
        ];
    }

    /** Extrémité opposée de la pièce `$i` depuis le nœud `$node`. */
    private function otherEnd(int $i, ?int $node): ?int
    {
        if ($node === null) {
            return null;
        }
        foreach ($this->adj[$node] ?? [] as [$v, $j]) {
            if ($j === $i) {
                return $v;
            }
        }

        return null;
    }

    /**
     * Composantes connexes du graphe gare-à-gare (union-find).
     *
     * @param  iterable<array{from_station_id:int, to_station_id:int}>  $edges
     * @param  list<int>  $stations  toutes les gares rattachées (y compris isolées)
     * @return array{components:int, largest:int, isolated:int}
     */
    public static function components(iterable $edges, array $stations): array
    {
        $parent = [];
        $find = function (int $x) use (&$parent, &$find): int {
            while ($parent[$x] !== $x) {
                $parent[$x] = $parent[$parent[$x]];
                $x = $parent[$x];
            }

            return $x;
        };
        foreach ($stations as $s) {
            $parent[$s] = $s;
        }
        $degree = [];
        foreach ($edges as $e) {
            $a = (int) $e['from_station_id'];
            $b = (int) $e['to_station_id'];
            $parent[$a] ??= $a;
            $parent[$b] ??= $b;
            $degree[$a] = ($degree[$a] ?? 0) + 1;
            $degree[$b] = ($degree[$b] ?? 0) + 1;
            $ra = $find($a);
            $rb = $find($b);
            if ($ra !== $rb) {
                $parent[$ra] = $rb;
            }
        }
        $sizes = [];
        foreach (array_keys($parent) as $s) {
            $r = $find($s);
            $sizes[$r] = ($sizes[$r] ?? 0) + 1;
        }

        return [
            'components' => count($sizes),
            'largest' => $sizes ? max($sizes) : 0,
            'isolated' => count(array_filter(array_keys($parent), fn ($s) => ! isset($degree[$s]))),
        ];
    }
}

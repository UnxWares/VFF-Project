<?php

namespace App\Services\Gtfs;

/**
 * Chemin le long des voies d'autres lignes, du bout d'une forme GTFS jusqu'à une gare.
 *
 * Cas visé : le tracé d'une ligne s'arrête au début du tronc commun qu'elle partage avec une
 * autre ligne (Gannat, Neuf-Brisach), alors que ses trains vont jusqu'à la gare. Le chemin suit
 * uniquement les voies fournies, sommet par sommet : il ne tire jamais de droite, à part le
 * raccord du bout de la forme à la voie la plus proche (au plus `$joinM`).
 *
 * Réseau : chaque voie est une polyligne [lon, lat]. Deux sommets à moins de `$snapM` l'un de
 * l'autre sont un même nœud (voies de deux lignes qui se touchent), et un bout de voie posé à
 * moins de `$snapM` du milieu d'une autre voie s'y raccorde (bifurcation en T). Les distances se
 * mesurent sur un plan local (équirectangulaire autour du départ), juste à l'échelle de quelques
 * kilomètres.
 *
 * Contraintes du chemin :
 *   - départ dans le prolongement du bout (cap lu de `$before` à `$from`), puis, à chaque sommet,
 *     changement de cap d'au plus `$turnDeg` (cap lu sur les LOOKBACK_M derniers mètres du
 *     chemin, pour ignorer le décrochement de quelques mètres entre deux tronçons) : jamais
 *     d'aller-retour ni de rebroussement ;
 *   - longueur d'au plus `$maxM` ;
 *   - arrivée au point de voie le plus proche de la gare, à au plus `$reachM` d'elle. Entre
 *     plusieurs arrivées possibles : la plus proche de la gare (au mètre près), puis la plus courte.
 */
final class SharedTrackRouter
{
    /** Mètres par degré de latitude. */
    private const M_PER_DEG = 111_320.0;

    /** Longueur (m) de chemin en arrière sur laquelle se lit le cap à chaque sommet. */
    private const LOOKBACK_M = 15.0;

    /** @var list<float> */
    private array $x = [];

    /** @var list<float> */
    private array $y = [];

    /** @var array<int, array<int, float>> nœud => [voisin => longueur] */
    private array $adj = [];

    /** @var array<string, list<int>> grille des nœuds (cellules de `$snapM`) */
    private array $grid = [];

    private float $lon0 = 0.0;

    private float $lat0 = 0.0;

    private float $kx = self::M_PER_DEG;

    public function __construct(
        private readonly float $snapM = 2.0,
        private readonly float $joinM = 10.0,
        private readonly float $reachM = 50.0,
        private readonly float $maxM = 3_000.0,
        private readonly float $turnDeg = 60.0,
    ) {}

    /**
     * Chemin du point de voie le plus proche de `$from` jusqu'au point de voie le plus proche de
     * `$to`, en [lon, lat], ou null s'il n'y en a pas qui respecte les contraintes.
     *
     * @param  list<list<array{0: float, 1: float}>>  $tracks  voies, chacune en [lon, lat]
     * @param  array{0: float, 1: float}  $from  bout de la forme
     * @param  array{0: float, 1: float}  $before  point de la forme juste avant le bout (cap d'arrivée)
     * @param  array{0: float, 1: float}  $to  gare
     * @return list<array{0: float, 1: float}>|null
     */
    public function route(array $tracks, array $from, array $before, array $to): ?array
    {
        $this->reset($from);
        foreach ($tracks as $track) {
            $this->addTrack($track);
        }
        $this->joinDanglingEnds();

        [$fx, $fy] = $this->project($from);
        [$bx, $by] = $this->project($before);
        [$tx, $ty] = $this->project($to);
        // Départs : un point sur chaque voie à moins de `$joinM` du bout (au croisement de deux
        // voies, la plus proche n'est pas forcément celle qui continue dans le bon sens).
        $starts = $this->insertOnEdgesWithin($fx, $fy, $this->joinM);
        if ($starts === [] || hypot($fx - $bx, $fy - $by) <= 0.0) {
            return null;
        }

        // Dijkstra sur les arêtes orientées (état = nœud d'arrivée et nœud précédent) : le cap
        // compte, pas seulement le nœud. Le précédent virtuel -1 porte le cap du bout de la forme.
        $dist = [];
        $prev = [];
        $queue = new \SplPriorityQueue;
        foreach ($starts as $start => $join) {
            $dist["-1:{$start}"] = $join;
            $queue->insert([-1, $start], -$join);
        }
        $best = null;
        while (! $queue->isEmpty()) {
            [$p, $u] = $queue->extract();
            $here = $dist["{$p}:{$u}"];
            // Cap lu depuis un point du chemin LOOKBACK_M en arrière (ou depuis le point de la forme
            // avant le bout) : un décrochement de deux mètres entre deux tronçons n'est pas un virage.
            [$kx, $ky] = $this->backPoint($p, $u, $prev, $bx, $by);
            $hx = $this->x[$u] - $kx;
            $hy = $this->y[$u] - $ky;
            foreach ($this->adj[$u] ?? [] as $v => $length) {
                if ($v === $p || $length <= 0.0 || $here + $length > $this->maxM) {
                    continue;
                }
                if ($this->turn($hx, $hy, $this->x[$v] - $kx, $this->y[$v] - $ky) > $this->turnDeg) {
                    continue;
                }
                // Arrivée possible sur cette arête : point le plus proche de la gare.
                [$t, $reach] = $this->closestOnEdge($u, $v, $tx, $ty);
                if ($reach <= $this->reachM) {
                    $candidate = [round($reach), $here + $t * $length, $p, $u, $v, $t];
                    if ($best === null || [$candidate[0], $candidate[1]] < [$best[0], $best[1]]) {
                        $best = $candidate;
                    }
                }
                $next = "{$u}:{$v}";
                if (! isset($dist[$next]) || $here + $length < $dist[$next]) {
                    $dist[$next] = $here + $length;
                    $prev[$next] = "{$p}:{$u}";
                    $queue->insert([$u, $v], -($here + $length));
                }
            }
        }
        if ($best === null) {
            return null;
        }

        [, , $p, $u, $v, $t] = $best;
        $nodes = [$u];
        $state = "{$p}:{$u}";
        while (isset($prev[$state])) {
            $state = $prev[$state];
            [$sp, $su] = array_map('intval', explode(':', $state));
            $nodes[] = $su;
            if ($sp === -1) {
                break;
            }
        }
        $nodes = array_reverse($nodes);
        $path = array_map(fn (int $n): array => $this->unproject($this->x[$n], $this->y[$n]), $nodes);
        if ($t > 0.0) {
            $path[] = $this->unproject($this->x[$u] + $t * ($this->x[$v] - $this->x[$u]), $this->y[$u] + $t * ($this->y[$v] - $this->y[$u]));
        }

        return count($path) >= 2 ? $path : null;
    }

    /**
     * Point du chemin situé au moins LOOKBACK_M avant le nœud `$u` atteint depuis `$p`, en
     * remontant les prédécesseurs ; à défaut (début du chemin), le point (bx, by) de la forme.
     *
     * @param  array<string, string>  $prev
     * @return array{0: float, 1: float}
     */
    private function backPoint(int $p, int $u, array $prev, float $bx, float $by): array
    {
        $back = 0.0;
        while ($p !== -1) {
            $back += $this->adj[$p][$u] ?? hypot($this->x[$u] - $this->x[$p], $this->y[$u] - $this->y[$p]);
            if ($back >= self::LOOKBACK_M) {
                return [$this->x[$p], $this->y[$p]];
            }
            $state = $prev["{$p}:{$u}"] ?? null;
            if ($state === null) {
                break;
            }
            [$p, $u] = array_map('intval', explode(':', $state));
        }

        return [$bx, $by];
    }

    /** @param array{0: float, 1: float} $origin */
    private function reset(array $origin): void
    {
        $this->x = [];
        $this->y = [];
        $this->adj = [];
        $this->grid = [];
        $this->lon0 = $origin[0];
        $this->lat0 = $origin[1];
        $this->kx = self::M_PER_DEG * cos(deg2rad($origin[1]));
    }

    /**
     * @param  array{0: float, 1: float}  $p
     * @return array{0: float, 1: float}
     */
    private function project(array $p): array
    {
        return [($p[0] - $this->lon0) * $this->kx, ($p[1] - $this->lat0) * self::M_PER_DEG];
    }

    /** @return array{0: float, 1: float} */
    private function unproject(float $x, float $y): array
    {
        return [$this->lon0 + $x / $this->kx, $this->lat0 + $y / self::M_PER_DEG];
    }

    /** @param list<array{0: float, 1: float}> $track */
    private function addTrack(array $track): void
    {
        $previous = null;
        foreach ($track as $point) {
            [$px, $py] = $this->project($point);
            $node = $this->node($px, $py);
            if ($previous !== null && $previous !== $node) {
                $this->link($previous, $node);
            }
            $previous = $node;
        }
    }

    /** Nœud à moins de `$snapM` de (x, y), créé au besoin. */
    private function node(float $x, float $y): int
    {
        $cx = (int) floor($x / $this->snapM);
        $cy = (int) floor($y / $this->snapM);
        for ($i = -1; $i <= 1; $i++) {
            for ($j = -1; $j <= 1; $j++) {
                foreach ($this->grid[($cx + $i).':'.($cy + $j)] ?? [] as $n) {
                    if (hypot($this->x[$n] - $x, $this->y[$n] - $y) <= $this->snapM) {
                        return $n;
                    }
                }
            }
        }
        $n = count($this->x);
        $this->x[] = $x;
        $this->y[] = $y;
        $this->adj[$n] = [];
        $this->grid["{$cx}:{$cy}"][] = $n;

        return $n;
    }

    private function link(int $a, int $b): void
    {
        $length = hypot($this->x[$b] - $this->x[$a], $this->y[$b] - $this->y[$a]);
        $this->adj[$a][$b] = $length;
        $this->adj[$b][$a] = $length;
    }

    private function unlink(int $a, int $b): void
    {
        unset($this->adj[$a][$b], $this->adj[$b][$a]);
    }

    /**
     * Bout de voie (nœud d'un seul voisin) à moins de `$snapM` du milieu d'une autre voie : il est
     * glissé sur elle et coupe l'arête en deux (bifurcation en T).
     */
    private function joinDanglingEnds(): void
    {
        foreach (array_keys($this->adj) as $n) {
            if (count($this->adj[$n]) !== 1) {
                continue;
            }
            $own = array_key_first($this->adj[$n]);
            $edge = $this->nearestEdge($this->x[$n], $this->y[$n], $this->snapM, [$n, $own]);
            if ($edge === null) {
                continue;
            }
            [$a, $b, $t] = $edge;
            $this->x[$n] = $this->x[$a] + $t * ($this->x[$b] - $this->x[$a]);
            $this->y[$n] = $this->y[$a] + $t * ($this->y[$b] - $this->y[$a]);
            $this->unlink($a, $b);
            $this->link($a, $n);
            $this->link($n, $b);
            $this->link($n, $own);
        }
    }

    /**
     * Nœuds de départ : sur chaque arête à au plus `$within` de (x, y), son point le plus proche
     * (arête coupée en deux au besoin), avec la distance du point au bout.
     *
     * @return array<int, float> nœud => distance (m)
     */
    private function insertOnEdgesWithin(float $x, float $y, float $within): array
    {
        $found = [];
        foreach ($this->adj as $a => $neighbours) {
            foreach ($neighbours as $b => $length) {
                if ($b < $a) {
                    continue;
                }
                [$t, $d] = $this->closestOnEdge($a, $b, $x, $y);
                if ($d <= $within) {
                    $found[] = [$a, $b, $t, $d];
                }
            }
        }
        $starts = [];
        foreach ($found as [$a, $b, $t, $d]) {
            if ($t <= 0.0 || $t >= 1.0) {
                $n = $t <= 0.0 ? $a : $b;
            } else {
                $n = count($this->x);
                $this->x[] = $this->x[$a] + $t * ($this->x[$b] - $this->x[$a]);
                $this->y[] = $this->y[$a] + $t * ($this->y[$b] - $this->y[$a]);
                $this->adj[$n] = [];
                $this->unlink($a, $b);
                $this->link($a, $n);
                $this->link($n, $b);
            }
            $starts[$n] = min($starts[$n] ?? INF, $d);
        }

        return $starts;
    }

    /**
     * Arête la plus proche de (x, y) à au plus `$within`, hors arêtes touchant `$exclude`.
     *
     * @param  list<int|string|null>  $exclude
     * @return array{0: int, 1: int, 2: float}|null [a, b, position 0..1 du point le plus proche]
     */
    private function nearestEdge(float $x, float $y, float $within, array $exclude): ?array
    {
        $best = null;
        $bestD = $within;
        foreach ($this->adj as $a => $neighbours) {
            if (in_array($a, $exclude, true)) {
                continue;
            }
            foreach ($neighbours as $b => $length) {
                if ($b < $a || in_array($b, $exclude, true)) {
                    continue;
                }
                [$t, $d] = $this->closestOnEdge($a, $b, $x, $y);
                if ($d <= $bestD) {
                    $bestD = $d;
                    $best = [$a, $b, $t];
                }
            }
        }

        return $best;
    }

    /**
     * Point de l'arête a-b le plus proche de (x, y) : position 0..1 et distance.
     *
     * @return array{0: float, 1: float}
     */
    private function closestOnEdge(int $a, int $b, float $x, float $y): array
    {
        $dx = $this->x[$b] - $this->x[$a];
        $dy = $this->y[$b] - $this->y[$a];
        $len2 = $dx * $dx + $dy * $dy;
        $t = $len2 > 0.0 ? max(0.0, min(1.0, (($x - $this->x[$a]) * $dx + ($y - $this->y[$a]) * $dy) / $len2)) : 0.0;

        return [$t, hypot($this->x[$a] + $t * $dx - $x, $this->y[$a] + $t * $dy - $y)];
    }

    /** Changement de cap (°) entre deux directions. */
    private function turn(float $ax, float $ay, float $bx, float $by): float
    {
        $na = hypot($ax, $ay);
        $nb = hypot($bx, $by);
        if ($na <= 0.0 || $nb <= 0.0) {
            return 180.0;
        }

        return rad2deg(acos(max(-1.0, min(1.0, ($ax * $bx + $ay * $by) / ($na * $nb)))));
    }
}

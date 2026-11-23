<?php

declare(strict_types=1);

namespace App\Services\Structures;

/**
 * Ouvrages de la BD TOPO reconstitués à partir des tronçons de voie ferrée en tunnel ou sur pont.
 *
 * 1. Les tronçons lus dans plusieurs tuiles du cache (un tronçon qui déborde est rendu par chaque tuile
 *    qu'il touche) ne comptent qu'une fois.
 * 2. Les tronçons de même position (tunnel ou pont) qui se touchent par une extrémité forment une
 *    chaîne : un tunnel coupé en plusieurs tronçons (changement de nombre de voies, aiguillage, limite de
 *    commune) redevient un seul ouvrage. Le tracé gardé est le plus long chemin entre deux bouts libres de
 *    la chaîne : une bifurcation dans un tunnel ne double pas sa longueur.
 * 3. Deux chaînes parallèles de même position (deux voies dessinées séparément, deux tabliers côte à
 *    côte) sont un seul ouvrage quand le milieu de chacune est à PARALLEL_M mètres au plus de l'autre :
 *    la plus longue est gardée.
 *
 * Fonctions pures (tests : tests/Unit/Structures/TrackChainsTest.php).
 */
final class TrackChains
{
    /** Écart (m) sous lequel deux chaînes parallèles de même position sont le même ouvrage. */
    public const PARALLEL_M = 30.0;

    /**
     * @param  iterable<array{kind: string, coords: list<array{0: float, 1: float}>}>  $tracks  tronçons
     *                                                                                          `tunnel` ou `bridge` (les autres sont ignorés)
     * @return list<array{kind: string, coords: list<array{0: float, 1: float}>, length_m: float}>
     */
    public static function build(iterable $tracks): array
    {
        $unique = [];
        foreach ($tracks as $t) {
            if (! in_array($t['kind'], ['tunnel', 'bridge'], true) || count($t['coords']) < 2) {
                continue;
            }
            $unique[self::trackKey($t['coords']).$t['kind']] = $t;
        }

        $chains = [];
        foreach (['tunnel', 'bridge'] as $kind) {
            $ofKind = array_values(array_filter($unique, fn (array $t): bool => $t['kind'] === $kind));
            foreach (self::components($ofKind) as $component) {
                $coords = self::longestPath($component);
                $chains[] = ['kind' => $kind, 'coords' => $coords, 'length_m' => StructureGeometry::length($coords)];
            }
        }

        return self::mergeParallel($chains);
    }

    /**
     * Groupes de tronçons reliés par leurs extrémités (union-find sur les clés d'extrémité).
     *
     * @param  list<array{kind: string, coords: list<array{0: float, 1: float}>}>  $tracks
     * @return list<list<list<array{0: float, 1: float}>>>
     */
    private static function components(array $tracks): array
    {
        $parent = array_keys($tracks);
        $find = function (int $i) use (&$parent): int {
            while ($parent[$i] !== $i) {
                $parent[$i] = $parent[$parent[$i]];
                $i = $parent[$i];
            }

            return $i;
        };
        $byEnd = [];
        foreach ($tracks as $i => $t) {
            foreach ([self::pointKey($t['coords'][0]), self::pointKey($t['coords'][count($t['coords']) - 1])] as $key) {
                if (isset($byEnd[$key])) {
                    $parent[$find($i)] = $find($byEnd[$key]);
                } else {
                    $byEnd[$key] = $i;
                }
            }
        }
        $groups = [];
        foreach ($tracks as $i => $t) {
            $groups[$find($i)][] = $t['coords'];
        }

        return array_values($groups);
    }

    /**
     * Chemin le plus long (en mètres) entre deux extrémités d'un groupe de tronçons, assemblé en une seule
     * polyligne : double parcours de Dijkstra (le point le plus éloigné d'une extrémité, puis le point le
     * plus éloigné de celui-ci), exact pour un groupe sans boucle, raisonnable sinon, et en temps
     * polynomial même pour les grands réseaux souterrains (RER, gares en tunnel).
     *
     * @param  list<list<array{0: float, 1: float}>>  $parts
     * @return list<array{0: float, 1: float}>
     */
    private static function longestPath(array $parts): array
    {
        if (count($parts) === 1) {
            return $parts[0];
        }
        $edges = [];
        $adjacent = [];
        foreach ($parts as $e => $coords) {
            $a = self::pointKey($coords[0]);
            $b = self::pointKey($coords[count($coords) - 1]);
            $edges[$e] = [$a, $b, StructureGeometry::length($coords)];
            $adjacent[$a][] = $e;
            $adjacent[$b][] = $e;
        }
        $leaves = array_keys(array_filter($adjacent, fn (array $es): bool => count($es) === 1));
        $start = (string) ($leaves[0] ?? array_key_first($adjacent));

        [$far] = self::farthest($start, $edges, $adjacent);
        [$end, $via] = self::farthest($far, $edges, $adjacent);

        // Remontée du chemin depuis le bout le plus éloigné jusqu'à `$far`.
        $steps = [];
        for ($node = $end; $node !== $far;) {
            [$e, $from] = $via[$node];
            $steps[] = [$e, $edges[$e][0] === $from];
            $node = $from;
        }
        $coords = [];
        foreach (array_reverse($steps) as [$e, $forward]) {
            $part = $forward ? $parts[$e] : array_reverse($parts[$e]);
            $coords = $coords === [] ? $part : [...$coords, ...array_slice($part, 1)];
        }

        return $coords;
    }

    /**
     * Dijkstra depuis `$start` : le nœud le plus éloigné et, pour chaque nœud atteint, le tronçon et le
     * nœud par lesquels on y arrive.
     *
     * @param  array<int, array{0: string, 1: string, 2: float}>  $edges
     * @param  array<string, list<int>>  $adjacent
     * @return array{0: string, 1: array<string, array{0: int, 1: string}>}
     */
    private static function farthest(string $start, array $edges, array $adjacent): array
    {
        $dist = [$start => 0.0];
        $via = [];
        $queue = new \SplPriorityQueue;
        $queue->insert($start, 0.0);
        $done = [];
        while (! $queue->isEmpty()) {
            $node = (string) $queue->extract();
            if (isset($done[$node])) {
                continue;
            }
            $done[$node] = true;
            foreach ($adjacent[$node] as $e) {
                $next = $edges[$e][0] === $node ? $edges[$e][1] : $edges[$e][0];
                $d = $dist[$node] + $edges[$e][2];
                if (! isset($dist[$next]) || $d < $dist[$next]) {
                    $dist[$next] = $d;
                    $via[$next] = [$e, $node];
                    $queue->insert($next, -$d);
                }
            }
        }
        arsort($dist);

        return [(string) array_key_first($dist), $via];
    }

    /**
     * Fusion des chaînes parallèles de même position : la plus longue absorbe les autres.
     *
     * @param  list<array{kind: string, coords: list<array{0: float, 1: float}>, length_m: float}>  $chains
     * @return list<array{kind: string, coords: list<array{0: float, 1: float}>, length_m: float}>
     */
    private static function mergeParallel(array $chains): array
    {
        usort($chains, fn (array $a, array $b): int => $b['length_m'] <=> $a['length_m']);
        $cell = 0.01;
        $grid = [];
        $kept = [];
        foreach ($chains as $chain) {
            $mid = StructureGeometry::midpoint($chain['coords']);
            $cx = (int) floor($mid[0] / $cell);
            $cy = (int) floor($mid[1] / $cell);
            $absorbed = false;
            for ($dx = -1; $dx <= 1 && ! $absorbed; $dx++) {
                for ($dy = -1; $dy <= 1 && ! $absorbed; $dy++) {
                    foreach ($grid[($cx + $dx).':'.($cy + $dy)] ?? [] as $k) {
                        $other = $kept[$k];
                        if ($other['kind'] === $chain['kind']
                            && StructureGeometry::distanceToLine($mid, $other['coords']) <= self::PARALLEL_M
                            && StructureGeometry::distanceToLine(StructureGeometry::midpoint($other['coords']), $chain['coords']) <= self::PARALLEL_M + $other['length_m'] / 2) {
                            $absorbed = true;
                            break;
                        }
                    }
                }
            }
            if (! $absorbed) {
                $kept[] = $chain;
                $grid[$cx.':'.$cy][] = count($kept) - 1;
            }
        }

        return $kept;
    }

    /** @param  list<array{0: float, 1: float}>  $coords */
    private static function trackKey(array $coords): string
    {
        $a = self::pointKey($coords[0]);
        $b = self::pointKey($coords[count($coords) - 1]);

        return ($a < $b ? $a.'/'.$b : $b.'/'.$a).'/'.count($coords);
    }

    /** @param  array{0: float, 1: float}  $p */
    private static function pointKey(array $p): string
    {
        return sprintf('%.6F,%.6F', $p[0], $p[1]);
    }
}

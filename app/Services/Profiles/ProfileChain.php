<?php

namespace App\Services\Profiles;

/**
 * Assemble les morceaux du tracé d'une ligne (sortie de ST_LineMerge : souvent plusieurs
 * morceaux, coupés aux bifurcations, aux raccordements et aux lacunes) en UN parcours de bout en
 * bout, celui que suit le profil en long.
 *
 * Méthode : graphe dont les nœuds sont les extrémités des morceaux (fusionnées à moins de
 * `SNAP_M`) et les arêtes les morceaux ; deux composantes séparées de moins de `MAX_GAP_M` sont
 * reliées par une arête « lacune » (ligne droite, pénalisée pour que le parcours préfère la voie).
 * Le parcours retenu est le plus long des plus courts chemins (pseudo-diamètre : deux recherches
 * de Dijkstra), qui suit l'artère principale et laisse de côté épis, raccordements et boucles
 * de gare. Les lacunes empruntées sont rendues à part : aucune altitude n'y est mesurée.
 */
final class ProfileChain
{
    /** Extrémités plus proches que cela : même nœud (voies d'une gare, arrondis des sources). */
    public const SNAP_M = 30.0;

    /** Lacune au-delà de laquelle deux morceaux ne sont plus reliés. */
    public const MAX_GAP_M = 5000.0;

    /** Pénalité d'une lacune dans la recherche du parcours (la voie réelle est préférée). */
    private const GAP_PENALTY = 3.0;

    /** @var list<array{0: float, 1: float}> */
    private array $nodes = [];

    /** @var list<array{a: int, b: int, coords: list<array{0: float, 1: float}>, length: float, gap: bool}> */
    private array $edges = [];

    /**
     * @param  list<list<array{0: float, 1: float}>>  $parts  morceaux du tracé, sommets [lon, lat]
     */
    public static function build(array $parts): ?ProfilePath
    {
        return (new self)->assemble($parts);
    }

    /**
     * @param  list<list<array{0: float, 1: float}>>  $parts
     */
    private function assemble(array $parts): ?ProfilePath
    {
        foreach ($parts as $coords) {
            if (count($coords) < 2) {
                continue;
            }
            $path = new ProfilePath($coords);
            if ($path->length() < 1.0) {
                continue;
            }
            $this->edges[] = [
                'a' => $this->nodeFor($coords[0]),
                'b' => $this->nodeFor($coords[count($coords) - 1]),
                'coords' => $coords,
                'length' => $path->length(),
                'gap' => false,
            ];
        }
        if ($this->edges === []) {
            return null;
        }
        $this->linkComponents();

        // Départ : une extrémité du plus long morceau (toujours dans l'artère principale).
        $longest = array_reduce($this->edges, fn (?array $best, array $e) => $best === null || $e['length'] > $best['length'] ? $e : $best);
        [$distStart] = $this->dijkstra($longest['a']);
        $from = $this->farthest($distStart);
        [$dist, $prev] = $this->dijkstra($from);
        $to = $this->farthest($dist);

        return $this->pathBetween($from, $to, $prev) ?? new ProfilePath($longest['coords']);
    }

    /** @param array{0: float, 1: float} $point */
    private function nodeFor(array $point): int
    {
        foreach ($this->nodes as $i => $node) {
            if (ProfilePath::distance($node, $point) <= self::SNAP_M) {
                return $i;
            }
        }
        $this->nodes[] = $point;

        return count($this->nodes) - 1;
    }

    /** Arêtes « lacune » entre nœuds de composantes différentes, à moins de MAX_GAP_M. */
    private function linkComponents(): void
    {
        $parent = array_keys($this->nodes);
        $find = function (int $i) use (&$parent): int {
            while ($parent[$i] !== $i) {
                $parent[$i] = $parent[$parent[$i]];
                $i = $parent[$i];
            }

            return $i;
        };
        foreach ($this->edges as $e) {
            $parent[$find($e['a'])] = $find($e['b']);
        }

        $n = count($this->nodes);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                if ($find($i) === $find($j)) {
                    continue;
                }
                $d = ProfilePath::distance($this->nodes[$i], $this->nodes[$j]);
                if ($d <= self::MAX_GAP_M) {
                    $this->edges[] = ['a' => $i, 'b' => $j, 'coords' => [$this->nodes[$i], $this->nodes[$j]], 'length' => $d, 'gap' => true];
                }
            }
        }
    }

    /**
     * Plus courts chemins depuis un nœud (Dijkstra simple : quelques centaines de nœuds au plus).
     * Les distances rendues sont des longueurs RÉELLES ; seule la recherche pénalise les lacunes.
     *
     * @return array{0: array<int, float>, 1: array<int, array{node: int, edge: int}>}
     */
    private function dijkstra(int $source): array
    {
        $cost = [$source => 0.0];
        $real = [$source => 0.0];
        $prev = [];
        $done = [];
        $adjacent = [];
        foreach ($this->edges as $k => $e) {
            $adjacent[$e['a']][] = [$e['b'], $k];
            $adjacent[$e['b']][] = [$e['a'], $k];
        }
        while (true) {
            $u = null;
            foreach ($cost as $node => $c) {
                if (! isset($done[$node]) && ($u === null || $c < $cost[$u])) {
                    $u = $node;
                }
            }
            if ($u === null) {
                break;
            }
            $done[$u] = true;
            foreach ($adjacent[$u] ?? [] as [$v, $k]) {
                $e = $this->edges[$k];
                $c = $cost[$u] + $e['length'] * ($e['gap'] ? self::GAP_PENALTY : 1.0);
                if (! isset($cost[$v]) || $c < $cost[$v]) {
                    $cost[$v] = $c;
                    $real[$v] = $real[$u] + $e['length'];
                    $prev[$v] = ['node' => $u, 'edge' => $k];
                }
            }
        }

        return [$real, $prev];
    }

    /** @param array<int, float> $dist */
    private function farthest(array $dist): int
    {
        arsort($dist);

        return (int) array_key_first($dist);
    }

    /**
     * @param  array<int, array{node: int, edge: int}>  $prev
     */
    private function pathBetween(int $from, int $to, array $prev): ?ProfilePath
    {
        $steps = [];
        for ($v = $to; $v !== $from; $v = $prev[$v]['node']) {
            if (! isset($prev[$v])) {
                return null;
            }
            $steps[] = ['from' => $prev[$v]['node'], 'edge' => $prev[$v]['edge']];
        }
        if ($steps === []) {
            return null;
        }

        $coords = [];
        $gaps = [];
        $length = 0.0;
        foreach (array_reverse($steps) as $step) {
            $e = $this->edges[$step['edge']];
            $part = $e['a'] === $step['from'] ? $e['coords'] : array_reverse($e['coords']);
            // Le premier sommet du morceau est le nœud d'arrivée du précédent (à SNAP_M près) : on
            // repart du dernier sommet posé pour ne pas créer d'aller-retour.
            if ($coords !== []) {
                array_shift($part);
            }
            $start = $length;
            foreach ($part as $p) {
                if ($coords !== []) {
                    $length += ProfilePath::distance($coords[count($coords) - 1], $p);
                }
                $coords[] = $p;
            }
            if ($e['gap']) {
                $gaps[] = [$start, $length];
            }
        }

        // Les bornes des lacunes sont mesurées avec la même fonction et dans le même ordre que les
        // distances cumulées du parcours : elles tombent exactement sur ses sommets.
        return new ProfilePath($coords, $gaps);
    }
}

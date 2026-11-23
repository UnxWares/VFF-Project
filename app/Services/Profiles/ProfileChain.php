<?php

namespace App\Services\Profiles;

/**
 * Assemble les morceaux du tracé de référence d'une ligne (sortie de ST_LineMerge : souvent
 * plusieurs morceaux, coupés aux bifurcations, aux raccordements et aux trous) en UN parcours de
 * bout en bout, celui que suit le profil en long.
 *
 * Méthode : graphe dont les nœuds sont les extrémités des morceaux (fusionnées à moins de
 * `SNAP_M`) et les arêtes les morceaux. Deux composantes séparées de moins de `NEAR_GAP_M` sont
 * reliées par une arête « lacune » entre chaque paire de nœuds assez proches ; les composantes qui
 * restent séparées sont ensuite reliées, quelle que soit la distance, par l'arête la plus courte
 * entre elles (arbre couvrant de Kruskal sur les composantes) : aucun morceau du référentiel n'est
 * laissé de côté parce qu'un trou le sépare du reste. Les lacunes sont des lignes droites,
 * pénalisées pour que le parcours préfère la voie.
 *
 * Le parcours retenu est le plus long des plus courts chemins (pseudo-diamètre : deux recherches
 * de Dijkstra), qui suit l'artère principale et laisse de côté épis, raccordements et boucles de
 * gare, qui sont assemblés ensuite en embranchements (assemble, append). Les lacunes empruntées sont rendues à part :
 * aucune altitude n'y est mesurée, la courbe s'y interrompt.
 *
 * Raccords en T (splitAtTees) : ST_LineMerge ne coupe pas un morceau là où un autre s'y accroche par
 * son bout (raccord posé au milieu d'une voie, pas sur l'un de ses sommets d'extrémité). Sans coupe,
 * le graphe n'a pas de nœud à cet endroit et le parcours ne peut pas tourner : il saute à vol d'oiseau
 * vers le morceau suivant (Moret - Lyon, 23/11/2026 : 75,8 km de faux trous entre Saint-Étienne et
 * Roanne alors que la voie était dessinée en entier). Chaque morceau est donc coupé au point le plus
 * proche de tout bout d'un autre morceau posé à moins de TEE_M de son intérieur.
 */
final class ProfileChain
{
    /** Extrémités plus proches que cela : même nœud (voies d'une gare, arrondis des sources). */
    public const SNAP_M = 30.0;

    /**
     * Lacune sous laquelle toutes les paires de nœuds de deux composantes sont reliées (le parcours
     * choisit le meilleur passage) ; au-delà, une seule arête par paire de composantes.
     */
    public const NEAR_GAP_M = 5000.0;

    /**
     * Bout d'un morceau posé à moins de cela de l'INTÉRIEUR d'un autre : l'autre est coupé au point le
     * plus proche (splitAtTees). Jusqu'à SNAP_M le bout et la coupe ne font qu'un nœud ; au-delà, une
     * courte lacune les relie au lieu d'un saut vers le bout lointain du morceau.
     */
    public const TEE_M = 150.0;

    /** Longueur sous laquelle un reste du référentiel n'est pas ajouté en embranchement. */
    public const MIN_BRANCH_M = 100.0;

    /** Embranchements au plus par ligne (les réseaux miniers en comptent quelques dizaines). */
    private const MAX_BRANCHES = 200;

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
        return self::assemble($parts)['path'] ?? null;
    }

    /**
     * Parcours principal, embranchements et longueur de ce qui reste hors parcours.
     *
     * Les morceaux que le parcours principal n'emprunte pas (épis, raccordements, réseaux en
     * arbre comme les lignes minières) sont assemblés à leur tour, de la même façon, en
     * embranchements : du plus long au plus court, tant qu'il en reste d'au moins MIN_BRANCH_M.
     * Ce qui reste (bouts de moins de MIN_BRANCH_M) est compté dans `off_path_m`.
     *
     * @param  list<list<array{0: float, 1: float}>>  $parts  morceaux du tracé, sommets [lon, lat]
     * @return array{path: ProfilePath, branches: list<ProfilePath>, off_path_m: float}|null
     */
    public static function assemble(array $parts): ?array
    {
        // Coupe aux raccords en T une seule fois : les embranchements reprennent les morceaux déjà coupés.
        $main = (new self)->run(self::splitAtTees($parts));
        if ($main === null) {
            return null;
        }
        $branches = [];
        $rest = $main['rest'];
        while ($rest !== [] && count($branches) < self::MAX_BRANCHES) {
            // Embranchement : une seule composante connexe, sans trou (les trous entre
            // embranchements sont posés par append).
            $branch = (new self)->run($rest, link: false);
            if ($branch === null || $branch['path']->length() < self::MIN_BRANCH_M) {
                break;
            }
            $branches[] = $branch['path'];
            $rest = $branch['rest'];
        }
        $offPath = (float) array_sum(array_map(fn (array $coords): float => (new ProfilePath($coords))->length(), $rest));

        return ['path' => $main['path'], 'branches' => $branches, 'off_path_m' => $offPath];
    }

    /**
     * Parcours principal suivi de ses embranchements, chacun après un trou : un embranchement
     * part de son bout le plus proche de la fin de ce qui précède. Le trou qui précède un
     * embranchement a une longueur conventionnelle (branchJump : 1 % de la ligne principale, 500 m
     * au moins), pas la distance à vol d'oiseau, qui allongerait l'axe de centaines de kilomètres
     * vides. Les trous et les bornes des embranchements tombent exactement sur les sommets
     * (distances cumulées du parcours final).
     *
     * @param  list<ProfilePath>  $branches
     * @return array{path: ProfilePath, branches: list<array{0: float, 1: float}>}
     */
    public static function append(ProfilePath $main, array $branches): array
    {
        if ($branches === []) {
            return ['path' => $main, 'branches' => []];
        }
        $coords = $main->coords;
        $jumps = $main->jumps;
        // [indice du premier sommet, indice du dernier sommet] de chaque morceau ajouté.
        $spans = [];
        $innerGaps = [];
        foreach ($branches as $branch) {
            $last = $coords[count($coords) - 1];
            if (ProfilePath::distance($last, $branch->coords[count($branch->coords) - 1]) < ProfilePath::distance($last, $branch->coords[0])) {
                $branch = $branch->reversed();
            }
            $start = count($coords);
            $jumps[$start] = self::branchJump($main->length());
            foreach ($branch->jumps as $i => $m) {
                $jumps[$start + $i] = $m;
            }
            array_push($coords, ...$branch->coords);
            $spans[] = [$start, count($coords) - 1];
            $innerGaps[] = [$start, $branch];
        }
        $probe = new ProfilePath($coords, [], $jumps);
        $at = fn (int $i): float => $probe->cumulative[$i];
        $gaps = $main->gaps;
        $ranges = [];
        foreach ($spans as $k => [$first, $last]) {
            $gaps[] = [$at($first - 1), $at($first)];
            [$start, $branch] = $innerGaps[$k];
            // Trous propres à l'embranchement, décalés au début de celui-ci.
            foreach ($branch->gaps as [$from, $to]) {
                $gaps[] = [$at($start) + $from, $at($start) + $to];
            }
            $ranges[] = [$at($first), $at($last)];
        }

        return ['path' => new ProfilePath($coords, $gaps, $jumps), 'branches' => $ranges];
    }

    /** Longueur conventionnelle du trou qui précède un embranchement, en mètres. */
    public static function branchJump(float $mainLength): float
    {
        return max(500.0, round($mainLength / 100));
    }

    /**
     * @param  list<list<array{0: float, 1: float}>>  $parts
     * @return array{path: ProfilePath, rest: list<list<array{0: float, 1: float}>>}|null
     */
    private function run(array $parts, bool $link = true): ?array
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
        if ($link) {
            $this->linkComponents();
        }

        // Départ : une extrémité du plus long morceau (toujours dans l'artère principale).
        $longest = array_reduce($this->edges, fn (?array $best, array $e) => $best === null || $e['length'] > $best['length'] ? $e : $best);
        [$distStart] = $this->dijkstra($longest['a']);
        $from = $this->farthest($distStart);
        [$dist, $prev] = $this->dijkstra($from);
        $to = $this->farthest($dist);

        $used = [];
        $path = $this->pathBetween($from, $to, $prev, $used);
        if ($path === null) {
            $path = new ProfilePath($longest['coords']);
            $used = [array_search($longest, $this->edges, true) => true];
        }
        $rest = [];
        foreach ($this->edges as $k => $e) {
            if (! $e['gap'] && ! isset($used[$k])) {
                $rest[] = $e['coords'];
            }
        }

        return ['path' => $path, 'rest' => $rest];
    }

    /**
     * Coupe chaque morceau au point le plus proche de tout bout d'un morceau (lui compris) posé à
     * moins de TEE_M de son intérieur et à plus de SNAP_M de ses propres bouts : un raccord en T
     * devient un nœud du graphe (le bout s'y fond jusqu'à SNAP_M, une courte lacune l'y relie au-delà). Calcul plan local (équirectangulaire), exact à mieux que 0,1 m à
     * cette distance.
     *
     * @param  list<list<array{0: float, 1: float}>>  $parts
     * @return list<list<array{0: float, 1: float}>>
     */
    public static function splitAtTees(array $parts): array
    {
        $ends = [];
        foreach ($parts as $coords) {
            if (count($coords) >= 2) {
                $ends[] = $coords[0];
                $ends[] = $coords[count($coords) - 1];
            }
        }
        if (count($ends) < 4) {
            return $parts;
        }
        $lat0 = deg2rad($ends[0][1]);
        $kx = cos($lat0) * 111_320.0;
        $ky = 110_574.0;
        $out = [];
        foreach ($parts as $coords) {
            $n = count($coords);
            if ($n < 2) {
                $out[] = $coords;

                continue;
            }
            $xy = array_map(fn (array $c): array => [$c[0] * $kx, $c[1] * $ky], $coords);
            $xs = array_column($xy, 0);
            $ys = array_column($xy, 1);
            [$minX, $maxX, $minY, $maxY] = [min($xs) - self::TEE_M, max($xs) + self::TEE_M, min($ys) - self::TEE_M, max($ys) + self::TEE_M];
            $cuts = [];
            foreach ($ends as $p) {
                $px = $p[0] * $kx;
                $py = $p[1] * $ky;
                if ($px < $minX || $px > $maxX || $py < $minY || $py > $maxY
                    || ProfilePath::distance($p, $coords[0]) <= self::SNAP_M || ProfilePath::distance($p, $coords[$n - 1]) <= self::SNAP_M) {
                    continue;
                }
                $best = null;
                for ($i = 0; $i < $n - 1; $i++) {
                    [$ax, $ay] = $xy[$i];
                    [$bx, $by] = $xy[$i + 1];
                    if (min($ax, $bx) - self::TEE_M > $px || max($ax, $bx) + self::TEE_M < $px
                        || min($ay, $by) - self::TEE_M > $py || max($ay, $by) + self::TEE_M < $py) {
                        continue;
                    }
                    $dx = $bx - $ax;
                    $dy = $by - $ay;
                    $len2 = $dx * $dx + $dy * $dy;
                    $t = $len2 > 0 ? max(0.0, min(1.0, (($px - $ax) * $dx + ($py - $ay) * $dy) / $len2)) : 0.0;
                    $d = hypot($ax + $t * $dx - $px, $ay + $t * $dy - $py);
                    if ($d <= self::TEE_M && ($best === null || $d < $best[2])) {
                        $best = [$i, $t, $d];
                    }
                }
                if ($best !== null) {
                    $cuts[] = [$best[0], $best[1]];
                }
            }
            if ($cuts === []) {
                $out[] = $coords;

                continue;
            }
            usort($cuts, fn (array $a, array $b): int => $a[0] <=> $b[0] ?: $a[1] <=> $b[1]);
            $piece = [$coords[0]];
            $next = 0;
            for ($i = 0; $i < $n - 1; $i++) {
                while ($next < count($cuts) && $cuts[$next][0] === $i) {
                    $t = $cuts[$next][1];
                    $q = [$coords[$i][0] + $t * ($coords[$i + 1][0] - $coords[$i][0]), $coords[$i][1] + $t * ($coords[$i + 1][1] - $coords[$i][1])];
                    if ($q !== $piece[count($piece) - 1]) {
                        $piece[] = $q;
                    }
                    if (count($piece) >= 2) {
                        $out[] = $piece;
                    }
                    $piece = [$q];
                    $next++;
                }
                if ($coords[$i + 1] !== $piece[count($piece) - 1]) {
                    $piece[] = $coords[$i + 1];
                }
            }
            if (count($piece) >= 2) {
                $out[] = $piece;
            }
        }

        return $out;
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

    /**
     * Arêtes « lacune » : entre nœuds de composantes différentes à moins de NEAR_GAP_M, puis, tant
     * que le graphe n'est pas connexe, la plus courte arête entre deux composantes encore séparées
     * (Kruskal), si loin qu'elles soient.
     */
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
        $candidates = [];
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                if ($find($i) === $find($j)) {
                    continue;
                }
                $d = ProfilePath::distance($this->nodes[$i], $this->nodes[$j]);
                if ($d <= self::NEAR_GAP_M) {
                    $this->addGap($i, $j, $d);
                }
                $candidates[] = [$d, $i, $j];
            }
        }
        // Kruskal : les arêtes ajoutées ci-dessus comptent déjà (deux composantes proches sont
        // reliées), puis les plus courtes arêtes restantes jusqu'à ce que tout soit relié.
        usort($candidates, fn (array $a, array $b): int => $a[0] <=> $b[0]);
        foreach ($candidates as [$d, $i, $j]) {
            $ri = $find($i);
            $rj = $find($j);
            if ($ri === $rj) {
                continue;
            }
            $parent[$ri] = $rj;
            if ($d > self::NEAR_GAP_M) {
                $this->addGap($i, $j, $d);
            }
        }
    }

    private function addGap(int $i, int $j, float $d): void
    {
        $this->edges[] = ['a' => $i, 'b' => $j, 'coords' => [$this->nodes[$i], $this->nodes[$j]], 'length' => $d, 'gap' => true];
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
     * @param  array<int, true>  $used  rempli avec les indices des arêtes empruntées
     */
    private function pathBetween(int $from, int $to, array $prev, array &$used): ?ProfilePath
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
            $used[$step['edge']] = true;
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

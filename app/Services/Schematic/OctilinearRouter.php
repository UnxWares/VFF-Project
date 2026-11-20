<?php

namespace App\Services\Schematic;

use SplPriorityQueue;

/**
 * Routage des axes du plan schématique sur une grille octilinéaire (pas horizontaux, verticaux et à
 * 45°), un axe après l'autre, dans l'esprit des plans de métro sur grille (Bast, Brosi et Storandt,
 * « Metro Maps on Octilinear Grid Graphs », EuroVis 2020), en plus simple : pas de déplacement des
 * nœuds pendant le routage.
 *
 * Chaque axe est le plus court chemin (A*) entre les points de ses deux nœuds, où un chemin coûte sa
 * longueur, plus :
 *   - chaque coude (45°, 90°, 135° ; le demi-tour est interdit) ;
 *   - l'écart au tracé réel simplifié (`guide`), au-delà d'une marge : le plan garde la forme
 *     générale du réseau ;
 *   - un départ ou une arrivée dans une autre direction que le tracé réel : l'ordre des axes autour
 *     d'un nœud reste celui de la carte ;
 *   - les pas déjà pris par un autre axe (superposition), les croisements, et le passage par un
 *     autre nœud (qui ferait croire à une correspondance). Un croisement coûte peu quand les deux
 *     voies se croisent aussi sur le terrain (pont, saut-de-mouton : `realCrossings`), beaucoup sinon.
 * Ces pénalités sont fortes mais finies : un axe trouve toujours un chemin, et un nœud de plus de
 * huit axes reste dessinable (les axes se rejoignent alors en faisceau).
 *
 * Un axe déjà routé peut être routé de nouveau (`reroute`) quand les axes tracés après lui lui
 * laissent un meilleur chemin (SchematicLayout : reprise des axes qui en croisent d'autres).
 *
 * Coordonnées : entiers (i, j) de la grille, j vers le nord. Directions 0 à 7 : est, nord-est, nord,
 * nord-ouest, ouest, sud-ouest, sud, sud-est (angle = 45° × direction).
 */
final class OctilinearRouter
{
    /** @var list<array{0: int, 1: int}> pas de chaque direction */
    public const DIRS = [[1, 0], [1, 1], [0, 1], [-1, 1], [-1, 0], [-1, -1], [0, -1], [1, -1]];

    private const OFFSET = 1 << 20;

    private const SPAN = 1 << 21;

    /** @var array<int, int> pas de grille pris (clé d'arête non orientée) → nombre d'axes */
    private array $edgeUse = [];

    /** @var array<int, array<int, int>> diagonales prises (case et orientation) → axes qui la prennent */
    private array $diagUse = [];

    /** @var array<int, array<int, int>> points traversés par un axe (hors extrémités) → axes qui le traversent */
    private array $pointUse = [];

    /** @var array<int, int> point → nœud du plan qui l'occupe */
    private array $placeAt = [];

    /** @var array<int, array<int, true>> axes dont la voie croise celle de chaque axe sur le terrain */
    private array $realCrossings = [];

    /** Axe en cours de routage (numéro donné par l'appelant). */
    private int $axis = -1;

    /** Axes routés par le tracé de secours (recherche trop longue). */
    public int $fallbacks = 0;

    public function __construct(private readonly SchematicOptions $options) {}

    public static function key(int $i, int $j): int
    {
        return ($i + self::OFFSET) * self::SPAN + ($j + self::OFFSET);
    }

    public function occupy(int $i, int $j, int $place): void
    {
        $this->placeAt[self::key($i, $j)] = $place;
    }

    public function placeAt(int $i, int $j): ?int
    {
        return $this->placeAt[self::key($i, $j)] ?? null;
    }

    /**
     * Paires d'axes dont les voies se croisent sur le terrain (sans jonction) : leur croisement sur
     * le plan coûte `crossCost`, celui de deux autres axes `falseCrossCost`.
     *
     * @param  array<int, array<int, true>>  $pairs  axe → axes qu'il croise
     */
    public function realCrossings(array $pairs): void
    {
        $this->realCrossings = $pairs;
    }

    /** Nombre de pas de grille pris par plus d'un axe (superpositions). */
    public function overlaps(): int
    {
        return count(array_filter($this->edgeUse, fn (int $n): bool => $n > 1));
    }

    /**
     * Croisements sans jonction du plan : points de la grille traversés par plusieurs axes (hors de
     * leurs extrémités et des nœuds) et cases où les deux diagonales sont prises. Avec `$real`, ceux
     * dont les deux voies se croisent aussi sur le terrain ; avec `$real` à faux, les autres.
     */
    public function crossingCount(?bool $real = null): int
    {
        $count = 0;
        foreach ($this->pointUse as $key => $axes) {
            if (count($axes) > 1 && ! isset($this->placeAt[$key]) && ($real === null || $this->allReal(array_keys($axes)) === $real)) {
                $count++;
            }
        }
        foreach ($this->diagUse as $key => $axes) {
            if ($key % 2 === 0 && isset($this->diagUse[$key + 1])
                && ($real === null || $this->allReal([...array_keys($axes), ...array_keys($this->diagUse[$key + 1])]) === $real)) {
                $count++;
            }
        }

        return $count;
    }

    /** @param  list<int>  $axes */
    private function allReal(array $axes): bool
    {
        $n = count($axes);
        for ($a = 0; $a < $n; $a++) {
            for ($b = $a + 1; $b < $n; $b++) {
                if (! isset($this->realCrossings[$axes[$a]][$axes[$b]])) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Coût de la traversée d'un point ou d'une diagonale déjà pris par d'autres axes.
     *
     * @param  array<int, int>  $owners  axe → nombre de passages
     */
    private function crossingCost(array $owners): float
    {
        $cost = 0.0;
        $real = $this->realCrossings[$this->axis] ?? [];
        foreach ($owners as $other => $n) {
            if ($other !== $this->axis) {
                $cost += $n * (isset($real[$other]) ? $this->options->crossCost : $this->options->falseCrossCost);
            }
        }

        return $cost;
    }

    /** Points de nœuds du plan traversés par un axe qui n'y finit pas (fausses correspondances). */
    public function nodePasses(): int
    {
        return count(array_intersect_key($this->pointUse, $this->placeAt));
    }

    /**
     * Chemin d'un axe, de `$from` à `$to` (points de la grille), sous forme de sommets (points où la
     * direction change, extrémités comprises). Le chemin est ensuite réservé.
     *
     * @param  array{0: int, 1: int}  $from
     * @param  array{0: int, 1: int}  $to
     * @param  list<array{0: float, 1: float}>  $guide  tracé réel simplifié, en unités de grille, de $from à $to
     * @param  float  $startBearing  direction du tracé réel au départ (degrés, 0 = est, 90 = nord)
     * @param  float  $endBearing  direction du tracé réel à l'arrivée (sens de la marche)
     * @param  int  $axis  numéro de l'axe (croisements réels, `realCrossings`)
     * @return list<array{0: int, 1: int}>
     */
    public function route(array $from, array $to, array $guide, float $startBearing, float $endBearing, int $axis = -1): array
    {
        $this->axis = $axis;
        if ($from === $to) {
            return [$from];
        }
        $cells = $this->search($from, $to, $guide, $startBearing, $endBearing);
        if ($cells === null) {
            $this->fallbacks++;
            $cells = $this->elbow($from, $to, $guide);
        }
        $this->reserve($cells, 1);

        return self::corners($cells);
    }

    /**
     * Nouveau chemin pour un axe déjà routé (`$corners`, son chemin actuel) : l'ancien chemin est
     * libéré, puis l'axe est routé de nouveau avec les autres axes en place. Le chemin trouvé coûte au
     * plus autant que l'ancien (A* donne le chemin le moins cher, l'ancien en fait partie) ; si la
     * recherche n'aboutit pas, l'ancien chemin est gardé.
     *
     * @param  list<array{0: int, 1: int}>  $corners
     * @param  list<array{0: float, 1: float}>  $guide
     * @return list<array{0: int, 1: int}>
     */
    public function reroute(array $corners, array $guide, float $startBearing, float $endBearing, int $axis = -1): array
    {
        $this->axis = $axis;
        $cells = self::cells($corners);
        if (count($cells) < 2) {
            return $corners;
        }
        $this->reserve($cells, -1);
        $found = $this->search($cells[0], $cells[count($cells) - 1], $guide, $startBearing, $endBearing);
        $this->reserve($found ?? $cells, 1);

        return $found === null ? $corners : self::corners($found);
    }

    /**
     * Défauts d'un axe routé : points de la grille qu'un autre axe traverse aussi (croisement ou
     * superposition), nœuds traversés, cases dont l'autre diagonale est prise.
     *
     * @param  list<array{0: int, 1: int}>  $corners
     */
    public function conflicts(array $corners): int
    {
        $cells = self::cells($corners);
        $count = 0;
        $last = count($cells) - 1;
        for ($k = 0; $k < $last; $k++) {
            [$i, $j] = $cells[$k];
            [$ni, $nj] = $cells[$k + 1];
            if ($k > 0) {
                $key = self::key($i, $j);
                if (count($this->pointUse[$key] ?? []) > 1 || isset($this->placeAt[$key])) {
                    $count++;
                }
            }
            if ($i !== $ni && $j !== $nj) {
                $cell = self::key(min($i, $ni), min($j, $nj)) * 2 + ($ni - $i === $nj - $j ? 1 : 0);
                if (isset($this->diagUse[$cell])) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Points de la grille d'un chemin donné par ses sommets.
     *
     * @param  list<array{0: int, 1: int}>  $corners
     * @return list<array{0: int, 1: int}>
     */
    public static function cells(array $corners): array
    {
        $cells = $corners === [] ? [] : [$corners[0]];
        $n = count($corners);
        for ($k = 1; $k < $n; $k++) {
            [$i, $j] = $corners[$k - 1];
            [$ti, $tj] = $corners[$k];
            $di = $ti <=> $i;
            $dj = $tj <=> $j;
            while ($i !== $ti || $j !== $tj) {
                $i += $di;
                $j += $dj;
                $cells[] = [$i, $j];
            }
        }

        return $cells;
    }

    /**
     * A* sur les états (point, direction d'arrivée).
     *
     * @param  array{0: int, 1: int}  $from
     * @param  array{0: int, 1: int}  $to
     * @param  list<array{0: float, 1: float}>  $guide
     * @return list<array{0: int, 1: int}>|null
     */
    private function search(array $from, array $to, array $guide, float $startBearing, float $endBearing): ?array
    {
        $o = $this->options;
        [$si, $sj] = $from;
        [$ti, $tj] = $to;

        // Fenêtre de recherche : emprise des extrémités et du tracé réel, plus une marge.
        $imin = min($si, $ti);
        $imax = max($si, $ti);
        $jmin = min($sj, $tj);
        $jmax = max($sj, $tj);
        foreach ($guide as [$gx, $gy]) {
            $imin = min($imin, (int) floor($gx));
            $imax = max($imax, (int) ceil($gx));
            $jmin = min($jmin, (int) floor($gy));
            $jmax = max($jmax, (int) ceil($gy));
        }
        $margin = max(3, (int) ceil(0.25 * max($imax - $imin, $jmax - $jmin)));
        $imin -= $margin;
        $imax += $margin;
        $jmin -= $margin;
        $jmax += $margin;
        $h = $jmax - $jmin + 1;

        $bend = [0.0, $o->bend45, $o->bend90, $o->bend135];
        $free = max($o->geoFree, $o->geoFreeRatio * hypot($ti - $si, $tj - $sj));
        $sqrt2 = M_SQRT2;
        $targetKey = self::key($ti, $tj);
        $startPorts = [];
        $endPorts = [];
        for ($d = 0; $d < 8; $d++) {
            $startPorts[$d] = $o->portWeight * self::turns($d * 45.0, $startBearing);
            $endPorts[$d] = $o->portWeight * self::turns($d * 45.0, $endBearing);
        }

        /** @var array<int, float> $geo écart au tracé réel, par point de la fenêtre */
        $geo = [];
        $g = [];
        $came = [];
        $open = new SplPriorityQueue;
        $open->setExtractFlags(SplPriorityQueue::EXTR_DATA);
        $seq = 0;
        $start = ((($si - $imin) * $h) + ($sj - $jmin)) * 9 + 8;
        $g[$start] = 0.0;
        $open->insert($start, [-self::octile($ti - $si, $tj - $sj), 0]);
        $closed = [];
        $expanded = 0;

        while (! $open->isEmpty()) {
            $state = $open->extract();
            if (isset($closed[$state])) {
                continue;
            }
            $closed[$state] = true;
            if (++$expanded > $o->maxExpansions) {
                return null;
            }
            $din = $state % 9;
            $local = intdiv($state, 9);
            $i = intdiv($local, $h) + $imin;
            $j = $local % $h + $jmin;
            if ($i === $ti && $j === $tj) {
                return $this->unwind($state, $came, $imin, $jmin, $h);
            }
            $base = $g[$state];
            $pk = self::key($i, $j);

            foreach (self::DIRS as $d => [$dx, $dy]) {
                $cost = 0.0;
                if ($din === 8) {
                    $cost += $startPorts[$d];
                } else {
                    $turn = abs($d - $din);
                    $turn = min($turn, 8 - $turn);
                    if ($turn === 4) {
                        continue;
                    }
                    $cost += $bend[$turn];
                }
                $ni = $i + $dx;
                $nj = $j + $dy;
                if ($ni < $imin || $ni > $imax || $nj < $jmin || $nj > $jmax) {
                    continue;
                }
                $diagonal = ($d & 1) === 1;
                $step = $diagonal ? $sqrt2 : 1.0;
                $cost += $step;
                $nk = self::key($ni, $nj);
                $edge = $pk < $nk ? $pk * 8 + $d : $nk * 8 + (($d + 4) % 8);
                if (isset($this->edgeUse[$edge])) {
                    $cost += $o->overlapCost * $this->edgeUse[$edge];
                }
                if ($diagonal) {
                    $crossing = self::key(min($i, $ni), min($j, $nj)) * 2 + ($dx === $dy ? 1 : 0);
                    if (isset($this->diagUse[$crossing])) {
                        $cost += $this->crossingCost($this->diagUse[$crossing]);
                    }
                }
                $nlocal = ($ni - $imin) * $h + ($nj - $jmin);
                if ($nk === $targetKey) {
                    $cost += $endPorts[$d];
                } else {
                    if (isset($this->placeAt[$nk])) {
                        $cost += $o->placeCost;
                    }
                    if (isset($this->pointUse[$nk])) {
                        $cost += $this->crossingCost($this->pointUse[$nk]);
                    }
                }
                if (! isset($geo[$nlocal])) {
                    $geo[$nlocal] = self::distanceToPolyline((float) $ni, (float) $nj, $guide);
                }
                $off = $geo[$nlocal] - $free;
                if ($off > 0) {
                    $cost += $o->geoWeight * $off * $step;
                }

                $next = $nlocal * 9 + $d;
                $candidate = $base + $cost;
                if (isset($closed[$next]) || (isset($g[$next]) && $g[$next] <= $candidate)) {
                    continue;
                }
                $g[$next] = $candidate;
                $came[$next] = $state;
                $open->insert($next, [-($candidate + self::octile($ti - $ni, $tj - $nj)), -(++$seq)]);
            }
        }

        return null;
    }

    /**
     * @param  array<int, int>  $came
     * @return list<array{0: int, 1: int}>
     */
    private function unwind(int $state, array $came, int $imin, int $jmin, int $h): array
    {
        $cells = [];
        while (true) {
            $local = intdiv($state, 9);
            $cells[] = [intdiv($local, $h) + $imin, $local % $h + $jmin];
            if (! isset($came[$state])) {
                break;
            }
            $state = $came[$state];
        }

        return array_reverse($cells);
    }

    /**
     * Tracé de secours à un coude (diagonale puis droite, ou l'inverse : celui qui passe le plus près
     * du milieu du tracé réel), point par point.
     *
     * @param  array{0: int, 1: int}  $from
     * @param  array{0: int, 1: int}  $to
     * @param  list<array{0: float, 1: float}>  $guide
     * @return list<array{0: int, 1: int}>
     */
    private function elbow(array $from, array $to, array $guide): array
    {
        $dx = $to[0] - $from[0];
        $dy = $to[1] - $from[1];
        $diag = min(abs($dx), abs($dy));
        $sx = $dx <=> 0;
        $sy = $dy <=> 0;
        $straight = abs($dx) > abs($dy) ? [$sx, 0] : [0, $sy];
        $bendA = [$from[0] + $diag * $sx, $from[1] + $diag * $sy];
        $bendB = [$to[0] - $diag * $sx, $to[1] - $diag * $sy];
        $mid = $guide[intdiv(count($guide), 2)] ?? [($from[0] + $to[0]) / 2, ($from[1] + $to[1]) / 2];
        $useA = hypot($bendA[0] - $mid[0], $bendA[1] - $mid[1]) <= hypot($bendB[0] - $mid[0], $bendB[1] - $mid[1]);
        $legs = $useA ? [[[$sx, $sy], $diag], [$straight, max(abs($dx), abs($dy)) - $diag]]
            : [[$straight, max(abs($dx), abs($dy)) - $diag], [[$sx, $sy], $diag]];

        $cells = [$from];
        [$i, $j] = $from;
        foreach ($legs as [[$ux, $uy], $n]) {
            for ($k = 0; $k < $n; $k++) {
                $i += $ux;
                $j += $uy;
                $cells[] = [$i, $j];
            }
        }

        return $cells;
    }

    /**
     * Prend (`$sign` = 1) ou libère (-1) les pas, diagonales et points d'un chemin.
     *
     * @param  list<array{0: int, 1: int}>  $cells
     */
    private function reserve(array $cells, int $sign): void
    {
        $last = count($cells) - 1;
        for ($k = 0; $k < $last; $k++) {
            [$i, $j] = $cells[$k];
            [$ni, $nj] = $cells[$k + 1];
            $dx = $ni - $i;
            $dy = $nj - $j;
            $d = array_search([$dx, $dy], self::DIRS, true);
            if ($d === false) {
                continue;
            }
            $pk = self::key($i, $j);
            $nk = self::key($ni, $nj);
            $edge = $pk < $nk ? $pk * 8 + $d : $nk * 8 + (($d + 4) % 8);
            $n = ($this->edgeUse[$edge] ?? 0) + $sign;
            if ($n > 0) {
                $this->edgeUse[$edge] = $n;
            } else {
                unset($this->edgeUse[$edge]);
            }
            if (($d & 1) === 1) {
                self::own($this->diagUse, self::key(min($i, $ni), min($j, $nj)) * 2 + ($dx === $dy ? 0 : 1), $this->axis, $sign);
            }
            if ($k > 0) {
                self::own($this->pointUse, $pk, $this->axis, $sign);
            }
        }
    }

    /**
     * Ajoute (`$sign` = 1) ou retire (-1) un passage de l'axe `$axis` en `$key`.
     *
     * @param  array<int, array<int, int>>  $use
     */
    private static function own(array &$use, int $key, int $axis, int $sign): void
    {
        $n = ($use[$key][$axis] ?? 0) + $sign;
        if ($n > 0) {
            $use[$key][$axis] = $n;
        } else {
            unset($use[$key][$axis]);
            if (($use[$key] ?? []) === []) {
                unset($use[$key]);
            }
        }
    }

    /**
     * Sommets d'un chemin : extrémités et points où la direction change.
     *
     * @param  list<array{0: int, 1: int}>  $cells
     * @return list<array{0: int, 1: int}>
     */
    public static function corners(array $cells): array
    {
        $n = count($cells);
        if ($n <= 2) {
            return $cells;
        }
        $out = [$cells[0]];
        for ($k = 1; $k < $n - 1; $k++) {
            $in = [$cells[$k][0] - $cells[$k - 1][0], $cells[$k][1] - $cells[$k - 1][1]];
            $outDir = [$cells[$k + 1][0] - $cells[$k][0], $cells[$k + 1][1] - $cells[$k][1]];
            if ($in !== $outDir) {
                $out[] = $cells[$k];
            }
        }
        $out[] = $cells[$n - 1];

        return $out;
    }

    /** Écart entre deux directions, en tranches de 45° (0 à 4). */
    public static function turns(float $a, float $b): float
    {
        $diff = fmod(abs($a - $b), 360.0);

        return min($diff, 360.0 - $diff) / 45.0;
    }

    /** Longueur du plus court chemin octilinéaire sans obstacle (minorant du coût). */
    private static function octile(int $dx, int $dy): float
    {
        $ax = abs($dx);
        $ay = abs($dy);

        return max($ax, $ay) + (M_SQRT2 - 1) * min($ax, $ay);
    }

    /** @param  list<array{0: float, 1: float}>  $line */
    public static function distanceToPolyline(float $x, float $y, array $line): float
    {
        $n = count($line);
        if ($n === 0) {
            return 0.0;
        }
        if ($n === 1) {
            return hypot($x - $line[0][0], $y - $line[0][1]);
        }
        $best = INF;
        for ($k = 1; $k < $n; $k++) {
            [$ax, $ay] = $line[$k - 1];
            [$bx, $by] = $line[$k];
            $vx = $bx - $ax;
            $vy = $by - $ay;
            $len2 = $vx * $vx + $vy * $vy;
            $t = $len2 > 0 ? max(0.0, min(1.0, (($x - $ax) * $vx + ($y - $ay) * $vy) / $len2)) : 0.0;
            $d = hypot($x - ($ax + $t * $vx), $y - ($ay + $t * $vy));
            if ($d < $best) {
                $best = $d;
            }
        }

        return $best;
    }
}

<?php

namespace App\Support;

/**
 * Raccord de jonction LISSÉ (géométrie dessinée, BuildSharedTrack règle 4) : remplace le bout d'une branche qui
 * arrive sur une autre ligne en coude, en crochet (« L ») ou en biseau par une courbe tangente des deux côtés,
 * comme un vrai aiguillage.
 *
 * Calcul pur, en mètres (Lambert-93), sans base de données :
 *   - approche A : bout de la branche, du point de jonction P (A[0], posé sur la ligne principale) vers l'amont ;
 *   - ligne principale W : morceau dessiné de la ligne rejointe autour de P.
 * Le cap de la branche est pris EN AMONT du coude (jamais la projection orthogonale du bout) : départ D à sD mètres de
 * P, tangent à la branche ; arrivée J sur W, glissée le long de W dans le sens de la branche, tangente à W à
 * l'angle d'aiguillage ALPHAS[0] près (tg 0,11 ≈ 6,3° : aiguillages courants du RFN ; 10°, puis 15° au plus si la
 * place manque). Construction des tangentes : la tangente en D et la tangente d'arrivée en J se coupent en V à la
 * même distance T de D et de J (T = y / (sin θ + sin α), y = écart de D à W, θ = angle de convergence de la
 * branche) ; la courbe est la cubique de Bézier (Hermite) qui approche l'arc de cercle inscrit (rayon
 * R = T / tan(|θ - α| / 2)), échantillonnée tous les ~2 m : tangente en D et en J, courbure continue, sans cassure.
 * Choix : le plus petit α, puis le départ le plus proche qui donne un rayon >= rTarget ; à défaut, le plus grand
 * rayon >= rFloor. Plafonds : départ à au plus SD_MAX m, courbe d'au plus LEN_MAX m, convergence d'au plus
 * THETA_MAX (au-delà, branche perpendiculaire : pas un aiguillage, laissée telle quelle).
 */
class JunctionCurve
{
    /** Angles d'arrivée essayés (°) : aiguillage tg 0,11 (6,3°), puis 10° et 15° (jamais au-delà). */
    public const ALPHAS = [6.28, 10.0, 15.0];

    /** Départ de la courbe : au plus SD_MAX m en amont du point de jonction, au moins SD_MIN m. */
    public const SD_MAX = 200.0;

    public const SD_MIN = 15.0;

    /** Pas (m) des départs essayés. */
    public const SD_STEP = 5.0;

    /** Longueur max (m) de la courbe de raccordement. */
    public const LEN_MAX = 250.0;

    /** Convergence max (°) de la branche vers la ligne principale. */
    public const THETA_MAX = 60.0;

    /** Coude (°, cordes de ±10 m) au-delà duquel le bout est à lisser ; cherché sur les COUDE_SPAN_M derniers m. */
    public const COUDE_DEG = 25.0;

    public const COUDE_SPAN_M = 120.0;

    /** Cassure (°, cordes de ±10 m) qui marque un coude : la courbe part en amont du dernier (+10 m). */
    public const COUDE_MARK_DEG = 10.0;

    /** Arrivée (°) au-delà de laquelle le bout est à lisser (angle des 10 derniers m avec la ligne principale). */
    public const ARRIVAL_DEG = 12.0;

    /** Branche précise (BD TOPO, OSM…) : lissée seulement si son coude dépasse ce seuil (rayon < 32 m : artefact). */
    public const PRECISE_COUDE_DEG = 35.0;

    /**
     * @param  list<array{0: float, 1: float}>  $a  approche, du point de jonction vers l'amont
     * @param  list<array{0: float, 1: float}>  $w  ligne principale dessinée autour du point de jonction
     * @param  float|null  $sBlock  distance (m, depuis P) du premier contact d'une autre voie avec l'approche : départ avant
     * @return array{sD: float, alpha: float, radius: float, length: float, sJ: float, points: list<array{0: float, 1: float}>}|null
     */
    public static function plan(array $a, array $w, float $rTarget, float $rFloor, ?float $sBlock = null, bool $precise = false): ?array
    {
        $ca = self::cumul($a);
        $cw = self::cumul($w);
        $la = end($ca);
        $lw = end($cw);
        if ($la < 25.0 || $lw < 10.0) {
            return null;
        }
        // Coudes de l'approche (cordes de ±10 m) : le départ se fait en amont du dernier coude marqué.
        $sCoude = 0.0;
        $maxTurn = 0.0;
        for ($s = 10.0; $s <= min(self::COUDE_SPAN_M, $la - 10.0); $s += 2.0) {
            $t = self::turn(self::at($a, $ca, $s + 10.0), self::at($a, $ca, $s), self::at($a, $ca, $s - 10.0));
            $maxTurn = max($maxTurn, $t);
            if ($t > self::COUDE_MARK_DEG) {
                $sCoude = $s;
            }
        }
        $p = $a[0];
        $sP = self::locate($w, $cw, $p)['s'];
        $arrival = self::lineAngle(self::sub(self::at($a, $ca, min(10.0, $la)), $p), self::tangent($w, $cw, $sP));
        if ($maxTurn <= ($precise ? self::PRECISE_COUDE_DEG : self::COUDE_DEG) && ($precise || $arrival <= self::ARRIVAL_DEG)) {
            return null;
        }
        $sMax = min(self::SD_MAX, $la - 20.0, $sBlock !== null ? $sBlock - 2.0 : INF);
        $best = null;
        foreach (self::ALPHAS as $alphaDeg) {
            $alpha = deg2rad($alphaDeg);
            for ($sD = max(self::SD_MIN, $sCoude + 10.0); $sD <= $sMax; $sD += self::SD_STEP) {
                $c = self::candidate($a, $ca, $w, $cw, $sD, $alpha);
                if ($c === null || $c['radius'] < $rFloor) {
                    continue;
                }
                $c['alpha'] = $alphaDeg;
                if ($c['radius'] >= $rTarget) {
                    return $c;
                }
                if ($best === null || $c['radius'] > $best['radius']) {
                    $best = $c;
                }
            }
            if ($best !== null) {
                return $best;
            }
        }

        return null;
    }

    /**
     * Courbe partant à sD m de P, arrivée à l'angle alpha (rad).
     *
     * @param  list<array{0: float, 1: float}>  $a
     * @param  list<float>  $ca
     * @param  list<array{0: float, 1: float}>  $w
     * @param  list<float>  $cw
     * @return array{sD: float, alpha: float, radius: float, length: float, sJ: float, points: list<array{0: float, 1: float}>}|null
     */
    private static function candidate(array $a, array $ca, array $w, array $cw, float $sD, float $alpha): ?array
    {
        $la = end($ca);
        $d = self::at($a, $ca, $sD);
        $h = self::unit(self::sub($d, self::at($a, $ca, min($la, $sD + 20.0))));
        if ($h === null) {
            return null;
        }
        $foot = self::locate($w, $cw, $d);
        $y = $foot['d'];
        if ($y < 0.5) {
            return null;
        }
        $t0 = self::tangent($w, $cw, $foot['s']);
        $sign = self::dot($h, $t0) >= 0 ? 1.0 : -1.0;
        $t0 = [$t0[0] * $sign, $t0[1] * $sign];
        $n = self::unit(self::sub($d, $foot['p']));
        if ($n === null) {
            return null;
        }
        $sinT = -self::dot($h, $n);
        $cosT = self::dot($h, $t0);
        $theta = atan2($sinT, $cosT);
        if (rad2deg(abs($theta)) > self::THETA_MAX || $sinT + sin($alpha) <= 0.02) {
            return null;
        }
        $tLen = $y / ($sinT + sin($alpha));
        if (2 * $tLen > self::LEN_MAX) {
            return null;
        }
        $sJ = $foot['s'] + $sign * $tLen * (cos($theta) + cos($alpha));
        $lw = end($cw);
        if ($sJ < 0 || $sJ > $lw) {
            return null;
        }
        $j = self::at($w, $cw, $sJ);
        $tj = self::tangent($w, $cw, $sJ);
        $tj = [$tj[0] * $sign, $tj[1] * $sign];
        // Côté de la branche en J (même côté que D) : normale de tj vers la branche.
        $nj = [-$tj[1], $tj[0]];
        if (self::dot($nj, self::sub($d, $j)) < 0) {
            $nj = [-$nj[0], -$nj[1]];
        }
        $arr = [cos($alpha) * $tj[0] - sin($alpha) * $nj[0], cos($alpha) * $tj[1] - sin($alpha) * $nj[1]];
        $phi = abs($theta - $alpha);
        $cc = $phi > 1e-4 ? (4 / 3) * tan($phi / 4) * $tLen / tan($phi / 2) : (2 / 3) * $tLen;
        $p1 = [$d[0] + $cc * $h[0], $d[1] + $cc * $h[1]];
        $p2 = [$j[0] - $cc * $arr[0], $j[1] - $cc * $arr[1]];
        $chord = hypot($j[0] - $d[0], $j[1] - $d[1]);
        $nSeg = max(8, (int) ceil($chord / 2.0));
        $pts = [];
        for ($i = 0; $i <= $nSeg; $i++) {
            $u = $i / $nSeg;
            $v = 1 - $u;
            $pts[] = [
                $v * $v * $v * $d[0] + 3 * $v * $v * $u * $p1[0] + 3 * $v * $u * $u * $p2[0] + $u * $u * $u * $j[0],
                $v * $v * $v * $d[1] + 3 * $v * $v * $u * $p1[1] + 3 * $v * $u * $u * $p2[1] + $u * $u * $u * $j[1],
            ];
        }
        $pts[0] = $d;
        $pts[$nSeg] = $j;
        $cp = self::cumul($pts);
        $length = $cp[count($cp) - 1];
        if ($length > self::LEN_MAX) {
            return null;
        }

        return ['sD' => $sD, 'alpha' => rad2deg($alpha), 'radius' => self::minRadius($pts), 'length' => $length, 'sJ' => $sJ, 'points' => $pts];
    }

    /**
     * Plus petit rayon (m) d'une polyligne : cercle circonscrit de trois points distants d'au moins 6 m.
     *
     * @param  list<array{0: float, 1: float}>  $pts
     */
    public static function minRadius(array $pts): float
    {
        $c = self::cumul($pts);
        $len = end($c);
        $r = INF;
        for ($s = 6.0; $s <= $len - 6.0; $s += 2.0) {
            $p0 = self::at($pts, $c, $s - 6.0);
            $p1 = self::at($pts, $c, $s);
            $p2 = self::at($pts, $c, $s + 6.0);
            $area2 = abs(($p1[0] - $p0[0]) * ($p2[1] - $p0[1]) - ($p1[1] - $p0[1]) * ($p2[0] - $p0[0]));
            if ($area2 > 1e-9) {
                $r = min($r, hypot($p1[0] - $p0[0], $p1[1] - $p0[1]) * hypot($p2[0] - $p1[0], $p2[1] - $p1[1]) * hypot($p2[0] - $p0[0], $p2[1] - $p0[1]) / (2 * $area2));
            }
        }

        return $r;
    }

    /**
     * Angle (°) entre la corde [a, b] et la corde [b, c].
     *
     * @param  array{0: float, 1: float}  $a
     * @param  array{0: float, 1: float}  $b
     * @param  array{0: float, 1: float}  $c
     */
    public static function turn(array $a, array $b, array $c): float
    {
        $u = self::sub($b, $a);
        $v = self::sub($c, $b);
        $nu = hypot($u[0], $u[1]);
        $nv = hypot($v[0], $v[1]);
        if ($nu < 1e-9 || $nv < 1e-9) {
            return 0.0;
        }

        return rad2deg(acos(max(-1.0, min(1.0, ($u[0] * $v[0] + $u[1] * $v[1]) / ($nu * $nv)))));
    }

    /**
     * Angle aigu (°) entre deux directions (droites non orientées).
     *
     * @param  array{0: float, 1: float}  $u
     * @param  array{0: float, 1: float}  $v
     */
    public static function lineAngle(array $u, array $v): float
    {
        $nu = hypot($u[0], $u[1]);
        $nv = hypot($v[0], $v[1]);
        if ($nu < 1e-9 || $nv < 1e-9) {
            return 0.0;
        }

        return rad2deg(asin(min(1.0, abs($u[0] * $v[1] - $u[1] * $v[0]) / ($nu * $nv))));
    }

    /**
     * Longueurs cumulées.
     *
     * @param  list<array{0: float, 1: float}>  $pts
     * @return non-empty-list<float>
     */
    public static function cumul(array $pts): array
    {
        $c = [0.0];
        for ($i = 1, $n = count($pts); $i < $n; $i++) {
            $c[] = $c[$i - 1] + hypot($pts[$i][0] - $pts[$i - 1][0], $pts[$i][1] - $pts[$i - 1][1]);
        }

        return $c;
    }

    /**
     * Point à l'abscisse s (bornée).
     *
     * @param  list<array{0: float, 1: float}>  $pts
     * @param  list<float>  $c
     * @return array{0: float, 1: float}
     */
    public static function at(array $pts, array $c, float $s): array
    {
        $n = count($pts);
        if ($s <= 0) {
            return $pts[0];
        }
        if ($s >= $c[$n - 1]) {
            return $pts[$n - 1];
        }
        $lo = 0;
        $hi = $n - 1;
        while ($hi - $lo > 1) {
            $mid = intdiv($lo + $hi, 2);
            if ($c[$mid] <= $s) {
                $lo = $mid;
            } else {
                $hi = $mid;
            }
        }
        $seg = $c[$hi] - $c[$lo];
        $f = $seg > 0 ? ($s - $c[$lo]) / $seg : 0.0;

        return [$pts[$lo][0] + $f * ($pts[$hi][0] - $pts[$lo][0]), $pts[$lo][1] + $f * ($pts[$hi][1] - $pts[$lo][1])];
    }

    /**
     * Tangente unitaire (corde de ±5 m) à l'abscisse s.
     *
     * @param  list<array{0: float, 1: float}>  $pts
     * @param  list<float>  $c
     * @return array{0: float, 1: float}
     */
    public static function tangent(array $pts, array $c, float $s): array
    {
        $len = end($c);
        $a = self::at($pts, $c, max(0.0, min($s - 5.0, $len - 10.0)));
        $b = self::at($pts, $c, min($len, max($s + 5.0, 10.0)));

        return self::unit(self::sub($b, $a)) ?? [1.0, 0.0];
    }

    /**
     * Projection d'un point sur la polyligne : abscisse, pied, distance.
     *
     * @param  list<array{0: float, 1: float}>  $pts
     * @param  list<float>  $c
     * @param  array{0: float, 1: float}  $p
     * @return array{s: float, p: array{0: float, 1: float}, d: float}
     */
    public static function locate(array $pts, array $c, array $p): array
    {
        $best = ['s' => 0.0, 'p' => $pts[0], 'd' => INF];
        for ($i = 1, $n = count($pts); $i < $n; $i++) {
            $ax = $pts[$i - 1][0];
            $ay = $pts[$i - 1][1];
            $dx = $pts[$i][0] - $ax;
            $dy = $pts[$i][1] - $ay;
            $l2 = $dx * $dx + $dy * $dy;
            $f = $l2 > 0 ? max(0.0, min(1.0, (($p[0] - $ax) * $dx + ($p[1] - $ay) * $dy) / $l2)) : 0.0;
            $q = [$ax + $f * $dx, $ay + $f * $dy];
            $d = hypot($p[0] - $q[0], $p[1] - $q[1]);
            if ($d < $best['d']) {
                $best = ['s' => $c[$i - 1] + $f * ($c[$i] - $c[$i - 1]), 'p' => $q, 'd' => $d];
            }
        }

        return $best;
    }

    /**
     * @param  array{0: float, 1: float}  $a
     * @param  array{0: float, 1: float}  $b
     * @return array{0: float, 1: float}
     */
    private static function sub(array $a, array $b): array
    {
        return [$a[0] - $b[0], $a[1] - $b[1]];
    }

    /**
     * @param  array{0: float, 1: float}  $a
     * @param  array{0: float, 1: float}  $b
     */
    private static function dot(array $a, array $b): float
    {
        return $a[0] * $b[0] + $a[1] * $b[1];
    }

    /**
     * @param  array{0: float, 1: float}  $v
     * @return array{0: float, 1: float}|null
     */
    private static function unit(array $v): ?array
    {
        $n = hypot($v[0], $v[1]);

        return $n < 1e-9 ? null : [$v[0] / $n, $v[1] / $n];
    }
}

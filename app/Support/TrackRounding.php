<?php

namespace App\Support;

/**
 * Arrondi des sommets d'un tracé (calcul pur, en mètres, Lambert-93) : une voie ferrée ne fait jamais d'angle, mais
 * ses tracés sources (OSM, BD TOPO, anciens tracés) la décrivent par des segments droits, souvent un point tous les
 * 30 m ou plus. Agrandie (zoom 16 et au-delà), la carte montrait des angles de 5 à 30°.
 *
 * Chaque sommet où le tracé tourne de MIN_TURN_DEG à MAX_TURN_DEG est remplacé par un arc de cercle tangent aux deux
 * segments (raccordement circulaire), échantillonné tous les STEP_DEG degrés : il ne reste que des cassures de 3° au
 * plus, invisibles à l'écran. En deçà de MIN_TURN_DEG, le sommet ne se voit pas et reste (moins de points à stocker). L'arc commence et finit sur les segments, au plus à mi-longueur de chacun (deux arcs voisins se touchent
 * sans se croiser), et passe au plus à MAX_DEV_M du sommet d'origine (flèche E = T × tan(θ/4), T = longueur de
 * tangente) : la précision du tracé est gardée. Sur un tracé qui échantillonne une vraie courbe, l'arc suit la voie
 * de plus près que les cordes.
 *
 * Ne bougent jamais : les extrémités, les sommets marqués fixes (jonction d'une autre ligne posée sur ce sommet),
 * les angles de plus de MAX_TURN_DEG (ce ne sont pas des courbes : défauts traités ailleurs ou vrais angles de la
 * donnée, qu'un arc transformerait en boucle).
 */
final class TrackRounding
{
    /** Virage (°) en deçà duquel le sommet reste tel quel (invisible à l'écran). */
    public const MIN_TURN_DEG = 6.0;

    /** Virage (°) au-delà duquel le sommet n'est pas une courbe : laissé tel quel. */
    public const MAX_TURN_DEG = 35.0;

    /** Pas angulaire (°) de l'échantillonnage des arcs. */
    public const STEP_DEG = 3.0;

    /** Écart maximal (m) entre l'arc et le sommet d'origine. */
    public const MAX_DEV_M = 2.0;

    /**
     * @param  list<array{0: float, 1: float}>  $c  polyligne (m)
     * @param  array<int, true>  $fixed  indices des sommets à ne pas arrondir
     * @return list<array{0: float, 1: float}> polyligne arrondie (mêmes extrémités)
     */
    public static function round(array $c, array $fixed = []): array
    {
        $n = count($c);
        if ($n < 3) {
            return $c;
        }
        $len = [];
        for ($i = 1; $i < $n; $i++) {
            $len[$i] = hypot($c[$i][0] - $c[$i - 1][0], $c[$i][1] - $c[$i - 1][1]);
        }
        $out = [$c[0]];
        for ($i = 1; $i < $n - 1; $i++) {
            $arc = isset($fixed[$i]) || $len[$i] < 1e-6 || $len[$i + 1] < 1e-6 ? null : self::arc($c[$i - 1], $c[$i], $c[$i + 1], $len[$i], $len[$i + 1]);
            foreach ($arc ?? [$c[$i]] as $p) {
                // Deux arcs qui se touchent à mi-segment : leur point commun n'est gardé qu'une fois.
                if (hypot($p[0] - end($out)[0], $p[1] - end($out)[1]) > 1e-6) {
                    $out[] = $p;
                }
            }
        }
        if (hypot($c[$n - 1][0] - end($out)[0], $c[$n - 1][1] - end($out)[1]) > 1e-6) {
            $out[] = $c[$n - 1];
        } else {
            $out[count($out) - 1] = $c[$n - 1];
        }

        return $out;
    }

    /**
     * Sommets que round() arrondirait (virage de MIN_TURN_DEG à MAX_TURN_DEG), pour chercher lesquels doivent rester fixes.
     *
     * @param  list<array{0: float, 1: float}>  $c
     * @return list<int>
     */
    public static function candidates(array $c): array
    {
        $out = [];
        for ($i = 1; $i < count($c) - 1; $i++) {
            $lab = hypot($c[$i][0] - $c[$i - 1][0], $c[$i][1] - $c[$i - 1][1]);
            $lbc = hypot($c[$i + 1][0] - $c[$i][0], $c[$i + 1][1] - $c[$i][1]);
            if ($lab > 1e-6 && $lbc > 1e-6 && self::arc($c[$i - 1], $c[$i], $c[$i + 1], $lab, $lbc) !== null) {
                $out[] = $i;
            }
        }

        return $out;
    }

    /**
     * Arc qui remplace le sommet $b (entre $a et $c), ou null s'il reste tel quel.
     *
     * @return list<array{0: float, 1: float}>|null
     */
    private static function arc(array $a, array $b, array $c, float $lab, float $lbc): ?array
    {
        $u = [($b[0] - $a[0]) / $lab, ($b[1] - $a[1]) / $lab];
        $v = [($c[0] - $b[0]) / $lbc, ($c[1] - $b[1]) / $lbc];
        $cross = $u[0] * $v[1] - $u[1] * $v[0];
        $theta = atan2(abs($cross), $u[0] * $v[0] + $u[1] * $v[1]);
        if ($theta < deg2rad(self::MIN_TURN_DEG) || $theta > deg2rad(self::MAX_TURN_DEG)) {
            return null;
        }
        // Longueur de tangente : mi-segment au plus, flèche au plus MAX_DEV_M.
        $t = min($lab / 2, $lbc / 2, self::MAX_DEV_M / tan($theta / 4));
        $r = $t / tan($theta / 2);
        $p0 = [$b[0] - $u[0] * $t, $b[1] - $u[1] * $t];
        // Centre : à gauche de la marche si le tracé tourne à gauche (produit vectoriel > 0), sinon à droite.
        $side = $cross > 0 ? 1.0 : -1.0;
        $center = [$p0[0] - $side * $u[1] * $r, $p0[1] + $side * $u[0] * $r];
        $steps = max(1, (int) ceil(rad2deg($theta) / self::STEP_DEG));
        $start = atan2($p0[1] - $center[1], $p0[0] - $center[0]);
        $pts = [];
        for ($k = 0; $k <= $steps; $k++) {
            $ang = $start + $side * $theta * $k / $steps;
            $pts[] = [$center[0] + $r * cos($ang), $center[1] + $r * sin($ang)];
        }

        return $pts;
    }
}

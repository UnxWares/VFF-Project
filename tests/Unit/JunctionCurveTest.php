<?php

namespace Tests\Unit;

use App\Support\JunctionCurve;
use PHPUnit\Framework\TestCase;

/**
 * Raccord de jonction lissé (App\Support\JunctionCurve, BuildSharedTrack règle 4) : géométrie pure, en mètres.
 * Ligne principale W = axe des x (y = 0) ; approche A = bout de la branche, du point de jonction vers l'amont.
 */
class JunctionCurveTest extends TestCase
{
    /** @return list<array{0: float, 1: float}> */
    private function main(): array
    {
        return [[-600.0, 0.0], [-200.0, 0.0], [0.0, 0.0], [200.0, 0.0], [600.0, 0.0]];
    }

    /**
     * Polyligne qui part de $p et suit les caps successifs ($steps = [[cap en degrés depuis +x, longueur], …]).
     *
     * @param  array{0: float, 1: float}  $p
     * @param  list<array{0: float, 1: float}>  $steps
     * @return list<array{0: float, 1: float}>
     */
    private function walk(array $p, array $steps): array
    {
        $pts = [$p];
        foreach ($steps as [$deg, $len]) {
            $n = max(1, (int) ceil($len / 10));
            for ($i = 0; $i < $n; $i++) {
                $p = [$p[0] + $len / $n * cos(deg2rad($deg)), $p[1] + $len / $n * sin(deg2rad($deg))];
                $pts[] = $p;
            }
        }

        return $pts;
    }

    /**
     * Biseau de Vire : la branche arrive de l'est (cap 180° + 7°, convergente), à 11 m de la voie, puis raccord droit de
     * 32 m à 20°. Approche, du point de jonction (0, 0) vers l'amont.
     *
     * @return list<array{0: float, 1: float}>
     */
    private function biseau(): array
    {
        return $this->walk([0.0, 0.0], [[20.0, 32.0], [7.0, 200.0]]);
    }

    /**
     * Crochet en « L » (Le Mans - Mézidon) : la branche vient de -x et file vers +x presque parallèle (convergence 14°)
     * à 12 m de la voie, puis descend perpendiculairement sur 12 m.
     *
     * @return list<array{0: float, 1: float}>
     */
    private function crochet(): array
    {
        return $this->walk([0.0, 0.0], [[90.0, 12.0], [180.0 - 14.0, 220.0]]);
    }

    /**
     * Contrôles communs : départ sur la branche et tangent à elle, 0 m, arrivée tangente à angle d'aiguillage, pas de coude.
     *
     * @param  list<array{0: float, 1: float}>  $a
     * @param  array{sD: float, alpha: float, radius: float, length: float, sJ: float, points: list<array{0: float, 1: float}>}  $plan
     */
    private function assertSmooth(array $a, array $plan, float $rTarget): void
    {
        $pts = $plan['points'];
        $ca = JunctionCurve::cumul($a);
        $d = JunctionCurve::at($a, $ca, $plan['sD']);
        $this->assertEqualsWithDelta($d[0], $pts[0][0], 1e-6, 'départ sur la branche');
        $this->assertEqualsWithDelta($d[1], $pts[0][1], 1e-6, 'départ sur la branche');
        // Tangence au départ : la courbe prolonge la branche (cap de A juste en amont de D).
        $up = JunctionCurve::at($a, $ca, $plan['sD'] + 10);
        $this->assertLessThan(1.0, JunctionCurve::turn($up, $pts[0], $pts[1]), 'tangente à la branche au départ');
        // 0 m : dernier sommet exactement sur la ligne principale.
        $last = $pts[count($pts) - 1];
        $this->assertEqualsWithDelta(0.0, $last[1], 1e-9, 'dernier sommet sur l\'axe');
        // Arrivée : angle d'aiguillage (6,3° à 15°) avec la ligne principale, pas de dépassement de l'autre côté.
        $prev = $pts[count($pts) - 2];
        $arr = JunctionCurve::lineAngle([$last[0] - $prev[0], $last[1] - $prev[1]], [1.0, 0.0]);
        $this->assertGreaterThanOrEqual(3.0, $arr);
        $this->assertLessThanOrEqual(15.5, $arr);
        $this->assertContains($plan['alpha'], JunctionCurve::ALPHAS);
        foreach ($pts as $p) {
            $this->assertGreaterThanOrEqual(-1e-9, $p[1] * ($pts[0][1] > 0 ? 1 : -1), 'reste du côté de la branche');
        }
        // Courbure continue : aucun coude (cordes ±10 m) au-delà de 35°, cassure entre segments < 3°, rayon plausible.
        $cp = JunctionCurve::cumul($pts);
        $len = $cp[count($cp) - 1];
        for ($s = 10.0; $s <= $len - 10.0; $s += 2.0) {
            $this->assertLessThan(35.0, JunctionCurve::turn(JunctionCurve::at($pts, $cp, $s - 10), JunctionCurve::at($pts, $cp, $s), JunctionCurve::at($pts, $cp, $s + 10)));
        }
        for ($i = 1; $i < count($pts) - 1; $i++) {
            $this->assertLessThan(3.0, JunctionCurve::turn($pts[$i - 1], $pts[$i], $pts[$i + 1]), "cassure au sommet {$i}");
        }
        $this->assertGreaterThanOrEqual($rTarget, $plan['radius']);
        $this->assertGreaterThanOrEqual($rTarget - 1, JunctionCurve::minRadius($pts));
        $this->assertLessThanOrEqual(JunctionCurve::LEN_MAX, $plan['length']);
    }

    public function test_biseau_remplace_par_une_courbe_tangente_partie_en_amont_du_coude(): void
    {
        $a = $this->biseau();
        $plan = JunctionCurve::plan($a, $this->main(), 150.0, 60.0);
        $this->assertNotNull($plan);
        $this->assertSmooth($a, $plan, 150.0);
        // Départ en amont du coude (à 32 m du bout) : le coude disparaît.
        $this->assertGreaterThan(32.0, $plan['sD']);
        $this->assertEqualsWithDelta(JunctionCurve::ALPHAS[0], $plan['alpha'], 1e-9, 'angle d\'aiguillage tg 0,11');
        // Point de jonction glissé le long de la voie dans le sens de la branche (vers -x), au-delà du bout d'origine.
        $last = $plan['points'][count($plan['points']) - 1];
        $this->assertLessThan(0.0, $last[0]);
    }

    public function test_crochet_en_l_suit_le_cap_de_la_branche_et_non_la_projection(): void
    {
        $a = $this->crochet();
        $plan = JunctionCurve::plan($a, $this->main(), 150.0, 60.0);
        $this->assertNotNull($plan);
        $this->assertSmooth($a, $plan, 150.0);
        $this->assertGreaterThan(12.0, $plan['sD']);
        // La jonction est loin dans le sens de la branche (elle vient de -x et file vers +x), pas au pied du crochet.
        $last = $plan['points'][count($plan['points']) - 1];
        $this->assertGreaterThan(60.0, $last[0]);
    }

    public function test_arrivee_deja_tangente_laissee_telle_quelle(): void
    {
        // Branche qui quitte la voie à 6° (aiguillage) puis s'en écarte en courbe douce : rien à lisser.
        $a = $this->walk([0.0, 0.0], [[174.0, 60.0], [168.0, 60.0], [160.0, 120.0]]);
        $this->assertNull(JunctionCurve::plan($a, $this->main(), 150.0, 60.0));
    }

    public function test_branche_perpendiculaire_laissee(): void
    {
        // Croisement / terminus en T : pas un aiguillage, pas de courbe de 200 m inventée.
        $a = $this->walk([0.0, 0.0], [[90.0, 250.0]]);
        $this->assertNull(JunctionCurve::plan($a, $this->main(), 150.0, 60.0));
    }

    public function test_branche_precise_a_coude_modere_non_touchee(): void
    {
        // Voie précise (BD TOPO, OSM) : arrivée à 25° avec un coude de 30° = géométrie réelle, pas de lissage.
        $a = $this->walk([0.0, 0.0], [[155.0, 20.0], [180.0 - 5.0, 200.0]]);
        $this->assertNull(JunctionCurve::plan($a, $this->main(), 150.0, 60.0, null, true));
        // Même branche, tracé imprécis : lissée.
        $this->assertNotNull(JunctionCurve::plan($a, $this->main(), 150.0, 60.0, null, false));
    }

    public function test_branche_precise_a_crochet_lissee(): void
    {
        $a = $this->crochet();
        $plan = JunctionCurve::plan($a, $this->main(), 150.0, 60.0, null, true);
        $this->assertNotNull($plan);
        $this->assertSmooth($a, $plan, 150.0);
    }

    public function test_depart_jamais_au_dela_d_une_autre_jonction(): void
    {
        $a = $this->biseau();
        // Une autre voie touche la branche à 35 m du bout : départ avant elle impossible (coude à 32 m) : laissé.
        $this->assertNull(JunctionCurve::plan($a, $this->main(), 150.0, 60.0, 35.0));
        // À 120 m : départ avant elle.
        $plan = JunctionCurve::plan($a, $this->main(), 150.0, 60.0, 120.0);
        $this->assertNotNull($plan);
        $this->assertLessThanOrEqual(118.0, $plan['sD']);
    }

    public function test_courbe_plafonnee(): void
    {
        // Branche parallèle à 40 m de la voie : la courbe tangente ferait plus de LEN_MAX même à 15° : laissée.
        $a = $this->walk([0.0, 0.0], [[90.0, 40.0], [180.0, 300.0]]);
        $this->assertNull(JunctionCurve::plan($a, $this->main(), 150.0, 60.0));
    }

    public function test_aides_geometriques(): void
    {
        $this->assertEqualsWithDelta(90.0, JunctionCurve::turn([0, 0], [10, 0], [10, 10]), 1e-9);
        $this->assertEqualsWithDelta(0.0, JunctionCurve::turn([0, 0], [10, 0], [20, 0]), 1e-9);
        $this->assertEqualsWithDelta(30.0, JunctionCurve::lineAngle([cos(deg2rad(150)), sin(deg2rad(150))], [1, 0]), 1e-9);
        $loc = JunctionCurve::locate($this->main(), JunctionCurve::cumul($this->main()), [10.0, 5.0]);
        $this->assertEqualsWithDelta(610.0, $loc['s'], 1e-9);
        $this->assertEqualsWithDelta(5.0, $loc['d'], 1e-9);
        // Arc de rayon 100 m échantillonné : rayon mesuré ~100 m.
        $arc = [];
        for ($i = 0; $i <= 40; $i++) {
            $arc[] = [100 * cos($i * 0.02), 100 * sin($i * 0.02)];
        }
        $this->assertEqualsWithDelta(100.0, JunctionCurve::minRadius($arc), 0.5);
    }
}

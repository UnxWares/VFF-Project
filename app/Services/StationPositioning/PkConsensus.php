<?php

namespace App\Services\StationPositioning;

/**
 * Calage robuste des PK d'une liste de gares (schéma Wikipédia, lignes-oubliees) sur
 * l'axe de la ligne, fondé sur une propriété physique : le PK est une distance le long
 * de la voie. Entre deux points d'appui justes, l'écart de PK et l'écart d'abscisse
 * concordent à quelques % près (tracé VFF calé sur OSM / BD TOPO, kilométrage des
 * compagnies) ; un écart plus grand trahit un point d'appui faux :
 *   - PK d'une autre ligne recopié par le schéma (gare de bifurcation : « Les Maures
 *     17,525 », PK de la ligne de Vire à Romagny, sur la ligne de Montsecret aux Maures
 *     qui mesure 19,6 km) ;
 *   - gare homonyme, ou gare d'appui posée loin de sa voie.
 *
 * Modèle : abscisse = sens × PK + décalage (sens ±1). Chaque point d'appui sert de pivot
 * dans les deux sens ; les points concordants avec le pivot (écart ≤ tolérance du type
 * + TOLERANCE_SLOPE × écart de PK) forment un consensus, pondéré (gare 2, lieu-dit 1).
 * Le consensus le plus lourd l'emporte s'il pèse au moins MIN_WEIGHT, couvre au moins
 * deux PK distincts et n'a pas de rival incompatible de même poids (sinon : ambigu, null).
 *
 * Avant : l'interpolation par morceaux acceptait toute pente entre 1/3 et 3 ; avec deux
 * gares d'appui dont une fausse, toutes les haltes intermédiaires étaient étirées (jusqu'à
 * 1,4 km d'erreur sur la ligne de Montsecret aux Maures).
 */
final class PkConsensus
{
    /** Part de l'écart de PK tolérée entre abscisse et PK (kilométrage ancien, tracé). */
    public const TOLERANCE_SLOPE = 0.03;

    /** Poids minimal d'un consensus : deux gares, ou une gare et un lieu-dit, ou trois lieux-dits. */
    public const MIN_WEIGHT = 3;

    /** Distance maximale (m) entre un point d'appui et l'axe de la ligne. */
    public const ANCHOR_MAX_M = 300;

    /** @param  list<PkAnchor>  $anchors */
    public static function fit(array $anchors): ?PkFit
    {
        $models = [];
        foreach ([1, -1] as $dir) {
            foreach ($anchors as $pivot) {
                $offset = $pivot->offset($dir);
                $in = [];
                $residual = 0.0;
                foreach ($anchors as $k => $a) {
                    $err = abs($a->measure - ($dir * $a->pk + $offset));
                    $tol = max($a->kind->toleranceM(), $pivot->kind->toleranceM()) + self::TOLERANCE_SLOPE * abs($a->pk - $pivot->pk);
                    if ($err <= $tol) {
                        $in[] = $k;
                        $residual += $err;
                    }
                }
                $weight = array_sum(array_map(fn ($k) => $anchors[$k]->kind->weight(), $in));
                $models[] = ['dir' => $dir, 'in' => $in, 'weight' => $weight, 'residual' => $residual];
            }
        }
        if ($models === []) {
            return null;
        }
        usort($models, fn ($a, $b) => [$b['weight'], $a['residual']] <=> [$a['weight'], $b['residual']]);
        $best = $models[0];
        $pks = array_unique(array_map(fn ($k) => $anchors[$k]->pk, $best['in']));
        if ($best['weight'] < self::MIN_WEIGHT || count($pks) < 2) {
            return null;
        }
        foreach (array_slice($models, 1) as $m) {
            if ($m['weight'] < $best['weight']) {
                break;
            }
            $sameSet = array_diff($m['in'], $best['in']) === [] || array_diff($best['in'], $m['in']) === [];
            // Rival de même poids : autres points d'appui, ou mêmes points dans l'autre sens.
            if (! $sameSet || $m['dir'] !== $best['dir']) {
                return null;
            }
        }
        $inliers = array_map(fn ($k) => $anchors[$k], $best['in']);
        usort($inliers, fn (PkAnchor $a, PkAnchor $b) => [$a->pk, $a->kind->weight()] <=> [$b->pk, $b->kind->weight()]);
        $outliers = array_values(array_diff_key($anchors, array_flip($best['in'])));

        return new PkFit($best['dir'], $inliers, $outliers, $best['weight']);
    }
}

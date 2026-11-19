<?php

namespace App\Support;

/**
 * Tuiles vectorielles (MVT) de VFF : zoom servi et simplification des tracés pour l'affichage.
 *
 * Les tuiles servent à dessiner la carte du site et la carte intégrable, qui ne demandent pas de tuile
 * au-delà du zoom MAX_ZOOM (sources MapLibre `maxzoom: 14`) : MapLibre agrandit ensuite la tuile 14. Le
 * serveur ne produit donc aucune tuile plus fine (HTTP 404 au-delà).
 *
 * Chaque tracé (ère, comparaison de deux ères, superposition des ères, signalisation) est découpé dans la
 * grille de la tuile (ST_AsMVTGeom, 4 096 unités de côté : 153 m au zoom 6, 9,6 m au zoom 10, 0,6 m au
 * zoom 14, en mètres Web Mercator). À partir du zoom SIMPLIFIED_FROM, il est d'abord simplifié
 * (Douglas-Peucker, ST_Simplify) :
 *   - zooms 10 à 13 : tolérance d'un demi-pixel d'écran. MapLibre affiche une tuile de 512 pixels du zoom
 *     z au zoom z + 1, où cet écart vaut au plus un pixel ;
 *   - zoom 14 : tolérance d'une unité de la grille de la tuile (environ 0,6 m en Web Mercator, 0,4 m sur
 *     le terrain en France), la précision que la tuile a déjà. Une tolérance plus forte se verrait quand
 *     MapLibre agrandit la tuile 14 aux zooms 16 et plus.
 * Sous le zoom 10, la grille suffit : à ces échelles, les tracés comptent des dizaines de sommets par
 * pixel, que MapLibre dessine plus épais. Simplifiés, même d'une seule unité de la grille, ils
 * retrouvent leur largeur nominale et la carte de la France entière change d'aspect (mesuré le
 * 19/11/2026 par captures aux zooms 6, 10 et 14, rapport soir/revue-pr18-corrections.md).
 */
final class MapTiles
{
    /** Zoom le plus fin servi : celui que demande la carte du site. */
    public const MAX_ZOOM = 14;

    /** Premier zoom dont les tracés sont simplifiés (voir la classe). */
    public const SIMPLIFIED_FROM = 10;

    /**
     * Version de la géométrie des tuiles, dans les clés du cache serveur (superposition des ères,
     * signalisation) : à changer quand la géométrie change, pour que les tuiles gardées soient recalculées.
     */
    public const FORMAT = 2;

    /** Côté d'une tuile MVT, en unités de la grille (ST_AsMVTGeom). */
    public const EXTENT = 4096;

    /** Côté d'une tuile vectorielle affichée par MapLibre, en pixels. */
    private const TILE_PX = 512;

    /** Circonférence de la Terre à l'équateur en Web Mercator (EPSG:3857), en mètres : côté de la tuile 0. */
    private const WORLD_M = 40_075_016.686;

    /** La tuile existe-t-elle (zoom servi, colonne et rangée dans la grille XYZ) ? */
    public static function onGrid(int $z, int $x, int $y): bool
    {
        return $z >= 0 && $z <= self::MAX_ZOOM && $x >= 0 && $y >= 0 && $x < (1 << $z) && $y < (1 << $z);
    }

    /**
     * Tolérance de simplification d'une tuile du zoom `$z`, en mètres Web Mercator, ou null sous
     * SIMPLIFIED_FROM (pas de simplification, voir la classe).
     */
    public static function tolerance(int $z): ?float
    {
        if ($z < self::SIMPLIFIED_FROM) {
            return null;
        }
        $tileM = self::WORLD_M / (2 ** $z);

        return $z >= self::MAX_ZOOM ? $tileM / self::EXTENT : $tileM / self::TILE_PX / 2;
    }

    /**
     * Expression SQL de la géométrie à passer à ST_AsMVTGeom : `$geom` (WGS 84) projetée en Web Mercator,
     * simplifiée à la tolérance du zoom s'il y en a une. Les petits morceaux sont gardés
     * (preserveCollapsed) : une portion courte reste dessinée.
     */
    public static function displaySql(string $geom, int $z): string
    {
        $mercator = "ST_Transform({$geom}, 3857)";
        $tolerance = self::tolerance($z);

        return $tolerance === null ? $mercator : sprintf('ST_Simplify(%s, %.6F, true)', $mercator, $tolerance);
    }
}

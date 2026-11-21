<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

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
 * (Douglas-Peucker, ST_Simplify) à une unité de cette grille (9,6 m au zoom 10, 1,2 m au zoom 13, 0,6 m
 * au zoom 14, soit 0,4 m sur le terrain en France) : la précision que la tuile a déjà. Les tracés de
 * lignes ont au zoom 14 une grille deux fois plus fine (FINE_EXTENT, voir plus bas).
 *
 * Jusqu'au 20/11/2026, les zooms 10 à 13 l'étaient à un demi-pixel d'écran (4,8 m au zoom 13). Les
 * courbes y devenaient des cordes de 140 à 230 m coudées de 19 à 27° (mesuré sur la ligne des Cévennes
 * vers Villefort et vers Chamonix), visibles en zoomant entre les zooms 10 et 14. Une unité de la grille
 * garde les coudes du tracé lui-même, pour 6 à 12 % de poids en plus sur ces tuiles. La tuile 14 reste
 * la plus précise servie : elle seule borne ce qu'on peut extraire des tuiles.
 *
 * Sous le zoom 10, la grille suffit : à ces échelles, les tracés comptent des dizaines de sommets par
 * pixel, que MapLibre dessine plus épais. Simplifiés, même d'une seule unité de la grille, ils
 * retrouvent leur largeur nominale et la carte de la France entière change d'aspect (mesuré le
 * 19/11/2026 par captures aux zooms 6, 10 et 14, rapport soir/revue-pr18-corrections.md).
 *
 * Tracés des lignes au zoom 14 (ère, comparaison, superposition : lineGeomSql) : grille de FINE_EXTENT
 * unités (0,3 m Web Mercator), la résolution interne de MapLibre, et tolérance d'une de ces unités.
 * Depuis le 21/11/2026, les tracés sont arrondis en arcs (App\Support\TrackRounding) ; agrandie jusqu'au
 * zoom 18, la tuile 14 à 0,6 m les redécoupait en cordes coudées de 6° et plus (152 coudes sur la ligne
 * des Cévennes à Villefort, 325 à Chamonix ; 64 et 202 sur la grille fine). Poids des tuiles 14 mesuré
 * sur six lieux (Paris, Lyon, Villefort, Chamonix, Argentan, Briouze) : de -5 à +9 % (gzip -1 à +11 %)
 * par rapport aux tuiles d'avant l'arrondi (rapport nuit-2111/geometrie-nuit.md). Servir le zoom 15
 * aurait donné la même précision pour deux fois plus d'octets sur la même surface.
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
    public const FORMAT = 4;

    /** Côté d'une tuile MVT, en unités de la grille (ST_AsMVTGeom). */
    public const EXTENT = 4096;

    /** Grille fine des tracés de lignes au zoom MAX_ZOOM (voir la classe). */
    public const FINE_EXTENT = 8192;

    /** Circonférence de la Terre à l'équateur en Web Mercator (EPSG:3857), en mètres : côté de la tuile 0. */
    private const WORLD_M = 40_075_016.686;

    /** La tuile existe-t-elle (zoom servi, colonne et rangée dans la grille XYZ) ? */
    public static function onGrid(int $z, int $x, int $y): bool
    {
        return $z >= 0 && $z <= self::MAX_ZOOM && $x >= 0 && $y >= 0 && $x < (1 << $z) && $y < (1 << $z);
    }

    /** Grille (unités par côté) d'une tuile de tracés de lignes du zoom `$z` : fine au zoom MAX_ZOOM. */
    public static function extent(int $z): int
    {
        return $z >= self::MAX_ZOOM ? self::FINE_EXTENT : self::EXTENT;
    }

    /**
     * Tolérance de simplification d'une tuile du zoom `$z` dont la grille compte `$extent` unités, en
     * mètres Web Mercator (une unité), ou null sous SIMPLIFIED_FROM (pas de simplification, voir la classe).
     */
    public static function tolerance(int $z, int $extent = self::EXTENT): ?float
    {
        if ($z < self::SIMPLIFIED_FROM) {
            return null;
        }

        return self::WORLD_M / (2 ** $z) / $extent;
    }

    /**
     * Expression SQL de la géométrie à passer à ST_AsMVTGeom : `$geom` (WGS 84) projetée en Web Mercator,
     * simplifiée à la tolérance du zoom (grille de `$extent` unités) s'il y en a une. Les petits morceaux
     * sont gardés (preserveCollapsed) : une portion courte reste dessinée.
     */
    public static function displaySql(string $geom, int $z, int $extent = self::EXTENT): string
    {
        $mercator = "ST_Transform({$geom}, 3857)";
        $tolerance = self::tolerance($z, $extent);

        return $tolerance === null ? $mercator : sprintf('ST_Simplify(%s, %.6F, true)', $mercator, $tolerance);
    }

    /**
     * ST_AsMVTGeom d'un tracé de ligne (`$geom` en WGS 84) dans la tuile `bounds.env_3857` du zoom `$z` :
     * grille extent($z), marge d'un seizième de tuile (256 unités sur 4 096). La couche doit être écrite
     * avec la même grille : ST_AsMVT(…, nom, MapTiles::extent($z)).
     */
    public static function lineGeomSql(string $geom, int $z): string
    {
        $extent = self::extent($z);

        return sprintf('ST_AsMVTGeom(%s, bounds.env_3857, %d, %d, true)', self::displaySql($geom, $z, $extent), $extent, intdiv($extent, 16));
    }

    /**
     * Exécute la requête d'une tuile (une ligne, colonne `tile`) et renvoie la tuile, sans compilation
     * JIT de PostgreSQL. Le planificateur la déclenche sur les tuiles des petits zooms (des milliers de
     * tracés) : 0,66 s de compilation pour une tuile qui s'exécute en 0,5 s (mesuré le 20/11/2026 sur la
     * tuile 4/8/5 de l'ère actuelle). `SET LOCAL` ne vaut que pour la transaction de la tuile.
     *
     * @param  array<string, mixed>  $bindings
     */
    public static function query(string $sql, array $bindings): string
    {
        return DB::transaction(function () use ($sql, $bindings): string {
            DB::statement('SET LOCAL jit = off');

            return MvtBytes::from(DB::selectOne($sql, $bindings)?->tile);
        });
    }
}

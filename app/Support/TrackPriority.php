<?php

namespace App\Support;

/**
 * Priorité d'affichage d'une portion (line_segments) quand plusieurs lignes
 * VFF sont dessinées sur la MÊME voie physique (tronc commun, raccordement,
 * ligne particulière posée sur une ligne RFN…) : la carte montre l'état le
 * PLUS ACTIF de la voie à l'époque affichée. Une seule définition, partagée
 * par vff:build-shared-track (portions masquées) et les tuiles MVT
 * (`draw_rank` = ordre de dessin `line-sort-key` côté MapLibre).
 *
 * Ordre (du plus prioritaire au moins prioritaire) :
 *   1. en service (in_service, single_track) : LGV, puis mixte, puis fret,
 *      puis inconnu ; une ligne particulière ou militaire passe APRÈS le
 *      réseau public (principal, secondaire) quel que soit son trafic : posée
 *      sur une ligne RFN, elle ne change pas la couleur du RFN ;
 *   2. voie de service ;
 *   3. associatif : train touristique / musée, puis vélorail ;
 *   4. en construction ;
 *   5. fermée (voie en place) ;
 *   6. déposée, puis disparue.
 * À égalité (même état) : principal > secondaire > particulier > militaire,
 * une ligne passe avant un raccordement, puis la plus petite line_id.
 *
 * Une portion fermée ne masque donc jamais une portion en service d'une autre
 * ligne, quel que soit l'ordre des entités dans la tuile.
 */
final class TrackPriority
{
    /** Distance maximale entre deux tracés de la même voie (m). */
    public const TOLERANCE_M = 10;

    /** Longueur minimale de voie commune pour masquer (m) : en deçà, simple croisement ou jonction. */
    public const MIN_SHARED_M = 100;

    /** Reliquat visible plus court que ça (m) entre deux parties masquées : supprimé (miettes). */
    public const MIN_REMAINDER_M = 30;

    /**
     * Rang principal (entier, plus grand = plus actif).
     *
     * @param  string  $lgv  expression SQL booléenne « portion LGV » (cf. Lgv::sql())
     * @param  string  $l  alias de `lines`
     * @param  string  $ls  alias de `line_segments`
     */
    public static function rankSql(string $lgv, string $l = 'l', string $ls = 'ls'): string
    {
        return <<<SQL
            (CASE
                WHEN {$ls}.status IN ('in_service', 'single_track') AND NOT COALESCE({$ls}.is_heritage, false) THEN
                    1000
                    + CASE WHEN {$l}.network IN ('particulier', 'militaire') THEN 0 ELSE 100 END
                    + CASE WHEN ({$lgv}) OR {$ls}.traffic_type = 'passenger' THEN 40
                           WHEN {$ls}.traffic_type = 'mixed' THEN 30
                           WHEN {$ls}.traffic_type = 'freight' THEN 20
                           ELSE 10 END
                WHEN {$ls}.status = 'service_track' AND NOT COALESCE({$ls}.is_heritage, false) THEN 900
                WHEN COALESCE({$ls}.is_heritage, false) AND {$ls}.status IN ('in_service', 'single_track') THEN 800
                WHEN COALESCE({$ls}.is_heritage, false) THEN 700
                WHEN {$ls}.status = 'construction' THEN 600
                WHEN {$ls}.status = 'abandoned' THEN 500
                WHEN {$ls}.status = 'dismantled' THEN 400
                WHEN {$ls}.status = 'razed' THEN 300
                ELSE 0
            END)
        SQL;
    }

    /** Départage à rang égal (0..49) : catégorie de réseau, ligne avant raccordement. */
    public static function tieSql(string $l = 'l'): string
    {
        return <<<SQL
            (CASE {$l}.network WHEN 'principal' THEN 40 WHEN 'secondaire' THEN 30
                               WHEN 'particulier' THEN 20 WHEN 'militaire' THEN 10 ELSE 30 END
             + CASE WHEN {$l}.name ILIKE '%raccordement%' THEN 0 ELSE 5 END)
        SQL;
    }

    /** Ordre de dessin exporté dans les tuiles (`draw_rank`, line-sort-key). */
    public static function drawRankSql(string $lgv, string $l = 'l', string $ls = 'ls'): string
    {
        return '('.self::rankSql($lgv, $l, $ls).' * 100 + '.self::tieSql($l).')';
    }

    /**
     * Jointures SQL qui découpent chaque portion `{$ls}` en parties à dessiner :
     * `part.g` (géométrie 4326) et `part.covered` (true = recouverte par une
     * ligne plus active, à ne pas dessiner). Sans entrée valide dans
     * line_segment_covers (jamais calculée, ou caduque : la portion ou une
     * recouvrante a changé d'état ou a été régénérée), la portion sort entière.
     */
    public static function partsJoinSql(string $ls = 'ls'): string
    {
        $state = self::stateSql('x');

        return <<<SQL
            LEFT JOIN LATERAL (
                SELECT c.visible_geom, c.covered_geom
                FROM line_segment_covers c
                WHERE c.segment_id = {$ls}.id
                  AND c.signature = (SELECT md5(string_agg({$state}, ',' ORDER BY x.id))
                                     FROM line_segments x WHERE x.id = ANY (c.covering_ids || {$ls}.id))
            ) cov ON TRUE
            CROSS JOIN LATERAL (
                SELECT {$ls}.geom AS g, false AS covered WHERE cov.covered_geom IS NULL
                UNION ALL SELECT cov.visible_geom, false WHERE cov.visible_geom IS NOT NULL
                UNION ALL SELECT cov.covered_geom, true WHERE cov.covered_geom IS NOT NULL
            ) part
        SQL;
    }

    /**
     * État d'une portion pris en compte pour décider du masquage : si l'un
     * change (moteur relancé, statut corrigé…), le masquage enregistré devient
     * caduc (cf. line_segment_covers.signature).
     */
    public static function stateSql(string $ls = 'ls'): string
    {
        return "({$ls}.id || ':' || {$ls}.status || ':' || {$ls}.traffic_type || ':' || {$ls}.is_heritage::text)";
    }
}

<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Signature des données d'une ligne, pour garder en cache des calculs géographiques coûteux (fiche de ligne)
 * sans jamais servir un résultat périmé.
 *
 * Pour chaque table lue, elle résume l'ensemble des versions de rangées visibles : leur adresse physique
 * (`ctid`) et la transaction qui les a écrites (`xmin`), deux colonnes système de PostgreSQL. Toute écriture
 * change cet ensemble, même faite en SQL brut sans toucher à `updated_at` (le moteur en fait beaucoup) et
 * même à l'intérieur d'une seule transaction : une mise à jour écrit une nouvelle version de la rangée à une
 * autre adresse, une suppression la retire, une insertion en ajoute une. Un VACUUM (gel, déplacement) peut
 * aussi la changer : il provoque seulement un recalcul de plus, jamais un résultat périmé.
 *
 * Coût : une lecture par index des rangées de la ligne, environ 15 ms pour les 7 033 tronçons de la LGV
 * Sud-Est (moins de 1 ms pour une ligne ordinaire), contre 100 à 330 ms pour les calculs qu'elle protège.
 */
final class LineDataSignature
{
    /**
     * Version du format des résultats gardés : à augmenter quand un calcul protégé change, pour que les
     * entrées écrites par l'ancien code ne soient plus lues.
     */
    public const FORMAT = 1;

    /** Résumé SQL des versions de rangées d'une table (nombre et empreinte de l'ensemble ordonné). */
    private const ROWS = "concat_ws(':', COUNT(*), md5(string_agg(ctid::text || xmin::text, ',' ORDER BY ctid)))";

    /** Tronçons de la ligne (toutes ères). */
    public static function segments(int $lineId): string
    {
        $row = DB::selectOne('SELECT '.self::ROWS.' AS s FROM line_segments WHERE line_id = ?', [$lineId]);

        return self::FORMAT.':'.(string) ($row->s ?? '');
    }

    /**
     * Tronçons et événements de la ligne, la ligne elle-même, ses catégories SNCF (`sncf_line_catlig`,
     * lues par Lgv::sql) et la liste des ères.
     */
    public static function segmentsEventsAndEras(int $lineId): string
    {
        $rows = self::ROWS;
        $row = DB::selectOne(<<<SQL
            SELECT concat_ws('|',
                (SELECT {$rows} FROM line_segments WHERE line_id = ?),
                (SELECT {$rows} FROM line_events WHERE line_id = ?),
                (SELECT {$rows} FROM lines WHERE id = ?),
                (SELECT {$rows} FROM sncf_line_catlig WHERE code_ligne = (SELECT code FROM lines WHERE id = ?)),
                (SELECT {$rows} FROM eras)) AS s
        SQL, [$lineId, $lineId, $lineId, $lineId]);

        return self::FORMAT.':'.md5((string) ($row->s ?? ''));
    }
}

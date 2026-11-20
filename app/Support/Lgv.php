<?php

namespace App\Support;

/**
 * Détection du réseau à grande vitesse (LGV et raccordements LGV) : UNE seule
 * définition, partagée par la classification (vff:classify-lines), la
 * génération des ères (vff:build-era-segments), les tuiles MVT / l'export
 * GeoJSON de la carte et la mini-carte des fiches lignes.
 *
 * Une portion est LGV dès qu'UN des signaux le dit (OU logique) :
 *   1. lines.is_lgv : drapeau posé par vff:classify-lines (catlig SNCF, nom,
 *      vitesse, et raccordements branchés sur une LGV, hérités en passe 2) ;
 *   2. SNCF « lignes-lgv-et-par-ecartement » (sncf_line_catlig) : le code RFN
 *      a au moins un tronçon « Ligne à grande vitesse » ;
 *   3. le nom de la ligne : « LGV » (mot entier) ou « grande vitesse » ;
 *   4. la vitesse de la portion ≥ 250 km/h (seuil UE/UIC de la grande
 *      vitesse ; aucune ligne classique française ne dépasse 220 km/h).
 *
 * Pourquoi un OU et non plus COALESCE(l.is_lgv, nom, vitesse) : lines.is_lgv
 * est NOT NULL DEFAULT false. Tant que la classification n'a pas tourné (base
 * recréée, ligne importée après coup, segments régénérés), le COALESCE
 * renvoyait ce false par défaut sans jamais consulter les autres signaux, et
 * les LGV s'affichaient en orange (réseau classique mixte). Le OU ne dépend
 * plus d'aucune valeur par défaut : il suffit que la donnée SNCF soit importée.
 */
final class Lgv
{
    /** Vitesse à partir de laquelle une portion relève de la grande vitesse. */
    public const MIN_SPEED_KMH = 250;

    /** Motif ILIKE de la catégorie SNCF « Ligne à grande vitesse » (sncf_line_catlig.catlig). */
    public const CATLIG_PATTERN = '%grande vitesse%';

    /**
     * Expression SQL booléenne, jamais NULL.
     *
     * @param  string  $line  alias de la table `lines`
     * @param  string|null  $segment  alias de `line_segments` (signal vitesse), null si absente
     */
    public static function sql(string $line = 'l', ?string $segment = 'ls'): string
    {
        $parts = [
            "COALESCE({$line}.is_lgv, false)",
            "COALESCE({$line}.name ~* '\\mLGV\\M' OR {$line}.name ILIKE '%grande vitesse%', false)",
            "EXISTS (SELECT 1 FROM sncf_line_catlig lgv_c WHERE lgv_c.code_ligne = {$line}.code AND lgv_c.catlig ILIKE '".self::CATLIG_PATTERN."')",
        ];
        if ($segment !== null) {
            $parts[] = "COALESCE({$segment}.max_speed_kmh, 0) >= ".self::MIN_SPEED_KMH;
        }

        return '('.implode(' OR ', $parts).')';
    }

    /** Même règle de nom que sql(), côté PHP. */
    public static function nameIsLgv(?string $name): bool
    {
        return $name !== null
            && (preg_match('/\bLGV\b/i', $name) === 1 || stripos($name, 'grande vitesse') !== false);
    }
}

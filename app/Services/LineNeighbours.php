<?php

namespace App\Services;

/**
 * Gares voisines d'une gare le long d'une ligne desservie : la précédente et la suivante par PK
 * (station_lines.pk_m) dont l'écart de PK est cohérent avec leur éloignement, avec la distance le long de la ligne, l'état d'aujourd'hui et, pour une gare
 * fermée qui a été ouverte, la dernière ère où elle l'était.
 *
 * Les voisines sont calculées dans la requête des lignes desservies de la fiche (StationSheet::lines) :
 * deux sous-requêtes corrélées, aucune requête de plus. Rien quand le PK de la gare est inconnu.
 */
final class LineNeighbours
{
    /**
     * Colonnes JSON `prev_neighbour` et `next_neighbour` à joindre au SELECT de station_lines (alias `sl`
     * et `s` pour la gare). Ne comptent ni les points techniques ni les fiches exclues du réseau
     * ferré, ni les gares qui n'ont jamais été desservies par la ligne.
     */
    public static function columns(): string
    {
        return self::column('<', 'DESC', 'prev_neighbour').', '.self::column('>', 'ASC', 'next_neighbour');
    }

    /** Même forme que les colonnes, nulles : repli sans rattachement (station_lines vide). */
    public const NULL_COLUMNS = 'NULL AS prev_neighbour, NULL AS next_neighbour';

    private static function column(string $cmp, string $dir, string $alias): string
    {
        return <<<SQL
            (SELECT ROW_TO_JSON(n) FROM (
                SELECT s2.slug, s2.name, s2.type, CASE WHEN s2.source_status = 'gone' THEN 'gone' ELSE s2.status END AS status,
                       ABS(sl2.pk_m - sl.pk_m) AS distance_m,
                       (SELECT MAX(e2.year) FROM station_eras se2 JOIN eras e2 ON e2.id = se2.era_id
                        WHERE se2.station_id = s2.id AND se2.status IN ('active', 'tourist', 'no_commercial')) AS last_open_era
                FROM station_lines sl2 JOIN stations s2 ON s2.id = sl2.station_id
                WHERE sl.pk_m IS NOT NULL AND sl2.line_id = sl.line_id AND sl2.station_id <> sl.station_id
                  AND sl2.pk_m {$cmp} sl.pk_m AND sl2.years_source IS DISTINCT FROM 'never_served'
                  AND COALESCE(s2.provenance->>'kind', '') NOT IN ('technical', 'excluded')
                  -- PK incohérent : à vol d'oiseau, deux gares ne sont jamais plus loin que le long de la ligne (marge : PK à quelques centaines de mètres).
                  AND ST_Distance(s2.geom::geography, s.geom::geography) <= ABS(sl2.pk_m - sl.pk_m) * 1.15 + 1500
                ORDER BY sl2.pk_m {$dir}, sl2.station_id
                LIMIT 1
            ) n) AS {$alias}
            SQL;
    }

    /**
     * Voisines d'une ligne desservie, à partir de la ligne lue par StationSheet::lines. Nul sans PK ou sans
     * aucune voisine. `last_open_era` n'est donné que pour une gare qui n'est plus ouverte aujourd'hui.
     *
     * @return array{previous: ?array<string, mixed>, next: ?array<string, mixed>}|null
     */
    public static function of(object $line): ?array
    {
        $previous = self::decode($line->prev_neighbour ?? null);
        $next = self::decode($line->next_neighbour ?? null);

        return $previous === null && $next === null ? null : ['previous' => $previous, 'next' => $next];
    }

    /** @return array<string, mixed>|null */
    private static function decode(mixed $json): ?array
    {
        $n = is_string($json) ? json_decode($json, true) : null;
        if (! is_array($n)) {
            return null;
        }
        $closed = in_array($n['status'], ['declassified', 'gone'], true);

        return [
            'slug' => $n['slug'], 'name' => $n['name'], 'type' => $n['type'], 'status' => $n['status'],
            'distance_m' => (int) $n['distance_m'],
            'last_open_era' => $closed && $n['last_open_era'] !== null ? (int) $n['last_open_era'] : null,
        ];
    }
}

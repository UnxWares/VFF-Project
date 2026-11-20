<?php

namespace App\Enums;

/**
 * Ce qui est arrivé à un tronçon entre deux ères de la carte (comparaison d'époques).
 *
 * Un tronçon est « en service » (in_service, single_track), « fermé, voie en place »
 * (abandoned, service_track : plus de trafic de ligne, comme dans NetworkStats) ou absent
 * (déposé, effacé, en construction, ou inconnu de l'ère). Les deux ères se comparent tronçon
 * par tronçon : même ligne et même géométrie (les tronçons sont recopiés à l'identique d'une
 * décennie à l'autre par le moteur de l'historique).
 */
enum EraChange: string
{
    /** En service à la seconde ère, pas à la première (ouverte ou rouverte entre les deux). */
    case Opened = 'opened';

    /** Fermée à la seconde ère (voie en place), alors qu'elle ne l'était pas à la première. */
    case Closed = 'closed';

    /** En service ou fermée à la première ère, déposée ou disparue à la seconde. */
    case Removed = 'removed';

    /** En service aux deux ères. */
    case Kept = 'kept';

    /** Statuts « en service » (SegmentStatus::isActive). */
    public const OPEN_STATUSES = ['in_service', 'single_track'];

    /** Statuts « fermée, voie en place ». */
    public const CLOSED_STATUSES = ['abandoned', 'service_track'];

    /**
     * Expression SQL qui ramène un statut de tronçon (colonne, éventuellement NULL quand le
     * tronçon manque à l'ère) à 'open', 'closed' ou 'none'.
     */
    public static function presenceSql(string $column): string
    {
        $open = self::openStatusesSql();
        $closed = self::sqlList(self::CLOSED_STATUSES);

        return "CASE WHEN {$column} IN ({$open}) THEN 'open' WHEN {$column} IN ({$closed}) THEN 'closed' ELSE 'none' END";
    }

    /**
     * Expression SQL du changement, à partir des présences à la première (`$before`) et à la
     * seconde ère (`$after`) ; NULL quand rien n'a changé pour une portion déjà fermée ou
     * absente aux deux ères.
     */
    public static function changeSql(string $before, string $after): string
    {
        return "CASE
            WHEN {$after} = 'open' AND {$before} = 'open' THEN '".self::Kept->value."'
            WHEN {$after} = 'open' THEN '".self::Opened->value."'
            WHEN {$after} = 'closed' AND {$before} <> 'closed' THEN '".self::Closed->value."'
            WHEN {$after} = 'none' AND {$before} <> 'none' THEN '".self::Removed->value."'
        END";
    }

    /** Liste SQL des statuts « en service » (pour un `IN (…)`). */
    public static function openStatusesSql(): string
    {
        return self::sqlList(self::OPEN_STATUSES);
    }

    /** @param list<string> $values */
    private static function sqlList(array $values): string
    {
        return implode(',', array_map(fn (string $v): string => "'{$v}'", $values));
    }
}

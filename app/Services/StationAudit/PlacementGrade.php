<?php

namespace App\Services\StationAudit;

/**
 * Note de placement d'une gare (docs/AUDIT-GARES.md, « Placement : vérifier parfaitement »).
 *
 * Seuils dans PlacementGrader : AGREE_M, CLOSE_M, DISAGREE_PRECISE_M, DISAGREE_COARSE_M.
 */
enum PlacementGrade: string
{
    /** Une source indépendante au moins à moins de 20 m, aucune source précise à plus de 100 m. */
    case Agree = 'agree';
    /** La source indépendante la plus proche est entre 20 et 100 m. */
    case Close = 'close';
    /** Aucune source indépendante proche : une seule famille de sources, ou seulement des positions grossières. */
    case Isolated = 'isolated';
    /** Une source précise à plus de 100 m, ou une source grossière à plus de 1 km. */
    case Disagree = 'disagree';
    /** Aucune position de source rattachée à la gare. */
    case None = 'none';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $g): string => $g->value, self::cases());
    }
}

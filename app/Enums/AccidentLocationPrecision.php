<?php

namespace App\Enums;

/**
 * D'où vient la position d'un accident, du plus sûr au plus vague. Elle fixe le rayon de
 * recherche des lignes candidates et la confiance du rattachement.
 */
enum AccidentLocationPrecision: string
{
    /** Coordonnées données par la source (Wikidata, infobox Wikipédia). */
    case Exact = 'exact';

    /** Passage à niveau cité par la source et retrouvé dans le jeu SNCF Réseau. */
    case LevelCrossing = 'level_crossing';

    /** Gare citée par la source (« en gare de… ») et retrouvée dans VFF. */
    case Station = 'station';

    /** Seule la commune est connue : centre de la commune. */
    case Commune = 'commune';

    /**
     * La position permet-elle de dire « à X m de telle gare » ? Un centre de commune ne le permet pas : la
     * gare « la plus proche » de ce point n'est qu'une coïncidence (Paris : jamais « à 0 m de Paris-Nord »).
     */
    public function pinpointsStation(): bool
    {
        return $this !== self::Commune;
    }

    /** Rayon, en mètres, dans lequel une ligne peut être le lieu de l'accident. */
    public function matchRadiusM(): int
    {
        return match ($this) {
            self::Exact => 800,
            self::LevelCrossing => 150,
            self::Station => 1500,
            self::Commune => 5000,
        };
    }
}

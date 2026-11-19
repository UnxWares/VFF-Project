<?php

namespace App\Services\StationPositioning;

/**
 * Nature d'un point d'appui du calage des PK d'une liste de gares.
 *
 *   - Station : gare de la liste dont la position est fiable (SNCF, OSM, Wikidata, OHM,
 *     correction sourcée), à moins de PkConsensus::ANCHOR_MAX_M de la voie ;
 *   - Place   : lieu-dit habité homonyme (BD TOPO, géocodage IGN) proche de la voie :
 *     la halte qui porte son nom est à quelques centaines de mètres, au plus.
 *
 * Un lieu-dit vote pour ou contre un calage ; il ne sert à interpoler que faute de gare.
 */
enum AnchorKind: string
{
    case Station = 'station';
    case Place = 'place';

    /** Poids dans le consensus : une gare vaut deux lieux-dits. */
    public function weight(): int
    {
        return match ($this) {
            self::Station => 2,
            self::Place => 1,
        };
    }

    /** Écart toléré au modèle « PK = distance », hors part proportionnelle (m). */
    public function toleranceM(): int
    {
        return match ($this) {
            self::Station => 150,
            self::Place => 500,
        };
    }
}

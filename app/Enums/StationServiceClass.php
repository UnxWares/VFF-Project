<?php

namespace App\Enums;

/**
 * Desserte voyageurs d'une gare AUJOURD'HUI, d'après les horaires publiés en
 * données ouvertes (GTFS) et les indices de trains touristiques et de vélorails
 * (vff:import-station-service, table station_services).
 */
enum StationServiceClass: string
{
    /** Trains voyageurs presque chaque semaine du flux en cours. */
    case Regular = 'regular';
    /** Trains voyageurs certains jours ou une saison seulement. */
    case Periodic = 'periodic';
    /** Train touristique (aucun train du service public). */
    case Tourist = 'tourist';
    /** Vélorail sur la ligne : plus de train voyageurs, mais la ligne a un usage. */
    case Velorail = 'velorail';
    /** Cars ou bus seulement (cars TER, substitution) : aucun train. */
    case BusOnly = 'bus_only';
    /** Aucun train ni car prévu dans les flux qui couvrent la gare. */
    case None = 'none';
    /** Aucun flux à jour ne couvre la gare (réseau sans horaires ouverts, flux périmé). */
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Regular => 'desserte régulière',
            self::Periodic => 'desserte périodique ou saisonnière',
            self::Tourist => 'train touristique',
            self::Velorail => 'vélorail',
            self::BusOnly => 'car ou bus seulement',
            self::None => 'aucune desserte',
            self::Unknown => 'inconnu',
        };
    }

    /**
     * Statut de l'ère courante que ce classement proposerait (règle 6 : une gare plus
     * exploitée est fermée). Fret seul gardé quand la gare l'était ; null si inconnu.
     */
    public function proposedStatus(StationStatus $current): ?StationStatus
    {
        return match ($this) {
            self::Regular, self::Periodic => StationStatus::Active,
            self::Tourist => StationStatus::Tourist,
            self::Velorail, self::BusOnly, self::None => $current === StationStatus::NoCommercialService
                ? StationStatus::NoCommercialService : StationStatus::Declassified,
            self::Unknown => null,
        };
    }
}

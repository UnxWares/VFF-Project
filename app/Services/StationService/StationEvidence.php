<?php

namespace App\Services\StationService;

/**
 * Indices réunis pour une gare : meilleure desserte de chaque sorte parmi les flux
 * qui la couvrent (EvidenceCollector) et indices sans horaires (VFF, OSM).
 */
final readonly class StationEvidence
{
    public function __construct(
        /** Trains d'un flux à jour, sur tout son horizon. */
        public ?ServiceProfile $rail = null,
        /** Les mêmes trains sur les quatre premières semaines seulement. */
        public ?ServiceProfile $railSoon = null,
        /** Tram-trains d'un flux à jour (Île-de-France). */
        public ?ServiceProfile $tram = null,
        /** Cars d'un flux à jour (cars TER, substitution). */
        public ?ServiceProfile $bus = null,
        /** Trains de la version de mi-juin du GTFS SNCF (été). */
        public ?ServiceProfile $summer = null,
        /** Trains de la version de mi-avril du GTFS SNCF, avril et mai seulement (printemps). */
        public ?ServiceProfile $spring = null,
        /** Trains d'un flux dont l'horizon est passé. */
        public ?ServiceProfile $stale = null,
        /** Trains d'un flux de train touristique (horaires publiés). */
        public ?ServiceProfile $touristTrains = null,
        public HeritageHints $heritage = new HeritageHints,
        /** Un flux à jour couvre la région de la gare (faux en Corse : flux des CFC périmé). */
        public bool $covered = true,
    ) {}
}

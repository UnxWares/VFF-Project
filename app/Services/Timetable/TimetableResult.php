<?php

namespace App\Services\Timetable;

/**
 * Résultat du calcul : desserte du jour type de chaque gare et de chaque ligne, et mesures du rapprochement.
 */
final readonly class TimetableResult
{
    /**
     * @param  array<int, array<string, mixed>>  $stations  station_id => bloc (voir TimetableAggregator)
     * @param  array<int, array<string, mixed>>  $lines  line_id => bloc
     * @param  list<array<string, mixed>>  $feeds  flux lus : clé, version, jour, horizon, licence, courses
     * @param  list<array<string, mixed>>  $unmatched  zones d'arrêt de trains sans gare de VFF (flux, nom, UIC, trains)
     */
    public function __construct(
        public array $stations,
        public array $lines,
        public array $feeds,
        public array $unmatched,
    ) {}
}

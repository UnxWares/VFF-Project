<?php

namespace App\Services\Timetable;

use App\Services\StationService\GtfsStop;

/**
 * Les trains d'un flux GTFS un jour donné : zones d'arrêt et courses (arrêts dans l'ordre, heures en secondes depuis minuit).
 */
final readonly class TypicalDay
{
    /**
     * @param  list<GtfsStop>  $zones  zones d'arrêt (l'indice sert de clé dans les courses)
     * @param  list<array{type: string, stops: list<array{int, ?int, ?int}>}>  $runs  arrêt = [zone, arrivée, départ] ; seuls les arrêts
     *                                                                                où l'on monte ou descend sont gardés
     * @param  array{string, string}  $horizon  début et fin de validité du flux (AAAA-MM-JJ)
     */
    public function __construct(
        public string $feed,
        public ?string $version,
        public int $date,
        public array $horizon,
        public array $zones,
        public array $runs,
        public int $skippedRuns = 0,
    ) {}
}

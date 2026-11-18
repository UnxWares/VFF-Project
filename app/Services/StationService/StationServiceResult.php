<?php

namespace App\Services\StationService;

/**
 * Résultat d'une analyse : une ligne par gare de VFF, arrêts ferroviaires des flux
 * qu'aucune gare de VFF ne rapproche, et résumé de chaque flux lu.
 */
final readonly class StationServiceResult
{
    /**
     * @param  array<int, StationServiceRow>  $rows  identifiant de gare => classement
     * @param  list<array<string, mixed>>  $unmatched
     * @param  list<array<string, mixed>>  $feeds
     */
    public function __construct(
        public array $rows,
        public array $unmatched,
        public array $feeds,
    ) {}
}

<?php

namespace App\GraphQL\Resolvers;

use App\Models\Station;
use App\Services\StationAudit\StationAuditQuery;

/**
 * Champ `Station.audit` : note de placement, notes des dates et dates candidates d'une gare, au
 * même contrat que `GET /api/v1/stations/{station}/audit` (StationAuditQuery::forStation). Nul
 * tant que la gare n'a pas été auditée.
 */
final class StationAuditResolver
{
    public function __construct(private readonly StationAuditQuery $audits) {}

    /** @return array<string, mixed>|null */
    public function __invoke(Station $station): ?array
    {
        return $this->audits->forStation((int) $station->id);
    }
}

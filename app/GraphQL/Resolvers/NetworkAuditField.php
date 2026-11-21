<?php

namespace App\GraphQL\Resolvers;

use App\Services\SegmentAudit\NetworkAudits;

/**
 * Requêtes `segmentAudits` et `stationAudits` : mêmes filtres, mêmes pages et mêmes champs que
 * `GET /api/v1/audits/segments` et `/stations` (NetworkAudits).
 */
final readonly class NetworkAuditField
{
    public function __construct(private NetworkAudits $audits) {}

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function segments(mixed $root, array $args): array
    {
        return $this->audits->segments($args, $args['limit'] ?? null, $args['offset'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function stations(mixed $root, array $args): array
    {
        return $this->audits->stations($args, $args['limit'] ?? null, $args['offset'] ?? null);
    }
}

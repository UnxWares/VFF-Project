<?php

namespace App\GraphQL\Resolvers;

use App\Services\SegmentAudit\NetworkAudits;
use Illuminate\Support\Facades\Validator;

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
        $args = $this->territory($args, NetworkAudits::segmentRules());

        return $this->audits->segments($args, $args['limit'] ?? null, $args['offset'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function stations(mixed $root, array $args): array
    {
        $args = $this->territory($args, NetworkAudits::stationRules());

        return $this->audits->stations($args, $args['limit'] ?? null, $args['offset'] ?? null);
    }

    /**
     * Département et région contrôlés par les règles de l'API REST et du serveur MCP (le schéma ne
     * porte pas la liste des codes) : un code inconnu est une erreur de validation, pas une page vide.
     * Département en majuscules d'abord (`2a` accepté, comme en REST).
     *
     * @param  array<string, mixed>  $args
     * @param  array<string, list<mixed>>  $rules
     * @return array<string, mixed>
     */
    private function territory(array $args, array $rules): array
    {
        if (is_string($args['department'] ?? null)) {
            $args['department'] = strtoupper($args['department']);
        }
        Validator::make($args, array_intersect_key($rules, array_flip(['department', 'region'])))->validate();

        return $args;
    }
}

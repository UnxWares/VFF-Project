<?php

namespace App\GraphQL\Resolvers;

use App\Services\SiteStats;

/**
 * Requête GraphQL `stats` : mêmes chiffres que l'API REST `GET /api/v1/stats` (SiteStats), mêmes noms de champs.
 */
final class SiteStatsResolver
{
    public function __construct(private readonly SiteStats $stats) {}

    /** @return array<string, mixed> */
    public function __invoke(): array
    {
        return $this->stats->counts() + ['profile' => $this->stats->profile()];
    }
}

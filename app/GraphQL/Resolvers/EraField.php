<?php

namespace App\GraphQL\Resolvers;

use App\Models\Era;
use App\Services\NetworkStats;
use Illuminate\Support\Facades\DB;

/**
 * Champs calculés d'une ère GraphQL : date de l'instantané, présence de données, statistiques du
 * réseau (NetworkStats::eraProfile, mêmes chiffres que `GET /api/v1/eras/{year}`).
 */
final class EraField
{
    public function __construct(private readonly NetworkStats $stats) {}

    public function snapshotDate(Era $era): ?string
    {
        return $era->is_current ? null : sprintf('%04d-12-31', $era->year);
    }

    public function hasData(Era $era): bool
    {
        return DB::table('line_segments')->where('era_id', $era->id)->exists();
    }

    /** @return array<string, mixed> */
    public function stats(Era $era): array
    {
        $profile = $this->stats->eraProfile($era->id);
        $profile['open_km_by_traffic'] = array_map(fn (string $code, int $km) => ['code' => $code, 'km' => $km],
            array_keys($profile['open_km_by_traffic']), $profile['open_km_by_traffic']);
        $profile['stations_by_status'] = array_map(fn (string $code, int $count) => ['code' => $code, 'count' => $count],
            array_keys($profile['stations_by_status']), $profile['stations_by_status']);

        return $profile;
    }
}

<?php

namespace App\GraphQL\Resolvers;

use App\Models\Line;
use App\Services\Profiles\LineProfiles;

/**
 * Champ GraphQL `Line.profile` : même contenu que `GET /api/v1/lines/{line}/profile`, points
 * rendus en objets `{distance_m, altitude_m}`.
 */
final readonly class LineProfileField
{
    public function __construct(private LineProfiles $profiles) {}

    /**
     * @param  array{max_points?: int|null}  $args
     * @return array<string, mixed>|null
     */
    public function __invoke(Line $line, array $args): ?array
    {
        $profile = $this->profiles->forLine($line, $args['max_points'] ?? null);
        if ($profile === null) {
            return null;
        }
        $profile['points'] = array_map(
            fn (array $p): array => ['distance_m' => $p[0], 'altitude_m' => $p[1]],
            $profile['points'],
        );

        return $profile;
    }
}

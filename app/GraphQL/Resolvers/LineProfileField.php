<?php

namespace App\GraphQL\Resolvers;

use App\Models\Line;
use App\Models\LineProfile;
use App\Services\Profiles\LineProfiles;

/**
 * Champ GraphQL `Line.profile` : même contenu que `GET /api/v1/lines/{line}/profile`, points
 * rendus en objets `{distance_m, altitude_m, pk_m}` ; sous-champs `samples` (référentiel point par
 * point, comme `/profile/points`) et `altitude_at` (comme `/altitude`).
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
        $model = $this->profiles->find($line);
        if ($model === null) {
            return null;
        }
        $profile = $this->profiles->present($model, $args['max_points'] ?? null);
        $profile['points'] = array_map(
            fn (array $p): array => ['distance_m' => $p[0], 'altitude_m' => $p[1], 'pk_m' => $p[2] ?? null],
            $profile['points'],
        );

        // Clé interne (absente du schéma) pour les sous-champs samples et altitude_at.
        return $profile + ['line_profile_id' => $model->id];
    }

    /**
     * @param  array<string, mixed>  $root
     * @param  array{pk_from_m?: int|null, pk_to_m?: int|null, max_points?: int|null}  $args
     * @return list<array<string, mixed>>
     */
    public function samples(array $root, array $args): array
    {
        $profile = LineProfile::find($root['line_profile_id'] ?? null);

        return $profile === null ? [] : $this->profiles->detailedPoints(
            $profile,
            $args['pk_from_m'] ?? null,
            $args['pk_to_m'] ?? null,
            $args['max_points'] ?? null,
        );
    }

    /**
     * @param  array<string, mixed>  $root
     * @param  array{pk_m: int}  $args
     * @return array<string, mixed>|null
     */
    public function altitudeAt(array $root, array $args): ?array
    {
        $profile = LineProfile::find($root['line_profile_id'] ?? null);

        return $profile === null ? null : $this->profiles->altitudeAtPk($profile, $args['pk_m']);
    }
}

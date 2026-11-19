<?php

namespace App\GraphQL\Resolvers;

use App\GraphQL\Resolvers\Concerns\FindsEra;
use App\Models\Line;
use App\Services\LinePk;

/**
 * Champ GraphQL `Line.pk(lat, lng, era)` : même résultat que `GET /api/v1/lines/{line}/pk`
 * (LinePk::locate). Nul quand la ligne n'a pas de tracé à l'ère demandée.
 */
final readonly class LinePkField
{
    use FindsEra;

    public function __construct(private LinePk $pk) {}

    /**
     * @param  array{lat: float, lng: float, era?: int|null}  $args
     * @return array<string, mixed>|null
     */
    public function __invoke(Line $line, array $args): ?array
    {
        return $this->pk->locate($line, self::findEra($args['era'] ?? null)->id, $args['lat'], $args['lng']);
    }
}

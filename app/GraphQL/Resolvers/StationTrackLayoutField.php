<?php

namespace App\GraphQL\Resolvers;

use App\Models\Station;
use App\Services\TrackLayout\StationTrackLayout;

/**
 * Voies d'une gare en GraphQL (`Station.track_layout`) : même contenu que l'API REST
 * (`GET /api/v1/stations/{station}/track-layout`), une ligne et des familles en option.
 */
final class StationTrackLayoutField
{
    public function __construct(private readonly StationTrackLayout $layout) {}

    /**
     * @param  array{line?: ?string, kinds?: ?list<string>}  $args
     * @return array<string, mixed>
     */
    public function __invoke(Station $station, array $args): array
    {
        return $this->layout->forStation($station, $args['kinds'] ?? null, $args['line'] ?? null);
    }
}

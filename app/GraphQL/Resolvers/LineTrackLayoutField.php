<?php

namespace App\GraphQL\Resolvers;

use App\Models\Line;
use App\Services\TrackLayout\TrackLayout;

/**
 * Schéma des voies d'une ligne en GraphQL (`Line.track_layout`) : même contenu que l'API REST
 * (`GET /api/v1/lines/{line}/track-layout`), portion en mètres et familles en option.
 */
final class LineTrackLayoutField
{
    public function __construct(private readonly TrackLayout $layout) {}

    /**
     * @param  array{from_pk_m?: ?int, to_pk_m?: ?int, kinds?: ?list<string>}  $args
     * @return array<string, mixed>
     */
    public function __invoke(Line $line, array $args): array
    {
        return $this->layout->forLine($line, $args['from_pk_m'] ?? null, $args['to_pk_m'] ?? null, $args['kinds'] ?? null);
    }
}

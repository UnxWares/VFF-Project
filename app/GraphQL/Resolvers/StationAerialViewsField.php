<?php

namespace App\GraphQL\Resolvers;

use App\Services\Media\Aerial\AerialImages;

/**
 * Vues aériennes historiques d'une gare en GraphQL (champ `StationSheet.aerial_views`) : mêmes vues que
 * `GET /api/v1/stations/{station}/aerial`, tirées du slug et de la position de la fiche. Aucune lecture amont ici :
 * l'image n'est demandée à l'IGN que par le relais `/media/aerial/{gare}/{période}.jpg`.
 */
final class StationAerialViewsField
{
    /**
     * @param  array<string, mixed>  $sheet  fiche de la gare (StationSheetResource)
     * @return list<array<string, mixed>>
     */
    public function __invoke(array $sheet): array
    {
        if (! is_string($sheet['slug'] ?? null) || ! is_numeric($sheet['lat'] ?? null) || ! is_numeric($sheet['lng'] ?? null)) {
            return [];
        }

        return AerialImages::views($sheet['slug'], (float) $sheet['lat'], (float) $sheet['lng']);
    }
}

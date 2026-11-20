<?php

namespace App\GraphQL\Resolvers;

use App\Http\Resources\MediaResource;
use App\Models\Media;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Champs calculés d'un média (`thumb_url`, `display_url`, `provider_label`, `date_precision`,
 * `depicted_year_to`, `attribution_required`, `featured`, `lat`, `lng`) : la même valeur que
 * l'API REST, lue dans MediaResource.
 */
final class MediaField
{
    public function __invoke(Media $media, array $args, GraphQLContext $context, ResolveInfo $info): mixed
    {
        return match ($info->fieldName) {
            'lat', 'lng' => $this->position($media, $info->fieldName),
            default => MediaResource::make($media)->resolve(new Request)[$info->fieldName] ?? null,
        };
    }

    /** Position sélectionnée avec la liste (MediaQuery::POSITION), sinon lue pour ce seul média. */
    private function position(Media $media, string $axis): ?float
    {
        if (! array_key_exists('lat', $media->getAttributes())) {
            $row = DB::selectOne('SELECT ST_Y(location) AS lat, ST_X(location) AS lng FROM media WHERE id = ?', [$media->id]);
            $media->setAttribute('lat', $row?->lat);
            $media->setAttribute('lng', $row?->lng);
        }

        return $media->getAttribute($axis) !== null ? (float) $media->getAttribute($axis) : null;
    }
}

<?php

namespace App\GraphQL\Resolvers;

use App\Models\LineSegment;
use Illuminate\Support\Facades\DB;

class LineSegmentResolver
{
    /**
     * Retourne la géométrie du segment en GeoJSON (string).
     * Le client peut JSON.parse() pour usage MapLibre.
     */
    public function geojson(LineSegment $segment): ?string
    {
        $row = DB::selectOne(
            'SELECT ST_AsGeoJSON(geom::geometry) AS gj FROM line_segments WHERE id = ?',
            [$segment->id],
        );

        return $row?->gj;
    }
}

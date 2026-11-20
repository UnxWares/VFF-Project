<?php

namespace App\GraphQL\Resolvers;

use App\Models\LineSegment;
use App\Services\TraceExport\LineTraceExport;
use Illuminate\Support\Facades\DB;

class LineSegmentResolver
{
    public function __construct(private readonly LineTraceExport $export) {}

    /**
     * Retourne la géométrie du segment en GeoJSON (string).
     * Le client peut JSON.parse() pour usage MapLibre.
     *
     * Même règle que les exports REST (GeoJSON, GPX, KML) : la géométrie d'un tronçon dont la source
     * n'a pas de licence ouverte écrite (registre des droits) n'est pas redistribuée, le champ vaut null.
     */
    public function geojson(LineSegment $segment): ?string
    {
        if (! $this->export->redistributable($segment->source)) {
            return null;
        }

        $row = DB::selectOne(
            'SELECT ST_AsGeoJSON(geom::geometry) AS gj FROM line_segments WHERE id = ?',
            [$segment->id],
        );

        return $row?->gj;
    }
}

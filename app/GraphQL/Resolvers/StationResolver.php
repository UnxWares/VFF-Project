<?php

namespace App\GraphQL\Resolvers;

use App\Models\Station;
use Illuminate\Support\Facades\DB;

class StationResolver
{
    /** Borne basse d'une ouverture encadrée (précision range, règle G3) : provenance.opened_after, nulle sinon. */
    public function openedAfter(Station $station): ?int
    {
        $after = $station->provenance['opened_after'] ?? null;

        return $station->opened_precision === 'range' && is_numeric($after) ? (int) $after : null;
    }

    public function lat(Station $station): ?float
    {
        $row = DB::selectOne('SELECT ST_Y(geom::geometry) AS lat FROM stations WHERE id = ?', [$station->id]);

        return $row?->lat !== null ? (float) $row->lat : null;
    }

    public function lng(Station $station): ?float
    {
        $row = DB::selectOne('SELECT ST_X(geom::geometry) AS lng FROM stations WHERE id = ?', [$station->id]);

        return $row?->lng !== null ? (float) $row->lng : null;
    }
}

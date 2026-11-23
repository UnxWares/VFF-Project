<?php

namespace App\GraphQL\Resolvers;

use App\Models\Station;
use App\Support\RedistributableDates;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class StationResolver
{
    /** @var array<int, array<string, mixed>> dates servies hors du site, par gare (une lecture par gare et par requête) */
    private array $served = [];

    /** Date d'ouverture servie hors du site (RedistributableDates) : celle d'une source servie, ou nulle. */
    public function openedAt(Station $station): ?Carbon
    {
        $date = $this->dates($station)['opened_at'];

        return $date === null ? null : Carbon::parse((string) $date)->startOfDay();
    }

    /** Précision de la date d'ouverture servie (nulle avec elle). */
    public function openedPrecision(Station $station): ?string
    {
        return $this->dates($station)['opened_at'] === null ? null : $this->dates($station)['opened_precision'];
    }

    /** Borne basse d'une ouverture encadrée (précision range, règle G3) : provenance.opened_after, nulle sinon. */
    public function openedAfter(Station $station): ?int
    {
        $dates = $this->dates($station);
        $after = $dates['opened_after'];

        return $dates['opened_precision'] === 'range' && is_numeric($after) ? (int) $after : null;
    }

    /** Date de fermeture servie hors du site (RedistributableDates) : celle d'une source servie, ou nulle. */
    public function closedAt(Station $station): ?Carbon
    {
        $date = $this->dates($station)['closed_at'];

        return $date === null ? null : Carbon::parse((string) $date)->startOfDay();
    }

    /** @return array<string, mixed> */
    private function dates(Station $station): array
    {
        return $this->served[(int) $station->id] ??= app(RedistributableDates::class)->station([
            'slug' => $station->slug,
            'opened_at' => $station->opened_at?->format('Y-m-d'), 'opened_precision' => $station->opened_precision,
            'opened_after' => $station->provenance['opened_after'] ?? null,
            'closed_at' => $station->closed_at?->format('Y-m-d'), 'closed_precision' => $station->closed_precision,
        ]);
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

<?php

namespace App\Services\Accidents\Context;

use App\Enums\AccidentDatePrecision;
use App\Enums\AccidentLocationPrecision;
use App\Models\Accident;
use App\Models\AccidentLine;
use App\Services\Accidents\AccidentCandidate;
use App\Services\Accidents\AccidentLineMatcher;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Corrections de lieu d'une fiche d'accident d'après son contexte sourcé (vff:enrich-accidents) :
 *
 *   - commune : nom, département et position (centre de la commune, précision « commune ») tirés du référentiel
 *     des communes de VFF, gare la plus proche recalculée ;
 *   - lignes : rattachement refait au nouveau lieu (AccidentLineMatcher), les lignes déjà citées par une source
 *     (`cited_as`) gardées comme indices, et leur titre et permalien conservés.
 *
 * Les valeurs d'avant sont rendues par restore() quand la correction disparaît du fichier.
 */
final class AccidentRecordCorrector
{
    public function __construct(private readonly AccidentLineMatcher $matcher) {}

    /** @return array<string, mixed> valeurs d'avant (pour restore) */
    public function snapshot(Accident $a): array
    {
        $point = DB::selectOne('SELECT ST_Y(geom) AS lat, ST_X(geom) AS lng FROM accidents WHERE id = ?', [$a->id]);

        return [
            'commune_name' => $a->commune_name,
            'department_code' => $a->department_code,
            'location_precision' => $a->location_precision?->value,
            'location_source' => $a->location_source,
            'lat' => $point?->lat !== null ? (float) $point->lat : null,
            'lng' => $point?->lng !== null ? (float) $point->lng : null,
            'nearest_station_id' => $a->nearest_station_id,
            'nearest_station_distance_m' => $a->nearest_station_distance_m,
        ];
    }

    /** Place la fiche au centre de la commune donnée (code INSEE du référentiel de VFF). */
    public function moveToCommune(Accident $a, string $insee): void
    {
        $commune = DB::selectOne(
            "SELECT nom, departement, ST_Y(geom) AS lat, ST_X(geom) AS lng FROM communes
             WHERE code_insee = ? ORDER BY (type = 'commune-actuelle') DESC LIMIT 1",
            [$insee],
        );
        if ($commune === null) {
            throw new RuntimeException("commune {$insee} absente du référentiel des communes");
        }
        $a->commune_insee = $insee;
        $a->commune_name = (string) $commune->nom;
        $a->department_code = $commune->departement !== null ? (string) $commune->departement : null;
        $a->location_precision = AccidentLocationPrecision::Commune;
        $a->location_source = 'context';
        $a->geom = Point::makeGeodetic((float) $commune->lat, (float) $commune->lng);
        $this->relocate($a, (float) $commune->lat, (float) $commune->lng);
    }

    /** @param  array<string, mixed>  $before  valeurs de snapshot() */
    public function restore(Accident $a, ?string $insee, array $before): void
    {
        $a->commune_insee = $insee;
        $a->commune_name = $before['commune_name'] ?? null;
        $a->department_code = $before['department_code'] ?? null;
        $a->location_precision = isset($before['location_precision']) ? AccidentLocationPrecision::from($before['location_precision']) : null;
        $a->location_source = $before['location_source'] ?? null;
        $a->geom = isset($before['lat'], $before['lng']) ? Point::makeGeodetic((float) $before['lat'], (float) $before['lng']) : null;
        if (isset($before['lat'], $before['lng'])) {
            $this->relocate($a, (float) $before['lat'], (float) $before['lng']);
        }
        $a->nearest_station_id = $before['nearest_station_id'] ?? null;
        $a->nearest_station_distance_m = $before['nearest_station_distance_m'] ?? null;
    }

    /** Gare la plus proche et lignes rattachées refaites pour la position donnée. */
    private function relocate(Accident $a, float $lat, float $lng): void
    {
        $c = new AccidentCandidate('context:'.$a->slug);
        $c->lat = $lat;
        $c->lng = $lng;
        $c->locationPrecision = $a->location_precision ?? AccidentLocationPrecision::Commune;
        $c->date = $a->occurred_on->toDateString();
        $c->precision = $a->date_precision ?? AccidentDatePrecision::Day;
        $cited = AccidentLine::query()->where('accident_id', $a->id)->whereNotNull('cited_as')->get(['line_id', 'cited_as', 'cited_url']);
        $c->lineArticles = $cited->pluck('cited_as')->unique()->values()->all();

        $station = $this->matcher->nearestStation($c);
        $a->nearest_station_id = $station['id'] ?? null;
        $a->nearest_station_distance_m = $station['distance_m'] ?? null;

        $links = $this->matcher->match($c);
        if ($links === []) {
            return;
        }
        AccidentLine::query()->where('accident_id', $a->id)->delete();
        foreach ($links as $link) {
            $source = $cited->firstWhere('line_id', $link['line_id']);
            (new AccidentLine)->forceFill([
                ...$link,
                'accident_id' => $a->id,
                'cited_as' => $source?->cited_as,
                'cited_url' => $source?->cited_url,
            ])->save();
        }
    }
}

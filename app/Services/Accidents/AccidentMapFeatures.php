<?php

namespace App\Services\Accidents;

use App\Enums\AccidentKind;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Accidents à poser sur la carte (/maps, calque « Accidents ») : une FeatureCollection GeoJSON
 * compacte, un point par accident dont la position est connue. Les accidents sans lieu (6 fiches
 * à l'import du 19/11/2026) n'y figurent pas : aucune position n'est inventée.
 *
 * `precision` dit d'où vient le point (cf. AccidentLocationPrecision) : `commune` = centre de la
 * commune, le lieu exact n'est pas connu ; la carte le montre comme une zone pâle.
 */
final class AccidentMapFeatures
{
    /** Durée de cache (s) : les accidents ne changent qu'à un import (vff:import-accidents). */
    private const TTL = 600;

    private const CACHE_KEY = 'accidents:map-features:v1';

    /** Décimales des coordonnées : 5, soit environ 1 m (la source la plus précise ne fait pas mieux). */
    private const COORD_DECIMALS = 5;

    /**
     * @param  int|null  $toYear  accidents survenus jusqu'à cette année incluse (null : tous)
     * @return array{type: 'FeatureCollection', features: list<array<string, mixed>>}
     */
    public function collection(?int $toYear = null): array
    {
        $features = Cache::remember(self::CACHE_KEY, self::TTL, fn (): array => $this->features());
        if ($toYear !== null) {
            $features = array_values(array_filter($features, fn (array $f): bool => $f['properties']['year'] <= $toYear));
        }

        return ['type' => 'FeatureCollection', 'features' => $features];
    }

    /** @return list<array<string, mixed>> */
    private function features(): array
    {
        $hidden = AccidentKind::HIDDEN;
        $rows = DB::select(
            'SELECT slug, occurred_on, date_precision, kind, commune_name, department_code,
                    location_precision, deaths, injuries, review_status,
                    ST_X(geom) AS lng, ST_Y(geom) AS lat
             FROM accidents
             WHERE geom IS NOT NULL AND kind NOT IN ('.implode(', ', array_fill(0, count($hidden), '?')).')
             ORDER BY occurred_on, id',
            $hidden,
        );

        return array_map(fn (object $r): array => [
            'type' => 'Feature',
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [round((float) $r->lng, self::COORD_DECIMALS), round((float) $r->lat, self::COORD_DECIMALS)],
            ],
            'properties' => [
                'slug' => $r->slug,
                'date' => substr((string) $r->occurred_on, 0, 10),
                'date_precision' => $r->date_precision,
                'year' => (int) substr((string) $r->occurred_on, 0, 4),
                'kind' => $r->kind,
                'place' => $r->commune_name,
                'department' => $r->department_code,
                'precision' => $r->location_precision,
                'deaths' => $r->deaths === null ? null : (int) $r->deaths,
                'injuries' => $r->injuries === null ? null : (int) $r->injuries,
                'to_review' => $r->review_status === 'to_review',
            ],
        ], $rows);
    }
}

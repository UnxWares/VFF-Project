<?php

namespace App\Services\Lines;

use App\Models\Line;
use App\Services\CatalogSearch;
use App\Services\Profiles\LineProfiles;
use App\Support\Search\PkQuery;
use Illuminate\Support\Facades\DB;

/**
 * Position d'un point kilométrique (PK) sur une ligne : entre quelles gares il tombe, dans quelle commune et
 * où le placer sur la carte. Une seule lecture pour la recherche (« 570000 PK 120 », `GET /api/v1/search`,
 * boîte de l'en-tête, page `/search`) et l'API REST (`GET /api/v1/lines/{line}/at-pk`).
 *
 * Les PK sont ceux des gares de la ligne (`line_stops.pk_m`, PK de SNCF Réseau pour la plupart), à l'ère
 * courante, sinon à la dernière ère où la ligne a des gares (ligne fermée). Les bornes de la ligne sont le PK de
 * sa première et de sa dernière gare. Un PK entre deux gares est placé sur le tracé du profil en long (PK
 * calés sur les gares) ; quand le tracé sous ce point n'est pas redistribuable, ou sans profil, il est placé
 * en ligne droite entre les deux gares (`position_method` = `stations`, approximatif).
 */
final readonly class LinePkPosition
{
    /** Lignes essayées au plus quand la requête désigne la ligne par son nom. */
    public const MAX_LINES = 3;

    public function __construct(private CatalogSearch $search, private LineProfiles $profiles) {}

    /**
     * Positions que désigne une requête de recherche, vide si ce n'est pas une position. Parmi les lignes
     * qui correspondent au nom, celles où le PK existe ; sinon la première, hors de ses bornes (`in_range` faux).
     *
     * @return list<array<string, mixed>>
     */
    public function find(string $q): array
    {
        $parsed = PkQuery::parse($q);
        if ($parsed === null) {
            return [];
        }
        $found = $this->search->search($parsed['text'], self::MAX_LINES, 0, 0, 0)['lines'];
        $lines = Line::whereIn('slug', array_column(array_filter($found, fn (array $l): bool => ! $l['approximate']), 'slug'))->get()->keyBy('slug');
        $positions = [];
        foreach ($found as $l) {
            $line = $lines[$l['slug']] ?? null;
            $at = $line === null ? null : $this->at($line, $parsed['pk_m']);
            if ($at !== null) {
                $positions[] = $at;
            }
        }
        $inRange = array_values(array_filter($positions, fn (array $p): bool => $p['in_range']));

        return $inRange !== [] ? $inRange : array_slice($positions, 0, 1);
    }

    /**
     * Null : la ligne n'a pas de gare, donc pas de PK connu.
     *
     * @return array{line: array{slug: string, code: ?string, name: string}, pk_m: int, pk_label: string, in_range: bool,
     *     bounds: array{from_m: int, to_m: int, from_label: string, to_label: string}, at_station: ?array<string, mixed>,
     *     between: ?array{from: array<string, mixed>, to: array<string, mixed>}, commune: ?string,
     *     lat: ?float, lng: ?float, position_method: ?string, position_withheld: ?string}|null
     */
    public function at(Line $line, int $pk): ?array
    {
        $stops = $this->stops($line);
        if ($stops === []) {
            return null;
        }
        $min = $stops[0]['pk_m'];
        $max = $stops[count($stops) - 1]['pk_m'];
        $out = [
            'line' => ['slug' => (string) $line->slug, 'code' => $line->code, 'name' => (string) $line->name],
            'pk_m' => $pk,
            'pk_label' => self::label($pk),
            'in_range' => $pk >= $min && $pk <= $max,
            'bounds' => ['from_m' => $min, 'to_m' => $max, 'from_label' => self::label($min), 'to_label' => self::label($max)],
            'at_station' => null,
            'between' => null,
            'commune' => null,
            'lat' => null,
            'lng' => null,
            'position_method' => null,
            'position_withheld' => null,
        ];
        if (! $out['in_range']) {
            return $out;
        }

        $below = null;
        $above = null;
        foreach ($stops as $s) {
            if ($s['pk_m'] <= $pk) {
                $below = $s;
            }
            if ($s['pk_m'] >= $pk && $above === null) {
                $above = $s;
            }
        }
        if ($below !== null && $below['pk_m'] === $pk) {
            $out['at_station'] = self::present($below);
            $out['commune'] = $below['city'];
            [$out['lat'], $out['lng'], $out['position_method']] = [round($below['lat'], 6), round($below['lng'], 6), 'station'];

            return $out;
        }
        $out['between'] = ['from' => self::present($below), 'to' => self::present($above)];
        // Commune de la gare la plus proche en PK (la même pour les deux, le plus souvent).
        $nearer = ($pk - $below['pk_m']) <= ($above['pk_m'] - $pk) ? $below : $above;
        $out['commune'] = $below['city'] === $above['city'] ? $below['city'] : $nearer['city'];

        $profile = $this->profiles->find($line);
        $on = $profile === null ? null : $this->profiles->altitudeAtPk($profile, $pk);
        if ($on !== null && $on['lat'] !== null) {
            [$out['lat'], $out['lng'], $out['position_method']] = [$on['lat'], $on['lng'], 'profile'];

            return $out;
        }
        $out['position_withheld'] = $on['position_withheld'] ?? null;
        $t = ($pk - $below['pk_m']) / ($above['pk_m'] - $below['pk_m']);
        $out['lat'] = round($below['lat'] + $t * ($above['lat'] - $below['lat']), 5);
        $out['lng'] = round($below['lng'] + $t * ($above['lng'] - $below['lng']), 5);
        $out['position_method'] = 'stations';

        return $out;
    }

    /** « 120+500 » : kilomètres et mètres. */
    public static function label(int $m): string
    {
        return sprintf('%d+%03d', intdiv($m, 1000), $m % 1000);
    }

    /**
     * Gares de la ligne dans l'ordre des PK, à l'ère courante ou, sans gare à celle-ci, à la dernière ère connue.
     *
     * @return list<array{pk_m: int, slug: string, name: string, city: ?string, lat: float, lng: float}>
     */
    private function stops(Line $line): array
    {
        $rows = DB::select(
            'SELECT ls.pk_m, s.slug, s.name, s.city, ST_X(s.geom::geometry) AS lng, ST_Y(s.geom::geometry) AS lat
             FROM line_stops ls JOIN stations s ON s.id = ls.station_id
             WHERE ls.line_id = ? AND ls.era_id = (
                 SELECT ls2.era_id FROM line_stops ls2 JOIN eras e ON e.id = ls2.era_id
                 WHERE ls2.line_id = ? ORDER BY e.is_current DESC, e.year DESC LIMIT 1)
             ORDER BY ls.pk_m, ls.position',
            [$line->id, $line->id],
        );

        return array_map(fn (object $r): array => [
            'pk_m' => (int) $r->pk_m, 'slug' => (string) $r->slug, 'name' => (string) $r->name, 'city' => $r->city,
            'lat' => (float) $r->lat, 'lng' => (float) $r->lng,
        ], $rows);
    }

    /**
     * @param  array{pk_m: int, slug: string, name: string, city: ?string}  $s
     * @return array{slug: string, name: string, city: ?string, pk_m: int, pk_label: string}
     */
    private static function present(array $s): array
    {
        return ['slug' => $s['slug'], 'name' => $s['name'], 'city' => $s['city'], 'pk_m' => $s['pk_m'], 'pk_label' => self::label($s['pk_m'])];
    }
}

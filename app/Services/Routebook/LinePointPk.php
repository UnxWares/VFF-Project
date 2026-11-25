<?php

namespace App\Services\Routebook;

use App\Services\Lines\LineSchematic;
use App\Services\LineStations;
use Illuminate\Support\Facades\DB;

/**
 * PK de ligne des bouts d'un morceau d'itinéraire, comme le schéma de ligne (LineSchematic) place ses bifurcations :
 *
 *   - une gare de la ligne : son PK de rattachement (`station_lines.pk_m`) ;
 *   - un autre point (changement de ligne en pleine voie, gare sans PK) : PK lu sur la forme SNCF de la ligne
 *     (`sncf_line_geometries`, tronçon le plus proche à LineSchematic::MAX_SNAP_M au plus, mesuré depuis la gare de
 *     PK sourcé la plus proche, voir onSncfShape), sinon interpolé entre les deux gares de la ligne qui l'encadrent (LineSchematic::interpolate).
 *
 * Précision : `exact` pour le PK sourcé d'une gare, `derived` pour un PK de gare projeté (StationPkProjector) ou lu
 * sur la forme SNCF, `approximate` pour une interpolation entre gares. Deux requêtes au plus, quel que soit le nombre de points.
 */
final class LinePointPk
{
    /**
     * @param  array<string, array{line_id: int, code: ?string, station_id: ?int, lng: float, lat: float}>  $points
     * @return array<string, array{pk_m: int, precision: string}|null>
     */
    public function locate(array $points): array
    {
        if ($points === []) {
            return [];
        }
        $lineIds = array_values(array_unique(array_map(fn (array $p): int => $p['line_id'], $points)));
        $stations = [];
        foreach (DB::select(<<<'SQL'
            SELECT sl.line_id, sl.station_id, sl.pk_m, sl.pk_source, ST_X(s.geom) AS lng, ST_Y(s.geom) AS lat
            FROM station_lines sl JOIN stations s ON s.id = sl.station_id
            WHERE sl.line_id = ANY(?::bigint[]) AND sl.pk_m IS NOT NULL AND s.geom IS NOT NULL
            SQL, ['{'.implode(',', $lineIds).'}']) as $r) {
            $stations[(int) $r->line_id][(int) $r->station_id] = [
                'pk_m' => (int) $r->pk_m, 'projected' => $r->pk_source === 'projected', 'lng' => (float) $r->lng, 'lat' => (float) $r->lat,
            ];
        }

        $out = [];
        $toSnap = [];
        foreach ($points as $key => $p) {
            $station = $p['station_id'] !== null ? ($stations[$p['line_id']][$p['station_id']] ?? null) : null;
            if ($station !== null) {
                $out[$key] = ['pk_m' => $station['pk_m'], 'precision' => $station['projected'] ? 'derived' : 'exact'];
            } else {
                $toSnap[$key] = $p;
            }
        }

        $snapped = $this->onSncfShape($toSnap, $stations);
        foreach ($toSnap as $key => $p) {
            if (isset($snapped[$key])) {
                $out[$key] = ['pk_m' => $snapped[$key], 'precision' => 'derived'];

                continue;
            }
            $pk = LineSchematic::interpolate(array_values($stations[$p['line_id']] ?? []), $p['lng'], $p['lat']);
            $out[$key] = $pk !== null ? ['pk_m' => $pk, 'precision' => 'approximate'] : null;
        }

        return $out;
    }

    /**
     * PK lus sur la forme SNCF des lignes (une requête). La position est mesurée en mètres (Lambert 93) le long du
     * tronçon SNCF le plus proche, depuis la gare de la ligne de PK sourcé la plus proche du point quand elle est
     * sur ce tronçon : PK = PK de la gare ± distance le long du tronçon (le sens vient des bornes du tronçon). Sans
     * gare sur le tronçon, PK interpolé entre ses bornes. Une fraction prise en degrés sur un tronçon de plusieurs
     * centaines de kilomètres déplacerait le PK de plusieurs kilomètres.
     *
     * @param  array<string, array{line_id: int, code: ?string, station_id: ?int, lng: float, lat: float}>  $points
     * @param  array<int, array<int, array{pk_m: int, projected: bool, lng: float, lat: float}>>  $stations  gares de PK connu, par ligne
     * @return array<string, int>
     */
    private function onSncfShape(array $points, array $stations): array
    {
        $rows = [];
        $bindings = [];
        foreach ($points as $key => $p) {
            if ($p['code'] === null || $p['code'] === '') {
                continue;
            }
            $anchor = self::nearestAnchor($stations[$p['line_id']] ?? [], $p['lng'], $p['lat']);
            $rows[] = '(?::text, ?::text, ?::float8, ?::float8, ?::float8, ?::float8, ?::int)';
            array_push($bindings, (string) $key, $p['code'], $p['lng'], $p['lat'], $anchor['lng'] ?? null, $anchor['lat'] ?? null, $anchor['pk_m'] ?? null);
        }
        if ($rows === []) {
            return [];
        }
        $bindings[] = LineSchematic::MAX_SNAP_M;
        $bindings[] = LineSchematic::MAX_SNAP_M;
        $values = implode(', ', $rows);
        $result = DB::select(<<<SQL
            WITH pts(k, code, lng, lat, a_lng, a_lat, a_pk) AS (VALUES {$values}),
            p AS (SELECT k, code, a_pk, ST_SetSRID(ST_MakePoint(lng, lat), 4326) AS g,
                         CASE WHEN a_lng IS NOT NULL THEN ST_SetSRID(ST_MakePoint(a_lng, a_lat), 4326) END AS a
                  FROM pts)
            SELECT p.k,
                   CASE WHEN p.a IS NOT NULL AND ST_DWithin(snap.geom::geography, p.a::geography, ?)
                        THEN p.a_pk + SIGN(snap.pk_fin_m - snap.pk_debut_m)
                             * (ST_LineLocatePoint(snap.gm, ST_Transform(p.g, 2154)) - ST_LineLocatePoint(snap.gm, ST_Transform(p.a, 2154))) * ST_Length(snap.gm)
                        ELSE snap.pk_debut_m + ST_LineLocatePoint(snap.gm, ST_Transform(p.g, 2154)) * (snap.pk_fin_m - snap.pk_debut_m)
                   END AS pk
            FROM p
            CROSS JOIN LATERAL (
                SELECT g.geom, ST_Transform(g.geom, 2154) AS gm, g.pk_debut_m, g.pk_fin_m
                FROM sncf_line_geometries g
                WHERE g.code_ligne = p.code AND g.pk_debut_m IS NOT NULL AND g.pk_fin_m IS NOT NULL
                  AND ST_GeometryType(g.geom) = 'ST_LineString'
                  AND ST_DWithin(g.geom::geography, p.g::geography, ?)
                ORDER BY g.geom <-> p.g
                LIMIT 1
            ) snap
            SQL, $bindings);
        $out = [];
        foreach ($result as $r) {
            $out[(string) $r->k] = (int) round((float) $r->pk);
        }

        return $out;
    }

    /**
     * Gare de PK sourcé (pas projeté) la plus proche d'un point, à vol d'oiseau.
     *
     * @param  array<int, array{pk_m: int, projected: bool, lng: float, lat: float}>  $stations
     * @return array{pk_m: int, projected: bool, lng: float, lat: float}|null
     */
    public static function nearestAnchor(array $stations, float $lng, float $lat): ?array
    {
        $best = null;
        $bestD = INF;
        foreach ($stations as $s) {
            if ($s['projected']) {
                continue;
            }
            $d = LineStations::metres($lng, $lat, $s['lng'], $s['lat']);
            if ($d < $bestD) {
                [$best, $bestD] = [$s, $d];
            }
        }

        return $best;
    }
}

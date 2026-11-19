<?php

namespace App\Services;

use App\Models\Line;
use Illuminate\Support\Facades\DB;

/**
 * Point kilométrique d'un lieu sur une ligne : le point est projeté sur le tracé de la ligne à une ère,
 * puis la distance est mesurée le long du tracé depuis son origine. Une seule implémentation pour
 * l'API REST (GET /api/v1/lines/{line}/pk), GraphQL (`Line.pk`) et le serveur MCP (`locate_on_line`).
 */
final class LinePk
{
    /**
     * Null : la ligne n'a pas de tracé à cette ère.
     *
     * @return array{pk_m: int, pk_label: string, total_m: int, snap: array{lat: float, lng: float, distance_from_input_m: float}}|null
     */
    public function locate(Line $line, int $eraId, float $lat, float $lng): ?array
    {
        // ST_LineMerge renvoie un MULTILINESTRING dès que le tracé n'est pas
        // parfaitement continu (raccordements, lacunes) : ST_LineLocatePoint
        // échoue alors (« 1st arg isn't a line » → HTTP 500). On découpe donc
        // le tracé fusionné en composantes, on snappe sur la plus proche et on
        // ajoute la longueur cumulée des composantes précédentes.
        $row = DB::selectOne(
            <<<'SQL'
            WITH merged AS (
                SELECT ST_LineMerge(ST_Collect(geom)) AS g
                FROM line_segments WHERE line_id = ? AND era_id = ?
            ),
            parts AS (
                SELECT COALESCE((d).path[1], 1) AS idx, (d).geom AS g
                FROM (SELECT ST_Dump(g) AS d FROM merged WHERE g IS NOT NULL) x
            ),
            lens AS (
                SELECT idx, g, ST_Length(g::geography) AS len,
                       COALESCE(SUM(ST_Length(g::geography)) OVER (
                           ORDER BY idx ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING
                       ), 0) AS offset_m
                FROM parts
            ),
            pt AS (SELECT ST_SetSRID(ST_MakePoint(?, ?), 4326) AS p),
            best AS (
                SELECT lens.*, ST_Distance(lens.g::geography, pt.p::geography) AS dist_m
                FROM lens, pt
                ORDER BY ST_Distance(lens.g::geography, pt.p::geography)
                LIMIT 1
            )
            SELECT
                best.offset_m + ST_LineLocatePoint(best.g, pt.p) * best.len AS pk_m,
                (SELECT SUM(len) FROM lens) AS total_m,
                ST_X(ST_ClosestPoint(best.g, pt.p)) AS snap_lng,
                ST_Y(ST_ClosestPoint(best.g, pt.p)) AS snap_lat,
                best.dist_m
            FROM best, pt
            SQL,
            [$line->id, $eraId, $lng, $lat],
        );

        if (! $row || ! $row->total_m) {
            return null;
        }

        $pkM = (int) round((float) $row->pk_m);

        return [
            'pk_m' => $pkM,
            'pk_label' => sprintf('%d+%03d', intdiv($pkM, 1000), $pkM % 1000),
            'total_m' => (int) round((float) $row->total_m),
            'snap' => [
                'lat' => (float) $row->snap_lat,
                'lng' => (float) $row->snap_lng,
                'distance_from_input_m' => (float) $row->dist_m,
            ],
        ];
    }
}

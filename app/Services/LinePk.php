<?php

namespace App\Services;

use App\Models\Line;
use App\Services\TraceExport\LineTraceExport;
use Illuminate\Support\Facades\DB;

/**
 * Point kilométrique d'un lieu sur une ligne : le point est projeté sur le tracé de la ligne à une ère,
 * puis la distance est mesurée le long du tracé depuis son origine. Une seule implémentation pour
 * l'API REST (GET /api/v1/lines/{line}/pk), GraphQL (`Line.pk`) et le serveur MCP (`locate_on_line`).
 *
 * Règle des droits des tracés (LineTraceExport) : quand le point projeté longe un tronçon dont la source
 * n'autorise pas la redistribution, la réponse ne doit pas permettre de reconstituer ce tracé en
 * interrogeant une grille de lieux. Le point projeté et la distance au tracé ne sont alors pas donnés :
 * même arrondie, une distance trahit le tracé (on cherche par dichotomie le lieu où l'arrondi change de
 * valeur, puis on retranche la demi-marche). Le lieu est ramené au centre de sa maille de GRID_DEG degré
 * avant le calcul, et le PK est arrondi à ROUNDING_M mètres : une maille ne donne qu'une réponse, à
 * environ 150 m près.
 */
final readonly class LinePk
{
    /** Pas de la grille (degrés) qui remplace le lieu demandé quand le tracé est retenu : environ 110 m sur 75 m en France. */
    public const GRID_DEG = 0.001;

    /** Arrondi du PK (mètres) quand le tracé est retenu. */
    public const ROUNDING_M = 100;

    /** Distance (mètres) sous laquelle le point projeté longe un tronçon : jonctions et tronçons superposés compris. */
    private const NEAR_M = 3.0;

    public function __construct(private LineTraceExport $rights) {}

    /**
     * Null : la ligne n'a pas de tracé à cette ère.
     *
     * @return array{pk_m: int, pk_label: string, total_m: int, geometry_withheld: bool, geometry_withheld_reason: ?string, snap: array{lat: float, lng: float, distance_from_input_m: float}|null}|null
     */
    public function locate(Line $line, int $eraId, float $lat, float $lng): ?array
    {
        $row = $this->project($line->id, $eraId, $lat, $lng);
        if ($row === null) {
            return null;
        }

        $withheld = $this->rights->enforced() ? $this->withheldSourcesNear($line->id, $eraId, (float) $row->snap_lng, (float) $row->snap_lat) : null;
        if ($withheld === null) {
            $pkM = (int) round((float) $row->pk_m);

            return self::result($pkM, (float) $row->total_m, null) + ['snap' => [
                'lat' => (float) $row->snap_lat,
                'lng' => (float) $row->snap_lng,
                'distance_from_input_m' => (float) $row->dist_m,
            ]];
        }

        // Tracé retenu : PK du centre de la maille, arrondi ; ni point projeté ni distance.
        $cell = fn (float $v): float => round((floor($v / self::GRID_DEG) + 0.5) * self::GRID_DEG, 6);
        $centre = $this->project($line->id, $eraId, $cell($lat), $cell($lng)) ?? $row;
        $pkM = (int) (round((float) $centre->pk_m / self::ROUNDING_M) * self::ROUNDING_M);

        return self::result($pkM, (float) $row->total_m, $this->rights->withheldReasonForSources($withheld)) + ['snap' => null];
    }

    /** @return array{pk_m: int, pk_label: string, total_m: int, geometry_withheld: bool, geometry_withheld_reason: ?string} */
    private static function result(int $pkM, float $totalM, ?string $withheldReason): array
    {
        return [
            'pk_m' => $pkM,
            'pk_label' => sprintf('%d+%03d', intdiv($pkM, 1000), $pkM % 1000),
            'total_m' => (int) round($totalM),
            'geometry_withheld' => $withheldReason !== null,
            'geometry_withheld_reason' => $withheldReason,
        ];
    }

    /** Projection du lieu sur le tracé de la ligne à l'ère ; null si la ligne n'a pas de tracé. */
    private function project(int $lineId, int $eraId, float $lat, float $lng): ?object
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
            [$lineId, $eraId, $lng, $lat],
        );

        return $row && $row->total_m ? $row : null;
    }

    /**
     * Sources des tronçons retenus que longe le point projeté (à NEAR_M près), ou null s'il n'en longe
     * aucun. Une source absente du registre (ou nulle) compte comme retenue, comme dans les exports.
     *
     * @return list<?string>|null
     */
    private function withheldSourcesNear(int $lineId, int $eraId, float $lng, float $lat): ?array
    {
        $withheld = $this->rights->sqlWithheld('source');
        $sources = DB::select(
            "SELECT DISTINCT source FROM line_segments
             WHERE line_id = ? AND era_id = ? AND {$withheld}
               AND ST_DWithin(geom::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)",
            [$lineId, $eraId, $lng, $lat, self::NEAR_M],
        );

        return $sources === [] ? null : array_map(fn (object $r): ?string => $r->source, $sources);
    }
}

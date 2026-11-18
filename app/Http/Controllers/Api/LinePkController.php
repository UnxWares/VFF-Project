<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Era;
use App\Models\Line;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

#[Group('Lignes', "Une ligne en détail : chronologie, gares dans l'ordre des points kilométriques, conversion de coordonnées en PK, signalisation, médias.", weight: 2)]
class LinePkController extends Controller
{
    /**
     * Point kilométrique d'un lieu sur une ligne.
     *
     * Convertit des coordonnées (latitude, longitude WGS 84) en **point
     * kilométrique (PK)** sur le tracé de la ligne à l'ère choisie : le point
     * est projeté sur le tracé le plus proche (`snap`), puis la distance est
     * mesurée le long du tracé depuis son origine (PostGIS `ST_LineLocatePoint`).
     *
     * - `pk_m` : PK en mètres ; `pk_label` : notation ferroviaire `km+mmm`
     *   (ex. `249+204` = 249,204 km) ;
     * - `total_m` : longueur totale du tracé à cette ère, en mètres ;
     * - `snap.distance_from_input_m` : distance en mètres entre le point fourni
     *   et le tracé. Au-delà de quelques dizaines de mètres, le point n'est
     *   probablement pas sur cette ligne.
     *
     * Ce PK est une distance mesurée le long du tracé VFF, de bout en bout de
     * ses portions : il peut différer du PK officiel de SNCF Réseau (origine
     * différente, sauts de numérotation, tracé discontinu). Pour les PK
     * officiels des gares, voir l'endpoint des gares de la ligne (`stops`).
     *
     * @response array{
     *     line: array{slug: string, code: ?string, name: string},
     *     pk_m: int,
     *     pk_label: string,
     *     total_m: int,
     *     snap: array{lat: float, lng: float, distance_from_input_m: float}
     * }
     */
    #[PathParameter('line', 'Identifiant (slug) de la ligne, tel que renvoyé par la recherche ou par les GeoJSON (`line_slug`). Les lignes du réseau ferré national sont nommées `rfn-` suivi de leur code à 6 chiffres.', example: 'rfn-420000')]
    #[QueryParameter('lat', 'Latitude WGS 84 du point, en degrés décimaux (-90 à 90).', required: true, type: 'float', example: 48.10)]
    #[QueryParameter('lng', 'Longitude WGS 84 du point, en degrés décimaux (-180 à 180).', required: true, type: 'float', example: -1.67)]
    #[QueryParameter('era', "Année de l'ère (décennie de 1830 à 2030). Par défaut : l'ère courante, 2030, c'est-à-dire le réseau d'aujourd'hui.", type: 'int', example: 1930)]
    #[ApiResponse(200, 'Point kilométrique du lieu sur la ligne.', examples: [[
        'line' => ['slug' => 'rfn-420000', 'code' => '420000', 'name' => 'Ligne de Paris-Montparnasse à Brest'],
        'pk_m' => 249204,
        'pk_label' => '249+204',
        'total_m' => 626972,
        'snap' => ['lat' => 48.103576, 'lng' => -1.67024, 'distance_from_input_m' => 395.12],
    ]])]
    #[ApiResponse(404, 'Ligne ou ère inconnue, ou ligne sans tracé à cette ère (`error` = `no_geometry`).')]
    public function fromPoint(Line $line, Request $request): JsonResponse
    {
        $request->validate([
            // Bornes WGS 84 : un point impossible n'a pas de PK.
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'era' => 'nullable|integer',
        ]);

        $era = $request->filled('era')
            ? Era::where('year', $request->integer('era'))->firstOrFail()
            : Era::where('is_current', true)->firstOrFail();

        // ST_LineMerge renvoie un MULTILINESTRING dès que le tracé n'est pas
        // parfaitement continu (raccordements, lacunes) : ST_LineLocatePoint
        // échoue alors (« 1st arg isn't a line » → HTTP 500). On découpe donc
        // le tracé fusionné en composantes, on snappe sur la plus proche et on
        // ajoute la longueur cumulée des composantes précédentes.
        $lng = (float) $request->input('lng');
        $lat = (float) $request->input('lat');

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
            [$line->id, $era->id, $lng, $lat],
        );

        if (! $row || ! $row->total_m) {
            return response()->json(['error' => 'no_geometry'], 404);
        }

        $pkM = (int) round((float) $row->pk_m);

        return response()->json([
            'line' => ['slug' => $line->slug, 'code' => $line->code, 'name' => $line->name],
            'pk_m' => $pkM,
            'pk_label' => $this->formatPk($pkM),
            'total_m' => (int) round((float) $row->total_m),
            'snap' => [
                'lat' => (float) $row->snap_lat,
                'lng' => (float) $row->snap_lng,
                'distance_from_input_m' => (float) $row->dist_m,
            ],
        ]);
    }

    private function formatPk(int $m): string
    {
        $km = intdiv($m, 1000);
        $rest = $m % 1000;

        return sprintf('%d+%03d', $km, $rest);
    }
}

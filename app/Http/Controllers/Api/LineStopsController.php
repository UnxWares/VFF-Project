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
class LineStopsController extends Controller
{
    /**
     * Gares d'une ligne, dans l'ordre.
     *
     * Les gares et haltes de la ligne à l'ère choisie, dans l'ordre des points
     * kilométriques. Sur le modèle de GTFS `stop_times.txt`, sans horaires :
     * VFF décrit l'infrastructure, pas les services.
     *
     * - `position` : rang de la gare sur la ligne (à partir de 0) ;
     * - `pk_m` : point kilométrique en mètres (PK officiel de SNCF Réseau quand
     *   il est connu, sinon estimé sur le tracé et calé sur les PK voisins) ;
     *   `pk_label` : notation `km+mmm`. Équivaut au `shape_dist_traveled` de GTFS ;
     * - `effective_status` : état de la gare **à cette ère** : `active`
     *   (desservie par des trains de voyageurs), `tourist` (train touristique),
     *   `no_commercial` (plus de voyageurs, fret seul), `declassified` (fermée).
     *   Les gares fermées sont incluses : filtrez-les au besoin ;
     * - `is_terminal` : gare d'extrémité de la ligne ;
     * - `station.type` : `gare`, `halte`, `point_arret`… ; coordonnées WGS 84.
     *
     * @response array{
     *     line: array{slug: string, code: ?string, name: string},
     *     era_year: int,
     *     stops: array<array{
     *         position: int,
     *         pk_m: int,
     *         pk_label: string,
     *         effective_status: string,
     *         is_terminal: bool,
     *         station: array{slug: string, name: string, type: string, city: ?string, lat: float, lng: float}
     *     }>
     * }
     */
    #[PathParameter('line', 'Identifiant (slug) de la ligne, tel que renvoyé par la recherche ou par les GeoJSON (`line_slug`). Les lignes du réseau ferré national sont nommées `rfn-` suivi de leur code à 6 chiffres.', example: 'rfn-420000')]
    #[QueryParameter('era', "Année de l'ère (décennie de 1830 à 2030). Par défaut : l'ère courante, 2030, c'est-à-dire le réseau d'aujourd'hui.", type: 'int', example: 2030)]
    #[ApiResponse(200, "Gares de la ligne à l'ère choisie, dans l'ordre des PK.", examples: [[
        'line' => ['slug' => 'rfn-420000', 'code' => '420000', 'name' => 'Ligne de Paris-Montparnasse à Brest'],
        'era_year' => 2030,
        'stops' => [[
            'position' => 0, 'pk_m' => 600, 'pk_label' => '0+600', 'effective_status' => 'active', 'is_terminal' => true,
            'station' => ['slug' => 'paris-montparnasse-pasteur', 'name' => 'Paris-Montparnasse-Pasteur', 'type' => 'gare', 'city' => 'Paris', 'lat' => 48.8392129, 'lng' => 2.318143],
        ], [
            'position' => 1, 'pk_m' => 800, 'pk_label' => '0+800', 'effective_status' => 'active', 'is_terminal' => false,
            'station' => ['slug' => 'paris-vaugirard', 'name' => 'Paris-Vaugirard', 'type' => 'gare', 'city' => 'Paris', 'lat' => 48.8375301, 'lng' => 2.3161706],
        ]],
    ]])]
    #[ApiResponse(404, 'Ligne ou ère inconnue.')]
    public function index(Line $line, Request $request): JsonResponse
    {
        $request->validate(['era' => 'nullable|integer']);

        $era = $request->filled('era')
            ? Era::where('year', $request->integer('era'))->firstOrFail()
            : Era::where('is_current', true)->firstOrFail();

        $rows = DB::select(
            'SELECT ls.position, ls.pk_m, ls.effective_status, ls.is_terminal,
                    s.slug, s.name, s.type, s.city,
                    ST_X(s.geom::geometry) AS lng, ST_Y(s.geom::geometry) AS lat
             FROM line_stops ls
             JOIN stations s ON s.id = ls.station_id
             WHERE ls.line_id = ? AND ls.era_id = ?
             ORDER BY ls.position',
            [$line->id, $era->id],
        );

        $stops = array_map(fn ($r) => [
            'position' => (int) $r->position,
            'pk_m' => (int) $r->pk_m,
            'pk_label' => $this->formatPk((int) $r->pk_m),
            'effective_status' => $r->effective_status,
            'is_terminal' => (bool) $r->is_terminal,
            'station' => [
                'slug' => $r->slug,
                'name' => $r->name,
                'type' => $r->type,
                'city' => $r->city,
                'lat' => (float) $r->lat,
                'lng' => (float) $r->lng,
            ],
        ], $rows);

        return response()->json([
            'line' => ['slug' => $line->slug, 'code' => $line->code, 'name' => $line->name],
            'era_year' => $era->year,
            'stops' => $stops,
        ])->header('Cache-Control', 'public, max-age=300');
    }

    private function formatPk(int $m): string
    {
        return sprintf('%d+%03d', intdiv($m, 1000), $m % 1000);
    }
}

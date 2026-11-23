<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Station;
use App\Services\Timetable\Timetables;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;

#[Group('Gares', "L'annuaire des gares, haltes et points d'arrêt, actuels et disparus, et la fiche complète de chaque gare : dates, présence par décennie, lignes desservies, sources.", weight: 2)]
class StationTimetableController extends Controller
{
    /**
     * Desserte d'un jour ouvrable type d'une gare.
     *
     * Les trains de voyageurs qui s'arrêtent dans la gare un mardi type (semaine sans jour férié, mardi dont le nombre de trains
     * est la médiane des mardis proches), d'après les horaires théoriques ouverts de SNCF Voyageurs (TER, Intercités, TGV INOUI,
     * OUIGO), de Transilien (RER compris) et, pour quelques gares du Sud, des réseaux ZOU !. Rattachement par le code UIC de
     * l'arrêt, à défaut par son nom et sa position.
     *
     * - `service_date` : le jour type lu dans les horaires ; `weekday` : `tuesday` ;
     * - `trains` : trains qui s'arrêtent dans la gare ce jour-là, chacun compté une fois (arrivées et départs confondus) ;
     *   `by_type` : par type (`ter`, `intercites`, `tgv`, `transilien`, `other`) ; `first` et `last` : première et dernière heure
     *   (`HH:MM`, après minuit comprise dans la journée de service) ;
     * - `directions[]` : les deux sens, qui sont les deux demi-axes du trafic de la gare : `heading` (cap de la marche des trains :
     *   `n`, `ne`, `e`, `se`, `s`, `sw`, `w`, `nw`), `trains`, `by_type`, `first`, `last`, `destinations[]` (terminus des trains
     *   qui repartent : `name`, `slug`, `trains`) ou, sur une gare terminus, `origins[]` (d'où viennent les trains) ;
     * - `destinations[]` : les principales destinations directes de la gare, tous sens confondus ;
     * - `feeds[]` : les horaires utilisés, avec leur `version`, `date` (jour lu), `valid_to` (fin de validité), licence et page ;
     * - `attribution` (mention à reproduire), `method` (comment est choisi le jour type) et `provenance`.
     *
     * `timetable` vaut `null` quand aucun train de voyageurs ne s'arrête dans la gare ce jour-là (gare fermée, desservie par des
     * cars, saisonnière, ou hors des horaires lus : Corse) : ce n'est pas une preuve de fermeture. Les horaires sont théoriques :
     * travaux, suppressions et retards ne sont pas connus. Même bloc que `timetable` de `GET /api/v1/stations/{station}`.
     */
    #[PathParameter('station', 'Identifiant (slug) de la gare, tel que renvoyé par l\'annuaire ou la recherche.', example: 'limoges-benedictins')]
    #[ApiResponse(200, 'La desserte du jour type (`timetable` nul si aucun train de voyageurs ne s\'arrête dans la gare).', examples: [[
        'station' => ['slug' => 'limoges-benedictins', 'name' => 'Limoges-Bénédictins', 'uic_code' => '87592006'],
        'timetable' => [
            'service_date' => '2026-12-08',
            'weekday' => 'tuesday',
            'trains' => 144,
            'by_type' => ['intercites' => 20, 'ter' => 124],
            'first' => '05:03',
            'last' => '23:02',
            'directions' => [
                ['trains' => 72, 'by_type' => ['intercites' => 10, 'ter' => 62], 'first' => '05:03', 'last' => '21:28', 'heading' => 'ne',
                    'destinations' => [['name' => 'Paris-Austerlitz', 'slug' => 'paris-austerlitz', 'trains' => 10]], 'origins' => []],
            ],
            'destinations' => [['name' => 'Brive-la-Gaillarde', 'slug' => 'brive-la-gaillarde', 'trains' => 14]],
            'origins' => [],
            'feeds' => [['feed' => 'sncf', 'version' => '2026-11-22', 'date' => '2026-12-08', 'valid_to' => '2027-04-30', 'name' => 'SNCF Voyageurs : TGV INOUI, OUIGO, Intercités, TER (GTFS)', 'licence' => 'ODbL', 'page' => 'https://transport.data.gouv.fr/datasets/horaires-sncf']],
            'attribution' => Timetables::ATTRIBUTION,
            'method' => Timetables::METHOD,
            'provenance' => ['provider' => 'sncf', 'name' => 'SNCF Open Data (SNCF Réseau, Gares & Connexions, GTFS voyageurs)', 'license' => 'ODbL 1.0', 'license_status' => 'verified'],
        ],
    ]])]
    #[ApiResponse(404, 'Gare inconnue.', examples: [ErrorExamples::STATION_NOT_FOUND])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function index(Station $station, Timetables $timetables): JsonResponse
    {
        return response()->json([
            'station' => ['slug' => $station->slug, 'name' => $station->name, 'uic_code' => $station->uic_code],
            'timetable' => $timetables->forStation((int) $station->id),
        ])->header('Cache-Control', 'public, max-age=3600');
    }
}

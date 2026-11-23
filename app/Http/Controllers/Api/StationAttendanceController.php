<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Station;
use App\Services\Sncf\StationAttendance;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;

#[Group('Gares', "L'annuaire des gares, haltes et points d'arrêt, actuels et disparus, et la fiche complète de chaque gare : dates, présence par décennie, lignes desservies, sources.", weight: 2)]
class StationAttendanceController extends Controller
{
    /**
     * Fréquentation annuelle d'une gare.
     *
     * Voyageurs par an, toutes les années publiées depuis 2015, d'après les jeux « Fréquentation en gares » de
     * SNCF Gares & Connexions (SNCF Open Data, ODbL 1.0), rattachés à la gare par son code UIC.
     *
     * - `years[]` : `year`, `passengers` (voyageurs), `footfall` (voyageurs et non-voyageurs réunis), `non_passengers`
     *   (la différence, nulle si l'un des deux manque : les non-voyageurs sont estimés par enquête en gare, à défaut
     *   par un taux par défaut) et `series` ;
     * - `latest` : la dernière année, avec `published_year`, l'année de mise en ligne du jeu qui la porte (les chiffres
     *   de 2025 sont publiés en 2026) ;
     * - `series_break` : vrai quand la gare a des années des deux séries. SNCF écrit que le changement de sources et de
     *   méthode de calcul de 2025 rend les séries `2015-2024` et `2025` non directement comparables : ne tirez pas de
     *   hausse ni de baisse de la jonction entre 2024 et 2025 ;
     * - `sources[]` : jeu (`dataset`) et date de dernière modification ; `attribution` à reproduire et `provenance`.
     *
     * `attendance` vaut `null` quand les jeux n'en donnent aucune pour la gare (gare fermée avant 2015, absente des
     * jeux) : ce n'est pas « aucun voyageur ». Les jeux couvrent environ 3 000 gares voyageurs en service ;
     * la gare routière n'en fait pas partie. Même bloc que `attendance` de `GET /api/v1/stations/{station}`.
     */
    #[PathParameter('station', 'Identifiant (slug) de la gare, tel que renvoyé par l\'annuaire ou la recherche.', example: 'angouleme')]
    #[ApiResponse(200, 'La fréquentation de la gare (`attendance` nul si les jeux n\'en donnent aucune).', examples: [[
        'station' => ['slug' => 'angouleme', 'name' => 'Angoulême', 'uic_code' => '87584003'],
        'attendance' => [
            'uic_code' => '87584003',
            'years' => [
                ['year' => 2024, 'passengers' => 1948021, 'footfall' => 2142823, 'non_passengers' => 194802, 'series' => '2015-2024'],
                ['year' => 2025, 'passengers' => 1991037, 'footfall' => 1991037, 'non_passengers' => 0, 'series' => '2025'],
            ],
            'latest' => ['year' => 2025, 'passengers' => 1991037, 'footfall' => 1991037, 'series' => '2025', 'published_year' => 2026],
            'series_break' => true,
            'sources' => [
                ['series' => '2015-2024', 'dataset' => 'frequentation-gares-archive', 'dataset_modified' => '2025-07-03'],
                ['series' => '2025', 'dataset' => 'frequentation-gares', 'dataset_modified' => '2026-11-13'],
            ],
            'attribution' => StationAttendance::ATTRIBUTION,
            'provenance' => ['provider' => 'sncf', 'name' => 'SNCF Open Data (SNCF Réseau, Gares & Connexions, GTFS voyageurs)', 'license' => "ODbL 1.0 (chaque jeu utilisé : champ metas.default.license de l'API)", 'license_status' => 'verified'],
        ],
    ]])]
    #[ApiResponse(404, 'Gare inconnue.', examples: [ErrorExamples::STATION_NOT_FOUND])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function index(Station $station, StationAttendance $attendance): JsonResponse
    {
        return response()->json([
            'station' => ['slug' => $station->slug, 'name' => $station->name, 'uic_code' => $station->uic_code],
            'attendance' => $attendance->forStation((int) $station->id),
        ])->header('Cache-Control', 'public, max-age=3600');
    }
}

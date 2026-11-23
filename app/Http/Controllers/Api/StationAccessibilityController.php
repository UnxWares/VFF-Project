<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Station;
use App\Services\Accessibility\StationAccessibility;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;

#[Group('Gares', "L'annuaire des gares, haltes et points d'arrêt, actuels et disparus, et la fiche complète de chaque gare : dates, présence par décennie, lignes desservies, sources.", weight: 2)]
class StationAccessibilityController extends Controller
{
    /**
     * Accessibilité et services d'une gare d'aujourd'hui.
     *
     * Ce que SNCF Gares & Connexions publie en données ouvertes (ODbL) sur la gare :
     *
     * - `access` : `mobility` (`partial` : accessible en partie aux personnes à mobilité réduite ; `none` : non déclarée accessible par SNCF ; `null` : le jeu
     *   NeTEx « Accessibilité des gares » n'a pas la gare), `accessible` (`mobility` vaut `partial`), `step_free` (cheminement sans marche),
     *   `tactile_guidance` (guidage tactile), `quays` (`total` de quais, dont `step_free`), `comment` (texte de SNCF) ;
     * - `elevators` : `count` (ascenseurs recensés), `escalators`, `travelators`, et `status`, l'état publié de chaque équipement :
     *   `state_at` (date et heure de la lecture), `ok`, `ko`, `unknown` (ascenseurs seulement), `items[]` (chaque équipement : `label`, `kind` : `lift`, `escalator` ou `travelator`, `location`, `state` : `ok`, `ko` ou `unknown`).
     *   Cet état est celui de la dernière lecture, pas celui du moment : SNCF écrit qu'il n'est pas contractuel ;
     * - `amenities` : équipements recensés parmi `step_free_entrance` (accès par ascenseur, rampe ou de plain-pied depuis l'entrée),
     *   `boarding_assistance`, `induction_loop` (boucle à induction magnétique), `tactile_strips` (bandes d'éveil de vigilance sur les quais),
     *   `audio_information`, `information_screens`, `wheelchair_loan`, `staff`, `toilets`, `toilets_accessible` ;
     * - `assistance` : assistance aux personnes en situation de handicap ou à mobilité réduite (service dit « Accès Plus ») :
     *   `mode` (`reserved`, `walk_in`, `reserved_or_walk_in`), `free`, `meeting_point`, `first_to_last_train`, `hours` (horaires par jour), `since` ;
     * - `sales` : `counter` (guichet), `machines[]` (`ter`, `tgv_intercites`), `assisted` (libre-service assisté) ; Transilien n'y est pas ;
     * - `bike_parking` : places de stationnement vélo sécurisé (`places`, `as_of`, `lom`, `lom_target`) ;
     * - `datasets[]` : jeux lus (`title`, `publisher`, `license`, `modified`, `page`) ; `attribution` (mention à reproduire), `method`, `provenance`.
     *
     * `accessibility` vaut `null` quand les jeux n'ont rien pour la gare. Une absence n'est pas une négation : SNCF précise que la liste des
     * équipements n'est pas complète. Même bloc que `accessibility` de `GET /api/v1/stations/{station}`.
     */
    #[PathParameter('station', 'Identifiant (slug) de la gare, tel que renvoyé par l\'annuaire ou la recherche.', example: 'limoges-benedictins')]
    #[ApiResponse(200, 'L\'accessibilité et les services (`accessibility` nul si les jeux n\'ont rien pour la gare).', examples: [[
        'station' => ['slug' => 'limoges-benedictins', 'name' => 'Limoges-Bénédictins', 'uic_code' => '87592006'],
        'accessibility' => [
            'uic_code' => '87592006',
            'access' => ['mobility' => 'partial', 'accessible' => true, 'step_free' => true, 'tactile_guidance' => false, 'quays' => ['total' => 4, 'step_free' => 4], 'comment' => null],
            'elevators' => ['count' => 6, 'escalators' => 0, 'travelators' => 0, 'status' => ['state_at' => '2026-11-23T10:00:00+01:00', 'ok' => 6, 'ko' => 0, 'unknown' => 0, 'items' => [['label' => 'Ascenseur - Quai 1', 'kind' => 'lift', 'location' => 'Souterrain / Voie 1', 'state' => 'ok']]]],
            'amenities' => ['step_free_entrance', 'induction_loop', 'tactile_strips', 'toilets', 'toilets_accessible'],
            'assistance' => ['mode' => 'reserved_or_walk_in', 'free' => true, 'meeting_point' => 'Guichet', 'first_to_last_train' => false, 'hours' => ['monday' => '06:00 - 21:00', 'tuesday' => '06:00 - 21:00', 'wednesday' => '06:00 - 21:00', 'thursday' => '06:00 - 21:00', 'friday' => '06:00 - 21:00', 'saturday' => '07:00 - 19:00', 'sunday' => '08:00 - 21:00'], 'since' => '2025-05-06'],
            'sales' => ['counter' => true, 'machines' => ['ter', 'tgv_intercites'], 'assisted' => false],
            'bike_parking' => ['places' => 20, 'as_of' => '2024-12-30', 'lom' => true, 'lom_target' => 20],
            'datasets' => [['dataset' => 'accessibilite_gares', 'title' => 'Accessibilité des gares', 'publisher' => 'Gares & Connexions', 'license' => 'ODbL', 'modified' => '2026-05-21', 'page' => 'https://data.sncf.com/explore/dataset/accessibilite_gares/']],
            'attribution' => StationAccessibility::ATTRIBUTION,
            'method' => StationAccessibility::METHOD,
            'provenance' => ['provider' => 'sncf', 'name' => 'SNCF Open Data (SNCF Réseau, Gares & Connexions, GTFS voyageurs)', 'license' => 'ODbL 1.0', 'license_status' => 'verified'],
        ],
    ]])]
    #[ApiResponse(404, 'Gare inconnue.', examples: [ErrorExamples::STATION_NOT_FOUND])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function index(Station $station, StationAccessibility $accessibility): JsonResponse
    {
        return response()->json([
            'station' => ['slug' => $station->slug, 'name' => $station->name, 'uic_code' => $station->uic_code],
            'accessibility' => $accessibility->forStation((int) $station->id),
        ])->header('Cache-Control', 'public, max-age=3600');
    }
}

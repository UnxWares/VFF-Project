<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Communes\CommuneSheet;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;

#[Group('Communes', 'Le rail d\'une commune : ses gares, les lignes qui la traversent ou la traversaient, sa chronologie et ses voisines.', weight: 3)]
class CommuneController extends Controller
{
    /**
     * Le rail d'une commune.
     *
     * La fiche « Le rail à… » du site pour une commune actuelle, par son code INSEE (`16015` pour Angoulême ; `2A004`
     * pour une commune corse) : `commune` (nom, département, population, noms des anciennes communes), `counts`,
     * `stations` (gares et haltes dont le point est dans la commune, avec statut, dates d'ouverture et de fermeture et
     * leur précision), `lines` (lignes dont 50 m de voie au moins sont dans la commune, à l'une des ères : `state`
     * `open` ou `closed`, `track` de la voie d'une ligne fermée, `km` dans la commune et `km_year` de la carte qui les
     * mesure, `opened`, `closed` et `electrified` quand un événement daté de la ligne passe par la commune),
     * `chronology` (ouvertures, fermetures, électrifications et accidents de la commune, du plus ancien au plus
     * récent, au plus 400 entrées) et `neighbors` (communes voisines qui ont du rail).
     *
     * Une commune sans gare ni ligne donne 404, comme un code inconnu. Les dates ont la précision de leur source
     * (`day`, `month`, `year`, `decade`, `after`, `before`, `range`) ; les noms d'accident sont dans la langue de la
     * requête. Positions des communes d'après les contours simplifiés à 100 m de l'IGN et d'Etalab : une gare à
     * moins de 100 m d'une limite peut être rattachée à la commune voisine.
     */
    #[PathParameter('insee', 'Code INSEE de la commune actuelle (cinq caractères, `2A` ou `2B` pour la Corse).', example: '16015')]
    #[ApiResponse(200, 'Angoulême, réponse raccourcie.', examples: [[
        'data' => [
            'commune' => ['insee' => '16015', 'slug' => '16015-angouleme', 'name' => 'Angoulême', 'department_code' => '16', 'department_name' => 'Charente', 'population' => 41908, 'former_names' => []],
            'counts' => ['stations' => 2, 'stations_open' => 1, 'lines' => 6, 'lines_open' => 3],
            'stations' => [['slug' => 'angouleme', 'name' => 'Angoulême', 'type' => 'gare', 'status' => 'active', 'opened_at' => '1852-09-01', 'opened_precision' => 'day', 'opened_after' => null, 'closed_at' => null, 'closed_precision' => null, 'closed_after' => null, 'lng' => 0.1636, 'lat' => 45.6516]],
            'lines' => [['slug' => 'rfn-579000', 'name' => 'Ligne de Beillant à Angoulême', 'code' => '579000', 'is_lgv' => false, 'state' => 'open', 'track' => null, 'km' => 3.4, 'km_year' => 2030, 'first_year' => 1860, 'last_year' => 2030, 'opened' => ['date' => '1867-01-01', 'precision' => 'year'], 'closed' => null, 'electrified' => null]],
            'chronology' => [['kind' => 'station_opening', 'date' => '1852-09-01', 'precision' => 'day', 'after' => null, 'station' => ['slug' => 'angouleme', 'name' => 'Angoulême']]],
            'neighbors' => [['insee' => '16206', 'slug' => '16206-la-couronne', 'name' => 'La Couronne']],
            'links' => ['page' => 'https://vff-project.org/communes/16015-angouleme'],
        ],
    ]])]
    #[ApiResponse(404, 'Code inconnu, ou commune sans gare ni ligne.', examples: [['message' => 'Unknown commune.']])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function show(string $insee, CommuneSheet $sheet): JsonResponse
    {
        $page = $sheet->page(strtoupper($insee), app()->getLocale());
        abort_if($page === null, 404, 'Unknown commune.');
        unset($page['map']);
        $page['links'] = ['page' => route('communes.show', $page['commune']['slug'])];

        return response()->json(['data' => $page])->header('Cache-Control', 'public, max-age=600');
    }
}

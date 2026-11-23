<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TouristRailways;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

#[Group('Lignes', 'Les lignes du réseau ferré français : annuaire, fiche complète, tracés, chronologie, matériel, voies vertes et exploitations touristiques.', weight: 2)]
class TouristRailwayController extends Controller
{
    /**
     * Chemins de fer touristiques et vélorails.
     *
     * Les exploitations touristiques actuelles (trains touristiques et vélorails sur rails) et, dans `greenways`,
     * les voies vertes sur ancienne voie : ce que montre la page « Chemins de fer touristiques et vélorails ».
     *
     * Chaque exploitation de `railways` donne :
     *
     * - `name`, `type` : `steam` (vapeur), `railcar` (autorail), `tram` (tramway), `rack` (crémaillère), `velorail`
     *   ou `train` (traction non précisée par les sources) ;
     * - `operator` (`null` quand aucune source ne le nomme), `gauge_mm` et `gauges_mm`, `length_m` (longueur exploitée, `null`
     *   si inconnue), `start_year`, `regions`, `departements` ;
     * - `lines` : fiches de ligne (`slug`, `name`, `length_m` de la portion exploitée), vide quand la ligne n'est pas
     *   rattachée ;
     * - `website` (site officiel), `wikipedia_url`, `wikidata`, `lat` et `lng` quand connus ;
     * - `section` (`from`, `to` : lieux de départ et d'arrivée de la section exploitée, `null` quand inconnue), `season`
     *   (saison d'exploitation en texte), `opened_on` (jour d'ouverture touristique, AAAA-MM-JJ) et `detail_source_url`
     *   (version de l'article Wikipédia qui les donne), tous `null` quand aucune source ne les donne ;
     * - `origin` : `both` (catalogue Wikipédia et exploitation de la base), `catalogue` ou `database` ;
     * - `sources` : pour chaque fait (`type`, `gauge`, `region`, `length`, `start_year`, `operator`, `website`), la clé de sa
     *   source dans `sources` de la réponse (libellé, adresse, licence), `null` quand le fait est inconnu.
     *
     * Les voies vertes (`greenways`) viennent d'OpenStreetMap (ODbL) : nom, longueur sur l'ancienne emprise, année
     * d'ouverture si connue, ligne et lien OpenStreetMap ; seules celles d'au moins 5 km sont listées. Sans filtre
     * `type`, `greenways` est donnée ; avec `type`, elle est vide.
     */
    #[QueryParameter('type', 'Type d\'exploitation : `steam`, `railcar`, `tram`, `rack`, `velorail` ou `train`. Inconnu : HTTP 422.', type: 'string', example: 'velorail')]
    #[QueryParameter('region', 'Région (nom exact, ex. `Occitanie`), tel que listé dans `regions` de la réponse.', type: 'string', example: 'Occitanie')]
    #[ApiResponse(200, 'Les exploitations touristiques et les voies vertes (extrait).', examples: [[
        'data' => [
            'railways' => [[
                'id' => 'train-a-vapeur-des-cevennes-steam', 'origin' => 'both', 'name' => 'Train à vapeur des Cévennes', 'type' => 'steam',
                'operator' => null, 'gauge_mm' => 1435, 'gauges_mm' => [1435], 'length_m' => 13700, 'start_year' => 1982,
                'regions' => ['Occitanie'], 'departements' => ['Gard'],
                'lines' => [['slug' => 'rfn-815000', 'name' => 'Ligne de Saint-Germain-des-Fossés à Nîmes-Courbessac', 'length_m' => 13700]],
                'website' => 'http://www.trainavapeur.com/', 'wikipedia_url' => 'https://fr.wikipedia.org/wiki/Train_%C3%A0_vapeur_des_C%C3%A9vennes', 'wikidata' => 'Q737163',
                'lat' => null, 'lng' => null,
                'section' => ['from' => 'Anduze', 'to' => 'Saint-Jean-du-Gard'], 'season' => null, 'opened_on' => null,
                'detail_source_url' => 'https://fr.wikipedia.org/w/index.php?title=Train_%C3%A0_vapeur_des_C%C3%A9vennes&oldid=241322938',
                'sources' => ['type' => 'wp-article', 'gauge' => 'wp-list', 'region' => 'wp-list', 'length' => 'ign', 'start_year' => 'wp-article', 'operator' => null, 'website' => 'wp-article'],
            ]],
            'greenways' => [[
                'name' => 'Via Ardèche', 'length_m' => 52808, 'opened_year' => null, 'regions' => ['Auvergne-Rhône-Alpes'],
                'line' => ['slug' => 'rfn-805000', 'name' => 'Ligne de Lyon-Perrache à Marseille-Saint-Charles (via Givors)'],
                'osm_url' => 'https://www.openstreetmap.org/way/1', 'sources' => ['length' => 'osm', 'name' => 'osm', 'opened_year' => null],
            ]],
            'regions' => ['Occitanie'],
            'types' => ['steam', 'velorail'],
            'sources' => ['wp-list' => ['label' => 'Wikipédia, liste des chemins de fer touristiques de France', 'url' => 'https://fr.wikipedia.org/wiki/Liste_des_chemins_de_fer_touristiques_de_France', 'licence' => 'CC BY-SA 4.0']],
        ],
        'meta' => ['railways' => 1, 'greenways' => 1],
        'links' => ['web' => 'https://vff-project.org/tourist-railways'],
    ]])]
    #[ApiResponse(422, 'Type inconnu : détail par paramètre dans `errors`.', examples: [['message' => 'The selected type is invalid.', 'errors' => ['type' => ['The selected type is invalid.']]]])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function index(Request $request, TouristRailways $railways): JsonResponse
    {
        $filters = $request->validate([
            'type' => ['nullable', 'string', Rule::in(TouristRailways::TYPES)],
            'region' => ['nullable', 'string', 'max:100'],
        ]);
        $data = $railways->get();
        $type = $filters['type'] ?? null;
        $region = $filters['region'] ?? null;

        $list = array_values(array_filter($data['railways'], fn (array $r): bool => ($type === null || $r['type'] === $type)
            && ($region === null || in_array($region, $r['regions'], true))));
        $greenways = $type !== null ? [] : array_values(array_filter($data['greenways'], fn (array $g): bool => $region === null || in_array($region, $g['regions'], true)));

        return response()->json([
            'data' => ['railways' => $list, 'greenways' => $greenways, 'regions' => $data['regions'], 'types' => $data['types'], 'sources' => $data['sources']],
            'meta' => ['railways' => count($list), 'greenways' => count($greenways)],
            'links' => ['web' => route('tourist-railways')],
        ])->header('Cache-Control', 'public, max-age=3600');
    }
}

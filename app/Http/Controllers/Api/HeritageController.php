<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Heritage\HeritageIndex;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

#[Group('Gares', "L'annuaire des gares, haltes et points d'arrêt, actuels et disparus, et la fiche complète de chaque gare : dates, présence par décennie, lignes desservies, sources.", weight: 2)]
class HeritageController extends Controller
{
    /**
     * Patrimoine ferroviaire protégé.
     *
     * Les bâtiments voyageurs, ouvrages de gare et ouvrages d'art (viaducs, ponts, tunnels) classés ou inscrits aux
     * monuments historiques ou labellisés « Architecture contemporaine remarquable » : ce que montre la page
     * « Patrimoine ferroviaire protégé ». Les objets seulement recensés à l'Inventaire général n'y figurent pas
     * (voir `GET /stations/{station}/heritage`). Sources : base Mérimée (Licence Ouverte 2.0), Wikidata (CC0), images
     * de Wikimedia Commons sous la licence de chaque fichier.
     *
     * Chaque objet de `items` donne :
     *
     * - `id`, `object` (`building` bâtiment voyageurs, `works` ouvrage de gare, `viaduct`, `bridge`, `tunnel`), `name`
     *   et `title` (titre de la notice Mérimée, nul sans notice) ;
     * - `protection` (`classified`, `registered`, `acr`), `protection_date` (date de l'arrêté, nulle pour un label
     *   ou quand la source l'ignore), `scope` (`partial` : seulement certaines parties) ;
     * - `architects` (noms), `built` (`from`, `to`, `period` : datation par siècle quand Mérimée ne donne pas d'années ;
     *   `year` : année connue ou estimée au milieu de la période), `era` (`before_1850`, `1850_1899`, `1900_1944`,
     *   `1945_1999`, `from_2000`, `unknown`), `style` ;
     * - `commune`, `department`, `region` ; pour un ouvrage d'art, la commune est celle dont le centre est le plus proche ;
     * - `reference` (`id`, `url` de la notice Mérimée ou de l'élément Wikidata), `image` (image libre : `url`,
     *   `source_url`, `license`, `author` ; nulle sinon), `station` (`slug`, `name`) ou `line` (`slug`, `name`, pour un
     *   ouvrage d'art), `lat`, `lng`.
     *
     * `facets` donne, sur l'ensemble des objets, les valeurs possibles de chaque filtre avec leur nombre.
     */
    #[QueryParameter('protection', 'Protection : `classified` (classé), `registered` (inscrit) ou `acr` (label Architecture contemporaine remarquable). Inconnue : HTTP 422.', type: 'string', example: 'registered')]
    #[QueryParameter('object', 'Objet : `building`, `works`, `viaduct`, `bridge` ou `tunnel`. Inconnu : HTTP 422.', type: 'string', example: 'building')]
    #[QueryParameter('style', 'Style (nom exact, tel que listé dans `facets.styles`).', type: 'string', example: 'Art déco')]
    #[QueryParameter('architect', 'Architecte (nom exact, tel que listé dans `facets.architects`).', type: 'string', example: 'Victor Laloux')]
    #[QueryParameter('region', 'Région (nom exact, ex. `Occitanie`), telle que listée dans `facets.regions`.', type: 'string', example: 'Nouvelle-Aquitaine')]
    #[QueryParameter('era', 'Époque de construction : `before_1850`, `1850_1899`, `1900_1944`, `1945_1999`, `from_2000` ou `unknown`. Inconnue : HTTP 422.', type: 'string', example: '1900_1944')]
    #[QueryParameter('sort', 'Tri : `name` (défaut), `protection_date` (la plus récente d\'abord) ou `built` (la plus ancienne d\'abord) ; dates inconnues en dernier. Inconnu : HTTP 422.', type: 'string', example: 'built')]
    #[ApiResponse(200, 'Les objets protégés et les valeurs de filtre (extrait).', examples: [[
        'data' => [
            'items' => [[
                'id' => 'st1', 'object' => 'building', 'name' => 'Limoges-Bénédictins', 'title' => 'Gare des Bénédictins',
                'protection' => 'registered', 'protection_date' => '1975-01-15', 'scope' => null,
                'architects' => ['Roger Gonthier'],
                'built' => ['from' => 1925, 'to' => null, 'period' => '1er quart 20e siècle', 'year' => 1925], 'era' => '1900_1944', 'style' => null,
                'commune' => 'Limoges', 'department' => '87', 'region' => 'Nouvelle-Aquitaine',
                'reference' => ['id' => 'PA00100349', 'url' => 'https://www.pop.culture.gouv.fr/notice/merimee/PA00100349'],
                'image' => ['url' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/ab/Exemple.jpg/500px-Exemple.jpg', 'source_url' => 'https://commons.wikimedia.org/wiki/File:Exemple.jpg', 'license' => 'CC BY-SA 4.0', 'author' => 'Exemple'],
                'station' => ['slug' => 'limoges-benedictins', 'name' => 'Limoges-Bénédictins'], 'line' => null,
                'lat' => 45.83, 'lng' => 1.27,
            ]],
            'facets' => [
                'protections' => [['key' => 'registered', 'count' => 76]], 'objects' => [['key' => 'building', 'count' => 85]],
                'eras' => [['key' => '1900_1944', 'count' => 20]],
                'styles' => [['name' => 'Art déco', 'count' => 3]], 'regions' => [['name' => 'Nouvelle-Aquitaine', 'count' => 12]],
                'architects' => [['name' => 'Roger Gonthier', 'count' => 1]],
            ],
            'attribution' => 'Base Mérimée, Plateforme ouverte du patrimoine (ministère de la Culture), sous Licence Ouverte 2.0 : mention « Ministère de la Culture, base Mérimée (POP) » ; Wikidata (CC0) ; images de Wikimedia Commons sous la licence de chaque fichier.',
        ],
        'meta' => ['count' => 1, 'total' => 131],
        'links' => ['web' => 'https://vff-project.org/heritage'],
    ]])]
    #[ApiResponse(422, 'Filtre inconnu : détail par paramètre dans `errors`.', examples: [['message' => 'The selected protection is invalid.', 'errors' => ['protection' => ['The selected protection is invalid.']]]])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function index(Request $request, HeritageIndex $heritage): JsonResponse
    {
        $f = $request->validate([
            'protection' => ['nullable', 'string', Rule::in(HeritageIndex::PROTECTIONS)],
            'object' => ['nullable', 'string', Rule::in(HeritageIndex::OBJECTS)],
            'style' => ['nullable', 'string', 'max:120'],
            'architect' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:100'],
            'era' => ['nullable', 'string', Rule::in(HeritageIndex::ERAS)],
            'sort' => ['nullable', 'string', Rule::in(HeritageIndex::SORTS)],
        ]);
        $data = $heritage->get();
        $items = HeritageIndex::filter($data['items'], $f, $f['sort'] ?? 'name');

        return response()->json([
            'data' => ['items' => $items, 'facets' => $data['facets'], 'attribution' => $data['attribution']],
            'meta' => ['count' => count($items), 'total' => count($data['items'])],
            'links' => ['web' => route('heritage')],
        ])->header('Cache-Control', 'public, max-age=3600');
    }
}

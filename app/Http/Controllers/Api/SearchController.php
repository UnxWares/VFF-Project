<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CatalogSearch;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Recherche', 'Trouver une ligne, une gare ou un matériel roulant par son nom.', weight: 0)]
class SearchController extends Controller
{
    /**
     * Recherche de lignes, gares et matériels.
     *
     * Recherche par nom pour l'autocomplétion : jusqu'à 8 lignes (nom ou code
     * à 6 chiffres), 8 gares (avec leur ville et le code de leur département
     * pour distinguer les homonymes) et 5 matériels roulants. Lignes et gares
     * sont triées par pertinence : correspondance exacte, début du nom, mot du
     * nom, puis le reste ; à pertinence égale, les lignes en service passent
     * avant les lignes fermées, puis ordre alphabétique français. Insensible à
     * la casse et aux accents (« saint-etienne » trouve « Saint-Étienne »).
     * Moins de 2 caractères : listes vides. Les `slug` renvoyés servent
     * d'identifiants dans les autres endpoints (`/lines/{line}/…`, `from` et
     * `to` de l'itinéraire).
     *
     * @response array{
     *     lines: array<array{type: 'line', slug: string, name: string, code: ?string}>,
     *     stations: array<array{type: 'station', slug: string, name: string, city: ?string, department_code: ?string, lat: float, lng: float}>,
     *     materials: array<array{type: 'material', slug: string, name: string, kind: string, builder: ?string}>
     * }
     */
    #[QueryParameter('q', 'Texte recherché, 2 caractères au moins : nom de ligne, code de ligne (ex. `420000`), nom de gare ou de matériel.', required: true, type: 'string', example: 'brest')]
    #[ApiResponse(200, 'Résultats groupés par catégorie.', examples: [[
        'lines' => [['type' => 'line', 'slug' => 'rfn-420000', 'name' => 'Ligne de Paris-Montparnasse à Brest', 'code' => '420000']],
        'stations' => [['type' => 'station', 'slug' => 'brest', 'name' => 'Brest', 'city' => 'Brest', 'department_code' => '29', 'lat' => 48.3876941, 'lng' => -4.4800927]],
        'materials' => [],
    ]])]
    public function index(Request $request, CatalogSearch $search): JsonResponse
    {
        return response()->json($search->search($request->query('q')))
            ->header('Cache-Control', 'public, max-age=30');
    }
}

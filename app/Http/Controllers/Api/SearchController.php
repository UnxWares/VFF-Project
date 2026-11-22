<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CatalogSearch;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Recherche', 'Trouver une ligne, une gare, un matériel roulant ou un accident par son nom.', weight: 0)]
class SearchController extends Controller
{
    /**
     * Recherche de lignes, gares, matériels et accidents.
     *
     * Recherche par nom pour l'autocomplétion : jusqu'à 8 lignes (nom, autres noms ou code à 6 chiffres, avec
     * ou sans espace : `570 000`), 8 gares (nom, commune, anciens noms, début du code UIC ou numéro de
     * département ; avec leur ville et le code de leur département pour distinguer les homonymes), 5 matériels
     * roulants et 5 accidents (par leur nom dans l'une des trois langues, leur commune ou leur date : `1891`,
     * `07/2013`, `2013-07-12`). Seuls les accidents publiés sont renvoyés, comme dans `GET /api/v1/accidents`.
     *
     * Même lecture de la requête dans tout VFF : casse, accents, tirets, apostrophes, points et espaces
     * indifférents ; abréviations françaises dans les deux sens (St, Ste, N.-D., s/, s/s, lès ou lez, Gd, Mt,
     * Ft, G.) ; petits mots facultatifs (de, la, l', à…) ; mots dans n'importe quel ordre, chacun au début d'un
     * mot (« st hilaire chaleons », « hilaire chaléons » et « saint-hilaire-de-c » trouvent St-Hilaire-de-Chaléons).
     * Tri par pertinence : nom exact, début du nom, début d'un mot du nom, tous les mots dans le nom, dans un autre
     * nom, puis au milieu d'un mot ; à pertinence égale, gares ouvertes et lignes en service d'abord, gares les plus
     * desservies, puis ordre alphabétique français (les accidents, à nom égal, du plus ancien au plus récent).
     * Quand moins de 3 fiches d'une catégorie correspondent, la liste est complétée par les noms les plus proches
     * (fautes de frappe : « angoulemme »), marqués `approximate: true`.
     * Moins de 2 caractères : listes vides. Les `slug` renvoyés servent d'identifiants dans les autres endpoints
     * (`/lines/{line}/…`, `from` et `to` de l'itinéraire). Le surnom d'une rame remarquable (« Patrick »,
     * « TGV Patrick ») trouve sa série, placée en tête avec `notable_unit` (numéro et surnom de la rame) ;
     * `notable_unit` est nul sinon.
     *
     * @response array{
     *     lines: array<array{type: 'line', slug: string, name: string, code: ?string, approximate: bool}>,
     *     stations: array<array{type: 'station', slug: string, name: string, city: ?string, department_code: ?string, lat: float, lng: float, approximate: bool}>,
     *     materials: array<array{type: 'material', slug: string, name: string, kind: string, builder: ?string, notable_unit: ?array{number: string, nickname: ?string}, approximate: bool}>,
     *     accidents: array<array{type: 'accident', slug: string, name: string, names: array{fr: string, en: string, de: string}, date: string, date_precision: 'day'|'month'|'year', kind: string, commune: ?string, department_code: ?string, lat: ?float, lng: ?float, approximate: bool}>
     * }
     */
    #[QueryParameter('q', 'Texte recherché, 2 caractères au moins : nom de ligne, code de ligne (ex. `420000`), nom de gare, de matériel ou d\'accident, commune ou date d\'accident (`1891`, `07/2013`, `12/07/2013`).', required: true, type: 'string', example: 'brest')]
    #[ApiResponse(200, 'Résultats groupés par catégorie.', examples: [[
        'lines' => [['type' => 'line', 'slug' => 'rfn-420000', 'name' => 'Ligne de Paris-Montparnasse à Brest', 'code' => '420000', 'approximate' => false]],
        'stations' => [['type' => 'station', 'slug' => 'brest', 'name' => 'Brest', 'city' => 'Brest', 'department_code' => '29', 'lat' => 48.3876941, 'lng' => -4.4800927, 'approximate' => false]],
        'materials' => [],
        'accidents' => [],
    ]])]
    public function index(Request $request, CatalogSearch $search): JsonResponse
    {
        return response()->json($search->search($request->query('q')))
            ->header('Cache-Control', 'public, max-age=30');
    }
}

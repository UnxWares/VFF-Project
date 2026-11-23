<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Services\Materials\PreservedRollingStock;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;

#[Group('Matériel roulant', "Les séries de matériel roulant qui ont circulé en France (locomotives, automotrices, autorails, rames TGV, voitures, wagons, tramways), leurs caractéristiques sourcées et les lignes qu'elles ont parcourues.", weight: 6)]
class PreservedMaterialController extends Controller
{
    /** Exemple : l'exemplaire 241 P 17, conservé au Creusot et classé au titre des objets en 1990 (lecture du 23/11/2026). */
    private const SERIES_EXAMPLE = [
        'data' => [[
            'number' => '241 P 17',
            'venue' => [
                'slug' => 'chemins-de-fer-du-creusot-cfc', 'name' => 'Chemins de fer du Creusot (CFC)', 'type' => 'tourist_railway', 'commune' => 'Le Creusot',
                'tourist_railway' => ['name' => 'Les Chemins de Fer du Creusot (241P 17)', 'url' => '/tourist-railways#les-chemins-de-fer-du-creusot-241p-17-train'],
            ],
            'where' => null,
            'state' => null,
            'protection' => ['kind' => 'classified', 'date' => '1990-03-27', 'year' => 1990, 'ref' => 'PM71000825', 'url' => 'https://www.pop.culture.gouv.fr/notice/palissy/PM71000825'],
            'sources' => [
                ['key' => 'wikipedia', 'label' => 'Wikipédia en français', 'licence' => 'CC BY-SA 4.0', 'ref' => '241 P', 'url' => 'https://fr.wikipedia.org/w/index.php?title=241_P&oldid=1'],
                ['key' => 'palissy', 'label' => 'Base Palissy, Plateforme ouverte du patrimoine (ministère de la Culture)', 'licence' => 'Licence Ouverte', 'ref' => 'PM71000825', 'url' => 'https://www.pop.culture.gouv.fr/notice/palissy/PM71000825'],
            ],
        ]],
        'meta' => ['material' => '241-p', 'total' => 1],
    ];

    /** Exemple de la liste des lieux, réduit à un lieu et à une pièce. */
    private const VENUES_EXAMPLE = [
        'data' => [[
            'slug' => 'cite-du-train', 'name' => 'Cité du train', 'type' => 'museum', 'commune' => 'Mulhouse', 'tourist_railway' => null, 'count' => 1,
            'pieces' => [[
                'number' => 'CC 40101', 'title' => null, 'series' => [['slug' => 'cc-40100', 'name' => 'CC 40100']], 'where' => null, 'state' => 'exposed', 'protection' => null,
                'sources' => [['key' => 'wikipedia', 'label' => 'Wikipédia en français', 'licence' => 'CC BY-SA 4.0', 'ref' => 'CC 40100', 'url' => 'https://fr.wikipedia.org/w/index.php?title=CC_40100&oldid=1']],
            ]],
        ]],
        'meta' => ['venues' => 1, 'units' => 462, 'series' => 107, 'protected' => 87, 'with_place' => 369, 'others' => 514],
    ];

    /**
     * Exemplaires préservés d'une série.
     *
     * Les exemplaires de la série que des sources disent conservés : `number` (tel que la source l'écrit), `venue` (le
     * lieu où on peut le voir : `type` `museum`, `association`, `tourist_railway` ou `site`, `commune`, et
     * `tourist_railway.url`, l'adresse de la ligne touristique sur `/tourist-railways` quand elle y figure), `where`
     * (le lieu dit par la source quand VFF n'en a pas fait une fiche de lieu), `state` (`running` en état de marche,
     * `exposed` exposé, `restoration` en restauration, `stored` garé ou en réserve : seulement si la source le dit, nul
     * sinon), `protection` (monument historique : `kind` `classified`, `listed` ou `declassified`, `date` de la notice
     * Palissy, `year` quand seule l'année est connue, `ref` et `url` de la notice) et `sources` (Wikipédia, base Palissy,
     * Wikidata, chacune avec sa licence et l'adresse de la version lue).
     *
     * **Couverture** : la liste est celle des sources, pas un inventaire. Une série absente n'a pas d'exemplaire préservé
     * connu de VFF ; un exemplaire absent n'est pas démoli pour autant. Un exemplaire que la source dit ferraillé ou
     * démantelé n'est pas repris. L'état est celui que la source rapporte à sa date de lecture, il peut avoir changé.
     */
    #[PathParameter('material', 'Slug de la série, tel que renvoyé par la liste (`slug`).', example: 'bb-15000')]
    #[ApiResponse(200, 'Les exemplaires préservés de la série (liste vide si aucune source n\'en cite).', examples: [self::SERIES_EXAMPLE])]
    #[ApiResponse(404, 'Série inconnue (slug introuvable).', examples: [MaterialExamples::MATERIAL_NOT_FOUND])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function show(Material $material, PreservedRollingStock $preserved): JsonResponse
    {
        $units = $preserved->forMaterial($material->slug);

        return response()->json(['data' => $units, 'meta' => ['material' => $material->slug, 'total' => count($units)]])
            ->header('Cache-Control', 'public, max-age=600');
    }

    /**
     * Lieux où voir du matériel préservé.
     *
     * Les musées, associations, lignes touristiques et sites (gare, remise, dépôt) qui conservent du matériel roulant,
     * avec leurs pièces : pour chacune, `number`, `series` (les séries de VFF auxquelles son numéro se rattache, vide
     * pour du matériel à voie étroite, des wagons ou des voitures), `title` (la désignation de la notice Palissy pour
     * ces pièces), `where`, `state`, `protection` et `sources`, comme pour `GET /materials/{material}/preserved`.
     * Une pièce rattachée à deux séries (une série large et une série d'un seul numéro) n'est comptée qu'une fois.
     * `meta` donne la couverture : exemplaires rattachés à une série (`units`), séries concernées, exemplaires
     * protégés au titre des monuments historiques, exemplaires dont on connaît le lieu (`with_place`) et objets
     * protégés sans série (`others`). La liste n'est pas paginée. Réponse gardée en cache 10 minutes.
     */
    #[ApiResponse(200, 'Les lieux et leurs pièces (ici un lieu, une pièce).', examples: [self::VENUES_EXAMPLE])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function index(PreservedRollingStock $preserved): JsonResponse
    {
        $venues = $preserved->venues();
        $figures = $preserved->figures();
        unset($figures['venues']);

        return response()->json(['data' => $venues, 'meta' => ['venues' => count($venues)] + $figures])
            ->header('Cache-Control', 'public, max-age=600');
    }
}

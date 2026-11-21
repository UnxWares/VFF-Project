<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AccidentMapRequest;
use App\Http\Requests\Api\ListAccidentsRequest;
use App\Http\Resources\AccidentDetailResource;
use App\Http\Resources\AccidentResource;
use App\Models\Accident;
use App\Services\Accidents\AccidentMapFeatures;
use App\Services\Accidents\Context\AccidentContextView;
use App\Services\Api\AccidentQuery;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;

#[Group('Accidents', "Les accidents et événements graves du rail français : date et précision, lieu, ligne et portion à l'époque, bilan humain et sa source, résumé, sources ouvertes avec leur licence.", weight: 7)]
class AccidentController extends Controller
{
    /**
     * Liste des accidents.
     *
     * Accidents et événements graves du rail en France, de 1842 à aujourd'hui, filtrables,
     * triables et paginés. Chaque entrée porte l'essentiel de la fiche (date, nature, lieu,
     * ligne principale, bilan, résumé) ; la fiche (`/accidents/{accident}`) ajoute les lignes et
     * portions, les trains, les sources et leurs faits, et les points à vérifier.
     *
     * **Sources** (`vff:import-accidents`) : Wikidata (CC0), articles de Wikipédia en français
     * (faits seulement, lien vers la révision lue ; CC BY-SA 4.0 pour toute citation) et bilan
     * général des enquêtes du BEA-TT (Licence Ouverte 2.0). Quand elles décrivent le même accident
     * (même jour à un près et même commune, ou lieux à moins de 20 km), elles sont fusionnées en
     * une fiche. Un champ nul veut dire qu'aucune source ne le donne ; VFF n'estime aucune valeur.
     *
     * **Bilan** (`deaths`, `injuries`) : valeur de l'enquête du BEA-TT quand elle existe, sinon de
     * Wikidata, sinon de Wikipédia (`toll_source`). Des sources qui divergent mettent la fiche
     * `to_review` ; une valeur Wikidata ambiguë (plusieurs bilans au même rang) n'est jamais retenue.
     *
     * **Rattachement aux lignes** : sur les tronçons en service aux ères qui encadrent la date
     * (un accident de 1933 se cherche sur les tracés de 1930 et 1940), dans un rayon qui dépend
     * de la précision du lieu (800 m pour des coordonnées, 5 km pour un centre de commune) ;
     * renforcé quand une source nomme la ligne. `line.confidence` dit ce qu'il vaut.
     *
     * **Tri** (`sort`) : `date` (par défaut, du plus ancien au plus récent), `-date`, `deaths`,
     * `-deaths` (bilan le plus lourd d'abord) ; bilan inconnu toujours en dernier, puis par date.
     *
     * **Pagination** : par pages (`page`, `per_page` de 1 à 100, 25 par défaut). La réponse
     * contient `links` (`first`, `last`, `prev`, `next` : adresses prêtes à suivre, filtres
     * conservés, `null` en bout de liste) et `meta` (`current_page`, `last_page`, `per_page`,
     * `total`, `from`, `to`, `path`).
     */
    #[QueryParameter('q', 'Recherche sans accents ni capitales dans le nom de l\'accident (toutes langues) et la commune (1 à 100 caractères).', type: 'string', example: 'bretigny')]
    #[QueryParameter('from', 'Accidents survenus à partir de cette année, incluse (1800 à 2100).', type: 'int', example: 1900)]
    #[QueryParameter('to', 'Accidents survenus jusqu\'à cette année, incluse (1800 à 2100, au moins égale à `from`).', type: 'int', example: 1999)]
    #[QueryParameter('line', 'Slug d\'une ligne : accidents rattachés à cette ligne (rattachement principal ou secondaire). Ligne inconnue : HTTP 422.', type: 'string', example: 'rfn-570000')]
    #[QueryParameter('department', 'Code INSEE du département actuel du lieu (`01` à `95`, `2A`, `2B`, `971` à `976`).', type: 'string', example: '91')]
    #[QueryParameter('region', 'Code INSEE de la région du lieu (`11`, `24`, `27`, `28`, `32`, `44`, `52`, `53`, `75`, `76`, `84`, `93`, `94`, outre-mer `01` à `06`). Liste avec les noms : `/regions`.', type: 'string', example: '11')]
    #[QueryParameter('station', 'Slug d\'une gare : accidents dont elle est la gare la plus proche du lieu (10 km au plus ; distance dans la fiche, `nearest_station.distance_m`). Gare inconnue : HTTP 422.', type: 'string', example: 'bretigny')]
    #[QueryParameter('kind', 'Nature : `collision`, `derailment`, `level_crossing`, `fire`, `explosion`, `structure_failure`, `attack`, `person_struck`, `runaway`, `incident`, `other`.', type: 'string', example: 'derailment')]
    #[QueryParameter('review', '`ok` : fiches dont la date et le lieu sont confirmés (enquête du BEA-TT ou deux sources indépendantes) ; `to_review` : fiches dont un point reste à vérifier.', type: 'string', example: 'ok')]
    #[QueryParameter('min_deaths', 'Bilan d\'au moins ce nombre de morts (0 ou plus) ; exclut les bilans inconnus.', type: 'int', example: 10)]
    #[QueryParameter('has_line', '`true` : accidents rattachés à une ligne VFF ; `false` : sans ligne (lieu inconnu, réseau urbain).', type: 'bool', example: true)]
    #[QueryParameter('sort', 'Tri : `date`, `-date`, `deaths`, `-deaths`. Bilan inconnu en dernier.', type: 'string', default: 'date', example: '-deaths')]
    #[QueryParameter('per_page', 'Nombre d\'accidents par page (1 à 100).', type: 'int', default: 25, example: 25)]
    #[QueryParameter('page', 'Numéro de page, à partir de 1.', type: 'int', default: 1, example: 1)]
    #[ApiResponse(200, 'Une page de la liste des accidents.', examples: [AccidentExamples::LIST])]
    #[ApiResponse(422, 'Paramètre invalide (nature, tri ou état inconnus, année hors bornes, `to` antérieure à `from`, ligne inconnue, département mal formé, `per_page` hors limites) : détail par paramètre dans `errors`.', examples: [AccidentExamples::INVALID])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function index(ListAccidentsRequest $request, AccidentQuery $query): JsonResponse
    {
        $accidents = $query->apply(Accident::query()->withCoordinates(), $request->filters())
            ->with(['lineLinks' => fn ($q) => $q->where('is_primary', true)->with('line:id,slug,code,name')])
            ->paginate($request->perPage())
            ->withQueryString();

        return AccidentResource::collection($accidents)->response()->header('Cache-Control', 'public, max-age=600');
    }

    /**
     * Fiche d'un accident.
     *
     * Tous les champs de la liste, plus : `lines` (lignes rattachées avec l'ère du tracé utilisé,
     * la distance, le PK estimé, la portion entre deux gares, la méthode et la confiance),
     * `nearest_station`, `trains`, `toll_source_url`, `wikipedia` (articles par langue), `sources`
     * (fournisseur, lien, licence, révision lue, date de consultation et faits relevés dans chaque
     * source), `review` (points à vérifier, avec un code stable et une explication) et
     * `provenance` (fournisseur de chaque fait retenu).
     *
     * Le résumé (`summary`) est rédigé par VFF à partir des faits retenus, en français, anglais
     * et allemand ; aucun texte de source n'est repris et aucune victime n'est nommée.
     */
    #[PathParameter('accident', 'Slug de l\'accident, tel que renvoyé par la liste (`slug`) : date puis commune.', example: '2013-07-12-bretigny-sur-orge')]
    #[ApiResponse(200, 'Fiche de l\'accident.', examples: [AccidentExamples::SHOW])]
    #[ApiResponse(404, 'Accident inconnu (slug introuvable).', examples: [AccidentExamples::NOT_FOUND])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function show(string $accident): JsonResponse
    {
        $model = Accident::query()->withCoordinates()->where('slug', $accident)->firstOrFail();
        $model->load([
            'lineLinks' => fn ($q) => $q->with(['line:id,slug,code,name', 'sectionFrom:id,slug,name', 'sectionTo:id,slug,name']),
            'sources',
            'nearestStation:id,slug,name',
            ...AccidentContextView::RELATIONS,
        ]);

        return AccidentDetailResource::make($model)->response()->header('Cache-Control', 'public, max-age=600');
    }

    /**
     * Accidents en GeoJSON (carte).
     *
     * Une FeatureCollection compacte, un point par accident dont le lieu est connu, pour poser les
     * accidents sur une carte (calque « Accidents » de `/maps`). Les accidents sans lieu ne sont pas
     * renvoyés : aucune position n'est estimée. Pour le détail d'un accident, suivez son `slug`
     * vers `/accidents/{accident}`.
     *
     * **Géométrie** : `Point`, coordonnées WGS 84 `[longitude, latitude]` arrondies à 5 décimales
     * (environ 1 m).
     *
     * **Précision du lieu** (`precision`) : `exact` (coordonnées données par la source),
     * `level_crossing` (passage à niveau cité, position SNCF Réseau), `station` (gare citée),
     * `commune` (centre de la commune : le lieu exact n'est pas connu, à montrer comme une zone et
     * non comme un point précis).
     *
     * **Propriétés** : `slug` ; `date` (AAAA-MM-JJ) et `date_precision` (`day`, `month`, `year` :
     * n'affichez que la partie connue) ; `year` (année, pour filtrer par époque) ; `kind` (mêmes
     * valeurs que le filtre `kind` de la liste) ; `place` (commune) et `department` (code INSEE
     * du département actuel), tous deux parfois nuls ; `deaths` et `injuries` (nul = bilan
     * inconnu des sources) ; `to_review` (`true` : un point de la fiche reste à vérifier).
     *
     * Ordre : du plus ancien au plus récent. Réponse en cache 10 minutes. Sources et licences :
     * celles de la liste des accidents (Wikidata CC0, Wikipédia pour les faits, BEA-TT Licence
     * Ouverte 2.0, passages à niveau SNCF Réseau ODbL).
     */
    #[QueryParameter('to', 'Accidents survenus jusqu\'à cette année, incluse (1800 à 2100). La carte de `/maps` affiche ainsi les accidents connus à la fin de la décennie choisie.', type: 'int', example: 1950)]
    #[ApiResponse(200, 'FeatureCollection GeoJSON des accidents localisés.', examples: [AccidentExamples::MAP])]
    #[ApiResponse(422, 'Année `to` hors des bornes 1800 à 2100 ou non entière.', examples: [AccidentExamples::MAP_INVALID])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function map(AccidentMapRequest $request, AccidentMapFeatures $features): JsonResponse
    {
        return response()
            ->json($features->collection($request->toYear()))
            ->header('Cache-Control', 'public, max-age=600');
    }
}

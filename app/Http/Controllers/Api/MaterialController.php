<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListMaterialsRequest;
use App\Http\Requests\Api\SheetMediaRequest;
use App\Http\Resources\MaterialDetailResource;
use App\Http\Resources\MaterialResource;
use App\Models\Material;
use App\Services\Api\MaterialQuery;
use App\Services\Api\MediaGallery;
use App\Services\Materials\MaterialLinesMap;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

#[Group('Matériel roulant', "Les séries de matériel roulant qui ont circulé en France (locomotives, automotrices, autorails, rames TGV, voitures, wagons, tramways), leurs caractéristiques sourcées et les lignes qu'elles ont parcourues.", weight: 6)]
class MaterialController extends Controller
{
    /**
     * Liste du matériel roulant.
     *
     * Les séries de matériel roulant ayant circulé en France, filtrables, triables et
     * paginées. Chaque entrée porte la fiche complète de la série (caractéristiques,
     * constructeurs, exploitants, image) sauf la provenance champ par champ et les lignes
     * parcourues, données par la fiche (`/materials/{material}`).
     *
     * **Sources** : fiches lues dans Wikidata (CC0, aucune obligation) par
     * `vff:import-materials` ; image et droits lus dans Wikimedia Commons (licence propre à
     * chaque fichier, à afficher avec l'image : `image.attribution`). Un champ nul veut dire
     * que la source ne le renseigne pas : VFF n'estime aucune valeur. En particulier,
     * `in_service_to` nul ne prouve pas que la série circule encore.
     *
     * **Unités** : vitesses en km/h, puissances en kW, écartements en mm, années sur 4
     * chiffres.
     *
     * **Recherche** (`q`) : sans accents ni capitales, dans le nom, les autres noms
     * (`designations`), les constructeurs et les numéros d'engins (« picasso » trouve
     * l'X 3800, « 15025 » la BB 15000).
     *
     * **Tri** (`sort`) : `name` (par défaut), `in_service_from`, `max_speed_kmh`,
     * `units_built`, `lines_count` ; préfixe `-` pour l'ordre décroissant
     * (`-max_speed_kmh` : les plus rapides d'abord). Les valeurs inconnues viennent toujours
     * en dernier ; à valeur égale, ordre alphabétique.
     *
     * **Pagination par curseur**, comme les autres listes : `per_page` (1 à 100, 25 par
     * défaut) et `cursor`. La réponse contient `links.next` (adresse de la page suivante,
     * filtres et tri conservés, `null` à la dernière page) et `meta.next_cursor`. Suivez
     * `links.next` jusqu'à `null`. Le curseur est une chaîne opaque, valable seulement pour
     * cette liste et ce tri ; un curseur illisible ou venu d'une autre liste donne HTTP 422.
     * Il n'y a ni numéro de page ni total.
     */
    #[QueryParameter('q', 'Recherche, sans accents ni capitales, dans le nom, les autres noms, les constructeurs et les numéros d\'engins (1 à 100 caractères).', type: 'string', example: 'picasso')]
    #[QueryParameter('type', 'Catégorie : `locomotive_vapeur`, `locomotive_diesel`, `locomotive_electrique`, `automotrice`, `bimode`, `autorail`, `rame_tgv`, `voiture`, `wagon`, `tramway`, `engin_service`.', type: 'string', example: 'locomotive_electrique')]
    #[QueryParameter('builder', 'Constructeur, recherche partielle sans accents ni capitales (ex. `alsthom`, `fives-lille`).', type: 'string', example: 'alstom')]
    #[QueryParameter('operator', 'Exploitant : identifiant Wikidata exact (`Q13646` = SNCF) ou nom partiel sans accents ni capitales (`paris a lyon` trouve le PLM).', type: 'string', example: 'Q13646')]
    #[QueryParameter('line', 'Slug d\'une ligne : séries qui l\'ont parcourue d\'après une source (voir `/lines/{line}/materials`). Ligne inconnue : HTTP 422.', type: 'string', example: 'rfn-67000')]
    #[QueryParameter('in_service_at', 'Année (1800 à 2100) : séries mises en service cette année-là ou avant, et pas retirées avant. Une série sans date de mise en service est exclue ; une série sans date de retrait est retenue.', type: 'int', example: 1960)]
    #[QueryParameter('from_year', 'Mise en service à partir de cette année, incluse (1800 à 2100).', type: 'int', example: 1950)]
    #[QueryParameter('to_year', 'Mise en service jusqu\'à cette année, incluse (1800 à 2100, au moins égale à `from_year`).', type: 'int', example: 1959)]
    #[QueryParameter('gauge_mm', 'Écartement en millimètres (500 à 2000) : `1435` voie normale, `1000` voie métrique.', type: 'int', example: 1000)]
    #[QueryParameter('service_status', 'État d\'utilisation déclaré par une source : `in_service` (en service) ou `withdrawn` (retirée). Une série dont aucune source ne dit l\'état n\'est dans aucun des deux cas : l\'absence de date de retrait ne prouve pas qu\'elle circule.', type: 'string', example: 'in_service')]
    #[QueryParameter('scope', 'Périmètre : `france` (séries rattachées à la France dans Wikidata) ou `attested` (séries non rattachées, comme un ICE, un AVE ou une locomotive belge, dont une source atteste la circulation sur une ligne française).', type: 'string', example: 'attested')]
    #[QueryParameter('has_image', '`true` : séries illustrées seulement ; `false` : séries sans image.', type: 'bool', example: true)]
    #[QueryParameter('has_lines', '`true` : séries reliées à au moins une ligne ; `false` : séries sans ligne connue.', type: 'bool', example: true)]
    #[QueryParameter('sort', 'Tri : `name`, `-name`, `in_service_from`, `-in_service_from`, `max_speed_kmh`, `-max_speed_kmh`, `units_built`, `-units_built`, `lines_count`, `-lines_count`. Inconnues en dernier.', type: 'string', default: 'name', example: '-max_speed_kmh')]
    #[QueryParameter('per_page', 'Nombre de séries par page (1 à 100).', type: 'int', default: 25, example: 25)]
    #[QueryParameter('cursor', 'Curseur de la page voulue, tel que renvoyé par `meta.next_cursor` : une chaîne opaque, à ne pas fabriquer. Sans curseur : première page.', type: 'string', example: 'eyJzb3J0X251bGwiOjAsInNvcnRfa2V5IjoiQkIgMTUwMDAiLCJzb3J0X25hbWUiOiJCQiAxNTAwMCIsImlkIjoxMiwiX3BvaW50c1RvTmV4dEl0ZW1zIjp0cnVlfQ')]
    #[ApiResponse(200, 'Une page de la liste du matériel roulant (ici `type=locomotive_electrique&sort=-max_speed_kmh&q=BB 1&per_page=1`).', examples: [self::LIST_EXAMPLE])]
    #[ApiResponse(422, 'Paramètre invalide (catégorie ou tri inconnus, année hors bornes, `to_year` antérieure à `from_year`, ligne inconnue, `per_page` hors limites, curseur illisible ou venu d\'une autre liste) : détail par paramètre dans `errors`.', examples: [MaterialExamples::MATERIALS_INVALID])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function index(ListMaterialsRequest $request, MaterialQuery $query): JsonResponse
    {
        $materials = $query->cursorQuery($request->filters())
            ->cursorPaginate($request->perPage())
            ->withQueryString();

        return MaterialResource::collection($materials)->response()->header('Cache-Control', 'public, max-age=600');
    }

    /**
     * Fiche d'une série de matériel.
     *
     * La fiche complète d'une série : tous les champs de la liste, plus
     * `field_sources` (provenance de CHAQUE champ : source, référence précise comme
     * `Q746572#P729` pour la propriété « date de mise en service » de l'élément Wikidata,
     * adresse, licence, et valeur d'origine quand VFF a converti une unité) et `lines`
     * (lignes parcourues, une entrée par lien attesté, avec sa preuve).
     *
     * **Liens avec les lignes** : créés seulement quand une source le dit, jamais déduits
     * d'un nom approchant : déclaration Wikidata « véhicule habituellement utilisé »
     * (P3438), ou lien posé vers la série dans une section « Matériel roulant »,
     * « Exploitation » ou « Desserte » de l'article Wikipédia de la ligne, ou lien posé vers
     * la ligne dans une section « Utilisation », « Services » ou « Carrière » de l'article de
     * la série. Chaque lien cite la phrase qui le porte et le permalien de la révision lue
     * (`evidence`), avec sa fiabilité (`confidence`). Les phrases au futur, au conditionnel
     * ou qui nient la circulation ne créent pas de lien.
     *
     * **Périodes de circulation** : `lines[].periods` donne, pour chaque ligne, les périodes
     * datées par une source (début et fin au format `AAAA-MM-JJ` avec leur précision `day`,
     * `month` ou `year`, fiabilité `high` ou `medium`, source citée par son permalien). Exemple
     * réel : les CC 72000 sur la ligne de Grenoble à Montmélian, « du 20/08/1982 au
     * 30/05/1986 » sur l'IC « Le Rhodanien » d'après l'article de la ligne. Une fin nulle ne
     * veut pas dire que la série y circule encore ; les détails sont décrits dans
     * `GET /api/v1/lines/{line}/materials`.
     */
    #[PathParameter('material', 'Slug de la série, tel que renvoyé par la liste (`slug`).', example: 'bb-15000')]
    #[ApiResponse(200, 'Fiche de la série.', examples: [self::SHOW_EXAMPLE])]
    #[ApiResponse(404, 'Série inconnue (slug introuvable).', examples: [MaterialExamples::MATERIAL_NOT_FOUND])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function show(Material $material): JsonResponse
    {
        $material->loadCount(['lineLinks as lines_count' => fn (Builder $q) => $q->select(\DB::raw('count(distinct line_id)'))]);
        $material->load(['lineLinks' => fn ($q) => $q->with(['line:id,slug,code,name', 'era:id,year', 'shownPeriods'])
            ->join('lines', 'lines.id', '=', 'line_materials.line_id')
            ->orderBy('lines.name')
            ->select('line_materials.*')]);

        return MaterialDetailResource::make($material)->response()->header('Cache-Control', 'public, max-age=600');
    }

    /** Exemple de la carte des lignes : BB 15000 (lecture de la base le 23/11/2026), réduit à deux lignes et à deux points de tracé chacune. */
    private const MAP_EXAMPLE = [
        'material' => ['slug' => 'bb-15000', 'name' => 'BB 15000', 'in_service_from' => 1971, 'in_service_to' => null],
        'lines' => [
            [
                'slug' => 'rfn-74000', 'code' => '074000', 'name' => "Ligne d'Épernay à Reims", 'confidence' => 'medium',
                'kind' => 'wikipedia_material_article', 'source' => 'wikipedia',
                'source_url' => 'https://fr.wikipedia.org/w/index.php?title=BB_15000&oldid=237800094',
                'article' => 'BB 15000', 'quote' => 'Paris Est - Reims - Charleville-Mézieres', 'periods' => [],
                'geometry' => ['type' => 'MultiLineString', 'coordinates' => [[[4.0204, 49.2561], [4.0186, 49.2548]]]],
                'geometry_withheld' => false,
            ],
            [
                'slug' => 'rfn-390000', 'code' => '390000', 'name' => 'Ligne de Lisieux à Trouville-Deauville', 'confidence' => 'medium',
                'kind' => 'wikipedia_line_article', 'source' => 'wikipedia',
                'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Lisieux_%C3%A0_Trouville_-_Deauville&oldid=235342270',
                'article' => 'Ligne de Lisieux à Trouville - Deauville',
                'quote' => "Les BB 16000 ont laissé place depuis 2007 à des BB 15000 en provenance du réseau Est, libérées par l'ouverture de la LGV Est européenne.",
                'periods' => [],
                'geometry' => ['type' => 'MultiLineString', 'coordinates' => [[[0.2315, 49.1384], [0.2362, 49.1351]]]],
                'geometry_withheld' => false,
            ],
        ],
    ];

    /**
     * Carte des lignes d'une série.
     *
     * Les lignes parcourues par la série, avec de quoi les dessiner et les dater : `lines[]` porte le
     * `geometry` GeoJSON (MultiLineString, WGS 84) de chaque ligne, **allégé** (simplifié à 40 m environ,
     * coordonnées à 4 décimales : pour situer la ligne, pas pour la mesurer ; pour le tracé précis, voir
     * `/lines/{line}/segments`), toutes ères confondues, ainsi que la fiabilité du lien (`confidence` :
     * `high` ou `medium`, `low` à confirmer), sa source (`source`, `source_url` : permalien de la révision qui
     * porte la phrase source, voir `evidence` dans la fiche de la série) et les périodes datées par une source
     * (`periods`, même forme que dans la fiche : début, fin, précisions, `source_url`), l'article cité et la phrase source (`article`, `quote`, extrait sous CC BY-SA 4.0 : citez l'article). Une ligne sans période
     * est attestée sans date : ne dites pas qu'elle était desservie à telle date.
     *
     * Règle des droits des tracés, comme `/lines/{line}/segments` : les tronçons d'une source qui n'autorise pas
     * la redistribution ne sont pas servis ; `geometry_withheld` vaut `true` quand une partie du tracé manque,
     * `geometry` est `null` quand tout manque. Réponse gardée en cache côté serveur et 10 minutes côté client.
     */
    #[PathParameter('material', 'Slug de la série, tel que renvoyé par la liste (`slug`).', example: 'bb-15000')]
    #[ApiResponse(200, 'Les lignes de la série avec leur tracé allégé, leur confiance, leur source et leurs périodes (ici la BB 15000, réduite à deux lignes et deux points de tracé chacune).', examples: [self::MAP_EXAMPLE])]
    #[ApiResponse(404, 'Série inconnue (slug introuvable).', examples: [MaterialExamples::MATERIAL_NOT_FOUND])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function map(Material $material, MaterialLinesMap $map): JsonResponse
    {
        return response()->json($map->for($material))->header('Cache-Control', 'public, max-age=600');
    }

    /**
     * Médias d'une série.
     *
     * Photos, plans et documents rattachés à la série, paginés et triés par
     * époque représentée, comme la galerie de la fiche `/materials/{slug}` :
     * mêmes champs, mêmes filtres et même réponse que `/lines/{line}/media`
     * (voir ce point d'accès pour le sens de chaque champ et des compteurs
     * `facets`). L'image principale de la fiche (`image` de la série) n'en
     * fait pas partie : elle vient de Wikidata.
     *
     * Ces images viennent des contributions validées : un fichier de
     * Wikimedia Commons (hébergé chez Wikimedia, `source_url` = sa page) ou un
     * fichier déposé (`provider` = `upload`, servi par VFF sous `/storage`).
     * `contribution_id` donne le numéro de la contribution. Une image proposée
     * et pas encore validée n'apparaît jamais. Affichez toujours
     * `attribution` (ou `author` et `license`) avec l'image.
     *
     * L'exemple montre la forme d'une réponse ; aucune image de série n'était
     * encore publiée quand il a été écrit.
     */
    #[PathParameter('material', 'Slug de la série, tel que renvoyé par la liste (`slug`).', example: 'bb-15000')]
    #[ApiResponse(200, 'Une page de médias, avec la pagination et les compteurs par filtre.', examples: [[
        'material' => ['slug' => 'bb-15000', 'name' => 'BB 15000'],
        'data' => [[
            'id' => 71501, 'provider' => 'upload', 'provider_label' => 'VFF', 'type' => 'photo',
            'title' => 'BB 15000 en gare', 'caption' => null,
            'thumb_url' => '/storage/media/contributions/12-4kq9z2m1x8c7b6v5.jpg',
            'display_url' => '/storage/media/contributions/12-4kq9z2m1x8c7b6v5.jpg',
            'source_url' => null,
            'width' => 2400, 'height' => 1600, 'depicted_year' => 2025, 'depicted_year_to' => null, 'date_precision' => 'month',
            'captured_at' => '2025-06-01', 'author' => 'A. Contributeur', 'attribution' => 'A. Contributeur, CC BY 4.0',
            'license' => 'CC BY 4.0', 'license_url' => 'https://creativecommons.org/licenses/by/4.0/', 'rights_status' => 'open_license',
            'commercial_ok' => true, 'share_alike' => false, 'attribution_required' => true, 'featured' => false, 'contribution_id' => 12, 'location' => null,
        ]],
        'meta' => ['current_page' => 1, 'per_page' => 24, 'total' => 1, 'last_page' => 1, 'sort' => 'oldest'],
        'facets' => [
            'types' => ['photo' => 1],
            'periods' => ['pre1900' => 0, '1900_1944' => 0, '1945_1969' => 0, '1970_1999' => 0, 'since2000' => 1, 'undated' => 0],
            'rights' => ['open_license' => 1],
            'providers' => ['upload' => 1],
        ],
    ]])]
    #[ApiResponse(422, 'Paramètre invalide (`per_page` hors de 1 à 60, `sort`, `type` ou `period` inconnu). Le corps donne `message` et, par paramètre, la liste des erreurs dans `errors`.')]
    #[ApiResponse(404, 'Série inconnue (slug introuvable).', examples: [MaterialExamples::MATERIAL_NOT_FOUND])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function media(Material $material, SheetMediaRequest $request, MediaGallery $gallery): JsonResponse
    {
        return response()->json([
            'material' => ['slug' => $material->slug, 'name' => $material->name],
            ...$gallery->page($material, $request->validated()),
        ])->header('Cache-Control', 'public, max-age=300');
    }

    /** Exemple de réponse de la liste, tiré de la base (vff:import-materials du 19/11/2026, curseur réel). */
    private const LIST_EXAMPLE = [
        'data' => [MaterialExamples::BB_15000],
        'links' => [
            'first' => null,
            'last' => null,
            'prev' => null,
            'next' => 'https://vff-project.org/api/v1/materials?type=locomotive_electrique&sort=-max_speed_kmh&q=BB%201&per_page=1&cursor=eyJzb3J0X251bGwiOjAsInNvcnRfa2V5IjoxNjAsInNvcnRfbmFtZSI6IkJCIDE1MDAwIiwiaWQiOjQ1LCJfcG9pbnRzVG9OZXh0SXRlbXMiOnRydWV9',
        ],
        'meta' => [
            'path' => 'https://vff-project.org/api/v1/materials', 'per_page' => 1,
            'next_cursor' => 'eyJzb3J0X251bGwiOjAsInNvcnRfa2V5IjoxNjAsInNvcnRfbmFtZSI6IkJCIDE1MDAwIiwiaWQiOjQ1LCJfcG9pbnRzVG9OZXh0SXRlbXMiOnRydWV9', 'prev_cursor' => null,
        ],
    ];

    /** Exemple de fiche, tiré de la base. */
    private const SHOW_EXAMPLE = [
        'data' => MaterialExamples::BB_15000 + [
            'field_sources' => MaterialExamples::BB_15000_FIELD_SOURCES,
            'lines' => [MaterialExamples::BB_15000_LINE],
        ],
    ];
}

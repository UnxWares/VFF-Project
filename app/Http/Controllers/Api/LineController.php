<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListLinesRequest;
use App\Http\Resources\LineSegmentResource;
use App\Http\Resources\LineSheetResource;
use App\Models\Era;
use App\Models\Line;
use App\Models\LineSegment;
use App\Services\Api\LineDirectoryQuery;
use App\Services\Api\SortedCursor;
use App\Services\LineSheet;
use App\Support\QueryBoolean;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Lignes', "L'annuaire des lignes et chaque ligne en détail : fiche complète, tronçons par ère, chronologie, gares dans l'ordre des points kilométriques, conversion de coordonnées en PK, signalisation, médias.", weight: 2)]
class LineController extends Controller
{
    /**
     * Annuaire des lignes.
     *
     * Toutes les lignes de l'encyclopédie, actuelles et disparues, avec les
     * filtres de la page [/lines](/lines), triées et paginées par curseur.
     *
     * **État et trafic** (`state`, `traffic`) se lisent à l'**ère de
     * référence** : l'ère courante (le réseau d'aujourd'hui) par défaut, ou
     * l'ère `era`. `state` : `open` (au moins une portion en service à l'ère),
     * `closed` (tracé connu à l'ère, plus aucune portion en service),
     * `unknown` (aucun tracé à cette ère : ligne pas encore construite ou
     * tracé manquant). `traffic` : trafic dominant, pondéré par la longueur
     * des portions en service (`passenger` voyageurs seuls, `mixed`
     * voyageurs et fret, `freight` fret seul, `unknown` non renseigné ;
     * `null` si rien n'est en service). `heritage` : au moins une portion
     * exploitée par une association (train touristique, vélorail) à l'ère.
     *
     * **Réseau** (`network`) : `principal` (grandes compagnies puis SNCF),
     * `secondaire` (intérêt local, tramways, souvent à voie étroite),
     * `militaire`, `particulier` (embranchements industriels), `null` non
     * renseigné.
     *
     * **Tri** : `sort=name` (par défaut, ordre alphabétique français : accents
     * et capitales sans effet), `code` (code SNCF Réseau ; lignes sans code
     * en dernier), `slug` ; `order=desc` inverse l'ordre. Pour trouver une
     * ligne par son nom avec un classement par pertinence, préférez
     * `/search`.
     *
     * **Pagination par curseur** : `per_page` (1 à 100) et `cursor`, comme
     * pour les événements et les médias. Suivez `links.next` jusqu'à `null`.
     *
     * **Compteurs** : `facets=true` ajoute `meta.facets`, les compteurs de la
     * page web pour la recherche en cours : `total`, `open`, `closed`,
     * `unknown`, nombre de lignes par réseau (`networks`, `none` = non
     * renseigné), par trafic (`traffics`, sur les portions en service à
     * l'ère) et avec une portion associative (`heritage`). Chaque facette est
     * comptée sans son propre filtre (filtrer `traffic=freight` laisse voir
     * les autres trafics).
     *
     * `meta.era_year` rappelle l'ère de référence. Données : registre des
     * lignes de SNCF Réseau (ODbL), Wikidata (CC0), relevés des lignes
     * disparues ; tracés de la carte VFF (voir `/sources`).
     */
    #[QueryParameter('q', 'Recherche littérale dans le nom, le code ou la compagnie d\'origine, casse ignorée, 100 caractères au plus.', type: 'string', example: 'Roscoff')]
    #[QueryParameter('network', 'Réseau : `principal`, `secondaire`, `militaire`, `particulier`, ou `none` (non renseigné). Une autre valeur donne HTTP 422.', type: 'string', example: 'principal')]
    #[QueryParameter('status', "État à l'ère de référence : `open` (au moins une portion en service) ou `closed` (tracé connu, plus rien en service).", type: 'string', example: 'closed')]
    #[QueryParameter('traffic', "Trafic d'au moins une portion en service à l'ère : `passenger`, `mixed`, `freight` ou `unknown`.", type: 'string', example: 'freight')]
    #[QueryParameter('heritage', 'Seulement les lignes avec une portion associative (train touristique, vélorail), toutes ères confondues : `true` ou `false`.', type: 'boolean', example: true)]
    #[QueryParameter('department', 'Code INSEE du département (`01` à `95`, `2A`, `2B`, `971` à `976`) : lignes citant ce département ou y desservant une gare. Minuscules acceptées.', type: 'string', example: '29')]
    #[QueryParameter('era', "Année de l'ère de référence (décennie de 1830 à 2030) pour `status`, `traffic` et l'état renvoyé. Par défaut : l'ère courante (aujourd'hui). Une année sans ère (`1935`) donne HTTP 422.", type: 'int', example: 1930)]
    #[QueryParameter('sort', 'Clé de tri : `name` (par défaut), `code` ou `slug`.', type: 'string', default: 'name', example: 'name')]
    #[QueryParameter('order', 'Sens du tri : `asc` (par défaut) ou `desc`.', type: 'string', default: 'asc', example: 'asc')]
    #[QueryParameter('facets', 'Ajouter les compteurs de la recherche dans `meta.facets` : `true` ou `false` (par défaut).', type: 'boolean', default: false, example: true)]
    #[QueryParameter('per_page', 'Nombre de lignes par page, de 1 à 100.', type: 'int', default: 25, example: 25)]
    #[QueryParameter('cursor', 'Curseur de la page voulue, tel que renvoyé par `meta.next_cursor` : une chaîne opaque, à ne pas fabriquer. Sans curseur : première page.', type: 'string', example: 'eyJzb3J0X251bGwiOjAsInNvcnRfa2V5IjoiTGlnbmUgZGUgTW9ybGFpeCDDoCBSb3Njb2ZmIiwiaWQiOjI1MiwiX3BvaW50c1RvTmV4dEl0ZW1zIjp0cnVlfQ')]
    #[ApiResponse(200, 'Une page de l\'annuaire (ici `department=29&status=closed&sort=code&per_page=2&facets=true`).', examples: [[
        'data' => [[
            'slug' => 'rfn-476000', 'code' => '476000', 'name' => 'Ligne de Rosporden à Concarneau', 'network' => 'principal',
            'historical_operator' => null, 'regions' => ['Bretagne'], 'departements' => ['Finistère'],
            'state' => 'closed', 'traffic' => null, 'heritage' => false,
            'links' => ['web' => 'https://vff-project.org/lines/rfn-476000', 'self' => 'https://vff-project.org/api/v1/lines/rfn-476000'],
        ], [
            'slug' => 'rfn-477000', 'code' => '477000', 'name' => "Ligne de Quimper à Pont-l'Abbé", 'network' => 'principal',
            'historical_operator' => null, 'regions' => ['Bretagne'], 'departements' => ['Finistère'],
            'state' => 'closed', 'traffic' => null, 'heritage' => false,
            'links' => ['web' => 'https://vff-project.org/lines/rfn-477000', 'self' => 'https://vff-project.org/api/v1/lines/rfn-477000'],
        ]],
        'links' => ['first' => null, 'last' => null, 'prev' => null, 'next' => 'https://vff-project.org/api/v1/lines?department=29&status=closed&sort=code&per_page=2&facets=true&cursor=eyJzb3J0X251bGwiOjAsInNvcnRfa2V5IjoiNDc3MDAwIiwiaWQiOjI2MiwiX3BvaW50c1RvTmV4dEl0ZW1zIjp0cnVlfQ'],
        'meta' => [
            'path' => 'https://vff-project.org/api/v1/lines', 'per_page' => 2,
            'next_cursor' => 'eyJzb3J0X251bGwiOjAsInNvcnRfa2V5IjoiNDc3MDAwIiwiaWQiOjI2MiwiX3BvaW50c1RvTmV4dEl0ZW1zIjp0cnVlfQ', 'prev_cursor' => null,
            'era_year' => 2030,
            'facets' => [
                'total' => 14, 'open' => 0, 'closed' => 14, 'unknown' => 0,
                'networks' => ['principal' => 2, 'secondaire' => 12, 'militaire' => 0, 'particulier' => 0, 'none' => 0],
                'traffics' => ['passenger' => 0, 'mixed' => 0, 'freight' => 0, 'unknown' => 0], 'heritage' => 0,
            ],
        ],
    ]])]
    #[ApiResponse(422, 'Filtre, tri ou curseur invalide : réseau, état ou trafic hors énumération, département ou ère inconnus, `per_page` hors de 1 à 100.', examples: [ErrorExamples::LINES_INVALID])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function index(ListLinesRequest $request, LineDirectoryQuery $query): JsonResponse
    {
        $filters = $request->filters();
        $page = $query->query($filters)->cursorPaginate($request->perPage())->withQueryString();
        $data = array_map(fn (array $l): array => $l + [
            'links' => ['web' => url('/lines/'.$l['slug']), 'self' => route('api.lines.show', $l['slug'])],
        ], $query->present($page->items(), $filters));

        return response()->json(SortedCursor::envelope($page, $data, array_filter([
            'era_year' => Era::whereKey($query->eraId($filters))->value('year'),
            'facets' => $request->wantsFacets() ? $query->facets($filters) : null,
        ], fn ($v) => $v !== null)))->header('Cache-Control', 'public, max-age=300');
    }

    /**
     * Fiche complète d'une ligne.
     *
     * Tout ce que montre la page [/lines/{slug}](/lines/rfn-610000), dans le
     * même calcul : identité, chiffres clés, électrification, signalisation,
     * phases datées, état décennie par décennie, chronique sourcée, gares,
     * sections nommées, exploitations associatives, matériels, sources.
     * Réponse lourde pour les grandes lignes (jusqu'à quelques secondes de
     * calcul) : mise en cache 5 minutes.
     *
     * **Identité** : `names` (autres noms, chacun avec `kind` : `name`,
     * `section`, `merged`, `wikipedia`… et sa source `source`, `url`, période
     * `from`/`to` en années), `wikidata` (élément Wikidata), `network`,
     * `is_lgv` (ligne à grande vitesse : signalisation en cabine),
     * `is_threatened` (ligne menacée de fermeture), `historical_operators`
     * (compagnies et gestionnaires successifs : `operator`, `role`
     * `exploitant` ou `gestionnaire`, `from_year`, `to_year`, `ongoing`,
     * `source`). `description` : extrait de l'article Wikipédia (en
     * français), sous CC BY-SA 4.0 ; `description_source` donne de quoi le
     * citer : `title` et `article_url` (article), `author` (« Contributeurs de
     * Wikipédia », auteur collectif) et `history_url` (liste des auteurs),
     * `revision_id` et `permalink` (version lue, `null` si elle n'est pas
     * connue ; `url` est le lien exact de la version lue), `fetched_at`
     * (date de lecture), `license` et `license_url`, `attribution` (mention
     * prête à copier). Pour une ligne décrite à la main, `description_source`
     * vaut `{"type": "curated", "urls": [...]}` (sources citées, sans licence
     * Wikipédia). Le message de soutien du site à certaines lignes n'est pas
     * une donnée : il n'est pas publié par l'API.
     *
     * **Aujourd'hui** (`today`) : ère affichée (`era_year` : l'ère courante si
     * la ligne y a un tracé, sinon sa dernière décennie), `state` (`open`,
     * `closed`, `gone` : déposée), `traffic` dominant (`passenger`, `mixed`,
     * `freight`, `heritage`, `unknown`).
     *
     * **Chiffres clés** (`key_figures`) : `length_km`, longueur du tracé VFF
     * à son plus grand développement, toutes portions comprises (en service,
     * fermées, déposées), la même que la page ; `length_definition` dit
     * d'où elle vient (`trace` tracé VFF, `sncf` formes SNCF, `segments`
     * tronçons) ; `active_length_km` : part en service aujourd'hui ;
     * `length_sncf_km` : longueur des formes SNCF (généralisées) ;
     * `length_official_m` : longueur publiée (Wikidata) ; vitesses en km/h,
     * écartement en mm, `tracks` nombre de voies ; nombres de gares (toutes
     * et ouvertes aujourd'hui), d'événements et de tronçons ;
     * `dismantled_from`/`dismantled_to` : premières et dernières déposes
     * connues (`AAAA-MM-JJ`).
     *
     * **Électrification** : `state` (`full`, `partial`, `marginal` : quelques
     * voies seulement, `none`), `km` et `pct` électrifiés, `voltages`
     * (`voltage_v`, `km`). **Signalisation** (SNCF Réseau Open Data, ODbL) :
     * km par mode de cantonnement (`block_systems_km` : `BAL`, `BAPR-VB`,
     * `CT-VU`…), par système de contrôle (`train_protection_km` : `KVB`,
     * `TVM 430`, `ETCS 2`…), `kvb_equipped`, et les tranches de PK
     * (`ranges`).
     *
     * **Faits** (`facts`) : chaque valeur de la fiche avec sa source
     * (`source` : `wikidata`, `sncf`, `vff`), sa portée (`scope` : `line`
     * ligne entière, ou `part` : élément Wikidata qui n'en décrit qu'une
     * partie, `item`) et, s'il y a lieu, sa date (`as_of`). `label` et `note`
     * sont en français.
     *
     * **Phases** (`phases`) : les changements datés de la ligne, tirés des
     * événements (`date` ISO et `precision` : `day`, `month`, `year`, ou
     * `range` : entre `from_year` et `to_year`, faute de date dans les
     * sources). `kind` : `growth` (ouverture), `decline` (fermeture, dépose),
     * `change` ; `changes` : portions passées de `from` à `to` (catégories
     * `passenger`, `mixed`, `freight`, `heritage`, `unknown`, `closed`,
     * `dismantled`, `none` pas encore construite), avec `length_m` et
     * `event_id` (voir `/events/{id}`) ; `state` : catégorie de chaque
     * portion après la phase (indices de `portions`) ; `length_by_category`
     * et `active_m` en mètres. `best_phase` : indice de la phase où la ligne
     * était la plus étendue. `phases_unexplained` : écarts entre la dernière
     * décennie et les sources d'aujourd'hui qu'aucun événement ne date.
     * `portions` : tronçons élémentaires de la ligne (`length_m`, et
     * `geometry` GeoJSON avec `geometry=true`).
     *
     * **Décennies** (`decades`, `era_summary`) : état de la ligne au 31/12 de
     * chaque décennie (km par catégorie, en mètres dans `decades`, en km dans
     * `era_summary`) et, dans `decades.stations`, gares ouvertes, incertaines,
     * fermées et fermetures de la décennie.
     *
     * **Chronique** (`chronicle`) : les événements regroupés par portion
     * (`groups`), avec `quote` (citation de la source), `status`
     * (`retained`, `context`, `superseded`, `conflict`…), accords et
     * désaccords entre sources (`agrees_with`, `conflicts_with`,
     * `conflicts`) et notes de source numérotées (`notes`). Textes en
     * français.
     *
     * **Gares** (`stations`) : toutes les gares de la ligne, toutes ères, dans
     * l'ordre du tracé : `pk_m`/`pk_label`, dates (`opened_at`, `closed_at` et
     * leur précision, `closed_after` : fermée après cette année),
     * `status_today` (`null` : disparue), `eras` : statut à chaque décennie.
     * **Sections nommées** (`sections`) : PK de début et de fin, années
     * d'ouverture, description, source. **Exploitations associatives**
     * (`heritage`) : nom, exploitant, nature (`tourist_train`, `velorail`),
     * portion et périodes. **Sources** (`sources`) : jeux de données utilisés
     * pour la fiche, avec lien, date de lecture et champs fournis. **Liens**
     * (`links`) : la page du site et les points d'accès de la ligne que la
     * fiche ne reprend pas en entier (`geojson` tracé, `profile` profil en
     * long, `level_crossings` passages à niveau, `accidents`, `materials`,
     * `signalling`, `stops`, `media`, `events`, `history`, `segments`).
     */
    #[PathParameter('line', 'Identifiant (slug) de la ligne, tel que renvoyé par l\'annuaire ou la recherche. Les lignes du réseau ferré national sont nommées `rfn-` suivi de leur code à 6 chiffres.', example: 'rfn-447000')]
    #[QueryParameter('geometry', 'Joindre le tracé GeoJSON de chaque portion (`portions.geometry`) : `true` ou `false` (par défaut).', type: 'boolean', default: false, example: false)]
    #[ApiResponse(200, 'Fiche de la ligne (extrait de la ligne de Morlaix à Roscoff ; listes raccourcies).', examples: [LineSheetExample::BODY])]
    #[ApiResponse(404, 'Ligne inconnue.', examples: [ErrorExamples::LINE_NOT_FOUND])]
    #[ApiResponse(422, '`geometry` n\'est pas un booléen.', examples: [ErrorExamples::GEOMETRY_INVALID])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function show(Request $request, Line $line, LineSheet $sheet): JsonResponse
    {
        $request->merge(['geometry' => QueryBoolean::cast($request->query('geometry'))])->validate(['geometry' => ['nullable', 'boolean']]);

        return LineSheetResource::make($sheet->build($line))->response()->header('Cache-Control', 'public, max-age=300');
    }

    /**
     * Tronçons d'une ligne à une ère.
     *
     * Les portions homogènes du tracé de la ligne au 31/12 de l'ère (l'ère
     * courante par défaut : aujourd'hui), dans l'ordre des points
     * kilométriques : même état, même trafic, mêmes caractéristiques. C'est
     * la matière de la carte. Pour toute une ère, voir les GeoJSON par ère.
     *
     * - `status` : `in_service` (en service), `single_track` (en service,
     *   voie unique), `service_track` (voie de service, sans trafic
     *   commercial), `construction` (en travaux), `abandoned` (fermée, voie en
     *   place), `dismantled` (voie déposée), `razed` (emprise effacée) ;
     * - `traffic_type` : `passenger`, `mixed`, `freight`, `unknown` ;
     *   `is_heritage` et `heritage_kind` (`tourist_train`, `velorail`,
     *   `museum`) pour une exploitation associative ;
     * - `electrified`, `voltage_v` (1500, 25000, 750, 3000…), `electrified_year`,
     *   `electrification_source` ; `max_speed_kmh` et `max_speed_source` ;
     *   `gauge_mm` (1435 voie normale, 1000 voie métrique, 600…), `gauge_class` ;
     *   `signalling`, `train_protection` ; `operator` ;
     * - `pk_start_m`, `pk_end_m`, `length_m` en mètres (`null` si inconnus) ;
     * - `dismantled_at` (`AAAA-MM-JJ`) ; `reuse` après dépose : `greenway`
     *   (voie verte), `road`, `path`, `urban`, `other` ;
     * - `source` du tracé (`osm`, `bdtopo` IGN, `ohm`, `sncf`,
     *   `archeoferroviaire`, `contrib`…) et `provenance` : fournisseur et
     *   licence (voir `/sources`) ;
     * - `geometry` (avec `geometry=true`) : LineString GeoJSON, WGS 84,
     *   coordonnées longitude, latitude à 6 décimales.
     */
    #[PathParameter('line', 'Identifiant (slug) de la ligne.', example: 'rfn-447000')]
    #[QueryParameter('era', "Année de l'ère (décennie de 1830 à 2030). Par défaut : l'ère courante (aujourd'hui). Une année sans ère (`1935`) donne HTTP 404, comme pour `/stops` et `/signalling`.", type: 'int', example: 1930)]
    #[QueryParameter('geometry', 'Joindre le tracé GeoJSON de chaque tronçon : `true` ou `false` (par défaut).', type: 'boolean', default: false, example: true)]
    #[ApiResponse(200, 'Tronçons de la ligne de Morlaix à Roscoff en 1930 (extrait).', examples: [[
        'line' => ['slug' => 'rfn-447000', 'code' => '447000', 'name' => 'Ligne de Morlaix à Roscoff'],
        'era_year' => 1930,
        'data' => [[
            'id' => 8573551, 'pk_start_m' => 1093, 'pk_end_m' => 1550, 'length_m' => 457, 'status' => 'in_service',
            'traffic_type' => 'mixed', 'is_heritage' => false, 'heritage_kind' => null, 'electrified' => false, 'voltage_v' => null,
            'electrified_year' => null, 'electrification_source' => null, 'max_speed_kmh' => null, 'max_speed_source' => null,
            'gauge_mm' => 1435, 'gauge_class' => null, 'signalling' => null, 'train_protection' => null, 'operator' => null,
            'dismantled_at' => null, 'reuse' => null, 'source' => 'bdtopo',
            'provenance' => ['provider' => 'ign', 'name' => 'IGN (BD TOPO, BD ORTHO historique)', 'license' => "Licence Ouverte 2.0 (Etalab), données publiques de l'IGN libres depuis le 1er janvier 2021", 'license_status' => 'verified'],
            'geometry' => ['type' => 'LineString', 'coordinates' => [[-3.837041, 48.578218], [-3.83613, 48.581991]]],
        ]],
    ]])]
    #[ApiResponse(404, 'Ligne ou ère inconnue.', examples: [ErrorExamples::LINE_NOT_FOUND])]
    #[ApiResponse(422, '`era` n\'est pas un entier ou `geometry` n\'est pas un booléen.', examples: [ErrorExamples::GEOMETRY_INVALID])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function segments(Request $request, Line $line): JsonResponse
    {
        $input = $request->merge(['geometry' => QueryBoolean::cast($request->query('geometry'))])->validate([
            'era' => ['nullable', 'integer'],
            'geometry' => ['nullable', 'boolean'],
        ]);
        $era = $this->era($input['era'] ?? null);

        return response()->json([
            'line' => ['slug' => $line->slug, 'code' => $line->code, 'name' => $line->name],
            'era_year' => $era->year,
            'data' => LineSegmentResource::collection($this->eraSegments($line, $era, (bool) ($input['geometry'] ?? false)))->resolve($request),
        ])->header('Cache-Control', 'public, max-age=300');
    }

    /**
     * Tracé d'une ligne à une ère, en GeoJSON.
     *
     * Le tracé de la ligne au 31/12 de l'ère (l'ère courante par défaut :
     * aujourd'hui), prêt pour un SIG, Leaflet, MapLibre ou `geojson.io` : une
     * `FeatureCollection` (RFC 7946, WGS 84, longitude puis latitude à 6
     * décimales) d'un `Feature` par tronçon, dans l'ordre des points
     * kilométriques. La réponse est servie en `application/geo+json`.
     *
     * Les `properties` de chaque `Feature` sont celles de `/lines/{line}/segments`
     * (état, trafic, électrification, vitesse, écartement, signalisation, PK,
     * longueur, `source` et `provenance` avec la licence du tracé) ; `id` est
     * l'identifiant du tronçon. La collection porte aussi `line` (`slug`,
     * `code`, `name`) et `era_year`. Une ère sans tracé pour cette ligne donne
     * une collection vide, pas une erreur.
     *
     * Les tracés viennent d'OpenStreetMap, de SNCF Réseau (ODbL), de l'IGN
     * (Licence Ouverte 2.0), d'OpenHistoricalMap (CC0) ou d'archeoferroviaire
     * (usage non commercial) : lisez `provenance.license_status` de chaque
     * tronçon avant de réutiliser.
     */
    #[PathParameter('line', 'Identifiant (slug) de la ligne.', example: 'rfn-447000')]
    #[QueryParameter('era', "Année de l'ère (décennie de 1830 à 2030). Par défaut : l'ère courante (aujourd'hui). Une année sans ère (`1935`) donne HTTP 404.", type: 'int', example: 1930)]
    #[ApiResponse(200, 'Tracé de la ligne de Morlaix à Roscoff en 1930 (un tronçon).', examples: [[
        'type' => 'FeatureCollection',
        'line' => ['slug' => 'rfn-447000', 'code' => '447000', 'name' => 'Ligne de Morlaix à Roscoff'],
        'era_year' => 1930,
        'features' => [[
            'type' => 'Feature',
            'id' => 8573551,
            'geometry' => ['type' => 'LineString', 'coordinates' => [[-3.837041, 48.578218], [-3.83613, 48.581991]]],
            'properties' => [
                'pk_start_m' => 1093, 'pk_end_m' => 1550, 'length_m' => 457, 'status' => 'in_service', 'traffic_type' => 'mixed',
                'is_heritage' => false, 'electrified' => false, 'gauge_mm' => 1435, 'source' => 'bdtopo',
                'provenance' => ['provider' => 'ign', 'name' => 'IGN (BD TOPO, BD ORTHO historique)', 'license' => "Licence Ouverte 2.0 (Etalab), données publiques de l'IGN libres depuis le 1er janvier 2021", 'license_status' => 'verified'],
            ],
        ]],
    ]])]
    #[ApiResponse(404, 'Ligne ou ère inconnue.', examples: [ErrorExamples::LINE_NOT_FOUND])]
    #[ApiResponse(422, '`era` n\'est pas un entier.', examples: [ErrorExamples::ERA_INVALID])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function geojson(Request $request, Line $line): JsonResponse
    {
        $input = $request->validate(['era' => ['nullable', 'integer']]);
        $era = $this->era($input['era'] ?? null);

        $features = LineSegmentResource::collection($this->eraSegments($line, $era, true))->resolve($request);

        return response()->json([
            'type' => 'FeatureCollection',
            'line' => ['slug' => $line->slug, 'code' => $line->code, 'name' => $line->name],
            'era_year' => $era->year,
            'features' => array_map(function (array $segment): array {
                $geometry = $segment['geometry'] ?? null;
                $id = $segment['id'];
                unset($segment['id'], $segment['geometry']);

                return ['type' => 'Feature', 'id' => $id, 'geometry' => $geometry, 'properties' => $segment];
            }, $features),
        ], 200, ['Content-Type' => 'application/geo+json', 'Cache-Control' => 'public, max-age=300']);
    }

    /** Ère demandée (404 si ce n'est pas une décennie de la carte) ; à défaut l'ère courante. */
    private function era(?int $year): Era
    {
        return $year !== null
            ? Era::where('year', $year)->firstOrFail()
            : Era::where('is_current', true)->first() ?? Era::orderByDesc('year')->firstOrFail();
    }

    /**
     * Tronçons d'une ligne à une ère, dans l'ordre des PK.
     *
     * @return Collection<int, LineSegment>
     */
    private function eraSegments(Line $line, Era $era, bool $withGeometry): Collection
    {
        return $line->segments()->where('era_id', $era->id)
            ->select(['id', 'pk_start_m', 'pk_end_m', 'length_meters', 'status', 'traffic_type', 'is_heritage', 'heritage_kind',
                'electrified', 'voltage_v', 'electrified_year', 'electrification_source', 'max_speed_kmh', 'max_speed_source',
                'gauge_mm', 'gauge_class', 'signalling', 'train_protection', 'operator', 'dismantled_at', 'reuse', 'source'])
            ->when($withGeometry, fn ($q) => $q->selectRaw('ST_AsGeoJSON(geom, 6) AS geojson'))
            ->orderByRaw('pk_start_m NULLS LAST, id')
            ->get();
    }
}

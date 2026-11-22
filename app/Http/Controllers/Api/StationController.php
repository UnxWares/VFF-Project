<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListStationsRequest;
use App\Http\Requests\Api\SheetMediaRequest;
use App\Http\Resources\StationSheetResource;
use App\Models\Station;
use App\Services\Api\MediaGallery;
use App\Services\Api\SortedCursor;
use App\Services\Api\StationDirectoryQuery;
use App\Services\StationSheet;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;

#[Group('Gares', "L'annuaire des gares, haltes et points d'arrêt, actuels et disparus, et la fiche complète de chaque gare : dates, présence par décennie, lignes desservies, sources.", weight: 2)]
class StationController extends Controller
{
    /**
     * Annuaire des gares.
     *
     * Les gares, haltes et points d'arrêt de l'encyclopédie, actuels et
     * disparus, avec les filtres de la page [/stations](/stations), triés et
     * paginés par curseur. Les points techniques (postes, garages, points
     * d'une LGV) et les fiches retirées du réseau ferré par une correction
     * sourcée (tramway, petit train d'un parc) n'y figurent pas.
     *
     * **Statut** (`status`) : sans `era`, le statut affiché aujourd'hui :
     * `active` (desservie par des trains de voyageurs), `tourist` (train
     * touristique), `no_commercial` (plus de voyageurs : fret ou service),
     * `declassified` (fermée), `gone` (disparue selon ses sources),
     * `planned` (projetée, jamais ouverte). Avec `era`, le statut **à cette
     * ère** (`active`, `tourist`, `no_commercial`, `declassified`), et seules
     * les gares qui existaient à cette ère sont listées.
     *
     * **Dates** : `opened_at` et `closed_at` au format `AAAA-MM-JJ`, à lire
     * avec `opened_precision` / `closed_precision` (`day`, `month`, `year`, ou
     * `range` pour une fermeture connue seulement par un intervalle : `closed_at`
     * n'en est alors que la borne haute, jamais un jour sûr) ; `closed_after` :
     * gare fermée après cette année, date exacte inconnue.
     * **Nature** (`type`) : `gare`, `halte`, `point_arret`, `passage_niveau`,
     * `embranchement`. `lines` : lignes rattachées à la gare (10 au plus,
     * codes SNCF Réseau d'abord ; `lines_more` compte les autres).
     * `position_flagged` : position à vérifier (sources éloignées les unes
     * des autres) ; `sources_count` : nombre de sources rattachées.
     *
     * **Tri** : `sort=name` (par défaut, alphabétique français), `city`
     * (commune), `opened` (date d'ouverture), `closed` (date de fermeture) ;
     * les gares sans valeur pour la clé de tri viennent toujours en dernier ;
     * `order=desc` inverse l'ordre. **Pagination par curseur** : `per_page`
     * (1 à 100) et `cursor`. **Compteurs** : `facets=true` ajoute
     * `meta.facets`, le nombre de gares par statut pour la recherche en
     * cours (sans le filtre `status`), et `meta.department_facets`, le
     * nombre de gares par département (code INSEE, sans le filtre
     * `department` ; départements sans gare absents).
     *
     * **Territoire** : `department` (code INSEE du département) ou `region`
     * (code INSEE de la région). Les valeurs acceptées, avec les noms, sont
     * listées par `/departments` et `/regions`.
     *
     * Données : SNCF Open Data (ODbL), OpenStreetMap (ODbL), Wikidata (CC0),
     * OpenHistoricalMap (CC0), relevés des lignes disparues (voir `/sources`).
     */
    #[QueryParameter('q', 'Recherche littérale dans le nom, la commune ou les autres noms, ou début du code UIC, casse ignorée, 100 caractères au plus.', type: 'string', example: 'Morlaix')]
    #[QueryParameter('status', 'Statut : `active`, `tourist`, `no_commercial`, `declassified`, `gone` ou `planned` (avec `era` : les quatre premiers). Une autre valeur donne HTTP 422.', type: 'string', example: 'declassified')]
    #[QueryParameter('department', 'Code INSEE du département de la gare (`01` à `95`, `2A`, `2B`, `971` à `976`), minuscules acceptées.', type: 'string', example: '29')]
    #[QueryParameter('region', 'Code INSEE de la région (`11`, `24`, `27`, `28`, `32`, `44`, `52`, `53`, `75`, `76`, `84`, `93`, `94`, outre-mer `01` à `06`) : gares de ses départements. Liste avec les noms : `/regions`.', type: 'string', example: '53')]
    #[QueryParameter('era', "Année d'une ère (décennie de 1830 à 2030) : statut à cette ère, gares absentes de l'ère exclues. Une année sans ère donne HTTP 422.", type: 'int', example: 1930)]
    #[QueryParameter('flagged', 'Seulement les gares dont la position est à vérifier : `true` ou `false`.', type: 'boolean', example: false)]
    #[QueryParameter('sort', 'Clé de tri : `name` (par défaut), `city`, `opened` ou `closed`.', type: 'string', default: 'name', example: 'opened')]
    #[QueryParameter('order', 'Sens du tri : `asc` (par défaut) ou `desc`.', type: 'string', default: 'asc', example: 'asc')]
    #[QueryParameter('facets', 'Ajouter le nombre de gares par statut (`meta.facets`) et par département (`meta.department_facets`) : `true` ou `false` (par défaut).', type: 'boolean', default: false, example: true)]
    #[QueryParameter('per_page', 'Nombre de gares par page, de 1 à 100.', type: 'int', default: 25, example: 25)]
    #[QueryParameter('cursor', 'Curseur de la page voulue, tel que renvoyé par `meta.next_cursor` : une chaîne opaque, à ne pas fabriquer.', type: 'string', example: 'eyJzb3J0X251bGwiOjAsInNvcnRfa2V5IjoiQnJlc3QiLCJpZCI6MTYzODIsIl9wb2ludHNUb05leHRJdGVtcyI6dHJ1ZX0')]
    #[ApiResponse(200, 'Une page de l\'annuaire (ici `department=29&era=1930&sort=opened&order=desc&per_page=1&facets=true`).', examples: [[
        'data' => [[
            'slug' => 'camaret-sur-mer', 'name' => 'Camaret-sur-Mer', 'type' => 'gare', 'status' => 'active', 'city' => 'Camaret-sur-Mer',
            'department_code' => '29', 'uic_code' => null, 'wikidata_id' => null,
            'opened_at' => '1923-08-12', 'opened_precision' => 'day', 'closed_at' => '1967-04-10', 'closed_precision' => 'day', 'closed_after' => null,
            'lat' => 48.2732755, 'lng' => -4.5948215, 'position_flagged' => false, 'sources_count' => 1,
            'lines' => [['slug' => 'archeo-ligne-de-chateaulin-a-camaret', 'code' => null, 'name' => 'Ligne de Châteaulin à Camaret']], 'lines_more' => 0,
            'links' => ['web' => 'https://vff-project.org/stations/camaret-sur-mer', 'self' => 'https://vff-project.org/api/v1/stations/camaret-sur-mer'],
        ]],
        'links' => ['first' => null, 'last' => null, 'prev' => null, 'next' => 'https://vff-project.org/api/v1/stations?department=29&era=1930&sort=opened&order=desc&per_page=1&facets=true&cursor=eyJzb3J0X251bGwiOjAsInNvcnRfa2V5IjoiMTkyMy0wOC0xMiIsImlkIjo0MjAxMywiX3BvaW50c1RvTmV4dEl0ZW1zIjp0cnVlfQ'],
        'meta' => ['path' => 'https://vff-project.org/api/v1/stations', 'per_page' => 1, 'next_cursor' => 'eyJzb3J0X251bGwiOjAsInNvcnRfa2V5IjoiMTkyMy0wOC0xMiIsImlkIjo0MjAxMywiX3BvaW50c1RvTmV4dEl0ZW1zIjp0cnVlfQ', 'prev_cursor' => null, 'era_year' => 1930, 'facets' => ['active' => 65], 'department_facets' => ['22' => 77, '29' => 65]],
    ]])]
    #[ApiResponse(422, 'Filtre, tri ou curseur invalide.', examples: [ErrorExamples::STATIONS_INVALID])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function index(ListStationsRequest $request, StationDirectoryQuery $query): JsonResponse
    {
        $filters = $request->filters();
        $page = $query->query($filters)->cursorPaginate($request->perPage())->withQueryString();
        $data = array_map(fn (array $s): array => $s + [
            'links' => ['web' => url('/stations/'.$s['slug']), 'self' => route('api.stations.show', $s['slug'])],
        ], $query->present($page->items()));

        return response()->json(SortedCursor::envelope($page, $data, array_filter([
            'era_year' => $query->era($filters)?->year,
            'facets' => $request->wantsFacets() ? $query->facets($filters) : null,
            'department_facets' => $request->wantsFacets() ? (object) $query->departmentFacets($filters) : null,
        ], fn ($v) => $v !== null)))->header('Cache-Control', 'public, max-age=300');
    }

    /**
     * Fiche complète d'une gare.
     *
     * Tout ce que montre la page [/stations/{slug}](/stations/brest) :
     *
     * - identité : `name`, `alt_names`, `type`, `status` (statut affiché, voir
     *   l'annuaire ; `technical` point technique, `excluded` fiche retirée du
     *   réseau ferré), `source_status` (statut selon les sources),
     *   `lifecycle`, `city`, `department_code`, `uic_code`, `wikidata_id`,
     *   `description` (en français) ;
     * - dates : `opened_at`, `closed_at` (`AAAA-MM-JJ`) et leur précision
     *   (`day`, `month`, `year`, ou `range` : fermée entre `closed_after` + 1 et
     *   l'année de `closed_at`, borne haute et non jour sûr), `closed_after`
     *   (fermée après cette année) ;
     * - position : `lat`, `lng` (WGS 84) ; `position.source` (source retenue),
     *   `position.spread_m` (écart en mètres entre les sources),
     *   `position.flagged` (à vérifier) ; `attribute_sources` : pour chaque
     *   attribut (`name`, `opened_at`, `geom`…), la source retenue (clé de
     *   `sources[].source`), et `geom_recalage` (détail du recalage de la
     *   position : point d'origine, déplacement en mètres, mode) ;
     * - `eras` : statut à chaque décennie (`null` : la gare n'existe pas
     *   encore ou plus ; `uncertain` : ouverte selon ses sources, non
     *   confirmée par les voies de l'ère), avec une note en français ;
     * - `events` : chronologie (`type`, `date`, `precision`, `source`,
     *   `source_url`, `inferred` : date déduite, `note`, `provenance`) ; la
     *   liste paginée et filtrable est sur `/stations/{station}/events` ;
     * - `lines` : lignes desservies, avec `pk_m` (point kilométrique en
     *   mètres) et sa source, `method` (comment la gare est rattachée),
     *   `first_year`/`last_year` (années de desserte), `last_year_kind`
     *   (nature de `last_year` : `year` = année tirée d'une date ;
     *   `upper_bound` = borne haute, desservie au plus tard cette année, la
     *   gare n'ayant qu'une fermeture en intervalle, ex. Frenelle-la-Grande -
     *   Puzieux sur 040000, `last_year` 1989 pour une fermeture « entre 1980
     *   et 1989 » ; `undated` = fin non datée, la gare est fermée mais rien
     *   ne date sa fermeture et `last_year` n'est que l'année du calcul, ex.
     *   Jarménil sur 062000, `last_year` 2026 ; `null` sans fin),
     *   `years_source` (d'où viennent les années : `line_events` =
     *   événements datés de la ligne au PK de la gare, `traces` = tracés
     *   datés, `station` = dates de la gare elle-même faute d'événement daté
     *   de la ligne, ex. Morlaix sur 447000 ; `never_served` ; `null` :
     *   inconnues), `service_start` et `service_end` (dates de début et
     *   de fin de desserte, détaillées plus bas), `never_served`
     *   (`line_closed`, `station_closed` ou `null`), `active_current`
     *   (desservie par une portion en service aujourd'hui), `era_years`
     *   (décennies où la gare est un arrêt de la ligne) ;
     * - `lines[].service_start` / `lines[].service_end` : date de l'événement
     *   de ligne qui ouvre (ferme) la desserte de la gare, quand elle est
     *   sûre. `date` (`AAAA-MM-JJ`) se lit avec `precision` : `day`, `month`
     *   (le jour vaut alors 01 et n'a pas de sens) ou `year` (seule l'année
     *   compte). `event_id` est l'événement de ligne qui la donne, à lire
     *   sur `/events/{id}` (`links.event`). Ces objets sont `null` quand
     *   seules les années sont sûres : année tirée d'un tracé daté ou des
     *   dates de la gare, gare ouverte ou fermée la même année à une date qui
     *   pourrait précéder (suivre) celle de la ligne, fin non datée ou borne
     *   haute (`last_year_kind` différent de `year`), ligne qui n'a jamais
     *   desservi la gare. Exemple réel : Saint-Calais sur la ligne de
     *   Bessé-sur-Braye à Saint-Calais (`lo-507000`), `service_start` =
     *   `{"date": "1879-03-31", "precision": "day", "event_id": 416}`,
     *   `service_end` = `{"date": "1938-05-15", "precision": "day",
     *   "event_id": 417}` (fermeture aux voyageurs) ;
     * - `sources` : provenance brute, en lecture seule, de chaque source
     *   rattachée. Identité : `source`, `ref` (identifiant dans la source),
     *   `name`, `url` (lien vers l'objet), `lifecycle` (état selon la source),
     *   `opened_on`, `closed_on`, `passenger`, `freight`. Récupération :
     *   `fetched_at` (date de lecture par VFF) et `raw` (attributs bruts reçus,
     *   dont la forme dépend de la source : étiquettes OSM, champs SNCF,
     *   éléments Wikidata, calage). Comme les tracés, ces attributs ne sont
     *   republiés que pour une source sous licence ouverte vérifiée
     *   (`provenance.license_status` = `verified`) : pour une autre source,
     *   `raw` vaut `null` et `raw_withheld` vaut `true`. Fiabilité : `match_method` (comment la source
     *   a été rapprochée de la gare : `seed`, `uic`, `wikidata`, `osm_ref`,
     *   `name_near`, `near`), `distance_m` (écart à la position retenue),
     *   `geocoding` et `precision_m` (comment et à quelle précision la source
     *   a été placée quand elle ne donne pas de coordonnées exactes), `pk_m`
     *   (point kilométrique de la source) et `provenance.license_status`
     *   (`verified`, `uncertain`) ; `provenance` donne aussi le fournisseur
     *   et la licence ;
     * - `neighbors` : gares à moins de 10 km (12 au plus), de la plus proche
     *   à la plus lointaine ;
     * - `platforms` : quais de la gare d'après le jeu « Liste des quais » de
     *   SNCF Réseau (ODbL, état actuel du réseau, jeu mis à jour le
     *   `dataset_modified`) : `count` (quais distincts ; un quai central qui
     *   borde deux voies compte une fois), `longest_m` (plus long quai, en
     *   mètres), `total_length_m` (longueurs additionnées), `items` (chaque
     *   quai : `label` SNCF tel quel, `code_ligne` de la voie bordée,
     *   `length_m`, `pk_start_m` et `pk_end_m` en mètres, nuls si le jeu
     *   les écrit autrement), `attribution` (mention à reproduire) et
     *   `provenance`. Le jeu n'a pas de code UIC : la gare est retrouvée par
     *   son nom et sa ligne dans le référentiel des gares SNCF, puis par son
     *   code UIC (à défaut, même nom à 1,5 km au plus). `null` quand le jeu ne
     *   donne aucun quai pour la gare, ce qui ne veut pas dire qu'elle n'en a
     *   pas (gare fermée, halte absente du jeu) ;
     * - `links` : page web (`web`) et points d'accès liés : chronologie
     *   paginée (`events`), médias (`media`), accidents survenus près de la
     *   gare (`accidents` : ceux dont elle est la gare la plus proche du
     *   lieu), département de la gare (`department`). Chaque ligne de `lines`
     *   et chaque gare de `neighbors` porte aussi `links` (`web`, `api`).
     */
    #[PathParameter('station', 'Identifiant (slug) de la gare, tel que renvoyé par l\'annuaire ou la recherche.', example: 'brest')]
    #[ApiResponse(200, 'Fiche de la gare de Brest (listes raccourcies).', examples: [StationSheetExample::BODY])]
    #[ApiResponse(404, 'Gare inconnue.', examples: [ErrorExamples::STATION_NOT_FOUND])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function show(Station $station, StationSheet $sheet): JsonResponse
    {
        return StationSheetResource::make($sheet->page($station, withRaw: true))->response()->header('Cache-Control', 'public, max-age=300');
    }

    /**
     * Médias d'une gare.
     *
     * Photos, cartes postales, plans et documents rattachés à la gare, paginés
     * et triés par époque représentée, comme la galerie de la fiche
     * `/stations/{slug}` : mêmes champs, mêmes filtres et même réponse que
     * `/lines/{line}/media` (voir ce point d'accès pour le sens de chaque
     * champ et des compteurs `facets`).
     *
     * Ces images viennent des contributions validées : un fichier de
     * Wikimedia Commons (hébergé chez Wikimedia, `source_url` = sa page) ou un
     * fichier déposé (`provider` = `upload`, servi par VFF sous `/storage`).
     * `contribution_id` donne le numéro de la contribution. Une image proposée
     * et pas encore validée n'apparaît jamais. Affichez toujours
     * `attribution` (ou `author` et `license`) avec l'image.
     *
     * L'exemple montre la forme d'une réponse ; aucune image de gare n'était
     * encore publiée quand il a été écrit.
     */
    #[PathParameter('station', 'Identifiant (slug) de la gare, tel que renvoyé par l\'annuaire ou la recherche.', example: 'brest')]
    #[ApiResponse(200, 'Une page de médias, avec la pagination et les compteurs par filtre.', examples: [[
        'station' => ['slug' => 'brest', 'name' => 'Brest'],
        'data' => [[
            'id' => 71501, 'provider' => 'upload', 'provider_label' => 'VFF', 'type' => 'photo',
            'title' => 'Bâtiment voyageurs, côté cour', 'caption' => null,
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
    #[ApiResponse(404, 'Gare inconnue.', examples: [ErrorExamples::STATION_NOT_FOUND])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function media(Station $station, SheetMediaRequest $request, MediaGallery $gallery): JsonResponse
    {
        return response()->json([
            'station' => ['slug' => $station->slug, 'name' => $station->name],
            ...$gallery->page($station, $request->validated()),
        ])->header('Cache-Control', 'public, max-age=300');
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListLinesRequest;
use App\Http\Resources\LineSegmentResource;
use App\Http\Resources\LineSheetResource;
use App\Mcp\Support\Attribution;
use App\Models\Era;
use App\Models\Line;
use App\Services\Api\LineDirectoryQuery;
use App\Services\Api\SortedCursor;
use App\Services\LineSheet;
use App\Services\Signalling\LineSignallingHistory;
use App\Services\TraceExport\LineTraceExport;
use App\Support\QueryBoolean;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
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
     * les autres trafics). `departments` donne le nombre de lignes par
     * département (code INSEE, sans le filtre `department` ; une ligne compte
     * dans chacun de ses départements, départements sans ligne absents).
     *
     * **Territoire** : `department` ou `region` (codes INSEE). Les valeurs
     * acceptées, avec les noms et les compteurs, sont listées par
     * `/departments` et `/regions`.
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
    #[QueryParameter('region', 'Code INSEE de la région (`11`, `24`, `27`, `28`, `32`, `44`, `52`, `53`, `75`, `76`, `84`, `93`, `94`, outre-mer `01` à `06`) : lignes citant l\'un de ses départements ou y desservant une gare. Liste avec les noms : `/regions`.', type: 'string', example: '53')]
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
                'total' => 15, 'open' => 0, 'closed' => 15, 'unknown' => 0,
                'networks' => ['principal' => 2, 'secondaire' => 13, 'militaire' => 0, 'particulier' => 0, 'none' => 0],
                'traffics' => ['passenger' => 0, 'mixed' => 0, 'freight' => 0, 'unknown' => 0], 'heritage' => 0,
                // Raccourci : 94 départements dans la réponse.
                'departments' => ['22' => 13, '29' => 15, '35' => 9, '56' => 10],
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
     * `geometry` GeoJSON avec `geometry=true`). Droits des tracés : la
     * géométrie d'une portion n'est jointe que si toutes ses sources
     * autorisent la redistribution ; sinon `geometry` vaut `null`,
     * `geometry_withheld` vaut `true` et `geometry_withheld_reason` donne la
     * raison (`licence_not_open` : source dont la licence n'autorise pas la
     * redistribution ; `unknown_source` : source absente du registre).
     *
     * **Décennies** (`decades`, `era_summary`) : état de la ligne au 31/12 de
     * chaque décennie (km par catégorie, en mètres dans `decades`, en km dans
     * `era_summary`) et, dans `decades.stations`, gares ouvertes, incertaines,
     * fermées et fermetures de la décennie (`closures` : chaque gare comptée
     * fermée à cette décennie et pas à la précédente, avec la date de sa fiche
     * gare ; une gare dont la frise finit par une disparition, ouverte puis
     * absente, après sa dernière fermeture porte sa date retenue à la décennie
     * de la disparition, ex. Vigny en 2030 pour le 17/10/2023 ; sans date retenue, l'intervalle de la frise en précision `range`
     * entre `closed_after` et `closed_at` ; `reopened` : rouverte ensuite).
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
     * `served_from`/`served_until` (années de desserte par cette ligne),
     * `served_until_kind` (`year` : année tirée d'une date ; `upper_bound` :
     * desservie au plus tard cette année, fermeture de la gare connue
     * seulement par un intervalle, ex. Frenelle-la-Grande - Puzieux sur
     * 040000, 1989 ; `undated` : fin non datée, l'année n'est que celle du
     * calcul, ex. Jarménil sur 062000, 2026 ; `null` sans fin), `years_source`
     * (`line_events`, `traces`, ou `station` : années tirées des dates de la
     * gare faute d'événement daté de la ligne, ex. Morlaix sur 447000),
     * `status_today` (`null` : disparue), `eras` : statut à chaque décennie.
     * **Sections nommées** (`sections`) : PK de début et de fin, années
     * d'ouverture, description, source, et `stages` : les mises en service
     * datées de ses années d'ouverture (`phase` : indice dans `phases`,
     * `date` et `precision`, `length_m` en mètres, `event_ids` : événements
     * qui la datent, voir `/events/{id}`). Une phase dont l'année tombe dans
     * les années de deux sections n'est rattachée à aucune. **Exploitations associatives**
     * (`heritage`) : nom, exploitant, nature (`tourist_train`, `velorail`),
     * portion et périodes. **Matériel roulant** (`materials`) : séries attestées sur la ligne,
     * par nom, chacune avec `builders`, la liste complète de ses constructeurs (`builder` les joint
     * et se coupe à 255 caractères), et `periods`, ses périodes de circulation datées par une source (début
     * et fin `AAAA-MM-JJ` et leur précision, portion, `source` et `source_url` ; sûres seulement,
     * détail et fiabilité dans `materials` des liens). **Sources** (`sources`) : jeux de données utilisés
     * pour la fiche, avec lien, date de lecture et champs fournis. **Liens**
     * (`links`) : la page du site et les points d'accès de la ligne que la
     * fiche ne reprend pas en entier (`geojson` tracé, `profile` profil en
     * long, `level_crossings` passages à niveau, `greenways` voies vertes sur
     * l'ancienne emprise, `accidents`, `materials`,
     * `signalling`, `signalling_history`, `stops`, `media`, `events`, `history`, `segments`).
     * **Signalisation** (`signalling`) : l'état actuel (`block_systems_km`, `train_protection_km`, `ranges`,
     * SNCF Réseau) et `history`, les mises en service datées lues dans Wikipédia (même contenu que
     * `signalling_history`).
     */
    #[PathParameter('line', 'Identifiant (slug) de la ligne, tel que renvoyé par l\'annuaire ou la recherche. Les lignes du réseau ferré national sont nommées `rfn-` suivi de leur code à 6 chiffres.', example: 'rfn-447000')]
    #[QueryParameter('geometry', 'Joindre le tracé GeoJSON de chaque portion (`portions.geometry`) : `true` ou `false` (par défaut). Une portion dont une source n\'autorise pas la redistribution garde `geometry` à `null`, avec `geometry_withheld` et `geometry_withheld_reason`.', type: 'boolean', default: false, example: false)]
    #[ApiResponse(200, 'Fiche de la ligne (extrait de la ligne de Morlaix à Roscoff ; listes raccourcies).', examples: [LineSheetExample::BODY])]
    #[ApiResponse(404, 'Ligne inconnue.', examples: [ErrorExamples::LINE_NOT_FOUND])]
    #[ApiResponse(422, '`geometry` n\'est pas un booléen.', examples: [ErrorExamples::GEOMETRY_INVALID])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function show(Request $request, Line $line, LineSheet $sheet, LineSignallingHistory $signallingHistory): JsonResponse
    {
        $request->merge(['geometry' => QueryBoolean::cast($request->query('geometry'))])->validate(['geometry' => ['nullable', 'boolean']]);

        return LineSheetResource::make($sheet->build($line) + ['signalling_history' => $signallingHistory->facts($line)])->response()->header('Cache-Control', 'public, max-age=300');
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
     *   coordonnées longitude, latitude à 6 décimales ; `null` pour un tronçon
     *   dont la source n'autorise pas la redistribution, avec alors
     *   `geometry_withheld` à `true` et `geometry_withheld_reason`
     *   (`licence_not_open` : licence qui n'autorise pas la redistribution ;
     *   `unknown_source` : source absente du registre des droits).
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
            'geometry_withheld' => false, 'geometry_withheld_reason' => null,
        ]],
    ]])]
    #[ApiResponse(404, 'Ligne ou ère inconnue.', examples: [ErrorExamples::LINE_NOT_FOUND])]
    #[ApiResponse(422, '`era` n\'est pas un entier ou `geometry` n\'est pas un booléen.', examples: [ErrorExamples::GEOMETRY_INVALID])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function segments(Request $request, Line $line, LineTraceExport $export): JsonResponse
    {
        $input = $request->merge(['geometry' => QueryBoolean::cast($request->query('geometry'))])->validate([
            'era' => ['nullable', 'integer'],
            'geometry' => ['nullable', 'boolean'],
        ]);
        $era = $export->era($input['era'] ?? null);

        return response()->json([
            'line' => ['slug' => $line->slug, 'code' => $line->code, 'name' => $line->name],
            'era_year' => $era->year,
            'data' => LineSegmentResource::collection($export->segments($line, $era, (bool) ($input['geometry'] ?? false)))->resolve($request),
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
     * `code`, `name`, `url` de la fiche sur VFF), `era_year`, `attribution`
     * (la mention de la base VFF et celle de chaque source dont vient une
     * géométrie exportée, avec sa licence) et `withheld`. Une ère sans tracé
     * pour cette ligne donne une collection vide, pas une erreur.
     *
     * **Droits.** La géométrie d'un tronçon n'est donnée que si sa source a une
     * licence ouverte écrite et vérifiée (OpenStreetMap, SNCF Réseau, IGN,
     * OpenHistoricalMap, contributions validées : `provenance.license_status` =
     * `verified`). Pour un tronçon d'une source dont la licence n'autorise pas
     * la redistribution, ou d'une source non déclarée, `geometry` vaut `null`,
     * `properties.geometry_withheld` vaut `true` et
     * `properties.geometry_withheld_reason` donne la raison ; ses autres
     * propriétés restent lisibles. `withheld` résume ces tronçons par source (nombre, longueur,
     * raison : `licence_not_open` ou `unknown_source`) ; la liste est vide quand
     * tout est exporté. Reprenez les mentions de `attribution` si vous publiez
     * le tracé. `/lines/{line}/segments?geometry=true` suit la même règle.
     *
     * Le même tracé existe en `/lines/{line}/gpx` et `/lines/{line}/kml`.
     */
    #[PathParameter('line', 'Identifiant (slug) de la ligne.', example: 'rfn-447000')]
    #[QueryParameter('era', "Année de l'ère (décennie de 1830 à 2030). Par défaut : l'ère courante (aujourd'hui). Une année sans ère (`1935`) donne HTTP 404.", type: 'int', example: 1930)]
    #[QueryParameter('lang', 'Langue des mentions de `attribution` : `fr` (défaut), `en` ou `de`.', type: 'string', example: 'fr')]
    #[ApiResponse(200, 'Tracé de la ligne de Morlaix à Roscoff en 1930 (un tronçon).', examples: [[
        'type' => 'FeatureCollection',
        'line' => ['slug' => 'rfn-447000', 'code' => '447000', 'name' => 'Ligne de Morlaix à Roscoff', 'url' => 'https://vff-project.org/lines/rfn-447000'],
        'era_year' => 1930,
        'attribution' => [
            'notice' => 'Données de Voies Ferrées de France (vff-project.org), base sous licence ODbL 1.0 : citez « Voies Ferrées de France (vff-project.org) », le lien de la fiche et les sources ci-dessous.',
            'sources' => [
                ['provider' => 'vff', 'mention' => 'Voies Ferrées de France (vff-project.org), UnxWares, ODbL 1.0', 'open_licence' => true],
                ['provider' => 'ign', 'mention' => 'IGN (BD TOPO, BD ORTHO historique), Licence Ouverte 2.0', 'open_licence' => true],
            ],
        ],
        'withheld' => [],
        'features' => [[
            'type' => 'Feature',
            'id' => 8573551,
            'geometry' => ['type' => 'LineString', 'coordinates' => [[-3.837041, 48.578218], [-3.83613, 48.581991]]],
            'properties' => [
                'pk_start_m' => 1093, 'pk_end_m' => 1550, 'length_m' => 457, 'status' => 'in_service', 'traffic_type' => 'mixed',
                'is_heritage' => false, 'electrified' => false, 'gauge_mm' => 1435, 'source' => 'bdtopo',
                'provenance' => ['provider' => 'ign', 'name' => 'IGN (BD TOPO, BD ORTHO historique)', 'license' => "Licence Ouverte 2.0 (Etalab), données publiques de l'IGN libres depuis le 1er janvier 2021", 'license_status' => 'verified'],
                'geometry_withheld' => false, 'geometry_withheld_reason' => null,
            ],
        ]],
    ]])]
    #[ApiResponse(404, 'Ligne ou ère inconnue.', examples: [ErrorExamples::LINE_NOT_FOUND])]
    #[ApiResponse(422, '`era` n\'est pas un entier ou `lang` n\'est pas `fr`, `en` ou `de`.', examples: [ErrorExamples::ERA_INVALID])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function geojson(Request $request, Line $line, LineTraceExport $export, Attribution $attribution): JsonResponse
    {
        $input = $request->validate(['era' => ['nullable', 'integer'], 'lang' => ['nullable', 'in:fr,en,de']]);
        $lang = $input['lang'] ?? 'fr';
        $era = $export->era($input['era'] ?? null);

        $segments = $export->segments($line, $era, true);
        $features = LineSegmentResource::collection($segments)->resolve($request);
        $providers = $export->exportedProviders($segments);

        return response()->json([
            'type' => 'FeatureCollection',
            'line' => ['slug' => $line->slug, 'code' => $line->code, 'name' => $line->name, 'url' => url('/lines/'.$line->slug)],
            'era_year' => $era->year,
            'attribution' => [
                'notice' => trans('mcp.attribution.notice', [], $lang),
                'sources' => array_map(
                    fn (string $provider): array => $attribution->source($provider, $lang),
                    ['vff', ...$providers],
                ),
            ],
            'withheld' => $export->withheld($segments),
            'features' => array_map(function (array $segment): array {
                $geometry = $segment['geometry'] ?? null;
                $id = $segment['id'];
                unset($segment['id'], $segment['geometry']);

                return ['type' => 'Feature', 'id' => $id, 'geometry' => $geometry, 'properties' => $segment];
            }, $features),
        ], 200, ['Content-Type' => 'application/geo+json', 'Cache-Control' => 'public, max-age=300']);
    }
}

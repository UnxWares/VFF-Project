<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListLineEventsRequest;
use App\Http\Resources\LineEventDetailResource;
use App\Http\Resources\LineEventResource;
use App\Models\LineEvent;
use App\Services\Api\LineEventQuery;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;

#[Group('Événements', 'Les événements datés du réseau : ouvertures, fermetures, électrifications, déposes… de toutes les lignes, et la chronologie des gares, avec la source et la licence de chaque événement.', weight: 5)]
class LineEventController extends Controller
{
    /**
     * Événements de lignes.
     *
     * Les événements datés de la vie des lignes (ouverture, fermeture,
     * électrification, dépose…), toutes lignes confondues, du plus ancien au
     * plus récent, filtrables et paginés par curseur. Pour la chronologie
     * complète d'une seule ligne avec son état à chaque ère, voir
     * `/lines/{line}/history`.
     *
     * **Pagination par curseur** : la réponse contient `links.next` (adresse de
     * la page suivante, filtres conservés, `null` à la dernière page) et
     * `meta.next_cursor`. Suivez `links.next` tant qu'elle n'est pas `null`.
     * Le curseur reste valable même si des événements sont ajoutés entre deux
     * appels, et il n'y a pas de numéro de page ni de total : on lit la liste
     * de proche en proche. Le curseur est une chaîne opaque, à reprendre telle
     * quelle ; un curseur illisible ou venu d'une autre liste donne HTTP 422.
     * Tri : `order=asc` (par défaut, du plus ancien) ou `order=desc` ; à date
     * égale, par identifiant.
     *
     * **Dates** : `date` est au format ISO `AAAA-MM-JJ`, à lire avec
     * `date_precision` : `day` (date exacte, à afficher jj/mm/aaaa), `month`
     * (mois et année connus, le jour vaut 01), `year` (année seule, mois et
     * jour valent 01). Les filtres `from` et `to` comparent ces dates telles
     * quelles : un événement connu à l'année seule est daté du 1er janvier.
     *
     * **Types** (`type`) :
     * - trafic, tous services : `opening` (ouverture), `closure` (fermeture),
     *   `reopening` (réouverture) ;
     * - un seul service : `opening_passenger`, `closure_passenger`,
     *   `reopening_passenger` (voyageurs), `opening_freight`, `closure_freight`,
     *   `reopening_freight` (fret) ;
     * - infrastructure : `electrification`, `modernization`, `dismantling`
     *   (dépose de la voie), `conversion` (reconversion de l'emprise) ;
     * - administration : `concession`, `concession_end`, `declassification`
     *   (retrait juridique du réseau, qui ne prouve pas la dépose) ;
     * - associations : `heritage_start`, `heritage_end` ;
     * - `accident`, `other`.
     *
     * **Portion** : `section_from` et `section_to` nomment les gares ou lieux
     * qui bornent la portion concernée quand l'événement ne touche pas toute
     * la ligne ; ils valent `null` pour la ligne entière.
     *
     * **Département** : le filtre retient les lignes dont la fiche cite ce
     * département ou qui y desservent une gare ; un événement est rattaché à la
     * ligne entière, pas à la seule portion située dans le département.
     * `region` applique la même règle à tous les départements d'une région.
     * Codes acceptés et noms : `/departments` et `/regions`.
     *
     * **Provenance et licence** : chaque événement garde sa source (`source`,
     * `source_url` : la page consultée) et un bloc `provenance` : `provider` et
     * `name` (fournisseur de la donnée), `license` (licence ou conditions de
     * réutilisation, telle que vérifiée par VFF) et `license_status`
     * (`verified` : licence ouverte établie ; `uncertain` : autorisation
     * particulière ou conditions à lire, citez la source ; `forbidden`). Citez
     * toujours le fournisseur en réutilisant un événement.
     * `confidence` (`high`, `medium`, `low`) est l'indice de fiabilité attribué
     * par VFF à l'événement : `low` invite à la prudence (date déduite, source
     * unique et faible).
     */
    #[QueryParameter('line', 'Identifiant (slug) de la ligne, tel que renvoyé par la recherche (ex. `rfn-420000`). Une ligne inconnue donne HTTP 422.', type: 'string', example: 'rfn-447000')]
    #[QueryParameter('type', "Type d'événement : `opening`, `closure`, `reopening`, `opening_passenger`, `closure_passenger`, `reopening_passenger`, `opening_freight`, `closure_freight`, `reopening_freight`, `electrification`, `modernization`, `dismantling`, `conversion`, `concession`, `concession_end`, `declassification`, `heritage_start`, `heritage_end`, `accident`, `other`. Une autre valeur donne HTTP 422.", type: 'string', example: 'closure_passenger')]
    #[QueryParameter('source', "Provenance de l'événement, 64 caractères au plus : `wikipedia`, `wikipedia-reseaux`, `wikidata`, `ohm`, `lignes-oubliees`, `archeoferroviaire`, `osm`, `heritage`, `estimation-reseau`, `manual`… Une valeur sans événement donne une liste vide.", type: 'string', example: 'wikipedia')]
    #[QueryParameter('department', 'Code INSEE du département (`01` à `95`, `2A`, `2B`, `971` à `976`) : lignes citant ce département ou y desservant une gare. Les minuscules sont acceptées (`2a`). Un autre code donne HTTP 422.', type: 'string', example: '29')]
    #[QueryParameter('region', 'Code INSEE de la région (`11`, `24`, `27`, `28`, `32`, `44`, `52`, `53`, `75`, `76`, `84`, `93`, `94`, outre-mer `01` à `06`) : lignes citant l\'un de ses départements ou y desservant une gare. Un autre code donne HTTP 422. Liste avec les noms : `/regions`.', type: 'string', example: '53')]
    #[QueryParameter('from', 'Début de la période, bornes incluses : une année (`1930`, soit le 1er janvier) ou une date (`1930-06-15`). Toute autre forme, ou une date inexistante (`1930-02-31`), donne HTTP 422.', type: 'string', example: '1930')]
    #[QueryParameter('to', 'Fin de la période, bornes incluses : une année (`1939`, soit le 31 décembre) ou une date (`1939-12-31`).', type: 'string', example: '1939')]
    #[QueryParameter('order', 'Ordre chronologique : `asc` (du plus ancien, par défaut) ou `desc` (du plus récent).', type: 'string', default: 'asc', example: 'asc')]
    #[QueryParameter('per_page', "Nombre d'événements par page, de 1 à 100.", type: 'int', default: 25, example: 25)]
    #[QueryParameter('cursor', 'Curseur de la page voulue, tel que renvoyé par `meta.next_cursor` : une chaîne opaque, à ne pas fabriquer. Sans curseur : première page.', type: 'string', example: 'eyJsaW5lX2V2ZW50cy5ldmVudF9kYXRlIjoiMTg4My0wMS0wMSAwMDowMDowMCIsImxpbmVfZXZlbnRzLmlkIjoyNzk0LCJfcG9pbnRzVG9OZXh0SXRlbXMiOnRydWV9')]
    #[ApiResponse(200, 'Une page de la liste des événements (ici deux événements de la ligne de Morlaix à Roscoff, `line=rfn-447000&per_page=2`).', examples: [[
        'data' => [[
            'id' => 7802, 'type' => 'other', 'date' => '1879-07-23', 'date_precision' => 'day',
            'section_from' => null, 'section_to' => null,
            'description' => 'Événement (Wikipédia, liste, « Historique > Chronologie »)',
            'confidence' => 'high', 'source' => 'wikipedia',
            'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Morlaix_%C3%A0_Roscoff&oldid=238010272',
            'provenance' => ['provider' => 'wikipedia', 'name' => 'Wikipédia (FR)', 'license' => 'CC BY-SA 4.0 pour les textes ; les faits (dates) ne sont pas protégés', 'license_status' => 'verified'],
            'line' => ['slug' => 'rfn-447000', 'code' => '447000', 'name' => 'Ligne de Morlaix à Roscoff'],
        ], [
            'id' => 2794, 'type' => 'opening', 'date' => '1883-01-01', 'date_precision' => 'year',
            'section_from' => null, 'section_to' => null, 'description' => 'Ouverture de la ligne',
            'confidence' => 'low', 'source' => 'wikidata', 'source_url' => 'https://www.wikidata.org/wiki/Q3239716#P1619',
            'provenance' => ['provider' => 'wikidata', 'name' => 'Wikidata', 'license' => 'CC0 1.0', 'license_status' => 'verified'],
            'line' => ['slug' => 'rfn-447000', 'code' => '447000', 'name' => 'Ligne de Morlaix à Roscoff'],
        ]],
        'links' => ['first' => null, 'last' => null, 'prev' => null, 'next' => 'https://vff-project.org/api/v1/events?line=rfn-447000&per_page=2&cursor=eyJsaW5lX2V2ZW50cy5ldmVudF9kYXRlIjoiMTg4My0wMS0wMSAwMDowMDowMCIsImxpbmVfZXZlbnRzLmlkIjoyNzk0LCJfcG9pbnRzVG9OZXh0SXRlbXMiOnRydWV9'],
        'meta' => ['path' => 'https://vff-project.org/api/v1/events', 'per_page' => 2, 'next_cursor' => 'eyJsaW5lX2V2ZW50cy5ldmVudF9kYXRlIjoiMTg4My0wMS0wMSAwMDowMDowMCIsImxpbmVfZXZlbnRzLmlkIjoyNzk0LCJfcG9pbnRzVG9OZXh0SXRlbXMiOnRydWV9', 'prev_cursor' => null],
    ]])]
    #[ApiResponse(422, 'Filtre ou curseur invalide : type inconnu, date mal formée, ligne inconnue, département inconnu, `per_page` hors de 1 à 100. Le corps donne `message` et, par paramètre, la liste des erreurs dans `errors`.', examples: [ErrorExamples::EVENTS_INVALID])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function index(ListLineEventsRequest $request, LineEventQuery $query): JsonResponse
    {
        $events = $query->apply(LineEvent::query()->with('line:id,slug,code,name'), $request->filters())
            ->cursorPaginate($request->perPage())
            ->withQueryString();

        return LineEventResource::collection($events)->response()->header('Cache-Control', 'public, max-age=300');
    }

    /**
     * Fiche d'un événement.
     *
     * Un événement de ligne avec ce que la liste ne donne pas : la **citation**
     * de la source (`source_quote`, l'extrait du texte d'où l'événement est
     * tiré, à citer avec le lien `source_url`), le détail de la portion
     * concernée, `reuse` et `heritage_kind`.
     *
     * - `section_pk_start_m` et `section_pk_end_m` : points kilométriques de
     *   début et de fin de la portion, en mètres (`null` si la portion n'est
     *   pas donnée en PK) ; `section_length_m` : longueur de la portion
     *   située sur le tracé, en mètres ;
     * - `section_resolution` : comment la portion a été située sur le tracé.
     *   `pk` (bornes en points kilométriques dans la source), `places` (noms de
     *   gares ou de lieux retrouvés sur le tracé), `trace` (événement rattaché
     *   à un tracé d'une source), `whole_line` (aucune portion décrite : toute
     *   la ligne), `unresolved` (portion non située : mieux vaut aucune portion
     *   qu'une portion douteuse) ;
     * - `reuse` : reconversion de l'emprise après la dépose, pour un événement
     *   `conversion` : `greenway` (voie verte), `road` (route), `urban`
     *   (voirie urbaine) ; `null` sinon ;
     * - `heritage_kind` : nature de l'exploitation associative, pour
     *   `heritage_start` et `heritage_end` : `tourist_train` (train
     *   touristique), `velorail` ; `null` sinon.
     *
     * Même provenance et même licence que dans la liste : `source`,
     * `source_url` et le bloc `provenance`. La citation est un extrait de la
     * source, sous la licence de celle-ci.
     */
    #[PathParameter('event', "Identifiant numérique de l'événement, tel que renvoyé par la liste (`id`), 1 à 18 chiffres.", example: 11941)]
    #[ApiResponse(200, "Fiche de l'événement (ici la fermeture aux voyageurs d'Étampes à Saint-Hilaire - Chalo).", examples: [[
        'data' => [
            'id' => 11941, 'type' => 'closure_passenger', 'date' => '1939-08-01', 'date_precision' => 'day',
            'section_from' => 'Étampes', 'section_to' => 'Saint-Hilaire - Chalo',
            'description' => "Fermeture aux voyageurs d'Étampes à Saint-Hilaire - Chalo (Wikipédia, tableau, « Historique > Chronologie »)",
            'confidence' => 'high', 'source' => 'wikipedia',
            'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_d%27%C3%89tampes_%C3%A0_Auneau-Embranchement&oldid=235150524',
            'provenance' => ['provider' => 'wikipedia', 'name' => 'Wikipédia (FR)', 'license' => 'CC BY-SA 4.0 pour les textes ; les faits (dates) ne sont pas protégés', 'license_status' => 'verified'],
            'line' => ['slug' => 'lo-549000', 'code' => '549000', 'name' => "Ligne d'Etampes à Auneau-Embranchement"],
            'source_quote' => 'Étampes - Saint-Hilaire - Chalo | | 6,7 | | 20 novembre 1883 | | 5 juin 1893 | | 1 août 1939 | | 3 novembre 1969',
            'section_pk_start_m' => null, 'section_pk_end_m' => null, 'section_length_m' => 5614, 'section_resolution' => 'places',
            'reuse' => null, 'heritage_kind' => null,
        ],
    ]])]
    #[ApiResponse(404, 'Événement inconnu (ou identifiant qui n\'est pas un entier de 1 à 18 chiffres).', examples: [ErrorExamples::EVENT_NOT_FOUND])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function show(LineEvent $event): JsonResponse
    {
        $event->load('line:id,slug,code,name');

        return LineEventDetailResource::make($event)->response()->header('Cache-Control', 'public, max-age=300');
    }
}

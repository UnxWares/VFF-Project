<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListStationEventsRequest;
use App\Http\Resources\StationEventResource;
use App\Models\Station;
use App\Models\StationEvent;
use App\Services\Api\StationEventQuery;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;

#[Group('Événements', 'Les événements datés du réseau : ouvertures, fermetures, électrifications, déposes… de toutes les lignes, et la chronologie des gares, avec la source et la licence de chaque événement.', weight: 5)]
class StationEventController extends Controller
{
    /**
     * Événements d'une gare.
     *
     * La chronologie d'une gare : ouverture, fermeture (aux voyageurs, au fret,
     * définitive), réouverture, démolition, du plus ancien au plus récent,
     * filtrable et paginée par curseur (même principe que `/events` : suivez
     * `links.next` tant qu'elle n'est pas `null`, `order=desc` inverse le
     * tri, pas de numéro de page ni de total).
     *
     * **Types** (`type`) : `opening` (ouverture), `closure` (fermeture
     * totale), `closure_passenger` (fermeture aux voyageurs, la gare pouvant
     * rester ouverte au fret), `closure_freight` (fermeture au fret),
     * `reopening` (réouverture), `demolition` (démolition du bâtiment ou de la
     * gare).
     *
     * **Dates** : `date` est au format ISO `AAAA-MM-JJ`, à lire avec
     * `date_precision` : `day` (date exacte), `month` (mois et année connus,
     * le jour vaut 01), `year` (année seule, mois et jour valent 01).
     *
     * **Provenance et licence** : `source` est la source de l'événement
     * (`wikidata`, `wikidata:qualifiers`, `wikipedia`, `ohm`, `osm`,
     * `osm:note`, `correction` pour une correction sourcée de VFF), `source_ref`
     * sa référence (identifiant Wikidata et propriété, page…) et `source_url`
     * un lien quand il existe. Le bloc `provenance` donne le fournisseur
     * (`provider`, `name`), la licence (`license`) et sa solidité
     * (`license_status` : `verified`, `uncertain` ou `forbidden`) : citez le
     * fournisseur en réutilisant l'événement. `is_inferred` vaut `true` quand
     * VFF a **déduit** l'événement des événements des lignes qui desservent la
     * gare ou de l'état des tracés (`source` vaut alors `inferred:line_event`
     * ou `inferred:trace`) : ce n'est pas un relevé direct, filtrez-le avec
     * `inferred=false` pour ne garder que les événements sourcés. `note`
     * précise parfois le contexte.
     */
    #[PathParameter('station', 'Identifiant (slug) de la gare, tel que renvoyé par la recherche (ex. `brest`).', example: 'brest')]
    #[QueryParameter('type', "Type d'événement : `opening`, `closure`, `closure_passenger`, `closure_freight`, `reopening`, `demolition`. 24 caractères au plus ; une valeur sans événement donne une liste vide.", type: 'string', example: 'opening')]
    #[QueryParameter('source', "Source de l'événement : `wikidata`, `wikidata:qualifiers`, `wikipedia`, `ohm`, `osm`, `osm:note`, `correction`, `inferred:line_event`, `inferred:trace`. 30 caractères au plus.", type: 'string', example: 'wikidata')]
    #[QueryParameter('inferred', '`true` : seulement les événements déduits par VFF ; `false` : seulement les événements relevés dans une source. Sans ce paramètre, les deux. Autres valeurs acceptées : `1` et `0`.', type: 'boolean', example: false)]
    #[QueryParameter('from', 'Début de la période, bornes incluses : une année (`1930`, soit le 1er janvier) ou une date (`1930-06-15`). Toute autre forme donne HTTP 422.', type: 'string', example: '1860')]
    #[QueryParameter('to', 'Fin de la période, bornes incluses : une année (`1939`, soit le 31 décembre) ou une date (`1939-12-31`).', type: 'string', example: '1869')]
    #[QueryParameter('order', 'Ordre chronologique : `asc` (du plus ancien, par défaut) ou `desc` (du plus récent).', type: 'string', default: 'asc', example: 'asc')]
    #[QueryParameter('per_page', "Nombre d'événements par page, de 1 à 100.", type: 'int', default: 25, example: 25)]
    #[QueryParameter('cursor', 'Curseur de la page voulue, tel que renvoyé par `meta.next_cursor` : une chaîne opaque, à ne pas fabriquer. Sans curseur : première page.', type: 'string', example: 'eyJzdGF0aW9uX2V2ZW50cy5ldmVudF9kYXRlIjoiMTg2NS0wNC0yNiAwMDowMDowMCIsInN0YXRpb25fZXZlbnRzLmlkIjozNTQ0OTIsIl9wb2ludHNUb05leHRJdGVtcyI6dHJ1ZX0')]
    #[ApiResponse(200, 'Une page des événements de la gare (ici la gare de Brest : son ouverture le 26/04/1865, d\'après Wikidata).', examples: [[
        'data' => [[
            'id' => 354492, 'type' => 'opening', 'date' => '1865-04-26', 'date_precision' => 'day',
            'is_inferred' => false, 'note' => null,
            'source' => 'wikidata', 'source_ref' => 'Q2255490#P1619', 'source_url' => 'https://www.wikidata.org/wiki/Q2255490',
            'provenance' => ['provider' => 'wikidata', 'name' => 'Wikidata', 'license' => 'CC0 1.0', 'license_status' => 'verified'],
        ]],
        'links' => ['first' => null, 'last' => null, 'prev' => null, 'next' => null],
        'meta' => ['path' => 'https://vff-project.org/api/v1/stations/brest/events', 'per_page' => 25, 'next_cursor' => null, 'prev_cursor' => null],
    ]])]
    #[ApiResponse(404, 'Gare inconnue (slug introuvable).', examples: [ErrorExamples::STATION_NOT_FOUND])]
    #[ApiResponse(422, 'Filtre ou curseur invalide : date mal formée, `inferred` ni booléen ni `1`/`0`, `per_page` hors de 1 à 100. Le corps donne `message` et, par paramètre, la liste des erreurs dans `errors`.', examples: [ErrorExamples::STATION_EVENTS_INVALID])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function index(Station $station, ListStationEventsRequest $request, StationEventQuery $query): JsonResponse
    {
        $events = $query->apply(StationEvent::query(), $station, $request->filters())
            ->cursorPaginate($request->perPage())
            ->withQueryString();

        return StationEventResource::collection($events)->response()->header('Cache-Control', 'public, max-age=300');
    }
}

<?php

namespace App\Mcp\Tools;

use App\Http\Resources\StationEventResource;
use App\Mcp\Support\CursorPage;
use App\Mcp\Support\NonCommercialSources;
use App\Mcp\Support\VffTool;
use App\Models\StationEvent;
use App\Rules\PaginationCursor;
use App\Services\Api\StationEventQuery;
use App\Support\EventText;
use App\Support\GeneratedText;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('station_events')]
#[Title("Chronologie d'une gare")]
#[Description(<<<'TXT'
Chronologie datée d'une gare, filtrable et paginée : ouverture, fermeture (aux voyageurs, au fret, définitive), réouverture, démolition. Mêmes événements et mêmes filtres que l'API REST GET /api/v1/stations/{station}/events ; get_station en donne la liste complète sans filtre, cet outil sert aux gares à longue histoire et aux questions précises (« quand la gare a-t-elle fermé aux voyageurs ? », « seulement les dates sourcées »).
Chaque événement : `id`, `type` (opening, closure, closure_passenger, closure_freight, reopening, demolition) et `label` dans la langue demandée, `date` ISO AAAA-MM-JJ à lire avec `date_precision` (day = date exacte ; month = mois connu, jour à 01 ; year = année seule), `when` (date rédigée à cette précision), `is_inferred` (true : date DÉDUITE par VFF des lignes qui desservent la gare ou de l'état des tracés, `source` inferred:line_event ou inferred:trace, jamais à présenter comme un relevé), `note` (contexte, dans la langue demandée quand VFF l'a rédigé), `source`, `source_ref`, `source_url` et `provenance` (provider, name, license, license_status : verified, uncertain = citer la source et respecter ses conditions). Les faits d'une source à usage non commercial ne sont jamais servis.
Filtres : `type`, `source` (wikidata, wikidata:qualifiers, wikipedia, ohm, osm, osm:note, correction, inferred:line_event, inferred:trace), `inferred` (false : seulement les dates relevées dans une source), `from`/`to` (année ou date, bornes incluses), `order` (asc par défaut, desc). Un ancien slug de gare fusionnée répond sous la fiche actuelle, signalé par `redirected`.
Pagination : `limit` événements par appel (1 à 100, 25 par défaut) ; si `has_more` vaut true, repassez `next_cursor` dans `cursor` avec les mêmes filtres. Une page raccourcie (`truncated`) reprend au premier événement non renvoyé : rien n'est perdu.
Exemples : {"station": "brest"} ; {"station": "limoges-benedictins", "inferred": false, "order": "desc"} ; {"station": "brest", "type": "closure_passenger", "from": "1930", "to": "1990"}.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class StationEventsTool extends VffTool
{
    private const CURSOR_COLUMNS = ['station_events.event_date', 'station_events.id'];

    /** Types d'événement d'une gare (StationEventQuery accepte toute valeur ; l'outil n'expose que celles-ci). */
    public const TYPES = ['opening', 'closure', 'closure_passenger', 'closure_freight', 'reopening', 'demolition'];

    protected function rules(): array
    {
        return ['station' => ['required', 'string', 'min:1', 'max:150'], 'type' => ['sometimes', 'string', 'in:'.implode(',', self::TYPES)]]
            + StationEventQuery::rules()
            + [
                'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
                'cursor' => ['sometimes', 'string', 'max:512', new PaginationCursor(self::CURSOR_COLUMNS)],
            ];
    }

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'station' => $schema->string()->min(1)->max(150)->required()
                ->description('Slug de la gare, tel que renvoyé par search (ex. "brest", "limoges-benedictins").'),
            'type' => $schema->string()->enum(self::TYPES)->description('Type d\'événement. Par défaut : tous.'),
            'source' => $schema->string()->max(30)->description('Source de l\'événement : wikidata, wikidata:qualifiers, wikipedia, ohm, osm, osm:note, correction, inferred:line_event ou inferred:trace. Par défaut : toutes.'),
            'inferred' => $schema->boolean()->description('true : seulement les dates déduites par VFF ; false : seulement les dates relevées dans une source. Par défaut : les deux.'),
            'from' => $schema->string()->max(10)->description('Début de période inclus : année ("1930", soit le 1er janvier) ou date ("1930-06-15").'),
            'to' => $schema->string()->max(10)->description('Fin de période incluse : année ("1939", soit le 31 décembre) ou date ("1939-12-31").'),
            'order' => $schema->string()->enum(['asc', 'desc'])->default('asc')->description('asc : du plus ancien (par défaut) ; desc : du plus récent.'),
            'limit' => $schema->integer()->min(1)->max(100)->default(25)->description('Nombre d\'événements par appel, 1 à 100 (25 par défaut).'),
            'cursor' => $schema->string()->max(512)->description('Curseur opaque `next_cursor` d\'un appel précédent, pour la page suivante (mêmes filtres).'),
        ];
    }

    protected function answer(array $input, string $lang): Response
    {
        $station = $this->station($input['station']);
        if ($station === null) {
            return $this->fail('station_not_found', ['query' => $input['station']], ['suggestions' => $this->suggestions($input['station'], 'stations')]);
        }
        $filters = StationEventQuery::normalize(array_diff_key($input, array_flip(['station', 'limit', 'cursor', 'lang'])));
        $page = app(StationEventQuery::class)->apply(StationEvent::query(), $station, $filters)
            // Jamais les faits d'une source non commerciale, ni les dates déduites de ses événements de ligne : NonCommercialSources.
            ->whereRaw(app(NonCommercialSources::class)->sqlKeptStationEvent('station_events'))
            ->cursorPaginate((int) ($input['limit'] ?? 25), ['*'], 'cursor', CursorPage::cursor($input['cursor'] ?? null));
        $events = array_map(function (array $e) use ($lang): array {
            $label = 'stations.events.'.$e['type'];

            return ['label' => trans()->has($label, $lang) ? (string) trans($label, [], $lang) : $e['type'],
                'when' => EventText::isoDate($e['date'], $e['date_precision'], $lang)]
                + array_merge($e, ['note' => GeneratedText::stationNote($e['note'], $lang)['text']]);
        }, StationEventResource::collection($page->items())->resolve());

        return $this->respondPage(
            ['station' => ['slug' => $station->slug, 'name' => $station->name], 'filters' => $filters], 'events', $page, $events,
            array_map(fn (array $e) => $e['provenance']['provider'] ?? null, $events),
            $lang,
            self::url('/stations/'.$station->slug),
        );
    }
}

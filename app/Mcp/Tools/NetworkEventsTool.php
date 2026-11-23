<?php

namespace App\Mcp\Tools;

use App\Http\Resources\LineEventResource;
use App\Mcp\Support\CursorPage;
use App\Mcp\Support\NonCommercialSources;
use App\Mcp\Support\VffTool;
use App\Models\LineEvent;
use App\Rules\PaginationCursor;
use App\Services\Api\LineEventQuery;
use App\Support\EventText;
use App\Support\FrenchDepartments;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('network_events')]
#[Title('Événements de tout le réseau')]
#[Description(<<<'TXT'
Événements datés de toutes les lignes (ouvertures, fermetures, électrifications, déposes…), filtrables par ligne, type, source, département ou région et période, dans l'ordre chronologique. Mêmes données et mêmes filtres que l'API REST GET /api/v1/events. Pour une seule ligne avec ses libellés rédigés, line_events convient mieux ; cet outil répond aux questions transversales (« quelles lignes du Finistère ont fermé aux voyageurs entre 1930 et 1939 ? »).
Chaque événement : `id`, `type`, `date` ISO AAAA-MM-JJ à lire avec `date_precision` (day = date exacte ; month = mois connu, jour à 01 ; year = année seule), `when` (date rédigée dans la langue demandée), `section_from`/`section_to` (portion, null pour la ligne entière), `description` (en français), `confidence` (high, medium, low), `source`, `source_url`, `provenance` (provider, name, license, license_status : verified, uncertain = citer la source et respecter ses conditions, forbidden) et `line` (slug, code, name). Les faits d'une source à usage non commercial ne sont jamais servis.
Types : opening, closure, reopening, opening_passenger, closure_passenger, reopening_passenger, opening_freight, closure_freight, reopening_freight, electrification, modernization, dismantling, conversion, concession, concession_end, declassification, heritage_start, heritage_end, accident, other.
Pagination : `limit` événements par appel (1 à 200, 50 par défaut) ; si `has_more` vaut true, repassez `next_cursor` dans `cursor` avec les mêmes filtres. Si la page dépasse la taille maximale, elle est raccourcie (`truncated`) et `next_cursor` reprend au premier événement non renvoyé : rien n'est perdu.
Exemple : {"department": "29", "type": "closure_passenger", "from": "1930", "to": "1939"} ; {"line": "rfn-610000", "order": "desc", "limit": 10}.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class NetworkEventsTool extends VffTool
{
    /** Ancien slug dans `line` ou `station` : filtre sous le slug actuel, dit dans `redirected`. */
    protected const SLUG_FILTERS = true;

    private const CURSOR_COLUMNS = ['line_events.event_date', 'line_events.id'];

    protected function rules(): array
    {
        return LineEventQuery::rules() + [
            'limit' => ['sometimes', 'integer', 'min:1', 'max:200'],
            'cursor' => ['sometimes', 'string', 'max:512', new PaginationCursor(self::CURSOR_COLUMNS)],
        ];
    }

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'line' => $schema->string()->max(255)->description('Slug de la ligne (ex. "rfn-447000"), tel que renvoyé par search. Par défaut : toutes les lignes.'),
            'type' => $schema->string()->enum(LineEventsTool::types())->description('Type d\'événement. Par défaut : tous.'),
            'source' => $schema->string()->max(64)->description('Provenance : wikipedia, wikidata, ohm, lignes-oubliees, osm, heritage, manual… (les sources à usage non commercial ne sont jamais servies). Par défaut : toutes.'),
            'department' => $schema->string()->max(3)->description('Code INSEE du département en majuscules ("29", "2A", "974") : lignes citant ce département ou y desservant une gare.'),
            'region' => $schema->string()->enum(FrenchDepartments::regionCodes())->description('Code INSEE de la région ("53" Bretagne ; liste avec les noms : outil list_departments) : lignes citant l\'un de ses départements ou y desservant une gare.'),
            'from' => $schema->string()->max(10)->description('Début de période inclus : année ("1930", soit le 1er janvier) ou date ("1930-06-15").'),
            'to' => $schema->string()->max(10)->description('Fin de période incluse : année ("1939", soit le 31 décembre) ou date ("1939-12-31").'),
            'order' => $schema->string()->enum(['asc', 'desc'])->default('asc')->description('asc : du plus ancien (par défaut) ; desc : du plus récent.'),
            'limit' => $schema->integer()->min(1)->max(200)->default(50)->description('Nombre d\'événements par appel, 1 à 200 (50 par défaut).'),
            'cursor' => $schema->string()->max(512)->description('Curseur opaque `next_cursor` d\'un appel précédent, pour la page suivante (mêmes filtres).'),
        ];
    }

    protected function answer(array $input, string $lang): Response
    {
        $filters = LineEventQuery::normalize(array_diff_key($input, array_flip(['limit', 'cursor', 'lang'])));
        $page = app(LineEventQuery::class)->apply(LineEvent::query()->with('line:id,slug,code,name'), $filters)
            // Jamais les faits d'une source non commerciale (archeoferroviaire) : NonCommercialSources.
            ->whereRaw(app(NonCommercialSources::class)->sqlKept('line_events', 'line_events.source', 'line_events.source_url'))
            ->cursorPaginate((int) ($input['limit'] ?? 50), ['*'], 'cursor', CursorPage::cursor($input['cursor'] ?? null));
        $events = array_map(fn (array $e): array => ['when' => EventText::isoDate($e['date'], $e['date_precision'], $lang)] + $e,
            LineEventResource::collection($page->items())->resolve());

        return $this->respondPage(
            ['filters' => $filters], 'events', $page, $events,
            array_map(fn (array $e) => $e['provenance']['provider'] ?? null, $events),
            $lang,
            self::url(isset($filters['line']) ? '/lines/'.$filters['line'] : '/lines'),
        );
    }
}

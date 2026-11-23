<?php

namespace App\Support;

use App\Mcp\Support\NonCommercialSources;
use App\Mcp\Support\ServedEventDates;
use App\Mcp\Support\ServedLineDates;
use App\Mcp\Support\ServedStationDates;
use App\Models\LineEvent;
use App\Services\Communes\CommuneSheet;
use Illuminate\Support\Facades\DB;

/**
 * Règle unique des dates servies HORS DU SITE (API REST, GraphQL, serveur MCP, données ouvertes) : une date de ligne
 * ou de gare n'en sort que si une source servie la donne. Une source à usage non commercial (NonCommercialSources)
 * n'est jamais servie hors du site : un fait qui ne repose que sur elle (événement de ligne, événement de gare déduit
 * d'un tel événement, date d'ouverture ou de fermeture d'une gare, borne de desserte, date d'une phase) est écarté,
 * ou sa date devient nulle avec la précision de ce qui reste servi :
 *   - un événement (de ligne ou de gare) écarté ne figure dans aucune liste et n'est pas trouvé par son identifiant ;
 *   - une date d'un objet servi (gare, desserte, phase, fiche de département) prend la date et la précision d'un
 *     événement servi de même nature et de même année, sinon elle est nulle (précision nulle, jamais un jour ni une
 *     année affirmés sans source servie).
 * Les faits que VFF en a tirés par calcul (années de desserte, état d'un tronçon à une ère, longueurs) restent servis.
 *
 * Cette classe est le point d'entrée unique ; elle s'appuie sur les briques du serveur MCP : NonCommercialSources
 * (sources écartées, événements de ligne et de gare), ServedStationDates (dates d'une gare), ServedEventDates
 * (bornes de desserte, phases) et ServedLineDates (dates d'une fiche de département).
 *
 * Le site lui-même garde toutes ses dates, avec le crédit et le lien de leur source. Les jeux de données ouverts
 * appliquent en plus leur propre règle, plus stricte (App\Services\OpenData\OpenDataRights).
 */
final class RedistributableDates
{
    public function __construct(
        private readonly NonCommercialSources $nonCommercial,
        private readonly ServedEventDates $eventDates,
    ) {}

    /** Condition SQL « cet événement de ligne (alias) est servi hors du site ». */
    public function lineEventKept(string $alias = 'line_events'): string
    {
        return $this->nonCommercial->sqlKept('line_events', "{$alias}.source", "{$alias}.source_url");
    }

    /** Vrai si cet événement de ligne est servi hors du site. */
    public function servesLineEvent(?string $source, ?string $url): bool
    {
        return ! $this->nonCommercial->excludes('line_events', $source, $url);
    }

    /**
     * Condition SQL « cet événement de gare (alias de station_events) est servi hors du site » : ni d'une source non
     * commerciale, ni déduit par VFF d'un événement de ligne écarté (NonCommercialSources::sqlKeptStationEvent).
     */
    public function stationEventKept(string $alias = 'station_events'): string
    {
        return $this->nonCommercial->sqlKeptStationEvent($alias);
    }

    /**
     * Dates d'ouverture et de fermeture de gares servies hors du site (ServedStationDates) : chaque ligne porte `slug`,
     * `opened_at`, `opened_precision`, `closed_at`, `closed_precision` et, facultatifs, `opened_after`, `closed_after`.
     * Une date que seuls des événements écartés donnent prend celle d'un événement servi de même nature et de même
     * année, ou devient nulle avec sa précision et sa borne. Une requête pour toute la liste.
     *
     * @param  array<array-key, array<string, mixed>>  $rows
     * @return array<array-key, array<string, mixed>>
     */
    public function stations(array $rows): array
    {
        if ($rows === []) {
            return $rows;
        }
        $dates = app(ServedStationDates::class)->load(array_values(array_map(fn (array $r): ?string => $r['slug'] ?? null, $rows)));

        return array_map(fn (array $r): array => $dates->station($r), $rows);
    }

    /** Expression SQL de la date servie d'une gare (alias de stations, `opened` ou `closed`), pour trier comme on sert. */
    public function stationDateSql(string $alias, string $kind): string
    {
        return app(ServedStationDates::class)->sql($alias, $kind);
    }

    /**
     * Une gare : stations() pour une seule ligne.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function station(array $row): array
    {
        return $this->stations([$row])[0];
    }

    /**
     * Bornes de desserte (StationServiceDates::bounds) servies hors du site : une borne dont l'événement de ligne est
     * écarté prend un événement servi équivalent, ou devient nulle (ServedEventDates::bounds).
     *
     * @param  array<array-key, array{bound: ?array{date: string, precision: string, event_id: ?int}, role: 'start'|'end', station_id: int}>  $requests
     * @return array<array-key, ?array{date: string, precision: string, event_id: ?int}>
     */
    public function serviceBounds(array $requests): array
    {
        return $this->eventDates->bounds($requests);
    }

    /**
     * Phases d'une ligne (LinePhases::forLine) servies hors du site (ServedEventDates::phases).
     *
     * @param  list<array<string, mixed>>  $phases
     * @param  list<array{geometry: mixed}>  $portions
     * @return list<array<string, mixed>>
     */
    public function phases(array $phases, array $portions): array
    {
        return $this->eventDates->phases($phases, $portions);
    }

    /**
     * Fiche d'un département (DepartmentFigures::sheet) servie hors du site : dates des lignes (`opened`, `closed`,
     * `electrified`), première ouverture et réouvertures (ServedLineDates) ; une réouverture sans date servie est retirée.
     *
     * @param  array<string, mixed>  $sheet
     * @return array<string, mixed>
     */
    public function departmentSheet(array $sheet): array
    {
        $slugs = array_column($sheet['lines'] ?? [], 'slug');
        foreach (['first_opening', 'reopenings'] as $key) {
            $entries = $key === 'first_opening' ? array_filter([$sheet['milestones'][$key] ?? null]) : ($sheet['milestones'][$key] ?? []);
            foreach ($entries as $e) {
                $slugs[] = $e['line']['slug'] ?? null;
            }
        }
        $served = app(ServedLineDates::class)->load($slugs);
        foreach ($sheet['lines'] ?? [] as $i => $line) {
            foreach (['opened', 'closed', 'electrified'] as $kind) {
                if (array_key_exists($kind, $line)) {
                    $sheet['lines'][$i][$kind] = $served->date($line['slug'] ?? null, $kind, $line[$kind]);
                }
            }
        }
        if (! isset($sheet['milestones'])) {
            return $sheet;
        }
        $first = $sheet['milestones']['first_opening'] ?? null;
        if ($first !== null) {
            $dated = $served->date($first['line']['slug'] ?? null, 'opened', ['date' => $first['date'], 'precision' => $first['precision']]);
            $sheet['milestones']['first_opening'] = $dated === null ? null : array_replace($first, $dated);
        }
        $reopenings = [];
        foreach ($sheet['milestones']['reopenings'] ?? [] as $r) {
            $dated = $served->date($r['line']['slug'] ?? null, 'reopened', ['date' => $r['date'], 'precision' => $r['precision']]);
            if ($dated !== null) {
                $reopenings[] = array_replace($r, $dated);
            }
        }
        $sheet['milestones']['reopenings'] = $reopenings;

        return $sheet;
    }

    /**
     * Fiche d'une commune (CommuneSheet::page) servie hors du site : dates des gares (ServedStationDates) ; dans la
     * chronologie, ouverture et fermeture d'une gare seulement si elles sont servies, et un autre événement de gare lu
     * dans station_events (`kind` « station_<type> », types CommuneSheet::STATION_EVENTS) seulement si un événement
     * servi le donne (même gare, même type, même année : sa date et sa précision s'il n'a pas la même date). Les
     * événements de ligne y sont déjà lus sans les sources non commerciales (CommuneSheet).
     *
     * @param  array<string, mixed>  $page
     * @return array<string, mixed>
     */
    public function communeSheet(array $page): array
    {
        $dates = app(ServedStationDates::class)->load(array_merge(array_column($page['stations'], 'slug'),
            array_map(fn (array $e): ?string => $e['station']['slug'] ?? null, $page['chronology'])));
        $page['stations'] = array_map(fn (array $s): array => $dates->station($s), $page['stations']);
        $chronology = array_values(array_filter($page['chronology'], fn (array $e): bool => match ($e['kind'] ?? null) {
            'station_opening' => $dates->served($e['station']['slug'] ?? null, 'opened', $e['date']),
            'station_closure' => $dates->served($e['station']['slug'] ?? null, 'closed', $e['date']),
            default => true,
        }));

        $stationKinds = array_map(fn (string $t): string => 'station_'.$t, CommuneSheet::STATION_EVENTS);
        $slugs = [];
        foreach ($chronology as $e) {
            if (in_array($e['kind'] ?? null, $stationKinds, true)) {
                $slugs[] = $e['station']['slug'];
            }
        }
        if ($slugs === []) {
            $page['chronology'] = $chronology;

            return $page;
        }
        $served = [];
        $rows = DB::table('station_events')->join('stations', 'stations.id', '=', 'station_events.station_id')
            ->whereIn('stations.slug', array_values(array_unique($slugs)))->whereNotNull('station_events.event_date')
            ->whereRaw($this->stationEventKept('station_events'))
            ->orderBy('station_events.event_date')->orderBy('station_events.id')
            ->get(['stations.slug', 'station_events.type', 'station_events.event_date', 'station_events.date_precision']);
        foreach ($rows as $r) {
            $served[$r->slug.'|station_'.$r->type.'|'.substr((string) $r->event_date, 0, 4)][] = ['date' => substr((string) $r->event_date, 0, 10), 'precision' => (string) ($r->date_precision ?? 'day')];
        }

        $out = [];
        foreach ($chronology as $e) {
            if (in_array($e['kind'] ?? null, $stationKinds, true)) {
                $same = $served[$e['station']['slug'].'|'.$e['kind'].'|'.substr((string) $e['date'], 0, 4)] ?? [];
                if ($same === []) {
                    continue;
                }
                if (! in_array(substr((string) $e['date'], 0, 10), array_column($same, 'date'), true)) {
                    $e = array_replace($e, $same[0]);
                }
            }
            $out[] = $e;
        }
        $page['chronology'] = $out;

        return $page;
    }

    /**
     * Histoires d'une ligne (EventStories::available) servies hors du site : pour chacune, l'événement qui la porte
     * et ses sources servies (le premier d'entre eux quand l'événement de l'histoire est écarté) ; une histoire dont
     * aucun événement n'est servi est retirée.
     *
     * @param  array<int, array{event_ids: list<int>, images: int, documents: int}>  $available
     * @return array<int, array{event: LineEvent, event_ids: list<int>, images: int, documents: int}>
     */
    public function stories(array $available): array
    {
        $events = LineEvent::query()->whereIn('id', array_merge(array_keys($available), ...array_column($available, 'event_ids')))
            ->get()->keyBy('id');
        $out = [];
        foreach ($available as $id => $counts) {
            $ids = $counts['event_ids'] === [] ? [$id] : $counts['event_ids'];
            $kept = array_values(array_filter($ids,
                fn (int $m): bool => ($e = $events->get($m)) instanceof LineEvent && $this->servesLineEvent($e->source, $e->source_url)));
            $event = $kept === [] ? null : $events->get(in_array($id, $kept, true) ? $id : $kept[0]);
            if ($event instanceof LineEvent) {
                // Rien d'écarté : la liste telle qu'EventStories la donne.
                $out[$id] = ['event' => $event, 'event_ids' => count($kept) === count($ids) ? $counts['event_ids'] : $kept] + $counts;
            }
        }

        return $out;
    }

    /**
     * Une histoire (EventStories::story) servie hors du site : ses sources sans source redistribuable sont retirées,
     * l'événement de l'histoire devient la première source servie s'il l'était ; null s'il n'en reste aucune.
     *
     * @param  array<string, mixed>  $story
     * @return array<string, mixed>|null
     */
    public function story(array $story): ?array
    {
        $kept = $this->storySourceIds($story);
        if ($kept === []) {
            return null;
        }
        $story['sources'] = array_values(array_filter($story['sources'], fn (array $e): bool => in_array($e['id'], $kept, true)));
        if (! in_array($story['event']['id'], $kept, true)) {
            $story['event'] = $story['sources'][0];
        }

        return $story;
    }

    /**
     * Identifiants des sources servies d'une histoire (EventStories::story), dans leur ordre.
     *
     * @param  array<string, mixed>  $story
     * @return list<int>
     */
    public function storySourceIds(array $story): array
    {
        $members = LineEvent::query()->whereIn('id', array_column($story['sources'], 'id'))->get(['id', 'source', 'source_url'])->keyBy('id');

        return array_values(array_map('intval', array_filter(array_column($story['sources'], 'id'),
            fn (mixed $id): bool => ($m = $members->get($id)) instanceof LineEvent && $this->servesLineEvent($m->source, $m->source_url))));
    }
}

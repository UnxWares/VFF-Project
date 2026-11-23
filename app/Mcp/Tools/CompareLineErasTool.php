<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\LineEvents;
use App\Mcp\Support\Lookup;
use App\Mcp\Support\SignallingFacts;
use App\Mcp\Support\VffTool;
use App\Models\Line;
use App\Services\LinePhases;
use App\Services\Signalling\LineSignallingHistory;
use App\Services\Signalling\SignallingHistoryState;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('compare_line_eras')]
#[Title('Comparer une ligne à deux époques')]
#[Description(<<<'TXT'
Ce qui a changé sur UNE ligne entre deux époques de la carte VFF : kilomètres par catégorie à chaque époque, gares ouvertes ou fermées entre les deux, signalisation en vigueur à chacune, et les événements datés qui expliquent le changement. Répond à « qu'est-ce qui a changé sur Angoulême - Limoges entre 1930 et 1990 ? ». Pour tout le réseau, utilisez compare_eras.
Chaque année est ramenée à l'ère qui la contient ou la précède (1937 donne 1930) ; les deux sont remises dans l'ordre chronologique (`from` la plus ancienne, `to` la plus récente). Deux années de la même ère : erreur `same_era`.
`from` et `to` : `year`, `is_current`, `snapshot` (31/12 de l'année de l'ère, ou aujourd'hui), `in_service_km` (longueur en service), `by_category_km` (code, label, km : lgv, mixed = voyageurs et fret, freight, unknown = en service sans trafic renseigné, heritage = train touristique, velorail, service_track, construction, closed = fermée voie en place, dismantled = déposée, razed, gone, none = pas encore construite), `stations` (nombre de gares par état à cette ère : active, tourist, no_commercial, declassified), `signalling_in_force` (mises en service de signalisation en vigueur à cette date, faits sûrs seulement : system, role = block, cab ou speed_control, date, date_precision, section from et to, source provider et url ; liste vide si aucun fait daté, ce qui ne prouve pas l'absence de signalisation).
`changes` : `in_service_km` (écart, positif = ligne plus longue en service), `by_category_km` (écart par catégorie, catégories inchangées omises), `stations_opened` et `stations_closed` (gares ouvertes à une ère et pas à l'autre : slug, name, pk_m, status_from, status_to ; au plus `max_stations` chacune, `stations_opened_total` et `stations_closed_total` donnent le compte complet).
`events` : événements datés de la ligne entre les deux instantanés (après le 31/12 de `from`, jusqu'à `to`), dans l'ordre chronologique, au plus `max_events` (`events_total` : compte complet ; `events_by_type`) ; chacun avec id, date, precision, when, type, label, section, text, source, source_url, provider. Les faits d'une source à usage non commercial (archeoferroviaire) ne sont jamais servis.
Longueurs : tronçons de la carte (ordre de grandeur, pas le kilométrage officiel). Sources : bloc `attribution` (tracés OSM, IGN, SNCF ; événements selon leur `provider` ; signalisation Wikipédia CC BY-SA 4.0, seuls des faits sont repris).
Exemples : {"line": "rfn-610000", "from": 1930, "to": 1990} ; {"line": "420000", "from": 2026, "to": 1950, "max_events": 10, "lang": "de"}.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class CompareLineErasTool extends VffTool
{
    /** États où une gare est ouverte à une ère. */
    private const OPEN = ['active', 'tourist', 'no_commercial'];

    protected function rules(): array
    {
        return [
            'line' => ['required', 'string', 'min:1', 'max:100'],
            'from' => ['required', 'integer', 'min:1800', 'max:2100'],
            'to' => ['required', 'integer', 'min:1800', 'max:2100'],
            'max_events' => ['sometimes', 'integer', 'min:0', 'max:200'],
            'max_stations' => ['sometimes', 'integer', 'min:0', 'max:200'],
        ];
    }

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'line' => $schema->string()->min(1)->max(100)->required()
                ->description('Ligne : slug (ex. "rfn-610000", renvoyé par search) ou code SNCF Réseau à 6 chiffres (ex. "610000").'),
            'from' => $schema->integer()->min(1800)->max(2100)->required()
                ->description('Première année, de 1800 à 2100 (ex. 1930), ramenée à l\'ère de la carte.'),
            'to' => $schema->integer()->min(1800)->max(2100)->required()
                ->description('Seconde année, de 1800 à 2100 (ex. 1990, ou l\'année en cours pour aujourd\'hui) ; peut précéder `from`.'),
            'max_events' => $schema->integer()->min(0)->max(200)->default(30)
                ->description('Nombre d\'événements renvoyés au plus, 0 à 200 (30 par défaut) ; 0 : comptes seulement.'),
            'max_stations' => $schema->integer()->min(0)->max(200)->default(50)
                ->description('Nombre de gares ouvertes et de gares fermées renvoyées au plus, 0 à 200 (50 par défaut).'),
        ];
    }

    protected function answer(array $input, string $lang): Response
    {
        $line = Lookup::line($input['line']);
        if ($line === null) {
            return $this->fail('line_not_found', ['query' => $input['line']], ['suggestions' => $this->suggestions($input['line'], 'lines')]);
        }
        $eras = [];
        foreach ([(int) $input['from'], (int) $input['to']] as $year) {
            $era = Lookup::era($year);
            if ($era === null) {
                return $this->fail('era_not_found', ['year' => $year], ['available_eras' => Lookup::eraYears()]);
            }
            $eras[] = $era;
        }
        usort($eras, fn (object $a, object $b): int => $a->year <=> $b->year);
        [$from, $to] = $eras;
        if ($from->id === $to->id) {
            return $this->fail('same_era', ['year' => $from->year], ['available_eras' => Lookup::eraYears()]);
        }
        $maxEvents = (int) ($input['max_events'] ?? 30);
        $maxStations = (int) ($input['max_stations'] ?? 50);

        $decades = collect(LinePhases::forLine($line)['decades'])->keyBy('year');
        $facts = app(LineSignallingHistory::class)->forSheet($line);
        $stopsFrom = $this->stops($line, $from->id);
        $stopsTo = $this->stops($line, $to->id);

        $state = function (object $era, array $stops) use ($decades, $facts, $lang): array {
            $byCat = (array) ($decades[$era->year]['length_by_category'] ?? []);
            $counts = [];
            foreach ($stops as $s) {
                $counts[$s['status']] = ($counts[$s['status']] ?? 0) + 1;
            }
            $until = SignallingHistoryState::until($era->year, $era->is_current);

            return [
                'year' => $era->year,
                'is_current' => $era->is_current,
                'snapshot' => $era->is_current ? (string) trans('mcp.today', [], $lang) : sprintf('%d-12-31', $era->year),
                'in_service_km' => round(array_sum(array_intersect_key($byCat, array_flip(LinePhases::ACTIVE))) / 1000, 1),
                'by_category_km' => self::categories($byCat, $lang),
                'stations' => $counts,
                'signalling_in_force' => array_map(SignallingFacts::compact(...), SignallingHistoryState::inForce($facts, $until)),
            ];
        };
        $stateFrom = $state($from, $stopsFrom);
        $stateTo = $state($to, $stopsTo);

        // Écart par catégorie (mètres), catégories inchangées omises.
        $catFrom = (array) ($decades[$from->year]['length_by_category'] ?? []);
        $catTo = (array) ($decades[$to->year]['length_by_category'] ?? []);
        $delta = [];
        foreach (array_unique([...array_keys($catFrom), ...array_keys($catTo)]) as $c) {
            $d = ($catTo[$c] ?? 0) - ($catFrom[$c] ?? 0);
            if (abs($d) >= 50) {
                $delta[(string) $c] = $d;
            }
        }

        $opened = $this->stationDiff($stopsFrom, $stopsTo, false);
        $closed = $this->stationDiff($stopsFrom, $stopsTo, true);

        $events = app(LineEvents::class);
        $rows = $events->query($line->id)
            ->where('event_date', '>', sprintf('%04d-12-31', $from->year))
            ->where('event_date', '<=', SignallingHistoryState::until($to->year, $to->is_current))
            ->get();
        $byType = $rows->countBy('type')->sortDesc()->all();
        $providers = DB::table('line_segments')->where('line_id', $line->id)->whereIn('era_id', [$from->id, $to->id])->distinct()->pluck('source')
            ->map(fn ($s) => $this->attribution->providerFor('line_segments', $s === null ? null : (string) $s))->all();
        foreach ($rows->take($maxEvents) as $e) {
            $providers[] = $this->attribution->providerFor('line_events', $e->source, $e->source_url);
        }
        if ($stateFrom['signalling_in_force'] !== [] || $stateTo['signalling_in_force'] !== []) {
            $providers[] = 'wikipedia';
        }

        return $this->respond([
            'line' => ['slug' => $line->slug, 'code' => $line->code, 'name' => $line->name],
            'requested_years' => [(int) $input['from'], (int) $input['to']],
            'from' => $stateFrom,
            'to' => $stateTo,
            'changes' => [
                'in_service_km' => round($stateTo['in_service_km'] - $stateFrom['in_service_km'], 1),
                'by_category_km' => self::categories($delta, $lang),
                'stations_opened_total' => count($opened),
                'stations_opened' => array_slice($opened, 0, $maxStations),
                'stations_closed_total' => count($closed),
                'stations_closed' => array_slice($closed, 0, $maxStations),
            ],
            'events_total' => $rows->count(),
            'events_by_type' => $byType,
            'events' => $rows->take($maxEvents)->map(fn (object $e): array => $events->format($e, $lang))->values()->all(),
            'available_eras' => Lookup::eraYears(),
        ], $providers, $lang, self::url('/lines/'.$line->slug), ['events', 'changes.stations_opened', 'changes.stations_closed']);
    }

    /**
     * Gares de la ligne à une ère, par slug.
     *
     * @return array<string, array{slug: string, name: string, pk_m: ?int, status: string}>
     */
    private function stops(Line $line, int $eraId): array
    {
        $out = [];
        foreach (DB::table('line_stops as ls')->join('stations as s', 's.id', '=', 'ls.station_id')
            ->where('ls.line_id', $line->id)->where('ls.era_id', $eraId)->orderBy('ls.position')
            ->get(['s.slug', 's.name', 'ls.pk_m', 'ls.effective_status']) as $r) {
            $out[(string) $r->slug] = ['slug' => (string) $r->slug, 'name' => (string) $r->name,
                'pk_m' => $r->pk_m === null ? null : (int) $r->pk_m, 'status' => (string) $r->effective_status];
        }

        return $out;
    }

    /**
     * Gares ouvertes à une ère et pas à l'autre ($closing : ouvertes à `from`, plus à `to`).
     *
     * @param  array<string, array{slug: string, name: string, pk_m: ?int, status: string}>  $from
     * @param  array<string, array{slug: string, name: string, pk_m: ?int, status: string}>  $to
     * @return list<array{slug: string, name: string, pk_m: ?int, status_from: ?string, status_to: ?string}>
     */
    private function stationDiff(array $from, array $to, bool $closing): array
    {
        $open = fn (?array $s): bool => $s !== null && in_array($s['status'], self::OPEN, true);
        $out = [];
        foreach ($closing ? $from : $to as $slug => $s) {
            [$a, $b] = $closing ? [$s, $to[$slug] ?? null] : [$from[$slug] ?? null, $s];
            if ($open($closing ? $a : $b) && ! $open($closing ? $b : $a)) {
                $out[] = ['slug' => $s['slug'], 'name' => $s['name'], 'pk_m' => $s['pk_m'],
                    'status_from' => $a['status'] ?? null, 'status_to' => $b['status'] ?? null];
            }
        }

        return $out;
    }

    /**
     * @param  array<array-key, int|float>  $metres  mètres par catégorie
     * @return list<array{code: string, label: string, km: float}>
     */
    private static function categories(array $metres, string $lang): array
    {
        arsort($metres);
        $out = [];
        foreach ($metres as $code => $m) {
            $out[] = ['code' => (string) $code, 'label' => (string) trans('lines.categories.'.$code, [], $lang), 'km' => round($m / 1000, 1)];
        }

        return $out;
    }
}

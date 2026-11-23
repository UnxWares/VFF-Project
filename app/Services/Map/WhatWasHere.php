<?php

namespace App\Services\Map;

use App\Mcp\Support\AccidentView;
use App\Mcp\Support\Attribution;
use App\Mcp\Support\LineEvents;
use App\Mcp\Support\Lookup;
use App\Mcp\Support\NonCommercialSources;
use App\Mcp\Support\SignallingFacts;
use App\Models\Accident;
use App\Models\Line;
use App\Models\LineMaterialPeriod;
use App\Services\Communes\CommuneRail;
use App\Services\Signalling\LineSignallingHistory;
use App\Services\Signalling\SignallingHistoryState;
use App\Services\TraceExport\LineTraceExport;
use App\Support\EventText;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * « Qu'y avait-il ici ? » : autour d'un point et pour une année, les lignes présentes avec leur état à l'ère,
 * les gares proches, la signalisation, le matériel daté, les événements et les accidents de l'année.
 *
 * Source unique de l'outil MCP `what_was_here` et du point d'API REST `GET /api/v1/what-was-here` (panneau de
 * la carte /maps) : aucune requête n'est écrite deux fois. Règle de droits : un tracé non redistribuable
 * ne donne ni distance ni PK précis (voir WITHHELD_MIN_RADIUS_M).
 */
class WhatWasHere
{
    /** Événements renvoyés au plus par ligne. */
    public const EVENTS_PER_LINE = 5;

    /** Séries de matériel renvoyées au plus par ligne. */
    public const MATERIALS_PER_LINE = 10;

    public const MAX_STATIONS = 10;

    /** Mode `summary` (page « Autour de moi ») : lignes et gares au plus, sans signalisation, matériel, événements ni accidents. */
    public const SUMMARY_MAX_STATIONS = 20;

    public const SUMMARY_MAX_LINES = 15;

    public const MAX_ACCIDENTS = 10;

    /** Rayon minimal de recherche d'un tracé non redistribuable, en mètres, autour du centre de la maille du point. */
    public const WITHHELD_MIN_RADIUS_M = 200;

    public function __construct(
        private readonly Attribution $attribution,
        private readonly LineTraceExport $traces,
        private readonly NonCommercialSources $nonCommercial,
        private readonly LineEvents $events,
        private readonly LineSignallingHistory $signalling,
        private readonly LineHistoryFacts $history,
        private readonly CommuneRail $communes,
    ) {}

    private function url(string $path): string
    {
        return config('mcp.vff.public_url').$path;
    }

    /**
     * Avec `summary` : version courte pour « Autour de moi » (lignes et gares avec une phrase d'histoire, la
     * commune du point), sans signalisation, matériel, événements ni accidents.
     *
     * @param  array<string, mixed>  $input  lat, lng, year, radius_m, max_lines, summary (déjà validés)
     * @return array{payload: array<string, mixed>, providers: list<?string>, map: string}|null null si aucune ère ne contient l'année
     */
    public function around(array $input, string $lang): ?array
    {
        $lat = (float) $input['lat'];
        $lng = (float) $input['lng'];
        $year = (int) ($input['year'] ?? date('Y'));
        $radius = (int) ($input['radius_m'] ?? 1000);
        $summary = (bool) ($input['summary'] ?? false);
        $maxLines = (int) ($input['max_lines'] ?? ($summary ? self::SUMMARY_MAX_LINES : 8));
        $era = Lookup::era($year);
        if ($era === null) {
            return null;
        }
        $isNow = $era->is_current && $year >= (int) date('Y');
        $until = SignallingHistoryState::until($year, $isNow);
        $yearStart = sprintf('%04d-01-01', $year);
        // Fenêtre d'index en degrés (au moins le rayon jusqu'à 65° de latitude), puis distance exacte.
        $deg = $radius / 45_000;
        $point = 'ST_SetSRID(ST_MakePoint(?, ?), 4326)';

        // Tracé non redistribuable : cherché autour du centre de la maille de 0,001 degré du point, dans au moins
        // WITHHELD_MIN_RADIUS_M, sans distance renvoyée, comme locate_on_line : une grille de requêtes ne doit pas
        // suffire à reconstituer le tracé (précision de 150 m environ).
        $gridLat = (floor($lat * 1000) + 0.5) / 1000;
        $gridLng = (floor($lng * 1000) + 0.5) / 1000;
        $open = 'COALESCE('.$this->traces->sqlRedistributable('ls.source').', FALSE)';
        $withheldRadius = max($radius, self::WITHHELD_MIN_RADIUS_M);
        $segments = DB::select(
            "SELECT DISTINCT ON (ls.line_id) ls.line_id, ls.status, ls.traffic_type, ls.is_heritage, ls.heritage_kind, ls.electrified, ls.voltage_v,
                    ls.max_speed_kmh, ls.gauge_mm, ls.source, ls.pk_start_m, ls.pk_end_m,
                    ST_Distance(ls.geom::geography, (CASE WHEN {$open} THEN p.g ELSE q.g END)::geography) AS d,
                    ST_LineLocatePoint(ls.geom, CASE WHEN {$open} THEN p.g ELSE q.g END) AS f
             FROM line_segments ls, (SELECT {$point} AS g) p, (SELECT {$point} AS g) q
             WHERE ls.era_id = ? AND ls.geom && ST_Expand(p.g, ?)
               AND CASE WHEN {$open} THEN ST_DWithin(ls.geom::geography, p.g::geography, ?)
                        ELSE ST_DWithin(ls.geom::geography, q.g::geography, ?) END
             ORDER BY ls.line_id, d",
            [$lng, $lat, $gridLng, $gridLat, $era->id, $withheldRadius / 45_000 + 0.001, $radius, $withheldRadius],
        );
        usort($segments, fn (object $a, object $b): int => $a->d <=> $b->d);
        $segments = array_slice($segments, 0, $maxLines);
        $lineModels = Line::whereIn('id', array_map(fn (object $s): int => (int) $s->line_id, $segments))->get()->keyBy('id');

        $nearest = $this->nearestStations(array_keys($lineModels->all()), $era->id, $lng, $lat);
        $providers = [];
        $lines = [];
        $history = $summary ? $this->history->forLines(array_keys($lineModels->all())) : ['facts' => [], 'providers' => []];
        array_push($providers, ...$history['providers']);
        foreach ($segments as $s) {
            $line = $lineModels[(int) $s->line_id] ?? null;
            if ($line === null) {
                continue;
            }
            $withheld = ! $this->traces->redistributable($s->source);
            $providers[] = $this->traces->provider($s->source);
            $pk = $s->pk_start_m !== null && $s->pk_end_m !== null
                ? (int) round($s->pk_start_m + (float) $s->f * ($s->pk_end_m - $s->pk_start_m)) : null;

            $facts = [];
            $events = [];
            $materials = [];
            if (! $summary) {
                $facts = array_map(SignallingFacts::compact(...),
                    SignallingHistoryState::inForce($this->signalling->forSheet($line), $until));
                if ($facts !== []) {
                    $providers[] = 'wikipedia';
                }
                $events = $this->events->query($line->id, $year, $year)->limit(self::EVENTS_PER_LINE)->get()
                    ->map(fn (object $e): array => $this->events->format($e, $lang))->values()->all();
                foreach ($events as $e) {
                    $providers[] = $e['provider'];
                }
                $materials = $this->materials($line->id, $yearStart, $until);
                foreach ($materials as $m) {
                    $providers[] = $this->attribution->providerFor('line_material_periods', $m['source']);
                }
            }

            $lines[] = [
                'slug' => $line->slug,
                'name' => $line->name,
                'code' => $line->code,
                'distance_m' => $withheld ? null : (int) round((float) $s->d),
                'nearest_station' => $nearest[$line->id] ?? null,
                'pk_m' => $withheld && $pk !== null ? (int) (round($pk / 100) * 100) : $pk,
                'status' => $s->status,
                'traffic' => $s->traffic_type,
                'heritage' => (bool) $s->is_heritage ? ($s->heritage_kind ?? 'heritage') : null,
                'electrified' => $s->electrified === null ? null : (bool) $s->electrified,
                'voltage_v' => $s->voltage_v === null ? null : (int) $s->voltage_v,
                'max_speed_kmh' => $s->max_speed_kmh === null ? null : (int) $s->max_speed_kmh,
                'gauge_mm' => $s->gauge_mm === null ? null : (int) $s->gauge_mm,
                'geometry_withheld' => $withheld,
                'signalling_in_force' => $facts,
                'materials' => $materials,
                'events' => $events,
                'url' => $this->url('/lines/'.$line->slug),
            ] + ($summary ? ['history' => LineHistoryFacts::reconcile($history['facts'][$line->id] ?? [], (string) $s->status) ?: (object) []] : []);
        }

        // « Autour de moi » : une gare sans ligne à l'ère actuelle (fermée depuis longtemps) garde son état de la fiche.
        $status = $summary ? 'COALESCE(se.status, s.status)' : 'se.status';
        $kept = $summary ? "AND COALESCE(s.provenance->>'kind', '') NOT IN ('technical', 'excluded')" : '';
        $stations = array_map(function (object $r) use ($lang, $summary, &$providers): array {
            $providers[] = $this->attribution->providerFor('station_sources', $r->position_source === null ? null : (string) $r->position_source);

            return [
                'slug' => $r->slug, 'name' => $r->name, 'city' => $r->city, 'distance_m' => (int) round((float) $r->d),
                'status' => $r->status,
                'status_label' => $r->status === null ? null : (string) trans('stations.statuses.'.$r->status, [], $lang),
                'uncertain' => (bool) $r->uncertain,
                'url' => $this->url('/stations/'.$r->slug),
            ] + ($summary ? [
                'opened_year' => $r->opened_at === null ? null : (int) substr((string) $r->opened_at, 0, 4),
                'closed_year' => $r->closed_at === null ? null : (int) substr((string) $r->closed_at, 0, 4),
            ] : []);
        }, DB::select(
            "SELECT s.slug, s.name, s.city, s.position_source, s.opened_at, s.closed_at, {$status} AS status, COALESCE(se.uncertain, false) AS uncertain,
                    ST_Distance(s.geom::geography, p.g::geography) AS d
             FROM stations s LEFT JOIN station_eras se ON se.station_id = s.id AND se.era_id = ?, (SELECT {$point} AS g) p
             WHERE s.geom && ST_Expand(p.g, ?) AND ST_DWithin(s.geom::geography, p.g::geography, ?) AND {$status} IS NOT NULL {$kept}
             ORDER BY d LIMIT ".($summary ? self::SUMMARY_MAX_STATIONS : self::MAX_STATIONS),
            [$era->id, $lng, $lat, $deg, $radius],
        ));

        $accidents = $summary ? new Collection : Accident::query()
            ->whereBetween('occurred_on', [$yearStart, sprintf('%04d-12-31', $year)])
            ->whereRaw("geom && ST_Expand({$point}, ?) AND ST_DWithin(geom::geography, {$point}::geography, ?)", [$lng, $lat, $deg, $lng, $lat, $radius])
            ->selectRaw("accidents.*, ST_Distance(geom::geography, {$point}::geography) AS d", [$lng, $lat])
            ->orderBy('d')->limit(self::MAX_ACCIDENTS)->get();
        $accidentItems = $accidents->map(function (Accident $a) use ($lang): array {
            $name = (array) $a->name;
            $date = $a->occurred_on->toDateString();
            $precision = (string) $a->date_precision->value;

            return [
                'slug' => $a->slug,
                'name' => $name[$lang] ?? $name['fr'] ?? $a->slug,
                'date' => $date,
                'date_precision' => $precision,
                'when' => EventText::isoDate($date, $precision, $lang),
                'kind' => $a->kind->value,
                'kind_label' => (string) trans('accidents.kinds.'.$a->kind->value, [], $lang),
                'deaths' => $a->deaths,
                'injuries' => $a->injuries,
                'review_status' => $a->review_status->value,
                'distance_m' => (int) round((float) $a->getAttribute('d')),
                'url' => $this->url('/accidents/'.$a->slug),
            ];
        })->values()->all();
        if ($accidents->isNotEmpty()) {
            array_push($providers, ...AccidentView::providers($accidents->pluck('id')->map(fn ($id): int => (int) $id)->all()));
        }

        $map = $this->url(sprintf('/maps?era=%d&center=%.5F,%.5F&zoom=15', $era->year, $lng, $lat));

        return [
            'payload' => [
                'point' => ['lat' => $lat, 'lng' => $lng, 'radius_m' => $radius],
                'year' => $year,
                'era' => [
                    'year' => $era->year,
                    'is_current' => $era->is_current,
                    'snapshot' => $era->is_current ? (string) trans('mcp.today', [], $lang) : sprintf('%d-12-31', $era->year),
                ],
                'lines' => $lines,
                'stations' => $stations,
                'accidents' => $accidentItems,
                'links' => ['map' => $map],
            ] + ($summary ? ['commune' => $this->commune($gridLng, $gridLat)] : []),
            'providers' => $providers,
            'map' => $map,
        ];
    }

    /**
     * Commune qui contient le centre de la case du point, avec sa fiche « Le rail à … » si elle en a une.
     *
     * @return array{insee: string, name: string, slug: string|null, url: string|null}|null
     */
    private function commune(float $lng, float $lat): ?array
    {
        $row = DB::selectOne(
            'SELECT code_insee, nom FROM commune_contours
             WHERE geom && ST_SetSRID(ST_MakePoint(?, ?), 4326) AND ST_Intersects(ST_MakeValid(geom), ST_SetSRID(ST_MakePoint(?, ?), 4326))
             ORDER BY code_insee LIMIT 1',
            [$lng, $lat, $lng, $lat],
        );
        if ($row === null) {
            return null;
        }
        $slug = in_array($row->code_insee, $this->communes->codes(), true) ? CommuneRail::slug($row->code_insee, $row->nom) : null;

        return ['insee' => $row->code_insee, 'name' => $row->nom, 'slug' => $slug, 'url' => $slug === null ? null : $this->url('/communes/'.$slug)];
    }

    /**
     * Séries dont une période sûre (fiabilité high ou medium) couvre l'année sur cette ligne, sans source non commerciale.
     *
     * @return list<array<string, mixed>>
     */
    private function materials(int $lineId, string $yearStart, string $yearEnd): array
    {
        $rows = DB::table('line_material_periods as p')
            ->join('line_materials as lm', 'lm.id', '=', 'p.line_material_id')
            ->join('materials as m', 'm.id', '=', 'lm.material_id')
            ->where('lm.line_id', $lineId)
            ->whereIn('p.confidence', LineMaterialPeriod::SHOWN_CONFIDENCES)
            ->where(fn ($q) => $q->whereNotNull('p.start_date')->orWhereNotNull('p.end_date'))
            // Borne absente de la période : remplacée par l'année de mise en service (ou de retrait) de la série,
            // sans quoi une période « jusqu'en 2021 » couvrirait 1930. Début inconnu des deux : période écartée.
            ->whereRaw('COALESCE(p.start_date, make_date(m.in_service_from::int, 1, 1)) <= ?', [$yearEnd])
            ->whereRaw('(COALESCE(p.end_date, make_date(m.in_service_to::int, 12, 31)) >= ? OR (p.end_date IS NULL AND m.in_service_to IS NULL))', [$yearStart])
            ->orderBy('m.name')->orderBy('p.start_date')
            ->get(['m.slug', 'm.name', 'm.type', 'p.start_date', 'p.start_precision', 'p.end_date', 'p.end_precision',
                'p.section_from', 'p.section_to', 'p.source', 'p.source_url']);

        $out = [];
        foreach ($rows as $r) {
            if (isset($out[$r->slug]) || $this->nonCommercial->excludes('line_material_periods', $r->source, $r->source_url)) {
                continue;
            }
            $out[$r->slug] = [
                'slug' => $r->slug,
                'name' => $r->name,
                'type' => $r->type,
                'period' => [
                    'start_date' => $r->start_date, 'start_precision' => $r->start_precision,
                    'end_date' => $r->end_date, 'end_precision' => $r->end_precision,
                    'section' => $r->section_from !== null ? ['from' => $r->section_from, 'to' => $r->section_to] : null,
                ],
                'certainty' => match (true) {
                    $r->start_date !== null && $r->end_date !== null => 'dated_period',
                    $r->end_date === null => 'open_end',
                    default => 'open_start',
                },
                'source' => $r->source,
                'source_url' => $r->source_url,
                'url' => self::url('/materials/'.$r->slug),
            ];
            if (count($out) >= self::MATERIALS_PER_LINE) {
                break;
            }
        }

        return array_values($out);
    }

    /**
     * Gare la plus proche du point parmi celles que desservent les lignes données et qui existent à l'ère.
     * Sa position est publique : aucune information sur un tracé non redistribuable.
     *
     * @param  list<int>  $lineIds
     * @return array<int, array{slug: string, name: string, distance_m: int}>
     */
    private function nearestStations(array $lineIds, int $eraId, float $lng, float $lat): array
    {
        if ($lineIds === []) {
            return [];
        }
        $in = implode(',', array_map('intval', $lineIds));
        $rows = DB::select(
            "SELECT DISTINCT ON (sl.line_id) sl.line_id, s.slug, s.name,
                    ST_Distance(s.geom::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography) AS d
             FROM station_lines sl
             JOIN stations s ON s.id = sl.station_id
             JOIN station_eras se ON se.station_id = s.id AND se.era_id = ? AND se.status IS NOT NULL
             WHERE sl.line_id IN ({$in}) AND s.geom IS NOT NULL
             ORDER BY sl.line_id, d",
            [$lng, $lat, $eraId],
        );
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->line_id] = ['slug' => $r->slug, 'name' => $r->name, 'distance_m' => (int) round((float) $r->d)];
        }

        return $out;
    }
}

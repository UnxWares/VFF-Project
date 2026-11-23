<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\AccidentView;
use App\Mcp\Support\Attribution;
use App\Mcp\Support\LineEvents;
use App\Mcp\Support\Lookup;
use App\Mcp\Support\NonCommercialSources;
use App\Mcp\Support\SignallingFacts;
use App\Mcp\Support\VffTool;
use App\Models\Accident;
use App\Models\Line;
use App\Models\LineMaterialPeriod;
use App\Services\Signalling\LineSignallingHistory;
use App\Services\Signalling\SignallingHistoryState;
use App\Services\TraceExport\LineTraceExport;
use App\Support\EventText;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('what_was_here')]
#[Title('Ce qui passait ici en telle année')]
#[Description(<<<'TXT'
« Qu'est-ce qui passait ici en 1930 ? » : autour d'un point (latitude, longitude WGS 84) et pour une année, les lignes de chemin de fer présentes à l'époque avec leur état et leurs caractéristiques à cet endroit, les gares proches avec leur état, la signalisation en vigueur, les séries de matériel roulant datées sur ces lignes cette année-là, les événements datés de l'année et les accidents survenus à proximité. Tout est sourcé.
L'année est ramenée à l'ère de la carte qui la contient ou la précède (1937 donne 1930) pour l'état des lignes et des gares (`era`) ; les événements, le matériel, la signalisation et les accidents utilisent l'année exacte demandée (`year`). Sans `year` : aujourd'hui.
`lines` (la plus proche d'abord, au plus `max_lines`) : slug, name, code, `distance_m` (du point au tracé), `pk_m` (point kilométrique estimé au droit du point, null sans PK), `status` à l'ère (in_service, single_track, service_track, construction, abandoned = fermée voie en place, dismantled = déposée, razed), `traffic` (mixed, passenger, freight, unknown), `heritage` (train touristique, vélorail), `electrified` et `voltage_v`, `max_speed_kmh`, `gauge_mm`, `geometry_withheld` (vrai quand le tracé à cet endroit vient d'une source non redistribuable : il est alors cherché autour du centre de la maille de 0,001 degré du point, dans 200 m au moins, `distance_m` est null et `pk_m` arrondi à 100 m, juste à 150 m près environ), `signalling_in_force` (mises en service datées en vigueur au 31/12 de l'année, faits sûrs seulement : system, role, date, date_precision, scope, section, source ; une liste vide ne prouve pas l'absence de signalisation), `materials` (séries dont une période de circulation datée par une source couvre l'année : slug, name, type, `period` = start_date, start_precision, end_date, end_precision, section ; `certainty` = dated_period (début et fin datés), open_end (fin non donnée par la source, bornée par le retrait de la série s'il est connu : la série n'y circulait pas forcément encore) ou open_start (début non donné, borné par la mise en service de la série) ; source, source_url), `events` (événements datés de l'année sur cette ligne, au plus 5 : id, date, precision, when, type, label, section, text, source, source_url, provider), `url`.
`stations` (au plus 10, la plus proche d'abord) : slug, name, city, `distance_m`, `status` à l'ère et `status_label`, `uncertain`, `url`. `accidents` (accidents publiés de l'année dans le rayon) : slug, name, date, when, kind, kind_label, deaths, injuries, review_status (to_review = à vérifier, ne pas présenter comme sûr), `distance_m`, url.
Les faits d'une source à usage non commercial (archeoferroviaire) ne sont jamais servis. Sources et licences : bloc `attribution`.
Exemples : {"lat": 45.6484, "lng": 0.1562, "year": 1930} ; {"lat": 48.39, "lng": -4.49, "year": 1975, "radius_m": 2000, "lang": "en"}.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class WhatWasHereTool extends VffTool
{
    /** Événements renvoyés au plus par ligne. */
    public const EVENTS_PER_LINE = 5;

    /** Séries de matériel renvoyées au plus par ligne. */
    public const MATERIALS_PER_LINE = 10;

    public const MAX_STATIONS = 10;

    public const MAX_ACCIDENTS = 10;

    /** Rayon minimal de recherche d'un tracé non redistribuable, en mètres, autour du centre de la maille du point. */
    public const WITHHELD_MIN_RADIUS_M = 200;

    public function __construct(
        Attribution $attribution,
        private readonly LineTraceExport $traces,
        private readonly NonCommercialSources $nonCommercial,
        private readonly LineEvents $events,
        private readonly LineSignallingHistory $signalling,
    ) {
        parent::__construct($attribution);
    }

    protected function rules(): array
    {
        return [
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'year' => ['sometimes', 'integer', 'min:1800', 'max:2100'],
            'radius_m' => ['sometimes', 'integer', 'min:50', 'max:5000'],
            'max_lines' => ['sometimes', 'integer', 'min:1', 'max:20'],
        ];
    }

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'lat' => $schema->number()->min(-90)->max(90)->required()->description('Latitude WGS 84 en degrés décimaux (ex. 45.6484).'),
            'lng' => $schema->number()->min(-180)->max(180)->required()->description('Longitude WGS 84 en degrés décimaux (ex. 0.1562).'),
            'year' => $schema->integer()->min(1800)->max(2100)->description('Année de 1800 à 2100 (ex. 1930) ; par défaut l\'année en cours.'),
            'radius_m' => $schema->integer()->min(50)->max(5000)->default(1000)->description('Rayon de recherche en mètres, 50 à 5 000 (1 000 par défaut).'),
            'max_lines' => $schema->integer()->min(1)->max(20)->default(8)->description('Nombre de lignes renvoyées au plus, 1 à 20 (8 par défaut).'),
        ];
    }

    protected function answer(array $input, string $lang): Response
    {
        $lat = (float) $input['lat'];
        $lng = (float) $input['lng'];
        $year = (int) ($input['year'] ?? date('Y'));
        $radius = (int) ($input['radius_m'] ?? 1000);
        $maxLines = (int) ($input['max_lines'] ?? 8);
        $era = Lookup::era($year);
        if ($era === null) {
            return $this->fail('era_not_found', ['year' => $year], ['available_eras' => Lookup::eraYears()]);
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

        $providers = [];
        $lines = [];
        foreach ($segments as $s) {
            $line = $lineModels[(int) $s->line_id] ?? null;
            if ($line === null) {
                continue;
            }
            $withheld = ! $this->traces->redistributable($s->source);
            $providers[] = $this->traces->provider($s->source);
            $pk = $s->pk_start_m !== null && $s->pk_end_m !== null
                ? (int) round($s->pk_start_m + (float) $s->f * ($s->pk_end_m - $s->pk_start_m)) : null;

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

            $lines[] = [
                'slug' => $line->slug,
                'name' => $line->name,
                'code' => $line->code,
                'distance_m' => $withheld ? null : (int) round((float) $s->d),
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
                'url' => self::url('/lines/'.$line->slug),
            ];
        }

        $stations = array_map(function (object $r) use ($lang, &$providers): array {
            $providers[] = $this->attribution->providerFor('station_sources', $r->position_source === null ? null : (string) $r->position_source);

            return [
                'slug' => $r->slug, 'name' => $r->name, 'city' => $r->city, 'distance_m' => (int) round((float) $r->d),
                'status' => $r->status,
                'status_label' => $r->status === null ? null : (string) trans('stations.statuses.'.$r->status, [], $lang),
                'uncertain' => (bool) $r->uncertain,
                'url' => self::url('/stations/'.$r->slug),
            ];
        }, DB::select(
            "SELECT s.slug, s.name, s.city, s.position_source, se.status, COALESCE(se.uncertain, false) AS uncertain,
                    ST_Distance(s.geom::geography, p.g::geography) AS d
             FROM stations s LEFT JOIN station_eras se ON se.station_id = s.id AND se.era_id = ?, (SELECT {$point} AS g) p
             WHERE s.geom && ST_Expand(p.g, ?) AND ST_DWithin(s.geom::geography, p.g::geography, ?) AND se.status IS NOT NULL
             ORDER BY d LIMIT ".self::MAX_STATIONS,
            [$era->id, $lng, $lat, $deg, $radius],
        ));

        $accidents = Accident::query()
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
                'url' => self::url('/accidents/'.$a->slug),
            ];
        })->values()->all();
        if ($accidents->isNotEmpty()) {
            array_push($providers, ...AccidentView::providers($accidents->pluck('id')->map(fn ($id): int => (int) $id)->all()));
        }

        $map = self::url(sprintf('/maps?era=%d&center=%.5F,%.5F&zoom=15', $era->year, $lng, $lat));

        return $this->respond([
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
        ], $providers, $lang, $map, ['lines', 'stations', 'accidents']);
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
}

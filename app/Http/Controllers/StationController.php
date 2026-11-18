<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ReadsListFilters;
use App\Models\Era;
use App\Models\Station;
use App\Support\GeneratedText;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Gares à travers le temps : annuaire filtrable (par décennie, statut,
 * département) et fiche (chronologie, présence par décennie, lignes
 * desservies selon l'époque, sources et provenance des attributs).
 *
 * Données : vff:import-station-sources → vff:consolidate-stations →
 * vff:attach-stations → vff:build-station-eras.
 */
class StationController extends Controller
{
    use ReadsListFilters;

    private const STATUSES = ['active', 'tourist', 'no_commercial', 'declassified', 'gone', 'planned'];

    public function index(Request $request): Response
    {
        $q = $this->filterString($request, 'q', trim: true);
        $status = $this->filterString($request, 'status');
        $dept = $this->filterString($request, 'dept', trim: true);
        $flagged = $request->boolean('flagged');
        $sort = $this->filterString($request, 'sort');
        $sort = in_array($sort, ['name', 'name-desc', 'city', 'opened', 'closed'], true) ? $sort : 'name';
        $eras = Era::orderBy('year')->get(['id', 'year', 'is_current']);
        $era = $request->integer('era') ? $eras->firstWhere('year', $request->integer('era')) : null;

        $base = DB::table('stations as s')
            ->when($era, fn ($b) => $b->join('station_eras as se', fn ($j) => $j->on('se.station_id', '=', 's.id')->where('se.era_id', $era->id)))
            ->when($q !== '', fn ($b) => $b->where(fn ($w) => $w->where('s.name', 'ilike', $this->containsPattern($q))
                ->orWhere('s.city', 'ilike', $this->containsPattern($q))->orWhere('s.uic_code', 'like', $this->prefixPattern($q))
                ->orWhereRaw('s.alt_names::text ILIKE ?', [$this->containsPattern($q)])))
            ->when($dept !== '', fn ($b) => $b->where('s.department_code', $dept))
            ->when($flagged, fn ($b) => $b->where('s.position_flagged', true))
            // Points techniques (postes, garages, points d'une LGV : jamais ouverts au public) et fiches hors
            // du réseau ferré (tramway, funiculaire, fiche erronée : correction sourcée) : pas des gares.
            ->whereRaw("COALESCE(s.provenance->>'kind', '') NOT IN ('technical', 'excluded')");
        // Gare projetée jamais ouverte : « projetée », jamais « fermée » ni « disparue ».
        $statusExpr = $era ? 'se.status' : "CASE WHEN s.provenance->>'kind' = 'projected' THEN 'planned' WHEN s.source_status = 'gone' THEN 'gone' ELSE s.status END";

        $counts = (clone $base)->selectRaw("{$statusExpr} AS st, COUNT(*) AS n")->groupByRaw($statusExpr)->pluck('n', 'st');

        $list = (clone $base)
            ->when(in_array($status, self::STATUSES, true), fn ($b) => $b->whereRaw("{$statusExpr} = ?", [$status]))
            ->selectRaw(<<<SQL
                s.id, s.slug, s.name, s.type, s.city, s.department_code, s.uic_code, s.sources_count, s.position_flagged,
                {$statusExpr} AS status,
                EXTRACT(YEAR FROM s.opened_at)::int AS opened_year, EXTRACT(YEAR FROM s.closed_at)::int AS closed_year,
                s.provenance->>'opened_at' AS opened_source, s.provenance->>'closed_at' AS closed_source,
                (s.provenance->>'closed_after')::int AS closed_after
            SQL)
            ->tap(fn ($b) => $this->orderIndex($b, $sort, $q))
            ->paginate(50)
            ->appends($request->query());
        $linesByStation = $this->indexLines(collect($list->items())->pluck('id')->all());
        $overall = DB::table('stations as s')
            ->whereRaw("COALESCE(s.provenance->>'kind', '') NOT IN ('technical', 'excluded')")
            ->count();

        return Inertia::render('Stations/Index', [
            'stations' => [
                'data' => collect($list->items())->map(function ($row) use ($linesByStation) {
                    $row = (array) $row;
                    $row['lines'] = $linesByStation[$row['id']] ?? ['items' => [], 'more' => 0];
                    unset($row['id']);

                    return $row;
                })->all(),
                'current_page' => $list->currentPage(),
                'last_page' => $list->lastPage(),
                'total' => $list->total(),
            ],
            'filters' => ['q' => $q, 'status' => $status, 'dept' => $dept, 'era' => $era?->year, 'flagged' => $flagged, 'sort' => $sort],
            'overall' => $overall,
            'counts' => array_map('intval', $counts->all()),
            'eras' => $eras->pluck('year'),
            'departments' => DB::table('stations')->whereNotNull('department_code')->distinct()->orderBy('department_code')->pluck('department_code'),
        ]);
    }

    /**
     * Ordre de l'annuaire : alphabétique FRANÇAIS (collation ICU : accents et capitales sans effet sur le rang).
     * Tri par nom avec une recherche : nom exact, puis noms qui commencent par le terme, puis le reste.
     * Les dates absentes viennent toujours en dernier.
     */
    private function orderIndex($builder, string $sort, string $q): void
    {
        $name = 's.name COLLATE "fr-x-icu"';

        match ($sort) {
            'name-desc' => $builder->orderByRaw("{$name} DESC, s.id"),
            'city' => $builder->orderByRaw("s.city IS NULL, s.city COLLATE \"fr-x-icu\", {$name}, s.id"),
            'opened' => $builder->orderByRaw("s.opened_at IS NULL, s.opened_at, {$name}, s.id"),
            'closed' => $builder->orderByRaw("s.closed_at IS NULL, s.closed_at, {$name}, s.id"),
            default => $this->orderByName($builder, $q, $name),
        };
    }

    private function orderByName($builder, string $q, string $name): void
    {
        if ($q !== '') {
            $builder->orderByRaw(
                'CASE WHEN lower(s.name) = lower(?) THEN 0 WHEN s.name ILIKE ? THEN 1 ELSE 2 END',
                [$q, $this->prefixPattern($q)],
            );
        }
        $builder->orderByRaw("{$name}, s.id");
    }

    /**
     * Lignes desservant chaque gare de la page (trois au plus, codes en priorité) : de quoi distinguer deux
     * fiches de même nom dans la même commune.
     *
     * @param  array<int>  $ids
     * @return array<int, array{items: list<array{slug: string, code: ?string, name: string}>, more: int}>
     */
    private function indexLines(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $rows = DB::table('station_lines as sl')
            ->join('lines as l', 'l.id', '=', 'sl.line_id')
            ->whereIn('sl.station_id', $ids)
            ->orderByRaw('l.code IS NULL, l.code, l.name')
            ->get(['sl.station_id', 'l.slug', 'l.code', 'l.name']);

        $out = [];
        foreach ($rows->groupBy('station_id') as $stationId => $group) {
            $out[(int) $stationId] = [
                'items' => $group->take(3)->map(fn ($r) => ['slug' => $r->slug, 'code' => $r->code, 'name' => $r->name])->values()->all(),
                'more' => max(0, $group->count() - 3),
            ];
        }

        return $out;
    }

    public function show(Station $station): Response
    {
        $sid = $station->id;
        $coords = DB::selectOne('SELECT ST_X(geom) AS lng, ST_Y(geom) AS lat FROM stations WHERE id = ?', [$sid]);

        // Présence par décennie.
        $eras = DB::select(<<<'SQL'
            SELECT e.year, e.is_current,
                   -- Incertaine : ouverte selon ses sources, non confirmée par les voies de l'ère.
                   CASE WHEN se.uncertain AND se.status <> 'declassified' THEN 'uncertain' ELSE se.status END AS status, se.note
            FROM eras e LEFT JOIN station_eras se ON se.era_id = e.id AND se.station_id = ?
            ORDER BY e.year
        SQL, [$sid]);

        // Chronologie (sources + dates déduites).
        $events = DB::table('station_events')->where('station_id', $sid)->orderBy('event_date')->orderBy('is_inferred')
            ->get(['type', 'event_date', 'date_precision', 'source', 'source_url', 'is_inferred', 'note']);

        // Lignes rattachées (station_lines) + décennies où la gare est un arrêt
        // de la ligne + desserte actuelle (tronçon en service de l'ère courante).
        // Gare pas encore rattachée (pipeline non passé, ajout manuel) :
        // repli sur les tronçons à moins de 300 m.
        $activeCurrent = <<<'SQL'
            EXISTS (SELECT 1 FROM line_segments x JOIN eras e ON e.id = x.era_id AND e.is_current
                    JOIN stations st ON st.id = ?
                    WHERE x.line_id = l.id AND x.status IN ('in_service', 'single_track')
                      AND x.geom && ST_Expand(st.geom, 0.007) AND ST_DWithin(x.geom::geography, st.geom::geography, 500))
            SQL;
        $lines = DB::select(<<<SQL
            SELECT l.slug, l.code, l.name, l.network, sl.method, sl.pk_m, sl.pk_source, sl.distance_m,
                   sl.first_year, sl.last_year, sl.years_source, {$activeCurrent} AS active_current,
                   COALESCE((SELECT ARRAY_TO_JSON(ARRAY_AGG(e.year ORDER BY e.year)) FROM line_stops ls JOIN eras e ON e.id = ls.era_id
                             WHERE ls.line_id = l.id AND ls.station_id = sl.station_id), '[]'::json) AS era_years
            FROM station_lines sl JOIN lines l ON l.id = sl.line_id
            WHERE sl.station_id = ?
            ORDER BY sl.last_year IS NOT NULL, sl.first_year NULLS LAST, l.name
        SQL, [$sid, $sid]);
        if ($lines === []) {
            $lines = DB::select(<<<SQL
                SELECT l.slug, l.code, l.name, l.network, 'segment' AS method, NULL AS pk_m, NULL AS pk_source,
                       MIN(ROUND(ST_Distance(s.geom::geography, ls.geom::geography)))::int AS distance_m,
                       NULL AS first_year, NULL AS last_year, NULL AS years_source, {$activeCurrent} AS active_current,
                       '[]'::json AS era_years
                FROM stations s
                JOIN line_segments ls ON ls.geom && ST_Expand(s.geom, 0.005) AND ST_DWithin(ls.geom::geography, s.geom::geography, 300)
                JOIN lines l ON l.id = ls.line_id
                WHERE s.id = ?
                GROUP BY l.id
                ORDER BY distance_m
                LIMIT 12
            SQL, [$sid, $sid]);
        }

        // Sources rattachées, avec écart à la position retenue.
        $sources = DB::select(<<<'SQL'
            SELECT ss.source, ss.source_ref, ss.name, ss.kind, ss.lifecycle, ss.uic_code, ss.wikidata_id,
                   ss.opened_on, ss.closed_on, ss.match_method, ss.passenger, ss.freight,
                   ss.properties->>'frwiki' AS frwiki, ss.properties->>'source_url' AS source_url, ss.properties->>'pk_m' AS pk_m,
                   ss.properties->>'geocoding' AS geocoding, ss.properties->>'precision_m' AS precision_m,
                   ROUND(ST_Distance(ss.geom::geography, s.geom::geography))::int AS distance_m
            FROM station_sources ss JOIN stations s ON s.id = ss.station_id
            WHERE ss.station_id = ?
            ORDER BY CASE ss.source WHEN 'sncf' THEN 0 WHEN 'sncf_voyageurs' THEN 1 WHEN 'osm' THEN 2 WHEN 'wikidata' THEN 3 WHEN 'ohm' THEN 4 ELSE 5 END, ss.id
        SQL, [$sid]);

        // Gares voisines, comme l'annuaire : ni point technique ni fiche exclue du réseau ferré par une
        // correction sourcée (« Safari Train », petit train d'un zoo, apparaissait près de Lisieux).
        $neighbors = DB::select(<<<'SQL'
            SELECT s.slug, s.name, s.type, s.city, CASE WHEN s.source_status = 'gone' THEN 'gone' ELSE s.status END AS status,
                   ST_X(s.geom) AS lng, ST_Y(s.geom) AS lat,
                   ST_Distance(s.geom::geography, ref.geom::geography) AS distance_m
            FROM stations s CROSS JOIN (SELECT geom FROM stations WHERE id = ?) ref
            WHERE s.id <> ? AND s.geom && ST_Expand(ref.geom, 0.15) AND ST_DWithin(s.geom::geography, ref.geom::geography, 10000)
              AND COALESCE(s.provenance->>'kind', '') NOT IN ('technical', 'excluded')
            ORDER BY distance_m ASC
            LIMIT 12
        SQL, [$sid, $sid]);

        $locale = app()->getLocale();

        return Inertia::render('Stations/Show', [
            'station' => [
                'id' => $sid,
                'slug' => $station->slug,
                'name' => $station->name,
                'alt_names' => $station->alt_names ?? [],
                'type' => $station->type->value,
                'status' => match ($station->provenance['kind'] ?? null) {
                    'technical' => 'technical',
                    'excluded' => 'excluded',
                    'projected' => 'planned',
                    default => $station->source_status === 'gone' ? 'gone' : ($station->status?->value ?? 'active'),
                },
                'source_status' => $station->source_status,
                'lifecycle' => $station->lifecycle,
                'city' => $station->city,
                'department_code' => $station->department_code,
                'opened_at' => $station->opened_at?->format('Y-m-d'),
                'opened_precision' => $station->opened_precision,
                'closed_at' => $station->closed_at?->format('Y-m-d'),
                'closed_precision' => $station->closed_precision,
                // Date retenue (vff:build-station-eras, StationTimeline::resolveClosure) : la même que la
                // carte et la fiche ligne. Intervalle (précision range) : fermée après closed_after.
                'closed_after' => is_numeric($station->provenance['closed_after'] ?? null) ? (int) $station->provenance['closed_after'] : null,
                'uic_code' => $station->uic_code,
                'wikidata_id' => $station->wikidata_id,
                'description' => $station->description,
                // Description tirée des sources, en français : lang="fr" sur une page EN ou DE.
                'description_lang' => $station->description !== null && trim((string) $station->description) !== '' && $locale !== 'fr' ? 'fr' : null,
                'provenance' => $station->provenance ?? [],
                'position_source' => $station->position_source,
                'position_spread_m' => $station->position_spread_m,
                'position_flagged' => (bool) $station->position_flagged,
                'lng' => (float) $coords->lng,
                'lat' => (float) $coords->lat,
            ],
            // Notes générées (gabarits lang/{fr,en,de}/generated.php) dans la langue de la page ; note_lang = fr
            // quand une partie reste en français (citation d'une source).
            'eras' => array_map(fn ($e) => [
                'year' => (int) $e->year, 'is_current' => (bool) $e->is_current, 'status' => $e->status,
                'note' => ($note = GeneratedText::stationNote($e->note, $locale))['text'], 'note_lang' => $note['lang'],
                // Ère incertaine faute de date de fermeture (StationTimeline::unconfirmedNote) : légende de la frise.
                'undated' => $e->status === 'uncertain' && (bool) preg_match('/non datée|Fermeture dans les années/u', (string) $e->note),
            ], $eras),
            'events' => $events->map(fn ($e) => [
                'type' => $e->type, 'date' => $e->event_date, 'precision' => $e->date_precision, 'source' => $e->source,
                'source_url' => $e->source_url, 'inferred' => (bool) $e->is_inferred,
                'note' => ($note = GeneratedText::stationNote($e->note, $locale))['text'], 'note_lang' => $note['lang'],
            ])->values(),
            'lines' => array_map(fn ($l) => [
                'slug' => $l->slug, 'code' => $l->code, 'name' => $l->name, 'network' => $l->network,
                'method' => $l->method, 'pk_m' => $l->pk_m !== null ? (int) $l->pk_m : null, 'pk_source' => $l->pk_source,
                'first_year' => $l->first_year ? (int) $l->first_year : null, 'last_year' => $l->last_year ? (int) $l->last_year : null,
                // Période vide (BuildStationEras::reconcileServiceYears) : ligne fermée avant l'ouverture de la
                // gare (line_closed), ou gare fermée avant l'ouverture de la ligne (station_closed).
                'never_served' => $l->years_source !== 'never_served' ? null
                    : ((int) $l->first_year === (int) $station->opened_at?->format('Y') ? 'line_closed' : 'station_closed'),
                'active_current' => (bool) $l->active_current,
                'era_years' => json_decode($l->era_years, true) ?? [],
            ], $lines),
            'sources' => array_map(fn ($s) => [
                'source' => $s->source, 'ref' => $s->source_ref, 'name' => $s->name, 'lifecycle' => $s->lifecycle,
                'uic_code' => $s->uic_code, 'opened_on' => $s->opened_on, 'closed_on' => $s->closed_on,
                'match_method' => $s->match_method, 'distance_m' => (int) $s->distance_m,
                'passenger' => $s->passenger, 'freight' => $s->freight, 'pk_m' => $s->pk_m, 'geocoding' => $s->geocoding,
                'precision_m' => is_numeric($s->precision_m) ? (int) $s->precision_m : null,
                'url' => $this->sourceUrl($s),
            ], $sources),
            'neighbors' => array_map(fn ($n) => [
                'slug' => $n->slug, 'name' => $n->name, 'type' => $n->type, 'city' => $n->city, 'status' => $n->status,
                'lng' => (float) $n->lng, 'lat' => (float) $n->lat, 'distance_m' => (int) $n->distance_m,
            ], $neighbors),
        ]);
    }

    private function sourceUrl(object $s): ?string
    {
        $osmType = fn (string $ref) => ['n' => 'node', 'w' => 'way', 'r' => 'relation'][$ref[0]] ?? 'node';

        return match ($s->source) {
            'wikidata' => 'https://www.wikidata.org/wiki/'.$s->source_ref,
            'osm' => 'https://www.openstreetmap.org/'.$osmType($s->source_ref).'/'.substr($s->source_ref, 1),
            'ohm' => 'https://www.openhistoricalmap.org/'.$osmType($s->source_ref).'/'.substr($s->source_ref, 1),
            'sncf' => 'https://ressources.data.sncf.com/explore/dataset/liste-des-gares/table/?q='.urlencode($s->source_ref),
            'sncf_voyageurs' => 'https://ressources.data.sncf.com/explore/dataset/gares-de-voyageurs/table/?q='.urlencode((string) $s->name),
            default => $s->source_url,
        };
    }
}

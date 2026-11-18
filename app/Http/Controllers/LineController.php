<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ReadsListFilters;
use App\Models\Era;
use App\Models\Line;
use App\Services\LineChronicle;
use App\Services\LineFacts;
use App\Services\LinePhases;
use App\Services\LineStations;
use App\Services\StationTimeline;
use App\Support\Advocacy;
use App\Support\Lgv;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class LineController extends Controller
{
    use ReadsListFilters;

    /** Catégories de réseau (contrat `lines.network`). */
    public const NETWORKS = ['principal', 'secondaire', 'militaire', 'particulier'];

    /** Types de trafic d'une portion (`line_segments.traffic_type`). */
    public const TRAFFICS = ['passenger', 'mixed', 'freight', 'unknown'];

    /** Statuts portant du trafic (cf. SegmentStatus::isActive / NetworkStats). */
    private const ACTIVE_STATUSES = "('in_service','single_track')";

    /**
     * Liste paginée et filtrée des lignes.
     *
     * Filtres acceptés :
     *   ?q=…                         nom, code, opérateur
     *   ?network=principal|secondaire|militaire|particulier|none
     *   ?traffic=passenger|mixed|freight|unknown   trafic ACTUEL (segments en
     *        service de l'ère courante) - `?type=` accepté comme alias historique
     *   ?status=open|closed          au moins une portion en service / aucune
     *   ?heritage=1                  au moins une portion patrimoniale
     *
     * Avant : le filtre et les compteurs reposaient sur `lines.type`, colonne non
     * curée (NULL partout) → « 0 voyageurs · 0 fret · 0 mixtes » (audit STATS-01).
     */
    public function index(Request $request): Response
    {
        $q = $this->filterString($request, 'q', trim: true);
        $network = $this->filterString($request, 'network');
        $traffic = $this->filterString($request, 'traffic') ?: $this->filterString($request, 'type');
        if ($traffic === 'passengers') {
            $traffic = 'passenger';
        }
        $status = $this->filterString($request, 'status');
        $heritage = $request->boolean('heritage');
        $eraId = $this->currentEraId();

        $network = in_array($network, [...self::NETWORKS, 'none'], true) ? $network : '';
        $traffic = in_array($traffic, self::TRAFFICS, true) ? $traffic : '';
        $status = in_array($status, ['open', 'closed'], true) ? $status : '';

        $activeSegments = fn ($s) => $s->select(DB::raw(1))->from('line_segments as s')
            ->whereColumn('s.line_id', 'lines.id')
            ->where('s.era_id', $eraId)
            ->whereIn('s.status', ['in_service', 'single_track']);
        // « Fermée » = tracé connu à l'ère courante mais plus aucune portion en
        // service (une ligne sans géométrie n'est ni ouverte ni fermée : inconnue).
        $anySegment = fn ($s) => $s->select(DB::raw(1))->from('line_segments as s')
            ->whereColumn('s.line_id', 'lines.id')
            ->where('s.era_id', $eraId);
        $closed = fn ($b) => $b->whereExists($anySegment)->whereNotExists($activeSegments);

        // Filtres appliqués À LA FOIS à la liste paginée ET aux stats du header,
        // pour que les compteurs reflètent la recherche en cours.
        $applyFilters = function ($builder, array $skip = []) use ($q, $network, $traffic, $status, $heritage, $activeSegments, $closed) {
            if ($q !== '') {
                $like = $this->containsPattern($q);
                $builder->where(function ($w) use ($like) {
                    $w->where('name', 'ilike', $like)
                        ->orWhere('code', 'ilike', $like)
                        ->orWhere('historical_operator', 'ilike', $like);
                });
            }
            if ($network !== '' && ! in_array('network', $skip, true)) {
                $network === 'none' ? $builder->whereNull('network') : $builder->where('network', $network);
            }
            if ($traffic !== '' && ! in_array('traffic', $skip, true)) {
                $builder->whereExists(fn ($s) => $activeSegments($s)->where('s.traffic_type', $traffic));
            }
            if ($status === 'open') {
                $builder->whereExists($activeSegments);
            } elseif ($status === 'closed') {
                $closed($builder);
            }
            if ($heritage) {
                $builder->whereHas('segments', fn ($s) => $s->where('is_heritage', true));
            }

            return $builder;
        };

        $lines = $applyFilters(
            Line::query()->select('id', 'slug', 'code', 'name', 'network', 'historical_operator')->orderBy('name'),
        )->paginate(40)->appends($request->query());

        // Trafic dominant (au km) et état actuel des seules lignes de la page.
        $summaries = $this->currentSummaries(collect($lines->items())->pluck('id')->all(), $eraId);
        $items = collect($lines->items())->map(fn ($l) => [
            'slug' => $l->slug,
            'code' => $l->code,
            'name' => $l->name,
            'network' => $l->network,
            'historical_operator' => $l->historical_operator,
            'traffic' => $summaries[$l->id]['traffic'] ?? null,
            'state' => $summaries[$l->id]['state'] ?? 'unknown',
        ])->values();

        // Compteurs : chaque facette est calculée sans son propre filtre pour
        // garder visibles les autres options (ex. filtrer « fret » n'écrase pas
        // les compteurs des autres trafics).
        $byNetwork = $applyFilters(Line::query(), ['network'])
            ->selectRaw("COALESCE(network, 'none') AS k, COUNT(*) AS n")
            ->groupByRaw("COALESCE(network, 'none')")
            ->pluck('n', 'k');
        $byTraffic = DB::table('line_segments')
            ->where('era_id', $eraId)
            ->whereIn('status', ['in_service', 'single_track'])
            ->whereIn('line_id', $applyFilters(Line::query()->select('lines.id'), ['traffic']))
            ->selectRaw("COALESCE(traffic_type, 'unknown') AS k, COUNT(DISTINCT line_id) AS n")
            ->groupByRaw("COALESCE(traffic_type, 'unknown')")
            ->pluck('n', 'k');
        $total = $applyFilters(Line::query())->count();
        $open = $applyFilters(Line::query())->whereExists($activeSegments)->count();
        $closedCount = $closed($applyFilters(Line::query()))->count();

        return Inertia::render('Lines/Index', [
            'lines' => [
                'data' => $items,
                'current_page' => $lines->currentPage(),
                'last_page' => $lines->lastPage(),
                'total' => $lines->total(),
            ],
            'filters' => [
                'q' => $q,
                'network' => $network,
                'traffic' => $traffic,
                'status' => $status,
                'heritage' => $heritage,
            ],
            'stats' => [
                'total' => $total,
                'open' => $open,
                'closed' => $closedCount,
                'unknown' => max(0, $total - $open - $closedCount),
                'networks' => collect([...self::NETWORKS, 'none'])
                    ->mapWithKeys(fn ($k) => [$k => (int) ($byNetwork[$k] ?? 0)]),
                'traffics' => collect(self::TRAFFICS)
                    ->mapWithKeys(fn ($k) => [$k => (int) ($byTraffic[$k] ?? 0)]),
                'heritage' => $applyFilters(Line::query())
                    ->whereHas('segments', fn ($s) => $s->where('is_heritage', true))->count(),
            ],
        ]);
    }

    /**
     * Pour un lot de lignes : état à l'ère courante (open = au moins une portion
     * en service, closed = tracé connu mais plus aucune portion en service) et
     * trafic dominant pondéré par la longueur des portions en service.
     *
     * @param  array<int>  $lineIds
     * @return array<int, array{state: string, traffic: ?string}>
     */
    private function currentSummaries(array $lineIds, ?int $eraId): array
    {
        if (! $lineIds || ! $eraId) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($lineIds), '?'));
        $rows = DB::select(
            "WITH s AS (
                SELECT line_id, status, COALESCE(traffic_type, 'unknown') AS traffic_type,
                       COALESCE(length_meters, ST_Length(geom::geography)) AS len,
                       status IN ".self::ACTIVE_STATUSES." AS active
                FROM line_segments
                WHERE era_id = ? AND line_id IN ({$placeholders})
            ),
            t AS (
                SELECT DISTINCT ON (line_id) line_id, traffic_type
                FROM (SELECT line_id, traffic_type, SUM(len) AS km FROM s WHERE active
                      GROUP BY line_id, traffic_type) x
                ORDER BY line_id, km DESC
            )
            SELECT s.line_id, BOOL_OR(s.active) AS is_open, MAX(t.traffic_type) AS traffic
            FROM s LEFT JOIN t ON t.line_id = s.line_id
            GROUP BY s.line_id",
            [$eraId, ...$lineIds],
        );

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->line_id] = [
                'state' => $r->is_open ? 'open' : 'closed',
                'traffic' => $r->traffic,
            ];
        }

        return $out;
    }

    private function currentEraId(): ?int
    {
        return Era::where('is_current', true)->value('id')
            ?? Era::orderByDesc('year')->value('id');
    }

    /**
     * Fiche détaillée d'une ligne ferroviaire.
     *
     * Stratégie :
     *   - Ère affichée = ère COURANTE si la ligne y a des portions, sinon la
     *     décennie la plus récente (par ANNÉE - les id d'ères ne suivent pas la
     *     chronologie : 1990-2030 = 1-5, 1900-1980 = 6-14 ; l'ancien
     *     `max('era_id')` aurait affiché 1980 au lieu de 2030).
     *   - Géométries de l'ère affichée en UNE requête (avant : 1 requête par
     *     segment) + synthèse par décennie (km en service / fermés / déposés,
     *     trafic dominant) pour la frise « évolution ».
     *   - Événements lus bruts : type (nouveaux types tolérés), précision de
     *     date, portion (section_from → section_to), source.
     */
    public function show(Line $line): Response
    {
        // Médias (envois locaux, Commons…) servis paginés par GET /api/v1/lines/{slug}/media (LineGallery).
        $line->load('materials');

        $eraRows = DB::select(
            'SELECT e.id, e.year, e.is_current,
                    SUM(CASE WHEN ls.status IN '.self::ACTIVE_STATUSES." THEN COALESCE(ls.length_meters, ST_Length(ls.geom::geography)) ELSE 0 END) / 1000.0 AS active_km,
                    SUM(CASE WHEN ls.status IN ('abandoned','service_track','construction') THEN COALESCE(ls.length_meters, ST_Length(ls.geom::geography)) ELSE 0 END) / 1000.0 AS closed_km,
                    SUM(CASE WHEN ls.status IN ('dismantled','razed') THEN COALESCE(ls.length_meters, ST_Length(ls.geom::geography)) ELSE 0 END) / 1000.0 AS gone_km,
                    SUM(CASE WHEN ls.is_heritage AND ls.status IN ".self::ACTIVE_STATUSES.' THEN COALESCE(ls.length_meters, ST_Length(ls.geom::geography)) ELSE 0 END) / 1000.0 AS heritage_km,
                    SUM(CASE WHEN ls.is_heritage THEN COALESCE(ls.length_meters, ST_Length(ls.geom::geography)) ELSE 0 END) / 1000.0 AS heritage_all_km,
                    -- Trafic dominant hors portions associatives (sans trafic commercial renseigné).
                    (SELECT x.traffic_type FROM line_segments x
                      WHERE x.line_id = ls.line_id AND x.era_id = e.id AND x.status IN '.self::ACTIVE_STATUSES.'
                        AND NOT COALESCE(x.is_heritage, false)
                      GROUP BY x.traffic_type
                      ORDER BY SUM(COALESCE(x.length_meters, ST_Length(x.geom::geography))) DESC LIMIT 1) AS traffic
             FROM line_segments ls JOIN eras e ON e.id = ls.era_id
             WHERE ls.line_id = ?
             GROUP BY e.id, e.year, e.is_current, ls.line_id
             ORDER BY e.year',
            [$line->id],
        );
        $eraSummary = collect($eraRows)->map(fn ($r) => [
            'year' => (int) $r->year,
            'is_current' => (bool) $r->is_current,
            'active_km' => round((float) $r->active_km, 1),
            'closed_km' => round((float) $r->closed_km, 1),
            'gone_km' => round((float) $r->gone_km, 1),
            'heritage_km' => round((float) $r->heritage_all_km, 1),
            // Train touristique majoritaire : couleur et libellé patrimoine, comme sur la carte.
            'traffic' => (float) $r->heritage_km > 0 && (float) $r->heritage_km * 2 >= (float) $r->active_km ? 'heritage' : $r->traffic,
            'state' => (float) $r->active_km > 0 ? 'open' : ((float) $r->closed_km > 0 ? 'closed' : 'gone'),
        ])->values();

        $displayEra = collect($eraRows)->firstWhere('is_current', true) ?? collect($eraRows)->last();
        $latestEraId = $displayEra?->id;
        $latestSegmentIds = $latestEraId
            ? $line->segments()->where('era_id', $latestEraId)->pluck('id')->all()
            : [];

        // Gares : TOUTES les gares de la ligne (toutes ères, même fermées ou sur une portion
        // déposée), dans l'ordre du tracé, avec leur état à chaque décennie. Une seule source,
        // App\Services\LineStations (agent « gares », mêmes données que la carte et la fiche
        // gare) pour l'en-tête, la liste, la carte et les décennies : aucun repli divergent.
        $lineStations = app(LineStations::class);
        $stations = $this->lineStations($lineStations, $line->id);

        // Événements : lecture brute (pas de cast enum → un type ajouté en base
        // par un import ne fait pas planter la fiche).
        $events = DB::table('line_events')
            ->where('line_id', $line->id)
            ->orderBy('event_date')->orderBy('id')
            ->get(['id', 'type', 'event_date', 'date_precision', 'description',
                'section_from', 'section_to', 'source', 'source_url', 'confidence'])
            ->map(fn ($e) => [
                'id' => $e->id,
                'type' => $e->type,
                'event_date' => substr((string) $e->event_date, 0, 10),
                'date_precision' => in_array($e->date_precision, ['day', 'month', 'year'], true) ? $e->date_precision : 'day',
                'section_from' => $e->section_from,
                'section_to' => $e->section_to,
                // Charte : pas de tiret long à l'affichage (descriptions importées « … - Wikipédia »).
                'description' => $e->description === null ? null : preg_replace('/\s*—\s*/u', ' - ', $e->description),
                'source' => $e->source,
                'source_url' => $e->source_url,
                'confidence' => $e->confidence,
            ])->values();

        $stats = $this->computeStats($line, $latestSegmentIds, $latestEraId, $events->count());
        // Compteur de l'en-tête = la liste des gares (toutes, fermées comprises), et parmi
        // elles celles ouvertes aujourd'hui.
        $stats['station_count'] = $stations->count();
        $stats['station_open_count'] = $stations->filter(fn ($s) => in_array($s['status_today'], StationTimeline::OPEN_STATUSES, true))->count();

        // Phases de la ligne (vraies dates de line_events, état de toute la ligne à chaque
        // phase) et chronique (événements par portion, notes de source, désaccords).
        $phases = LinePhases::forLine($line);
        $chronicle = LineChronicle::forLine($line, $phases['used_events']);
        $decades = $this->decadesWithStations($phases['decades'], $lineStations->byEra($line->id));

        // Longueurs de l'en-tête : length_km = la ligne aujourd'hui (SNCF, sinon l'ère
        // courante) ; length_greatest_km = la ligne à son plus grand développement, seulement
        // quand elle dépasse de plus de 5 % la longueur actuelle (sinon null : une seule
        // valeur). La longueur actuelle ne couvre que ce qui existe encore : sur une ligne
        // fermée ou déposée en partie, elle afficherait seule « 0,3 km » pour 16 km de ligne.
        // La fiche affiche les deux (« 29,3 km à son apogée, 13,7 km aujourd'hui »), ou l'état
        // (fermée, déposée) quand plus rien n'est en service (line.current_state).
        $knownKm = 0.0;
        foreach ($phases['phases'] as $ph) {
            $knownKm = max($knownKm, array_sum($ph['length_by_category']) / 1000);
        }
        $stats['length_greatest_km'] = $knownKm > ($stats['length_km'] ?? 0) * 1.05 ? round($knownKm, 1) : null;

        $segmentsGeoJson = [];
        if ($latestEraId) {
            $features = collect(DB::select(
                "SELECT ls.id, ls.status, COALESCE(ls.traffic_type, 'unknown') AS traffic_type,
                        ls.is_heritage, ls.max_speed_kmh, ls.source,
                        ".Lgv::sql('l', 'ls').' AS is_lgv,
                        ST_AsGeoJSON(ls.geom, 6) AS gj
                 FROM line_segments ls
                 JOIN lines l ON l.id = ls.line_id
                 WHERE ls.line_id = ? AND ls.era_id = ?
                 ORDER BY ls.pk_start_m NULLS LAST, ls.id',
                [$line->id, $latestEraId],
            ))->map(fn ($seg) => [
                'type' => 'Feature',
                'properties' => [
                    'id' => $seg->id,
                    'status' => $seg->status,
                    'traffic_type' => $seg->traffic_type,
                    'is_heritage' => (bool) $seg->is_heritage,
                    // Même règle LGV que les tuiles de la carte (couleur violette).
                    'is_lgv' => (bool) $seg->is_lgv,
                    'max_speed_kmh' => $seg->max_speed_kmh,
                    'source' => $seg->source,
                ],
                'geometry' => json_decode($seg->gj, true),
            ])->values();

            $statusCounts = $features->countBy(fn ($f) => $f['properties']['status']);
            $segmentsGeoJson[] = [
                'era_year' => (int) $displayEra->year,
                'status' => $statusCounts->sortDesc()->keys()->first(),
                'features' => $features,
            ];
        }

        $signallingRanges = $latestEraId
            ? $this->signallingRangesForLine($line->id, $latestEraId)
            : [];

        // Sections nommées (LGV Sud-Est / Rhône-Alpes / Méditerranée…)
        $sections = DB::table('line_sections')
            ->where('line_id', $line->id)
            ->orderBy('pk_start_m')
            ->get(['name', 'alt_names', 'pk_start_m', 'pk_end_m',
                'opening_year_from', 'opening_year_to', 'description', 'source_url'])
            ->map(fn ($s) => [
                'name' => $s->name,
                'alt_names' => $s->alt_names ? json_decode($s->alt_names, true) : [],
                'pk_start_m' => (int) $s->pk_start_m,
                'pk_end_m' => (int) $s->pk_end_m,
                'pk_start_label' => sprintf('%d+%03d', intdiv($s->pk_start_m, 1000), $s->pk_start_m % 1000),
                'pk_end_label' => sprintf('%d+%03d', intdiv($s->pk_end_m, 1000), $s->pk_end_m % 1000),
                'length_km' => round(($s->pk_end_m - $s->pk_start_m) / 1000, 1),
                'opening_year_from' => $s->opening_year_from,
                'opening_year_to' => $s->opening_year_to,
                'description' => $s->description,
                'source_url' => $s->source_url,
            ]);

        // Charge les métadonnées encyclopédiques + sources
        $encyclopedia = DB::table('lines')
            ->where('id', $line->id)
            ->first([
                'length_official_m', 'gauge_mm_default', 'nb_voies_default',
                'max_speed_kmh_official', 'concession_year', 'wikipedia_title',
                'archeo_url', 'regions', 'departements', 'historical_operators',
                'encyclopedia_updated_at', 'network',
            ]);
        $sources = DB::table('line_sources')->where('line_id', $line->id)
            ->get(['source_type', 'source_url', 'fetched_at', 'fields_provided']);

        $currentSummary = $eraSummary->firstWhere('year', (int) ($displayEra->year ?? 0));

        return Inertia::render('Lines/Show', [
            'line' => [
                'id' => $line->id,
                'slug' => $line->slug,
                'name' => $line->name,
                // Autres noms, avec leur source (cf. historicalNames) ; `alt_names` = les noms seuls.
                'alt_names' => array_column($names = $this->historicalNames($line), 'name'),
                'names' => $names,
                'wikidata' => is_array($line->alt_names) ? ($line->alt_names['wikidata'] ?? null) : null,
                'code' => $line->code,
                'type' => $line->getRawOriginal('type'), // nullable (colonne non curée)
                'network' => $encyclopedia->network ?? null, // principal | secondaire | militaire | particulier
                'display_era_year' => $displayEra ? (int) $displayEra->year : null,
                'current_traffic' => $currentSummary['traffic'] ?? null,
                'current_state' => $currentSummary['state'] ?? null,
                'is_threatened' => (bool) $line->is_threatened,
                // {kind, title, message, cta_label, cta_url, lang} dans la langue de la page, ou null.
                'advocacy' => Advocacy::localized($line->advocacy, app()->getLocale()),
                'historical_operator' => $line->historical_operator,
                'description' => $line->description === null ? null : LineChronicle::dashes($line->description),
                'description_source' => $this->descriptionSource($line),
                'length_official_m' => $encyclopedia->length_official_m ?? null,
                'gauge_mm_default' => $encyclopedia->gauge_mm_default ?? null,
                'nb_voies_default' => $encyclopedia->nb_voies_default ?? null,
                'max_speed_kmh_official' => $encyclopedia->max_speed_kmh_official ?? null,
                'concession_year' => $encyclopedia->concession_year ?? null,
                'wikipedia_title' => $encyclopedia->wikipedia_title ?? null,
                'archeo_url' => $encyclopedia->archeo_url ?? null,
                'regions' => $encyclopedia->regions ? json_decode($encyclopedia->regions, true) : [],
                'departements' => $encyclopedia->departements ? json_decode($encyclopedia->departements, true) : [],
                'historical_operators' => $encyclopedia->historical_operators
                    ? json_decode($encyclopedia->historical_operators, true)
                    : [],
                'encyclopedia_updated_at' => $encyclopedia->encyclopedia_updated_at ?? null,
                // Bloc « Fiche » : chaque valeur avec ce qu'elle décrit (ligne entière, ou élément
                // Wikidata qui n'en décrit qu'une partie), sa source et sa date (cf. LineFacts).
                'facts' => LineFacts::build($line, $encyclopedia, $stats, $displayEra ? (int) $displayEra->year : null,
                    (bool) ($displayEra->is_current ?? false)),
            ],
            'sources' => $sources->map(fn ($s) => [
                'type' => $s->source_type,
                'url' => $s->source_url,
                'fetched_at' => $s->fetched_at,
                'fields' => $s->fields_provided ? json_decode($s->fields_provided, true) : [],
            ]),
            'stats' => $stats,
            'segments' => $segmentsGeoJson,
            'era_summary' => $eraSummary,
            // Carte de la fiche : portions (géométrie une fois), phases datées, phase « au meilleur ».
            'portions' => $phases['portions'],
            'phases' => $phases['phases'],
            'best_phase' => $phases['best'],
            // Ère courante : écarts entre l'état reconstitué de la dernière décennie et les sources
            // d'aujourd'hui qu'aucun événement ne date (pas des phases, cf. LinePhases).
            'phases_unexplained' => $phases['unexplained'],
            'decades' => $decades,
            'chronicle' => $chronicle,
            'stations' => $stations,
            'signalling' => $signallingRanges,
            'sections' => $sections,
            'events' => $events,
            // Exploitations associatives (trains touristiques, vélorails), cf. vff:import-heritage-lines.
            'heritage' => $this->heritageOperations($line->id),
            'materials' => $line->materials->map(fn ($m) => [
                'slug' => $m->slug,
                'name' => $m->name,
                'type' => $m->type,
                'builder' => $m->builder,
                'in_service_from' => $m->in_service_from,
                'in_service_to' => $m->in_service_to,
            ]),
        ]);
    }

    /**
     * Tranches PK de signalisation pour une ligne et une ère, depuis
     * `line_signalling` (source : SNCF Réseau Open Data + curation ETCS).
     *
     * @return array<array{
     *     pk_start_m: int, pk_end_m: int, pk_start_label: string,
     *     pk_end_label: string, length_m: int, signalling: string,
     *     train_protection: array<string>, kvb_equipped: bool, note: ?string
     * }>
     */
    private function signallingRangesForLine(int $lineId, int $eraId): array
    {
        $rows = DB::table('line_signalling')
            ->where('line_id', $lineId)
            ->where('era_id', $eraId)
            ->orderBy('pk_start_m')
            ->get(['pk_start_m', 'pk_end_m', 'signalling', 'train_protection', 'kvb_equipped', 'note'])
            ->all();

        return array_map(fn ($r) => [
            'pk_start_m' => (int) $r->pk_start_m,
            'pk_end_m' => (int) $r->pk_end_m,
            'pk_start_label' => $this->formatPkLabel((int) $r->pk_start_m),
            'pk_end_label' => $this->formatPkLabel((int) $r->pk_end_m),
            'length_m' => (int) ($r->pk_end_m - $r->pk_start_m),
            'signalling' => $r->signalling,
            'train_protection' => $r->train_protection ? json_decode($r->train_protection, true) : [],
            'kvb_equipped' => (bool) $r->kvb_equipped,
            'note' => $r->note,
        ], $rows);
    }

    /** PK lisible : « 7+735 » ; avant l'origine de la ligne : « -0+250 ». */
    private function formatPkLabel(int $m): string
    {
        return ($m < 0 ? '-' : '').sprintf('%d+%03d', intdiv(abs($m), 1000), abs($m) % 1000);
    }

    /**
     * Exploitations associatives de la ligne (line_events heritage_start /
     * heritage_end) : une entrée par exploitation et par portion, avec ses
     * périodes (une interruption donne deux périodes). Le nom et l'exploitant
     * viennent de la description normalisée de vff:import-heritage-lines
     * (« Exploitation associative : NOM (exploitant : X), train touristique… »).
     *
     * @return list<array<string, mixed>>
     */
    private function heritageOperations(int $lineId): array
    {
        $rows = DB::table('line_events')->where('line_id', $lineId)
            ->whereIn('type', ['heritage_start', 'heritage_end'])
            ->orderBy('event_date')->orderBy('id')
            ->get(['type', 'heritage_kind', 'event_date', 'date_precision', 'description', 'section_from', 'section_to',
                'section_length_m', 'source_url', 'confidence']);
        $ops = [];
        foreach ($rows as $r) {
            $name = $operator = null;
            if (preg_match('/^[^:]*exploitation associative\s*:\s*(.+?),\s*(?:train touristique|vélorail|musée)/iu', (string) $r->description, $m)) {
                $name = $m[1];
                if (preg_match('/^(.+?)\s*\(exploitant : (.+)\)$/u', $name, $o)) {
                    [$name, $operator] = [$o[1], $o[2]];
                }
            }
            $key = $r->heritage_kind.'|'.$name.'|'.$r->section_length_m.'|'.$r->section_from;
            $ops[$key] ??= [
                'name' => $name, 'operator' => $operator, 'kind' => $r->heritage_kind ?? 'tourist_train',
                'from' => $r->section_from, 'to' => $r->section_to,
                'length_km' => $r->section_length_m ? round($r->section_length_m / 1000, 1) : null,
                'periods' => [], 'description' => null, 'source_url' => $r->source_url,
            ];
            $date = ['date' => substr((string) $r->event_date, 0, 10), 'precision' => $r->date_precision];
            if ($r->type === 'heritage_start') {
                $ops[$key]['periods'][] = ['start' => $date, 'end' => null,
                    // Début non daté : exploitation seulement attestée à cette date.
                    'attested' => str_contains((string) $r->description, 'date de début non trouvée')];
                $ops[$key]['description'] ??= preg_replace('/\s*—\s*/u', ' - ', (string) $r->description);
            } else {
                $last = array_key_last($ops[$key]['periods']);
                if ($last !== null && $ops[$key]['periods'][$last]['end'] === null) {
                    $ops[$key]['periods'][$last]['end'] = $date;
                }
            }
        }

        return array_values(array_filter($ops, fn ($o) => $o['periods'] !== []));
    }

    /**
     * TOUTES les gares de la ligne, quelle que soit l'ère, même fermées ou sur une portion
     * déposée, dans l'ordre du tracé, avec leurs dates et leur état à chaque décennie.
     *
     * Source unique : App\Services\LineStations::forLine (agent « gares » : station_lines,
     * station_eras, stations.closed_at, les mêmes données que la carte et la fiche gare).
     * Pas de repli (comptage spatial, line_stops d'une seule ère) : il divergerait de la carte
     * et de la fiche gare. Une ligne sans gare rattachée affiche une liste vide.
     *
     * @return Collection<int, array<mixed>>
     */
    private function lineStations(LineStations $service, int $lineId): Collection
    {
        return collect($service->forLine($lineId)['stations'])->map(fn ($s) => $this->stationProps($s))->values();
    }

    /** Gare de LineStations::forLine au format de la fiche (états par ère réduits au statut affiché). */
    private function stationProps(array $s): array
    {
        $eras = [];
        foreach ($s['eras'] ?? [] as $year => $e) {
            $status = is_array($e) ? ($e['status'] ?? null) : $e;
            if ($status !== null) {
                $eras[(int) $year] = $status;
            }
        }

        return [
            'slug' => $s['slug'],
            'name' => $s['name'],
            'type' => $s['type'] ?? null,
            'city' => $s['city'] ?? null,
            'department_code' => $s['department_code'] ?? null,
            'lng' => $s['lng'],
            'lat' => $s['lat'],
            'pk_m' => $s['pk_m'],
            'pk_label' => $s['pk_m'] === null ? null : $this->formatPkLabel((int) $s['pk_m']),
            'is_terminal' => (bool) $s['is_terminal'],
            'link_method' => $s['link_method'] ?? null,
            'served_from' => $s['served_from'] ?? null,
            'served_until' => $s['served_until'] ?? null,
            'opened_at' => $s['opened_at'],
            'opened_precision' => $s['opened_precision'],
            'closed_at' => $s['closed_at'],
            'closed_precision' => $s['closed_precision'],
            'closed_after' => $s['closed_after'] ?? null,
            'closed_year' => $s['closed_year'] ?? null,
            // Statut du jour (null : la gare n'existe plus) ; repris par era_status pour la carte.
            'status_today' => $s['status_today'],
            'era_status' => $s['status_today'],
            'effective_status' => $s['status_today'],
            'eras' => (object) $eras,
        ];
    }

    /**
     * Décennie par décennie : km par catégorie (LinePhases) et, pour les gares, le résumé
     * App\Services\LineStations::byEra tel quel (ouvertes, incertaines, fermées, gares
     * fermées dans la décennie avec la date de la fiche gare).
     *
     * @param  list<array<string, mixed>>  $decades
     * @param  list<array<string, mixed>>  $byEra
     * @return list<array<string, mixed>>
     */
    private function decadesWithStations(array $decades, array $byEra): array
    {
        $byYear = [];
        foreach ($byEra as $e) {
            $byYear[(int) $e['year']] = $e;
        }
        foreach ($decades as &$d) {
            $e = $byYear[(int) $d['year']] ?? null;
            $d['stations'] = [
                'open' => $e['open'] ?? 0,
                'uncertain' => $e['uncertain'] ?? 0,
                'closed' => $e['closed'] ?? 0,
                'closures' => array_map(fn ($c) => [
                    'slug' => $c['slug'], 'name' => $c['name'], 'closed_at' => $c['closed_at'],
                    'closed_precision' => $c['closed_precision'], 'closed_after' => $c['closed_after'] ?? null,
                ], $e['closures'] ?? []),
            ];
        }
        unset($d);

        return $decades;
    }

    /**
     * Autres noms de la ligne, chacun avec sa source : noms successifs et sections nommées
     * (vff:import-line-names), sections nommées (line_sections), nom lignes-oubliees, noms
     * archeoferroviaire, fiches fusionnées, titre de l'article Wikipédia. Sans doublon (casse,
     * accents et ponctuation ignorés) ni le nom principal.
     *
     * @return list<array{name: string, kind: string, source: ?string, url: ?string, from: ?int, to: ?int}>
     */
    private function historicalNames(Line $line): array
    {
        $alt = is_array($line->alt_names) ? $line->alt_names : [];
        $key = fn (string $n) => preg_replace('/[^a-z0-9]+/', '', Str::lower(Str::ascii($n)));
        $seen = [$key($line->name) => true];
        $out = [];
        $add = function (?string $name, string $kind, ?string $source, ?string $url = null, ?int $from = null, ?int $to = null) use (&$out, &$seen, $key) {
            $name = $name === null ? '' : trim(LineChronicle::dashes($name));
            if ($name === '' || isset($seen[$key($name)])) {
                return;
            }
            $seen[$key($name)] = true;
            $out[] = ['name' => $name, 'kind' => $kind, 'source' => $source, 'url' => $url, 'from' => $from, 'to' => $to];
        };

        foreach (is_array($alt['names'] ?? null) ? $alt['names'] : [] as $n) {
            if (is_array($n)) {
                $add($n['name'] ?? null, $n['kind'] ?? 'name', $n['source'] ?? null, $n['source_url'] ?? null,
                    isset($n['from']) ? (int) $n['from'] : null, isset($n['to']) ? (int) $n['to'] : null);
            }
        }
        foreach (DB::table('line_sections')->where('line_id', $line->id)->orderBy('pk_start_m')
            ->get(['name', 'alt_names', 'opening_year_from', 'opening_year_to', 'source_url']) as $s) {
            $add($s->name, 'section', 'wikipedia', $s->source_url, $s->opening_year_from, $s->opening_year_to);
            foreach (json_decode((string) $s->alt_names, true) ?: [] as $a) {
                $add(is_string($a) ? $a : null, 'section', 'wikipedia', $s->source_url, $s->opening_year_from, $s->opening_year_to);
            }
        }
        if ($line->wikipedia_title) {
            $add($line->wikipedia_title, 'name', 'wikipedia', 'https://fr.wikipedia.org/wiki/'.rawurlencode(str_replace(' ', '_', $line->wikipedia_title)));
        }
        $lo = $alt['lignes_oubliees'] ?? null;
        if (is_array($lo)) {
            $add($lo['name'] ?? null, 'name', 'lignes-oubliees', $lo['url'] ?? null);
        }
        if (($alt['source'] ?? null) === 'archeoferroviaire' || ($alt['name_source'] ?? null) === 'archeoferroviaire') {
            foreach (is_array($alt['labels'] ?? null) ? $alt['labels'] : [] as $l) {
                $add(is_string($l) ? $l : null, 'name', 'archeoferroviaire', $line->archeo_url);
            }
        }
        foreach (is_array($alt['merged'] ?? null) ? $alt['merged'] : [] as $m) {
            $add(is_array($m) ? ($m['name'] ?? null) : null, 'merged', 'vff', null);
        }
        foreach (is_array($alt['sections'] ?? null) ? $alt['sections'] : [] as $m) {
            $add(is_string($m) ? $m : null, 'merged', 'vff', null);
        }

        return $out;
    }

    /**
     * Source du texte « À propos » : introduction Wikipédia (vff:import-line-descriptions,
     * line_sources « wikipedia-intro » : titre, version, licence CC BY-SA 4.0), ou sources
     * citées par la ligne déclarée à la main (database/data/lignes_hors_rfn.php).
     */
    private function descriptionSource(Line $line): ?array
    {
        if ($line->description === null) {
            return null;
        }
        $src = DB::table('line_sources')->where('line_id', $line->id)->where('source_type', 'wikipedia-intro')
            ->first(['source_url', 'fetched_at', 'fields_provided']);
        if ($src) {
            $meta = json_decode((string) $src->fields_provided, true) ?: [];

            return [
                'type' => 'wikipedia',
                'title' => $meta['title'] ?? $line->wikipedia_title,
                'url' => $src->source_url,
                'article_url' => isset($meta['title']) ? 'https://fr.wikipedia.org/wiki/'.rawurlencode(str_replace(' ', '_', $meta['title'])) : $src->source_url,
                'fetched_at' => substr((string) $src->fetched_at, 0, 10),
                'license' => 'CC BY-SA 4.0',
                'license_url' => 'https://creativecommons.org/licenses/by-sa/4.0/deed.fr',
            ];
        }
        $alt = is_array($line->alt_names) ? $line->alt_names : [];
        $urls = array_values(array_filter(is_array($alt['sources'] ?? null) ? $alt['sources'] : [], 'is_string'));

        return $urls ? ['type' => 'curated', 'urls' => $urls] : ['type' => 'curated', 'urls' => []];
    }

    private function computeStats(Line $line, array $segmentIds, ?int $eraId, int $eventCount = 0): array
    {
        if (! $segmentIds || ! $eraId) {
            return [
                'length_km' => 0.0,
                'segment_count' => 0,
                'event_count' => $eventCount,
                'max_speed_kmh' => null,
                'electrified' => false,
            ];
        }

        $stats = DB::selectOne(
            <<<'SQL'
            SELECT
                SUM(ST_Length(geom::geography)) AS total_len_m,
                SUM(CASE WHEN status IN ('in_service','single_track') THEN ST_Length(geom::geography) ELSE 0 END) AS active_len_m,
                SUM(CASE WHEN electrified THEN ST_Length(geom::geography) ELSE 0 END) AS elec_len_m,
                MAX(max_speed_kmh) AS max_speed,
                BOOL_OR(electrified) AS electrified,
                MODE() WITHIN GROUP (ORDER BY gauge_mm) AS gauge_mm,
                MIN(dismantled_at) AS dismantled_at_min,
                MAX(dismantled_at) AS dismantled_at_max
            FROM line_segments
            WHERE line_id = ? AND era_id = ?
            SQL,
            [$line->id, $eraId],
        );

        // Longueur de LIGNE (route-km) depuis la géométrie officielle SNCF
        // (centerline simple) plutôt que la somme des voies OSM (track-km, ~×2
        // en double voie). Fallback OSM pour les lignes hors référentiel SNCF.
        $sncfKm = $line->code
            ? (float) (DB::selectOne(
                'SELECT SUM(ST_Length(geom::geography)) / 1000.0 AS km FROM sncf_line_geometries WHERE code_ligne = ?',
                [$line->code],
            )?->km ?? 0)
            : 0.0;
        $totalLenM = (float) ($stats->total_len_m ?? 0);
        $routeKm = $sncfKm > 0 ? $sncfKm : $totalLenM / 1000.0;

        // Tension dominante (MODE) parmi les segments électrifiés - évite qu'une
        // valeur OSM aberrante isolée (MAX) fausse l'affichage.
        $voltageV = DB::selectOne(
            'SELECT MODE() WITHIN GROUP (ORDER BY voltage_v) AS v
             FROM line_segments WHERE line_id = ? AND era_id = ? AND electrified AND voltage_v IS NOT NULL',
            [$line->id, $eraId],
        )?->v;

        $electrifiedPct = $totalLenM > 0 ? (int) round((float) $stats->elec_len_m / $totalLenM * 100) : 0;

        // Signalisation : agrégation depuis line_signalling (vérité officielle
        // SNCF Réseau Open Data), pas depuis line_segments.signalling qui
        // n'est qu'une vue de l'ancien fallback approximatif.
        $sigAgg = DB::selectOne(
            "SELECT STRING_AGG(DISTINCT signalling, ', ' ORDER BY signalling) AS sigs,
                    BOOL_OR(kvb_equipped) AS any_kvb
             FROM line_signalling WHERE line_id = ? AND era_id = ?",
            [$line->id, $eraId],
        );
        $protAgg = DB::select(
            'SELECT DISTINCT jsonb_array_elements_text(train_protection::jsonb) AS p
             FROM line_signalling WHERE line_id = ? AND era_id = ? AND train_protection IS NOT NULL',
            [$line->id, $eraId],
        );
        $trainProtection = array_map(fn ($r) => $r->p, $protAgg);
        sort($trainProtection);

        return [
            'length_km' => round($routeKm, 1),                              // route-km (SNCF officiel)
            'length_track_km' => round($totalLenM / 1000.0, 1),             // track-km (voies OSM cumulées)
            'active_length_km' => round((float) $stats->active_len_m / 1000.0, 1),
            'segment_count' => count($segmentIds),
            'event_count' => $eventCount,
            'max_speed_kmh' => $stats->max_speed,
            'electrified' => $electrifiedPct > 0,
            'electrified_pct' => $electrifiedPct,
            'voltage_v' => $voltageV,
            'gauge_mm' => $stats->gauge_mm,
            'signalling' => $sigAgg?->sigs,
            'train_protection' => $trainProtection ? implode(', ', $trainProtection) : null,
            'kvb_equipped' => (bool) ($sigAgg?->any_kvb ?? false),
            'dismantled_at_min' => $stats->dismantled_at_min,
            'dismantled_at_max' => $stats->dismantled_at_max,
        ];
    }
}

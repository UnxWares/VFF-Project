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
use App\Support\FrenchGrammar;
use App\Support\Lgv;
use Illuminate\Database\Eloquent\Builder;
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

    /** Part électrifiée (%) sous laquelle une ligne n'est électrifiée qu'à la marge (voies d'une gare, raccordement). */
    public const MARGINAL_ELECTRIFIED_PCT = 5;

    /** Gestionnaires d'infrastructure (Wikidata) : jamais « exploitants » des trains, avec leurs années d'existence. */
    public const INFRASTRUCTURE_MANAGERS = [
        'Q1071978' => ['name' => 'Réseau ferré de France', 'from' => 1997, 'to' => 2014],
        'Q21605526' => ['name' => 'SNCF Réseau', 'from' => 2015, 'to' => null],
    ];

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

        $sort = $this->filterString($request, 'sort');
        $sort = in_array($sort, ['name', 'name-desc', 'code'], true) ? $sort : 'name';

        $lines = $this->applyIndexOrder(
            $applyFilters(Line::query()->select('id', 'slug', 'code', 'name', 'network', 'historical_operator')),
            $sort,
            $q,
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
            'heritage' => $summaries[$l->id]['heritage'] ?? false,
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

        // Aperçu de l'encyclopédie entière (sans filtre) : le chapeau de la page ne change pas avec la recherche.
        $overallTotal = Line::query()->count();
        $overallOpen = Line::query()->whereExists($activeSegments)->count();
        $overallClosed = $closed(Line::query())->count();

        return Inertia::render('Lines/Index', [
            'overview' => [
                'total' => $overallTotal,
                'open' => $overallOpen,
                'closed' => $overallClosed,
                'unknown' => max(0, $overallTotal - $overallOpen - $overallClosed),
            ],
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
                'sort' => $sort,
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
     * Ordre de l'annuaire : alphabétique FRANÇAIS (accents et capitales sans effet sur le rang, collation ICU),
     * et, pour le tri par nom avec une recherche, le nom exact puis les noms qui commencent par le terme.
     *
     * @param  Builder<Line>  $builder
     * @return Builder<Line>
     */
    private function applyIndexOrder($builder, string $sort, string $q)
    {
        $name = 'name COLLATE "fr-x-icu"';

        if ($sort === 'code') {
            return $builder->orderByRaw("code IS NULL, code COLLATE \"fr-x-icu\", {$name}, id");
        }
        if ($sort === 'name-desc') {
            return $builder->orderByRaw("{$name} DESC, id");
        }
        if ($q !== '') {
            $builder->orderByRaw(
                'CASE WHEN lower(name) = lower(?) THEN 0 WHEN name ILIKE ? THEN 1 ELSE 2 END',
                [$q, $this->prefixPattern($q)],
            );
        }

        return $builder->orderByRaw("{$name}, id");
    }

    /**
     * Pour un lot de lignes : état à l'ère courante (open = au moins une portion
     * en service, closed = tracé connu mais plus aucune portion en service) et
     * trafic dominant pondéré par la longueur des portions en service.
     *
     * @param  array<int>  $lineIds
     * @return array<int, array{state: string, traffic: ?string, heritage: bool}>
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
                       COALESCE(is_heritage, false) AS is_heritage,
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
            SELECT s.line_id, BOOL_OR(s.active) AS is_open, MAX(t.traffic_type) AS traffic, BOOL_OR(s.is_heritage) AS heritage
            FROM s LEFT JOIN t ON t.line_id = s.line_id
            GROUP BY s.line_id",
            [$eraId, ...$lineIds],
        );

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->line_id] = [
                'state' => $r->is_open ? 'open' : 'closed',
                'traffic' => $r->traffic,
                'heritage' => (bool) $r->heritage,
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

        // Trafic dominant d'une ère : un trafic connu passe avant « non renseigné », même plus court
        // (Nice - Digne : « Trafic non renseigné » en tête d'une ligne desservie par des trains régionaux).
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
                      ORDER BY COALESCE(x.traffic_type, \'unknown\') = \'unknown\', SUM(COALESCE(x.length_meters, ST_Length(x.geom::geography))) DESC LIMIT 1) AS traffic
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
        $decades = $this->decadesWithStations($phases['decades'], $lineStations->byEra($line->id));

        // UNE longueur par fiche (chantier « UI unifiée », constats « six longueurs pour la même
        // ligne ») : celle du tracé que VFF dessine, toutes portions comprises (en service, fermées,
        // déposées), à son plus grand développement. C'est la longueur du rail « Aujourd'hui », des
        // phases, de la carte, du tableau des décennies et de la portion « Toute la ligne » de la
        // chronique. Avant : longueur des formes SNCF (généralisées, 121 km sur 610000 pour un tracé
        // de 124,7 km), sans rapport avec le reste de la page ; elle reste en `length_sncf_km`.
        // `active_length_km` = la part en service aujourd'hui, de la même mesure ; plus de second
        // chiffre « à son apogée » (`length_greatest_km` toujours null).
        $lineLengthM = self::lineLengthM($phases);
        if ($lineLengthM > 0) {
            $stats['length_km'] = round($lineLengthM / 1000, 1);
            $today = collect($phases['decades'])->firstWhere('is_current', true) ?? collect($phases['decades'])->last();
            if ($today !== null) {
                $stats['active_length_km'] = round(array_sum(array_intersect_key($today['length_by_category'], array_flip(LinePhases::ACTIVE))) / 1000, 1);
            }
        }
        $stats['length_greatest_km'] = null;
        $stats['length_definition'] = $lineLengthM > 0 ? 'trace' : ($stats['length_sncf_km'] !== null ? 'sncf' : 'segments');
        $chronicle = LineChronicle::forLine($line, $phases['used_events'], $stations->all(), $lineLengthM > 0 ? $lineLengthM : null);

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
                // Description tirée de Wikipédia, en français : lang="fr" sur une page EN ou DE.
                'description_lang' => $s->description !== null && app()->getLocale() !== 'fr' ? 'fr' : null,
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
                // Typographie adaptée (annoncée sous l'extrait) : « no 752 000 » de l'article → « n° 752 000 ».
                'description' => $line->description === null ? null
                    : (string) preg_replace('/(?<!\p{L})no (?=\d)/u', 'n° ', LineChronicle::dashes($line->description)),
                'description_source' => $this->descriptionSource($line),
                // Langue des textes de la ligne qui restent en français sur une page EN ou DE (extrait de
                // Wikipédia, nom officiel et autres noms : noms propres français) : attribut lang.
                'description_lang' => $line->description !== null && app()->getLocale() !== 'fr' ? 'fr' : null,
                'name_lang' => app()->getLocale() !== 'fr' ? 'fr' : null,
                // Ligne à grande vitesse (même règle que la carte) : signalisation en cabine, pas de BAL général.
                'is_lgv' => (bool) ($line->is_lgv ?? false) || Lgv::nameIsLgv($line->name),
                'length_official_m' => $encyclopedia->length_official_m ?? null,
                'gauge_mm_default' => $encyclopedia->gauge_mm_default ?? null,
                'nb_voies_default' => $encyclopedia->nb_voies_default ?? null,
                'max_speed_kmh_official' => $encyclopedia->max_speed_kmh_official ?? null,
                'concession_year' => $encyclopedia->concession_year ?? null,
                'wikipedia_title' => $encyclopedia->wikipedia_title ?? null,
                'archeo_url' => $encyclopedia->archeo_url ?? null,
                'regions' => $encyclopedia->regions ? json_decode($encyclopedia->regions, true) : [],
                'departements' => $encyclopedia->departements ? json_decode($encyclopedia->departements, true) : [],
                // Rôles et années relus (cf. operators()) : gestionnaire d'infrastructure, jamais
                // « exploitant » avant la mise en service ; `ongoing` = toujours en fonction.
                'historical_operators' => self::operators(
                    $encyclopedia->historical_operators ? (array) json_decode($encyclopedia->historical_operators, true) : [],
                    self::firstOpeningYear($phases['phases']),
                ),
                'encyclopedia_updated_at' => $encyclopedia->encyclopedia_updated_at ?? null,
                // Bloc « Fiche » : chaque valeur avec ce qu'elle décrit (ligne entière, ou élément
                // Wikidata qui n'en décrit qu'une partie), sa source et sa date (cf. LineFacts).
                'facts' => LineFacts::build($line, $encyclopedia, $stats, $displayEra ? (int) $displayEra->year : null,
                    (bool) ($displayEra->is_current ?? false)),
            ],
            // Données de la fiche : sources de la ligne (line_sources, lien réparé) et sources des
            // tronçons affichés (tracé, vitesses, électrification, signalisation), cf. dataSources().
            'sources' => $this->dataSources($line, $sources, $latestEraId),
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
            // Sans la signalisation en cabine déjà nommée (« TVM 300 » une fois, pas deux).
            'train_protection' => array_values(array_diff($r->train_protection ? (array) json_decode($r->train_protection, true) : [], [$r->signalling])),
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
     * Longueur de la ligne (m) : le plus grand total, toutes catégories (en service, fermée, déposée),
     * des phases et des décennies de LinePhases, c'est-à-dire l'échelle du rail de l'en-tête. 0 sans
     * tracé.
     *
     * @param  array{phases: list<array<string, mixed>>, decades: list<array<string, mixed>>}  $phases
     */
    public static function lineLengthM(array $phases): int
    {
        $max = 0;
        foreach ([...$phases['phases'], ...$phases['decades']] as $p) {
            $max = max($max, (int) array_sum($p['length_by_category'] ?? []));
        }

        return $max;
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
        // Même nom à la forme près : « Ligne Limoges-Bénédictins - Angoulême », « ligne de St-Germain-des-Fossés
        // à Darsac » ou « Voie ferrée Angoulême-Limoges » = mêmes lieux (ordre, articles, « St » / « Saint »,
        // « (LGV) » ignorés) : un seul nom, et jamais le titre lui-même (cf. nameKey).
        $key = fn (string $n) => self::nameKey($n);
        $seen = [$key($line->name) => true];
        $out = [];
        $add = function (?string $name, string $kind, ?string $source, ?string $url = null, ?int $from = null, ?int $to = null) use (&$out, &$seen, $key) {
            $name = $name === null ? '' : trim(LineChronicle::dashes($name));
            if ($name === '') {
                return;
            }
            $k = $key($name);
            if (isset($seen[$k])) {
                // Deux formes du même nom : celle qui commence par « Ligne » plutôt que « Voie ferrée ».
                $i = $seen[$k];
                if ($i !== true && ! preg_match('/^ligne\b/iu', $out[$i]['name']) && preg_match('/^ligne\b/iu', $name)) {
                    $out[$i]['name'] = FrenchGrammar::ucfirst($name);
                }

                return;
            }
            $seen[$k] = count($out);
            $out[] = ['name' => FrenchGrammar::ucfirst($name), 'kind' => $kind, 'source' => $source, 'url' => $url, 'from' => $from, 'to' => $to];
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

        // Nom d'une AUTRE ligne de VFF (Wikidata cite parfois un raccordement parmi les alias : 610000
        // « Raccordement de Limoges-Puy-Imbert à Limoges-Montjovis », qui est la ligne 590306) : ce
        // n'est pas un autre nom de celle-ci. Les fiches fusionnées (kind merged) restent.
        $others = $out === [] ? [] : array_flip(array_map(fn ($n) => Str::lower($n), DB::table('lines')->where('id', '<>', $line->id)
            ->whereIn(DB::raw('lower(name)'), array_map(fn ($n) => Str::lower($n['name']), $out))->pluck('name')->all()));

        return array_values(array_filter($out, fn ($n) => $n['kind'] === 'merged' || ! isset($others[Str::lower($n['name'])])));
    }

    /** Mots qui ne distinguent pas deux noms de la même ligne (article, « ligne », « voie ferrée »…). */
    private const NAME_STOPWORDS = ['ligne', 'lignes', 'voie', 'ferree', 'chemin', 'chemins', 'fer', 'de', 'd', 'du', 'des',
        'a', 'au', 'aux', 'la', 'le', 'les', 'l', 'et'];

    /**
     * Clé d'un nom de ligne : ses lieux, sans ordre ni article, « St » = « Saint », sans la mention
     * « (LGV) ». « Ligne de St-Germain-des-Fossés à Darsac » = « ligne Saint-Germain-des-Fossés - Darsac ».
     */
    public static function nameKey(string $name): string
    {
        $n = Str::lower(Str::ascii((string) preg_replace('/\s*\((?:lgv|ligne a grande vitesse)\)\s*$/iu', '', $name)));
        $words = array_filter(preg_split('/[^a-z0-9]+/', $n) ?: [], fn ($w) => $w !== '' && ! in_array($w, self::NAME_STOPWORDS, true));
        $words = array_map(fn ($w) => match ($w) {
            'st' => 'saint',
            'ste' => 'sainte',
            default => $w,
        }, $words);
        sort($words);

        return implode(' ', $words);
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

    /** Année de la première mise en service de la ligne (première phase d'ouverture datée), ou null. */
    public static function firstOpeningYear(array $phases): ?int
    {
        foreach ($phases as $ph) {
            if (($ph['kind'] ?? null) === 'growth' && ($ph['precision'] ?? null) !== 'range') {
                return (int) substr((string) $ph['date'], 0, 4);
            }
        }

        return null;
    }

    /**
     * Exploitants de la ligne (Wikidata P137 / P127), relus pour la fiche :
     *   - Réseau ferré de France et SNCF Réseau gèrent l'infrastructure, ils n'exploitent pas de
     *     trains : rôle `gestionnaire`, années bornées par leur existence (RFF : 1997-2014) ;
     *   - un exploitant ne l'est pas avant la mise en service : début ramené à l'année d'ouverture
     *     (« Compagnie des Charentes 1868-1878 » sur une ligne ouverte en 1875 : 1875-1878 ; la date
     *     de 1868 est celle de la concession) ;
     *   - `ongoing` : toujours en fonction (début connu, pas de fin) : « depuis 2015 ».
     *
     * @param  array<mixed>  $operators  valeurs JSON de lines.historical_operators
     * @return list<array<string, mixed>>
     */
    public static function operators(array $operators, ?int $openingYear): array
    {
        $out = [];
        foreach ($operators as $o) {
            if (! is_array($o)) {
                continue;
            }
            $manager = self::INFRASTRUCTURE_MANAGERS[$o['wikidata'] ?? ''] ?? null;
            foreach (self::INFRASTRUCTURE_MANAGERS as $m) {
                $manager ??= ($o['operator'] ?? null) === $m['name'] ? $m : null;
            }
            $from = isset($o['from_year']) ? (int) $o['from_year'] : null;
            $to = isset($o['to_year']) ? (int) $o['to_year'] : null;
            if ($manager !== null) {
                if (($o['role'] ?? null) === 'exploitant') {
                    $o['role'] = 'gestionnaire';
                }
                $from = $from === null ? $manager['from'] : max($from, $manager['from']);
                $to = $manager['to'] === null ? $to : ($to === null ? $manager['to'] : min($to, $manager['to']));
            } elseif (($o['role'] ?? null) === 'exploitant' && $openingYear !== null && $from !== null && $from < $openingYear
                && ($to === null || $to >= $openingYear)) {
                $from = $openingYear;
            }
            $o['from_year'] = $from;
            $o['to_year'] = $to;
            $o['ongoing'] = $from !== null && $to === null;
            $out[] = $o;
        }

        return $out;
    }

    /** Jeux de données SNCF Réseau (Open Data) cités par la fiche. */
    private const SNCF_DATASETS = 'https://ressources.data.sncf.com/explore/dataset/';

    /**
     * Données de la fiche (« Notes et sources ») : les sources de la ligne (line_sources) et celles des
     * tronçons affichés, chacune une fois (type unique), avec un lien valide :
     *   - classement SNCF : lien vers le portail (l'adresse stockée porte la liste des jeux entre
     *     parenthèses, `datasets`) ; omis quand aucun signal SNCF n'a servi (ligne hors réseau ferré
     *     national, comme le train des Pignes) ;
     *   - tracé : OpenStreetMap, IGN BD TOPO, OpenHistoricalMap, archeoferroviaire, formes SNCF selon
     *     les tronçons ; vitesses, électrification et signalisation SNCF quand elles sont affichées.
     *
     * @param  Collection<int, \stdClass>  $sources
     * @return list<array<string, mixed>>
     */
    private function dataSources(Line $line, Collection $sources, ?int $eraId): array
    {
        /** @var array<string, array<string, mixed>> $out */
        $out = [];
        foreach ($sources as $s) {
            $fields = $s->fields_provided ? (array) json_decode($s->fields_provided, true) : [];
            $entry = ['type' => $s->source_type, 'url' => $s->source_url, 'fetched_at' => $s->fetched_at, 'fields' => $fields];
            if ($s->source_type === 'sncf-classification') {
                if (($fields['signal'] ?? null) === 'aucun') {
                    continue;
                }
                if (preg_match('#^(https?://\S+?)/?\s*\((.+)\)\s*$#u', (string) $s->source_url, $m)) {
                    $entry['url'] = $m[1].'/';
                    $entry['datasets'] = array_values(array_filter(array_map('trim', explode(',', str_replace('…', '', $m[2])))));
                }
            }
            $out[$s->source_type] = $entry;
        }
        if ($eraId !== null) {
            $seg = DB::selectOne(<<<'SQL'
                SELECT ARRAY_TO_JSON(ARRAY_AGG(DISTINCT source) FILTER (WHERE source IS NOT NULL)) AS traces,
                       BOOL_OR(max_speed_source = 'sncf') AS sncf_speed,
                       BOOL_OR(electrification_source = 'sncf') AS sncf_elec,
                       EXISTS (SELECT 1 FROM line_signalling WHERE line_id = ? AND era_id = ?) AS sncf_sig
                FROM line_segments WHERE line_id = ? AND era_id = ?
            SQL, [$line->id, $eraId, $line->id, $eraId]);
            $traces = [
                'osm' => 'https://www.openstreetmap.org/',
                'bdtopo' => 'https://geoservices.ign.fr/bdtopo',
                'ohm' => 'https://www.openhistoricalmap.org/',
                'archeoferroviaire' => $line->archeo_url ?: 'http://archeoferroviaire.free.fr/',
                'sncf' => self::SNCF_DATASETS.'formes-des-lignes-du-rfn/',
            ];
            foreach ((array) json_decode((string) ($seg->traces ?? '[]'), true) as $src) {
                $type = $src === 'sncf' ? 'sncf-shapes' : $src;
                if (array_key_exists($src, $traces) && ! isset($out[$type])) {
                    $out[$type] = ['type' => $type, 'url' => $traces[$src], 'fetched_at' => null, 'fields' => ['geometry']];
                }
            }
            foreach ([
                'sncf-speed' => [(bool) ($seg->sncf_speed ?? false), 'vitesse-maximale-nominale-sur-ligne', 'max_speed_kmh'],
                'sncf-electrification' => [(bool) ($seg->sncf_elec ?? false), 'liste-des-lignes-electrifiees', 'electrified'],
                'sncf-signalling' => [(bool) ($seg->sncf_sig ?? false), 'mode-de-cantonnement-des-lignes', 'signalling'],
            ] as $type => [$used, $dataset, $field]) {
                if ($used) {
                    $out[$type] = ['type' => $type, 'url' => self::SNCF_DATASETS.$dataset.'/', 'fetched_at' => null, 'fields' => [$field]];
                }
            }
        }

        return array_values($out);
    }

    private function computeStats(Line $line, array $segmentIds, ?int $eraId, int $eventCount = 0): array
    {
        if (! $segmentIds || ! $eraId) {
            return [
                'length_km' => 0.0,
                'length_sncf_km' => null,
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
        // n'est qu'une vue de l'ancien fallback approximatif. Chaque mode avec la
        // longueur qu'il couvre, du plus étendu au moins étendu (sur une LGV, la TVM
        // avant le BAL des raccordements) ; contrôle de vitesse sans la signalisation
        // en cabine déjà citée (la TVM est les deux : une seule mention).
        $sigAgg = DB::selectOne(
            'SELECT BOOL_OR(kvb_equipped) AS any_kvb FROM line_signalling WHERE line_id = ? AND era_id = ?',
            [$line->id, $eraId],
        );
        $signallingCoverage = array_map(fn ($r) => ['code' => $r->code, 'km' => round((float) $r->m / 1000, 1)], DB::select(
            'SELECT signalling AS code, SUM(GREATEST(pk_end_m - pk_start_m, 0)) AS m
             FROM line_signalling WHERE line_id = ? AND era_id = ? AND signalling IS NOT NULL
             GROUP BY signalling ORDER BY m DESC, signalling',
            [$line->id, $eraId],
        ));
        $cabCodes = array_values(array_filter(array_column($signallingCoverage, 'code'), fn ($c) => (bool) preg_match('/^(TVM-|ETCS_|ERTMS_)/', (string) $c)));
        $protectionCoverage = array_values(array_filter(array_map(fn ($r) => ['code' => $r->code, 'km' => round((float) $r->m / 1000, 1)], DB::select(
            'SELECT p.code, SUM(GREATEST(ls.pk_end_m - ls.pk_start_m, 0)) AS m
             FROM line_signalling ls, jsonb_array_elements_text(ls.train_protection::jsonb) AS p(code)
             WHERE ls.line_id = ? AND ls.era_id = ? AND ls.train_protection IS NOT NULL
             GROUP BY p.code ORDER BY m DESC, p.code',
            [$line->id, $eraId],
        )), fn ($p) => ! in_array($p['code'], $cabCodes, true)));
        $trainProtection = array_column($protectionCoverage, 'code');

        // Électrification : part du tracé de l'ère et tensions, chacune avec sa longueur (Paris - Brest :
        // 1,5 kV jusqu'au Mans, 25 kV au-delà ; une seule tension « dominante » cachait l'autre).
        // state : none, marginal (moins de MARGINAL_ELECTRIFIED_PCT % : voies d'une gare, raccordement),
        // partial, full (95 % et plus).
        $voltages = array_map(fn ($r) => ['voltage_v' => (int) $r->v, 'km' => round((float) $r->m / 1000, 1)], DB::select(
            'SELECT voltage_v AS v, SUM(ST_Length(geom::geography)) AS m FROM line_segments
             WHERE line_id = ? AND era_id = ? AND electrified AND voltage_v IS NOT NULL
             GROUP BY voltage_v ORDER BY m DESC',
            [$line->id, $eraId],
        ));
        $elecKm = round((float) $stats->elec_len_m / 1000, 1);
        $speedKm = $stats->max_speed ? round((float) (DB::selectOne(
            'SELECT SUM(ST_Length(geom::geography)) AS m FROM line_segments WHERE line_id = ? AND era_id = ? AND max_speed_kmh = ?',
            [$line->id, $eraId, $stats->max_speed],
        )->m ?? 0) / 1000, 1) : null;

        return [
            'signalling_coverage' => $signallingCoverage,
            'train_protection_coverage' => $protectionCoverage,
            'electrification' => [
                'state' => match (true) {
                    $elecKm <= 0 => 'none',
                    $electrifiedPct < self::MARGINAL_ELECTRIFIED_PCT => 'marginal',
                    $electrifiedPct >= 95 => 'full',
                    default => 'partial',
                },
                'km' => $elecKm,
                'pct' => $electrifiedPct,
                'voltages' => $voltages,
            ],
            // Longueur (km) des tronçons à la vitesse maximale : « 160 km/h » sur 9 km d'une ligne de 145 km.
            'max_speed_km' => $speedKm,
            'length_km' => round($routeKm, 1),                              // remplacée dans show() par la longueur du tracé
            'length_sncf_km' => $sncfKm > 0 ? round($sncfKm, 1) : null,     // formes des lignes SNCF (généralisées)
            'length_track_km' => round($totalLenM / 1000.0, 1),             // track-km (voies OSM cumulées)
            'active_length_km' => round((float) $stats->active_len_m / 1000.0, 1),
            'segment_count' => count($segmentIds),
            'event_count' => $eventCount,
            'max_speed_kmh' => $stats->max_speed,
            'electrified' => $electrifiedPct > 0,
            'electrified_pct' => $electrifiedPct,
            'voltage_v' => $voltageV,
            'gauge_mm' => $stats->gauge_mm,
            'signalling' => $signallingCoverage ? implode(', ', array_column($signallingCoverage, 'code')) : null,
            'train_protection' => $trainProtection ? implode(', ', $trainProtection) : null,
            'kvb_equipped' => (bool) ($sigAgg?->any_kvb ?? false),
            'dismantled_at_min' => $stats->dismantled_at_min,
            'dismantled_at_max' => $stats->dismantled_at_max,
        ];
    }
}

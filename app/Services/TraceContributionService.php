<?php

namespace App\Services;

use App\Enums\LineEventType;
use App\Models\Contribution;
use App\Models\Line;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Contributions « tracé » (type = line_trace) : une ligne disparue dessinée
 * sur fond de carte historique, ou la correction d'une portion existante,
 * découpée en PORTIONS portant chacune leurs événements datés et sourcés.
 *
 * Cycle de vie :
 *   1. normalize() - valide et normalise la saisie de l'éditeur (géométrie
 *      contrôlée par PostGIS, dates selon leur précision, source obligatoire)
 *      et calcule l'instantané « avant » des tracés remplacés ;
 *   2. modération (2 validateurs, cf. ContributionController::approve) ;
 *   3. apply() - écrit dans `source_traces` (source='contrib', source_ref = id
 *      de contribution) et `line_events` (source='contrib', section_geom,
 *      section_resolution='trace'), selon le contrat des migrations
 *      2026_10_15_110000 / 111000. Idempotent (upsert sur source_ref et
 *      fingerprint) : ré-appliquer une contribution ne duplique rien.
 *
 * La matérialisation par ère dans `line_segments` reste du ressort du
 * pipeline source_traces → line_segments ; `properties.supersedes` lui
 * indique quels tracés/tronçons la contribution remplace.
 */
class TraceContributionService
{
    public const TYPE = 'line_trace';

    public const NETWORKS = ['principal', 'secondaire', 'militaire', 'particulier'];

    public const PRECISIONS = ['day', 'month', 'year'];

    public const MAX_PORTIONS = 60;

    public const MAX_POINTS = 8000;

    /** Types d'événements proposés dans l'éditeur (sous-ensemble de LineEventType). */
    public static function eventTypes(): array
    {
        $wanted = [
            LineEventType::Opening, LineEventType::OpeningPassenger, LineEventType::OpeningFreight,
            LineEventType::ClosurePassenger, LineEventType::ClosureFreight, LineEventType::Closure,
            LineEventType::Reopening, LineEventType::ReopeningPassenger, LineEventType::ReopeningFreight,
            LineEventType::Dismantling, LineEventType::Electrification, LineEventType::Declassification,
            LineEventType::Other,
        ];

        return array_map(fn (LineEventType $t) => $t->value, $wanted);
    }

    /** Règles de validation du formulaire de l'éditeur. */
    public static function rules(): array
    {
        return [
            'kind' => 'required|in:new_line,correction',
            'line_slug' => 'nullable|required_if:kind,correction|string|exists:lines,slug',
            'new_line_name' => 'nullable|required_if:kind,new_line|string|min:3|max:250',
            'network' => 'required|in:'.implode(',', self::NETWORKS),
            'basemap' => 'nullable|string|max:60',
            'trace_source' => 'required|string|min:3|max:500',
            'notes' => 'nullable|string|max:2000',
            'portions' => 'required|array|min:1|max:'.self::MAX_PORTIONS,
            'portions.*.key' => 'required|string|max:24',
            'portions.*.label' => 'nullable|string|max:250',
            'portions.*.from' => 'nullable|string|max:250',
            'portions.*.to' => 'nullable|string|max:250',
            'portions.*.coordinates' => 'required|array|min:2|max:'.self::MAX_POINTS,
            'portions.*.coordinates.*' => 'required|array|size:2',
            'portions.*.coordinates.*.0' => 'required|numeric|between:-180,180',
            'portions.*.coordinates.*.1' => 'required|numeric|between:-90,90',
            'portions.*.events' => 'nullable|array|max:30',
            'portions.*.events.*.type' => 'required|in:'.implode(',', self::eventTypes()),
            'portions.*.events.*.precision' => 'required|in:'.implode(',', self::PRECISIONS),
            'portions.*.events.*.date' => 'required|string|max:10',
            'portions.*.events.*.source' => 'required|string|min:3|max:500',
            'portions.*.events.*.source_url' => 'nullable|url:http,https|max:500',
            'portions.*.events.*.description' => 'nullable|string|max:1000',
            'replaces' => 'nullable|array',
            'replaces.source_trace_ids' => 'nullable|array|max:200',
            'replaces.source_trace_ids.*' => 'integer',
            'replaces.line_segment_ids' => 'nullable|array|max:2000',
            'replaces.line_segment_ids.*' => 'integer',
            'replaces.line_ids' => 'nullable|array|max:5',
            'replaces.line_ids.*' => 'integer',
        ];
    }

    /** Messages de validation traduits (lang/*\/editor.php), par motif de champ. */
    public static function messages(): array
    {
        return [
            'new_line_name.required_if' => __('editor.errors.name_required'),
            'new_line_name.min' => __('editor.errors.name_required'),
            'line_slug.required_if' => __('editor.errors.line_required'),
            'line_slug.exists' => __('editor.errors.line_required'),
            'trace_source.required' => __('editor.errors.source_required'),
            'trace_source.min' => __('editor.errors.source_required'),
            'portions.required' => __('editor.errors.no_portion'),
            'portions.min' => __('editor.errors.no_portion'),
            'portions.*.coordinates.*' => __('editor.errors.portion_too_short'),
            'portions.*.coordinates.min' => __('editor.errors.portion_too_short'),
            'portions.*.events.*.source.required' => __('editor.errors.source_required'),
            'portions.*.events.*.source.min' => __('editor.errors.source_required'),
            'portions.*.events.*.date.required' => __('editor.errors.bad_date'),
            'portions.*.events.*.date.max' => __('editor.errors.bad_date'),
            'portions.*.events.*.source_url.url' => __('editor.errors.bad_url'),
        ];
    }

    /**
     * Normalise une saisie validée en payload de contribution.
     *
     * @return array{payload: array, line: ?Line}
     */
    public function normalize(array $data): array
    {
        $line = null;
        if ($data['kind'] === 'correction') {
            $line = Line::where('slug', $data['line_slug'])->firstOrFail();
        }

        $portions = [];
        $keys = [];
        foreach (array_values($data['portions']) as $i => $p) {
            $key = Str::limit(preg_replace('/[^A-Za-z0-9_-]/', '', (string) $p['key']) ?: 'p'.($i + 1), 24, '');
            if (isset($keys[$key])) {
                $key .= '-'.($i + 1);
            }
            $keys[$key] = true;

            $coords = $this->cleanCoordinates($p['coordinates']);
            if (count($coords) < 2) {
                throw ValidationException::withMessages([
                    "portions.$i.coordinates" => __('editor.errors.portion_too_short'),
                ]);
            }
            $check = DB::selectOne(
                'SELECT ST_IsValid(g) AS valid, ST_Length(g::geography) AS len
                 FROM (SELECT ST_SetSRID(ST_GeomFromGeoJSON(?), 4326) AS g) t',
                [json_encode(['type' => 'LineString', 'coordinates' => $coords])],
            );
            if (! $check || ! $check->valid || (float) $check->len < 10) {
                throw ValidationException::withMessages([
                    "portions.$i.coordinates" => __('editor.errors.portion_too_short'),
                ]);
            }

            $events = [];
            foreach (array_values($p['events'] ?? []) as $j => $e) {
                $date = $this->normalizeDate((string) $e['date'], (string) $e['precision']);
                if ($date === null) {
                    throw ValidationException::withMessages([
                        "portions.$i.events.$j.date" => __('editor.errors.bad_date'),
                    ]);
                }
                $events[] = [
                    'type' => $e['type'],
                    'precision' => $e['precision'],
                    'date' => $date,
                    'source' => trim($e['source']),
                    'source_url' => $e['source_url'] ?? null,
                    'description' => isset($e['description']) ? trim($e['description']) ?: null : null,
                ];
            }
            usort($events, fn ($a, $b) => strcmp($a['date'], $b['date']));

            $portions[] = [
                'key' => $key,
                'label' => $this->clean($p['label'] ?? null),
                'from' => $this->clean($p['from'] ?? null),
                'to' => $this->clean($p['to'] ?? null),
                'length_m' => (int) round((float) $check->len),
                'geometry' => ['type' => 'LineString', 'coordinates' => $coords],
                'events' => $events,
            ];
        }

        $replaces = [
            'source_trace_ids' => array_values(array_unique(array_map('intval', $data['replaces']['source_trace_ids'] ?? []))),
            'line_segment_ids' => array_values(array_unique(array_map('intval', $data['replaces']['line_segment_ids'] ?? []))),
            'line_ids' => array_values(array_unique(array_map('intval', $data['replaces']['line_ids'] ?? []))),
        ];

        $payload = [
            'kind' => $data['kind'],
            'line_id' => $line?->id,
            'line_slug' => $line?->slug,
            'line_name' => $line?->name ?? $this->clean($data['new_line_name'] ?? null),
            'line_code' => $line?->code,
            'line_network_before' => $line?->network,
            'network' => $data['network'],
            'basemap' => $data['basemap'] ?? null,
            'trace_source' => trim($data['trace_source']),
            'notes' => $this->clean($data['notes'] ?? null),
            'portions' => $portions,
            'length_m' => array_sum(array_column($portions, 'length_m')),
            'events_count' => array_sum(array_map(fn ($p) => count($p['events']), $portions)),
            'replaces' => $replaces,
            'before' => $this->beforeSnapshot($replaces),
            'warnings' => $this->warnings($portions),
        ];

        return ['payload' => $payload, 'line' => $line];
    }

    /**
     * Écrit une contribution approuvée dans source_traces + line_events.
     *
     * @return array{line_id: int, line_slug: string, line_created: bool, source_trace_id: int, events: int}
     */
    public function apply(Contribution $contribution): array
    {
        $p = $contribution->payload;

        return DB::transaction(function () use ($contribution, $p) {
            // 1. Ligne : existante (correction) ou créée (nouvelle ligne disparue).
            $created = false;
            $line = ! empty($p['line_id']) ? Line::find($p['line_id']) : null;
            if (! $line && ! empty($p['applied']['line_id'])) {
                $line = Line::find($p['applied']['line_id']); // ré-application
            }
            if (! $line) {
                if (($p['kind'] ?? null) === 'correction') {
                    throw new \RuntimeException(__('messages.line_not_found', ['id' => $p['line_id']]));
                }
                $line = $this->createLine($contribution, $p);
                $created = true;
            } elseif (! empty($p['network']) && $line->network !== $p['network']) {
                DB::table('lines')->where('id', $line->id)->update(['network' => $p['network'], 'updated_at' => now()]);
            }

            // 2. Tracé brut (une ligne de source_traces par contribution).
            $multi = [
                'type' => 'MultiLineString',
                'coordinates' => array_map(fn ($q) => $q['geometry']['coordinates'], $p['portions']),
            ];
            [$opened, $closed] = $this->yearsFromEvents($p['portions']);
            $properties = [
                'contribution_id' => $contribution->id,
                'user_id' => $contribution->user_id,
                'kind' => $p['kind'],
                'basemap' => $p['basemap'] ?? null,
                'trace_source' => $p['trace_source'] ?? null,
                'notes' => $p['notes'] ?? null,
                'portions' => array_map(fn ($q) => [
                    'key' => $q['key'], 'label' => $q['label'], 'from' => $q['from'], 'to' => $q['to'],
                    'length_m' => $q['length_m'], 'events' => count($q['events']),
                ], $p['portions']),
                'supersedes' => $p['replaces'] ?? [],
            ];
            $hasDates = $opened !== null || $closed !== null;

            $row = DB::selectOne(
                "INSERT INTO source_traces (source, source_ref, layer, name, properties, geom, length_m, line_id,
                        match_method, match_score, opened_year, closed_year, dates_source, dates_score,
                        seen_at, created_at, updated_at)
                 SELECT 'contrib', :ref, :layer, :name, CAST(:props AS jsonb), g, ROUND(ST_Length(g::geography))::int,
                        :line_id, :method, 1.0, :opened, :closed, :dates_source, :dates_score, NOW(), NOW(), NOW()
                 FROM (SELECT ST_Multi(ST_SetSRID(ST_GeomFromGeoJSON(:geojson), 4326)) AS g) t
                 ON CONFLICT (source, source_ref) DO UPDATE SET
                        layer = EXCLUDED.layer, name = EXCLUDED.name, properties = EXCLUDED.properties,
                        geom = EXCLUDED.geom, length_m = EXCLUDED.length_m, line_id = EXCLUDED.line_id,
                        match_method = EXCLUDED.match_method, match_score = EXCLUDED.match_score,
                        opened_year = EXCLUDED.opened_year, closed_year = EXCLUDED.closed_year,
                        dates_source = EXCLUDED.dates_source, dates_score = EXCLUDED.dates_score,
                        seen_at = NOW(), updated_at = NOW()
                 RETURNING id",
                [
                    'ref' => (string) $contribution->id,
                    'layer' => $p['network'] ?? null,
                    'name' => $line->name,
                    'props' => json_encode($properties, JSON_UNESCAPED_UNICODE),
                    'geojson' => json_encode($multi),
                    'line_id' => $line->id,
                    'method' => $created ? 'created' : 'contrib',
                    'opened' => $opened,
                    'closed' => $closed,
                    'dates_source' => $hasDates ? 'contrib' : null,
                    'dates_score' => $hasDates ? $this->datedShare($p['portions']) : null,
                ],
            );
            $traceId = (int) $row->id;

            // 3. Événements datés, rattachés à LEUR portion (section_geom).
            $fingerprints = [];
            foreach ($p['portions'] as $portion) {
                foreach ($portion['events'] as $i => $e) {
                    $fingerprint = "contrib:{$contribution->id}:{$portion['key']}:{$i}";
                    $fingerprints[] = $fingerprint;
                    DB::statement(
                        "INSERT INTO line_events (line_id, type, event_date, date_precision, description, source,
                                source_url, source_quote, source_trace_id, section_from, section_to,
                                section_geom, section_resolution, confidence, fingerprint, created_at, updated_at)
                         VALUES (:line_id, :type, :date, :precision, :description, 'contrib', :url, :quote, :trace_id,
                                 :from, :to, ST_Multi(ST_SetSRID(ST_GeomFromGeoJSON(:geojson), 4326)), 'trace',
                                 'high', :fp, NOW(), NOW())
                         ON CONFLICT (fingerprint) DO UPDATE SET
                                line_id = EXCLUDED.line_id, type = EXCLUDED.type, event_date = EXCLUDED.event_date,
                                date_precision = EXCLUDED.date_precision, description = EXCLUDED.description,
                                source_url = EXCLUDED.source_url, source_quote = EXCLUDED.source_quote,
                                source_trace_id = EXCLUDED.source_trace_id, section_from = EXCLUDED.section_from,
                                section_to = EXCLUDED.section_to, section_geom = EXCLUDED.section_geom,
                                section_resolution = EXCLUDED.section_resolution, updated_at = NOW()",
                        [
                            'line_id' => $line->id,
                            'type' => $e['type'],
                            'date' => $e['date'],
                            'precision' => $e['precision'],
                            'description' => $e['description'] ?: $this->defaultDescription($e, $portion, $contribution->id),
                            'url' => $e['source_url'] ?? null,
                            'quote' => Str::limit($e['source'], 500, ''),
                            'trace_id' => $traceId,
                            'from' => $portion['from'] ? Str::limit($portion['from'], 250, '') : null,
                            'to' => $portion['to'] ? Str::limit($portion['to'], 250, '') : null,
                            'geojson' => json_encode($portion['geometry']),
                            'fp' => $fingerprint,
                        ],
                    );
                }
            }
            // Ré-application d'une contribution modifiée : on retire les événements disparus.
            DB::table('line_events')
                ->where('source', 'contrib')
                ->where('fingerprint', 'like', "contrib:{$contribution->id}:%")
                ->when($fingerprints !== [], fn ($q) => $q->whereNotIn('fingerprint', $fingerprints))
                ->delete();

            return [
                'line_id' => $line->id,
                'line_slug' => $line->slug,
                'line_created' => $created,
                'source_trace_id' => $traceId,
                'events' => count($fingerprints),
            ];
        });
    }

    /**
     * Données de référence pour l'accrochage (snap) dans une emprise :
     * tracés bruts (source_traces) + tronçons matérialisés (line_segments).
     */
    public function referenceFeatures(array $bbox, float $zoom, ?array $sources = null, bool $withSegments = true): array
    {
        [$minx, $miny, $maxx, $maxy] = $bbox;
        $tol = $zoom >= 13 ? 0.0 : ($zoom >= 11 ? 0.00002 : 0.0001);
        $geomExpr = $tol > 0 ? "ST_SimplifyPreserveTopology(geom, {$tol})" : 'geom';
        $env = [$minx, $miny, $maxx, $maxy];

        // À petite échelle, on écarte les voies courtes (faisceaux de gare BD TOPO…).
        $minLength = $zoom >= 12 ? 0 : 500;
        $traces = DB::select(
            "SELECT id, source, layer, LEFT(name, 160) AS name, line_id, opened_year, closed_year,
                    ST_AsGeoJSON({$geomExpr}, 6) AS g
             FROM source_traces
             WHERE geom && ST_MakeEnvelope(?, ?, ?, ?, 4326)
               AND COALESCE(length_m, 0) >= ?
               AND (?::text[] IS NULL OR source = ANY(?::text[]))
             ORDER BY length_m DESC NULLS LAST
             LIMIT 2500",
            [...$env, $minLength, $sources === null ? null : '{'.implode(',', $sources).'}', $sources === null ? null : '{'.implode(',', $sources).'}'],
        );
        if ($sources === []) {
            $traces = [];
        }
        // Un même tronçon peut exister dans plusieurs ères : on dédoublonne.
        $segments = ! $withSegments ? [] : DB::select(
            "SELECT DISTINCT ON (ls.line_id, COALESCE(ls.osm_way_id::text, 't' || ls.source_trace_id::text, md5(ST_AsBinary(ls.geom)::text)))
                    ls.id, ls.line_id, ls.status, e.year AS era, l.slug AS line_slug, l.name AS line_name, l.code AS line_code,
                    ST_AsGeoJSON(".str_replace('geom', 'ls.geom', $geomExpr).", 6) AS g
             FROM line_segments ls
             JOIN lines l ON l.id = ls.line_id
             JOIN eras e ON e.id = ls.era_id
             WHERE ls.geom && ST_MakeEnvelope(?, ?, ?, ?, 4326)
             ORDER BY ls.line_id, COALESCE(ls.osm_way_id::text, 't' || ls.source_trace_id::text, md5(ST_AsBinary(ls.geom)::text)), e.year DESC
             LIMIT 3000",
            $env,
        );

        $features = [];
        foreach ($traces as $t) {
            $features[] = [
                'type' => 'Feature',
                'geometry' => json_decode($t->g, true),
                'properties' => [
                    'kind' => 'trace', 'id' => $t->id, 'source' => $t->source, 'layer' => $t->layer,
                    'name' => $t->name, 'line_id' => $t->line_id,
                    'opened_year' => $t->opened_year, 'closed_year' => $t->closed_year,
                ],
            ];
        }
        foreach ($segments as $s) {
            $features[] = [
                'type' => 'Feature',
                'geometry' => json_decode($s->g, true),
                'properties' => [
                    'kind' => 'segment', 'id' => $s->id, 'line_id' => $s->line_id, 'status' => $s->status,
                    'era' => $s->era, 'line_slug' => $s->line_slug, 'line_name' => $s->line_name,
                    'line_code' => $s->line_code,
                ],
            ];
        }

        return [
            'type' => 'FeatureCollection',
            'features' => $features,
            'truncated' => count($traces) >= 2500 || count($segments) >= 3000,
        ];
    }

    /**
     * Géométrie complète d'un élément de référence, fusionnée en parties
     * continues (ST_LineMerge), pour la reprendre comme portion à corriger.
     *
     * @return array{kind: string, id: int, name: ?string, line: ?array, parts: array, replaces: array}|null
     */
    public function featureParts(string $kind, int $id): ?array
    {
        [$sql, $replaces] = match ($kind) {
            'trace' => [
                'SELECT st.name, st.line_id, ST_AsGeoJSON(ST_LineMerge(st.geom), 7) AS g FROM source_traces st WHERE st.id = ?',
                ['source_trace_ids' => [$id], 'line_segment_ids' => [], 'line_ids' => []],
            ],
            'segment' => [
                'SELECT l.name, ls.line_id, ST_AsGeoJSON(ls.geom, 7) AS g FROM line_segments ls JOIN lines l ON l.id = ls.line_id WHERE ls.id = ?',
                ['source_trace_ids' => [], 'line_segment_ids' => [$id], 'line_ids' => []],
            ],
            'line' => [
                'SELECT l.name, l.id AS line_id, ST_AsGeoJSON(ST_LineMerge(ST_Collect(s.geom)), 7) AS g
                 FROM lines l
                 JOIN LATERAL (
                     SELECT ls.geom FROM line_segments ls
                     WHERE ls.line_id = l.id
                       AND ls.era_id = (SELECT ls2.era_id FROM line_segments ls2 JOIN eras e ON e.id = ls2.era_id
                                        WHERE ls2.line_id = l.id ORDER BY e.is_current DESC, e.year DESC LIMIT 1)
                 ) s ON TRUE
                 WHERE l.id = ?
                 GROUP BY l.id, l.name',
                ['source_trace_ids' => [], 'line_segment_ids' => [], 'line_ids' => [$id]],
            ],
            default => [null, null],
        };
        if (! $sql) {
            return null;
        }
        $row = DB::selectOne($sql, [$id]);
        if (! $row || ! $row->g) {
            return null;
        }
        $geom = json_decode($row->g, true);
        $parts = match ($geom['type'] ?? null) {
            'LineString' => [$geom['coordinates']],
            'MultiLineString' => $geom['coordinates'],
            default => [],
        };
        $parts = array_values(array_filter($parts, fn ($c) => count($c) >= 2));
        $line = $row->line_id ? DB::table('lines')->where('id', $row->line_id)->first(['id', 'slug', 'name', 'code', 'network']) : null;

        return [
            'kind' => $kind,
            'id' => $id,
            'name' => $row->name,
            'line' => $line ? (array) $line : null,
            'parts' => $parts,
            'replaces' => $replaces,
        ];
    }

    /**
     * Géométrie actuelle d'une ligne (tronçons de l'ère la plus récente
     * disponible + tracés bruts rattachés), simplifiée pour l'affichage.
     */
    public function lineGeometry(int $lineId, float $tolerance = 0.00001): array
    {
        $segments = DB::select(
            'SELECT ls.id, ls.status, e.year AS era, ST_AsGeoJSON(ST_SimplifyPreserveTopology(ls.geom, ?), 6) AS g
             FROM line_segments ls JOIN eras e ON e.id = ls.era_id
             WHERE ls.line_id = ?
               AND ls.era_id = (SELECT ls2.era_id FROM line_segments ls2 JOIN eras e2 ON e2.id = ls2.era_id
                                WHERE ls2.line_id = ? ORDER BY e2.is_current DESC, e2.year DESC LIMIT 1)
             LIMIT 4000',
            [$tolerance, $lineId, $lineId],
        );
        $traces = DB::select(
            'SELECT id, source, layer, LEFT(name, 160) AS name, ST_AsGeoJSON(ST_SimplifyPreserveTopology(geom, ?), 6) AS g
             FROM source_traces WHERE line_id = ? LIMIT 500',
            [$tolerance, $lineId],
        );
        $bbox = DB::selectOne(
            'SELECT ST_XMin(b) AS minx, ST_YMin(b) AS miny, ST_XMax(b) AS maxx, ST_YMax(b) AS maxy
             FROM (SELECT ST_Extent(g) AS b FROM (
                 SELECT geom AS g FROM line_segments WHERE line_id = ?
                 UNION ALL SELECT geom FROM source_traces WHERE line_id = ?
             ) u) t',
            [$lineId, $lineId],
        );

        $fc = fn (array $rows, string $kind) => [
            'type' => 'FeatureCollection',
            'features' => array_map(fn ($r) => [
                'type' => 'Feature',
                'geometry' => json_decode($r->g, true),
                'properties' => array_filter([
                    'kind' => $kind, 'id' => $r->id, 'status' => $r->status ?? null, 'era' => $r->era ?? null,
                    'source' => $r->source ?? null, 'layer' => $r->layer ?? null, 'name' => $r->name ?? null,
                ], fn ($v) => $v !== null),
            ], $rows),
        ];

        return [
            'segments' => $fc($segments, 'segment'),
            'traces' => $fc($traces, 'trace'),
            'bbox' => $bbox && $bbox->minx !== null
                ? [(float) $bbox->minx, (float) $bbox->miny, (float) $bbox->maxx, (float) $bbox->maxy]
                : null,
        ];
    }

    /** Lignes existantes à proximité d'un tracé proposé (aide à la détection de doublons). */
    public function nearbyLines(array $portions, ?int $excludeLineId = null, int $meters = 150): array
    {
        if ($portions === []) {
            return [];
        }
        $multi = json_encode([
            'type' => 'MultiLineString',
            'coordinates' => array_map(fn ($q) => $q['geometry']['coordinates'], $portions),
        ]);
        $deg = $meters / 70000; // marge en degrés pour l'index (≈ 150 m à 45° N)

        $rows = DB::select(
            "WITH g AS (SELECT ST_SetSRID(ST_GeomFromGeoJSON(?), 4326) AS g)
             SELECT l.slug, l.name, l.code, l.network, 'segment' AS via
             FROM line_segments ls JOIN lines l ON l.id = ls.line_id, g
             WHERE ls.geom && ST_Expand(g.g, ?) AND ST_DWithin(ls.geom::geography, g.g::geography, ?)
               AND (?::bigint IS NULL OR l.id <> ?::bigint)
             GROUP BY l.slug, l.name, l.code, l.network
             UNION
             SELECT COALESCE(l.slug, ''), COALESCE(l.name, LEFT(st.name, 120)), l.code, COALESCE(l.network, st.layer), st.source
             FROM source_traces st LEFT JOIN lines l ON l.id = st.line_id, g
             WHERE st.geom && ST_Expand(g.g, ?) AND ST_DWithin(st.geom::geography, g.g::geography, ?)
               AND (?::bigint IS NULL OR st.line_id IS DISTINCT FROM ?::bigint)
             LIMIT 12",
            [$multi, $deg, $meters, $excludeLineId, $excludeLineId, $deg, $meters, $excludeLineId, $excludeLineId],
        );

        return array_map(fn ($r) => (array) $r, $rows);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /** Arrondit (1e-7 ≈ 1 cm) et retire les sommets consécutifs en double. */
    private function cleanCoordinates(array $coords): array
    {
        $out = [];
        foreach ($coords as $c) {
            $pt = [round((float) $c[0], 7), round((float) $c[1], 7)];
            if ($out !== [] && end($out) === $pt) {
                continue;
            }
            $out[] = $pt;
        }

        return $out;
    }

    /** 'YYYY' / 'YYYY-MM' / 'YYYY-MM-DD' selon la précision → date ISO (1er du mois / 1er janvier). */
    public function normalizeDate(string $raw, string $precision): ?string
    {
        $raw = trim($raw);
        if (! preg_match('/^(\d{4})(?:-(\d{2}))?(?:-(\d{2}))?$/', $raw, $m)) {
            return null;
        }
        $y = (int) $m[1];
        $mo = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : null;
        $d = isset($m[3]) && $m[3] !== '' ? (int) $m[3] : null;
        if ($y < 1750 || $y > (int) date('Y') + 30) {
            return null;
        }

        return match ($precision) {
            'year' => sprintf('%04d-01-01', $y),
            'month' => $mo && $mo >= 1 && $mo <= 12 ? sprintf('%04d-%02d-01', $y, $mo) : null,
            'day' => $mo && $d && checkdate($mo, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $mo, $d) : null,
            default => null,
        };
    }

    private function clean(?string $s): ?string
    {
        $s = $s !== null ? trim(preg_replace('/\s+/u', ' ', $s)) : null;

        return $s === '' ? null : $s;
    }

    /** Instantané « avant » des tracés/tronçons remplacés (pour le diff de modération). */
    private function beforeSnapshot(array $replaces): ?array
    {
        if ($replaces['source_trace_ids'] === [] && $replaces['line_segment_ids'] === [] && $replaces['line_ids'] === []) {
            return null;
        }
        $arr = fn (array $ids) => '{'.implode(',', array_map('intval', $ids)).'}';
        $row = DB::selectOne(
            'SELECT ST_AsGeoJSON(ST_Multi(ST_SimplifyPreserveTopology(
                        ST_LineMerge(ST_CollectionExtract(ST_Collect(u.geom), 2)), 0.00001)), 6) AS g
             FROM (
                 SELECT geom::geometry AS geom FROM source_traces WHERE id = ANY(?::bigint[])
                 UNION ALL
                 SELECT geom::geometry FROM line_segments WHERE id = ANY(?::bigint[])
                 UNION ALL
                 SELECT ls.geom::geometry FROM line_segments ls
                 WHERE ls.line_id = ANY(?::bigint[])
                   AND ls.era_id = (SELECT ls2.era_id FROM line_segments ls2 JOIN eras e ON e.id = ls2.era_id
                                    WHERE ls2.line_id = ls.line_id ORDER BY e.is_current DESC, e.year DESC LIMIT 1)
             ) u',
            [$arr($replaces['source_trace_ids']), $arr($replaces['line_segment_ids']), $arr($replaces['line_ids'])],
        );

        return $row?->g ? json_decode($row->g, true) : null;
    }

    /** Incohérences signalées aux validateurs (non bloquantes). */
    private function warnings(array $portions): array
    {
        $warnings = [];
        foreach ($portions as $p) {
            $opening = null;
            $closing = null;
            $dismantled = null;
            foreach ($p['events'] as $e) {
                if (str_starts_with($e['type'], 'opening') && ($opening === null || $e['date'] < $opening)) {
                    $opening = $e['date'];
                }
                if (str_starts_with($e['type'], 'closure') || $e['type'] === 'dismantling') {
                    $closing = $closing === null ? $e['date'] : min($closing, $e['date']);
                }
                if ($e['type'] === 'dismantling') {
                    $dismantled = $dismantled === null ? $e['date'] : min($dismantled, $e['date']);
                }
            }
            if ($opening && $closing && $closing < $opening) {
                $warnings[] = ['portion' => $p['key'], 'code' => 'closure_before_opening'];
            }
            // Après une dépose, seul un événement de (ré)ouverture a du sens.
            if ($dismantled) {
                foreach ($p['events'] as $e) {
                    if ($e['date'] > $dismantled && ! str_starts_with($e['type'], 'reopening')
                        && ! str_starts_with($e['type'], 'opening') && $e['type'] !== 'other') {
                        $warnings[] = ['portion' => $p['key'], 'code' => 'event_after_dismantling'];
                        break;
                    }
                }
            }
            if ($p['events'] === []) {
                $warnings[] = ['portion' => $p['key'], 'code' => 'no_event'];
            }
        }

        return $warnings;
    }

    /** [année d'ouverture la plus ancienne, année de fermeture totale si le dernier état est « fermé »]. */
    private function yearsFromEvents(array $portions): array
    {
        $opened = null;
        $last = null;
        foreach ($portions as $p) {
            foreach ($p['events'] as $e) {
                $year = (int) substr($e['date'], 0, 4);
                if (str_starts_with($e['type'], 'opening')) {
                    $opened = $opened === null ? $year : min($opened, $year);
                }
                if (in_array($e['type'], ['opening', 'reopening', 'closure', 'dismantling', 'declassification'], true)
                    && ($last === null || $e['date'] >= $last['date'])) {
                    $last = $e;
                }
            }
        }
        $closed = $last && in_array($last['type'], ['closure', 'dismantling', 'declassification'], true)
            ? (int) substr($last['date'], 0, 4)
            : null;

        return [$opened, $closed];
    }

    /** Part de la longueur couverte par au moins un événement daté (0-1). */
    private function datedShare(array $portions): float
    {
        $total = array_sum(array_column($portions, 'length_m'));
        if ($total <= 0) {
            return 0.0;
        }
        $dated = array_sum(array_map(fn ($p) => $p['events'] !== [] ? $p['length_m'] : 0, $portions));

        return round($dated / $total, 3);
    }

    private function defaultDescription(array $e, array $portion, int $contributionId): string
    {
        $type = __('editor.event_types.'.$e['type'], [], 'fr');
        $where = $portion['label'] ?: trim(($portion['from'] ?? '').' - '.($portion['to'] ?? ''), ' -');

        return trim($type.($where ? " ({$where})" : '')." - contribution #{$contributionId}");
    }

    private function createLine(Contribution $contribution, array $p): Line
    {
        $name = Str::limit($p['line_name'] ?: 'Ligne sans nom', 250, '');
        $slug = 'contrib-'.Str::limit(Str::slug($name), 80, '');
        if ($slug === 'contrib-' || DB::table('lines')->where('slug', $slug)->exists()) {
            $slug = rtrim($slug, '-').'-'.$contribution->id;
        }
        $id = DB::table('lines')->insertGetId([
            'slug' => $slug,
            'name' => $name,
            'network' => $p['network'] ?? null,
            'alt_names' => json_encode(['name_source' => 'contrib', 'contribution_id' => $contribution->id], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Line::findOrFail($id);
    }
}

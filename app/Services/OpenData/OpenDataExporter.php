<?php

namespace App\Services\OpenData;

use App\Enums\AccidentKind;
use App\Models\Era;
use App\Services\StationTimeline;
use Illuminate\Support\Facades\DB;

/**
 * Contenu des jeux de données ouverts (vff:export-open-data) : lecture seule de la base, écriture des
 * fichiers d'un jeu dans un dossier. Chaque donnée garde sa provenance (clé du fournisseur dans le
 * registre des droits) ; ce qui vient d'une source non autorisée (OpenDataRights) est écarté ou
 * remplacé par la meilleure donnée libre disponible, et compté dans le OpenDataLedger du jeu.
 *
 * Remplacements :
 *   - géométrie d'une section : tracés d'une source libre (BD TOPO, IGN historique, OpenStreetMap, SNCF)
 *     dans un couloir de REPLACEMENT_TOLERANCE_M mètres autour de la section, retenus s'ils en couvrent
 *     au moins REPLACEMENT_COVERAGE ; OpenHistoricalMap et les tracés contribués ne servent pas de
 *     remplacement (une partie d'OHM reprend des tracés non libres, cf. registre) ;
 *   - nom d'une ligne : titre de l'article Wikipédia, à défaut nom Wikidata ;
 *   - nom et position d'une gare : ceux d'une source libre rattachée à la gare (station_sources) ;
 *     une gare recalée sur une voie dont le tracé n'est pas libre reprend la position de sa source.
 * Les longueurs et PK des sections restent ceux de VFF (mesures, pas des tracés).
 */
final class OpenDataExporter
{
    public const REPLACEMENT_TOLERANCE_M = 15;

    public const REPLACEMENT_COVERAGE = 0.8;

    /** Sources de tracés (source_traces.source) acceptées pour remplacer une géométrie, par priorité. */
    public const REPLACEMENT_TRACE_SOURCES = ['bdtopo', 'ign-historique', 'osm', 'declared', 'sncf'];

    /** Sources de gares (station_sources.source) pour remplacer un nom ou une position, par priorité. */
    public const REPLACEMENT_STATION_SOURCES = ['sncf', 'sncf_voyageurs', 'osm', 'wikidata', 'ohm', 'wikipedia_schema'];

    public const OPENING_TYPES = ['opening', 'opening_passenger', 'opening_freight'];

    public const CLOSURE_TYPES = ['closure', 'closure_passenger', 'closure_freight'];

    public const SECTION_FIELDS = ['section_id', 'line_id', 'line_slug', 'line_code', 'era', 'pk_start_m', 'pk_end_m', 'length_m',
        'status', 'traffic_type', 'is_heritage', 'heritage_kind', 'electrified', 'voltage_v', 'electrified_year', 'gauge_mm',
        'max_speed_kmh', 'signalling', 'train_protection', 'operator', 'dismantled_on', 'reuse', 'geometry_status', 'geometry_source',
        'gauge_source'];

    public const LINE_FIELDS = ['line_id', 'slug', 'code', 'name', 'name_source', 'network', 'is_lgv', 'era', 'sections',
        'length_m', 'length_in_service_m', 'main_status', 'statuses', 'first_opening_on', 'first_opening_precision',
        'first_opening_source', 'last_closure_on', 'last_closure_precision', 'last_closure_source', 'geometry_sections', 'geometry_withheld_sections', 'geometry_sources'];

    public const STATION_FIELDS = ['station_id', 'slug', 'name', 'name_source', 'type', 'era', 'status', 'uncertain', 'city',
        'department_code', 'uic_code', 'wikidata_id', 'opened_on', 'opened_precision', 'opened_after', 'opened_source', 'closed_on',
        'closed_precision', 'closed_source', 'lat', 'lon', 'geometry_status', 'geometry_source'];

    public const EVENT_FIELDS = ['event_id', 'line_id', 'line_slug', 'line_code', 'type', 'event_date', 'date_precision',
        'section_from', 'section_to', 'section_pk_start_m', 'section_pk_end_m', 'confidence', 'reuse', 'heritage_kind',
        'source', 'source_url'];

    public const ACCIDENT_FIELDS = ['accident_id', 'slug', 'wikidata_id', 'occurred_on', 'date_precision', 'occurred_at_time',
        'kind', 'name_fr', 'name_en', 'name_de', 'commune_insee', 'commune_name', 'department_code', 'lat', 'lon',
        'location_precision', 'deaths', 'injuries', 'review_status', 'line_slug', 'date_source', 'location_source',
        'toll_source', 'sources', 'source_urls'];

    public const MATERIAL_FIELDS = ['material_id', 'slug', 'name', 'type', 'builder', 'builders', 'operators', 'in_service_from',
        'in_service_to', 'units_built', 'max_speed_kmh', 'power_kw', 'gauge_mm', 'wheel_arrangement', 'service_status', 'scope',
        'wikidata_id', 'wikipedia_fr', 'sources'];

    /** Champs du matériel relus dans materials.provenance (colonne => clé de provenance). */
    private const MATERIAL_PROVENANCE = ['name' => 'name', 'type' => 'type', 'builder' => 'builder', 'builders' => 'builders',
        'operators' => 'operators', 'in_service_from' => 'in_service_from', 'in_service_to' => 'in_service_to',
        'units_built' => 'units_built', 'max_speed_kmh' => 'max_speed_kmh', 'power_kw' => 'power_kw', 'gauge_mm' => 'gauge_mm',
        'wheel_arrangement' => 'wheel_arrangement', 'wikipedia_fr' => 'wikipedia_fr'];

    public function __construct(private readonly OpenDataRights $rights) {}

    public function rights(): OpenDataRights
    {
        return $this->rights;
    }

    /**
     * Réseau d'une ère : lignes, sections et gares (CSV et GeoJSON).
     *
     * @param  list<int>|null  $lineIds  échantillon (null : tout le réseau)
     */
    public function network(Era $era, string $dir, OpenDataLedger $ledger, ?array $lineIds = null): void
    {
        $year = (int) $era->year;
        $lines = $this->lines($era, $lineIds, $ledger);
        $dates = $this->lineDates($lineIds, $ledger);

        $sectionsCsv = OpenDataFiles::csv("{$dir}/sections.csv", self::SECTION_FIELDS);
        $sectionsGeo = OpenDataFiles::geojson("{$dir}/sections.geojson", "vff-sections-{$year}");
        $linesCsv = OpenDataFiles::csv("{$dir}/lignes.csv", self::LINE_FIELDS);
        $linesGeo = OpenDataFiles::geojson("{$dir}/lignes.geojson", "vff-lignes-{$year}");

        $current = null;
        $acc = [];
        foreach ($this->sections($era, $lineIds) as $row) {
            $lineId = (int) $row->line_id;
            if ($lineId !== $current) {
                if ($current !== null && isset($lines[$current])) {
                    $this->writeLine($current, $lines[$current], $dates[$current] ?? [], $acc, $year, $linesCsv, $linesGeo);
                }
                $current = $lineId;
                $acc = ['sections' => 0, 'length' => 0, 'in_service' => 0, 'by_status' => [], 'with_geometry' => 0,
                    'geometry_sources' => [], 'parts' => []];
            }
            $line = $lines[$lineId] ?? null;
            if ($line === null) {
                continue;
            }

            [$geometry, $geometryStatus, $geometryProvider] = $this->sectionGeometry($row, $ledger);
            $gauge = $row->gauge_mm !== null ? (int) $row->gauge_mm : null;
            $gaugeProvider = $row->gauge_source !== null ? $this->rights->provider('line_segments.gauge_source', $row->gauge_source) : null;
            if ($row->gauge_source !== null && ($refusal = $this->rights->refusal($gaugeProvider)) !== null) {
                $ledger->withhold('sections.gauge', $refusal);
                $gauge = null;
                $gaugeProvider = null;
            }
            $ledger->cite($gaugeProvider);

            $props = [
                'section_id' => (int) $row->id,
                'line_id' => $lineId,
                'line_slug' => $line['slug'],
                'line_code' => $line['code'],
                'era' => $year,
                'pk_start_m' => $this->int($row->pk_start_m),
                'pk_end_m' => $this->int($row->pk_end_m),
                'length_m' => $this->int($row->length_meters),
                'status' => $row->status,
                'traffic_type' => $row->traffic_type,
                'is_heritage' => (bool) $row->is_heritage,
                'heritage_kind' => $row->heritage_kind,
                'electrified' => $row->electrified === null ? null : (bool) $row->electrified,
                'voltage_v' => $this->int($row->voltage_v),
                'electrified_year' => $this->int($row->electrified_year),
                'gauge_mm' => $gauge,
                'max_speed_kmh' => $this->int($row->max_speed_kmh),
                'signalling' => $row->signalling,
                'train_protection' => $row->train_protection,
                'operator' => $row->operator,
                'dismantled_on' => $row->dismantled_at,
                'reuse' => $row->reuse,
                'geometry_status' => $geometryStatus,
                'geometry_source' => $geometryProvider,
                'gauge_source' => $gauge !== null ? $gaugeProvider : null,
            ];
            $sectionsCsv->row(array_values($props));
            $sectionsGeo->feature((int) $row->id, $geometry, $props);

            $length = (int) $row->length_meters;
            $acc['sections']++;
            $acc['length'] += $length;
            $acc['in_service'] += $row->status === 'in_service' ? $length : 0;
            $acc['by_status'][(string) $row->status] = ($acc['by_status'][(string) $row->status] ?? 0) + $length;
            if ($geometry !== null) {
                $acc['with_geometry']++;
                $acc['geometry_sources'][(string) $geometryProvider] = true;
                foreach ($this->lineStrings($geometry) as $part) {
                    $acc['parts'][] = $part;
                }
            }
        }
        if ($current !== null && isset($lines[$current])) {
            $this->writeLine($current, $lines[$current], $dates[$current] ?? [], $acc, $year, $linesCsv, $linesGeo);
        }

        $ledger->count('sections.csv', $sectionsCsv->close());
        $ledger->count('sections.geojson', $sectionsGeo->close());
        $ledger->count('lignes.csv', $linesCsv->close());
        $ledger->count('lignes.geojson', $linesGeo->close());

        $this->stations($era, $dir, $ledger, $lineIds);
    }

    /**
     * Écrit la ligne et son entité GeoJSON d'après le cumul de ses sections.
     *
     * @param  array{slug: string, code: ?string, name: ?string, name_source: ?string, network: ?string, is_lgv: bool}  $line
     * @param  array{opening?: array{0: string, 1: string, 2: ?string}, closure?: array{0: string, 1: string, 2: ?string}}  $dates
     * @param  array{sections: int, length: int, in_service: int, by_status: array<string, int>, with_geometry: int, geometry_sources: array<string, true>, parts: list<mixed>}  $acc
     */
    private function writeLine(int $current, array $line, array $dates, array $acc, int $year, OpenDataFiles $linesCsv, OpenDataFiles $linesGeo): void
    {
        arsort($acc['by_status']);
        $statuses = array_keys($acc['by_status']);
        $sources = array_keys($acc['geometry_sources']);
        sort($sources);
        $props = [
            'line_id' => $current,
            'slug' => $line['slug'],
            'code' => $line['code'],
            'name' => $line['name'],
            'name_source' => $line['name_source'],
            'network' => $line['network'],
            'is_lgv' => $line['is_lgv'],
            'era' => $year,
            'sections' => $acc['sections'],
            'length_m' => $acc['length'],
            'length_in_service_m' => $acc['in_service'],
            'main_status' => $statuses[0] ?? null,
            'statuses' => implode('|', $this->sorted($statuses)),
            'first_opening_on' => $dates['opening'][0] ?? null,
            'first_opening_precision' => $dates['opening'][2] ?? null,
            'first_opening_source' => $dates['opening'][1] ?? null,
            'last_closure_on' => $dates['closure'][0] ?? null,
            'last_closure_precision' => $dates['closure'][2] ?? null,
            'last_closure_source' => $dates['closure'][1] ?? null,
            'geometry_sections' => $acc['with_geometry'],
            'geometry_withheld_sections' => $acc['sections'] - $acc['with_geometry'],
            'geometry_sources' => implode('|', $sources),
        ];
        $linesCsv->row(array_values($props));
        $linesGeo->feature($current, $acc['parts'] === [] ? null
            : (string) json_encode(['type' => 'MultiLineString', 'coordinates' => $acc['parts']], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION), $props);
    }

    /**
     * Lignes qui ont au moins une section à l'ère, avec leur nom libre.
     *
     * @param  list<int>|null  $lineIds
     * @return array<int, array{slug: string, code: ?string, name: ?string, name_source: ?string, network: ?string, is_lgv: bool}>
     */
    private function lines(Era $era, ?array $lineIds, OpenDataLedger $ledger): array
    {
        $rows = DB::table('lines as l')
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('line_segments as ls')->whereColumn('ls.line_id', 'l.id')->where('ls.era_id', $era->id))
            ->when($lineIds !== null, fn ($q) => $q->whereIn('l.id', $lineIds ?? []))
            ->orderBy('l.id')
            ->get(['l.id', 'l.slug', 'l.code', 'l.name', 'l.network', 'l.is_lgv', 'l.wikipedia_title', 'l.alt_names']);

        $out = [];
        foreach ($rows as $r) {
            $alt = is_string($r->alt_names) ? (json_decode($r->alt_names, true) ?: []) : [];
            // Ligne créée d'après le tracé d'une source non autorisée, sans code SNCF Réseau : son existence, ses
            // sections, longueurs et états viennent de cette source. Ni la ligne ni ses sections n'entrent.
            $created = is_string($alt['source'] ?? null) && ! str_starts_with($alt['source'], 'vff:') ? $alt['source'] : null;
            if ($created !== null && $r->code === null
                && ($refusal = $this->rights->refusal($this->rights->provider('line_segments', $created))) !== null) {
                $ledger->withhold('lines', $refusal);

                continue;
            }
            [$name, $provider] = $this->lineName((string) $r->slug, (string) $r->name, is_string($r->wikipedia_title) ? $r->wikipedia_title : null, $alt, $ledger);
            $ledger->cite($provider);
            $out[(int) $r->id] = [
                'slug' => (string) $r->slug,
                'code' => $r->code !== null ? (string) $r->code : null,
                'name' => $name,
                'name_source' => $provider,
                'network' => $r->network !== null ? (string) $r->network : null,
                'is_lgv' => (bool) $r->is_lgv,
            ];
        }

        return $out;
    }

    /**
     * Nom libre d'une ligne et son fournisseur. Provenance : alt_names.name_source ; sans elle, une ligne du
     * RFN (slug rfn-) porte le nom de SNCF Réseau et une ligne créée par VFF le sien.
     *
     * @param  array<string, mixed>  $alt
     * @return array{0: ?string, 1: ?string}
     */
    public function lineName(string $slug, string $name, ?string $wikipediaTitle, array $alt, OpenDataLedger $ledger): array
    {
        $nameSource = is_string($alt['name_source'] ?? null) ? $alt['name_source'] : null;
        $provider = match (true) {
            $nameSource !== null => $this->rights->provider('lines.name_source', $nameSource),
            str_starts_with($slug, 'rfn-') => 'sncf',
            is_string($alt['source'] ?? null) && str_starts_with($alt['source'], 'vff:') => 'vff',
            default => null,
        };
        $refusal = $this->rights->refusal($provider);
        if ($refusal === null) {
            return [$name, $provider];
        }
        if ($wikipediaTitle !== null && $wikipediaTitle !== '' && $this->rights->allows('wikipedia')) {
            $ledger->withhold('lines.name', $refusal, OpenDataLedger::REPLACED);

            return [$wikipediaTitle, 'wikipedia'];
        }
        foreach (is_array($alt['names'] ?? null) ? $alt['names'] : [] as $n) {
            if (is_array($n) && ($n['kind'] ?? 'name') === 'name' && is_string($n['name'] ?? null) && $n['name'] !== ''
                && $this->rights->allowsSource('lines.name_source', is_string($n['source'] ?? null) ? $n['source'] : null)) {
                $ledger->withhold('lines.name', $refusal, OpenDataLedger::REPLACED);

                return [mb_strtoupper(mb_substr($n['name'], 0, 1)).mb_substr($n['name'], 1), (string) $this->rights->provider('lines.name_source', $n['source'])];
            }
        }
        $ledger->withhold('lines.name', $refusal);

        return [null, null];
    }

    /**
     * Première ouverture et dernière fermeture de chaque ligne, d'après ses seuls événements libres.
     *
     * @param  list<int>|null  $lineIds
     * @return array<int, array{opening?: array{0: string, 1: string, 2: ?string}, closure?: array{0: string, 1: string, 2: ?string}}>
     */
    private function lineDates(?array $lineIds, OpenDataLedger $ledger): array
    {
        $rows = DB::table('line_events')
            ->whereNotNull('event_date')
            ->whereIn('type', [...self::OPENING_TYPES, ...self::CLOSURE_TYPES])
            ->when($lineIds !== null, fn ($q) => $q->whereIn('line_id', $lineIds ?? []))
            ->orderBy('line_id')->orderBy('event_date')->orderBy('id')
            ->get(['line_id', 'type', 'event_date', 'date_precision', 'source', 'source_url']);

        $out = [];
        foreach ($rows as $r) {
            $provider = $this->rights->provider('line_events', $r->source, $r->source_url);
            if (! $this->rights->allows($provider)) {
                continue;
            }
            $lineId = (int) $r->line_id;
            $date = (string) $r->event_date;
            if (in_array($r->type, self::OPENING_TYPES, true)) {
                $out[$lineId]['opening'] ??= [$date, (string) $provider, $r->date_precision];
            } else {
                $out[$lineId]['closure'] = [$date, (string) $provider, $r->date_precision];
            }
        }
        foreach ($out as $d) {
            $ledger->cite($d['opening'][1] ?? null);
            $ledger->cite($d['closure'][1] ?? null);
        }

        return $out;
    }

    /**
     * Sections de l'ère avec leur géométrie libre ou de remplacement, triées par ligne puis PK.
     *
     * @param  list<int>|null  $lineIds
     * @return iterable<object>
     */
    private function sections(Era $era, ?array $lineIds): iterable
    {
        $open = $this->rights->sqlAllowed('line_segments', 'ls.source');
        $candidates = array_values(array_filter(self::REPLACEMENT_TRACE_SOURCES,
            fn (string $s): bool => $this->rights->allowsSource('source_traces', $s)));
        $quote = fn (string $v): string => "'".str_replace("'", "''", $v)."'";
        $in = $candidates === [] ? "''" : implode(', ', array_map($quote, $candidates));
        $priority = 'CASE c.source '.implode(' ', array_map(fn (string $s, int $i): string => 'WHEN '.$quote($s).' THEN '.$i,
            $candidates, array_keys($candidates))).' ELSE 99 END';
        $filter = $lineIds !== null ? 'AND ls.line_id IN ('.($lineIds === [] ? '0' : implode(', ', array_map('intval', $lineIds))).')' : '';
        $tol = self::REPLACEMENT_TOLERANCE_M;
        $ratio = self::REPLACEMENT_COVERAGE;

        return DB::cursor(<<<SQL
            SELECT ls.id, ls.line_id, ls.pk_start_m, ls.pk_end_m, ls.length_meters, ls.status, ls.traffic_type,
                   ls.is_heritage, ls.heritage_kind, ls.electrified, ls.voltage_v, ls.electrified_year, ls.gauge_mm,
                   ls.gauge_source, ls.max_speed_kmh, ls.signalling, ls.train_protection, ls.operator,
                   ls.dismantled_at::text AS dismantled_at, ls.reuse, ls.source,
                   {$open} AS open,
                   CASE WHEN {$open} THEN ST_AsGeoJSON(ls.geom, 6) ELSE rep.geojson END AS geometry,
                   rep.source AS replacement_source
            FROM line_segments ls
            LEFT JOIN LATERAL (
                SELECT c.source, ST_AsGeoJSON(k.g, 6) AS geojson
                FROM (
                    SELECT st.source, ST_LineMerge(ST_CollectionExtract(ST_Intersection(ST_Union(st.geom), b.zone), 2)) AS raw
                    FROM (SELECT ST_Buffer(ls.geom::geography, {$tol}, 'endcap=flat')::geometry AS zone) b
                    JOIN source_traces st ON st.geom && b.zone AND st.source IN ({$in}) AND ST_Intersects(st.geom, b.zone)
                    WHERE NOT {$open} AND ls.geom IS NOT NULL
                    GROUP BY st.source, b.zone
                ) c
                CROSS JOIN LATERAL (SELECT GREATEST(ST_Length(ls.geom::geography), 1) AS total) t
                -- Morceaux courts (voie qui traverse le couloir, embranchement) écartés.
                CROSS JOIN LATERAL (
                    SELECT ST_LineMerge(ST_Collect(d.geom)) AS g FROM ST_Dump(c.raw) d
                    WHERE ST_Length(d.geom::geography) >= LEAST(3 * {$tol}, 0.5 * t.total)
                ) k
                CROSS JOIN LATERAL (
                    SELECT ST_Length(ST_Intersection(ls.geom, ST_Buffer(k.g::geography, {$tol})::geometry)::geography) AS covered,
                           t.total, ST_Length(k.g::geography) AS out_len
                ) m
                -- Couvre la section, sans la doubler (voies parallèles d'une même source).
                WHERE k.g IS NOT NULL AND NOT ST_IsEmpty(k.g) AND m.covered >= {$ratio} * m.total
                  AND m.out_len <= 1.5 * m.total + 2 * {$tol}
                ORDER BY ROUND(m.covered / m.total * 20) DESC, {$priority}, m.covered DESC
                LIMIT 1
            ) rep ON TRUE
            WHERE ls.era_id = {$era->id} {$filter}
            ORDER BY ls.line_id, ls.pk_start_m NULLS LAST, ls.id
        SQL);
    }

    /**
     * Géométrie exportée d'une section : la sienne si sa source est libre, sinon celle d'un tracé libre,
     * sinon aucune.
     *
     * @return array{0: ?string, 1: string, 2: ?string} géométrie GeoJSON, statut (original, replaced, withheld), fournisseur
     */
    private function sectionGeometry(object $row, OpenDataLedger $ledger): array
    {
        if ($row->open) {
            $provider = $this->rights->provider('line_segments', $row->source);
            $ledger->cite($provider);

            return [$row->geometry, $row->geometry !== null ? 'original' : 'none', $row->geometry !== null ? $provider : null];
        }
        $refusal = $this->rights->refusal($this->rights->provider('line_segments', $row->source)) ?? OpenDataRights::NOT_OPEN;
        if ($row->geometry !== null && $row->replacement_source !== null) {
            $provider = $this->rights->provider('source_traces', $row->replacement_source);
            $ledger->cite($provider);
            $ledger->withhold('sections.geometry', $refusal, OpenDataLedger::REPLACED);

            return [$row->geometry, 'replaced', $provider];
        }
        $ledger->withhold('sections.geometry', $refusal);

        return [null, 'withheld', null];
    }

    /**
     * Gares présentes à l'ère (station_eras), avec nom, dates et position libres.
     *
     * @param  list<int>|null  $lineIds
     */
    private function stations(Era $era, string $dir, OpenDataLedger $ledger, ?array $lineIds): void
    {
        $year = (int) $era->year;
        $freeTrack = $this->rights->sqlAllowed('line_segments', 'x.source');
        $rows = DB::select(<<<SQL
            SELECT s.id, s.slug, s.name, s.type, se.status, se.uncertain, s.city, s.department_code, s.uic_code, s.wikidata_id,
                   s.opened_at::text AS opened_at, s.opened_precision, s.closed_at::text AS closed_at, s.closed_precision,
                   s.position_source, s.provenance::text AS provenance, ST_X(s.geom) AS lon, ST_Y(s.geom) AS lat,
                   EXISTS (
                       SELECT 1 FROM line_segments x
                       WHERE x.line_id = NULLIF(s.provenance->'geom_recalage'->>'line_id', '')::bigint AND {$freeTrack}
                         AND x.geom && ST_Expand(s.geom, 0.0001) AND ST_DWithin(x.geom::geography, s.geom::geography, 1)
                   ) AS snapped_on_free_track
            FROM stations s
            JOIN station_eras se ON se.station_id = s.id AND se.era_id = ?
            WHERE (?::boolean OR s.id IN (SELECT station_id FROM line_stops WHERE era_id = ? AND line_id = ANY(?::bigint[])))
            ORDER BY s.id
        SQL, [$era->id, $lineIds === null, $era->id, '{'.implode(',', array_map('intval', $lineIds ?? [])).'}']);

        $prepared = [];
        $needs = [];
        foreach ($rows as $r) {
            $prov = is_string($r->provenance) ? (json_decode($r->provenance, true) ?: []) : [];
            $item = ['row' => $r, 'prov' => $prov, 'name' => $r->name, 'name_source' => null, 'lat' => $r->lat, 'lon' => $r->lon,
                'geometry_status' => $r->lat !== null ? 'original' : 'none', 'geometry_source' => null];

            $nameProvider = $this->rights->provider('stations.fields', is_string($prov['name'] ?? null) ? $prov['name'] : null);
            $item['name_refusal'] = $this->rights->refusal($nameProvider);
            $item['name_source'] = $item['name_refusal'] === null ? $nameProvider : null;

            $geomKey = is_string($prov['geom'] ?? null) ? $prov['geom'] : $r->position_source;
            $geomProvider = $this->rights->provider('stations.fields', is_string($geomKey) ? $geomKey : null);
            $item['geom_refusal'] = $this->rights->refusal($geomProvider);
            $item['geometry_source'] = $item['geom_refusal'] === null ? $geomProvider : null;
            // Recalée sur une voie dont le tracé n'est pas libre : position de la source avant recalage.
            $snappedFrom = $prov['geom_recalage']['snapped_from'] ?? null;
            if ($item['geom_refusal'] === null && is_string($geomKey) && str_starts_with($geomKey, 'recalage:')
                && ! $r->snapped_on_free_track && is_array($snappedFrom) && count($snappedFrom) === 2) {
                $item['lon'] = (float) $snappedFrom[0];
                $item['lat'] = (float) $snappedFrom[1];
                $item['geometry_status'] = 'replaced';
                $ledger->withhold('stations.geometry', 'snapped_to_track', OpenDataLedger::REPLACED);
            }
            if ($item['name_refusal'] !== null || $item['geom_refusal'] !== null) {
                $needs[] = (int) $r->id;
            }
            $prepared[] = $item;
        }

        $alternatives = $this->stationAlternatives($needs);

        $csv = OpenDataFiles::csv("{$dir}/gares.csv", self::STATION_FIELDS);
        $geo = OpenDataFiles::geojson("{$dir}/gares.geojson", "vff-gares-{$year}");
        foreach ($prepared as $item) {
            $r = $item['row'];
            $alt = $alternatives[(int) $r->id] ?? [];
            if ($item['name_refusal'] !== null) {
                $found = $this->firstWith($alt, 'name');
                $item['name'] = $found['name'] ?? null;
                $item['name_source'] = $found !== null ? $found['provider'] : null;
                $ledger->withhold('stations.name', $item['name_refusal'], $found !== null ? OpenDataLedger::REPLACED : OpenDataLedger::REMOVED);
            }
            if ($item['geom_refusal'] !== null) {
                $found = $this->firstWith($alt, 'lat');
                $item['lat'] = $found['lat'] ?? null;
                $item['lon'] = $found['lon'] ?? null;
                $item['geometry_source'] = $found !== null ? $found['provider'] : null;
                $item['geometry_status'] = $found !== null ? 'replaced' : 'withheld';
                $ledger->withhold('stations.geometry', $item['geom_refusal'], $found !== null ? OpenDataLedger::REPLACED : OpenDataLedger::REMOVED);
            }
            [$opened, $openedSource] = $this->stationDate($r->opened_at, $item['prov']['opened_at'] ?? null, 'stations.opened_on', $ledger);
            [$closed, $closedSource] = $this->stationDate($r->closed_at, $item['prov']['closed_at'] ?? null, 'stations.closed_on', $ledger);
            $ledger->cite($item['name_source']);
            $ledger->cite($item['geometry_source']);

            $lat = $item['lat'] !== null ? round((float) $item['lat'], 6) : null;
            $lon = $item['lon'] !== null ? round((float) $item['lon'], 6) : null;
            $props = [
                'station_id' => (int) $r->id,
                'slug' => $r->slug,
                'name' => $item['name'],
                'name_source' => $item['name_source'],
                'type' => $r->type,
                'era' => $year,
                'status' => $r->status,
                'uncertain' => (bool) $r->uncertain,
                'city' => $r->city,
                'department_code' => $r->department_code,
                'uic_code' => $r->uic_code,
                'wikidata_id' => $r->wikidata_id,
                'opened_on' => $opened,
                'opened_precision' => $opened !== null ? $r->opened_precision : null,
                // Ouverture encadrée (précision range) : entre opened_after + 1 et l'année de opened_on, la borne haute.
                'opened_after' => $opened !== null && $r->opened_precision === StationTimeline::RANGE && is_numeric($item['prov']['opened_after'] ?? null)
                    ? (int) $item['prov']['opened_after'] : null,
                'opened_source' => $openedSource,
                'closed_on' => $closed,
                'closed_precision' => $closed !== null ? $r->closed_precision : null,
                'closed_source' => $closedSource,
                'lat' => $lat,
                'lon' => $lon,
                'geometry_status' => $item['geometry_status'],
                'geometry_source' => $lat !== null ? $item['geometry_source'] : null,
            ];
            $csv->row(array_values($props));
            unset($props['lat'], $props['lon']);
            $geo->feature((int) $r->id, $lat !== null && $lon !== null
                ? json_encode(['type' => 'Point', 'coordinates' => [$lon, $lat]], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION) : null, $props);
        }
        $ledger->count('gares.csv', $csv->close());
        $ledger->count('gares.geojson', $geo->close());
    }

    /**
     * Noms et positions des sources libres rattachées aux gares, par priorité.
     *
     * @param  list<int>  $stationIds
     * @return array<int, list<array{provider: string, name: ?string, lat: ?float, lon: ?float}>>
     */
    private function stationAlternatives(array $stationIds): array
    {
        if ($stationIds === []) {
            return [];
        }
        $allowed = array_values(array_filter(self::REPLACEMENT_STATION_SOURCES, fn (string $s): bool => $this->rights->allowsSource('station_sources', $s)));
        if ($allowed === []) {
            return [];
        }
        $out = [];
        foreach (array_chunk($stationIds, 2000) as $chunk) {
            $rows = DB::table('station_sources')
                ->whereIn('station_id', $chunk)
                ->whereIn('source', $allowed)
                ->selectRaw('station_id, source, name, ST_X(geom) AS lon, ST_Y(geom) AS lat')
                ->orderBy('station_id')
                ->orderByRaw('array_position(?::text[], source::text)', ['{'.implode(',', $allowed).'}'])
                ->orderByRaw('match_distance_m NULLS LAST')
                ->orderBy('id')
                ->get();
            foreach ($rows as $r) {
                $out[(int) $r->station_id][] = [
                    'provider' => (string) $this->rights->provider('station_sources', $r->source),
                    'name' => is_string($r->name) && $r->name !== '' ? $r->name : null,
                    'lat' => $r->lat !== null ? (float) $r->lat : null,
                    'lon' => $r->lon !== null ? (float) $r->lon : null,
                ];
            }
        }

        return $out;
    }

    /**
     * @param  list<array{provider: string, name: ?string, lat: ?float, lon: ?float}>  $alternatives
     * @return array{provider: string, name: ?string, lat: ?float, lon: ?float}|null
     */
    private function firstWith(array $alternatives, string $field): ?array
    {
        foreach ($alternatives as $a) {
            if ($a[$field] !== null) {
                return $a;
            }
        }

        return null;
    }

    /** @return array{0: ?string, 1: ?string} date et fournisseur, ou rien si la source de la date n'est pas libre */
    private function stationDate(?string $date, mixed $source, string $item, OpenDataLedger $ledger): array
    {
        if ($date === null) {
            return [null, null];
        }
        $provider = $this->rights->provider('stations.fields', is_string($source) ? $source : null);
        if (($refusal = $this->rights->refusal($provider)) !== null) {
            $ledger->withhold($item, $refusal);

            return [null, null];
        }
        $ledger->cite($provider);

        return [$date, $provider];
    }

    /**
     * Événements datés des lignes, sans texte (ni description ni citation) : seuls ceux d'une source libre.
     *
     * @param  list<int>|null  $lineIds
     */
    public function events(string $dir, OpenDataLedger $ledger, ?array $lineIds = null): void
    {
        $csv = OpenDataFiles::csv("{$dir}/evenements.csv", self::EVENT_FIELDS);
        $query = DB::table('line_events as e')
            ->join('lines as l', 'l.id', '=', 'e.line_id')
            ->when($lineIds !== null, fn ($q) => $q->whereIn('e.line_id', $lineIds ?? []))
            ->orderBy('e.line_id')->orderByRaw('e.event_date NULLS LAST')->orderBy('e.id')
            ->select(['e.id', 'e.line_id', 'l.slug', 'l.code', 'e.type', 'e.event_date', 'e.date_precision', 'e.section_from',
                'e.section_to', 'e.section_pk_start_m', 'e.section_pk_end_m', 'e.confidence', 'e.reuse', 'e.heritage_kind',
                'e.source', 'e.source_url']);
        foreach ($query->cursor() as $e) {
            $provider = $this->rights->provider('line_events', $e->source, $e->source_url);
            if (($refusal = $this->rights->refusal($provider)) !== null) {
                $ledger->withhold('events', $refusal);

                continue;
            }
            $ledger->cite($provider);
            $csv->row([(int) $e->id, (int) $e->line_id, $e->slug, $e->code, $e->type, $e->event_date, $e->date_precision,
                $e->section_from, $e->section_to, $this->int($e->section_pk_start_m), $this->int($e->section_pk_end_m), $e->confidence,
                $e->reuse, $e->heritage_kind, $provider, $this->safeUrl($e->source_url, $provider)]);
        }
        $ledger->count('evenements.csv', $csv->close());
    }

    /**
     * Accidents publiés par VFF (PublicAccidents), faits seulement : ni résumé ni contexte rédigé.
     *
     * @param  list<int>|null  $lineIds
     */
    public function accidents(string $dir, OpenDataLedger $ledger, ?array $lineIds = null): void
    {
        $publishable = AccidentKind::publishableSql('a');
        $sample = $lineIds !== null ? 'AND a.id IN (SELECT accident_id FROM accident_lines WHERE line_id = ANY(?::bigint[]))' : '';
        $rows = DB::select(<<<SQL
            SELECT a.id, a.slug, a.wikidata_id, a.occurred_on::text AS occurred_on, a.date_precision,
                   a.occurred_at_time::text AS occurred_at_time, a.kind, a.name::text AS name, a.commune_insee, a.commune_name,
                   a.department_code, a.location_precision, a.location_source, a.deaths, a.injuries, a.toll_source,
                   a.review_status, a.provenance::text AS provenance, ST_X(a.geom) AS lon, ST_Y(a.geom) AS lat,
                   st.provenance->>'geom' AS station_geom_source, st.position_source AS station_position_source,
                   (SELECT l.slug FROM accident_lines al JOIN lines l ON l.id = al.line_id
                     WHERE al.accident_id = a.id ORDER BY al.is_primary DESC, al.id LIMIT 1) AS line_slug
            FROM accidents a
            LEFT JOIN stations st ON st.id = a.nearest_station_id
            WHERE {$publishable} AND a.alias_of_id IS NULL {$sample}
            ORDER BY a.occurred_on NULLS LAST, a.id
        SQL, $lineIds !== null ? ['{'.implode(',', array_map('intval', $lineIds)).'}'] : []);

        $sources = [];
        $ids = array_map(fn (object $r): int => (int) $r->id, $rows);
        foreach (array_chunk($ids, 2000) as $chunk) {
            foreach (DB::table('accident_sources')->whereIn('accident_id', $chunk)->orderBy('accident_id')->orderBy('id')
                ->get(['accident_id', 'provider', 'url']) as $s) {
                $sources[(int) $s->accident_id][] = $s;
            }
        }

        $csv = OpenDataFiles::csv("{$dir}/accidents.csv", self::ACCIDENT_FIELDS);
        foreach ($rows as $a) {
            $prov = is_string($a->provenance) ? (json_decode($a->provenance, true) ?: []) : [];
            $field = fn (string $key, ?string $fallback = null): ?string => $this->rights->provider('accidents.fields',
                is_string($prov[$key] ?? null) ? $prov[$key] : $fallback);

            $dateProvider = $field('date');
            if (($refusal = $this->rights->refusal($dateProvider)) !== null) {
                $ledger->withhold('accidents', $refusal);

                continue;
            }
            $ledger->cite($dateProvider);

            $locationKey = is_string($prov['location'] ?? null) ? $prov['location'] : $a->location_source;
            $locationProvider = $field('location', $a->location_source);
            if ($locationKey === 'station' && $this->rights->allows($locationProvider)) {
                // Position de la gare la plus proche : sa propre provenance décide.
                $locationProvider = $this->rights->provider('stations.fields', $a->station_geom_source ?? $a->station_position_source);
            }
            $lat = $a->lat;
            $lon = $a->lon;
            if ($lat !== null && ($refusal = $this->rights->refusal($locationProvider)) !== null) {
                $ledger->withhold('accidents.location', $refusal);
                [$lat, $lon, $locationProvider] = [null, null, null];
            }
            $ledger->cite($lat !== null ? $locationProvider : null);

            $toll = function (string $key, mixed $value) use ($field, $a, $ledger): array {
                if ($value === null) {
                    return [null, null];
                }
                $p = $field($key, $a->toll_source);
                if (($refusal = $this->rights->refusal($p)) !== null) {
                    $ledger->withhold("accidents.{$key}", $refusal);

                    return [null, null];
                }
                $ledger->cite($p);

                return [(int) $value, $p];
            };
            [$deaths, $deathsProvider] = $toll('deaths', $a->deaths);
            [$injuries] = $toll('injuries', $a->injuries);

            $kind = $a->kind;
            if (isset($prov['kind']) && ($refusal = $this->rights->refusal($field('kind'))) !== null) {
                $ledger->withhold('accidents.kind', $refusal);
                $kind = null;
            }
            $names = is_string($a->name) ? (json_decode($a->name, true) ?: []) : [];
            if (isset($prov['name']) && ($refusal = $this->rights->refusal($field('name'))) !== null) {
                $ledger->withhold('accidents.name', $refusal);
                $names = [];
            } else {
                $ledger->cite(isset($prov['name']) ? $field('name') : null);
            }

            $keptProviders = [];
            $keptUrls = [];
            foreach ($sources[(int) $a->id] ?? [] as $s) {
                $p = $this->rights->provider('accident_sources', $s->provider, $s->url);
                if (($refusal = $this->rights->refusal($p)) !== null) {
                    $ledger->withhold('accidents.sources', $refusal);

                    continue;
                }
                $ledger->cite($p);
                $keptProviders[(string) $p] = true;
                if (is_string($s->url) && $s->url !== '') {
                    $keptUrls[$s->url] = true;
                }
            }

            $csv->row([(int) $a->id, $a->slug, $a->wikidata_id, $a->occurred_on, $a->date_precision, $a->occurred_at_time, $kind,
                $names['fr'] ?? null, $names['en'] ?? null, $names['de'] ?? null, $a->commune_insee, $a->commune_name,
                $a->department_code, $lat !== null ? round((float) $lat, 6) : null, $lon !== null ? round((float) $lon, 6) : null,
                $a->location_precision, $deaths, $injuries, $a->review_status, $a->line_slug, $dateProvider,
                $lat !== null ? $locationProvider : null, $deathsProvider, implode('|', array_keys($keptProviders)),
                implode(' ', array_keys($keptUrls))]);
        }
        $ledger->count('accidents.csv', $csv->close());
    }

    /**
     * Séries de matériel roulant, champ par champ selon leur provenance ; ni description ni image.
     *
     * @param  list<int>|null  $lineIds
     */
    public function materials(string $dir, OpenDataLedger $ledger, ?array $lineIds = null): void
    {
        $csv = OpenDataFiles::csv("{$dir}/materiel.csv", self::MATERIAL_FIELDS);
        $query = DB::table('materials as m')
            ->when($lineIds !== null, fn ($q) => $q->whereIn('m.id', DB::table('line_materials')->whereIn('line_id', $lineIds ?? [])->select('material_id')))
            ->orderBy('m.id')
            ->select(['m.id', 'm.slug', 'm.name', 'm.type', 'm.builder', 'm.builders', 'm.operators', 'm.in_service_from',
                'm.in_service_to', 'm.units_built', 'm.max_speed_kmh', 'm.power_kw', 'm.gauge_mm', 'm.wheel_arrangement',
                'm.service_status', 'm.scope', 'm.wikidata_id', 'm.wikipedia_fr', 'm.source', 'm.provenance']);
        foreach ($query->cursor() as $m) {
            $owner = $this->rights->provider('materials', $m->source);
            if (($refusal = $this->rights->refusal($owner)) !== null) {
                $ledger->withhold('materials', $refusal);

                continue;
            }
            $ledger->cite($owner);
            $prov = is_string($m->provenance) ? (json_decode($m->provenance, true) ?: []) : [];
            $values = (array) $m;
            $used = [(string) $owner => true];
            foreach (self::MATERIAL_PROVENANCE as $column => $key) {
                $source = is_array($prov[$key] ?? null) ? ($prov[$key]['source'] ?? null) : ($prov[$key] ?? null);
                if ($source === null || $values[$column] === null) {
                    continue;
                }
                $p = $this->rights->provider('materials.fields', is_string($source) ? $source : null);
                if (($refusal = $this->rights->refusal($p)) !== null) {
                    $ledger->withhold("materials.{$column}", $refusal);
                    $values[$column] = null;

                    continue;
                }
                $ledger->cite($p);
                $used[(string) $p] = true;
            }
            $names = function (mixed $json): ?string {
                $list = is_string($json) ? json_decode($json, true) : null;
                if (! is_array($list)) {
                    return null;
                }
                $out = array_values(array_unique(array_filter(array_map(fn ($x) => is_array($x) ? ($x['name'] ?? null) : null, $list), 'is_string')));

                return $out === [] ? null : implode('|', $out);
            };
            $providers = array_keys($used);
            sort($providers);
            $csv->row([(int) $m->id, $m->slug, $values['name'], $values['type'], $values['builder'], $names($values['builders']),
                $names($values['operators']), $this->int($values['in_service_from']), $this->int($values['in_service_to']),
                $this->int($values['units_built']), $this->int($values['max_speed_kmh']), $this->int($values['power_kw']),
                $this->int($values['gauge_mm']), $values['wheel_arrangement'], $m->service_status, $m->scope, $m->wikidata_id,
                $values['wikipedia_fr'], implode('|', $providers)]);
        }
        $ledger->count('materiel.csv', $csv->close());
    }

    /** Lien cité, gardé seulement s'il désigne le fournisseur retenu ou aucun fournisseur déclaré. */
    private function safeUrl(?string $url, ?string $provider): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }
        $host = $this->rights->registry()->providerForUrl($url);

        return $host === null || $host === $provider || $this->rights->allows($host) ? $url : null;
    }

    private function int(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    private function sorted(array $values): array
    {
        sort($values);

        return $values;
    }

    /**
     * Lignes brisées d'une géométrie GeoJSON (LineString ou MultiLineString).
     *
     * @return list<list<list<float>>>
     */
    private function lineStrings(string $geojson): array
    {
        $g = json_decode($geojson, true);
        if (! is_array($g)) {
            return [];
        }

        return match ($g['type'] ?? null) {
            'LineString' => [$g['coordinates']],
            'MultiLineString' => array_values($g['coordinates']),
            default => [],
        };
    }
}

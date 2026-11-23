<?php

namespace App\Services;

use App\Enums\ServiceEnd;
use App\Models\Station;
use App\Support\GeneratedText;
use App\Support\SourceRights;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lectures de la fiche gare (page /stations/{slug} et serveur MCP) : état à chaque décennie,
 * chronologie sourcée, lignes rattachées et sources de la gare avec leur lien. Lecture seule.
 *
 * Données : vff:import-station-sources → vff:consolidate-stations → vff:attach-stations →
 * vff:build-station-eras.
 */
final class StationSheet
{
    public function __construct(private readonly SourceRights $rights, private readonly StationChronology $chronology) {}

    /**
     * Fiche complète d'une gare (props de la page Stations/Show, reprises par l'API publique
     * GET /api/v1/stations/{station}) : identité, dates, position et sa provenance, présence par
     * décennie, chronologie, lignes desservies, sources et gares voisines (10 km, 12 au plus). Les
     * notes générées sont dans la langue courante de l'application. `$withRaw` ajoute à chaque source
     * sa date de lecture et ses attributs bruts (API publique : provenance en lecture seule) ; la page
     * web n'en a pas besoin.
     *
     * @return array<string, mixed>
     */
    public function page(Station $station, bool $withRaw = false): array
    {
        $sid = $station->id;
        $coords = DB::selectOne('SELECT ST_X(geom) AS lng, ST_Y(geom) AS lat FROM stations WHERE id = ?', [$sid]);

        $eras = $this->eras($sid);
        $events = $this->events($sid);
        $lines = $this->lines($sid);
        $sources = $this->sources($sid);
        $rawBySource = $withRaw ? $this->rawBySource($sid) : [];

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

        $page = [
            'station' => [
                'id' => $sid,
                'slug' => $station->slug,
                'name' => $station->name,
                'alt_names' => $station->alt_names ?? [],
                'type' => $station->type->value,
                'status' => self::displayStatus($station),
                'source_status' => $station->source_status,
                'lifecycle' => $station->lifecycle,
                'city' => $station->city,
                'department_code' => $station->department_code,
                'opened_at' => $station->opened_at?->format('Y-m-d'),
                'opened_precision' => $station->opened_precision,
                // Ouverture encadrée (précision range, règle G3) : ouverte après opened_after, au plus tard l'année de opened_at.
                'opened_after' => is_numeric($station->provenance['opened_after'] ?? null) ? (int) $station->provenance['opened_after'] : null,
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
                // year, upper_bound (au plus tard cette année) ou undated (fin non datée) : App\Enums\ServiceEnd.
                'last_year_kind' => ServiceEnd::of($l->last_year ? (int) $l->last_year : null, $station->closed_at?->format('Y-m-d'),
                    $station->closed_precision, (int) now()->year)?->value,
                // line_events, traces, station (années tirées des dates de la gare : aucun événement daté de la
                // ligne ne la concerne), never_served, ou null.
                'years_source' => $l->years_source,
                // Période vide (BuildStationEras::reconcileServiceYears) : ligne fermée avant l'ouverture de la
                // gare (line_closed), ou gare fermée avant l'ouverture de la ligne (station_closed).
                'never_served' => $l->years_source !== 'never_served' ? null
                    : ((int) $l->first_year === (int) $station->opened_at?->format('Y') ? 'line_closed' : 'station_closed'),
                // Date, précision et événement de ligne qui ouvrent et terminent la desserte, nuls quand seules
                // les années sont sûres (StationServiceDates::bounds).
                'service_start' => ($bounds = StationServiceDates::bounds($l))['start'],
                'service_end' => $bounds['end'],
                'active_current' => (bool) $l->active_current,
                'era_years' => json_decode($l->era_years, true) ?? [],
            ], $lines),
            'sources' => array_map(fn ($s) => [
                'source' => $s->source, 'ref' => $s->source_ref, 'name' => $s->name, 'lifecycle' => $s->lifecycle,
                'uic_code' => $s->uic_code, 'opened_on' => $s->opened_on, 'closed_on' => $s->closed_on,
                'match_method' => $s->match_method, 'distance_m' => (int) $s->distance_m,
                'passenger' => $s->passenger, 'freight' => $s->freight, 'pk_m' => $s->pk_m, 'geocoding' => $s->geocoding,
                'precision_m' => is_numeric($s->precision_m) ? (int) $s->precision_m : null,
                'url' => self::sourceUrl($s),
            ] + ($withRaw ? self::rawFor($rawBySource, $s) : []), $sources),
            'neighbors' => array_map(fn ($n) => [
                'slug' => $n->slug, 'name' => $n->name, 'type' => $n->type, 'city' => $n->city, 'status' => $n->status,
                'lng' => (float) $n->lng, 'lat' => (float) $n->lat, 'distance_m' => (int) $n->distance_m,
            ], $neighbors),
        ];
        // Vie de la gare en une liste datée : événements regroupés, dessertes des lignes, noms, accidents proches.
        $page['chronology'] = StationChronology::build($page['station'], $page['events'], $page['lines'],
            $this->chronology->accidents($sid, $locale));

        return $page;
    }

    /**
     * Présence par décennie (toutes les ères, statut null si la gare n'existe pas encore).
     *
     * @return list<object{year: int, is_current: bool, status: ?string, note: ?string}>
     */
    public function eras(int $stationId): array
    {
        return DB::select(<<<'SQL'
            SELECT e.year, e.is_current,
                   -- Incertaine : ouverte selon ses sources, non confirmée par les voies de l'ère.
                   CASE WHEN se.uncertain AND se.status <> 'declassified' THEN 'uncertain' ELSE se.status END AS status, se.note
            FROM eras e LEFT JOIN station_eras se ON se.era_id = e.id AND se.station_id = ?
            ORDER BY e.year
        SQL, [$stationId]);
    }

    /**
     * Chronologie (sources + dates déduites).
     *
     * @return Collection<int, \stdClass>
     */
    public function events(int $stationId): Collection
    {
        return DB::table('station_events')->where('station_id', $stationId)->orderBy('event_date')->orderBy('is_inferred')
            ->get(['type', 'event_date', 'date_precision', 'source', 'source_url', 'is_inferred', 'note']);
    }

    /**
     * Lignes rattachées (station_lines) + décennies où la gare est un arrêt de la ligne + desserte
     * actuelle (tronçon en service de l'ère courante). Gare pas encore rattachée (pipeline non
     * passé, ajout manuel) : repli sur les tronçons à moins de 300 m.
     *
     * @return list<object>
     */
    public function lines(int $stationId): array
    {
        $activeCurrent = <<<'SQL'
            EXISTS (SELECT 1 FROM line_segments x JOIN eras e ON e.id = x.era_id AND e.is_current
                    JOIN stations st ON st.id = ?
                    WHERE x.line_id = l.id AND x.status IN ('in_service', 'single_track')
                      AND x.geom && ST_Expand(st.geom, 0.007) AND ST_DWithin(x.geom::geography, st.geom::geography, 500))
            SQL;
        // Dates de début et de fin de desserte et événements sources (StationServiceDates::bounds).
        $dates = StationServiceDates::columns();
        $nullDates = StationServiceDates::NULL_COLUMNS;
        $lines = DB::select(<<<SQL
            SELECT l.slug, l.code, l.name, l.network, sl.method, sl.pk_m, sl.pk_source, sl.distance_m,
                   sl.first_year, sl.last_year, sl.years_source, {$activeCurrent} AS active_current, {$dates},
                   COALESCE((SELECT ARRAY_TO_JSON(ARRAY_AGG(e.year ORDER BY e.year)) FROM line_stops ls JOIN eras e ON e.id = ls.era_id
                             WHERE ls.line_id = l.id AND ls.station_id = sl.station_id), '[]'::json) AS era_years
            FROM station_lines sl JOIN lines l ON l.id = sl.line_id JOIN stations s ON s.id = sl.station_id
            WHERE sl.station_id = ?
            ORDER BY sl.last_year IS NOT NULL, sl.first_year NULLS LAST, l.name
        SQL, [$stationId, $stationId]);
        if ($lines === []) {
            $lines = DB::select(<<<SQL
                SELECT l.slug, l.code, l.name, l.network, 'segment' AS method, NULL AS pk_m, NULL AS pk_source,
                       MIN(ROUND(ST_Distance(s.geom::geography, ls.geom::geography)))::int AS distance_m,
                       NULL AS first_year, NULL AS last_year, NULL AS years_source, {$activeCurrent} AS active_current,
                       {$nullDates}, '[]'::json AS era_years
                FROM stations s
                JOIN line_segments ls ON ls.geom && ST_Expand(s.geom, 0.005) AND ST_DWithin(ls.geom::geography, s.geom::geography, 300)
                JOIN lines l ON l.id = ls.line_id
                WHERE s.id = ?
                GROUP BY l.id
                ORDER BY distance_m
                LIMIT 12
            SQL, [$stationId, $stationId]);
        }

        return $lines;
    }

    /**
     * Sources rattachées, avec écart à la position retenue.
     *
     * @return list<object>
     */
    public function sources(int $stationId): array
    {
        return DB::select(<<<'SQL'
            SELECT ss.source, ss.source_ref, ss.name, ss.kind, ss.lifecycle, ss.uic_code, ss.wikidata_id,
                   ss.opened_on, ss.closed_on, ss.match_method, ss.passenger, ss.freight,
                   ss.properties->>'frwiki' AS frwiki, ss.properties->>'source_url' AS source_url, ss.properties->>'pk_m' AS pk_m,
                   ss.properties->>'geocoding' AS geocoding, ss.properties->>'precision_m' AS precision_m,
                   ROUND(ST_Distance(ss.geom::geography, s.geom::geography))::int AS distance_m
            FROM station_sources ss JOIN stations s ON s.id = ss.station_id
            WHERE ss.station_id = ?
            ORDER BY CASE ss.source WHEN 'sncf' THEN 0 WHEN 'sncf_voyageurs' THEN 1 WHEN 'osm' THEN 2 WHEN 'wikidata' THEN 3 WHEN 'ohm' THEN 4 ELSE 5 END, ss.id
        SQL, [$stationId]);
    }

    /**
     * Date de lecture et attributs bruts de chaque source de la gare (colonnes `seen_at` et `properties`
     * de station_sources), indexés par « source|référence ». Provenance publiée par l'API (REST, GraphQL
     * et MCP) selon la règle de redistribution du registre des droits, comme les géométries : les
     * attributs bruts d'une source qui n'est pas sous licence ouverte vérifiée (statut incertain, usage
     * non commercial, source non déclarée) ne sont jamais republiés. `raw` vaut alors null et
     * `raw_withheld` vrai ; `provenance.license_status` dit pourquoi.
     *
     * @return array<string, array{fetched_at: ?string, raw: array<string, mixed>|\stdClass|null, raw_withheld: bool}>
     */
    public function rawBySource(int $stationId): array
    {
        $out = [];
        foreach (DB::select('SELECT source, source_ref, seen_at, properties FROM station_sources WHERE station_id = ?', [$stationId]) as $r) {
            $raw = json_decode((string) $r->properties, true);
            $url = is_array($raw) && is_string($raw['source_url'] ?? null) ? $raw['source_url'] : null;
            $open = $this->rights->allowsRedistribution($this->rights->providerFor('station_sources', $r->source, $url));
            $out[$r->source.'|'.$r->source_ref] = [
                'fetched_at' => $r->seen_at !== null ? substr((string) $r->seen_at, 0, 10) : null,
                'raw' => ! $open ? null : (is_array($raw) && $raw !== [] ? $raw : new \stdClass),
                'raw_withheld' => ! $open,
            ];
        }

        return $out;
    }

    /**
     * Date de lecture et attributs bruts d'une source de la fiche (cf. rawBySource).
     *
     * @param  array<string, array{fetched_at: ?string, raw: array<string, mixed>|\stdClass|null, raw_withheld: bool}>  $rawBySource
     * @return array{fetched_at: ?string, raw: array<string, mixed>|\stdClass|null, raw_withheld: bool}
     */
    public static function rawFor(array $rawBySource, object $source): array
    {
        return $rawBySource[$source->source.'|'.$source->source_ref] ?? ['fetched_at' => null, 'raw' => null, 'raw_withheld' => true];
    }

    /**
     * Statut affiché de la gare : point technique, fiche exclue du réseau ferré, gare projetée,
     * gare disparue selon ses sources, sinon son statut actuel.
     */
    public static function displayStatus(Station $station): string
    {
        return match (data_get($station->provenance, 'kind')) {
            'technical' => 'technical',
            'excluded' => 'excluded',
            'projected' => 'planned',
            default => $station->source_status === 'gone' ? 'gone' : (string) ($station->getRawOriginal('status') ?? 'active'),
        };
    }

    /** Lien vers l'objet de la source (fiche Wikidata, objet OSM ou OHM, jeu SNCF filtré). */
    public static function sourceUrl(object $s): ?string
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

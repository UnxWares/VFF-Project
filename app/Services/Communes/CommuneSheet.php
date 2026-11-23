<?php

namespace App\Services\Communes;

use App\Mcp\Support\NonCommercialSources;
use App\Models\Accident;
use App\Support\FrenchDepartments;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Fiche « Le rail à <commune> » (/communes/{insee}-{nom}, `GET /api/v1/communes/{insee}`) : ce que VFF sait du rail
 * d'une commune actuelle, tiré des gares, des portions de ligne et des événements déjà en base, jamais d'une saisie.
 *
 * - gares et haltes : celles dont le point est dans le contour de la commune (`commune_contours`, simplifié à 100 m),
 *   points techniques et fiches hors du réseau ferré exclus, comme l'annuaire des gares ;
 * - lignes : celles dont une portion de 50 m au moins est dans la commune, à l'une des ères. État actuel d'après les
 *   portions de l'ère courante (en service, ou fermée avec voie en place, déposée ou disparue de la carte), longueur
 *   dans la commune à la dernière ère où la ligne y est en service (à défaut, à la dernière où elle figure),
 *   dates d'ouverture et de fermeture des événements de la ligne dont la section passe par la commune (jamais ceux
 *   d'une source à usage non commerciale : la fiche ne les crédite pas, et l'API les servirait sans mention) ;
 * - chronologie : ces dates, celles des gares et les accidents dont la commune est le lieu, du plus ancien au
 *   plus récent (CHRONOLOGY_LIMIT entrées au plus) ;
 * - communes voisines qui ont du rail ; carte : contour, gares et tracés de la commune.
 *
 * Une commune sans gare ni ligne n'a pas de fiche (page() renvoie null). Lecture seule, quelques dizaines de
 * millisecondes (Paris : une seconde environ) : chaque requête passe par les index géométriques. La fiche est gardée
 * TTL secondes en cache par commune et par langue (page publique sans limite de débit, 16 000 communes à parcourir).
 */
final class CommuneSheet
{
    /** Longueur de voie minimale dans la commune pour y compter une ligne (mètres) : un tracé qui effleure la limite n'en est pas. */
    public const MIN_LINE_M = 50;

    /** Longueur minimale de la section d'un événement dans la commune pour qu'il la concerne (mètres). */
    public const MIN_EVENT_M = 100;

    /** Entrées de la chronologie au plus (les plus récentes d'abord retirées, jamais les ouvertures). */
    public const CHRONOLOGY_LIMIT = 400;

    /** Durée de garde d'une fiche (secondes), comme la liste des communes qui ont du rail (CommuneRail::TTL). */
    public const TTL = 86400;

    /** Préfixe de la clé de cache d'une fiche (suivi du code INSEE et de la langue). */
    public const CACHE_KEY = 'communes:sheet:v1:';

    /** Statuts d'une portion qui porte des trains (cf. SegmentStatus::isActive). */
    private const ACTIVE = ['in_service', 'single_track'];

    /** Événements de ligne repris dans la chronologie de la commune. */
    private const LINE_EVENTS = [
        'opening', 'opening_passenger', 'opening_freight', 'reopening', 'reopening_passenger', 'reopening_freight',
        'closure', 'closure_passenger', 'closure_freight', 'electrification', 'dismantling',
    ];

    /** Événements de gare repris (ouverture et fermeture viennent de la fiche de la gare). */
    public const STATION_EVENTS = ['reopening', 'closure_passenger', 'closure_freight', 'demolition'];

    /** Ordre d'entrées de même date : la vie du lieu se lit de l'ouverture à la fermeture. */
    private const ORDER = [
        'station_opening' => 0, 'line_opening' => 1, 'line_opening_passenger' => 1, 'line_opening_freight' => 1,
        'station_reopening' => 2, 'line_reopening' => 2, 'line_reopening_passenger' => 2, 'line_reopening_freight' => 2,
        'line_electrification' => 3, 'accident' => 4,
        'station_closure_passenger' => 5, 'line_closure_passenger' => 5, 'station_closure_freight' => 6, 'line_closure_freight' => 6,
        'station_closure' => 7, 'line_closure' => 7, 'line_dismantling' => 8, 'station_demolition' => 9,
    ];

    /** Précisions de la plus fine à la plus large (choix de l'entrée gardée quand deux sources se recouvrent). */
    private const RANK = ['day' => 0, 'month' => 1, 'year' => 2];

    public function __construct(private readonly CommuneRail $rail, private readonly NonCommercialSources $nonCommercial) {}

    /**
     * La fiche de la commune actuelle de ce code INSEE, null si elle n'existe pas ou n'a ni gare ni ligne.
     *
     * @return array<string, mixed>|null
     */
    public function page(string $insee, string $locale): ?array
    {
        /** @var array<string, mixed>|null */
        return Cache::remember(self::CACHE_KEY.$insee.':'.$locale, self::TTL, fn (): ?array => $this->compute($insee, $locale));
    }

    /**
     * La fiche calculée, sans cache.
     *
     * @return array<string, mixed>|null
     */
    private function compute(string $insee, string $locale): ?array
    {
        $commune = DB::table('communes')->where('code_insee', $insee)->where('type', 'commune-actuelle')
            ->first(['code_insee', 'nom', 'departement', 'population']);
        if ($commune === null || ! DB::table('commune_contours')->where('code_insee', $insee)->exists()) {
            return null;
        }

        $stations = $this->stations($insee);
        $lines = $this->lines($insee);
        if ($stations === [] && $lines === []) {
            return null;
        }
        $events = $this->lineEvents($insee, array_keys($lines));
        foreach ($events as $e) {
            $lines[$e['line_id']] = $this->withDates($lines[$e['line_id']], $e);
        }
        // Une ligne en service aujourd'hui n'est pas « fermée » : sa fermeture totale ancienne (un tronçon, rouvert
        // depuis) reste dans la chronologie, pas sur sa ligne.
        foreach ($lines as $id => $line) {
            if ($line['state'] === 'open' && ($line['closed']['scope'] ?? null) === 'all') {
                $lines[$id]['closed'] = null;
            }
        }

        $stationRows = array_values($stations);
        $lineRows = array_values($lines);
        $open = fn (array $s): bool => in_array($s['status'], ['active', 'tourist', 'no_commercial'], true);
        $department = $commune->departement === null ? null : (FrenchDepartments::ALL[$commune->departement][0] ?? null);

        return [
            'commune' => [
                'insee' => $insee,
                'slug' => CommuneRail::slug($insee, (string) $commune->nom),
                'name' => (string) $commune->nom,
                'department_code' => $commune->departement,
                'department_name' => $department,
                'population' => $commune->population === null ? null : (int) $commune->population,
                'former_names' => DB::table('communes')->where('chef_lieu', $insee)->whereNot('type', 'commune-actuelle')
                    ->orderBy('nom')->pluck('nom')->unique()->values()->all(),
            ],
            // Année de la carte « aujourd'hui » : les années des lignes (`first_year`, `last_year`, `km_year`) qui la valent se lisent « aujourd'hui ».
            'current_era' => (int) (DB::table('eras')->where('is_current', true)->value('year') ?? DB::table('eras')->max('year')),
            'counts' => [
                'stations' => count($stationRows),
                'stations_open' => count(array_filter($stationRows, $open)),
                'lines' => count($lineRows),
                'lines_open' => count(array_filter($lineRows, fn (array $l): bool => $l['state'] === 'open')),
            ],
            'stations' => array_map(fn (array $s): array => array_diff_key($s, ['id' => 1]), $stationRows),
            'lines' => array_map(fn (array $l): array => array_diff_key($l, ['line_id' => 1, 'era_id' => 1, 'years' => 1]), $lineRows),
            'chronology' => $this->chronology($stationRows, $lines, $events, $insee, $locale),
            'neighbors' => $this->neighbors($insee),
            'map' => $this->map($insee, $stationRows, $lineRows),
        ];
    }

    /**
     * Gares et haltes de la commune : ouvertes d'abord, puis par nom.
     *
     * @return array<int, array<string, mixed>> par identifiant de gare
     */
    private function stations(string $insee): array
    {
        $rows = DB::select(<<<'SQL'
            SELECT s.id, s.slug, s.name, s.type,
                   CASE WHEN s.provenance->>'kind' = 'projected' THEN 'planned' WHEN s.source_status = 'gone' THEN 'gone' ELSE s.status END AS status,
                   s.opened_at, s.opened_precision, (s.provenance->>'opened_after')::int AS opened_after,
                   s.closed_at, s.closed_precision, (s.provenance->>'closed_after')::int AS closed_after,
                   ST_X(s.geom) AS lng, ST_Y(s.geom) AS lat
            FROM commune_contours c
            JOIN stations s ON s.geom && c.geom AND ST_Contains(c.geom, s.geom)
            WHERE c.code_insee = ? AND COALESCE(s.provenance->>'kind', '') NOT IN ('technical', 'excluded')
            ORDER BY (CASE WHEN s.status IN ('active', 'tourist', 'no_commercial') THEN 0 ELSE 1 END), s.name COLLATE "fr-x-icu", s.id
        SQL, [$insee]);

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->id] = [
                'id' => (int) $r->id, 'slug' => $r->slug, 'name' => $r->name, 'type' => $r->type, 'status' => $r->status,
                'opened_at' => $r->opened_at, 'opened_precision' => $r->opened_precision,
                'opened_after' => $r->opened_after === null ? null : (int) $r->opened_after,
                'closed_at' => $r->closed_at, 'closed_precision' => $r->closed_precision,
                'closed_after' => $r->closed_after === null ? null : (int) $r->closed_after,
                'lng' => (float) $r->lng, 'lat' => (float) $r->lat,
            ];
        }

        return $out;
    }

    /**
     * Lignes de la commune (clé : identifiant de ligne), en service d'abord, puis par nom.
     *
     * @return array<int, array<string, mixed>>
     */
    private function lines(string $insee): array
    {
        // ST_MakeValid : une vingtaine de contours ont une auto-intersection, qui fait échouer ST_Intersection.
        $rows = DB::select(<<<'SQL'
            SELECT g.line_id, g.era_id, e.year, e.is_current, g.status,
                   SUM(ST_Length(ST_Intersection(g.geom, ST_MakeValid(c.geom))::geography)) AS meters
            FROM commune_contours c
            JOIN line_segments g ON g.geom && c.geom AND ST_Intersects(g.geom, c.geom)
            JOIN eras e ON e.id = g.era_id
            WHERE c.code_insee = ?
            GROUP BY g.line_id, g.era_id, e.year, e.is_current, g.status
            HAVING SUM(ST_Length(ST_Intersection(g.geom, ST_MakeValid(c.geom))::geography)) >= ?
        SQL, [$insee, self::MIN_LINE_M]);
        if ($rows === []) {
            return [];
        }

        // Par ligne : ères où elle porte des trains, ère actuelle, et ère de référence de la longueur.
        $by = [];
        foreach ($rows as $r) {
            $by[(int) $r->line_id][] = $r;
        }
        $meta = DB::table('lines')->whereIn('id', array_keys($by))->get(['id', 'slug', 'name', 'code', 'is_lgv'])->keyBy('id');

        $out = [];
        foreach ($by as $lineId => $parts) {
            $line = $meta[$lineId] ?? null;
            if ($line === null) {
                continue;
            }
            $active = array_filter($parts, fn ($p): bool => in_array($p->status, self::ACTIVE, true));
            $years = array_values(array_unique(array_map(fn ($p): int => (int) $p->year, $active)));
            sort($years);
            $reference = $active !== [] ? $active : $parts;
            $refYear = max(array_map(fn ($p): int => (int) $p->year, $reference));
            $refParts = array_filter($reference, fn ($p): bool => (int) $p->year === $refYear);
            $current = array_filter($parts, fn ($p): bool => (bool) $p->is_current);
            $currentActive = array_filter($current, fn ($p): bool => in_array($p->status, self::ACTIVE, true));
            $out[$lineId] = [
                'line_id' => $lineId,
                'era_id' => (int) reset($refParts)->era_id,
                'slug' => $line->slug, 'name' => $line->name, 'code' => $line->code, 'is_lgv' => (bool) $line->is_lgv,
                // open : portions en service à l'ère courante ; closed : fermée (voie en place, déposée ou disparue de la carte).
                'state' => $currentActive !== [] ? 'open' : 'closed',
                'track' => $currentActive !== [] ? null : $this->trackState($current),
                'km' => round(array_sum(array_map(fn ($p): float => (float) $p->meters, $refParts)) / 1000, 1),
                'km_year' => $refYear,
                'years' => $years,
                'first_year' => $years[0] ?? null,
                'last_year' => $years === [] ? null : end($years),
                'opened' => null, 'closed' => null, 'electrified' => null,
            ];
        }
        uasort($out, fn (array $a, array $b): int => [$a['state'] === 'open' ? 0 : 1, mb_strtolower($a['name'])] <=> [$b['state'] === 'open' ? 0 : 1, mb_strtolower($b['name'])]);

        return $out;
    }

    /**
     * Voie d'une ligne fermée à l'ère courante : en place (abandonnée), déposée, ou absente de la carte.
     *
     * @param  array<int, object>  $current  portions de la commune à l'ère courante
     */
    private function trackState(array $current): string
    {
        $statuses = array_map(fn ($p): string => (string) $p->status, $current);
        if ($statuses === []) {
            return 'none';
        }

        return in_array('abandoned', $statuses, true) ? 'in_place' : (in_array('dismantled', $statuses, true) ? 'dismantled' : 'in_place');
    }

    /**
     * Événements datés des lignes de la commune dont la section passe par elle, une entrée par ligne, type et date
     * (sections réunies) ; la plus précise quand deux sources donnent la même année.
     *
     * @param  list<int>  $lineIds
     * @return list<array{line_id: int, type: string, date: string, precision: string, sections: list<array{from: string, to: string}>}>
     */
    private function lineEvents(string $insee, array $lineIds): array
    {
        if ($lineIds === []) {
            return [];
        }
        $types = implode(',', array_map(fn (string $t): string => "'{$t}'", self::LINE_EVENTS));
        $rows = DB::select(<<<SQL
            SELECT le.line_id, le.type, le.event_date, le.date_precision, le.section_from, le.section_to, le.section_resolution
            FROM commune_contours c
            JOIN line_events le ON le.section_geom && c.geom AND ST_Intersects(le.section_geom, c.geom)
            WHERE c.code_insee = ? AND le.type IN ({$types}) AND le.event_date IS NOT NULL AND le.line_id = ANY(?::bigint[])
              AND {$this->nonCommercial->sqlKept('line_events', 'le.source', 'le.source_url')}
              AND ST_Length(ST_Intersection(le.section_geom, ST_MakeValid(c.geom))::geography) >= ?
            ORDER BY le.event_date, le.id
        SQL, [$insee, '{'.implode(',', $lineIds).'}', self::MIN_EVENT_M]);

        $grouped = [];
        foreach ($rows as $r) {
            $precision = $r->date_precision ?? 'day';
            // Une même année donnée par deux sources (« 1875 » et « 12 mai 1875 ») n'est qu'un fait : la plus précise.
            $key = $r->line_id.'|'.$r->type.'|'.substr((string) $r->event_date, 0, 4);
            $known = $grouped[$key] ?? null;
            if ($known === null || (self::RANK[$precision] ?? 3) < (self::RANK[$known['precision']] ?? 3)) {
                $grouped[$key] = [
                    'line_id' => (int) $r->line_id, 'type' => $r->type, 'date' => (string) $r->event_date,
                    'precision' => $precision, 'sections' => $known['sections'] ?? [],
                ];
            }
            if ($r->section_resolution !== 'whole_line' && $r->section_from !== null && $r->section_to !== null) {
                $section = ['from' => (string) $r->section_from, 'to' => (string) $r->section_to];
                if (! in_array($section, $grouped[$key]['sections'], true) && count($grouped[$key]['sections']) < 3) {
                    $grouped[$key]['sections'][] = $section;
                }
            }
        }

        return array_values($grouped);
    }

    /**
     * Ajoute à la ligne la première ouverture, la dernière fermeture (sauf réouverture ensuite) et l'électrification.
     *
     * @param  array<string, mixed>  $line
     * @param  array{type: string, date: string, precision: string}  $event
     * @return array<string, mixed>
     */
    private function withDates(array $line, array $event): array
    {
        $dated = ['date' => $event['date'], 'precision' => $event['precision']];
        $type = $event['type'];
        if (in_array($type, ['opening', 'opening_passenger', 'opening_freight'], true) && ($line['opened'] === null || $event['date'] < $line['opened']['date'])) {
            $line['opened'] = $dated;
        }
        if ($type === 'electrification' && ($line['electrified'] === null || $event['date'] < $line['electrified']['date'])) {
            $line['electrified'] = $dated;
        }
        if (in_array($type, ['closure', 'closure_passenger', 'closure_freight'], true)) {
            $scope = $type === 'closure' ? 'all' : substr($type, 8);
            // La fermeture totale prime sur celle d'un seul trafic ; à rang égal, la plus récente.
            $rank = fn (string $s): int => $s === 'all' ? 1 : 0;
            $known = $line['closed'];
            if ($known === null || [$rank($scope), $event['date']] > [$rank($known['scope']), $known['date']]) {
                $line['closed'] = $dated + ['scope' => $scope];
            }
        }
        if (str_starts_with($type, 'reopening') && $line['closed'] !== null && $event['date'] > $line['closed']['date']) {
            $line['closed'] = null;
        }

        return $line;
    }

    /**
     * Chronologie fusionnée : gares (ouverture, fermeture, événements), lignes (ouverture, fermeture,
     * électrification…) et accidents de la commune, du plus ancien au plus récent.
     *
     * @param  list<array<string, mixed>>  $stations
     * @param  array<int, array<string, mixed>>  $lines
     * @param  list<array<string, mixed>>  $events
     * @return list<array<string, mixed>>
     */
    private function chronology(array $stations, array $lines, array $events, string $insee, string $locale): array
    {
        $entries = [];
        foreach ($stations as $s) {
            $ref = ['slug' => $s['slug'], 'name' => $s['name']];
            if ($s['opened_at'] !== null) {
                $entries[] = ['kind' => 'station_opening', 'date' => $s['opened_at'], 'precision' => $s['opened_precision'] ?? 'day', 'after' => $s['opened_after'], 'station' => $ref];
            }
            if ($s['closed_at'] !== null) {
                $entries[] = ['kind' => 'station_closure', 'date' => $s['closed_at'], 'precision' => $s['closed_precision'] ?? 'day', 'after' => $s['closed_after'], 'station' => $ref];
            }
        }
        $entries = [...$entries, ...$this->stationEvents(array_column($stations, 'id'), array_column($stations, null, 'id'))];
        foreach ($events as $e) {
            $line = $lines[$e['line_id']];
            $entries[] = [
                'kind' => 'line_'.$e['type'], 'date' => $e['date'], 'precision' => $e['precision'], 'after' => null,
                'line' => ['slug' => $line['slug'], 'name' => $line['name'], 'code' => $line['code']],
                'sections' => $e['sections'],
            ];
        }
        foreach ($this->accidents($insee, $locale) as $a) {
            $entries[] = ['kind' => 'accident', 'date' => $a['date'], 'precision' => $a['date_precision'], 'after' => null, 'accident' => $a];
        }

        usort($entries, fn (array $a, array $b): int => [$a['date'], self::ORDER[$a['kind']] ?? 5] <=> [$b['date'], self::ORDER[$b['kind']] ?? 5]);

        return array_slice($entries, 0, self::CHRONOLOGY_LIMIT);
    }

    /**
     * Événements des gares de la commune (réouverture, fin des voyageurs ou du fret, démolition), une entrée par gare,
     * type et année (la plus précise).
     *
     * @param  list<int>  $ids
     * @param  array<int, array<string, mixed>>  $byId
     * @return list<array<string, mixed>>
     */
    private function stationEvents(array $ids, array $byId): array
    {
        if ($ids === []) {
            return [];
        }
        $rows = DB::table('station_events')->whereIn('station_id', $ids)->whereIn('type', self::STATION_EVENTS)
            ->whereNotNull('event_date')->orderBy('event_date')->orderBy('id')->get(['station_id', 'type', 'event_date', 'date_precision']);

        $kept = [];
        foreach ($rows as $r) {
            $precision = $r->date_precision ?? 'day';
            $key = $r->station_id.'|'.$r->type.'|'.substr((string) $r->event_date, 0, 4);
            if (! isset($kept[$key]) || (self::RANK[$precision] ?? 3) < (self::RANK[$kept[$key]['precision']] ?? 3)) {
                $s = $byId[(int) $r->station_id];
                $kept[$key] = [
                    'kind' => 'station_'.$r->type, 'date' => (string) $r->event_date, 'precision' => $precision, 'after' => null,
                    'station' => ['slug' => $s['slug'], 'name' => $s['name']],
                ];
            }
        }

        return array_values($kept);
    }

    /**
     * Accidents publiés dont la commune est le lieu.
     *
     * @return list<array<string, mixed>>
     */
    private function accidents(string $insee, string $locale): array
    {
        return Accident::query()->where('commune_insee', $insee)->orderBy('occurred_on')->orderBy('id')->limit(60)
            ->get(['slug', 'name', 'occurred_on', 'date_precision', 'kind', 'deaths', 'injuries'])
            ->map(fn (Accident $a): array => [
                'slug' => $a->slug, 'name' => $a->nameIn($locale), 'date' => $a->occurred_on->toDateString(),
                'date_precision' => $a->date_precision->value, 'kind' => $a->kind->value, 'deaths' => $a->deaths, 'injuries' => $a->injuries,
            ])->all();
    }

    /**
     * Communes qui touchent celle-ci (à 25 m près : les contours sont simplifiés) et ont du rail, par nom.
     *
     * @return list<array{insee: string, slug: string, name: string}>
     */
    private function neighbors(string $insee): array
    {
        $rows = DB::select(<<<'SQL'
            SELECT n.code_insee, m.nom
            FROM commune_contours c
            JOIN commune_contours n ON n.code_insee <> c.code_insee AND n.geom && ST_Expand(c.geom, 0.0003) AND ST_DWithin(n.geom, c.geom, 0.0003)
            JOIN communes m ON m.code_insee = n.code_insee AND m.type = 'commune-actuelle'
            WHERE c.code_insee = ? AND n.code_insee = ANY(?::text[])
            ORDER BY m.nom COLLATE "fr-x-icu", n.code_insee
            LIMIT 30
        SQL, [$insee, '{'.implode(',', $this->rail->codes()).'}']);

        return array_map(fn ($r): array => [
            'insee' => (string) $r->code_insee, 'slug' => CommuneRail::slug((string) $r->code_insee, (string) $r->nom), 'name' => (string) $r->nom,
        ], $rows);
    }

    /**
     * Données de la carte : contour, gares et tracés de la commune (à la dernière ère où chaque ligne y est en
     * service, la même que la longueur), au plus MAP_LINES tronçons.
     *
     * @param  list<array<string, mixed>>  $stations
     * @param  list<array<string, mixed>>  $lines
     * @return array{contour: array<string, mixed>|null, stations: list<array<string, mixed>>, lines: list<array<string, mixed>>}
     */
    private function map(string $insee, array $stations, array $lines): array
    {
        $contour = DB::selectOne('SELECT ST_AsGeoJSON(geom, 5) AS g FROM commune_contours WHERE code_insee = ?', [$insee]);
        $traces = [];
        if ($lines !== []) {
            $bySlug = array_column($lines, null, 'slug');
            $rows = DB::select(<<<'SQL'
                SELECT l.slug, ST_AsGeoJSON(ST_SimplifyPreserveTopology(ST_Intersection(g.geom, ST_MakeValid(c.geom)), 0.00003), 5) AS geometry
                FROM commune_contours c
                JOIN line_segments g ON g.geom && c.geom AND ST_Intersects(g.geom, c.geom)
                JOIN lines l ON l.id = g.line_id
                JOIN unnest(?::bigint[], ?::bigint[]) AS ref(line_id, era_id) ON ref.line_id = g.line_id AND ref.era_id = g.era_id
                WHERE c.code_insee = ? AND g.status IN ('in_service', 'single_track', 'abandoned', 'dismantled', 'service_track')
                LIMIT 800
            SQL, ['{'.implode(',', array_column($lines, 'line_id')).'}', '{'.implode(',', array_column($lines, 'era_id')).'}', $insee]);
            foreach ($rows as $r) {
                $geometry = json_decode((string) $r->geometry, true);
                if (is_array($geometry) && ! in_array($geometry['type'] ?? '', ['Point', 'MultiPoint', 'GeometryCollection'], true)) {
                    $traces[] = ['slug' => $r->slug, 'state' => $bySlug[$r->slug]['state'], 'geometry' => $geometry];
                }
            }
        }

        return [
            'contour' => $contour === null ? null : json_decode((string) $contour->g, true),
            'stations' => array_map(fn (array $s): array => ['slug' => $s['slug'], 'name' => $s['name'], 'status' => $s['status'], 'lng' => $s['lng'], 'lat' => $s['lat']], $stations),
            'lines' => $traces,
        ];
    }
}

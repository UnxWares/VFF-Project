<?php

namespace App\Services\Departments;

use App\Models\Accident;
use App\Services\Communes\CommuneRail;
use App\Support\FrenchDepartments;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Fiches « Le rail dans <département> » (/departments/{code}-{nom}, `GET /api/v1/departments/{code}/history`) et
 * tableau de tous les départements (/departments, `GET /api/v1/stats/departments`).
 *
 * Tout est calculé par `vff:compute-department-figures` (aucune planification) et rangé dans le cache sans
 * expiration : la page publique ne calcule jamais, elle lit une entrée par département (`sheet()`) et l'index
 * (`index()`). Sans calcul préalable, elles rendent null. Lecture seule en base, requêtes bornées.
 *
 * Mesures (mêmes statuts que « Le réseau en chiffres », NetworkFigures) :
 *   - longueurs : `length_meters` des tronçons de la carte (sinon longueur géographique), voies parallèles
 *     comprises. Un tronçon n'a pas de département : sa longueur est répartie sur des points échantillonnés tous
 *     les 500 m (au plus), chacun versé au département dont un contour de commune (IGN, simplifié à 100 m) le
 *     contient. À 250 m près par limite franchie ; environ 1 % de la longueur tombe hors des contours (frontières,
 *     côtes) et n'est comptée nulle part ;
 *   - ouvert = « en service » ou « voie unique » ; fermé = « abandonné » ou « voie de service » ; déposé = « déposé »
 *     ou « arasé » ;
 *   - part fermée : part de la longueur ouverte en 1930, ligne par ligne, qui n'est plus ouverte aujourd'hui ;
 *   - gares : celles de la table `station_eras` (statut à l'ère), départements de la fiche de gare, sans point
 *     technique ni fiche hors du réseau ferré ;
 *   - dates : ouvertures, réouvertures, fermetures, dépôts et électrifications datés dont la section de ligne passe
 *     au moins 100 m dans le département, sans les dates qui ne sont qu'une année tirée du nom d'un tracé.
 *
 * Seules des longueurs et des comptes sont rangés ; la géométrie de la carte n'est pas une donnée de l'API.
 */
final class DepartmentFigures
{
    public const INDEX_KEY = 'department_figures:v1:index';

    public const SHEET_KEY = 'department_figures:v1:sheet:';

    /** Longueur de voie minimale dans le département pour y compter une ligne (mètres). */
    public const MIN_LINE_M = 1000;

    /** Longueur minimale de la section d'un événement dans le département (mètres). */
    public const MIN_EVENT_M = 100;

    /** Pas d'échantillonnage d'un tronçon (mètres). */
    private const STEP_M = 500;

    /** Nombre de tranches de lignes par ère : chaque requête reste sous la limite de 30 secondes. */
    private const CHUNKS = 6;

    /** Ère de référence du tableau (la plus grande extension du réseau). */
    public const REFERENCE_YEAR = 1930;

    private const EVENT_TYPES = [
        'opening', 'opening_passenger', 'opening_freight', 'reopening', 'reopening_passenger', 'reopening_freight',
        'closure', 'closure_passenger', 'closure_freight', 'dismantling', 'electrification',
    ];

    private const OPEN_STATIONS = ['active', 'tourist', 'no_commercial'];

    public const ACCIDENT_LIMIT = 10;

    /** @var callable(string): void|null */
    private $progress = null;

    private int $reference = self::REFERENCE_YEAR;

    /** Ère de référence du tableau, autre que 1930 (tests, ou une carte qui ne la compterait pas). */
    public function referenceYear(int $year): self
    {
        $this->reference = $year;

        return $this;
    }

    /** Avancement du calcul (une ligne de texte à la fois), pour la commande. */
    public function onProgress(?callable $progress): self
    {
        $this->progress = $progress;

        return $this;
    }

    // ---------------------------------------------------------------------------------------------------------
    // Lecture (cache)
    // ---------------------------------------------------------------------------------------------------------

    /**
     * Le tableau de tous les départements, null tant que rien n'a été calculé.
     *
     * @return array{computed_at: string, reference_year: int, current_year: int, departments: list<array<string, mixed>>}|null
     */
    public function index(): ?array
    {
        $cached = Cache::get(self::INDEX_KEY);

        return is_array($cached) ? $cached : null;
    }

    /**
     * La fiche d'un département (code `16`, `2A`, `971`), null s'il n'est pas calculé. Les noms d'accidents sont
     * dans la langue demandée.
     *
     * @return array<string, mixed>|null
     */
    public function sheet(string $code, string $locale): ?array
    {
        $cached = Cache::get(self::SHEET_KEY.strtoupper($code));
        if (! is_array($cached)) {
            return null;
        }
        $cached['accidents']['list'] = array_map(function (array $a) use ($locale): array {
            $names = $a['names'];
            unset($a['names']);

            return ['name' => $names[$locale] ?? $names['fr'] ?? $a['slug']] + $a;
        }, $cached['accidents']['list']);

        return $cached;
    }

    /** Adresse relative d'une fiche : `16-charente`. */
    public static function slug(string $code, string $name): string
    {
        $words = Str::slug($name);

        return $words === '' ? $code : "{$code}-{$words}";
    }

    /** Code d'une adresse `16-charente` (ou `16`, `2a-corse-du-sud`) : majuscules, null s'il n'en commence pas par un. */
    public static function codeOf(string $slug): ?string
    {
        return preg_match('/^([0-9]{2,3}|2[ABab])(?:-[a-z0-9-]*)?$/', $slug, $m) === 1 ? strtoupper($m[1]) : null;
    }

    // ---------------------------------------------------------------------------------------------------------
    // Calcul
    // ---------------------------------------------------------------------------------------------------------

    /**
     * Calcule les fiches (toutes, ou celles des codes demandés) et l'index ; ne touche pas au cache.
     *
     * @param  list<string>|null  $only  codes de départements (l'index n'est alors calculé que pour eux)
     * @return array{index: array<string, mixed>, sheets: array<string, array<string, mixed>>}
     */
    public function compute(?array $only = null): array
    {
        DB::statement('SET statement_timeout = 30000');
        $eras = DB::table('eras')->orderBy('year')->get(['id', 'year', 'is_current']);
        $years = $eras->pluck('year')->map(fn ($y): int => (int) $y)->all();
        $currentEra = $eras->firstWhere('is_current', true);
        $current = (int) ($currentEra->year ?? end($years));
        $eraIds = $eras->pluck('id', 'year')->map(fn ($id): int => (int) $id)->all();

        $this->say('Longueurs par département et par ère...');
        $meters = $this->meters($eras->all());
        // Les clés numériques d'un tableau PHP deviennent des entiers : tout se compare en chaînes.
        $order = array_map('strval', array_keys(FrenchDepartments::ALL));
        $codes = array_values(array_filter(array_map('strval', array_keys($meters)), fn (string $c): bool => isset(FrenchDepartments::ALL[$c]) && ($only === null || in_array($c, $only, true))));
        usort($codes, fn (string $a, string $b): int => array_search($a, $order, true) <=> array_search($b, $order, true));

        $this->say('Gares par ère et par commune...');
        $stations = $this->stationsByEra($years);
        $communes = $this->communes();
        $this->say('Dates des lignes...');
        $events = $this->events();
        $this->say('Accidents...');
        $accidents = $this->accidents($years);
        $lineMeta = DB::table('lines')->get(['id', 'slug', 'name', 'code', 'is_lgv'])->keyBy('id')->all();

        $sheets = [];
        foreach ($codes as $code) {
            $this->say("Fiche {$code}...");
            $sheets[$code] = $this->sheetFor($code, $meters[$code], $years, $current, $eraIds, $stations[$code] ?? [], $communes[$code] ?? [], $events[$code] ?? [], $accidents[$code] ?? null, $lineMeta);
        }

        $index = [
            'computed_at' => now()->toIso8601String(),
            'reference_year' => $this->reference,
            'current_year' => $current,
            'departments' => array_values(array_map(fn (array $s): array => $s['summary'], $sheets)),
        ];

        return ['index' => $index, 'sheets' => $sheets];
    }

    /** Calcule puis range dans le cache (sans expiration). */
    public function refresh(?array $only = null): array
    {
        $result = $this->compute($only);
        foreach ($result['sheets'] as $code => $sheet) {
            Cache::forever(self::SHEET_KEY.$code, $sheet);
        }
        if ($only === null) {
            Cache::forever(self::INDEX_KEY, $result['index']);
        } elseif (($known = $this->index()) !== null) {
            // Calcul partiel : les départements recalculés remplacent leur ligne, les autres restent.
            $byCode = array_column($known['departments'], null, 'code');
            foreach ($result['index']['departments'] as $row) {
                $byCode[$row['code']] = $row;
            }
            $order = array_map('strval', array_keys(FrenchDepartments::ALL));
            uksort($byCode, fn ($a, $b): int => array_search((string) $a, $order, true) <=> array_search((string) $b, $order, true));
            Cache::forever(self::INDEX_KEY, ['departments' => array_values($byCode)] + $result['index']);
        }

        return $result;
    }

    private function say(string $line): void
    {
        if ($this->progress !== null) {
            ($this->progress)($line);
        }
    }

    /**
     * Mètres de voie par département, ligne, année d'ère et état du tronçon : open (en service, voie unique), closed (fermée, voie en place), service (voie de service), dismantled (déposée), razed (emprise effacée), comme la légende de la carte.
     *
     * @param  iterable<object{id: int, year: int}>  $eras
     * @return array<string, array<int, array<int, array{open: float, closed: float, service: float, dismantled: float, razed: float}>>> [département][ligne][année]
     */
    private function meters(iterable $eras): array
    {
        $step = self::STEP_M;
        $chunks = self::CHUNKS;
        $out = [];
        foreach ($eras as $era) {
            for ($chunk = 0; $chunk < $chunks; $chunk++) {
                $rows = DB::select(<<<SQL
                    WITH seg AS (
                        SELECT line_id, status, COALESCE(length_meters, ST_Length(geom::geography)) AS len, geom
                        FROM line_segments WHERE era_id = ? AND line_id % {$chunks} = ?
                    ), pts AS (
                        SELECT s.line_id, s.status, s.len / c.n AS part, ST_LineInterpolatePoint(s.geom, (i - 0.5) / c.n) AS p
                        FROM seg s
                        CROSS JOIN LATERAL (SELECT GREATEST(1, CEIL(s.len / {$step}.0))::int AS n) c
                        CROSS JOIN LATERAL generate_series(1, c.n) i
                    )
                    SELECT d.departement, p.line_id,
                           SUM(p.part) FILTER (WHERE p.status IN ('in_service', 'single_track')) AS open_m,
                           SUM(p.part) FILTER (WHERE p.status = 'abandoned') AS closed_m,
                           SUM(p.part) FILTER (WHERE p.status = 'service_track') AS service_m,
                           SUM(p.part) FILTER (WHERE p.status = 'dismantled') AS dismantled_m,
                           SUM(p.part) FILTER (WHERE p.status = 'razed') AS razed_m
                    FROM pts p
                    JOIN LATERAL (SELECT c.departement FROM commune_contours c WHERE c.geom && p.p AND ST_Contains(c.geom, p.p) LIMIT 1) d ON TRUE
                    WHERE d.departement IS NOT NULL
                    GROUP BY d.departement, p.line_id
                SQL, [$era->id, $chunk]);
                foreach ($rows as $r) {
                    $out[(string) $r->departement][(int) $r->line_id][(int) $era->year] = [
                        'open' => (float) $r->open_m, 'closed' => (float) $r->closed_m, 'service' => (float) $r->service_m,
                        'dismantled' => (float) $r->dismantled_m, 'razed' => (float) $r->razed_m,
                    ];
                }
            }
        }

        return $out;
    }

    /**
     * Gares par département, année d'ère et statut.
     *
     * @param  list<int>  $years
     * @return array<string, array<int, array{active: int, tourist: int, no_commercial: int, declassified: int}>>
     */
    private function stationsByEra(array $years): array
    {
        $rows = DB::select(<<<'SQL'
            SELECT s.department_code AS code, e.year, se.status, COUNT(*) AS n
            FROM station_eras se
            JOIN stations s ON s.id = se.station_id
            JOIN eras e ON e.id = se.era_id
            WHERE s.department_code IS NOT NULL AND COALESCE(s.provenance->>'kind', '') NOT IN ('technical', 'excluded')
            GROUP BY s.department_code, e.year, se.status
        SQL);
        $out = [];
        foreach ($rows as $r) {
            $out[(string) $r->code][(int) $r->year] ??= ['active' => 0, 'tourist' => 0, 'no_commercial' => 0, 'declassified' => 0];
            if (isset($out[(string) $r->code][(int) $r->year][$r->status])) {
                $out[(string) $r->code][(int) $r->year][$r->status] = (int) $r->n;
            }
        }

        return $out;
    }

    /**
     * Communes qui ont du rail (celles de CommuneRail) par département, avec leurs gares.
     *
     * @return array<string, list<array{insee: string, slug: string, name: string, population: int|null, stations: int, stations_open: int}>>
     */
    private function communes(): array
    {
        $codes = (new CommuneRail)->codes();
        $counts = [];
        $rows = DB::select(<<<'SQL'
            SELECT c.code_insee, COUNT(*) AS n,
                   COUNT(*) FILTER (WHERE s.status IN ('active', 'tourist', 'no_commercial') AND COALESCE(s.provenance->>'kind', '') <> 'projected' AND s.source_status IS DISTINCT FROM 'gone') AS open
            FROM stations s
            JOIN commune_contours c ON s.geom && c.geom AND ST_Contains(c.geom, s.geom)
            WHERE COALESCE(s.provenance->>'kind', '') NOT IN ('technical', 'excluded')
            GROUP BY c.code_insee
        SQL);
        foreach ($rows as $r) {
            $counts[(string) $r->code_insee] = [(int) $r->n, (int) $r->open];
        }
        $out = [];
        foreach (array_chunk($codes, 2000) as $chunk) {
            $found = DB::table('communes')->where('type', 'commune-actuelle')->whereRaw('code_insee = ANY(?::text[])', ['{'.implode(',', $chunk).'}'])
                ->get(['code_insee', 'nom', 'departement', 'population']);
            foreach ($found as $c) {
                if ($c->departement === null) {
                    continue;
                }
                [$n, $open] = $counts[$c->code_insee] ?? [0, 0];
                $out[$c->departement][] = [
                    'insee' => (string) $c->code_insee, 'slug' => CommuneRail::slug((string) $c->code_insee, (string) $c->nom), 'name' => (string) $c->nom,
                    'population' => $c->population === null ? null : (int) $c->population, 'stations' => $n, 'stations_open' => $open,
                ];
            }
        }
        foreach ($out as &$list) {
            usort($list, fn (array $a, array $b): int => [-$a['stations_open'], -$a['stations'], $a['name']] <=> [-$b['stations_open'], -$b['stations'], $b['name']]);
        }

        return $out;
    }

    /**
     * Événements datés par département, une entrée par ligne, type et année (la plus précise), sections réunies.
     *
     * @return array<string, list<array{line_id: int, type: string, date: string, precision: string}>>
     */
    private function events(): array
    {
        $types = implode(',', array_map(fn (string $t): string => "'{$t}'", self::EVENT_TYPES));
        $max = (int) DB::table('line_events')->max('id');
        $rank = ['day' => 0, 'month' => 1, 'year' => 2];
        $kept = [];
        for ($from = 0; $from <= $max; $from += 2000) {
            $rows = DB::select(<<<SQL
                SELECT c.departement, le.line_id, le.type, le.event_date, le.date_precision
                FROM line_events le
                JOIN commune_contours c ON le.section_geom && c.geom AND ST_Intersects(le.section_geom, c.geom)
                WHERE le.id > ? AND le.id <= ? AND le.type IN ({$types}) AND c.departement IS NOT NULL
                  AND NOT (le.source = 'archeoferroviaire' AND le.date_precision = 'year')
                GROUP BY c.departement, le.id, le.line_id, le.type, le.event_date, le.date_precision
                HAVING SUM(ST_Length(ST_Intersection(le.section_geom, ST_MakeValid(c.geom))::geography)) >= ?
            SQL, [$from, $from + 2000, self::MIN_EVENT_M]);
            foreach ($rows as $r) {
                $precision = $r->date_precision ?? 'day';
                $key = $r->departement.'|'.$r->line_id.'|'.$r->type.'|'.substr((string) $r->event_date, 0, 4);
                if (! isset($kept[$key]) || ($rank[$precision] ?? 3) < ($rank[$kept[$key]['precision']] ?? 3)) {
                    $kept[$key] = ['department' => (string) $r->departement, 'line_id' => (int) $r->line_id, 'type' => (string) $r->type, 'date' => (string) $r->event_date, 'precision' => $precision];
                }
            }
        }
        $out = [];
        foreach ($kept as $e) {
            $out[$e['department']][] = ['line_id' => $e['line_id'], 'type' => $e['type'], 'date' => $e['date'], 'precision' => $e['precision']];
        }

        return $out;
    }

    /**
     * Accidents publiés par département : total, morts, décennies de la carte, les plus lourds.
     *
     * @param  list<int>  $years
     * @return array<string, array{count: int, deaths: int, by_period: array<int, array{count: int, deaths: int}>, list: list<array<string, mixed>>}>
     */
    private function accidents(array $years): array
    {
        $first = min($years);
        $last = max($years);
        $out = [];
        $all = Accident::query()->whereNotNull('department_code')->orderBy('occurred_on')->orderBy('id')
            ->get(['slug', 'name', 'occurred_on', 'date_precision', 'kind', 'deaths', 'injuries', 'commune_name', 'department_code']);
        foreach ($all as $a) {
            $code = (string) $a->department_code;
            $period = max($first, min($last, (int) (ceil(((int) $a->occurred_on->format('Y')) / 10) * 10)));
            $out[$code]['count'] = ($out[$code]['count'] ?? 0) + 1;
            $out[$code]['deaths'] = ($out[$code]['deaths'] ?? 0) + (int) $a->deaths;
            $out[$code]['by_period'][$period]['count'] = ($out[$code]['by_period'][$period]['count'] ?? 0) + 1;
            $out[$code]['by_period'][$period]['deaths'] = ($out[$code]['by_period'][$period]['deaths'] ?? 0) + (int) $a->deaths;
            $out[$code]['all'][] = [
                'slug' => $a->slug, 'names' => $a->name, 'date' => $a->occurred_on->toDateString(), 'date_precision' => $a->date_precision->value,
                'kind' => $a->kind->value, 'deaths' => $a->deaths, 'injuries' => $a->injuries, 'commune' => $a->commune_name,
            ];
        }
        foreach ($out as &$d) {
            $heavy = $d['all'];
            usort($heavy, fn (array $x, array $y): int => [-($x['deaths'] ?? 0), $x['date']] <=> [-($y['deaths'] ?? 0), $y['date']]);
            $heavy = array_slice($heavy, 0, self::ACCIDENT_LIMIT);
            usort($heavy, fn (array $x, array $y): int => $x['date'] <=> $y['date']);
            $d['list'] = $heavy;
            unset($d['all']);
        }

        return $out;
    }

    /**
     * La fiche d'un département à partir des mètres mesurés et des lectures préparées.
     *
     * @param  array<int, array<int, array{open: float, closed: float, service: float, dismantled: float, razed: float}>>  $perLine  [ligne][année]
     * @param  list<int>  $years
     * @param  array<int, int>  $eraIds
     * @param  array<int, array<string, int>>  $stationYears
     * @param  list<array<string, mixed>>  $communes
     * @param  list<array<string, mixed>>  $events
     * @param  array<string, mixed>|null  $accidents
     * @param  array<int|string, object>  $lineMeta
     * @return array<string, mixed>
     */
    private function sheetFor(string $code, array $perLine, array $years, int $current, array $eraIds, array $stationYears, array $communes, array $events, ?array $accidents, $lineMeta): array
    {
        [$name, $region] = FrenchDepartments::ALL[$code];

        // Série par ère.
        $series = [];
        foreach ($years as $year) {
            $row = ['year' => $year, 'current' => $year === $current, 'open_km' => 0.0, 'closed_km' => 0.0, 'service_km' => 0.0, 'dismantled_km' => 0.0, 'razed_km' => 0.0, 'removed_km' => 0.0, 'added_km' => 0.0];
            foreach ($perLine as $byYear) {
                $m = $byYear[$year] ?? null;
                $row['open_km'] += ($m['open'] ?? 0) / 1000;
                $row['closed_km'] += ($m['closed'] ?? 0) / 1000;
                $row['service_km'] += ($m['service'] ?? 0) / 1000;
                $row['dismantled_km'] += ($m['dismantled'] ?? 0) / 1000;
                $row['razed_km'] += ($m['razed'] ?? 0) / 1000;
            }
            $series[$year] = $row;
        }
        $previous = null;
        foreach ($years as $year) {
            if ($previous !== null) {
                foreach ($perLine as $byYear) {
                    $delta = (($byYear[$year]['open'] ?? 0) - ($byYear[$previous]['open'] ?? 0)) / 1000;
                    $series[$year][$delta < 0 ? 'removed_km' : 'added_km'] += abs($delta);
                }
            }
            $previous = $year;
        }
        $decades = [];
        foreach ($years as $year) {
            $s = $series[$year];
            $st = $stationYears[$year] ?? ['active' => 0, 'tourist' => 0, 'no_commercial' => 0, 'declassified' => 0];
            $acc = $accidents['by_period'][$year] ?? ['count' => 0, 'deaths' => 0];
            $decades[] = [
                'year' => $year, 'current' => $s['current'],
                'open_km' => (int) round($s['open_km']), 'closed_km' => (int) round($s['closed_km']), 'service_km' => (int) round($s['service_km']),
                'dismantled_km' => (int) round($s['dismantled_km']), 'razed_km' => (int) round($s['razed_km']),
                'removed_km' => (int) round($s['removed_km']), 'added_km' => (int) round($s['added_km']),
                'stations' => $st, 'accidents' => $acc,
            ];
        }

        $lines = $this->lines($perLine, $years, $current, $events, $lineMeta);

        // Part de la longueur ouverte en 1930 qui ne l'est plus aujourd'hui, ligne par ligne.
        $ref = 0.0;
        $kept = 0.0;
        foreach ($perLine as $byYear) {
            $then = ($byYear[$this->reference]['open'] ?? 0) / 1000;
            $now = ($byYear[$current]['open'] ?? 0) / 1000;
            $ref += $then;
            $kept += min($then, $now);
        }
        $peak = collect($decades)->sortByDesc('open_km')->first();
        $at = fn (int $y): ?array => collect($decades)->firstWhere('year', $y);
        $stationsOpen = fn (?array $d): int => $d === null ? 0 : array_sum(array_intersect_key($d['stations'], array_flip(self::OPEN_STATIONS)));
        $today = $at($current);
        $reference = $at($this->reference);

        $summary = [
            'code' => $code, 'slug' => self::slug($code, $name), 'name' => $name, 'region' => $region,
            'km_reference' => $reference['open_km'] ?? 0, 'km_today' => $today['open_km'] ?? 0,
            'km_peak' => $peak['open_km'] ?? 0, 'peak_year' => ($peak['open_km'] ?? 0) > 0 ? $peak['year'] : null,
            'closed_share' => $ref > 0 ? round(1 - $kept / $ref, 4) : null,
            'stations_reference' => $stationsOpen($reference), 'stations_today' => $stationsOpen($today),
            'lines' => count($lines), 'lines_open' => count(array_filter($lines, fn (array $l): bool => $l['state'] === 'open')),
            'accidents' => $accidents['count'] ?? 0,
        ];

        return [
            'summary' => $summary,
            'department' => ['code' => $code, 'slug' => $summary['slug'], 'name' => $name, 'region' => $region],
            'computed_at' => now()->toIso8601String(),
            'reference_year' => $this->reference,
            'current_year' => $current,
            'decades' => $decades,
            'closed_share' => $summary['closed_share'],
            'milestones' => $this->milestones($decades, $perLine, $years, $events, $lines, $lineMeta),
            'lines' => $lines,
            'communes' => $communes,
            'accidents' => [
                'count' => $accidents['count'] ?? 0, 'deaths' => $accidents['deaths'] ?? 0,
                'list' => $accidents['list'] ?? [], 'limit' => self::ACCIDENT_LIMIT,
            ],
            'map' => $this->map($code, $perLine, $years, $current, $eraIds, $lines, $stationYears),
        ];
    }

    /**
     * Lignes du département : état aujourd'hui, longueur ici, dates.
     *
     * @param  array<int, array<int, array{open: float, closed: float, service: float, dismantled: float, razed: float}>>  $perLine
     * @param  list<int>  $years
     * @param  list<array<string, mixed>>  $events
     * @param  array<int|string, object>  $lineMeta
     * @return list<array<string, mixed>>
     */
    private function lines(array $perLine, array $years, int $current, array $events, $lineMeta): array
    {
        $byLineEvents = [];
        foreach ($events as $e) {
            $byLineEvents[$e['line_id']][] = $e;
        }
        $out = [];
        foreach ($perLine as $lineId => $byYear) {
            $meta = $lineMeta[$lineId] ?? null;
            $total = fn (array $m): float => $m['open'] + $m['closed'] + $m['service'] + $m['dismantled'] + $m['razed'];
            if ($meta === null || max(array_map($total, $byYear)) < self::MIN_LINE_M) {
                continue;
            }
            $openYears = array_keys(array_filter($byYear, fn (array $m): bool => $m['open'] >= self::MIN_LINE_M));
            sort($openYears);
            $now = $byYear[$current] ?? null;
            $open = $now !== null && $now['open'] >= self::MIN_LINE_M;
            $refYear = $openYears !== [] ? max($openYears) : max(array_keys(array_filter($byYear, fn (array $m): bool => $total($m) >= self::MIN_LINE_M)));
            $refMeters = $openYears !== [] ? $byYear[$refYear]['open'] : $total($byYear[$refYear]);
            // État d'aujourd'hui selon la légende de la carte : en service, voie de service, ou fermée (voie en place,
            // déposée, emprise effacée : l'état qui porte le plus de longueur ici).
            $track = null;
            $state = $open ? 'open' : 'closed';
            if (! $open) {
                $by = ['in_place' => $now['closed'] ?? 0, 'dismantled' => $now['dismantled'] ?? 0, 'razed' => $now['razed'] ?? 0, 'service' => $now['service'] ?? 0];
                arsort($by);
                $top = (string) array_key_first($by);
                $track = $by[$top] > 0 ? $top : 'none';
                if ($track === 'service') {
                    $state = 'service';
                    $track = null;
                }
            }
            $dates = $this->lineDates($byLineEvents[$lineId] ?? []);
            if ($open && ($dates['closed']['scope'] ?? null) === 'all') {
                $dates['closed'] = null;
            }
            $out[] = [
                'slug' => $meta->slug, 'name' => $meta->name, 'code' => $meta->code, 'is_lgv' => (bool) $meta->is_lgv,
                'state' => $state, 'track' => $track,
                'km' => round($refMeters / 1000, 1), 'km_year' => $refYear,
                'first_year' => $openYears[0] ?? null, 'last_year' => $openYears === [] ? null : end($openYears),
            ] + $dates;
        }
        usort($out, fn (array $a, array $b): int => [$a['state'] === 'open' ? 0 : 1, mb_strtolower($a['name'])] <=> [$b['state'] === 'open' ? 0 : 1, mb_strtolower($b['name'])]);

        return $out;
    }

    /**
     * Première ouverture, dernière fermeture (annulée par une réouverture plus tardive) et première électrification.
     *
     * @param  list<array{type: string, date: string, precision: string}>  $events
     * @return array{opened: array<string, string>|null, closed: array<string, string>|null, electrified: array<string, string>|null}
     */
    private function lineDates(array $events): array
    {
        usort($events, fn (array $a, array $b): int => $a['date'] <=> $b['date']);
        $out = ['opened' => null, 'closed' => null, 'electrified' => null];
        foreach ($events as $e) {
            $dated = ['date' => $e['date'], 'precision' => $e['precision']];
            $type = $e['type'];
            if (in_array($type, ['opening', 'opening_passenger', 'opening_freight'], true) && $out['opened'] === null) {
                $out['opened'] = $dated;
            }
            if ($type === 'electrification' && $out['electrified'] === null) {
                $out['electrified'] = $dated;
            }
            if (in_array($type, ['closure', 'closure_passenger', 'closure_freight'], true)) {
                $scope = $type === 'closure' ? 'all' : substr($type, 8);
                $rank = fn (string $s): int => $s === 'all' ? 1 : 0;
                if ($out['closed'] === null || [$rank($scope), $e['date']] > [$rank($out['closed']['scope']), $out['closed']['date']]) {
                    $out['closed'] = $dated + ['scope' => $scope];
                }
            }
            if (str_starts_with($type, 'reopening') && $out['closed'] !== null && $e['date'] > $out['closed']['date']) {
                $out['closed'] = null;
            }
        }

        return $out;
    }

    /**
     * Grandes dates : première ouverture de ligne et de gare, sommet du réseau, plus fortes vagues de retrait du
     * service (avec les lignes qui y perdent le plus), réouvertures datées.
     *
     * @param  list<array<string, mixed>>  $decades
     * @param  array<int, array<int, array{open: float, closed: float, service: float, dismantled: float, razed: float}>>  $perLine
     * @param  list<int>  $years
     * @param  list<array<string, mixed>>  $events
     * @param  list<array<string, mixed>>  $lines
     * @param  array<int|string, object>  $lineMeta
     * @return array<string, mixed>
     */
    private function milestones(array $decades, array $perLine, array $years, array $events, array $lines, $lineMeta): array
    {
        $name = fn (int $id): ?array => isset($lineMeta[$id]) ? ['slug' => $lineMeta[$id]->slug, 'name' => $lineMeta[$id]->name] : null;
        $openings = array_values(array_filter($events, fn (array $e): bool => in_array($e['type'], ['opening', 'opening_passenger', 'opening_freight'], true)));
        usort($openings, fn (array $a, array $b): int => $a['date'] <=> $b['date']);
        $first = $openings[0] ?? null;

        $peak = collect($decades)->sortByDesc('open_km')->first();

        $waves = [];
        $previous = null;
        foreach ($years as $year) {
            if ($previous !== null) {
                $lost = [];
                foreach ($perLine as $lineId => $byYear) {
                    $drop = (($byYear[$previous]['open'] ?? 0) - ($byYear[$year]['open'] ?? 0)) / 1000;
                    if ($drop >= 1 && ($meta = $name($lineId)) !== null) {
                        $lost[] = $meta + ['km' => (int) round($drop)];
                    }
                }
                usort($lost, fn (array $a, array $b): int => $b['km'] <=> $a['km']);
                $row = collect($decades)->firstWhere('year', $year);
                if ($row['removed_km'] >= 1) {
                    $waves[] = ['year' => $year, 'from_year' => $previous + 1, 'removed_km' => $row['removed_km'], 'lines' => array_slice($lost, 0, 3), 'lines_count' => count($lost)];
                }
            }
            $previous = $year;
        }
        usort($waves, fn (array $a, array $b): int => $b['removed_km'] <=> $a['removed_km']);

        $reopenings = array_values(array_filter($events, fn (array $e): bool => str_starts_with($e['type'], 'reopening')));
        usort($reopenings, fn (array $a, array $b): int => $a['date'] <=> $b['date']);
        $reopenings = array_map(fn (array $e): array => ['date' => $e['date'], 'precision' => $e['precision'], 'line' => $name($e['line_id'])], array_slice($reopenings, 0, 8));

        return [
            'first_opening' => $first === null ? null : ['date' => $first['date'], 'precision' => $first['precision'], 'line' => $name($first['line_id'])],
            'peak' => ($peak['open_km'] ?? 0) > 0 ? ['year' => $peak['year'], 'km' => $peak['open_km']] : null,
            'closure_waves' => array_slice($waves, 0, 3),
            'reopenings' => $reopenings,
        ];
    }

    /**
     * Carte du département : contour (communes réunies, simplifié à 200 m), gares et tracés des lignes (aujourd'hui,
     * et au sommet du réseau pour les lignes disparues de la carte actuelle), simplifiés à 50 m. Pour l'affichage
     * seulement : n'entre pas dans l'API.
     *
     * @param  array<int, array<int, array{open: float, closed: float, service: float, dismantled: float, razed: float}>>  $perLine
     * @param  list<int>  $years
     * @param  array<int, int>  $eraIds
     * @param  list<array<string, mixed>>  $lines
     * @param  array<int, array<string, int>>  $stationYears
     * @return array{contour: array<string, mixed>|null, stations: list<array<string, mixed>>, lines: list<array<string, mixed>>}
     */
    private function map(string $code, array $perLine, array $years, int $current, array $eraIds, array $lines, array $stationYears): array
    {
        $union = 'SELECT ST_CollectionExtract(ST_MakeValid(ST_Union(ST_MakeValid(geom))), 3) AS g FROM commune_contours WHERE departement = ?';
        $contour = DB::selectOne("SELECT ST_AsGeoJSON(ST_SimplifyPreserveTopology(u.g, 0.002), 4) AS g FROM ({$union}) u", [$code]);

        // Année du plus grand réseau ici.
        $peakYear = $current;
        $peakKm = -1.0;
        foreach ($years as $y) {
            $km = array_sum(array_map(fn (array $byYear): float => $byYear[$y]['open'] ?? 0, $perLine));
            if ($km > $peakKm) {
                [$peakKm, $peakYear] = [$km, $y];
            }
        }
        $wanted = array_values(array_unique([$eraIds[$current], $eraIds[$peakYear]]));
        $rows = DB::select(<<<SQL
            WITH u AS ({$union})
            SELECT l.slug, s.era_id, (s.status IN ('in_service', 'single_track')) AS open,
                   ST_AsGeoJSON(ST_SimplifyPreserveTopology(ST_Collect(s.geom), 0.0005), 4) AS geometry
            FROM u
            JOIN line_segments s ON s.geom && u.g AND ST_Intersects(s.geom, u.g)
            JOIN lines l ON l.id = s.line_id
            WHERE s.era_id = ANY(?::bigint[]) AND s.status IN ('in_service', 'single_track', 'abandoned', 'service_track', 'dismantled')
            GROUP BY l.slug, s.era_id, (s.status IN ('in_service', 'single_track'))
        SQL, [$code, '{'.implode(',', $wanted).'}']);

        $state = array_column($lines, 'state', 'slug');
        $presentNow = [];
        foreach ($rows as $r) {
            if ((int) $r->era_id === $eraIds[$current]) {
                $presentNow[$r->slug] = true;
            }
        }
        $traces = [];
        foreach ($rows as $r) {
            $now = (int) $r->era_id === $eraIds[$current];
            // Au sommet du réseau, seules comptent les lignes que la carte d'aujourd'hui ne porte plus.
            if (! $now && (isset($presentNow[$r->slug]) || ! $r->open)) {
                continue;
            }
            $geometry = json_decode((string) $r->geometry, true);
            if (! is_array($geometry) || in_array($geometry['type'] ?? '', ['Point', 'MultiPoint', 'GeometryCollection'], true)) {
                continue;
            }
            $traces[] = ['slug' => $r->slug, 'state' => $now && $r->open ? 'open' : 'closed', 'geometry' => $geometry];
        }

        $stations = DB::table('stations')->where('department_code', $code)->whereRaw("COALESCE(provenance->>'kind', '') NOT IN ('technical', 'excluded', 'projected')")
            ->whereNotNull('geom')->selectRaw("slug, name, CASE WHEN source_status = 'gone' THEN 'gone' ELSE status END AS status, ST_X(geom) AS lng, ST_Y(geom) AS lat")->orderBy('id')->limit(1500)->get();

        return [
            'contour' => $contour === null || $contour->g === null ? null : json_decode((string) $contour->g, true),
            'stations' => $stations->map(fn ($s): array => ['slug' => $s->slug, 'name' => $s->name, 'status' => $s->status, 'lng' => round((float) $s->lng, 4), 'lat' => round((float) $s->lat, 4)])->all(),
            'lines' => $traces,
        ];
    }
}

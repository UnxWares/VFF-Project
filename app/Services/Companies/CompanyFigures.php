<?php

namespace App\Services\Companies;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Fiches des compagnies de chemins de fer historiques (/companies, /companies/{slug}, `GET /api/v1/companies`).
 *
 * Tout est calculé par `vff:compute-company-figures` (aucune planification) et rangé dans le cache sans
 * expiration : la page publique et l'API ne calculent jamais, elles lisent une entrée par compagnie (`sheet()`),
 * l'index (`index()`) et la liste des compagnies d'une ligne (`forLine()`). Sans calcul préalable, elles rendent null.
 * Lecture seule en base, requêtes bornées (30 s), aucune union de tracés.
 *
 * Les faits sur chaque compagnie (création, fusions, rachats, fin, siège) sont dans `database/data/companies.php`,
 * sourcés. Les lignes qu'elle exploitait viennent des seuls événements datés de concession de la base
 * (`line_events`, types `concession` et `concession_end`, tirés de l'infobox de l'article de la ligne) : le libellé
 * du concessionnaire est normalisé (`normalize()`) puis rapproché des `aliases` de la compagnie. Un libellé qui ne
 * correspond à aucune compagnie n'est rattaché à rien : il est compté et listé dans le rapport de la commande.
 *
 * Intervalle d'un rattachement (en années, l'année de l'événement comptant en entier) :
 *   - début : l'année de l'événement de concession ; ignoré s'il précède la création de la compagnie (`attach_from`)
 *     ou suit sa fin ;
 *   - fin, par ordre de priorité : l'événement `concession_end` de la même compagnie sur la même ligne (`event`) ;
 *     le début de la concession suivante de la même ligne à une autre compagnie d'exploitation (`successor`) ;
 *     la fin de la compagnie (`company_end`) ou aucune fin pour une compagnie toujours en activité (`open`) ;
 *     sinon la fin est inconnue (`unknown`) : la ligne est listée mais jamais comptée par décennie. Une fin
 *     explicite ne dépasse jamais la fin de la compagnie.
 *   - présent à l'ère `Y` (carte au 31 décembre de l'année `Y`) : début <= Y et Y < fin.
 *
 * Longueurs : `length_meters` des tronçons de la carte « en service » ou « voie unique », voies parallèles comprises
 * (mêmes statuts que « Le réseau en chiffres »), pour les lignes rattachées à l'ère. Une ligne rattachée à deux
 * compagnies à la même ère est comptée dans chacune ; la couverture, elle, la compte une fois.
 */
final class CompanyFigures
{
    public const INDEX_KEY = 'company_figures:v1:index';

    public const SHEET_KEY = 'company_figures:v1:sheet:';

    public const BY_LINE_KEY = 'company_figures:v1:by_line';

    public const MAP_KEY = 'company_figures:v1:map:';

    /** Lignes envoyées avec la page ; les autres sont lues à part (`GET /companies/{slug}/lines.json`). */
    public const LINES_SHOWN = 30;

    /** Genres de compagnies d'exploitation entre lesquelles une concession en remplace une autre sur une même ligne. */
    private const OPERATING = ['grande-compagnie', 'etat', 'sncf'];

    private const MAP_CHUNK = 25;

    /** @var callable(string): void|null */
    private $progress = null;

    /** @var array<int, list<array<string, mixed>>>|null */
    private static ?array $byLineMemo = null;

    public function onProgress(?callable $progress): self
    {
        $this->progress = $progress;

        return $this;
    }

    // ---------------------------------------------------------------------------------------------------------
    // Lecture (cache)
    // ---------------------------------------------------------------------------------------------------------

    /**
     * @return array{computed_at: string, current_year: int, companies: list<array<string, mixed>>, coverage: list<array<string, mixed>>, quality: array<string, mixed>}|null
     */
    public function index(string $locale = 'fr'): ?array
    {
        $cached = Cache::get(self::INDEX_KEY);
        if (! is_array($cached)) {
            return null;
        }
        $cached['companies'] = array_map(fn (array $c): array => self::localize($c, $locale), $cached['companies']);

        return $cached;
    }

    /** @return array<string, mixed>|null */
    public function sheet(string $slug, string $locale): ?array
    {
        $cached = Cache::get(self::SHEET_KEY.$slug);
        if (! is_array($cached)) {
            return null;
        }
        $cached['company'] = self::localize($cached['company'], $locale);
        $cached['events'] = array_map(fn (array $e): array => ['date' => $e['date'], 'precision' => $e['precision'], 'text' => $e['text'][$locale] ?? $e['text']['fr']], $cached['events']);
        $cached['predecessors'] = $this->neighbours($cached['predecessors']);
        $cached['successors'] = $this->neighbours($cached['successors']);

        return $cached;
    }

    /**
     * Tracé simplifié du réseau d'une compagnie (cache), lu à part de la fiche : il pèse plusieurs centaines de Ko.
     *
     * @return array{year: int, bbox: array<int, float>, paths: list<list<array{0: float, 1: float}>>, lines: int}|null
     */
    public function map(string $slug): ?array
    {
        $map = Cache::get(self::MAP_KEY.$slug);

        return is_array($map) ? $map : null;
    }

    /**
     * Les compagnies auxquelles une ligne est rattachée par un événement daté (cache), dans l'ordre des dates.
     *
     * @return list<array{slug: string, name: string, from_year: int, to_year: int|null, to_basis: string}>
     */
    public static function forLine(int $lineId): array
    {
        self::$byLineMemo ??= (array) Cache::get(self::BY_LINE_KEY, []);

        return self::$byLineMemo[$lineId] ?? [];
    }

    /** Oublie la copie en mémoire (tests, calcul suivi d'une lecture dans le même processus). */
    public static function forget(): void
    {
        self::$byLineMemo = null;
    }

    /** Libellé d'un concessionnaire normalisé : minuscules, sans accent ni ponctuation, sans « non concédée » ni parenthèses. */
    public static function normalize(string $label): string
    {
        $s = (string) preg_replace('/\([^)]*\)|\[[^\]]*\]/u', ' ', $label);
        $s = Str::lower(Str::ascii(str_replace(['’', "'"], ' ', $s)));
        $s = (string) preg_replace('/\bnon concede(?:e|s|es)?\b/', ' ', $s);

        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $s));
    }

    /** @return list<array<string, mixed>> les compagnies du fichier de données */
    public static function definitions(): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = require database_path('data/companies.php');

        return array_map(function (array $c): array {
            $c['aliases'] = array_values(array_unique(array_map(self::normalize(...), $c['aliases'])));
            $c['attach_from'] ??= (int) substr($c['founded'][0], 0, 4);

            return $c;
        }, $rows);
    }

    // ---------------------------------------------------------------------------------------------------------
    // Calcul
    // ---------------------------------------------------------------------------------------------------------

    /**
     * Calcule tout sans toucher au cache.
     *
     * @return array{index: array<string, mixed>, sheets: array<string, array<string, mixed>>, by_line: array<int, list<array<string, mixed>>>}
     */
    public function compute(): array
    {
        DB::statement('SET statement_timeout = 30000');
        $definitions = self::definitions();
        $eras = DB::table('eras')->orderBy('year')->get(['id', 'year', 'is_current']);
        $years = $eras->pluck('year')->map(fn ($y): int => (int) $y)->all();
        $eraIds = $eras->pluck('id', 'year')->map(fn ($id): int => (int) $id)->all();
        $current = (int) ($eras->firstWhere('is_current', true)->year ?? end($years));

        $this->say('Événements de concession...');
        [$attachments, $quality] = $this->attachments($definitions);
        $this->say('Longueurs ouvertes par ligne et par ère...');
        $open = $this->openMeters($eras->all());
        $lineMeta = DB::table('lines')->get(['id', 'slug', 'name', 'code', 'is_lgv'])->keyBy('id')->all();

        $coverage = $this->coverage($attachments, $open, $years, $current);
        $sheets = [];
        foreach ($definitions as $company) {
            $this->say("Fiche {$company['slug']}...");
            $sheets[$company['slug']] = $this->sheetFor($company, $attachments[$company['slug']] ?? [], $open, $years, $current, $eraIds, $lineMeta);
        }
        $quality['unattached_lines'] = $coverage['unattached_lines'];

        $byLine = [];
        foreach ($definitions as $company) {
            foreach ($attachments[$company['slug']] ?? [] as $a) {
                $byLine[$a['line_id']][] = [
                    'slug' => $company['slug'], 'name' => $company['short'], 'from_year' => $a['from_year'],
                    'to_year' => $a['to_year'], 'to_basis' => $a['to_basis'],
                ];
            }
        }
        foreach ($byLine as &$list) {
            usort($list, fn (array $a, array $b): int => [$a['from_year'], $a['slug']] <=> [$b['from_year'], $b['slug']]);
        }
        unset($list);

        $index = [
            'computed_at' => now()->toIso8601String(),
            'current_year' => $current,
            'companies' => array_values(array_map(fn (array $s): array => $s['summary'], $sheets)),
            'coverage' => $coverage['by_era'],
            'quality' => $quality,
        ];

        return ['index' => $index, 'sheets' => $sheets, 'by_line' => $byLine];
    }

    /** Calcule puis range dans le cache (sans expiration). */
    public function refresh(): array
    {
        $result = $this->compute();
        foreach ($result['sheets'] as $slug => $sheet) {
            Cache::forever(self::MAP_KEY.$slug, $sheet['map']);
            unset($sheet['map']);
            Cache::forever(self::SHEET_KEY.$slug, $sheet);
        }
        Cache::forever(self::INDEX_KEY, $result['index']);
        Cache::forever(self::BY_LINE_KEY, $result['by_line']);
        self::forget();

        return $result;
    }

    private function say(string $line): void
    {
        if ($this->progress !== null) {
            ($this->progress)($line);
        }
    }

    /**
     * Les rattachements ligne - compagnie, par compagnie, et le bilan du rapprochement des libellés.
     *
     * @param  list<array<string, mixed>>  $definitions
     * @return array{0: array<string, list<array<string, mixed>>>, 1: array<string, mixed>}
     */
    private function attachments(array $definitions): array
    {
        $bySlug = array_column($definitions, null, 'slug');
        $aliasIndex = [];
        foreach ($definitions as $c) {
            foreach ($c['aliases'] as $alias) {
                $aliasIndex[$alias] = $c['slug'];
            }
        }
        $rows = DB::table('line_events')->whereIn('type', ['concession', 'concession_end'])->where('source', 'wikipedia')
            ->where('description', 'like', '%(infobox Wikipédia)')
            ->orderBy('line_id')->orderBy('event_date')->orderBy('id')
            ->get(['id', 'line_id', 'type', 'event_date', 'date_precision', 'description', 'source_url']);

        $starts = $ends = [];
        $unmatched = [];
        $events = 0;
        foreach ($rows as $r) {
            $isEnd = $r->type === 'concession_end';
            if (preg_match($isEnd ? '/^Fin de concession de (.+?) \(infobox Wikipédia\)$/u' : '/^Concession à (.+?) \(infobox Wikipédia\)$/u', (string) $r->description, $m) !== 1) {
                continue;
            }
            $events++;
            $slug = $aliasIndex[self::normalize($m[1])] ?? null;
            if ($slug === null) {
                $key = self::normalize($m[1]);
                $unmatched[$key] = ($unmatched[$key] ?? 0) + 1;

                continue;
            }
            $row = [
                'event_id' => (int) $r->id, 'line_id' => (int) $r->line_id, 'company' => $slug, 'label' => $m[1],
                'year' => (int) substr((string) $r->event_date, 0, 4), 'date' => substr((string) $r->event_date, 0, 10), 'precision' => (string) $r->date_precision,
            ];
            if ($isEnd) {
                $ends[$r->line_id][] = $row;
            } else {
                $starts[$r->line_id][] = $row;
            }
        }

        $out = array_fill_keys(array_keys($bySlug), []);
        $quality = ['events_read' => $events, 'attached' => 0, 'before_founding' => 0, 'after_end' => 0, 'duplicates' => 0, 'end_event' => 0, 'end_successor' => 0, 'end_company' => 0, 'end_open' => 0, 'end_unknown' => 0, 'end_capped' => 0];
        foreach ($starts as $lineId => $list) {
            usort($list, fn (array $a, array $b): int => [$a['date'], $a['event_id']] <=> [$b['date'], $b['event_id']]);
            $kept = [];
            foreach ($list as $s) {
                $c = $bySlug[$s['company']];
                $endYear = $c['ended'] === null ? null : (int) substr($c['ended'][0], 0, 4);
                if ($s['year'] < $c['attach_from']) {
                    $quality['before_founding']++;

                    continue;
                }
                if ($endYear !== null && $s['year'] > $endYear) {
                    $quality['after_end']++;

                    continue;
                }
                $duplicate = false;
                foreach ($kept as $k) {
                    $duplicate = $duplicate || ($k['company'] === $s['company'] && $k['year'] === $s['year']);
                }
                if ($duplicate) {
                    $quality['duplicates']++;

                    continue;
                }
                $kept[] = $s;
            }
            $usedEnds = [];
            foreach ($kept as $i => $s) {
                $c = $bySlug[$s['company']];
                $companyEnd = $c['ended'] === null ? null : (int) substr($c['ended'][0], 0, 4);
                $to = null;
                $basis = null;
                $endEvent = null;
                foreach ($ends[$lineId] ?? [] as $j => $e) {
                    if ($e['company'] === $s['company'] && $e['year'] >= $s['year'] && ! isset($usedEnds[$j])) {
                        $usedEnds[$j] = true;
                        [$to, $basis, $endEvent] = [$e['year'], 'event', $e];

                        break;
                    }
                }
                if ($basis === null && in_array($c['kind'], self::OPERATING, true)) {
                    foreach (array_slice($kept, $i + 1) as $next) {
                        if ($next['company'] !== $s['company'] && in_array($bySlug[$next['company']]['kind'], self::OPERATING, true) && $next['year'] >= $s['year']) {
                            [$to, $basis] = [$next['year'], 'successor'];

                            break;
                        }
                    }
                }
                if ($basis === null) {
                    $basis = $c['end_policy'];
                    $to = $c['end_policy'] === 'company_end' ? $companyEnd : null;
                }
                if ($basis === 'event' && $companyEnd !== null && $to > $companyEnd) {
                    $to = $companyEnd;
                    $quality['end_capped']++;
                }
                $quality['end_'.match ($basis) {
                    'event' => 'event', 'successor' => 'successor', 'company_end' => 'company', 'open' => 'open', default => 'unknown'
                }]++;
                $quality['attached']++;
                $out[$s['company']][] = [
                    'line_id' => $lineId, 'from_year' => $s['year'], 'to_year' => $to, 'to_basis' => $basis,
                    'from_date' => $s['date'], 'from_precision' => $s['precision'], 'label' => $s['label'], 'event_id' => $s['event_id'],
                    'end_event_id' => $endEvent['event_id'] ?? null,
                ];
            }
        }
        arsort($unmatched);
        $quality['unmatched_labels'] = array_slice($unmatched, 0, 25, true);
        $quality['unmatched_total'] = array_sum($unmatched);

        return [$out, $quality];
    }

    /**
     * Mètres de voie ouverte par ère et par ligne.
     *
     * @param  iterable<object{id: int, year: int}>  $eras
     * @return array<int, array<int, float>> [année][ligne] => mètres
     */
    private function openMeters(iterable $eras): array
    {
        $out = [];
        foreach ($eras as $era) {
            $rows = DB::select("SELECT line_id, SUM(COALESCE(length_meters, ST_Length(geom::geography))) AS m FROM line_segments WHERE era_id = ? AND status IN ('in_service', 'single_track') GROUP BY line_id", [$era->id]);
            foreach ($rows as $r) {
                if ((float) $r->m > 0) {
                    $out[(int) $era->year][(int) $r->line_id] = (float) $r->m;
                }
            }
        }

        return $out;
    }

    /** Une ligne est-elle rattachée à l'ère `$year` (et sa fin est-elle connue) ? */
    private static function covers(array $a, int $year): bool
    {
        return $a['to_basis'] !== 'unknown' && $a['from_year'] <= $year && ($a['to_year'] === null || $year < $a['to_year']);
    }

    /**
     * Couverture : par ère, lignes et kilomètres ouverts, et part rattachée à au moins une compagnie.
     *
     * @param  array<string, list<array<string, mixed>>>  $attachments
     * @param  array<int, array<int, float>>  $open
     * @param  list<int>  $years
     * @return array{by_era: list<array<string, mixed>>, unattached_lines: array<string, int>}
     */
    private function coverage(array $attachments, array $open, array $years, int $current): array
    {
        $byEra = [];
        $unattachedToday = 0;
        foreach ($years as $year) {
            $attached = [];
            foreach ($attachments as $list) {
                foreach ($list as $a) {
                    if (self::covers($a, $year)) {
                        $attached[$a['line_id']] = true;
                    }
                }
            }
            $lines = $open[$year] ?? [];
            $km = array_sum($lines) / 1000;
            $kmAttached = array_sum(array_intersect_key($lines, $attached)) / 1000;
            $byEra[] = [
                'year' => $year, 'current' => $year === $current,
                'lines_open' => count($lines), 'lines_attached' => count(array_intersect_key($lines, $attached)),
                'km_open' => (int) round($km), 'km_attached' => (int) round($kmAttached),
                'share' => $km > 0 ? round($kmAttached / $km, 3) : null,
            ];
            if ($year === $current) {
                $unattachedToday = count(array_diff_key($lines, $attached));
            }
        }

        return ['by_era' => $byEra, 'unattached_lines' => ['today' => $unattachedToday]];
    }

    /**
     * @param  array<string, mixed>  $company
     * @param  list<array<string, mixed>>  $attachments
     * @param  array<int, array<int, float>>  $open
     * @param  list<int>  $years
     * @param  array<int, int>  $eraIds
     * @param  array<int, object>  $lineMeta
     * @return array<string, mixed>
     */
    private function sheetFor(array $company, array $attachments, array $open, array $years, int $current, array $eraIds, array $lineMeta): array
    {
        $decades = [];
        $peak = null;
        foreach ($years as $year) {
            $lines = $km = 0;
            foreach ($attachments as $a) {
                if (self::covers($a, $year) && isset($open[$year][$a['line_id']])) {
                    $lines++;
                    $km += $open[$year][$a['line_id']];
                }
            }
            $row = ['year' => $year, 'current' => $year === $current, 'lines' => $lines, 'km' => (int) round($km / 1000)];
            $decades[] = $row;
            if ($row['km'] > 0 && ($peak === null || $row['km'] > $peak['km'])) {
                $peak = $row;
            }
        }
        $firstYear = null;
        foreach ($decades as $d) {
            if ($d['lines'] > 0) {
                $firstYear ??= $d['year'];
            }
        }

        $lines = [];
        $order = [];
        foreach ($attachments as $a) {
            $meta = $lineMeta[$a['line_id']] ?? null;
            if ($meta === null) {
                continue;
            }
            $order[] = sprintf('%04d', $a['from_year']).' '.Str::lower((string) $meta->name);
            $lines[] = [
                'slug' => $meta->slug, 'name' => $meta->name, 'code' => $meta->code, 'is_lgv' => (bool) $meta->is_lgv,
                'from_year' => $a['from_year'], 'to_year' => $a['to_year'], 'to_basis' => $a['to_basis'],
                'from_date' => $a['from_date'], 'from_precision' => $a['from_precision'],
                'km' => $peak === null ? null : (isset($open[$peak['year']][$a['line_id']]) && self::covers($a, $peak['year']) ? round($open[$peak['year']][$a['line_id']] / 1000, 1) : null),
                'open_today' => isset($open[$current][$a['line_id']]) && self::covers($a, $current),
                'event_id' => $a['event_id'], 'line_id' => $a['line_id'],
            ];
        }
        array_multisort($order, SORT_ASC, SORT_STRING, $lines);

        $map = $peak === null ? null : $this->buildMap($peak['year'], $eraIds[$peak['year']], array_values(array_unique(array_map(
            fn (array $a): int => $a['line_id'],
            array_filter($attachments, fn (array $a): bool => self::covers($a, $peak['year']) && isset($open[$peak['year']][$a['line_id']])),
        ))));

        $today = end($decades) ?: ['km' => 0, 'lines' => 0];
        $info = [
            'slug' => $company['slug'], 'kind' => $company['kind'], 'name' => $company['name'], 'short' => $company['short'],
            'wikidata' => $company['wikidata'], 'founded' => ['date' => $company['founded'][0], 'precision' => $company['founded'][1]],
            'ended' => $company['ended'] === null ? null : ['date' => $company['ended'][0], 'precision' => $company['ended'][1]],
            'seat' => $company['seat'], 'summary' => $company['summary'],
        ];
        $summary = $info + [
            'lines' => count($lines), 'lines_counted' => count(array_filter($lines, fn (array $l): bool => $l['to_basis'] !== 'unknown')),
            'km_peak' => $peak['km'] ?? 0, 'peak_year' => $peak['year'] ?? null, 'km_today' => $today['km'], 'lines_open_today' => $today['lines'],
        ];

        return [
            'company' => $info, 'summary' => $summary,
            'computed_at' => now()->toIso8601String(), 'current_year' => $current,
            'events' => array_map(fn (array $e): array => ['date' => $e[0], 'precision' => $e[1], 'text' => $e[2]], $company['events']),
            'sources' => array_map(fn (array $s): array => ['label' => $s[0], 'url' => $s[1]], $company['sources']),
            'predecessors' => $company['predecessors'], 'successors' => $company['successors'],
            'decades' => $decades, 'peak' => $peak, 'first_year' => $firstYear,
            'lines' => array_map(function (array $l): array {
                unset($l['line_id']);

                return $l;
            }, $lines),
            'map' => $map,
            'map_info' => $map === null ? null : ['year' => $map['year'], 'lines' => $map['lines']],
        ];
    }

    /**
     * Tracé simplifié du réseau à l'ère de plus grande extension : un chemin par ligne (au plus un millier de points
     * par compagnie au total en pratique), simplifié par ligne, sans union.
     *
     * @param  list<int>  $lineIds
     * @return array{year: int, bbox: array{0: float, 1: float, 2: float, 3: float}, paths: list<list<array{0: float, 1: float}>>, lines: int}|null
     */
    private function buildMap(int $year, int $eraId, array $lineIds): ?array
    {
        if ($lineIds === []) {
            return null;
        }
        $tolerance = count($lineIds) > 400 ? 0.08 : (count($lineIds) > 150 ? 0.04 : 0.02);
        $paths = [];
        foreach (array_chunk($lineIds, self::MAP_CHUNK) as $chunk) {
            $rows = DB::select(
                "SELECT ST_AsGeoJSON(ST_SimplifyPreserveTopology(ST_LineMerge(ST_Collect(geom)), {$tolerance}), 2) AS g FROM line_segments WHERE era_id = ? AND status IN ('in_service', 'single_track') AND line_id = ANY(?::bigint[]) GROUP BY line_id",
                [$eraId, '{'.implode(',', $chunk).'}'],
            );
            foreach ($rows as $r) {
                $g = json_decode((string) $r->g, true);
                $parts = ($g['type'] ?? '') === 'LineString' ? [$g['coordinates']] : ($g['coordinates'] ?? []);
                foreach ($parts as $part) {
                    if (is_array($part) && count($part) >= 2) {
                        $paths[] = array_map(fn (array $p): array => [(float) $p[0], (float) $p[1]], $part);
                    }
                }
            }
        }
        if ($paths === []) {
            return null;
        }
        $lons = $lats = [];
        foreach ($paths as $path) {
            foreach ($path as $p) {
                $lons[] = $p[0];
                $lats[] = $p[1];
            }
        }

        return ['year' => $year, 'bbox' => [min($lons), min($lats), max($lons), max($lats)], 'paths' => $paths, 'lines' => count($lineIds)];
    }

    /** @param  array<string, mixed>  $c */
    private static function localize(array $c, string $locale): array
    {
        if (isset($c['summary']) && is_array($c['summary'])) {
            $c['summary'] = $c['summary'][$locale] ?? $c['summary']['fr'];
        }

        return $c;
    }

    /**
     * @param  list<string>  $slugs
     * @return list<array{slug: string, short: string}>
     */
    private function neighbours(array $slugs): array
    {
        $out = [];
        foreach ($slugs as $slug) {
            $sheet = Cache::get(self::SHEET_KEY.$slug);
            if (is_array($sheet)) {
                $out[] = ['slug' => $slug, 'short' => $sheet['company']['short']];
            }
        }

        return $out;
    }
}

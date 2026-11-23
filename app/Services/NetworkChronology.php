<?php

namespace App\Services;

use App\Mcp\Support\NonCommercialSources;
use App\Models\Accident;
use App\Support\EventText;
use App\Support\FrenchDepartments;
use App\Support\SourceRights;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * « Chronologie du rail en France » : année par année, de 1827 à aujourd'hui, ce que l'encyclopédie sait déjà
 * (page /chronology et `GET /api/v1/chronology`).
 *
 * Calculée par `vff:compute-network-chronology` (aucune planification) et rangée dans le cache sans expiration :
 * un aperçu (comptes par année, type et région) et une liste de faits par décennie. Lecture seule en base.
 *
 * Faits rassemblés :
 *   - ouvertures, réouvertures, fermetures (aux voyageurs, au fret, totales) et électrifications des
 *     chronologies de lignes (`line_events`), sans les estimations ni les années tirées du nom d'un tracé ;
 *   - mises en service de systèmes de signalisation (`line_signalling_events` : BAL, BAPR, TVM, ETCS…) ;
 *   - accidents graves publiés (au moins ACCIDENT_MIN_DEATHS morts) ;
 *   - arrivées de séries de matériel marquantes (au moins MATERIAL_MIN_UNITS exemplaires, ou rames TGV) ;
 *   - quelques jalons nationaux (MILESTONES : lois, création des entreprises du réseau), chacun avec sa source.
 *
 * Un même fait rapporté par plusieurs sources (même ligne, même type, même portion, même année) n'est compté
 * qu'une fois, avec toutes ses sources. Sa date reste au jour seulement si les sources publiables qui le
 * datent au jour s'accordent et que leur confiance n'est pas faible ; sinon au mois commun, sinon à l'année.
 * Un fait « toute la ligne » de l'année d'un fait de portion de la même ligne est le même fait (la première
 * section ouverte) : ses sources rejoignent la portion. Kilomètres : longueur résolue de la portion ;
 * toute la ligne seulement si c'est le seul fait de ce type pour la ligne (ouverte ou fermée d'un coup).
 *
 * Un fait n'est détaillé que si au moins une de ses sources est publiable (isPublishable) ; les autres sont
 * comptés (`hidden`) sans date, ligne ni lien.
 */
final class NetworkChronology
{
    public const CACHE_KEY = 'network_chronology:v1';

    public const FIRST_YEAR = 1827;

    /** Types de faits, dans l'ordre d'affichage d'une année. */
    public const KINDS = ['milestone', 'opening', 'reopening', 'closure', 'electrification', 'signalling', 'material', 'accident'];

    /** Types sans région : ils ne sont pas dans une vue filtrée par région. */
    public const NATIONAL_KINDS = ['milestone', 'material'];

    public const ACCIDENT_MIN_DEATHS = 5;

    public const MATERIAL_MIN_UNITS = 100;

    /**
     * Sources au statut « incertain » dont les FAITS (dates, lieux) peuvent être cités avec un lien : autorisation
     * écrite de reprendre les informations avec mention (lignes-oubliees), faits avec lien acceptés
     * (argentanwebferro), faits isolés cités avec lien (tiers-cites). Registre : database/data/source_rights.php.
     */
    public const CITABLE_UNCERTAIN = ['lignes-oubliees', 'argentanwebferro', 'tiers-cites'];

    /** Valeurs de provenance qui ne sont pas des faits (estimations d'après le réseau). */
    private const EXCLUDED_SOURCES = ['estimation-reseau'];

    /** Type d'événement de ligne → [type de fait, précision du trafic]. */
    private const LINE_TYPES = [
        'opening' => ['opening', null], 'opening_passenger' => ['opening', 'passenger'], 'opening_freight' => ['opening', 'freight'],
        'reopening' => ['reopening', null], 'reopening_passenger' => ['reopening', 'passenger'], 'reopening_freight' => ['reopening', 'freight'],
        'closure' => ['closure', 'total'], 'closure_passenger' => ['closure', 'passenger'], 'closure_freight' => ['closure', 'freight'],
        'electrification' => ['electrification', null],
    ];

    /**
     * Jalons nationaux (date vérifiée dans l'article cité le 23/11/2026) ; titres et textes : lang/<langue>/chronology.php.
     *
     * @var list<array{key: string, date: string, url: string}>
     */
    public const MILESTONES = [
        ['key' => 'legrand', 'date' => '1842-06-11', 'url' => 'https://fr.wikipedia.org/wiki/Loi_relative_%C3%A0_l%27%C3%A9tablissement_des_grandes_lignes_de_chemin_de_fer_en_France'],
        ['key' => 'freycinet', 'date' => '1879-07-17', 'url' => 'https://fr.wikipedia.org/wiki/Plan_Freycinet'],
        ['key' => 'sncf', 'date' => '1938-01-01', 'url' => 'https://fr.wikipedia.org/wiki/Soci%C3%A9t%C3%A9_nationale_des_chemins_de_fer_fran%C3%A7ais'],
        ['key' => 'rff', 'date' => '1997-02-13', 'url' => 'https://fr.wikipedia.org/wiki/R%C3%A9seau_ferr%C3%A9_de_France'],
        ['key' => 'sncf_reseau', 'date' => '2015-01-01', 'url' => 'https://fr.wikipedia.org/wiki/SNCF_R%C3%A9seau'],
        ['key' => 'sncf_sa', 'date' => '2020-01-01', 'url' => 'https://fr.wikipedia.org/wiki/Soci%C3%A9t%C3%A9_nationale_des_chemins_de_fer_fran%C3%A7ais'],
    ];

    private const PRECISION_RANK = ['day' => 0, 'month' => 1, 'year' => 2];

    /** @var array<string, bool> */
    private array $publishable = [];

    /** @var array<int, array<string, string>> ligne → clé de lieu d'une de ses gares (LineChronicle::placeKey) → région */
    private array $placeRegions = [];

    public function __construct(private readonly SourceRights $rights) {}

    /**
     * Aperçu rangé dans le cache, ou calcul complet si le cache est vide. Le calcul n'est lancé que par une requête à
     * la fois : les autres attendent son résultat (même règle que NetworkFigures).
     */
    public function meta(): array
    {
        $meta = Cache::get(self::CACHE_KEY.':meta');
        if (is_array($meta)) {
            return $meta;
        }

        return Cache::lock(self::CACHE_KEY.':lock', 120)->block(60, function (): array {
            $meta = Cache::get(self::CACHE_KEY.':meta');

            return is_array($meta) ? $meta : $this->refresh()['meta'];
        });
    }

    /**
     * Faits d'une décennie (1930 = 1930 à 1939), dans le cache.
     *
     * @return list<array<string, mixed>>
     */
    public function decadeFacts(int $decade): array
    {
        $this->meta();
        $facts = Cache::get(self::CACHE_KEY.':decade:'.$decade);

        return is_array($facts) ? $facts : [];
    }

    /** Recalcule et range le résultat (sans expiration). */
    public function refresh(): array
    {
        $result = $this->compute();
        foreach ($result['decades'] as $decade => $facts) {
            Cache::forever(self::CACHE_KEY.':decade:'.$decade, $facts);
        }
        Cache::forever(self::CACHE_KEY.':meta', $result['meta']);

        return $result;
    }

    /**
     * Calcul complet, sans toucher au cache.
     *
     * @return array{meta: array<string, mixed>, decades: array<int, list<array<string, mixed>>>}
     */
    public function compute(): array
    {
        $lastYear = (int) now()->year;
        $regions = $this->lineRegions();
        $facts = array_merge(
            self::collapse(array_merge($this->lineFacts($regions), $this->signallingFacts($regions))),
            $this->accidentFacts(),
            $this->materialFacts(),
            $this->milestoneFacts(),
        );
        // Rien d'annoncé : la chronologie s'arrête au jour du calcul (même règle que l'ère courante de la carte).
        $today = now()->toDateString();
        $facts = array_values(array_filter($facts, fn (array $f): bool => $f['year'] >= self::FIRST_YEAR && $f['date'] <= $today));

        // Comptes : année → type → région (« all » : tout le réseau) → [détaillés, comptés seulement, km].
        $counts = [];
        $decades = [];
        foreach ($facts as $f) {
            $buckets = array_merge(['all'], $f['regions']);
            foreach ($buckets as $b) {
                $counts[$f['year']][$f['kind']][$b] ??= [0, 0, 0];
                $counts[$f['year']][$f['kind']][$b][$f['shown'] ? 0 : 1]++;
                if ($f['shown'] && $f['km'] !== null) {
                    $counts[$f['year']][$f['kind']][$b][2] += $f['km'];
                }
            }
            if ($f['shown']) {
                unset($f['shown']);
                $decades[intdiv($f['year'], 10) * 10][] = $f;
            }
        }
        foreach ($counts as &$byKind) {
            foreach ($byKind as &$byRegion) {
                foreach ($byRegion as &$c) {
                    $c[2] = round($c[2], 1);
                }
            }
        }
        unset($byKind, $byRegion, $c);
        ksort($counts);
        $order = array_flip(self::KINDS);
        foreach ($decades as &$list) {
            usort($list, fn (array $a, array $b): int => [$a['year'], $order[$a['kind']], $a['date'], $a['title'] ?? $a['line']['name'] ?? '', $a['id']]
                <=> [$b['year'], $order[$b['kind']], $b['date'], $b['title'] ?? $b['line']['name'] ?? '', $b['id']]);
        }
        unset($list);
        ksort($decades);
        $all = [];
        for ($d = intdiv(self::FIRST_YEAR, 10) * 10; $d <= intdiv($lastYear, 10) * 10; $d += 10) {
            $all[$d] = $decades[$d] ?? [];
        }

        return [
            'meta' => [
                'computed_at' => now()->toIso8601String(),
                'first_year' => self::FIRST_YEAR,
                'last_year' => $lastYear,
                'counts' => $counts,
            ],
            'decades' => $all,
        ];
    }

    /** Première et dernière décennie de la chronologie (1820, décennie en cours). */
    public static function decadeBounds(int $lastYear): array
    {
        return [intdiv(self::FIRST_YEAR, 10) * 10, intdiv($lastYear, 10) * 10];
    }

    /**
     * Aperçu filtré : décennies et années avec leurs comptes (sans les faits).
     *
     * @param  list<string>  $types
     * @return array{computed_at: string, first_year: int, last_year: int, filters: array{types: list<string>, region: ?string},
     *     decades: list<array{decade: int, shown: int, hidden: int}>, years: list<array{year: int, counts: array<string, array{shown: int, hidden: int, km: float|int}>}>}
     */
    public function overview(array $types, ?string $region): array
    {
        $meta = $this->meta();
        $counts = self::filterCounts($meta['counts'], $types, $region);
        [$first, $last] = self::decadeBounds((int) $meta['last_year']);
        $decades = [];
        for ($d = $first; $d <= $last; $d += 10) {
            $decades[$d] = ['decade' => $d, 'shown' => 0, 'hidden' => 0];
        }
        $years = [];
        foreach ($counts as $year => $byKind) {
            $d = intdiv($year, 10) * 10;
            foreach ($byKind as $c) {
                $decades[$d]['shown'] += $c['shown'];
                $decades[$d]['hidden'] += $c['hidden'];
            }
            $years[] = ['year' => $year, 'counts' => self::ordered($byKind)];
        }

        return [
            'computed_at' => (string) $meta['computed_at'],
            'first_year' => (int) $meta['first_year'],
            'last_year' => (int) $meta['last_year'],
            'filters' => ['types' => $types, 'region' => $region],
            'decades' => array_values($decades),
            'years' => $years,
        ];
    }

    /**
     * Une décennie filtrée, année par année : comptes de l'année et faits détaillés dans la langue.
     *
     * @param  list<string>  $types
     * @return array{decade: int, years: list<array{year: int, counts: array<string, array{shown: int, hidden: int, km: float|int}>, facts: list<array<string, mixed>>}>}
     */
    public function decade(int $decade, array $types, ?string $region, string $locale): array
    {
        $meta = $this->meta();
        $counts = self::filterCounts($meta['counts'], $types, $region);
        $byYear = [];
        foreach ($this->present($this->decadeFacts($decade), $types, $region, $locale) as $f) {
            $byYear[$f['year']][] = $f;
        }
        $years = [];
        for ($y = max($decade, self::FIRST_YEAR); $y <= min($decade + 9, (int) $meta['last_year']); $y++) {
            if (isset($counts[$y]) || isset($byYear[$y])) {
                $years[] = ['year' => $y, 'counts' => self::ordered($counts[$y] ?? []), 'facts' => $byYear[$y] ?? []];
            }
        }

        return ['decade' => $decade, 'years' => $years];
    }

    /**
     * @param  array<string, array{shown: int, hidden: int, km: float|int}>  $byKind
     * @return array<string, array{shown: int, hidden: int, km: float|int}>
     */
    private static function ordered(array $byKind): array
    {
        $out = [];
        foreach (self::KINDS as $kind) {
            if (isset($byKind[$kind])) {
                $out[$kind] = $byKind[$kind];
            }
        }

        return $out;
    }

    /**
     * Comptes par année d'un filtre (type, région) : [année => [type => [shown, hidden, km]]].
     *
     * @param  array<int|string, array<string, array<string, array{0: int, 1: int, 2: float|int}>>>  $counts
     * @param  list<string>  $kinds  types retenus (vide : tous)
     * @return array<int, array<string, array{shown: int, hidden: int, km: float|int}>>
     */
    public static function filterCounts(array $counts, array $kinds, ?string $region): array
    {
        $out = [];
        foreach ($counts as $year => $byKind) {
            foreach ($byKind as $kind => $byRegion) {
                if ($kinds !== [] && ! in_array($kind, $kinds, true)) {
                    continue;
                }
                $c = $byRegion[$region ?? 'all'] ?? null;
                if ($c !== null) {
                    $out[(int) $year][$kind] = ['shown' => $c[0], 'hidden' => $c[1], 'km' => $c[2]];
                }
            }
        }

        return $out;
    }

    /**
     * Faits d'une décennie filtrés (types, région) et rédigés dans la langue : portion en toutes lettres,
     * date à sa précision, titre (accident, matériel, jalon, système de signalisation), nom court des sources.
     *
     * @param  list<array<string, mixed>>  $facts
     * @param  list<string>  $kinds
     * @return list<array<string, mixed>>
     */
    public function present(array $facts, array $kinds, ?string $region, string $locale): array
    {
        $out = [];
        foreach ($facts as $f) {
            if (($kinds !== [] && ! in_array($f['kind'], $kinds, true)) || ($region !== null && ! in_array($region, $f['regions'], true))) {
                continue;
            }
            $title = match ($f['kind']) {
                'accident' => $f['names'][$locale] ?? $f['names']['fr'] ?? reset($f['names']) ?: null,
                'milestone' => (string) trans('chronology.milestones.'.$f['key'].'.title', [], $locale),
                'signalling' => trans()->has('lines.signalling.codes.'.$f['system'], $locale)
                    ? (string) trans('lines.signalling.codes.'.$f['system'], [], $locale) : (string) $f['system'],
                default => $f['title'] ?? null,
            };
            $out[] = [
                'id' => $f['id'],
                'kind' => $f['kind'],
                'traffic' => $f['traffic'] ?? null,
                'year' => $f['year'],
                'date' => $f['date'],
                'precision' => $f['precision'],
                'when' => EventText::isoDate($f['date'], $f['precision'], $locale),
                'title' => $title,
                'text' => $f['kind'] === 'milestone' ? (string) trans('chronology.milestones.'.$f['key'].'.text', [], $locale) : null,
                'line' => $f['line'] ?? null,
                'sections' => array_map(fn (array $p): array => [
                    'text' => (string) LineChronicle::placeText($p[0], $p[1], $locale),
                    'km' => $p[2] ?? null,
                ], $f['sections'] ?? []),
                'km' => $f['km'],
                'deaths' => $f['deaths'] ?? null,
                'units' => $f['units'] ?? null,
                'path' => $f['path'],
                'regions' => $f['regions'],
                'sources' => array_map(fn (array $s): array => $s + ['name' => $this->sourceName($s['provider'], $locale)], $f['sources']),
            ];
        }

        return $out;
    }

    /** Nom court d'une source dans la langue (lang/<langue>/chronology.php), sinon le nom du registre. */
    public function sourceName(string $provider, string $locale): string
    {
        $key = 'chronology.sources.'.$provider;
        if (trans()->has($key, $locale)) {
            return (string) trans($key, [], $locale);
        }
        $name = (string) ($this->rights->provider($provider)['nom'] ?? $provider);

        return trim((string) preg_replace('/\s*\(.*$/u', '', $name));
    }

    /**
     * Le fait de ce fournisseur peut-il être détaillé ? Licence ouverte établie (« sur »), ou source dont les faits
     * se citent avec un lien (CITABLE_UNCERTAIN) ; jamais une source à usage non commercial.
     */
    public function isPublishable(?string $provider): bool
    {
        if ($provider === null) {
            return false;
        }

        return $this->publishable[$provider] ??= ! in_array($provider, NonCommercialSources::PROVIDERS, true)
            && ($this->rights->status($provider) === SourceRights::SAFE || in_array($provider, self::CITABLE_UNCERTAIN, true));
    }

    /**
     * Régions de chaque ligne : départements cités par sa fiche et départements de ses gares. Retient aussi la
     * région de chaque gare de la ligne (placeRegions) pour situer une portion par ses extrémités.
     *
     * @return array<int, list<string>>
     */
    private function lineRegions(): array
    {
        $byName = [];
        foreach (FrenchDepartments::ALL as $code => [$name]) {
            $byName[$name] = FrenchDepartments::regionCode((string) $code);
        }
        $out = [];
        foreach (DB::table('lines')->whereNotNull('departements')->get(['id', 'departements']) as $l) {
            foreach ((array) json_decode((string) $l->departements, true) as $name) {
                if (is_string($name) && ($r = $byName[$name] ?? null) !== null) {
                    $out[(int) $l->id][$r] = true;
                }
            }
        }
        $rows = DB::select('SELECT DISTINCT sl.line_id, s.name, s.department_code FROM station_lines sl
            JOIN stations s ON s.id = sl.station_id WHERE s.department_code IS NOT NULL');
        foreach ($rows as $r) {
            if (($code = FrenchDepartments::regionCode((string) $r->department_code)) !== null) {
                $out[(int) $r->line_id][$code] = true;
                if (($k = LineChronicle::placeKey((string) $r->name)) !== null) {
                    $this->placeRegions[(int) $r->line_id][$k] ??= $code;
                }
            }
        }

        return array_map(function (array $set): array {
            $codes = array_map('strval', array_keys($set));
            sort($codes);

            return $codes;
        }, $out);
    }

    /**
     * Ouvertures, réouvertures, fermetures et électrifications des lignes, un fait par ligne, type, portion et année.
     *
     * @param  array<int, list<string>>  $regions
     * @return list<array<string, mixed>>
     */
    private function lineFacts(array $regions): array
    {
        $types = array_keys(self::LINE_TYPES);
        $rows = DB::table('line_events as e')->join('lines as l', 'l.id', '=', 'e.line_id')
            ->whereIn('e.type', $types)
            ->where(fn ($q) => $q->whereNull('e.source')->orWhereNotIn('e.source', self::EXCLUDED_SOURCES))
            // Année seule d'archeoferroviaire : tirée du nom d'un tracé, pas une date (même règle que NetworkFigures).
            ->whereRaw("NOT (COALESCE(e.source, '') = 'archeoferroviaire' AND e.date_precision = 'year')")
            ->orderBy('e.id')
            ->get(['e.id', 'e.line_id', 'e.type', 'e.event_date', 'e.date_precision', 'e.confidence', 'e.source', 'e.source_url',
                'e.section_from', 'e.section_to', 'e.section_length_m', 'l.slug', 'l.name', 'l.length_official_m']);

        $groups = [];
        foreach ($rows as $r) {
            [$kind, $traffic] = self::LINE_TYPES[$r->type];
            $date = substr((string) $r->event_date, 0, 10);
            $precision = self::precision((string) $r->date_precision, $date, $r->source);
            $portion = self::portion($r->section_from, $r->section_to);
            // Une ouverture « aux voyageurs » et une ouverture « tous trafics » de la même portion la même année
            // sont le même fait ; les fermetures restent distinctes (voyageurs, fret, totale).
            $family = $kind === 'closure' ? $kind.':'.$traffic : $kind;
            $key = $r->line_id.'|'.$family.'|'.$portion.'|'.substr($date, 0, 4);
            $groups[$key][] = [
                'id' => (int) $r->id, 'line_id' => (int) $r->line_id, 'kind' => $kind, 'family' => $family, 'traffic' => $traffic,
                'date' => $date, 'precision' => $precision, 'confidence' => (string) $r->confidence, 'portion' => $portion,
                'provider' => $this->rights->providerFor('line_events', $r->source, $r->source_url), 'url' => $r->source_url,
                'from' => self::place($r->section_from), 'to' => self::place($r->section_to),
                'length_m' => $r->section_length_m === null ? null : (int) $r->section_length_m,
                'line' => ['slug' => (string) $r->slug, 'name' => (string) $r->name], 'official_m' => $r->length_official_m,
            ];
        }

        // Toute la ligne la même année qu'une portion de la même ligne et du même type : la portion la plus ancienne
        // de l'année reçoit ses sources. Une seule « toute la ligne » sans portion : elle porte la longueur de la ligne.
        $sectioned = [];
        $wholeCount = [];
        foreach ($groups as $key => $members) {
            $m = $members[0];
            if ($m['portion'] === 'whole') {
                $wholeCount[$m['line_id'].'|'.$m['family']] = ($wholeCount[$m['line_id'].'|'.$m['family']] ?? 0) + 1;
            } else {
                $sectioned[$m['line_id'].'|'.$m['family']][substr($m['date'], 0, 4)][] = $key;
                $sectioned[$m['line_id'].'|'.$m['family']]['any'] = true;
            }
        }
        foreach ($groups as $key => $members) {
            $m = $members[0];
            $lf = $m['line_id'].'|'.$m['family'];
            if ($m['portion'] !== 'whole' || ! isset($sectioned[$lf][substr($m['date'], 0, 4)])) {
                continue;
            }
            $targets = $sectioned[$lf][substr($m['date'], 0, 4)];
            usort($targets, fn (string $a, string $b): int => min(array_column($groups[$a], 'date')) <=> min(array_column($groups[$b], 'date')));
            foreach ($members as $w) {
                $groups[$targets[0]][] = ['length_m' => null, 'from' => null, 'to' => null, 'merged' => true] + $w;
            }
            unset($groups[$key]);
        }

        $facts = [];
        foreach ($groups as $members) {
            $m = $members[0];
            $lf = $m['line_id'].'|'.$m['family'];
            $kept = array_values(array_filter($members, fn (array $x): bool => $this->isPublishable($x['provider'])));
            $dated = array_values(array_filter($kept, fn (array $x): bool => ! ($x['merged'] ?? false)));
            [$date, $precision] = self::agreedDate($dated !== [] ? $dated : ($kept !== [] ? $kept : $members));
            $own = array_values(array_filter($members, fn (array $x): bool => ! ($x['merged'] ?? false)));
            if ($m['portion'] === 'whole') {
                $alone = ! isset($sectioned[$lf]['any']) && ($wholeCount[$lf] ?? 0) === 1;
                $lengths = array_filter(array_column($own, 'length_m'));
                $meters = $alone ? ($lengths !== [] ? max($lengths) : ($m['official_m'] !== null ? (int) $m['official_m'] : null)) : null;
            } else {
                $lengths = array_filter(array_column($kept !== [] ? $kept : $own, 'length_m'));
                $meters = $lengths !== [] ? max($lengths) : null;
            }
            $traffics = array_values(array_unique(array_filter(array_column($own, 'traffic'))));
            $facts[] = [
                'id' => 'e'.min(array_column($own, 'id')),
                'kind' => $m['kind'],
                'traffic' => $m['kind'] === 'closure' ? $m['traffic'] : (count($traffics) === 1 && count(array_filter(array_column($own, 'traffic'), fn ($t) => $t === null)) === 0 ? $traffics[0] : null),
                'year' => (int) substr($date, 0, 4),
                'date' => $date,
                'precision' => $precision,
                'line' => $m['line'],
                'sections' => $m['portion'] === 'whole' ? [] : [[$own[0]['from'] ?? null, $own[0]['to'] ?? null, $meters === null ? null : round($meters / 1000, 1)]],
                'km' => $meters === null ? null : round($meters / 1000, 1),
                'path' => '/lines/'.$m['line']['slug'],
                'regions' => $this->sectionRegions($m['line_id'], $own[0]['from'] ?? null, $own[0]['to'] ?? null, $regions),
                'sources' => self::sources($kept),
                'shown' => $kept !== [],
            ];
        }

        return $facts;
    }

    /**
     * Régions d'une portion : celles de ses extrémités quand ce sont des gares connues de la ligne, sinon celles de la
     * ligne (une section de Saint-Étienne n'est pas en Île-de-France parce que sa ligne finit à Moret).
     *
     * @param  array<int, list<string>>  $regions
     * @return list<string>
     */
    private function sectionRegions(int $lineId, ?string $from, ?string $to, array $regions): array
    {
        $found = [];
        foreach ([$from, $to] as $place) {
            $k = LineChronicle::placeKey($place);
            if ($k !== null && isset($this->placeRegions[$lineId][$k])) {
                $found[$this->placeRegions[$lineId][$k]] = true;
            }
        }
        if ($found === []) {
            return $regions[$lineId] ?? [];
        }
        $codes = array_map('strval', array_keys($found));
        sort($codes);

        return $codes;
    }

    /**
     * Mises en service de systèmes de signalisation, un fait par ligne, système, portion et année.
     *
     * @param  array<int, list<string>>  $regions
     * @return list<array<string, mixed>>
     */
    private function signallingFacts(array $regions): array
    {
        $rows = DB::table('line_signalling_events as e')->join('lines as l', 'l.id', '=', 'e.line_id')
            ->orderBy('e.id')
            ->get(['e.id', 'e.line_id', 'e.system', 'e.event_date', 'e.date_precision', 'e.confidence', 'e.source', 'e.source_url',
                'e.section_from', 'e.section_to', 'l.slug', 'l.name']);
        $groups = [];
        foreach ($rows as $r) {
            $date = substr((string) $r->event_date, 0, 10);
            $key = $r->line_id.'|'.$r->system.'|'.self::portion($r->section_from, $r->section_to).'|'.substr($date, 0, 4);
            $groups[$key][] = [
                'id' => (int) $r->id, 'line_id' => (int) $r->line_id, 'system' => (string) $r->system, 'date' => $date,
                'precision' => self::precision((string) $r->date_precision, $date, $r->source), 'confidence' => (string) ($r->confidence ?? 'medium'),
                'provider' => $this->rights->providerFor('line_signalling_events', $r->source, $r->source_url), 'url' => $r->source_url,
                'from' => self::place($r->section_from), 'to' => self::place($r->section_to),
                'line' => ['slug' => (string) $r->slug, 'name' => (string) $r->name],
            ];
        }
        $facts = [];
        foreach ($groups as $members) {
            $m = $members[0];
            $kept = array_values(array_filter($members, fn (array $x): bool => $this->isPublishable($x['provider'])));
            [$date, $precision] = self::agreedDate($kept !== [] ? $kept : $members);
            $facts[] = [
                'id' => 's'.min(array_column($members, 'id')),
                'kind' => 'signalling',
                'system' => $m['system'],
                'year' => (int) substr($date, 0, 4),
                'date' => $date,
                'precision' => $precision,
                'line' => $m['line'],
                'sections' => $m['from'] === null && $m['to'] === null ? [] : [[$m['from'], $m['to'], null]],
                'km' => null,
                'path' => '/lines/'.$m['line']['slug'],
                'regions' => $this->sectionRegions($m['line_id'], $m['from'], $m['to'], $regions),
                'sources' => self::sources($kept),
                'shown' => $kept !== [],
            ];
        }

        return $facts;
    }

    /**
     * Accidents graves publiés (règles de la liste /accidents), avec leurs sources publiables.
     *
     * @return list<array<string, mixed>>
     */
    private function accidentFacts(): array
    {
        $accidents = Accident::query()->where('deaths', '>=', self::ACCIDENT_MIN_DEATHS)
            ->toBase()->get(['id', 'slug', 'occurred_on', 'date_precision', 'name', 'deaths', 'department_code']);
        $sources = [];
        foreach (DB::table('accident_sources')->whereIn('accident_id', $accidents->pluck('id'))
            ->orderByRaw("CASE role WHEN 'article' THEN 0 WHEN 'report' THEN 1 ELSE 2 END")->orderBy('id')
            ->get(['accident_id', 'provider', 'url']) as $s) {
            $sources[(int) $s->accident_id][] = ['provider' => (string) $s->provider, 'url' => $s->url];
        }
        $facts = [];
        foreach ($accidents as $a) {
            $kept = array_values(array_filter($sources[(int) $a->id] ?? [], fn (array $s): bool => $this->isPublishable($s['provider'])));
            $date = substr((string) $a->occurred_on, 0, 10);
            $region = $a->department_code !== null ? FrenchDepartments::regionCode((string) $a->department_code) : null;
            $facts[] = [
                'id' => 'a'.$a->id,
                'kind' => 'accident',
                'year' => (int) substr($date, 0, 4),
                'date' => $date,
                'precision' => in_array($a->date_precision, ['day', 'month', 'year'], true) ? (string) $a->date_precision : 'year',
                'names' => array_filter((array) json_decode((string) $a->name, true), 'is_string'),
                'deaths' => (int) $a->deaths,
                'km' => null,
                'path' => '/accidents/'.$a->slug,
                'regions' => $region !== null ? [$region] : [],
                'sources' => self::sources($kept),
                'shown' => $kept !== [],
            ];
        }

        return $facts;
    }

    /**
     * Séries de matériel marquantes : année de mise en service, au moins MATERIAL_MIN_UNITS exemplaires ou rame TGV.
     *
     * @return list<array<string, mixed>>
     */
    private function materialFacts(): array
    {
        $rows = DB::table('materials')->where('scope', 'france')->whereNotNull('in_service_from')
            ->where(fn ($q) => $q->where('units_built', '>=', self::MATERIAL_MIN_UNITS)->orWhere('type', 'rame_tgv'))
            ->orderBy('id')->get(['id', 'slug', 'name', 'in_service_from', 'units_built', 'wikidata_id', 'source']);
        $facts = [];
        foreach ($rows as $m) {
            $provider = $this->rights->providerFor('materials', $m->source);
            $url = $provider === 'wikidata' && $m->wikidata_id !== null ? 'https://www.wikidata.org/wiki/'.$m->wikidata_id : null;
            $kept = $this->isPublishable($provider) ? [['provider' => (string) $provider, 'url' => $url]] : [];
            $year = (int) $m->in_service_from;
            $facts[] = [
                'id' => 'm'.$m->id,
                'kind' => 'material',
                'year' => $year,
                'date' => sprintf('%04d-01-01', $year),
                'precision' => 'year',
                'title' => (string) $m->name,
                'units' => $m->units_built === null ? null : (int) $m->units_built,
                'km' => null,
                'path' => '/materials/'.$m->slug,
                'regions' => [],
                'sources' => self::sources($kept),
                'shown' => $kept !== [],
            ];
        }

        return $facts;
    }

    /** @return list<array<string, mixed>> */
    private function milestoneFacts(): array
    {
        return array_map(fn (array $m): array => [
            'id' => 'j'.$m['key'],
            'kind' => 'milestone',
            'key' => $m['key'],
            'year' => (int) substr($m['date'], 0, 4),
            'date' => $m['date'],
            'precision' => 'day',
            'km' => null,
            'path' => null,
            'regions' => [],
            'sources' => [['provider' => 'wikipedia', 'url' => $m['url']]],
            'shown' => true,
        ], self::MILESTONES);
    }

    /**
     * Regroupe les faits d'une même ligne, du même type (trafic, système) et de la même année :
     *   - un fait « toute la ligne » rejoint le plus ancien fait de portion de l'année (ses sources s'y ajoutent) ;
     *   - les portions datées du même jour (ou du même mois, de la même année seule) font un seul fait à plusieurs
     *     portions, longueurs additionnées (une LGV ouverte le 27 septembre 1981 en vingt tronçons OpenHistoricalMap).
     * Un fait détaillé et un fait seulement compté ne se mélangent pas.
     *
     * @param  list<array<string, mixed>>  $facts
     * @return list<array<string, mixed>>
     */
    public static function collapse(array $facts): array
    {
        $groups = [];
        foreach ($facts as $f) {
            $key = implode('|', [$f['line']['slug'] ?? '', $f['kind'], $f['traffic'] ?? '', $f['system'] ?? '', $f['year'], $f['shown'] ? 1 : 0]);
            $groups[$key][] = $f;
        }
        $out = [];
        foreach ($groups as $group) {
            $whole = array_values(array_filter($group, fn (array $f): bool => $f['sections'] === []));
            $parts = array_values(array_filter($group, fn (array $f): bool => $f['sections'] !== []));
            if ($parts === []) {
                array_push($out, ...$whole);

                continue;
            }
            usort($parts, fn (array $a, array $b): int => [$a['date'], $a['id']] <=> [$b['date'], $b['id']]);
            $byDate = [];
            foreach ($parts as $f) {
                $byDate[$f['date'].'|'.$f['precision']][] = $f;
            }
            $merged = [];
            foreach ($byDate as $same) {
                $first = array_shift($same);
                foreach ($same as $f) {
                    $first['sections'] = array_merge($first['sections'], $f['sections']);
                    $first['km'] = self::sectionsKm($first['sections']);
                    $first['sources'] = self::sources(array_merge($first['sources'], $f['sources']));
                    $first['regions'] = array_values(array_unique(array_merge($first['regions'], $f['regions'])));
                    sort($first['regions']);
                }
                $merged[] = $first;
            }
            foreach ($whole as $w) {
                $merged[0]['sources'] = self::sources(array_merge($merged[0]['sources'], $w['sources']));
            }
            array_push($out, ...$merged);
        }

        return $out;
    }

    /**
     * Longueur de portions d'un même fait, sans compter deux fois le même tronçon : une portion dont les deux
     * extrémités sont déjà reliées par les autres (« de Bapaume à Marcoing » et « de Bapaume à Havrincourt » +
     * « d'Havrincourt à Marcoing ») n'ajoute rien. null si aucune longueur n'est connue.
     *
     * @param  list<array{0: ?string, 1: ?string, 2: float|int|null}>  $sections
     */
    public static function sectionsKm(array $sections): ?float
    {
        $edges = [];
        foreach ($sections as $i => [$from, $to]) {
            $a = LineChronicle::placeKey($from);
            $b = LineChronicle::placeKey($to);
            if ($a !== null && $b !== null && $a !== $b) {
                $edges[$i] = [$a, $b];
            }
        }
        $order = array_keys($sections);
        usort($order, fn (int $x, int $y): int => ($sections[$y][2] ?? 0) <=> ($sections[$x][2] ?? 0));
        $total = null;
        $dropped = [];
        foreach ($order as $i) {
            $km = $sections[$i][2] ?? null;
            if ($km === null) {
                continue;
            }
            if (isset($edges[$i]) && self::connected($edges[$i][0], $edges[$i][1], array_diff_key($edges, [$i => true] + $dropped))) {
                $dropped[$i] = true;

                continue;
            }
            $total = ($total ?? 0) + $km;
        }

        return $total === null ? null : round($total, 1);
    }

    /** @param  array<int, array{0: string, 1: string}>  $edges */
    private static function connected(string $from, string $to, array $edges): bool
    {
        $seen = [$from => true];
        $queue = [$from];
        while ($queue !== []) {
            $at = array_shift($queue);
            foreach ($edges as [$a, $b]) {
                $next = $a === $at ? $b : ($b === $at ? $a : null);
                if ($next === $to) {
                    return true;
                }
                if ($next !== null && ! isset($seen[$next])) {
                    $seen[$next] = true;
                    $queue[] = $next;
                }
            }
        }

        return false;
    }

    /**
     * Précision d'une date : un 1er janvier « au jour » d'OpenHistoricalMap est une année seule (start_date=1827).
     */
    public static function precision(string $precision, string $date, ?string $source): string
    {
        $precision = isset(self::PRECISION_RANK[$precision]) ? $precision : 'year';

        return $precision === 'day' && $source === 'ohm' && str_ends_with($date, '-01-01') ? 'year' : $precision;
    }

    /**
     * Date retenue d'un fait : au jour si les sources qui le datent au jour donnent le même jour, avec une confiance
     * qui n'est pas faible ; sinon au mois commun ; sinon à l'année (la plus ancienne date de l'année).
     *
     * @param  list<array{date: string, precision: string, confidence?: string}>  $members
     * @return array{0: string, 1: string}
     */
    public static function agreedDate(array $members): array
    {
        $year = substr(min(array_column($members, 'date')), 0, 4);
        $days = array_values(array_unique(array_map(fn (array $m): string => $m['date'],
            array_filter($members, fn (array $m): bool => $m['precision'] === 'day' && ($m['confidence'] ?? 'medium') !== 'low'))));
        if (count($days) === 1) {
            return [$days[0], 'day'];
        }
        $months = array_values(array_unique(array_map(fn (array $m): string => substr($m['date'], 0, 7),
            array_filter($members, fn (array $m): bool => in_array($m['precision'], ['day', 'month'], true)))));
        if (count($months) === 1) {
            return [$months[0].'-01', 'month'];
        }

        return [$year.'-01-01', 'year'];
    }

    /** Clé de portion : « whole » sans lieu, sinon les lieux normalisés et triés (LineChronicle::placeKey). */
    public static function portion(?string $from, ?string $to): string
    {
        $places = array_values(array_unique(array_filter([LineChronicle::placeKey($from), LineChronicle::placeKey($to)])));
        if ($places === []) {
            return 'whole';
        }
        sort($places);

        return implode('|', $places);
    }

    private static function place(?string $place): ?string
    {
        return $place !== null && trim($place) !== '' ? LineChronicle::dashes(trim($place)) : null;
    }

    /**
     * Une source par fournisseur, avec le premier lien cité.
     *
     * @param  list<array{provider: ?string, url: ?string}>  $members
     * @return list<array{provider: string, url: ?string}>
     */
    private static function sources(array $members): array
    {
        $out = [];
        foreach ($members as $m) {
            $p = (string) $m['provider'];
            if (! isset($out[$p]) || ($out[$p]['url'] === null && ($m['url'] ?? null) !== null)) {
                $out[$p] = ['provider' => $p, 'url' => $m['url'] ?? null];
            }
        }

        return array_values($out);
    }
}

<?php

namespace App\Services\Archives;

use Illuminate\Support\Facades\DB;

/**
 * Lieux de toutes les lignes de VFF, pour rattacher un document d'archives à la bonne ligne et repérer
 * les titres ambigus (deux lieux communs à plusieurs lignes).
 *
 * Lieux d'une ligne : communes et noms de ses gares (station_lines, toutes époques), extrémités de son
 * nom (« Ligne de Limoges-Bénédictins à Angoulême »), de ses anciens noms (Wikidata, lignes-oubliees,
 * Wikipédia, lignes fusionnées) et noms de ses gares terminus. Un nom composé d'une commune et d'un
 * quartier (« Limoges-Bénédictins ») donne aussi la commune seule comme extrémité.
 *
 * Années de la ligne : première concession ou ouverture datée (line_events), sinon dix ans avant la
 * première ère où elle est en service ; dernière fermeture, déclassement ou dépose datés, sinon dix ans
 * après la dernière ère en service ; aucune fin si elle est en service aujourd'hui.
 */
final class LinePlaceIndex
{
    /** Lignes jamais interrogées : raccordements, voies-mères et autres ouvrages sans « ligne de X à Y » propre. */
    public const SKIPPED_NAMES = '/^(raccordement|voie-m[eè]re|saut-de-mouton|laverie|plan inclin|t[ée]l[ée]ph[ée]rique|funiculaire|transbordeur|transbordement|bifurcation|boucle|d[ée]viation|contournement|faisceau|r[ée]seau)/iu';

    /** Couples de recherche par ligne au plus. */
    public const MAX_QUERY_PAIRS = 4;

    /** @var array<string, list<int>> clé de lieu → lignes qui y passent */
    private array $byKey = [];

    private int $maxWords = 1;

    /** @param array<int, LinePlaces> $lines par identifiant */
    public function __construct(public readonly array $lines)
    {
        foreach ($lines as $line) {
            foreach (array_keys($line->places) as $key) {
                $this->byKey[$key][] = $line->id;
                $this->maxWords = max($this->maxWords, substr_count($key, ' ') + 1);
            }
        }
    }

    /** Toutes les lignes de la base (lecture seule). */
    public static function load(): self
    {
        $stations = [];
        foreach (DB::select(
            'SELECT sl.line_id, sl.pk_m, s.name, s.city FROM station_lines sl JOIN stations s ON s.id = sl.station_id
             ORDER BY sl.line_id, sl.pk_m NULLS LAST, s.id',
        ) as $r) {
            $stations[(int) $r->line_id][] = ['name' => (string) $r->name, 'city' => $r->city, 'pk_m' => $r->pk_m === null ? null : (int) $r->pk_m];
        }
        $dates = [];
        foreach (DB::select(
            "WITH ev AS (
                SELECT line_id,
                       (MIN(EXTRACT(YEAR FROM event_date)) FILTER (WHERE type IN ('concession','opening','opening_passenger','opening_freight')))::int AS first_year,
                       (MAX(EXTRACT(YEAR FROM event_date)) FILTER (WHERE type IN ('closure','declassification','dismantling')))::int AS last_year
                FROM line_events GROUP BY line_id),
             seg AS (
                SELECT s.line_id, MIN(e.year) AS first_era, MAX(e.year) AS last_era, BOOL_OR(e.is_current) AS open_today
                FROM line_segments s JOIN eras e ON e.id = s.era_id
                WHERE s.status IN ('in_service','single_track')
                GROUP BY s.line_id)
             SELECT COALESCE(ev.line_id, seg.line_id) AS line_id, ev.first_year, ev.last_year, seg.first_era, seg.last_era,
                    COALESCE(seg.open_today, false) AS open_today
             FROM ev FULL JOIN seg ON seg.line_id = ev.line_id",
        ) as $r) {
            $dates[(int) $r->line_id] = (array) $r;
        }

        $lines = [];
        foreach (DB::select('SELECT id, slug, name, alt_names, wikipedia_title FROM lines ORDER BY id') as $r) {
            $d = $dates[(int) $r->id] ?? [];
            $lines[(int) $r->id] = self::linePlaces([
                'id' => (int) $r->id,
                'slug' => (string) $r->slug,
                'name' => (string) $r->name,
                'alt_names' => is_string($r->alt_names) ? json_decode($r->alt_names, true) : null,
                'wikipedia_title' => $r->wikipedia_title,
                'stations' => $stations[(int) $r->id] ?? [],
                'first_year' => self::intOrNull($d['first_year'] ?? null),
                'last_year' => self::intOrNull($d['last_year'] ?? null),
                'first_era' => self::intOrNull($d['first_era'] ?? null),
                'last_era' => self::intOrNull($d['last_era'] ?? null),
                'open_today' => (bool) ($d['open_today'] ?? false),
            ]);
        }

        return new self($lines);
    }

    /**
     * Lieux et années d'une ligne à partir de ses données brutes (testable sans base).
     *
     * @param  array{id: int, slug: string, name: string, alt_names?: mixed, wikipedia_title?: ?string,
     *     stations?: list<array{name: string, city: ?string, pk_m?: ?int}>, first_year?: ?int, last_year?: ?int,
     *     first_era?: ?int, last_era?: ?int, open_today?: bool}  $row
     */
    public static function linePlaces(array $row): LinePlaces
    {
        $stations = $row['stations'] ?? [];
        $places = [];
        $add = function (?string $name) use (&$places): ?string {
            $name = $name === null ? '' : trim($name);
            $key = $name === '' ? null : TitlePlaces::key($name);
            if ($key !== null && ! isset($places[$key])) {
                $places[$key] = $name;
            }

            return $key;
        };
        $cityKeys = [];
        $cityOfStation = [];
        foreach ($stations as $s) {
            $cityKey = $add($s['city'] ?? null);
            $nameKey = $add($s['name']);
            if ($cityKey !== null) {
                $cityKeys[$cityKey] = true;
                if ($nameKey !== null) {
                    $cityOfStation[$nameKey] = (string) $s['city'];
                }
            }
        }

        // Une extrémité et ses variantes : le nom, et la commune seule quand le nom commence par elle
        // (« Limoges » pour « Limoges-Bénédictins »), jamais une commune qui ne figure pas dans le nom
        // (« La Râpée » n'est pas « Paris » : sinon toute ligne parisienne aurait Paris pour extrémité).
        $side = function (string $name) use ($add, $cityKeys, $cityOfStation): array {
            $names = [$name];
            $key = TitlePlaces::key($name);
            $city = $key !== null ? ($cityOfStation[$key] ?? null) : null;
            $cityKey = $city !== null ? TitlePlaces::key($city) : null;
            if ($cityKey !== null && $cityKey !== $key && str_starts_with((string) $key, $cityKey.' ')) {
                $names[] = (string) $city;
            }
            if (preg_match('/^([^-\s]+)-/u', $name, $m) === 1 && isset($cityKeys[TitlePlaces::key($m[1]) ?? ''])) {
                $names[] = $m[1];
            }
            $byKey = [];
            foreach ($names as $n) {
                $k = $add($n);
                if ($k !== null && ! isset($byKey[$k])) {
                    $byKey[$k] = $n;
                }
            }

            return ['names' => $byKey, 'city' => $cityKey];
        };
        // Nom le plus court d'une extrémité : le terme de recherche (« Limoges » plutôt que « Limoges-Bénédictins »).
        $term = function (array $names): string {
            $shortest = '';
            foreach ($names as $n) {
                $shortest = $shortest === '' || mb_strlen($n) < mb_strlen($shortest) ? $n : $shortest;
            }

            return TitlePlaces::queryTerm($shortest);
        };

        $routes = [];
        foreach (self::historicalNames($row) as $name) {
            $ends = self::routeEnds($name);
            if ($ends !== null) {
                $routes[] = $ends;
            }
        }
        $withPk = array_values(array_filter($stations, fn (array $s): bool => ($s['pk_m'] ?? null) !== null));
        if (count($withPk) >= 2) {
            $first = $withPk[0];
            $last = $withPk[count($withPk) - 1];
            $routes[] = [$first['name'], $last['name']];
        }

        $endPairs = [];
        $queryPairs = [];
        $seenQueries = [];
        $eligible = preg_match(self::SKIPPED_NAMES, $row['name']) !== 1;
        foreach ($routes as [$a, $b]) {
            $x = $side($a);
            $y = $side($b);
            // Deux extrémités dans la même commune (ligne de ceinture, ligne urbaine) : la commune n'est pas une extrémité.
            $shared = $x['city'] !== null && $x['city'] === $y['city'] ? $x['city'] : null;
            if ($shared !== null && count($x['names']) > 1) {
                unset($x['names'][$shared]);
            }
            if ($shared !== null && count($y['names']) > 1) {
                unset($y['names'][$shared]);
            }
            if ($x['names'] === [] || $y['names'] === [] || array_intersect_key($x['names'], $y['names']) !== []) {
                continue;
            }
            $endPairs[] = [array_keys($x['names']), array_keys($y['names'])];
            $termA = $term($x['names']);
            $termB = $term($y['names']);
            $id = implode('|', [TitlePlaces::key($termA), TitlePlaces::key($termB)]);
            $di = implode('|', [TitlePlaces::key($termB), TitlePlaces::key($termA)]);
            if ($eligible && $termA !== '' && $termB !== '' && ! isset($seenQueries[$id]) && ! isset($seenQueries[$di])
                && count($queryPairs) < self::MAX_QUERY_PAIRS) {
                $seenQueries[$id] = true;
                $queryPairs[] = [$termA, $termB];
            }
        }

        $firstYear = $row['first_year'] ?? null;
        if ($firstYear === null && ($row['first_era'] ?? null) !== null) {
            $firstYear = (int) $row['first_era'] - 10;
        }
        $openToday = (bool) ($row['open_today'] ?? false);
        $lastYear = $openToday ? null : ($row['last_year'] ?? (($row['last_era'] ?? null) !== null ? (int) $row['last_era'] + 10 : null));

        return new LinePlaces(
            id: $row['id'],
            slug: $row['slug'],
            name: $row['name'],
            places: $places,
            endPairs: $endPairs,
            queryPairs: $queryPairs,
            firstYear: $firstYear === null ? null : (int) $firstYear,
            lastYear: $lastYear === null ? null : (int) $lastYear,
            openToday: $openToday,
            via: self::viaKeys($row['name']),
        );
    }

    /**
     * Lieux de passage nommés par un nom de ligne, en clés de lieu : « Ligne de Lyon-Perrache à
     * Marseille-St-Charles (via Grenoble) » → [grenoble], « Ligne de Sedan à Paliseul par Corbion et Bouillon »
     * → [corbion, bouillon]. Liste vide quand le nom n'en donne pas.
     *
     * @return list<string>
     */
    public static function viaKeys(string $name): array
    {
        $n = str_replace('’', "'", $name);
        if (preg_match('/\((?:via|par)\s+([^)]+)\)/iu', $n, $m) !== 1
            && preg_match('/\s(?:par|via)\s+(\p{Lu}[^()]*?)\s*(?:\(.*)?$/u', $n, $m) !== 1) {
            return [];
        }
        $keys = [];
        foreach (preg_split('/\s*(?:,|\bet\b)\s*/u', trim($m[1])) ?: [] as $part) {
            $key = TitlePlaces::key($part);
            if ($key !== null) {
                $keys[] = $key;
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * Extrémités d'un nom de ligne : « Ligne de Paris-Saint-Lazare au Havre » → [Paris-Saint-Lazare, Havre],
     * « Chemin de fer d'Épernay à Reims » → [Épernay, Reims], « Ligne L'Aigle - Conches » → [L'Aigle, Conches].
     * La partie « par … » et les parenthèses sont ignorées. Null si le nom ne relie pas deux lieux.
     *
     * @return array{0: string, 1: string}|null
     */
    public static function routeEnds(string $name): ?array
    {
        $n = trim((string) preg_replace('/\s*\([^)]*\)\s*/u', ' ', str_replace('’', "'", $name)));
        if (preg_match("/(?:^|\s)(?:de|du|des|d')\s*(?=\p{Lu})(.+?)\s+(?:à|au|aux)\s+(.+?)(?:\s+(?:par|via)\s+.+)?$/u", $n, $m) === 1) {
            return [trim($m[1]), trim((string) preg_replace("/^(?:l'|la |le |les )/iu", '', trim($m[2])))];
        }
        if (preg_match('/^(?:[Ll]ignes?|[Vv]oies? ferr[ée]es?|[Cc]hemin de fer)?\s*(\p{Lu}.+?)\s+[-–—]\s+(\p{Lu}.+)$/u', $n, $m) === 1) {
            return [trim($m[1]), trim($m[2])];
        }

        return null;
    }

    /**
     * Nom actuel et anciens noms d'une ligne (Wikidata, lignes-oubliees, titre Wikipédia, lignes fusionnées).
     *
     * @param  array{name: string, alt_names?: mixed, wikipedia_title?: ?string}  $row
     * @return list<string>
     */
    private static function historicalNames(array $row): array
    {
        $alt = is_array($row['alt_names'] ?? null) ? $row['alt_names'] : [];
        $names = [$row['name']];
        foreach (is_array($alt['names'] ?? null) ? $alt['names'] : [] as $n) {
            if (is_array($n) && is_string($n['name'] ?? null)) {
                $names[] = $n['name'];
            }
        }
        if (is_array($alt['lignes_oubliees'] ?? null) && is_string($alt['lignes_oubliees']['name'] ?? null)) {
            $names[] = $alt['lignes_oubliees']['name'];
        }
        foreach (is_array($alt['merged'] ?? null) ? $alt['merged'] : [] as $m) {
            if (is_array($m) && is_string($m['name'] ?? null)) {
                $names[] = $m['name'];
            }
        }
        if (is_string($row['wikipedia_title'] ?? null) && $row['wikipedia_title'] !== '') {
            $names[] = $row['wikipedia_title'];
        }

        return array_values(array_unique($names));
    }

    private static function intOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    /** @return array<string, list<int>> */
    public function keys(): array
    {
        return $this->byKey;
    }

    public function maxWords(): int
    {
        return $this->maxWords;
    }

    public function line(int $id): ?LinePlaces
    {
        return $this->lines[$id] ?? null;
    }
}

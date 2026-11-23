<?php

declare(strict_types=1);

namespace App\Services\Heritage;

use App\Services\Sncf\StationAttendanceMatcher;

/**
 * Rattache les notices Mérimée et les éléments Wikidata aux gares de VFF et compose les lignes de `station_heritage`.
 * Sans accès à la base ni au réseau : les gares, les notices (MerimeeSource::railwayRecords) et les éléments
 * (WikidataStationHeritage::fetch) lui sont donnés.
 *
 * Rattachement, du plus sûr au moins sûr :
 *   1. `wikidata` : l'élément Wikidata de la gare (`stations.wikidata_id`) porte la référence Mérimée (P380) ou un statut
 *      de patrimoine (P1435) ; la notice de la référence en donne l'arrêté, les auteurs et la datation ;
 *   2. `name-commune` : le nom de la gare figure dans le titre de la notice, dans la commune (ou à moins de 1,5 km) ;
 *   3. `proximity` : une seule gare à moins de 250 m des coordonnées de la notice (500 m pour un ouvrage de gare, dans
 *      la même commune), ou la plus proche si la suivante est deux fois plus loin ;
 *   4. `commune-unique` : notice sans coordonnées, une seule gare ouverte dans la commune ;
 *   5. `scored` : notice restée sans gare, gare à moins de 3 km au meilleur score expliqué (rescue).
 * Une notice qui reste sans gare, ou ambiguë, n'est pas écrite : le rapport la liste.
 */
final class StationHeritageBuilder
{
    private const STRENGTH = ['classified' => 4, 'registered' => 3, 'acr' => 2, 'inventory' => 1];

    private const NEAR_BUILDING_M = 250;

    private const NEAR_WORKS_M = 500;

    private const NAME_RADIUS_M = 1500;

    /** @var array<int, object> */
    private array $byId = [];

    /** @var array<string, list<object>> */
    private array $byQid = [];

    /**
     * @param  iterable<object>  $stations  `id`, `slug`, `name`, `city`, `department_code`, `status`, `wikidata_id`, `lat`, `lng`
     */
    public function __construct(iterable $stations)
    {
        foreach ($stations as $s) {
            $this->byId[(int) $s->id] = $s;
            if (! empty($s->wikidata_id)) {
                $this->byQid[$s->wikidata_id][] = $s;
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $records  notices Mérimée
     * @param  array<string, array<string, mixed>>  $wikidata  éléments par identifiant Q
     * @param  string|null  $merimeeUpdated  date de mise à jour du fichier Mérimée
     * @return array{rows: list<array<string, mixed>>, unmatched: list<array<string, mixed>>, methods: array<string, int>, scored: list<array<string, mixed>>}
     */
    public function build(array $records, array $wikidata, ?string $merimeeUpdated = null): array
    {
        $byRef = [];
        foreach ($records as $r) {
            $byRef[$r['ref']] = $r;
        }
        $rows = [];
        $used = [];
        $methods = [];

        // 1. Par Wikidata.
        foreach ($wikidata as $qid => $item) {
            foreach ($this->byQid[$qid] ?? [] as $station) {
                $record = null;
                foreach ($item['refs'] as $ref) {
                    if (isset($byRef[$ref])) {
                        $record = $byRef[$ref];
                        break;
                    }
                }
                $row = $this->row($station, $record, $item, $qid, 'wikidata', null, $merimeeUpdated);
                if ($row === null) {
                    continue;
                }
                $rows[$station->id.'|'.$row['ref']] = $row;
                $methods['wikidata'] = ($methods['wikidata'] ?? 0) + 1;
                if ($record !== null) {
                    $used[$record['ref']] = true;
                }
            }
        }

        // 2 à 4. Notices restantes.
        $unmatched = [];
        $scored = [];
        foreach ($records as $record) {
            if (isset($used[$record['ref']])) {
                continue;
            }
            $found = $this->match($record);
            if ($found['station'] === null) {
                $rescued = $this->rescue($record);
                if ($rescued === null) {
                    $unmatched[] = $record + ['why' => $found['method']];

                    continue;
                }
                $found = ['station' => $rescued['station'], 'method' => 'scored', 'distance' => $rescued['distance']];
                $scored[] = ['record' => $record, 'station' => $rescued['station'], 'score' => $rescued['score'], 'reasons' => $rescued['reasons']];
            }
            $station = $found['station'];
            $item = $record['element'] === 'building' && ! empty($station->wikidata_id) ? ($wikidata[$station->wikidata_id] ?? null) : null;
            $row = $this->row($station, $record, $item, $station->wikidata_id ?? null, $found['method'], $found['distance'], $merimeeUpdated);
            if ($row === null || isset($rows[$station->id.'|'.$row['ref']])) {
                continue;
            }
            $rows[$station->id.'|'.$row['ref']] = $row;
            $methods[$found['method']] = ($methods[$found['method']] ?? 0) + 1;
        }

        return ['rows' => array_values($rows), 'unmatched' => $unmatched, 'methods' => $methods, 'scored' => $scored];
    }

    /**
     * Gare d'une notice Mérimée (rattachements 2 à 4).
     *
     * @param  array<string, mixed>  $record
     * @return array{station: ?object, method: string, distance: ?int}
     */
    public function match(array $record): array
    {
        $title = StationAttendanceMatcher::normalize($record['title'].' '.$record['denomination']);
        $commune = StationAttendanceMatcher::normalize($record['commune']);
        $hasPoint = $record['lat'] !== null && $record['lng'] !== null;
        $works = $record['element'] === 'works';

        // « Halle des messageries de la gare d'Austerlitz » : le nom cité après « gare de ».
        $mention = preg_match('/\bgare\s+(?:de la |de l[\x{2019}\']|de |du |d[\x{2019}\']|des )([^,(]+?)(?:\s+(?:ou|dite?|dit)\b|,|\(|$)/iu', $record['title'], $m) === 1
            ? StationAttendanceMatcher::normalize($m[1]) : '';
        $named = [];
        $cited = [];
        $based = [];
        $near = [];
        $inCommune = [];
        foreach ($this->byId as $s) {
            $sameCommune = $commune !== '' && StationAttendanceMatcher::normalize((string) $s->city) === $commune;
            $distance = $hasPoint ? (int) round(self::distance($record['lat'], $record['lng'], (float) $s->lat, (float) $s->lng)) : null;
            if ($sameCommune) {
                $inCommune[] = $s;
            }
            $name = StationAttendanceMatcher::normalize(preg_replace('/^gare (de |d\')?/iu', '', (string) $s->name) ?? (string) $s->name);
            $full = strlen($name) >= 5 && str_contains($title, $name);
            $mentioned = strlen($mention) >= 4 && str_contains($name, $mention);
            $inTitle = $full || $mentioned;
            if ($inTitle && ($sameCommune || ($distance !== null && $distance <= self::NAME_RADIUS_M))) {
                $full ? $named[] = [$s, $distance ?? 0] : $cited[] = [$s, $distance ?? 0];
            }
            // « Montluçon-Ville » pour une notice « Gare de Montluçon » : le nom sans qualificatif, dans la commune.
            $base = StationAttendanceMatcher::normalize(preg_split('/[-(]/u', (string) $s->name)[0] ?? '');
            if (! $works && $sameCommune && strlen($base) >= 5 && str_contains($title, $base) && ($distance === null || $distance <= 800)) {
                $based[] = [$s, $distance ?? 0];
            }
            if ($distance !== null && $distance <= ($works ? self::NEAR_WORKS_M : self::NEAR_BUILDING_M) && (! $works || $sameCommune || $inTitle)) {
                $near[] = [$s, $distance];
            }
        }

        if ($named !== []) {
            usort($named, fn (array $a, array $b): int => $a[1] <=> $b[1]);
            // Le nom le plus long l'emporte (« Bordeaux-Saint-Jean » avant « Bordeaux »).
            usort($named, fn (array $a, array $b): int => strlen(StationAttendanceMatcher::normalize((string) $b[0]->name)) <=> strlen(StationAttendanceMatcher::normalize((string) $a[0]->name)));

            return ['station' => $named[0][0], 'method' => 'name-commune', 'distance' => $hasPoint ? $named[0][1] : null];
        }
        if ($cited !== []) {
            usort($cited, fn (array $a, array $b): int => $a[1] <=> $b[1]);
            if (count($cited) === 1 || $cited[1][1] >= 2 * max(1, $cited[0][1])) {
                return ['station' => $cited[0][0], 'method' => 'name-commune', 'distance' => $hasPoint ? $cited[0][1] : null];
            }
            // Gares superposées (même point à 25 m près, « Paris-Austerlitz » et son niveau souterrain) : le nom le plus court.
            $close = array_values(array_filter($cited, fn (array $c): bool => $c[1] - $cited[0][1] <= 25));
            if (count($close) === count($cited)) {
                usort($close, fn (array $a, array $b): int => strlen((string) $a[0]->name) <=> strlen((string) $b[0]->name));

                return ['station' => $close[0][0], 'method' => 'name-commune', 'distance' => $hasPoint ? $close[0][1] : null];
            }

            return ['station' => null, 'method' => 'ambiguous', 'distance' => null];
        }
        if ($based !== [] && $hasPoint) {
            usort($based, fn (array $a, array $b): int => $a[1] <=> $b[1]);
            if (count($based) === 1 || $based[1][1] >= 2 * max(1, $based[0][1])) {
                return ['station' => $based[0][0], 'method' => 'name-commune', 'distance' => $based[0][1]];
            }

            return ['station' => null, 'method' => 'ambiguous', 'distance' => null];
        }
        if ($near !== []) {
            usort($near, fn (array $a, array $b): int => $a[1] <=> $b[1]);
            if (count($near) === 1 || $near[1][1] >= 2 * max(1, $near[0][1])) {
                return ['station' => $near[0][0], 'method' => 'proximity', 'distance' => $near[0][1]];
            }

            return ['station' => null, 'method' => 'ambiguous', 'distance' => null];
        }
        if (! $hasPoint && ! $works) {
            $open = array_values(array_filter($inCommune, fn (object $s): bool => in_array($s->status, ['active', 'tourist'], true)));
            if (count($open) === 1) {
                return ['station' => $open[0], 'method' => 'commune-unique', 'distance' => null];
            }
        }

        return ['station' => null, 'method' => 'unmatched', 'distance' => null];
    }

    /** Score minimal d'un rattachement par points (rattachement 5), et écart minimal avec la deuxième gare. */
    public const RESCUE_MIN_SCORE = 75;

    /** Une gare (bâtiment voyageurs) demande plus de preuves qu'un ouvrage de dépôt : le nom de la gare, ou moins de 500 m. */
    public const RESCUE_MIN_SCORE_BUILDING = 85;

    public const RESCUE_MIN_MARGIN = 15;

    /**
     * Rattachement 5, `scored` : pour une notice que les règles 2 à 4 n'ont pas placée (rotonde ou château d'eau loin de la
     * gare, « gare Saint-Jean » sans « de »). Chaque gare à moins de 3 km reçoit des points, la meilleure est gardée
     * seulement si elle atteint RESCUE_MIN_SCORE et devance la suivante d'au moins RESCUE_MIN_MARGIN :
     *   - même commune : 30 ; distance 250 m : 35, 500 m : 30, 1 km : 20, 2 km : 10, 3 km : 5 ;
     *   - nom de la gare cité par la notice (« gare Saint-Jean », nom entier ou sans qualificatif) : 40 ;
     *   - gare ouverte au trafic (active ou touristique) : 10 ; seule gare ouverte de la commune : 15 de plus.
     * Un doute (deux gares proches du même score) laisse la notice non rattachée.
     *
     * @param  array<string, mixed>  $record
     * @return array{station: object, score: int, distance: ?int, reasons: list<string>}|null
     */
    public function rescue(array $record): ?array
    {
        $title = self::fold($record['title'].' '.$record['denomination']);
        $commune = StationAttendanceMatcher::normalize($record['commune']);
        $hasPoint = $record['lat'] !== null && $record['lng'] !== null;
        $mention = preg_match('/\bgare\s+(?:de la |de l[\x{2019}\']|de |du |d[\x{2019}\']|des )?([A-Z\x{C0}-\x{DD}][^,(]*?)(?:\s+(?:ou|dite?|dit)\b|,|\(|$)/u', $record['title'], $m) === 1
            ? self::fold($m[1]) : '';

        $inCommune = [];
        foreach ($this->byId as $s) {
            if ($this->inCommune($s, $commune) && in_array($s->status, ['active', 'tourist'], true)) {
                $inCommune[] = $s;
            }
        }
        $candidates = [];
        foreach ($this->byId as $s) {
            $sameCommune = $this->inCommune($s, $commune);
            $distance = $hasPoint ? (int) round(self::distance($record['lat'], $record['lng'], (float) $s->lat, (float) $s->lng)) : null;
            if (! $sameCommune && ($distance === null || $distance > 3000)) {
                continue;
            }
            $score = 0;
            $reasons = [];
            if ($sameCommune) {
                $score += 30;
                $reasons[] = 'même commune +30';
            }
            if ($distance !== null) {
                $points = $distance <= 250 ? 35 : ($distance <= 500 ? 30 : ($distance <= 1000 ? 20 : ($distance <= 2000 ? 10 : ($distance <= 3000 ? 5 : 0))));
                $score += $points;
                $reasons[] = "à {$distance} m +{$points}";
            }
            $name = self::fold(preg_replace('/^gare (de |d\')?/iu', '', (string) $s->name) ?? (string) $s->name);
            $base = self::fold(preg_split('/[-(]/u', (string) $s->name)[0] ?? '');
            $cited = (strlen($name) >= 5 && str_contains($title, $name)) || (strlen($mention) >= 4 && (str_contains($name, $mention) || str_contains($mention, $name)))
                || ($sameCommune && strlen($base) >= 5 && str_contains($title, $base));
            if ($cited) {
                $score += 40;
                $reasons[] = 'nom cité +40';
            }
            $open = in_array($s->status, ['active', 'tourist'], true);
            if ($open) {
                $score += 10;
                $reasons[] = 'gare ouverte +10';
                if ($sameCommune && count($inCommune) === 1) {
                    $score += 15;
                    $reasons[] = 'seule gare ouverte de la commune +15';
                }
            }
            $candidates[] = ['station' => $s, 'score' => $score, 'distance' => $distance, 'reasons' => $reasons];
        }
        usort($candidates, fn (array $a, array $b): int => $b['score'] <=> $a['score'] ?: ($a['distance'] ?? 0) <=> ($b['distance'] ?? 0));
        $minimum = $record['element'] === 'building' ? self::RESCUE_MIN_SCORE_BUILDING : self::RESCUE_MIN_SCORE;
        if ($candidates === [] || $candidates[0]['score'] < $minimum) {
            return null;
        }
        if (isset($candidates[1]) && $candidates[0]['score'] - $candidates[1]['score'] < self::RESCUE_MIN_MARGIN) {
            return null;
        }

        return $candidates[0];
    }

    /** Commune de la gare : la même, ou un arrondissement de celle-ci (« Marseille 3e Arrondissement » pour « Marseille »). */
    private function inCommune(object $station, string $commune): bool
    {
        $city = StationAttendanceMatcher::normalize((string) $station->city);

        return $commune !== '' && ($city === $commune || (str_starts_with($city, $commune) && ctype_digit(substr($city, strlen($commune), 1))));
    }

    /** Nom sans accents ni espaces, « Saint » écrit « st » : « gare Saint-Jean » et « Bordeaux-St-Jean » se retrouvent. */
    private static function fold(string $text): string
    {
        $text = (string) preg_replace('/\bsaint(e?)\b/iu', 'st$1', $text);

        return StationAttendanceMatcher::normalize($text);
    }

    /**
     * Ligne de `station_heritage` d'un objet, ou null quand il n'a ni protection, ni label, ni recensement.
     *
     * @param  array<string, mixed>|null  $record  notice Mérimée
     * @param  array<string, mixed>|null  $item  élément Wikidata de la gare
     * @return array<string, mixed>|null
     */
    private function row(object $station, ?array $record, ?array $item, ?string $qid, string $method, ?int $distance, ?string $merimeeUpdated): ?array
    {
        $designations = [];
        foreach ($record['designations'] ?? [] as $d) {
            $designations[$d['kind']] = $d;
        }
        foreach ($item['protections'] ?? [] as $p) {
            $designations[$p['kind']] ??= ['kind' => $p['kind'], 'date' => $p['date']];
            if ($record === null && ($designations[$p['kind']]['date'] ?? null) === null) {
                $designations[$p['kind']]['date'] = $p['date'];
            }
        }
        if ($designations === []) {
            return null;
        }
        uasort($designations, fn (array $a, array $b): int => self::STRENGTH[$b['kind']] <=> self::STRENGTH[$a['kind']]);
        $primary = reset($designations);

        $ref = $record['ref'] ?? null;
        if ($ref === null) {
            // Sans notice lue : une référence de la gare (PA avant IA), sinon l'identifiant Wikidata.
            $refs = $item['refs'] ?? [];
            usort($refs, fn (string $a, string $b): int => strcmp($a, $b));
            $ref = $refs[0] ?? $qid;
        }
        if ($ref === null) {
            return null;
        }
        $kind = preg_match('/^(PA|IA|EA)\d{8}$/', $ref) === 1 ? substr($ref, 0, 2) : 'Q';
        $architects = $record['architects'] ?? [];
        if ($architects === []) {
            foreach ($item['architects'] ?? [] as $name) {
                $architects[] = ['name' => $name, 'role' => 'architecte'];
            }
        } else {
            $architects = self::preferWikidataNames($architects, $item['architects'] ?? []);
        }
        $building = ($record['element'] ?? 'building') === 'building';
        $image = $building ? ($item['image'] ?? null) : null;

        return [
            'station_id' => (int) $station->id,
            'element' => $record['element'] ?? 'building',
            'protection' => $primary['kind'],
            'protection_date' => $primary['date'],
            'scope' => ($record['protection'] ?? null) === $primary['kind'] ? ($record['scope'] ?? null) : null,
            'designations' => array_values(array_map(fn (array $d): array => ['kind' => $d['kind'], 'date' => $d['date']], $designations)),
            'title' => $record['title'] ?? null,
            'denomination' => ($record['denomination'] ?? '') !== '' ? $record['denomination'] : null,
            'architects' => $architects,
            'built_from' => $record['built_from'] ?? $item['built'] ?? null,
            'built_to' => $record['built_to'] ?? null,
            'built_period' => $record['built_period'] ?? null,
            'style' => $building ? ($item['style'] ?? null) : null,
            'ref' => $ref,
            'ref_kind' => $kind,
            'ref_url' => $kind === 'Q' ? 'https://www.wikidata.org/wiki/'.$ref : MerimeeSource::NOTICE_URL.$ref,
            'wikidata_id' => $qid,
            'image_file' => $image['file'] ?? null,
            'image_url' => $image['url'] ?? null,
            'image_source_url' => $image['source_url'] ?? null,
            'image_license' => $image['license'] ?? null,
            'image_author' => $image['author'] ?? null,
            'source' => $record !== null ? 'merimee' : 'wikidata',
            'match_method' => $method,
            'match_distance_m' => $distance,
            'source_updated' => $record !== null ? $merimeeUpdated : null,
        ];
    }

    /**
     * « Laloux Victor » devient le libellé de Wikidata (« Victor Laloux ») quand les deux écrivent les mêmes mots.
     *
     * @param  list<array{name: string, role: ?string}>  $architects
     * @param  list<string>  $wikidataNames
     * @return list<array{name: string, role: ?string}>
     */
    private static function preferWikidataNames(array $architects, array $wikidataNames): array
    {
        $key = function (string $name): string {
            $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', mb_strtolower($name)) ?: mb_strtolower($name);
            $words = preg_split('/[^a-z0-9]+/', $ascii, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            sort($words);

            return implode(' ', $words);
        };
        $known = [];
        foreach ($wikidataNames as $name) {
            $known[$key($name)] = $name;
        }
        foreach ($architects as &$a) {
            $a['name'] = $known[$key($a['name'])] ?? $a['name'];
        }

        return $architects;
    }

    private static function distance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371000.0;
        $p1 = deg2rad($lat1);
        $p2 = deg2rad($lat2);
        $a = sin(($p2 - $p1) / 2) ** 2 + cos($p1) * cos($p2) * sin(deg2rad($lng2 - $lng1) / 2) ** 2;

        return 2 * $r * asin(min(1.0, sqrt($a)));
    }
}

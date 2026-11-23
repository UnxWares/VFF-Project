<?php

declare(strict_types=1);

namespace App\Services\Heritage;

use App\Services\Sncf\StationAttendanceMatcher;

/**
 * Rapproche les notices Mérimée d'ouvrages d'art (viaduc, pont, tunnel : MerimeeSource::structureRecords) des ouvrages de VFF
 * (table `structures`) et compose les lignes de `structure_heritage`. Sans base ni réseau : les ouvrages et les notices sont donnés.
 *
 * Chaque ouvrage à moins de 1,5 km de la notice (écart diminué de la moitié de sa longueur : un viaduc de 500 m a son milieu à
 * 250 m de la notice sans que rien ne cloche) reçoit des points, tous expliqués :
 *   - la référence Mérimée de l'ouvrage est celle de la notice : rattachement sûr, 100 points ;
 *   - genre : même genre 15 points ; viaduc et pont, 8 ; tunnel et viaduc ou pont : écarté ;
 *   - nom : le nom de la notice (sans « viaduc de », « pont de »…) égal à celui de l'ouvrage 45, contenu dans l'autre 35, un mot
 *     significatif commun 20 ; deux noms sans aucun mot commun : moins 30 ;
 *   - écart : 100 m 30 points, 300 m 25, 700 m 15, 1,5 km 5 ;
 *   - chemin de fer : ouvrage porté par une ligne de VFF 15, sinon notice qui parle de chemin de fer 10 ; sans l'un ni l'autre,
 *     la notice (un pont de route) est écartée.
 * Le meilleur ouvrage est gardé s'il atteint MIN_SCORE et devance le suivant d'au moins MIN_MARGIN. Seule la meilleure
 * notice d'un genre de protection par ouvrage est écrite ; deux notices pour un même ouvrage (Rouzat : deux références) sont
 * toutes deux gardées, la plus protégée sert à l'affichage.
 */
final class StructureHeritageMatcher
{
    public const MIN_SCORE = 70;

    public const MIN_MARGIN = 15;

    public const RADIUS_M = 1500;

    private const STRENGTH = ['classified' => 4, 'registered' => 3, 'acr' => 2];

    /** Mots qui ne distinguent pas un ouvrage d'un autre. */
    private const GENERIC = ['viaduc', 'pont', 'tunnel', 'passerelle', 'de', 'du', 'des', 'la', 'le', 'les', 'sur', 'dit', 'dite', 'ou', 'et', 'ferroviaire', 'ancien', 'ancienne', 'chemin', 'fer', 'ligne', 'sncf', 'grand', 'petit', 'vieux', 'vieille', 'neuf', 'nouveau', 'en', 'au', 'aux', 'lieu', 'commune', 'egalement', 'sous', 'dans', 'rivière', 'riviere', 'ruisseau', 'fleuve', 'cours', 'eau'];

    /** @var list<object> */
    private array $structures;

    /**
     * @param  iterable<object>  $structures  `id`, `kind`, `name`, `lat`, `lon`, `length_m`, `merimee_ref`, `on_line` (porté par une ligne : bool)
     */
    public function __construct(iterable $structures)
    {
        $this->structures = [];
        foreach ($structures as $s) {
            $this->structures[] = $s;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $records  notices d'ouvrage d'art (MerimeeSource::structureRecords)
     * @return array{rows: list<array<string, mixed>>, unmatched: list<array<string, mixed>>, scored: list<array<string, mixed>>}
     */
    public function build(array $records, ?string $merimeeUpdated = null): array
    {
        $byRef = [];
        foreach ($this->structures as $s) {
            if (! empty($s->merimee_ref)) {
                $byRef[$s->merimee_ref][] = $s;
            }
        }
        $rows = [];
        $unmatched = [];
        $scored = [];
        foreach ($records as $record) {
            $found = isset($byRef[$record['ref']])
                ? ['structure' => $byRef[$record['ref']][0], 'score' => 100, 'distance' => null, 'reasons' => ['référence Mérimée déjà portée par l\'ouvrage +100']]
                : $this->match($record);
            if ($found === null) {
                continue;
            }
            if (isset($found['why'])) {
                $unmatched[] = $record + ['why' => $found['why']];

                continue;
            }
            $row = self::row($found['structure'], $record, $found['score'], $found['distance'], $merimeeUpdated);
            $rows[$found['structure']->id.'|'.$record['ref']] = $row;
            $scored[] = ['record' => $record, 'structure' => $found['structure'], 'score' => $found['score'], 'distance' => $found['distance'], 'reasons' => $found['reasons']];
        }

        return ['rows' => array_values($rows), 'unmatched' => $unmatched, 'scored' => $scored];
    }

    /**
     * Meilleur ouvrage d'une notice, ou null quand la notice n'est pas ferroviaire (aucune preuve), ou `['why' => …]` quand elle l'est
     * sans ouvrage sûr.
     *
     * @param  array<string, mixed>  $record
     * @return array{structure: object, score: int, distance: ?int, reasons: list<string>, name?: int}|array{why: string}|null
     */
    public function match(array $record): ?array
    {
        if ($record['lat'] === null || $record['lng'] === null) {
            return $record['rail_text'] ? ['why' => 'no-point'] : null;
        }
        if (self::predatesRailways($record)) {
            return null;
        }
        $core = self::core((string) $record['title']);
        $candidates = [];
        $anyRail = (bool) $record['rail_text'];
        foreach ($this->structures as $s) {
            if (($record['kind'] === 'tunnel') !== ($s->kind === 'tunnel')) {
                continue;
            }
            $gap = self::distance($record['lat'], $record['lng'], (float) $s->lat, (float) $s->lon);
            $effective = max(0.0, $gap - min(7000.0, ((float) ($s->length_m ?? 0)) / 2));
            if ($effective > self::RADIUS_M) {
                continue;
            }
            $score = 0;
            $reasons = [];
            $score += $record['kind'] === $s->kind ? 15 : 8;
            $reasons[] = ($record['kind'] === $s->kind ? 'même genre +15' : 'viaduc ou pont +8');
            $nameScore = self::nameScore($core, (string) ($s->name ?? ''));
            $score += $nameScore;
            if ($nameScore !== 0) {
                $reasons[] = sprintf('nom %s%d', $nameScore > 0 ? '+' : '', $nameScore);
            }
            $points = $effective <= 100 ? 30 : ($effective <= 300 ? 25 : ($effective <= 700 ? 15 : 5));
            $score += $points;
            $reasons[] = sprintf('écart %d m +%d', (int) round($effective), $points);
            if (! empty($s->on_line)) {
                $score += 15;
                $reasons[] = 'porté par une ligne +15';
            } elseif ($record['rail_text']) {
                $score += 10;
                $reasons[] = 'notice ferroviaire +10';
            } else {
                continue;
            }
            $candidates[] = ['structure' => $s, 'score' => $score, 'distance' => (int) round($effective), 'reasons' => $reasons, 'name' => $nameScore];
        }
        if ($candidates === []) {
            return $anyRail ? ['why' => 'no-structure'] : null;
        }
        usort($candidates, fn (array $a, array $b): int => $b['score'] <=> $a['score'] ?: $a['distance'] <=> $b['distance']);
        if ($candidates[0]['score'] < self::MIN_SCORE) {
            return ['why' => 'low-score'];
        }
        if (isset($candidates[1]) && $candidates[0]['score'] - $candidates[1]['score'] < self::MIN_MARGIN) {
            // Deux morceaux du même ouvrage (même nom, à moins de 250 m l'un de l'autre) : le meilleur porte la notice.
            $a = $candidates[0];
            $b = $candidates[1];
            $same = $a['name'] >= 35 && $b['name'] >= 35
                && self::distance((float) $a['structure']->lat, (float) $a['structure']->lon, (float) $b['structure']->lat, (float) $b['structure']->lon) <= 250;
            if (! $same || (isset($candidates[2]) && $a['score'] - $candidates[2]['score'] < self::MIN_MARGIN
                && ! ($candidates[2]['name'] >= 35))) {
                return ['why' => 'ambiguous'];
            }
        }

        return $candidates[0];
    }

    /**
     * Pont construit avant 1820 (années de la notice, ou siècle 18e et avant) et qui ne dit pas « viaduc » ni « ferroviaire » : un pont de
     * pierre ancien, pas un ouvrage de chemin de fer (le texte de la notice ne parle de gare ou de train que pour le situer).
     *
     * @param  array<string, mixed>  $record
     */
    public static function predatesRailways(array $record): bool
    {
        if ($record['kind'] === 'tunnel' || preg_match('/viaduc|ferroviaire|chemin de fer|SNCF/iu', (string) $record['title']) === 1) {
            return false;
        }
        if ($record['built_from'] !== null) {
            return $record['built_from'] < 1820;
        }

        return preg_match('/^(?:\d+(?:er|e)? (?:quart|moiti[ée]|tiers)? ?)?(\d{1,2})(?:er|e) si[èe]cle/u', (string) $record['built_period'], $m) === 1 && (int) $m[1] <= 18;
    }

    /**
     * Mots significatifs d'un titre, sans la partie « (également sur commune de …) » ni les mots génériques.
     *
     * @return list<string>
     */
    public static function core(string $title): array
    {
        $title = (string) preg_replace('/\(.*?\)/u', ' ', $title);
        $title = (string) preg_replace('/\s+(?:ou|dit|dite|dits|dites)\s+.*$/iu', '', $title) ?: $title;
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', mb_strtolower($title)) ?: mb_strtolower($title);
        $words = preg_split('/[^a-z0-9]+/', $ascii, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $generic = array_map(fn (string $w): string => StationAttendanceMatcher::normalize($w), self::GENERIC);

        return array_values(array_filter($words, fn (string $w): bool => strlen($w) >= 3 && ! in_array($w, $generic, true) && ! ctype_digit($w)));
    }

    /**
     * @param  list<string>  $core  mots significatifs de la notice
     */
    public static function nameScore(array $core, string $name): int
    {
        $other = self::core($name);
        if ($core === [] || $other === []) {
            return 0;
        }
        $common = 0;
        foreach ($core as $w) {
            foreach ($other as $o) {
                if (self::sameWord($w, $o)) {
                    $common++;

                    break;
                }
            }
        }
        if ($common === 0) {
            return -30;
        }
        if ($common === count($core) && $common === count($other)) {
            return 45;
        }

        return $common === count($core) || $common === count($other) ? 35 : 20;
    }

    /** Même mot, au pluriel ou à une lettre près pour les mots de 6 lettres et plus (« Barajols », « Barajol »). */
    private static function sameWord(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }
        if (min(strlen($a), strlen($b)) >= 6) {
            return levenshtein($a, $b) <= 1;
        }

        return min(strlen($a), strlen($b)) >= 4 && in_array(substr($a, -1) === 's' || substr($a, -1) === 'x' ? substr($a, 0, -1) : $a, [$b, substr($b, 0, -1)], true);
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private static function row(object $structure, array $record, int $score, ?int $distance, ?string $merimeeUpdated): array
    {
        $designations = [];
        foreach ($record['designations'] as $d) {
            $designations[$d['kind']] = $d;
        }
        uasort($designations, fn (array $a, array $b): int => self::STRENGTH[$b['kind']] <=> self::STRENGTH[$a['kind']]);
        $primary = reset($designations);

        return [
            'structure_id' => (int) $structure->id,
            'protection' => $primary['kind'],
            'protection_date' => $primary['date'],
            'scope' => ($record['protection'] ?? null) === $primary['kind'] ? ($record['scope'] ?? null) : null,
            'designations' => array_values($designations),
            'title' => $record['title'] !== '' ? $record['title'] : null,
            'denomination' => $record['denomination'] !== '' ? $record['denomination'] : null,
            'architects' => $record['architects'],
            'built_from' => $record['built_from'],
            'built_to' => $record['built_to'],
            'built_period' => $record['built_period'],
            'ref' => $record['ref'],
            'ref_url' => MerimeeSource::NOTICE_URL.$record['ref'],
            'match_score' => $score,
            'match_distance_m' => $distance,
            'source' => 'merimee',
            'source_updated' => $merimeeUpdated,
        ];
    }

    private static function distance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $p1 = deg2rad($lat1);
        $p2 = deg2rad($lat2);
        $a = sin(($p2 - $p1) / 2) ** 2 + cos($p1) * cos($p2) * sin(deg2rad($lng2 - $lng1) / 2) ** 2;

        return 2 * 6371000.0 * asin(min(1.0, sqrt($a)));
    }
}

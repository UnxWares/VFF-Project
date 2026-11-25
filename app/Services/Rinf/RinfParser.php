<?php

namespace App\Services\Rinf;

/**
 * Lecture des réponses SPARQL du RINF (RinfQueries) en lignes des tables rinf_* : fonctions pures, testées sur des
 * extraits réels (tests/Fixtures/rinf). Une propriété multiple (plusieurs coordonnées d'une extrémité, plusieurs
 * systèmes de ligne de contact) donne plusieurs lignes de résultat : elles sont réunies ici, objet par objet.
 */
final class RinfParser
{
    private const CONCEPT = 'http://data.europa.eu/949/concepts/';

    private const GRAPH = 'http://data.europa.eu/949/graph/';

    /** Hauteur (mm) des concepts platform-heights du RINF (libellés du vocabulaire de l'ERA, lus le 24/11/2026). */
    public const PLATFORM_HEIGHTS = [
        0 => 0, 10 => 250, 20 => 280, 30 => 550, 40 => 760, 50 => 150, 60 => 200, 70 => 580, 80 => 680, 90 => 685,
        100 => 730, 110 => 840, 120 => 900, 130 => 915, 140 => 920, 150 => 960, 160 => 1100, 220 => 220, 300 => 300,
        350 => 350, 380 => 380, 1030 => 1030,
    ];

    /** Rayon déclaré à 99 999 m ou plus : alignement droit, pas de rayon. */
    private const STRAIGHT_RADIUS = 99999;

    /**
     * Sections de ligne, par URI canonique.
     *
     * @param  list<array<string, array{type: string, value: string}>>  $rows
     * @return array<string, array<string, mixed>>
     */
    public static function sections(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $uri = self::v($r, 'uri');
            $lineId = (string) self::v($r, 'lineId');
            if ($uri === null || $lineId === '') {
                continue;
            }
            $start = self::pk($r, 'skm', 'soff', 'slrs', $lineId);
            $end = self::pk($r, 'ekm', 'eoff', 'elrs', $lineId);
            if (isset($out[$uri])) {
                // Une extrémité à plusieurs coordonnées : celle de la ligne de la section l'emporte.
                $out[$uri]['pk_start_m'] = self::better($out[$uri]['pk_start_m'], $out[$uri]['_start_own'], $start);
                $out[$uri]['_start_own'] = $out[$uri]['_start_own'] || ($start['own'] ?? false);
                $out[$uri]['pk_end_m'] = self::better($out[$uri]['pk_end_m'], $out[$uri]['_end_own'], $end);
                $out[$uri]['_end_own'] = $out[$uri]['_end_own'] || ($end['own'] ?? false);

                continue;
            }
            $len = self::v($r, 'len');
            $out[$uri] = [
                'uri' => $uri, 'national_line' => mb_substr($lineId, 0, 20), 'line_code' => self::lineCode($lineId),
                'op_start_uopid' => (string) self::v($r, 'opS'), 'op_end_uopid' => (string) self::v($r, 'opE'),
                'pk_start_m' => $start['m'] ?? null, 'pk_end_m' => $end['m'] ?? null,
                'length_m' => $len === null ? null : (int) round((float) $len * 1000),
                'nature' => self::code(self::v($r, 'nature')) === 20 ? 'link' : 'regular',
                'dataset' => self::graph($r),
                '_start_own' => $start['own'] ?? false, '_end_own' => $end['own'] ?? false,
            ];
        }

        return array_map(fn (array $s): array => array_diff_key($s, ['_start_own' => 1, '_end_own' => 1]), $out);
    }

    /**
     * Voies des sections, par URI de voie.
     *
     * @param  list<array<string, array{type: string, value: string}>>  $rows
     * @return array<string, array<string, mixed>>
     */
    public static function sectionTracks(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $t = self::v($r, 't');
            if ($t === null) {
                continue;
            }
            $electrified = self::electrified(self::v($r, 'cls'));
            if (isset($out[$t])) {
                $out[$t]['electrified'] = self::orElectrified($out[$t]['electrified'], $electrified);

                continue;
            }
            $radius = self::v($r, 'radius');
            $out[$t] = [
                'uri' => $t, 'parent' => 'section', 'section_uri' => self::v($r, 'uri'), 'op_uopid' => null, 'rinf_class' => 'running',
                'ref' => mb_substr((string) self::v($r, 'trackId'), 0, 160),
                'direction' => match (self::code(self::v($r, 'dir'))) {
                    10 => 'N', 20 => 'O', 30 => 'B', default => null,
                },
                'max_speed_kmh' => self::int($r, 'vmax'),
                'length_m' => null, 'gradient_permille' => null, 'gradient_profile' => null,
                'min_radius_m' => $radius === null || (int) $radius >= self::STRAIGHT_RADIUS ? null : (int) $radius,
                'electrified' => $electrified,
            ];
        }

        return $out;
    }

    /**
     * Voies et voies de service des points d'exploitation, par URI.
     *
     * @param  list<array<string, array{type: string, value: string}>>  $rows
     * @return array<string, array<string, mixed>>
     */
    public static function opTracks(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $t = self::v($r, 't');
            if ($t === null) {
                continue;
            }
            $electrified = self::electrified(self::v($r, 'ctype'));
            if (isset($out[$t])) {
                $out[$t]['electrified'] = self::orElectrified($out[$t]['electrified'], $electrified);

                continue;
            }
            $len = self::v($r, 'len');
            $grad = self::v($r, 'grad');
            $out[$t] = [
                'uri' => $t, 'parent' => 'op', 'section_uri' => null, 'op_uopid' => (string) self::v($r, 'uopid'),
                'rinf_class' => self::v($r, 'cls') === 'siding' ? 'siding' : 'running',
                'ref' => mb_substr(trim((string) self::v($r, 'ref')), 0, 160), 'direction' => null,
                'max_speed_kmh' => self::int($r, 'vmax'),
                'length_m' => $len === null ? null : (int) round((float) $len),
                'gradient_permille' => $grad === null ? null : (float) $grad, 'gradient_profile' => null,
                'min_radius_m' => null, 'electrified' => $electrified,
            ];
        }

        return $out;
    }

    /**
     * Points d'exploitation, par UOPID (celui du graphe de SNCF Réseau d'abord quand deux gestionnaires le décrivent).
     *
     * @param  list<array<string, array{type: string, value: string}>>  $rows
     * @return array<string, array<string, mixed>>
     */
    public static function operationalPoints(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $uopid = self::v($r, 'uopid');
            if ($uopid === null) {
                continue;
            }
            $graph = self::graph($r);
            if (isset($out[$uopid]) && ! str_starts_with($graph, '0087')) {
                continue;
            }
            $type = self::code(self::v($r, 'type'));
            $name = trim((string) self::v($r, 'name'));
            $lat = self::v($r, 'lat');
            $lon = self::v($r, 'lon');
            $out[$uopid] = [
                'uopid' => mb_substr($uopid, 0, 20), 'name' => mb_substr($name, 0, 160), 'op_type' => $type,
                'kind' => RinfDataset::opKind($type, $name),
                'lat' => $lat === null ? null : (float) $lat, 'lon' => $lon === null ? null : (float) $lon,
                'uri' => (string) self::v($r, 'op'), 'dataset' => $graph,
            ];
        }

        return $out;
    }

    /**
     * Bords de quai, par URI.
     *
     * @param  list<array<string, array{type: string, value: string}>>  $rows
     * @return array<string, array<string, mixed>>
     */
    public static function platforms(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $pe = self::v($r, 'pe');
            if ($pe === null || isset($out[$pe])) {
                continue;
            }
            $len = self::v($r, 'len');
            $height = self::code(self::v($r, 'h'));
            $out[$pe] = [
                'uri' => $pe, 'track_uri' => (string) self::v($r, 't'), 'op_uopid' => self::v($r, 'uopid'),
                'ref' => mb_substr(trim((string) self::v($r, 'ref')), 0, 120),
                'length_m' => $len === null ? null : (int) round((float) $len),
                'height_mm' => $height === null ? null : (self::PLATFORM_HEIGHTS[$height] ?? null),
            ];
        }

        return $out;
    }

    /**
     * Profils de déclivités déclarés, par URI de voie (« +04.0(+18.300)|+02.2 »).
     *
     * @param  list<array<string, array{type: string, value: string}>>  $rows
     * @return array<string, string>
     */
    public static function gradientProfiles(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $t = self::v($r, 't');
            $p = self::v($r, 'profile');
            if ($t !== null && $p !== null && $p !== '') {
                $out[$t] = $p;
            }
        }

        return $out;
    }

    /**
     * Portions de déclivité d'une voie de section, dans l'ordre des PK croissants.
     *
     * Le RINF donne une valeur sans position et des valeurs « pente(+km) ». Lecture retenue, la seule cohérente entre
     * les voies 1 et 2 d'une même section (Paris - Bordeaux, PK 18+272 à 19+038 : voie 1 « +02.2 ; +04.0(+18.300) ;
     * +00.0(+18.855) », voie 2 « +04.0 ; +02.9(+18.855) ; +00.0(+18.300) ») : dans le sens de marche de la voie,
     * chaque valeur positionnée vaut JUSQU'AU PK indiqué, depuis le changement précédent ; la valeur sans position
     * vaut après le dernier changement, jusqu'au bout de la section. Le signe est gardé tel quel (même signe sur les
     * deux voies au même endroit : il est donné dans le sens des PK croissants). Résultat `derived`.
     *
     * @return list<array{pk_from_m: int, pk_to_m: int, permille: float}>
     */
    public static function gradientSegments(string $profile, int $from, int $to, bool $runsUp): array
    {
        [$from, $to] = [min($from, $to), max($from, $to)];
        $changes = [];
        $last = null;
        foreach (explode('|', $profile) as $value) {
            if (preg_match('/^\s*([+-]?\d+(?:[.,]\d+)?)\s*(?:\(\s*([+-]?\d+(?:[.,]\d+)?)\s*\))?\s*$/', $value, $m) !== 1) {
                continue;
            }
            $permille = (float) str_replace(',', '.', $m[1]);
            if (($m[2] ?? '') === '') {
                $last = $permille;
            } else {
                $changes[] = [(int) round((float) str_replace(',', '.', $m[2]) * 1000), $permille];
            }
        }
        usort($changes, fn (array $a, array $b): int => $runsUp ? $a[0] <=> $b[0] : $b[0] <=> $a[0]);

        $out = [];
        $cursor = $runsUp ? $from : $to;
        foreach ($changes as [$at, $permille]) {
            $at = max($from, min($to, $at));
            $a = min($cursor, $at);
            $b = max($cursor, $at);
            if ($b > $a) {
                $out[] = ['pk_from_m' => $a, 'pk_to_m' => $b, 'permille' => $permille];
            }
            $cursor = $at;
        }
        if ($last !== null) {
            $a = $runsUp ? $cursor : $from;
            $b = $runsUp ? $to : $cursor;
            if ($b > $a) {
                $out[] = ['pk_from_m' => $a, 'pk_to_m' => $b, 'permille' => $last];
            }
        }
        usort($out, fn (array $x, array $y): int => $x['pk_from_m'] <=> $y['pk_from_m']);

        return $out;
    }

    /** Code de ligne SNCF Réseau d'une ligne nationale RINF (« 579000-1 » : 579000). */
    public static function lineCode(string $lineId): string
    {
        return preg_match('/^(\d{6})(?:-\d+)?$/', trim($lineId), $m) === 1 ? $m[1] : mb_substr(trim($lineId), 0, 16);
    }

    /** @param  array<string, array{type: string, value: string}>  $r */
    private static function v(array $r, string $key): ?string
    {
        return isset($r[$key]['value']) ? (string) $r[$key]['value'] : null;
    }

    /** @param  array<string, array{type: string, value: string}>  $r */
    private static function int(array $r, string $key): ?int
    {
        $v = self::v($r, $key);

        return $v === null || ! is_numeric($v) ? null : (int) round((float) $v);
    }

    /** Numéro d'un concept (« …/concepts/op-types/10 » : 10). */
    private static function code(?string $concept): ?int
    {
        if ($concept === null || ! str_starts_with($concept, self::CONCEPT) || preg_match('~/(\d+)$~', $concept, $m) !== 1) {
            return null;
        }

        return (int) $m[1];
    }

    /** @param  array<string, array{type: string, value: string}>  $r */
    private static function graph(array $r): string
    {
        $g = (string) self::v($r, 'g');

        return str_starts_with($g, self::GRAPH) ? substr($g, strlen(self::GRAPH)) : '0087';
    }

    /**
     * PK d'une extrémité : poteau kilométrique et décalage ; `own` vrai si la coordonnée est sur la ligne de la section.
     *
     * @param  array<string, array{type: string, value: string}>  $r
     * @return array{m: int, own: bool}|null
     */
    private static function pk(array $r, string $km, string $off, string $lrs, string $lineId): ?array
    {
        $k = self::v($r, $km);
        $o = self::v($r, $off);
        if ($k === null || $o === null) {
            return null;
        }

        return ['m' => RinfDataset::pkMeters((float) $k, (float) $o), 'own' => self::v($r, $lrs) === null || self::v($r, $lrs) === $lineId];
    }

    /** @param  array{m: int, own: bool}|null  $candidate */
    private static function better(?int $current, bool $currentOwn, ?array $candidate): ?int
    {
        if ($candidate === null) {
            return $current;
        }
        if ($current === null || (! $currentOwn && $candidate['own'])) {
            return $candidate['m'];
        }

        return $current;
    }

    /** Ligne de contact : 10 (caténaire), 20 (troisième rail), 30 (quatrième rail) électrifiée ; 40 non. */
    private static function electrified(?string $concept): ?bool
    {
        return match (self::code($concept)) {
            10, 20, 30 => true, 40 => false, default => null,
        };
    }

    private static function orElectrified(?bool $a, ?bool $b): ?bool
    {
        return $a === true || $b === true ? true : ($a ?? $b);
    }
}

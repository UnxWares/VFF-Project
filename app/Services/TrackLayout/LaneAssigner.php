<?php

namespace App\Services\TrackLayout;

/**
 * Rangées (`lane`) du schéma des voies, calculées par le serveur.
 *
 * - Voies principales : 0 pour la voie 1 et la voie unique, 1 pour la voie 2 (références « V1 », « Voie V1 »,
 *   « 1 », « Voie UNIQUE… », « VU »…). Une liaison (`crossover`) porte la rangée de la voie principale la plus
 *   basse (0) : elle rejoint la rangée voisine.
 * - Autres voies (voies de gare, d'évitement, de service, troisième voie) : du côté de la voie 1 (rangées -1,
 *   -2… au-dessus) quand leur numéro est impair, du côté de la voie 2 (rangées 2, 3…, ou 1, 2… sur une ligne
 *   sans voie 2) quand il est pair, comme la numérotation des voies de SNCF Réseau ; sans numéro, du côté le
 *   moins chargé à cet endroit. Sur chaque côté, la voie prend la rangée libre la plus proche des voies
 *   principales sur toute sa longueur (marge GAP_M) : deux voies de service ne se chevauchent jamais et une
 *   voie n'en enjambe pas une autre sans raison.
 */
final class LaneAssigner
{
    /** Écart minimal (m) entre deux voies d'une même rangée. */
    public const GAP_M = 40;

    /**
     * @param  list<array<string, mixed>>  $tracks  voies au format du contrat (pk_from_m, pk_to_m, ref, kind)
     * @return list<array<string, mixed>> les mêmes voies, `lane` rempli
     */
    public static function assign(array $tracks): array
    {
        $main = [];
        foreach ($tracks as $i => $t) {
            $lane = self::mainLane($t);
            if ($lane !== null) {
                $main[$i] = $lane;
            }
        }
        $hasSecond = in_array(1, $main, true);
        $evenBase = $hasSecond ? 2 : 1;

        $others = array_values(array_diff(array_keys($tracks), array_keys($main)));
        usort($others, fn (int $a, int $b): int => [$tracks[$a]['pk_from_m'], $tracks[$a]['pk_to_m'], (string) $tracks[$a]['id']]
            <=> [$tracks[$b]['pk_from_m'], $tracks[$b]['pk_to_m'], (string) $tracks[$b]['id']]);

        /** @var array{odd: array<int, list<array{0: int, 1: int}>>, even: array<int, list<array{0: int, 1: int}>>} $slots */
        $slots = ['odd' => [], 'even' => []];
        $assigned = [];
        foreach ($others as $i) {
            $from = (int) $tracks[$i]['pk_from_m'];
            $to = (int) $tracks[$i]['pk_to_m'];
            $side = self::side($tracks[$i]) ?? self::lighterSide($slots, $from, $to);
            $k = 1;
            while (self::busy($slots[$side][$k] ?? [], $from, $to)) {
                $k++;
            }
            $slots[$side][$k][] = [$from, $to];
            $assigned[$i] = $side === 'odd' ? -$k : $evenBase + $k - 1;
        }

        $out = [];
        foreach ($tracks as $i => $t) {
            $t['lane'] = $main[$i] ?? $assigned[$i] ?? 0;
            $out[] = $t;
        }

        return $out;
    }

    /** Rangée d'une voie principale (0 voie 1 ou voie unique, 1 voie 2), null pour une autre voie. */
    public static function mainLane(array $track): ?int
    {
        $kind = (string) ($track['kind'] ?? '');
        if ($kind === 'crossover') {
            return 0;
        }
        if ($kind !== 'running' || ($track['op_id'] ?? null) !== null) {
            return null;
        }
        $ref = self::normalize((string) ($track['ref'] ?? ''));

        return match (true) {
            preg_match('/^(VOIE)?V?(1|I)$/', $ref) === 1 => 0,
            preg_match('/^(VOIE)?V?(2|II)$/', $ref) === 1 => 1,
            str_starts_with($ref, 'VOIEUNIQUE'), $ref === 'VU', $ref === 'UNIQUE' => 0,
            default => null,
        };
    }

    /** Côté d'une voie d'après la parité de son numéro : odd (voie 1), even (voie 2), null sans numéro. */
    public static function side(array $track): ?string
    {
        if (preg_match('/\d+/', (string) ($track['ref'] ?? ''), $m) !== 1) {
            return null;
        }

        return ((int) $m[0]) % 2 === 1 ? 'odd' : 'even';
    }

    private static function normalize(string $ref): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $ref));
    }

    /** @param  list<array{0: int, 1: int}>  $ranges */
    private static function busy(array $ranges, int $from, int $to): bool
    {
        foreach ($ranges as [$a, $b]) {
            if ($from < $b + self::GAP_M && $a < $to + self::GAP_M) {
                return true;
            }
        }

        return false;
    }

    /** @param  array{odd: array<int, list<array{0: int, 1: int}>>, even: array<int, list<array{0: int, 1: int}>>}  $slots */
    private static function lighterSide(array $slots, int $from, int $to): string
    {
        $load = [];
        foreach (['odd', 'even'] as $side) {
            $load[$side] = 0;
            foreach ($slots[$side] as $ranges) {
                $load[$side] += self::busy($ranges, $from, $to) ? 1 : 0;
            }
        }

        return $load['even'] < $load['odd'] ? 'even' : 'odd';
    }
}

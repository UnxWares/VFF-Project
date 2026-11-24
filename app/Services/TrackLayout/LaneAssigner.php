<?php

namespace App\Services\TrackLayout;

/**
 * Rangées (`lane`) du schéma des voies, calculées par le serveur, une seule implémentation pour toutes les sources.
 *
 * - Voies principales (`running`, en ligne ou à la traversée d'une gare) : 0 pour la voie 1 et la voie unique, 1
 *   pour la voie 2 (références « V1 », « Voie V1 », « 1 », « Voie UNIQUE… », « VU », « U »…). Une liaison
 *   (`crossover`) porte la rangée la plus basse des voies qu'elle relie (clé facultative `connects`, identifiants
 *   des voies), sinon celle de la voie principale la plus basse (0) : elle rejoint la rangée voisine.
 * - Voies dont la source donne la géométrie (clé facultative `offset_m` : décalage latéral signé au tracé de
 *   référence, positif à gauche face aux PK croissants, OpenStreetMap) : l'ordre latéral réel décide. La voie 1
 *   est du côté que donnent ses décalages face à la voie 2 (à droite sur les lignes à circulation à droite
 *   d'Alsace et de Moselle), à gauche sans les deux. Les voies principales sans numéro se rangent de la voie 1
 *   vers la voie 2, puis les voies d'évitement et de service, les plus proches de l'axe d'abord, dans la première
 *   rangée libre de leur côté entre leurs voisines, sans croisement inutile. Deux pièces d'une même voie qui se
 *   touchent (à OVERLAP_M près) partagent une rangée.
 * - Autres voies (voies de gare, d'évitement, de service, troisième voie, sans géométrie) : du côté de la voie 1
 *   (rangées -1, -2… au-dessus) quand leur numéro est impair, du côté de la voie 2 (rangées 2, 3…, ou 1, 2… sur
 *   une ligne sans voie 2) quand il est pair, comme la numérotation des voies de SNCF Réseau ; sans numéro, du
 *   côté le moins chargé à cet endroit. Sur chaque côté, la voie prend la rangée libre la plus proche des voies
 *   principales sur toute sa longueur (marge GAP_M) : deux voies de service ne se chevauchent jamais et une
 *   voie n'en enjambe pas une autre sans raison.
 * Un numéro de voie n'est jamais inventé : la rangée ne fait qu'ordonner le dessin. Les clés internes
 * `offset_m` et `connects` sont retirées des voies rendues.
 */
final class LaneAssigner
{
    /** Écart minimal (m) entre deux voies d'une même rangée sans géométrie. */
    public const GAP_M = 40;

    /** Chevauchement (m) sous lequel deux voies placées par leur géométrie peuvent partager une rangée. */
    public const OVERLAP_M = 10;

    /**
     * @param  list<array<string, mixed>>  $tracks  voies au format du contrat (pk_from_m, pk_to_m, ref, kind ; offset_m et connects facultatifs)
     * @return list<array<string, mixed>> les mêmes voies, `lane` rempli
     */
    public static function assign(array $tracks): array
    {
        $sign = self::trackOneSide($tracks);
        $main = [];
        $hasSecond = false;
        foreach ($tracks as $i => $t) {
            $lane = ($t['kind'] ?? null) === 'crossover' ? null : self::mainLane($t);
            if ($lane !== null) {
                $main[$i] = $lane;
                $hasSecond = $hasSecond || $lane === 1;
            }
        }
        $evenBase = $hasSecond ? 2 : 1;

        /** @var array<int, list<array{0: int, 1: int, 2: ?float}>> $occupied rangée => [début, fin, valeur latérale] */
        $occupied = [];
        $lanes = [];
        $place = function (int $i, int $lane) use (&$occupied, &$lanes, $tracks, $sign): void {
            $lanes[$i] = $lane;
            [$from, $to] = self::range($tracks[$i]);
            $occupied[$lane][] = [$from, $to, self::lateral($tracks[$i], $sign)];
        };
        foreach ($main as $i => $lane) {
            $place($i, $lane);
        }

        $rest = array_values(array_diff(array_keys($tracks), array_keys($main)));
        $geometric = array_values(array_filter($rest, fn (int $i): bool => self::lateral($tracks[$i], $sign) !== null && $tracks[$i]['kind'] !== 'crossover'));
        $plain = array_values(array_filter($rest, fn (int $i): bool => self::lateral($tracks[$i], $sign) === null && $tracks[$i]['kind'] !== 'crossover'));
        $crossovers = array_values(array_filter($rest, fn (int $i): bool => $tracks[$i]['kind'] === 'crossover'));

        // Géométrie : voies principales sans numéro de la voie 1 vers la voie 2, puis évitements et voies de
        // service, puis faisceaux et embranchements, les plus proches de l'axe d'abord.
        $rank = fn (array $t): int => match ($t['kind']) {
            'running' => 0,
            'loop', 'siding' => 1,
            default => 2,
        };
        usort($geometric, function (int $a, int $b) use ($tracks, $sign, $rank): int {
            $ta = $tracks[$a];
            $tb = $tracks[$b];
            if ($rank($ta) !== $rank($tb)) {
                return $rank($ta) <=> $rank($tb);
            }
            if ($rank($ta) === 0) {
                return [self::lateral($tb, $sign), (string) $ta['id']] <=> [self::lateral($ta, $sign), (string) $tb['id']];
            }

            return [abs((float) $ta['offset_m']), (string) $ta['id']] <=> [abs((float) $tb['offset_m']), (string) $tb['id']];
        });
        foreach ($geometric as $i) {
            $place($i, self::lateralLane($tracks[$i], (float) self::lateral($tracks[$i], $sign), $occupied));
        }

        usort($plain, fn (int $a, int $b): int => [$tracks[$a]['pk_from_m'], $tracks[$a]['pk_to_m'], (string) $tracks[$a]['id']]
            <=> [$tracks[$b]['pk_from_m'], $tracks[$b]['pk_to_m'], (string) $tracks[$b]['id']]);
        foreach ($plain as $i) {
            [$from, $to] = self::range($tracks[$i]);
            $side = self::side($tracks[$i]) ?? self::lighterSide($occupied, $evenBase, $from, $to);
            $lane = $side === 'odd' ? -1 : $evenBase;
            while (self::busy($occupied[$lane] ?? [], $from, $to, self::GAP_M)) {
                $lane += $side === 'odd' ? -1 : 1;
            }
            $place($i, $lane);
        }

        $ids = [];
        foreach ($tracks as $i => $t) {
            $ids[(string) $t['id']] = $i;
        }
        foreach ($crossovers as $i) {
            $connected = [];
            foreach ($tracks[$i]['connects'] ?? [] as $id) {
                if (isset($ids[(string) $id], $lanes[$ids[(string) $id]])) {
                    $connected[] = $lanes[$ids[(string) $id]];
                }
            }
            $lanes[$i] = $connected === [] ? 0 : min($connected);
        }

        $out = [];
        foreach ($tracks as $i => $t) {
            $t['lane'] = $lanes[$i] ?? 0;
            unset($t['offset_m'], $t['connects']);
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
        if ($kind !== 'running') {
            return null;
        }
        $ref = self::normalize((string) ($track['ref'] ?? ''));

        return match (true) {
            preg_match('/^(VOIE)?V?(1|I)$/', $ref) === 1 => 0,
            preg_match('/^(VOIE)?V?(2|II)$/', $ref) === 1 => 1,
            str_starts_with($ref, 'VOIEUNIQUE'), $ref === 'VU', $ref === 'UNIQUE', $ref === 'U' => 0,
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

    /**
     * +1 quand la voie 1 est à gauche de la voie 2 face aux PK croissants (ou que rien ne dit le contraire), -1
     * quand les décalages des voies 1 et 2 qui se chevauchent la mettent à droite.
     *
     * @param  list<array<string, mixed>>  $tracks
     */
    public static function trackOneSide(array $tracks): int
    {
        $byLane = [0 => [], 1 => []];
        foreach ($tracks as $t) {
            $lane = ($t['kind'] ?? null) === 'running' ? self::mainLane($t) : null;
            $ref = self::normalize((string) ($t['ref'] ?? ''));
            // La voie unique (rangée 0 elle aussi) ne dit rien du côté de la voie 1.
            if ($lane !== null && isset($t['offset_m']) && preg_match('/^(VOIE)?V?(1|I|2|II)$/', $ref) === 1) {
                $byLane[$lane][] = $t;
            }
        }
        $votes = 0;
        foreach ($byLane[0] as $a) {
            foreach ($byLane[1] as $b) {
                $overlap = min((int) $a['pk_to_m'], (int) $b['pk_to_m']) - max((int) $a['pk_from_m'], (int) $b['pk_from_m']);
                if ($overlap > self::OVERLAP_M) {
                    $votes += ((float) $a['offset_m'] <=> (float) $b['offset_m']) * min($overlap, 10_000);
                }
            }
        }

        return $votes < 0 ? -1 : 1;
    }

    /** Valeur latérale : plus grande vers la voie 1 ; null sans géométrie. */
    private static function lateral(array $track, int $sign): ?float
    {
        return isset($track['offset_m']) && is_numeric($track['offset_m']) ? $sign * (float) $track['offset_m'] : null;
    }

    /** @return array{0: int, 1: int} */
    private static function range(array $track): array
    {
        $a = (int) $track['pk_from_m'];
        $b = (int) $track['pk_to_m'];

        return [min($a, $b), max($a, $b)];
    }

    /**
     * Première rangée libre dans l'ordre latéral des voies déjà placées qu'elle chevauche : juste après sa
     * voisine du côté de la voie 1, avant sa voisine du côté de la voie 2 ; serrée entre deux rangées voisines,
     * au-delà de la voie 2.
     *
     * @param  array<int, list<array{0: int, 1: int, 2: ?float}>>  $occupied
     */
    private static function lateralLane(array $track, float $v, array $occupied): int
    {
        [$from, $to] = self::range($track);
        $busy = [];
        $towardsOne = null; // rangée la plus haute des voisines du côté de la voie 1
        $towardsTwo = null; // rangée la plus basse des voisines du côté de la voie 2
        foreach ($occupied as $lane => $spans) {
            foreach ($spans as [$a, $b, $w]) {
                if (min($to, $b) - max($from, $a) <= self::OVERLAP_M) {
                    continue;
                }
                $busy[$lane] = true;
                if ($w !== null && $w > $v) {
                    $towardsOne = $towardsOne === null ? $lane : max($towardsOne, $lane);
                } elseif ($w !== null) {
                    $towardsTwo = $towardsTwo === null ? $lane : min($towardsTwo, $lane);
                }
            }
        }
        if ($busy === []) {
            return 0;
        }
        if ($towardsOne !== null) {
            for ($lane = $towardsOne + 1; $towardsTwo === null || $lane < $towardsTwo; $lane++) {
                if (! isset($busy[$lane])) {
                    return $lane;
                }
            }
        } elseif ($towardsTwo !== null) {
            for ($lane = $towardsTwo - 1; ; $lane--) {
                if (! isset($busy[$lane])) {
                    return $lane;
                }
            }
        }
        for ($lane = max(array_keys($busy)) + 1; isset($busy[$lane]); $lane++);

        return $lane;
    }

    private static function normalize(string $ref): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $ref));
    }

    /** @param  list<array{0: int, 1: int, 2: ?float}>  $ranges */
    private static function busy(array $ranges, int $from, int $to, int $gap): bool
    {
        foreach ($ranges as [$a, $b]) {
            if ($from < $b + $gap && $a < $to + $gap) {
                return true;
            }
        }

        return false;
    }

    /** @param  array<int, list<array{0: int, 1: int, 2: ?float}>>  $occupied */
    private static function lighterSide(array $occupied, int $evenBase, int $from, int $to): string
    {
        $load = ['odd' => 0, 'even' => 0];
        foreach ($occupied as $lane => $ranges) {
            if ($lane < 0 || $lane >= $evenBase) {
                $load[$lane < 0 ? 'odd' : 'even'] += self::busy($ranges, $from, $to, self::GAP_M) ? 1 : 0;
            }
        }

        return $load['even'] < $load['odd'] ? 'even' : 'odd';
    }
}

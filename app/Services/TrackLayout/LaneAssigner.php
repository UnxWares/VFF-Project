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
 * - Les pièces d'une même voie (même source, même numéro, qui se touchent : une voie du RINF coupée à chaque section
 *   de ligne) partagent une rangée, choisie pour toute leur étendue : un seul « V1BIS » sur une seule rangée.
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
        // Une unité (pièces d'une même voie, voir units()) reçoit une seule rangée.
        $place = function (array $unit, int $lane) use (&$occupied, &$lanes): void {
            foreach ($unit['pieces'] as $i) {
                $lanes[$i] = $lane;
            }
            $occupied[$lane][] = [$unit['from'], $unit['to'], $unit['lateral']];
        };
        foreach ($main as $i => $lane) {
            $place(['pieces' => [$i], 'from' => self::range($tracks[$i])[0], 'to' => self::range($tracks[$i])[1],
                'lateral' => self::lateral($tracks[$i], $sign)], $lane);
        }

        $rest = array_values(array_filter(array_diff(array_keys($tracks), array_keys($main)), fn (int $i): bool => $tracks[$i]['kind'] !== 'crossover'));
        $crossovers = array_values(array_filter(array_keys($tracks), fn (int $i): bool => ! isset($main[$i]) && $tracks[$i]['kind'] === 'crossover'));
        $units = self::units($tracks, $rest, $sign);
        $geometric = array_values(array_filter($units, fn (array $u): bool => $u['lateral'] !== null));
        $plain = array_values(array_filter($units, fn (array $u): bool => $u['lateral'] === null));

        // Géométrie : voies principales sans numéro de la voie 1 vers la voie 2, puis évitements et voies de
        // service, puis faisceaux et embranchements, les plus proches de l'axe d'abord.
        $rank = fn (array $t): int => match ($t['kind']) {
            'running' => 0,
            'loop', 'siding' => 1,
            default => 2,
        };
        usort($geometric, function (array $a, array $b) use ($tracks, $rank): int {
            $ta = $tracks[$a['pieces'][0]];
            $tb = $tracks[$b['pieces'][0]];
            if ($rank($ta) !== $rank($tb)) {
                return $rank($ta) <=> $rank($tb);
            }
            if ($rank($ta) === 0) {
                return [$b['lateral'], (string) $ta['id']] <=> [$a['lateral'], (string) $tb['id']];
            }

            return [abs((float) $a['lateral']), (string) $ta['id']] <=> [abs((float) $b['lateral']), (string) $tb['id']];
        });
        foreach ($geometric as $u) {
            $place($u, self::lateralLane(['pk_from_m' => $u['from'], 'pk_to_m' => $u['to']], (float) $u['lateral'], $occupied));
        }

        usort($plain, fn (array $a, array $b): int => [$a['from'], $a['to'], (string) $tracks[$a['pieces'][0]]['id']]
            <=> [$b['from'], $b['to'], (string) $tracks[$b['pieces'][0]]['id']]);
        foreach ($plain as $u) {
            $side = self::side($tracks[$u['pieces'][0]]) ?? self::lighterSide($occupied, $evenBase, $u['from'], $u['to']);
            $lane = $side === 'odd' ? -1 : $evenBase;
            while (self::busy($occupied[$lane] ?? [], $u['from'], $u['to'], self::GAP_M)) {
                $lane += $side === 'odd' ? -1 : 1;
            }
            $place($u, $lane);
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

    /**
     * Unités à ranger : les pièces d'une même voie (même source, même genre, même numéro, qui se touchent ou se
     * recouvrent à OVERLAP_M près, comme les voies d'une ligne du RINF coupées à chaque section) en forment une,
     * rangée d'un bloc ; une voie sans numéro est une unité à elle seule. Valeur latérale : moyenne des pièces qui
     * en ont une, pondérée par leur longueur.
     *
     * @param  list<array<string, mixed>>  $tracks
     * @param  list<int>  $indices
     * @return list<array{pieces: list<int>, from: int, to: int, lateral: ?float}>
     */
    private static function units(array $tracks, array $indices, int $sign): array
    {
        $groups = [];
        $units = [];
        foreach ($indices as $i) {
            $ref = self::normalize((string) ($tracks[$i]['ref'] ?? ''));
            if ($ref === '') {
                $units[] = [$i];

                continue;
            }
            $groups[($tracks[$i]['source'] ?? '').'|'.$tracks[$i]['kind'].'|'.$ref][] = $i;
        }
        foreach ($groups as $members) {
            usort($members, fn (int $a, int $b): int => self::range($tracks[$a]) <=> self::range($tracks[$b]));
            $chain = [];
            $end = null;
            foreach ($members as $i) {
                [$from, $to] = self::range($tracks[$i]);
                if ($chain !== [] && $from > $end + self::OVERLAP_M) {
                    $units[] = $chain;
                    $chain = [];
                }
                $chain[] = $i;
                $end = $chain === [$i] ? $to : max($end, $to);
            }
            $units[] = $chain;
        }

        return array_map(function (array $pieces) use ($tracks, $sign): array {
            $from = min(array_map(fn (int $i): int => self::range($tracks[$i])[0], $pieces));
            $to = max(array_map(fn (int $i): int => self::range($tracks[$i])[1], $pieces));
            $sum = 0.0;
            $weight = 0;
            foreach ($pieces as $i) {
                $v = self::lateral($tracks[$i], $sign);
                if ($v !== null) {
                    $w = max(1, self::range($tracks[$i])[1] - self::range($tracks[$i])[0]);
                    $sum += $v * $w;
                    $weight += $w;
                }
            }

            return ['pieces' => $pieces, 'from' => $from, 'to' => $to, 'lateral' => $weight > 0 ? $sum / $weight : null];
        }, $units);
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

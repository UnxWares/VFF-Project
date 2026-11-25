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
 * - Voies à quai (clé facultative `platform_keys` : les quais que la voie borde, voir platformOrder) : les deux voies
 *   d'un même quai sont voisines, de part et d'autre de lui, et un quai ne longe jamais une voie à travers une autre ;
 *   la voie d'un quai qu'elle partage avec la voie 1 (ou 2) est juste au-delà d'elle ; les voies à quai d'une gare
 *   gardent l'ordre de leurs décalages quand il ne sépare aucun quai, sinon l'ordre de leurs numéros (Paris-Austerlitz :
 *   14, 18, 15, 19, 20, 17, 21 d'après OSM, dont les voies souterraines du RER se superposent aux voies de surface).
 * Un numéro de voie n'est jamais inventé : la rangée ne fait qu'ordonner le dessin. Les clés internes
 * `offset_m`, `connects` et `platform_keys` sont retirées des voies rendues.
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

        self::platformOrder($tracks, $lanes, $evenBase, array_map(fn (array $u): array => $u['pieces'], $units));

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
            unset($t['offset_m'], $t['connects'], $t['platform_keys']);
            $out[] = $t;
        }

        return $out;
    }

    /**
     * Voies à quai rangées autour de leurs quais, une fois toutes les voies placées.
     *
     * - Unité : une voie entière (ses pièces rangées d'un bloc) qui borde un quai, avec les voies de même numéro qui
     *   bordent le même quai (platformUnits).
     * - Chaîne : les unités reliées par un quai qu'elles sont seules à border à deux (quai central) ; une chaîne qui
     *   n'est pas un simple chemin (trois voies pour un quai, boucle) est laissée telle quelle.
     * - Chaîne qui contient la voie 1 ou 2 (rangées fixes) : ses autres voies se rangent juste au-delà d'elle, du côté
     *   opposé à l'autre voie principale. Puis, de chaque côté, les autres chaînes d'un même point d'exploitation,
     *   chacune sur des rangées qui se suivent : à leur place quand l'ordre des décalages ne sépare aucune de leurs
     *   paires (cohérent), sinon les unes après les autres dans l'ordre des numéros (« 14 15 », « 16 17 »…), en
     *   partant des voies principales.
     * Une voie ne change de rangée qu'en prenant une rangée insérée pour elle (les voies au-delà s'écartent d'un cran) :
     * l'ordre de toutes les autres voies entre elles ne change pas et deux voies d'une rangée ne se recouvrent jamais.
     *
     * @param  list<array<string, mixed>>  $tracks
     * @param  array<int, int>  $lanes  rangée de chaque voie, hors liaisons (modifiée)
     * @param  list<list<int>>  $wholes  pièces de chaque voie rangée d'un bloc (units)
     */
    private static function platformOrder(array $tracks, array &$lanes, int $evenBase, array $wholes = []): void
    {
        $units = self::platformUnits($tracks, $lanes, $wholes);
        if ($units === []) {
            return;
        }
        $byKey = [];
        foreach ($units as $u => $unit) {
            foreach ($unit['keys'] as $key) {
                $byKey[$key][] = $u;
            }
        }
        /** @var array<int, array<int, true>> $next unité => unités qui bordent un même quai */
        $next = [];
        foreach ($byKey as $members) {
            $members = array_values(array_unique($members));
            if (count($members) === 2) {
                $next[$members[0]][$members[1]] = true;
                $next[$members[1]][$members[0]] = true;
            }
        }

        // Rangée d'une unité, lue à chaque fois : les déplacements la changent.
        $lane = function (int $u) use (&$lanes, $units): int {
            return $lanes[$units[$u]['pieces'][0]];
        };
        $mean = fn (array $chain): float => array_sum(array_map($lane, $chain)) / count($chain);
        $negative = fn (array $chain): bool => $mean($chain) < ($evenBase - 1) / 2;
        $fixed = ['neg' => [], 'pos' => []];
        $free = [];
        $mainOps = [];
        foreach (self::chains(array_keys($units), $next) as $path) {
            $mains = array_keys(array_filter($path, fn (int $u): bool => $units[$u]['main'] !== null));
            if ($mains === []) {
                $free[$units[$path[0]]['op']][$negative($path) ? 'neg' : 'pos'][] = $path;

                continue;
            }
            if (count($mains) > 2 || (count($mains) === 2 && ($mains[1] - $mains[0] !== 1
                || $units[$path[$mains[0]]]['main'] === $units[$path[$mains[1]]]['main']))) {
                continue;
            }
            $before = array_reverse(array_slice($path, 0, $mains[0]));
            $after = array_slice($path, end($mains) + 1);
            $first = (int) $units[$path[$mains[0]]]['main'];
            if (count($mains) === 2) {
                // Voie 1 et voie 2 voisines par leur quai : chacune garde ce qui est au-delà d'elle.
                [$up, $down] = $first === 0 ? [$before, $after] : [$after, $before];
            } elseif ($before !== [] && $after !== []) {
                [$up, $down] = $mean($before) <= $mean($after) ? [$before, $after] : [$after, $before];
            } elseif ($before === [] && $after === []) {
                // Seule voie à quai, une voie principale (Chirac : le quai longe la voie unique) : rien à ranger au-delà.
                [$up, $down] = [[], []];
            } else {
                // Au-delà de la voie 2, au-delà de la voie 1 ; d'un côté ou de l'autre de la voie unique.
                $part = $before ?: $after;
                $below = $first === 1 || ($evenBase === 1 && ! $negative($part));
                [$up, $down] = $below ? [[], $part] : [$part, []];
            }
            foreach (['neg' => $up, 'pos' => $down] as $side => $segment) {
                if ($segment !== []) {
                    $fixed[$side][] = $segment;
                }
            }
            $mainOps[$units[$path[0]]['op']] = true;
        }
        // Les voies à quai du point qui a les voies principales à quai d'abord (gare de surface avant les voies
        // souterraines du RER à Paris-Austerlitz), puis par point.
        uksort($free, fn (string $a, string $b): int => [isset($mainOps[$b]), $a] <=> [isset($mainOps[$a]), $b]);

        foreach (['neg' => -1, 'pos' => 1] as $side => $dir) {
            // Rang d'une unité de ce côté : 1 sur la rangée la plus proche des voies principales.
            $rank = fn (int $u): int => $dir < 0 ? -$lane($u) : $lane($u) - ($evenBase - 1);
            $row = fn (int $r): int => $dir < 0 ? -$r : $evenBase - 1 + $r;
            $cursor = 0;
            foreach ($fixed[$side] as $segment) {
                foreach ($segment as $u) {
                    self::moveUnit($tracks, $lanes, $units[$u]['pieces'], $row(++$cursor), $dir);
                }
            }
            foreach ($free as $sides) {
                $chains = $sides[$side] ?? [];
                if ($chains === []) {
                    continue;
                }
                $coherent = self::coherent($chains, $rank);
                foreach ($chains as &$chain) {
                    $reverse = $coherent ? $rank(end($chain)) < $rank($chain[0])
                        : self::numberKey($units[end($chain)]['number']) < self::numberKey($units[$chain[0]]['number']);
                    if ($reverse) {
                        $chain = array_reverse($chain);
                    }
                }
                unset($chain);
                usort($chains, fn (array $a, array $b): int => $coherent ? $rank($a[0]) <=> $rank($b[0])
                    : self::numberKey($units[$a[0]]['number']) <=> self::numberKey($units[$b[0]]['number']));
                foreach ($chains as $chain) {
                    $start = $coherent ? max($cursor + 1, $rank($chain[0])) : $cursor + 1;
                    foreach ($chain as $k => $u) {
                        self::moveUnit($tracks, $lanes, $units[$u]['pieces'], $row($start + $k), $dir);
                    }
                    $cursor = $start + count($chain) - 1;
                }
            }
        }
    }

    /**
     * Unités des voies à quai : une voie entière (toutes les pièces que `units` range d'un bloc, une voie du RINF coupée
     * à chaque section par exemple) dont une pièce au moins borde un quai ; les voies de même numéro qui bordent un
     * même quai n'en font qu'une (les clés d'un quai passent à plusieurs pièces d'une voie du RINF quand elle absorbe
     * plusieurs pièces d'OSM), tant qu'elles ne se recouvrent pas de plus de OVERLAP_M. `keys` : les quais qu'elle
     * borde ; `main` : rangée de la voie 1 ou 2 (ou unique) qu'elle est, sinon null ; `op` : le point d'exploitation
     * de ses quais ; `number` : son numéro (ordre des numéros).
     *
     * @param  list<array<string, mixed>>  $tracks
     * @param  array<int, int>  $lanes
     * @param  list<list<int>>  $wholes
     * @return list<array{pieces: list<int>, keys: list<string>, main: ?int, op: string, number: string}>
     */
    private static function platformUnits(array $tracks, array $lanes, array $wholes): array
    {
        $wholeOf = [];
        foreach ($wholes as $w => $pieces) {
            foreach ($pieces as $i) {
                $wholeOf[$i] = $w;
            }
        }
        $keysOf = fn (int $i): array => array_values(array_unique(array_map('strval', (array) ($tracks[$i]['platform_keys'] ?? []))));
        // Voies entières qui bordent un quai, regroupées par numéro et quai partagé.
        $parent = [];
        $find = function (int|string $x) use (&$parent, &$find): int|string {
            return ($parent[$x] ?? $x) === $x ? $x : ($parent[$x] = $find($parent[$x]));
        };
        $owner = [];
        foreach ($tracks as $i => $t) {
            if (($t['kind'] ?? null) === 'crossover' || ! isset($lanes[$i]) || $keysOf($i) === []) {
                continue;
            }
            $whole = isset($wholeOf[$i]) ? 'w'.$wholeOf[$i] : 't'.$i;
            $parent[$whole] ??= $whole;
            foreach ($keysOf($i) as $key) {
                $slot = $key.'#'.TrackLayout::trackNumber((string) ($t['ref'] ?? ''));
                if (isset($owner[$slot])) {
                    $parent[$find($whole)] = $find($owner[$slot]);
                } else {
                    $owner[$slot] = $whole;
                }
            }
        }
        $groups = [];
        foreach (array_keys($parent) as $whole) {
            $groups[$find($whole)][] = $whole;
        }

        $units = [];
        foreach ($groups as $members) {
            $sets = array_map(fn (string $w): array => $w[0] === 'w' ? $wholes[(int) substr($w, 1)] : [(int) substr($w, 1)], $members);
            $span = fn (array $pieces): int => array_sum(array_map(fn (int $i): int => self::range($tracks[$i])[1] - self::range($tracks[$i])[0], $pieces));
            usort($sets, fn (array $a, array $b): int => $span($b) <=> $span($a));
            $pieces = [];
            foreach ($sets as $set) {
                foreach ($set as $i) {
                    foreach ($pieces as $p) {
                        if (min(self::range($tracks[$i])[1], self::range($tracks[$p])[1]) - max(self::range($tracks[$i])[0], self::range($tracks[$p])[0]) > self::OVERLAP_M) {
                            continue 3;
                        }
                    }
                }
                $pieces = [...$pieces, ...$set];
            }
            $keys = array_values(array_unique(array_merge(...array_map($keysOf, $pieces))));
            sort($keys);
            $mains = array_values(array_filter(array_map(fn (int $i): ?int => self::mainLane($tracks[$i]), $pieces), fn (?int $l): bool => $l !== null));
            $units[] = ['pieces' => $pieces, 'keys' => $keys, 'main' => $mains[0] ?? null, 'op' => explode('|', $keys[0])[0],
                'number' => TrackLayout::trackNumber((string) ($tracks[$pieces[0]]['ref'] ?? ''))];
        }

        return $units;
    }

    /**
     * Chaînes d'unités reliées par leurs quais, chacune dans l'ordre du chemin (d'un bout à l'autre) ; une composante
     * qui n'est pas un chemin (une unité a trois voisines, ou une boucle) n'est pas rendue.
     *
     * @param  list<int>  $all
     * @param  array<int, array<int, true>>  $next
     * @return list<list<int>>
     */
    private static function chains(array $all, array $next): array
    {
        $seen = [];
        $out = [];
        foreach ($all as $start) {
            if (isset($seen[$start])) {
                continue;
            }
            $component = [];
            $stack = [$start];
            $seen[$start] = true;
            while ($stack !== []) {
                $u = array_pop($stack);
                $component[] = $u;
                foreach (array_keys($next[$u] ?? []) as $v) {
                    if (! isset($seen[$v])) {
                        $seen[$v] = true;
                        $stack[] = $v;
                    }
                }
            }
            $degrees = array_map(fn (int $u): int => count($next[$u] ?? []), $component);
            if (max($degrees) > 2 || array_sum($degrees) / 2 !== count($component) - 1) {
                continue;
            }
            sort($component);
            $end = array_values(array_filter($component, fn (int $u): bool => count($next[$u] ?? []) <= 1))[0];
            $path = [$end];
            while (count($path) < count($component)) {
                $last = end($path);
                $prev = $path[count($path) - 2] ?? null;
                foreach (array_keys($next[$last]) as $v) {
                    if ($v !== $prev) {
                        $path[] = $v;
                        break;
                    }
                }
            }
            $out[] = $path;
        }

        return $out;
    }

    /**
     * L'ordre des décalages convient quand chaque chaîne y occupe des rangs qui se suivent (aucune voie à quai d'une
     * autre chaîne entre deux voies d'un même quai).
     *
     * @param  list<list<int>>  $chains
     * @param  callable(int): int  $rank
     */
    private static function coherent(array $chains, callable $rank): bool
    {
        $all = [];
        foreach ($chains as $c => $chain) {
            foreach ($chain as $u) {
                $all[] = [$rank($u), $c];
            }
        }
        sort($all);
        $done = [];
        $current = null;
        foreach ($all as [, $c]) {
            if ($c !== $current) {
                if (isset($done[$c])) {
                    return false;
                }
                $done[$c] = true;
                $current = $c;
            }
        }

        return true;
    }

    /**
     * Les pièces d'une unité sur une rangée : déjà toutes sur elle, rien ne change ; sinon une rangée vide est insérée
     * à cette place (les voies sur elle ou au-delà, de ce côté, s'écartent d'un cran) et l'unité la prend.
     *
     * @param  list<array<string, mixed>>  $tracks
     * @param  array<int, int>  $lanes
     * @param  list<int>  $pieces
     */
    private static function moveUnit(array $tracks, array &$lanes, array $pieces, int $row, int $dir): void
    {
        if (array_filter($pieces, fn (int $i): bool => $lanes[$i] !== $row) === []) {
            return;
        }
        foreach ($lanes as $i => $l) {
            if (($tracks[$i]['kind'] ?? null) !== 'crossover' && ($dir > 0 ? $l >= $row : $l <= $row)) {
                $lanes[$i] = $l + $dir;
            }
        }
        foreach ($pieces as $i) {
            $lanes[$i] = $row;
        }
    }

    /**
     * Clé d'ordre d'un numéro de voie : les numéros d'abord, par suffixe (« », « BIS », « S » des voies souterraines)
     * puis par valeur, puis les codes en lettres.
     *
     * @return array{0: int, 1: string, 2: int}
     */
    private static function numberKey(string $number): array
    {
        return preg_match('/^(\d+)(.*)$/', $number, $m) === 1 ? [0, $m[2], (int) $m[1]] : [1, $number, 0];
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

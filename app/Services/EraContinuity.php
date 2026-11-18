<?php

namespace App\Services;

/**
 * Continuité des portions d'une ligne le long de son tracé, d'une ère à l'autre
 * (cf. vff:build-era-segments). Calculs purs, sans base de données.
 *
 * Le tracé de référence d'une ligne est fait de nombreux morceaux (BD TOPO, OSM,
 * anciens tracés…), découpés en tronçons de 100 m. Chaque tronçon reçoit les
 * événements dont la section passe à moins de 30 m, l'état actuel observé, le
 * statut SNCF… Aux limites, ces attributs « bruitent » : un tronçon à cheval sur
 * deux sections d'événements, un bout d'ancien tracé de 80 m intercalé entre deux
 * morceaux BD TOPO, une observation ponctuelle donnent des morceaux minuscules
 * d'un autre état que leurs voisins, ou des trous au milieu d'une ligne active.
 */
class EraContinuity
{
    /**
     * Enchaîne des morceaux de tracé par leurs extrémités. Les extrémités à moins de $tol
     * mètres l'une de l'autre forment un nœud ; à chaque nœud, les deux extrémités des
     * morceaux les plus LONGS (à égalité, le premier indice) sont reliées, les autres restent
     * libres. Ainsi un carrefour ne relie que deux branches, et de petits doublons de
     * quelques mètres au même endroit (tronçons BD TOPO superposés) ne coupent pas la ligne.
     *
     * @param  list<array{0: float, 1: float, 2: float, 3: float}>  $ends  [x début, y début, x fin, y fin] de chaque morceau (m)
     * @param  list<float>  $lengths  longueur de chaque morceau (m), à défaut la distance entre ses extrémités
     * @return list<list<array{0: int, 1: bool}>> chaînes de [indice du morceau, parcouru dans son sens ?]
     */
    public static function chains(array $ends, float $tol, array $lengths = []): array
    {
        $pts = [];
        $grid = [];
        foreach ($ends as $s => [$sx, $sy, $ex, $ey]) {
            $pts[2 * $s] = [$sx, $sy];
            $pts[2 * $s + 1] = [$ex, $ey];
            $lengths[$s] ??= sqrt(($ex - $sx) ** 2 + ($ey - $sy) ** 2);
        }
        foreach ($pts as $e => [$x, $y]) {
            $grid[(int) floor($x / $tol)][(int) floor($y / $tol)][] = $e;
        }
        // Nœuds : extrémités proches, regroupées de proche en proche (union-find).
        $parent = array_combine(array_keys($pts), array_keys($pts));
        $find = function (int $e) use (&$parent): int {
            while ($parent[$e] !== $e) {
                $e = $parent[$e] = $parent[$parent[$e]];
            }

            return $e;
        };
        foreach ($pts as $e => [$x, $y]) {
            [$gx, $gy] = [(int) floor($x / $tol), (int) floor($y / $tol)];
            for ($dx = -1; $dx <= 1; $dx++) {
                for ($dy = -1; $dy <= 1; $dy++) {
                    foreach ($grid[$gx + $dx][$gy + $dy] ?? [] as $f) {
                        if ($f > $e && intdiv($f, 2) !== intdiv($e, 2) && ($pts[$f][0] - $x) ** 2 + ($pts[$f][1] - $y) ** 2 <= $tol ** 2) {
                            $parent[$find($f)] = $find($e);
                        }
                    }
                }
            }
        }
        $nodes = [];
        foreach (array_keys($pts) as $e) {
            $nodes[$find($e)][] = $e;
        }
        $link = [];
        foreach ($nodes as $members) {
            usort($members, fn ($a, $b) => [-$lengths[intdiv($a, 2)], $a] <=> [-$lengths[intdiv($b, 2)], $b]);
            $first = $members[0];
            foreach (array_slice($members, 1) as $other) {
                if (intdiv($other, 2) !== intdiv($first, 2)) {
                    $link[$first] = $other;
                    $link[$other] = $first;
                    break;
                }
            }
        }

        $seen = [];
        $walk = function (int $s, bool $forward) use ($link, &$seen): array {
            $chain = [];
            while (! isset($seen[$s])) {
                $seen[$s] = true;
                $chain[] = [$s, $forward];
                $next = $link[$forward ? 2 * $s + 1 : 2 * $s] ?? null;
                if ($next === null) {
                    break;
                }
                $s = intdiv($next, 2);
                $forward = $next % 2 === 0;
            }

            return $chain;
        };
        $chains = [];
        foreach (array_keys($ends) as $s) {
            if (! isset($seen[$s]) && ! isset($link[2 * $s])) {
                $chains[] = $walk($s, true);
            } elseif (! isset($seen[$s]) && ! isset($link[2 * $s + 1])) {
                $chains[] = $walk($s, false);
            }
        }
        foreach (array_keys($ends) as $s) {
            if (! isset($seen[$s])) {
                $chains[] = $walk($s, true); // boucle
            }
        }

        return $chains;
    }

    /**
     * Lissage d'une chaîne de tronçons, dans l'ordre du tracé. Une « suite » = tronçons
     * contigus de même signature (état à toutes les ères).
     *   - une suite plus courte que $minPiece, d'un état différent de ses deux voisines,
     *     est absorbée (par la voisine la plus longue, ou la seule à une extrémité) ;
     *   - un trou plus court que $maxGap entre deux suites de même état, moins présent
     *     qu'elles (absent ou fermé là où elles sont en service ou visibles) et jamais
     *     plus, est recousu ;
     * sauf si $justified (positions d'origine de la suite, positions voisines) le refuse :
     * une fermeture réellement limitée à 150 m doit rester visible.
     *
     * @param  list<float>  $len  longueur de chaque tronçon (m)
     * @param  list<string>  $sig  signature de chaque tronçon
     * @param  null|callable(list<int>, list<int>): bool  $justified
     * @return list<int> pour chaque tronçon, la position (d'origine) dont il prend l'état
     */
    public static function smooth(array $len, array $sig, float $minPiece, float $maxGap, ?callable $justified = null): array
    {
        $src = array_keys($sig);
        for ($guard = count($sig); $guard > 0; $guard--) {
            $runs = [];
            foreach ($sig as $pos => $s) {
                $last = count($runs) - 1;
                if ($last >= 0 && $runs[$last]['sig'] === $s) {
                    $runs[$last]['to'] = $pos;
                    $runs[$last]['len'] += $len[$pos];
                } else {
                    $runs[] = ['sig' => $s, 'from' => $pos, 'to' => $pos, 'len' => (float) $len[$pos]];
                }
            }
            if (count($runs) < 2) {
                break;
            }
            $pick = null;
            foreach ($runs as $j => $r) {
                if ($pick !== null && $r['len'] >= $runs[$pick]['len']) {
                    continue;
                }
                $left = $runs[$j - 1] ?? null;
                $right = $runs[$j + 1] ?? null;
                $hole = $left && $right && $left['sig'] === $right['sig'] && $r['len'] < $maxGap && self::isHole($r['sig'], $left['sig']);
                if ($r['len'] >= $minPiece && ! $hole) {
                    continue;
                }
                if ($justified) {
                    $around = array_merge(
                        $left ? range(max($left['from'], $r['from'] - 3), $r['from'] - 1) : [],
                        $right ? range($r['to'] + 1, min($right['to'], $r['to'] + 3)) : [],
                    );
                    $at = fn (array $positions) => array_map(fn ($p) => $src[$p], $positions);
                    if ($justified($at(range($r['from'], $r['to'])), $at($around))) {
                        continue;
                    }
                }
                $pick = $j;
            }
            if ($pick === null) {
                break;
            }
            $r = $runs[$pick];
            $left = $runs[$pick - 1] ?? null;
            $right = $runs[$pick + 1] ?? null;
            $toLeft = $right === null || ($left !== null && ($left['sig'] === $right['sig'] || $left['len'] >= $right['len']));
            $target = $toLeft ? $r['from'] - 1 : $r['to'] + 1;
            for ($pos = $r['from']; $pos <= $r['to']; $pos++) {
                $src[$pos] = $src[$target];
                $sig[$pos] = $sig[$target];
            }
        }

        return $src;
    }

    /**
     * Ouvertures « en sandwich » le long d'une chaîne de tronçons ($firstOpen : première
     * ouverture Y-m-d de chaque tronçon, null = jamais daté) : une suite de moins de $maxLen
     * mètres, jamais datée ou ouverte plus de $years ans après ses DEUX voisines, prend
     * l'ouverture de la plus récente des deux. Une ligne n'est pas coupée en son milieu
     * pendant des décennies : la date tardive est une électrification, une double voie ou
     * une rectification de tracé mal typée (ex. Paris - Brest, Laval - Vitré « 1965 » entre
     * deux portions de 1857) ; une suite jamais datée entre deux portions datées existait
     * avec elles.
     *
     * @param  list<float>  $len
     * @param  list<?string>  $firstOpen
     * @return array<int, array{date: string, from: int}> position => date déduite, et position voisine qui la donne
     */
    public static function sandwichedOpenings(array $len, array $firstOpen, float $maxLen, int $years): array
    {
        $runs = [];
        foreach ($firstOpen as $pos => $d) {
            $last = count($runs) - 1;
            if ($last >= 0 && $runs[$last]['d'] === $d) {
                $runs[$last]['to'] = $pos;
                $runs[$last]['len'] += $len[$pos];
            } else {
                $runs[] = ['d' => $d, 'from' => $pos, 'to' => $pos, 'len' => (float) $len[$pos]];
            }
        }
        $out = [];
        foreach ($runs as $j => $r) {
            $left = $runs[$j - 1] ?? null;
            $right = $runs[$j + 1] ?? null;
            if (! $left || ! $right || $left['d'] === null || $right['d'] === null || $r['len'] >= $maxLen) {
                continue;
            }
            [$date, $from] = $left['d'] >= $right['d'] ? [$left['d'], $left['to']] : [$right['d'], $right['from']];
            if ($r['d'] !== null && (int) substr($r['d'], 0, 4) <= (int) substr($date, 0, 4) + $years) {
                continue;
            }
            for ($pos = $r['from']; $pos <= $r['to']; $pos++) {
                $out[$pos] = ['date' => $date, 'from' => $from];
            }
        }

        return $out;
    }

    /**
     * La signature $sig est-elle un « trou » dans $around : jamais plus présente, et moins
     * présente à au moins une ère (absente < fermée ou déposée < en service) ?
     * Signature = états des ères séparés par « ; », « - » = absente, « in_service/… ».
     */
    public static function isHole(string $sig, string $around): bool
    {
        $rank = fn (string $s) => $s === '-' ? 0 : (str_starts_with($s, 'in_service/') ? 2 : 1);
        $hole = false;
        foreach (array_map(null, explode(';', $sig), explode(';', $around)) as [$a, $b]) {
            if ($rank((string) $a) > $rank((string) $b)) {
                return false;
            }
            $hole = $hole || $rank((string) $a) < $rank((string) $b);
        }

        return $hole;
    }

    /**
     * Ouverture d'une portion datée seulement « toute la ligne », d'après la portion datée
     * voisine ($neighbour, Y-m-d) : pas avant la première ouverture globale, pas après
     * l'achèvement ($completion, s'il y a plusieurs ouvertures globales), et jamais après
     * la première fermeture connue de la portion (null = pas d'inférence).
     */
    public static function neighbourOpening(string $neighbour, string $wholeFirst, ?string $completion, ?string $firstEnd): ?string
    {
        $date = max($neighbour, $wholeFirst);
        if ($completion !== null) {
            $date = min($date, $completion);
        }

        return $firstEnd !== null && $date > $firstEnd ? null : $date;
    }

    /**
     * Point daté le plus proche de ($x, $y) dans une grille de cases de $cell mètres
     * ([case x][case y] => liste de [x, y, valeur]), à moins de $maxD mètres, ou null.
     * Recherche par anneaux de cases, arrêtée dès que l'anneau suivant est plus loin que le
     * meilleur candidat ; à distance égale, la valeur de plus petite date l'emporte.
     * $accept (facultatif) écarte des valeurs : ex. une fermeture voisine antérieure au
     * dernier événement de la portion, qui ne peut pas la dater (cf. neighbourClosure).
     *
     * @param  (callable(array): bool)|null  $accept
     * @return array{date: string}|null
     */
    public static function nearest(array $grid, float $x, float $y, float $cell, float $maxD, ?callable $accept = null): ?array
    {
        [$gx, $gy] = [(int) floor($x / $cell), (int) floor($y / $cell)];
        $best = null;
        $bestD = $maxD ** 2;
        for ($r = 0; $r <= (int) ceil($maxD / $cell) + 1 && ($best === null || $bestD > (($r - 1) * $cell) ** 2); $r++) {
            for ($dx = -$r; $dx <= $r; $dx++) {
                for ($dy = -$r; $dy <= $r; $dy++) {
                    if (max(abs($dx), abs($dy)) !== $r) {
                        continue;
                    }
                    foreach ($grid[$gx + $dx][$gy + $dy] ?? [] as [$px, $py, $v]) {
                        if ($accept !== null && ! $accept($v)) {
                            continue;
                        }
                        $d = ($px - $x) ** 2 + ($py - $y) ** 2;
                        if ($d < $bestD || ($d == $bestD && $best !== null && $v['date'] < $best['date'])) {
                            [$best, $bestD] = [$v, $d];
                        }
                    }
                }
            }
        }

        return $best;
    }

    /**
     * Une fermeture « toute la ligne » sans réouverture, alors qu'une partie de la ligne est
     * exploitée aujourd'hui, est contredite là où la portion vit encore ; elle reste valable
     * pour une portion fermée ou disparue aujourd'hui.
     */
    public static function wholeLineClosureContradicted(bool $partOfLineExploited, bool $portionClosedToday): bool
    {
        return $partOfLineExploited && ! $portionClosedToday;
    }

    /**
     * Ouverture d'un raccordement jamais daté, d'après les lignes qu'il relie : pour chaque
     * extrémité libre, [ligne touchée, date de cette ligne à cet endroit (Y-m-d) ou null,
     * ligne à grande vitesse ?]. Seulement s'il dessert une LGV (sinon null : entre deux
     * lignes classiques, la plus récente n'est qu'une borne souvent fausse de décennies).
     * Une ligne touchée par plusieurs extrémités compte pour sa date la plus ancienne (elle
     * existait là dès cette date) ; le raccordement ne peut pas être plus ancien que la plus
     * récente des lignes reliées (borne inférieure, confiance basse). Il faut au moins deux
     * extrémités, toutes datées (sinon null : on ne sait pas ce qu'il relie).
     *
     * @param  list<array{0: ?int, 1: ?string, 2?: bool}>  $ends
     */
    public static function junctionOpening(array $ends): ?string
    {
        if (count($ends) < 2 || ! array_filter($ends, fn ($e) => $e[2] ?? false)) {
            return null;
        }
        $byLine = [];
        foreach ($ends as [$line, $date]) {
            if ($line === null || $date === null) {
                return null;
            }
            $byLine[$line] = min($byLine[$line] ?? $date, $date);
        }

        return max($byLine);
    }

    /**
     * Fermeture d'une portion fermée ou disparue aujourd'hui que sa chronologie laisse en
     * service : la fermeture définitive de la portion voisine ($neighbour), à défaut la
     * première fermeture non localisée de la ligne ($orphans, triées par date), à condition
     * qu'elle suive le dernier événement propre de la portion ($lastEvent, Y-m-d).
     *
     * @param  array{date: string}|null  $neighbour
     * @param  list<array{date: string}>  $orphans
     * @return array{date: string}|null
     */
    public static function neighbourClosure(?array $neighbour, array $orphans, ?string $lastEvent): ?array
    {
        // (Voisine fermée AVANT le dernier événement de la portion : on tente les fermetures non localisées.)
        foreach (array_merge($neighbour ? [$neighbour] : [], $orphans) as $e) {
            if ($lastEvent === null || $e['date'] > $lastEvent) {
                return $e;
            }
        }

        return null;
    }

    /**
     * Fin de TOUT trafic d'une portion connue seulement par ses fins de service : la plus
     * tardive de sa première fin des voyageurs et de sa première fin du fret, toutes deux
     * localisées (pas « toute la ligne »), sans réouverture après. Null si un des deux
     * services n'a pas de fin, ou s'il rouvre ensuite (ex. 4194 Le Mans - La Chartre :
     * voyageurs et marchandises fermés le 1er mars 1947 → fin de tout trafic le 1er mars 1947).
     *
     * @param  list<array{type: string, date: string, source?: ?string, whole?: bool}>  $events  événements retenus (EraTimeline::dedupe)
     * @return array{date: string, source: ?string}|null
     */
    public static function pairedServiceEnd(array $events): ?array
    {
        $first = [];
        foreach ($events as $e) {
            if (in_array($e['type'], ['closure_passenger', 'closure_freight'], true) && ! ($e['whole'] ?? false)
                && (! isset($first[$e['type']]) || $e['date'] < $first[$e['type']]['date'])) {
                $first[$e['type']] = $e;
            }
        }
        if (count($first) < 2) {
            return null;
        }
        $end = $first['closure_passenger']['date'] >= $first['closure_freight']['date'] ? $first['closure_passenger'] : $first['closure_freight'];
        foreach ($events as $e) {
            if (str_starts_with($e['type'], 'reopening') && $e['date'] >= $end['date']) {
                return null;
            }
        }

        return ['date' => $end['date'], 'source' => $end['source'] ?? null];
    }
}

<?php

namespace App\Services;

/**
 * Découpe d'un tracé source qui couvre PLUSIEURS lignes bout à bout
 * (logique pure, sans base : utilisée par vff:link-traces, étape d'').
 *
 * archeoferroviaire dessine parfois d'un seul trait deux ou trois lignes
 * successives : « Auray Saint-Brieuc » = ligne d'Auray à Pontivy + ligne de
 * Pontivy à Saint-Brieuc, « Bram Lavelanet » = Bram - Belvèze + Belvèze -
 * Moulin-Neuf (section de Pamiers - Limoux) + Moulin-Neuf - Lavelanet. Rattaché
 * en bloc, ce tracé crée une ligne fantôme ou double une partie d'une autre.
 *
 * chains() cherche, pour un libellé « A B », les chaînes de 2 ou 3 lignes
 * L1 … Ln telles que :
 *   - A est un lieu de L1 (extrémité ou gare), B un lieu de Ln ;
 *   - deux lignes consécutives se touchent en un point de jonction J, extrémité
 *     d'au moins l'une des deux et lieu (extrémité ou gare) de l'autre ;
 *   - le morceau de L1 (A → J1) et celui de Ln (Jn-1 → B) sont bornés par au
 *     moins une extrémité de leur ligne (une section prise entre deux gares
 *     n'est admise qu'au milieu de la chaîne : Belvèze - Moulin-Neuf) ;
 *   - aucune ligne ne contient À LA FOIS A et B (sinon le tracé est cette
 *     ligne, ou une section de celle-ci : pas de découpe).
 * Le placement sur le tracé (ordre des jonctions, longueurs, géométrie connue)
 * est vérifié ensuite par l'appelant (cuts()).
 */
final class TraceSplitter
{
    /** @var array<int, array{ends: list<list<list<string>>>, stations: list<list<list<string>>>, end_names: list<string>, station_names: list<string>}> lieux : variantes (cf. variants()) */
    private array $lines = [];

    /** @var array<string, list<array{0: int, 1: bool, 2: list<string>}>> premier mot → [ligne, extrémité ?, mots] */
    private array $placeIndex = [];

    /**
     * @param  array<int, array{ends: list<string>, stations?: list<string>}>  $lines  id → extrémités (2 noms) et gares
     */
    public function __construct(array $lines)
    {
        foreach ($lines as $id => $l) {
            $ends = array_values(array_slice($l['ends'], 0, 2));
            if (count($ends) < 2) {
                continue;
            }
            $entry = ['ends' => [], 'stations' => [], 'end_names' => $ends, 'station_names' => []];
            foreach ($ends as $e) {
                $entry['ends'][] = self::variants($e);
            }
            foreach (array_values(array_unique($l['stations'] ?? [])) as $s) {
                $v = self::variants($s);
                if ($v) {
                    $entry['stations'][] = $v;
                    $entry['station_names'][] = $s;
                }
            }
            $this->lines[$id] = $entry;
            foreach ([[true, $entry['ends']], [false, $entry['stations']]] as [$isEnd, $places]) {
                foreach ($places as $variants) {
                    foreach ($variants as $tokens) {
                        $this->placeIndex[$tokens[0]][] = [$id, $isEnd, $tokens];
                    }
                }
            }
        }
    }

    /**
     * Deux toponymes désignent-ils le même lieu ? Mots identiques, ou l'un est
     * le début de l'autre (« Mortagne » / « Mortagne-au-Perche », « Lons » /
     * « Lons-le-Saunier ») pourvu que la partie commune contienne un mot
     * significatif ; « La Hutte/Coulombiers » vaut aussi pour chacune des
     * variantes séparées par « / » ou « - » entre espaces.
     */
    public static function samePlace(string $a, string $b): bool
    {
        foreach (self::variants($a) as $ta) {
            foreach (self::variants($b) as $tb) {
                if (self::tokensMatch($ta, $tb)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Chaînes de lignes (2 puis 3) qui mettent bout à bout A et B.
     *
     * @return list<list<array{line: int, from: string, to: string, from_end: bool, to_end: bool}>>
     */
    public function chains(string $a, string $b, int $maxLines = 3): array
    {
        $aLines = $this->linesWith($a);
        $bLines = $this->linesWith($b);
        if (! $aLines || ! $bLines) {
            return [];
        }
        // Une ligne qui contient A et B : le tracé est cette ligne (ou une de ses sections).
        if (array_intersect_key($aLines, $bLines)) {
            return [];
        }

        $out = [];
        foreach ($aLines as $l1 => $aIsEnd) {
            foreach ($this->junctions($l1, [$a]) as [$j1, $j1EndOfL1, $l2, $j1EndOfL2]) {
                if (! $aIsEnd && ! $j1EndOfL1) {
                    continue; // morceau de L1 pris entre deux gares : pas en tête de chaîne
                }
                // Chaîne de 2 : L2 contient B.
                if (isset($bLines[$l2]) && ($bLines[$l2] || $j1EndOfL2)) {
                    $out[] = [
                        ['line' => $l1, 'from' => $a, 'to' => $j1, 'from_end' => $aIsEnd, 'to_end' => $j1EndOfL1],
                        ['line' => $l2, 'from' => $j1, 'to' => $b, 'from_end' => $j1EndOfL2, 'to_end' => $bLines[$l2]],
                    ];
                }
                if ($maxLines < 3 || isset($bLines[$l2])) {
                    continue;
                }
                // Chaîne de 3 : L2 relie J1 à J2, L3 contient B.
                foreach ($this->junctions($l2, [$a, $b, $j1]) as [$j2, $j2EndOfL2, $l3, $j2EndOfL3]) {
                    if ($l3 === $l1 || ! isset($bLines[$l3]) || (! $bLines[$l3] && ! $j2EndOfL3)) {
                        continue;
                    }
                    $out[] = [
                        ['line' => $l1, 'from' => $a, 'to' => $j1, 'from_end' => $aIsEnd, 'to_end' => $j1EndOfL1],
                        ['line' => $l2, 'from' => $j1, 'to' => $j2, 'from_end' => $j1EndOfL2, 'to_end' => $j2EndOfL2],
                        ['line' => $l3, 'from' => $j2, 'to' => $b, 'from_end' => $j2EndOfL3, 'to_end' => $bLines[$l3]],
                    ];
                }
            }
        }

        // Dédoublonnage (même suite de lignes par deux noms de jonction équivalents).
        $uniq = [];
        foreach ($out as $c) {
            $uniq[implode('>', array_column($c, 'line')).'|'.PlaceNames::normalize(implode('|', array_column($c, 'to')))] ??= $c;
        }

        return array_values($uniq);
    }

    /**
     * Validation du placement d'une chaîne sur le tracé, à partir des abscisses
     * (0 … 1) des lieux A, J1 … B le long du tracé.
     *
     * @param  list<float>  $fractions  abscisses de A, J1, …, B (dans l'ordre de la chaîne)
     * @param  float  $lengthM  longueur du tracé
     * @return array{ok: bool, why: ?string, cuts: list<float>, reversed: bool}
     *                                                                          cuts = bornes croissantes des morceaux [0, c1, …, 1] ; reversed = A est en fin de tracé
     */
    public static function cuts(array $fractions, float $lengthM, float $endTolerance = 0.25, float $minPieceM = 1000): array
    {
        $n = count($fractions);
        $reversed = $fractions[0] > $fractions[$n - 1];
        $f = $reversed ? array_map(fn ($x) => 1 - $x, $fractions) : $fractions;
        $no = fn (string $why) => ['ok' => false, 'why' => $why, 'cuts' => [], 'reversed' => $reversed];
        if ($f[0] > $endTolerance || $f[$n - 1] < 1 - $endTolerance) {
            return $no('extrémités du libellé loin des bouts du tracé');
        }
        for ($i = 1; $i < $n; $i++) {
            if ($f[$i] <= $f[$i - 1]) {
                return $no('jonctions dans le désordre le long du tracé');
            }
        }
        $cuts = array_merge([0.0], array_slice($f, 1, $n - 2), [1.0]);
        for ($i = 1; $i < count($cuts); $i++) {
            if (($cuts[$i] - $cuts[$i - 1]) * $lengthM < $minPieceM) {
                return $no('morceau trop court');
            }
        }

        return ['ok' => true, 'why' => null, 'cuts' => $cuts, 'reversed' => $reversed];
    }

    /**
     * Longueur d'un morceau plausible pour sa ligne ? Morceau borné par les
     * deux extrémités : 0,6 à 1,5 fois la longueur officielle ; sinon (section) :
     * au plus 1,3 fois + 3 km. Sans longueur officielle : pas de contrôle.
     */
    public static function plausibleLength(float $pieceM, ?int $officialM, bool $wholeLine): bool
    {
        if (! $officialM) {
            return true;
        }

        return $wholeLine
            ? $pieceM >= 0.6 * $officialM && $pieceM <= 1.5 * $officialM + 2000
            : $pieceM <= 1.3 * $officialM + 3000;
    }

    /**
     * Morceaux d'un tracé autour de portions communes avec d'autres lignes
     * (étape « portions sur la voie d'une autre ligne » de vff:link-traces) :
     * chaque portion va à sa ligne, les intervalles restants à $own ; un reste
     * plus court que $minRestM est absorbé par la portion voisine. Tracé
     * entièrement commun à UNE ligne : aucun morceau (c'est l'étape « même
     * voie » sur tout le tracé).
     *
     * @param  list<array{0: float, 1: float, 2: int}>  $runs  portions [f0, f1, ligne], triées, disjointes
     * @return list<array{0: float, 1: float, 2: int, 3: bool}> [f0, f1, ligne, portion commune ?]
     */
    public static function piecesAroundRuns(array $runs, float $lengthM, int $own, float $minRestM = 200): array
    {
        $bounds = [];
        $cursor = 0.0;
        foreach ($runs as [$f0, $f1, $lid]) {
            if ($f0 > $cursor) {
                $bounds[] = [$cursor, $f0, $own, false];
            }
            $bounds[] = [$f0, $f1, $lid, true];
            $cursor = $f1;
        }
        if ($cursor < 1.0) {
            $bounds[] = [$cursor, 1.0, $own, false];
        }
        $out = [];
        $carry = null; // reste trop court en tête : rattaché à la portion suivante
        foreach ($bounds as $b) {
            if (! $b[3] && ($b[1] - $b[0]) * $lengthM < $minRestM) {
                if ($out) {
                    $out[count($out) - 1][1] = $b[1];
                } else {
                    $carry = $b[0];
                }

                continue;
            }
            if ($carry !== null) {
                $b[0] = $carry;
                $carry = null;
            }
            $out[] = $b;
        }
        if (count($out) === 1 && $out[0][3]) {
            return [];
        }

        return $out;
    }

    // ───────────────────────────── interne ─────────────────────────────

    /**
     * Lignes dont $place est un lieu : id → true si c'est une extrémité, false si c'est une gare.
     *
     * @return array<int, bool>
     */
    private function linesWith(string $place): array
    {
        return $this->matching(self::variants($place));
    }

    /**
     * @param  list<list<string>>  $variants
     * @return array<int, bool> lignes qui ont ce lieu → true si c'est une de leurs extrémités
     */
    private function matching(array $variants): array
    {
        $out = [];
        foreach ($variants as $tokens) {
            foreach ($this->placeIndex[$tokens[0]] ?? [] as [$id, $isEnd, $other]) {
                if (self::tokensMatch($tokens, $other)) {
                    $out[$id] = ($out[$id] ?? false) || $isEnd;
                }
            }
        }

        return $out;
    }

    /**
     * Jonctions possibles depuis la ligne $id : lieux de $id (hors $exclude)
     * partagés avec une autre ligne, l'une des deux au moins y ayant une extrémité.
     *
     * @param  list<string>  $exclude
     * @return list<array{0: string, 1: bool, 2: int, 3: bool}> [nom, extrémité de $id, autre ligne, extrémité de l'autre]
     */
    private function junctions(int $id, array $exclude): array
    {
        $l = $this->lines[$id];
        $places = [];
        foreach ($l['end_names'] as $i => $name) {
            $places[] = [$name, $l['ends'][$i], true];
        }
        foreach ($l['station_names'] as $i => $name) {
            $places[] = [$name, $l['stations'][$i], false];
        }
        $excluded = array_merge(...array_map(fn ($x) => self::variants($x), $exclude));
        $out = [];
        foreach ($places as [$name, $variants, $isEnd]) {
            if (self::anyMatch($variants, $excluded)) {
                continue;
            }
            foreach ($this->matching($variants) as $other => $role) {
                $key = $other.'|'.PlaceNames::normalize($name);
                if ($other === $id || (! $isEnd && ! $role) || isset($out[$key])) {
                    continue; // gare des deux côtés : pas une jonction
                }
                // Nom de la jonction : celui de l'extrémité (le plus précis des deux).
                $out[$key] = [$isEnd ? $name : $this->endName($other, $variants), $isEnd, $other, $role];
            }
        }

        return array_values($out);
    }

    /** @param  list<list<string>>  $variants */
    private function endName(int $id, array $variants): string
    {
        foreach ($this->lines[$id]['ends'] as $i => $end) {
            if (self::anyMatch($variants, $end)) {
                return $this->lines[$id]['end_names'][$i];
            }
        }

        return '';
    }

    /**
     * @param  list<list<string>>  $a
     * @param  list<list<string>>  $b
     */
    private static function anyMatch(array $a, array $b): bool
    {
        foreach ($a as $ta) {
            foreach ($b as $tb) {
                if (self::tokensMatch($ta, $tb)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $a
     * @param  list<string>  $b
     */
    private static function tokensMatch(array $a, array $b): bool
    {
        if ($a === $b) {
            return true;
        }
        [$short, $long] = count($a) <= count($b) ? [$a, $b] : [$b, $a];

        return $short && array_slice($long, 0, count($short)) === $short
            && PlaceNames::significant(implode(' ', $short)) !== [];
    }

    /**
     * Formes comparables d'un toponyme : mots utiles du nom entier et de
     * chacune de ses variantes (« La Hutte/Coulombiers », « Néant - Bois-de-la-Roche »).
     *
     * @return list<list<string>>
     */
    private static function variants(string $place): array
    {
        $out = [];
        foreach (array_merge([$place], preg_split('#\s*/\s*|\s+-\s+#u', $place)) as $v) {
            $t = PlaceNames::tokens($v);
            if ($t) {
                $out[implode(' ', $t)] = $t;
            }
        }

        return array_values($out);
    }
}

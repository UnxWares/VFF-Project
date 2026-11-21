<?php

namespace App\Services\Profiles;

/**
 * Sens d'un profil en long : celui des PK croissants de la ligne. Fonction pure, testée dans
 * tests/Unit/Profiles/ProfileOrientationTest.php.
 */
final class ProfileOrientation
{
    /**
     * Faut-il retourner le parcours pour que les PK croissent avec la distance ? La pente de la
     * droite des moindres carrés du PK selon la distance est plus robuste qu'une comparaison des
     * deux gares extrêmes quand une gare est mal placée. Null quand le sens ne peut pas être lu
     * (moins de deux points, ou un seul PK distinct, ou une distance unique) : l'appelant garde alors
     * le sens de l'assemblage.
     *
     * @param  list<array{0: float|int, 1: int|float}>  $points  [distance le long du parcours, PK en mètres]
     */
    public static function shouldReverse(array $points): ?bool
    {
        if (count($points) < 2 || count(array_unique(array_column($points, 1))) < 2) {
            return null;
        }
        $n = count($points);
        $md = array_sum(array_column($points, 0)) / $n;
        $mp = array_sum(array_column($points, 1)) / $n;
        $slope = 0.0;
        foreach ($points as [$d, $pk]) {
            $slope += ($d - $md) * ($pk - $mp);
        }

        return $slope === 0.0 ? null : $slope < 0;
    }

    /**
     * Sens donné par le nom de la ligne, « Ligne de A à B » ou « A - B » : le parcours commence du côté
     * de A. On rapproche A et B des noms des gares placées aux deux bouts du parcours (accents, tirets,
     * « Saint » et « St » normalisés). Null si le nom n'a pas cette forme, si le rapprochement ne
     * départage pas les deux sens, ou si les découpages possibles du nom (un nom de gare peut contenir
     * lui-même « - ») ne donnent pas le même sens.
     *
     * @param  list<string>|null  $stations  noms des gares de la ligne, pour valider la coupure d'un nom
     *                                       « A - B » ; sans elle, seul un nom à un seul « - » est coupé
     */
    public static function reverseByName(string $lineName, string $startStation, string $endStation, ?array $stations = null): ?bool
    {
        $decisions = [];
        foreach (self::endsCandidates($lineName, $stations) as [$a, $b]) {
            $straight = self::match($a, $startStation) + self::match($b, $endStation);
            $reversed = self::match($a, $endStation) + self::match($b, $startStation);
            if (abs($straight - $reversed) >= 0.25) {
                $decisions[] = $reversed > $straight;
            }
        }
        $distinct = array_unique($decisions);

        return count($distinct) === 1 ? $distinct[0] : null;
    }

    /**
     * Les deux bouts du nom, quand il n'y a qu'une façon de le couper ; null sinon (pas de forme
     * « A à B » ni « A - B », ou plusieurs coupures possibles et rien pour choisir).
     *
     * @return array{0: string, 1: string}|null
     */
    public static function lineEnds(string $lineName): ?array
    {
        $candidates = self::endsCandidates($lineName, null);

        return count($candidates) === 1 ? $candidates[0] : null;
    }

    /**
     * Coupures possibles du nom. La forme « de A à B » prime : « à », « au » et « aux » ne se trouvent
     * pas dans un nom de gare et la première coupe suffit. Sans elle, chaque « - » (ou « – ») entouré
     * d'espaces est une coupure possible ; avec la liste des gares de la ligne, on ne garde que celles
     * dont les deux morceaux correspondent à une gare (tous les mots de l'un se retrouvent dans le nom
     * d'une gare). Sans liste, une seule coupure est admise.
     *
     * @param  list<string>|null  $stations
     * @return list<array{0: string, 1: string}>
     */
    private static function endsCandidates(string $lineName, ?array $stations): array
    {
        $name = trim((string) preg_replace('/^\s*(?:ligne|embranchement|chemin de fer)\s+(?:d\'|de\s+l\'|de\s+la\s+|de\s+|du\s+|des\s+)\s*/iu', '', $lineName));
        $to = preg_split('/\s+(?:à|a|au|aux)\s+(?:l\'|la\s+)?/iu', $name, 2);
        if ($to !== false && count($to) === 2 && trim($to[0]) !== '' && trim($to[1]) !== '') {
            return [[trim($to[0]), trim($to[1])]];
        }
        $pieces = preg_split('/\s+[-–]\s+(?:l\'|la\s+)?/iu', $name);
        if ($pieces === false || count($pieces) < 2) {
            return [];
        }
        $candidates = [];
        for ($i = 1; $i < count($pieces); $i++) {
            $a = trim(implode(' - ', array_slice($pieces, 0, $i)));
            $b = trim(implode(' - ', array_slice($pieces, $i)));
            if ($a === '' || $b === '') {
                continue;
            }
            if ($stations === null || (self::isStation($a, $stations) && self::isStation($b, $stations))) {
                $candidates[] = [$a, $b];
            }
        }

        return $stations === null && count($candidates) > 1 ? [] : $candidates;
    }

    /** @param  list<string>  $stations */
    private static function isStation(string $place, array $stations): bool
    {
        foreach ($stations as $station) {
            if (self::match($place, $station) >= 1.0) {
                return true;
            }
        }

        return false;
    }

    /** Part des mots du plus court des deux noms que l'autre contient (0 à 1). */
    private static function match(string $place, string $station): float
    {
        $p = self::tokens($place);
        $s = self::tokens($station);
        if ($p === [] || $s === []) {
            return 0.0;
        }
        $common = count(array_intersect($p, $s));

        return $common / min(count($p), count($s));
    }

    /** @return list<string> */
    private static function tokens(string $name): array
    {
        $ascii = strtolower((string) iconv('UTF-8', 'ASCII//TRANSLIT', $name));
        $ascii = (string) preg_replace('/[^a-z0-9]+/', ' ', $ascii);
        $words = array_filter(explode(' ', $ascii), fn (string $w): bool => $w !== '' && ! in_array($w, ['de', 'du', 'des', 'la', 'le', 'les', 'l', 'd', 'sur', 'en', 'gare'], true));

        return array_values(array_unique(array_map(fn (string $w): string => $w === 'saint' || $w === 'sainte' ? ($w === 'saint' ? 'st' : 'ste') : $w, $words)));
    }
}

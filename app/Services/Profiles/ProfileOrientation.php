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
     * « Saint » et « St » normalisés). Null si le nom n'a pas cette forme ou si le rapprochement ne
     * départage pas les deux sens.
     */
    public static function reverseByName(string $lineName, string $startStation, string $endStation): ?bool
    {
        $ends = self::lineEnds($lineName);
        if ($ends === null) {
            return null;
        }
        [$a, $b] = $ends;
        $straight = self::match($a, $startStation) + self::match($b, $endStation);
        $reversed = self::match($a, $endStation) + self::match($b, $startStation);
        if (abs($straight - $reversed) < 0.25) {
            return null;
        }

        return $reversed > $straight;
    }

    /** @return array{0: string, 1: string}|null */
    public static function lineEnds(string $lineName): ?array
    {
        $name = trim((string) preg_replace('/^\s*(?:ligne|embranchement|chemin de fer)\s+(?:d\'|de\s+l\'|de\s+la\s+|de\s+|du\s+|des\s+)\s*/iu', '', $lineName));
        $parts = preg_split('/\s+(?:à|a|au|aux|-|–)\s+(?:l\'|la\s+)?/iu', $name, 2);
        if ($parts === false || count($parts) !== 2 || trim($parts[0]) === '' || trim($parts[1]) === '') {
            return null;
        }

        return [trim($parts[0]), trim($parts[1])];
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

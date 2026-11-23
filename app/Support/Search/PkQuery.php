<?php

namespace App\Support\Search;

/**
 * Lit une position sur une ligne dans une requête de recherche : « 570000 PK 120 », « 570000 km 120,5 »,
 * « ligne 570000 120+500 », « Paris - Bordeaux PK 300 ». Fonction pure.
 *
 * Le PK s'écrit après `PK`, `P.K.`, `km`, `kilomètre` ou `point kilométrique` (`pk 120`, `km 120,5`,
 * `pk120`, `km 120+500`), ou seul en notation ferroviaire `120+500`. Il est en kilomètres : entier
 * (`120`), décimal (`120,5` ou `120.5`, jusqu'au mètre) ou `km+mmm` (`120+500`). Le reste de la requête
 * désigne la ligne (code ou nom), une fois retirés les mots « ligne », « line » et « linie » et leurs
 * articles ; sans reste, la requête n'est pas une position.
 */
final class PkQuery
{
    /** PK maximal lu (kilomètres) : au-delà, ce n'est pas une position sur une ligne. */
    public const MAX_KM = 4999;

    private const KEYWORD = '(?:p\.?\s?k\.?|km|kms|kilom[eèé]tres?|point\s+kilom[eèé]trique)';

    private const VALUE = '(\d{1,4})(?:\s*\+\s*(\d{3})|[.,](\d{1,3}))?(?![\d+])';

    /**
     * @return array{text: string, pk_m: int}|null `text` : la partie qui désigne la ligne ; `pk_m` : le PK en mètres
     */
    public static function parse(string $q): ?array
    {
        $q = trim($q);
        $pk = null;
        $text = '';

        if (preg_match('/(?<![\p{L}\d])'.self::KEYWORD.'\s*(?:n°\s*)?'.self::VALUE.'/iu', $q, $m, PREG_OFFSET_CAPTURE) === 1) {
            $pk = self::metres($m);
            $text = substr($q, 0, $m[0][1]).' '.substr($q, $m[0][1] + strlen($m[0][0]));
        } elseif (preg_match('/(?<![\d+.,])(\d{1,4})\s*\+\s*(\d{3})(?![\d+])/u', $q, $m, PREG_OFFSET_CAPTURE) === 1) {
            $pk = self::metres($m);
            $text = substr($q, 0, $m[0][1]).' '.substr($q, $m[0][1] + strlen($m[0][0]));
        }
        if ($pk === null) {
            return null;
        }

        $text = trim((string) preg_replace('/(?<![\p{L}\d])(?:ligne|lignes|line|linie|n°|no|num[eé]ro)(?![\p{L}\d])|[,;:()]+/iu', ' ', $text));
        $text = trim((string) preg_replace('/\s+/u', ' ', $text), " \t-");
        // Un mot qui sert de liant seul (« la ligne de ») ne désigne rien.
        if (preg_match('/^(?:(?:de|du|des|la|le|les|sur|à|a|of|the|der|die|das)\s*)*$/iu', $text) === 1 || mb_strlen($text) < 2) {
            return null;
        }

        return ['text' => $text, 'pk_m' => $pk];
    }

    /**
     * PK d'une valeur seule (`120`, `120,5`, `120+500`), en mètres ; null si ce n'est pas un PK.
     */
    public static function parseValue(string $value): ?int
    {
        if (preg_match('/^\s*'.self::VALUE.'\s*$/u', $value, $m) !== 1) {
            return null;
        }

        return self::metres([$m[0], $m[1], $m[2] ?? '', $m[3] ?? '']);
    }

    /**
     * @param  array<int, mixed>  $m  groupes de la regex (avec ou sans décalage) : 1 = km, 2 = mètres après « + », 3 = décimales
     */
    private static function metres(array $m): ?int
    {
        $g = fn (int $i): string => is_array($m[$i] ?? null) ? (string) $m[$i][0] : (string) ($m[$i] ?? '');
        $km = (int) $g(1);
        if ($km > self::MAX_KM) {
            return null;
        }
        if ($g(2) !== '') {
            return $km * 1000 + (int) $g(2);
        }

        // Décimales d'un kilomètre : « ,5 » = 500 m, « ,05 » = 50 m.
        return $km * 1000 + ($g(3) === '' ? 0 : (int) str_pad($g(3), 3, '0'));
    }
}

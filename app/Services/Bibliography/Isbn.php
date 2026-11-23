<?php

namespace App\Services\Bibliography;

/**
 * ISBN : lecture tolérante (tirets, espaces, préfixe « ISBN », ISBN-10 ou ISBN-13), contrôle de la clé et
 * normalisation en ISBN-13 sans séparateur, pour que le même ouvrage cité par la BnF et par Wikipédia donne la
 * même valeur. Une valeur dont la clé est fausse n'est pas gardée : un ISBN mal recopié ferait fusionner deux ouvrages.
 */
final class Isbn
{
    /** ISBN-13 normalisé (13 chiffres) d'un texte qui contient un ISBN valide, sinon null. */
    public static function normalize(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }
        preg_match_all('/(?<![\dXx])(?:97[89][\s\x{00A0}\x{2010}-\x{2015}-]?(?:\d[\s\x{00A0}\x{2010}-\x{2015}-]?){9}\d|(?:\d[\s\x{00A0}\x{2010}-\x{2015}-]?){9}[\dXx])(?![\dXx])/u', $text, $m);
        foreach ($m[0] as $candidate) {
            $digits = strtoupper((string) preg_replace('/[^\dXx]/u', '', $candidate));
            if (strlen($digits) === 13 && self::checks13($digits)) {
                return $digits;
            }
            if (strlen($digits) === 10 && self::checks10($digits)) {
                return self::to13($digits);
            }
        }

        return null;
    }

    /** ISBN-10 d'un ISBN-13 en 978 (la forme que la BnF garde dans les notices anciennes), null pour un 979. */
    public static function to10(string $isbn13): ?string
    {
        if (strlen($isbn13) !== 13 || ! str_starts_with($isbn13, '978') || ! ctype_digit($isbn13)) {
            return null;
        }
        $body = substr($isbn13, 3, 9);
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += (int) $body[$i] * (10 - $i);
        }
        $check = (11 - $sum % 11) % 11;

        return $body.($check === 10 ? 'X' : (string) $check);
    }

    /** Présentation lisible : « 978-2-9535807-1-6 » n'est pas reconstructible sans table des préfixes, on groupe 3-10. */
    public static function display(string $isbn13): string
    {
        return substr($isbn13, 0, 3).'-'.substr($isbn13, 3);
    }

    private static function checks13(string $d): bool
    {
        if (! ctype_digit($d)) {
            return false;
        }
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $d[$i] * ($i % 2 === 0 ? 1 : 3);
        }

        return (10 - $sum % 10) % 10 === (int) $d[12];
    }

    private static function checks10(string $d): bool
    {
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            if (! ctype_digit($d[$i])) {
                return false;
            }
            $sum += (int) $d[$i] * (10 - $i);
        }
        $sum += $d[9] === 'X' ? 10 : (ctype_digit($d[9]) ? (int) $d[9] : -100);

        return $sum % 11 === 0;
    }

    private static function to13(string $isbn10): string
    {
        $body = '978'.substr($isbn10, 0, 9);
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $body[$i] * ($i % 2 === 0 ? 1 : 3);
        }

        return $body.((10 - $sum % 10) % 10);
    }
}

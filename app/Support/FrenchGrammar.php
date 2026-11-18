<?php

namespace App\Support;

/**
 * Grammaire française des textes générés des fiches (chronologie, titres de portion, phases) :
 *
 *   - élision et contraction de l'article devant un nom de lieu : « d’Ambert », « d’Évry »,
 *     « du Mans », « des Aubrais », « au Mans », « aux Aubrais » (jamais « de Ambert »,
 *     « de Le Puley », « à Le Mans ») ;
 *   - « 1er » pour le premier jour du mois (« 1er décembre 1950 », jamais « 1 décembre ») ;
 *   - noms de lieux écrits comme dans la liste des gares de la ligne (« Saillat-Chassenon »,
 *     pas « Saillat - Chassenon ») : voir LineChronicle.
 *
 * Seules les parties générées d'un texte sont reprises : un passage cité entre guillemets
 * « … » (phrase d'une source, titre d'article ou de rubrique) n'est jamais modifié. « H » initial
 * laissé tel quel (h muet ou aspiré : impossible à décider sans dictionnaire).
 */
final class FrenchGrammar
{
    /** Apostrophe typographique (celle de lang/fr/lines.php). */
    public const APOS = '’';

    /** Initiales voyelles (majuscules, accentuées comprises) qui entraînent l'élision. */
    private const VOWELS = 'AEIOUYÀÂÄÉÈÊËÎÏÔÖÛÜŒÆ';

    private const MONTHS = 'janvier|février|mars|avril|mai|juin|juillet|août|septembre|octobre|novembre|décembre';

    /** « de » devant un lieu : « de Troyes », « d’Ambert », « du Mans », « des Aubrais ». */
    public static function de(string $place): string
    {
        $place = trim($place);
        if (preg_match('/^(Le|Les)[ -](?=\p{Lu})(.+)$/su', $place, $m)) {
            return ($m[1] === 'Le' ? 'du ' : 'des ').$m[2];
        }

        return preg_match('/^['.self::VOWELS.']/u', $place) ? 'd'.self::APOS.$place : 'de '.$place;
    }

    /** « à » devant un lieu : « à Troyes », « au Mans », « aux Aubrais ». */
    public static function a(string $place): string
    {
        $place = trim($place);
        if (preg_match('/^(Le|Les)[ -](?=\p{Lu})(.+)$/su', $place, $m)) {
            return ($m[1] === 'Le' ? 'au ' : 'aux ').$m[2];
        }

        return 'à '.$place;
    }

    /** « de X à Y » (« D’Ambert à Arlanc » en tête de titre). */
    public static function between(string $from, string $to, bool $capital = false): string
    {
        $text = self::de($from).' '.self::a($to);

        return $capital ? self::ucfirst($text) : $text;
    }

    /** « près de X » (« Près d’Évry » en tête de titre). */
    public static function near(string $place, bool $capital = false): string
    {
        $text = 'près '.self::de($place);

        return $capital ? self::ucfirst($text) : $text;
    }

    /** Première lettre en capitale (multioctet). */
    public static function ucfirst(string $text): string
    {
        return mb_strtoupper(mb_substr($text, 0, 1)).mb_substr($text, 1);
    }

    /**
     * Texte généré corrigé : élisions et contractions devant un lieu, « 1er » du mois. Les
     * passages entre guillemets « … » (citations) restent tels quels.
     */
    public static function fix(string $text): string
    {
        $parts = preg_split('/(«[^»]*»)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$text];
        foreach ($parts as $i => $part) {
            if ($i % 2 === 1) {
                continue; // citation
            }
            // « de Le Mans » → « du Mans », « De Les Aubrais » → « Des Aubrais ».
            $part = (string) preg_replace_callback('/(?<!\p{L})([Dd])e (Le|Les)[ -](?=\p{Lu})/u',
                fn ($m) => ($m[1] === 'D' ? 'D' : 'd').($m[2] === 'Le' ? 'u ' : 'es '), $part);
            // « à Le Mans » → « au Mans », « à Les Aubrais » → « aux Aubrais ».
            $part = (string) preg_replace_callback('/(?<!\p{L})([àÀ]) (Le|Les)[ -](?=\p{Lu})/u',
                fn ($m) => ($m[1] === 'À' ? 'Au' : 'au').($m[2] === 'Les' ? 'x ' : ' '), $part);
            // « de Ambert » → « d’Ambert » (lieu ou nom propre à initiale voyelle).
            $part = (string) preg_replace('/(?<!\p{L})([Dd])e (?=['.self::VOWELS.'])/u', '$1'.self::APOS, $part);
            $parts[$i] = self::firstOfMonth($part);
        }

        return implode('', $parts);
    }

    /** « 1 décembre 1950 » → « 1er décembre 1950 ». */
    public static function firstOfMonth(string $text): string
    {
        return (string) preg_replace('/(?<![\d\p{L}])1 ('.self::MONTHS.')(?!\p{L})/u', '1er $1', $text);
    }
}

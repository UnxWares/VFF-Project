<?php

namespace App\Services\Materials;

/**
 * Lecture de l'infobox « Infobox Matériel ferroviaire » d'un article Wikipédia en français : la
 * catégorie annoncée (`Type`, `Type2`, `Motorisation`, par exemple « automotrice bimode ») et
 * l'effectif en service (`Effectif`, par exemple « 1 au 13 07 2026 »).
 *
 * Seul ce que l'article écrit est repris : un effectif sans date lisible, ou une phrase qui ne
 * commence pas par un nombre, ne donne rien. Aucune valeur n'est déduite.
 */
final class WikipediaMaterialInfobox
{
    private const TEMPLATE = 'Infobox Matériel ferroviaire';

    private const MONTHS = [
        'janvier' => 1, 'février' => 2, 'fevrier' => 2, 'mars' => 3, 'avril' => 4, 'mai' => 5, 'juin' => 6,
        'juillet' => 7, 'août' => 8, 'aout' => 8, 'septembre' => 9, 'octobre' => 10, 'novembre' => 11,
        'décembre' => 12, 'decembre' => 12,
    ];

    /**
     * Paramètres des blocs « Début » et « Identification » de l'infobox, en texte lisible, clés en
     * minuscules. Vide si l'article n'a pas cette infobox. Le premier bloc qui définit une clé gagne.
     *
     * @return array<string, string>
     */
    public static function parameters(string $wikitext): array
    {
        $out = [];
        $offset = 0;
        while (($start = stripos($wikitext, '{{'.self::TEMPLATE.'/', $offset)) !== false) {
            $end = self::closing($wikitext, $start);
            $block = substr($wikitext, $start + 2, $end - $start - 2);
            $offset = $end + 2;
            [$name, $body] = array_pad(explode('|', $block, 2), 2, '');
            if (! preg_match('~/(?:Début|Identification)$~iu', trim($name))) {
                continue;
            }
            foreach (self::split($body) as $param) {
                if (! str_contains($param, '=')) {
                    continue;
                }
                [$key, $value] = explode('=', $param, 2);
                $key = mb_strtolower(trim($key));
                $text = self::text($value);
                if ($key !== '' && $text !== '' && ! isset($out[$key])) {
                    $out[$key] = $text;
                }
            }
        }

        return $out;
    }

    /**
     * La catégorie annoncée par l'article est « bimode » (`Type` ou `Type2`). `Motorisation` ne compte pas :
     * un article de famille y écrit « électrique ou bimode » (Régiolis, AGC, Traxx), sans que la série elle-même
     * soit bimode.
     *
     * @param  array<string, string>  $parameters
     */
    public static function saysBimode(array $parameters): bool
    {
        foreach (['type', 'type2'] as $key) {
            if (preg_match('/\bbi-?modes?\b/iu', $parameters[$key] ?? '') === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Effectif en service lu dans le paramètre `Effectif` : nombre d'engins et date à laquelle
     * l'article le donne (jour, mois ou année seulement). Nul si le texte ne commence pas par un
     * nombre ou si aucune date n'est lisible.
     *
     * @return array{count: int, as_of: string, precision: string}|null `as_of` : AAAA-MM-JJ, AAAA-MM ou AAAA
     */
    public static function fleet(?string $text): ?array
    {
        if ($text === null || preg_match('/^\s*(?:environ\s+|~\s*)?(\d{1,3}(?:[\s\x{a0}\x{202f}]\d{3})*|\d+)(?!\d)/u', $text, $m) !== 1) {
            return null;
        }
        $count = (int) preg_replace('/\D/', '', $m[1]);
        $rest = substr($text, strlen($m[0]));

        $asOf = null;
        $precision = '';
        if (preg_match('/\b(\d{1,2})[\s\/.-](\d{1,2})[\s\/.-]((?:19|20)\d{2})\b/u', $rest, $d) === 1 && (int) $d[2] >= 1 && (int) $d[2] <= 12 && (int) $d[1] >= 1 && (int) $d[1] <= 31) {
            $asOf = sprintf('%04d-%02d-%02d', $d[3], $d[2], $d[1]);
            $precision = 'day';
        } elseif (preg_match('/\b(?:(\d{1,2})(?:er)?\s+)?('.implode('|', array_keys(self::MONTHS)).')\s+((?:19|20)\d{2})\b/iu', $rest, $d) === 1) {
            $month = self::MONTHS[mb_strtolower($d[2])];
            $day = (int) $d[1];
            $asOf = $day >= 1 && $day <= 31 ? sprintf('%04d-%02d-%02d', $d[3], $month, $day) : sprintf('%04d-%02d', $d[3], $month);
            $precision = $day >= 1 && $day <= 31 ? 'day' : 'month';
        } elseif (preg_match('/\b((?:19|20)\d{2})\b/u', $rest, $d) === 1) {
            $asOf = $d[1];
            $precision = 'year';
        }

        return $asOf !== null ? ['count' => $count, 'as_of' => $asOf, 'precision' => $precision] : null;
    }

    /** Position de la fin (`}}`) du modèle ouvert en `$start`, accolades imbriquées comprises. */
    private static function closing(string $text, int $start): int
    {
        $depth = 0;
        $length = strlen($text);
        for ($i = $start; $i < $length - 1; $i++) {
            $pair = $text[$i].$text[$i + 1];
            if ($pair === '{{') {
                $depth++;
                $i++;
            } elseif ($pair === '}}') {
                $depth--;
                $i++;
                if ($depth === 0) {
                    return $i - 1;
                }
            }
        }

        return $length - 2;
    }

    /**
     * Paramètres séparés par `|` hors des modèles et des liens imbriqués.
     *
     * @return list<string>
     */
    private static function split(string $body): array
    {
        $parts = [];
        $current = '';
        $depth = 0;
        $length = strlen($body);
        for ($i = 0; $i < $length; $i++) {
            $pair = substr($body, $i, 2);
            if ($pair === '{{' || $pair === '[[') {
                $depth++;
                $current .= $pair;
                $i++;
            } elseif ($pair === '}}' || $pair === ']]') {
                $depth--;
                $current .= $pair;
                $i++;
            } elseif ($body[$i] === '|' && $depth === 0) {
                $parts[] = $current;
                $current = '';
            } else {
                $current .= $body[$i];
            }
        }
        $parts[] = $current;

        return $parts;
    }

    /** Texte lisible d'une valeur : modèles et liens simplifiés, balises retirées. */
    private static function text(string $value): string
    {
        $value = (string) preg_replace('/<br\s*\/?>/iu', ' ; ', $value);
        $value = (string) preg_replace('/<!--.*?-->/su', '', $value);
        $value = (string) preg_replace('/<ref[^>\/]*\/>|<ref[^>]*>.*?<\/ref>/su', '', $value);
        $value = trim(strip_tags($value));

        return WikipediaMaterialSections::plain($value);
    }
}

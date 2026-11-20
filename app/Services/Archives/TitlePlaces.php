<?php

namespace App\Services\Archives;

use Illuminate\Support\Str;

/**
 * Noms de lieux dans les titres de documents anciens : découpage en mots, clé de comparaison et repérage
 * d'un lieu dans un titre, avec des garde-fous contre les noms composés. À ne pas confondre avec
 * App\Services\PlaceNames, qui compare les libellés des tracés.
 *
 * Clé d'un nom : mots en minuscules sans accents, « St » et « Ste » écrits en entier, article de tête
 * retiré (« Le Havre » → `havre`, « L'Aigle » → `aigle`, « St-Junien » → `saint junien`).
 *
 * Un lieu n'est reconnu dans un titre que s'il y forme un nom entier : il commence par une majuscule
 * (sauf titre tout en capitales), n'est pas collé par un trait d'union à un autre mot
 * (« Paris-Lyon-Méditerranée »), ne nomme pas une voie urbaine (« rue de Paris »), n'est pas précédé de
 * « Saint » ni suivi d'un mot à majuscule (« Bourg Madame ») ou d'un mot de liaison suivi d'un tel mot
 * (« Villefranche de Conflent », « Villefranche sur Mer »).
 */
final class TitlePlaces
{
    /** Caractères de non-classement des titres UNIMARC. */
    private const NON_SORT = ["\u{0088}", "\u{0089}", "\u{0098}", "\u{009C}"];

    /** Articles retirés en tête d'un nom de lieu. */
    private const ARTICLES = ['le', 'la', 'les', 'l'];

    /** Mots de liaison des noms composés de communes. */
    private const LINKS = ['sur', 'sous', 'en', 'de', 'du', 'des', 'les', 'le', 'la', 'aux', 'lez', 'd', 'l'];

    /**
     * Noms de communes qui sont aussi des mots courants des titres (« Port », « Mer », « Plan ») : jamais
     * comptés comme lieux, la précision passe avant.
     */
    private const COMMON_WORDS = [
        'port', 'mer', 'plan', 'vue', 'pont', 'bois', 'val', 'mont', 'gare', 'gares', 'ligne', 'carte', 'route',
        'canal', 'voie', 'ville', 'vallee', 'lac', 'ile', 'cap', 'nord', 'sud', 'est', 'ouest', 'centre', 'france',
        'saint', 'sainte', 'notre dame', 'chemin', 'fer', 'halte', 'poste', 'usine', 'mine', 'mines', 'quai', 'bourse',
    ];

    /** Voies urbaines nommées d'après une ville (« rue de Paris », « avenue de Lyon ») : pas le lieu lui-même. */
    private const STREETS = ['rue', 'rues', 'avenue', 'boulevard', 'bd', 'place', 'quai', 'faubourg', 'porte', 'cours', 'allee', 'impasse', 'chaussee', 'barriere'];

    /** Mots qui font d'un nom de gare une installation, pas un lieu (« Chemin de Fer de Charente-Limousine »). */
    private const NOT_A_PLACE = '/\b(chemin de fer|chemins de fer|gare|halte|arret|poste|bifurcation|raccordement|embranchement|triage|depot|atelier|usine|sucrerie|carriere|ligne)\b/';

    /**
     * Mots d'un texte, avec leur forme en minuscules (clé), la présence d'une majuscule initiale et ce qui
     * les sépare du mot précédent (« - » pour un trait d'union collé).
     *
     * @return list<array{lower: string, upper: bool, sep: string}>
     */
    public static function tokens(string $text): array
    {
        $ascii = Str::ascii(str_replace(self::NON_SORT, '', $text));
        preg_match_all('/([^A-Za-z0-9]*)([A-Za-z0-9]+)/', $ascii, $m, PREG_SET_ORDER);
        $tokens = [];
        foreach ($m as [, $sep, $word]) {
            $tokens[] = ['lower' => self::word(strtolower($word)), 'upper' => ctype_upper($word[0]), 'sep' => $sep];
        }

        return $tokens;
    }

    /** Clé de comparaison d'un nom de lieu, ou null s'il n'en reste rien d'utilisable. */
    public static function key(string $name): ?string
    {
        $words = array_column(self::tokens($name), 'lower');
        if (count($words) > 1 && in_array($words[0], self::ARTICLES, true)) {
            array_shift($words);
        }
        $key = implode(' ', $words);
        if ($key === '' || strlen($key) < 2 || ctype_digit(str_replace(' ', '', $key)) || in_array($key, self::COMMON_WORDS, true)) {
            return null;
        }

        return preg_match(self::NOT_A_PLACE, $key) === 1 ? null : $key;
    }

    /** Terme d'une requête CQL : le nom sans article de tête, sans guillemets ni ponctuation. */
    public static function queryTerm(string $name): string
    {
        $name = trim((string) preg_replace('/^(le|la|les|l)\s*[\'’ ]\s*/iu', '', trim($name)));

        return trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $name));
    }

    /** Titre écrit tout en capitales : la casse n'aide plus à reconnaître les noms propres. */
    public static function allCaps(string $text): bool
    {
        $letters = preg_replace('/[^\p{L}]/u', '', $text) ?? '';
        if (mb_strlen($letters) < 8) {
            return false;
        }

        return mb_strlen((string) preg_replace('/[^\p{Lu}]/u', '', $letters)) >= 0.8 * mb_strlen($letters);
    }

    /**
     * Lieux connus nommés dans un titre, du premier au dernier, le plus long d'abord à chaque position.
     *
     * @param  list<array{lower: string, upper: bool, sep: string}>  $tokens  mots du titre (tokens())
     * @param  array<string, mixed>  $keys  clés connues (en clés du tableau)
     * @param  int  $maxWords  nombre de mots du plus long nom connu
     * @return list<array{key: string, start: int, end: int}> `end` exclu
     */
    public static function find(array $tokens, array $keys, int $maxWords, bool $allCaps = false): array
    {
        $found = [];
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            for ($n = min($maxWords, $count - $i); $n >= 1; $n--) {
                $key = implode(' ', array_column(array_slice($tokens, $i, $n), 'lower'));
                if (! isset($keys[$key]) || ! self::standsAlone($tokens, $i, $i + $n, $allCaps)) {
                    continue;
                }
                $found[] = ['key' => $key, 'start' => $i, 'end' => $i + $n];
                $i += $n - 1;
                break;
            }
        }

        return $found;
    }

    /** Le nom des mots $start à $end (exclu) est-il un nom entier, et pas le morceau d'un nom composé ? */
    private static function standsAlone(array $tokens, int $start, int $end, bool $allCaps): bool
    {
        if (! $allCaps && ! $tokens[$start]['upper']) {
            return false;
        }
        if ($start > 0) {
            if (self::glued($tokens[$start]['sep']) || in_array($tokens[$start - 1]['lower'], ['saint', 'sainte'], true)) {
                return false;
            }
            if ($start > 1 && in_array($tokens[$start - 1]['lower'], ['de', 'du', 'des', 'd'], true)
                && in_array($tokens[$start - 2]['lower'], self::STREETS, true)) {
                return false;
            }
        }
        $next = $tokens[$end] ?? null;
        if ($next === null) {
            return true;
        }
        if (self::glued($next['sep'])) {
            return false;
        }
        $spaceOnly = trim($next['sep']) === '';
        if (! $allCaps && $spaceOnly && $next['upper']) {
            return false;
        }
        $after = $tokens[$end + 1] ?? null;
        if ($spaceOnly && $after !== null && in_array($next['lower'], self::LINKS, true)
            && preg_match('/^[\s\'’-]*$/u', $after['sep']) === 1 && ($after['upper'] || ($allCaps && ! in_array($after['lower'], self::LINKS, true)))) {
            return false;
        }

        return true;
    }

    /** Trait d'union collé (« Paris-Lyon ») : pas un tiret de séparation entouré d'espaces (« Paris - Lyon »). */
    private static function glued(string $sep): bool
    {
        return preg_match('/^[-‐]$/u', $sep) === 1;
    }

    /** « st » et « ste » abrégés, écrits en entier. */
    private static function word(string $lower): string
    {
        return match ($lower) {
            'st' => 'saint',
            'ste' => 'sainte',
            default => $lower,
        };
    }
}

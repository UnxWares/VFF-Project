<?php

namespace App\Support\Search;

use Illuminate\Support\Facades\DB;
use Transliterator;

/**
 * Normalisation commune de toute la recherche par nom (en-tête, accueil, carte, pages Gares, Lignes, Accidents,
 * Matériel, API REST, GraphQL et MCP), écrite deux fois à l'identique : en PHP pour la requête (key()) et en SQL
 * pour les noms en base (sqlKeyFormula(), fonction immuable `vff_search_key` de la migration 2026_12_12_000000,
 * calculée une fois dans les colonnes `search_name` et `search_all`, indexées en trigrammes). tests/Unit/Search/SearchTextTest.php vérifie que les deux donnent
 * la même clé.
 *
 * Clé d'un texte : minuscules sans accents, « s/s » lu « sous » et « s/ » lu « sur », lettres et chiffres
 * séparés (« BB7600 » = « BB 7600 »), toute autre ponctuation (tirets, apostrophes droites et typographiques,
 * points) lue comme une espace, abréviations françaises développées (St, Ste, Sts, Stes, N.-D., Gd, Gde, Mt,
 * Ft, G., s., lez), mots séparés par une espace, avec une espace en tête : « St-Hilaire-de-Chaléons » donne
 * « ␣saint hilaire de chaleons », si bien que « LIKE '% hil%' » cherche un début de mot.
 *
 *   SearchText::key('St-Hilaire-de-Chaléons')     // " saint hilaire de chaleons"
 *   SearchText::key("Fontenay-s/s-Bois")          // " fontenay sous bois"
 *   SearchText::core(' saint hilaire de chaleons') // " saint hilaire chaleons" (sans les petits mots)
 */
final class SearchText
{
    /** Petits mots facultatifs dans une requête (articles, prépositions, « lès » devenu « les »). */
    public const STOPWORDS = ['de', 'du', 'des', 'd', 'la', 'le', 'les', 'l', 'en', 'et', 'a', 'au', 'aux'];

    /**
     * Abréviations développées, dans l'ordre d'application (les groupes de deux lettres d'abord).
     *
     * @var array<string, string>
     */
    public const ABBREVIATIONS = [
        'n d' => 'notre dame',
        's s' => 'sous',
        'st' => 'saint',
        'ste' => 'sainte',
        'sts' => 'saints',
        'stes' => 'saintes',
        'gd' => 'grand',
        'gde' => 'grande',
        'mt' => 'mont',
        'ft' => 'fort',
        'g' => 'gare',
        's' => 'sur',
        'lez' => 'les',
    ];

    private static ?Transliterator $ascii = null;

    private static ?bool $sqlReady = null;

    /** Clé de recherche d'un texte (voir l'en-tête), « » pour un texte vide ; `$expand` faux : abréviations gardées. */
    public static function key(?string $text, bool $expand = true): string
    {
        if ($text === null) {
            return '';
        }
        self::$ascii ??= Transliterator::create('Latin-ASCII; Lower()');
        $s = self::$ascii?->transliterate($text) ?: mb_strtolower($text);
        $s = (string) preg_replace('~(^|[^a-z0-9])s/s(?=[^a-z0-9]|$)~', '$1 sous ', $s);
        $s = (string) preg_replace('~(^|[^a-z0-9])s/~', '$1 sur ', $s);
        $s = (string) preg_replace('~([a-z])([0-9])~', '$1 $2', $s);
        $s = (string) preg_replace('~([0-9])([a-z])~', '$1 $2', $s);
        $s = trim((string) preg_replace('~[^a-z0-9]+~', ' ', $s));
        foreach ($expand ? self::ABBREVIATIONS : [] as $short => $long) {
            $s = (string) preg_replace('~\b'.$short.'\b~', $long, $s);
        }

        return $s === '' ? '' : ' '.$s;
    }

    /** Clé sans les petits mots (STOPWORDS) : « Le Mans » et « Mans » ont le même cœur. */
    public static function core(string $key): string
    {
        $words = array_values(array_filter(explode(' ', trim($key)), fn (string $w) => $w !== '' && ! in_array($w, self::STOPWORDS, true)));

        return $words === [] ? '' : ' '.implode(' ', $words);
    }

    /** Expression SQL du cœur d'une clé (voir core()). */
    public static function sqlCore(string $keyExpr): string
    {
        $stop = implode('|', self::STOPWORDS);

        return "rtrim(regexp_replace(regexp_replace({$keyExpr}, '\\m({$stop})\\M', '', 'g'), ' {2,}', ' ', 'g'))";
    }

    /**
     * Formule SQL de la clé, identique à key() ; `$unaccent` : fonction qui ôte les accents (`public.vff_unaccent`,
     * immuable, dans la fonction indexée ; `unaccent` en ligne).
     */
    public static function sqlKeyFormula(string $expr, string $unaccent = 'public.vff_unaccent'): string
    {
        $s = "lower({$unaccent}(COALESCE({$expr}, '')))";
        $s = "regexp_replace({$s}, '(^|[^a-z0-9])s/s(?=[^a-z0-9]|\$)', '\\1 sous ', 'g')";
        $s = "regexp_replace({$s}, '(^|[^a-z0-9])s/', '\\1 sur ', 'g')";
        $s = "regexp_replace({$s}, '([a-z])([0-9])', '\\1 \\2', 'g')";
        $s = "regexp_replace({$s}, '([0-9])([a-z])', '\\1 \\2', 'g')";
        $s = "btrim(regexp_replace({$s}, '[^a-z0-9]+', ' ', 'g'))";
        foreach (self::ABBREVIATIONS as $short => $long) {
            $s = "regexp_replace({$s}, '\\m{$short}\\M', '{$long}', 'g')";
        }

        return "rtrim(' ' || {$s})";
    }

    /**
     * Les colonnes `search_name` et `search_all` existent-elles (migration 2026_12_12_000000 passée) ? Lu une fois
     * par processus ; sans elles, SearchFields écrit la formule en ligne (même résultat, sans index).
     */
    public static function sqlReady(): bool
    {
        return self::$sqlReady ??= DB::selectOne(
            "SELECT EXISTS (SELECT 1 FROM pg_attribute WHERE attrelid = to_regclass('materials') AND attname = 'search_all' AND NOT attisdropped) AS ok",
        )?->ok === true;
    }

    /** Oublie l'état lu par sqlReady() (tests). */
    public static function forgetSqlState(): void
    {
        self::$sqlReady = null;
    }
}

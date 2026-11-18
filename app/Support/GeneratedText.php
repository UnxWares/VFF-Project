<?php

namespace App\Support;

/**
 * Textes générés par VFF, dans la langue de la page.
 *
 * Les commandes du pipeline écrivent en base des textes FRANÇAIS bâtis sur des
 * gabarits (notes des fiches gare, descriptions d'événements…). Ces gabarits
 * vivent dans lang/fr/generated.php (version française = celle stockée) et sont
 * traduits dans lang/{en,de}/generated.php. À l'affichage, un texte stocké est
 * reconnu (motif tiré du gabarit français, « :x » = valeur) puis rendu dans la
 * langue de la page ; les noms propres et nombres passent tels quels.
 *
 * Valeurs mises en forme selon la langue :
 *   - line : nom d'une ligne ; en français dans la phrase (« ligne de Briouze à
 *     La Ferté-Macé », jamais « la ligne Ligne de … »), ailleurs le nom propre
 *     tel quel (« Ligne de Briouze à La Ferté-Macé ») ;
 *   - decade : « 1980 » (fr), « 1980s » (en), « 1980er-Jahren » (de).
 *
 * Un texte non reconnu (citation d'une source « … », texte rédigé à la main)
 * reste tel quel et la réponse signale sa langue (fr) : la page le marque
 * lang="fr" au lieu de le présenter comme traduit.
 */
final class GeneratedText
{
    /** Séparateur des parties d'une note composée (BuildStationEras). */
    public const SEPARATOR = ' · ';

    /** @var array<string, string> cache des motifs */
    private static array $patterns = [];

    /** Gabarits des notes de gare (generated.station.*), du plus spécifique au plus général. */
    public static function stationKeys(): array
    {
        return array_map(fn ($k) => 'station.'.$k, array_keys((array) trans('generated.station', [], 'fr')));
    }

    /** Texte français d'un gabarit (génération). */
    public static function fr(string $key, array $params = []): string
    {
        return self::render($key, $params, 'fr');
    }

    /** Gabarit rendu dans une langue (valeurs brutes : nom de ligne, année de la décennie…). */
    public static function render(string $key, array $params, string $locale): string
    {
        foreach ($params as $name => $value) {
            $params[$name] = self::formatParam((string) $name, (string) $value, $locale);
        }

        return (string) trans('generated.'.$key, $params, $locale);
    }

    /** Mise en forme d'une valeur selon la langue (cf. en-tête). */
    public static function formatParam(string $name, string $value, string $locale): string
    {
        return match ($name) {
            'line' => $locale === 'fr' ? self::lineInSentence($value) : $value,
            'decade' => match ($locale) {
                'en' => $value.'s',
                'de' => $value.'er-Jahren',
                default => $value,
            },
            default => $value,
        };
    }

    /** « Ligne de X à Y » → « ligne de X à Y » ; autre nom → « ligne « Nom » ». */
    public static function lineInSentence(string $name): string
    {
        $name = trim($name);

        return preg_match('/^Ligne\s+(.+)$/u', $name, $m) ? 'ligne '.$m[1] : 'ligne « '.$name.' »';
    }

    /** Même règle en SQL (génération en base), sur une expression de nom de ligne. */
    public static function lineInSentenceSql(string $nameExpr): string
    {
        return "CASE WHEN {$nameExpr} ~ '^Ligne\\s' THEN 'ligne '||regexp_replace({$nameExpr}, '^Ligne\\s+', '') ELSE 'ligne « '||{$nameExpr}||' »' END";
    }

    /**
     * Nom propre d'une ligne retrouvé dans un texte stocké : « ligne de X » (forme
     * actuelle), « ligne Ligne de X » (ancienne forme, doublon), « ligne « Nom » ».
     */
    public static function lineName(string $inSentence): string
    {
        $s = trim((string) preg_replace('/^ligne\s+/u', '', trim($inSentence)));
        if (preg_match('/^«\s*(.+?)\s*»$/u', $s, $m)) {
            return $m[1];
        }
        if (preg_match('/^(?:de|du|des|d[\'’])/u', $s)) {
            return 'Ligne '.$s;
        }

        return $s;
    }

    /**
     * Reconnaît un texte stocké parmi des gabarits.
     *
     * @param  list<string>  $keys
     * @return array{key: string, params: array<string, string>}|null valeurs brutes (nom propre de la ligne…)
     */
    public static function recognize(string $text, array $keys): ?array
    {
        $text = trim($text);
        foreach ($keys as $key) {
            if (! preg_match(self::pattern($key), $text, $m)) {
                continue;
            }
            $params = [];
            foreach ($m as $name => $value) {
                if (is_string($name)) {
                    $params[$name] = $name === 'line' ? self::lineName($value) : $value;
                }
            }

            return ['key' => $key, 'params' => $params];
        }

        return null;
    }

    /** Motif d'un gabarit français : texte littéral, « :x » = (?<x>.+?). */
    public static function pattern(string $key): string
    {
        if (isset(self::$patterns[$key])) {
            return self::$patterns[$key];
        }
        $template = (string) trans('generated.'.$key, [], 'fr');
        $parts = preg_split('/:([a-z_]+)/', $template, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$template];
        $rx = '';
        foreach ($parts as $i => $part) {
            $rx .= $i % 2 === 0 ? preg_quote($part, '/') : '(?<'.$part.'>.+?)';
        }

        return self::$patterns[$key] = '/^'.$rx.'$/u';
    }

    /**
     * Note de gare (station_eras.note, station_events.note) dans la langue de la page.
     * Note composée « A · B » : chaque partie à part.
     *
     * @return array{text: ?string, lang: ?string} lang : « fr » si une partie n'a pas pu être traduite
     */
    public static function stationNote(?string $note, string $locale): array
    {
        if ($note === null || trim($note) === '') {
            return ['text' => $note, 'lang' => null];
        }
        $keys = self::stationKeys();
        $out = [];
        $untranslated = false;
        foreach (explode(self::SEPARATOR, $note) as $part) {
            $hit = self::recognize($part, $keys);
            if ($hit === null) {
                $out[] = trim($part);
                $untranslated = true;

                continue;
            }
            $out[] = self::render($hit['key'], $hit['params'], $locale);
        }

        return ['text' => implode(self::SEPARATOR, $out), 'lang' => $untranslated && $locale !== 'fr' ? 'fr' : null];
    }
}

<?php

namespace App\Services;

/**
 * Lecture des dates OpenHistoricalMap (start_date / end_date et leurs variantes
 * :edtf) vers une date + précision exploitables par `line_events`.
 *
 * OHM exige `start_date` / `end_date` au format ISO 8601 réduit (AAAA, AAAA-MM,
 * AAAA-MM-JJ) et réserve l'incertitude aux clés `*:edtf` (Extended Date/Time
 * Format : « 1890~ », « 1890? », « 199X », intervalles « 1890/1895 », bornes
 * ouvertes « /1866 », « ..1890 », ensembles « [..1890] »). En pratique on
 * rencontre aussi, sur les voies françaises, du texte libre (« hiver
 * 1997/1998 », « vers 1890 », « été/automne 1932 », « approx. 1890 »).
 *
 * Règles (choix de prudence pour des cartes par décennie) :
 *   - la valeur ISO, quand elle est lisible, fait foi pour la date : c'est celle
 *     que le rendu OHM utilise et que le contributeur a choisie ;
 *   - une clé :edtf incertaine (intervalle, ~, ?, X, borne ouverte, texte)
 *     rétrograde la précision à l'année et marque la date « approximative » ;
 *   - sans ISO lisible, on retient la borne la PLUS TARDIVE de l'intervalle
 *     (l'événement avait sûrement eu lieu à cette date) ;
 *   - « 199X » → 1990 (début de la décennie), approximatif ;
 *   - années hors [1700, an courant + 30] et dates négatives : ignorées.
 */
final class OhmDate
{
    /**
     * Date d'un élément OHM pour une borne donnée.
     *
     * @param  array<string, mixed>  $tags
     * @param  'start'|'end'  $bound
     * @return array{date: string, year: int, precision: 'day'|'month'|'year', approx: bool, raw: string}|null
     */
    public static function fromTags(array $tags, string $bound): ?array
    {
        $iso = isset($tags["{$bound}_date"]) ? trim((string) $tags["{$bound}_date"]) : '';
        $edtf = isset($tags["{$bound}_date:edtf"]) ? trim((string) $tags["{$bound}_date:edtf"]) : '';
        if ($iso === '' && $bound === 'start' && isset($tags['opening_date'])) {
            $iso = trim((string) $tags['opening_date']);
        }

        $parsed = $iso !== '' ? self::parse($iso) : null;
        if ($parsed && $edtf !== '' && self::isUncertain($edtf)) {
            $parsed = self::degrade($parsed);
        }
        if (! $parsed && $edtf !== '') {
            $parsed = self::parse($edtf);
        }
        if (! $parsed) {
            return null;
        }
        $parsed['raw'] = trim($iso.($edtf !== '' ? " (edtf : {$edtf})" : ''));

        return $parsed;
    }

    /**
     * Analyse une valeur de date isolée (ISO, EDTF ou texte libre).
     *
     * @return array{date: string, year: int, precision: 'day'|'month'|'year', approx: bool}|null
     */
    public static function parse(string $value): ?array
    {
        $v = trim($value);
        if ($v === '' || str_starts_with($v, '-')) {
            return null; // vide ou avant notre ère
        }
        $v = preg_replace('/T\d{2}(:\d{2}){0,2}.*$/', '', $v); // heure éventuelle

        // ISO strict : AAAA, AAAA-MM, AAAA-MM-JJ.
        if (preg_match('/^(\d{4})(?:-(\d{1,2}))?(?:-(\d{1,2}))?$/', $v, $m)) {
            return self::build((int) $m[1], isset($m[2]) ? (int) $m[2] : null, isset($m[3]) ? (int) $m[3] : null, false);
        }

        $approx = (bool) preg_match('/[~?%]|\.\.|\/|\[|\{|x|vers|env|approx|circa|ca\.|c\.|avant|apr[eè]s|before|after|hiver|printemps|[ée]t[ée]|automne|d[ée]but|fin|milieu/iu', $v);

        // Décennie / siècle non spécifiés : 199X, 19XX.
        if (preg_match('/\b(\d{2,3})[xX]{1,2}\b/', $v, $m)) {
            $year = (int) str_pad($m[1], 4, '0');

            return self::build($year, null, null, true);
        }

        // Intervalle dégénéré « a/a » : date exacte.
        if (preg_match('/^(\d{4}(?:-\d{1,2}){0,2})\/\1$/', $v, $m)) {
            return self::parse($m[1]);
        }

        // Dates complètes présentes (intervalle « a/b », « a..b », liste « [a..b] ») :
        // on garde la plus tardive (une année seule plus tardive l'emporte : « 1833-08-29/1864 »).
        if (preg_match_all('/(\d{4})-(\d{1,2})(?:-(\d{1,2}))?/', $v, $all, PREG_SET_ORDER)) {
            $best = null;
            foreach ($all as $m) {
                $cand = self::build((int) $m[1], (int) $m[2], isset($m[3]) && $m[3] !== '' ? (int) $m[3] : null, $approx);
                if ($cand && (! $best || $cand['date'] > $best['date'])) {
                    $best = $cand;
                }
            }
            preg_match_all('/(?<![\d-])(\d{4})(?![\d-])/', $v, $years);
            $maxYear = $years[1] ? max(array_map('intval', $years[1])) : 0;
            if ($best && $maxYear > $best['year']) {
                return self::build($maxYear, null, null, true);
            }
            if ($best) {
                return $approx ? self::degrade($best) : $best;
            }
        }

        // Années seules (texte libre, intervalles d'années) : la plus tardive.
        if (preg_match_all('/(?<!\d)(\d{4})(?!\d)/', $v, $years)) {
            $year = max(array_map('intval', $years[1]));

            return self::build($year, null, null, true);
        }

        return null;
    }

    /** L'EDTF exprime-t-il une incertitude (vs une date simple recopiée) ? */
    public static function isUncertain(string $edtf): bool
    {
        $parsed = self::parse($edtf);

        return $parsed === null || $parsed['approx'];
    }

    /**
     * @param  array{date: string, year: int, precision: string, approx: bool}  $d
     * @return array{date: string, year: int, precision: 'year', approx: true}
     */
    private static function degrade(array $d): array
    {
        return ['date' => sprintf('%04d-01-01', $d['year']), 'year' => $d['year'], 'precision' => 'year', 'approx' => true];
    }

    /** @return array{date: string, year: int, precision: 'day'|'month'|'year', approx: bool}|null */
    private static function build(int $year, ?int $month, ?int $day, bool $approx): ?array
    {
        if ($year < 1700 || $year > (int) date('Y') + 30) {
            return null;
        }
        if ($month === null || $month < 1 || $month > 12) {
            return ['date' => sprintf('%04d-01-01', $year), 'year' => $year, 'precision' => 'year', 'approx' => $approx];
        }
        if ($day === null || ! checkdate($month, $day, $year)) {
            return ['date' => sprintf('%04d-%02d-01', $year, $month), 'year' => $year, 'precision' => 'month', 'approx' => $approx];
        }

        return ['date' => sprintf('%04d-%02d-%02d', $year, $month, $day), 'year' => $year, 'precision' => 'day', 'approx' => $approx];
    }
}

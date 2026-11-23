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

    /** Nom des champs de l'infobox qui donnent la date de mise en service, par ordre de préférence, et ce qu'ils disent. */
    private const FROM_FIELDS = [
        'mise en service' => 'mise en service',
        'livraison' => 'livraison de la première unité',
        'service commercial' => 'début du service commercial',
        'période de service' => 'début de la période de service',
    ];

    /** Mots qui disent qu'un nombre est un nombre d'engins construits. */
    private const UNIT_NOUNS = 'locomotives?|automotrices?|autorails?|rames?|exemplaires?|locotracteurs?|unités|éléments|engins|prototypes?|fourgons automoteurs|rames automotrices|véhicules';

    /**
     * Années de service écrites par l'article : mise en service (`mise en service`, à défaut `livraison`, `service commercial`
     * ou `période de service`) et retrait (`retrait`, ou fin d'une période de service écrite comme une plage d'années).
     * Seules les années écrites sont reprises (la plus ancienne pour la mise en service, la plus récente pour le retrait) ;
     * rien n'est déduit : un texte « depuis », « en cours », « à partir de », une année à venir, un retrait détaillé par
     * sous-série ou un effectif encore positif ne donnent pas de retrait.
     *
     * @param  array<string, string>  $parameters
     * @return array{from?: array{year: int, field: string, basis: string, quote: string}, to?: array{year: int, field: string, basis: string, quote: string}}
     */
    public static function serviceYears(array $parameters): array
    {
        $out = [];
        foreach (self::FROM_FIELDS as $field => $basis) {
            $text = $parameters[$field] ?? null;
            if ($text === null || trim($text) === '') {
                continue;
            }
            // Le premier champ renseigné fait foi : un texte qu'on ne sait pas lire (série détaillée par engin, date à venir)
            // ne laisse pas la place à un champ moins précis.
            $years = self::years($text);
            if ($years !== null) {
                $out['from'] = ['year' => min($years), 'field' => $field, 'basis' => $basis, 'quote' => self::quote($text)];
            }
            break;
        }

        // Un effectif encore positif : la série roule, le « retrait » ne vaut que pour une partie des engins.
        $fleet = preg_match('/^\s*(\d+)/u', $parameters['effectif'] ?? '', $f) === 1 ? (int) $f[1] : 0;
        $retrait = $parameters['retrait'] ?? null;
        $text = $retrait;
        $field = 'retrait';
        $basis = 'retrait';
        if ($text === null && isset($parameters['période de service']) && preg_match('/^\s*(?:19|18|20)\d{2}\s*[-\x{2013}\/]\s*(?:19|20)\d{2}\s*$/u', $parameters['période de service']) === 1) {
            [$text, $field, $basis] = [$parameters['période de service'], 'période de service', 'fin de la période de service'];
        }
        if ($text !== null && $fleet === 0 && preg_match('/depuis|en cours|à partir|remise|restaur|mises? au type|reconstruit|modernis|[\p{L}\d)]\s*:/iu', $text) !== 1) {
            $years = self::years($text);
            if ($years !== null) {
                $out['to'] = ['year' => max($years), 'field' => $field, 'basis' => $basis, 'quote' => self::quote($text)];
            }
        }
        if (isset($out['from'], $out['to']) && $out['to']['year'] < $out['from']['year']) {
            unset($out['to']);
        }

        return $out;
    }

    /**
     * Nombre d'engins construits, seulement quand l'infobox l'écrit seul (`nombre`, `production totale`, `construction`),
     * éventuellement suivi du nom de l'engin : « 80 locomotives », « 232 exemplaires ». Rien pour « commandées », une année (champ `construction`), une somme,
     * une décomposition ou un article de famille (effectif détaillé par sous-série).
     *
     * @param  array<string, string>  $parameters
     * @return array{value: int, field: string, quote: string}|null
     */
    public static function unitsBuilt(array $parameters): ?array
    {
        if (preg_match('/\b[A-Z]{1,3}\s?\d{3,5}\s*:/u', $parameters['effectif'] ?? '') === 1) {
            return null;
        }
        foreach (['nombre', 'production totale', 'construction'] as $field) {
            $text = trim((string) preg_replace('/-->/', '', $parameters[$field] ?? ''));
            if (preg_match('/^(\d{1,5})(?:\s+(?:'.self::UNIT_NOUNS.'))?$/iu', $text, $m) === 1 && (int) $m[1] > 0 && ! ($field === 'construction' && (int) $m[1] >= 1800 && (int) $m[1] <= 2100)) {
                return ['value' => (int) $m[1], 'field' => $field, 'quote' => self::quote($text)];
            }
        }

        return null;
    }

    /**
     * Constructeur écrit par l'infobox quand c'est un simple nom (ou une liste de noms) : sans chiffre, parenthèse, deux-points,
     * point-virgule ni formule d'attente (« à définir », « choisi ultérieurement »).
     *
     * @param  array<string, string>  $parameters
     */
    public static function builder(array $parameters): ?string
    {
        $text = trim($parameters['constructeur'] ?? '');
        if ($text === '' || mb_strlen($text) > 120 || preg_match('/[\d():;?+]|définir|ultérieurement|inconnu|divers|voir /iu', $text) === 1) {
            return null;
        }

        return $text;
    }

    /**
     * Années (1800 à l'année en cours) écrites dans un texte de date, après retrait de « dans les chemins de fer ». Nul si le texte
     * ne contient aucune année ou une année à venir (livraison annoncée).
     *
     * @return list<int>|null
     */
    private static function years(string $text): ?array
    {
        // Hors parenthèses (« 2006 (construit en 1995) »), avant une transformation, et pas pour une autre série (« BB 8051 : juin 1951 »).
        $text = (string) preg_replace('/dans les chemins de fer|-->|\([^)]*\)?/u', '', $text);
        $text = (string) preg_split('/transformation|modification|reconstruction/iu', $text)[0];
        if (preg_match('/\b[A-Z]{1,3}\s?\d{3,5}\s*:/u', $text) === 1) {
            return null;
        }
        preg_match_all('/(?<!\d)(1[89]\d{2}|20\d{2})(?!\d)/u', $text, $m);
        $years = array_map('intval', $m[1]);
        if ($years === [] || max($years) > (int) date('Y')) {
            return null;
        }

        return $years;
    }

    private static function quote(string $text): string
    {
        return mb_strimwidth(trim($text), 0, 160, '…');
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

<?php

namespace App\Services\Media\OpenData;

/**
 * Petits outils de texte communs aux jeux d'images ouverts : pliage des accents et de la casse pour comparer
 * des noms de communes et de gares, dates en français.
 */
final class OpenDataText
{
    private const MONTHS = [
        'janvier' => 1, 'fevrier' => 2, 'mars' => 3, 'avril' => 4, 'mai' => 5, 'juin' => 6, 'juillet' => 7,
        'aout' => 8, 'septembre' => 9, 'octobre' => 10, 'novembre' => 11, 'decembre' => 12,
        // Dates en anglais (Getty : « about 1861 », « June 1866 »).
        'january' => 1, 'february' => 2, 'march' => 3, 'april' => 4, 'may' => 5, 'june' => 6, 'july' => 7, 'august' => 8,
        'september' => 9, 'october' => 10, 'november' => 11, 'december' => 12,
    ];

    /**
     * Texte lu en Windows-1250 alors qu'il était en Windows-1252 (copies analysées de CSV : « Aprčs » pour « Après »,
     * « ŕ » pour « à ») : rendu en bon UTF-8. Les valeurs sans caractère touché restent identiques.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function repairCp1250(array $row): array
    {
        foreach ($row as $key => $value) {
            if (is_string($value) && preg_match('/[^\x00-\x7F]/', $value) === 1) {
                $bytes = iconv('UTF-8', 'CP1250//IGNORE', $value);
                $fixed = $bytes === false ? false : iconv('CP1252', 'UTF-8//IGNORE', $bytes);
                $row[$key] = $fixed === false || $fixed === '' ? $value : $fixed;
            }
        }

        return $row;
    }

    /**
     * Commune d'une vue parmi une liste de lieux indexés (« Chalon-sur-Saône (arrondissement) / Montchanin / Montchanin (canton) »),
     * sans les arrondissements ni les cantons marqués : celle qui est le début du titre (« Montchanin-les-Mines. Intérieur de la
     * gare. »), à défaut la plus longue qui se lit dans le titre, à défaut l'unique lieu non marqué. Rien sinon (l'arrondissement ne dit
     * pas la commune).
     */
    public static function communeFromList(?string $list, ?string $title, string $separator = ' / '): ?string
    {
        if ($list === null || trim($list) === '') {
            return null;
        }
        $places = array_values(array_filter(
            array_map('trim', explode($separator, $list)),
            fn (string $p): bool => $p !== '' && preg_match('/\((?:arrondissement|canton)\)/iu', $p) !== 1,
        ));
        $head = self::fold(explode('. ', (string) $title, 2)[0]);
        $whole = ' '.self::fold((string) $title).' ';
        foreach ($places as $p) {
            if (self::fold($p) === $head) {
                return $p;
            }
        }
        usort($places, fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));
        foreach ($places as $p) {
            if (self::fold($p) !== '' && str_starts_with($head.' ', self::fold($p).' ')) {
                return $p;
            }
        }
        foreach ($places as $p) {
            if (self::fold($p) !== '' && str_contains($whole, ' '.self::fold($p).' ')) {
                return $p;
            }
        }

        // Un hameau nommé par le titre (« Pontanevaux-la-Chapelle ») : s'il ne reste qu'un lieu non marqué, c'est la commune.
        return count($places) === 1 ? $places[0] : null;
    }

    /**
     * Titre d'une notice de bibliothèque numérique : sans « S. T. » (sans titre) ni crochets autour du titre supposé, coupé au
     * premier « : » (ce qui suit est la série ou la collection : « Gare de Nice : Ministère des travaux publics. Photographies. »).
     *
     * @return array{0: string, 1: ?string} titre, reste
     */
    public static function cleanTitle(string $title): array
    {
        $title = trim(html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        // Typographie du site : tiret simple à la place des tirets demi-cadratin et cadratin des notices.
        $title = str_replace(["\u{2013}", "\u{2014}"], '-', $title);
        $title = trim((string) preg_replace('/^S\.\s*T\.\s*/u', '', $title));
        $rest = null;
        if (preg_match('/^(.*?)\s+:\s+(.*)$/us', $title, $m) === 1) {
            [$title, $rest] = [trim($m[1]), trim($m[2])];
        }
        if (preg_match('/^\[(.*?)\]\.?(.*)$/us', $title, $m) === 1) {
            $title = trim($m[1].($m[2] !== '' ? ' '.trim($m[2]) : ''));
        }
        $title = trim($title, " \t\n\r\0\x0B[]");

        return [$title, $rest === '' ? null : $rest];
    }

    /** Minuscules, sans accent, ponctuation en espaces, « saint » développé : forme de comparaison. */
    public static function fold(?string $text): string
    {
        $text = mb_strtolower(trim((string) $text));
        $text = strtr($text, ['œ' => 'oe', 'æ' => 'ae', '’' => "'", '‘' => "'"]);
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        $text = $ascii === false ? $text : $ascii;
        $text = (string) preg_replace('/[^a-z0-9]+/', ' ', $text);
        $text = (string) preg_replace('/\bst\b/', 'saint', $text);
        $text = (string) preg_replace('/\bste\b/', 'sainte', $text);

        return trim((string) preg_replace('/\s+/', ' ', $text));
    }

    /**
     * Texte sans les noms de rues, de commerces et de gares de funiculaire qui contiennent « gare », « chemin de fer » ou
     * « tramway » (« rue de la Gare », « avenue des 2 Gares », « rue du Chemin de fer », « quartier de la Gare »,
     * « Café de la Gare », « Gare du Funiculaire » : ces trois dernières seulement avec `$stationsOnly`, pour savoir si la vue
     * montre une gare ; une gare de funiculaire reste une vue de funiculaire) : ils ne disent rien d'une gare ni d'une voie ferrée.
     */
    public static function withoutStreets(?string $text, bool $stationsOnly = false): string
    {
        $text = $stationsOnly ? (string) preg_replace(
            '/\b(?:caf[ée]|restaurant|h[ôo]tel|brasserie|auberge|buvette|[ée]picerie|pharmacie|librairie)(?:[- ]restaurant)?(?:\s+[\p{L}\'’-]+){0,3}?\s+(?:de la|du|de l\')\s*gare\b|\b(?:environs|abords|alentours)\s+(?:de la|du|de l\')\s*gare\b|\bgares?\s+(?:du|de la|des)\s+(?:funiculaire|tramways?|bateaux?)\b/iu',
            ' ',
            (string) $text,
        ) : (string) $text;

        return trim((string) preg_replace(
            '/\b(?:rue|rues|avenue|av\.?|boulevard|bd|route|chemin|passage|impasse|all[ée]e|cit[ée]|villa|ruelle|quartier|faubourg|rond-point|carrefour|square)\s+(?:de |du |des |d\'|de l\'|de la |de |l\')?\s*(?:\d+\s*|(?:deux|trois|quatre)[\s-]+)?(?:gares?|chemins? de fer|tramways?|stations?)\b/iu',
            ' ',
            (string) $text,
        ));
    }

    /**
     * Jetons significatifs d'un nom (sans les mots vides ni « gare » / « halte »).
     *
     * @return list<string>
     */
    public static function tokens(?string $text): array
    {
        $stop = ['de', 'du', 'des', 'la', 'le', 'les', 'l', 'd', 'en', 'sur', 'sous', 'et', 'a', 'au', 'aux', 'gare', 'halte', 'station', 'arret'];
        $out = [];
        foreach (explode(' ', self::fold($text)) as $t) {
            if ($t !== '' && ! in_array($t, $stop, true)) {
                $out[] = $t;
            }
        }

        return $out;
    }

    /**
     * Date en français ou année seule.
     *
     * @return array{captured_at: ?string, precision: ?string, year: ?int, year_to: ?int}
     */
    public static function date(mixed $raw, bool $roundYearsAreApproximate = false): array
    {
        $none = ['captured_at' => null, 'precision' => null, 'year' => null, 'year_to' => null];
        if ($raw === null || $raw === '') {
            return $none;
        }
        if (is_int($raw) || is_float($raw)) {
            $raw = (string) (int) $raw;
        }
        $text = self::fold((string) $raw);
        $approx = str_contains((string) $raw, '?') || preg_match('/\b(vers|circa|env|environ|about|ca)\b/', $text) === 1;

        // Date collée AAAAMMJJ (saisie de certains jeux).
        if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $text, $m) === 1 && $m[1] >= 1500 && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return ['captured_at' => $m[1].'-'.$m[2].'-'.$m[3], 'precision' => 'day', 'year' => (int) $m[1], 'year_to' => null];
        }
        if (preg_match('/^(\d{4})\s+(\d{4})$/', $text, $m) === 1) {
            $a = (int) $m[1];
            $b = (int) $m[2];
            if ($a < 1800 || $b > 2100 || $b < $a) {
                return $none;
            }

            return ['captured_at' => null, 'precision' => $a === $b ? 'year' : 'range', 'year' => $a, 'year_to' => $a === $b ? null : $b];
        }
        if (preg_match('/\b(\d{1,2})(?:er)? ([a-z]+) (\d{4})\b/', $text, $m) === 1 && isset(self::MONTHS[$m[2]])) {
            $d = (int) $m[1];
            if ($d >= 1 && $d <= 31 && checkdate(self::MONTHS[$m[2]], $d, (int) $m[3])) {
                return ['captured_at' => sprintf('%04d-%02d-%02d', $m[3], self::MONTHS[$m[2]], $d), 'precision' => $approx ? 'circa' : 'day', 'year' => (int) $m[3], 'year_to' => null];
            }
        }
        if (preg_match('/\b([a-z]+) (\d{4})\b/', $text, $m) === 1 && isset(self::MONTHS[$m[1]])) {
            return ['captured_at' => sprintf('%04d-%02d-01', $m[2], self::MONTHS[$m[1]]), 'precision' => $approx ? 'circa' : 'month', 'year' => (int) $m[2], 'year_to' => null];
        }
        if (preg_match('/\b(1[5-9]\d{2}|20\d{2})\b/', $text, $m) === 1) {
            $year = (int) $m[1];
            $precision = 'year';
            if (preg_match('/\b(avant|before)\b/', $text) === 1) {
                $precision = 'before';
            } elseif (preg_match('/\b(apres|after)\b/', $text) === 1) {
                $precision = 'after';
            } elseif ($approx || ($roundYearsAreApproximate && $year % 10 === 0)) {
                $precision = 'circa';
            }

            return ['captured_at' => null, 'precision' => $precision, 'year' => $year, 'year_to' => null];
        }

        return $none;
    }
}

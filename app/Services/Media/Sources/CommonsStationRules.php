<?php

namespace App\Services\Media\Sources;

/**
 * Règles pures (sans base ni réseau) du rattachement aux gares des fichiers Commons déjà en base
 * (CommonsStationSource) : normalisation des noms, noms de gare lus dans une catégorie ou un titre, refus.
 *
 * Un fichier va à une gare quand une de ses catégories est la catégorie Commons de la gare (Wikidata P373),
 * ou quand une catégorie ou le titre nomme la gare (« Gare de X », « Halte de X », « Bahnhof X », « X railway
 * station ») ; dans les deux cas, ses coordonnées, si elles sont connues, sont à moins de MAX_DISTANCE_M de la
 * gare, et la date représentée n'est pas antérieure à l'ouverture. Un nom seul ne suffit pas : la gare doit
 * aussi être desservie par une ligne à laquelle le fichier est déjà rattaché, ou se trouver à moins de
 * MAX_DISTANCE_M du point de prise de vue (homonymes).
 */
final class CommonsStationRules
{
    public const MAX_DISTANCE_M = 300;

    /** Années de marge avant l'ouverture (dates d'ouverture parfois arrondies). */
    public const OPENING_MARGIN_YEARS = 2;

    /** Au-delà, le fichier montre une ligne entière ou une carte du réseau, pas une gare. */
    public const MAX_STATIONS_PER_FILE = 3;

    private const NOT_PICTURE = '/\b(logos?|icons?|ic[oô]nes?|pictogram(me)?s?|wappen|blasons?|coats? of arms|signatures?|bsicon|diagrams?|sch[ée]mas?|route maps?|plans? de r[ée]seau)\b/iu';

    /** Transports urbains : une gare ferroviaire n'est pas illustrée par la station de métro ou de tramway homonyme. */
    private const URBAN = '/\b(m[ée]tro|metro stations?|subway|tram stops?|tramway stations?|stations? de tramway|bus stations?|gares? routi[eè]res?|rer [a-e] stations?)\b/iu';

    /** « Gare de X », « Halte d'X », « Former gare du X », « Trains at Gare des X » (X capturé). */
    private const FR_NAME = "/\\b(?:gares?|haltes?)\\s+(de la |de l'|de |d'|du |des )?([^(),;]+)/iu";

    private const DE_NAME = '/\bbahnhof\s+([^(),;]+)/iu';

    private const EN_NAME = '/^(?:[^(),;]*\b(?:of|at|in)\s+)?([^(),;]+?)\s+(?:railway|train)\s+station\b/iu';

    /** Forme comparable d'un nom : minuscules sans accents, « St » et « Ste » développés, tirets et apostrophes en espaces. */
    public static function normalize(string $name): string
    {
        $s = mb_strtolower(trim($name));
        $s = strtr($s, ['’' => "'", '‐' => '-', '‑' => '-']);
        $s = (string) preg_replace('/\.(jpe?g|png|gif|tiff?|webp|svg|pdf|djvu)$/u', '', $s);
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        $s = $ascii !== false ? $ascii : $s;
        $s = (string) preg_replace('/[^a-z0-9]+/', ' ', $s);
        $s = (string) preg_replace('/\b(st)\b/', 'saint', $s);
        $s = (string) preg_replace('/\b(ste)\b/', 'sainte', $s);

        return trim((string) preg_replace('/\s+/', ' ', $s));
    }

    /**
     * Clés d'une gare : son nom et ses autres noms, normalisés, avec et sans précision entre parenthèses.
     *
     * @param  list<string>  $names
     * @return list<string>
     */
    public static function stationKeys(array $names): array
    {
        $keys = [];
        foreach ($names as $name) {
            foreach ([$name, (string) preg_replace('/\s*\([^)]*\)\s*$/u', '', $name)] as $variant) {
                $k = self::normalize($variant);
                if (mb_strlen($k) >= 2) {
                    $keys[$k] = true;
                }
            }
        }

        return array_keys($keys);
    }

    /**
     * Noms de gare qu'une catégorie désigne, sous forme de clés (normalize()) : « Gare de Saint-Marcellin (Isère) »
     * donne `saint marcellin` ; « Gare du Bosquet », `le bosquet` et `bosquet`.
     *
     * @return list<string>
     */
    public static function categoryKeys(string $category): array
    {
        if (preg_match(self::URBAN, $category) === 1) {
            return [];
        }
        $keys = [];
        if (preg_match_all(self::FR_NAME, $category, $m, PREG_SET_ORDER) > 0) {
            foreach ($m as [, $prep, $name]) {
                $base = self::normalize($name);
                if ($base === '') {
                    continue;
                }
                $keys[$base] = true;
                $article = match (mb_strtolower($prep)) {
                    'du ' => 'le ',
                    'des ' => 'les ',
                    'de la ' => 'la ',
                    "de l'" => 'l ',
                    default => null,
                };
                if ($article !== null) {
                    $keys[self::normalize($article.$name)] = true;
                }
            }
        }
        foreach ([self::DE_NAME, self::EN_NAME] as $pattern) {
            if (preg_match($pattern, $category, $m) === 1) {
                $k = self::normalize($m[1]);
                if ($k !== '') {
                    $keys[$k] = true;
                }
            }
        }

        return array_keys($keys);
    }

    /** Vrai si le texte contient un mot de gare (« gare », « halte », « station », « Bahnhof », « Haltepunkt »). */
    public static function namesAStation(string $text): bool
    {
        return preg_match('/\b(gares?|haltes?|stations?|bahnhof|haltepunkt|arr[eê]ts?)\b/iu', $text) === 1
            && preg_match(self::URBAN, $text) !== 1;
    }

    /**
     * Vrai si le titre du fichier nomme la gare : « gare de <clé> », « halte d'<clé> », « <clé> station »,
     * « bahnhof <clé> », mots entiers. Le nom ne doit pas se prolonger par un trait d'union suivi d'une lettre :
     * « gare de Grenoble-Gières » ne nomme pas la gare de Grenoble (un chiffre peut suivre : « Gare-Lingolsheim-1900 »).
     * La légende n'est pas lue : elle cite souvent une gare voisine (« à 5 km de la gare de … »).
     *
     * @param  list<string>  $stationKeys
     */
    public static function titleNames(string $title, array $stationKeys): bool
    {
        return self::titleKey($title, $stationKeys) !== null;
    }

    /**
     * Clé de la gare que le titre nomme (la plus longue), null sinon. Voir titleNames().
     *
     * @param  list<string>  $stationKeys
     */
    public static function titleKey(string $title, array $stationKeys): ?string
    {
        $t = self::softNormalize($title);
        $best = null;
        foreach ($stationKeys as $k) {
            $q = str_replace(' ', '(?: - |[ -])', preg_quote($k, '/'));
            // Fin du nom : fin du titre, espace ou trait d'union suivis d'autre chose qu'un nom (« - Riquier », « -Gières »).
            $end = '(?=$|[ -])(?!-[a-z]| -+ *[a-z])';
            if (preg_match("/(?:^| )(?:gare|halte|bahnhof)s?(?:[ -](?:de[ -]la|de[ -]l|de|d|du|des))?[ -]{$q}{$end}|(?:^|[ -]){$q}[ -](?:railway[ -]|train[ -])?station(?=$|[ -])/", $t) === 1
                && ($best === null || mb_strlen($k) > mb_strlen($best))) {
                $best = $k;
            }
        }

        return $best;
    }

    /**
     * Noms plus longs que le titre forme à partir de la clé trouvée : « gare de Valence TGV côté TER » donne
     * `valence tgv`, `valence tgv cote`, `valence tgv cote ter`. Si l'un d'eux est le nom d'une autre gare, le titre
     * la désigne, elle, et pas la gare de la clé.
     *
     * @return list<string>
     */
    public static function titleExtensions(string $title, string $key, int $words = 3): array
    {
        $t = ' '.self::normalize(preg_replace('/^file:/iu', '', $title) ?? '').' ';
        $pos = strpos($t, ' '.$key.' ');
        if ($pos === false) {
            return [];
        }
        $rest = array_slice(explode(' ', trim(substr($t, $pos + strlen($key) + 2))), 0, $words);
        $out = [];
        $acc = $key;
        foreach ($rest as $w) {
            if ($w === '') {
                break;
            }
            $acc .= ' '.$w;
            $out[] = $acc;
        }

        return $out;
    }

    /** Comme normalize(), mais garde les traits d'union (fin d'un nom composé). */
    private static function softNormalize(string $text): string
    {
        $s = mb_strtolower(trim($text));
        $s = (string) preg_replace('/^file:/u', '', $s);
        $s = (string) preg_replace('/\.(jpe?g|png|gif|tiff?|webp|svg|pdf|djvu)$/u', '', $s);
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        $s = $ascii !== false ? $ascii : $s;
        $s = (string) preg_replace('/[^a-z0-9-]+/', ' ', $s);
        $s = (string) preg_replace('/(?<=^|[ -])st(?=$|[ -])/', 'saint', $s);
        $s = (string) preg_replace('/(?<=^|[ -])ste(?=$|[ -])/', 'sainte', $s);

        return trim((string) preg_replace('/\s+/', ' ', $s));
    }

    /**
     * Motif de refus d'un rattachement, null s'il est accepté.
     *
     * @param  ?float  $distanceM  distance entre le point de prise de vue et la gare (nulle si le fichier n'a pas de position)
     * @param  bool  $corroborated  gare desservie par une ligne du fichier, ou à moins de MAX_DISTANCE_M
     */
    public static function rejectReason(string $method, ?float $distanceM, bool $corroborated, ?int $depictedYear, ?int $openedYear, string $text): ?string
    {
        return match (true) {
            preg_match(self::NOT_PICTURE, $text) === 1 => 'not_picture',
            $distanceM !== null && $distanceM > self::MAX_DISTANCE_M => 'too_far',
            $method !== CommonsStationSource::VIA_P373 && ! $corroborated => 'not_corroborated',
            $depictedYear !== null && $openedYear !== null && $depictedYear < $openedYear - self::OPENING_MARGIN_YEARS => 'before_opening',
            default => null,
        };
    }
}

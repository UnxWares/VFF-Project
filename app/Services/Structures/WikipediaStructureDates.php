<?php

declare(strict_types=1);

namespace App\Services\Structures;

/**
 * Dates d'un ouvrage lues dans l'infobox de son article de Wikipédia en français (« Infobox Pont »,
 * « Infobox Tunnel ») : seuls des FAITS (dates) sont repris, avec le permalien de la révision lue ; aucun
 * texte n'est gardé. Ne sert qu'à combler les dates que Wikidata ne donne pas (StructureImporter).
 *
 * Champs lus : construction (`dates`, sinon `fin`, sinon `début`) : la dernière année de la période,
 * l'achèvement ; mise en service (`mise en service`, sinon `ouverture`, sinon `inauguration`) : la première
 * date écrite ; fermeture (`fermeture`). Une date se lit jour, mois et année (« 27 mai 1888 », modèle
 * {{Date|27|mai|1888}}), mois et année, ou année seule (1800 à 2100).
 *
 * Fonctions pures (tests : tests/Unit/Structures/WikipediaStructureDatesTest.php).
 */
final class WikipediaStructureDates
{
    private const MONTHS = [
        'janvier' => 1, 'février' => 2, 'fevrier' => 2, 'mars' => 3, 'avril' => 4, 'mai' => 5, 'juin' => 6,
        'juillet' => 7, 'août' => 8, 'aout' => 8, 'septembre' => 9, 'octobre' => 10, 'novembre' => 11, 'décembre' => 12, 'decembre' => 12,
    ];

    /**
     * @return array{built: ?array{date: string, precision: string}, opened: ?array{date: string, precision: string}, closed: ?array{date: string, precision: string}}
     */
    public static function fromWikitext(string $wikitext): array
    {
        $fields = self::fields($wikitext);
        $first = function (array $keys) use ($fields): ?string {
            foreach ($keys as $k) {
                if (($fields[$k] ?? '') !== '') {
                    return $fields[$k];
                }
            }

            return null;
        };

        $built = $first(['dates', 'fin', 'début']);

        return [
            'built' => $built === null ? null : self::lastYear($built) ?? self::date($built),
            'opened' => ($v = $first(['mise en service', 'ouverture', 'inauguration'])) === null ? null : self::date($v),
            'closed' => ($v = $first(['fermeture'])) === null ? null : self::date($v),
        ];
    }

    /**
     * Champs `| nom = valeur` de l'infobox, valeurs nettoyées (références, commentaires, liens, modèles).
     *
     * @return array<string, string>
     */
    public static function fields(string $wikitext): array
    {
        $out = [];
        foreach (preg_split('/\R/u', $wikitext) ?: [] as $line) {
            if (preg_match('/^\s*\|\s*([^=|]+?)\s*=\s*(.*)$/u', $line, $m)) {
                $key = mb_strtolower(trim($m[1]));
                $out[$key] ??= self::clean($m[2]);
            }
        }

        return $out;
    }

    /** Valeur d'un champ en texte simple. */
    public static function clean(string $value): string
    {
        $v = (string) preg_replace('/<!--.*?-->/su', '', $value);
        $v = (string) preg_replace('/<ref[^>]*\/>|<ref[^>]*>.*?(<\/ref>|$)/isu', '', $v);
        // {{Date|27|mai|1888|dans les chemins de fer}} et {{date-|…}} : jour, mois, année.
        $v = (string) preg_replace_callback('/\{\{\s*date-?\s*\|([^}]*)\}\}/iu', function (array $m): string {
            $parts = array_values(array_filter(array_map('trim', explode('|', $m[1])), fn (string $p): bool => $p !== '' && ! str_contains($p, '=')));

            return implode(' ', array_slice($parts, 0, 3));
        }, $v);
        $v = (string) preg_replace('/\[\[(?:[^\]|]*\|)?([^\]]*)\]\]/u', '$1', $v);
        $v = (string) preg_replace('/\{\{[^{}]*\}\}/u', '', $v);

        return trim((string) preg_replace('/\s+/u', ' ', strip_tags($v)));
    }

    /**
     * Première date écrite, à sa précision.
     *
     * @return array{date: string, precision: string}|null
     */
    public static function date(string $text): ?array
    {
        $months = implode('|', array_keys(self::MONTHS));
        if (preg_match('/\b(1er|\d{1,2})\s+('.$months.')\s+(1[89]\d\d|20\d\d)\b/iu', $text, $m)) {
            $day = $m[1] === '1er' ? 1 : (int) $m[1];
            $month = self::MONTHS[mb_strtolower($m[2])];
            if (checkdate($month, $day, (int) $m[3])) {
                return ['date' => sprintf('%04d-%02d-%02d', (int) $m[3], $month, $day), 'precision' => 'day'];
            }
        }
        if (preg_match('/\b('.$months.')\s+(1[89]\d\d|20\d\d)\b/iu', $text, $m)) {
            return ['date' => sprintf('%04d-%02d-01', (int) $m[2], self::MONTHS[mb_strtolower($m[1])]), 'precision' => 'month'];
        }
        if (preg_match('/\b(1[89]\d\d|20\d\d)\b/u', $text, $m)) {
            return ['date' => $m[1].'-01-01', 'precision' => 'year'];
        }

        return null;
    }

    /**
     * Dernière année d'une période (« 1880-1884 », « de 1880 à 1884 ») ; null s'il n'y a pas deux années.
     *
     * @return array{date: string, precision: string}|null
     */
    private static function lastYear(string $text): ?array
    {
        if (preg_match_all('/\b(1[89]\d\d|20\d\d)\b/u', $text, $m) < 2) {
            return null;
        }

        return ['date' => max(array_map('intval', $m[1])).'-01-01', 'precision' => 'year'];
    }
}

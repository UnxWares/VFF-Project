<?php

namespace App\Support\Search;

/**
 * Passage trouvé dans un nom, pour la surbrillance de la page de résultats (/search) : les mots du nom que la
 * recherche a trouvés, lus avec la même clé que la recherche (SearchText::key), sans autre normalisation.
 *
 * Un mot du nom est trouvé quand sa clé commence par un mot de la requête (« hil » dans « Hilaire ») ou, pour un
 * mot de la requête de 3 lettres ou plus, le contient (« vire » dans « Évires »), comme SearchQuery::matchSql.
 * Seul le passage est marqué quand la clé du mot a la même longueur que le mot (accents, casse) ; sinon (« St »
 * lu « saint », « œ » lu « oe ») le mot entier l'est. Lettres et chiffres sont des mots distincts (« BB7600 »).
 *
 *   SearchHighlight::segments('St-Hilaire-de-Chaléons', SearchQuery::parse('st hilaire c'))
 *   // [['text' => 'St', 'mark' => true], ['text' => '-', 'mark' => false], ['text' => 'Hilaire', 'mark' => true],
 *   //  ['text' => '-de-', 'mark' => false], ['text' => 'C', 'mark' => true], ['text' => 'haléons', 'mark' => false]]
 */
final class SearchHighlight
{
    /**
     * Nom découpé en passages, marqués ou non ; un seul passage non marqué si rien n'est trouvé.
     *
     * @return list<array{text: string, mark: bool}>
     */
    public static function segments(string $text, SearchQuery $query): array
    {
        $words = array_values(array_unique(array_merge(...$query->tokens)));
        if ($text === '' || $words === []) {
            return [['text' => $text, 'mark' => false]];
        }

        // Plages marquées, en caractères : [début, fin[.
        $ranges = [];
        preg_match_all('/\p{L}+|\p{N}+/u', $text, $matches, PREG_OFFSET_CAPTURE);
        foreach ($matches[0] as [$word, $byteOffset]) {
            $range = self::match($word, $words);
            if ($range !== null) {
                $start = mb_strlen(substr($text, 0, $byteOffset));
                $ranges[] = [$start + $range[0], $start + $range[1]];
            }
        }

        $segments = [];
        $cursor = 0;
        foreach ($ranges as [$from, $to]) {
            if ($from > $cursor) {
                $segments[] = ['text' => mb_substr($text, $cursor, $from - $cursor), 'mark' => false];
            }
            $segments[] = ['text' => mb_substr($text, $from, $to - $from), 'mark' => true];
            $cursor = $to;
        }
        if ($cursor < mb_strlen($text)) {
            $segments[] = ['text' => mb_substr($text, $cursor), 'mark' => false];
        }

        return $segments;
    }

    /**
     * Plage trouvée dans un mot (en caractères du mot), la plus longue des mots de la requête, ou null.
     *
     * @param  list<string>  $words  mots de la requête (clés)
     * @return array{0: int, 1: int}|null
     */
    private static function match(string $word, array $words): ?array
    {
        $plain = trim(SearchText::key($word, false));
        $expanded = trim(SearchText::key($word));
        $length = mb_strlen($word);
        // La clé a-t-elle un caractère par caractère du mot ? Sinon, seul le mot entier peut être marqué.
        $aligned = mb_strlen($plain) === $length;
        $best = null;
        foreach ($words as $w) {
            $size = mb_strlen($w);
            if (str_starts_with($plain, $w) || str_starts_with($expanded, $w)) {
                $range = $aligned && str_starts_with($plain, $w) ? [0, $size] : [0, $length];
            } elseif ($size >= 3 && ($at = mb_strpos($plain, $w)) !== false) {
                $range = $aligned ? [$at, $at + $size] : [0, $length];
            } else {
                continue;
            }
            if ($best === null || $range[1] - $range[0] > $best[1] - $best[0]) {
                $best = $range;
            }
        }

        return $best;
    }
}

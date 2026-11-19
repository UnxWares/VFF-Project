<?php

namespace App\Services\Accidents;

/**
 * Faits des listes d'accidents des Wikipédia en anglais et en allemand, pour les accidents en France :
 * date, lieux nommés comme lieu de l'accident, et présence d'une référence propre à l'entrée. Seuls des
 * FAITS sont lus (CC BY-SA 4.0) ; aucun texte n'est repris.
 *
 *   - anglais : puces « Mois jour – ''France'' – texte » des pages « List of rail accidents (années) », l'année
 *     venant de l'intertitre, et tableau de « List of rail accidents in France » (lieu en colonne) ;
 *   - allemand : puces « Lieu ({{FRA-…}}) – nature : ''jour mois année'' – texte » de « Liste schwerer Unfälle
 *     im Schienenverkehr » (le lieu est le début de la puce).
 *
 * Un lieu de puce anglaise n'est retenu que s'il est introduit comme lieu (« at », « near », « in »,
 * « outside ») : ni « from Paris to Le Mans » (trajet), ni « between X and Y » (entre deux gares) ne désignent un lieu.
 */
final class WikipediaEditionListReader
{
    private const EN_MONTHS = [
        'January' => 1, 'February' => 2, 'March' => 3, 'April' => 4, 'May' => 5, 'June' => 6, 'July' => 7,
        'August' => 8, 'September' => 9, 'October' => 10, 'November' => 11, 'December' => 12,
    ];

    private const DE_MONTHS = [
        'Januar' => 1, 'Februar' => 2, 'März' => 3, 'April' => 4, 'Mai' => 5, 'Juni' => 6, 'Juli' => 7,
        'August' => 8, 'September' => 9, 'Oktober' => 10, 'November' => 11, 'Dezember' => 12,
    ];

    /**
     * Mots qui introduisent un lieu d'accident dans une puce anglaise : « at », « near », « in », « outside », « town of ».
     * « between X and Y » n'en fait pas partie : l'accident est entre deux gares, et aucune des deux communes n'est
     * le lieu ; « from X to Y » non plus (trajet du train).
     */
    private const EN_PLACE_MARKER = '/(?:\b(?:at|near|in|outside)\s+(?:the\s+)?(?:(?:station|town|village|commune|city|junction|siding|halt|stop|hamlet)\s+(?:of\s+)?)?|\b(?:town|village|commune|city|hamlet)\s+of\s+)$/i';

    /**
     * @return list<array{date: string, places: list<array{name: string, qualifier: ?string}>, referenced: bool, references: list<string>, anchor: ?string}>
     */
    public function readEnglish(string $wikitext): array
    {
        $entries = [];
        $year = null;
        $anchor = null;
        foreach (preg_split('/\R/u', $wikitext) ?: [] as $line) {
            if (preg_match('/^=+\s*(\d{4})\s*=+\s*$/u', $line, $h)) {
                $year = (int) $h[1];
                $anchor = $h[1];

                continue;
            }
            if ($year === null || ! str_starts_with($line, '*') || str_starts_with($line, '**')) {
                continue;
            }
            $text = preg_replace('/^\*\s*(?:\[\[(?:File|Image):(?:[^\[\]]|\[\[[^\]]*\]\])*\]\]\s*)?/u', '', $line) ?? $line;
            if (! preg_match('/^(?:\'\'\')?\s*('.implode('|', array_keys(self::EN_MONTHS)).')\s+(\d{1,2})\s*[–—-]\s*\'\'France\'\'\s*[–—-]\s*(.*)$/u', $text, $m)) {
                continue;
            }
            $date = $this->date((int) $year, self::EN_MONTHS[$m[1]], (int) $m[2]);
            if ($date === null) {
                continue;
            }
            $entries[] = [
                'date' => $date,
                'places' => $this->englishPlaces($m[3]),
                'referenced' => $this->hasReference($m[3]),
                'references' => $this->references($m[3]),
                'anchor' => $anchor,
            ];
        }

        return $entries;
    }

    /**
     * Tableau « List of rail accidents in France » : une rangée par accident, date « 8 February 2014 »,
     * lieu en troisième colonne (tous ses liens), référence en dernière colonne ou dans la description.
     *
     * @return list<array{date: string, places: list<array{name: string, qualifier: ?string}>, referenced: bool, references: list<string>, anchor: ?string}>
     */
    public function readEnglishTable(string $wikitext): array
    {
        $entries = [];
        foreach (preg_split('/\R\|-\s*\R/u', $wikitext) ?: [] as $row) {
            $cells = preg_split('/\R\|(?!\}|-)/u', $row) ?: [];
            if (count($cells) < 4) {
                continue;
            }
            $cells = array_map('trim', $cells);
            // La première cellule d'une rangée peut porter le « | » de tête.
            $cells[0] = ltrim($cells[0], "| \t");
            if (! preg_match('/^(\d{1,2})\s+('.implode('|', array_keys(self::EN_MONTHS)).')\s+(\d{4})$/u', trim(strip_tags($cells[1])), $d)) {
                continue;
            }
            $date = $this->date((int) $d[3], self::EN_MONTHS[$d[2]], (int) $d[1]);
            if ($date === null) {
                continue;
            }
            $rest = implode(' ', array_slice($cells, 5));
            $entries[] = [
                'date' => $date,
                // « Between X and Y » : entre deux gares, aucune des deux communes n'est le lieu.
                'places' => preg_match('/\bbetween\b/i', $cells[2]) ? [] : $this->links($cells[2]),
                'referenced' => $this->hasReference($rest),
                'references' => $this->references($rest),
                'anchor' => null,
            ];
        }

        return $entries;
    }

    /**
     * @return list<array{date: string, places: list<array{name: string, qualifier: ?string}>, referenced: bool, references: list<string>, anchor: ?string}>
     */
    public function readGerman(string $wikitext): array
    {
        $entries = [];
        $anchor = null;
        foreach (preg_split('/\R/u', $wikitext) ?: [] as $line) {
            if (preg_match('/^=+\s*(.+?)\s*=+\s*$/u', $line, $h)) {
                $anchor = str_replace(' ', '_', trim(strip_tags(preg_replace('/\[\[(?:[^\]|]*\|)?([^\]]*)\]\]/u', '$1', $h[1]) ?? $h[1])));

                continue;
            }
            if (! str_starts_with($line, '*') || str_starts_with($line, '**')) {
                continue;
            }
            if (! preg_match('/^\*\s*(.+?)\s*\(\s*\{\{FRA[^}]*\}\}\s*\)\s*(?:[–—-]|:)\s*(.*)$/u', $line, $m)) {
                continue;
            }
            if (! preg_match('/\'\'\s*(\d{1,2})\.\s*('.implode('|', array_keys(self::DE_MONTHS)).')\s+(\d{4})\s*\'\'/u', $m[2], $d)) {
                continue;
            }
            $date = $this->date((int) $d[3], self::DE_MONTHS[$d[2]], (int) $d[1]);
            if ($date === null) {
                continue;
            }
            $places = $this->links($m[1]);
            if ($places === []) {
                $name = trim(preg_replace('/\s*\(\?\)\s*/u', ' ', strip_tags($m[1])) ?? $m[1]);
                $places = $name !== '' ? [['name' => $name, 'qualifier' => null]] : [];
            }
            $entries[] = [
                'date' => $date,
                'places' => $places,
                'referenced' => str_contains($m[2], '<ref'),
                'references' => [],
                'anchor' => $anchor,
            ];
        }

        return $entries;
    }

    /**
     * Lieux d'une puce anglaise : les liens introduits par « at », « near », « in », « outside », « town of ».
     *
     * @return list<array{name: string, qualifier: ?string}>
     */
    private function englishPlaces(string $text): array
    {
        $text = preg_replace('/<ref[^>]*\/>|<ref[^>]*>.*?<\/ref>|<!--.*?-->|\{\{(?:sfn|harv|sfnp)[^{}]*\}\}/su', '', $text) ?? $text;
        $text = preg_replace('/\{\{(?:convert|cvt)\|[^{}]*\}\}/u', '', $text) ?? $text;
        $places = [];
        $plain = '';
        $pos = 0;
        while (preg_match('/\[\[([^\]|]+)(?:\|([^\]]*))?\]\]/u', $text, $m, PREG_OFFSET_CAPTURE, $pos)) {
            $plain .= substr($text, $pos, $m[0][1] - $pos);
            if (preg_match(self::EN_PLACE_MARKER, mb_substr($plain, -40)) && ! str_contains($m[1][0], ':')) {
                [$name, $qualifier] = $this->nameAndQualifier($m[1][0]);
                $places[] = ['name' => $name, 'qualifier' => $qualifier];
                if (($m[2][0] ?? '') !== '' && $m[2][0] !== $m[1][0]) {
                    $label = trim($m[2][0]);
                    $places[] = ['name' => $label, 'qualifier' => $qualifier];
                }
            }
            $plain .= $m[2][0] ?? $m[1][0];
            $pos = $m[0][1] + strlen($m[0][0]);
        }

        return $places;
    }

    /**
     * Tous les liens d'un fragment, comme lieux (cellule de lieu d'un tableau, début d'une puce allemande).
     *
     * @return list<array{name: string, qualifier: ?string}>
     */
    private function links(string $text): array
    {
        $places = [];
        preg_match_all('/\[\[([^\]|]+)(?:\|([^\]]*))?\]\]/u', $text, $all, PREG_SET_ORDER);
        foreach ($all as $m) {
            if (str_contains($m[1], ':')) {
                continue;
            }
            [$name, $qualifier] = $this->nameAndQualifier($m[1]);
            $places[] = ['name' => $name, 'qualifier' => $qualifier];
            if (($m[2] ?? '') !== '' && trim($m[2]) !== $m[1]) {
                $places[] = ['name' => trim($m[2]), 'qualifier' => $qualifier];
            }
        }

        return $places;
    }

    /**
     * « Châteaubourg, Ardèche » et « Sarry (Marne) » : nom et qualificatif.
     *
     * @return array{0: string, 1: ?string}
     */
    private function nameAndQualifier(string $target): array
    {
        $target = trim(str_replace('_', ' ', $target));
        if (preg_match('/^(.+?)\s*,\s*([^,]+)$/u', $target, $m) || preg_match('/^(.+?)\s*\(([^)]+)\)$/u', $target, $m)) {
            return [trim($m[1]), trim($m[2])];
        }

        return [$target, null];
    }

    /** L'entrée cite-t-elle une référence qui lui est propre (note, modèle de citation d'un ouvrage) ? */
    private function hasReference(string $text): bool
    {
        return (bool) preg_match('/<ref[\s>]|\{\{\s*(?:sfn|sfnp|harv|cite|citation)/iu', $text);
    }

    /**
     * Références de l'entrée en clair court : « Semmens 1994 » pour un renvoi {{sfn}}, nom de domaine pour un lien.
     *
     * @return list<string>
     */
    private function references(string $text): array
    {
        $out = [];
        if (preg_match_all('/\{\{\s*(?:sfn|sfnp|harv)\s*\|\s*([^|}]+)\|\s*(\d{4})/iu', $text, $all, PREG_SET_ORDER)) {
            foreach ($all as $m) {
                $out[] = trim($m[1]).' '.$m[2];
            }
        }
        if (preg_match_all('~https?://([^/\s|\]}]+)~u', $text, $urls)) {
            foreach ($urls[1] as $host) {
                $out[] = preg_replace('/^www\./', '', strtolower($host)) ?? $host;
            }
        }

        return array_values(array_unique($out));
    }

    private function date(int $year, int $month, int $day): ?string
    {
        return $year >= 1800 && $year <= 2100 && checkdate($month, $day, $year) ? sprintf('%04d-%02d-%02d', $year, $month, $day) : null;
    }
}

<?php

namespace App\Services\Accidents;

/**
 * Faits d'un article DÉDIÉ à un accident, dans la Wikipédia en anglais ou en allemand : date, lieux nommés comme
 * lieu de l'accident, et présence de références propres à l'article. Seuls des FAITS sont lus (CC BY-SA 4.0) ;
 * aucun texte n'est repris. Même forme de résultat que WikipediaEditionListReader, pour le même rapprochement.
 *
 *   - anglais : modèle « Infobox rail accident » (ou « public transit accident ») : `date` ({{start date|…}},
 *     {{date|…}} ou « 8 February 2014 ») et `location` (liens ; « Between X and Y » : entre deux gares, aucun lieu) ;
 *   - allemand : première phrase (« … ereignete sich am 23. Dezember 1933 … », « … stürzte am 17. September 1914 bei
 *     [[Sarry (Marne)|Sarry]] … ») et titre « Eisenbahnunfall von Lieu ».
 *
 * Un article qui ne donne pas de date au jour (année seule) ne donne rien : il ne confirmerait pas une date au jour.
 */
final class WikipediaEditionArticleReader
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
     * @return array{date: string, places: list<array{name: string, qualifier: ?string}>, referenced: bool, references: list<string>, anchor: ?string}|null
     */
    public function readEnglish(string $wikitext): ?array
    {
        if (! preg_match('/\{\{\s*Infobox (?:rail|public transit|train|railway) (?:accident|incident|crash)[^|{}]*\|(.*?)\n\}\}/su', $wikitext, $box)) {
            return null;
        }
        $params = [];
        foreach (preg_split('/\R\s*\|\s*/u', "\n|".$box[1]) ?: [] as $chunk) {
            if (preg_match('/^([a-z_ ]+?)\s*=\s*(.*)$/su', $chunk, $m)) {
                $params[trim($m[1])] = trim(preg_replace('/<!--.*?-->/su', '', $m[2]) ?? $m[2]);
            }
        }
        $date = $this->englishDate((string) ($params['date'] ?? ''));
        if ($date === null) {
            return null;
        }
        $location = (string) ($params['location'] ?? '');
        $places = preg_match('/\bbetween\b/i', $location) ? [] : $this->links($location);

        return [
            'date' => $date,
            'places' => $places,
            'referenced' => $this->hasReference($wikitext),
            'references' => [],
            'anchor' => null,
        ];
    }

    /**
     * @return array{date: string, places: list<array{name: string, qualifier: ?string}>, referenced: bool, references: list<string>, anchor: ?string}|null
     */
    public function readGerman(string $title, string $wikitext): ?array
    {
        $lead = $this->germanLead($wikitext);
        if (! preg_match('/(\d{1,2})\.\s*('.implode('|', array_keys(self::DE_MONTHS)).')\s+(\d{4})/u', $lead, $d)) {
            return null;
        }
        $date = $this->date((int) $d[3], self::DE_MONTHS[$d[2]], (int) $d[1]);
        if ($date === null) {
            return null;
        }
        $places = [];
        if (preg_match('/^Eisenbahnunfall (?:von|bei|in|im Tunnel von|auf dem|an der)\s+(.+?)$/u', $title, $t)) {
            [$name, $qualifier] = $this->nameAndQualifier($t[1]);
            $places[] = ['name' => $name, 'qualifier' => $qualifier];
        }
        // « … am 17. September 1914 bei [[Sarry (Marne)|Sarry]] » : le lieu cité après « bei » ou « in » dans la première phrase.
        if (preg_match('/\b(?:bei|in|nahe)\s+(?:dem\s+Bahnhof\s+)?\[\[([^\]|]+)(?:\|([^\]]*))?\]\]/u', $lead, $p) && ! str_contains($p[1], ':')) {
            [$name, $qualifier] = $this->nameAndQualifier($p[1]);
            $places[] = ['name' => $name, 'qualifier' => $qualifier];
        }

        return [
            'date' => $date,
            'places' => $this->unique($places),
            'referenced' => $this->hasReference($wikitext),
            'references' => [],
            'anchor' => null,
        ];
    }

    /** Premier paragraphe de texte (hors image, modèles et commentaires) : là se trouve la date de l'accident. */
    private function germanLead(string $wikitext): string
    {
        $text = preg_replace('/<!--.*?-->|<ref[^>]*\/>|<ref[^>]*>.*?<\/ref>/su', '', $wikitext) ?? $wikitext;
        $text = preg_replace('/\{\{(?:[^{}]|\{\{(?:[^{}]|\{\{[^{}]*\}\})*\}\})*\}\}/su', '', $text) ?? $text;
        $text = preg_replace('/\[\[(?:Datei|Bild|File):(?:[^\[\]]|\[\[[^\]]*\]\])*\]\]/su', '', $text) ?? $text;
        foreach (preg_split('/\R{2,}/u', trim($text)) ?: [] as $paragraph) {
            if (str_contains($paragraph, "'''")) {
                return $paragraph;
            }
        }

        return '';
    }

    private function englishDate(string $value): ?string
    {
        if (preg_match('/\{\{\s*(?:start date|start-date)(?:\s+and\s+age)?\s*\|\s*(\d{4})\s*\|\s*(\d{1,2})\s*\|\s*(\d{1,2})/iu', $value, $m)) {
            return $this->date((int) $m[1], (int) $m[2], (int) $m[3]);
        }
        $value = preg_replace('/\{\{\s*date\s*\|\s*([^|}]*)[^}]*\}\}/iu', '$1', $value) ?? $value;
        $value = trim(strip_tags(preg_replace('/\[\[(?:[^\]|]*\|)?([^\]]*)\]\]/u', '$1', $value) ?? $value));
        $months = implode('|', array_keys(self::EN_MONTHS));
        if (preg_match('/^(\d{1,2})\s+('.$months.')\s+(\d{4})\b/u', $value, $m)) {
            return $this->date((int) $m[3], self::EN_MONTHS[$m[2]], (int) $m[1]);
        }
        if (preg_match('/^('.$months.')\s+(\d{1,2}),?\s+(\d{4})\b/u', $value, $m)) {
            return $this->date((int) $m[3], self::EN_MONTHS[$m[1]], (int) $m[2]);
        }

        return null;
    }

    /**
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
        }

        return $this->unique($places);
    }

    /**
     * @param  list<array{name: string, qualifier: ?string}>  $places
     * @return list<array{name: string, qualifier: ?string}>
     */
    private function unique(array $places): array
    {
        $seen = [];
        $out = [];
        foreach ($places as $p) {
            $key = $p['name'].'|'.$p['qualifier'];
            if (! isset($seen[$key])) {
                $seen[$key] = true;
                $out[] = $p;
            }
        }

        return $out;
    }

    /**
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

    /** L'article cite-t-il ses propres références (notes, modèles de citation) ? */
    private function hasReference(string $text): bool
    {
        return (bool) preg_match('/<ref[\s>]|\{\{\s*(?:sfn|sfnp|harv|cite|citation)/iu', $text);
    }

    private function date(int $year, int $month, int $day): ?string
    {
        return $year >= 1800 && $year <= 2100 && checkdate($month, $day, $year) ? sprintf('%04d-%02d-%02d', $year, $month, $day) : null;
    }
}

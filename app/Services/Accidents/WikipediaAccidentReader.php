<?php

namespace App\Services\Accidents;

use App\Enums\AccidentDatePrecision;
use App\Support\FrenchDepartments;

/**
 * Faits d'un article de Wikipédia en français sur un accident, lus dans son introduction
 * (infobox « Accident de transport » ou voisine, puis liens du texte). Seuls des FAITS sont
 * gardés (date, bilan, coordonnées, nature, train, exploitant, lignes citées) : aucun texte de
 * l'article n'est repris (CC BY-SA 4.0), le résumé de VFF est rédigé à partir des faits.
 */
final class WikipediaAccidentReader
{
    private const MONTHS = [
        'janvier' => 1, 'février' => 2, 'fevrier' => 2, 'mars' => 3, 'avril' => 4, 'mai' => 5, 'juin' => 6,
        'juillet' => 7, 'août' => 8, 'aout' => 8, 'septembre' => 9, 'octobre' => 10, 'novembre' => 11,
        'décembre' => 12, 'decembre' => 12,
    ];

    /**
     * @return array{
     *   date: ?string, precision: ?AccidentDatePrecision, deaths: ?int, deaths_approx: bool,
     *   injuries: ?int, injuries_approx: bool, lat: ?float, lng: ?float, type: ?string,
     *   trains: list<string>, operators: list<string>,
     *   time: ?string, site_links: list<string>, department: ?string, line_articles: list<string>
     * }
     */
    public function read(string $wikitext): array
    {
        $fields = $this->infobox($wikitext);
        [$date, $precision] = $this->date($fields['date'] ?? null);
        [$deaths, $deathsApprox] = $this->total($fields, isset($fields['morts']) ? 'morts' : 'victimes');
        [$injuries, $injuriesApprox] = $this->total($fields, 'blessés');

        return [
            'date' => $date,
            'precision' => $precision,
            'deaths' => $deaths,
            'deaths_approx' => $deathsApprox,
            'injuries' => $injuries,
            'injuries_approx' => $injuriesApprox,
            'lat' => $this->coordinate($fields['latitude'] ?? null, 90),
            'lng' => $this->coordinate($fields['longitude'] ?? null, 180),
            'type' => $this->text($fields['type'] ?? null),
            'trains' => array_values(array_filter([$this->text($fields['appareil'] ?? null, 120), $this->text($fields['appareil2'] ?? null, 120)])),
            'operators' => array_values(array_unique(array_filter([$this->text($fields['compagnie'] ?? null, 120), $this->text($fields['compagnie2'] ?? null, 120)]))),
            'time' => $this->time($fields['date'] ?? null),
            'site_links' => $this->links($fields['site'] ?? $fields['lieu'] ?? null),
            'department' => $this->department($fields['géolocalisation'] ?? null),
            'line_articles' => $this->lineArticles($wikitext),
        ];
    }

    /**
     * Paramètres de la première infobox (« {{Infobox … »), au premier niveau seulement (les
     * modèles imbriqués restent dans la valeur).
     *
     * @return array<string, string>
     */
    public function infobox(string $wikitext): array
    {
        $start = stripos($wikitext, '{{Infobox');
        if ($start === false) {
            return [];
        }
        $depth = 0;
        $len = strlen($wikitext);
        $body = '';
        for ($i = $start; $i < $len - 1; $i++) {
            $two = substr($wikitext, $i, 2);
            if ($two === '{{' || $two === '[[') {
                $depth++;
                $body .= $two;
                $i++;

                continue;
            }
            if ($two === '}}' || $two === ']]') {
                $depth--;
                if ($depth === 0) {
                    break;
                }
                $body .= $two;
                $i++;

                continue;
            }
            // Séparateur de paramètre au premier niveau (dans « {{Infobox », profondeur 1).
            $body .= ($wikitext[$i] === '|' && $depth === 1) ? "\x1F" : $wikitext[$i];
        }
        $fields = [];
        foreach (array_slice(explode("\x1F", $body), 1) as $part) {
            if (! str_contains($part, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $part, 2);
            $key = mb_strtolower(trim($key));
            $value = trim($value);
            if ($key !== '' && $value !== '') {
                $fields[$key] = $value;
            }
        }

        return $fields;
    }

    /**
     * Date d'une valeur d'infobox : {{date|12|juillet|2013}}, {{date-|26 juillet 1891}},
     * « 12 juillet 2013 », « juillet 2013 », « 1891 ».
     *
     * @return array{0: ?string, 1: ?AccidentDatePrecision}
     */
    public function date(?string $value): array
    {
        if ($value === null) {
            return [null, null];
        }
        $v = $this->stripRefs($value);
        // Modèle {{date|…}} : les paramètres deviennent des mots.
        $v = preg_replace_callback('/\{\{\s*date-?\s*\|([^{}]*)\}\}/iu', fn ($m) => ' '.str_replace('|', ' ', $m[1]).' ', $v) ?? $v;
        $v = mb_strtolower(strip_tags(str_replace(['[[', ']]'], '', $v)));
        $v = str_replace(['1er', '1ᵉʳ'], '1', $v);
        $months = implode('|', array_keys(self::MONTHS));
        if (preg_match('/\b(\d{1,2})\s+('.$months.')\s+(\d{4})\b/u', $v, $m)) {
            return [sprintf('%04d-%02d-%02d', $m[3], self::MONTHS[$m[2]], $m[1]), AccidentDatePrecision::Day];
        }
        if (preg_match('/\b('.$months.')\s+(\d{4})\b/u', $v, $m)) {
            return [sprintf('%04d-%02d-01', $m[2], self::MONTHS[$m[1]]), AccidentDatePrecision::Month];
        }
        if (preg_match('/\b(1[89]\d{2}|20\d{2})\b/', $v, $m)) {
            return [sprintf('%04d-01-01', $m[1]), AccidentDatePrecision::Year];
        }

        return [null, null];
    }

    /**
     * Bilan de l'accident : l'infobox d'un accident à plusieurs trains (ou train et véhicule) donne
     * un bilan par véhicule (`morts`, `morts2`, `morts3`…), additionné ici. Zoufftgen :
     * « 1 (+ 1 agent de chantier au sol) » et « 4 » font 6.
     *
     * @param  array<string, string>  $fields
     * @return array{0: ?int, 1: bool}
     */
    public function total(array $fields, string $key): array
    {
        $sum = null;
        $approx = false;
        foreach (['', '2', '3', '4', '5'] as $suffix) {
            [$n, $a] = $this->count($fields[$key.$suffix] ?? null);
            if ($n !== null) {
                $sum = ($sum ?? 0) + $n;
                $approx = $approx || $a;
            }
        }

        return [$sum, $approx];
    }

    /**
     * Nombre en tête d'une valeur (« 70 dont 9 graves » → 70), plus les ajouts entre parenthèses
     * (« 1 (+ 1 agent au sol) » → 2) ; approximatif si la valeur dit
     * « environ », « au moins », « plus de », « près de » ou donne une fourchette.
     *
     * @return array{0: ?int, 1: bool}
     */
    public function count(?string $value): array
    {
        if ($value === null) {
            return [null, false];
        }
        $v = trim(strip_tags($this->stripRefs($value)));
        $v = preg_replace('/\{\{\s*(?:formatnum|nombre|nobr)\s*:?\|?\s*([^{}|]*)[^{}]*\}\}/iu', '$1', $v) ?? $v;
        $v = str_replace(["\u{00A0}", "\u{202F}", '&nbsp;'], ' ', $v);
        if (! preg_match('/(\d[\d ]*)/u', $v, $m)) {
            return preg_match('/\baucun/iu', $v) ? [0, false] : [null, false];
        }
        $n = (int) str_replace(' ', '', $m[1]);
        preg_match_all('/\(\s*\+\s*(\d+)/u', $v, $plus);
        $n += array_sum(array_map('intval', $plus[1]));
        $approx = (bool) preg_match('/environ|au moins|plus de|près de|pres de|~|≈|\d\s*(?:à|-)\s*\d/iu', $v);

        return [$n, $approx];
    }

    /** Heure écrite après la date (« … 1991 6h29 », « à 18 h 30 ») : HH:MM. */
    public function time(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $v = preg_replace('/\{\{[^{}]*\}\}/u', ' ', $this->stripRefs($value)) ?? $value;
        if (! preg_match('/\b([01]?\d|2[0-3])\s*h\s*([0-5]\d)?\b/u', $v, $m)) {
            return null;
        }

        return sprintf('%02d:%02d', $m[1], (int) ($m[2] ?? 0));
    }

    /**
     * Cibles des liens d'une valeur (« [[Gare d'Appilly]] », « [[Laguian-Mazous]] »).
     *
     * @return list<string>
     */
    public function links(?string $value): array
    {
        if ($value === null) {
            return [];
        }
        preg_match_all('/\[\[\s*([^\]|#]+)/u', $this->stripRefs($value), $m);

        return array_values(array_unique(array_map('trim', $m[1])));
    }

    /** Département nommé par le champ « géolocalisation » (« Essonne/Île-de-France/France »). */
    public function department(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        foreach (array_map('trim', explode('/', $value)) as $part) {
            foreach (FrenchDepartments::ALL as $code => [$name]) {
                if (mb_strtolower($part) === mb_strtolower($name)) {
                    return (string) $code;
                }
            }
        }

        return null;
    }

    private function coordinate(?string $value, int $max): ?float
    {
        if ($value === null || ! preg_match('/^-?\d+(?:\.\d+)?$/', trim($this->stripRefs($value)))) {
            return null;
        }
        $f = (float) trim($this->stripRefs($value));

        return abs($f) <= $max ? $f : null;
    }

    /** Texte lisible d'une valeur : liens et modèles simples résolus, balises retirées. */
    public function text(?string $value, int $max = 80): ?string
    {
        if ($value === null) {
            return null;
        }
        $v = $this->stripRefs($value);
        $v = preg_replace('/\[\[(?:[^\]|]*\|)?([^\]]*)\]\]/u', '$1', $v) ?? $v;
        $v = preg_replace('/\{\{[^{}]*\}\}/u', '', $v) ?? $v;
        $v = preg_replace('/<br\s*\/?>/iu', ' ', $v) ?? $v;
        $v = trim(preg_replace('/\s+/u', ' ', strip_tags(str_replace(["'''", "''"], '', $v))) ?? '');
        if ($v === '') {
            return null;
        }

        return mb_strlen($v) > $max ? rtrim(mb_substr($v, 0, $max - 1)).'…' : $v;
    }

    /**
     * Articles de ligne cités dans l'introduction : liens [[Ligne de …]], [[Ligne d'…]],
     * [[LGV …]], dans l'ordre d'apparition.
     *
     * @return list<string>
     */
    public function lineArticles(string $wikitext): array
    {
        preg_match_all("/\\[\\[\\s*((?:Ligne (?:de |d['’]|du |des )|LGV )[^\\]|#]+)/u", $wikitext, $m);

        return array_values(array_unique(array_map(fn ($t) => trim($t), $m[1])));
    }

    private function stripRefs(string $value): string
    {
        $v = preg_replace('/<ref[^>]*\/>|<ref[^>]*>.*?<\/ref>/su', '', $value) ?? $value;

        return preg_replace('/<!--.*?-->/su', '', $v) ?? $v;
    }
}

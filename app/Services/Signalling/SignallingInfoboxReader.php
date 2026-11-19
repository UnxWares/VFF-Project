<?php

namespace App\Services\Signalling;

use App\Enums\SignallingRole;
use App\Services\WikipediaLineParser;

/**
 * Mises en service de signalisation lues dans l'infobox « Ligne ferroviaire » d'un article (champ
 * `signalisation`), en complément du texte (SignallingHistoryExtractor).
 *
 * Deux sortes de faits :
 *   - `infobox` : un système daté dans l'infobox même, par élément (« Capdenac - Viviez : BMU (depuis 1985) »).
 *     Fiabilité `high` si le jour ou le mois est donné, `medium` pour une année, `low` si l'élément porte
 *     plusieurs dates. Un élément hors service, reporté ou prévu (« HS », « mise en service reportée »)
 *     ne donne rien ;
 *   - `opening` : la signalisation en cabine d'une ligne à grande vitesse (TVM). Une LGV n'a pas de signaux
 *     latéraux : elle ne peut pas être exploitée sans sa TVM, en service dès l'ouverture. Le fait prend la
 *     première date de mise en service de l'infobox, toujours en fiabilité `medium` (date déduite de
 *     l'ouverture, l'article ne date pas la TVM elle-même). Version : celle de l'infobox (TVM 300, TVM 430),
 *     ou `TVM` sans version quand l'infobox en cite deux (« TVM 300 / 430 ») ou une autre à l'origine
 *     (« TVM 300 (TVM 260 puis 270 à l'origine) »). Portée : toute la ligne s'il n'y a qu'une date de mise
 *     en service, sinon non précisée (la ligne a ouvert en plusieurs fois). Jamais pour l'ETCS, souvent
 *     ajouté après l'ouverture (« ETCS (mise en service reportée) »).
 */
final class SignallingInfoboxReader
{
    /** Hors service, reporté, prévu, supprimé : l'élément ne date pas une mise en service. */
    private const NEGATIVE = '/\b(?:HS|hors\s+service|report\w*|prévu\w*|projet\w*|futur\w*|à\s+terme|supprim\w+|dépos\w+|abandonn\w+|neutralis\w+|marche\s+à\s+vue|jusqu[’\']en)\b/iu';

    /** Une autre version a précédé celle de l'infobox : la version à l'ouverture n'est pas connue. */
    private const OTHER_VERSION = '/\b(?:origine|puis|auparavant|avant|ancien\w*|remplac\w+)\b|TVM\s*\d{3}\s*\/\s*\d{3}/iu';

    private const MAX_EXCERPT = 200;

    public function __construct(private readonly WikipediaLineParser $parser) {}

    /**
     * @param  list<string>  $ends  extrémités de la ligne (WikipediaLineParser::lineEnds)
     * @return list<array{system: string, role: string, date: string, precision: string, scope: string,
     *     section_from: ?string, section_to: ?string, pk_start_m: ?int, pk_end_m: ?int,
     *     confidence: string, origin: string, excerpt: string}>
     */
    public function facts(string $wikitext, array $ends, bool $lgv, \DateTimeInterface $today): array
    {
        $raw = self::rawField($wikitext, 'signalisation');
        if ($raw === null || trim($raw) === '') {
            return [];
        }
        $items = $this->items($raw);
        $facts = [];
        foreach ($items as $item) {
            foreach ($this->datedFacts($item, $ends, $today) as $fact) {
                $facts[] = $fact;
            }
        }
        if ($lgv) {
            $opening = $this->openingFact($items, $this->parser->parseInfobox($wikitext), $today);
            if ($opening !== null) {
                $facts[] = $opening;
            }
        }

        return $facts;
    }

    /**
     * Valeur brute (wikitexte) d'un champ de l'infobox, sur une ou plusieurs lignes, ou null s'il est absent.
     */
    public static function rawField(string $wikitext, string $key): ?string
    {
        $wikitext = (string) preg_replace('/<!--.*?-->/su', '', $wikitext);
        if (! preg_match('/\n\s*\|\s*'.preg_quote($key, '/').'\s*=([^\n]*)((?:\n(?!\s*\||\s*\}\}).*)*)/u', $wikitext, $m)) {
            return null;
        }

        return trim($m[1].$m[2]);
    }

    /**
     * Éléments du champ (un par ligne affichée) en texte brut : modèles d'insécabilité retirés, coupés aux
     * `<br>`, liens et références nettoyés.
     *
     * @return list<string>
     */
    private function items(string $raw): array
    {
        $raw = (string) preg_replace('/<ref[^>]*\/>|<ref[^>]*>.*?<\/ref>/isu', '', $raw);
        do {
            $raw = (string) preg_replace('/\{\{\s*(?:l?nobr|nowrap)\s*\|((?:[^{}]|\{\{[^{}]*\}\})*)\}\}/iu', '$1', $raw, -1, $n);
        } while ($n > 0);
        $out = [];
        foreach (preg_split('/<br\s*\/?>|\n/iu', $raw) ?: [] as $piece) {
            $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags($this->parser->toPlainText($piece))));
            $text = trim(str_replace(['⟦', '⟧'], '', $text), " ,;\t");
            if ($text !== '') {
                $out[] = $text;
            }
        }

        return $out;
    }

    /**
     * Systèmes datés d'un élément : « Capdenac - Viviez : BMU (depuis 1985) », « BAL (1965) de X à Y ».
     *
     * @param  list<string>  $ends
     * @return list<array<string, mixed>>
     */
    private function datedFacts(string $item, array $ends, \DateTimeInterface $today): array
    {
        $mentions = SignallingSystems::mentions($item);
        if ($mentions === [] || preg_match(self::NEGATIVE, $item)) {
            return [];
        }
        // « X - Y : système » : la portion précède les deux-points.
        $section = null;
        if (preg_match('/^(.+?)\s+[-–]\s+(.+?)\s*:\s/u', $item, $m) && ! SignallingSystems::cited($m[0])) {
            $section = ['from' => trim($m[1]), 'to' => trim($m[2]), 'pk_start' => null, 'pk_end' => null, 'whole_line' => false];
        }

        $facts = [];
        foreach ($mentions as $k => $mention) {
            $next = $mentions[$k + 1]['pos'] ?? mb_strlen($item);
            $segment = mb_substr($item, $mention['pos'], $next - $mention['pos']);
            $dates = array_values(array_filter($this->parser->findDates($segment), fn (array $d) => $d['precision'] !== 'decade'));
            if ($dates === [] || strtotime($dates[0]['date']) > $today->getTimestamp()) {
                continue;
            }
            $place = $section ?? $this->parser->findSection($segment, $ends);
            if ($place !== null && ($place['other_line'] ?? false)) {
                continue;
            }
            $date = $dates[0];
            $several = count(array_unique(array_column($dates, 'date'))) > 1;
            $facts[] = [
                'system' => $mention['system'],
                'role' => SignallingRole::forSystem($mention['system'])->value ?? SignallingRole::Block->value,
                'date' => $date['date'],
                'precision' => $date['precision'],
                'scope' => self::scope($place),
                'section_from' => $place['from'] ?? null,
                'section_to' => $place['to'] ?? null,
                'pk_start_m' => $place['pk_start'] ?? null,
                'pk_end_m' => $place['pk_end'] ?? null,
                'confidence' => $several || SignallingSystems::tooEarly($mention['system'], $date['year'])
                    ? 'low' : ($date['precision'] === 'year' ? 'medium' : 'high'),
                'origin' => 'infobox',
                'excerpt' => self::excerpt('Infobox, signalisation : '.$item),
            ];
        }

        return $facts;
    }

    /**
     * TVM d'une LGV, en service dès l'ouverture : première date de mise en service de l'infobox.
     *
     * @param  list<string>  $items
     * @param  array<string, string>  $infobox  infobox nettoyée (WikipediaLineParser::parseInfobox)
     * @return array<string, mixed>|null
     */
    private function openingFact(array $items, array $infobox, \DateTimeInterface $today): ?array
    {
        $item = null;
        $codes = [];
        foreach ($items as $candidate) {
            $tvm = array_values(array_filter(SignallingSystems::mentions($candidate), fn (array $m) => SignallingSystems::base($m['system']) === 'TVM'));
            if ($tvm !== []) {
                $item = $candidate;
                $codes = array_unique(array_column($tvm, 'system'));
                break;
            }
        }
        if ($item === null || preg_match(self::NEGATIVE, $item)) {
            return null;
        }
        $system = count($codes) === 1 && ! preg_match(self::OTHER_VERSION, $item) ? $codes[0] : 'TVM';

        $openings = [];
        foreach (['mise en service', 'mise en service2', 'mise en service3', 'mise en service4'] as $key) {
            $dates = array_values(array_filter($this->parser->findDates((string) ($infobox[$key] ?? '')), fn (array $d) => $d['precision'] !== 'decade'));
            if ($dates !== []) {
                $openings[] = $dates[0];
            }
        }
        if ($openings === []) {
            return null;
        }
        usort($openings, fn (array $a, array $b) => $a['date'] <=> $b['date']);
        $first = $openings[0];
        if (strtotime($first['date']) > $today->getTimestamp() || SignallingSystems::tooEarly('TVM', $first['year'])) {
            return null;
        }

        return [
            'system' => $system,
            'role' => SignallingRole::Cab->value,
            'date' => $first['date'],
            'precision' => $first['precision'],
            'scope' => count($openings) === 1 ? 'whole_line' : 'unspecified',
            'section_from' => null,
            'section_to' => null,
            'pk_start_m' => null,
            'pk_end_m' => null,
            'confidence' => 'medium',
            'origin' => 'opening',
            'excerpt' => self::excerpt('Infobox, signalisation : '.$item.' ; mise en service : '.trim((string) ($infobox['mise en service'] ?? ''))),
        ];
    }

    /** @param  array<string, mixed>|null  $section */
    private static function scope(?array $section): string
    {
        return match (true) {
            $section === null => 'unspecified',
            (bool) ($section['whole_line'] ?? false) => 'whole_line',
            ($section['from'] ?? null) !== null || ($section['to'] ?? null) !== null || ($section['pk_start'] ?? null) !== null => 'section',
            default => 'unspecified',
        };
    }

    private static function excerpt(string $text): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', str_replace(['—', '–'], '-', $text)));

        return mb_strlen($text) > self::MAX_EXCERPT ? rtrim(mb_substr($text, 0, self::MAX_EXCERPT - 1)).'…' : $text;
    }
}

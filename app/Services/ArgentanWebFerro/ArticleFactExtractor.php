<?php

namespace App\Services\ArgentanWebFerro;

use App\Enums\ArgentanFactKind;
use App\Enums\FactDatePrecision;
use App\Enums\LineEventType;
use App\Enums\StationType;
use App\Services\PlaceNames;
use Closure;

/**
 * Faits d'un article d'histoire de ligne d'Argentan Web Ferroviaire (HTML de WordPress).
 *
 * Ce qui est lu (les articles sont « tous droits réservés » : on n'en garde que des FAITS,
 * jamais leur texte ; au plus une phrase citée, 240 caractères) :
 *   - événements datés : chaque date citée (jour, mois, année, période « entre 1966 et 1971 »,
 *     « 1887/1888 ») reçoit le type du mot déclencheur de sa zone de phrase (ouverture,
 *     fermeture voyageurs, fermeture marchandises, dépose, déclassement, réouverture…) et la
 *     section la plus proche (« entre X et Y », « de X à Y », « la section X-Y », « jusqu'à Y »,
 *     chaînage « prolongée jusqu'à Y puis Z ») ; année en tête de paragraphe (« 1888: … ») et
 *     listes annoncées par « ouverte en plusieurs fois: » reconnues ;
 *   - arrêts : « Nom - PK 12.3 » (tiret typographique de l'article), « Nom (halte) - PK 4 », « Nom (pk 7.7) », « Nom (arrêt) »,
 *     et listes sans PK annoncées par « dessert les gares … suivantes: » ;
 *   - exploitants : « 1888->1891: Exploitation par … », « l'exploitation est reprise par … ».
 *
 * Ce qui est ignoré : légendes de photos (figcaption), tableaux (matériel, horaires),
 * fermetures de mines, de carrières ou de dépôts, dates sans mot déclencheur. Un fait non
 * daté n'est gardé (sans date) que pour une dépose, un déclassement, une électrification,
 * une fermeture ou une réouverture : rien n'est inventé.
 *
 * $isPlace (facultatif) dit si un nom est une gare connue : il sert à couper une section
 * « Ste Gauburge-Montreuil-Cernières » au bon trait d'union.
 */
final class ArticleFactExtractor
{
    private const MONTHS = [
        'janvier' => 1, 'février' => 2, 'fevrier' => 2, 'mars' => 3, 'avril' => 4, 'mai' => 5, 'juin' => 6,
        'juillet' => 7, 'août' => 8, 'aout' => 8, 'septembre' => 9, 'octobre' => 10, 'novembre' => 11,
        'décembre' => 12, 'decembre' => 12,
    ];

    /** Mot de toponyme : majuscule, lettres, apostrophes, point d'abréviation (« St. »). */
    private const WORD = "\\p{Lu}[\\p{L}'\u{2019}.]*";

    /** Petits mots admis à l'intérieur d'un toponyme (« Pré en Pail », « Bagnoles de l'Orne »). */
    private const LINK = "(?:(?:de|du|des|la|le|les|en|sur|sous|aux|au)\\s+|l['\u{2019}]|d['\u{2019}])*";

    /** Années plausibles d'une histoire ferroviaire. */
    private const YEAR = '(?:18[2-9]\d|19\d\d|20[0-3]\d)';

    /**
     * Mots déclencheurs, du plus précis au plus général : en cas de chevauchement, le premier
     * de la liste l'emporte (« fermée au trafic voyageurs » = fermeture voyageurs, pas totale).
     */
    private const TRIGGERS = [
        ['/\b(?:ferm\w*|suppression|suspension|cess\w*|dispar\w*|fin)\b[^.;:]{0,40}?\bvoyageurs?\b/iu', LineEventType::ClosurePassenger],
        ['/\b(?:service|trafic|trains?|circulations?)\s+voyageurs?\b[^.;:]{0,40}?\b(?:ferm\w*|cess\w*|dispar\w*|suspen\w*|suppr\w*|coordonn\w*|report\w*)/iu', LineEventType::ClosurePassenger],
        ['/\b(?:mise sur route|report\w* sur la route|service voyageurs se fait par autobus|est conventionn\w*|est coordonn\w*)/iu', LineEventType::ClosurePassenger],
        ['/\b(?:remis en route|r\x{e9}appara\w*|r\x{e9}tablit une desserte)/iu', LineEventType::ReopeningPassenger],
        ['/\br\x{e9}ouverture\b/iu', LineEventType::Reopening],
        ['/\b(?:ferm\w*|cess\w*|stopp\w*|arr\x{ea}t)\b[^.;:]{0,40}?\b(?:marchandises|fret)\b/iu', LineEventType::ClosureFreight],
        ['/\b(?:service|trafic|celui)\s+(?:des\s+)?(?:marchandises|fret)\b[^.;:]{0,30}?\b(?:ferm\w*|cess\w*|stopp\w*)/iu', LineEventType::ClosureFreight],
        ['/\bsupprimer le trafic (?:marchandises|fret)/iu', LineEventType::ClosureFreight],
        ['/\ble fret cess\w*/iu', LineEventType::ClosureFreight],
        ['/\b(?:d\x{e9}pose(?! le bilan)|d\x{e9}pos\x{e9}e?s?|d\x{e9}ferr\w*)\b/iu', LineEventType::Dismantling],
        ['/\bd\x{e9}class\w*/iu', LineEventType::Declassification],
        ['/\b(?:fermeture|ferme|ferment|fermera|ferm\x{e9}e?s?|fermer|cesse son activit\x{e9}|neutralis\x{e9}e?s?|arr\x{ea}t d\x{e9}finitif|s\'arr\x{ea}te d\x{e9}finitivement|fin de toutes les circulations|suppression de l\'exploitation|suspension, devenue d\x{e9}finitive|dernier train)\b/iu', LineEventType::Closure],
        ['/\b(?:ouverture|ouverte?s?|ouvre|mise en service|met en service|en service depuis|prolong\x{e9}e?|atteint)\b/iu', LineEventType::Opening],
        ['/\b(?:conc\x{e9}d\x{e9}e?s?|adjudication|concessionnaire en)\b/iu', LineEventType::Concession],
        ['/\b\x{e9}lectrifi\x{e9}e?\b/iu', LineEventType::Electrification],
        ['/\b(?:double voie est pos\x{e9}e|mise \x{e0} double voie|mises? \x{e0} voie unique)/iu', LineEventType::Modernization],
        ['/\b(?:d\x{e9}raill\w*|heurte|collision)\b/iu', LineEventType::Accident],
    ];

    /** Déclencheurs qui ne parlent pas de la ligne (mines, carrières, dépôts, bilan d'une compagnie). */
    private const NOT_THE_LINE = '/\b(?:fermeture des mines|la mine ferme|mine\w*[^.;]{0,30}ferm\w*|carri\x{e8}res?[^.;]{0,30}ferm\w*|d\x{e9}p\x{f4}t[^.;]{0,40}ferm\w*|ferm\w*[^.;]{0,15}ses portes)/iu';

    /** Types gardés sans date (fait attesté, date inconnue). */
    private const UNDATED_TYPES = [
        LineEventType::Dismantling, LineEventType::Declassification, LineEventType::Electrification,
        LineEventType::Closure, LineEventType::ClosurePassenger, LineEventType::ClosureFreight,
        LineEventType::Reopening, LineEventType::ReopeningPassenger,
    ];

    /** Ligne de tête d'une liste d'ouvertures (« ouverte en plusieurs fois: », « en 3 étapes: »). */
    private const LIST_LEAD = '/\b(?:ouverte?s?|ouverture|r\x{e9}alisation|\x{e9}tapes|mise en service)\b[^:]*:\s*$/iu';

    /** Ligne de tête d'une liste d'arrêts sans PK (« dessert les gares ou arrêts suivants: »). */
    private const STOP_LEAD = '/\b(?:desser\w*|desert)\b[^:]*\b(?:gares?|arr\x{ea}ts?|haltes?)\b[^:]*:\s*$/iu';

    public function __construct(private readonly ?Closure $isPlace = null) {}

    /** @return list<ExtractedFact> */
    public function extract(string $html): array
    {
        $facts = [];
        $listType = null;
        $stopList = false;
        $previousTo = null;
        $lastYear = null;
        foreach (self::blocks($html) as $block) {
            $stop = $this->stop($block);
            if ($stop !== null) {
                $facts[] = $stop;

                continue;
            }
            if ($stopList && self::looksLikeBareStop($block)) {
                $facts[] = new ExtractedFact(ArgentanFactKind::Stop, $block, stopName: self::cleanName($block));

                continue;
            }
            $stopList = (bool) preg_match(self::STOP_LEAD, $block);

            $operator = $this->operator($block);
            if ($operator !== null) {
                $facts[] = $operator;

                continue;
            }

            [$yearPrefix, $text] = self::yearPrefix($block);
            $isListItem = mb_strlen($block) <= 90 && ! preg_match('/[.:]\s*$/u', $block);
            $inherited = $isListItem ? $listType : null;
            foreach (self::sentences($text) as $sentence) {
                $takeover = $this->takeover($sentence, $yearPrefix);
                if ($takeover !== null) {
                    $facts[] = $takeover;
                }
                foreach ($this->events($sentence, $yearPrefix, $inherited, $previousTo, $lastYear) as $fact) {
                    $facts[] = $fact;
                    $previousTo = $fact->to ?? $previousTo;
                    $lastYear = $fact->yearTo ?? $fact->year ?? $lastYear;
                }
            }
            if (! $isListItem) {
                $listType = preg_match(self::LIST_LEAD, $block) ? LineEventType::Opening : null;
                $previousTo = null;
            }
        }

        return self::unique($facts);
    }

    /**
     * Texte de l'article en blocs (paragraphes, éléments de liste), sans légendes de photos,
     * tableaux, scripts ni codes courts ; tirets typographiques ramenés au trait d'union.
     *
     * @return list<string>
     */
    public static function blocks(string $html): array
    {
        $html = (string) preg_replace('#<(script|style|table|figcaption|iframe)\b.*?</\1>#is', ' ', $html);
        $html = (string) preg_replace('/\[su_tooltip\b[^\]]*\](.*?)\[\/su_tooltip\]/isu', '$1', $html);
        $html = (string) preg_replace('/\[[a-z_]+\b[^\]]*\]/iu', ' ', $html);
        $html = (string) preg_replace('#<br\s*/?>|</(?:p|h\d|li|div|blockquote|figure)>#i', "\n", $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = (string) preg_replace('/[\x{2013}\x{2014}\x{2012}\x{2015}]/u', '-', $text);
        $text = (string) preg_replace('/[\x{2019}\x{2018}\x{02bc}]/u', "'", $text);
        $text = (string) preg_replace('/[\x{00a0}\x{202f}\t ]+/u', ' ', $text);

        return array_values(array_filter(array_map('trim', explode("\n", $text)), fn (string $l) => $l !== ''));
    }

    /** @return list<string> phrases d'un bloc (pas de coupure après une initiale : « M. Laval », « T. Danglos ») */
    public static function sentences(string $text): array
    {
        $parts = preg_split('/(?<=[.!?])(?<!\b\p{Lu}\.)(?<!\bSt\.)\s+(?=[\p{Lu}\d])/u', $text) ?: [$text];

        return array_values(array_filter(array_map('trim', $parts), fn (string $s) => $s !== ''));
    }

    /** Citation : une phrase au plus, 240 caractères au plus (coupée au mot, avec points de suspension). */
    public static function quote(string $sentence): string
    {
        $sentence = trim((string) preg_replace('/\s+/u', ' ', $sentence));
        if (mb_strlen($sentence) <= 240) {
            return $sentence;
        }
        $cut = mb_substr($sentence, 0, 238);

        return rtrim((string) preg_replace('/\s+\S*$/u', '', $cut), ' ,;:').' …';
    }

    // ─── arrêts ─────────────────────────────────────────────────────────────

    /** Arrêt avec PK ou nature : « Nom - PK 12.3 (…) », « Nom (halte) - PK 4 », « Nom (pk 7.7) », « Nom (arrêt) » (tirets déjà ramenés à « - »). */
    private function stop(string $block): ?ExtractedFact
    {
        $nature = '(?:halte|arr\x{ea}t|arret|gare|bifurcation)';
        if (preg_match('/^(?<name>[^():\d][^():]{0,70}?)\s*(?:\((?<n1>'.$nature.')\)\s*)?-\s*PK\s*(?<pk>\d+(?:[.,]\d+)?)?\s*(?:\((?<rest>[^)]*)\)\s*)?$/iu', $block, $m)
            || preg_match('/^(?<name>[^():\d][^():]{0,70}?)\s*\(pk\s*(?<pk>\d+(?:[.,]\d+)?)(?<rest>[^)]*)\)(?<after>[^():]*)$/iu', $block, $m)
            || preg_match('/^(?<name>[^():\d][^():]{1,70}?)\s*\((?<n1>'.$nature.')\)\s*$/iu', $block, $m)) {
            $name = self::cleanName($m['name']);
            if ($name === '' || ! preg_match('/^\p{Lu}/u', $name) || str_word_count($name) > 8) {
                return null;
            }
            $natureWord = mb_strtolower(($m['n1'] ?? '') !== '' ? $m['n1'] : (string) ($m['rest'] ?? ''));
            $pk = ($m['pk'] ?? '') !== '' ? (int) round((float) str_replace(',', '.', $m['pk']) * 1000) : null;

            return new ExtractedFact(ArgentanFactKind::Stop, self::quote($block), stopName: $name,
                stopNature: self::nature($natureWord, $name), pkM: $pk);
        }

        return null;
    }

    private static function nature(string $word, string $name): ?StationType
    {
        return match (true) {
            str_contains($word, 'halte') => StationType::Halte,
            str_contains($word, 'arrêt'), str_contains($word, 'arret') => StationType::PointArret,
            str_contains($word, 'bifurcation'), (bool) preg_match('/^bif(?:ur(?:cation)?)?\b/iu', $name) => StationType::Embranchement,
            $word === 'gare' => StationType::Gare,
            default => null,
        };
    }

    /** Élément d'une liste d'arrêts sans PK : quelques mots à majuscule, sans verbe ni ponctuation finale. */
    private static function looksLikeBareStop(string $block): bool
    {
        if (preg_match('/^(?:Gare|Profil|Plan|Carte|Vue|D\x{e9}p\x{f4}t|Horaires?|Trac\x{e9}|Sch\x{e9}ma|Coll)\b/u', $block)) {
            return false;
        }

        return mb_strlen($block) <= 40 && (bool) preg_match('/^\p{Lu}[\p{L}\s\'\x{2019}\-\/]*$/u', $block)
            && count(preg_split('/\s+/u', $block) ?: []) <= 5;
    }

    private static function cleanName(string $name): string
    {
        $name = (string) preg_replace('/\s*\((?:halte|arr\x{ea}t|arret|gare)\)\s*/iu', ' ', $name);

        return trim((string) preg_replace('/\s+/u', ' ', $name), " -\t");
    }

    // ─── exploitants ────────────────────────────────────────────────────────

    /** Reprise d'exploitation datée dans une phrase : « l'exploitation est reprise par X », « X en reprend l'exploitation ». */
    private function takeover(string $sentence, ?int $yearPrefix): ?ExtractedFact
    {
        $who = null;
        if (preg_match("/(?:exploitation (?:est )?reprise par|reprise de la ligne par|confie l'exploitation \x{e0})\s+(?<who>[^,.;:()]+)/iu", $sentence, $m)
            || preg_match("/(?<who>(?:la |les |le )?(?:Compagnie|Soci\x{e9}t\x{e9}|Chemins? de Fer|SNCF|STAO|SCF|VFEO|CGFCS)[^,.;:()]*?)\s+(?:en\s+)?reprend l'exploitation/u", $sentence, $m)) {
            $who = trim((string) preg_replace('/^(?:la|les|le|l\')\s*/iu', '', trim($m['who'])));
            $who = trim((string) preg_replace('/\s+(?:en|au|le|depuis)\s+(?:\d.*)?$/u', '', $who));
        }
        if ($who === null || $who === '') {
            return null;
        }
        $dates = self::dates($sentence);
        $date = $dates[0] ?? null;
        $year = $date['year'] ?? $yearPrefix;
        if ($year === null) {
            return null;
        }

        return new ExtractedFact(ArgentanFactKind::Operator, self::quote($sentence), year: $year,
            month: $date['month'] ?? null, day: $date['day'] ?? null,
            precision: $date['precision'] ?? FactDatePrecision::Year, operator: self::quote($who));
    }

    private function operator(string $block): ?ExtractedFact
    {
        if (preg_match('/^(?<y1>'.self::YEAR.')\s*-+>\s*(?<y2>'.self::YEAR.')\s*:\s*(?<who>.+)$/u', $block, $m)) {
            $who = (string) preg_replace('/^(?:exploitation|reprise)\s+(?:par\s+)?(?:la\s+|les\s+|le\s+|l[\'\x{2019}])?/iu', '', trim($m['who']));

            return new ExtractedFact(ArgentanFactKind::Operator, self::quote($block), year: (int) $m['y1'], yearTo: (int) $m['y2'],
                precision: FactDatePrecision::Year, operator: self::quote($who));
        }

        return null;
    }

    // ─── événements ─────────────────────────────────────────────────────────

    /** @return array{0: ?int, 1: string} année en tête de paragraphe (« 1888: … ») et reste du texte */
    private static function yearPrefix(string $block): array
    {
        if (preg_match('/^('.self::YEAR.')\s*:\s*(.*)$/u', $block, $m)) {
            return [(int) $m[1], $m[2]];
        }

        return [null, $block];
    }

    /**
     * Événements d'une phrase. Chaque date reçoit le déclencheur de sa zone (entre les milieux
     * des intervalles qui la séparent des dates voisines) ; sans déclencheur, elle hérite du type
     * de la date précédente si elles sont reliées par « et », « puis » ou une virgule.
     *
     * @return list<ExtractedFact>
     */
    private function events(string $sentence, ?int $yearPrefix, ?LineEventType $inherited, ?string $previousTo, ?int $lastYear): array
    {
        $triggers = $this->triggers($sentence);
        $dates = self::dates($sentence);
        $quote = self::quote($sentence);

        if ($dates === []) {
            $contextYear = $yearPrefix ?? (preg_match('/\b(?:alors|la m\x{ea}me ann\x{e9}e|cette m\x{ea}me ann\x{e9}e)\b/iu', $sentence) ? $lastYear : null);
            $types = array_values(array_unique(array_map(fn (array $t) => $t['type'], $triggers), SORT_REGULAR));
            if ($types === [] && $inherited !== null && $contextYear !== null) {
                $types = [$inherited];
            }
            $section = $this->nearestSection($sentence, 0, mb_strlen($sentence));
            $out = [];
            foreach ($types as $type) {
                if ($contextYear === null && ! in_array($type, self::UNDATED_TYPES, true)) {
                    continue;
                }
                $out[] = new ExtractedFact(ArgentanFactKind::Event, $quote, type: $type, year: $contextYear,
                    precision: $contextYear !== null ? FactDatePrecision::Year : null,
                    from: $section['from'] ?? null, to: $section['to'] ?? null);
            }

            return $out;
        }

        $out = [];
        $previousType = null;
        $owner = [];
        foreach ($triggers as $k => $t) {
            $gaps = array_map(fn (array $d) => max(0, $t['start'] - $d['end'], $d['start'] - $t['end']), $dates);
            $owner[$k] = array_search(min($gaps), $gaps, true);
        }
        foreach ($dates as $i => $date) {
            $types = array_values(array_unique(array_map(fn (array $t) => $t['type'],
                array_filter($triggers, fn (array $t, int $k) => $owner[$k] === $i, ARRAY_FILTER_USE_BOTH)), SORT_REGULAR));
            if ($types === []) {
                $between = $i === 0 ? '' : mb_substr($sentence, $dates[$i - 1]['end'], $date['start'] - $dates[$i - 1]['end']);
                $chained = $previousType !== null && preg_match('/^\s*(?:,|et\b|puis\b)/u', $between) === 1;
                $types = match (true) {
                    $chained => [$previousType],
                    $inherited !== null => [$inherited],
                    default => [],
                };
            }
            if ($types === []) {
                continue;
            }
            $section = $this->sectionFor($sentence, $dates, $i);
            if (($section['from'] ?? null) === null && ($section['to'] ?? null) !== null) {
                $section['from'] = $previousTo;
            }
            foreach ($types as $type) {
                $out[] = new ExtractedFact(ArgentanFactKind::Event, $quote, type: $type,
                    year: $date['year'], month: $date['month'], day: $date['day'], yearTo: $date['year_to'], precision: $date['precision'],
                    from: $section['from'] ?? null, to: $section['to'] ?? null);
            }
            $previousTo = $section['to'] ?? $previousTo;
            $previousType = $types[0];
        }

        return $out;
    }

    /** @return list<array{type: LineEventType, start: int, end: int}> déclencheurs sans chevauchement, par position */
    private function triggers(string $sentence): array
    {
        $excluded = [];
        if (preg_match_all(self::NOT_THE_LINE, $sentence, $neg, PREG_OFFSET_CAPTURE)) {
            foreach ($neg[0] as [$text, $byte]) {
                $start = mb_strlen(substr($sentence, 0, $byte));
                $excluded[] = [$start, $start + mb_strlen($text)];
            }
        }
        $found = [];
        foreach (self::TRIGGERS as [$regex, $type]) {
            if (! preg_match_all($regex, $sentence, $mm, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            foreach ($mm[0] as [$text, $byte]) {
                $start = mb_strlen(substr($sentence, 0, $byte));
                $end = $start + mb_strlen($text);
                $overlaps = fn (array $span) => $start < $span[1] && $end > $span[0];
                if (array_filter($excluded, $overlaps) !== [] || array_filter($found, fn (array $f) => $overlaps([$f['start'], $f['end']])) !== []) {
                    continue;
                }
                $found[] = ['type' => $type, 'start' => $start, 'end' => $end];
            }
        }
        usort($found, fn (array $a, array $b) => $a['start'] <=> $b['start']);

        return $found;
    }

    /**
     * Dates citées, dans l'ordre : jour (« 22/12/1881 », « 26 mai 1991 »), mois (« février 1858 »,
     * « 03/1995 »), période (« entre 1966 et 1971 », « 1887/1888 »), année.
     *
     * @return list<array{start: int, end: int, year: int, month: ?int, day: ?int, year_to: ?int, precision: FactDatePrecision}>
     */
    public static function dates(string $sentence): array
    {
        $months = implode('|', array_keys(self::MONTHS));
        $patterns = [
            ['/\b(\d{1,2})\/(\d{1,2})\/('.self::YEAR.')\b/u', fn (array $m) => [(int) $m[3], (int) $m[2], (int) $m[1], null, FactDatePrecision::Day]],
            ['/\b(1er|\d{1,2})\s+('.$months.')\s+('.self::YEAR.')\b/iu', fn (array $m) => [(int) $m[3], self::MONTHS[mb_strtolower($m[2])], (int) $m[1], null, FactDatePrecision::Day]],
            ['/\b('.$months.')\s+('.self::YEAR.')\b/iu', fn (array $m) => [(int) $m[2], self::MONTHS[mb_strtolower($m[1])], null, null, FactDatePrecision::Month]],
            ['/\b(\d{1,2})\/('.self::YEAR.')\b/u', fn (array $m) => [(int) $m[2], (int) $m[1], null, null, FactDatePrecision::Month]],
            ['/\b(?:entre|de)\s+('.self::YEAR.')\s+(?:et|\x{e0})\s+('.self::YEAR.')\b/iu', fn (array $m) => [(int) $m[1], null, null, (int) $m[2], FactDatePrecision::Range]],
            ['/\b('.self::YEAR.')\/('.self::YEAR.')\b/u', fn (array $m) => [(int) $m[1], null, null, (int) $m[2], FactDatePrecision::Range]],
            ['/(?<![\d\/.,])('.self::YEAR.')(?![\d\/]|\s*(?:km|m\b|T\b|ch\b|kg|places))/u', fn (array $m) => [(int) $m[1], null, null, null, FactDatePrecision::Year]],
        ];
        $found = [];
        foreach ($patterns as [$regex, $build]) {
            if (! preg_match_all($regex, $sentence, $all, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                continue;
            }
            foreach ($all as $m) {
                $start = mb_strlen(substr($sentence, 0, $m[0][1]));
                $end = $start + mb_strlen($m[0][0]);
                if (array_filter($found, fn (array $f) => $start < $f['end'] && $end > $f['start']) !== []) {
                    continue;
                }
                [$year, $month, $day, $yearTo, $precision] = $build(array_map(fn (array $g) => $g[0], $m));
                if ($month !== null && ($month < 1 || $month > 12) || $day !== null && ($day < 1 || $day > 31)) {
                    continue;
                }
                $found[] = ['start' => $start, 'end' => $end, 'year' => $year, 'month' => $month, 'day' => $day, 'year_to' => $yearTo, 'precision' => $precision];
            }
        }
        usort($found, fn (array $a, array $b) => $a['start'] <=> $b['start']);

        return $found;
    }

    // ─── sections ───────────────────────────────────────────────────────────

    /**
     * Section la plus proche de la date $i ; à défaut, la seule section de la phrase.
     *
     * @param  list<array{start: int, end: int}>  $dates
     * @return array{from?: ?string, to?: ?string}
     */
    private function sectionFor(string $sentence, array $dates, int $i): array
    {
        $sections = $this->sections($sentence);
        if ($sections === []) {
            return [];
        }
        $mine = array_values(array_filter($sections, function (array $s) use ($dates, $i): bool {
            $distance = fn (array $d) => max(0, $s['start'] - $d['end'], $d['start'] - $s['end']);
            $best = array_keys(array_map($distance, $dates), min(array_map($distance, $dates)));

            return in_array($i, $best, true);
        }));
        if ($mine !== []) {
            return $mine[0];
        }

        return count($sections) === 1 ? $sections[0] : [];
    }

    /** @return array{from?: ?string, to?: ?string} */
    private function nearestSection(string $sentence, int $start, int $end): array
    {
        $sections = $this->sections(mb_substr($sentence, $start, $end - $start));

        return $sections[0] ?? [];
    }

    /** @return list<array{start: int, end: int, from: ?string, to: ?string}> */
    private function sections(string $sentence): array
    {
        $name = self::WORD.'(?:(?:\s*-\s*|\s+|\/)'.self::LINK.self::WORD.')*';
        $prefix = "(?:la gare d(?:e|es|u)\\s+|le\\s+|la\\s+|les\\s+|l['\u{2019}])?";
        $patterns = [
            ['/\bentre\s+'.$prefix.'(?<a>'.$name.')\s+et\s+'.$prefix.'(?<b>'.$name.')/u', 'pair'],
            ['/\b(?:de|du|d[\'\x{2019}])\s*(?<a>'.$name.')\s+(?:\x{e0}|au|aux|jusqu[\'\x{2019}](?:\x{e0}|au|aux))\s+'.$prefix.'(?<b>'.$name.')/u', 'pair'],
            ['/\b(?:sections?|tron\x{e7}ons?|antenne)(?:\s+de\s+ligne)?\s+(?:de\s+|d[\'\x{2019}])?(?<c>'.$name.')/u', 'compound'],
            ['/\bpour\s+(?<c>'.$name.')/u', 'compound'],
            ['/^(?<c>'.$name.')\s+(?:en|ferme|ouvre)\b/u', 'compound'],
            ['/\b(?:jusqu[\'\x{2019}](?:\x{e0}|au|aux)|[Vv]ers)\s+'.$prefix.'(?<b>'.$name.')/u', 'to'],
            ['/\bet\s+(?:\x{e0}\s+)?(?<b>'.$name.')\s+en\s+'.self::YEAR.'/u', 'compound_or_to'],
        ];
        $found = [];
        foreach ($patterns as [$regex, $kind]) {
            if (! preg_match_all($regex, $sentence, $all, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                continue;
            }
            foreach ($all as $m) {
                $start = mb_strlen(substr($sentence, 0, $m[0][1]));
                $end = $start + mb_strlen($m[0][0]);
                if (array_filter($found, fn (array $f) => $start < $f['end'] && $end > $f['start']) !== []) {
                    continue;
                }
                [$from, $to] = match ($kind) {
                    'pair' => [$m['a'][0], $m['b'][0]],
                    'compound' => $this->split($m['c'][0]) ?? [null, null],
                    'compound_or_to' => $this->split($m['b'][0]) ?? [null, $m['b'][0]],
                    default => [null, $m['b'][0]],
                };
                if ($from === null && $to === null) {
                    continue;
                }
                $found[] = ['start' => $start, 'end' => $end, 'from' => self::trimName($from), 'to' => self::trimName($to)];
            }
        }
        usort($found, fn (array $a, array $b) => $a['start'] <=> $b['start']);

        return $found;
    }

    /**
     * Coupe « Tinchebray-Les Maures », « Mortagne au Perche/Condé sur Huisne » en deux gares.
     * « Condé/Huisne » (« / » pour « sur ») n'est pas coupé : la partie droite doit compter deux
     * mots. Parmi les coupures possibles, celle dont les deux moitiés sont des gares connues ;
     * à défaut, « - » entouré d'espaces, puis le premier trait d'union.
     *
     * @return array{0: string, 1: string}|null
     */
    public function split(string $compound): ?array
    {
        $candidates = [];
        if (preg_match_all('/\s*-\s*|\//u', $compound, $all, PREG_OFFSET_CAPTURE)) {
            foreach ($all[0] as [$sep, $byte]) {
                $left = trim(substr($compound, 0, $byte));
                $right = trim(substr($compound, $byte + strlen($sep)));
                if ($left === '' || $right === '' || ! preg_match('/^\p{Lu}/u', $right)) {
                    continue;
                }
                if ($sep === '/' && count(preg_split('/\s+/u', $right) ?: []) < 2) {
                    continue;
                }
                $score = ($this->isPlace($left) ? 2 : 0) + ($this->isPlace($right) ? 2 : 0) + (trim($sep) !== $sep ? 1 : 0);
                $candidates[] = ['score' => $score, 'pair' => [$left, $right]];
            }
        }
        if ($candidates === []) {
            return null;
        }
        $best = $candidates[0];
        foreach ($candidates as $c) {
            if ($c['score'] > $best['score']) {
                $best = $c;
            }
        }

        return $best['pair'];
    }

    private function isPlace(string $name): bool
    {
        return $this->isPlace !== null && ($this->isPlace)($name);
    }

    private static function trimName(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }
        $name = trim((string) preg_replace('/[\s,.;:]+$/u', '', $name));

        return $name === '' ? null : $name;
    }

    /**
     * Doublons d'un même article (même fait cité deux fois) : le premier est gardé.
     *
     * @param  list<ExtractedFact>  $facts
     * @return list<ExtractedFact>
     */
    private static function unique(array $facts): array
    {
        $out = [];
        foreach ($facts as $f) {
            $key = $f->kind === ArgentanFactKind::Stop ? 'stop|'.self::normalizeName((string) $f->stopName) : $f->key();
            $kept = $out[$key] ?? null;
            if ($kept === null) {
                $out[$key] = $f;
            } elseif ($f->kind === ArgentanFactKind::Stop) {
                // Même arrêt cité deux fois (liste et légende) : on complète PK et nature.
                $out[$key] = $kept->with(['pkM' => $kept->pkM ?? $f->pkM, 'stopNature' => $kept->stopNature ?? $f->stopNature]);
            }
        }

        return array_values($out);
    }

    /** Nom normalisé pour comparer des gares (« St Pierre d'Entremont » = « Saint-Pierre-d'Entremont »). */
    public static function normalizeName(string $name): string
    {
        return PlaceNames::normalize($name);
    }
}

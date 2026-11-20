<?php

namespace App\Services\Materials;

use App\Services\WikipediaLineParser;

/**
 * Périodes de circulation d'une série sur une ligne, lues dans les phrases d'un article Wikipédia
 * qui portent un lien : vers la série dans l'article d'une LIGNE, vers la ligne dans l'article d'une
 * SÉRIE. Seuls des faits datés sont gardés (début, fin, ou les deux, avec leur précision) ; la
 * phrase elle-même ne sert qu'à relire et n'est jamais montrée.
 *
 * Lecture, proposition par proposition (coupure aux « ; », « puis », « tandis que », « mais ») :
 *   - période : « de 1971 à 1989 », « du 28/09/1975 à 1986 », « entre 1962 et 1990 », « 1975-1987 »,
 *     « de 1934 jusqu'à l'électrification … en 1959 » ;
 *   - début : « depuis 2009 », « à partir du 6 septembre 2009 », « dès 1975 », ou une date seule avec
 *     un verbe d'arrivée (« mis en service », « arrivée », « introduits », « font leur apparition ») ;
 *   - fin : « jusqu'en 1990 », ou une date seule avec un verbe de départ (« retirés », « disparaissent »,
 *     « dernier ») ;
 *   - remplacement : « les X 72500 sont remplacés par les B 82500 en 2009 », « en remplacement des
 *     X 72500 », « laissent la place aux » : la série remplacée finit, celle qui la remplace commence.
 * Une date seule sans verbe (« en 2019, le parcours est assuré par des Z 50000 ») atteste une présence,
 * pas un début : rien n'est gardé.
 *
 * Écarté : futur, conditionnel, projet, commande, livraison, essai ou circulation exceptionnelle
 * (« venues en renfort », « affrété »), date à venir, début postérieur à la fin.
 * Une série citée comme terme de comparaison (« un confort similaire aux X 73500 », « à l'image des »)
 * ne reçoit aucune période (WikipediaMaterialSections::isCompared()).
 *
 * Fiabilité (comme l'historique de la signalisation) :
 *   - `high`   : chaque date au jour ou au mois ;
 *   - `medium` : une date à l'année ;
 *   - `low`    : date approximative (« vers 1937 », « années 1970 », « fin 1952 »), plus de deux dates
 *                dans la proposition, période et remplacement mêlés, date loin de la série citée,
 *                autre ligne ou autre série citée dans la même proposition (le fait peut la concerner),
 *                chiffres de fréquentation. Gardé, jamais montré.
 */
final class MaterialPeriodExtractor
{
    /** Article d'une ligne : les liens désignent des séries. */
    public const MODE_LINE_ARTICLE = 'line_article';

    /** Article d'une série : les liens désignent des lignes ; la série est le sujet. */
    public const MODE_MATERIAL_ARTICLE = 'material_article';

    private const MAX_EXCERPT = 200;

    /** Écart maximal (caractères) entre le verbe d'arrivée ou de départ et la série qu'il date. */
    private const MAX_VERB_DISTANCE = 70;

    /** Écart maximal (caractères) entre la date et la mention la plus proche pour un fait sûr. */
    private const MAX_DISTANCE = 120;

    /**
     * Futur, conditionnel, projet, commande ou livraison : la phrase ne dit pas que la série a roulé. Mêmes
     * familles que le filtre des liens (WikipediaMaterialSections : DOUBT_WORDS écarte la phrase, UNCERTAIN_WORDS
     * fait tomber le lien à `low`), plus les mots qui datent autre chose que la circulation (livraison,
     * construction) et des formes plus larges (« spécialement », « étudiés »).
     */
    private const DOUBTFUL = '/\b(?:'.WikipediaMaterialSections::DOUBT_WORDS.'|'.WikipediaMaterialSections::UNCERTAIN_WORDS
        .'|destiné\w*|command\w*|livr(?:é|ée|és|ées|aison\w*)|construit\w*|exceptionnel\w*|spécial\w*|étudi\w*)\b/iu';

    /** Chiffres de trafic : la date porte sur la fréquentation, pas sur le matériel. */
    private const STATISTIC = '/%|\bfréquentation\b|\bvoyageurs\s+par\b|\bproportion\b|\b\d+\s+trains\b/iu';

    /** Autre action datée juste avant la date (« la mise sous tension de la ligne de 1954 à 1957 ») : la date est la sienne. */
    private const OTHER_ACTION = '/(?:électrifi\w*|mise\s+sous\s+tension|ouvert\w*|ouverture|fermet\w+|ferm(?:é|ée|és|ées)|construction|travaux|inaugur\w*)\b[^.;]{0,40}$/iu';

    /** Marque d'un début juste avant la date. */
    private const START_BEFORE = '/(?:\bdepuis|\bdès|\bà\s+partir|\bà\s+compter)(?:\s+(?:du|de|d[’\']))?(?:\s*(?:le|la\s+fin|le\s+début|le\s+milieu|les\s+années|l[’\']année)\s*(?:de\s+|d[’\'])?)?\s*$/iu';

    /** Début d'une période (« de 1971 à… », « entre 1962 et… », « (1975-1987) »). */
    private const RANGE_BEFORE = '/(?:\bde|\bdu|\bd[’\']|\bentre|\()\s*(?:(?:le|la\s+fin\s+de|le\s+début\s+de|l[’\']année)\s*)?$/iu';

    /** Marque d'une fin juste avant la date. */
    private const END_BEFORE = '/\bjusqu[’\']\s*(?:en|au|à|à\s+la\s+fin\s+de|à\s+fin|à\s+l[’\']année)\s*(?:de\s+|d[’\'])?$/iu';

    /** Ce qui sépare les deux dates d'une période. */
    private const RANGE_JOIN = '/^\s*(?:,\s*)?(?:à|au|aux|et|-|\/|jusqu[’\'](?:en|au|à)(?:\s+(?:la\s+fin\s+de|fin))?|jusqu[’\'][^.;]{0,90}?\ben)\s*(?:le\s+|l[’\']année\s+|en\s+)?$/iu';

    /** Mots qui rendent la date approximative quand ils la précèdent. */
    private const APPROXIMATE = '/(?:\bvers|\benviron|\bautour\s+de|\baux\s+alentours\s+de|\bcourant|\bfin|\bdébut|\bmilieu|\bau\s+cours\s+de|\bannées?|\bavant|\baprès|\bpériode)\s*(?:de\s+|d[’\']|des\s+|l[’\']année\s+|le\s+|les\s+années\s+)?$/iu';

    /** Arrivée sur la ligne (avec une date seule). */
    private const START_VERB = '/\bmise?s?\s+en\s+(?:service|exploitation|circulation)|\barriv(?:ée|ent|èrent|ent|ant|é|ée|és|ées)\b|\bintrodu\w+|\bapparai\w+|\bapparition\b|\bprennent\s+le\s+relais|\bpris\s+le\s+relais|\breprennent\b|\bcirculent\s+(?:depuis|à\s+partir)|\bremis\s+en\s+service/iu';

    /** Départ de la ligne (avec une date seule). */
    private const END_VERB = '/\bretir(?:é|ée|és|ées|ent|èrent)\b|\bdisparai\w+|\bdisparu\w*|\bdisparition\b|\bradi(?:é|ée|és|ées)\b|\bréform(?:é|ée|és|ées)\b|\bquitt(?:e|ent|èrent|é|ée|és|ées)\b|\bderni(?:er|ère|ers|ères)\s+(?:trains?|roulements?|services?|circulations?|voyages?|parcours|trajets?)\b|\bfin\s+de\s+(?:leur\s+)?(?:service|carrière)|\bcess(?:e|ent|èrent|é)\b|\bmis(?:es)?\s+fin/iu';

    /** Remplacement où la série citée AVANT le mot s'en va (« sont remplacées par », « laissent la place aux »). */
    private const REPLACED_BY = '/\b(?:remplac(?:é|ée|és|ées)\s+(?:\S+\s+){0,3}?par|évinc(?:é|ée|és|ées)\s+par|supplant(?:é|ée|és|ées)\s+par|(?:laiss|cèd|céd)\w*\s+(?:la\s+)?place\s+(?:\S+\s+){0,3}?(?:à|aux|au)|relay(?:é|ée|és|ées)\s+par)\b/iu';

    /** Remplacement où la série citée AVANT le mot arrive (« remplacent les », « en remplacement des », « succèdent aux »). */
    private const REPLACING = '/\b(?:remplac(?:ent|èrent|e|ant|ait|aient)|ont\s+remplacé|a\s+remplacé|en\s+remplacement\s+d(?:es|u|e)|succ[èé]d\w*|évinc(?:ent|èrent)|supplant(?:ent|èrent))\b/iu';

    /** Libellé de série juste avant un nombre (« X 2000 », « BB 1600 ») : ce nombre n'est pas une année. */
    private const SERIES_PREFIX = '/\b(?:BB|CC|AA|A1A|X|XR|XBD|Z|ZR|Y|B|RGP|TGV|EAD|AGC|TER|n°|série|séries|type|types|class)\s*$/u';

    public function __construct(private readonly WikipediaLineParser $parser) {}

    /**
     * Faits datés d'un texte de section.
     *
     * @param  string  $wikitext  texte de la section (wikitexte)
     * @param  callable(string): list<int>  $mentions  cible de lien → identifiants (séries dans un article de ligne, lignes dans un article de série)
     * @param  callable(string): bool  $ambiguous  cible de lien qui rend la proposition ambiguë (autre ligne, autre série)
     * @param  string  $mode  self::MODE_LINE_ARTICLE ou self::MODE_MATERIAL_ARTICLE
     * @return list<array{id: int, from: ?array{date: string, precision: string}, to: ?array{date: string, precision: string}, confidence: string, excerpt: string}>
     */
    public function extract(string $wikitext, callable $mentions, callable $ambiguous, string $mode, ?\DateTimeInterface $today = null): array
    {
        $today ??= new \DateTimeImmutable;
        $facts = [];
        foreach (WikipediaMaterialSections::linkedSentences($wikitext) as $sentence) {
            foreach ($this->clauses($sentence['text']) as [$start, $clause]) {
                $found = [];
                $isAmbiguous = false;
                foreach ($sentence['links'] as $link) {
                    // Hors de la proposition, ou série citée comme terme de comparaison (« similaire aux X 73500 »).
                    if ($link['pos'] < $start || $link['pos'] >= $start + mb_strlen($clause) || $link['compared']) {
                        continue;
                    }
                    $ids = $mentions($link['target']);
                    if ($ids === [] && $ambiguous($link['target'])) {
                        $isAmbiguous = true;
                    }
                    foreach ($ids as $id) {
                        $found[] = ['id' => $id, 'pos' => $link['pos'] - $start, 'length' => $link['length']];
                    }
                }
                if ($found !== []) {
                    array_push($facts, ...$this->factsOfClause($clause, $found, $isAmbiguous, $mode, $today));
                }
            }
        }

        return $this->deduplicated($facts);
    }

    /**
     * @param  non-empty-list<array{id: int, pos: int, length: int}>  $found
     * @return list<array<string, mixed>>
     */
    private function factsOfClause(string $clause, array $found, bool $ambiguous, string $mode, \DateTimeInterface $today): array
    {
        if (preg_match(self::DOUBTFUL, $clause)) {
            return [];
        }
        $dates = $this->dates($clause, $mode === self::MODE_LINE_ARTICLE ? $found : []);
        if ($dates === []) {
            return [];
        }
        $reading = $this->reading($clause, $dates);
        if ($reading === null) {
            return [];
        }
        [$from, $to, $verbPos] = $reading;
        $replacement = $this->replacement($clause);
        $distinct = count(array_unique(array_column($dates, 'date')));
        $low = $distinct > 2 || ($distinct === 2 && ($from === null || $to === null))
            || $ambiguous || preg_match(self::STATISTIC, $clause) === 1
            || ($replacement !== null && $from !== null && $to !== null)
            || array_filter([$from, $to], fn (?array $d): bool => $d !== null && ($d['approximate'] || $d['other_action'])) !== [];
        if ($replacement === null && $mode === self::MODE_LINE_ARTICLE) {
            $found = $this->nearSegment($clause, $found, ($from ?? $to)['pos']);
        }

        $out = [];
        foreach ($found as $mention) {
            // Date seule datée par un verbe : le verbe doit porter sur la série (« la mise en service des 2D2 »),
            // pas sur un train ou un service (« la mise en service d'Aqualys…, voitures tractées par des BB 7200 »).
            $gap = $verbPos === null ? 0 : ($verbPos >= $mention['pos'] + $mention['length'] ? $verbPos - $mention['pos'] - $mention['length'] : $mention['pos'] - $verbPos);
            if ($mode === self::MODE_LINE_ARTICLE && $gap > self::MAX_VERB_DISTANCE) {
                continue;
            }
            [$start, $end] = [$from, $to];
            // « Depuis 2003, les Z 5300 disparaissent » : la date marque le départ de cette série-là.
            if ($replacement === null && $end === null && $mode === self::MODE_LINE_ARTICLE
                && preg_match(self::END_VERB, mb_substr($clause, $mention['pos'] + $mention['length'], 40))) {
                [$start, $end] = [null, $from];
            }
            if ($replacement !== null && ($from === null || $to === null)) {
                $date = $from ?? $to;
                // Article de série : la série (sujet, avant le mot) est du côté « avant ».
                $before = $mode === self::MODE_MATERIAL_ARTICLE || $mention['pos'] < $replacement['pos'];
                $leaves = $replacement['before_leaves'] === $before;
                [$start, $end] = $leaves ? [null, $date] : [$date, null];
            }
            if ($start !== null && $end !== null && $start['date'] > $end['date']) {
                continue;
            }
            if (($start !== null && $start['date'] > $today->format('Y-m-d')) || ($end !== null && $end['date'] > $today->format('Y-m-d'))) {
                continue;
            }
            $used = array_filter([$start, $end]);
            $far = min(array_map(fn (array $d): int => abs($d['pos'] - $mention['pos']), $used)) > self::MAX_DISTANCE && $mode === self::MODE_LINE_ARTICLE;
            $confidence = match (true) {
                $low || $far => 'low',
                array_filter($used, fn (array $d): bool => $d['precision'] === 'year') !== [] => 'medium',
                default => 'high',
            };
            $out[] = [
                'id' => $mention['id'],
                'from' => $start === null ? null : ['date' => $start['date'], 'precision' => $start['precision']],
                'to' => $end === null ? null : ['date' => $end['date'], 'precision' => $end['precision']],
                'confidence' => $confidence,
                'excerpt' => $this->excerpt($clause),
            ];
        }

        return $out;
    }

    /**
     * Séries de la même partie de phrase (entre deux virgules) que la date : « …des Z 20500 et, depuis le
     * 7 octobre 2013, avec des Z 50000 » ne date que les Z 50000. Si cette partie n'en cite aucune, la
     * première partie suivante qui en cite (« À partir de 1996, les X 4500 assurent… »), sinon la
     * précédente.
     *
     * @param  non-empty-list<array{id: int, pos: int, length: int}>  $found
     * @return non-empty-list<array{id: int, pos: int, length: int}>
     */
    private function nearSegment(string $clause, array $found, int $datePos): array
    {
        // Bornes des parties : virgules hors parenthèses.
        $bounds = [0];
        $depth = 0;
        $length = mb_strlen($clause);
        for ($i = 0; $i < $length; $i++) {
            $char = mb_substr($clause, $i, 1);
            $depth += $char === '(' ? 1 : ($char === ')' && $depth > 0 ? -1 : 0);
            if ($char === ',' && $depth === 0) {
                $bounds[] = $i + 1;
            }
        }
        $bounds[] = $length + 1;
        $segmentOf = function (int $pos) use ($bounds): int {
            for ($k = count($bounds) - 2; $k >= 0; $k--) {
                if ($pos >= $bounds[$k]) {
                    return $k;
                }
            }

            return 0;
        };
        $bySegment = [];
        foreach ($found as $mention) {
            $bySegment[$segmentOf($mention['pos'])][] = $mention;
        }
        $home = $segmentOf($datePos);
        if (isset($bySegment[$home])) {
            return $bySegment[$home];
        }
        $after = array_filter(array_keys($bySegment), fn (int $k): bool => $k > $home);
        $before = array_filter(array_keys($bySegment), fn (int $k): bool => $k < $home);

        return $bySegment[$after !== [] ? min($after) : max($before)];
    }

    /**
     * Début et fin lus dans la proposition, ou null si elle ne date ni l'un ni l'autre.
     *
     * @param  non-empty-list<array{pos: int, end: int, date: string, precision: string, approximate: bool, other_action: bool, before: string}>  $dates
     * @return array{0: ?array<string, mixed>, 1: ?array<string, mixed>, 2: ?int}|null début, fin, position du verbe qui date (date seule)
     */
    private function reading(string $clause, array $dates): ?array
    {
        $count = count($dates);
        for ($i = 0; $i + 1 < $count; $i++) {
            $between = mb_substr($clause, $dates[$i]['end'], $dates[$i + 1]['pos'] - $dates[$i]['end']);
            $opens = preg_match(self::RANGE_BEFORE, $dates[$i]['before']) || preg_match(self::START_BEFORE, $dates[$i]['before']);
            if (preg_match(self::RANGE_JOIN, $between) && ($opens || preg_match('/^\s*-\s*$/u', $between))) {
                return [$dates[$i], $dates[$i + 1], null];
            }
        }
        $start = array_values(array_filter($dates, fn (array $d): bool => preg_match(self::START_BEFORE, $d['before']) === 1))[0] ?? null;
        $end = array_values(array_filter($dates, fn (array $d): bool => preg_match(self::END_BEFORE, $d['before']) === 1))[0] ?? null;
        if ($start !== null || $end !== null) {
            return [$start, $end, null];
        }
        if (count(array_unique(array_column($dates, 'date'))) !== 1) {
            return null;
        }
        // Une date seule : le verbe dit s'il s'agit d'une arrivée ou d'un départ.
        $date = $dates[0];
        if ($this->replacement($clause) !== null) {
            return [$date, null, null];
        }
        if (preg_match(self::START_VERB, $clause, $m, PREG_OFFSET_CAPTURE)) {
            return [$date, null, mb_strlen(substr($clause, 0, $m[0][1]))];
        }
        if (preg_match(self::END_VERB, $clause, $m, PREG_OFFSET_CAPTURE)) {
            return [null, $date, mb_strlen(substr($clause, 0, $m[0][1]))];
        }

        return null;
    }

    /**
     * Mot de remplacement de la proposition : position et sens (vrai si la série citée avant lui s'en va).
     *
     * @return array{pos: int, before_leaves: bool}|null
     */
    private function replacement(string $clause): ?array
    {
        if (preg_match(self::REPLACED_BY, $clause, $m, PREG_OFFSET_CAPTURE)) {
            return ['pos' => mb_strlen(substr($clause, 0, $m[0][1])), 'before_leaves' => true];
        }
        if (preg_match(self::REPLACING, $clause, $m, PREG_OFFSET_CAPTURE)) {
            return ['pos' => mb_strlen(substr($clause, 0, $m[0][1])), 'before_leaves' => false];
        }

        return null;
    }

    /**
     * Dates de la proposition, sans les nombres qui appartiennent au nom d'une série (« X 2000 »).
     *
     * @param  list<array{id: int, pos: int, length: int}>  $found  mentions de séries (positions masquées)
     * @return list<array{pos: int, end: int, date: string, precision: string, approximate: bool, other_action: bool, before: string}>
     */
    private function dates(string $clause, array $found): array
    {
        $masked = $clause;
        foreach ($found as $m) {
            $masked = mb_substr($masked, 0, $m['pos']).str_repeat('#', $m['length']).mb_substr($masked, $m['pos'] + $m['length']);
        }
        $raw = $this->parser->findDates($masked);
        // « 28/09/1975 » : jour précis (findDates ignore une année précédée d'une barre oblique).
        if (preg_match_all('~(?<!\d)([0-3]?\d)/([01]?\d)/(1[89]\d\d|20[0-3]\d)(?!\d)~u', $masked, $all, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            foreach ($all as $m) {
                [$d, $mo, $y] = [(int) $m[1][0], (int) $m[2][0], (int) $m[3][0]];
                if (checkdate($mo, $d, $y)) {
                    $raw[] = ['pos' => mb_strlen(substr($masked, 0, $m[0][1])), 'date' => sprintf('%04d-%02d-%02d', $y, $mo, $d), 'year' => $y, 'precision' => 'day'];
                }
            }
        }
        usort($raw, fn (array $a, array $b): int => $a['pos'] <=> $b['pos']);

        $out = [];
        foreach ($raw as $d) {
            $before = mb_substr($masked, max(0, $d['pos'] - 45), min(45, $d['pos']));
            if ($d['precision'] !== 'decade' && preg_match(self::SERIES_PREFIX, $before)) {
                continue;
            }
            $decade = $d['precision'] === 'decade';
            $pos = $decade ? $d['pos'] + 7 : $d['pos'];
            $literal = preg_match('~^(?:(?:1er|\d{1,2})\s+)?(?:\p{L}+\s+)?\d{4}|^\d{1,2}/\d{1,2}/\d{4}~u', mb_substr($masked, $pos, 30), $lm) ? mb_strlen($lm[0]) : 4;
            $before = mb_substr($masked, max(0, $pos - 45), min(45, $pos));
            $out[] = [
                'pos' => $pos,
                'end' => $pos + $literal,
                'date' => $d['date'],
                'precision' => $decade ? 'year' : $d['precision'],
                'approximate' => $decade || preg_match(self::APPROXIMATE, $before) === 1
                    || preg_match('/^\s*(?:environ|env\.|\?)/iu', mb_substr($masked, $pos + $literal, 12)) === 1,
                'other_action' => preg_match(self::OTHER_ACTION, mb_substr($before, -45)) === 1,
                'before' => $before,
            ];
        }

        return $out;
    }

    /**
     * Propositions d'une phrase, avec leur position de départ (en caractères).
     *
     * @return list<array{0: int, 1: string}>
     */
    private function clauses(string $sentence): array
    {
        $parts = preg_split('/\s*;\s*|,?\s+puis\s+|,\s+(?=(?:alors|tandis|mais)\b)|\s+(?:tandis|alors)\s+que\s+|,?\s+et\s*,?\s+(?=(?:depuis|dès|à\s+partir|jusqu))/iu', $sentence, -1, PREG_SPLIT_OFFSET_CAPTURE) ?: [];
        $out = [];
        foreach ($parts as [$text, $offset]) {
            if (trim($text) !== '') {
                $out[] = [mb_strlen(substr($sentence, 0, $offset)), $text];
            }
        }

        return $out;
    }

    private function excerpt(string $clause): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', str_replace(['—', '–'], '-', $clause)));

        return mb_strlen($text) > self::MAX_EXCERPT ? rtrim(mb_substr($text, 0, self::MAX_EXCERPT - 1)).'…' : $text;
    }

    /**
     * Un fait par série (ou ligne), début et fin : le plus fiable gagne.
     *
     * @param  list<array<string, mixed>>  $facts
     * @return list<array<string, mixed>>
     */
    private function deduplicated(array $facts): array
    {
        $rank = ['high' => 3, 'medium' => 2, 'low' => 1];
        $byKey = [];
        foreach ($facts as $f) {
            $key = implode('|', [$f['id'], $f['from']['date'] ?? '', $f['to']['date'] ?? '']);
            if (! isset($byKey[$key]) || $rank[$f['confidence']] > $rank[$byKey[$key]['confidence']]) {
                $byKey[$key] = $f;
            }
        }

        return array_values($byKey);
    }
}

<?php

namespace App\Services\Signalling;

use App\Enums\SignallingRole;
use App\Services\WikipediaLineParser;

/**
 * Mises en service datées de systèmes de signalisation, lues dans le wikitexte d'un article de ligne.
 *
 * Seuls des FAITS sont gardés : un système (BAL, BAPR, BM, CT, TVM, ETCS, KVB), une date et sa précision
 * (jour, mois, année), la portion quand l'article la donne (PK ou lieux), la phrase source (courte) et une
 * fiabilité. Rien n'est deviné : sans date, sans verbe de mise en service près du système, au futur, au passé
 * d'une dépose, ou si le système est celui qui est remplacé, aucun fait n'est produit.
 *
 * Le KVB est un contrôle de vitesse, jamais un cantonnement : son rôle (`SignallingRole::SpeedControl`) le
 * sépare des systèmes de cantonnement (`Block`) et de la signalisation en cabine TVM / ETCS (`Cab`).
 *
 * Fiabilité :
 *   - `high`   : jour ou mois précis, une seule date dans la proposition, système sujet de la mise en service ;
 *   - `medium` : année seule, mêmes conditions ;
 *   - `low`    : date approximative (« vers 1937 », « avant 1950 »), plusieurs dates dans la proposition,
 *                période (« de 1968 à 1984 »), date hors de la vie du système. Gardé, jamais montré.
 *
 * Sections écartées : notes, bibliographie, galerie, projets et avenir. Les tableaux ne sont pas lus (première
 * version). Une proposition qui cite une autre ligne (lien ⟦…⟧ du texte brut de WikipediaLineParser) est écartée :
 * le fait peut concerner l'autre ligne.
 */
final class SignallingHistoryExtractor
{
    /** Système → (expression, sensible à la casse pour les sigles). Le code final est calculé par code(). */
    private const MENTIONS = [
        'BAL' => '/(?i:blo(?:ck|c)\s+automatique\s+lumineux)|\bBAL(?:-VB)?\b/u',
        'BAPR' => '/(?i:blo(?:ck|c)\s+automatique\s+à\s+permissivité\s+restreinte)|\bBAPR(?:-[A-Z]{2})?\b/u',
        'BM' => '/(?i:blo(?:ck|c)\s+manuel(?:\s+unifié)?)|\bBM(?:VU|U)?\b/u',
        'CT' => '/(?i:cantonnement\s+téléphonique)(?!\s+assisté)/u',
        'TVM' => '/(?i:transmission\s+voie[- ]machine)|\bTVM(?:[\s-]*(?:300|430))?\b/u',
        'ETCS' => '/\b(?:ETCS|ERTMS)(?:\s*[\/-]\s*(?:ETCS|ERTMS))?(?:\s+(?:de\s+)?(?:niveau|level|N)\s*([12])|\s*L([12])\b)?/u',
        'KVB' => '/\bKVB(?:\s?P)?\b|(?i:contrôle\s+de\s+vitesse\s+par\s+balises)/u',
    ];

    /** Année avant laquelle le système n'existait pas : une date plus ancienne est une erreur de lecture probable. */
    private const FIRST_YEAR = ['BAL' => 1925, 'BAPR' => 1935, 'BM' => 1850, 'CT' => 1850, 'TVM' => 1976, 'ETCS' => 1998, 'KVB' => 1980];

    /** Verbe ou nom de mise en service. */
    private const VERB = '/\bmis(?:e|es)?\s+en\s+(?:service|place|œuvre)|\bentr\w+\s+en\s+service|\binstall\w+|\bpos(?:é|ée|és|ées|ent|er)\b|\bpose\b|\béquip\w+|\bdot(?:é|ée|és|ées|ation)\b|\bétend\w+|\bétendu\w*|\bextension\b|\bdéploy\w+|\bgénéralis\w+|\binaugur\w+|\brempla[cç]\w+|\bactiv(?:é|ée|ation)\b|\bsubstitu\w+|\bintrodui\w+|\binstauré\w*/iu';

    /** Futur, projet, retrait : pas une mise en service passée. */
    private const NEGATIVE = '/\b(?:sera|seront|serait|seraient|prévu\w*|projet\w*|doit|doivent|devai(?:t|ent)|devrait|devraient|envisag\w+|programm\w+|futur\w*|à terme|souhait\w+|étud(?:e|es|ié\w*)|supprim\w+|abandonn\w+|dépos\w+|retir\w+|désaffect\w+|déman\w+|désactiv\w+|rempla[cç]era\w*|fermeture|fermé\w*)\b/iu';

    /** Titres de section sans fait daté sûr. */
    private const SKIP_HEADINGS = '/^(?:notes?|références|sources?|bibliographie|voir aussi|liens? externes?|articles? connexes|annexes?|galerie|culture|littérature|cinéma|projets?|avenir|perspectives?|évolutions? futures?|réouverture envisagée)\b/iu';

    /** Mots qui rendent la date approximative quand ils la précèdent. */
    private const APPROXIMATE = '/(?:vers|environ|autour\s+de|aux\s+alentours\s+de|courant|fin|début|milieu|avant|jusqu[’\']en|années|dans\s+les\s+années|après|dès\s+que)\s*(?:de\s+|d[’\']|de\s+l[’\']année\s+|le\s+)?$/iu';

    /** Début de proposition qui reprend le verbe de la précédente (« …, puis du KVB en 1989 »). */
    private const CONTINUATION = '/^(?:(?:et|puis|ainsi|ensuite|enfin)\s+)?(?:(?:de\s+la|du|des|le|la|les|un|une)\s|de\s+l[’\']|d[’\']|l[’\'])/iu';

    /** Renvoi à la date de la phrase précédente (« La même année », « À cette occasion »). */
    private const SAME_TIME = '/^(?:(?:et|puis|mais)\s+)?(?:(?:la|cette)\s+même\s+(?:année|date)|le\s+même\s+jour|à\s+cette\s+(?:occasion|date))\b/iu';

    /** Exception à la portée (« hormis les tronçons… déjà équipés ») : la portion citée n'est pas celle du fait. */
    private const EXCEPTION = '/\b(?:hormis|sauf|excepté|à\s+l[’\']exception|déjà\s+équip\w+)/iu';

    private const MAX_EXCERPT = 200;

    public function __construct(private readonly WikipediaLineParser $parser) {}

    /**
     * @return list<array{system: string, role: string, date: string, precision: string, scope: string,
     *     section_from: ?string, section_to: ?string, pk_start_m: ?int, pk_end_m: ?int,
     *     confidence: string, origin: string, excerpt: string}>
     */
    public function extract(string $wikitext, string $title, ?\DateTimeInterface $today = null): array
    {
        $today ??= new \DateTimeImmutable;
        $infobox = $this->parser->parseInfobox($wikitext);
        $ends = $this->parser->lineEnds($title, $infobox);
        $lines = explode("\n", $this->parser->toPlainText($wikitext, $title));

        $facts = [];
        $skip = false;
        $parentDates = [];
        $count = count($lines);
        for ($i = 0; $i < $count; $i++) {
            $line = trim($lines[$i]);
            if ($line === '') {
                continue;
            }
            if (preg_match('/^={2,6}\s*(.+?)\s*=+$/u', $line, $m)) {
                $skip = (bool) preg_match(self::SKIP_HEADINGS, trim($m[1]));
                $parentDates = [];

                continue;
            }
            if (str_starts_with($line, '{|')) {
                for ($depth = 0; $i < $count; $i++) {
                    $tl = trim($lines[$i]);
                    $depth += str_starts_with($tl, '{|') ? 1 : 0;
                    $depth -= str_starts_with($tl, '|}') ? 1 : 0;
                    if ($depth <= 0) {
                        break;
                    }
                }

                continue;
            }
            if ($skip || preg_match('/^[|!]/u', $line)) {
                continue;
            }

            $origin = 'text';
            $depth = 0;
            if (preg_match('/^([*#]+)[:*#]*\s*(.*)$/u', $line, $m)) {
                $origin = 'list';
                $depth = strlen($m[1]);
                $line = $m[2];
            } else {
                $parentDates = [];
            }
            $inherited = $origin === 'list' ? ($parentDates[$depth - 1] ?? null) : null;
            $parentDates = array_filter($parentDates, fn ($d) => $d < $depth, ARRAY_FILTER_USE_KEY);

            if ($origin === 'list' && mb_strlen($line) <= 120 && $this->mentions($line) === []
                && ($dates = array_values(array_filter($this->parser->findDates($line), fn ($d) => $d['precision'] !== 'decade'))) !== []) {
                $parentDates[$depth] = $dates[0];

                continue;
            }

            $previousDate = null;
            foreach ($this->sentences($line) as $sentence) {
                foreach ($this->factsOfSentence($sentence, $ends, $inherited, $previousDate, $today) as $fact) {
                    $facts[] = $fact + ['origin' => $origin];
                }
                $previousDate = $this->singleDate($sentence);
            }
        }

        return $this->deduplicated($facts);
    }

    /**
     * @param  list<string>  $ends
     * @param  array{date: string, precision: string, year: int, pos: int}|null  $inherited  date de la puce parente
     * @param  array{date: string, precision: string, year: int, pos: int}|null  $previousDate  date unique de la phrase précédente
     * @return list<array<string, mixed>>
     */
    private function factsOfSentence(string $sentence, array $ends, ?array $inherited, ?array $previousDate, \DateTimeInterface $today): array
    {
        if ($this->mentions($sentence) === []) {
            return [];
        }
        $out = [];
        $previousHadVerb = false;
        foreach ($this->clauses($sentence) as $clause) {
            $hasVerb = (bool) preg_match(self::VERB, $clause);
            $verbOk = $hasVerb || ($previousHadVerb && preg_match(self::CONTINUATION, $clause));
            $previousHadVerb = $hasVerb || $previousHadVerb && preg_match(self::CONTINUATION, $clause);
            $mentions = $this->mentions($clause);
            if ($mentions === [] || ! $verbOk || preg_match(self::NEGATIVE, $clause) || str_contains($clause, '⟦')) {
                continue;
            }
            $dates = $this->datesOf($clause);
            $viaReference = false;
            if ($dates === [] && $inherited !== null) {
                $dates = [$inherited + ['pos' => 0]];
            } elseif ($dates === [] && $previousDate !== null && preg_match(self::SAME_TIME, $clause, $same)) {
                // « La même année, le BAL est installé… » : année seulement ; « À cette occasion » : la date précise.
                $sameDay = ! str_contains(mb_strtolower($same[0]), 'année');
                $dates = [['pos' => 0, 'date' => $sameDay ? $previousDate['date'] : sprintf('%04d-01-01', $previousDate['year']),
                    'year' => $previousDate['year'], 'precision' => $sameDay ? $previousDate['precision'] : 'year']];
                $viaReference = true;
            }
            if ($dates === []) {
                continue;
            }
            $section = $this->parser->findSection($clause, $ends);
            if ($section !== null && $section['other_line']) {
                continue;
            }

            foreach ($mentions as $mention) {
                if ($this->isReplaced($clause, $mention, $mentions) || $this->isFormer($clause, $mention)) {
                    continue;
                }
                $date = $this->nearestDate($dates, $mention['pos']);
                if (strtotime($date['date']) > $today->getTimestamp()) {
                    continue;
                }
                $out[] = [
                    'system' => $mention['system'],
                    'role' => SignallingRole::forSystem($mention['system'])->value,
                    'date' => $date['date'],
                    'precision' => $date['precision'],
                    'scope' => $this->scope($section),
                    'section_from' => $section['from'] ?? null,
                    'section_to' => $section['to'] ?? null,
                    'pk_start_m' => $section['pk_start'] ?? null,
                    'pk_end_m' => $section['pk_end'] ?? null,
                    'confidence' => $this->confidence($clause, $mention, $dates, $date, $viaReference),
                    'excerpt' => $this->excerpt($clause),
                ];
            }
        }

        return $out;
    }

    /**
     * Systèmes cités dans un texte, avec leur position (en caractères) et leur code.
     *
     * @return list<array{system: string, pos: int, text: string}>
     */
    private function mentions(string $text): array
    {
        $found = [];
        foreach (self::MENTIONS as $family => $rx) {
            if (! preg_match_all($rx, $text, $all, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
                continue;
            }
            foreach ($all as $m) {
                $found[] = [
                    'system' => $this->code($family, $m),
                    'pos' => mb_strlen(substr($text, 0, $m[0][1])),
                    'text' => $m[0][0],
                ];
            }
        }
        usort($found, fn ($a, $b) => $a['pos'] <=> $b['pos']);

        // « block automatique lumineux (BAL) » : le sigle répète le nom, c'est une seule mention.
        $mentions = [];
        foreach ($found as $m) {
            $last = $mentions === [] ? null : $mentions[array_key_last($mentions)];
            $gap = $last === null ? '' : mb_substr($text, $last['pos'] + mb_strlen($last['text']), $m['pos'] - $last['pos'] - mb_strlen($last['text']));
            if ($last !== null && $last['system'] === $m['system'] && preg_match('/^\s*\(\s*$/u', $gap)) {
                continue;
            }
            $mentions[] = $m;
        }

        return $mentions;
    }

    /** Code du système d'après la famille et ce que le texte précise (TVM 300, ETCS niveau 2). */
    private function code(string $family, array $match): string
    {
        $text = $match[0][0];
        if ($family === 'TVM' && preg_match('/(300|430)/', $text, $n)) {
            return 'TVM-'.$n[1];
        }
        if ($family === 'ETCS') {
            $level = $match[1][0] ?? ($match[2][0] ?? '');

            return $level !== '' ? 'ETCS_L'.$level : 'ETCS';
        }

        return $family;
    }

    /**
     * Phrases d'une ligne de texte, sans couper après une abréviation ou un chiffre (« 1er. », « St. »).
     *
     * @return list<string>
     */
    private function sentences(string $line): array
    {
        $parts = preg_split('/(?<=[.!?])\s+(?=[\p{Lu}«"(⟦])/u', $line) ?: [$line];
        $out = [];
        foreach ($parts as $part) {
            $last = array_key_last($out);
            if ($last !== null && preg_match('/\b(?:St|Ste|Mme|MM?|Dr|Pr|Cie|env|av|J-C|n°|no|vol|fig|pp?)\.$/u', $out[$last])) {
                $out[$last] .= ' '.$part;

                continue;
            }
            $out[] = $part;
        }

        return array_values(array_filter(array_map('trim', $out), fn ($s) => $s !== ''));
    }

    /**
     * Propositions d'une phrase : coupée aux « ; », « , puis », « , tandis que », « , mais », à un « et » suivi
     * d'un participe (« …électrifiée en 1960 et équipée du BAL »), et aux virgules qui séparent deux dates.
     *
     * @return list<string>
     */
    private function clauses(string $sentence): array
    {
        $parts = preg_split('/\s*;\s*|,?\s+puis\s+|,\s+(?=(?:avant|alors|tandis|mais)\b)|\s+(?:tandis|alors)\s+que\s+/iu', $sentence) ?: [$sentence];
        $parts = array_merge(...array_map($this->splitAtParticiple(...), $parts));
        $out = [];
        foreach ($parts as $part) {
            $pieces = explode(', ', $part);
            $current = array_shift($pieces);
            foreach ($pieces as $piece) {
                if (($this->hasDate($current) && $this->hasDate($piece)) || $this->startsWithOtherSystem($current, $piece)) {
                    $out[] = $current;
                    $current = $piece;

                    continue;
                }
                $current .= ', '.$piece;
            }
            $out[] = $current;
        }

        return array_values(array_filter(array_map('trim', $out), fn ($c) => $c !== ''));
    }

    /**
     * « …électrifiée en 1960 et équipée du BAL », « …depuis 1995, équipée du block manuel » : la date qui précède
     * appartient à une autre action. Coupé seulement si la partie de gauche a une date et aucun système
     * (« le BAL, mis en service en 1978 » garde son participe).
     *
     * @return list<string>
     */
    private function splitAtParticiple(string $part): array
    {
        $tokens = preg_split('/(,?\s+et\s+|,\s+)(?=(?:équip|install|dot|mis|mise|étend|pos|complét|électrifi|ouvert|fermé|relié|déploy|rempla[cç]|construit|prolong|déclass)\w*)/iu', $part, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$part];
        $out = [];
        $current = $tokens[0];
        for ($i = 1; $i < count($tokens); $i += 2) {
            // Une autre action (électrification, ouverture…) coupe toujours : sa date n'est pas celle du système.
            $otherAction = (bool) preg_match('/^(?:électrifi|ouvert|fermé|relié|construit|prolong|déclass)/iu', $tokens[$i + 1]);
            if ($otherAction || ($this->hasDate($current) && $this->mentions($current) === [])) {
                $out[] = $current;
                $current = $tokens[$i + 1];

                continue;
            }
            $current .= $tokens[$i].$tokens[$i + 1];
        }
        $out[] = $current;

        return $out;
    }

    /** « …du BAL en 1978, du BAPR depuis 1980 » : chaque système garde sa date, la date du premier n'est pas celle du second. */
    private function startsWithOtherSystem(string $left, string $right): bool
    {
        $first = $this->mentions($right)[0] ?? null;

        return $first !== null && $first['pos'] < 30 && $this->mentions($left) !== []
            && preg_match('/^(?:et\s+)?(?:(?:du|de\s+la|des|le|la|les|l[’\']|d[’\'])\s*)?(?:(?:ancien|nouveau)\s+)?(?:\S+\s+){0,3}$/iu', mb_substr($right, 0, $first['pos']));
    }

    private function hasDate(string $text): bool
    {
        return (bool) preg_match('/\b(?:1[89]\d\d|20[0-3]\d)\b/u', $text);
    }

    /** Le système cité est celui qui disparaît (« remplace le BM », « le BM est remplacé par »). */
    private function isReplaced(string $clause, array $mention, array $mentions): bool
    {
        $before = mb_substr($clause, max(0, $mention['pos'] - 70), min(70, $mention['pos']));
        $end = $mention['pos'] + mb_strlen($mention['text']);
        // Jusqu'au système suivant : « Le BAL est installé… le block manuel est remplacé » ne retire pas le BAL.
        $next = array_values(array_filter(array_column($mentions, 'pos'), fn (int $p) => $p > $mention['pos']))[0] ?? PHP_INT_MAX;
        $after = mb_substr($clause, $end, min(200, $next - $end));

        return (bool) preg_match('/rempla[cç](?:e|ent|era|erait|é|ée|és|ées|ant|er|ement)\s+(?:(?:du|de\s+la|de\s+l[’\']|des|le|la|l[’\']|les|un|une|d[’\']\s*une?|d[’\'])\s*)?(?:ancien(?:ne)?\s+)?(?:système\s+(?:de\s+)?)?$/iu', $before)
            || preg_match('/^\W*(?:\([^)]*\)\s*)?[^.;]{0,160}?\b(?:n[’\']\s*)?(?:est|sont|fut|furent|a\s+été|ont\s+été)\s+(?:(?:progressivement|ensuite|alors|peu\s+à\s+peu|plus\s+tard|qu[’\']en\s+\S+)\s+)?rempla[cç]/iu', $after)
            || preg_match('/(?:au\s+profit\s+d[eu]|en\s+lieu\s+et\s+place\s+d[eu])\s*(?:la\s+|l[’\'])?$/iu', $before);
    }

    /** « l'ancien BAL », « elle était jusque-là assurée par du block manuel » : un système déjà disparu. */
    private function isFormer(string $clause, array $mention): bool
    {
        $before = mb_substr($clause, max(0, $mention['pos'] - 60), min(60, $mention['pos']));

        return (bool) preg_match('/\bancien(?:ne)?s?\s+(?:système\s+)?$/iu', mb_substr($before, -18))
            || preg_match('/\b(?:était|étaient|jusque-là|jusqu[’\']alors|auparavant|précédemment|anciennement|jadis)\b[^.;()]{0,45}$/iu', $before)
            || preg_match('/\b(?!(?:avait|aurait|serait|ferait|pourrait|devrait|voudrait|faudrait)\b)\p{L}{4,}ait\b[^.;()]{0,45}$/u', $before);
    }

    /**
     * Date de la proposition la plus proche du système (celle qui le suit est légèrement préférée).
     *
     * @param  non-empty-list<array{pos: int, date: string, precision: string, year: int}>  $dates
     * @return array{pos: int, date: string, precision: string, year: int}
     */
    private function nearestDate(array $dates, int $pos): array
    {
        usort($dates, function ($a, $b) use ($pos) {
            $da = $a['pos'] >= $pos ? $a['pos'] - $pos : ($pos - $a['pos']) * 1.3;
            $db = $b['pos'] >= $pos ? $b['pos'] - $pos : ($pos - $b['pos']) * 1.3;

            return $da <=> $db;
        });

        return $dates[0];
    }

    /**
     * @param  array{from: ?string, to: ?string, pk_start: ?int, pk_end: ?int, whole_line: bool}|null  $section
     */
    private function scope(?array $section): string
    {
        return match (true) {
            $section === null => 'unspecified',
            $section['whole_line'] => 'whole_line',
            $section['from'] !== null || $section['to'] !== null || $section['pk_start'] !== null => 'section',
            default => 'unspecified',
        };
    }

    /**
     * @param  list<array{pos: int, date: string, precision: string, year: int}>  $dates
     * @param  array{pos: int, date: string, precision: string, year: int}  $date
     */
    private function confidence(string $clause, array $mention, array $dates, array $date, bool $viaReference = false): string
    {
        $distinct = array_unique(array_column($dates, 'date'));
        $before = mb_substr($clause, max(0, $date['pos'] - 30), min(30, $date['pos']));
        $year = $date['year'];
        $period = (bool) preg_match('/(?:19|20)\d\d\s*(?:-|–|à|et|au)\s*(?:19|20)\d\d/u', $clause);
        $code = preg_replace('/[-_].*$/', '', $mention['system']);
        $tooEarly = $year < (self::FIRST_YEAR[$code] ?? 0);
        if (count($distinct) > 1 || $period || $tooEarly || preg_match(self::APPROXIMATE, $before) || preg_match(self::EXCEPTION, $clause)) {
            return 'low';
        }

        return $date['precision'] === 'year' || $viaReference ? 'medium' : 'high';
    }

    /**
     * Dates utilisables d'un texte : ni décennie (« années 1930 »), ni nombre qui n'est pas une date
     * (« cantons de 2000 à 3000 mètres »).
     *
     * @return list<array{pos: int, date: string, precision: string, year: int}>
     */
    private function datesOf(string $text): array
    {
        return array_values(array_filter($this->parser->findDates($text), function (array $d) use ($text): bool {
            if ($d['precision'] === 'decade') {
                return false;
            }
            $after = mb_substr($text, $d['pos'], 40);

            return $d['precision'] !== 'year' || ! preg_match('/^\d{4}\s*(?:à|-|–|et)\s*\d{3,4}\s*(?:m\b|mètres|km|v\b|kv|volts)/iu', $after);
        }));
    }

    /** Date de la phrase si elle n'en cite qu'une, sinon null (« La même année » ne saurait pas laquelle prendre). */
    private function singleDate(string $sentence): ?array
    {
        $dates = $this->datesOf($sentence);

        return count(array_unique(array_column($dates, 'date'))) === 1 ? $dates[0] : null;
    }

    private function excerpt(string $clause): string
    {
        $text = trim(str_replace(['⟦', '⟧', '—', '–'], ['', '', '-', '-'], $clause));
        $text = (string) preg_replace('/\s+/u', ' ', $text);

        return mb_strlen($text) > self::MAX_EXCERPT ? rtrim(mb_substr($text, 0, self::MAX_EXCERPT - 1)).'…' : $text;
    }

    /**
     * Un fait par système, date et portion : le plus fiable gagne.
     *
     * @param  list<array<string, mixed>>  $facts
     * @return list<array<string, mixed>>
     */
    private function deduplicated(array $facts): array
    {
        $rank = ['high' => 3, 'medium' => 2, 'low' => 1];
        $byKey = [];
        foreach ($facts as $f) {
            $key = implode('|', [$f['system'], $f['date'], $f['section_from'], $f['section_to'], $f['pk_start_m'], $f['pk_end_m']]);
            if (! isset($byKey[$key]) || $rank[$f['confidence']] > $rank[$byKey[$key]['confidence']]) {
                $byKey[$key] = $f;
            }
        }

        return array_values($byKey);
    }
}

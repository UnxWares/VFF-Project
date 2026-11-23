<?php

namespace App\Services\Speed;

use App\Services\WikipediaLineParser;

/**
 * Changements de vitesse maximale datés, lus dans le wikitexte d'un article de ligne.
 *
 * Seuls des FAITS sont gardés : une vitesse en km/h, la vitesse d'avant quand l'article la donne
 * (« de 90 à 140 km/h »), une date et sa précision, la portion quand l'article la donne (PK ou lieux),
 * la phrase source (courte) et une fiabilité. Rien n'est deviné.
 *
 * Nature (`kind`) :
 *   - `raise`   : relèvement (« relevée à », « portée à », « relèvement de la vitesse à ») ;
 *   - `lower`   : abaissement durable (« abaissée de 70 à 60 km/h ») ;
 *   - `opening` : vitesse à l'ouverture ou à la mise en service (« ouverte à 260 km/h ») ;
 *   - `state`   : vitesse autorisée à une date (« En 2013, la vitesse limite est de 100 km/h »).
 *
 * Écartés : futur et projets (« sera relevée », « prévoient »), records, essais, moyennes, temps de parcours,
 * vitesse d'un train ou d'une catégorie de trains et non de la voie (accident, « aborde à 120 km/h », « pour les AGC »), limitations temporaires (glissement,
 * canicule, travaux), propositions qui citent une autre ligne (lien ⟦…⟧ de WikipediaLineParser), sections
 * notes, bibliographie, projets et avenir.
 *
 * Fiabilité : `high` (jour ou mois, une seule date dans la proposition), `medium` (année seule), `low`
 * (plusieurs dates, date approximative, plusieurs vitesses sans « de … à … », date reprise d'ailleurs). Une
 * fiabilité basse est gardée en base mais jamais montrée.
 */
final class SpeedHistoryExtractor
{
    /** Relèvement. */
    private const RAISE = '/\b(?:relèvements?|relev(?:er|é|ée|és|ées|ant)|port(?:é|ée|és|ées)\s+(?:à|de\s+\d)|porter\s+(?:la\s+vitesse|à)|augment\w*\s+(?:la\s+)?vitesse|passe(?:nt)?\s+(?:de\s+\d+\s+)?à\s+\d|mise?s?\s+à\s+\d{2,3}\s*km)/iu';

    /** Abaissement. */
    private const LOWER = '/\b(?:abaiss\w+|réduit\w*|réduction|diminu\w+)/iu';

    /** Ouverture, mise en service. */
    private const OPENING = '/\b(?:ouvert\w*|mis(?:e|es)?\s+en\s+service|inaugur\w+)/iu';

    /** Vitesse autorisée à une date. */
    private const STATE = '/\bvitesses?\s+(?:limites?|maximales?|plafond|nominales?)(?:\s+autorisées?)?(?:\s+\w+){0,6}\s+(?:est|était|sont|étaient)\s+(?:de|fixée?s?\s+à)\b|\blimit(?:é|ée|és|ées)\s+à\s+\d|\bparcourable\s+à\b|\bapte\s+à\b|\bautoris\w+\s+à\s+\d/iu';

    /** Futur, projet : pas un fait passé. */
    private const NEGATIVE = '/\b(?:sera|seront|serait|seraient|prévu\w*|prévoi\w*|projet\w*|programm\w+|envisag\w+|doit|doivent|devai(?:t|ent)|devrait|devraient|permettra\w*|pourra\w*|pourrai\w*|à\s+terme|vis(?:e|ent|ant)|objectif|promi\w*|souhait\w+|étud(?:e|es|ié\w*)|futur\w*)\b/iu';

    /** Vitesse qui n'est pas celle de la voie, ou limitation passagère. */
    private const NOISE = '/\b(?:moyenne|record\w*|essais?|déraill\w+|accident\w*|abord\w+|collision|vitesse\s+commerciale|temps\s+de\s+parcours|horaire|temporair\w+|provisoire\w*|glissement|canicule|intempéries|ralentissement|automotrices?|locomotives?|autorails?|rames?|matériel|machines?|liaisons?|trans-europ\w*|kilomètres\s+par\s+heure|pour\s+les\s+(?:AGC|TGV|X\s?\d+|Z\s?\d+|BB|trains\s+V))\b/iu';

    /** Exception à la portée (« hormis les tronçons… ») : la portion citée n'est pas celle du fait. */
    private const EXCEPTION = '/\b(?:hormis|sauf|excepté|à\s+l[’\']exception)\b/iu';

    /** Titres de section sans fait daté sûr. */
    private const SKIP_HEADINGS = '/^(?:notes?|références|sources?|bibliographie|voir aussi|liens? externes?|articles? connexes|annexes?|galerie|culture|littérature|cinéma|projets?|avenir|perspectives?|évolutions? futures?|accidents?|incidents?|records?|essais?)\b/iu';

    /** Mots qui rendent la date approximative quand ils la précèdent. */
    private const APPROXIMATE = '/(?:vers|environ|autour\s+de|aux\s+alentours\s+de|courant|fin|début|milieu|avant|jusqu[’\']en|années|après)\s*(?:de\s+|d[’\']|de\s+l[’\']année\s+|le\s+)?$/iu';

    /** Toute la ligne. */
    private const WHOLE_LINE = '/\b(?:(?:de|sur)\s+(?:toute|l[’\']ensemble\s+de)\s+la\s+ligne|de\s+la\s+ligne\b|la\s+ligne\s+(?:est|était)\s+(?:parcourable|apte))/iu';

    private const SPEED = '/(?<![\d,.])(\d{2,3})\s*km\s*\/\s*h/iu';

    private const MAX_EXCERPT = 200;

    public function __construct(private readonly WikipediaLineParser $parser) {}

    /**
     * @return list<array{kind: string, speed_kmh: int, previous_speed_kmh: ?int, date: string, precision: string,
     *     scope: string, section_from: ?string, section_to: ?string, pk_start_m: ?int, pk_end_m: ?int,
     *     confidence: string, excerpt: string}>
     */
    public function extract(string $wikitext, string $title, ?\DateTimeInterface $today = null): array
    {
        $today ??= new \DateTimeImmutable;
        $ends = $this->parser->lineEnds($title, $this->parser->parseInfobox($wikitext));
        $facts = [];
        $skip = false;
        $parentDate = null;
        foreach (explode("\n", $this->parser->toPlainText($wikitext, $title)) as $raw) {
            $line = trim($raw);
            if ($line === '') {
                continue;
            }
            if (preg_match('/^={2,6}\s*(.+?)\s*=+$/u', $line, $m)) {
                $skip = (bool) preg_match(self::SKIP_HEADINGS, trim($m[1]));
                $parentDate = null;

                continue;
            }
            if ($skip || preg_match('/^(?:[|!]|\{\|)/u', $line)) {
                continue;
            }
            $isList = (bool) preg_match('/^[*#]+[:*#]*\s*(.*)$/u', $line, $m);
            if ($isList) {
                $line = $m[1];
            }
            // Puce « 1982 : » sans vitesse : sa date vaut pour les puces suivantes du même bloc.
            if ($isList && ! preg_match(self::SPEED, $line) && mb_strlen($line) <= 40 && ($d = $this->singleDate($line)) !== null) {
                $parentDate = $d;

                continue;
            }
            if (! $isList) {
                $parentDate = null;
            }
            foreach ($this->sentences($line) as $sentence) {
                array_push($facts, ...$this->fromSentence($sentence, $ends, $today, $isList ? $parentDate : null));
            }
        }

        return $this->unique($facts);
    }

    /**
     * @param  list<string>  $ends
     * @param  array{date: string, precision: string}|null  $inherited
     * @return list<array<string, mixed>>
     */
    private function fromSentence(string $sentence, array $ends, \DateTimeInterface $today, ?array $inherited): array
    {
        if (! preg_match(self::SPEED, $sentence) || preg_match(self::NEGATIVE, $sentence) || preg_match(self::NOISE, $sentence)) {
            return [];
        }
        $sentenceDates = $this->dates($sentence);
        $facts = [];
        $kind = null;
        $clauses = $this->clauses($sentence);
        // Plusieurs propositions avec une vitesse : une date de la phrase n'appartient qu'à l'une d'elles.
        $speedClauses = count(array_filter($clauses, fn (string $c): bool => (bool) preg_match(self::SPEED, $c)));
        $distinctSpeeds = preg_match_all(self::SPEED, $sentence, $all) ? count(array_unique($all[1])) : 0;
        foreach ($clauses as $clause) {
            if (str_contains($clause, '⟦')) {
                $kind = null;

                continue;
            }
            $clauseKind = $this->kind($clause);
            // « portée à 140 km/h entre X et Y, puis en 1976 à 160 km/h » : la nature vaut pour la suite.
            $kind = $clauseKind ?? $kind;
            if ($kind === null || ! preg_match_all(self::SPEED, $clause, $sm)) {
                continue;
            }
            $speeds = array_map('intval', $sm[1]);
            $previous = null;
            if (preg_match('/\bde\s+(\d{2,3})(?:\s*km\s*\/\s*h)?\s+à\s+(\d{2,3})\s*km\s*\/\s*h/iu', $clause, $pm)) {
                $previous = (int) $pm[1];
                $speed = (int) $pm[2];
                $speeds = array_values(array_diff($speeds, [$previous]));
            } elseif (preg_match('/\bà\s+(\d{2,3})\s*km\s*\/\s*h\s+au\s+lieu\s+de\s+(\d{2,3})/iu', $clause, $pm)) {
                $speed = (int) $pm[1];
                $previous = (int) $pm[2];
                $speeds = [$speed];
            } else {
                $speed = $speeds[0];
            }
            if ($speed < 10 || $speed > 360 || $speed % 5 !== 0 || ($previous !== null && ($previous === $speed || $previous % 5 !== 0))) {
                continue;
            }
            $clauseDates = $this->dates($clause);
            $doubtful = count(array_unique($speeds)) > 1 || $distinctSpeeds > 3
                || (bool) preg_match('/\bou\s+\d{2,3}\s*km/iu', $clause) || (bool) preg_match(self::EXCEPTION, $clause);
            if (count($clauseDates) === 1) {
                $date = $clauseDates[0];
                $doubtful = $doubtful || $this->approximate($clause, $date['pos']);
            } elseif ($clauseDates === [] && count($sentenceDates) === 1) {
                $date = $sentenceDates[0];
                $doubtful = $doubtful || $speedClauses > 1 || $this->approximate($sentence, $date['pos']);
            } elseif ($clauseDates === [] && $sentenceDates === [] && $inherited !== null) {
                $date = $inherited;
            } elseif ($clauseDates !== []) {
                $date = $clauseDates[0];
                $doubtful = true;
            } else {
                continue;
            }
            if ($date['date'] > $today->format('Y-m-d')) {
                continue;
            }
            // Portion de la phrase seulement si elle n'a qu'une proposition : sinon elle peut être celle d'un autre fait.
            $section = $this->parser->findSection($clause, $ends) ?? (count($clauses) === 1 ? $this->parser->findSection($sentence, $ends) : null);
            $whole = (bool) preg_match(self::WHOLE_LINE, $clause) || ($section !== null && $section['whole_line']);
            $hasPlace = $section !== null && ($section['from'] !== null || $section['pk_start'] !== null) && ! $section['other_line'];
            $facts[] = [
                'kind' => $kind,
                'speed_kmh' => $speed,
                'previous_speed_kmh' => $previous,
                'date' => $date['date'],
                'precision' => $date['precision'],
                'scope' => $hasPlace ? 'section' : ($whole ? 'whole_line' : 'unspecified'),
                'section_from' => $hasPlace ? $section['from'] : null,
                'section_to' => $hasPlace ? $section['to'] : null,
                'pk_start_m' => $hasPlace ? $section['pk_start'] : null,
                'pk_end_m' => $hasPlace ? $section['pk_end'] : null,
                'confidence' => $doubtful ? 'low' : (in_array($date['precision'], ['day', 'month'], true) ? 'high' : 'medium'),
                'excerpt' => self::excerpt($sentence),
            ];
        }

        return $facts;
    }

    /** Nature du changement d'une proposition, ou null si elle n'en dit aucun. */
    private function kind(string $clause): ?string
    {
        return match (true) {
            (bool) preg_match(self::LOWER, $clause) => 'lower',
            (bool) preg_match(self::RAISE, $clause) => 'raise',
            (bool) preg_match(self::STATE, $clause) => 'state',
            (bool) preg_match(self::OPENING, $clause) && (bool) preg_match('/\bà\s+\d{2,3}\s*km/iu', $clause) => 'opening',
            default => null,
        };
    }

    /** @return list<array{pos: int, date: string, year: int, precision: string}> */
    private function dates(string $s): array
    {
        return array_values(array_filter($this->parser->findDates($s), fn (array $d): bool => $d['precision'] !== 'decade'));
    }

    /** @return array{date: string, precision: string}|null */
    private function singleDate(string $s): ?array
    {
        $dates = $this->dates($s);

        return count($dates) === 1 && ! $this->approximate($s, $dates[0]['pos']) ? ['date' => $dates[0]['date'], 'precision' => $dates[0]['precision']] : null;
    }

    private function approximate(string $s, int $pos): bool
    {
        return (bool) preg_match(self::APPROXIMATE, mb_substr($s, max(0, $pos - 40), min(40, $pos)));
    }

    /** @return list<string> */
    private function sentences(string $paragraph): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/(?<=[.!?])\s+(?=\p{Lu})/u', $paragraph) ?: [])));
    }

    /** Propositions : points-virgules, « puis », « ; ». @return list<string> */
    private function clauses(string $sentence): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\s*;\s*|,?\s+puis\s+/u', $sentence) ?: [])));
    }

    private static function excerpt(string $sentence): string
    {
        $s = trim((string) preg_replace('/[⟦⟧]/u', '', $sentence));

        return mb_strlen($s) <= self::MAX_EXCERPT ? $s : rtrim(mb_substr($s, 0, self::MAX_EXCERPT - 1)).'…';
    }

    /**
     * Un fait par date, vitesse, nature et portion : la plus haute fiabilité gagne.
     *
     * @param  list<array<string, mixed>>  $facts
     * @return list<array<string, mixed>>
     */
    private function unique(array $facts): array
    {
        $rank = ['high' => 3, 'medium' => 2, 'low' => 1];
        $kept = [];
        foreach ($facts as $f) {
            $key = implode('|', [$f['date'], $f['speed_kmh'], $f['kind'], $f['section_from'], $f['section_to']]);
            if (! isset($kept[$key]) || $rank[$f['confidence']] > $rank[$kept[$key]['confidence']]) {
                $kept[$key] = $f;
            }
        }

        // Même changement daté à l'année et au jour (« 2015 : relèvement à 140 km/h », « relevée le 15 décembre 2015 ») : le plus précis.
        $precise = [];
        foreach ($kept as $f) {
            if ($f['precision'] !== 'year') {
                $precise[substr($f['date'], 0, 4).'|'.$f['speed_kmh'].'|'.$f['kind']] = true;
            }
        }

        return array_values(array_filter($kept, fn (array $f): bool => $f['precision'] !== 'year'
            || ! isset($precise[substr($f['date'], 0, 4).'|'.$f['speed_kmh'].'|'.$f['kind']])));
    }
}

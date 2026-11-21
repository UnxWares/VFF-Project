<?php

namespace App\Services;

/**
 * Fermetures datées d'une gare dans des sources qui parlent de LA gare
 * (cf. vff:import-station-closures). Ne touche pas la base.
 *
 * Événement produit : ['type' => closure | closure_passenger | closure_freight
 * | demolition, 'date' => 'Y-m-d', 'precision' => day | month | year |
 * decade | after, 'quote' => courte citation, 'origin' => infobox | texte |
 * liste | tableau | qualificatif Wikidata…] ; les articles de ligne ajoutent
 * 'names' (noms des gares tels qu'écrits).
 *
 * Précisions particulières :
 *   decade = « fermée dans les années 1980 » (date = 1980-01-01) ;
 *   after  = « fermée dans la deuxième moitié du XXe siècle » (date =
 *            1950-01-01) : la gare était encore ouverte à cette date, la
 *            fermeture est postérieure et inconnue. Ce n'est pas une date de
 *            fermeture, seulement une preuve d'ouverture.
 *
 * Type : « fermée aux voyageurs » = closure_passenger, « aux marchandises /
 * au fret » = closure_freight, « fermée » sans précision, « à tout trafic »,
 * « désaffectée » = closure. Une HALTE (service voyageurs seul) fermée aux
 * voyageurs est fermée tout court (closure).
 */
final class StationClosureParser
{
    /** Gare sujet de la phrase (article de la gare). */
    private const SUBJECT = '/(?:^|[\s,;(])(elle|la gare|la halte|l[\'’]arrêt|le point d[\'’]arrêt|cette gare|cette halte|la station|l[\'’]ancienne gare|l[\'’]ancienne halte|c[\'’]est une (?:ancienne )?(?:petite )?(?:gare|halte)|la gare (?:de |d[\'’]|du |des )[^,;]{1,60}?|la halte (?:de |d[\'’]|du |des )[^,;]{1,60}?)(?=[\s,])/iu';

    /** Autre sujet entre la gare et le verbe : la phrase parle d'autre chose. */
    private const OTHER_SUBJECT = '/\b(?:la ligne|cette ligne|les lignes|le tronçon|la section|la portion|le service|la desserte|le trafic|les trains|la voie|les voies|le bâtiment|le dépôt|la marquise|le guichet|les guichets|la boutique|le buffet|la cour|l[\'’]usine|le passage|la halle|le quai|les quais|l[\'’]embranchement|le poste|la billetterie|l[\'’]agence|le point de vente|le café|le restaurant|le hall|le magasin|la gare (?:de marchandises|routière|de triage|maritime)|la sncf|l[\'’]administration)\b|⟦/iu';

    /** La phrase parle de la ligne (un « elle » qui suit la reprend). */
    private const LINE_TOPIC = '/\b(?:la ligne|cette ligne|le tronçon|ce tronçon|la section|cette section|la portion|la desserte|le trafic)\b/iu';

    /** Verbe de fermeture (proposition au passif ou forme active). */
    private const CLOSE_VERB = '/\b(?:(?:définitivement|officiellement|finalement|alors|ensuite|également|elle aussi|aussi|progressivement)\s+)*(fermée?s?|ferme|ferment|fermait|fermera|désaffectée?s?|supprimée?s?)\b|\bn[\'’](?:est|était)\s+plus\s+desservie|\bne\s+(?:sont|sera)\s+plus\s+desservies?|\bcesse(?:nt)?\s+d[\'’]être\s+desservies?|\bplus\s+desservie\s+depuis/iu';

    /** Liaison verbe → date qui change le sens (« fermée en même temps que… », « lors de… »). */
    private const GAP_BREAK = '/\b(?:lors|après|avant|quand|lorsque|que|qui|tandis|comme|pendant|durant|entre|mais|cependant|toutefois|néanmoins|encore|puis)\b|[;:(]/iu';

    /** Réserve de la source sur la date. */
    private const HEDGE = '/sans doute|probablement|semble-t-il|vraisemblablement|peut-être|aux alentours|environ|\bvers\b|\?|\{\{quand\}\}|date inconnue|date indéterminée/iu';

    /** Nom de gare (texte brut) : mots capitalisés, traits d'union, « X - Y ». */
    private const NAME = '(?:(?:Le|La|Les|L[\'’])\s*)?\p{Lu}[\p{L}\'’.\-]*(?:(?:\s+-\s+|\s+|-)(?:(?:sur|sous|lès|lez|le|la|les|de|du|des|en|aux?|d[\'’]|l[\'’])(?:\s+|-|(?<=[\'’])))*(?:\p{Lu}|\d)[\p{L}\'’.\-\d]*)*';

    /** Section de ligne : une liste « entre les gares de X et Y » n'est pas une liste de gares fermées. */
    private const BEFORE_MENTION_SECTION = '/(?:entre|section|tronçon|portion|ligne|jusqu[\'’]à|depuis|vers|via|desservant|reliant|exception|sauf|hormis|excepté|au-delà|en deçà)\s+(?:de\s+|d[\'’])?(?:les?\s+|la\s+|l[\'’])?$/iu';

    public function __construct(private WikipediaLineParser $wp) {}

    // ── Article de la gare ──────────────────────────────────────────────────

    /**
     * Infobox « Gare » (champ fermeture) et phrases dont la gare est le sujet.
     *
     * @return list<array<string, mixed>>
     */
    public function fromStationArticle(string $wikitext, string $title): array
    {
        $wikitext = self::expandCenturies($wikitext);
        $out = $this->infoboxClosures($wikitext, $title);
        $isHalt = self::isHalt($wikitext, $title);

        $text = $this->wp->toPlainText(self::withoutInfobox($wikitext), $title);
        $prev = '';
        foreach ($this->sentences($text, true) as $s) {
            foreach ($this->stationSentence($s, $prev) as $e) {
                $out[] = $e + ['origin' => 'texte'];
            }
            $prev = $s;
        }
        if ($isHalt) {
            $out = array_map(fn ($e) => $e['type'] === 'closure_passenger' ? ['type' => 'closure'] + $e : $e, $out);
        }

        return self::dedupe($out);
    }

    /**
     * Infobox « Gare » seule (champs fermeture), sans les phrases de l'article
     * (cf. vff:import-station-passenger-ends). Types tels qu'écrits : une
     * halte fermée aux voyageurs reste closure_passenger ici (isHalt() permet à
     * l'appelant d'appliquer la règle de fromStationArticle()).
     *
     * @return list<array<string, mixed>>
     */
    public function fromStationInfobox(string $wikitext, string $title): array
    {
        return self::dedupe($this->infoboxClosures(self::expandCenturies($wikitext), $title));
    }

    /** @return list<array<string, mixed>> */
    private function infoboxClosures(string $wikitext, string $title): array
    {
        if (! preg_match('/\{\{\s*Infobox\s+(Gare|Station)\b/iu', $wikitext)) {
            return [];
        }
        $out = [];
        $info = $this->wp->parseInfobox($wikitext);
        foreach (['fermeture', 'fermeture2', 'fermeture3', 'date de fermeture'] as $k) {
            if (! empty($info[$k])) {
                foreach ($this->closureValue($info[$k], $title) as $e) {
                    $out[] = $e + ['origin' => 'infobox'];
                }
            }
        }

        return $out;
    }

    /** Article d'une halte (service voyageurs seul) : titre « Halte de… » ou « c'est une halte » en tête. */
    public static function isHalt(string $wikitext, string $title): bool
    {
        return (bool) preg_match('/^Halte\b/iu', $title)
            || (bool) preg_match('/\bc[\'’]est une (?:ancienne )?(?:petite )?halte\b|\best une halte\b/iu', mb_substr($wikitext, 0, 6000));
    }

    /**
     * Valeur d'un champ « fermeture » d'infobox (déjà nettoyée) :
     * « 27 septembre 1969 (voyageurs) 30 novembre 1990 (marchandises) »,
     * « 1946 », « années 1980 », « deuxième moitié du XXe siècle »…
     *
     * @return list<array<string, mixed>>
     */
    public function closureValue(string $value, string $title = ''): array
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value));
        if ($value === '') {
            return [];
        }
        $raw = $value;
        // Disposition du champ : portée avant la première année (« Voyageurs le 6 mars 1972 … ») ?
        $qualifierFirst = preg_match('/^(.*?)(?:1[89]\d\d|20[0-3]\d)/u', $value, $head) === 1 && self::trafficType($head[1]) !== null;
        // « 1939 voyageurs 1952 marchandises » : findDates prend « 1939 voyageurs » pour un nombre de
        // voyageurs ; dans un champ fermeture dont la portée suit la date, c'est une année et sa portée.
        if (! $qualifierFirst) {
            $value = preg_replace('/\b(1[89]\d\d|20[0-3]\d)\s+(voyageurs?|marchandises?|frets?)\b/iu', '$1 ($2)', $value);
        }
        $dates = array_values(array_filter($this->wp->findDates($value), fn ($d) => $d['year'] >= 1827 && $d['year'] <= (int) date('Y')));
        $out = [];
        $hedged = (bool) preg_match(self::HEDGE, $value);
        foreach ($dates as $i => $d) {
            $start = $d['pos'];
            $end = $dates[$i + 1]['pos'] ?? mb_strlen($value);
            // Texte propre à cette date : de la fin de la précédente au début de la suivante.
            $after = preg_replace('/^.*?(?:1[89]\d\d|20[0-3]\d)/u', '', mb_substr($value, $start, $end - $start), 1);
            $prevEnd = $i > 0 ? $dates[$i - 1]['pos'] + mb_strlen(preg_replace('/^(.*?(?:1[89]\d\d|20[0-3]\d)).*$/su', '$1', mb_substr($value, $dates[$i - 1]['pos']))) : 0;
            $before = mb_substr($value, $prevEnd, max(0, $start - $prevEnd));
            // « 1973 (voyageurs) » : la parenthèse qui suit ; sinon « Voyageurs le 6 mars 1972 » : ce qui précède.
            $paren = preg_match('/^\s*\(([^)]*)\)/u', $after, $pm) ? $pm[1] : null;
            // Disposition du champ : « 1938 (voyageurs) 1988 … » (précision après la date) ou
            // « Voyageurs le 6 mars 1972 Marchandises le … » (avant). Précision après la date : le
            // texte qui précède est celle de la date d'avant (« 15 mai 1939 (voyageurs) 1991 (ligne) » :
            // 1991 n'est pas une fin des voyageurs).
            $type = ($paren !== null ? self::trafficType($paren) : null)
                ?? ($qualifierFirst ? self::trafficType($before) ?? self::trafficType($after) : self::trafficType($after))
                ?? 'closure';
            if (preg_match('/\bavant\s*$/iu', $before)) {
                $out[] = ['type' => $type, 'date' => $d['date'], 'precision' => 'before',
                    'quote' => 'Fermeture : '.$raw];

                continue;
            }
            // « 1947 (exploitation du CFEL) » : fermeture d'un exploitant, pas forcément de la gare.
            if (preg_match('/exploitation|concession|compagnie|tramway|\bCF[A-Z]+\b/u', $after) && count($dates) > 1) {
                continue;
            }
            $out[] = ['type' => $type, 'date' => $d['date'], 'precision' => $d['precision'] === 'decade' ? 'decade' : $d['precision'],
                'quote' => 'Fermeture : '.$raw.($hedged ? ' (date incertaine selon la source)' : '')];
        }
        if ($out === [] && ($y = self::centuryLowerBound($value)) !== null) {
            $out[] = ['type' => 'closure', 'date' => sprintf('%04d-01-01', $y), 'precision' => 'after', 'quote' => 'Fermeture : '.$raw];
        }

        return $out;
    }

    /**
     * Phrase de l'article d'une gare : sujet = la gare, verbe de fermeture, date.
     *
     * @return list<array<string, mixed>>
     */
    private function stationSentence(string $s, string $prev = ''): array
    {
        if (! preg_match_all(self::CLOSE_VERB, $s, $verbs, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            return [];
        }
        $out = [];
        foreach ($verbs as $v) {
            $vpos = mb_strlen(substr($s, 0, $v[0][1]));
            $vend = $vpos + mb_strlen($v[0][0]);
            $head = mb_substr($s, 0, $vpos);
            // Sujet : la gare (ou « elle »), dernier sujet avant le verbe, sans autre sujet entre les deux.
            if (! preg_match_all(self::SUBJECT, $head, $subj, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            $last = end($subj[1]);
            $subjPos = mb_strlen(substr($head, 0, $last[1]));
            // « La section entre la gare de X et celle de Y, fermée… » : une portion de ligne.
            if (preg_match(self::BEFORE_MENTION_SECTION, mb_substr($head, max(0, $subjPos - 30), min(30, $subjPos)))) {
                continue;
            }
            $subjEnd = $subjPos + mb_strlen($last[0]);
            // « Elle » qui reprend la ligne (« La ligne … ; elle est fermée… », phrase précédente sur la ligne).
            if (mb_strtolower($last[0]) === 'elle' && (preg_match(self::LINE_TOPIC, mb_substr($head, 0, $subjPos))
                || ($subjPos < 3 && preg_match('/^(?:(?:En|Le|Dès|Depuis)\s[^,]{1,30},\s*)?'.trim(self::LINE_TOPIC, '/iu').'/iu', $prev)))) {
                continue;
            }
            $gap = mb_substr($head, $subjEnd);
            if (preg_match(self::OTHER_SUBJECT, $gap) || preg_match('/[;:(]|\b(?:qui|que|dont|mais|où|sauf|lorsque|quand)\b/u', $gap) || mb_strlen($gap) > 90) {
                continue;
            }
            // « supprimée » ne se dit d'une gare que dans « la gare est supprimée ».
            if (preg_match('/^supprim/iu', $v[1][0] ?? '') && ! preg_match('/^\s*(?:est|a été|fut)?\s*(?:définitivement\s+)?$/u', $gap)) {
                continue;
            }
            // « fermée au trafic au cours du XXe siècle (1946 pour les voyageurs, 1973 pour les marchandises) ».
            if (preg_match_all('/((?:(?:1er|[12]?\d|3[01])\s+\p{L}+\s+)?(?:1[89]\d\d|20[0-3]\d))\s+(?:pour|aux?)\s+(?:les\s+|le\s+)?(voyageurs|marchandises|fret)\b/iu',
                mb_substr($s, $vend, 160), $pairs, PREG_SET_ORDER)) {
                foreach ($pairs as $p) {
                    $d = $this->wp->findDates($p[1])[0] ?? null;
                    if ($d && $d['year'] >= 1827) {
                        $out[] = ['type' => self::trafficType($p[2]), 'date' => $d['date'], 'precision' => $d['precision'], 'quote' => self::quote($s)];
                    }
                }
                break;
            }
            $e = $this->dateNear($s, $vpos, $vend);
            if ($e === null) {
                continue;
            }
            // Date avant le verbe (« En 2007, la gare est désaffectée du trafic marchandises ») : la portée
            // se lit juste après le verbe, jusqu'à la ponctuation.
            $window = $e['pos'] > $vend ? mb_substr($s, $vend, $e['pos'] - $vend) : (preg_split('/[,;.(]/u', mb_substr($s, $vend, 80))[0] ?? '');
            $type = self::trafficType($window) ?? (preg_match('/plus\s+desservie|cesse/iu', $v[0][0]) ? 'closure_passenger' : 'closure');
            $hedge = preg_match(self::HEDGE, mb_substr($s, max(0, $vpos - 40), 120));
            $out[] = ['type' => $type, 'date' => $e['date'], 'precision' => $e['precision'],
                'quote' => self::quote($s).($hedge ? ' (date incertaine selon la source)' : '')];
            // « …fermée aux voyageurs en 1938, puis au fret en 1952 ».
            $rest = mb_substr($s, $e['pos']);
            if (preg_match('/^[^,;]{0,30},?\s*puis\s+(?:aux?|à)\s+(?:la\s+|l[\'’])?(fret|marchandises|voyageurs|tout trafic)\b(.{0,40})/iu', $rest, $m)) {
                $d2 = array_values(array_filter($this->wp->findDates($m[2]), fn ($d) => $d['precision'] !== 'decade'))[0] ?? null;
                if ($d2) {
                    $out[] = ['type' => self::trafficType($m[1]) ?? 'closure', 'date' => $d2['date'], 'precision' => $d2['precision'], 'quote' => self::quote($s)];
                }
            }
            break;
        }

        return $out;
    }

    // ── Article de la ligne ────────────────────────────────────────────────

    /**
     * Gares fermées nommées dans l'article d'une ligne : phrases (« les
     * haltes de X et de Y sont fermées »), puces (« * [[Gare de X]], ouverte
     * le …, fermée le … »), tableaux (« X || (Fermée en 2018) »).
     *
     * @return list<array<string, mixed>> événements avec 'names'
     */
    public function fromLineArticle(string $wikitext, string $title): array
    {
        $out = [];
        $raw = preg_replace('/<!--.*?-->/su', '', $wikitext);
        $raw = preg_replace('/<ref[^>]*\/>/iu', '', $raw);
        $raw = preg_replace('/<ref[^>]*>.*?<\/ref\s*>/isu', '', $raw);

        // Tableaux : une ligne de tableau par gare.
        foreach ($this->tableRows($raw) as $row) {
            foreach ($this->tableRow($row['cells'], $row['headers']) as $e) {
                $out[] = $e + ['origin' => 'tableau'];
            }
        }

        // Puces qui commencent par une gare : « * [[Gare de X|X]], ouverte le …, fermée le … ».
        foreach (preg_split('/\n/u', $raw) as $line) {
            if (! preg_match('/^[*#]+\s*(?:\'{2,3})?\[\[\s*((?:Gare|Halte|Arrêt)s?\s+(?:de\s+la\s+|de\s+l[\'’]|du\s+|des\s+|de\s+|d[\'’])[^\]|]+)(?:\|([^\]]+))?\]\]/u', $line, $m)) {
                continue;
            }
            $name = trim($m[2] ?? '') !== '' ? $m[2] : preg_replace('/^(?:Gare|Halte|Arrêt)s?\s+(?:de\s+la\s+|de\s+l[\'’]|du\s+|des\s+|de\s+|d[\'’])/u', '', $m[1]);
            $name = trim(preg_replace('/\s*\(.*?\)\s*$/u', '', $this->wp->toPlainText($name)));
            $text = trim($this->wp->toPlainText(mb_substr($line, mb_strlen($m[0]))));
            if (preg_match_all(self::CLOSE_VERB, $text, $verbs, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
                foreach ($verbs as $v) {
                    $vpos = mb_strlen(substr($text, 0, $v[0][1]));
                    $vend = $vpos + mb_strlen($v[0][0]);
                    // Le verbe porte sur la gare de la puce, pas sur un autre sujet cité ensuite.
                    if (preg_match(self::OTHER_SUBJECT, mb_substr($text, 0, $vpos))) {
                        break;
                    }
                    $d = $this->dateNear($text, $vpos, $vend, false);
                    if ($d) {
                        $window = mb_substr($text, $vend, max(0, $d['pos'] - $vend));
                        $out[] = ['names' => [$name], 'type' => self::trafficType($window) ?? 'closure', 'date' => $d['date'],
                            'precision' => $d['precision'], 'quote' => self::quote($name.' '.$text), 'origin' => 'liste'];
                        break;
                    }
                }
            }
        }

        // Phrases du texte.
        $text = $this->wp->toPlainText($raw, $title);
        $text = preg_replace('/^[|!].*$/mu', '', $text); // tableaux traités plus haut
        foreach ($this->sentences($text, false) as $s) {
            foreach ($this->lineSentence($s) as $e) {
                $out[] = $e + ['origin' => 'texte'];
            }
        }

        return self::dedupe($out, true);
    }

    /**
     * Phrase d'un article de ligne qui nomme des gares fermées.
     *
     * @return list<array<string, mixed>>
     */
    private function lineSentence(string $s): array
    {
        $kinds = '(?:gares?|haltes?|arrêts?|points?\s+d[\'’]arrêt|stations?)';
        $prep = '(?:de\s+la\s+|de\s+l[\'’]|du\s+|des\s+|de\s+|d[\'’])';
        $list = '('.self::NAME.'(?:\s*,\s*'.$prep.'?'.self::NAME.')*(?:\s+et\s+'.$prep.'?'.self::NAME.')?)';
        $rx = '/\b(?:(fermeture|suppression|désaffectation)\s+(?:de\s+la\s+|des\s+|du\s+|de\s+l[\'’]))?'.$kinds.'\s+(?:(?:voyageurs?|intermédiaires?|rurales?)\s+)?'.$prep.$list.'/u';
        if (! preg_match_all($rx, $s, $mm, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            return [];
        }
        $out = [];
        foreach ($mm as $m) {
            $mpos = mb_strlen(substr($s, 0, $m[0][1]));
            $mend = $mpos + mb_strlen($m[0][0]);
            $before = mb_substr($s, max(0, $mpos - 30), min(30, $mpos));
            if (preg_match(self::BEFORE_MENTION_SECTION, $before)) {
                continue;
            }
            // « de la gare de X à celle de Y », « gare de X à Y » : une portion de ligne.
            if (preg_match('/^\s*(?:à|au|aux|jusqu)\b/u', mb_substr($s, $mend, 8))) {
                continue;
            }
            $names = self::splitNames($m[2][0]);
            if ($names === []) {
                continue;
            }
            $nominal = ($m[1][0] ?? '') !== '' && $m[1][1] >= 0;
            if ($nominal) {
                $vpos = $mpos;
                $vend = $mpos + mb_strlen($m[1][0]);
                $after = mb_substr($s, $mend, 60);
                $window = mb_substr($s, max(0, $mpos - 40), 40).' '.$after;
            } else {
                $tail = mb_substr($s, $mend);
                if (! preg_match(self::CLOSE_VERB, $tail, $v, PREG_OFFSET_CAPTURE)) {
                    continue;
                }
                $gapLen = mb_strlen(substr($tail, 0, $v[0][1]));
                $gap = mb_substr($tail, 0, $gapLen);
                if ($gapLen > 120 || preg_match(self::OTHER_SUBJECT, $gap) || preg_match('/[;:]|\b(?:qui|que|dont|où)\b/u', $gap)
                    || preg_match('/\b(?:reste|restent|demeure|demeurent|subsiste|subsistent|maintenue?s?|rouverte?s?|ouverte?s?)\b/iu', $gap)) {
                    continue;
                }
                $vpos = $mend + $gapLen;
                $vend = $vpos + mb_strlen($v[0][0]);
                $window = mb_substr($s, $vend, 60);
            }
            $d = $this->dateNear($s, $vpos, $vend);
            if ($d === null) {
                continue;
            }
            $type = self::trafficType(mb_substr($s, $vend, max(0, $d['pos'] - $vend))) ?? self::trafficType($window) ?? 'closure';
            if (preg_match('/^halte/iu', $m[0][0]) || preg_match('/\bhaltes?\b/iu', $m[0][0])) {
                $type = $type === 'closure_passenger' ? 'closure' : $type;
            }
            $out[] = ['names' => $names, 'type' => $type, 'date' => $d['date'], 'precision' => $d['precision'], 'quote' => self::quote($s)];
        }

        return $out;
    }

    /**
     * Lignes de tableaux wiki : cellules brutes + en-têtes du tableau.
     *
     * @return list<array{cells: list<string>, headers: list<string>}>
     */
    private function tableRows(string $raw): array
    {
        $out = [];
        if (! preg_match_all('/^\{\|.*?^\|\}/msu', $raw, $tables)) {
            return [];
        }
        foreach ($tables[0] as $table) {
            $headers = [];
            foreach (preg_split('/^\|-.*$/mu', $table) as $chunk) {
                $chunk = trim(preg_replace('/^\{\|.*$/mu', '', $chunk, 1));
                if ($chunk === '') {
                    continue;
                }
                if (str_starts_with($chunk, '!')) {
                    $headers = array_map(fn ($h) => mb_strtolower(trim($this->wp->toPlainText(preg_replace('/^[^|]*\|(?!\|)/u', '', $h)))),
                        preg_split('/!!|\n!/u', ltrim($chunk, '!')));

                    continue;
                }
                $cells = preg_split('/\|\||\n\|(?!\})/u', ltrim($chunk, '|'));
                // Attributs de cellule « style="…" | valeur ».
                $cells = array_map(fn ($c) => preg_match('/^\s*(?:style|align|class|rowspan|colspan|bgcolor|width)\s*=[^|\[\]]*\|(?!\|)(.*)$/su', $c, $m) ? $m[1] : $c, $cells);
                $out[] = ['cells' => array_values($cells), 'headers' => $headers];
            }
        }

        return $out;
    }

    /**
     * Une ligne de tableau : gare (lien « Gare de X ») + cellule « Fermée en 2018 »
     * ou colonne « Fermeture ».
     *
     * @param  list<string>  $cells
     * @param  list<string>  $headers
     * @return list<array<string, mixed>>
     */
    private function tableRow(array $cells, array $headers): array
    {
        $nameIdx = null;
        $name = null;
        foreach ($cells as $i => $c) {
            if (preg_match('/\[\[\s*(?:Gare|Halte|Arrêt)s?\s+(?:de\s+la\s+|de\s+l[\'’]|du\s+|des\s+|de\s+|d[\'’])([^\]|]+)(?:\|([^\]]+))?\]\]/u', $c, $m)) {
                $nameIdx = $i;
                $name = trim($m[2] ?? '') !== '' ? $m[2] : $m[1];
                break;
            }
        }
        if ($name === null) {
            // Colonne « Gare » / « Nom » sans lien.
            foreach ($headers as $i => $h) {
                if (preg_match('/^(gares?|haltes?|stations?|arrêts?)\b/u', $h) && isset($cells[$i])) {
                    $nameIdx = $i;
                    $name = $cells[$i];
                    break;
                }
            }
        }
        if ($name === null) {
            return [];
        }
        $name = trim(preg_replace('/\s*\(.*?\)\s*$/u', '', $this->wp->toPlainText($name)));
        if ($name === '' || mb_strlen($name) > 80) {
            return [];
        }
        $out = [];
        foreach ($cells as $i => $c) {
            if ($i === $nameIdx) {
                continue;
            }
            $plain = trim(preg_replace('/\s+/u', ' ', $this->wp->toPlainText($c)));
            $header = $headers[$i] ?? '';
            $closeCol = (bool) preg_match('/fermeture|fermée|désaffectation|suppression/u', $header);
            if (! $closeCol && ! preg_match('/^\(?\s*(?:fermée?s?|désaffectée?s?|supprimée?s?)\b/iu', $plain)) {
                continue;
            }
            if (count($this->wp->findDates($plain)) > 3) {
                continue;
            }
            foreach ($this->closureValue($plain) as $e) {
                // Colonne « Fermeture aux voyageurs » : le type vient de l'en-tête.
                if ($e['type'] === 'closure' && ($t = self::trafficType($header))) {
                    $e['type'] = $t;
                }
                $out[] = ['names' => [$name], 'quote' => self::quote($name.' : '.($header !== '' && $closeCol ? $header.' ' : '').$plain)] + $e;
            }
        }

        return $out;
    }

    // ── Wikidata ───────────────────────────────────────────────────────────

    /** États d'utilisation (P5817) qui disent la gare fermée. */
    private const WD_CLOSED_STATES = ['Q11639308', 'Q55570340', 'Q104664889', 'Q56651571', 'Q110435753', 'Q56556915', 'Q63065035'];

    private const WD_IN_USE = ['Q55654238', 'Q109551035'];

    /** Classes « gare » (P31) : fin de la déclaration = la gare cesse d'en être une. */
    private const WD_STATION_CLASSES = ['Q55488', 'Q55678', 'Q4663385', 'Q1793804', 'Q1402443', 'Q55485', 'Q55491', 'Q1147171', 'Q1335652'];

    /**
     * Fermetures Wikidata que vff:import-station-sources ne lit pas :
     *   P582 « date de fin » (déclaration principale) ;
     *   P31 gare / halte avec P582, sans autre P31 gare encore valable ;
     *   P5817 état fermé avec P580, ou état « en fonctionnement » avec P582 ;
     *   P793 « événement clé » fermeture (Q5135520), désaffectation
     *   (Q12780996) ou démolition (Q331483) avec P585 ;
     *   P3999 / P576 à la décennie près (précision 8, ignorée à l'import).
     * $withMain : relit aussi P3999 / P576 précis (élément Wikidata secondaire
     * de la gare, dont l'import ne garde pas les dates).
     *
     * @param  array<string, mixed>  $ent  entité wbgetentities
     * @return list<array<string, mixed>>
     */
    public static function fromWikidata(array $ent, bool $withMain = false): array
    {
        $claims = $ent['claims'] ?? [];
        $out = [];
        $add = function (string $type, ?array $time, string $prop, string $what) use (&$out) {
            $t = self::wdTime($time);
            if ($t !== null) {
                $out[] = ['type' => $type, 'date' => $t[0], 'precision' => $t[1], 'prop' => $prop, 'quote' => $what, 'origin' => $prop];
            }
        };
        $statements = fn (string $p) => array_filter($claims[$p] ?? [], fn ($c) => ($c['rank'] ?? '') !== 'deprecated'
            && ($c['mainsnak']['snaktype'] ?? '') === 'value');
        $qual = fn (array $c, string $p) => $c['qualifiers'][$p][0]['datavalue']['value'] ?? null;

        foreach ($statements('P582') as $c) {
            $add('closure', $c['mainsnak']['datavalue']['value'] ?? null, 'P582', 'Wikidata : date de fin (P582)');
        }
        $p31 = $statements('P31');
        $openClass = array_filter($p31, fn ($c) => in_array($c['mainsnak']['datavalue']['value']['id'] ?? '', self::WD_STATION_CLASSES, true)
            && empty($c['qualifiers']['P582']));
        if ($openClass === []) {
            foreach ($p31 as $c) {
                if (in_array($c['mainsnak']['datavalue']['value']['id'] ?? '', self::WD_STATION_CLASSES, true) && ($end = $qual($c, 'P582'))) {
                    $add('closure', $end, 'P31', 'Wikidata : nature « gare » jusqu\'à cette date (P31, qualificatif P582)');
                }
            }
        }
        foreach ($statements('P5817') as $c) {
            $state = $c['mainsnak']['datavalue']['value']['id'] ?? '';
            $parts = array_map(fn ($q) => $q['datavalue']['value']['id'] ?? null, $c['qualifiers']['P518'] ?? []);
            if ($parts !== [] && ! array_intersect($parts, self::WD_STATION_CLASSES)) {
                continue; // s'applique à une autre partie (gare routière…)
            }
            if (in_array($state, self::WD_CLOSED_STATES, true) && ($start = $qual($c, 'P580'))) {
                $add($state === 'Q56556915' ? 'demolition' : 'closure', $start, 'P5817', 'Wikidata : état d\'utilisation fermé depuis cette date (P5817, qualificatif P580)');
            } elseif (in_array($state, self::WD_IN_USE, true) && ($end = $qual($c, 'P582'))) {
                $add('closure', $end, 'P5817', 'Wikidata : en fonctionnement jusqu\'à cette date (P5817, qualificatif P582)');
            }
        }
        foreach ($statements('P793') as $c) {
            $ev = $c['mainsnak']['datavalue']['value']['id'] ?? '';
            $type = ['Q5135520' => 'closure', 'Q12780996' => 'closure', 'Q331483' => 'demolition'][$ev] ?? null;
            if ($type && ($when = $qual($c, 'P585'))) {
                $add($type, $when, 'P793', 'Wikidata : événement clé « '.($type === 'demolition' ? 'démolition' : 'fermeture').' » (P793, qualificatif P585)');
            }
        }
        foreach (['P3999' => 'date de fermeture officielle', 'P576' => 'date de dissolution ou de fin'] as $p => $label) {
            foreach ($statements($p) as $c) {
                $v = $c['mainsnak']['datavalue']['value'] ?? null;
                if (($v['precision'] ?? 0) === 8 || ($withMain && ($v['precision'] ?? 0) >= 9)) {
                    $type = $p === 'P3999' && array_intersect(array_map(fn ($q) => $q['datavalue']['value']['id'] ?? null,
                        $c['qualifiers']['P518'] ?? []), ['Q1433891', 'Q2072431']) ? 'closure_passenger' : 'closure';
                    $add($type, $v, $p, "Wikidata : {$label} ({$p})");
                }
            }
        }

        return self::dedupe($out);
    }

    /**
     * @return array{0: string, 1: string}|null [Y-m-d, précision]
     */
    private static function wdTime(?array $v): ?array
    {
        if (! $v || ! isset($v['time']) || ($v['precision'] ?? 0) < 8 || ! preg_match('/^\+(\d{4})-(\d{2})-(\d{2})/', $v['time'], $m)) {
            return null;
        }
        $y = (int) $m[1];
        if ($y < 1827 || $y > (int) date('Y')) {
            return null;
        }
        if ($v['precision'] === 8) {
            return [sprintf('%04d-01-01', intdiv($y, 10) * 10), 'decade'];
        }
        $prec = $v['precision'] >= 11 ? 'day' : ($v['precision'] === 10 ? 'month' : 'year');
        $mo = $prec === 'year' || $m[2] === '00' ? '01' : $m[2];
        $d = $prec !== 'day' || $m[3] === '00' ? '01' : $m[3];

        return ["{$m[1]}-{$mo}-{$d}", $prec];
    }

    // ── Texte libre (OSM description / note) ────────────────────────────────

    /**
     * « Gare non desservie depuis 2016 », « gare fermée en 1972 ».
     *
     * @return list<array<string, mixed>>
     */
    public static function fromFreeText(string $text): array
    {
        $text = trim($text);
        if ($text === '' || ! preg_match('/\b(fermée?|désaffectée?|non desservie|plus desservie|fermeture)\b\D{0,25}?\b(?:en|depuis|le|en l[\'’]an)?\s*((?:1er|[12]?\d|3[01])\s+\p{L}+\s+)?(1[89]\d\d|20[0-3]\d)\b/iu', $text, $m)) {
            return [];
        }
        $y = (int) $m[3];
        if ($y < 1827 || $y > (int) date('Y')) {
            return [];
        }
        $type = preg_match('/voyageur|desservie/iu', $text) ? 'closure_passenger' : 'closure';

        return [['type' => $type, 'date' => sprintf('%04d-01-01', $y), 'precision' => 'year', 'quote' => mb_strimwidth($text, 0, 200, '…')]];
    }

    // ── Outils ──────────────────────────────────────────────────────────────

    /**
     * Date de l'événement : la plus proche APRÈS le verbe (≤ 70 caractères,
     * sans rupture de sens entre les deux), sinon une date en tête de phrase
     * (« En 1960, les haltes… sont fermées »), sinon la plus proche avant le
     * verbe dans la même proposition.
     *
     * @return array{date: string, precision: string, pos: int}|null
     */
    private function dateNear(string $s, int $vpos, int $vend, bool $allowCentury = true): ?array
    {
        $dates = array_values(array_filter($this->wp->findDates($s), fn ($d) => $d['year'] >= 1827 && $d['year'] <= (int) date('Y')));
        foreach ($dates as $d) {
            if ($d['pos'] < $vend) {
                continue;
            }
            $gap = mb_substr($s, $vend, $d['pos'] - $vend);
            if (mb_strlen($gap) > 70 || preg_match(self::GAP_BREAK, $gap) || preg_match('/\b(?:ouverte?s?|rouverte?s?|mise?s? en service|construite?s?|inaugurée?s?)\b/iu', $gap)) {
                break;
            }
            // « à la fin de l'année 2011 » : une année, pas une décennie (« années 1980 »).
            if ($d['precision'] === 'decade' && ! preg_match('/^années/iu', mb_substr($s, $d['pos'], 7))) {
                $d = ['precision' => 'year', 'date' => sprintf('%04d-01-01', $d['year'])] + $d;
            }
            $prec = $d['precision'] === 'decade' ? 'decade' : $d['precision'];
            $date = $d['precision'] === 'decade' ? sprintf('%04d-01-01', intdiv($d['year'], 10) * 10) : $d['date'];

            return ['date' => $date, 'precision' => $prec, 'pos' => $d['pos']];
        }
        // Siècle (« fermée dans la deuxième moitié du XXe siècle ») : borne basse seulement.
        if ($allowCentury && preg_match('/^.{0,40}?\b(?:[XVI]+e\s+siècle)/u', mb_substr($s, $vend), $cm)
            && ($y = self::centuryLowerBound($cm[0])) !== null) {
            return ['date' => sprintf('%04d-01-01', $y), 'precision' => 'after', 'pos' => $vend];
        }
        // « En 1960, les haltes de … sont fermées » / « Le 6 mars 1972, la gare ferme ».
        $first = $dates[0] ?? null;
        if ($first && $first['pos'] < $vpos && $first['precision'] !== 'decade'
            && preg_match('/^\s*(?:\*\s*)?(?:en|le|dès|depuis|à partir du|au|fin|début)?\s*$/iu', mb_substr($s, 0, $first['pos']))
            && ! preg_match('/[;:]/u', mb_substr($s, $first['pos'], $vpos - $first['pos']))
            && count(array_filter($dates, fn ($d) => $d['pos'] < $vpos)) === 1) {
            return ['date' => $first['date'], 'precision' => $first['precision'], 'pos' => $first['pos']];
        }
        // Puce de chronologie « * 6 mars 1972 : fermeture des gares… ».
        if ($first && $first['pos'] < $vpos && $first['precision'] !== 'decade'
            && preg_match('/^\s*(?:le\s+)?$/iu', mb_substr($s, 0, $first['pos']))
            && preg_match('/^[^.]{0,25}:\s/u', mb_substr($s, $first['pos'], $vpos - $first['pos']))) {
            return ['date' => $first['date'], 'precision' => $first['precision'], 'pos' => $first['pos']];
        }

        return null;
    }

    /**
     * « au trafic des voyageurs » → closure_passenger ; « aux marchandises »
     * → closure_freight ; « à tout trafic » → closure ; sinon null.
     */
    public static function trafficType(string $s): ?string
    {
        $s = preg_replace('/\bbâtiments?\s+(?:des\s+)?voyageurs\b|\bhalles?\s+(?:aux|à)\s+marchandises\b/iu', ' ', $s);
        $passenger = (bool) preg_match('/voyageurs?|\bomnibus\b/iu', $s);
        $freight = (bool) preg_match('/marchandises?|\bfrets?\b|\bgrande vitesse\b|\bpetite vitesse\b/iu', $s);
        if (preg_match('/tout trafic|tous trafics|toutes? (?:les )?circulations?|à toute circulation/iu', $s) || ($passenger && $freight)) {
            return 'closure';
        }
        if ($passenger || $freight) {
            return $passenger ? 'closure_passenger' : 'closure_freight';
        }

        return preg_match('/définitivement|totalement/iu', $s) ? 'closure' : null;
    }

    /**
     * « deuxième moitié du XXe siècle » → 1950 (la gare était ouverte au moins
     * jusque-là) ; « fin du XXe » → 1970 ; « début du XXIe » → 2000 ;
     * « XXe siècle » → 1900. Null hors XIXe à XXIe siècles.
     */
    public static function centuryLowerBound(string $s): ?int
    {
        if (! preg_match('/\b(XIX|XX|XXI)e\s+siècle/iu', $s, $m, PREG_OFFSET_CAPTURE)) {
            return null;
        }
        $start = ['XIX' => 1800, 'XX' => 1900, 'XXI' => 2000][strtoupper($m[1][0])];
        $before = mb_strtolower(substr($s, max(0, $m[0][1] - 45), min(45, $m[0][1])));
        $offset = match (true) {
            (bool) preg_match('/(?:2|deux)(?:e|ème|ième)\s+moitié|seconde?\s+moitié|troisième quart/u', $before) => 50,
            (bool) preg_match('/\bfin\b|dernier quart|dernières années/u', $before) => 70,
            (bool) preg_match('/\bmilieu\b/u', $before) => 40,
            default => 0,
        };
        $y = $start + $offset;

        return $y >= 1827 && $y <= (int) date('Y') ? $y : null;
    }

    /** {{XXe siècle}}, {{2e|moitié}} → texte lisible avant le nettoyage du wikitexte. */
    public static function expandCenturies(string $wikitext): string
    {
        $s = preg_replace('/\{\{\s*([XVI]+)e\s+siècle\s*\}\}/u', '$1e siècle', $wikitext);
        $s = preg_replace('/\{\{\s*(\d+)\s*(e|er|re|ème)\s*\|\s*([^{}|]*)\}\}/u', '$1$2 $3', $s);

        return preg_replace('/\{\{\s*(?:s|s-|sp|sp-|siècle)\s*\|\s*([XVI]+)\s*(?:\|[^{}]*)?\}\}/iu', '$1e siècle', $s);
    }

    /** Wikitexte sans l'infobox (son champ « fermeture » est lu à part). */
    private static function withoutInfobox(string $wikitext): string
    {
        if (! preg_match('/\{\{\s*Infobox/iu', $wikitext, $m, PREG_OFFSET_CAPTURE)) {
            return $wikitext;
        }
        $start = $m[0][1];
        $depth = 0;
        $len = strlen($wikitext);
        for ($i = $start; $i < $len - 1; $i++) {
            $two = $wikitext[$i].$wikitext[$i + 1];
            if ($two === '{{') {
                $depth++;
                $i++;
            } elseif ($two === '}}') {
                $depth--;
                $i++;
                if ($depth === 0) {
                    return substr($wikitext, 0, $start).substr($wikitext, $i + 1);
                }
            }
        }

        return $wikitext;
    }

    /**
     * Phrases du texte brut (titres, tableaux et catégories écartés). Article
     * de gare : sections bibliographiques et « Voir aussi » ignorées.
     *
     * @return list<string>
     */
    private function sentences(string $text, bool $stationArticle): array
    {
        $out = [];
        $skip = false;
        foreach (explode("\n", $text) as $line) {
            $line = trim($line);
            if ($line === '' || preg_match('/^[|!{]/u', $line)) {
                continue;
            }
            if (preg_match('/^={2,6}\s*(.+?)\s*=+$/u', $line, $h)) {
                $skip = (bool) preg_match('/^(notes?|références|sources?|bibliographie|voir aussi|liens? externes?|articles? connexes|annexes?|galerie|patrimoine)/iu', $h[1]);

                continue;
            }
            if ($skip) {
                continue;
            }
            foreach (preg_split('/(?<=[.!?])\s+(?=[\p{Lu}«*])/u', $line) as $s) {
                $s = trim($s);
                if (mb_strlen($s) >= 12) {
                    $out[] = $s;
                }
            }
        }

        return $out;
    }

    /**
     * « les haltes de Tourgéville et de Gonneville - Saint-Vaast » → noms.
     *
     * @return list<string>
     */
    public static function splitNames(string $list): array
    {
        $parts = preg_split('/\s*,\s*|\s+et\s+/u', $list);
        $out = [];
        foreach ($parts as $p) {
            $p = trim(preg_replace('/^(?:de\s+la\s+|de\s+l[\'’]|du\s+|des\s+|de\s+|d[\'’])/u', '', trim($p)));
            $p = trim($p, " .'’-");
            if ($p === '' || mb_strlen($p) < 3 || ! preg_match('/^\p{Lu}/u', ltrim($p, 'LeLaLs \'’'))) {
                continue;
            }
            $out[] = $p;
        }

        return array_values(array_unique($out));
    }

    private static function quote(string $s): string
    {
        $s = trim(preg_replace('/\s+/u', ' ', str_replace(['⟦', '⟧'], '', $s)));

        return str_replace(['—', '–'], '-', mb_strimwidth($s, 0, 290, '…'));
    }

    /**
     * @param  list<array<string, mixed>>  $events
     * @return list<array<string, mixed>>
     */
    private static function dedupe(array $events, bool $withNames = false): array
    {
        $out = [];
        foreach ($events as $e) {
            $k = $e['type'].'|'.$e['date'].'|'.$e['precision'].($withNames ? '|'.implode('/', $e['names'] ?? []) : '');
            $out[$k] ??= $e;
        }

        return array_values($out);
    }
}

<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Descriptions et citations générées des événements de ligne (line_events), dans la
 * langue de la page (chronique de la fiche ligne).
 *
 * Les importeurs écrivent en français, sur des gabarits fixes (cf. lang/fr/generated.php :
 * label.*, event.*, heritage.*) : WikipediaLineParser (« Fermeture aux voyageurs de X à Y
 * (Wikipédia, liste, « Histoire ») »), vff:import-wikidata-lines, OhmEvents,
 * DatesFromTraceNames, ImportLignesOubliees, ImportSecondaryNetworkDates, ImportHeritageLines,
 * DetectPresentStates, ApplyEventCorrections. En français, le texte stocké est affiché tel quel ;
 * en anglais et en allemand, il est reconnu puis rendu avec les gabarits traduits. Noms de
 * lieux, de compagnies, d'articles et de lignes : inchangés.
 *
 * Ce qui n'est pas un gabarit reste dans sa langue et est signalé (lang = fr) : citation d'une
 * source (phrase d'un article, fiche lignes-oubliees), raison rédigée d'une correction sourcée,
 * libellés Wikidata (français), texte libre. Jamais présenté comme traduit.
 */
final class EventText
{
    /** @var array<string, string>|null libellé français → clé (generated.label.*) */
    private static ?array $labels = null;

    /**
     * Description d'un événement dans une langue.
     *
     * @return array{text: ?string, lang: ?string} lang : « fr » si tout ou partie reste en français
     */
    public static function describe(?string $source, ?string $description, string $locale, ?string $from = null, ?string $to = null): array
    {
        if ($description === null || trim($description) === '' || $locale === 'fr') {
            return ['text' => $description, 'lang' => null];
        }
        $d = trim($description);
        $r = self::special($source, $d, $locale) ?? self::structured($d, $locale, $from, $to);
        // Correction sourcée au libellé rédigé à la main : libellé et raison restent en français, le cadre est traduit.
        if ($r === null && preg_match('/^(.+?) \(correction sourcée : (.*)\)$/su', $d, $m)) {
            $r = ['text' => '« '.$m[1].' » '.self::t('event.correction', ['reason' => $m[2]], $locale), 'lang' => 'fr'];
        }

        return $r ?? ['text' => $description, 'lang' => 'fr'];
    }

    /**
     * Citation (source_quote) dans une langue : les citations générées (« Infobox : … »,
     * « Wikidata Q… · … », « way w… : … ») sont traduites ; une vraie citation de source reste
     * telle quelle, signalée en français.
     *
     * @return array{text: ?string, lang: ?string}
     */
    public static function quote(?string $source, ?string $quote, string $locale): array
    {
        if ($quote === null || trim($quote) === '' || $locale === 'fr') {
            return ['text' => $quote, 'lang' => null];
        }
        $q = trim($quote);
        if (preg_match('/^Infobox : (.+)$/su', $q, $m)) {
            return ['text' => self::t('event.infobox_quote', ['raw' => str_replace(' ; ', '; ', $m[1])], $locale), 'lang' => null];
        }
        if (preg_match('/^Wikidata (Q\d+) · (P\d+) \((.*?)\) = (.*?) - date retenue (\S+) \(précision : (jour|mois|année)\)(.*)$/su', $q, $m)) {
            $prop = array_search($m[3], (array) trans('generated.event', [], 'fr'), true);
            $text = self::t('event.wd_quote', [
                'qid' => $m[1], 'pid' => $m[2],
                'prop' => is_string($prop) && str_starts_with($prop, 'wd_prop_') ? self::t('event.'.$prop, [], $locale) : $m[3],
                'value' => $m[4], 'date' => $m[5],
                'precision' => self::t('event.precision_'.['jour' => 'day', 'mois' => 'month', 'année' => 'year'][$m[6]], [], $locale),
            ], $locale);
            $rest = $m[7];
            $lang = null;
            foreach (['qualificatifs' => 'wd_qualifiers', 'références' => 'wd_references'] as $fr => $key) {
                if (preg_match('/ ; '.$fr.' : (.*?)(?= ; (?:qualificatifs|références) : |$)/su', $rest, $p)) {
                    $text .= '; '.self::t('event.'.$key, ['list' => $p[1]], $locale);
                    $rest = str_replace($p[0], '', $rest);
                    $lang = 'fr'; // libellés Wikidata en français
                }
            }

            return trim($rest) === '' ? ['text' => $text, 'lang' => $lang] : ['text' => $quote, 'lang' => 'fr'];
        }
        if (preg_match('/^(way \S+) : (.*?)(?: \(\+(\d+) autre\(s\) way\(s\) OHM\))?$/su', $q, $m, PREG_UNMATCHED_AS_NULL)) {
            return ['text' => $m[1].': '.$m[2].($m[3] !== null ? ' '.self::t('event.ohm_more_ways', ['n' => $m[3]], $locale) : ''), 'lang' => null];
        }

        // Nom d'un tracé archeoferroviaire : nom propre.
        return ['text' => $quote, 'lang' => $source === 'archeoferroviaire' ? null : 'fr'];
    }

    // ------------------------------------------------------------------
    // Gabarits entiers
    // ------------------------------------------------------------------

    /** @return array{text: string, lang: ?string}|null */
    private static function special(?string $source, string $d, string $locale): ?array
    {
        $fr = fn (string $key) => (string) trans('generated.event.'.$key, [], 'fr');
        foreach (['infobox_closure', 'infobox_closure_partial', 'wd_other_section', 'wd_partial_closure'] as $key) {
            if ($d === $fr($key)) {
                return ['text' => self::t('event.'.$key, [], $locale), 'lang' => null];
            }
        }
        if (preg_match('/^Fermeture de la ligne \(trafic non précisé par Wikidata\)( - d\'après P576 « dissolution ou abolition »)?$/u', $d, $m)) {
            return ['text' => self::t('event.wd_line_closure', [], $locale).(isset($m[1]) ? ' - '.self::t('event.wd_p576', [], $locale) : ''), 'lang' => null];
        }
        if ($d === 'Désélectrification (infobox Wikipédia)') {
            return ['text' => self::t('label.deelectrification', [], $locale).' '.self::t('event.infobox', [], $locale), 'lang' => null];
        }
        if (preg_match('/^Concession à (.+) \(infobox Wikipédia\)$/u', $d, $m)) {
            return ['text' => self::t('event.concession_to', ['company' => $m[1]], $locale), 'lang' => null];
        }
        if (preg_match('/^Fin de concession de (.+) \(infobox Wikipédia\)$/u', $d, $m)) {
            return ['text' => self::t('event.concession_end_of', ['company' => $m[1]], $locale), 'lang' => null];
        }
        if (preg_match('/^(.+) d\'une portion non précisée \(infobox Wikipédia\)$/u', $d, $m) && ($label = self::labelKey($m[1]))) {
            return ['text' => self::t('event.unknown_portion', ['label' => self::t('label.'.$label, [], $locale)], $locale), 'lang' => null];
        }
        if (preg_match('/^Fin de la ligne sous cette forme, remplacée par (.+)$/u', $d, $m)) {
            return ['text' => self::t('event.wd_replaced', ['name' => $m[1]], $locale), 'lang' => null];
        }
        // OpenHistoricalMap.
        if (preg_match('/^(.+?) (?:de la portion (.+) [–-] (.+) \(([\d  ,.]+) km\)|d\'une portion de ([\d  ,.]+) km(?: près de (.+?))?), d\'après OpenHistoricalMap\.$/u', $d, $m)
            && ($label = self::labelKey($m[1]))) {
            $l = self::t('label.'.$label, [], $locale);

            return ['text' => match (true) {
                ($m[2] ?? '') !== '' => self::t('event.ohm_portion', ['label' => $l, 'from' => $m[2], 'to' => $m[3], 'km' => self::num($m[4], $locale)], $locale),
                ($m[6] ?? '') !== '' => self::t('event.ohm_near', ['label' => $l, 'km' => self::num($m[5], $locale), 'place' => $m[6]], $locale),
                default => self::t('event.ohm_length', ['label' => $l, 'km' => self::num($m[5], $locale)], $locale),
            }, 'lang' => null];
        }
        // archeoferroviaire (nom du tracé).
        if (preg_match('/^(Ouverture|Fermeture) \((\d{4})\) d\'après le nom du tracé archeoferroviaire$/u', $d, $m)) {
            return ['text' => self::t('event.archeo', ['label' => self::t('label.'.($m[1] === 'Ouverture' ? 'opening' : 'closure_plain'), [], $locale), 'year' => $m[2]], $locale), 'lang' => null];
        }
        // Voies vertes (DetectPresentStates).
        if (preg_match('/^Reconversion en voie verte \((.+)\), ouverte en (\d{4}) d\'après OpenStreetMap$/u', $d, $m)) {
            return ['text' => self::t('event.greenway_osm', ['label' => $m[1], 'year' => $m[2]], $locale), 'lang' => null];
        }
        if (preg_match('/^Reconversion en voie verte \((.+)\) en (\d{4}) d\'après lignes-oubliees$/u', $d, $m)) {
            return ['text' => self::t('event.greenway_lo', ['label' => $m[1], 'year' => $m[2]], $locale), 'lang' => null];
        }
        // Estimations par réseau (ImportSecondaryNetworkDates).
        if (preg_match('/^Estimation \(date non sourcée pour cette ligne\) : (ouverture supposée à la mise en service|fermeture supposée au plus tard à la fin de l\'exploitation) du réseau « (.+) » \((\d{4})\), réseau reconnu par (.+) ; date à confirmer, jamais une date sûre$/u', $d, $m)) {
            $how = match (true) {
                (bool) preg_match('/^fiche « (.+) » de l\'article$/u', $m[4], $h) => self::t('event.how_record', ['route' => $h[1]], $locale),
                (bool) preg_match('/^position : (.+) cités par l\'article, le long du tracé$/u', $m[4], $h) => self::t('event.how_position', ['places' => $h[1]], $locale),
                default => null,
            };
            if ($how !== null) {
                return ['text' => self::t(str_starts_with($m[1], 'ouverture') ? 'event.estimate_opening' : 'event.estimate_closure',
                    ['network' => $m[2], 'year' => $m[3], 'how' => $how], $locale), 'lang' => null];
            }
        }
        // Exploitations associatives (ImportHeritageLines).
        foreach (['heritage_interruption', 'heritage_resumption', 'heritage_end', 'heritage_start'] as $key) {
            $prefix = (string) preg_replace('/:rest$/', '', $fr($key));
            if (str_starts_with($d, $prefix)) {
                [$rest, $ok] = self::heritageRest(mb_substr($d, mb_strlen($prefix)), $locale);

                return ['text' => self::t('event.'.$key, ['rest' => $rest], $locale), 'lang' => $ok ? null : 'fr'];
            }
        }
        // Fiche lignes-oubliees : rubrique, section et dates de la fiche = citation de la source.
        if ($source === 'lignes-oubliees') {
            $pk = null;
            if (preg_match('/^(.*) - PK déduits (de la liste des gares de la fiche|des autres mentions de la fiche)$/u', $d, $m)) {
                [$d, $pk] = [$m[1], str_starts_with($m[2], 'de la liste') ? 'lo_pk_stations' : 'lo_pk_page'];
            }

            return ['text' => self::t('event.lo_record', ['text' => $d], $locale).($pk ? ' - '.self::t('event.'.$pk, [], $locale) : ''), 'lang' => 'fr'];
        }

        return null;
    }

    // ------------------------------------------------------------------
    // Libellé + portion + suffixe (Wikipédia, Wikidata, réseaux secondaires, corrections)
    // ------------------------------------------------------------------

    /** @return array{text: string, lang: ?string}|null */
    private static function structured(string $d, string $locale, ?string $from, ?string $to): ?array
    {
        $label = null;
        foreach (self::labels() as $fr => $key) {
            if ($d === $fr || str_starts_with($d, $fr.' ') || str_starts_with($d, $fr.',')) {
                $label = [$fr, $key];
                break;
            }
        }
        if ($label === null) {
            return null;
        }
        $rest = mb_substr($d, mb_strlen($label[0]));
        $parts = [self::t('label.'.$label[1], [], $locale)];
        $lang = null;

        // Portion.
        $between = function (string $a, string $b) use ($locale) {
            return self::t('event.between', ['from' => $a, 'to' => $b], $locale);
        };
        $fixed = ['whole_line' => ' de toute la ligne', 'on_whole_line' => ' sur toute la ligne', 'of_line' => ' de la ligne', 'whole_line_paren' => ' (toute la ligne)'];
        $matched = false;
        foreach ($fixed as $key => $fr) {
            // « de la ligne » seul, ou suivi d'un suffixe (« en 25 kV », « (mise en service) »), jamais d'un nom.
            if (str_starts_with($rest, $fr) && ! preg_match('/^\s*\p{Lu}/u', mb_substr($rest, mb_strlen($fr)))) {
                $parts[] = self::t('event.'.$key, [], $locale);
                $rest = mb_substr($rest, mb_strlen($fr));
                $matched = true;
                break;
            }
        }
        if (! $matched && $from !== null && $to !== null) {
            foreach ([' de '.$from.' à '.$to, ' d\''.$from.' à '.$to] as $fr) {
                if (str_starts_with($rest, $fr)) {
                    $parts[] = $between($from, $to);
                    $rest = mb_substr($rest, mb_strlen($fr));
                    $matched = true;
                    break;
                }
            }
        }
        $sectionEnd = '(?= \\(| au plus tard|, | en | - (?:cause|d\'après|section)|$)';
        if (! $matched && (preg_match('/^ de la section (.+?) – (.+?)'.$sectionEnd.'/u', $rest, $m)
            || preg_match('/^ de la section (.+?) - (.+?)'.$sectionEnd.'/u', $rest, $m))) {
            $parts[] = self::t('event.section', ['from' => $m[1], 'to' => $m[2]], $locale);
            $rest = mb_substr($rest, mb_strlen($m[0]));
            $matched = true;
        }
        if (! $matched && preg_match('/^ (?:de |d\')(.+?) à (.+?)(?= \((?:Wikipédia|correction|PK)|$)/u', $rest, $m)) {
            $parts[] = $between($m[1], $m[2]);
            $rest = mb_substr($rest, mb_strlen($m[0]));
        } elseif (! $matched && preg_match('/^ \(à ([^()]+)\)/u', $rest, $m)) {
            $parts[] = self::t('event.at', ['place' => $m[1]], $locale);
            $rest = mb_substr($rest, mb_strlen($m[0]));
        }
        if (preg_match('/^ \(PK ([\d,]+) à ([\d,]+)\)/u', $rest, $m)) {
            $parts[] = self::t('event.pk', ['from' => self::num($m[1], $locale), 'to' => self::num($m[2], $locale)], $locale);
            $rest = mb_substr($rest, mb_strlen($m[0]));
        }

        // Suffixes, dans l'ordre où les importeurs les écrivent.
        $guard = 0;
        while ($rest !== '' && $guard++ < 8) {
            if (preg_match('/^ \(Wikipédia, (texte|liste|tableau|infobox), (?:« (.*) »|introduction)\)$/u', $rest, $m)) {
                $origin = self::t('event.origin_'.$m[1], [], $locale);
                $parts[] = ($m[2] ?? '') !== '' ? self::t('event.wikipedia', ['origin' => $origin, 'heading' => $m[2]], $locale)
                    : self::t('event.wikipedia_intro', ['origin' => $origin], $locale);
                $rest = '';
            } elseif ($rest === ' (infobox Wikipédia)') {
                $parts[] = self::t('event.infobox', [], $locale);
                $rest = '';
            } elseif (preg_match('/^ \(correction sourcée : (.*)\)$/su', $rest, $m)) {
                $parts[] = self::t('event.correction', ['reason' => $m[1]], $locale);
                $lang = 'fr'; // raison rédigée en français
                $rest = '';
            } elseif (str_starts_with($rest, ' (date tirée d\'une période)')) {
                $parts[] = self::t('event.from_period', [], $locale);
                $rest = mb_substr($rest, mb_strlen(' (date tirée d\'une période)'));
            } elseif (preg_match('/^,? (d\'après une phrase d\'ensemble sur les lignes du réseau|au plus tard à la fin de l\'exploitation du réseau)(?=,| \()/u', $rest, $m)) {
                $parts[array_key_last($parts)] .= ($m[0][0] === ',' ? ',' : '').' '.self::t(str_starts_with($m[1], 'd\'après') ? 'event.network_statement' : 'event.network_end', [], $locale);
                $rest = mb_substr($rest, mb_strlen($m[0]));
            } elseif (str_starts_with($rest, ', sur toute la ligne')) {
                $parts[array_key_last($parts)] .= ',';
                $parts[] = self::t('event.on_whole_line', [], $locale);
                $rest = mb_substr($rest, mb_strlen(', sur toute la ligne'));
            } elseif (preg_match('/^ au plus tard avec la ligne (.+?)(?= \(article Wikipédia)/u', $rest, $m)) {
                $parts[] = self::t('event.with_line', ['other' => $m[1]], $locale);
                $rest = mb_substr($rest, mb_strlen($m[0]));
            } elseif (preg_match('/^ \((?:(.+), )?article Wikipédia « (.+) »\)$/u', $rest, $m)) {
                $parts[] = '('.($m[1] !== '' ? $m[1].', ' : '').self::t('event.article', ['article' => $m[2]], $locale).')';
                $rest = '';
            } elseif (preg_match('/^ en (.+?)(?= - |$)/u', $rest, $m) && $label[1] === 'electrification') {
                $parts[] = self::t('event.wd_system', ['system' => self::system($m[1], $locale)], $locale);
                $rest = mb_substr($rest, mb_strlen($m[0]));
            } elseif (preg_match('/^ - section (.+?) [–-] (.+?)(?= - |$)/u', $rest, $m)) {
                $parts[] = '- '.self::t('event.section_suffix', ['from' => $m[1], 'to' => $m[2]], $locale);
                $rest = mb_substr($rest, mb_strlen($m[0]));
            } elseif (str_starts_with($rest, ' - d\'après P576 « dissolution ou abolition »')) {
                $parts[] = '- '.self::t('event.wd_p576', [], $locale);
                $rest = mb_substr($rest, mb_strlen(' - d\'après P576 « dissolution ou abolition »'));
            } elseif (preg_match('/^ - cause : (.+)$/u', $rest, $m)) {
                $parts[] = '- '.self::t('event.wd_cause', ['causes' => $m[1]], $locale);
                $lang = 'fr'; // libellés Wikidata en français
                $rest = '';
            } elseif (preg_match('/^ \(([^()]+)\)(?= - |$)/u', $rest, $m)) {
                // Libellé de l'événement clé Wikidata (P793), en français.
                $parts[] = '('.$m[1].')';
                $lang = 'fr';
                $rest = mb_substr($rest, mb_strlen($m[0]));
            } else {
                return null;
            }
        }

        return $rest === '' ? ['text' => implode(' ', $parts), 'lang' => $lang] : null;
    }

    /**
     * Reste d'une description d'exploitation associative : « NOM, train touristique entre A et
     * B, depuis le 9 mai 1986 (d'après article Wikipédia de l'exploitation) ; aussi nommé « … ».
     * Sources : … ». Fragments fixes traduits (generated.heritage.*), noms inchangés.
     *
     * @return array{0: string, 1: bool} [texte, entièrement reconnu]
     */
    private static function heritageRest(string $rest, string $locale): array
    {
        $h = fn (string $key, array $p = []) => self::t('heritage.'.$key, $p, $locale);
        $kinds = ['train touristique' => 'kind_tourist_train', 'vélorail' => 'kind_velorail', 'musée ferroviaire roulant' => 'kind_museum'];
        $srcs = ['l\'article Wikipédia de l\'exploitation' => 'src_operator_article', 'article Wikipédia de l\'exploitation' => 'src_operator_article',
            'article Wikipédia de la ligne' => 'src_line_article', 'Wikipédia (liste)' => 'src_list', 'Wikipédia (article)' => 'src_article'];
        $src = fn (string $s) => isset($srcs[$s]) ? $h($srcs[$s]) : $s;
        $kindAlt = implode('|', array_map(fn ($k) => preg_quote($k, '/'), array_keys($kinds)));
        if (! preg_match('/^(?<who>.+?), (?<kind>'.$kindAlt.')(?<tail>.*)$/su', $rest, $m)) {
            return [$rest, false];
        }
        $who = (string) preg_replace_callback('/\(exploitant : (.+?)\)/u', fn ($x) => '('.$h('operator', ['name' => $x[1]]).')', $m['who']);
        $out = $who.', '.$h($kinds[$m['kind']]);
        $tail = $m['tail'];
        $date = fn (string $s) => self::date($s, $locale);
        $guard = 0;
        while ($tail !== '' && $guard++ < 10) {
            if (preg_match('/^ entre (.+?) et (.+?)(?=, | ; |\. Sources|$)/u', $tail, $x)) {
                $out .= ' '.$h('between', ['from' => $x[1], 'to' => $x[2]]);
            } elseif (preg_match('/^ du PK ([\d,]+) au PK ([\d,]+)/u', $tail, $x)) {
                $out .= ' '.$h('pk', ['from' => self::num($x[1], $locale), 'to' => self::num($x[2], $locale)]);
            } elseif (preg_match('/^, en activité en (\d{4}) \(date de début non trouvée ; attestation : ((?:[^()]|\([^()]*\))+)\)/u', $tail, $x)) {
                $out .= ', '.$h('attested', ['year' => $x[1], 'by' => $src($x[2])]);
            } elseif (preg_match('/^, depuis (?:le )?(.+?) \(d\'après (.+?)(?:, date de l\'activité (.+?) de la même exploitation)?\)/u', $tail, $x)) {
                $out .= ', '.$h('since', ['date' => $date($x[1])]).' ('.trim(substr($h('according', ['source' => $src($x[2])]), 1, -1))
                    .(($x[3] ?? '') !== '' ? ', '.$h('sibling', ['name' => $x[3]]) : '').')';
            } elseif (preg_match('/^, de (.+?) à (.+?) \(d\'après (.+?)\)/u', $tail, $x)) {
                $out .= ', '.$h('from_to', ['from' => $date($x[1]), 'to' => $date($x[2])]).' '.$h('according', ['source' => $src($x[3])]);
            } elseif (preg_match('/^, (en|le) (.+?) \(d\'après (.+?)\)/u', $tail, $x)) {
                $out .= ', '.$h($x[1] === 'en' ? 'in' : 'on', ['date' => $date($x[2])]).' '.$h('according', ['source' => $src($x[3])]);
            } elseif (preg_match('/^ ; aussi nommé (.+?)(?= ; |\. Sources|$)/u', $tail, $x)) {
                $out .= '; '.$h('alias', ['names' => $x[1]]);
            } elseif (preg_match('/^ ; voie partagée avec (.+?)(?= ; |\. Sources|$)/u', $tail, $x)) {
                $out .= '; '.$h('shared', ['names' => $x[1]]);
            } elseif (preg_match('/^\. Sources : (.+)\.$/u', $tail, $x)) {
                $out .= '. '.$h('sources', ['list' => implode(', ', array_map($src, explode(', ', $x[1])))]).'.';
            } elseif ($tail === '.') {
                $out .= '.';
            } else {
                return [$out.$tail, false];
            }
            $tail = mb_substr($tail, mb_strlen($x[0] ?? '.'));
            unset($x);
        }

        return [$out, $tail === ''];
    }

    // ------------------------------------------------------------------
    // Outils
    // ------------------------------------------------------------------

    /** @return array<string, string> libellé français → clé, du plus long au plus court */
    public static function labels(): array
    {
        if (self::$labels === null) {
            $map = array_flip((array) trans('generated.label', [], 'fr'));
            uksort($map, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));
            self::$labels = $map;
        }

        return self::$labels;
    }

    private static function labelKey(string $fr): ?string
    {
        return self::labels()[trim($fr)] ?? null;
    }

    private static function t(string $key, array $params, string $locale): string
    {
        return (string) trans('generated.'.$key, $params, $locale);
    }

    /** Nombre écrit à la française (« 12,3 », « 1 234,5 ») dans une langue. */
    public static function num(string $fr, string $locale): string
    {
        $n = trim(str_replace(["\u{202F}", "\u{00A0}", ' '], '', $fr));

        return $locale === 'en' ? str_replace(',', '.', $n) : $n;
    }

    /** Système d'électrification Wikidata (« 1500 V continu ») dans une langue. */
    private static function system(string $fr, string $locale): string
    {
        $words = ['en' => ['continu' => 'DC', 'alternatif' => 'AC', 'monophasé' => 'single-phase', 'triphasé' => 'three-phase'],
            'de' => ['continu' => 'Gleichstrom', 'alternatif' => 'Wechselstrom', 'monophasé' => 'Einphasen', 'triphasé' => 'Drehstrom']];

        return strtr($fr, $words[$locale] ?? []);
    }

    /** Date écrite en français (« 9 mai 1986 », « juin 1985 », « 1er avril 1990 », « 1985 ») dans une langue. */
    public static function date(string $fr, string $locale): string
    {
        $months = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
        if (! preg_match('/^(?:(1er|\d{1,2}) )?(?:('.implode('|', $months).') )?(\d{4})$/u', trim($fr), $m)) {
            return $fr;
        }
        $day = $m[1] === '1er' ? '1' : $m[1];
        $month = $m[2] !== '' ? array_search($m[2], $months, true) + 1 : null;
        if ($month === null) {
            return $m[3];
        }
        $name = Carbon::create((int) $m[3], $month, 1)->locale($locale)->translatedFormat('F');

        return $day === '' ? $name.' '.$m[3] : ($locale === 'de' ? $day.'. '.$name.' '.$m[3] : $day.' '.$name.' '.$m[3]);
    }
}

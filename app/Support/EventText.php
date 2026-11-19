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
     * Français : texte stocké, repris par la grammaire des textes générés (FrenchGrammar :
     * « d’Ambert », « du Mans », « 1er décembre ») et les noms de compagnie développés
     * (« Concession : Compagnie du Paris-Orléans (PO) », jamais « Concession à PO »).
     *
     * @return array{text: ?string, lang: ?string} lang : « fr » si tout ou partie reste en français
     */
    public static function describe(?string $source, ?string $description, string $locale, ?string $from = null, ?string $to = null): array
    {
        if ($description === null || trim($description) === '') {
            return ['text' => $description, 'lang' => null];
        }
        $d = trim($description);
        // Méthode de localisation de VFF (« - PK déduits de la liste des gares de la fiche ») : pas un
        // fait de la ligne, retirée du texte affiché (la note renvoie à la fiche lignes-oubliees).
        $d = (string) preg_replace('/ - PK déduits (?:de la liste des gares de la fiche|des autres mentions de la fiche)$/u', '', $d);
        if ($locale === 'fr') {
            // Français : texte stocké (gabarits français), sauf les concessions (compagnie développée).
            $r = preg_match('/^(?:Concession à|Fin de concession de) /u', $d) ? self::special($source, $d, $locale) : null;

            return ['text' => FrenchGrammar::fix($r['text'] ?? $d), 'lang' => null];
        }
        $r = self::special($source, $d, $locale) ?? self::structured($d, $locale, $from, $to);
        // Correction sourcée au libellé rédigé à la main : libellé et raison restent en français, le cadre est traduit.
        if ($r === null && preg_match('/^(.+?) \(correction sourcée : (.*)\)$/su', $d, $m)) {
            $r = ['text' => '« '.$m[1].' » '.self::t('event.correction', ['reason' => $m[2]], $locale), 'lang' => 'fr'];
        }

        return $r ?? ['text' => $description, 'lang' => 'fr'];
    }

    /**
     * Sépare la raison d'une correction sourcée (« … (correction sourcée : RAISON) », texte rédigé
     * à la main, en français) du fait lui-même : la chronologie affiche le fait, la raison va dans
     * sa propre note (prop `correction`), au lieu d'une parenthèse de travail au milieu du texte.
     *
     * @return array{0: string, 1: ?string} [description sans la raison, raison ou null]
     */
    public static function splitCorrection(string $description): array
    {
        if (preg_match('/^(.+?) \(correction sourcée : (.*)\)$/su', trim($description), $m)) {
            $reason = trim($m[2]);

            return [$m[1], $reason === '' ? null : FrenchGrammar::ucfirst($reason)];
        }

        return [$description, null];
    }

    /**
     * Citation (source_quote) dans une langue. Les « citations » générées par les importeurs
     * (« Infobox : concessionnaire1 = PO ; dateconc1 = 1883 », « Wikidata Q… · P1619 (…) =
     * 1875-00-00 - date retenue … », « way 1234 : name=…, gauge=1000 ») sont des relevés de
     * données, rendus en phrases lisibles dans la langue de la page (sans identifiant de
     * propriété ni date impossible) ; une vraie citation de source reste telle quelle (lang = fr
     * hors du français), sans les marques de lien ⟦…⟧ de l'importeur.
     *
     * @return array{text: ?string, lang: ?string}
     */
    public static function quote(?string $source, ?string $quote, string $locale): array
    {
        $q = self::quoteDetails($source, $quote, $locale);

        return ['text' => $q['text'], 'lang' => $q['lang']];
    }

    /**
     * Comme quote(), avec la nature du texte : `data` (relevé de données rédigé par VFF : infobox,
     * Wikidata, objet OpenHistoricalMap) ou `citation` (phrase d'une source, à guillemeter).
     *
     * @return array{text: ?string, lang: ?string, kind: ?string}
     */
    public static function quoteDetails(?string $source, ?string $quote, string $locale): array
    {
        if ($quote === null || trim($quote) === '') {
            return ['text' => $quote, 'lang' => null, 'kind' => null];
        }
        $q = trim($quote);
        if (preg_match('/^Infobox : (.+)$/su', $q, $m)) {
            return ['text' => self::infoboxQuote($m[1], $locale), 'lang' => null, 'kind' => 'data'];
        }
        if (preg_match('/^Wikidata (Q\d+) · (P\d+) \((.*?)\) = (.*?) [-—] date retenue (\S+) \(précision : (jour|mois|année)\)(.*)$/su', $q, $m)) {
            return self::wikidataQuote($m, $quote, $locale);
        }
        if (preg_match('/^way (\S+) : (.*?)(?: \(\+(\d+) autre\(s\) way\(s\) OHM\))?$/su', $q, $m, PREG_UNMATCHED_AS_NULL)) {
            return ['text' => self::ohmQuote((string) $m[2], $m[3] === null ? 0 : (int) $m[3], $locale), 'lang' => null, 'kind' => 'data'];
        }
        // Ligne d'un tableau de fermetures ou de déclassements (RFF, repris par lignes-oubliees) :
        // « Ligne | Section | PK début | PK fin | longueur | IG | [DECL] | date [| remarque] ».
        if ($source === 'lignes-oubliees' && ($row = self::tableRowQuote($q, $locale)) !== null) {
            return ['text' => $row, 'lang' => null, 'kind' => 'data'];
        }
        $q = str_replace(['⟦', '⟧'], '', $q);

        // Nom d'un tracé archeoferroviaire : nom propre.
        return ['text' => $q, 'lang' => $locale === 'fr' || $source === 'archeoferroviaire' ? null : 'fr', 'kind' => 'citation'];
    }

    /** Champs d'infobox (« concessionnaire1 = PO ; dateconc1 = 1883 ») en phrase : « Infobox de l’article Wikipédia (concessionnaire : PO ; début de la concession : 1883) ». */
    private static function infoboxQuote(string $raw, string $locale): string
    {
        $fields = [];
        foreach (preg_split('/\s*;\s*/u', trim($raw)) ?: [] as $pair) {
            if (! preg_match('/^(.+?)\s*=\s*(.*)$/su', $pair, $m)) {
                $fields[] = $pair;

                continue;
            }
            $name = (string) preg_replace('/\d+$|act$/u', '', trim($m[1]));
            $key = match (trim($name)) {
                'concessionnaire' => 'concessionnaire',
                'dateconc' => 'dateconc',
                'finconc' => 'finconc',
                'mise en service', 'date d\'ouverture' => 'opening',
                'fermeture', 'date de fermeture' => 'closure',
                'électrification' => 'electrification',
                'désélectrification' => 'deelectrification',
                'début' => 'start',
                'fin' => 'end',
                'date de disparition' => 'disappearance',
                default => null,
            };
            $value = trim($m[2]);
            $fields[] = $key === null ? trim($m[1]).' = '.$value : self::t('quote.field_'.$key, ['value' => $value], $locale);
        }

        return self::t('quote.infobox', ['fields' => implode($locale === 'fr' ? ' ; ' : '; ', $fields)], $locale);
    }

    /**
     * Relevé Wikidata en phrase : « Wikidata, date d’ouverture officielle : 1875 (d’après Wikipédia
     * en français) ». La valeur est une date à sa précision (jamais « 1875-00-00 »).
     *
     * @param  array<int, string>  $m
     * @return array{text: string, lang: ?string, kind: string}
     */
    private static function wikidataQuote(array $m, string $quote, string $locale): array
    {
        [$pid, $propFr, $value, $precision, $rest] = [$m[2], $m[3], $m[4], ['jour' => 'day', 'mois' => 'month', 'année' => 'year'][$m[6]], $m[7]];
        $known = (array) trans('generated.event', [], 'fr');
        $prop = isset($known['wd_prop_'.$pid]) ? self::t('event.wd_prop_'.$pid, [], $locale) : $propFr;
        $lang = isset($known['wd_prop_'.$pid]) || $locale === 'fr' ? null : 'fr';
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $d)) {
            $value = self::isoDate($d[1].'-'.$d[2].'-'.$d[3], $precision, $locale);
        } elseif ($locale !== 'fr') {
            $lang = 'fr'; // libellé Wikidata en français (« électrification ferroviaire 25 kV 50 Hz »)
        }
        $text = self::t('quote.wikidata', ['prop' => $prop, 'value' => $value], $locale);
        $rest = trim((string) preg_replace('/\s*\(Wikidata, CC0\)\s*$/u', '', $rest));
        $extra = [];
        if (preg_match('/; qualificatifs : (.*?)(?= ; références : |$)/su', $rest, $p)) {
            $extra[] = self::wikidataQualifiers($p[1], $locale);
        }
        if (preg_match('/; références : (.*)$/su', $rest, $p)) {
            if (preg_match('/^importé de Wikipédia en (\p{L}+)$/u', trim($p[1]), $w) && self::t('quote.wiki_lang_'.$w[1], [], $locale) !== 'generated.quote.wiki_lang_'.$w[1]) {
                $extra[] = self::t('quote.wikidata_imported', ['wiki' => self::t('quote.wiki_lang_'.$w[1], [], $locale)], $locale);
            } else {
                $extra[] = self::t('quote.wikidata_references', ['list' => trim($p[1])], $locale);
                $lang = $locale === 'fr' ? null : 'fr';
            }
        }
        if ($rest !== '' && $extra === [] && ! str_starts_with($rest, ';')) {
            return ['text' => $quote, 'lang' => $locale === 'fr' ? null : 'fr', 'kind' => 'data'];
        }

        return ['text' => $text.($extra ? ' ('.implode($locale === 'fr' ? ' ; ' : '; ', $extra).')' : ''), 'lang' => $lang, 'kind' => 'data'];
    }

    /** Qualificatifs Wikidata (« P580 = 1950-01-01 ; P2283 = caténaire ») : propriétés nommées, dates lisibles. */
    private static function wikidataQualifiers(string $raw, string $locale): string
    {
        $out = [];
        foreach (preg_split('/\s*;\s*/u', trim($raw)) ?: [] as $pair) {
            if (! preg_match('/^(P\d+) = (.*)$/u', $pair, $m)) {
                $out[] = $pair;

                continue;
            }
            $value = preg_match('/^\d{4}-\d{2}-\d{2}$/', $m[2]) ? self::isoDate($m[2], str_ends_with($m[2], '-01-01') ? 'year' : 'day', $locale) : $m[2];
            $key = 'quote.qualifier_'.$m[1];
            $label = self::t($key, ['value' => $value], $locale);
            $out[] = $label === 'generated.'.$key ? $m[1].' = '.$value : $label;
        }

        return implode($locale === 'fr' ? ' ; ' : '; ', $out);
    }

    /**
     * Objet OpenHistoricalMap en phrase : « Tracé OpenHistoricalMap « Chemins de fer de Provence » :
     * voie métrique, ligne secondaire, ouvert en 1892 (et 38 autres tracés) ». Seules les étiquettes
     * qui décrivent la voie sont reprises (nom, écartement, état, usage, électrification, dates) ;
     * sources et identifiants restent dans le lien de la note.
     */
    private static function ohmQuote(string $tags, int $more, string $locale): string
    {
        preg_match_all('/(?:^|, )([a-z_:]+)=(.*?)(?=, [a-z_:]+=|$)/su', $tags, $all, PREG_SET_ORDER);
        $t = [];
        foreach ($all as $x) {
            $t[$x[1]] = trim($x[2]);
        }
        $details = [];
        $gauge = $t['gauge'] ?? null;
        if ($gauge !== null && ctype_digit($gauge)) {
            $details[] = match ((int) $gauge) {
                1435 => self::t('quote.ohm_gauge_standard', [], $locale),
                1000 => self::t('quote.ohm_gauge_metre', [], $locale),
                default => self::t('quote.ohm_gauge', ['mm' => self::thousands((int) $gauge, $locale)], $locale),
            };
        }
        foreach (['railway' => ['disused', 'abandoned', 'razed', 'construction', 'preserved', 'tram', 'light_rail', 'funicular'],
            'usage' => ['main', 'branch', 'industrial', 'military', 'tourism'],
            'electrified' => ['no', 'contact_line', 'rail']] as $tag => $values) {
            if (isset($t[$tag]) && in_array($t[$tag], $values, true)) {
                $details[] = self::t('quote.ohm_'.$tag.'_'.$t[$tag], [], $locale);
            }
        }
        $date = function (?string $v) use ($locale): ?string {
            $v = trim((string) preg_replace('/\s*\(.*\)$/u', '', (string) $v));

            return match (true) {
                (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) => self::isoDate($v, 'day', $locale),
                (bool) preg_match('/^\d{4}-\d{2}$/', $v) => self::isoDate($v.'-01', 'month', $locale),
                (bool) preg_match('/^\d{4}$/', $v) => $v,
                default => null,
            };
        };
        if ($s = $date($t['start_date'] ?? null)) {
            $details[] = self::t('quote.ohm_start', ['date' => $s], $locale);
        }
        if ($e = $date($t['end_date'] ?? null)) {
            $details[] = self::t('quote.ohm_end', ['date' => $e], $locale);
        }
        $head = self::t('quote.ohm_way', [], $locale).(isset($t['name']) && $t['name'] !== '' ? ' '.self::t('quote.quoted', ['text' => $t['name']], $locale) : '');
        $text = $details ? self::t('quote.list', ['head' => $head, 'details' => implode(', ', $details)], $locale) : $head;

        return $text.($more > 0 ? ' '.trans_choice('generated.quote.ohm_more', $more, ['count' => $more], $locale) : '');
    }

    /** Ligne de tableau RFF (lignes-oubliees) en phrase : « Tableau RFF : section A - B, PK 268+588 à 270+037 (1,449 km), 17 octobre 1994 ». */
    private static function tableRowQuote(string $q, string $locale): ?string
    {
        $cells = array_map('trim', explode('|', $q));
        if (count($cells) < 6 || ! preg_match('/^\d+,\d{3}$/', $cells[2]) || ! preg_match('/^\d+,\d{3}$/', $cells[3])) {
            return null;
        }
        $date = null;
        foreach (array_reverse($cells) as $c) {
            if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $c, $d)) {
                $date = self::isoDate($d[3].'-'.$d[2].'-'.$d[1], 'day', $locale);
                break;
            }
        }
        $pk = fn (string $v) => str_replace(',', '+', $v);
        $km = preg_match('/^\d+,\d+$/', $cells[4]) ? self::num($cells[4], $locale) : null;

        return self::t('quote.lo_table', [
            'section' => self::dashes($cells[1]), 'from' => $pk($cells[2]), 'to' => $pk($cells[3]),
            'km' => $km ?? '?', 'date' => $date ?? '?',
        ], $locale);
    }

    /** Tiret moyen d'une source remplacé par un tiret simple (charte VFF). */
    private static function dashes(string $text): string
    {
        return (string) preg_replace('/\s*[–—]\s*/u', ' - ', $text);
    }

    /** Noms entre guillemets français (« A », « B ») repris avec les guillemets de la langue (“A”, „A“). */
    private static function requote(string $names, string $locale): string
    {
        return (string) preg_replace_callback('/« (.+?) »/u', fn (array $q) => self::t('quote.quoted', ['text' => $q[1]], $locale), $names);
    }

    /** Date ISO (AAAA-MM-JJ) à sa précision : « 1875 », « avril 1875 », « 1er avril 1875 », « 1 April 1875 », « 1. April 1875 ». */
    public static function isoDate(string $iso, string $precision, string $locale): string
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $iso, $m) || $precision === 'year' || (int) $m[2] === 0) {
            return substr($iso, 0, 4);
        }
        $month = Carbon::create((int) $m[1], (int) $m[2], 1)->locale($locale)->translatedFormat('F');
        if ($precision === 'month' || (int) $m[3] === 0) {
            return $month.' '.$m[1];
        }
        $day = (int) $m[3];

        return match ($locale) {
            'fr' => ($day === 1 ? '1er' : $day).' '.$month.' '.$m[1],
            'de' => $day.'. '.$month.' '.$m[1],
            default => $day.' '.$month.' '.$m[1],
        };
    }

    /** Entier avec séparateur des milliers de la langue (« 1 435 », « 1,435 », « 1.435 »). */
    public static function thousands(int $n, string $locale): string
    {
        return (new \NumberFormatter($locale === 'en' ? 'en_GB' : $locale, \NumberFormatter::DECIMAL))->format($n);
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
        if (preg_match('/^(Concession à|Fin de concession de) (.+) \(infobox Wikipédia\)$/u', $d, $m)) {
            [$company, $none] = self::company($m[2]);
            $key = ($m[1] === 'Concession à' ? 'concession_to' : 'concession_end_of').($none ? '_none' : '');

            return ['text' => self::t('event.'.$key, ['company' => $company], $locale), 'lang' => null];
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
        // Fiche lignes-oubliees : rubrique, section et dates de la fiche = citation de la source
        // (en français, le texte stocké se lit déjà comme l'extrait de la fiche).
        if ($source === 'lignes-oubliees' && $locale !== 'fr') {
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
                $out .= '; '.$h('alias', ['names' => self::requote($x[1], $locale)]);
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

    /**
     * Compagnies abrégées des infobox (« PO », « État (Non concédée) », « • RFF ») : nom développé
     * (noms propres, identiques dans les trois langues) et ligne exploitée sans concession.
     */
    private const COMPANIES = [
        'po' => 'Compagnie du Paris-Orléans (PO)',
        'plm' => 'Compagnie du Paris-Lyon-Méditerranée (PLM)',
        'ch. de fer plm' => 'Compagnie du Paris-Lyon-Méditerranée (PLM)',
        'midi' => 'Compagnie du Midi',
        'du midi' => 'Compagnie du Midi',
        'nord' => 'Compagnie du Nord',
        'ch. de fer du nord' => 'Compagnie du Nord',
        'est' => 'Compagnie de l’Est',
        'ch. de fer de l\'est' => 'Compagnie de l’Est',
        'cie de l\'est' => 'Compagnie de l’Est',
        'ouest' => 'Compagnie de l’Ouest',
        'état' => 'Administration des chemins de fer de l’État',
        'etat' => 'Administration des chemins de fer de l’État',
        'al' => 'Chemins de fer d’Alsace et de Lorraine (AL)',
        'rff' => 'Réseau ferré de France (RFF)',
        'cfd' => 'Compagnie de chemins de fer départementaux (CFD)',
        'charentes' => 'Compagnie des Charentes',
        'grand-central' => 'Compagnie du Grand-Central',
    ];

    /** @return array{0: string, 1: bool} [nom développé, exploitation sans concession] */
    public static function company(string $raw): array
    {
        $name = trim((string) preg_replace('/^[•·*]\s*/u', '', trim($raw)));
        $none = (bool) preg_match('/\s*[(\[]\s*non concédée?s?\s*[)\]]$/iu', $name, $m);
        if ($none) {
            $name = trim(mb_substr($name, 0, mb_strlen($name) - mb_strlen($m[0])));
        }

        return [self::COMPANIES[mb_strtolower($name)] ?? $name, $none];
    }

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

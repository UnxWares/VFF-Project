<?php

namespace App\Services\StationNames;

use Illuminate\Support\Str;

/**
 * Renommages d'une gare lus dans le wikitexte de son article Wikipédia, de façon prudente : seules les
 * phrases qui disent explicitement « renommée », « rebaptisée », « prend le nom de », « change de nom »,
 * « devient » suivi d'un nom entre guillemets français, et dont l'année est écrite, donnent un renommage.
 *
 * Écartés : le sujet qui n'est pas la gare (rue, commune, compagnie, ligne, tramway…), les projets, demandes
 * et vœux (« demande que la halte soit renommée »), une phrase sans année, un nom qui n'en est pas un, un
 * ancien et un nouveau nom qui ne diffèrent que par l'écriture.
 *
 * Ce que la phrase ne dit pas n'est pas deviné : l'ancien nom reste inconnu sauf s'il figure dans la phrase
 * (« « X » est renommée « Y » »), s'il est annoncé comme nom d'origine (« d'abord baptisée « X » ») ou s'il est le
 * nom pris juste avant dans la même phrase (« devenu « X » en 1992, il a depuis été renommé « Y » »).
 *
 * Licence : Wikipédia est sous CC BY-SA ; seuls les noms et les années sont repris, avec une citation courte de
 * la phrase et le permalien de la révision lue.
 */
final class RenameExtractor
{
    /** Verbes de renommage suivis d'un nom entre guillemets. */
    private const VERB = '(?:renomm(?:é|ée|és|ées|er)|rebaptis(?:é|ée|és|ées|er)|prend(?:ra)?\s+(?:alors\s+|ensuite\s+|officiellement\s+|désormais\s+|également\s+)?(?:le\s+nom|la\s+dénomination|l\'appellation|le\s+titre)(?:\s+(?:officiel|germanisé|allemand))?\s+(?:de|d\')|change\s+de\s+nom(?:\s+pour|\s+et\s+devient)?|porte\s+(?:alors\s+|désormais\s+|dès\s+lors\s+)?le\s+nom(?:\s+(?:germanisé|allemand))?\s+(?:de|d\')|reçoit\s+le\s+nom\s+(?:de|d\')|adopte\s+le\s+nom(?:\s+de|\s+d\')?|se\s+nomme\s+désormais|s\'appelle\s+désormais|devient|devenu(?:e|s|es)?)';

    /** Mots du sujet qui disent que le renommage n'est pas celui de la gare. */
    private const NOT_STATION = '/\b(rue|avenue|boulevard|place|quai|pont|commune|village|ville|compagnie|société|réseau|ligne|lignes|tramway|métro|lycée|hôtel|musée|église|quartier|rame|locomotive|train|tgv|viaduc|tunnel|pôle|centre|parking|parvis|département|syndicat|association|région|province|arrondissement|canton|lieu-dit|hameau|écluse|port|route|chemin|usine|entreprise|groupe|filiale|marque|service|offre|projet|concession|théâtre|bâtiment|bâtiments|promenade|salle|restaurant|café|bar|hall|édifice|maison|immeuble|terrain|zone|espace|site|locaux|magasin|halle|coulée|jardin|piste|voie|marque)\b/iu';

    /** Verbes de demande ou de projet : le renommage n'a pas eu lieu (ou n'est pas affirmé). */
    private const NOT_DONE = '/\b(demand(?:e|é|ent)\s+(?:que|de|à)|vœu|vœux|voeu|propos(?:e|é|ition)|souhait\w*|envisag\w*|devrait|devraient|devait|devaient|devra|pourrait|serait|serions|prévu|prévoit|réclam\w*|suggér\w*|refus\w*|rejet\w*)\b/iu';

    private const MONTHS = ['janvier' => 1, 'février' => 2, 'fevrier' => 2, 'mars' => 3, 'avril' => 4, 'mai' => 5, 'juin' => 6, 'juillet' => 7,
        'août' => 8, 'aout' => 8, 'septembre' => 9, 'octobre' => 10, 'novembre' => 11, 'décembre' => 12, 'decembre' => 12];

    /**
     * @return list<array{old: ?string, new: string, year: int, date: string, precision: string, initial: bool, quote: string, to_year: ?int, strong: bool}>
     */
    public static function extract(string $wikitext): array
    {
        /** @var list<array{old: ?string, new: string, year: int, date: string, precision: string, initial: bool, quote: string, to_year: ?int, strong: bool}> $events */
        $events = [];
        $initial = null;
        foreach (self::sentences(self::clean($wikitext)) as $sentence) {
            if ($initial === null) {
                $initial = self::initialName($sentence);
            }
            $found = self::inSentence($sentence);
            foreach ($found as $i => $e) {
                if ($e['old'] !== null && $initial !== null && self::key($e['old']) === self::key($initial)) {
                    $e['initial'] = true;
                }
                if ($e['old'] === null && $i > 0) {
                    $e['old'] = $found[$i - 1]['new'];
                }
                if ($e['old'] === null && $events === [] && $initial !== null && self::key($initial) !== self::key($e['new'])) {
                    $e['old'] = $initial;
                    $e['initial'] = true;
                }
                if (($e['old'] !== null && self::key($e['old']) === self::key($e['new'])) || $e['year'] === null) {
                    continue;
                }
                $events[] = $e;
            }
        }

        $seen = [];
        $unique = [];
        foreach ($events as $e) {
            $k = self::key((string) $e['old']).'|'.self::key($e['new']).'|'.$e['year'];
            if (! isset($seen[$k])) {
                $seen[$k] = true;
                $unique[] = $e;
            }
        }

        return $unique;
    }

    /** Wikitexte réduit au texte : références, modèles, liens, mises en forme retirés. */
    public static function clean(string $wikitext): string
    {
        $t = preg_replace(['/<!--.*?-->/su', '/<ref[^>]*\/>/u', '/<ref[^>]*>.*?<\/ref>/su'], '', $wikitext) ?? $wikitext;
        $t = preg_replace('/\{\{\s*(?:[Dd]ate|[Dd]ate-)\s*\|\s*(\d{1,2}|1er)\s*\|\s*([^|}]+?)\s*\|\s*(\d{4})[^}]*\}\}/u', '$1 $2 $3', $t) ?? $t;
        $t = preg_replace('/\{\{\s*(?:[Dd]ate-?)\s*\|\s*([^|}]+?)\s*(?:\|[^}]*)?\}\}/u', '$1', $t) ?? $t;
        $t = preg_replace('/\{\{\s*(?:[Nn]obr|[Nn]owrap|[Ll]ang\|[a-z-]+|[Ll]angue\|[a-z-]+|[Gg]uil)\s*\|\s*([^|}]*)[^}]*\}\}/u', '$1', $t) ?? $t;
        // Modèles imbriqués : les plus internes d'abord (infobox, sfn, citations…).
        for ($i = 0; $i < 6 && preg_match('/\{\{[^{}]*\}\}/u', $t) === 1; $i++) {
            $t = preg_replace('/\{\{[^{}]*\}\}/u', '', $t) ?? $t;
        }
        $t = preg_replace(['/\[\[(?:Fichier|File|Image|Catégorie|Category):[^\]]*\]\]/iu', '/\[\[(?:[^\]|]*\|)?([^\]|]*)\]\]/u', '/\[https?:[^\s\]]+\s*([^\]]*)\]/u'], ['', '$1', '$1'], $t) ?? $t;
        $t = preg_replace(["/'{2,}/", '/<[^>]+>/u', '/&nbsp;|\x{00A0}|\x{202F}/u', '/^=+.*?=+\s*$/mu', '/^[|!].*$/mu'], ['', '', ' ', '', ''], $t) ?? $t;
        $t = preg_replace('/«\s+/u', '«', $t) ?? $t;
        $t = preg_replace('/\s+»/u', '»', $t) ?? $t;

        return trim(preg_replace('/[ \t]+/u', ' ', $t) ?? $t);
    }

    /** @return list<string> */
    private static function sentences(string $text): array
    {
        $out = [];
        foreach (preg_split('/\R+/u', $text) ?: [] as $line) {
            foreach (preg_split('/(?<=[.!?])\s+(?=[A-ZÉÈÀÎÂ«"])/u', $line) ?: [] as $s) {
                if (mb_strlen(trim($s)) > 20) {
                    $out[] = trim($s);
                }
            }
        }

        return $out;
    }

    /** Nom d'origine annoncé (« d'abord baptisée « X » », « ouverte sous le nom de « X » »). */
    private static function initialName(string $s): ?string
    {
        $marker = '(?:d\'abord|initialement|à\s+l\'origine|primitivement|originellement|à\s+son\s+ouverture|lors\s+de\s+son\s+ouverture)';
        if (preg_match('/'.$marker.'[^.«»]{0,80}?(?:(?:baptis|nomm|dénomm|appel|intitul)\w*\s+(?:alors\s+)?|le\s+nom\s+(?:de\s+|d\'))«([^«»]{2,70})»/iu', $s, $m) === 1
            || preg_match('/(?:baptis|nomm|dénomm|appel)\w*\s+«([^«»]{2,70})»[^.«»]{0,60}(?:mise?\s+en\s+service|ouverte?|inaugurée?)/iu', $s, $m) === 1
            || preg_match('/(?:dénomm|nomm|appel)\w*\s+([A-ZÉÈÀÂÎÔÛÇ][^,;.«»]{1,50}?)\s+(?:fut|a\s+été|est|était)\s+(?:ouverte?|mise?\s+en\s+service|inaugurée?)/u', $s, $m) === 1
            || preg_match('/(?:ouverte?|mise?\s+en\s+service|inaugurée?)\s+sous\s+(?:le\s+nom|l\'appellation|la\s+dénomination)\s+(?:de\s+|d\')?«([^«»]{2,70})»/iu', $s, $m) === 1) {
            return self::name($m[1]);
        }

        return null;
    }

    /**
     * @return list<array{old: ?string, new: string, year: ?int, date: string, precision: string, initial: bool, quote: string, to_year: ?int, strong: bool}>
     */
    private static function inSentence(string $s): array
    {
        if (preg_match(self::NOT_DONE, $s) === 1 || preg_match('/confondre|homonym/iu', $s) === 1 || preg_match('/\b(gare|station|halte|arrêt|elle|il|établissement|embarcadère|devenue?|renomm\w+|rebaptis\w+)\b/iu', $s) !== 1) {
            return [];
        }
        $former = self::formerName($s);
        if ($former !== []) {
            return $former;
        }
        $chain = self::chain($s);
        if ($chain !== []) {
            return $chain;
        }
        $matches = [];
        $quoted = '/\b'.self::VERB.'\s+(?:alors\s+|ensuite\s+|finalement\s+|officiellement\s+|en\s+|sous\s+le\s+nom\s+(?:de\s+|d\')?|pour\s+)?«([^«»]{2,70})»(?:\s+en\s+«([^«»]{2,70})»)?/iu';
        // « « X » sera dénommée « Y » » : l'ancien nom est cité juste avant.
        $named = '/(?<=»)\s*(?:est|sera|a\s+été|fut)\s+(?:alors\s+|ensuite\s+)?(?i:dénomm(?:é|ée)|nomm(?:é|ée)|appel(?:é|ée))\s+«([^«»]{2,70})»/u';
        $plain = '/\b(?i:renomm(?:é|ée|és|ées|er)|rebaptis(?:é|ée|és|ées|er)|prend(?:ra)?\s+(?:alors\s+|ensuite\s+|officiellement\s+)?(?:le\s+nom|la\s+dénomination)\s+(?:de\s+|d\')|change\s+de\s+nom\s+pour)\s+'
            .'(?:(?i:par\s+la\s+suite|plus\s+tard|alors|ensuite|finalement|officiellement|en|sous\s+le\s+nom\s+(?:de|d\'))\s+)?'
            .'((?:(?i:la\s+)?(?i:gare|station|halte|arrêt)\s+(?i:de\s+la\s+|de\s+l\'|de\s+|d\'|des\s+))?[A-ZÉÈÀÂÎÔÛÇ][^,;.:()«»]{1,58}?)'
            .'(?=\s+(?i:en|le|vers|lors|par|pour|afin|à|au|aux|car|avec|puis|depuis|sous|dans|du\s+fait|suite|après|avant|ce|qui|dès|jusqu|et|où)\b|\s*[,;.:]|\s*$)/u';
        if (preg_match_all($quoted, $s, $all, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) > 0) {
            foreach ($all as $m) {
                $matches[] = ['pos' => (int) $m[0][1], 'end' => (int) $m[0][1] + strlen($m[0][0]), 'a' => $m[1][0], 'b' => $m[2][0] ?? null, 'before_quote' => false, 'text' => $m[0][0]];
            }
        }
        if (preg_match_all($named, $s, $all, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) > 0) {
            foreach ($all as $m) {
                $matches[] = ['pos' => (int) $m[0][1], 'end' => (int) $m[0][1] + strlen($m[0][0]), 'a' => $m[1][0], 'b' => null, 'before_quote' => true, 'text' => $m[0][0]];
            }
        }
        if (preg_match_all($plain, $s, $all, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) > 0) {
            foreach ($all as $m) {
                $pos = (int) $m[0][1];
                $end = $pos + strlen($m[0][0]);
                if (array_filter($matches, fn (array $x): bool => $pos < $x['end'] && $end > $x['pos']) === []) {
                    $matches[] = ['pos' => $pos, 'end' => $end, 'a' => $m[1][0], 'b' => null, 'before_quote' => false, 'text' => $m[0][0]];
                }
            }
        }
        usort($matches, fn (array $x, array $y): int => $x['pos'] <=> $y['pos']);

        $out = [];
        $from = 0;
        foreach ($matches as $k => $m) {
            $before = mb_strcut($s, max(0, $m['pos'] - 90), min(90, $m['pos']));
            $subject = mb_strcut($s, max(0, $from), max(0, $m['pos'] - $from));
            $subjectText = preg_replace('/«[^«»]*»/u', '', $subject) ?? $subject;
            $oldQuote = preg_match('/«([^«»]{2,70})»\s*(?:est(?:\s+alors|\s+ensuite)?|a\s+été|sera|fut|avant\s+d\'être|avant\s+de\s+devenir)?\s*$/u', $before, $o) === 1
                || preg_match('/\b(?:dite|dénommée|nommée|appelée)\s+([A-ZÉÈÀÂÎÔÛÇ][^,;.«»]{1,50}?),?\s+(?:qui\s+)?(?:est|a\s+été|fut|sera)?\s*$/u', $before, $o) === 1
                || preg_match('/\b(?:gare|station|halte)\s+(?:de\s+la\s+|de\s+l\'|de\s+|d\'|du\s+|des\s+)([A-ZÉÈÀÂÎÔÛÇ][^,;.«»]{1,50}?)\s+(?:est|a\s+été|fut|sera)\s*$/u', $before, $o) === 1 ? self::name($o[1]) : null;
            if (! $m['before_quote'] && preg_match(self::NOT_STATION, mb_strcut($subject, -80)) === 1 && $oldQuote === null) {
                $from = $m['end'];

                continue;
            }
            // Le sujet est la gare : elle est nommée avant le verbe, ou le nom pris commence par « gare de », ou l'ancien nom est cité.
            if (! $m['before_quote'] && $oldQuote === null && ($m['b'] ?? '') === ''
                && preg_match('/\b(gare|station|halte|arrêt|point\s+d\'arrêt|elle)\b/iu', $subjectText) !== 1
                && (preg_match('/^\s*(?:la\s+)?(?:gare|station|halte)\b/iu', $m['a']) !== 1 || preg_match('/\b(?:cette|celle|cet|ce)\b/iu', $subjectText) === 1)) {
                $from = $m['end'];

                continue;
            }
            if ($m['before_quote']) {
                // « « X » sera dénommée « Y » » : le nom cité est le nouveau, l'ancien est la citation qui précède.
                $old = $oldQuote;
                $new = self::name($m['a']);
            } elseif (($m['b'] ?? '') !== '') {
                $old = self::name($m['a']);
                $new = self::name($m['b']);
            } else {
                $old = $oldQuote;
                $new = self::name($m['a']);
            }
            if ($new === null || ($m['before_quote'] && $old === null)) {
                $from = $m['end'];

                continue;
            }
            $gap = (int) ($matches[$k + 1]['pos'] ?? strlen($s)) - $m['end'];
            $after = mb_strcut($s, $m['end'], max(0, min(120, $gap)));
            $clause = preg_split('/[,;]|\bmais\b|\bpuis\b|\bavant\b|\bet\b/u', $after)[0] ?? '';
            $dateAfter = self::date($clause) ?? (preg_match('/\b(?:du|depuis\s+le|depuis|à\s+partir\s+(?:du|de))\s+(?:le\s+)?(\d{1,2}|1er)?\s*\p{L}*\s*\d{4}/u', $after) === 1 ? self::date($after) : null);
            $date = $dateAfter ?? self::dateBefore($subject);
            $range = self::range($after);
            $consumed = $date !== null && $date === $dateAfter ? $m['end'] + $date['end'] : $m['end'];
            $out[] = ['old' => $old, 'new' => $new, 'year' => $date['year'] ?? null, 'date' => $date['date'] ?? '', 'precision' => $date['precision'] ?? 'year',
                'initial' => false, 'quote' => self::quote($s, $m['pos'], $m['end']), 'to_year' => $range,
                'strong' => preg_match('/renomm|rebaptis|change\s+de\s+nom/iu', $m['text']) === 1];
            $from = $consumed;
        }

        return $out;
    }

    /** Date écrite juste avant le verbe (« En 1900, elle est renommée »), pas celle d'un autre fait de la phrase. */
    private static function dateBefore(string $subject): ?array
    {
        $d = self::date($subject);
        if ($d === null || preg_match('/par\s+la\s+suite|plus\s+tard|ultérieurement|depuis/iu', $subject) === 1) {
            return null;
        }

        return strlen($subject) - $d['end'] <= 45 ? $d : null;
    }

    /**
     * « Elle portait antérieurement le nom de « X », le changement de nom étant survenu en 1911 » : l'ancien nom et
     * l'année, le nouveau nom n'étant pas dit (c'est celui de la fiche).
     *
     * @return list<array{old: ?string, new: string, year: ?int, date: string, precision: string, initial: bool, quote: string, to_year: ?int, strong: bool}>
     */
    private static function formerName(string $s): array
    {
        if (preg_match('/\bport(?:ait|a)\s+(?:antérieurement|auparavant|précédemment|jusqu\'alors)\s+le\s+nom\s+(?:de\s+|d\')(?:«([^«»]{2,70})»|"([^"]{2,70})"|([A-ZÉÈÀÂÎÔÛÇ][^,;.«»]{1,50}?)(?=\s*[,;.]|\s+(?:en|et|jusqu)\b))/u', $s, $m, PREG_OFFSET_CAPTURE) !== 1
            || preg_match(self::NOT_DONE, $s) === 1) {
            return [];
        }
        $old = self::name((string) ($m[1][0] ?: $m[2][0] ?: ($m[3][0] ?? '')));
        $date = self::date(mb_strcut($s, (int) $m[0][1]));
        if ($old === null || $date === null) {
            return [];
        }

        return [['old' => $old, 'new' => '', 'year' => $date['year'], 'date' => $date['date'], 'precision' => $date['precision'], 'initial' => false,
            'quote' => self::quote($s, (int) $m[0][1], (int) $m[0][1] + strlen($m[0][0])), 'to_year' => null, 'strong' => true]];
    }

    /**
     * « D'abord nommée Grigny, puis Grigny (Essonne) dans les années 1970, puis enfin Grigny-Val-de-Seine en 1974 » :
     * une suite de noms, chacun avec sa date écrite ; un nom sans date n'est suivi d'aucun renommage.
     *
     * @return list<array{old: ?string, new: string, year: ?int, date: string, precision: string, initial: bool, quote: string, to_year: ?int, strong: bool}>
     */
    private static function chain(string $s): array
    {
        if (preg_match('/\b(?:d\'abord|initialement|à\s+l\'origine|primitivement)\s+(?:(?i:nomm|dénomm|appel|baptis)\w+\s+)((?:«[^«»]+»|[A-ZÉÈÀÂÎÔÛÇ])[^;.]*\bpuis\b[^;.]*)/u', $s, $m, PREG_OFFSET_CAPTURE) !== 1
            || preg_match(self::NOT_DONE, $s) === 1) {
            return [];
        }
        $names = [];
        foreach (preg_split('/,?\s+puis\s+(?:(?i:enfin|ensuite|de\s+nouveau)\s+)?/u', $m[1][0]) ?: [] as $seg) {
            $seg = trim(str_replace(['«', '»'], '', $seg));
            if (preg_match('/^([A-ZÉÈÀÂÎÔÛÇ][^,;]*?)(?=\s+(?i:dans\s+les\s+années|en|vers|lors|à|au|le|depuis)\b|\s*,|\s*$)(.*)$/u', $seg, $p) !== 1) {
                return [];
            }
            $name = self::name($p[1]);
            if ($name === null) {
                return [];
            }
            $names[] = ['name' => $name, 'date' => self::date($p[2])];
        }
        $out = [];
        for ($i = 1; $i < count($names); $i++) {
            $d = $names[$i]['date'];
            if ($d !== null && self::key($names[$i - 1]['name']) !== self::key($names[$i]['name'])) {
                $out[] = ['old' => $names[$i - 1]['name'], 'new' => $names[$i]['name'], 'year' => $d['year'], 'date' => $d['date'], 'precision' => $d['precision'],
                    'initial' => $i === 1, 'quote' => self::quote($s, (int) $m[0][1], (int) $m[0][1] + strlen($m[0][0])), 'to_year' => null, 'strong' => true];
            }
        }

        return $out;
    }

    /** Fin d'une période écrite après le nom (« du 1er juillet 1940 au 3 mars 1945 », « jusqu'en 1918 »). */
    private static function range(string $after): ?int
    {
        if (preg_match('/jusqu\'à\s+la\s+Libération\s*\((?:en\s+)?(19\d\d)/iu', $after, $l) === 1) {
            return (int) $l[1];
        }

        if (preg_match('/\b(?:jusqu\'(?:en|à|au)|puis\s+en|et\s+jusqu\'en|\bau|à)\s+(?:le\s+)?(?:(?:\d{1,2}|1er)\s+\p{L}+\s+)?(1[5-9]\d\d|20[0-2]\d)\b/iu', $after, $m) === 1
            && preg_match('/\b(?:du|de|depuis|entre|pendant)\b/iu', $after) === 1) {
            return (int) $m[1];
        }

        return null;
    }

    /**
     * Première date écrite dans le texte (jour, mois ou année), null sans année ; `end` : fin de la date dans le texte.
     *
     * @return ?array{year: int, date: string, precision: string, end: int, length: int}
     */
    public static function date(string $t): ?array
    {
        $d = self::dateAny($t);
        if ($d === null) {
            return null;
        }
        // « après 2001 », « vers 1880 », « avant 1950 » : une borne ou une approximation n'est pas une date.
        $head = mb_strcut($t, 0, max(0, $d['end'] - $d['length']));

        return preg_match('/\b(?:après|avant|vers|environ|dès|jusqu\'en|jusqu\'à|au\s+plus\s+tard)\s*(?:le\s+|en\s+|fin\s+|la\s+fin\s+)?$/iu', mb_strcut($head, max(0, strlen($head) - 25))) === 1 ? null : $d;
    }

    /** @return ?array{year: int, date: string, precision: string, end: int, length: int} */
    private static function dateAny(string $t): ?array
    {
        $months = implode('|', array_keys(self::MONTHS));
        $flags = PREG_OFFSET_CAPTURE;
        if (preg_match('/\b(\d{1,2}|1er)\s+('.$months.')\s+(1[5-9]\d\d|20[0-2]\d)\b/iu', $t, $m, $flags) === 1) {
            $mo = self::MONTHS[mb_strtolower($m[2][0])];

            return ['year' => (int) $m[3][0], 'date' => sprintf('%04d-%02d-%02d', $m[3][0], $mo, (int) $m[1][0]), 'precision' => 'day', 'end' => $m[0][1] + strlen($m[0][0]), 'length' => strlen($m[0][0])];
        }
        if (preg_match('/\b('.$months.')\s+(1[5-9]\d\d|20[0-2]\d)\b/iu', $t, $m, $flags) === 1) {
            return ['year' => (int) $m[2][0], 'date' => sprintf('%04d-%02d', $m[2][0], self::MONTHS[mb_strtolower($m[1][0])]), 'precision' => 'month', 'end' => $m[0][1] + strlen($m[0][0]), 'length' => strlen($m[0][0])];
        }
        if (preg_match('/années\s+((?:1[5-9]|20)\d)0\b/iu', $t, $m, $flags) === 1) {
            return ['year' => (int) ($m[1][0].'0'), 'date' => $m[1][0].'0', 'precision' => 'decade', 'end' => $m[0][1] + strlen($m[0][0]), 'length' => strlen($m[0][0])];
        }
        if (preg_match('/\b(1[5-9]\d\d|20[0-2]\d)\b/u', $t, $m, $flags) === 1) {
            return ['year' => (int) $m[1][0], 'date' => $m[1][0], 'precision' => 'year', 'end' => $m[0][1] + strlen($m[0][0]), 'length' => strlen($m[0][0])];
        }

        return null;
    }

    /** Nom écrit entre guillemets, sans « gare de » ; null si ce n'est pas un nom. */
    private static function name(string $raw): ?string
    {
        $n = trim($raw, " \t,.;:");
        $n = preg_replace('/^(?:la\s+|le\s+)?(?:gare|station|halte|arrêt|point\s+d\'arrêt)\s+(?:de\s+la\s+|de\s+l\'|de\s+|d\'|des\s+)/iu', '', $n) ?? $n;
        if ($n === '' || mb_strlen($n) > 60 || preg_match('/^[A-ZÉÈÀÂÎÔÛÇ0-9]/u', $n) !== 1
            || preg_match('/\b(est|sont|dans|pour|avec|qui|que|sur|sera|ont)\b/iu', $n) === 1) {
            return null;
        }

        return $n;
    }

    private static function quote(string $s, int $from, int $to): string
    {
        return trim(mb_strcut($s, max(0, $from - 60), $to - $from + 100));
    }

    /** Clé de comparaison d'un nom : sans accents, sans « gare », « de », « la »… ni ponctuation. */
    public static function key(string $name): string
    {
        return (string) preg_replace(['/\b(gare|station|halte|de|du|des|la|le|les|l|d|st|saint|sainte|ste)\b/u', '/[^a-z0-9]+/'], ['', ''],
            Str::ascii(mb_strtolower($name)));
    }
}

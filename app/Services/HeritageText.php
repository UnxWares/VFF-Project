<?php

namespace App\Services;

/**
 * Analyse de textes décrivant les EXPLOITATIONS ASSOCIATIVES de lignes
 * (trains touristiques, vélorails, musées roulants) : type d'exploitation,
 * dates de début / fin, noms comparables entre sources, tableaux de la
 * « Liste des chemins de fer touristiques de France » (Wikipédia FR) et
 * rubrique « État actuel » des fiches lignes-oubliees.
 *
 * Fonctions PURES (aucun accès réseau ni base) : testées par
 * tests/Unit/HeritageTextTest.php, utilisées par vff:import-heritage-lines.
 */
class HeritageText
{
    public const KINDS = ['tourist_train', 'velorail', 'museum'];

    /** Années plausibles d'une exploitation associative (le premier chemin de fer touristique français date de 1957). */
    public const MIN_YEAR = 1950;

    private const MONTHS = [
        'janvier' => 1, 'fevrier' => 2, 'mars' => 3, 'avril' => 4, 'mai' => 5, 'juin' => 6, 'juillet' => 7,
        'aout' => 8, 'septembre' => 9, 'octobre' => 10, 'novembre' => 11, 'decembre' => 12,
    ];

    private const RE_VELORAIL = '/\b(?:velo ?rails?|cyclo ?rails?|cyclo ?draisines?|draisines?|pedalo ?rails?|rando ?rails?|rail ?bikes?)\b/';

    private const RE_MUSEUM = '/\b(?:musees?|museum|museotrain|ecomusee)\b/';

    private const RE_TRAIN = '/\b(?:trains?|chemins? de fer|tramways?|autorails?|vapeur|tacot|tortillard|express|micheline|mastrou|petit train|p ?tit train|ligne touristique|cft\w*)\b/';

    /** Mots sans valeur distinctive pour comparer deux noms d'exploitation. */
    private const STOP = [
        'train', 'trains', 'touristique', 'touristiques', 'tourisme', 'chemin', 'chemins', 'fer', 'de', 'du', 'des',
        'la', 'le', 'les', 'l', 'd', 'a', 'au', 'aux', 'et', 'en', 'sur', 'par', 'velorail', 'velorails', 'velo', 'rail',
        'rails', 'cyclorail', 'cyclo', 'draisine', 'draisines', 'pedalorail', 'pedalo', 'randorail', 'rando', 'association',
        'amicale', 'amis', 'vapeur', 'ligne', 'voie', 'voies', 'ferree', 'ferrees', 'express', 'petit', 'ptit', 'p', 'tit',
        'tacot', 'tramway', 'tram', 'historique', 'musee', 'cft', 'cf', 'sarl', 'sas', 'compagnie', 'pays',
        'electrique', 'autorail', 'site', 'gare', 'ancienne', 'ancien', 'loisirs', 'patrimoine', 'ferroviaire',
        'ferroviaires', 'st', 'ste', 'saint', 'sainte', 'the', 'of', 'railway', 'heritage',
    ];

    /** Minuscules sans accents, ponctuation → espaces. */
    public static function norm(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = strtr($s, ['à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c',
            'œ' => 'oe', 'æ' => 'ae', 'ÿ' => 'y', '’' => "'", 'É' => 'e']);
        $s = preg_replace('/[^a-z0-9]+/u', ' ', $s);

        return trim(preg_replace('/\s+/', ' ', $s));
    }

    /**
     * Types d'exploitation cités par un libellé (« Train Touristique, Vélo-rail »,
     * « inexploitée (vélorail) », « Train Touristique et Vélorail d'Étretat »…).
     *
     * @return list<string> sous-ensemble de KINDS
     */
    public static function kindsOf(string $text): array
    {
        $n = ' '.self::norm($text).' ';
        $kinds = [];
        $withoutVelorail = preg_replace(self::RE_VELORAIL, ' ', $n);
        if (preg_match(self::RE_TRAIN, $withoutVelorail)) {
            $kinds[] = 'tourist_train';
        }
        if (preg_match(self::RE_VELORAIL, $n)) {
            $kinds[] = 'velorail';
        }
        if (preg_match(self::RE_MUSEUM, $n)) {
            $kinds[] = 'museum';
        }

        return $kinds;
    }

    /**
     * Type retenu pour la carte quand plusieurs coexistent : un train circule
     * (voie en service) prime sur le vélorail ; un musée qui fait rouler des
     * trains est un train touristique.
     */
    public static function mainKind(array $kinds): ?string
    {
        foreach (['tourist_train', 'velorail', 'museum'] as $k) {
            if (in_array($k, $kinds, true)) {
                return $k;
            }
        }

        return null;
    }

    /** Jetons distinctifs d'un nom (« Train à vapeur des Cévennes » → [cevennes]). */
    public static function nameTokens(string $name): array
    {
        $tokens = [];
        foreach (explode(' ', self::norm($name)) as $t) {
            $t = preg_replace('/(?<=[a-z]{4})s$/', '', $t); // pluriels
            if ($t === '' || in_array($t, self::STOP, true) || ctype_digit($t) || strlen($t) < 2) {
                continue;
            }
            $tokens[$t] = true;
        }

        return array_keys($tokens);
    }

    /**
     * Ressemblance de deux noms d'exploitation (0 à 1) : moyenne de la part des
     * jetons distinctifs du nom le plus court présents dans l'autre et de la
     * part pour le plus long. « Chemin de Fer Touristique du Vivarais » ≈
     * « Chemin de fer du Vivarais, Le vélo-rail des gorges du Doux » (0,67) ;
     * « Livradois-Forez » et « Haut-Forez » ne partagent que « forez » (0,5).
     */
    public static function nameSimilarity(string $a, string $b): float
    {
        $ta = self::nameTokens($a);
        $tb = self::nameTokens($b);
        if (! $ta || ! $tb) {
            return 0.0;
        }
        $common = count(array_intersect($ta, $tb));

        return 0.5 * $common / min(count($ta), count($tb)) + 0.5 * $common / max(count($ta), count($tb));
    }

    /**
     * Date française isolée : « 3 juin 1982 », « 1er juillet 2017 », « juin 1969 »,
     * « 1982 », « 03/06/1982 », « 06/1982 », modèles {{date|3|juin|1982}},
     * liens [[1982 dans les chemins de fer|1982]].
     *
     * @return array{date: string, precision: string, year: int}|null
     */
    public static function parseDate(?string $s): ?array
    {
        if ($s === null) {
            return null;
        }
        $s = preg_replace('/\{\{\s*date[^|}]*\|([^}]*)\}\}/iu', '$1', $s);
        $s = preg_replace('/\[\[(?:[^\]|]*\|)?([^\]]*)\]\]/u', '$1', $s);
        $s = str_replace('|', ' ', $s);
        $n = self::norm($s);
        $months = implode('|', array_keys(self::MONTHS));
        if (preg_match('/\b(1er|[12]?\d|3[01])\s*(?:er)?\s+('.$months.')\s+((?:19|20)\d\d)\b/', $n, $m)) {
            $d = $m[1] === '1er' ? 1 : (int) $m[1];
            $mo = self::MONTHS[$m[2]];
            if (checkdate($mo, $d, (int) $m[3])) {
                return ['date' => sprintf('%04d-%02d-%02d', $m[3], $mo, $d), 'precision' => 'day', 'year' => (int) $m[3]];
            }
        }
        if (preg_match('#\b(\d{1,2})/(\d{1,2})/((?:19|20)\d\d)\b#', $s, $m) && checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
            return ['date' => sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]), 'precision' => 'day', 'year' => (int) $m[3]];
        }
        if (preg_match('/\b('.$months.')\s+((?:19|20)\d\d)\b/', $n, $m)) {
            return ['date' => sprintf('%04d-%02d-01', $m[2], self::MONTHS[$m[1]]), 'precision' => 'month', 'year' => (int) $m[2]];
        }
        if (preg_match('#\b(\d{1,2})/((?:19|20)\d\d)\b#', $s, $m) && (int) $m[1] >= 1 && (int) $m[1] <= 12) {
            return ['date' => sprintf('%04d-%02d-01', $m[2], $m[1]), 'precision' => 'month', 'year' => (int) $m[2]];
        }
        if (preg_match('/\b((?:19|20)\d\d)\b/', $n, $m)) {
            return ['date' => $m[1].'-01-01', 'precision' => 'year', 'year' => (int) $m[1]];
        }

        return null;
    }

    /**
     * Dates d'une exploitation dans une PHRASE qui la cite : « depuis le 3 juin
     * 1982 », « ouvert en juin 2018 », « a circulé entre 1983 et 2003 »,
     * « jusqu'en 2008 »… Les années hors [MIN_YEAR, $maxYear] sont ignorées
     * (ouverture ou fermeture de la LIGNE, pas de l'exploitation associative).
     *
     * @return array{start?: array, end?: array}
     */
    public static function datesInSentence(string $sentence, ?int $maxYear = null): array
    {
        $out = [];
        foreach (self::dateMatches($sentence, $maxYear) as $m) {
            $out[$m['type']] ??= array_intersect_key($m, array_flip(['date', 'precision', 'year']));
        }
        if (isset($out['start'], $out['end']) && $out['end']['date'] < $out['start']['date']) {
            unset($out['end']);
        }

        return $out;
    }

    /**
     * Toutes les dates de début / fin d'une phrase, avec leur position
     * (caractères) : un intervalle « entre 1983 et 2003 » donne les deux.
     *
     * @return list<array{type: string, date: string, precision: string, year: int, pos: int}>
     */
    public static function dateMatches(string $sentence, ?int $maxYear = null): array
    {
        $maxYear ??= (int) date('Y');
        $s = str_replace(['’', "\u{a0}"], ["'", ' '], $sentence);
        $months = 'janvier|février|fevrier|mars|avril|mai|juin|juillet|août|aout|septembre|octobre|novembre|décembre|decembre';
        $date = '((?:(?:1er|[12]?\d|3[01])\s+)?(?:(?:'.$months.')\s+)?(?:19|20)\d\d)';
        $ok = fn (?array $d) => $d !== null && $d['year'] >= self::MIN_YEAR && $d['year'] <= $maxYear;
        $pos = fn (int $byte) => mb_strlen(substr($s, 0, $byte));
        $out = [];
        $taken = [];
        $add = function (string $type, ?array $d, int $byte) use (&$out, &$taken, $ok, $pos) {
            if ($ok($d) && ! isset($taken[$type.$byte])) {
                $taken[$type.$byte] = true;
                $out[] = $d + ['type' => $type, 'pos' => $pos($byte)];
            }
        };
        // Intervalle : « entre 1983 et 2003 », « de 1990 à 2005 », « (1983-2003) ».
        foreach (['/\b(?:entre|de)\s+'.$date.'\s+(?:et|à|a)\s+'.$date.'/iu', '/\(\s*((?:19|20)\d\d)\s*[-–]\s*((?:19|20)\d\d)\s*\)/u'] as $re) {
            if (preg_match_all($re, $s, $mm, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                foreach ($mm as $m) {
                    $a = self::parseDate($m[1][0]);
                    $b = self::parseDate($m[2][0]);
                    if ($ok($a) && $ok($b) && $a['date'] <= $b['date']) {
                        $add('start', $a, $m[1][1]);
                        $add('end', $b, $m[2][1]);
                    }
                }
            }
        }
        $startRe = [
            '/\bdepuis\s+(?:le\s+|l\'(?:année|an)\s+|la saison\s+|l\'été\s+|le printemps\s+)?'.$date.'/iu',
            '/\b(?:ouvert|ouverte|ouverts|ouvre|ouvrent|ouvrit|ouvrirent|inauguré|inaugurée|inaugure|inaugurent|créé|créée|crée|fondé|fondée|mis en service|mise en service|lancé|lancée|démarré|démarrée|en service)\s+(?:au public\s+|officiellement\s+)?(?:en|le|à l\'été|au printemps|à l\'automne|dès)\s+'.$date.'/iu',
            '/\b(?:à partir de|dès)\s+(?:l\'(?:année|été)\s+)?'.$date.'/iu',
            '/\b(?:premières?|premiers?)\s+(?:circulations?|trains?|saisons?|voyages?)\s+(?:a lieu |ont lieu |le |en |dès )?'.$date.'/iu',
            // « Le 14 juin 1969 une exploitation touristique démarre… », « En 2009, un vélo-rail est créé »
            '/\b(?:le|en|dès)\s+'.$date.'\s*,?\s+(?:une?|l\'|la|le|les)\s+[^.;]{0,60}?\b(?:démarre|commence|débute|ouvre|voit le jour|est inauguré|est inaugurée|est lancé|est lancée|est créé|est créée|est ouvert|est ouverte)\b/iu',
        ];
        foreach ($startRe as $re) {
            if (preg_match_all($re, $s, $mm, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                foreach ($mm as $m) {
                    $add('start', self::parseDate($m[1][0]), $m[1][1]);
                }
            }
        }
        $endRe = [
            '/\bjusqu\'(?:en|au|à)\s+'.$date.'/iu',
            '/\b(?:a cessé|cesse|cessé|cessent|arrêté|arrêtée|arrête|s\'arrête|interrompu|interrompue|suspendu|suspendue|disparu|disparaît|dissous|dissoute|fermé|fermée)\b[^.;]{0,60}?\b(?:en|le|fin|à la fin de)\s+'.$date.'/iu',
            '/\b(?:dernière|dernier)\s+(?:année|saison|circulation|train)\b[^.;]{0,30}?\b(?:en\s+)?'.$date.'/iu',
            '/\b(?:fin|arrêt|fermeture|cessation)\s+(?:de\s+l\'exploitation|des circulations|du train|du vélorail|de l\'activité)\s+(?:en|le)\s+'.$date.'/iu',
        ];
        foreach ($endRe as $re) {
            if (preg_match_all($re, $s, $mm, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                foreach ($mm as $m) {
                    $add('end', self::parseDate($m[1][0]), $m[1][1]);
                }
            }
        }
        usort($out, fn ($a, $b) => $a['pos'] <=> $b['pos']);

        return $out;
    }

    /**
     * Repli ASCII caractère pour caractère (même longueur que l'original) :
     * positions comparables entre texte original et texte normalisé.
     */
    public static function fold(string $s): string
    {
        $map = ['à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'î' => 'i', 'ï' => 'i',
            'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'œ' => 'o', 'æ' => 'a', 'ÿ' => 'y'];
        $out = '';
        foreach (mb_str_split(mb_strtolower($s)) as $c) {
            $c = $map[$c] ?? $c;
            $out .= preg_match('/^[a-z0-9]$/', $c) ? $c : ' ';
        }

        return $out;
    }

    /**
     * Interruptions d'une exploitation : « Après avoir été interrompues à l'été
     * 2008, […] les circulations ont repris […] le 2 juillet 2013. »
     *
     * @return list<array{end: array, restart: array, quote: string}>
     */
    public static function interruptionsInText(string $text, ?int $maxYear = null): array
    {
        $maxYear ??= (int) date('Y');
        $months = 'janvier|février|fevrier|mars|avril|mai|juin|juillet|août|aout|septembre|octobre|novembre|décembre|decembre';
        $date = '((?:(?:1er|[12]?\d|3[01])\s+)?(?:(?:'.$months.')\s+)?(?:19|20)\d\d)';
        $out = [];
        foreach (self::sentences(str_replace('’', "'", $text)) as $s) {
            if (! preg_match('/\b(?:interromp\w*|suspend\w*|arrêt\w*)\b[^.;]{0,80}?'.$date.'.{0,220}?\b(?:repri(?:s|t|rent)\w*|reprend\w*|rouvert\w*|réouvert\w*|relancé\w*|redémarr\w*)\b[^.;]{0,200}?'.$date.'/iu', $s, $m)) {
                continue;
            }
            $a = self::parseDate($m[1]);
            $b = self::parseDate($m[2]);
            if ($a && $b && $a['year'] >= self::MIN_YEAR && $b['year'] <= $maxYear && $a['date'] < $b['date']) {
                $out[] = ['end' => $a, 'restart' => $b, 'quote' => mb_substr($s, 0, 480)];
            }
        }

        return $out;
    }

    /** Découpe un texte brut en phrases (titres de sections « == … == » et puces comprises). */
    public static function sentences(string $text): array
    {
        $text = preg_replace('/\s*={2,}[^=\n]{1,120}={2,}\s*|\n\s*[*#:]+\s*|\n{2,}/u', ". \n", $text);
        $text = preg_replace('/\s+/u', ' ', $text);
        $parts = preg_split('/(?<=[.!?])\s+(?=[\p{Lu}«"(0-9])/u', $text) ?: [];

        return array_values(array_filter(array_map(fn ($p) => trim($p, " .\t"), $parts), fn ($p) => mb_strlen($p) > 3));
    }

    /** Contexte (texte replié) qui disqualifie une date voisine : elle parle de la LIGNE, pas de l'exploitation. */
    private const NEG_CONTEXT = '/\b(?:ferme|fermee|fermees|fermes|fermeture|declasse\w*|depose\w*|fret|marchandises|electrifi\w*|demoli\w*|travaux|etude|assistance electrique|limite|navette|chronologie|desaffecte\w*)\b/';

    /** Sujet de la phrase étranger à l'exploitation (voie verte, projet, vitesse…), où qu'il soit avant la date. */
    private const NEG_SUBJECT = '/\b(?:voie verte|velo route|veloroute|projet\w*|vitesse|en projet|a l etude)\b/';

    /**
     * Dates de l'exploitation dans un texte : seules comptent les dates à
     * moins de $window caractères d'une mention de l'exploitation ($needles :
     * nom, mot-clé du type), sans contexte de ligne (fermeture, voie verte,
     * vitesse…) juste avant. Début = le plus ancien, fin = la plus récente.
     *
     * @param  list<string>  $needles  noms / mots-clés normalisés (cf. norm())
     * @return array{start?: array, end?: array, quote_start?: string, quote_end?: string}
     */
    public static function datesInText(string $text, array $needles, ?int $maxYear = null, int $window = 120): array
    {
        $cands = ['start' => [], 'end' => []];
        foreach (self::sentences($text) as $sentence) {
            $fold = ' '.self::fold($sentence).' ';
            $hits = [];
            foreach ($needles as $needle) {
                $needle = trim(self::norm($needle));
                if ($needle === '') {
                    continue;
                }
                $off = 0;
                while (($p = strpos($fold, ' '.$needle.' ', $off)) !== false) {
                    $hits[] = $p;
                    $off = $p + 1;
                }
            }
            if (! $hits) {
                continue;
            }
            foreach (self::dateMatches($sentence, $maxYear) as $m) {
                $near = min(array_map(fn ($h) => abs($h - $m['pos']), $hits));
                $before = substr($fold, max(0, $m['pos'] + 1 - 70), min(70, $m['pos'] + 1));
                // Fermeture, dépose, fret… juste avant une date de DÉBUT : c'est l'histoire de la ligne.
                if ($near > $window || ($m['type'] === 'start' && preg_match(self::NEG_CONTEXT, $before))
                    || preg_match(self::NEG_SUBJECT, substr($fold, 0, $m['pos'] + 1))) {
                    continue;
                }
                $cands[$m['type']][] = $m + ['quote' => mb_substr($sentence, 0, 480)];
            }
        }
        $out = [];
        if ($cands['start']) {
            usort($cands['start'], fn ($a, $b) => $a['date'] <=> $b['date']);
            $out['start'] = array_intersect_key($cands['start'][0], array_flip(['date', 'precision', 'year']));
            $out['quote_start'] = $cands['start'][0]['quote'];
        }
        if ($cands['end']) {
            usort($cands['end'], fn ($a, $b) => $b['date'] <=> $a['date']);
            $out['end'] = array_intersect_key($cands['end'][0], array_flip(['date', 'precision', 'year']));
            $out['quote_end'] = $cands['end'][0]['quote'];
        }
        if (isset($out['start'], $out['end']) && $out['end']['date'] < $out['start']['date']) {
            unset($out['end'], $out['quote_end']);
        }

        return $out;
    }

    /** Lien wiki → [cible, libellé] ; texte sans lien → [null, texte]. */
    public static function wikiLinks(string $cell): array
    {
        $links = [];
        if (preg_match_all('/\[\[([^\]|#]+)(?:#[^\]|]*)?(?:\|([^\]]*))?\]\]/u', $cell, $mm, PREG_SET_ORDER)) {
            foreach ($mm as $m) {
                $links[] = [trim($m[1]), trim($m[2] ?? $m[1])];
            }
        }

        return $links;
    }

    /** Texte brut d'une cellule de tableau wiki (liens → libellés, modèles et références retirés). */
    public static function plain(string $cell): string
    {
        $s = preg_replace('/<ref[^>]*\/>|<ref[^>]*>.*?<\/ref\s*>/isu', '', $cell);
        $s = preg_replace('/<!--.*?-->/su', '', $s);
        $s = preg_replace('/\{\{\s*(?:unité|nobr|nb)\s*\|([^|}]*)\|?([^}]*)\}\}/iu', '$1 $2', $s);
        $s = preg_replace('/\{\{[^{}]*\}\}/u', '', $s);
        $s = preg_replace('/\[\[(?:[^\]|]*\|)?([^\]]*)\]\]/u', '$1', $s);
        $s = preg_replace('/\[https?:\/\/\S+\s+([^\]]*)\]/u', '$1', $s);
        $s = preg_replace('/\[https?:\/\/\S+\]/u', '', $s);
        $s = str_replace(["'''", "''", '<br>', '<br/>', '<br />'], ['', '', ' ', ' ', ' '], $s);

        return trim(preg_replace('/\s+/u', ' ', strip_tags($s)));
    }

    /**
     * Section « A - B - C » d'une cellule → bornes extrêmes (noms de lieux, ou
     * points kilométriques « km 527,5 »). Les préfixes « Gare de » sont
     * conservés : le résolveur de sections les sait retirer.
     *
     * @return array{from: ?string, to: ?string, pk_from: ?int, pk_to: ?int}
     */
    public static function parseSection(string $cell): array
    {
        $res = ['from' => null, 'to' => null, 'pk_from' => null, 'pk_to' => null];
        // Les libellés de liens sont protégés : « [[Gare de Lugarde - Marchastel]] » est UN lieu.
        $protected = [];
        $cell = preg_replace_callback('/\[\[(?:[^\]|]*\|)?([^\]]*)\]\]/u', function ($m) use (&$protected) {
            $protected['⟦'.count($protected).'⟧'] = $m[1];

            return '⟦'.(count($protected) - 1).'⟧';
        }, $cell);
        $text = self::plain($cell);
        if ($text === '') {
            return $res;
        }
        // Notes entre parenthèses retirées AVANT le découpage : « (697,710 - 711,357) » n'est pas une borne.
        $text = trim(preg_replace('/\s*\([^)]*\)\s*/u', ' ', $text));
        $text = preg_replace('/^(?:de la |de l\'|du |de |d\')/iu', '', rtrim($text, ' .'));
        $parts = array_values(array_filter(array_map('trim', preg_split('/\s+[-–]\s+|\s+à\s+|\s+jusqu\'à\s+/u', $text)), fn ($p) => $p !== ''));
        if (count($parts) < 2) {
            return $res;
        }
        foreach (['from' => reset($parts), 'to' => end($parts)] as $k => $p) {
            $p = strtr($p, $protected);
            if (preg_match('/^(?:km|pk)\s*(\d+)(?:[,.](\d+|x+))?/iu', $p, $m)) {
                $dec = isset($m[2]) && ctype_digit($m[2]) ? (int) str_pad(substr($m[2], 0, 3), 3, '0') : 0;
                $res['pk_'.$k] = (int) $m[1] * 1000 + $dec;
            } else {
                $res[$k] = trim(preg_replace('/\s*\(.*?\)\s*|["«»]/u', ' ', $p));
            }
        }

        return $res;
    }

    /**
     * Tableaux de la « Liste des chemins de fer touristiques de France »
     * (une ligne par exploitation, plusieurs lignes ferroviaires possibles via
     * rowspan). Colonnes repérées par leur en-tête.
     *
     * @return list<array{name: string, article: ?string, kinds: list<string>, type: string, depot: string,
     *                    lines: list<array{link: ?string, label: string, section: string, from: ?string, to: ?string, pk_from: ?int, pk_to: ?int}>,
     *                    gauge: string, status: ?string, status_raw: string, region: ?string}>
     */
    public static function parseWikiList(string $wikitext): array
    {
        $out = [];
        $region = null;
        $lines = preg_split('/\R/u', $wikitext);
        $headers = [];
        $inTable = false;
        $row = [];
        $pending = []; // colonne → [restant, valeur] (rowspan)
        $flush = function () use (&$row, &$out, &$headers, &$pending, &$region) {
            if (! $row) {
                return;
            }
            // Recompose la ligne complète avec les cellules prolongées par rowspan.
            $cells = [];
            $i = 0;
            $col = 0;
            $n = count($headers);
            while ($col < $n) {
                if (isset($pending[$col]) && $pending[$col][0] > 0) {
                    $cells[$col] = $pending[$col][1];
                    $pending[$col][0]--;
                } else {
                    if (! array_key_exists($i, $row)) {
                        break;
                    }
                    [$span, $val] = $row[$i++];
                    $cells[$col] = $val;
                    if ($span > 1) {
                        $pending[$col] = [$span - 1, $val];
                    }
                }
                $col++;
            }
            $row = [];
            $get = function (string $key) use ($cells, $headers) {
                $idx = array_search($key, $headers, true);

                return $idx === false ? '' : ($cells[$idx] ?? '');
            };
            $nameCell = $get('name');
            if (trim(self::plain($nameCell)) === '') {
                return;
            }
            $links = self::wikiLinks($nameCell);
            $article = null;
            if ($links && str_starts_with(ltrim($nameCell), '[[')) {
                $article = $links[0][0];
            }
            $lineCell = $get('line');
            $lineLinks = self::wikiLinks($lineCell);
            $section = $get('section');
            $statusRaw = self::plain($get('status'));
            $st = self::norm($statusRaw);
            $status = match (true) {
                str_contains($st, 'disparu') || str_contains($st, 'ferme') || str_contains($st, 'arret') || str_contains($st, 'cesse') => 'ended',
                str_contains($st, 'projet') || str_contains($st, 'reconstruction') || str_contains($st, 'travaux') => 'project',
                str_contains($st, 'service') || str_contains($st, 'ouvert') || str_contains($st, 'activite') => 'active',
                default => null,
            };
            $type = self::plain($get('type'));
            $out[] = [
                'name' => self::plain($nameCell),
                'article' => $article,
                'kinds' => self::kindsOf($type.' '.self::plain($nameCell)),
                'type' => $type,
                'depot' => self::plain($get('depot')),
                'lines' => [[
                    'link' => $lineLinks[0][0] ?? null,
                    'label' => self::plain($lineCell),
                    'section' => self::plain($section),
                ] + self::parseSection($section)],
                'gauge' => self::plain($get('gauge')),
                'status' => $status,
                'status_raw' => $statusRaw,
                'region' => $region,
            ];
        };

        foreach ($lines as $raw) {
            $l = rtrim($raw);
            if (preg_match('/^==+\s*(.*?)\s*==+\s*$/u', $l, $m)) {
                $region = self::plain($m[1]);

                continue;
            }
            if (str_starts_with($l, '{|')) {
                $inTable = true;
                $headers = [];
                $row = [];
                $pending = [];

                continue;
            }
            if (! $inTable) {
                continue;
            }
            if (str_starts_with($l, '|}')) {
                $flush();
                $inTable = false;

                continue;
            }
            if (str_starts_with($l, '!')) {
                $h = self::norm(ltrim($l, '! '));
                $headers[] = match (true) {
                    str_contains($h, 'chemin de fer') || str_contains($h, 'nom') => 'name',
                    str_contains($h, 'type') => 'type',
                    str_contains($h, 'depot') => 'depot',
                    str_contains($h, 'ligne') => 'line',
                    str_contains($h, 'section') || str_contains($h, 'parcours') => 'section',
                    str_contains($h, 'ecartement') => 'gauge',
                    str_contains($h, 'departement') => 'dept',
                    str_contains($h, 'statut') || str_contains($h, 'etat') => 'status',
                    default => 'other'.count($headers),
                };

                continue;
            }
            if (str_starts_with($l, '|-')) {
                $flush();

                continue;
            }
            if (str_starts_with($l, '|')) {
                $cell = substr($l, 1);
                $span = 1;
                // Attributs « rowspan="2" | contenu » (le | des attributs hors liens/modèles).
                if (preg_match('/^\s*((?:rowspan|colspan|style|align|class)\s*=\s*"?[^"|]*"?\s*)+\|(?!\|)(.*)$/iu', $cell, $m)) {
                    if (preg_match('/rowspan\s*=\s*"?(\d+)/iu', $m[1], $r)) {
                        $span = (int) $r[1];
                    }
                    $cell = $m[2];
                }
                $row[] = [$span, trim($cell)];

                continue;
            }
            // Continuation d'une cellule sur plusieurs lignes.
            if ($row) {
                $row[count($row) - 1][1] .= ' '.trim($l);
            }
        }

        return $out;
    }

    /**
     * Rubrique « État actuel de la ligne » (et notes) d'une fiche lignes-oubliees :
     * sections exploitées par un train touristique / vélorail, avec la date
     * quand la fiche la donne (« train touristique (depuis le 3 juin 1982) »,
     * « inexploitée (vélorail entre Gourfaleur et Condé-sur-Vire) »), plus les
     * phrases des notes qui citent une exploitation datée (« Depuis 2001,
     * "Les Vélorails du Larzac" exploitent la section La Bastide - L'Hospitalet »).
     *
     * @return list<array{section: string, from: ?string, to: ?string, kind: string, name: ?string,
     *                    start: ?array, end: ?array, quote: string, origin: string}>
     */
    public static function parseLoFiche(string $html, ?int $maxYear = null): array
    {
        $out = [];
        if (preg_match('/<dt>\s*(?:É|&Eacute;|E)tat actuel[^<]*<\/dt>(.*?)(?:<\/dl>|<dt>)/isu', $html, $m)
            && preg_match_all('/<dd>\s*<span>(.*?)<\/span>(.*?)<\/dd>/isu', $m[1], $dd, PREG_SET_ORDER)) {
            foreach ($dd as $d) {
                $section = trim(rtrim(html_entity_decode(strip_tags($d[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'), " :\t"));
                $state = trim(html_entity_decode(strip_tags($d[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                $kinds = self::kindsOf($state);
                if (! $kinds) {
                    continue;
                }
                [$from, $to] = self::sectionBounds($section);
                // Sous-portion précisée entre parenthèses : « (vélorail entre Gourfaleur et Condé-sur-Vire) ».
                if (preg_match('/\((?:[^()]*?)\b(?:entre|de)\s+(.+?)\s+(?:et|à|-)\s+(.+?)\)/u', $state, $sub)
                    && ! preg_match('/^\d/u', $sub[1])) {
                    [$from, $to] = [trim($sub[1]), trim($sub[2])];
                }
                $dates = self::datesInSentence($state, $maxYear);
                $out[] = [
                    'section' => $section, 'from' => $from, 'to' => $to, 'kind' => self::mainKind($kinds), 'name' => null,
                    'start' => $dates['start'] ?? null, 'end' => $dates['end'] ?? null,
                    'quote' => "État actuel de la ligne : {$section} : {$state}", 'origin' => 'etat',
                ];
            }
        }
        // Notes et informations complémentaires : phrases datées qui citent une exploitation.
        $body = $html;
        if (($p = stripos($body, 'tat actuel de la ligne')) !== false) {
            $body = substr($body, $p);
            if (($q = stripos($body, '</dl>')) !== false) {
                $body = substr($body, $q + 5);
            }
        }
        if (($p = stripos($body, '<h2>Les gares')) !== false) {
            $tail = substr($body, $p);
            $body = substr($body, 0, $p);
            // Les « Informations complémentaires » peuvent suivre la liste des gares.
            if (($q = stripos($tail, 'compl')) !== false) {
                $body .= ' '.substr($tail, $q, 6000);
            }
        }
        $text = html_entity_decode(strip_tags(str_replace(['</li>', '</p>', '</h4>', '</h3>'], '. ', $body)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        foreach (self::sentences($text) as $sentence) {
            $kinds = self::kindsOf(preg_replace('/\btrains? (?:de |du )?(?:voyageurs|marchandises|fret|centenaire)\b/iu', '', $sentence));
            $n = self::norm($sentence);
            if (! $kinds || ! preg_match('/touristique|velo ?rail|cyclo|draisine|pedalo|association|musee|vapeur/', $n)) {
                continue;
            }
            $dates = self::datesInSentence($sentence, $maxYear);
            if (! $dates) {
                continue;
            }
            [$from, $to] = self::sectionInSentence($sentence);
            $name = null;
            if (preg_match('/[«"“]\s*([^»"”]{3,80}?)\s*[»"”]/u', $sentence, $q)) {
                $name = trim($q[1]);
            } elseif (preg_match('/\(([^()]{3,80})\)/u', $sentence, $q) && self::kindsOf($q[1])) {
                $name = trim($q[1]);
            } elseif (preg_match('/\b((?:[Aa]ssociation|[Aa]micale)\s+[^,;.()]{2,80}?)(?=\s+(?:a|exploite|qui|depuis|entre|fait|organise)\b|[,;.(]|$)/u', $sentence, $q)) {
                $name = mb_strtoupper(mb_substr(trim($q[1]), 0, 1)).mb_substr(trim($q[1]), 1);
            }
            $out[] = [
                'section' => $from && $to ? "{$from} - {$to}" : '', 'from' => $from, 'to' => $to,
                'kind' => self::mainKind($kinds), 'name' => $name,
                'start' => $dates['start'] ?? null, 'end' => $dates['end'] ?? null,
                'quote' => mb_substr(trim($sentence), 0, 480), 'origin' => 'note',
            ];
        }

        return $out;
    }

    /**
     * Portion citée dans une phrase : « exploitent la section La Bastide -
     * L'Hospitalet », « entre les gares de Cordesse-Igornay et Manlay ».
     *
     * @return array{0: ?string, 1: ?string}
     */
    public static function sectionInSentence(string $sentence): array
    {
        $stop = '(?=\s*[,;.(]|\s+(?:soit|sur|depuis|en|de|entre|dans|pour|est|sont|a|ont|via|le|les|la|du|au|qui)\s|\s*$)';
        if (preg_match('/\b(?:la section|le tronçon|la portion|entre les gares d[e\'’]|entre)\s+(?:d[e\'’]\s*)?(?!\d)([\p{Lu}][^,;.()]*?)(?:\s*\([^)]*\))?\s+(?:-|–|et|à)\s+(?:la gare d[e\'’]\s*)?([\p{Lu}][^,;.()]*?)'.$stop.'/u', $sentence, $m)) {
            return [trim($m[1]), trim($m[2])];
        }

        return [null, null];
    }

    /** « Anduze - Saint-Jean-du-Gard (697,710 - 711,357) » → [Anduze, Saint-Jean-du-Gard]. */
    public static function sectionBounds(string $section): array
    {
        $s = trim(preg_replace('/\s*\([^)]*\)\s*/u', ' ', $section));
        $parts = array_values(array_filter(array_map('trim', preg_split('/\s+[-–]\s+/u', $s))));
        if (count($parts) < 2) {
            return [null, null];
        }

        return [reset($parts), end($parts)];
    }

    /**
     * Champs utiles de l'infobox d'un article d'exploitation (« Infobox Chemin
     * de fer touristique » : date création / date fin ; « Infobox Ligne
     * ferroviaire » : concessionnaire actuel + date de concession) et
     * exploitant / association.
     *
     * @param  array<string, string>  $infobox  paramètres nettoyés (WikipediaLineParser::parseInfobox)
     * @return array{start?: array, end?: array, operator?: string}
     */
    public static function infoboxFacts(array $infobox, ?int $maxYear = null): array
    {
        $maxYear ??= (int) date('Y');
        $get = function (array $keys) use ($infobox): ?string {
            foreach ($infobox as $k => $v) {
                if (in_array(self::norm((string) $k), $keys, true) && trim((string) $v) !== '') {
                    return (string) $v;
                }
            }

            return null;
        };
        $out = [];
        $start = self::parseDate($get(['date creation', 'date de creation', 'creation', 'date ouverture', 'ouverture', 'date d ouverture', 'fondation', 'date fondation']));
        // Concessionnaire associatif ou touristique (CFT…, mais pas « SNCF » : mot entier).
        if (! $start && ($conc = $get(['concessionnaireact'])) && preg_match('/association|touristi|\bcf\w*|amicale/i', self::norm($conc))) {
            $start = self::parseDate($get(['dateconcact']));
        }
        if ($start && $start['year'] >= self::MIN_YEAR && $start['year'] <= $maxYear) {
            $out['start'] = $start;
        }
        $end = self::parseDate($get(['date fin', 'date de fin', 'fin', 'date fermeture', 'fermeture exploitation', 'date dissolution']));
        if ($end && $end['year'] >= self::MIN_YEAR && $end['year'] <= $maxYear && (! isset($out['start']) || $end['date'] >= $out['start']['date'])) {
            $out['end'] = $end;
        }
        $op = $get(['type association', 'association', 'exploitant', 'exploitants', 'exploitant actuel', 'concessionnaireact', 'gestionnaire']);
        // « nom = Train à Vapeur des Cévennes (Sàrl CITEV) » : l'exploitant entre parenthèses.
        if ($op === null && preg_match('/\(([^()]*\b(?:s[àa]rl|sas|sa|association|amicale|compagnie|soci[ée]t[ée]|sem|spl)\b[^()]*)\)/iu', (string) $get(['nom']), $m)) {
            $op = $m[1];
        }
        if ($op !== null) {
            $op = trim(preg_replace('/\s+/u', ' ', strip_tags($op)));
            if ($op !== '' && mb_strlen($op) <= 160 && ! preg_match('/^(oui|non|association|aucun)$/iu', $op)) {
                $out['operator'] = $op;
            }
        }

        return $out;
    }

    /** Libellé français d'un type d'exploitation. */
    public static function kindLabel(string $kind): string
    {
        return match ($kind) {
            'velorail' => 'vélorail',
            'museum' => 'musée ferroviaire roulant',
            default => 'train touristique',
        };
    }
}

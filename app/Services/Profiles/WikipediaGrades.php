<?php

namespace App\Services\Profiles;

use App\Services\WikimediaClient;
use Closure;

/**
 * Déclivité la plus forte d'une ligne lue dans son article de Wikipédia en français (wikitexte déjà
 * en cache, aucun appel réseau), pour vff:import-wikipedia-grades, qui écrit
 * `database/data/sourced_grades.php` (lu par SourcedGrades). Remplace le script
 * storage/app/reports/agents/aprem-2111/profil-declivites/extraire-pentes.py du 22/11/2026, dont il
 * reprend les règles, plus celles de l'agent declivites-2 (numéro de ligne, texte de l'article).
 *
 * Deux lectures, l'infobox d'abord :
 *   - infobox « Ligne ferroviaire » : champ `pente` (pente maximale, seul champ de déclivité de ce
 *     modèle, en ‰ ou mm/m). La valeur est reprise si l'article est bien celui de la ligne : longueur
 *     de l'infobox à LENGTH_TOLERANCE près de celle de la ligne (officielle, sinon du profil), ou champ
 *     `numéro` égal au code de la ligne ;
 *   - texte de l'article (infobox, notes et commentaires ôtés) : phrase qui parle de déclivité, de
 *     pente ou de rampe avec un mot de maximum (« maximale », « atteint », « ne dépasse pas »…) et
 *     une seule valeur en ‰, sans fourchette ; toutes ces phrases doivent donner la même valeur. Le
 *     texte dit rarement sur quelle partie de la ligne porte le chiffre : la valeur n'est reprise que
 *     si elle s'accorde avec la mesure du profil (au plus MAX_RATIO fois la mesure, et une mesure au
 *     plus MAX_MEASURE_RATIO fois la valeur, le terrain sous un tracé ancien la surestimant).
 *
 * Toujours écartés : article rattaché à plusieurs lignes (la pente peut être celle d'une autre
 * partie), ligne à crémaillère (rack_railways.php), valeur hors de MIN_PERMIL à MAX_PERMIL.
 */
final class WikipediaGrades
{
    public const MIN_PERMIL = 1.0;

    /** Au-delà : crémaillère (rack_railways.php) ou valeur fausse ; ProfileStats écarte aussi 120 ‰. */
    public const MAX_PERMIL = 120.0;

    /** Écart toléré entre la longueur de l'infobox et celle de la ligne. */
    public const LENGTH_TOLERANCE = 0.25;

    /** Valeur du texte écartée si elle dépasse ce multiple de la mesure du profil. */
    public const MAX_RATIO = 3.0;

    /** Valeur du texte écartée si la mesure (souvent sur le terrain) dépasse ce multiple de la valeur. */
    public const MAX_MEASURE_RATIO = 4.0;

    /** Écart toléré entre le texte et une déclivité mesurée sur la voie : 5 ‰, ou la moitié de la mesure si c'est plus. */
    public const TRACK_AGREE_PERMIL = 5.0;

    public const TRACK_AGREE_SHARE = 0.5;

    /** Longueur la plus grande d'une phrase citée dans `quote`, en caractères. */
    public const QUOTE_MAX = 220;

    private const UNIT = '(?:‰|&permil;|mm\/m|pour mille|pour 1000)';

    /**
     * Champs utiles de la première infobox : `pente`, `longueur`, `numéro` (première occurrence de
     * chacun), valeurs brutes sans appels de note ni commentaires.
     *
     * @return array<string, string>
     */
    public static function infobox(string $wikitext): array
    {
        preg_match_all('/^[ \t]*\|[ \t]*(pente|longueur|numéro|numero)[ \t]*=[ \t]*([^\n]*?)[ \t]*$/mu', $wikitext, $m, PREG_SET_ORDER);
        $fields = [];
        foreach ($m as [, $name, $value]) {
            $name = $name === 'numero' ? 'numéro' : $name;
            $fields[$name] ??= self::clean($value);
        }

        return $fields;
    }

    /** Valeur d'un champ `pente` en ‰ (« 25 », « 7,8 ‰ », « {{unité|15|‰}} », « 12 mm/m ») ; null si illisible ou fourchette. */
    public static function grade(string $raw): ?float
    {
        $raw = self::clean($raw);
        if (preg_match('/^\{\{\s*unité\s*\|\s*(\d+(?:[.,]\d+)?)\s*\|\s*(?:‰|mm\/m)\s*\}\}$/iu', $raw, $m)
            || preg_match('/^(\d+(?:[.,]\d+)?)\s*'.self::UNIT.'?$/u', $raw, $m)) {
            return (float) str_replace(',', '.', $m[1]);
        }

        return null;
    }

    /** Valeur d'un champ `longueur` en km ; null si illisible. */
    public static function length(string $raw): ?float
    {
        $raw = self::clean($raw);
        if (preg_match('/^\{\{\s*unité\s*\|\s*(\d+(?:[.,]\d+)?)\s*\|\s*km\s*\}\}$/iu', $raw, $m)
            || preg_match('/^(\d+(?:[.,]\d+)?)\s*(?:km)?$/u', $raw, $m)) {
            return (float) str_replace(',', '.', $m[1]);
        }

        return null;
    }

    /**
     * Vrai si le champ `numéro` (« 787 000 », « 787000 ») est le code de la ligne. Un champ qui dit
     * autre chose que le numéro (« 834000 <small>(deuxième section)</small> ») ne garantit pas que
     * l'infobox décrive toute la ligne : faux.
     */
    public static function sameCode(string $raw, ?string $code): bool
    {
        $raw = self::clean($raw);
        if (! preg_match('/^\d[\d \x{00A0}\x{202F}]*$/u', $raw)) {
            return false;
        }
        $digits = preg_replace('/\D/', '', $raw) ?? '';
        $code = preg_replace('/\D/', '', (string) $code) ?? '';

        return $digits !== '' && $code !== '' && ltrim($digits, '0') === ltrim($code, '0');
    }

    /**
     * Déclivités maximales écrites dans le texte de l'article : une par phrase retenue (voir la
     * description de la classe), avec la phrase (réduite à QUOTE_MAX caractères autour de la valeur).
     *
     * @return list<array{value: float, quote: string}>
     */
    public static function textGrades(string $wikitext): array
    {
        $found = [];
        foreach (self::sentences(self::prose($wikitext)) as $sentence) {
            // Fourchette (« de 6 à 10 ‰ », « entre 15 et 20 ‰ ») : la phrase ne dit pas un maximum unique.
            if (preg_match('/\d(?:[.,]\d+)?\s*'.self::UNIT.'?\s*(?:à|et|-|–|ou)\s*\d+(?:[.,]\d+)?\s*'.self::UNIT.'/u', $sentence)
                // Portion nommée (« Entre Albertville et Moûtiers… ») : le chiffre ne vaut pas pour toute la ligne.
                || preg_match('/\b(?i:entre)\s+(?:la gare d(?:e |\')|l\'|le |la )?[[:upper:]]/u', $sentence)) {
                continue;
            }
            preg_match_all('/(\d+(?:[.,]\d+)?)\s*'.self::UNIT.'/u', $sentence, $all);
            if (count(array_unique(array_map(fn (string $v): float => (float) str_replace(',', '.', $v), $all[1]))) !== 1) {
                continue;
            }
            // Mot de maximum attaché au mot de déclivité, puis la valeur, dans le même membre de phrase.
            if (! preg_match('/\b(?:déclivités?|pentes?|rampes?)\s+(?:(?:sont\s+|est\s+)?maxim(?:ale|ales|um|ums|a)|la plus forte|les plus fortes)\b[^.;:]{0,40}?(\d+(?:[.,]\d+)?)\s*'.self::UNIT.'/iu', $sentence, $m, PREG_OFFSET_CAPTURE)
                && ! preg_match('/\b(?:déclivités?|pentes?|rampes?)\b[^.;:\d]{0,30}?\b(?:ne\s+dépass\w*(?:\s+pas|\s+jamais)?|n.excèd\w*\s+pas|atteign\w*|atteint|atteindre|peu\w*\s+atteindre)\b[^.;:\d]{0,30}?(\d+(?:[.,]\d+)?)\s*'.self::UNIT.'/iu', $sentence, $m, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            $found[] = ['value' => (float) str_replace(',', '.', $m[1][0]), 'quote' => self::excerpt($sentence, (int) $m[0][1] + strlen($m[0][0]))];
        }

        return $found;
    }

    /**
     * Décision pour une ligne et son article. `$lengthM` : longueur officielle de la ligne, sinon
     * celle du profil ; `$measure` : appelée seulement pour contrôler une valeur du texte (lecture
     * du profil en base), rend la déclivité mesurée (MeasuredGrade::of) ou null.
     *
     * @param  array{title: string, revid: int, content: string}  $article
     * @param  Closure(): (array{value: float, kind: string}|null)  $measure
     * @return array{status: string, entry: array{max_grade_permil: int|float, provider: string, license: string, source_url: string, source_title: string, quote: string}|null}
     */
    public static function decide(array $article, ?string $code, ?int $lengthM, bool $rack, bool $shared, Closure $measure): array
    {
        $out = fn (string $status, ?float $value = null, string $quote = ''): array => [
            'status' => $status,
            'entry' => $value === null ? null : self::entry($article, $value, $quote),
        ];
        if ($rack) {
            return $out('rack');
        }
        if ($shared) {
            return $out('shared_article');
        }

        $fields = self::infobox($article['content']);
        $raw = $fields['pente'] ?? '';
        $fallback = 'no_grade';
        if ($raw !== '') {
            $grade = self::grade($raw);
            if ($grade === null) {
                $fallback = 'infobox_unreadable';
            } elseif ($grade < self::MIN_PERMIL || $grade > self::MAX_PERMIL) {
                $fallback = 'infobox_out_of_range';
            } else {
                $length = isset($fields['longueur']) ? self::length($fields['longueur']) : null;
                if ($length !== null && $lengthM !== null && $lengthM > 0 && abs($length * 1000 - $lengthM) <= self::LENGTH_TOLERANCE * $lengthM) {
                    return $out('infobox_length', $grade, "pente = {$raw} ; longueur = {$fields['longueur']}");
                }
                if (isset($fields['numéro']) && self::sameCode($fields['numéro'], $code)) {
                    return $out('infobox_number', $grade, "pente = {$raw} ; numéro = {$fields['numéro']}");
                }
                $fallback = 'infobox_unmatched';
            }
        }

        $text = self::textGrades($article['content']);
        if ($text === []) {
            return $out($fallback);
        }
        $values = array_values(array_unique(array_column($text, 'value')));
        if (count($values) > 1) {
            return $out('text_ambiguous');
        }
        $value = $values[0];
        if ($value < self::MIN_PERMIL || $value > self::MAX_PERMIL) {
            return $out('text_out_of_range');
        }
        $measured = $measure();
        if ($measured === null) {
            return $out('text_unchecked');
        }
        if ($measured['value'] <= 0 || $value > self::MAX_RATIO * $measured['value'] || $measured['value'] > self::MAX_MEASURE_RATIO * $value
            // Mesure sur la voie : le texte doit en être proche (sinon il parle sans doute d'une autre portion).
            || ($measured['kind'] === 'track' && abs($value - $measured['value']) > max(self::TRACK_AGREE_PERMIL, self::TRACK_AGREE_SHARE * $measured['value']))) {
            return $out('text_implausible');
        }

        return $out('text', $value, ($raw !== '' ? "pente = {$raw} ; " : '').'texte : « '.$text[0]['quote'].' »');
    }

    /**
     * Entrée du fichier de données.
     *
     * @param  array{title: string, revid: int, content: string}  $article
     * @return array{max_grade_permil: int|float, provider: string, license: string, source_url: string, source_title: string, quote: string}
     */
    public static function entry(array $article, float $value, string $quote): array
    {
        return [
            'max_grade_permil' => floor($value) === $value ? (int) $value : $value,
            'provider' => 'wikipedia',
            'license' => SourcedGrades::LICENSE,
            'source_url' => WikimediaClient::permalink($article['title'], $article['revid']),
            'source_title' => $article['title'],
            'quote' => $quote,
        ];
    }

    /**
     * Fusion avec le fichier en place : une entrée nouvelle est ajoutée ; une entrée de même valeur
     * et de même révision reste telle quelle ; une entrée tirée du texte (relue à la main, `quote`
     * contient « texte : ») n'est jamais remplacée ; une entrée que la lecture ne retrouve plus est
     * gardée (l'import n'efface rien : une ligne à retirer l'est dans git, à la main).
     *
     * @param  array<string, array<string, mixed>>  $existing
     * @param  array<string, array<string, mixed>>  $read
     * @return array{entries: array<string, array<string, mixed>>, outcome: array<string, string>}
     */
    public static function merge(array $existing, array $read): array
    {
        $entries = $existing;
        $outcome = [];
        foreach ($read as $slug => $entry) {
            $old = $existing[$slug] ?? null;
            if ($old === null) {
                $entries[$slug] = $entry;
                $outcome[$slug] = 'added';
            } elseif ((float) $old['max_grade_permil'] === (float) $entry['max_grade_permil'] && $old['source_url'] === $entry['source_url']) {
                $outcome[$slug] = 'unchanged';
            } elseif (str_contains((string) ($old['quote'] ?? ''), 'texte :')) {
                $outcome[$slug] = 'kept_reviewed';
            } else {
                $entries[$slug] = $entry;
                $outcome[$slug] = 'updated';
            }
        }
        foreach (array_diff_key($existing, $read) as $slug => $_) {
            $outcome[$slug] = 'kept_not_read';
        }
        ksort($entries);
        ksort($outcome);

        return ['entries' => $entries, 'outcome' => $outcome];
    }

    /**
     * Contenu de `database/data/sourced_grades.php`, entrées triées par slug : deux imports des
     * mêmes articles donnent le même fichier, octet pour octet.
     *
     * @param  array<string, array<string, mixed>>  $entries
     */
    public static function render(array $entries): string
    {
        ksort($entries);
        $body = '';
        foreach ($entries as $slug => $entry) {
            $body .= '    '.var_export($slug, true)." => [\n";
            foreach ($entry as $key => $value) {
                $body .= '        '.var_export($key, true).' => '.var_export($value, true).",\n";
            }
            $body .= "    ],\n";
        }

        return <<<PHP
        <?php

        /**
         * Déclivités les plus fortes publiées par Wikipédia, une par ligne, avec la révision lue.
         * Lu par App\Services\Profiles\SourcedGrades : quand le profil en long ne publie pas sa propre
         * mesure (altitude du terrain seulement, tunnel, points interpolés), la fiche, l'API REST, GraphQL
         * et l'outil MCP `line_profile` donnent cette valeur à la place, avec sa source
         * (`sourced_grade`) ; quand la mesure est publiée, la valeur est donnée à côté, avec l'écart
         * (`sourced_grade_compared`). Une déclivité mesurée sur la voie n'est jamais remplacée.
         *
         * Fichier ÉCRIT par `php artisan vff:import-wikipedia-grades` (articles déjà en cache, aucun appel
         * réseau ; règles dans App\Services\Profiles\WikipediaGrades) : champ `pente` de l'infobox
         * « Ligne ferroviaire » quand l'article est bien celui de la ligne (longueur à 25 % près ou
         * numéro de ligne), sinon déclivité maximale écrite dans le texte de l'article, contrôlée par
         * la mesure du profil. L'import ajoute et met à jour, il n'efface rien ; une entrée tirée du
         * texte (`quote` « texte : … ») a été relue et n'est jamais remplacée.
         *
         * Les déclivités du jeu SNCF Open Data (SNCF Réseau, ODbL) sont dans un fichier à part, écrit par
         * `vff:import-sncf-grades` : database/data/sourced_grades_sncf.php.
         *
         * Clé : slug de la ligne (`lines.slug`). Champs :
         *   - max_grade_permil : déclivité la plus forte selon la source, en ‰ ;
         *   - provider : fournisseur, clé du registre des droits (`wikipedia`) ;
         *   - license : licence de la source (`CC BY-SA 4.0`) ;
         *   - source_url : permalien de la révision lue ;
         *   - source_title : titre de l'article ;
         *   - quote : champs de l'infobox lus, ou phrase du texte, sans leurs appels de note.
         *
         * Wikipédia : CC BY-SA 4.0 ; seuls des faits (une valeur numérique) sont repris, avec le lien.
         *
         * @return array<string, array{max_grade_permil: int|float, provider: string, license: string, source_url: string, source_title: string, quote: string}>
         */
        return [
        {$body}];

        PHP;
    }

    /**
     * Phrase citée : entière si elle tient en QUOTE_MAX caractères, sinon les QUOTE_MAX caractères qui
     * finissent sur la valeur (`$end` : position en octets juste après elle), avec des points de suspension.
     */
    private static function excerpt(string $sentence, int $end): string
    {
        if (mb_strlen($sentence) <= self::QUOTE_MAX) {
            return $sentence;
        }
        $head = substr($sentence, 0, $end);
        $tail = substr($sentence, $end);
        $start = max(0, mb_strlen($head) - self::QUOTE_MAX + 2);
        $quote = ($start > 0 ? '…' : '').ltrim(mb_substr($head, $start));
        $room = self::QUOTE_MAX - mb_strlen($quote);
        if ($tail !== '' && $room > 1) {
            $quote .= mb_strlen($tail) > $room ? rtrim(mb_substr($tail, 0, $room - 1)).'…' : $tail;
        } elseif ($tail !== '') {
            $quote .= '…';
        }

        return $quote;
    }

    /** Valeur brute sans appels de note ni commentaires. */
    private static function clean(string $raw): string
    {
        return trim((string) preg_replace('/<ref[^>]*\/>|<ref.*?<\/ref>|<!--.*?-->/su', '', $raw));
    }

    /** Texte courant de l'article : sans infobox, notes, commentaires, modèles de mise en forme ni liens. */
    private static function prose(string $wikitext): string
    {
        $text = self::withoutInfobox($wikitext);
        $text = (string) preg_replace('/<ref[^>]*\/>|<ref.*?<\/ref>|<!--.*?-->/su', '', $text);
        // {{unité|12|‰}}, {{nobr|40 ‰}}, {{nombre|…}} : paramètres mis bout à bout.
        $text = (string) preg_replace_callback('/\{\{\s*(?:unité|nobr|nombre|nb)\s*\|([^{}]*)\}\}/iu',
            fn (array $m): string => implode(' ', array_map('trim', explode('|', $m[1]))), $text);
        $text = (string) preg_replace('/\[\[(?:[^\]|]*\|)?([^\]]*)\]\]/u', '$1', $text);

        return str_replace(["'''", "''", '&nbsp;', "\u{202F}", "\u{00A0}"], ['', '', ' ', ' ', ' '], $text);
    }

    /** Wikitexte sans le premier modèle « Infobox » (accolades équilibrées). */
    private static function withoutInfobox(string $wikitext): string
    {
        $start = stripos($wikitext, '{{Infobox');
        if ($start === false) {
            return $wikitext;
        }
        $depth = 0;
        $n = strlen($wikitext);
        for ($i = $start; $i < $n - 1; $i++) {
            $pair = substr($wikitext, $i, 2);
            if ($pair === '{{') {
                $depth++;
                $i++;
            } elseif ($pair === '}}') {
                $depth--;
                $i++;
                if ($depth === 0) {
                    return substr($wikitext, 0, $start).substr($wikitext, $i + 1);
                }
            }
        }

        return substr($wikitext, 0, $start);
    }

    /** @return list<string> phrases du texte, espaces réduits */
    private static function sentences(string $text): array
    {
        $parts = preg_split('/(?<=[.!?])\s+(?=[[:upper:]«])|\n+/u', $text) ?: [];

        return array_values(array_filter(array_map(fn (string $s): string => trim((string) preg_replace('/\s+/u', ' ', $s)), $parts),
            fn (string $s): bool => $s !== '' && ! str_starts_with($s, '{{') && ! str_starts_with($s, '|')));
    }
}

<?php

namespace App\Services\Materials;

/**
 * Lecture du wikitexte des articles Wikipédia pour relier matériel et lignes.
 *
 * Seuls comptent les LIENS internes ([[Cible|texte]]) posés dans des sections qui parlent du
 * matériel engagé : « Matériel roulant », « Exploitation », « Desserte »… pour un article de
 * ligne ; « Utilisation », « Services », « Affectations », « Carrière »… pour un article de
 * matériel. Un lien désigne un article précis (donc une série ou une ligne précise) : aucun
 * rapprochement de noms approximatif. La phrase qui porte le lien est gardée comme preuve.
 *
 * Prudence : une phrase au futur ou au conditionnel (« devraient remplacer », « prévu »), ou qui
 * nie la circulation (« n'a jamais circulé », « interdit »), ne crée pas de lien.
 *
 * Une phrase qui reprend un sujet déjà posé pour dire qu'il a été remplacé (« Ce train a été remplacé
 * par le TGV ») parle d'un AUTRE matériel que celui qui est cité : le lien est gardé mais marqué
 * `refers_back`, la phrase précédente est ajoutée à la citation pour qu'elle se lise seule, et le
 * lien ne peut pas être rangé en « source directe ».
 */
final class WikipediaMaterialSections
{
    /** Sections d'un article de LIGNE qui décrivent le matériel engagé ou l'exploitation. */
    public const LINE_SECTIONS = '/^(?:mat[ée]riels?(?:\s+(?:roulants?|utilis[ée]s?|moteurs?|remorqu[ée]s?|ferroviaires?|voyageurs))?|exploitation|desserte|dessertes|trafic|services?\s+ferroviaires?|circulations?)\b/iu';

    /** Sections d'un article de MATÉRIEL qui décrivent ses services et ses lignes. */
    public const MATERIAL_SECTIONS = '/^(?:utilisations?|services?|service\s+commercial|affectations?|lignes?\s+(?:parcourues|desservies|exploit[ée]es)|dessertes?|exploitation|carri[èe]res?|parcours|missions?|engagements?|en\s+service)\b/iu';

    /** Longueur maximale d'une phrase citée comme preuve. */
    private const QUOTE_WIDTH = 300;

    /** Futur, conditionnel, projet ou négation de la circulation : pas de lien. */
    private const DOUBTFUL = '/\b(?:jamais|interdite?s?|incompatibles?|ne\s+(?:peu[tv]ent|pouvai(?:en)?t|pourr\w*)|devr(?:a|ont|ait|aient)|pourr(?:a|ont|ait|aient)|prévue?s?|envisag\w*|projet\w*|à\s+terme|seront|sera)\b/iu';

    /** Phrase qui reprend un sujet antérieur (« Ce train », « Ces rames », « Ils », « Elles ») pour dire par quoi il est remplacé. */
    private const REFERS_BACK_REPLACEMENT = '/^(?:ce|cet|cette|ces|celui|celle|ceux|celles|il|ils|elle|elles)\b(?:-(?:ci|là))?[^.;]*\bremplac(?:é|ée|és|ées)\b[^.;]{0,40}?\bpar\b/iu';

    /** Titre d'une section consacrée à des projets ou à l'avenir. */
    private const DOUBTFUL_SECTION = '/\b(?:prévue?s?|projets?|futur\w*|envisag\w*|perspectives?)\b/iu';

    /** Modèles en ligne dont on garde le texte (dates, unités, nombres, siècles). */
    private const INLINE_TEMPLATES = ['date', 'date-', 'unité', 'nobr', 'nombre', 'nb', 's', 's-', 'sp', 'siècle', 'x', 'citation', 'japonais', 'formatnum', 'num', 'lang', 'abréviation', 'abr', 'm', 'n°', 'numéro'];

    /**
     * Sections dont le titre correspond au motif, avec leurs sous-sections.
     *
     * @return list<array{title: string, level: int, text: string}>
     */
    public static function sections(string $wikitext, string $pattern): array
    {
        $parts = preg_split('/^(={2,6})\s*(.+?)\s*\1\s*$/mu', $wikitext, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $out = [];
        $keepUntil = null;
        $current = null;
        for ($i = 1; $i + 1 < count($parts); $i += 3) {
            $level = strlen($parts[$i]);
            $title = self::plainTitle($parts[$i + 1]);
            $body = $parts[$i + 2] ?? '';
            if ($keepUntil !== null && $level <= $keepUntil) {
                $keepUntil = null;
                $current = null;
            }
            if ($keepUntil === null && preg_match($pattern, $title)) {
                $keepUntil = $level;
                $current = $title;
            }
            if ($keepUntil !== null) {
                $out[] = ['title' => $level > $keepUntil ? "{$current} > {$title}" : $title, 'level' => $level, 'text' => $body];
            }
        }

        return $out;
    }

    /**
     * Section consacrée à des projets ou à l'avenir (« Dessertes réalisées ou prévues »,
     * « Projets ») : ses liens ne prouvent pas une circulation. Le titre inclut celui de la
     * section parente (« Parent > Sous-section »).
     */
    public static function isDoubtfulSection(string $title): bool
    {
        return preg_match(self::DOUBTFUL_SECTION, $title) === 1;
    }

    /**
     * Liens internes d'un texte, avec la phrase (ou la ligne de liste ou de tableau) qui les porte.
     * Les liens vers des fichiers, catégories ou autres espaces de noms sont ignorés.
     *
     * @return list<array{target: string, quote: string, refers_back: bool}>
     */
    public static function links(string $text): array
    {
        $text = self::stripNoise($text);
        $out = [];
        foreach (self::units($text) as $unit) {
            if (! preg_match_all('/\[\[([^\[\]|#]+)(?:#[^\[\]|]*)?(?:\|[^\[\]]*)?\]\]/u', $unit['sentence'], $matches)) {
                continue;
            }
            $quote = self::plain($unit['sentence']);
            if ($quote === '' || preg_match(self::DOUBTFUL, $quote)) {
                continue;
            }
            $refersBack = preg_match(self::REFERS_BACK_REPLACEMENT, $quote) === 1;
            // La phrase précédente (même ligne) donne son sujet à « Ce train… » : on la cite avec elle.
            if ($refersBack && $unit['previous'] !== null) {
                $room = self::QUOTE_WIDTH - mb_strlen($quote) - 1;
                $quote = ($room >= 40 ? mb_strimwidth(self::plain($unit['previous']), 0, $room, '…').' ' : '').$quote;
            }
            foreach ($matches[1] as $raw) {
                $target = self::normalizeTitle($raw);
                // Fichiers, catégories, autres espaces de noms et liens interwikis (« :en:… ») : pas des articles.
                if ($target !== '' && ! str_starts_with($target, ':')
                    && ! preg_match('/^(?:fichier|file|image|catégorie|category|modèle|template|portail|projet|aide|wikipédia|wp|wikt|wiktionary|commons|s|d|en|de|it|es|nl)\s*:/iu', $target)) {
                    $out[] = ['target' => $target, 'quote' => mb_strimwidth($quote, 0, self::QUOTE_WIDTH, '…'), 'refers_back' => $refersBack];
                }
            }
        }

        return $out;
    }

    /** Titre d'article normalisé comme MediaWiki : espaces, première lettre en capitale. */
    public static function normalizeTitle(string $title): string
    {
        $title = html_entity_decode(str_replace('_', ' ', $title), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $title = trim((string) preg_replace('/[\s\x{00A0}\x{202F}]+/u', ' ', $title));
        if ($title === '' || str_starts_with($title, ':')) {
            return $title;
        }

        return mb_strtoupper(mb_substr($title, 0, 1)).mb_substr($title, 1);
    }

    /** Commentaires, références, balises et galeries retirés (ils ne sont pas du texte lu). */
    private static function stripNoise(string $text): string
    {
        $text = (string) preg_replace('/<!--.*?-->/su', ' ', $text);
        $text = (string) preg_replace('/<ref[^>\/]*\/>|<ref[^>]*>.*?<\/ref>/su', ' ', $text);
        $text = (string) preg_replace('/<gallery[^>]*>.*?<\/gallery>/su', ' ', $text);

        return (string) preg_replace('/<\/?[a-z][^>]*>/iu', ' ', $text);
    }

    /**
     * Unités de lecture : chaque ligne (élément de liste, cellule de tableau), découpée en phrases ;
     * `previous` est la phrase qui précède dans la même ligne (null pour la première).
     *
     * @return list<array{sentence: string, previous: ?string}>
     */
    private static function units(string $text): array
    {
        $out = [];
        foreach (preg_split('/\n+/u', $text) ?: [] as $line) {
            // Fin de phrase : point suivi d'un espace et d'une capitale, hors des liens.
            $previous = null;
            foreach (preg_split('/(?<=[.!?])\s+(?=[A-ZÉÈÀÂÎÔÛÇ«])(?![^\[]*\]\])/u', $line) ?: [] as $sentence) {
                if (trim($sentence) !== '') {
                    $out[] = ['sentence' => $sentence, 'previous' => $previous];
                    $previous = $sentence;
                }
            }
        }

        return $out;
    }

    /** Texte lisible d'un morceau de wikitexte : liens remplacés par leur texte, modèles simplifiés. */
    public static function plain(string $wikitext): string
    {
        $text = $wikitext;
        // Modèles imbriqués : du plus intérieur au plus extérieur.
        for ($i = 0; $i < 6 && str_contains($text, '{{'); $i++) {
            $text = (string) preg_replace_callback('/\{\{([^{}]*)\}\}/u', function (array $m): string {
                $params = array_map('trim', explode('|', $m[1]));
                $name = mb_strtolower((string) array_shift($params));
                $positional = array_values(array_filter($params, fn (string $p): bool => ! str_contains($p, '=') && $p !== ''));
                if ($name === 'lien') {
                    foreach ($params as $p) {
                        if (preg_match('/^(?:fr|texte)\s*=\s*(.+)$/u', $p, $mm)) {
                            return $mm[1];
                        }
                    }

                    return $positional[0] ?? '';
                }

                return in_array($name, self::INLINE_TEMPLATES, true) ? implode(' ', $positional) : '';
            }, $text);
        }
        // Liens du plus intérieur au plus extérieur ; d'un fichier, seule la légende (dernier paramètre) est du texte.
        for ($i = 0; $i < 4 && str_contains($text, '[['); $i++) {
            $text = (string) preg_replace_callback('/\[\[([^\[\]]*)\]\]/u', function (array $m): string {
                $params = explode('|', $m[1]);
                if (preg_match('/^\s*(?:fichier|file|image)\s*:/iu', $params[0])) {
                    $caption = trim((string) end($params));

                    return count($params) > 1 && ! preg_match('/^(?:thumb|vignette|left|right|gauche|droite|centre|center|upright|redresse|\d+px)\b/iu', $caption) ? $caption : '';
                }

                return (string) end($params);
            }, $text);
        }
        $text = (string) preg_replace('/\[https?:\/\/\S+\s*([^\]]*)\]/u', '$1', $text);
        $text = str_replace(["'''", "''"], '', $text);
        $text = (string) preg_replace('/^[\s*#:;|!{}-]+/u', '', $text);
        $text = str_replace(['||', '!!'], ' ; ', $text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = (string) preg_replace('/[\x{2014}\x{2013}]/u', '-', $text);
        // Espace laissée devant un point ou une virgule par une référence retirée.
        $text = (string) preg_replace('/\s+([.,)])/u', '$1', $text);

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    private static function plainTitle(string $title): string
    {
        return trim(self::plain($title), " =\t");
    }
}

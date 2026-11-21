<?php

namespace App\Support;

use App\Http\Middleware\SetLocale;
use InvalidArgumentException;

/**
 * Message de soutien de VFF à une ligne (lines.advocacy), en français, anglais et
 * allemand.
 *
 * Stockage (JSON, rétrocompatible) : les champs historiques `title`, `message`,
 * `cta_label` portent le texte FRANÇAIS (langue de référence, lu tel quel par les
 * anciens lecteurs) ; `translations` = {en: {title, message, cta_label}, de: {…}}.
 *
 *   {"kind": "reopening", "title": "Rouvrons…", "message": "…", "cta_label": "…",
 *    "cta_url": "https://…", "translations": {"en": {"title": "…", …}, "de": {…}},
 *    "sources": [{"label": "France 3 Normandie", "date": "2026-07-23", "url": "https://…"}]}
 *
 * Place sur l'accueil (facultative, clé `home` absente sinon) : rang croissant (1 d'abord ; sans rang,
 * après les lignes classées), forme `card` (encart complet, par défaut) ou `mention` (une phrase courte
 * et un lien vers la fiche, après les encarts), texte court de la mention en français et ses traductions :
 *
 *   "home": {"rank": 3, "display": "mention", "summary": "En juillet 2026, …",
 *            "translations": {"en": "In July 2026, …", "de": "Im Juli 2026 …"}}
 *
 * L'encart de la fiche de la ligne ne dépend pas de cette clé.
 *
 * Sources (facultatives, clé absente sinon) : articles et documents qui étayent le message, nom
 * du média ou de l'auteur (non traduit) et date de publication, rédigée dans la langue de la page
 * à la lecture.
 *
 * Lecture (localized) : le texte de la langue de la page ; à défaut, le texte par
 * défaut traduit quand le français est le texte par défaut de vff:flag-threatened
 * (lang/{fr,en,de}/home.php, advocacy.default_*), sinon le français, signalé par
 * `lang` (attribut lang="fr" côté page) : jamais de texte d'une autre langue sans
 * le dire. Un champ absent reste absent (null).
 */
final class Advocacy
{
    /** Champs traduisibles. */
    public const FIELDS = ['title', 'message', 'cta_label'];

    public const KINDS = ['threatened', 'reopening'];

    /** Formes d'une campagne sur l'accueil : encart complet ou mention courte. */
    public const HOME_DISPLAYS = ['card', 'mention'];

    /**
     * Lit une source donnée en ligne de commande : « Libellé|AAAA-MM-JJ|https://… ».
     *
     * @return array{label: string, date: string, url: string}
     *
     * @throws InvalidArgumentException format invalide
     */
    public static function parseSource(string $raw): array
    {
        $parts = array_map('trim', explode('|', $raw));
        if (count($parts) !== 3 || $parts[0] === '' || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $parts[1], $m)
            || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1]) || ! preg_match('#^https://\S+$#', $parts[2])) {
            throw new InvalidArgumentException("source invalide « {$raw} » (attendu : Libellé|AAAA-MM-JJ|https://…)");
        }

        return ['label' => $parts[0], 'date' => $parts[1], 'url' => $parts[2]];
    }

    /** Clé de langue (home.advocacy.*) du texte par défaut d'un champ. */
    public static function defaultKey(string $kind, string $field): string
    {
        return match ($field) {
            'title' => 'home.advocacy.default_title_'.$kind,
            'message' => 'home.advocacy.default_message_'.$kind,
            default => 'home.advocacy.default_cta',
        };
    }

    /** Texte par défaut d'un champ dans une langue. */
    public static function defaultText(string $kind, string $field, string $locale = 'fr'): string
    {
        return (string) __(self::defaultKey($kind, $field), [], $locale);
    }

    /**
     * Construit la valeur stockée.
     *
     * @param  array<string, array<string, ?string>>  $texts  champ → [langue → texte] (null ou vide : défaut)
     * @param  list<array{label: string, date: string, url: string}>  $sources  sources du message (parseSource)
     * @param  array<string, mixed>|null  $home  place sur l'accueil (buildHome)
     * @return array<string, mixed>
     */
    public static function build(string $kind, array $texts, ?string $url, array $sources = [], ?array $home = null): array
    {
        $out = ['kind' => $kind];
        foreach (self::FIELDS as $field) {
            $fr = trim((string) ($texts[$field]['fr'] ?? ''));
            $out[$field] = $fr !== '' ? $fr : self::defaultText($kind, $field);
        }
        $out['cta_url'] = $url !== null && trim($url) !== '' ? trim($url) : null;
        $translations = [];
        foreach (array_diff(SetLocale::SUPPORTED, ['fr']) as $locale) {
            foreach (self::FIELDS as $field) {
                $t = trim((string) ($texts[$field][$locale] ?? ''));
                if ($t !== '') {
                    $translations[$locale][$field] = $t;
                }
            }
        }
        if ($translations !== []) {
            $out['translations'] = $translations;
        }
        if ($sources !== []) {
            $out['sources'] = $sources;
        }
        if ($home !== null) {
            $out['home'] = $home;
        }

        return $out;
    }

    /**
     * Construit la place sur l'accueil ; null quand rien n'est précisé (encart complet, sans rang).
     *
     * @param  array<string, ?string>  $summary  langue → texte court de la mention
     * @return array{rank?: int, display: string, summary?: string, translations?: array<string, string>}|null
     *
     * @throws InvalidArgumentException forme inconnue, rang < 1, mention sans texte français
     */
    public static function buildHome(?int $rank, ?string $display, array $summary = []): ?array
    {
        $summary = array_filter(array_map(fn (?string $t) => trim((string) $t), $summary), fn (string $t) => $t !== '');
        if ($rank === null && ($display === null || $display === 'card') && $summary === []) {
            return null;
        }
        $display ??= 'card';
        if (! in_array($display, self::HOME_DISPLAYS, true)) {
            throw new InvalidArgumentException('forme d\'accueil inconnue « '.$display.' » (attendu : '.implode(' ou ', self::HOME_DISPLAYS).')');
        }
        if ($rank !== null && $rank < 1) {
            throw new InvalidArgumentException('le rang d\'accueil commence à 1');
        }
        if ($display === 'mention' && ! isset($summary['fr'])) {
            throw new InvalidArgumentException('une mention d\'accueil demande son texte court en français');
        }
        $home = $rank !== null ? ['rank' => $rank] : [];
        $home['display'] = $display;
        if (isset($summary['fr'])) {
            $home['summary'] = $summary['fr'];
        }
        $translations = array_diff_key($summary, ['fr' => true]);
        if ($translations !== []) {
            $home['translations'] = $translations;
        }

        return $home;
    }

    /**
     * Place sur l'accueil dans la langue de la page. Sans clé `home` : encart complet, sans rang.
     * Mention sans texte (valeur abîmée) : rendue en encart, jamais en lien muet.
     *
     * @param  array<string, mixed>|null  $advocacy  valeur stockée
     * @return array{rank: ?int, display: string, summary: ?string, lang: ?string}
     */
    public static function home(?array $advocacy, string $locale): array
    {
        $home = is_array($advocacy['home'] ?? null) ? $advocacy['home'] : [];
        $rank = is_int($home['rank'] ?? null) && $home['rank'] >= 1 ? $home['rank'] : null;
        $fr = trim((string) ($home['summary'] ?? ''));
        $translated = $locale !== 'fr' ? trim((string) ($home['translations'][$locale] ?? '')) : '';
        [$summary, $lang] = match (true) {
            $translated !== '' => [$translated, $locale],
            $fr !== '' => [$fr, 'fr'],
            default => [null, null],
        };
        $display = ($home['display'] ?? null) === 'mention' && $summary !== null ? 'mention' : 'card';

        return ['rank' => $rank, 'display' => $display, 'summary' => $summary, 'lang' => $lang];
    }

    /**
     * Message dans la langue de la page.
     *
     * @param  array<string, mixed>|null  $advocacy  valeur stockée
     * @return array{kind: ?string, title: ?string, message: ?string, cta_label: ?string, cta_url: ?string, lang: array<string, ?string>, sources?: list<array{label: string, date: string, url: string}>}|null
     *                                                                                                                                                                                                          lang : langue réelle de chaque champ (fr si repli, null si absent)
     */
    public static function localized(?array $advocacy, string $locale): ?array
    {
        if ($advocacy === null || $advocacy === []) {
            return null;
        }
        $kind = in_array($advocacy['kind'] ?? null, self::KINDS, true) ? $advocacy['kind'] : null;
        $out = ['kind' => $kind];
        $lang = [];
        foreach (self::FIELDS as $field) {
            // Champ absent : absent (la page a ses propres libellés de repli), jamais un texte inventé.
            $fr = trim((string) ($advocacy[$field] ?? ''));
            [$text, $l] = [$fr === '' ? null : $fr, $fr === '' ? null : 'fr'];
            if ($locale !== 'fr' && $fr !== '') {
                $translated = trim((string) ($advocacy['translations'][$locale][$field] ?? ''));
                if ($translated !== '') {
                    [$text, $l] = [$translated, $locale];
                } elseif ($kind !== null && $fr === self::defaultText($kind, $field)) {
                    [$text, $l] = [self::defaultText($kind, $field, $locale), $locale];
                }
            }
            $out[$field] = $text;
            $lang[$field] = $l;
        }
        $out['cta_url'] = isset($advocacy['cta_url']) && $advocacy['cta_url'] !== '' ? (string) $advocacy['cta_url'] : null;
        $out['lang'] = $lang;
        // Sources : date de publication rédigée dans la langue de la page ; une entrée incomplète est ignorée.
        if (is_array($advocacy['sources'] ?? null)) {
            $out['sources'] = [];
            foreach ($advocacy['sources'] as $source) {
                if (is_array($source) && isset($source['label'], $source['date'], $source['url'])) {
                    $out['sources'][] = ['label' => (string) $source['label'],
                        'date' => EventText::isoDate((string) $source['date'], 'day', $locale), 'url' => (string) $source['url']];
                }
            }
        }

        return $out;
    }
}

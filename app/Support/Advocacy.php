<?php

namespace App\Support;

use App\Http\Middleware\SetLocale;

/**
 * Message de soutien de VFF à une ligne (lines.advocacy), en français, anglais et
 * allemand.
 *
 * Stockage (JSON, rétrocompatible) : les champs historiques `title`, `message`,
 * `cta_label` portent le texte FRANÇAIS (langue de référence, lu tel quel par les
 * anciens lecteurs) ; `translations` = {en: {title, message, cta_label}, de: {…}}.
 *
 *   {"kind": "reopening", "title": "Rouvrons…", "message": "…", "cta_label": "…",
 *    "cta_url": "https://…", "translations": {"en": {"title": "…", …}, "de": {…}}}
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
     * @return array<string, mixed>
     */
    public static function build(string $kind, array $texts, ?string $url): array
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

        return $out;
    }

    /**
     * Message dans la langue de la page.
     *
     * @param  array<string, mixed>|null  $advocacy  valeur stockée
     * @return array{kind: ?string, title: ?string, message: ?string, cta_label: ?string, cta_url: ?string, lang: array<string, ?string>}|null
     *                                                                                                                                         lang : langue réelle de chaque champ (fr si repli, null si absent)
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

        return $out;
    }
}

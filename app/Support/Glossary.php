<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Glossaire du vocabulaire ferroviaire (page `/glossary`).
 *
 * Le registre (thème, sigle, renvois, fiche exemple de chaque terme) est versionné dans
 * `resources/content/glossary/terms.php` ; les textes (nom, développé du sigle, définition) sont dans
 * `lang/{fr,en,de}/glossary_terms.php` sous la même clé, avec repli sur le français. Les termes sortent
 * classés par ordre alphabétique (sans tenir compte des accents ni de la casse) ; le thème permet à la page de
 * les regrouper autrement.
 */
final class Glossary
{
    /** @var array{themes: list<string>, terms: array<string, array<string, mixed>>}|null */
    private static ?array $registry = null;

    /** @return list<string> Thèmes, dans l'ordre de la page. */
    public static function themes(): array
    {
        return self::registry()['themes'];
    }

    /**
     * Tous les termes, en `$locale`, du A au Z.
     *
     * @return list<array{slug: string, name: string, full: ?string, definition: string, theme: string, abbr: ?string, letter: string, see: list<array{slug: string, name: string}>, example: ?array{path: string, name?: string, page?: string}}>
     */
    public static function all(string $locale): array
    {
        $names = [];
        foreach (self::registry()['terms'] as $slug => $entry) {
            $names[$slug] = self::text($slug, $locale)['name'];
        }

        $terms = [];
        foreach (self::registry()['terms'] as $slug => $entry) {
            $text = self::text($slug, $locale);
            $terms[] = [
                'slug' => $slug,
                'name' => $text['name'],
                'full' => $text['full'],
                'definition' => $text['definition'],
                'theme' => $entry['theme'],
                'abbr' => $entry['abbr'] ?? null,
                'letter' => self::letter($text['name']),
                'see' => array_map(fn (string $other): array => ['slug' => $other, 'name' => $names[$other]], $entry['see']),
                'example' => $entry['example'] ?? null,
            ];
        }
        usort($terms, fn (array $a, array $b): int => strcmp(self::sortKey($a['name']), self::sortKey($b['name'])));

        return $terms;
    }

    /** Un terme par sa clé, ou null. */
    public static function find(string $slug, string $locale): ?array
    {
        foreach (self::all($locale) as $term) {
            if ($term['slug'] === $slug) {
                return $term;
            }
        }

        return null;
    }

    /** Lettre de classement d'un nom : sa première lettre, sans accent, en capitale. */
    public static function letter(string $name): string
    {
        return Str::upper(substr(self::sortKey($name), 0, 1));
    }

    private static function sortKey(string $name): string
    {
        return Str::lower(Str::ascii($name));
    }

    /** @return array{name: string, full: ?string, definition: string} */
    private static function text(string $slug, string $locale): array
    {
        $text = trans("glossary_terms.{$slug}", [], $locale);
        $text = is_array($text) ? $text : [];

        return [
            'name' => (string) ($text['name'] ?? $slug),
            'full' => isset($text['full']) ? (string) $text['full'] : null,
            'definition' => (string) ($text['definition'] ?? ''),
        ];
    }

    /** @return array{themes: list<string>, terms: array<string, array<string, mixed>>} */
    private static function registry(): array
    {
        return self::$registry ??= require resource_path('content/glossary/terms.php');
    }
}

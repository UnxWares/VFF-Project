<?php

namespace App\Services\Materials;

/**
 * Familles de séries et leurs versions, d'après database/data/material_families.php. Une famille (« Autorail
 * grande capacité ») et ses versions (X 76500, Z 27500, B 82500) ont chacune leur fiche et sont toutes liées
 * aux mêmes lignes : la fiche de ligne ne montre que les versions, avec le nom de la famille.
 *
 * Distinct de MaterialFamilies, qui lit les redirections des articles Wikipédia pour relier une phrase à la
 * bonne version à l'import.
 */
final class MaterialFamilyMembers
{
    /** @var array<string, list<string>>|null famille (slug) → versions (slugs) */
    private static ?array $families = null;

    /** @return array<string, list<string>> */
    public static function all(): array
    {
        return self::$families ??= (require database_path('data/material_families.php'));
    }

    /**
     * Famille d'une série (slug), ou null si la série n'est la version d'aucune famille.
     */
    public static function familyOf(string $versionSlug): ?string
    {
        foreach (self::all() as $family => $versions) {
            if (in_array($versionSlug, $versions, true)) {
                return $family;
            }
        }

        return null;
    }

    /** Oublie le fichier lu (tests). */
    public static function flush(): void
    {
        self::$families = null;
    }
}

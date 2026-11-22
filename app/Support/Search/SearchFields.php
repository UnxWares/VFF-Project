<?php

namespace App\Support\Search;

/**
 * Textes cherchés de chaque fiche, écrits une seule fois : les requêtes (CatalogSearch, StationDirectory,
 * LineDirectory, AccidentQuery, MaterialQuery) et les index trigrammes de la migration 2026_12_12_000000
 * utilisent exactement la même expression, sans quoi PostgreSQL n'emploie pas l'index.
 *
 * `$t` : préfixe de table (« s. », « lines. ») ; l'index est créé sans préfixe, ce qui revient au même.
 */
final class SearchFields
{
    /**
     * Clés SQL [nom, tous les noms] d'une fiche (`station`, `line`, `accident`, `material`) : les colonnes
     * calculées si la migration est passée, sinon la formule en ligne.
     *
     * @param  'station'|'line'|'accident'|'material'  $entity
     * @return array{0: string, 1: string}
     */
    public static function keys(string $entity, string $t): array
    {
        if (SearchText::sqlReady()) {
            return ["{$t}search_name", "{$t}search_all"];
        }
        [$name, $all] = match ($entity) {
            'station' => ["{$t}name", self::stationAll($t)],
            'line' => ["{$t}name", self::lineAll($t)],
            'accident' => ["{$t}name->>'fr'", self::accidentAll($t)],
            'material' => ["{$t}name", self::materialAll($t)],
        };

        return [SearchText::sqlKeyFormula($name, 'unaccent'), SearchText::sqlKeyFormula($all, 'unaccent')];
    }

    /** Gare : nom, commune, identifiant (élisions collées : « lisle-adam ») et autres noms (alt_names). */
    public static function stationAll(string $t = ''): string
    {
        return "{$t}name || ' ' || COALESCE({$t}city, '') || ' ' || {$t}slug || ' ' || ".self::altNames("{$t}alt_names");
    }

    /** Ligne : nom, compagnie d'origine, identifiant, autres noms, lignes fusionnées et sections citées. */
    public static function lineAll(string $t = ''): string
    {
        return "{$t}name || ' ' || COALESCE({$t}historical_operator, '') || ' ' || {$t}slug || ' ' || "
            .self::altNames("{$t}alt_names")." || ' ' || COALESCE(jsonb_path_query_array({$t}alt_names, 'lax \$.sections')::text, '')";
    }

    /**
     * Accident : nom dans les trois langues et commune. Pas l'identifiant : ses chiffres (la date) feraient
     * répondre « 1 » ou « 13 » à presque tous les accidents ; une date se cherche par CatalogSearch::dateCondition.
     */
    public static function accidentAll(string $t = 'accidents.'): string
    {
        return "COALESCE({$t}name->>'fr', '') || ' ' || COALESCE({$t}name->>'en', '') || ' ' || "
            ."COALESCE({$t}name->>'de', '') || ' ' || COALESCE({$t}commune_name, '')";
    }

    /** Matériel : nom, autres désignations, constructeur, numéros de la série. */
    public static function materialAll(string $t = 'materials.'): string
    {
        return "{$t}name || ' ' || COALESCE({$t}designations::text, '') || ' ' || COALESCE({$t}builder, '') || ' ' || "
            ."COALESCE({$t}fleet_numbers, '')";
    }

    /** Autres noms d'une colonne JSON : valeurs des clés `name` à toute profondeur et chaînes d'une liste. */
    private static function altNames(string $column): string
    {
        return "COALESCE(jsonb_path_query_array({$column}, 'lax \$.**.name')::text, '') || ' ' || "
            ."COALESCE(jsonb_path_query_array({$column}, 'lax \$[*] ? (@.type() == \"string\")')::text, '')";
    }
}

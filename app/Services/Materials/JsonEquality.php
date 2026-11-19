<?php

namespace App\Services\Materials;

/**
 * Égalité de valeurs JSON indépendante de l'ordre des clés : PostgreSQL (jsonb) réordonne les
 * clés des objets, ce qui ferait passer chaque fiche ou chaque lien réimporté pour modifié.
 */
final class JsonEquality
{
    public static function same(mixed $a, mixed $b): bool
    {
        return self::canonical($a) === self::canonical($b);
    }

    private static function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return is_float($value) && floor($value) === $value ? (int) $value : $value;
        }
        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(self::canonical(...), $value);
    }
}

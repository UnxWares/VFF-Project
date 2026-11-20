<?php

namespace App\Support;

/**
 * Booléen d'URL de l'API publique : `true` et `false` (toute casse) acceptés en plus de `1`, `0`,
 * `on`, `off` que comprend la règle de validation `boolean`. Toute autre valeur est rendue telle
 * quelle, pour que la validation la refuse (HTTP 422).
 */
final class QueryBoolean
{
    public static function cast(mixed $value): mixed
    {
        return is_string($value) ? match (strtolower($value)) {
            'true' => true,
            'false' => false,
            default => $value,
        } : $value;
    }

    /**
     * Applique cast() aux clés données d'un tableau de paramètres, quand elles sont présentes.
     *
     * @param  array<string, mixed>  $input
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    public static function castKeys(array $input, array $keys): array
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $input)) {
                $input[$key] = self::cast($input[$key]);
            }
        }

        return $input;
    }
}

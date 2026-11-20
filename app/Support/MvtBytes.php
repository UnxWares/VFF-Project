<?php

namespace App\Support;

/**
 * Tuile MVT renvoyée par PostgreSQL (bytea) : selon le pilote, une chaîne ou un flux.
 * Normalisée ici en chaîne binaire.
 */
final class MvtBytes
{
    public static function from(mixed $tile): string
    {
        if (is_resource($tile)) {
            return stream_get_contents($tile) ?: '';
        }

        return is_string($tile) ? $tile : '';
    }
}

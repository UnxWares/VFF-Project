<?php

namespace App\Support;

/**
 * Protection des CSV de relecture contre l'injection de formule : un tableur lit comme une formule toute
 * cellule de texte qui commence par =, +, -, @, une tabulation ou un retour chariot. On la neutralise avec
 * une apostrophe en tête. Les nombres (entiers, décimaux, y compris négatifs) restent intacts.
 */
final class CsvSafe
{
    /** Valeur d'une cellule, prête à passer à fputcsv. */
    public static function cell(mixed $value): string|int|float
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        $text = (string) $value;
        if ($text === '' || preg_match('/^[+-]?\d+(\.\d+)?$/', $text) === 1) {
            return $text;
        }

        return preg_match('/^[=+\-@\t\r]/', $text) === 1 ? "'".$text : $text;
    }

    /**
     * @param  array<array-key, mixed>  $row
     * @return list<string|int|float>
     */
    public static function row(array $row): array
    {
        return array_map(self::cell(...), array_values($row));
    }
}

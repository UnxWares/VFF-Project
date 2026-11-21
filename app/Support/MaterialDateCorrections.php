<?php

namespace App\Support;

/**
 * Dates de service corrigées d'après une source lue (database/data/material_date_corrections.php), par élément
 * Wikidata de la série. Lues une fois par processus.
 */
final class MaterialDateCorrections
{
    /** @var array<string, array<string, array{material: string, field: string, year: int, replaces: int|null, source: string, quote: string, reason: string}>>|null */
    private static ?array $corrections = null;

    /**
     * Correction du champ pour la série, si la valeur de Wikidata est bien celle que la correction remplace.
     *
     * @return array{material: string, field: string, year: int, replaces: int|null, source: string, quote: string, reason: string}|null
     */
    public static function for(string $qid, string $field, ?int $wikidataYear): ?array
    {
        if (self::$corrections === null) {
            self::$corrections = [];
            /** @var list<array{material: string, field: string, year: int, replaces: int|null, source: string, quote: string, reason: string}> $data */
            $data = require database_path('data/material_date_corrections.php');
            foreach ($data as $row) {
                self::$corrections[$row['material']][$row['field']] = $row;
            }
        }
        $row = self::$corrections[$qid][$field] ?? null;

        return $row !== null && $row['replaces'] === $wikidataYear ? $row : null;
    }
}

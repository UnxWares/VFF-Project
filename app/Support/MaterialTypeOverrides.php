<?php

namespace App\Support;

use App\Enums\MaterialType;

/**
 * Catégorie de matériel posée à la main (database/data/material_type_overrides.php), par élément Wikidata de la
 * série, pour celles que ni Wikidata ni l'article Wikipédia ne rangent. Lue une fois par processus.
 */
final class MaterialTypeOverrides
{
    /** @var array<string, array{material: string, type: string, source: string, quote: string, wikidata: string, name?: string}>|null */
    private static ?array $rows = null;

    /**
     * @return array{0: MaterialType, 1: string}|null catégorie et permalien de la source
     */
    public static function for(string $qid): ?array
    {
        if (self::$rows === null) {
            self::$rows = [];
            /** @var list<array{material: string, type: string, source: string, quote: string, wikidata: string, name?: string}> $data */
            $data = require database_path('data/material_type_overrides.php');
            foreach ($data as $row) {
                self::$rows[$row['material']] = $row;
            }
        }
        $row = self::$rows[$qid] ?? null;

        return $row === null ? null : [MaterialType::from($row['type']), $row['source']];
    }

    /** Nom posé à la main pour une série sans libellé français ni anglais dans Wikidata. */
    public static function nameFor(string $qid): ?string
    {
        self::for($qid);

        return self::$rows[$qid]['name'] ?? null;
    }
}

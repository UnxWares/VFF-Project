<?php

namespace App\Support;

/**
 * Valeurs de fiche (dates de service, nombre construit, constructeur) lues à la main dans une source citée, pour les séries
 * que ni Wikidata ni l'infobox de leur article ne renseignent (database/data/material_date_supplements.php), par élément
 * Wikidata. Appliquées par WikidataMaterialMapper seulement quand Wikidata ne donne pas la valeur. Lues une fois par processus.
 */
final class MaterialDateSupplements
{
    /** @var array<string, array<string, array{material: string, field: string, value: int|string, basis: string, source: string, revid: int|null, quote: string}>>|null */
    private static ?array $rows = null;

    /**
     * @return array{material: string, field: string, value: int|string, basis: string, source: string, revid: int|null, quote: string}|null
     */
    public static function for(string $qid, string $field): ?array
    {
        if (self::$rows === null) {
            self::$rows = [];
            /** @var list<array{material: string, field: string, value: int|string, basis: string, source: string, revid: int|null, quote: string}> $data */
            $data = require database_path('data/material_date_supplements.php');
            foreach ($data as $row) {
                self::$rows[$row['material']][$row['field']] = $row;
            }
        }

        return self::$rows[$qid][$field] ?? null;
    }
}

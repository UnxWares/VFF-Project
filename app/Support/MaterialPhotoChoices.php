<?php

namespace App\Support;

/**
 * Photos de séries choisies après relecture (database/data/material_photo_choices.php), par élément Wikidata
 * de la série : premier candidat de repli quand Wikidata n'a pas d'image (P18). Lues une fois par processus.
 */
final class MaterialPhotoChoices
{
    /** @var array<string, array{material: string, file: string, found_in: string, shows: string}>|null */
    private static ?array $choices = null;

    /** @return array{material: string, file: string, found_in: string, shows: string}|null */
    public static function for(string $qid): ?array
    {
        if (self::$choices === null) {
            /** @var list<array{material: string, file: string, found_in: string, shows: string}> $data */
            $data = require database_path('data/material_photo_choices.php');
            self::$choices = array_column($data, null, 'material');
        }

        return self::$choices[$qid] ?? null;
    }
}

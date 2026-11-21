<?php

namespace App\Support;

/**
 * Photos de séries écartées après relecture (database/data/material_photo_exclusions.php), par nom de fichier
 * Commons sans « File: ». Lues une fois par processus.
 */
final class MaterialPhotoExclusions
{
    /** @var array<string, string>|null nom de fichier → motif */
    private static ?array $files = null;

    /** Le fichier (avec ou sans « File: ») est-il écarté ? */
    public static function excludes(?string $file): bool
    {
        if ($file === null || $file === '') {
            return false;
        }
        if (self::$files === null) {
            /** @var list<array{file: string, material: string, reason: string}> $data */
            $data = require database_path('data/material_photo_exclusions.php');
            self::$files = array_column($data, 'reason', 'file');
        }

        return isset(self::$files[str_replace('_', ' ', (string) preg_replace('/^File:/u', '', $file))]);
    }
}

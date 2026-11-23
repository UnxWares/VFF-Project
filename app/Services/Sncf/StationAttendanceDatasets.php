<?php

namespace App\Services\Sncf;

/**
 * Les deux jeux SNCF « Fréquentation en gares » (Gares & Connexions, ODbL) et la lecture de leurs colonnes.
 *
 * `frequentation-gares-archive` (2015 à 2024, plus mis à jour) et `frequentation-gares` (2025 et les suivantes) :
 * SNCF précise que le changement de sources et de méthode de calcul en 2025 rend les deux séries non directement
 * comparables. D'où `series`.
 */
final class StationAttendanceDatasets
{
    public const ARCHIVE = 'frequentation-gares-archive';

    public const CURRENT = 'frequentation-gares';

    public const PUBLISHER = 'Gares & Connexions';

    public const SERIES_ARCHIVE = '2015-2024';

    public const SERIES_CURRENT = '2025';

    /**
     * Années lues sur une ligne du jeu : [année => [voyageurs, voyageurs et non-voyageurs]]. Les colonnes se
     * nomment `total_voyageurs_AAAA` et `total_voyageurs_non_voyageurs_AAAA` (une seule fait exception :
     * `totalvoyageurs2017`). Une valeur absente ou négative est écartée.
     *
     * @param  array<string, mixed>  $record
     * @return array<int, array{0: ?int, 1: ?int}>
     */
    public static function years(array $record): array
    {
        $years = [];
        foreach ($record as $key => $value) {
            if (! preg_match('/^(total_?voyageurs_non_voyageurs_|total_?voyageurs_?)(\d{4})$/', (string) $key, $m)) {
                continue;
            }
            $both = str_contains($m[1], 'non_voyageurs');
            $years[(int) $m[2]] ??= [null, null];
            $years[(int) $m[2]][$both ? 1 : 0] = is_numeric($value) && $value >= 0 ? (int) round((float) $value) : null;
        }
        ksort($years);

        return array_filter($years, fn (array $v): bool => $v[0] !== null || $v[1] !== null);
    }

    /** Série d'une année : 2015 à 2024 pour l'archive, 2025 et après pour le jeu courant. */
    public static function series(int $year): string
    {
        return $year <= 2024 ? self::SERIES_ARCHIVE : self::SERIES_CURRENT;
    }
}

<?php

namespace App\Enums;

/**
 * Changement d'une année de desserte (station_lines.first_year ou last_year) entre deux calculs
 * du rattachement gares-lignes (vff:attach-stations --dry-run, App\Services\StationLinkChanges).
 *
 * Pour la fin de desserte, une année vide veut dire « encore desservie aujourd'hui » : une fin
 * ajoutée termine la desserte, une fin retirée la prolonge jusqu'à aujourd'hui.
 */
enum ServiceYearChange: string
{
    /** Aucune année avant, une année après. */
    case Added = 'added';

    /** Une année avant, aucune après. */
    case Removed = 'removed';

    /** Année plus ancienne qu'avant. */
    case Earlier = 'earlier';

    /** Année plus récente qu'avant. */
    case Later = 'later';

    /** Changement entre deux valeurs (null si l'année n'a pas changé). */
    public static function between(?int $before, ?int $after): ?self
    {
        return match (true) {
            $before === $after => null,
            $before === null => self::Added,
            $after === null => self::Removed,
            $after < $before => self::Earlier,
            default => self::Later,
        };
    }

    /** Libellé français pour la sortie de la commande et le rapport CSV. */
    public function label(): string
    {
        return match ($this) {
            self::Added => 'ajoutée',
            self::Removed => 'retirée',
            self::Earlier => 'avancée',
            self::Later => 'reculée',
        };
    }
}

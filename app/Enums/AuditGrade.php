<?php

namespace App\Enums;

/**
 * Note d'un contrôle de l'audit des tronçons (table segment_audits, docs/AUDIT-TRONCONS.md).
 *
 *   good    : sûr (les sources sont d'accord, l'écart est dans la tolérance)
 *   fair    : à revoir (accord partiel, écart au-delà de la cible, source peu précise)
 *   poor    : faux ou contredit (une source fiable dit autre chose, défaut de géométrie)
 *   unknown : rien pour juger (aucune source, aucune voie de référence indépendante)
 */
enum AuditGrade: string
{
    case Good = 'good';
    case Fair = 'fair';
    case Poor = 'poor';
    case Unknown = 'unknown';

    /** Gravité, pour trier et prendre la pire note : unknown passe après fair, avant good. */
    public function severity(): int
    {
        return match ($this) {
            self::Good => 0,
            self::Unknown => 1,
            self::Fair => 2,
            self::Poor => 3,
        };
    }

    /** Points de la note dans le score (0 à 4 par contrôle, quatre contrôles : 0 à 16, ramené à 0 à 100). */
    public function points(): int
    {
        return match ($this) {
            self::Good => 0,
            self::Unknown => 1,
            self::Fair => 2,
            self::Poor => 4,
        };
    }

    /** @param  iterable<self>  $grades */
    public static function worst(iterable $grades): self
    {
        $worst = self::Good;
        foreach ($grades as $grade) {
            if ($grade->severity() > $worst->severity()) {
                $worst = $grade;
            }
        }

        return $worst;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $g): string => $g->value, self::cases());
    }
}

<?php

namespace App\Enums;

/**
 * Groupe d'un passage à niveau, d'après sa classe SNCF Réseau (champ « Mnémo type IF » du jeu
 * `liste-des-passages-a-niveau`, « CLASSE 17 »). Les définitions officielles des classes sont dans
 * la description du jeu ; les groupes servent au résumé de la fiche ligne et aux teintes du calque.
 */
enum LevelCrossingGroup: string
{
    /** PN publics pour voitures à barrières ou demi-barrières automatiques (SAL 2, SAL 2B, SAL 4) : classes 17 à 19. */
    case Automatic = 'automatic';

    /** PN publics gardés : protection par un agent, barrières manœuvrées sur place ou à distance : classes 10 à 16. */
    case Guarded = 'guarded';

    /** PN publics pour voitures sans barrières, avec ou sans feux clignotants (SAL 0) : classes 20 et 21. */
    case NoBarrier = 'no_barrier';

    /** PN publics isolés pour piétons, avec ou sans portillons : classes 31 et 32. */
    case Pedestrian = 'pedestrian';

    /** PN privés, pour voitures ou pour piétons : classes 41 à 46. */
    case Private = 'private';

    /**
     * PN secondaire (classe 00) : un même passage situé sur plusieurs lignes n'a sa classe que sur
     * l'une d'elles (le « PN père ») ; sur les autres, il est noté 00 et renvoie au père dans le
     * libellé de l'obstacle. Compté sur la fiche de la ligne, jamais sur la carte (déjà dessiné).
     */
    case Secondary = 'secondary';

    /** Ordre d'affichage (résumé, légende). */
    public const ORDER = ['automatic', 'guarded', 'no_barrier', 'pedestrian', 'private', 'secondary'];

    /** Classes officielles connues (description du jeu SNCF Réseau, lue le 20/11/2026). */
    public const CLASSES = [0, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 31, 32, 41, 42, 43, 44, 45, 46];

    /** Groupe d'une classe ; null pour une classe absente de la liste officielle. */
    public static function fromClass(int $class): ?self
    {
        return match (true) {
            $class === 0 => self::Secondary,
            $class >= 10 && $class <= 16 => self::Guarded,
            $class >= 17 && $class <= 19 => self::Automatic,
            $class === 20, $class === 21 => self::NoBarrier,
            $class === 31, $class === 32 => self::Pedestrian,
            $class >= 41 && $class <= 46 => self::Private,
            default => null,
        };
    }

    /**
     * Classes du groupe.
     *
     * @return list<int>
     */
    public function classes(): array
    {
        return array_values(array_filter(self::CLASSES, fn (int $c): bool => self::fromClass($c) === $this));
    }

    /** Classe tirée du libellé SNCF (« CLASSE 17 » → 17) ; null si illisible. */
    public static function parseClass(?string $mnemo): ?int
    {
        return $mnemo !== null && preg_match('/^CLASSE\s+(\d{1,2})$/', trim($mnemo), $m) ? (int) $m[1] : null;
    }

    /** Expression SQL de la classe (entier) à partir de la colonne `mnemo`. */
    public static function classSql(string $column): string
    {
        return "CASE WHEN {$column} ~ '^CLASSE [0-9]{1,2}$' THEN substring({$column} from 8)::int END";
    }

    /** Expression SQL du groupe à partir d'une expression de classe entière (même règle que fromClass). */
    public static function groupSql(string $class): string
    {
        return "CASE
            WHEN {$class} = 0 THEN '".self::Secondary->value."'
            WHEN {$class} BETWEEN 10 AND 16 THEN '".self::Guarded->value."'
            WHEN {$class} BETWEEN 17 AND 19 THEN '".self::Automatic->value."'
            WHEN {$class} IN (20, 21) THEN '".self::NoBarrier->value."'
            WHEN {$class} IN (31, 32) THEN '".self::Pedestrian->value."'
            WHEN {$class} BETWEEN 41 AND 46 THEN '".self::Private->value."'
        END";
    }
}

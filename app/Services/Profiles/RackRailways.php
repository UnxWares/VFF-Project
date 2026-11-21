<?php

namespace App\Services\Profiles;

/**
 * Lignes à crémaillère (`database/data/rack_railways.php`, déclarées d'après Wikipédia avec le
 * permalien de la révision lue). Sur ces lignes, la déclivité mesurée n'est pas publiée : le
 * calcul du profil écarte toute pente au-delà de ProfileStats::MAX_PLAUSIBLE_GRADE (120 ‰), et
 * la valeur restante (41 ‰ pour Chamonix - Montenvers, 220 ‰ en réalité) serait fausse. Le profil
 * donne à la place la déclivité publiée par la source.
 */
final class RackRailways
{
    /** Types de crémaillère connus. */
    public const SYSTEMS = ['strub', 'abt', 'riggenbach'];

    /** Étendue : sur tout le parcours, sur une partie ; null quand la source ne le dit pas. */
    public const EXTENTS = ['full', 'partial'];

    /** Licence des faits repris (registre des droits, fournisseur `wikipedia`). */
    public const LICENSE = 'CC BY-SA 4.0';

    /** @var array<string, array{system: string, extent: string|null, max_grade_permil: int, braking_only: bool, source_url: string, source_title: string, quote: string}>|null */
    private static ?array $entries = null;

    /**
     * Toutes les lignes déclarées, par slug.
     *
     * @return array<string, array{system: string, extent: string|null, max_grade_permil: int, braking_only: bool, source_url: string, source_title: string, quote: string}>
     */
    public static function all(): array
    {
        return self::$entries ??= require database_path('data/rack_railways.php');
    }

    public static function isRack(string $slug): bool
    {
        return array_key_exists($slug, self::all());
    }

    /**
     * Fiche publiée dans le profil (`rack`), null pour une ligne à adhérence.
     *
     * @return array{system: string, extent: string|null, max_grade_permil: int, braking_only: bool,
     *     source: array{provider: string, title: string, url: string, license: string}}|null
     */
    public static function present(string $slug): ?array
    {
        $entry = self::all()[$slug] ?? null;
        if ($entry === null) {
            return null;
        }

        return [
            'system' => $entry['system'],
            'extent' => $entry['extent'],
            'max_grade_permil' => $entry['max_grade_permil'],
            'braking_only' => $entry['braking_only'],
            'source' => [
                'provider' => 'wikipedia',
                'title' => $entry['source_title'],
                'url' => $entry['source_url'],
                'license' => self::LICENSE,
            ],
        ];
    }
}

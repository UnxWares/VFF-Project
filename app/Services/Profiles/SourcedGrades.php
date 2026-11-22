<?php

namespace App\Services\Profiles;

/**
 * Déclivités les plus fortes publiées par une source écrite (`database/data/sourced_grades.php`,
 * champ `pente` de l'infobox de Wikipédia, avec le permalien de la révision lue). Elles ne
 * remplacent jamais une mesure : le profil ne les donne que lorsque sa propre déclivité n'est pas
 * publiée parce que l'altitude vient du terrain seul, d'un tunnel ou de points interpolés
 * (ProfileStats::summarize). Une ligne à crémaillère garde sa fiche à elle (RackRailways).
 *
 * Contrôle du 22/11/2026 sur les 235 lignes du fichier dont la déclivité est aussi mesurée sur la
 * voie : 141 à 2 ‰ près, 210 à 5 ‰ près ; la mesure porte sur une rampe d'un kilomètre, l'infobox
 * souvent sur un point (storage/app/reports/agents/aprem-2111/profil-declivites.md).
 */
final class SourcedGrades
{
    /** Motifs de non-publication pour lesquels la valeur de la source est donnée. */
    public const REPLACES = ['terrain_only', 'tunnel', 'interpolated'];

    /** Licence des faits repris (registre des droits, fournisseur `wikipedia`). */
    public const LICENSE = 'CC BY-SA 4.0';

    /** @var array<string, array{max_grade_permil: int|float, source_url: string, source_title: string, quote: string}>|null */
    private static ?array $entries = null;

    /**
     * Toutes les lignes déclarées, par slug.
     *
     * @return array<string, array{max_grade_permil: int|float, source_url: string, source_title: string, quote: string}>
     */
    public static function all(): array
    {
        return self::$entries ??= require database_path('data/sourced_grades.php');
    }

    /**
     * Déclivité de la source publiée dans le profil (`sourced_grade`), ou null : ligne absente du
     * fichier, ou déclivité mesurée publiée (`$withheld` nul), ou ligne à crémaillère.
     *
     * @return array{max_grade_permil: float, source: array{provider: string, title: string, url: string, license: string}}|null
     */
    public static function present(string $slug, ?string $withheld): ?array
    {
        $entry = self::all()[$slug] ?? null;
        if ($entry === null || ! in_array($withheld, self::REPLACES, true)) {
            return null;
        }

        return [
            'max_grade_permil' => (float) $entry['max_grade_permil'],
            'source' => [
                'provider' => 'wikipedia',
                'title' => $entry['source_title'],
                'url' => $entry['source_url'],
                'license' => self::LICENSE,
            ],
        ];
    }
}

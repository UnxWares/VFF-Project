<?php

namespace App\Services\Profiles;

/**
 * Déclivités les plus fortes publiées par une source écrite. Elles ne remplacent jamais une
 * mesure : le profil ne les donne que lorsque sa propre déclivité n'est pas publiée parce que
 * l'altitude vient du terrain seul, d'un tunnel ou de points interpolés (ProfileStats::summarize).
 * Une ligne à crémaillère garde sa fiche à elle (RackRailways).
 *
 * Deux fournisseurs, un fichier chacun (FILES), le second remplaçant le premier pour une même ligne :
 *   - `wikipedia` (`database/data/sourced_grades.php`) : champ `pente` de l'infobox ou texte de
 *     l'article de Wikipédia en français, avec le permalien de la révision lue (CC BY-SA 4.0) ;
 *   - `sncf` (`database/data/sourced_grades_sncf.php`, écrit par vff:import-sncf-grades) : valeur
 *     la plus forte du jeu « Caractéristique des voies et déclivité » de SNCF Réseau (ODbL 1.0),
 *     retenue selon les règles de SncfGrades, avec la part de la ligne couverte par le jeu.
 *
 * Contrôle du 22/11/2026 sur les 235 lignes de Wikipédia dont la déclivité est aussi mesurée sur la
 * voie : 141 à 2 ‰ près, 210 à 5 ‰ près ; la mesure porte sur une rampe d'un kilomètre, l'infobox
 * souvent sur un point (storage/app/reports/agents/aprem-2111/profil-declivites.md).
 */
final class SourcedGrades
{
    /** Motifs de non-publication pour lesquels la valeur de la source est donnée. */
    public const REPLACES = ['terrain_only', 'tunnel', 'interpolated'];

    /** Licence des faits repris de Wikipédia (fournisseur `wikipedia`, le premier fichier). */
    public const LICENSE = 'CC BY-SA 4.0';

    /** Fichiers lus, dans l'ordre : une entrée d'un fichier suivant remplace celle d'avant. */
    public const FILES = ['data/sourced_grades.php', 'data/sourced_grades_sncf.php'];

    /**
     * Fournisseurs admis (clés du registre des droits) : licence attendue et attribution exigée.
     *
     * @var array<string, array{license: string, attribution: string}>
     */
    public const PROVIDERS = [
        'wikipedia' => ['license' => self::LICENSE, 'attribution' => 'Wikipédia'],
        'sncf' => ['license' => SncfGrades::LICENSE, 'attribution' => SncfGrades::ATTRIBUTION],
    ];

    /** @var array<string, array{max_grade_permil: int|float, provider: string, license: string, source_url: string, source_title: string, quote: string, attribution?: string, covered_share?: float}>|null */
    private static ?array $entries = null;

    /**
     * Toutes les lignes déclarées, par slug, fichiers fusionnés.
     *
     * @return array<string, array{max_grade_permil: int|float, provider: string, license: string, source_url: string, source_title: string, quote: string, attribution?: string, covered_share?: float}>
     */
    public static function all(): array
    {
        if (self::$entries === null) {
            $entries = [];
            foreach (self::FILES as $file) {
                $path = database_path($file);
                if (is_file($path)) {
                    $entries = array_replace($entries, require $path);
                }
            }
            self::$entries = $entries;
        }

        return self::$entries;
    }

    /** Oublie les fichiers lus (après une réécriture par vff:import-sncf-grades, et dans les tests). */
    public static function flush(): void
    {
        self::$entries = null;
    }

    /**
     * Déclivité de la source publiée dans le profil (`sourced_grade`), ou null : ligne absente des
     * fichiers, ou déclivité mesurée publiée (`$withheld` nul), ou ligne à crémaillère.
     * `covered_share` : part de la ligne décrite par la source (0 à 1), null quand elle porte sur
     * toute la ligne (Wikipédia).
     *
     * @return array{max_grade_permil: float, covered_share: float|null, source: array{provider: string, title: string, url: string, license: string, attribution: string}}|null
     */
    public static function present(string $slug, ?string $withheld): ?array
    {
        $entry = self::all()[$slug] ?? null;
        if ($entry === null || ! in_array($withheld, self::REPLACES, true)) {
            return null;
        }

        return [
            'max_grade_permil' => (float) $entry['max_grade_permil'],
            'covered_share' => isset($entry['covered_share']) ? (float) $entry['covered_share'] : null,
            'source' => [
                'provider' => $entry['provider'],
                'title' => $entry['source_title'],
                'url' => $entry['source_url'],
                'license' => $entry['license'],
                'attribution' => $entry['attribution'] ?? self::PROVIDERS[$entry['provider']]['attribution'] ?? $entry['provider'],
            ],
        ];
    }
}

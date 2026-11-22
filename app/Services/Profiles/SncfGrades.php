<?php

namespace App\Services\Profiles;

/**
 * Déclivités du jeu SNCF Open Data « Caractéristique des voies et déclivité » (SNCF Réseau, ODbL),
 * lues par la commande vff:import-sncf-grades : agrégation par ligne du RFN, contrôle de
 * cohérence, puis écriture de `database/data/sourced_grades_sncf.php` (lu par SourcedGrades).
 *
 * Le jeu ne décrit qu'une partie des voies (segments de 50 m à quelques kilomètres, rampes et pentes
 * en ‰ ; la moitié des lignes est couverte à moins de 7 %). Sa valeur la plus forte n'est donc la
 * déclivité maximale de la ligne que si les segments en couvrent une part suffisante : la part
 * couverte est gardée avec la valeur et servie par l'API.
 *
 * Règles (brief du 22/11/2026) :
 *   - une valeur au-dessus de MAX_PLAIN_PERMIL est écartée, sauf sur une ligne de montagne connue
 *     (Wikipédia ou mesure sur la voie à MOUNTAIN_PERMIL au moins) ;
 *   - une valeur de plus de MAX_RATIO fois la déclivité mesurée (sur la voie, sinon sur le terrain,
 *     qui la surestime) est écartée ;
 *   - sans mesure du profil, rien ne permet le contrôle : la valeur n'est pas reprise ;
 *   - face à Wikipédia : valeurs proches, Wikipédia est gardée (elle porte sur toute la ligne) ;
 *     valeurs divergentes, la SNCF n'est retenue que si elle est cohérente avec la voie et qu'elle
 *     dépasse Wikipédia (une rampe plus forte est relevée) ou couvre presque toute la ligne ;
 *   - la valeur qui remplace Wikipédia doit porter sur une portion d'au moins MIN_STEEP_M mètres
 *     (segments à cette valeur, réunis), sauf couverture quasi totale : un segment de 50 m n'est pas
 *     la rampe de la ligne (36,2 ‰ sur 50 m à Mézidon - Trouville-Deauville, contre 25 ‰ pour Wikipédia).
 */
final class SncfGrades
{
    public const DATASET = 'caracteristique-des-voies-et-declivite';

    public const LICENSE = 'ODbL 1.0';

    public const ATTRIBUTION = 'SNCF Réseau';

    public const TITLE = 'SNCF Réseau, jeu « Caractéristique des voies et déclivité »';

    /** Page du jeu filtrée sur une ligne (lien servi avec la valeur). */
    public const PAGE = 'https://data.sncf.com/explore/dataset/caracteristique-des-voies-et-declivite/table/?refine.code_ligne=';

    /** Au-delà, valeur écartée hors ligne de montagne connue (90 ‰ sur Hirson - Amagne-Lucquy). */
    public const MAX_PLAIN_PERMIL = 60.0;

    /** Wikipédia ou mesure sur la voie à partir de laquelle la ligne compte comme ligne de montagne. */
    public const MOUNTAIN_PERMIL = 40.0;

    /** Valeur écartée si elle dépasse ce multiple de la déclivité mesurée. */
    public const MAX_RATIO = 3.0;

    /** Part de la ligne couverte par les segments, par défaut, pour reprendre la valeur. */
    public const MIN_COVERAGE = 0.1;

    /** Part couverte à partir de laquelle la valeur SNCF vaut pour toute la ligne. */
    public const FULL_COVERAGE = 0.9;

    /** Longueur minimale, en mètres, de la portion la plus raide pour qu'elle remplace la valeur de Wikipédia. */
    public const MIN_STEEP_M = 500;

    /** Écart toléré avec Wikipédia : 2 ‰, ou 20 % de la valeur de Wikipédia si c'est plus. */
    public const AGREE_PERMIL = 2.0;

    public const AGREE_SHARE = 0.2;

    /**
     * Segments RAMPE et PENTE regroupés par code de ligne : valeur la plus forte (et son segment),
     * nombre de segments et longueur couverte (réunion des intervalles de PK, toutes voies
     * confondues). Les segments aux PK non numériques (PK à lettre de la région parisienne) comptent
     * pour la valeur, pas pour la longueur.
     *
     * @param  iterable<array<string, mixed>>  $records
     * @return array<int|string, array{max_grade_permil: float, type: string, track: string, pk_from: string, pk_to: string, segments: int, covered_m: int, steep_m: int, name: string}> clé : code de ligne (entier pour PHP quand il ne commence pas par 0)
     */
    public static function aggregate(iterable $records): array
    {
        $lines = [];
        $intervals = [];
        $steep = [];
        foreach ($records as $r) {
            $code = trim((string) ($r['code_ligne'] ?? ''));
            $type = (string) ($r['type'] ?? '');
            $value = $r['valeur'] ?? null;
            if (! preg_match('/^\d{6}$/', $code) || ! in_array($type, ['RAMPE', 'PENTE'], true) || ! is_numeric($value)) {
                continue;
            }
            $value = abs((float) $value);
            $current = $lines[$code] ?? null;
            if ($current === null || $value > $current['max_grade_permil']) {
                $lines[$code] = [
                    'max_grade_permil' => $value,
                    'type' => $type,
                    'track' => (string) ($r['code_voie'] ?? ''),
                    'pk_from' => (string) ($r['pkd'] ?? ''),
                    'pk_to' => (string) ($r['pkf'] ?? ''),
                    'segments' => ($current['segments'] ?? 0) + 1,
                    'covered_m' => 0,
                    'steep_m' => 0,
                    'name' => (string) ($r['lib_ligne'] ?? ''),
                ];
            } else {
                $lines[$code]['segments']++;
            }
            $a = self::pk($r['pkd'] ?? null);
            $b = self::pk($r['pkf'] ?? null);
            if ($value === $lines[$code]['max_grade_permil']) {
                // Portion la plus raide : les segments à la valeur maximale (la liste repart de zéro à chaque nouveau maximum).
                if ($current === null || $value > $current['max_grade_permil']) {
                    $steep[$code] = [];
                }
                if ($a !== null && $b !== null && $a !== $b) {
                    $steep[$code][] = [min($a, $b), max($a, $b)];
                }
            }
            if ($a !== null && $b !== null && $a !== $b) {
                $intervals[$code][] = [min($a, $b), max($a, $b)];
            }
        }
        foreach ($intervals as $code => $list) {
            $lines[$code]['covered_m'] = self::union($list);
            $lines[$code]['steep_m'] = self::union($steep[$code] ?? []);
        }
        ksort($lines);

        return $lines;
    }

    /**
     * Décision pour une ligne. `$measure` : déclivité mesurée par le profil, sur la voie (`track`,
     * publiée) ou sur le terrain (`terrain`, non publiée), null sans profil exploitable.
     *
     * `steep_m` (longueur de la portion la plus raide) est facultatif : absent, la longueur minimale n'est pas contrôlée.
     * `$minSteepM` : longueur minimale de cette portion pour remplacer Wikipédia (0 : pas de contrôle).
     *
     * @param  array{max_grade_permil: float, covered_m: int, steep_m?: int}  $sncf
     * @param  array{value: float, kind: string}|null  $measure
     * @return array{status: string, retained: bool, covered_share: float|null, steep_m: int|null}
     */
    public static function decide(array $sncf, ?int $lengthM, ?float $wikipedia, ?array $measure, float $minCoverage = self::MIN_COVERAGE, int $minSteepM = self::MIN_STEEP_M): array
    {
        $value = $sncf['max_grade_permil'];
        $share = $lengthM !== null && $lengthM > 0 ? round(min(1.0, $sncf['covered_m'] / $lengthM), 3) : null;
        $out = fn (string $status, bool $retained = false): array => ['status' => $status, 'retained' => $retained, 'covered_share' => $share, 'steep_m' => $sncf['steep_m'] ?? null];

        $mountain = ($wikipedia !== null && $wikipedia >= self::MOUNTAIN_PERMIL)
            || ($measure !== null && $measure['kind'] === 'track' && $measure['value'] >= self::MOUNTAIN_PERMIL);
        if ($value > self::MAX_PLAIN_PERMIL && ! $mountain) {
            return $out('rejected_above_limit');
        }
        if ($value > ProfileStats::MAX_PLAUSIBLE_GRADE * 1000) {
            return $out('rejected_above_limit');
        }
        if ($measure !== null && $measure['value'] > 0 && $value > self::MAX_RATIO * $measure['value']) {
            return $out('rejected_ratio');
        }
        if ($value < 1.0) {
            return $out('rejected_below_one');
        }
        if ($share === null || $share < $minCoverage) {
            return $out('rejected_coverage');
        }
        if ($measure === null) {
            return $out('rejected_unchecked');
        }
        if ($wikipedia === null) {
            return $out('retained', true);
        }
        if (abs($value - $wikipedia) <= max(self::AGREE_PERMIL, self::AGREE_SHARE * $wikipedia)) {
            return $out('kept_wikipedia_agree');
        }
        if ($share >= self::FULL_COVERAGE) {
            return $out('retained_over_wikipedia', true);
        }
        if ($value > $wikipedia) {
            return isset($sncf['steep_m']) && $sncf['steep_m'] < $minSteepM
                ? $out('kept_wikipedia_short')
                : $out('retained_over_wikipedia', true);
        }

        return $out('kept_wikipedia_partial');
    }

    /**
     * Entrée du fichier de données pour une valeur retenue.
     *
     * @param  array{max_grade_permil: float, type: string, track: string, pk_from: string, pk_to: string, segments: int}  $sncf
     * @return array{max_grade_permil: float, provider: string, license: string, attribution: string, source_url: string, source_title: string, quote: string, covered_share: float}
     */
    public static function entry(string $code, array $sncf, float $share): array
    {
        $percent = rtrim(rtrim(number_format($share * 100, 1, ',', ''), '0'), ',');

        return [
            'max_grade_permil' => round($sncf['max_grade_permil'], 2),
            'provider' => 'sncf',
            'license' => self::LICENSE,
            'attribution' => self::ATTRIBUTION,
            'source_url' => self::PAGE.$code,
            'source_title' => self::TITLE,
            'quote' => sprintf('%s %s ‰ ; voie %s ; PK %s à %s ; %d segment%s, %s %% de la ligne',
                $sncf['type'], self::number($sncf['max_grade_permil']), $sncf['track'], $sncf['pk_from'], $sncf['pk_to'],
                $sncf['segments'], $sncf['segments'] > 1 ? 's' : '', $percent),
            'covered_share' => $share,
        ];
    }

    /**
     * Contenu du fichier `database/data/sourced_grades_sncf.php`, entrées triées par slug : deux
     * imports des mêmes données donnent le même fichier, octet pour octet.
     *
     * @param  array<string, array<string, mixed>>  $entries
     */
    public static function render(array $entries, string $processedAt): string
    {
        ksort($entries);
        $body = '';
        foreach ($entries as $slug => $entry) {
            $body .= '    '.var_export($slug, true)." => [\n";
            foreach ($entry as $key => $value) {
                $body .= '        '.var_export($key, true).' => '.var_export($value, true).",\n";
            }
            $body .= "    ],\n";
        }

        return <<<PHP
        <?php

        /**
         * Déclivités les plus fortes du jeu SNCF Open Data « Caractéristique des voies et déclivité »
         * (SNCF Réseau, ODbL 1.0), une par ligne du RFN, jeu traité par SNCF le {$processedAt}.
         * Fichier ÉCRIT par `php artisan vff:import-sncf-grades` : ne pas le modifier à la main.
         * Lu par App\Services\Profiles\SourcedGrades après database/data/sourced_grades.php
         * (Wikipédia), qu'il remplace pour une même ligne ; règles de reprise dans
         * App\Services\Profiles\SncfGrades.
         *
         * Le jeu ne décrit qu'une partie des voies : `covered_share` donne la part de la ligne
         * couverte par ses segments (0 à 1), servie avec la valeur.
         *
         * @return array<string, array{max_grade_permil: int|float, provider: string, license: string, attribution: string, source_url: string, source_title: string, quote: string, covered_share: float}>
         */
        return [
        {$body}];

        PHP;
    }

    /** PK SNCF « 019+663 » en mètres (« 000-771 » : -771) ; null pour un PK à lettre. */
    public static function pk(mixed $raw): ?int
    {
        if (! is_string($raw) || ! preg_match('/^(\d+)([+-])(\d{1,3})$/', trim($raw), $m)) {
            return null;
        }
        $base = (int) $m[1] * 1000;

        return $m[2] === '-' ? $base - (int) $m[3] : $base + (int) $m[3];
    }

    /** @param  list<array{0: int, 1: int}>  $intervals */
    private static function union(array $intervals): int
    {
        sort($intervals);
        $total = 0;
        $current = null;
        foreach ($intervals as [$a, $b]) {
            if ($current !== null && $a <= $current[1]) {
                $current[1] = max($current[1], $b);

                continue;
            }
            if ($current !== null) {
                $total += $current[1] - $current[0];
            }
            $current = [$a, $b];
        }

        return $total + ($current === null ? 0 : $current[1] - $current[0]);
    }

    private static function number(float $value): string
    {
        return str_replace('.', ',', (string) round($value, 2));
    }
}

<?php

namespace App\Console\Commands;

use App\Services\Profiles\MeasuredGrade;
use App\Services\Profiles\RackRailways;
use App\Services\Profiles\SncfGrades;
use App\Services\Profiles\SourcedGrades;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Déclivités maximales du jeu SNCF Open Data « Caractéristique des voies et déclivité » (SNCF
 * Réseau, ODbL), confrontées à Wikipédia (database/data/sourced_grades.php) et au profil mesuré en
 * base (line_profiles, line_profile_points), puis écrites dans database/data/sourced_grades_sncf.php
 * (lu par App\Services\Profiles\SourcedGrades). La base n'est jamais modifiée : seules des lectures.
 *
 * Le jeu est téléchargé une fois par l'API (deux requêtes : métadonnées, dont la licence vérifiée,
 * puis export JSON), puis gardé dans storage/app/sources/sncf-declivites/ (dossier `.nobackup`) ;
 * --refresh le télécharge de nouveau. Règles de reprise : App\Services\Profiles\SncfGrades.
 * Le fichier est réécrit en entier, trié : relancer la commande sur les mêmes données donne le
 * même fichier (import idempotent, réversible par git).
 *
 *   php artisan vff:import-sncf-grades --dry-run          # rapport seulement
 *   php artisan vff:import-sncf-grades --csv=rapport.csv  # écrit le fichier et le rapport détaillé
 */
class ImportSncfGrades extends Command
{
    protected $signature = 'vff:import-sncf-grades
        {--dry-run : rapport seulement, aucun fichier écrit}
        {--refresh : télécharge de nouveau le jeu au lieu du cache local}
        {--min-coverage=0.1 : part de la ligne (0 à 1) que les segments du jeu doivent couvrir}
        {--min-steep-m=500 : longueur minimale (m) de la portion la plus raide pour remplacer la valeur de Wikipédia}
        {--cache-dir= : dossier du cache (défaut : storage/app/sources/sncf-declivites)}
        {--output= : fichier de données écrit (défaut : database/data/sourced_grades_sncf.php)}
        {--csv= : rapport détaillé par ligne, au format CSV}';

    protected $description = 'Déclivités maximales SNCF Réseau (ODbL) par ligne, contrôlées par Wikipédia et le profil mesuré, écrites dans le fichier des déclivités sourcées';

    private const API = 'https://ressources.data.sncf.com/api/explore/v2.1/catalog/datasets/';

    private const UA = 'VFF-Bot/1.0 (+https://vff-project.org)';

    private const FIELDS = 'code_ligne,lib_ligne,code_voie,type,valeur,pkd,pkf';

    public function handle(): int
    {
        $minCoverage = (float) $this->option('min-coverage');
        if ($minCoverage < 0 || $minCoverage > 1) {
            $this->error('--min-coverage : une part entre 0 et 1.');

            return self::FAILURE;
        }
        $minSteep = (int) $this->option('min-steep-m');
        if ($minSteep < 0) {
            $this->error('--min-steep-m : une longueur positive ou nulle (mètres).');

            return self::FAILURE;
        }
        // Tests : jamais le vrai cache ni le vrai fichier de données.
        $output = $this->option('output') ?: (app()->runningUnitTests() ? null : database_path('data/sourced_grades_sncf.php'));
        if ($output === null && ! $this->option('dry-run')) {
            $this->error('--output obligatoire pendant les tests.');

            return self::FAILURE;
        }

        try {
            $dataset = $this->dataset();
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage().' : aucun fichier modifié.');

            return self::FAILURE;
        }
        $license = (string) ($dataset['metas']['license'] ?? '');
        $publisher = (string) ($dataset['metas']['publisher'] ?? '');
        if (! str_contains($license, 'ODbL') || $publisher !== SncfGrades::ATTRIBUTION) {
            $this->error("Licence ou éditeur inattendus (« {$license} », « {$publisher} ») : rien écrit, registre des droits à revoir.");

            return self::FAILURE;
        }

        $sncf = SncfGrades::aggregate($dataset['records']);
        $wikipedia = require database_path('data/sourced_grades.php');
        $lines = $this->lines(array_map('strval', array_keys($sncf)));

        $entries = [];
        $rows = [];
        foreach ($sncf as $code => $agg) {
            $code = (string) $code;
            $matches = $lines[$code] ?? [];
            $row = ['code' => $code, 'slug' => null, 'name' => $agg['name'], 'sncf' => $agg['max_grade_permil'], 'segments' => $agg['segments'],
                'covered_share' => null, 'steep_m' => $agg['steep_m'], 'wikipedia' => null, 'measure' => null, 'measure_kind' => null, 'withheld' => null];
            if (count($matches) !== 1) {
                $rows[] = $row + ['status' => $matches === [] ? 'line_unknown' : 'line_ambiguous'];

                continue;
            }
            $line = $matches[0];
            $row['slug'] = $line->slug;
            $row['withheld'] = $line->max_grade_withheld;
            if (RackRailways::isRack($line->slug)) {
                $rows[] = $row + ['status' => 'rejected_rack'];

                continue;
            }
            $wiki = isset($wikipedia[$line->slug]) ? (float) $wikipedia[$line->slug]['max_grade_permil'] : null;
            $measure = MeasuredGrade::of($line->profile_id === null ? null : (int) $line->profile_id, $line->max_grade_permil === null ? null : (float) $line->max_grade_permil, $line->gaps === null ? null : (string) $line->gaps);
            $decision = SncfGrades::decide($agg, $this->length($line), $wiki, $measure, $minCoverage, $minSteep);
            $row = array_replace($row, ['covered_share' => $decision['covered_share'], 'wikipedia' => $wiki,
                'measure' => $measure['value'] ?? null, 'measure_kind' => $measure['kind'] ?? null]);
            $rows[] = $row + ['status' => $decision['status']];
            if ($decision['retained'] && $decision['covered_share'] !== null) {
                $entries[$line->slug] = SncfGrades::entry($code, $agg, $decision['covered_share']);
            }
        }

        $this->report($rows);
        if ($this->option('csv')) {
            $this->csv((string) $this->option('csv'), $rows);
        }
        $shown = count(array_filter($rows, fn (array $r): bool => isset($entries[$r['slug'] ?? '']) && in_array($r['withheld'], SourcedGrades::REPLACES, true)));
        $this->info(count($entries)." déclivités SNCF retenues, dont {$shown} affichées aujourd'hui (profil sans déclivité mesurée publiée).");

        if ($this->option('dry-run')) {
            $this->line('Passe à blanc : aucun fichier écrit.');

            return self::SUCCESS;
        }
        $content = SncfGrades::render($entries, substr((string) ($dataset['metas']['data_processed'] ?? ''), 0, 10));
        $before = is_file((string) $output) ? (string) file_get_contents((string) $output) : null;
        if ($before === $content) {
            $this->line("{$output} : inchangé.");
        } else {
            file_put_contents((string) $output, $content);
            $this->info("{$output} écrit.");
        }
        SourcedGrades::flush();

        return self::SUCCESS;
    }

    /**
     * Métadonnées utiles et enregistrements du jeu : cache local, ou téléchargement (mis en cache).
     *
     * @return array{metas: array<string, mixed>, records: list<array<string, mixed>>}
     */
    private function dataset(): array
    {
        $dir = $this->option('cache-dir') ?: (app()->runningUnitTests() ? null : storage_path('app/sources/sncf-declivites'));
        $cache = $dir === null ? null : rtrim((string) $dir, '/').'/'.SncfGrades::DATASET.'.json';
        if ($cache !== null && ! $this->option('refresh') && is_file($cache)) {
            $cached = json_decode((string) file_get_contents($cache), true);
            if (is_array($cached['records'] ?? null) && $cached['records'] !== [] && is_array($cached['metas'] ?? null)) {
                $this->line('Cache du '.($cached['fetched_at'] ?? '?').' ('.count($cached['records']).' enregistrements).');

                return ['metas' => $cached['metas'], 'records' => $cached['records']];
            }
        }

        $meta = $this->get(self::API.SncfGrades::DATASET, []);
        $default = (array) ($meta['metas']['default'] ?? []);
        $metas = ['license' => $default['license'] ?? null, 'publisher' => $default['publisher'] ?? null,
            'data_processed' => $default['data_processed'] ?? null, 'records_count' => $default['records_count'] ?? null];
        sleep(app()->runningUnitTests() ? 0 : 1);
        $records = array_values(array_filter($this->get(self::API.SncfGrades::DATASET.'/exports/json', ['select' => self::FIELDS]), 'is_array'));
        if ($records === []) {
            throw new \RuntimeException('Jeu SNCF vide');
        }
        if ($cache !== null) {
            if (! is_dir(dirname($cache))) {
                mkdir(dirname($cache), 0775, true);
            }
            // Cache régénérable : hors sauvegarde (convention .nobackup du poste de développement).
            if (! is_file(dirname($cache).'/.nobackup')) {
                touch(dirname($cache).'/.nobackup');
            }
            file_put_contents($cache, json_encode(['dataset' => SncfGrades::DATASET, 'fetched_at' => now()->toDateTimeString(),
                'metas' => $metas, 'records' => $records], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        return ['metas' => $metas, 'records' => $records];
    }

    /**
     * @param  array<string, string>  $query
     * @return array<mixed>
     */
    private function get(string $url, array $query): array
    {
        try {
            $response = Http::timeout(120)->withUserAgent(self::UA)->get($url, $query);
        } catch (ConnectionException) {
            throw new \RuntimeException('SNCF injoignable');
        }
        if (! $response->successful()) {
            throw new \RuntimeException("SNCF HTTP {$response->status()}");
        }

        return (array) $response->json();
    }

    /**
     * Lignes de VFF par code du RFN, avec leur profil (lecture seule).
     *
     * @param  list<string>  $codes
     * @return array<string, list<object>>
     */
    private function lines(array $codes): array
    {
        $out = [];
        foreach (array_chunk($codes, 500) as $chunk) {
            $rows = DB::table('lines as l')
                ->leftJoin('line_profiles as p', 'p.line_id', '=', 'l.id')
                ->whereIn('l.code', $chunk)
                ->orderBy('l.slug')
                ->get(['l.slug', 'l.code', 'l.length_official_m', 'p.id as profile_id', 'p.length_m', 'p.max_grade_permil', 'p.max_grade_withheld', 'p.gaps']);
            foreach ($rows as $row) {
                $out[(string) $row->code][] = $row;
            }
        }

        return $out;
    }

    private function length(object $line): ?int
    {
        $length = $line->length_official_m ?? $line->length_m ?? null;

        return $length === null ? null : (int) $length;
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function report(array $rows): void
    {
        $counts = array_count_values(array_column($rows, 'status'));
        ksort($counts);
        $this->table(['Résultat', 'Lignes'], array_map(fn (string $k, int $n): array => [$k, $n], array_keys($counts), $counts));
        $listed = array_filter($rows, fn (array $r): bool => str_starts_with($r['status'], 'rejected_above') || $r['status'] === 'rejected_ratio'
            || str_starts_with($r['status'], 'retained') || str_starts_with($r['status'], 'kept_wikipedia'));
        $this->table(['Code', 'Ligne', 'SNCF ‰', 'Portion raide (m)', 'Part', 'Wikipédia ‰', 'Mesure ‰', 'Résultat'], array_map(fn (array $r): array => [
            $r['code'], $r['slug'] ?? '-', $r['sncf'], $r['steep_m'] ?? '-', $r['covered_share'] ?? '-', $r['wikipedia'] ?? '-',
            $r['measure'] === null ? '-' : $r['measure'].' ('.$r['measure_kind'].')', $r['status'],
        ], array_values($listed)));
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function csv(string $path, array $rows): void
    {
        $handle = fopen($path, 'w');
        if ($handle === false) {
            $this->warn("{$path} : écriture impossible.");

            return;
        }
        fputcsv($handle, ['code', 'slug', 'status', 'sncf_permil', 'covered_share', 'segments', 'steep_m', 'wikipedia_permil', 'measure_permil', 'measure_kind', 'withheld', 'name'], escape: '');
        foreach ($rows as $r) {
            fputcsv($handle, [$r['code'], $r['slug'], $r['status'], $r['sncf'], $r['covered_share'], $r['segments'], $r['steep_m'], $r['wikipedia'],
                $r['measure'], $r['measure_kind'], $r['withheld'], $r['name']], escape: '');
        }
        fclose($handle);
    }
}

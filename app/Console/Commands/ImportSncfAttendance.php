<?php

namespace App\Console\Commands;

use App\Services\Sncf\SncfOpenDataCache;
use App\Services\Sncf\StationAttendanceDatasets as Datasets;
use App\Services\Sncf\StationAttendanceMatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Import de la fréquentation annuelle des gares : jeux SNCF Open Data « Fréquentation en gares » (SNCF Gares &
 * Connexions, ODbL), archive 2015-2024 et jeu courant 2025 et suivantes. Chaque gare du jeu est rattachée à une
 * gare de VFF par son code UIC (StationAttendanceMatcher : doublons de fiches départagés par le département,
 * quelques rattachements par le nom et le département, un alias vérifié à la main). Voyageurs et voyageurs +
 * non-voyageurs sont gardés tels quels, une ligne par gare et par année dans `station_attendances` ; une gare du
 * jeu sans gare de VFF n'est pas écrite (le rapport la liste). Toutes les lignes `sncf` sont remplacées d'un
 * coup, dans une transaction ; aucune autre table n'est écrite.
 *
 * Les jeux sont téléchargés une fois (licence et éditeur vérifiés à chaque téléchargement, cache
 * storage/app/sources/sncf-attendance/, dossier `.nobackup`) ; --refresh les télécharge de nouveau.
 *
 *   php artisan vff:import-sncf-attendance --dry-run                 # mesure, aucune écriture
 *   php artisan vff:import-sncf-attendance --dry-run --csv=/chemin/ # écrit rapport.csv, non-rattachés.csv, sans-frequentation.csv
 *   php artisan vff:import-sncf-attendance                           # écrit
 */
class ImportSncfAttendance extends Command
{
    protected $signature = 'vff:import-sncf-attendance
        {--dry-run : rapport seulement, aucune écriture en base}
        {--refresh : télécharge de nouveau les jeux au lieu du cache local}
        {--cache-dir= : dossier du cache (défaut : storage/app/sources/sncf-attendance)}
        {--csv= : dossier où écrire les rapports détaillés au format CSV}';

    protected $description = 'Importe la fréquentation annuelle des gares (SNCF Gares & Connexions) et la rattache aux gares par code UIC';

    /** Priorité des règles de rattachement quand deux lignes du jeu désignent la même gare. */
    private const RANK = ['uic' => 0, 'uic-department' => 1, 'uic-status' => 2, 'alias' => 3, 'name-department' => 4];

    public function handle(SncfOpenDataCache $cache): int
    {
        $dir = (string) ($this->option('cache-dir') ?: storage_path('app/sources/sncf-attendance'));
        $datasets = [];
        try {
            foreach ([Datasets::ARCHIVE, Datasets::CURRENT] as $name) {
                $datasets[$name] = $cache->load($name, [], $dir, (bool) $this->option('refresh'), Datasets::PUBLISHER);
                $this->info(sprintf('%s : %d gares (jeu modifié le %s, licence %s).', $name, count($datasets[$name]['records']),
                    $datasets[$name]['modified'] ?? '?', $datasets[$name]['license']));
            }
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $stations = DB::table('stations')->get(['id', 'slug', 'name', 'uic_code', 'status', 'department_code', 'city']);
        $matcher = new StationAttendanceMatcher($stations);
        $now = now();

        // Une gare de VFF peut recevoir deux lignes d'un même jeu : la règle la plus sûre l'emporte, l'autre est écartée.
        $best = [];
        $methods = [];
        $unmatched = [];
        $duplicates = [];
        foreach ($datasets as $name => $data) {
            foreach ($data['records'] as $r) {
                $uic = trim((string) ($r['code_uic_complet'] ?? ''));
                $label = trim((string) ($r['nom_gare'] ?? ''));
                $years = Datasets::years($r);
                if ($uic === '' || $years === []) {
                    continue;
                }
                $found = $matcher->match($uic, $label, $r['code_postal'] ?? null);
                $methods[$name][$found['method']] = ($methods[$name][$found['method']] ?? 0) + 1;
                if ($found['station'] === null) {
                    $unmatched[] = [$name, $uic, $label, (string) ($r['code_postal'] ?? ''), $found['method'], max(array_keys($years)),
                        $years[max(array_keys($years))][0] ?? $years[max(array_keys($years))][1]];

                    continue;
                }
                $key = $found['station']->id.'|'.$name;
                if (isset($best[$key])) {
                    $keep = (self::RANK[$found['method']] ?? 9) < (self::RANK[$best[$key]['method']] ?? 9);
                    $duplicates[] = [$name, $keep ? $best[$key]['uic'] : $uic, $found['station']->slug, $keep ? $best[$key]['label'] : $label];
                    if (! $keep) {
                        continue;
                    }
                }
                $best[$key] = ['station' => $found['station'], 'method' => $found['method'], 'uic' => $uic, 'label' => $label,
                    'years' => $years, 'dataset' => $name];
            }
        }

        $all = [];
        foreach ($best as $b) {
            foreach ($b['years'] as $year => [$passengers, $footfall]) {
                $all[] = [
                    'station_id' => $b['station']->id, 'year' => $year, 'passengers' => $passengers, 'footfall' => $footfall,
                    'series' => Datasets::series($year), 'uic_code' => $b['uic'], 'source_name' => mb_substr($b['label'], 0, 160),
                    'match_method' => $b['method'], 'source' => 'sncf', 'dataset' => $b['dataset'],
                    'dataset_modified' => $datasets[$b['dataset']]['modified'], 'created_at' => $now, 'updated_at' => $now,
                ];
            }
        }
        // Une gare présente dans les deux jeux (2015-2024 et 2025) : une ligne par année, jamais deux pour la même.
        $unique = [];
        foreach ($all as $row) {
            $unique[$row['station_id'].'|'.$row['year']] = $row;
        }
        $rows = [];
        foreach ($unique as $row) {
            $rows[] = $row;
        }

        $this->report($datasets, $methods, $stations, $rows, $unmatched, $duplicates);

        if ($this->option('dry-run')) {
            $this->line('Passe à blanc : aucune écriture en base.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($rows): void {
            DB::table('station_attendances')->where('source', 'sncf')->delete();
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('station_attendances')->insert($chunk);
            }
        });
        $this->info('Écrit : '.count($rows).' lignes.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, array<string, mixed>>  $datasets
     * @param  array<string, array<string, int>>  $methods
     * @param  iterable<object>  $stations
     * @param  list<array<string, mixed>>  $rows
     * @param  list<list<mixed>>  $unmatched
     * @param  list<list<mixed>>  $duplicates
     */
    private function report(array $datasets, array $methods, iterable $stations, array $rows, array $unmatched, array $duplicates): void
    {
        $covered = array_flip(array_column($rows, 'station_id'));
        $latest = [];
        foreach ($rows as $row) {
            $latest[$row['station_id']] = max($latest[$row['station_id']] ?? 0, $row['year']);
        }
        $this->line('');
        foreach ($methods as $name => $count) {
            arsort($count);
            $this->line(sprintf('%s, rattachement : %s', $name, implode(', ', array_map(fn ($m, $n) => "{$m} {$n}", array_keys($count), $count))));
        }
        $byYear = array_count_values(array_column($rows, 'year'));
        ksort($byYear);
        $this->line(sprintf('Lignes à écrire dans station_attendances : %d, pour %d gares (aujourd\'hui en base : %d).', count($rows), count($covered),
            Schema::hasTable('station_attendances') ? (int) DB::table('station_attendances')->where('source', 'sncf')->count() : 0));
        $this->line('Gares par année : '.implode(', ', array_map(fn ($y, $n) => "{$y} {$n}", array_keys($byYear), $byYear)));
        $this->line(sprintf('Gares du jeu sans gare de VFF : %d (voir non-rattachees.csv) ; lignes en double écartées : %d.', count($unmatched), count($duplicates)));
        foreach ($unmatched as [$set, $uic, $label, $postal, $why]) {
            $this->warn("  {$why} : {$label} ({$uic}, {$postal}) [{$set}]");
        }

        $current = 0;
        $withCurrent = 0;
        $open = ['active' => [0, 0], 'tourist' => [0, 0], 'no_commercial' => [0, 0], 'declassified' => [0, 0]];
        $missing = [];
        $maxYear = $rows === [] ? 0 : max(array_column($rows, 'year'));
        foreach ($stations as $s) {
            $has = isset($covered[$s->id]);
            $open[$s->status][0]++;
            $open[$s->status][1] += $has ? 1 : 0;
            if ($has && ($latest[$s->id] ?? 0) === $maxYear) {
                $withCurrent++;
            }
            if (! $has && in_array($s->status, ['active', 'tourist'], true)) {
                $missing[] = [$s->slug, $s->name, $s->status, (string) $s->uic_code, (string) $s->department_code, (string) $s->city];
            }
            $current++;
        }
        $this->line('');
        $this->line("Couverture (gares de VFF, {$current} fiches) :");
        foreach ($open as $status => [$total, $with]) {
            $this->line(sprintf('  %-13s %5d gares, %5d avec une fréquentation (%.1f %%)', $status, $total, $with, $total > 0 ? 100 * $with / $total : 0));
        }
        $this->line(sprintf('  dont avec la dernière année (%d) : %d', $maxYear, $withCurrent));
        $this->line(sprintf('Gares ouvertes (active, tourist) sans fréquentation : %d (voir sans-frequentation.csv)', count($missing)));

        if ($dir = rtrim((string) $this->option('csv'), '/')) {
            if (! is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            $this->csv($dir.'/rapport.csv', ['station_id', 'annee', 'voyageurs', 'voyageurs_et_non_voyageurs', 'serie', 'uic', 'nom_du_jeu', 'methode', 'jeu'],
                array_map(fn ($r) => [$r['station_id'], $r['year'], $r['passengers'], $r['footfall'], $r['series'], $r['uic_code'], $r['source_name'], $r['match_method'], $r['dataset']], $rows));
            $this->csv($dir.'/non-rattachees.csv', ['jeu', 'uic', 'nom', 'code_postal', 'raison', 'derniere_annee', 'valeur'], $unmatched);
            $this->csv($dir.'/doublons.csv', ['jeu', 'uic', 'gare_vff', 'nom'], $duplicates);
            $this->csv($dir.'/sans-frequentation.csv', ['slug', 'nom', 'statut', 'uic', 'departement', 'commune'], $missing);
            $this->line("Rapports : {$dir}");
        }
    }

    /**
     * @param  list<string>  $header
     * @param  list<list<mixed>>  $rows
     */
    private function csv(string $path, array $header, array $rows): void
    {
        $h = fopen($path, 'w');
        fputcsv($h, $header, ',', '"', '');
        foreach ($rows as $r) {
            fputcsv($h, $r, ',', '"', '');
        }
        fclose($h);
    }
}

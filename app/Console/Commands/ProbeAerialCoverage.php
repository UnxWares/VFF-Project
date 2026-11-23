<?php

namespace App\Console\Commands;

use App\Services\Media\Aerial\AerialCoverageProbe;
use App\Services\Media\Aerial\AerialImages;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Sleep;

/**
 * Couverture des vues aériennes historiques de l'IGN (1950-1965, 1965-1980, 1980-1995) pour chaque gare, lue dans les
 * graphes de mosaïquage des missions (WFS de la Géoplateforme, environ 300 octets par réponse, aucune image
 * téléchargée). Pour chaque période absente, la commande pose le marqueur négatif `<gare>.none` (valable un an) que
 * `AerialImages` lit déjà : la fiche de la gare ne propose plus la période et la première visite ne fait plus de 404.
 * Le journal `<cache>/coverage/<période>.tsv` (id, 1 ou 0, date du vol, mission, date de la sonde) permet de reprendre.
 * Lecture seule en base, 2 requêtes par seconde au plus.
 *
 *   vff:probe-aerial-coverage --dry-run --sample=200 --seed=2311   mesure sur 200 gares, rien n'est écrit
 *   vff:probe-aerial-coverage --limit=500                          les 500 premières gares pas encore sondées
 *   vff:probe-aerial-coverage                                      toutes les gares, reprise là où on s'est arrêté
 */
class ProbeAerialCoverage extends Command
{
    protected $signature = 'vff:probe-aerial-coverage
        {--dry-run : Sonde et compte, mais n\'écrit ni marqueur ni journal}
        {--limit= : Nombre maximal de gares sondées (par période)}
        {--sample=0 : Nombre de gares tirées au hasard (mesure)}
        {--seed=1 : Graine du tirage}
        {--station= : Une seule gare (slug)}
        {--period=* : Périodes sondées (toutes par défaut)}
        {--refresh : Sonde de nouveau les gares déjà au journal}
        {--rate=2 : Requêtes par seconde (2 au plus)}
        {--csv= : Fichier CSV des résultats (gare, période, couverte, date du vol)}';

    protected $description = 'Détermine hors ligne quelles gares ont une vue aérienne IGN par période (emprises des missions, sans image) et marque les périodes vides';

    public function handle(AerialCoverageProbe $probe): int
    {
        $periods = array_values(array_map('strval', (array) $this->option('period')));
        $periods = $periods === [] ? array_keys(AerialImages::PERIODS) : $periods;
        if (array_diff($periods, array_keys(AerialImages::PERIODS)) !== []) {
            $this->error('Période inconnue ('.implode(', ', array_keys(AerialImages::PERIODS)).').');

            return self::FAILURE;
        }
        $rate = max(0.1, min(2.0, (float) $this->option('rate')));
        $dry = (bool) $this->option('dry-run');
        $root = (string) config('vff.media.aerial.cache_path', storage_path('app/cache/aerial'));

        DB::statement("SET statement_timeout = '30s'");
        $query = DB::table('stations')->selectRaw('id, slug, ST_Y(geom) AS lat, ST_X(geom) AS lng')->orderBy('id');
        if ($this->option('station') !== null) {
            $query->where('slug', (string) $this->option('station'));
        }
        $stations = $query->get()->map(fn ($s): array => [(int) $s->id, (float) $s->lat, (float) $s->lng, (string) $s->slug])->all();
        if ($stations === []) {
            $this->warn('Aucune gare.');

            return self::SUCCESS;
        }
        if ((int) $this->option('sample') > 0) {
            // Tirage reproductible : ordre fixé par la graine.
            mt_srand((int) $this->option('seed'));
            shuffle($stations);
            $stations = array_slice($stations, 0, (int) $this->option('sample'));
        }
        $limit = $this->option('limit') !== null ? max(1, (int) $this->option('limit')) : null;

        $csv = $this->option('csv') !== null ? fopen((string) $this->option('csv'), 'wb') : false;
        if ($csv) {
            fputcsv($csv, ['slug', 'period', 'covered', 'flight_date', 'mission'], escape: '\\');
        }
        $started = microtime(true);
        $rows = [];
        $consecutiveErrors = 0;
        foreach ($periods as $period) {
            $journal = $root.'/coverage/'.$period.'.tsv';
            $done = $this->option('refresh') ? [] : $this->readJournal($journal);
            $todo = array_values(array_filter($stations, fn (array $s): bool => ! isset($done[$s[0]])));
            if ($limit !== null) {
                $todo = array_slice($todo, 0, $limit);
            }
            $hit = $miss = $failed = $observed = 0;
            $bySlug = [];
            for ($i = 0; $i < count($todo);) {
                // Taille du groupe testé d'un coup : grande tant que la période couvre peu de gares.
                $size = $observed < 20 ? 1 : $this->groupSize($hit / max(1, $hit + $miss));
                $group = array_slice($todo, $i, $size);
                $i += count($group);
                $this->throttle($rate);
                $before = $probe->requests;
                $results = $probe->probe($period, array_map(fn (array $s): array => [$s[0], $s[1], $s[2]], $group));
                for ($k = $probe->requests - $before - 1; $k > 0; $k--) {
                    $this->throttle($rate);   // chaque requête de la dichotomie respecte aussi le débit
                }
                foreach ($group as $s) {
                    $r = $results[$s[0]] ?? null;
                    if ($r === null) {
                        $failed++;
                        $consecutiveErrors++;

                        continue;
                    }
                    $consecutiveErrors = 0;
                    $observed++;
                    $r['covered'] ? $hit++ : $miss++;
                    $bySlug[$s[3]] = $r['covered'];
                    if (! $dry) {
                        $this->record($root, $period, $journal, $s[0], $r);
                    }
                    if ($csv) {
                        fputcsv($csv, [$s[3], $period, $r['covered'] ? 1 : 0, $r['date'], $r['pva']], escape: '\\');
                    }
                }
                if ($consecutiveErrors >= 10) {
                    $this->error('10 erreurs de suite : arrêt (relancer plus tard, le journal permet la reprise).');

                    break 2;
                }
            }
            $rows[] = [$period, count($done), count($todo), $hit, $miss, $failed, $hit + $miss > 0 ? sprintf('%.1f %%', 100 * $hit / ($hit + $miss)) : '-'];
        }
        if ($csv) {
            fclose($csv);
        }
        $this->info(($dry ? '[à blanc] ' : '').sprintf('%d gares, %d requêtes, %.1f Ko reçus, %d erreurs, %.0f s.', count($stations), $probe->requests, $probe->bytes / 1024, $probe->errors, microtime(true) - $started));
        $this->table(['Période', 'Déjà au journal', 'Sondées', 'Couvertes', 'Absentes', 'Erreurs', 'Couverture'], $rows);

        return self::SUCCESS;
    }

    private function groupSize(float $rate): int
    {
        return match (true) {
            $rate < 0.05 => 16,
            $rate < 0.15 => 8,
            $rate < 0.3 => 3,
            default => 1,
        };
    }

    private float $last = 0.0;

    private function throttle(float $rate): void
    {
        $wait = $this->last + 1 / $rate - microtime(true);
        if ($wait > 0) {
            Sleep::usleep((int) ($wait * 1_000_000));
        }
        $this->last = microtime(true);
    }

    /** @return array<int, true> */
    private function readJournal(string $file): array
    {
        $done = [];
        if (is_file($file)) {
            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $done[(int) explode("\t", $line)[0]] = true;
            }
        }

        return $done;
    }

    /** @param array{covered: bool, date: ?string, pva: string|int|null} $r */
    private function record(string $root, string $period, string $journal, int $id, array $r): void
    {
        $dir = dirname($journal);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        foreach (['.nobackup', AerialImages::MARKER] as $m) {
            if (! is_file($root.'/'.$m)) {
                touch($root.'/'.$m);
            }
        }
        if (! $r['covered']) {
            $none = $root.'/'.$period.'/'.$id.'.none';
            if (! is_dir($root.'/'.$period)) {
                mkdir($root.'/'.$period, 0775, true);
            }
            if (! is_file($root.'/'.$period.'/'.$id.'.jpg')) {
                AerialImages::atomicWrite($none, AerialImages::PROBE_MARK);
            }
        }
        file_put_contents($journal, implode("\t", [$id, $r['covered'] ? 1 : 0, substr((string) $r['date'], 0, 10), $r['pva'] ?? '', date('Y-m-d')])."\n", FILE_APPEND | LOCK_EX);
    }
}

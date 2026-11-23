<?php

namespace App\Console\Commands;

use App\Services\Departments\DepartmentFigures;
use App\Support\FrenchDepartments;
use Illuminate\Console\Command;

/**
 * Calcule les fiches par département (/departments, /departments/{code}-{nom}, `GET /api/v1/departments/{code}/history`,
 * `GET /api/v1/stats/departments`) et les range dans le cache, sans expiration. Lecture seule en base (quelques
 * minutes, requêtes de 30 secondes au plus) . Recalculées aussi par `vff:refresh-stale-figures` quand les données ont changé ;
 * cette commande force un calcul (ou limite à quelques départements avec --only).
 *
 *   php artisan vff:compute-department-figures
 *   php artisan vff:compute-department-figures --only=16,23      (seulement ces départements)
 *   php artisan vff:compute-department-figures --dry-run         (calcule et affiche, ne touche pas au cache)
 */
class ComputeDepartmentFigures extends Command
{
    protected $signature = 'vff:compute-department-figures
        {--only= : codes de départements séparés par des virgules (16,23,2A)}
        {--dry-run : calcule et affiche sans ranger dans le cache}';

    protected $description = 'Calcule les chiffres par département (longueurs par décennie, gares, dates, lignes, communes, accidents) et les range dans le cache.';

    public function handle(DepartmentFigures $figures): int
    {
        $started = microtime(true);
        $dry = (bool) $this->option('dry-run');
        $only = null;
        if (filled($this->option('only'))) {
            $only = array_values(array_unique(array_map(fn (string $c): string => strtoupper(trim($c)), explode(',', (string) $this->option('only')))));
            $unknown = array_values(array_diff($only, array_keys(FrenchDepartments::ALL)));
            if ($unknown !== []) {
                $this->error('Départements inconnus : '.implode(', ', $unknown));

                return self::FAILURE;
            }
        }

        $figures->onProgress(fn (string $line) => $this->line($line));
        $result = $dry ? $figures->compute($only) : $figures->refresh($only);

        $this->table(
            ['Code', 'Département', 'Km 1930', 'Km aujourd\'hui', 'Sommet', 'Part fermée', 'Gares 1930', 'Gares auj.', 'Lignes', 'Accidents'],
            array_map(fn (array $d): array => [
                $d['code'], $d['name'], $d['km_reference'], $d['km_today'], $d['km_peak'].($d['peak_year'] ? " ({$d['peak_year']})" : ''),
                $d['closed_share'] === null ? '-' : round($d['closed_share'] * 100, 1).' %', $d['stations_reference'], $d['stations_today'], $d['lines'], $d['accidents'],
            ], $result['index']['departments']),
        );
        $this->info(sprintf('%d départements calculés en %.1f s%s.', count($result['sheets']), microtime(true) - $started, $dry ? ' [DRY-RUN, cache inchangé]' : ' et rangés dans le cache'));

        return self::SUCCESS;
    }
}

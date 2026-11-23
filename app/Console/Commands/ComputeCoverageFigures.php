<?php

namespace App\Console\Commands;

use App\Services\CoverageFigures;
use Illuminate\Console\Command;

/**
 * Calcule « Ce que sait l'encyclopédie » (page /network/coverage, `GET /api/v1/stats/coverage`) et le range
 * dans le cache, sans expiration, avec sa date de calcul. Lecture seule en base (requêtes bornées à 30 s) . Recalculée aussi par
 * `vff:refresh-stale-figures` quand les données ont changé ; cette commande force un calcul.
 *
 *   php artisan vff:compute-coverage-figures
 *   php artisan vff:compute-coverage-figures --dry-run   (calcule et affiche, ne touche pas au cache)
 */
class ComputeCoverageFigures extends Command
{
    protected $signature = 'vff:compute-coverage-figures {--dry-run : calcule et affiche sans ranger dans le cache}';

    protected $description = 'Mesure la couverture des données (lignes, gares, accidents, matériel) et la range dans le cache.';

    public function handle(CoverageFigures $figures): int
    {
        $started = microtime(true);
        $dry = (bool) $this->option('dry-run');
        $result = $dry ? $figures->compute() : $figures->refresh();

        foreach (['lines', 'stations', 'accidents', 'materials'] as $section) {
            $this->line(strtoupper($section));
            $this->table(
                ['Mesure', 'Unité', 'Total', 'Renseigné', 'Part %', 'Dont source ouverte'],
                array_map(fn (array $m): array => [
                    $m['key'], $m['unit'], $m['available'] ? $m['total'] : '-', $m['available'] ? $m['done'] : 'table absente',
                    $m['share'] === null ? '-' : round($m['share'] * 100, 1), $m['open'] ?? '-',
                ], $result[$section]['metrics']),
            );
        }
        $this->info(sprintf('Couverture calculée le %s en %.1f s%s.', $result['computed_at'], microtime(true) - $started, $dry ? ' [DRY-RUN, cache inchangé]' : ' et rangée dans le cache'));

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Services\Companies\CompanyFigures;
use App\Services\CoverageFigures;
use App\Services\Departments\DepartmentFigures;
use App\Services\FiguresVersion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Recalcule les chiffres rangés sans expiration (couverture, compagnies, départements) dont les données ont changé
 * depuis leur dernier calcul (FiguresVersion). Les pages, l'API REST, GraphQL et MCP lisent la dernière valeur
 * rangée pendant ce temps : personne n'attend le calcul (environ 3 min pour les départements).
 *
 * Appelée par la tâche planifiée (production) et en fin de `vff:refresh-current-era` ; à la main après une
 * application en base. Sans --now, attend que les données soient stables depuis 10 minutes (une application en
 * cours changerait la version à chaque écriture). Lecture seule en base, un seul passage à la fois.
 *
 *   php artisan vff:refresh-stale-figures
 *   php artisan vff:refresh-stale-figures --now       (sans attendre la stabilité des données)
 *   php artisan vff:refresh-stale-figures --dry-run   (dit ce qui est périmé, ne calcule rien)
 */
class RefreshStaleFigures extends Command
{
    /** Minutes sans changement des données avant un recalcul automatique. */
    public const SETTLE_MINUTES = 10;

    protected $signature = 'vff:refresh-stale-figures
        {--now : recalcule sans attendre que les données soient stables}
        {--dry-run : liste les chiffres périmés sans rien calculer}';

    protected $description = 'Recalcule les chiffres des départements, des compagnies et de la couverture si les données ont changé depuis leur dernier calcul.';

    public function handle(FiguresVersion $versions, CoverageFigures $coverage, CompanyFigures $companies, DepartmentFigures $departments): int
    {
        /** @var array<string, array{0: bool, 1: callable(): mixed}> $jobs */
        $jobs = [
            'coverage' => [$coverage->isStale(), fn () => $coverage->refresh()],
            'companies' => [$companies->isStale(), fn () => $companies->refresh()],
            'departments' => [$departments->isStale(), fn () => $departments->refresh()],
        ];
        $stale = array_keys(array_filter($jobs, fn (array $job): bool => $job[0]));
        if ($stale === []) {
            $this->info('Chiffres à jour.');

            return self::SUCCESS;
        }
        $this->line('Périmés : '.implode(', ', $stale).'.');
        if ($this->option('dry-run')) {
            $this->line('[DRY-RUN] rien n\'est calculé.');

            return self::SUCCESS;
        }
        if (! $this->option('now') && ! $versions->settled(self::SETTLE_MINUTES)) {
            $this->warn('Données modifiées il y a moins de '.self::SETTLE_MINUTES.' minutes : recalcul reporté.');

            return self::SUCCESS;
        }

        $lock = Cache::lock('figures:refresh-stale', 1800);
        if (! $lock->get()) {
            $this->warn('Un recalcul des chiffres est déjà en cours.');

            return self::SUCCESS;
        }
        try {
            foreach ($stale as $name) {
                $started = microtime(true);
                $jobs[$name][1]();
                $this->info(sprintf('✓ %s recalculés en %.1f s.', $name, microtime(true) - $started));
            }
        } finally {
            $lock->release();
        }

        return self::SUCCESS;
    }
}

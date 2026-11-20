<?php

namespace App\Console\Commands;

use App\Services\Reputation\ReputationCalculator;
use Illuminate\Console\Command;

/**
 * Recalcule la réputation de tous les comptes (contributions acceptées, refusées, relectures
 * faites) à partir des contributions et des approbations. Rejouable sans risque : le résultat ne
 * dépend pas de l'état précédent. Sert à remplir les compteurs des comptes existants après la
 * migration et à rattraper une dérive ; la réputation d'un membre est de toute façon recalculée
 * à chaque décision de validation.
 *
 *   php artisan vff:recalculate-reputation --dry-run   # compte les comptes qui changeraient
 *   php artisan vff:recalculate-reputation
 */
class RecalculateReputation extends Command
{
    protected $signature = 'vff:recalculate-reputation
        {--dry-run : Compte les comptes dont la réputation changerait, sans rien écrire}';

    protected $description = 'Recalcule la réputation de tous les comptes (contributions acceptées, refusées, relectures)';

    public function handle(ReputationCalculator $calculator): int
    {
        $dryRun = (bool) $this->option('dry-run');
        ['checked' => $checked, 'changed' => $changed] = $calculator->recalculateAll($dryRun);

        $this->info($dryRun
            ? sprintf('Simulation : %d compte(s) lu(s), %d à mettre à jour.', $checked, $changed)
            : sprintf('%d compte(s) lu(s), %d mis à jour.', $checked, $changed));

        return self::SUCCESS;
    }
}

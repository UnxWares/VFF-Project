<?php

namespace App\Console\Commands;

use App\Services\Account\InactiveAccounts;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Comptes inutilisés pendant 3 ans (politique de confidentialité) : supprime ceux qui ont été
 * avertis il y a un mois au moins sans revenir, puis avertit par courriel ceux qui atteignent
 * 3 ans d'inactivité dans un mois.
 *
 *   php artisan vff:prune-inactive-accounts --dry-run   # compte sans rien envoyer ni supprimer
 *   php artisan vff:prune-inactive-accounts             # tâche quotidienne de production
 *
 * Déclarée dans le planificateur pour la production seulement (routes/console.php).
 */
class PruneInactiveAccounts extends Command
{
    protected $signature = 'vff:prune-inactive-accounts
        {--dry-run : Affiche le nombre de comptes concernés sans rien envoyer ni supprimer}';

    protected $description = 'Avertit puis supprime les comptes inutilisés depuis 3 ans (politique de confidentialité)';

    public function handle(InactiveAccounts $accounts): int
    {
        $now = CarbonImmutable::now();

        if ($this->option('dry-run')) {
            $this->info(sprintf(
                'Simulation : %d compte(s) à supprimer, %d compte(s) à avertir.',
                $accounts->dueForDeletion($now)->count(),
                $accounts->dueForWarning($now)->count(),
            ));

            return self::SUCCESS;
        }

        // Suppressions d'abord : un compte averti aujourd'hui ne peut pas être supprimé aujourd'hui.
        $deleted = $accounts->deleteExpired($now);
        ['warned' => $warned, 'failed' => $failed] = $accounts->warn($now);

        $this->info(sprintf('%d compte(s) supprimé(s), %d averti(s).', $deleted, $warned));
        if ($failed > 0) {
            $this->warn(sprintf('%d avertissement(s) non envoyé(s) (courriel en échec) : nouvel essai au prochain passage.', $failed));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}

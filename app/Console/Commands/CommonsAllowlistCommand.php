<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Media\CommonsAllowlist;
use Illuminate\Console\Command;
use Throwable;

/**
 * Liste des fichiers de Commons que VFF cite, seuls servis par le relais des vignettes
 * (CommonsAllowlist, config commons_proxy) : sans option, état de la liste ; `--refresh` la reconstruit
 * depuis la base et le journal (aucune écriture dans la base : lecture seule) ; `--dry-run` compte sans rien
 * écrire dans Valkey.
 *
 *   php artisan vff:commons-allowlist
 *   php artisan vff:commons-allowlist --refresh
 *   php artisan vff:commons-allowlist --refresh --dry-run
 *
 * Déclarée dans le planificateur, toutes les dix minutes, en production et quand le relais est activé
 * (routes/console.php) ; à lancer aussi une fois au déploiement, avant d'activer le relais.
 */
class CommonsAllowlistCommand extends Command
{
    protected $signature = 'vff:commons-allowlist
        {--refresh : Reconstruit la liste depuis la base et le journal}
        {--dry-run : Compte les fichiers sans écrire la liste}';

    protected $description = 'Liste des fichiers de Commons cités par VFF, seuls servis par le relais des vignettes : état, ou reconstruction (--refresh)';

    public function handle(CommonsAllowlist $allowlist): int
    {
        if ($this->option('refresh') || $this->option('dry-run')) {
            $dry = (bool) $this->option('dry-run');
            try {
                $meta = $allowlist->rebuild(write: ! $dry);
            } catch (Throwable $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }
            $this->info(sprintf('%s : %d fichier(s) en %s s.', $dry ? 'Comptage (rien écrit)' : 'Liste reconstruite', $meta['count'], $meta['seconds']));
            $this->report($meta['sources']);
            if (! $dry && ($bytes = $allowlist->memoryBytes()) !== null) {
                $this->line(sprintf('Ensemble Valkey : %s Ko.', number_format($bytes / 1024, 0, ',', ' ')));
            }

            return self::SUCCESS;
        }

        $meta = $allowlist->meta();
        if ($meta === null) {
            $this->warn('Liste jamais construite : lancer `vff:commons-allowlist --refresh` (le relais répond 503 d\'ici là).');

            return self::SUCCESS;
        }
        $this->info(sprintf('%d fichier(s), construite le %s en %s s.', $meta['count'], $meta['built_at'], $meta['seconds']));
        $this->report($meta['sources']);

        return self::SUCCESS;
    }

    /** @param  array<string, int>  $sources */
    private function report(array $sources): void
    {
        foreach ($sources as $source => $count) {
            $this->line(sprintf('  %-16s %d adresse(s) retenue(s)', $source, $count));
        }
    }
}

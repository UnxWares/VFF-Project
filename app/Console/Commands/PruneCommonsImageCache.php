<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Media\CommonsImageCache;
use Illuminate\Console\Command;

/**
 * Cache disque du relais des vignettes de Commons (config commons_proxy) : retire les vignettes périmées
 * (`ttl_days`), puis les plus anciennes jusqu'à revenir à 90 % de `cache_max_mb`. N'agit que dans un dossier
 * qui porte le marqueur du cache (CommonsImageCache::MARKER), et seulement sur ses fichiers.
 *
 *   php artisan vff:commons-cache-prune
 *
 * Déclarée dans le planificateur, toutes les heures, en production et quand le relais est activé
 * (routes/console.php).
 */
class PruneCommonsImageCache extends Command
{
    protected $signature = 'vff:commons-cache-prune';

    protected $description = 'Retire du cache du relais de Commons les vignettes périmées puis les plus anciennes au-delà de la taille maximale';

    public function handle(): int
    {
        $result = CommonsImageCache::fromConfig()->prune();
        $this->info(sprintf(
            '%d vignette(s) retirée(s) (%s Mo libérés), %d gardée(s) (%s Mo).',
            $result['removed'],
            number_format($result['freed_bytes'] / 1_048_576, 1, ',', ' '),
            $result['kept'],
            number_format($result['kept_bytes'] / 1_048_576, 1, ',', ' '),
        ));

        return self::SUCCESS;
    }
}

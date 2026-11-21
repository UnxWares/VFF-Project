<?php

namespace App\Console\Commands;

use App\Services\Media\HomeImageSelector;
use Illuminate\Console\Command;

/**
 * Réserve d'images anciennes pour « Le rail en images » de l'accueil (règle : App\Services\Media\HomeImageSelector).
 * Rejouable : le drapeau provider_meta.home_pick est recalculé à chaque passage.
 *
 *   php artisan vff:select-home-images --dry-run
 *   php artisan vff:select-home-images --max-year=1970
 */
class SelectHomeImages extends Command
{
    protected $signature = 'vff:select-home-images
        {--max-year=1970 : Dernière année représentée prise en compte}
        {--dry-run : Compte sans écrire}';

    protected $description = 'Retient des images Commons anciennes pour la section « Le rail en images » de l\'accueil';

    public function handle(HomeImageSelector $selector): int
    {
        $dry = (bool) $this->option('dry-run');
        $r = $selector->apply((int) $this->option('max-year'), $dry);
        $this->info(sprintf('%s : %d image(s) retenue(s) avant, %d après.', $dry ? 'Simulation' : 'Fait', $r['before'], $r['after']));

        return self::SUCCESS;
    }
}

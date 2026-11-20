<?php

namespace App\Console\Commands;

use App\Services\Signalling\SignallingMap;
use Illuminate\Console\Command;

/**
 * Recalcule le tracé des tranches de signalisation (vue matérialisée `line_signalling_map`, calque
 * « Signalisation » de /maps) et vide le cache de ses tuiles. Lecture seule sur les données sources ;
 * environ 9 s. vff:import-sncf-signalling le fait déjà à la fin de chaque import.
 *
 *   php artisan vff:refresh-signalling-map
 */
class RefreshSignallingMap extends Command
{
    protected $signature = 'vff:refresh-signalling-map';

    protected $description = 'Recalcule le tracé des tranches de signalisation pour la carte (vue matérialisée).';

    public function handle(SignallingMap $map): int
    {
        $pieces = $map->refresh();
        $this->info("Carte de la signalisation : {$pieces} morceaux de tracé recalculés");

        return self::SUCCESS;
    }
}

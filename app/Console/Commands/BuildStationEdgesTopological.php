<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * @deprecated Alias conservé pour les anciens scripts : la couche gare-à-gare
 * (station_edges) est construite, avec la topologie de chaque ère, par
 * vff:build-network-graph (App\Services\NetworkGraph\EraGraphBuilder).
 */
class BuildStationEdgesTopological extends Command
{
    protected $signature = 'vff:build-station-edges-topo
                            {--era= : Année (défaut : toutes les ères)}';

    protected $description = 'Alias de vff:build-network-graph (graphe par ère, gare-à-gare compris).';

    public function handle(): int
    {
        return $this->call('vff:build-network-graph', array_filter(['--era' => $this->option('era')]));
    }
}

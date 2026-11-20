<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Ancien point d'entrée (vff:rebuild-pipeline --with-stations), conservé pour
 * compatibilité : le statut des gares est désormais calculé par
 * vff:build-station-eras, pour TOUTES les ères à la fois (chronologie de la
 * gare plafonnée par l'état des tronçons de chaque ère : ligne fermée → gare
 * fermée, quels que soient les drapeaux SNCF voyageurs / fret).
 */
class DeriveStationStatus extends Command
{
    protected $signature = 'vff:derive-station-status {--era= : Une seule ère (année)}';

    protected $description = 'Statut des gares par ère, plafonné par l\'état des lignes (délègue à vff:build-station-eras)';

    public function handle(): int
    {
        return $this->call('vff:build-station-eras', array_filter(['--era' => $this->option('era')]));
    }
}

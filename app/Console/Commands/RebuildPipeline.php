<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Point d'entrée UNIQUE et idempotent du pipeline de données (audit 2026-05).
 * Enchaîne les étapes dans le bon ordre, sans ré-import destructif : ne touche
 * QUE les étapes de dérivation/classification (pas de re-fetch OSM qui
 * effacerait la géométrie ou les corrections). Pour un re-fetch OSM explicite,
 * lancer vff:import-osm séparément.
 *
 *   php artisan vff:rebuild-pipeline           # ère courante
 *   php artisan vff:rebuild-pipeline --era=2030
 *   php artisan vff:rebuild-pipeline --with-signalling --with-graph
 */
class RebuildPipeline extends Command
{
    protected $signature = 'vff:rebuild-pipeline
        {--era= : année de l\'ère cible (défaut : courante)}
        {--with-refs : re-télécharge aussi les caches SNCF (gares/types/LGV)}
        {--with-signalling : reconstruit la signalisation par PK}
        {--with-graph : reconstruit le graphe ferroviaire (topologie + gare-à-gare) de l\'ère, ou de toutes sans --era}
        {--with-stations : recalcule le statut des gares}';

    protected $description = 'Pipeline idempotent : classification trafic + statut, sans ré-import destructif.';

    public function handle(): int
    {
        $era = $this->option('era');
        // Ère inconnue : refus avant toute étape (sinon vff:classify-lines plante sur firstOrFail).
        if ($era && ! DB::table('eras')->where('year', (int) $era)->exists()) {
            $this->error("Ère {$era} introuvable : pipeline non lancé.");

            return self::FAILURE;
        }
        $eraOpt = $era ? ['--era' => $era] : [];

        $steps = [];
        if ($this->option('with-refs')) {
            $steps[] = ['vff:import-sncf-refs', []];
        }
        // Cœur : classification trafic/LGV de l'ère courante. Le statut SNCF officiel
        // est déjà appliqué portion par portion par vff:build-era-segments (avec les
        // garde-fous IGN / exploitation associative) : NE PAS relancer
        // vff:reclassify-status, qui les écraserait (ex. RER A redevenu « fermé »).
        // Pour toute la chaîne historique : vff:rebuild-history.
        $steps[] = ['vff:classify-lines', $eraOpt];

        if ($this->option('with-stations')) {
            $steps[] = ['vff:derive-station-status', $eraOpt];
        }
        if ($this->option('with-signalling')) {
            $steps[] = ['vff:import-sncf-signalling', $eraOpt];
        }
        if ($this->option('with-graph')) {
            $steps[] = ['vff:build-network-graph', $eraOpt];
        }

        foreach ($steps as [$cmd, $args]) {
            $this->newLine();
            $this->info("▶ {$cmd} ".collect($args)->map(fn ($v, $k) => "{$k}={$v}")->implode(' '));
            $code = $this->call($cmd, $args);
            if ($code !== self::SUCCESS) {
                $this->error("✗ Échec à l'étape {$cmd} : pipeline interrompu.");

                return self::FAILURE;
            }
        }

        Cache::forget('home_stats');
        $this->newLine();
        $this->info('✓ Pipeline terminé (idempotent, relançable sans risque).');

        return self::SUCCESS;
    }
}

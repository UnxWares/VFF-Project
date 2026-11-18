<?php

namespace App\Console\Commands;

use App\Models\Era;
use App\Services\OsmImporter;
use Illuminate\Console\Command;

class EnrichTunnels extends Command
{
    protected $signature = 'vff:enrich-tunnels
        {--region=FR}
        {--era=}
        {--distance=200 : Distance max en mètres pour attacher un way à une Line}';

    protected $description = 'Importe les ways OSM sans ref (tunnels/raccordements) et les attache à la Line existante la plus proche';

    public function handle(OsmImporter $importer): int
    {
        $region = (string) $this->option('region');
        $year = $this->option('era');
        $distance = (int) $this->option('distance');

        $era = $year ? Era::where('year', $year)->first() : Era::where('is_current', true)->first();
        if (! $era) {
            $this->error('Ère introuvable (option --era ou ère courante).');

            return self::FAILURE;
        }

        $this->info("Enrichissement tunnels/raccordements - région={$region} distance={$distance}m");
        $this->line('→ Requête Overpass…');

        try {
            $stats = $importer->enrichTunnels($region, $era, $distance);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("✓ {$stats['fetched']} ways reçus | {$stats['attached']} attachés | {$stats['skipped']} skippés (trop loin)");

        return self::SUCCESS;
    }
}

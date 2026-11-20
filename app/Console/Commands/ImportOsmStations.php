<?php

namespace App\Console\Commands;

use App\Services\OsmImporter;
use Illuminate\Console\Command;

class ImportOsmStations extends Command
{
    protected $signature = 'vff:import-osm-stations {--region=FR}';

    protected $description = 'Importe les gares OSM (railway=station|halt|stop|service_station)';

    public function handle(OsmImporter $importer): int
    {
        $region = (string) $this->option('region');
        $this->info("Import gares OSM - région={$region}");
        $this->line('→ Requête Overpass en cours…');

        try {
            $stats = $importer->importStations($region);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("✓ {$stats['fetched']} nodes reçus | {$stats['created']} créées | {$stats['updated']} mises à jour");

        return self::SUCCESS;
    }
}

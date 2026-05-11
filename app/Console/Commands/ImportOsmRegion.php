<?php

namespace App\Console\Commands;

use App\Models\Era;
use App\Services\OsmImporter;
use Illuminate\Console\Command;

class ImportOsmRegion extends Command
{
    protected $signature = 'vff:import-osm
        {--region=FR : Code région ISO 3166-2 (FR, FR-IDF, ...)}
        {--era= : Année de l\'ère cible (défaut : ère is_current)}';

    protected $description = 'Importe le réseau ferré d\'une région depuis OpenStreetMap (Overpass API)';

    public function handle(OsmImporter $importer): int
    {
        $region = (string) $this->option('region');
        $year = $this->option('era');

        $era = $year
            ? Era::where('year', $year)->firstOrFail()
            : Era::where('is_current', true)->firstOrFail();

        $this->info("Import depuis OSM Overpass — région={$region} ère={$era->year}");

        $this->line('→ Requête Overpass en cours (peut prendre plusieurs minutes)…');
        $payload = $importer->fetch($region);

        $count = count($payload['elements'] ?? []);
        $this->line("→ {$count} éléments reçus.");

        $created = $importer->persist($payload['elements'] ?? [], $era);

        $this->info("✓ {$created} tronçons créés.");

        return self::SUCCESS;
    }
}

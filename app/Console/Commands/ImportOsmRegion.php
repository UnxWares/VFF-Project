<?php

namespace App\Console\Commands;

use App\Models\Era;
use App\Services\OsmImporter;
use Illuminate\Console\Command;

class ImportOsmRegion extends Command
{
    protected $signature = 'vff:import-osm
        {--region=FR : Code région ISO 3166-2 (FR, FR-IDF, ...)}
        {--era= : Année de l\'ère cible (défaut : ère is_current)}
        {--include-unref : Inclut aussi les ways sans tag ref (par défaut, on les ignore : stratégie RFN)}
        {--strict-existing : N\'attache QUE les ways matchant une Line déjà en base (typiquement seedée via SNCF)}';

    protected $description = 'Importe le réseau ferré d\'une région depuis OpenStreetMap (Overpass API)';

    public function handle(OsmImporter $importer): int
    {
        $region = (string) $this->option('region');
        $year = $this->option('era');
        $skipWithoutRef = ! $this->option('include-unref');
        $strictExisting = (bool) $this->option('strict-existing');

        $era = $year ? Era::where('year', $year)->first() : Era::where('is_current', true)->first();
        if (! $era) {
            $this->error('Ère introuvable (option --era ou ère courante).');

            return self::FAILURE;
        }

        $this->info("Import depuis OSM Overpass - région={$region} ère={$era->year} ".
            ($skipWithoutRef ? '(lignes numérotées RFN uniquement)' : '(toutes lignes)'));

        $this->line('→ Requête Overpass en cours (peut prendre plusieurs minutes)…');
        try {
            $payload = $importer->fetch($region);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $count = count($payload['elements']);
        $this->line("→ {$count} éléments reçus.");

        $created = $importer->persist($payload['elements'], $era, $skipWithoutRef, $strictExisting);

        $this->info("✓ {$created} tronçons créés.");

        return self::SUCCESS;
    }
}

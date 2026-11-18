<?php

namespace App\Console\Commands;

use App\Services\OsmImporter;
use Illuminate\Console\Command;

class BackfillLineNames extends Command
{
    protected $signature = 'vff:backfill-names {--region=FR}';

    protected $description = 'Renomme les Lines anonymes (830000 → "Ligne de X à Y") via Overpass tags-only';

    public function handle(OsmImporter $importer): int
    {
        $region = (string) $this->option('region');
        $this->info("Backfill noms - région={$region}");
        $this->line('→ Requête Overpass tags-only en cours…');

        try {
            $updated = $importer->backfillLineNames($region);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("✓ {$updated} lignes renommées.");

        return self::SUCCESS;
    }
}

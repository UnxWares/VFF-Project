<?php

namespace App\Console\Commands;

use App\Models\Era;
use App\Services\SncfOpenDataImporter;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;

class ImportSncfOpenData extends Command
{
    protected $signature = 'vff:import-sncf
        {--era= : Année de l\'ère cible (défaut : ère is_current)}
        {--all-statuses : Importe aussi les Fermée/Neutralisée (défaut: Exploitée uniquement)}';

    protected $description = 'Importe les lignes officielles SNCF Réseau (lignes-par-statut)';

    public function handle(SncfOpenDataImporter $importer): int
    {
        $year = $this->option('era');
        $onlyExploitee = ! $this->option('all-statuses');

        $era = $year
            ? Era::where('year', $year)->firstOrFail()
            : Era::where('is_current', true)->firstOrFail();

        $this->info("Import SNCF Open Data, ère={$era->year} ".
            ($onlyExploitee ? '(Exploitée uniquement)' : '(tous statuts)'));

        $this->line('→ Téléchargement du GeoJSON SNCF…');
        try {
            $geojson = $importer->fetch();
        } catch (\RuntimeException|ConnectionException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        if (! is_array($geojson['features'] ?? null)) {
            $this->error('Réponse SNCF sans « features » : rien importé.');

            return self::FAILURE;
        }
        $featCount = count($geojson['features']);
        $this->line("→ {$featCount} features reçues.");

        $stats = $importer->persist($geojson, $era, $onlyExploitee);

        $this->info("✓ Traités: {$stats['processed']} | Lines créées: {$stats['created_lines']} | Segments: {$stats['created_segments']} | Skippés: {$stats['skipped']}");

        return self::SUCCESS;
    }
}

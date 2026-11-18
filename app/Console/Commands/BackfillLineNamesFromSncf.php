<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Backfill les noms des lignes anonymes (name = "Ligne XXXXXX") depuis
 * SNCF Réseau Open Data - `mode-de-cantonnement-des-lignes` qui contient
 * `code_ligne` + `lib_ligne` (vrai libellé "Ligne de X à Y").
 */
class BackfillLineNamesFromSncf extends Command
{
    protected $signature = 'vff:backfill-line-names-sncf {--force : Écrase les noms même non anonymes}';

    protected $description = 'Backfill les noms des lignes anonymes depuis SNCF Open Data.';

    public function handle(): int
    {
        $this->info('Backfill noms lignes depuis SNCF Open Data…');

        // Récupère tous les couples code_ligne → lib_ligne (unique)
        $this->info('  → Fetch des libellés SNCF…');
        $mapping = [];
        $offset = 0;
        $limit = 100;
        do {
            try {
                $response = Http::timeout(60)
                    ->get('https://ressources.data.sncf.com/api/explore/v2.1/catalog/datasets/mode-de-cantonnement-des-lignes/records',
                        ['limit' => $limit, 'offset' => $offset, 'select' => 'code_ligne,lib_ligne']);
            } catch (ConnectionException $e) {
                $response = null;
            }
            // Source indisponible (délai dépassé, HTTP 5xx, réponse non JSON) : on s'arrête net,
            // sans rien écrire, plutôt que de conclure « 0 libellé » et de réussir en silence.
            if ($response === null || ! $response->successful() || ! is_array($response->json())) {
                $this->error('SNCF Open Data indisponible ('.($response ? "HTTP {$response->status()}" : 'connexion impossible').') : aucun nom modifié.');

                return self::FAILURE;
            }
            $records = $response->json('results') ?? [];
            if (! $records) {
                break;
            }
            foreach ($records as $r) {
                $code = $r['code_ligne'] ?? null;
                $lib = $r['lib_ligne'] ?? null;
                if (! $code || ! $lib || $lib === $code) {
                    continue;
                }
                $mapping[$code] = $lib;
            }
            $offset += $limit;
        } while (count($records) === $limit && $offset < 5000);

        $this->info('    '.count($mapping).' libellés uniques SNCF récupérés');

        // Backfill
        $this->info('  → Application sur lines anonymes…');
        $updated = 0;
        $skippedExisting = 0;
        $notFound = 0;
        $force = $this->option('force');

        $anonRegex = "name ~ '^Ligne [0-9]+\$'";
        $q = DB::table('lines')->select(['id', 'code', 'name']);
        if (! $force) {
            $q->whereRaw($anonRegex);
        }
        $lines = $q->get();

        $bar = $this->output->createProgressBar($lines->count());
        foreach ($lines as $line) {
            if (! $line->code) {
                $bar->advance();

                continue;
            }
            $newName = $mapping[$line->code] ?? null;
            if (! $newName) {
                $notFound++;
                $bar->advance();

                continue;
            }
            if ($line->name === $newName) {
                $skippedExisting++;
                $bar->advance();

                continue;
            }
            DB::table('lines')->where('id', $line->id)
                ->update(['name' => $newName, 'updated_at' => now()]);
            $updated++;
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();

        $this->info("    ✓ {$updated} noms backfillés");
        $this->info("    · {$skippedExisting} déjà identiques");
        $this->info("    · {$notFound} non trouvés dans SNCF Open Data");

        $remaining = DB::selectOne("SELECT COUNT(*) AS n FROM lines WHERE {$anonRegex}")->n;
        $this->info("Restant anonymes : {$remaining}");

        return self::SUCCESS;
    }
}

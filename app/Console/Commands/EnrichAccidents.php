<?php

namespace App\Console\Commands;

use App\Services\Accidents\Context\AccidentContextImporter;
use App\Services\Accidents\Context\AccidentImageResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Contexte sourcé des accidents (circonstances, déroulement, victimes, causes, suites, mémoire, contradictions),
 * documents d'archives et images de Commons, lus dans database/data/accident_contexts/<slug>.php
 * (cf. App\Services\Accidents\Context\AccidentContextImporter et AccidentContextValidator).
 *
 * Rejouable et idempotente. Requêtes : seulement l'API de Wikimedia Commons pour l'auteur et la licence des
 * images (User-Agent générique, une requête par seconde au plus, cache dans storage/app/data/accidents/commons).
 * À rejouer après vff:import-accidents, qui réécrit la date et le bilan depuis ses propres sources.
 *
 *   vff:enrich-accidents --check                    # contrôle des fichiers, rien n'est écrit
 *   vff:enrich-accidents --only=1842-05-08-meudon   # une fiche (option répétable)
 *   vff:enrich-accidents --dry-run --offline        # tout calculer sans rien garder ni rien demander
 */
class EnrichAccidents extends Command
{
    protected $signature = 'vff:enrich-accidents
        {--check : Contrôle les fichiers de contexte sans rien écrire}
        {--only=* : Slug d\'une fiche à traiter (répétable) ; sans cette option, tous les fichiers, et le contexte d\'une fiche dont le fichier a disparu est retiré}
        {--dry-run : Calcule tout dans une transaction annulée}
        {--offline : N\'interroge pas Commons : métadonnées des images lues dans le cache seulement}
        {--dir= : Dossier des fichiers de contexte (par défaut database/data/accident_contexts)}
        {--report= : Fichier JSON du bilan détaillé}';

    protected $description = 'Enregistre le contexte sourcé des accidents (archives, presse, enquêtes) depuis database/data/accident_contexts';

    public function handle(AccidentContextImporter $importer): int
    {
        $images = new AccidentImageResolver(AccidentImageResolver::defaultCacheDir(), (bool) $this->option('offline'));
        /** @var list<string> $only */
        $only = array_values(array_filter((array) $this->option('only'), 'is_string'));
        $result = $importer->run(
            (string) ($this->option('dir') ?: AccidentContextImporter::defaultDirectory()),
            $only,
            $images,
            (bool) $this->option('dry-run'),
            (bool) $this->option('check'),
        );

        foreach ($result->invalid as $slug => $errors) {
            $this->error("{$slug} :");
            foreach ($errors as $e) {
                $this->line("  - {$e}");
            }
        }
        foreach ($result->imagesRejected as $line) {
            $this->warn("Image écartée : {$line}");
        }
        foreach ($result->corrections as $line) {
            $this->line("Correction : {$line}");
        }
        if ($this->option('check')) {
            $this->info(sprintf('Contrôle : %d fichier(s), %d en erreur.', $result->checked, count($result->invalid)));
        } else {
            $this->info(($this->option('dry-run') ? 'Simulation : ' : '').sprintf(
                '%d fiche(s) écrite(s) sur %d fichier(s) ; %d document(s), %d énoncé(s), %d image(s) ; %d correction(s) ; %d contexte(s) retiré(s) ; %d requête(s) à Commons.',
                count($result->written), $result->checked, $result->references, $result->entries, $result->images,
                count($result->corrections), count($result->removed), $result->imageRequests,
            ));
        }

        if (($path = $this->option('report')) !== null) {
            File::ensureDirectoryExists(dirname((string) $path));
            File::put((string) $path, (string) json_encode(get_object_vars($result), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $this->line("Bilan détaillé : {$path}");
        }

        return $result->invalid === [] ? self::SUCCESS : self::FAILURE;
    }
}

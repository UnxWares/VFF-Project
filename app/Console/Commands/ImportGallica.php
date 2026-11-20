<?php

namespace App\Console\Commands;

use App\Services\Archives\ArchiveMatcher;
use App\Services\Archives\CatalogueSruClient;
use App\Services\Archives\GallicaImporter;
use App\Services\Archives\LinePlaceIndex;
use App\Services\Archives\LinePlaces;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Documents de Gallica (BnF) rattachés aux lignes → table line_archive_documents (section « Dans les
 * archives » de la fiche ligne, GET /api/v1/lines/{slug}/archives).
 *
 * Seules les métadonnées des notices du catalogue général de la BnF sont gardées (Licence Ouverte :
 * titre, date, type, auteurs, cote, identifiants ark), avec un lien vers Gallica ; aucune image, vignette
 * ni texte numérisé (database/data/source_rights.php, fournisseur `gallica`). Une requête par seconde au
 * plus, cache disque des réponses (storage/app/data/gallica) : une relance reprend là où l'import s'est
 * arrêté sans redemander les pages déjà lues. N'écrit dans aucune table du moteur.
 *
 * Chaque notice relue est rejugée pour toutes les lignes et ses rattachements sont remplacés ; une notice que
 * la passe n'a pas relue reste en place (seul l'import complet sans erreur retire celles qu'aucune requête ne
 * trouve plus). Une ligne dont une requête échoue (serveur, réponse illisible, page absente du cache avec
 * --offline) garde ses documents : la commande la nomme et se termine en erreur.
 *
 *   vff:import-gallica --dry-run --report=storage/app/data/gallica/candidats.csv
 *   vff:import-gallica --line=rfn-610000 --line=rfn-340000
 *   vff:import-gallica --limit=50 --offline
 */
class ImportGallica extends Command
{
    protected $signature = 'vff:import-gallica
        {--line=* : Slug d\'une ligne dont les requêtes sont relancées (option répétable) ; toutes les lignes par défaut}
        {--limit= : Nombre de lignes traitées au plus, dans l\'ordre des identifiants}
        {--threshold= : Score minimal d\'un document rattaché (9 par défaut, sur 11)}
        {--offline : N\'utilise que le cache local, aucune requête réseau}
        {--refresh : Ignore le cache et relit le catalogue (le cache est réécrit)}
        {--dry-run : Calcule tout sans écrire en base}
        {--report= : Fichier CSV de tous les documents jugés, retenus ou non, avec leur score et ses raisons}';

    protected $description = 'Rattache aux lignes les documents numérisés de Gallica qui en parlent (métadonnées de la BnF et lien, jamais l\'image)';

    public function handle(): int
    {
        $threshold = $this->option('threshold') === null ? ArchiveMatcher::THRESHOLD : (int) $this->option('threshold');
        if ($threshold < 1 || $threshold > ArchiveMatcher::MAX_SCORE) {
            $this->error('Seuil invalide : de 1 à 11.');

            return self::INVALID;
        }

        $index = LinePlaceIndex::load();
        $slugs = array_values(array_filter((array) $this->option('line')));
        $lines = array_values(array_filter($index->lines, fn (LinePlaces $l): bool => $slugs === []
            ? $l->queryPairs !== []
            : in_array($l->slug, $slugs, true)));
        if ($slugs !== [] && count($lines) !== count(array_unique($slugs))) {
            $known = array_map(fn (LinePlaces $l): string => $l->slug, $lines);
            $this->error('Ligne inconnue : '.implode(', ', array_diff($slugs, $known)));

            return self::INVALID;
        }
        if ($this->option('limit') !== null) {
            $lines = array_slice($lines, 0, max(0, (int) $this->option('limit')));
        }
        $complete = $slugs === [] && $this->option('limit') === null;

        $client = CatalogueSruClient::default((bool) $this->option('offline'), (bool) $this->option('refresh'));
        $importer = new GallicaImporter($client);
        $bar = $this->output->createProgressBar(count($lines));
        $result = $importer->run($index, $lines, $threshold, fn () => $bar->advance());
        $bar->finish();
        $this->newLine();

        foreach (array_slice($result['errors'], 0, 20) as $error) {
            $this->warn($error);
        }
        $s = $result['stats'];
        $this->info(sprintf(
            'Lignes : %d ; requêtes : %d (%d au serveur, %d lues dans le cache, %d absentes du cache) ; notices lues : %d ; '
            .'documents retenus : %d sur %d ligne(s) ; notices ambiguës : %d ; requêtes coupées à %d notices : %d ; erreurs : %d.',
            $s['lines'], $s['queries'], $client->networkRequests, $client->cacheHits, $client->cacheMisses, $s['records'],
            $s['accepted'], $s['lines_with_documents'], $s['ambiguous'], CatalogueSruClient::MAX_PAGES * CatalogueSruClient::PAGE_SIZE,
            $s['truncated'], count($result['errors']),
        ));

        if (($path = $this->option('report')) !== null) {
            $this->writeReport((string) $path, $result['candidates']);
            $this->line("Documents jugés : {$path}");
        }

        if ($result['failed'] !== []) {
            $this->error(sprintf(
                '%d ligne(s) non mise(s) à jour, documents gardés tels quels (requête en erreur ou absente du cache) : %s',
                count($result['failed']), implode(', ', array_keys($result['failed'])),
            ));
        }

        if ($this->option('dry-run')) {
            $this->line('Essai (--dry-run) : rien n\'est écrit.');

            return $result['failed'] === [] ? self::SUCCESS : self::FAILURE;
        }
        $stored = $importer->store($result, $complete);
        $this->info("Documents enregistrés : {$stored['written']} ; documents Gallica en base : {$stored['before']} avant, {$stored['after']} après.");

        return $result['failed'] === [] ? self::SUCCESS : self::FAILURE;
    }

    /** @param list<array<string, mixed>> $rows */
    private function writeReport(string $path, array $rows): void
    {
        File::ensureDirectoryExists(dirname($path));
        $out = fopen($path, 'w');
        if ($out === false) {
            $this->error("Impossible d'écrire {$path}");

            return;
        }
        fputcsv($out, ['accepted', 'line', 'line_name', 'score', 'reasons', 'rejected', 'places', 'year', 'date', 'type', 'title', 'gallica_ark'], escape: '');
        foreach ($rows as $r) {
            fputcsv($out, [$r['accepted'] ? 'oui' : 'non', $r['line'], $r['line_name'], $r['score'], $r['reasons'], $r['rejected'],
                $r['places'], $r['year'], $r['date'], $r['type'], $r['title'], $r['gallica_ark']], escape: '');
        }
        fclose($out);
    }
}

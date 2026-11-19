<?php

namespace App\Console\Commands;

use App\Services\ArgentanWebFerro\ArgentanClient;
use App\Services\ArgentanWebFerro\ArticleImporter;
use App\Services\ArgentanWebFerro\Curation;
use App\Services\ArgentanWebFerro\GalleryInventory;
use App\Services\ArgentanWebFerro\VffReader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Argentan Web Ferroviaire (argentanwebferro.fr, Thierry Danglos) → tables de SOURCE :
 * argentanwebferro_articles (inventaire), argentanwebferro_facts (faits datés, arrêts,
 * exploitants, comparés à VFF), argentanwebferro_photos (galerie Piwigo, classes de droits).
 *
 * Droits (database/data/source_rights.php) : faits seulement, avec le lien de l'article et
 * au plus une phrase citée ; galerie sous CC BY-SA 4.0 pour les seules œuvres de l'auteur.
 * N'écrit JAMAIS dans les tables du moteur (line_events, stations, media…) : l'utilisateur
 * décide sur rapport. Exploration polie (cf. ArgentanClient) : robots.txt, 1 requête / 2,5 s,
 * cache local réutilisé.
 *
 *   vff:import-argentanwebferro                 # tout : articles, faits, galerie, comparaison CSV
 *   vff:import-argentanwebferro --offline       # cache seulement (aucune requête réseau)
 *   vff:import-argentanwebferro --dry-run --show
 */
class ImportArgentanWebFerro extends Command
{
    protected $signature = 'vff:import-argentanwebferro
        {--offline : N\'utilise que le cache local, aucune requête réseau}
        {--dry-run : Analyse et affiche, sans écrire en base}
        {--show : Affiche chaque fait extrait}
        {--no-gallery : Ignore la galerie de photos}
        {--csv= : Fichier CSV de la comparaison avec VFF (défaut : storage/app/reports/agents/source-argentan-comparaison.csv)}';

    protected $description = 'Argentan Web Ferroviaire → faits des articles (comparés à VFF) et inventaire des photos libres, tables de source seulement';

    public function handle(): int
    {
        $client = new ArgentanClient(ArgentanClient::defaultCacheDir(), offline: (bool) $this->option('offline'));
        $curation = Curation::load();
        $reader = new VffReader;

        $importer = new ArticleImporter($client, $curation, $reader);
        $data = $importer->collect();
        foreach ($importer->unknownRefs as $ref) {
            $this->warn("Ligne inconnue dans la curation : {$ref}");
        }
        foreach ($importer->orphanCorrections as $fingerprint) {
            $this->warn("Correction sans fait correspondant (article modifié ?) : {$fingerprint}");
        }

        $lineArticles = array_filter($data['articles'], fn (array $a) => $a['is_line_article']);
        $this->info(count($data['articles']).' article(s) inventorié(s), dont '.count($lineArticles).' de lignes ; '
            .count($data['facts']).' fait(s).');
        $this->table(['nature', 'revue', 'statut VFF', 'nombre'], $this->summary($data['facts']));
        if ($this->option('show')) {
            $this->table(['article', 'empreinte', 'nature', 'type', 'date', 'préc.', 'section / arrêt', 'ligne', 'VFF'],
                array_map(fn (array $f) => [
                    mb_strimwidth($f['slug'], 0, 24, '…'), substr($f['fingerprint'], 0, 10), $f['kind'], $f['event_type'] ?? '',
                    $f['event_date'] ?? '', $f['date_precision'] ?? '',
                    mb_strimwidth($f['kind'] === 'stop' ? "{$f['stop_name']} ({$f['stop_nature']}, {$f['pk_m']})" : trim(($f['section_from'] ?? '').' > '.($f['section_to'] ?? ''), ' >').($f['operator'] ?? ''), 0, 46, '…'),
                    ($f['line_id'] ?? '-').' '.$f['line_match'], mb_strimwidth(trim(($f['vff_status'] ?? '').' '.($f['vff_detail'] ?? '')), 0, 50, '…'),
                ], $data['facts']));
        }

        $photos = null;
        if (! $this->option('no-gallery')) {
            $gallery = new GalleryInventory($client, $curation, $reader);
            $photos = $gallery->collect();
            foreach ($gallery->warnings as $warning) {
                $this->warn("Galerie : {$warning}");
            }
            $republishable = array_filter($photos, fn (array $p) => $p['republishable']);
            $this->info(count($photos).' photo(s) dans l\'album « Lignes Normandes », '.count($republishable).' republiable(s).');
            $this->table(['classe', 'nombre'], collect($photos)->countBy('photo_class')->map(fn ($n, $c) => [$c, $n])->values()->all());
        }

        $csv = (string) ($this->option('csv') ?: storage_path('app/reports/agents/source-argentan-comparaison.csv'));
        $this->writeCsv($csv, $data['facts'], $reader);
        $this->line("Comparaison écrite dans {$csv}");

        if ($this->option('dry-run')) {
            $this->line('Simulation : aucune écriture en base. Requêtes réseau : '.$client->requests);

            return self::SUCCESS;
        }
        $written = $importer->persist($data);
        $this->info("Écrit : {$written['articles']} article(s), {$written['facts']} fait(s), {$written['removed']} fait(s) retiré(s).");
        if ($photos !== null) {
            $this->info('Photos : '.(new GalleryInventory($client, $curation, $reader))->persist($photos).' ligne(s).');
        }
        $this->line('Requêtes réseau : '.$client->requests);

        return self::SUCCESS;
    }

    /**
     * @param  list<array<string, mixed>>  $facts
     * @return list<list<string|int>>
     */
    private function summary(array $facts): array
    {
        return collect($facts)->groupBy(fn (array $f) => $f['kind'].'|'.$f['review'].'|'.($f['vff_status'] ?? '-'))
            ->map(fn ($g, $k) => [...explode('|', (string) $k), $g->count()])->sortKeys()->values()->all();
    }

    /** @param  list<array<string, mixed>>  $facts */
    private function writeCsv(string $path, array $facts, VffReader $reader): void
    {
        File::ensureDirectoryExists(dirname($path));
        $lines = $reader->lines(array_values(array_unique(array_filter(array_column($facts, 'line_id')))));
        $out = fopen($path, 'w');
        fputcsv($out, ['empreinte', 'article', 'url', 'nature', 'type', 'date', 'precision', 'annee_fin', 'section_de', 'section_a', 'arret', 'nature_arret', 'pk_m',
            'exploitant', 'ligne_vff_id', 'ligne_vff_code', 'ligne_vff_nom', 'rattachement', 'revue', 'statut_vff', 'detail_vff', 'note', 'citation'], escape: '');
        foreach ($facts as $f) {
            $line = $lines[$f['line_id']] ?? null;
            fputcsv($out, [$f['fingerprint'], $f['slug'], $f['source_url'], $f['kind'], $f['event_type'], $f['event_date'], $f['date_precision'], $f['year_to'],
                $f['section_from'], $f['section_to'], $f['stop_name'], $f['stop_nature'], $f['pk_m'], $f['operator'],
                $f['line_id'], $line['code'] ?? null, $line['name'] ?? null, $f['line_match'], $f['review'], $f['vff_status'], $f['vff_detail'], $f['note'], $f['source_quote']], escape: '');
        }
        fclose($out);
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Line;
use App\Models\Station;
use App\Services\Media\OpenData\OpenDataFetcher;
use App\Services\Media\OpenData\OpenDataMatcher;
use App\Services\Media\OpenData\OpenDataMediaSource;
use App\Services\Media\Sources\MediaWriter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Autochromes d'Albert-Kahn et cartes postales d'archives publiées en données ouvertes, rattachés aux gares et aux
 * lignes (jeux et correspondance de champs : database/data/open_data_media.php).
 *
 * Lecture : API tabulaire de data.gouv.fr ou WFS du Grand Lyon, jamais l'API des portails Opendatasoft (leur
 * robots.txt l'interdit) ; SPARQL et notices Linked Art du Getty ; plan du site et manifestes IIIF de l'École des ponts
 * (jamais sa recherche, interdite par son robots.txt ; la lecture reprend où elle s'est arrêtée) ; une requête toutes les 2 s, cache local `storage/app/sources/open-data-media` (.nobackup).
 * Les images ne sont pas téléchargées. Écriture par MediaWriter (idempotent, clé provider + external_id + fiche).
 *
 *   vff:import-open-data-media --dry-run --sample=20            # mesure à blanc sur le cache (lit le réseau si le cache manque)
 *   vff:import-open-data-media albert-kahn --refresh --dry-run   # relit le jeu sur le réseau, n'écrit rien en base
 *   vff:import-open-data-media                                   # écrit dans media (après la migration des droits)
 */
class ImportOpenDataMedia extends Command
{
    protected $signature = 'vff:import-open-data-media
        {source?* : Clés des jeux (albert-kahn, ad92-9fi, suresnes-cp, evry-cp, sevres-cp, gadagne-cp, sl71-cp, getty, enpc-ph, numistral, rosalis, pireneas) ; tous par défaut}
        {--dry-run : N\'écrit rien en base : compte les images, les gares et les lignes gagnées}
        {--refresh : Relit le jeu sur le réseau et réécrit le cache}
        {--retry-errors : Jeu IIIF : redemande les manifestes dont la lecture avait échoué}
        {--rebuild : Jeu IIIF ou Dublin Core : reconstruit le cache des lignes depuis les manifestes ou notices déjà lus, sans requête}
        {--offline : N\'utilise que le cache local, aucune requête réseau}
        {--sample=0 : Nombre de rattachements tirés au hasard et affichés}
        {--seed=1 : Graine du tirage de l\'échantillon}
        {--limit= : Nombre maximal de lignes lues par jeu}
        {--review= : Fichier JSONL où écrire les vues ferroviaires non rattachées}';

    protected $description = 'Autochromes Albert-Kahn et cartes postales d\'archives ouvertes → media, rattachées aux gares et aux lignes (licence et crédit par jeu)';

    public function handle(OpenDataFetcher $fetcher, MediaWriter $writer): int
    {
        /** @var array<string, array<string, mixed>> $datasets */
        $datasets = require base_path('database/data/open_data_media.php');
        $keys = (array) $this->argument('source');
        $keys = $keys === [] ? array_keys($datasets) : $keys;
        foreach ($keys as $key) {
            if (! isset($datasets[$key])) {
                $this->error("Jeu inconnu : {$key} (jeux : ".implode(', ', array_keys($datasets)).')');

                return self::FAILURE;
            }
        }
        $fetcher->retryErrors = (bool) $this->option('retry-errors');
        $dry = (bool) $this->option('dry-run');
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $matcher = new OpenDataMatcher;
        $review = $this->option('review') !== null ? fopen((string) $this->option('review'), 'wb') : null;
        $examples = [];
        $totals = ['retenues' => 0, 'inserees' => 0, 'mises_a_jour' => 0];
        $stationIds = [];
        $lineIds = [];
        $errors = 0;

        foreach ($keys as $key) {
            $dataset = $datasets[$key];
            if ((bool) $this->option('rebuild') && in_array($dataset['driver'] ?? null, ['iiif', 'oai-dc'], true)) {
                $this->line("{$key} : ".$fetcher->rebuild($key, $dataset).' ligne(s) reconstruites depuis les manifestes ou notices lus.');
            } elseif (! OpenDataFetcher::hasCache($key) || (bool) $this->option('refresh')) {
                if ((bool) $this->option('offline')) {
                    $this->warn("{$key} : pas de cache et --offline, jeu ignoré.");

                    continue;
                }
                try {
                    $n = $fetcher->refresh($key, $dataset, $limit);
                    $this->line("{$key} : {$n} ligne(s) lues sur le réseau ({$fetcher->requestCount} requête(s) depuis le début).");
                } catch (Throwable $e) {
                    $errors++;
                    $this->error("{$key} : lecture impossible : {$e->getMessage()}");

                    continue;
                }
            }

            $source = new OpenDataMediaSource($key, $dataset, $matcher);
            $report = $writer->write($source->records($limit), $dry);
            ksort($source->rejected);
            arsort($source->attached);
            arsort($source->unattached);

            $this->info(sprintf(
                '%s : %d ligne(s) lues, %d vue(s) ferroviaire(s) retenue(s), %d rattachée(s), %d à relire.',
                $key, $source->read, $source->retained, array_sum($source->attached), array_sum($source->unattached),
            ));
            $this->line('  refus : '.json_encode($source->rejected, JSON_UNESCAPED_UNICODE));
            $this->line('  méthodes : '.json_encode($source->attached, JSON_UNESCAPED_UNICODE).' ; non rattachées : '.json_encode($source->unattached, JSON_UNESCAPED_UNICODE));
            $this->line(sprintf(
                '  %s : %d écrite(s), %d mise(s) à jour, refusées par le schéma %s ; gares touchées %d (gagnées %d), lignes touchées %d (gagnées %d).',
                $dry ? 'à blanc' : 'base', $report->inserted, $report->updated, json_encode($report->refused),
                $report->touchedCount(Station::class), $report->gainedCount(Station::class), $report->touchedCount(Line::class), $report->gainedCount(Line::class),
            ));
            $errors += $report->refusedCount();
            $totals['retenues'] += $source->retained;
            $totals['inserees'] += $report->inserted;
            $totals['mises_a_jour'] += $report->updated;
            $stationIds += $source->stations;
            $lineIds += $source->lines;
            foreach ($source->examples as $e) {
                $examples[] = [$key, $e['candidate'], $e['match']];
            }
            if ($review !== null) {
                foreach ($source->review as $row) {
                    fwrite($review, json_encode(['source' => $key] + $row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
                }
            }
        }
        if ($review !== null) {
            fclose($review);
        }

        $served = $stationIds === [] ? 0 : (int) DB::table('station_lines')->whereIn('station_id', array_keys($stationIds))->distinct()->count('line_id');
        $this->info(sprintf(
            '%s : %d image(s) rattachée(s) à %d gare(s) et %d ligne(s) directement ; %d ligne(s) desservent ces gares ; %d erreur(s).',
            $dry ? 'Mesure à blanc' : 'Écrit', $totals['inserees'] + $totals['mises_a_jour'], count($stationIds), count($lineIds), $served, $errors,
        ));

        $sample = (int) $this->option('sample');
        if ($sample > 0 && $examples !== []) {
            mt_srand((int) $this->option('seed'));
            $picked = (array) array_rand($examples, min($sample, count($examples)));
            shuffle($picked);
            foreach ($picked as $i => $k) {
                [$key, $c, $m] = $examples[$k];
                $this->line(sprintf(
                    '%2d. [%s] %s | %s (%s) | %s | %s -> %s %s%s (%s, %s, %s m)',
                    $i + 1, $key, $c['id'], $c['title'] ?? $c['title_alt'], $c['commune'] ?? '?', $c['place'] ?? '-', $c['date_raw'] ?? '-',
                    $m['kind'], $m['name'], isset($m['city']) ? ' ['.$m['city'].', '.$m['status'].']' : '', $m['method'], $m['confidence'], $m['distance_m'] ?? '?',
                ));
            }
        }

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Media;
use App\Services\Media\CommonsCoverage;
use App\Services\Media\Sources\MediaWriter;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Écrit dans `media` les images Commons trouvées par `vff:discover-commons-candidates` pour les
 * fiches qui n'en avaient pas (gares, lignes, séries de matériel).
 *
 * Le fichier de candidats (JSONL) porte les métadonnées déjà normalisées ; cette commande ne fait
 * aucune requête réseau. Elle revérifie chaque fichier (licence CC BY, CC BY-SA, CC0 ou domaine
 * public, auteur ou crédit, format, taille) et ne touche jamais une fiche qui a déjà des images
 * Commons venues de l'import historique : seules les fiches sans image, ou déjà remplies par cette
 * règle (`provider_meta.rule`), sont écrites. Idempotente (upsert sur provider, external_id, fiche).
 *
 *   vff:import-commons-candidates storage/app/sources/commons-candidates/stations.jsonl --dry-run
 *   vff:import-commons-candidates storage/app/sources/commons-candidates/stations.jsonl --dry-run --sample=30 --seed=7
 *   vff:import-commons-candidates storage/app/sources/commons-candidates/stations.jsonl
 */
class ImportCommonsCandidates extends Command
{
    protected $signature = 'vff:import-commons-candidates
        {file : Fichier JSONL produit par vff:discover-commons-candidates}
        {--dry-run : N\'écrit rien en base : compte les fiches et les images, tire un échantillon}
        {--sample=0 : Nombre de rattachements tirés au hasard et affichés (avec --dry-run)}
        {--seed=1 : Graine du tirage de l\'échantillon}
        {--limit= : Nombre maximal de fiches lues}
        {--via= : Origines retenues, séparées par des virgules (p18, category, weak_category, name_category, geosearch, search)}
        {--gains= : Fichier CSV (sujet, slug, nombre d\'images) des fiches qui gagnent des images}';

    protected $description = 'Écrit dans media les images Commons candidates des fiches sans image (licence et auteur revérifiés, sans réseau)';

    public function handle(MediaWriter $writer): int
    {
        $file = (string) $this->argument('file');
        if (! is_file($file)) {
            $this->error("Fichier introuvable : {$file}");

            return self::FAILURE;
        }
        $dry = (bool) $this->option('dry-run');
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : PHP_INT_MAX;

        $stats = ['lues' => 0, 'fiches_absentes_ou_deja_illustrees' => 0, 'deja_images_historiques' => 0, 'fiches_gagnantes' => 0, 'images' => 0, 'rejets_revérification' => 0, 'règle_obsolète' => 0];
        $byVia = [];
        $byType = [];
        $all = [];
        $records = [];
        $only = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('via')))));
        $gainsHandle = $this->option('gains') !== null ? fopen((string) $this->option('gains'), 'wb') : null;
        $handle = fopen($file, 'rb');
        while (($line = fgets($handle)) !== false && $stats['lues'] < $limit) {
            $rec = json_decode($line, true);
            if (! is_array($rec) || ! isset($rec['subject'], $rec['id'], $rec['slug'])) {
                continue;
            }
            $stats['lues']++;
            if (($rec['rules'] ?? 0) !== CommonsCoverage::RULES_VERSION) {
                $stats['règle_obsolète']++;

                continue;
            }
            $class = CommonsCoverage::modelClass((string) $rec['subject']);
            if ($class === null || ($rec['files'] ?? []) === []) {
                continue;
            }
            $query = $class::query()->whereKey($rec['id'])->where('slug', $rec['slug']);
            if ($rec['subject'] === 'material') {
                // Une série qui a déjà son image (champ `image`) n'est pas un trou de couverture.
                $query->whereNull('image');
            }
            $current = $query->first();
            // Fiche absente, ou élément Wikidata changé depuis la découverte (correction d'un lien erroné) : rien.
            if ($current === null || ! $this->sameItem($rec, $current)) {
                $stats['fiches_absentes_ou_deja_illustrees']++;

                continue;
            }
            $historic = DB::table('media')->where('mediable_type', $class)->where('mediable_id', $rec['id'])
                ->where('provider', Media::PROVIDER_COMMONS)
                ->whereRaw("COALESCE(provider_meta->>'rule', '') = ''")->exists();
            if ($historic) {
                $stats['deja_images_historiques']++;

                continue;
            }

            $rows = [];
            $featured = false;
            $pos = $rec['subject'] === 'station'
                ? DB::selectOne('SELECT ST_Y(geom) AS lat, ST_X(geom) AS lon, city, department_code, (SELECT COUNT(*) FROM stations o WHERE o.id <> stations.id AND o.search_name = stations.search_name) AS homonyms FROM stations WHERE id = ?', [$rec['id']])
                : null;
            foreach ($rec['files'] as $f) {
                $meta = $f['meta'] ?? [];
                // Les règles sont rejouées à l'écriture : un durcissement s'applique sans relire Commons.
                $via = (string) ($f['via'] ?? '');
                $reason = match (true) {
                    $via === 'geosearch' && (! CommonsCoverage::titleIsRailway((string) ($meta['file_title'] ?? '')) || ($f['distance_m'] ?? 0) > CommonsCoverage::MAX_GEOSEARCH_DISTANCE_M) => 'hors_sujet',
                    in_array($via, ['name_category', 'weak_category'], true) && CommonsCoverage::offTopicTitle((string) ($meta['file_title'] ?? '')) => 'hors_sujet',
                    in_array($via, ['geosearch', 'weak_category'], true) && ! CommonsCoverage::mentionsPlace($meta, (string) ($rec['name'] ?? '')) => 'hors_sujet',
                    $via === 'search' && (! CommonsCoverage::titleIsRailway((string) ($meta['file_title'] ?? ''))
                        || ! CommonsCoverage::titleNamesStation($meta, (string) ($rec['name'] ?? ''))
                        || ! CommonsCoverage::mentionsCity($meta, $pos->city ?? null, (string) ($rec['name'] ?? ''))) => 'hors_sujet',
                    // Nom partagé avec une autre gare de VFF (homonymes) : la recherche par texte ne peut pas trancher.
                    $via === 'search' && ($pos->homonyms ?? 0) > 0 => 'homonyme',
                    // Règle de lieu : ni pays ni département étranger à la gare, position à 2 km ou commune et département nommés.
                    $via === 'search' && CommonsCoverage::placeReject($meta, (string) ($rec['name'] ?? ''), $pos->city ?? null, $pos->department_code ?? null, $pos?->lat !== null ? (float) $pos->lat : null, $pos?->lon !== null ? (float) $pos->lon : null) !== null => 'hors_sujet',
                    $via === 'search' && ($meta['mime'] ?? '') === 'image/png' => 'hors_sujet',
                    $via === 'search' && (preg_match('#^https?://#i', (string) ($meta['author'] ?? '')) === 1
                        || preg_match('/\b(thermale|mines?)\b/iu', (string) ($meta['file_title'] ?? '')) === 1) => 'hors_sujet',
                    default => null,
                };
                $reason ??= $pos !== null
                    ? CommonsCoverage::stationReject($meta, (string) ($f['via'] ?? ''), (float) $pos->lat, (float) $pos->lon)
                    : CommonsCoverage::railReject($meta, (string) ($f['via'] ?? ''));
                if ($reason !== null) {
                    $stats['rejets_revérification']++;

                    continue;
                }
                if ($only !== [] && ! in_array($f['via'] ?? '', $only, true)) {
                    continue;
                }
                $isP18 = ($f['via'] ?? '') === 'p18' && ! $featured;
                $featured = $featured || $isP18;
                $rows[] = CommonsCoverage::record($class, (int) $rec['id'], (int) $f['pageid'], $meta, [
                    'via' => $f['via'] ?? null, 'category' => $f['category'] ?? null,
                    'qid' => $rec['qid'] ?? null, 'distance_m' => $f['distance_m'] ?? null, 'featured' => $isP18,
                ]);
                $byVia[$f['via'] ?? '?'] = ($byVia[$f['via'] ?? '?'] ?? 0) + 1;
                $all[] = [$rec['subject'], $rec['slug'], $rec['name'] ?? '', $f];
            }
            if ($rows === []) {
                continue;
            }
            $stats['fiches_gagnantes']++;
            $stats['images'] += count($rows);
            $byType[$rec['subject']]['fiches'] = ($byType[$rec['subject']]['fiches'] ?? 0) + 1;
            $byType[$rec['subject']]['images'] = ($byType[$rec['subject']]['images'] ?? 0) + count($rows);
            array_push($records, ...$rows);
            if ($gainsHandle !== null && $gainsHandle !== false) {
                fputcsv($gainsHandle, [$rec['subject'], $rec['slug'], count($rows)], escape: '\\');
            }
        }
        fclose($handle);
        if ($gainsHandle !== null && $gainsHandle !== false) {
            fclose($gainsHandle);
        }
        $report = $writer->write($records, $dry);

        $this->info(($dry ? 'Passe à blanc : ' : 'Écrit : ').json_encode($stats, JSON_UNESCAPED_UNICODE));
        $this->line(sprintf(
            'MediaWriter : %d nouveau(x), %d déjà là, %d refusé(s) %s ; fiches gagnées (aucun média avant) : %d sur %d touchées',
            $report->inserted, $report->updated, $report->refusedCount(), json_encode($report->refused), $report->gainedCount(), $report->touchedCount(),
        ));
        $this->line('Par sujet : '.json_encode($byType, JSON_UNESCAPED_UNICODE));
        $this->line('Par origine : '.json_encode($byVia, JSON_UNESCAPED_UNICODE));

        $sample = (int) $this->option('sample');
        if ($sample > 0 && $all !== []) {
            mt_srand((int) $this->option('seed'));
            $keys = (array) array_rand($all, min($sample, count($all)));
            shuffle($keys);
            foreach ($keys as $i => $k) {
                [$subject, $slug, $name, $f] = $all[$k];
                $m = $f['meta'];
                $this->line(sprintf(
                    '%2d. [%s] %s (%s) <- %s | via %s | cat %s | %s m | %s | %s | %s',
                    $i + 1, $subject, $name, $slug, $m['file_title'], $f['via'], $f['category'] ?? '-',
                    $f['distance_m'] ?? '?', $m['license'] ?? '?', $m['author'] ?? ($m['credit'] ?? '?'), $m['depicted_year'] ?? '????',
                ));
            }
        }

        return self::SUCCESS;
    }

    /**
     * L'élément Wikidata de la découverte est-il encore celui de la fiche ? Une fiche dont le lien
     * a été corrigé ou retiré depuis (autre élément en base) ne reçoit pas les images de l'ancien.
     *
     * @param  array<string, mixed>  $rec
     */
    private function sameItem(array $rec, Model $fiche): bool
    {
        $qid = $rec['qid'] ?? null;
        if ($qid === null) {
            return true;
        }
        $current = $rec['subject'] === 'line' ? data_get($fiche->getAttribute('alt_names'), 'wikidata') : $fiche->getAttribute('wikidata_id');

        return $current === null ? $rec['subject'] === 'line' : $current === $qid;
    }
}

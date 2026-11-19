<?php

namespace App\Console\Commands;

use App\Services\Signalling\SignallingHistoryExtractor;
use App\Services\Signalling\WikipediaArticleCache;
use App\Services\WikimediaClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Mises en service datées de signalisation (BAL, BAPR, block manuel, cantonnement téléphonique, TVM, ETCS,
 * KVB comme contrôle de vitesse) lues dans les articles Wikipédia des lignes, rangées dans
 * `line_signalling_events`. Historique par époque de la phase 13 : `line_signalling` (état actuel SNCF Réseau)
 * et la carte par ère ne sont pas touchés.
 *
 * Articles : `lines.wikipedia_title`, déjà vérifié par l'import des chronologies. Le wikitexte est mis en
 * cache dans storage/app/data/signalisation-historique/ (`.nobackup`) ; seul un article absent du cache est
 * demandé à Wikipédia (lecture seule, lots de 50, pause entre deux lots), sauf avec --offline.
 * Un article dont l'infobox annonce un autre code de ligne est écarté ; un article de plusieurs lignes ne donne
 * que des faits peu fiables (jamais montrés).
 *
 * Rejouable : un fait a une empreinte stable (ligne, système, date, portion) ; les faits d'une ligne lue qui ne
 * sont plus extraits sont retirés (cette table seulement).
 *
 *   php artisan vff:import-signalling-history
 *   php artisan vff:import-signalling-history --line=956000 --dry-run -v
 *   php artisan vff:import-signalling-history --offline
 */
class ImportSignallingHistory extends Command
{
    protected $signature = 'vff:import-signalling-history
        {--line=* : limite à ces lignes (code, slug ou identifiant)}
        {--limit= : nombre maximal de lignes}
        {--offline : n\'appelle jamais Wikipédia, lit le cache seulement}
        {--refresh : redemande les articles même s\'ils sont en cache}
        {--cache-dir= : dossier du cache des articles}
        {--dry-run : n\'écrit rien en base, affiche le bilan (et les faits avec -v)}';

    protected $description = 'Range les mises en service de signalisation (BAL, BAPR, BM, CT, TVM, ETCS, KVB) datées dans les articles Wikipédia des lignes.';

    public function handle(WikimediaClient $wiki, SignallingHistoryExtractor $extractor): int
    {
        $lines = $this->lines();
        if ($lines === []) {
            $this->error('Aucune ligne avec un article Wikipédia.');

            return self::FAILURE;
        }
        $this->info('Lignes avec un article : '.count($lines));

        $cache = new WikipediaArticleCache($wiki, $this->option('cache-dir') ?: WikipediaArticleCache::defaultDirectory());
        $titles = array_values(array_unique(array_column($lines, 'wikipedia_title')));
        if (! $this->option('offline')) {
            $asked = $cache->fill($titles, (bool) $this->option('refresh'), fn (int $done, int $all) => $this->line("  Wikipédia : lot {$done}/{$all}"));
            $this->line("  Articles demandés à Wikipédia : {$asked} (le reste vient du cache)");
        }

        $perTitle = array_count_values(array_column($lines, 'wikipedia_title'));
        $stats = ['read' => 0, 'missing' => 0, 'rejected' => 0, 'with_facts' => 0, 'facts' => 0, 'removed' => 0, 'confidence' => [], 'system' => []];
        $dry = (bool) $this->option('dry-run');

        foreach ($lines as $line) {
            $article = $cache->get($line['wikipedia_title']);
            if ($article === null) {
                $stats['missing']++;

                continue;
            }
            $codes = WikimediaClient::codesFromInfobox($article['content']);
            // Article d'une autre ligne (code d'infobox), ou titre partagé sans code pour trancher.
            if (($codes !== [] && ($line['code'] === null || ! in_array($line['code'], $codes, true)))
                || ($codes === [] && $perTitle[$line['wikipedia_title']] > 1)) {
                $stats['rejected']++;

                continue;
            }
            $stats['read']++;
            $facts = $extractor->extract($article['content'], $article['title']);
            if (count($codes) > 1) {
                // Article de plusieurs lignes : on ne sait pas à laquelle le fait s'applique.
                $facts = array_map(fn (array $f) => ['confidence' => 'low'] + $f, $facts);
            }
            $rows = $this->rows($line, $article, $facts);
            if ($rows !== []) {
                $stats['with_facts']++;
            }
            foreach ($rows as $row) {
                $stats['facts']++;
                $stats['confidence'][$row['confidence']] = ($stats['confidence'][$row['confidence']] ?? 0) + 1;
                $stats['system'][$row['system']] = ($stats['system'][$row['system']] ?? 0) + 1;
                if ($dry && $this->output->isVerbose()) {
                    $this->line(sprintf('  [%s] %-8s %s %-5s %-6s %s%s', $line['code'] ?? $line['slug'], $row['system'], $row['event_date'], $row['date_precision'], $row['confidence'],
                        $row['scope'] === 'section' ? trim(($row['section_from'] ?? '?').' -> '.($row['section_to'] ?? '?')).' ' : $row['scope'].' ', $row['source_excerpt']));
                }
            }
            if (! $dry) {
                $stats['removed'] += $this->store($line['id'], $rows);
            }
        }

        $this->info(sprintf('%s%d lignes lues, %d avec des faits, %d faits (%d retirés car disparus de l\'article).',
            $dry ? '[simulation] ' : '', $stats['read'], $stats['with_facts'], $stats['facts'], $stats['removed']));
        $this->line(sprintf('  Sans article en cache : %d ; article d\'une autre ligne ou partagé : %d.', $stats['missing'], $stats['rejected']));
        ksort($stats['system']);
        $this->table(['Système', 'Faits'], array_map(fn ($k, $v) => [$k, $v], array_keys($stats['system']), $stats['system']));
        $this->table(['Fiabilité', 'Faits'], array_map(fn ($k, $v) => [$k, $v], array_keys($stats['confidence']), $stats['confidence']));

        return self::SUCCESS;
    }

    /** @return list<array{id: int, code: ?string, slug: string, wikipedia_title: string}> */
    private function lines(): array
    {
        $query = DB::table('lines')->whereNotNull('wikipedia_title')->where('wikipedia_title', '!=', '')->orderBy('id');
        $refs = (array) $this->option('line');
        if ($refs !== []) {
            $query->where(fn ($q) => $q->whereIn('code', $refs)->orWhereIn('slug', $refs)
                ->orWhereIn('id', array_map('intval', array_filter($refs, fn ($r) => ctype_digit((string) $r) && strlen((string) $r) < 6))));
        }
        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        return $query->get(['id', 'code', 'slug', 'wikipedia_title'])->map(fn ($l) => [
            'id' => (int) $l->id, 'code' => $l->code, 'slug' => $l->slug, 'wikipedia_title' => $l->wikipedia_title,
        ])->all();
    }

    /**
     * @param  array{id: int}  $line
     * @param  array{title: string, revid: int}  $article
     * @param  list<array<string, mixed>>  $facts
     * @return list<array<string, mixed>>
     */
    private function rows(array $line, array $article, array $facts): array
    {
        $now = now();
        $url = WikimediaClient::permalink($article['title'], $article['revid']);
        $rows = [];
        foreach ($facts as $f) {
            $fingerprint = sha1(implode('|', [$line['id'], $f['system'], $f['date'], $f['section_from'], $f['section_to'], $f['pk_start_m'], $f['pk_end_m']]));
            $rows[$fingerprint] = [
                'line_id' => $line['id'],
                'system' => $f['system'],
                'role' => $f['role'],
                'event_date' => $f['date'],
                'date_precision' => $f['precision'],
                'scope' => $f['scope'],
                'section_from' => $f['section_from'] !== null ? mb_substr($f['section_from'], 0, 255) : null,
                'section_to' => $f['section_to'] !== null ? mb_substr($f['section_to'], 0, 255) : null,
                'pk_start_m' => $f['pk_start_m'],
                'pk_end_m' => $f['pk_end_m'],
                'confidence' => $f['confidence'],
                'origin' => $f['origin'],
                'source' => 'wikipedia',
                'source_title' => $article['title'],
                'source_revision' => $article['revid'],
                'source_url' => $url,
                'source_excerpt' => $f['excerpt'],
                'fingerprint' => $fingerprint,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return array_values($rows);
    }

    /**
     * Écrit les faits d'une ligne et retire ceux qui ne sont plus extraits.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return int nombre de faits retirés
     */
    private function store(int $lineId, array $rows): int
    {
        return DB::transaction(function () use ($lineId, $rows): int {
            if ($rows !== []) {
                DB::table('line_signalling_events')->upsert($rows, ['fingerprint'], [
                    'role', 'date_precision', 'scope', 'confidence', 'origin', 'source_title', 'source_revision', 'source_url', 'source_excerpt', 'updated_at',
                ]);
            }

            return DB::table('line_signalling_events')->where('line_id', $lineId)
                ->whereNotIn('fingerprint', array_column($rows, 'fingerprint') ?: [''])
                ->delete();
        });
    }
}

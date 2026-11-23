<?php

namespace App\Console\Commands;

use App\Services\Signalling\SignallingSectionLocator;
use App\Services\Signalling\WikipediaArticleCache;
use App\Services\Sncf\SncfPk;
use App\Services\Speed\LineSpeeds;
use App\Services\Speed\SpeedHistoryExtractor;
use App\Services\WikimediaClient;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Vitesses maximales des lignes par points kilométriques, en deux parties.
 *
 * 1. `line_speed_ranges` : jeu SNCF Réseau `vitesse-maximale-nominale-sur-ligne` (ODbL), une tranche par
 *    enregistrement, PK de SNCF Réseau lus comme les autres imports SNCF (SncfPk) et rattachés à la ligne par
 *    son code, comme `line_signalling`. Le jeu décrit le réseau au jour même : ère courante seulement (ou --era).
 *    Même cache que vff:import-sncf-vmax (storage/app/sources/sncf-vmax) ; sans cache, le jeu est téléchargé
 *    (sauf --offline). Les tranches de l'ère sont remplacées en entier, dans une transaction.
 * 2. `line_speed_events` : changements de vitesse datés lus dans les articles Wikipédia des lignes
 *    (SpeedHistoryExtractor), depuis le cache des articles de vff:import-signalling-history, sans réseau.
 *    Portion nommée placée sur la ligne par ses gares (SignallingSectionLocator). Rejouable : empreinte stable,
 *    les faits d'une ligne lue qui ne sont plus extraits sont retirés.
 *
 * `line_segments.max_speed_kmh` (carte) n'est pas touché. Avec --dry-run, rien n'est écrit : bilan et couverture.
 *
 *   php artisan vff:import-line-speeds --dry-run
 *   php artisan vff:import-line-speeds --only=ranges
 *   php artisan vff:import-line-speeds --only=history --line=536000 --dry-run -v
 */
class ImportLineSpeeds extends Command
{
    protected $signature = 'vff:import-line-speeds
        {--era= : année de l\'ère (défaut : ère courante)}
        {--only= : ranges (SNCF Réseau) ou history (Wikipédia) ; les deux par défaut}
        {--line=* : limite l\'historique à ces lignes (code, slug ou identifiant)}
        {--offline : jamais de réseau : cache SNCF obligatoire}
        {--cache-dir= : dossier du cache SNCF (défaut : storage/app/sources/sncf-vmax)}
        {--articles-dir= : dossier du cache des articles Wikipédia}
        {--dry-run : n\'écrit rien en base, affiche le bilan}';

    protected $description = 'Importe les vitesses maximales par PK (SNCF Réseau) et leurs changements datés (articles Wikipédia).';

    private const API = 'https://ressources.data.sncf.com/api/explore/v2.1/catalog/datasets/';

    public function handle(SpeedHistoryExtractor $extractor, SignallingSectionLocator $locator, WikimediaClient $wiki): int
    {
        if (! LineSpeeds::available()) {
            $this->error('Tables line_speed_ranges et line_speed_events absentes : migration 2026_12_13_010000 à appliquer.');

            return self::FAILURE;
        }
        $only = $this->option('only');
        if ($only !== null && ! in_array($only, ['ranges', 'history'], true)) {
            $this->error('--only : ranges ou history.');

            return self::FAILURE;
        }
        $era = $this->option('era')
            ? DB::table('eras')->where('year', (int) $this->option('era'))->first(['id', 'year'])
            : DB::table('eras')->where('is_current', true)->first(['id', 'year']);
        if (! $era) {
            $this->error('Ère introuvable (option --era ou ère courante).');

            return self::FAILURE;
        }
        $dry = (bool) $this->option('dry-run');
        $ok = true;
        if ($only !== 'history') {
            $ok = $this->ranges((int) $era->id, (int) $era->year, $dry);
        }
        if ($ok && $only !== 'ranges') {
            $this->history($extractor, $locator, $wiki, $dry);
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    private function ranges(int $eraId, int $eraYear, bool $dry): bool
    {
        try {
            $records = $this->records();
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage().' : aucune vitesse modifiée.');

            return false;
        }
        if ($records === null) {
            $this->error('Pas de cache local du jeu SNCF (--offline) : aucune vitesse modifiée.');

            return false;
        }
        $lineIds = DB::table('lines')->whereNotNull('code')->pluck('id', 'code')->map(fn ($id): int => (int) $id)->all();
        $rows = [];
        $skipped = ['unknown_line' => 0, 'bad_record' => 0];
        $now = now();
        foreach ($records as $r) {
            $code = (string) ($r['code_ligne'] ?? '');
            $kmh = isset($r['v_max']) && is_numeric($r['v_max']) ? (int) $r['v_max'] : null;
            $a = SncfPk::meters($r['pkd'] ?? null);
            $b = SncfPk::meters($r['pkf'] ?? null);
            if ($code === '' || $kmh === null || $kmh <= 0 || $kmh > 400 || $a === null || $b === null || $a === $b) {
                $skipped['bad_record']++;

                continue;
            }
            if (! isset($lineIds[$code])) {
                $skipped['unknown_line']++;

                continue;
            }
            $rows[] = [
                'line_id' => $lineIds[$code], 'era_id' => $eraId,
                'pk_start_m' => min($a, $b), 'pk_end_m' => max($a, $b), 'max_speed_kmh' => $kmh,
                'sncf_rank' => isset($r['rg_troncon']) && is_numeric($r['rg_troncon']) ? (int) $r['rg_troncon'] : null,
                'sncf_id' => isset($r['idgaia']) ? mb_substr((string) $r['idgaia'], 0, 64) : null,
                'source' => 'sncf', 'created_at' => $now, 'updated_at' => $now,
            ];
        }
        $lines = count(array_unique(array_column($rows, 'line_id')));
        $this->info(sprintf('%sVitesses SNCF Réseau (ère %d) : %d tranches sur %d lignes ; écartées : %d d\'une ligne inconnue, %d illisibles.',
            $dry ? '[simulation] ' : '', $eraYear, count($rows), $lines, $skipped['unknown_line'], $skipped['bad_record']));
        $this->coverage($eraId, array_flip(array_unique(array_column($rows, 'line_id'))));
        if ($dry) {
            return true;
        }
        DB::transaction(function () use ($eraId, $rows): void {
            DB::table('line_speed_ranges')->where('era_id', $eraId)->where('source', 'sncf')->delete();
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('line_speed_ranges')->insert($chunk);
            }
        });

        return true;
    }

    /**
     * Couverture : lignes en service à l'ère (un tronçon `in_service` au moins) qui ont une vitesse.
     *
     * @param  array<int, int>  $withSpeed  identifiants des lignes qui ont des tranches
     */
    private function coverage(int $eraId, array $withSpeed): void
    {
        $active = DB::table('line_segments')->where('era_id', $eraId)
            ->where('status', 'in_service')
            ->distinct()->pluck('line_id')->map(fn ($id): int => (int) $id)->all();
        $covered = count(array_filter($active, fn (int $id): bool => isset($withSpeed[$id])));
        $this->line(sprintf('  Couverture : %d lignes en service sur %d ont une vitesse SNCF (%s %%).',
            $covered, count($active), $active === [] ? '0' : number_format(100 * $covered / count($active), 1, ',', ' ')));
    }

    /**
     * Enregistrements du jeu SNCF : cache local, sinon téléchargement complet mis en cache. null : --offline sans cache.
     *
     * @return list<array<string, mixed>>|null
     */
    private function records(): ?array
    {
        $dir = $this->option('cache-dir') ?: (app()->runningUnitTests() ? null : storage_path('app/sources/sncf-vmax'));
        $cache = $dir === null ? null : rtrim($dir, '/').'/'.LineSpeeds::DATASET.'.json';
        if ($cache !== null && is_file($cache)) {
            $cached = json_decode((string) file_get_contents($cache), true);
            if (is_array($cached['records'] ?? null) && $cached['records'] !== []) {
                $this->line('  Cache SNCF du '.($cached['fetched_at'] ?? '?').' ('.count($cached['records']).' enregistrements)');

                return array_values($cached['records']);
            }
        }
        if ($this->option('offline')) {
            return null;
        }
        $all = $this->download();
        if ($cache !== null && $all !== []) {
            @mkdir(dirname($cache), 0775, true);
            file_put_contents($cache, json_encode(['dataset' => LineSpeeds::DATASET, 'fetched_at' => now()->toDateTimeString(), 'records' => $all],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        return $all;
    }

    /** @return list<array<string, mixed>> */
    private function download(): array
    {
        $limit = 100;
        $offset = 0;
        $all = [];
        do {
            try {
                $response = Http::timeout(60)->withUserAgent('VFF-Bot/1.0 (+https://vff-project.org)')
                    ->get(self::API.LineSpeeds::DATASET.'/records', ['limit' => $limit, 'offset' => $offset]);
            } catch (ConnectionException) {
                throw new \RuntimeException('SNCF injoignable');
            }
            if (! $response->successful()) {
                throw new \RuntimeException("SNCF HTTP {$response->status()}");
            }
            $records = $response->json('results') ?? [];
            array_push($all, ...$records);
            $offset += $limit;
        } while (count($records) === $limit && $offset < 10000);

        return $all;
    }

    private function history(SpeedHistoryExtractor $extractor, SignallingSectionLocator $locator, WikimediaClient $wiki, bool $dry): void
    {
        $refs = array_values(array_filter((array) $this->option('line')));
        $query = DB::table('lines')->whereNotNull('wikipedia_title')->where('wikipedia_title', '!=', '');
        if ($refs !== []) {
            $ids = array_map('intval', array_filter($refs, fn (string $r): bool => ctype_digit($r) && strlen($r) < 6));
            $query->where(fn ($w) => $w->whereIn('code', $refs)->orWhereIn('slug', $refs)->orWhereIn('id', $ids ?: [0]));
        }
        $lines = $query->orderBy('id')->get(['id', 'code', 'slug', 'wikipedia_title'])->all();
        $perTitle = DB::table('lines')->whereNotNull('wikipedia_title')->where('wikipedia_title', '!=', '')
            ->groupBy('wikipedia_title')->selectRaw('wikipedia_title, COUNT(*) AS n')->pluck('n', 'wikipedia_title')->map(fn ($n): int => (int) $n)->all();
        $cache = new WikipediaArticleCache($wiki, $this->option('articles-dir') ?: WikipediaArticleCache::defaultDirectory());
        $stats = ['read' => 0, 'missing' => 0, 'rejected' => 0, 'with_facts' => 0, 'facts' => 0, 'shown' => 0, 'removed' => 0];
        $readIds = [];
        foreach ($lines as $line) {
            $article = $cache->get((string) $line->wikipedia_title);
            if ($article === null) {
                $stats['missing']++;

                continue;
            }
            $codes = WikimediaClient::codesFromInfobox($article['content']);
            // Article d'une autre ligne (code d'infobox), ou titre partagé sans code pour trancher.
            if (($codes !== [] && ($line->code === null || ! in_array($line->code, $codes, true)))
                || ($codes === [] && ($perTitle[$line->wikipedia_title] ?? 1) > 1)) {
                $stats['rejected']++;

                continue;
            }
            $stats['read']++;
            $readIds[] = (int) $line->id;
            $facts = $extractor->extract($article['content'], $article['title']);
            if (count($codes) > 1) {
                // Article de plusieurs lignes : on ne sait pas à laquelle le fait s'applique.
                $facts = array_map(fn (array $f): array => ['confidence' => 'low'] + $f, $facts);
            }
            $rows = [];
            $url = WikimediaClient::permalink($article['title'], (int) $article['revid']);
            foreach ($facts as $f) {
                $place = $locator->locate((int) $line->id, $f['scope'], $f['section_from'], $f['section_to'], $f['pk_start_m'], $f['pk_end_m']);
                $rows[] = [
                    'line_id' => (int) $line->id, 'event_date' => $f['date'], 'date_precision' => $f['precision'],
                    'kind' => $f['kind'], 'speed_kmh' => $f['speed_kmh'], 'previous_speed_kmh' => $f['previous_speed_kmh'],
                    'scope' => $f['scope'], 'section_from' => $f['section_from'], 'section_to' => $f['section_to'],
                    'pk_start_m' => $place['pk_start_m'], 'pk_end_m' => $place['pk_end_m'],
                    'confidence' => $f['confidence'], 'source' => 'wikipedia', 'source_title' => $article['title'],
                    'source_revision' => (int) $article['revid'], 'source_url' => mb_substr($url, 0, 600),
                    'source_excerpt' => mb_substr($f['excerpt'], 0, 255),
                    'fingerprint' => sha1(implode('|', [$line->id, $f['date'], $f['kind'], $f['speed_kmh'], $f['section_from'], $f['section_to']])),
                ];
                if ($dry && $this->output->isVerbose()) {
                    $this->line(sprintf('  [%s] %s %-5s %-7s %s%d km/h %-6s %s', $line->code ?? $line->slug, $f['date'], $f['precision'], $f['kind'],
                        $f['previous_speed_kmh'] !== null ? $f['previous_speed_kmh'].' -> ' : '', $f['speed_kmh'], $f['confidence'], $f['excerpt']));
                }
            }
            $stats['with_facts'] += $rows === [] ? 0 : 1;
            $stats['facts'] += count($rows);
            $stats['shown'] += count(array_filter($rows, fn (array $r): bool => $r['confidence'] !== 'low'));
            if (! $dry) {
                $stats['removed'] += $this->store((int) $line->id, $rows);
            }
        }
        if ($refs === []) {
            $orphans = DB::table('line_speed_events')->where('source', 'wikipedia')->whereNotIn('line_id', $readIds ?: [0]);
            $stats['removed'] += $dry ? $orphans->count() : $orphans->delete();
        }
        $this->info(sprintf('%sHistorique : %d articles lus, %d lignes avec des faits, %d faits (%d montrés, fiabilité haute ou moyenne), %d retirés.',
            $dry ? '[simulation] ' : '', $stats['read'], $stats['with_facts'], $stats['facts'], $stats['shown'], $stats['removed']));
        $this->line(sprintf('  Sans article en cache : %d ; article d\'une autre ligne ou partagé : %d.', $stats['missing'], $stats['rejected']));
    }

    /**
     * Remplace les faits de Wikipédia d'une ligne ; renvoie le nombre de faits retirés.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function store(int $lineId, array $rows): int
    {
        return DB::transaction(function () use ($lineId, $rows): int {
            $now = now();
            foreach ($rows as $row) {
                DB::table('line_speed_events')->upsert([$row + ['created_at' => $now, 'updated_at' => $now]], ['fingerprint'],
                    ['event_date', 'date_precision', 'kind', 'speed_kmh', 'previous_speed_kmh', 'scope', 'section_from', 'section_to',
                        'pk_start_m', 'pk_end_m', 'confidence', 'source_title', 'source_revision', 'source_url', 'source_excerpt', 'updated_at']);
            }

            return DB::table('line_speed_events')->where('line_id', $lineId)->where('source', 'wikipedia')
                ->whereNotIn('fingerprint', array_column($rows, 'fingerprint') ?: [''])->delete();
        });
    }
}

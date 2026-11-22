<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Texte « À propos » des fiches lignes : introduction de l'article Wikipédia de la ligne
 * (lines.wikipedia_title), avec son attribution (titre, version citée, licence
 * CC BY-SA 4.0) dans line_sources (source_type « wikipedia-intro »). Aucun texte n'est
 * inventé : une ligne sans article reste sans description.
 *
 *   - API MediaWiki par lots de 20 titres (extraits en texte brut, introduction seule,
 *     redirections suivies, pages d'homonymie écartées), User-Agent VFF-Bot ;
 *   - cache par titre dans storage/app/sources/wikipedia-intros (--from-cache : aucune
 *     requête réseau ; --refresh : tout retélécharger) ;
 *   - n'écrit JAMAIS par-dessus une description d'une autre origine (texte rédigé à la
 *     main, cf. database/data/lignes_hors_rfn.php) : seulement si la description est vide
 *     ou vient déjà de cette commande ;
 *   - charte : tirets longs remplacés par un tiret simple (« typographie adaptée »),
 *     longueur limitée à --max-chars, coupée à la fin d'une phrase.
 */
class ImportLineDescriptions extends Command
{
    protected $signature = 'vff:import-line-descriptions
        {--line= : code RFN d\'une seule ligne}
        {--from-cache : n\'utilise que le cache local, aucune requête réseau}
        {--refresh : retélécharge les introductions déjà en cache}
        {--max-chars=1800 : longueur maximale du texte}
        {--cache-dir= : dossier du cache (défaut : storage/app/sources/wikipedia-intros)}
        {--dry-run : affiche sans écrire}';

    protected $description = 'Introduction Wikipédia des lignes (avec attribution CC BY-SA) comme texte « À propos ».';

    public const API = 'https://fr.wikipedia.org/w/api.php';

    public const USER_AGENT = 'VFF-Bot/1.0 (+https://vff-project.org)';

    public const SOURCE_TYPE = 'wikipedia-intro';

    public const LICENSE = 'CC BY-SA 4.0';

    private const BATCH = 20;

    public function handle(): int
    {
        $query = DB::table('lines')->whereNotNull('wikipedia_title')->where('wikipedia_title', '<>', '');
        if ($code = $this->option('line')) {
            $query->where('code', $code);
        }
        $lines = $query->orderBy('id')->get(['id', 'code', 'wikipedia_title', 'description']);
        if ($lines->isEmpty()) {
            $this->info('Aucune ligne avec un article Wikipédia.');

            return self::SUCCESS;
        }

        @mkdir($this->cacheDir(), 0775, true);
        $titles = $lines->pluck('wikipedia_title')->unique()->values()->all();
        if (! $this->option('from-cache')) {
            $missing = $this->option('refresh') ? $titles : array_values(array_filter($titles, fn ($t) => ! is_file($this->cachePath($t))));
            $this->line(count($missing).' article(s) à télécharger sur '.count($titles).'.');
            foreach (array_chunk($missing, self::BATCH) as $n => $batch) {
                if ($n > 0) {
                    usleep(300_000); // politesse envers l'API
                }
                try {
                    $this->fetchBatch($batch);
                } catch (\RuntimeException $e) {
                    $this->error($e->getMessage().' : arrêt, le cache déjà constitué est utilisé.');
                    break;
                }
            }
        }

        $mine = DB::table('line_sources')->where('source_type', self::SOURCE_TYPE)->pluck('line_id')->flip();
        $written = 0;
        $kept = 0;
        $empty = 0;
        foreach ($lines as $line) {
            $cached = is_file($this->cachePath($line->wikipedia_title))
                ? json_decode((string) file_get_contents($this->cachePath($line->wikipedia_title)), true)
                : null;
            $text = is_array($cached) && empty($cached['missing']) && empty($cached['disambiguation'])
                ? self::clean((string) ($cached['extract'] ?? ''), (int) $this->option('max-chars'))
                : '';
            if ($text === '') {
                $empty++;

                continue;
            }
            // Description d'une autre origine (rédigée à la main) : intouchable.
            if ($line->description !== null && ! isset($mine[$line->id])) {
                $kept++;

                continue;
            }
            $written++;
            if ($this->option('dry-run')) {
                $this->line("  {$line->code} « {$cached['title']} » : ".mb_substr($text, 0, 90).'…');

                continue;
            }
            DB::table('lines')->where('id', $line->id)->update(['description' => $text, 'updated_at' => now()]);
            DB::table('line_sources')->updateOrInsert(
                ['line_id' => $line->id, 'source_type' => self::SOURCE_TYPE],
                [
                    'source_url' => self::permalink($cached['title'], $cached['revid'] ?? null),
                    'fetched_at' => $cached['fetched_at'] ?? now(),
                    'fields_provided' => json_encode(['fields' => ['description'], 'title' => $cached['title'],
                        'revision_id' => $cached['revid'] ?? null, 'license' => self::LICENSE], JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }

        $this->info(($this->option('dry-run') ? '[simulation] ' : '')."{$written} description(s) écrite(s), {$kept} rédigée(s) à la main conservée(s), {$empty} sans introduction exploitable.");

        return self::SUCCESS;
    }

    /** Télécharge un lot de titres et met chaque introduction en cache. */
    private function fetchBatch(array $titles): void
    {
        $params = [
            'action' => 'query', 'format' => 'json', 'formatversion' => 2, 'redirects' => 1,
            'prop' => 'extracts|revisions|pageprops', 'exintro' => 1, 'explaintext' => 1, 'exlimit' => 'max',
            'rvprop' => 'ids|timestamp', 'ppprop' => 'disambiguation', 'titles' => implode('|', $titles),
            'maxlag' => 5,
        ];
        $response = null;
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $response = Http::withUserAgent(self::USER_AGENT)->timeout(60)->get(self::API, $params);
            } catch (ConnectionException) {
                $response = null;
            }
            if ($response?->successful() && $response->json('error.code') !== 'maxlag') {
                break;
            }
            if ($attempt < 3) {
                // Serveurs en retard (maxlag) : on attend le délai demandé, sinon une pause croissante.
                usleep(1_000_000 * max($attempt, min(30, (int) ($response?->header('Retry-After') ?: 0))));
            }
        }
        if (! $response?->successful() || $response->json('error.code') === 'maxlag') {
            throw new \RuntimeException('Wikipédia injoignable (HTTP '.($response?->status() ?? 'connexion').')');
        }

        $query = $response->json('query') ?? [];
        // Titre demandé → titre final (normalisation, puis redirection).
        $map = [];
        foreach ($query['normalized'] ?? [] as $n) {
            $map[$n['from']] = $n['to'];
        }
        $redirects = [];
        foreach ($query['redirects'] ?? [] as $r) {
            $redirects[$r['from']] = $r['to'];
        }
        $pages = [];
        foreach ($query['pages'] ?? [] as $p) {
            $pages[$p['title']] = $p;
        }
        foreach ($titles as $t) {
            $final = $map[$t] ?? $t;
            $final = $redirects[$final] ?? $final;
            $p = $pages[$final] ?? null;
            file_put_contents($this->cachePath($t), json_encode([
                'requested' => $t,
                'title' => $p['title'] ?? $final,
                'extract' => $p['extract'] ?? null,
                'revid' => $p['revisions'][0]['revid'] ?? null,
                'revision_timestamp' => $p['revisions'][0]['timestamp'] ?? null,
                'missing' => $p === null || ! empty($p['missing']) || ! empty($p['invalid']),
                'disambiguation' => isset($p['pageprops']['disambiguation']),
                'fetched_at' => now()->toDateTimeString(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        }
    }

    /**
     * Texte affichable : espaces normalisés, tirets longs remplacés (charte), parenthèses
     * vidées par la suppression des modèles retirées, coupé à la fin d'une phrase.
     */
    public static function clean(string $text, int $maxChars = 1800): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = (string) preg_replace(['/\s*[—―]\s*/u', '/\s+–\s+/u', '/--/'], [' - ', ' - ', '-'], $text);
        $text = (string) preg_replace(['/\(\s*[,;]?\s*\)/u', '/[ \t]+/u', '/ ([,.])/u', '/\n{3,}/'], ['', ' ', '$1', "\n\n"], $text);
        $text = trim(implode("\n\n", array_filter(array_map('trim', explode("\n", $text)), fn ($l) => $l !== '')));
        if (mb_strlen($text) < 40) {
            return '';
        }
        if (mb_strlen($text) > $maxChars) {
            $cut = mb_substr($text, 0, $maxChars);
            $end = max(mb_strrpos($cut, '. ') ?: 0, mb_strrpos($cut, ".\n") ?: 0);
            $text = $end > $maxChars / 3 ? mb_substr($cut, 0, $end + 1) : rtrim($cut).'…';
        }

        return $text;
    }

    public static function permalink(string $title, ?int $revid): string
    {
        $t = rawurlencode(str_replace(' ', '_', $title));

        return $revid ? "https://fr.wikipedia.org/w/index.php?title={$t}&oldid={$revid}" : "https://fr.wikipedia.org/wiki/{$t}";
    }

    private function cacheDir(): string
    {
        // Tests : jamais le vrai cache (dossier temporaire si --cache-dir n'est pas donné).
        $default = app()->runningUnitTests() ? sys_get_temp_dir().'/vff-wikipedia-intros-test' : storage_path('app/sources/wikipedia-intros');

        return rtrim($this->option('cache-dir') ?: $default, '/');
    }

    private function cachePath(string $title): string
    {
        return $this->cacheDir().'/'.md5($title).'.json';
    }
}

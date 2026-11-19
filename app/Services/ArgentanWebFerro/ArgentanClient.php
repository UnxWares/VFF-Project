<?php

namespace App\Services\ArgentanWebFerro;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use RuntimeException;

/**
 * Client POLI d'argentanwebferro.fr (Thierry Danglos) : API REST de WordPress (pages et
 * articles) et API ws.php de la galerie Piwigo (/AWF/).
 *
 * Politesse : robots.txt respecté, une requête au plus toutes les 2,5 s, User-Agent générique
 * de VFF (aucune donnée personnelle), cache local dans storage/app/data/argentanwebferro (avec
 * un fichier .nobackup : données régénérables) ; ce qui est en cache n'est jamais redemandé.
 * Hors ligne, seul le cache est lu (aucune requête réseau).
 *
 * Le cache garde les réponses telles quelles (usage interne d'analyse, jamais publié) ; les
 * tables VFF n'en reçoivent que des faits (cf. ArticleFactExtractor).
 */
final class ArgentanClient
{
    public const BASE = 'https://argentanwebferro.fr';

    public const GALLERY = self::BASE.'/AWF';

    public const USER_AGENT = 'VFF-Bot/1.0 (+https://vff-project.org)';

    /** Champs demandés à WordPress : le contenu sert à l'extraction des faits, rien d'autre. */
    private const WP_FIELDS = [
        'pages' => 'id,slug,link,title,date,modified,content',
        'posts' => 'id,slug,link,title,date,modified,categories,content',
    ];

    public int $requests = 0;

    private float $lastRequestAt = 0.0;

    /** @var list<string>|null chemins interdits par robots.txt (User-agent: *) */
    private ?array $disallow = null;

    public function __construct(
        private readonly string $cacheDir,
        private readonly bool $offline = false,
        private readonly int $minIntervalMs = 2500,
    ) {}

    public static function defaultCacheDir(): string
    {
        return storage_path('app/data/argentanwebferro');
    }

    /**
     * Toutes les pages (`pages`) ou tous les articles (`posts`) de WordPress, contenu compris.
     *
     * @return list<array<string, mixed>>
     */
    public function wordpress(string $type): array
    {
        $fields = self::WP_FIELDS[$type] ?? throw new RuntimeException("Type WordPress inconnu : {$type}");
        $all = [];
        for ($page = 1; $page <= 50; $page++) {
            $pageParam = $page === 1 ? '' : "&page={$page}";
            $batch = $this->json("/wp-json/wp/v2/{$type}?per_page=100{$pageParam}&_fields={$fields}");
            if (! is_array($batch) || ! array_is_list($batch) || $batch === []) {
                break;
            }
            $all = [...$all, ...$batch];
            if (count($batch) < 100) {
                break;
            }
        }

        return $all;
    }

    /** @return array<int, string> identifiant → slug des catégories WordPress */
    public function categories(): array
    {
        $rows = $this->json('/wp-json/wp/v2/categories?per_page=100');

        return is_array($rows) ? collect($rows)->mapWithKeys(fn (array $c) => [(int) $c['id'] => (string) $c['slug']])->all() : [];
    }

    /**
     * Appel de l'API Piwigo de la galerie (ws.php, format JSON).
     *
     * @param  array<string, scalar>  $params
     * @return array<string, mixed> champ `result`
     */
    public function piwigo(string $method, array $params = []): array
    {
        $query = http_build_query(['format' => 'json', 'method' => $method, ...$params]);
        $data = $this->json('/AWF/ws.php?'.$query);
        if (! is_array($data) || ($data['stat'] ?? null) !== 'ok') {
            throw new RuntimeException("Piwigo {$method} : réponse invalide");
        }

        return (array) $data['result'];
    }

    /** Réponse JSON décodée (null si absente du cache hors ligne ou illisible). */
    public function json(string $pathAndQuery): mixed
    {
        $body = $this->get($pathAndQuery);

        return $body === null ? null : self::decodeJson($body);
    }

    /**
     * JSON d'une réponse, débarrassé des avertissements PHP que la galerie imprime parfois
     * avant le document (« Warning: Undefined array key… » de pwg.categories.php).
     */
    public static function decodeJson(string $body): mixed
    {
        $start = strcspn($body, '{[');
        if ($start >= strlen($body)) {
            return null;
        }

        return json_decode(substr($body, $start), true);
    }

    /** Corps d'une réponse 2xx (cache d'abord), null si indisponible. */
    public function get(string $pathAndQuery): ?string
    {
        $url = self::BASE.$pathAndQuery;
        $cached = $this->cached($url);
        if ($cached !== null) {
            return $cached['status'] >= 200 && $cached['status'] < 300 ? $cached['body'] : null;
        }
        if ($this->offline) {
            return null;
        }
        if (! $this->allowed($pathAndQuery)) {
            throw new RuntimeException("robots.txt interdit {$pathAndQuery}");
        }
        [$status, $body] = $this->fetch($url);
        $this->store($url, $status, $body);

        return $status >= 200 && $status < 300 ? $body : null;
    }

    /** Chemin autorisé par robots.txt (groupe « User-agent: * »). */
    public function allowed(string $pathAndQuery): bool
    {
        if ($this->disallow === null) {
            $robots = $this->cached(self::BASE.'/robots.txt')['body'] ?? null;
            if ($robots === null && ! $this->offline) {
                [$status, $robots] = $this->fetch(self::BASE.'/robots.txt');
                $this->store(self::BASE.'/robots.txt', $status, $robots);
            }
            $this->disallow = self::parseRobots((string) $robots);
        }
        $path = (string) parse_url($pathAndQuery, PHP_URL_PATH);

        return array_filter($this->disallow, fn (string $prefix) => str_starts_with($path, $prefix)) === [];
    }

    /** @return list<string> préfixes interdits au groupe « User-agent: * » */
    public static function parseRobots(string $robots): array
    {
        $disallow = [];
        $inStar = false;
        foreach (preg_split('/\R/', $robots) ?: [] as $line) {
            $line = trim((string) preg_replace('/#.*/', '', $line));
            if (preg_match('/^user-agent:\s*(.+)$/i', $line, $m)) {
                $inStar = trim($m[1]) === '*';
            } elseif ($inStar && preg_match('/^disallow:\s*(\S+)/i', $line, $m)) {
                $disallow[] = $m[1];
            }
        }

        return $disallow;
    }

    /** @return array{0: int, 1: string} */
    private function fetch(string $url): array
    {
        $wait = $this->lastRequestAt + $this->minIntervalMs / 1000 - microtime(true);
        if ($wait > 0) {
            Sleep::for((int) ceil($wait * 1000))->milliseconds();
        }
        $response = Http::withHeaders(['User-Agent' => self::USER_AGENT, 'Accept-Language' => 'fr'])
            ->timeout(60)->retry(2, 5000, throw: false)->get($url);
        $this->lastRequestAt = microtime(true);
        $this->requests++;

        return [$response->status(), $response->body()];
    }

    /** Fichier de cache d'une URL (même disposition que l'exploration initiale : u/<sha1 16>.body|.meta). */
    private function cachePath(string $url): string
    {
        return $this->cacheDir.'/raw/u/'.substr(sha1($url), 0, 16);
    }

    /** @return array{status: int, body: string}|null */
    private function cached(string $url): ?array
    {
        $path = $this->cachePath($url);
        if (! is_file($path.'.body') || ! is_file($path.'.meta')) {
            return null;
        }
        $meta = json_decode((string) file_get_contents($path.'.meta'), true);

        return ['status' => (int) ($meta['status'] ?? 0), 'body' => (string) file_get_contents($path.'.body')];
    }

    private function store(string $url, int $status, string $body): void
    {
        $path = $this->cachePath($url);
        File::ensureDirectoryExists(dirname($path));
        if (! is_file($this->cacheDir.'/.nobackup')) {
            File::put($this->cacheDir.'/.nobackup', '');
        }
        File::put($path.'.body', $body);
        File::put($path.'.meta', (string) json_encode(['url' => $url, 'status' => $status, 'fetched' => now()->toIso8601String()],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}

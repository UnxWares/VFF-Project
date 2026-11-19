<?php

namespace App\Services\Accidents;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use RuntimeException;

/**
 * Client POLI des sources d'accidents : service SPARQL et API de Wikidata, API de Wikipédia,
 * fichiers publiés par le BEA-TT.
 *
 * Politesse : User-Agent générique de VFF (aucune donnée personnelle), une requête au plus par
 * seconde et par hôte (le BEA-TT demande Crawl-Delay: 1), maxlag=5 sur les API MediaWiki,
 * attente puis nouvel essai sur HTTP 429/503 (Retry-After respecté), requêtes groupées par 50.
 *
 * Cache local dans storage/app/data/accidents (fichier .nobackup : données régénérables) : ce
 * qui est en cache n'est jamais redemandé, sauf avec $refresh. Hors ligne ($offline), seul le
 * cache est lu et ce qui manque est signalé dans $missing.
 */
final class AccidentSourceClient
{
    public const USER_AGENT = 'VFF-Bot/1.0 (+https://vff-project.org)';

    public const SPARQL = 'https://query.wikidata.org/sparql';

    public const WIKIDATA_API = 'https://www.wikidata.org/w/api.php';

    public int $requests = 0;

    /** @var list<string> éléments demandés hors ligne mais absents du cache */
    public array $missing = [];

    /** @var array<string, float> dernière requête par hôte (microtime) */
    private array $lastRequestAt = [];

    public function __construct(
        private readonly string $cacheDir,
        private readonly bool $offline = false,
        private readonly bool $refresh = false,
        private readonly int $minIntervalMs = 1000,
    ) {}

    public static function defaultCacheDir(): string
    {
        return storage_path('app/data/accidents');
    }

    public function cacheDir(): string
    {
        return $this->cacheDir;
    }

    /**
     * Résultats d'une requête SPARQL (liste des liaisons), en cache sous $name.
     *
     * @return list<array<string, array{type: string, value: string}>>
     */
    public function sparql(string $query, string $name): array
    {
        $json = $this->cachedJson("wikidata/sparql-{$name}.json", function () use ($query): ?string {
            $response = $this->send(self::SPARQL, fn () => Http::withUserAgent(self::USER_AGENT)
                ->withHeaders(['Accept' => 'application/sparql-results+json'])
                ->asForm()->timeout(120)->post(self::SPARQL, ['query' => $query]));

            return $response;
        });

        return array_values($json['results']['bindings'] ?? []);
    }

    /**
     * Éléments Wikidata complets (libellés fr/en/de, déclarations, liens vers Wikipédia), un
     * fichier de cache par élément, 50 éléments par requête.
     *
     * @param  list<string>  $ids
     * @return array<string, array<string, mixed>> indexé par identifiant (Q…)
     */
    public function entities(array $ids, bool $withSitelinks = true): array
    {
        $out = [];
        $toFetch = [];
        foreach (array_values(array_unique($ids)) as $id) {
            if (! preg_match('/^Q\d+$/', $id)) {
                continue;
            }
            $cached = $this->refresh ? null : $this->readJson("wikidata/entities/{$id}.json");
            if ($cached !== null) {
                $out[$id] = $cached;
            } else {
                $toFetch[] = $id;
            }
        }
        if ($this->offline) {
            array_push($this->missing, ...$toFetch);

            return $out;
        }
        foreach (array_chunk($toFetch, 50) as $chunk) {
            $json = $this->mediawiki(self::WIKIDATA_API, [
                'action' => 'wbgetentities', 'ids' => implode('|', $chunk), 'languages' => 'fr|en|de',
                'props' => $withSitelinks ? 'labels|claims|sitelinks|info' : 'labels|claims|info',
                'sitefilter' => 'frwiki|enwiki|dewiki',
            ]);
            foreach ($json['entities'] ?? [] as $id => $entity) {
                if (! isset($entity['missing']) && is_array($entity)) {
                    $this->writeJson("wikidata/entities/{$id}.json", $entity);
                    $out[$id] = $entity;
                }
            }
        }

        return $out;
    }

    /**
     * Membres (articles, espace de noms 0) d'une catégorie de Wikipédia, avec leur élément
     * Wikidata.
     *
     * @return array<string, string> titre => Q…
     */
    public function categoryItems(string $lang, string $category): array
    {
        $api = "https://{$lang}.wikipedia.org/w/api.php";
        $slug = substr(sha1($category), 0, 12);

        return $this->cachedJson("wikipedia/{$lang}-category-{$slug}.json", function () use ($api, $category): string {
            $items = [];
            $continue = [];
            do {
                $json = $this->mediawiki($api, [
                    'action' => 'query', 'generator' => 'categorymembers', 'gcmtitle' => $category,
                    'gcmnamespace' => 0, 'gcmlimit' => 50, 'prop' => 'pageprops', 'ppprop' => 'wikibase_item',
                ] + $continue);
                foreach ($json['query']['pages'] ?? [] as $page) {
                    if (isset($page['pageprops']['wikibase_item'])) {
                        $items[$page['title']] = $page['pageprops']['wikibase_item'];
                    }
                }
                $continue = $json['continue'] ?? [];
            } while ($continue !== []);
            ksort($items);

            return (string) json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }) ?? [];
    }

    /**
     * Introduction (section 0, infobox comprise) et révision de plusieurs articles, 50 par
     * requête, redirections suivies ; un fichier de cache par titre demandé.
     *
     * @param  list<string>  $titles
     * @return array<string, array{title: string, revid: int, content: string}> indexé par titre demandé
     */
    public function articleLeads(string $lang, array $titles): array
    {
        return $this->articles($lang, $titles, full: false);
    }

    /**
     * Texte complet (wikicode) et révision de plusieurs articles, 10 par requête (les pages de
     * liste sont longues), redirections suivies ; un fichier de cache par titre demandé.
     *
     * @param  list<string>  $titles
     * @return array<string, array{title: string, revid: int, content: string}> indexé par titre demandé
     */
    public function articleTexts(string $lang, array $titles): array
    {
        return $this->articles($lang, $titles, full: true);
    }

    /**
     * @param  list<string>  $titles
     * @return array<string, array{title: string, revid: int, content: string}>
     */
    private function articles(string $lang, array $titles, bool $full): array
    {
        $api = "https://{$lang}.wikipedia.org/w/api.php";
        $out = [];
        $toFetch = [];
        foreach (array_values(array_unique($titles)) as $title) {
            $cached = $this->refresh ? null : $this->readJson($this->leadPath($lang, $title, $full));
            if ($cached !== null) {
                $out[$title] = $cached;
            } else {
                $toFetch[] = $title;
            }
        }
        if ($this->offline) {
            array_push($this->missing, ...array_map(fn ($t) => "{$lang}:{$t}", $toFetch));

            return $out;
        }
        foreach (array_chunk($toFetch, $full ? 10 : 50) as $chunk) {
            $json = $this->mediawiki($api, [
                'action' => 'query', 'prop' => 'revisions', 'rvprop' => 'content|ids', 'rvslots' => 'main',
                'redirects' => 1, 'titles' => implode('|', $chunk),
            ] + ($full ? [] : ['rvsection' => 0]));
            $aliases = [];
            foreach (['normalized', 'redirects'] as $k) {
                foreach ($json['query'][$k] ?? [] as $n) {
                    $aliases[$n['from']] = $n['to'];
                }
            }
            $pages = [];
            foreach ($json['query']['pages'] ?? [] as $page) {
                $content = $page['revisions'][0]['slots']['main']['content'] ?? null;
                if (is_string($content)) {
                    $pages[$page['title']] = ['title' => $page['title'], 'revid' => (int) $page['revisions'][0]['revid'], 'content' => $content];
                }
            }
            foreach ($chunk as $title) {
                $final = $title;
                for ($i = 0; $i < 3 && isset($aliases[$final]); $i++) {
                    $final = $aliases[$final];
                }
                if (isset($pages[$final])) {
                    $this->writeJson($this->leadPath($lang, $title, $full), $pages[$final]);
                    $out[$title] = $pages[$final];
                }
            }
        }

        return $out;
    }

    /**
     * Fichier publié (PDF du BEA-TT…) : chemin local du fichier en cache, null s'il manque hors
     * ligne ou si le serveur le refuse.
     */
    public function file(string $url, string $name): ?string
    {
        $path = $this->cacheDir.'/'.$name;
        if (! $this->refresh && is_file($path)) {
            return $path;
        }
        if ($this->offline) {
            $this->missing[] = $url;

            return is_file($path) ? $path : null;
        }
        $body = $this->send($url, fn () => Http::withUserAgent(self::USER_AGENT)->timeout(120)->get($url));
        if ($body === null) {
            return is_file($path) ? $path : null;
        }
        $this->ensureDir(dirname($path));
        File::put($path, $body);

        return $path;
    }

    /** Écrit un fichier dérivé dans le cache (conversion d'un PDF, par exemple). */
    public function putDerived(string $name, string $contents): string
    {
        $path = $this->cacheDir.'/'.$name;
        $this->ensureDir(dirname($path));
        File::put($path, $contents);

        return $path;
    }

    /**
     * Appel d'une API MediaWiki (maxlag, reprises). Réponse JSON décodée.
     *
     * @param  array<string, string|int>  $params
     * @return array<string, mixed>
     */
    private function mediawiki(string $endpoint, array $params): array
    {
        $params += ['format' => 'json', 'formatversion' => 2, 'maxlag' => 5];
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $body = $this->send($endpoint, fn () => Http::withUserAgent(self::USER_AGENT)->timeout(60)->get($endpoint, $params));
            $json = $body !== null ? json_decode($body, true) : null;
            if (is_array($json) && ($json['error']['code'] ?? null) === 'maxlag') {
                Sleep::sleep(5 * $attempt);

                continue;
            }
            if (is_array($json)) {
                return $json;
            }
        }
        throw new RuntimeException("MediaWiki : pas de réponse exploitable de {$endpoint}");
    }

    /**
     * Requête HTTP espacée d'au moins $minIntervalMs par hôte, avec reprises sur 429/503 et
     * erreurs réseau. Renvoie le corps, ou null après des refus répétés (4xx).
     *
     * @param  callable(): Response  $request
     */
    private function send(string $url, callable $request): ?string
    {
        $host = (string) parse_url($url, PHP_URL_HOST);
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $wait = ($this->lastRequestAt[$host] ?? 0.0) + $this->minIntervalMs / 1000 - microtime(true);
            if ($wait > 0) {
                Sleep::for((int) ceil($wait * 1000))->milliseconds();
            }
            try {
                $response = $request();
            } catch (ConnectionException) {
                $this->lastRequestAt[$host] = microtime(true);
                Sleep::sleep(3 * $attempt);

                continue;
            }
            $this->lastRequestAt[$host] = microtime(true);
            $this->requests++;
            if (in_array($response->status(), [429, 500, 502, 503, 504], true)) {
                Sleep::sleep(min(60, (int) ($response->header('Retry-After') ?: 5 * $attempt)));

                continue;
            }

            return $response->successful() ? $response->body() : null;
        }

        return null;
    }

    /**
     * JSON en cache, ou calculé par $fetch (corps JSON) puis mis en cache.
     *
     * @param  callable(): ?string  $fetch
     * @return array<mixed>|null
     */
    private function cachedJson(string $name, callable $fetch): ?array
    {
        if (! $this->refresh && ($cached = $this->readJson($name)) !== null) {
            return $cached;
        }
        if ($this->offline) {
            $this->missing[] = $name;

            return $this->readJson($name);
        }
        $body = $fetch();
        $json = $body !== null ? json_decode($body, true) : null;
        if (! is_array($json)) {
            return $this->readJson($name);
        }
        $this->writeJson($name, $json);

        return $json;
    }

    /** @return array<mixed>|null */
    private function readJson(string $name): ?array
    {
        $path = $this->cacheDir.'/'.$name;
        if (! is_file($path)) {
            return null;
        }
        $json = json_decode((string) file_get_contents($path), true);

        return is_array($json) ? $json : null;
    }

    /** @param array<mixed> $data */
    private function writeJson(string $name, array $data): void
    {
        $path = $this->cacheDir.'/'.$name;
        $this->ensureDir(dirname($path));
        File::put($path, (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function ensureDir(string $dir): void
    {
        File::ensureDirectoryExists($dir);
        if (! is_file($this->cacheDir.'/.nobackup')) {
            File::put($this->cacheDir.'/.nobackup', '');
        }
    }

    private function leadPath(string $lang, string $title, bool $full = false): string
    {
        return 'wikipedia/'.$lang.($full ? '-full' : '').'/'.substr(sha1($title), 0, 16).'.json';
    }
}

<?php

namespace App\Services\Media\OpenData;

use App\Services\Media\CommonsClient;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use RuntimeException;

/**
 * Lit les lignes d'un jeu ouvert d'images (voir database/data/open_data_media.php) et les garde en cache local.
 *
 * Deux accès, tous deux sans robots.txt qui l'interdise (lu le 22/11/2026) :
 *   - `tabular` : API tabulaire de data.gouv.fr (`tabular-api.data.gouv.fr/api/resources/<id>/data/`), qui sert
 *     les copies analysées des CSV publiés par les portails Opendatasoft dont le robots.txt interdit `/api/` ;
 *   - `wfs` : GeoJSON du WFS de data.grandlyon.com (jeu du musée Gadagne) ;
 *   - `getty` : SPARQL puis une notice Linked Art par objet de data.getty.edu (GettyReader) ;
 *   - `iiif` : plan du site puis manifestes IIIF d'une bibliothèque numérique de marque blanche Gallica (IiifManifestReader).
 * Politesse : une requête toutes les 2 secondes au plus (attente via Sleep, simulable), User-Agent générique
 * de VFF, lecture séquentielle, une reprise unique sur 429 ou 5xx. Le cache (`storage/app/sources/open-data-media`,
 * avec `.nobackup`) garde une ligne JSON par ligne du jeu, au nom de colonnes du jeu, et un petit fichier de
 * mesure (`<jeu>.meta.json`) : date de lecture, nombre de requêtes, total annoncé.
 */
class OpenDataFetcher
{
    public const TABULAR = 'https://tabular-api.data.gouv.fr/api/resources/';

    public const PAGE_SIZE = 50;

    /** Intervalle minimal entre deux requêtes (ms). */
    public const MIN_INTERVAL_MS = 2000;

    private float $lastRequestAt = 0.0;

    public int $requestCount = 0;

    /** Jeux IIIF : redemande les manifestes dont la lecture a échoué (500, 503, délai dépassé). */
    public bool $retryErrors = false;

    public static function cacheDir(): string
    {
        return storage_path('app/sources/open-data-media');
    }

    public static function cachePath(string $key): string
    {
        return self::cacheDir().'/'.$key.'.jsonl';
    }

    public static function hasCache(string $key): bool
    {
        return is_file(self::cachePath($key));
    }

    /**
     * Lignes du cache, dans l'ordre d'écriture.
     *
     * @return iterable<array<string, mixed>>
     */
    public static function cached(string $key): iterable
    {
        $path = self::cachePath($key);
        if (! is_file($path)) {
            return;
        }
        $handle = fopen($path, 'rb');
        try {
            while (($line = fgets($handle)) !== false) {
                $row = json_decode($line, true);
                if (is_array($row)) {
                    yield $row;
                }
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Lit le jeu sur le réseau et réécrit le cache. Les lignes de plusieurs pré-sélections sont réunies sans
     * doublon (clé : colonne `id` du jeu, sinon ligne entière).
     *
     * @param  array<string, mixed>  $dataset  bloc de configuration du jeu
     * @return int nombre de lignes écrites
     */
    public function refresh(string $key, array $dataset, ?int $limit = null): int
    {
        $rows = match ($dataset['driver'] ?? null) {
            'tabular' => $this->readTabular($dataset, $limit),
            'wfs' => $this->readWfs($dataset, $limit),
            'getty' => (new GettyReader($this))->read($dataset, $limit),
            'iiif' => (new IiifManifestReader($this, $key))->read($dataset, $limit),
            default => throw new RuntimeException("Jeu {$key} : accès inconnu."),
        };

        return $this->store($key, $dataset, $rows);
    }

    /**
     * Réécrit le cache des lignes d'un jeu `iiif` depuis ses manifestes déjà lus, sans aucune requête (lecture interrompue).
     *
     * @param  array<string, mixed>  $dataset
     */
    public function rebuild(string $key, array $dataset): int
    {
        if (($dataset['driver'] ?? null) !== 'iiif') {
            throw new RuntimeException("Jeu {$key} : seule la lecture IIIF se reconstruit depuis ses manifestes.");
        }

        return $this->store($key, $dataset, IiifManifestReader::rowsFromCache($key));
    }

    /**
     * @param  array<string, mixed>  $dataset
     * @param  list<array<string, mixed>>  $rows
     */
    private function store(string $key, array $dataset, array $rows): int
    {
        File::ensureDirectoryExists(self::cacheDir());
        if (! is_file(self::cacheDir().'/.nobackup')) {
            file_put_contents(self::cacheDir().'/.nobackup', '');
        }
        $handle = fopen(self::cachePath($key), 'wb');
        foreach ($rows as $row) {
            fwrite($handle, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
        }
        fclose($handle);
        file_put_contents(self::cacheDir().'/'.$key.'.meta.json', json_encode([
            'fetched_at' => now()->toIso8601String(),
            'driver' => $dataset['driver'],
            'rows' => count($rows),
            'requests' => $this->requestCount,
            'resource' => $dataset['resource'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return count($rows);
    }

    /**
     * @param  array<string, mixed>  $dataset
     * @return list<array<string, mixed>>
     */
    private function readTabular(array $dataset, ?int $limit): array
    {
        $idColumn = $dataset['fields']['id'] ?? null;
        $queries = ($dataset['prefilter'] ?? []) === [] ? [[]] : $dataset['prefilter'];
        $out = [];
        foreach ($queries as $query) {
            $url = self::TABULAR.$dataset['resource'].'/data/?'.http_build_query($query + ['page_size' => self::PAGE_SIZE], '', '&', PHP_QUERY_RFC3986);
            while ($url !== null) {
                $json = $this->get($url);
                foreach ((array) ($json['data'] ?? []) as $row) {
                    unset($row['__id']);
                    $id = $idColumn !== null ? ($row[$idColumn] ?? null) : null;
                    $out[$id !== null ? (string) $id : md5(json_encode($row))] ??= $row;
                }
                $url = $json['links']['next'] ?? null;
                if ($limit !== null && count($out) >= $limit) {
                    break 2;
                }
            }
        }

        return array_values($out);
    }

    /**
     * @param  array<string, mixed>  $dataset
     * @return list<array<string, mixed>>
     */
    private function readWfs(array $dataset, ?int $limit): array
    {
        // Le WFS renvoie tout le jeu en une réponse (quelques centaines de lignes) ; `count` borne un essai.
        $url = $dataset['resource'].($limit !== null ? '&count='.$limit : '');
        $out = [];
        foreach ((array) ($this->get($url)['features'] ?? []) as $feature) {
            $row = (array) ($feature['properties'] ?? []);
            $coords = $feature['geometry']['coordinates'] ?? null;
            if (is_array($coords) && count($coords) >= 2) {
                $row['__lng'] = $coords[0];
                $row['__lat'] = $coords[1];
            }
            $out[] = $row;
        }

        return $out;
    }

    /**
     * Lecture JSON polie (une requête toutes les 2 s, une reprise sur 429 ou 5xx). Options : `accept` (en-tête Accept),
     * `verify` (fichier d'autorités de certification, École des ponts), `query` (paramètres ajoutés à l'adresse), `timeout` (secondes), `retry` (faux : pas de reprise sur 5xx).
     *
     * @param  array{accept?: string, verify?: string, query?: array<string, string>, timeout?: int, retry?: bool}  $options
     * @return array<string, mixed>
     */
    public function getJson(string $url, array $options = []): array
    {
        return (array) $this->request($url, $options)->json();
    }

    /**
     * Lecture de texte brut (plan du site), mêmes politesse et options que getJson.
     *
     * @param  array{accept?: string, verify?: string, query?: array<string, string>, timeout?: int, retry?: bool}  $options
     */
    public function getText(string $url, array $options = []): string
    {
        return $this->request($url, $options)->body();
    }

    /** @return array<string, mixed> */
    private function get(string $url): array
    {
        return $this->getJson($url);
    }

    /** @param  array{accept?: string, verify?: string, query?: array<string, string>, timeout?: int, retry?: bool}  $options */
    private function request(string $url, array $options): Response
    {
        $response = $this->send($url, $options);
        // Une reprise unique, après la pause demandée, sur 429 ou 5xx (sauf `retry` faux : manifestes lourds qui n'aboutissent pas).
        if (($options['retry'] ?? true) && ($response->status() === 429 || $response->serverError())) {
            Sleep::for(min(60, max(5, (int) $response->header('Retry-After'))) * 1000)->milliseconds();
            $response = $this->send($url, $options);
        }
        if (! $response->successful()) {
            throw new RuntimeException("Lecture refusée ({$response->status()}) : {$url}");
        }

        return $response;
    }

    /** @param  array{accept?: string, verify?: string, query?: array<string, string>, timeout?: int, retry?: bool}  $options */
    private function send(string $url, array $options = []): Response
    {
        $wait = $this->lastRequestAt + self::MIN_INTERVAL_MS / 1000 - microtime(true);
        if ($wait > 0) {
            Sleep::for((int) ceil($wait * 1000))->milliseconds();
        }
        $client = Http::withHeaders(['User-Agent' => CommonsClient::USER_AGENT, 'Accept' => $options['accept'] ?? 'application/json'])->timeout($options['timeout'] ?? 90);
        if (isset($options['verify'])) {
            $client = $client->withOptions(['verify' => $options['verify']]);
        }
        // Deux requêtes partent à 2 s au moins d'écart (départ à départ), jamais en parallèle.
        $this->lastRequestAt = microtime(true);
        // Sans paramètres à ajouter, l'adresse part telle quelle : une option `query` vide effacerait le filtre déjà dans l'adresse.
        $response = isset($options['query']) ? $client->get($url, $options['query']) : $client->get($url);
        $this->requestCount++;

        return $response;
    }
}

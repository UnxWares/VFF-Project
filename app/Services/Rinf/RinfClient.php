<?php

namespace App\Services\Rinf;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Client du point SPARQL public du graphe de connaissances de l'ERA (RinfDataset::ENDPOINT), avec cache disque.
 *
 * Règles de lecture (consignes des agents, registre des sources) : User-Agent du projet, une requête à la fois,
 * PAUSE_S secondes entre deux requêtes, délai de TIMEOUT_S secondes, BUDGET requêtes au plus par passage (une
 * lecture de la France entière en prend une trentaine), aucune clé ni compte. Chaque réponse est gardée telle
 * quelle dans le dossier du cache (storage/app/data/rinf/<portée>/<nom>.json, dossier `.nobackup`), avec la date
 * de lecture dans manifest.json ; en mode hors ligne, une réponse absente du cache est une erreur.
 */
final class RinfClient
{
    public const USER_AGENT = 'VFF-Bot/1.0 (+https://vff-project.org)';

    public const PAUSE_S = 5;

    public const TIMEOUT_S = 180;

    public const BUDGET = 80;

    private int $requests = 0;

    private ?float $lastAt = null;

    /** @var array<string, string> nom de réponse => date de lecture (ISO 8601) */
    private array $manifest = [];

    public function __construct(
        private readonly string $cacheDir,
        private readonly bool $offline = false,
        private readonly bool $refresh = false,
        private readonly int $budget = self::BUDGET,
        private readonly int $pauseS = self::PAUSE_S,
    ) {
        $path = $this->cacheDir.'/manifest.json';
        if (is_file($path)) {
            $this->manifest = (array) json_decode((string) file_get_contents($path), true);
        }
    }

    /** Requêtes envoyées sur le réseau pendant ce passage. */
    public function requests(): int
    {
        return $this->requests;
    }

    /** Date de lecture la plus ancienne des réponses utilisées (ISO 8601), null sans réponse. */
    public function fetchedAt(): ?string
    {
        return $this->manifest === [] ? null : min($this->manifest);
    }

    /**
     * Lignes de résultat (`results.bindings`) d'une requête SELECT, depuis le cache ou le réseau.
     *
     * @return list<array<string, array{type: string, value: string}>>
     */
    public function select(string $name, string $query): array
    {
        $path = $this->cacheDir.'/'.$name.'.json';
        if (! $this->refresh && is_file($path)) {
            return self::bindings((string) file_get_contents($path), $name);
        }
        if ($this->offline) {
            throw new RuntimeException("Réponse {$name} absente du cache ({$path}) : relancez sans --offline pour la lire.");
        }
        if ($this->requests >= $this->budget) {
            throw new RuntimeException("Budget de {$this->budget} requêtes atteint avant {$name} : rien de plus n'est demandé à l'ERA.");
        }
        if ($this->lastAt !== null) {
            $wait = $this->pauseS - (microtime(true) - $this->lastAt);
            if ($wait > 0) {
                usleep((int) ($wait * 1_000_000));
            }
        }
        $this->requests++;
        $response = Http::withHeaders(['User-Agent' => self::USER_AGENT, 'Accept' => 'application/sparql-results+json'])
            ->timeout(self::TIMEOUT_S)->asForm()->post(RinfDataset::ENDPOINT, ['query' => $query]);
        $this->lastAt = microtime(true);
        if (! $response->successful()) {
            throw new RuntimeException("Point SPARQL de l'ERA : HTTP {$response->status()} pour {$name}.");
        }
        $body = $response->body();
        $rows = self::bindings($body, $name);
        File::ensureDirectoryExists($this->cacheDir);
        if (! is_file(dirname($this->cacheDir).'/.nobackup')) {
            @touch(dirname($this->cacheDir).'/.nobackup');
        }
        file_put_contents($path, $body);
        $this->manifest[$name] = now()->toIso8601String();
        file_put_contents($this->cacheDir.'/manifest.json', json_encode($this->manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

        return $rows;
    }

    /**
     * Toutes les pages d'une requête (LIMIT/OFFSET ajoutés), jusqu'à une page incomplète.
     *
     * @return list<array<string, array{type: string, value: string}>>
     */
    public function selectAll(string $name, string $query, int $pageSize): array
    {
        $rows = [];
        for ($page = 0; ; $page++) {
            $chunk = $this->select(sprintf('%s-%03d', $name, $page), $query.sprintf("\nLIMIT %d OFFSET %d", $pageSize, $page * $pageSize));
            $rows = [...$rows, ...$chunk];
            if (count($chunk) < $pageSize) {
                return $rows;
            }
        }
    }

    /** @return list<array<string, array{type: string, value: string}>> */
    private static function bindings(string $body, string $name): array
    {
        $json = json_decode($body, true);
        if (! is_array($json) || ! isset($json['results']['bindings']) || ! is_array($json['results']['bindings'])) {
            throw new RuntimeException("Réponse {$name} illisible (JSON SPARQL attendu).");
        }

        return array_values($json['results']['bindings']);
    }
}

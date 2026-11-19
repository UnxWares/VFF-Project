<?php

namespace App\Services\StationService;

use Illuminate\Http\Client\Factory as HttpFactory;
use RuntimeException;

/**
 * Cache local des flux (storage/app/data/gtfs/<flux>/gtfs.zip, dossier marqué
 * .nobackup : données régénérables). Un flux archivé (FeedDefinition::$history) est
 * choisi dans l'historique des ressources de transport.data.gouv.fr : la version
 * enregistrée le plus près du jour « MM-JJ » passé le plus récent.
 */
final readonly class GtfsFeedStore
{
    public const HISTORY_URL = 'https://transport.data.gouv.fr/datasets/%d/resources_history_csv';

    public function __construct(
        private HttpFactory $http,
        private string $root,
    ) {}

    public function path(FeedDefinition $feed): string
    {
        return "{$this->root}/{$feed->key}/gtfs.zip";
    }

    /** Chemin du flux, téléchargé sauf s'il est en cache et que $fromCache est demandé. */
    public function fetch(FeedDefinition $feed, bool $fromCache, int $today): string
    {
        $path = $this->path($feed);
        if ($fromCache && is_file($path)) {
            return $path;
        }
        $url = $feed->url ?? $this->archivedUrl($feed, $fromCache, $today);
        $this->download($url, $path);
        if (! str_starts_with((string) file_get_contents($path, false, null, 0, 2), 'PK')) {
            throw new RuntimeException("Flux {$feed->key} : {$url} n'est pas une archive zip");
        }
        file_put_contents(dirname($path).'/source.json', json_encode(['url' => $url, 'fetched_at' => date(DATE_ATOM)], JSON_UNESCAPED_SLASHES));

        return $path;
    }

    /** Fichier quelconque (ex. fréquentation des gares), même règle de cache. */
    public function fetchFile(string $url, string $name, bool $fromCache): string
    {
        $path = "{$this->root}/{$name}";
        if (! $fromCache || ! is_file($path)) {
            $this->download($url, $path);
        }

        return $path;
    }

    private function archivedUrl(FeedDefinition $feed, bool $fromCache, int $today): string
    {
        ['dataset' => $dataset, 'resource' => $resource] = $feed->history
            ?? throw new RuntimeException("Flux {$feed->key} : ni url ni history");
        $csv = $this->fetchFile(sprintf(self::HISTORY_URL, $dataset), "history-{$dataset}.csv", $fromCache);

        return self::closestVersion($csv, $resource, (int) $feed->targetDay($today))
            ?? throw new RuntimeException("Flux {$feed->key} : aucune version archivée de la ressource {$resource}");
    }

    /**
     * Version archivée d'une ressource enregistrée le plus près d'un jour, dans l'historique
     * CSV de transport.data.gouv.fr (colonnes resource_id, permanent_url, inserted_at).
     */
    public static function closestVersion(string $csv, int $resource, int $day): ?string
    {
        $target = Ymd::toDate($day)->getTimestamp();
        $best = null;
        // Vérifié avant fopen : Laravel convertit l'avertissement d'un fopen raté en ErrorException.
        $handle = is_file($csv) && is_readable($csv) ? fopen($csv, 'rb') : false;
        if ($handle === false) {
            throw new RuntimeException("Historique illisible : {$csv}");
        }
        $header = fgetcsv($handle, null, ',', '"', '') ?: [];
        while (($row = fgetcsv($handle, null, ',', '"', '')) !== false) {
            $r = array_combine($header, array_pad(array_slice($row, 0, count($header)), count($header), ''));
            if ((int) $r['resource_id'] !== $resource || $r['permanent_url'] === '') {
                continue;
            }
            $gap = abs($target - (int) strtotime((string) $r['inserted_at']));
            if ($best === null || $gap < $best[0]) {
                $best = [$gap, (string) $r['permanent_url']];
            }
        }
        fclose($handle);

        return $best[1] ?? null;
    }

    private function download(string $url, string $path): void
    {
        if (! is_dir(dirname($path)) && ! mkdir(dirname($path), 0775, true) && ! is_dir(dirname($path))) {
            throw new RuntimeException('Dossier impossible à créer : '.dirname($path));
        }
        if (! is_file("{$this->root}/.nobackup")) {
            touch("{$this->root}/.nobackup");
        }
        $partial = $path.'.part';
        $response = $this->http->timeout(900)->sink($partial)->get($url);
        if (! $response->successful()) {
            throw new RuntimeException("Téléchargement refusé ({$response->status()}) : {$url}");
        }
        rename($partial, $path);
    }
}

<?php

namespace App\Services;

use Illuminate\Support\Sleep;

/**
 * Wikitexte des articles Wikipédia des gares et des lignes, en cache disque
 * (storage/app/sources/stations/wikipedia, un fichier JSON par titre demandé,
 * nommé sha1(titre)). Partagé par vff:import-station-closures et
 * vff:import-station-passenger-ends : un article lu par l'une sert à l'autre.
 *
 * Téléchargement par WikimediaClient (User-Agent du robot VFF, maxlag, 50
 * titres par requête), avec une pause d'une demi-seconde entre deux requêtes.
 * Un titre introuvable est mis en cache comme tel (« missing ») pour ne pas
 * être redemandé.
 */
final class StationArticleCache
{
    /** @param  int  $pauseMicroseconds  pause entre deux requêtes (une demi-seconde par défaut) */
    public function __construct(private WikimediaClient $wiki, private int $pauseMicroseconds = 500_000) {}

    public static function directory(): string
    {
        return storage_path('app/sources/stations/wikipedia');
    }

    /**
     * Pages indexées par titre demandé ; `fetched` = titres téléchargés ;
     * `uncached` = titres absents du cache et non téléchargés ($fromCache).
     *
     * @param  list<string>  $titles
     * @param  bool  $fromCache  aucune requête : les titres absents du cache sont ignorés
     * @param  bool  $refresh  re-télécharge les titres déjà en cache
     * @return array{pages: array<string, array{title: string, revid: int, content: string}>, fetched: int, uncached: int}
     */
    public function pages(array $titles, bool $fromCache = false, bool $refresh = false): array
    {
        $dir = self::directory();
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        // Cache régénérable : hors sauvegarde (convention .nobackup du poste de développement).
        if (! is_file($dir.'/.nobackup')) {
            touch($dir.'/.nobackup');
        }
        $path = fn (string $t) => $dir.'/'.sha1($t).'.json';
        $out = [];
        $missing = [];
        foreach (array_values(array_unique($titles)) as $t) {
            if (! $refresh && is_file($path($t))) {
                $data = json_decode((string) file_get_contents($path($t)), true);
                if (is_array($data) && ! empty($data['content'])) {
                    $out[$t] = ['title' => (string) $data['title'], 'revid' => (int) $data['revid'], 'content' => (string) $data['content']];
                }

                continue;
            }
            $missing[] = $t;
        }
        if ($fromCache) {
            return ['pages' => $out, 'fetched' => 0, 'uncached' => count($missing)];
        }
        foreach (array_chunk($missing, 50) as $i => $chunk) {
            if ($i > 0) {
                Sleep::usleep($this->pauseMicroseconds);
            }
            $fetched = $this->wiki->fetchPages($chunk);
            foreach ($chunk as $t) {
                $page = $fetched[$t] ?? ['title' => $t, 'missing' => true];
                file_put_contents($path($t), json_encode($page + ['requested' => $t], JSON_UNESCAPED_UNICODE));
                if (! empty($page['content'])) {
                    $out[$t] = $page;
                }
            }
        }

        return ['pages' => $out, 'fetched' => count($missing), 'uncached' => 0];
    }
}

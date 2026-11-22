<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Support\Media\CommonsImagePath;
use Illuminate\Cache\RedisStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Liste des fichiers de Wikimedia Commons que VFF cite (images des fiches de ligne et de gare, des accidents,
 * du matériel, du journal, des propositions en attente) : le relais des vignettes
 * (CommonsImageController) ne sert que ces fichiers. Un tiers ne peut donc pas s'en servir de relais vers
 * n'importe quel fichier de Commons, même à la bonne forme.
 *
 * La clé est le nom du fichier (CommonsImagePath::$file : décodé, espaces soulignés), quelle que soit la
 * largeur de la vignette. La liste est un ensemble précalculé que reconstruit `vff:commons-allowlist --refresh`
 * (tâche planifiée en production) : le contrôleur ne fait qu'une lecture en O(1), sans requête SQL ni
 * lecture de fichier.
 *
 * Stockage : avec le cache Valkey (`redis`), un ensemble (SISMEMBER), construit sous un nom temporaire puis
 * renommé d'un coup, de sorte que le relais ne voit jamais une liste à moitié écrite. Avec un autre cache
 * (tests : `array`), une table de hachage en un seul bloc. Une liste jamais construite est distinguée d'une
 * liste qui ne contient pas le fichier (isBuilt) : le relais répond alors 503 et non 404.
 *
 * Sources : colonnes `media` (thumb_url, display_url), `accident_images` (image_url, thumb_url),
 * `materials.image` (thumb_url, display_url), articles du journal (toute adresse de Commons des fichiers
 * Markdown), propositions de contribution en attente. Un fichier que la lecture d'une proposition
 * (CommonsFileResolver::lookup) vient d'aider à retrouver est ajouté aussitôt (add), pour l'aperçu.
 */
final class CommonsAllowlist
{
    public const SET_KEY = 'commons-allowlist:files';

    public const META_KEY = 'commons-allowlist:meta';

    /** Adresse de Commons dans un texte (JSON de contribution, Markdown du journal). */
    private const URL_PATTERN = '#https://(?:upload|thumb)\.wikimedia\.org/wikipedia/commons/[^\s"\'<>)\\\\]+#';

    private const CHUNK = 2000;

    public function contains(string $file): bool
    {
        if ($file === '') {
            return false;
        }
        $store = Cache::getStore();
        if ($store instanceof RedisStore) {
            return (bool) $store->connection()->command('sismember', [$store->getPrefix().self::SET_KEY, $file]);
        }
        $map = Cache::get(self::SET_KEY);

        return is_array($map) && isset($map[$file]);
    }

    /** La liste a été construite au moins une fois (et n'a pas été vidée du cache). */
    public function isBuilt(): bool
    {
        return Cache::get(self::META_KEY) !== null;
    }

    /** @return array{built_at: string, count: int, sources: array<string, int>, seconds: float}|null */
    public function meta(): ?array
    {
        $meta = Cache::get(self::META_KEY);

        return is_array($meta) ? $meta : null;
    }

    /** Ajoute un fichier à la liste sans la reconstruire (aperçu d'une proposition de contribution). */
    public function add(string $url): void
    {
        $image = CommonsImagePath::fromUrl($url);
        if ($image === null || ! $this->isBuilt()) {
            return;
        }
        $store = Cache::getStore();
        if ($store instanceof RedisStore) {
            $store->connection()->command('sadd', [$store->getPrefix().self::SET_KEY, $image->file]);

            return;
        }
        $map = Cache::get(self::SET_KEY);
        $map = is_array($map) ? $map : [];
        $map[$image->file] = true;
        Cache::forever(self::SET_KEY, $map);
    }

    /**
     * Relit les sources et remplace la liste (sauf `$write` faux : compte seulement). Une liste vide ne
     * remplace jamais une liste existante (base illisible, mauvaise connexion) : exception.
     *
     * @return array{built_at: string, count: int, sources: array<string, int>, seconds: float}
     */
    public function rebuild(bool $write = true): array
    {
        $start = microtime(true);
        $sources = [];
        $names = [];
        foreach ($this->collect() as $source => $urls) {
            $sources[$source] = 0;
            foreach ($urls as $url) {
                $image = CommonsImagePath::fromUrl($url);
                if ($image !== null) {
                    $sources[$source]++;
                    $names[$image->file] = true;
                }
            }
        }
        $meta = [
            'built_at' => now()->toIso8601String(),
            'count' => count($names),
            'sources' => $sources,
            'seconds' => round(microtime(true) - $start, 3),
        ];
        if (! $write) {
            return $meta;
        }
        if ($names === []) {
            throw new \RuntimeException('Aucun fichier de Commons trouvé dans les sources : la liste existante est gardée.');
        }
        $this->store(array_keys($names));
        Cache::forever(self::META_KEY, $meta);

        return $meta;
    }

    /** Taille de l'ensemble dans Valkey (octets, MEMORY USAGE), null hors Valkey ou si la liste est absente. */
    public function memoryBytes(): ?int
    {
        $store = Cache::getStore();
        if (! $store instanceof RedisStore) {
            return null;
        }
        // Commande brute : le préfixe des clés du client (database.redis.options.prefix) n'y est pas appliqué.
        $key = (string) config('database.redis.options.prefix').$store->getPrefix().self::SET_KEY;
        $bytes = $store->connection()->client()->executeRaw(['MEMORY', 'USAGE', $key]);

        return is_numeric($bytes) ? (int) $bytes : null;
    }

    /** @param  list<string>  $names */
    private function store(array $names): void
    {
        $store = Cache::getStore();
        if (! $store instanceof RedisStore) {
            Cache::forever(self::SET_KEY, array_fill_keys($names, true));

            return;
        }
        $connection = $store->connection();
        $final = $store->getPrefix().self::SET_KEY;
        $temporary = $final.':building:'.Str::random(12);
        foreach (array_chunk($names, self::CHUNK) as $chunk) {
            $connection->command('sadd', [$temporary, ...$chunk]);
        }
        $connection->command('rename', [$temporary, $final]);
    }

    /** @return array<string, iterable<string>> adresses de Commons, par source */
    private function collect(): array
    {
        $like = 'https://%.wikimedia.org/wikipedia/commons/%';
        $columns = fn (string $table, string ...$cols): array => array_merge(...array_map(
            fn (string $c): array => DB::table($table)->where($c, 'like', $like)->distinct()->pluck($c)->all(),
            $cols,
        ));
        $materials = [];
        foreach (['thumb_url', 'display_url'] as $key) {
            $materials[] = DB::table('materials')
                ->whereRaw("image->>'{$key}' like ?", [$like])
                ->selectRaw("distinct image->>'{$key}' as url")
                ->pluck('url')
                ->all();
        }

        return [
            'media' => $columns('media', 'thumb_url', 'display_url'),
            'accident_images' => $columns('accident_images', 'image_url', 'thumb_url'),
            'materials' => array_merge(...$materials),
            'journal' => $this->urlsInFiles(glob(resource_path('content/journal/*.md')) ?: []),
            'contributions' => $this->contributionUrls(),
        ];
    }

    /**
     * @param  list<string>  $paths
     * @return list<string>
     */
    private function urlsInFiles(array $paths): array
    {
        $urls = [];
        foreach ($paths as $path) {
            preg_match_all(self::URL_PATTERN, (string) file_get_contents($path), $m);
            array_push($urls, ...$m[0]);
        }

        return $urls;
    }

    /** @return list<string> */
    private function contributionUrls(): array
    {
        $urls = [];
        $rows = DB::table('contributions')->where('status', 'pending')
            ->whereRaw('payload::text like ?', ['%wikimedia.org/wikipedia/commons/%'])
            ->select('payload')->cursor();
        foreach ($rows as $row) {
            preg_match_all(self::URL_PATTERN, (string) $row->payload, $m);
            array_push($urls, ...$m[0]);
        }

        return $urls;
    }
}

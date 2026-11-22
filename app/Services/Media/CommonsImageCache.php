<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Support\Media\CommonsImagePath;
use FilesystemIterator;
use RuntimeException;

/**
 * Cache disque des vignettes de Commons relayées par VFF (config commons_proxy) : un fichier par image,
 * `{dossier}/{ab}/{empreinte SHA-256 du chemin}.{extension}`, daté du moment où il a été lu sur Commons.
 *
 * Le dossier porte deux marqueurs : `.nobackup` (rien à sauvegarder, tout se relit sur Commons) et
 * `.vff-commons-cache`. Le nettoyage (prune) n'agit que dans un dossier qui porte ce second marqueur, et
 * seulement sur les fichiers au nom du cache : une erreur de configuration (`cache_path` vers un autre
 * dossier) ne peut rien effacer d'autre.
 */
final readonly class CommonsImageCache
{
    public const MARKER = '.vff-commons-cache';

    private const SHARD = '/^[0-9a-f]{2}$/';

    private const ENTRY = '/^[0-9a-f]{64}\.(?:jpe?g|png|gif|webp)$/';

    private const TEMPORARY = '/^\.tmp-[0-9a-f]{32}$/';

    public function __construct(
        private string $directory,
        private int $maxBytes,
        private int $ttlSeconds,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            rtrim((string) config('commons_proxy.cache_path'), '/'),
            max(1, (int) config('commons_proxy.cache_max_mb')) * 1_048_576,
            max(1, (int) config('commons_proxy.ttl_days')) * 86_400,
        );
    }

    public function ttlSeconds(): int
    {
        return $this->ttlSeconds;
    }

    /**
     * Fichier du cache d'une image (qu'il existe ou non), relatif au dossier du cache. L'extension suit le type
     * réellement reçu : Commons sert parfois une vignette JPEG ou PNG en WebP.
     */
    public function relativePath(CommonsImagePath $image, string $mimeType): string
    {
        $key = $image->cacheKey();
        $extension = array_search($mimeType, CommonsImagePath::MIME_TYPES, true);

        return substr($key, 0, 2).'/'.$key.'.'.($extension === false || $extension === 'jpeg' ? $image->extension : $extension);
    }

    /** Fichier gardé pour cette image (le plus récent, même périmé), ou null. */
    public function find(CommonsImagePath $image): ?string
    {
        $found = null;
        foreach (array_unique([$image->mimeType(), 'image/webp']) as $mimeType) {
            $file = $this->directory.'/'.$this->relativePath($image, $mimeType);
            if (is_file($file) && ($found === null || filemtime($file) > filemtime($found))) {
                $found = $file;
            }
        }

        return $found;
    }

    /** Chemin d'un fichier du cache relatif à son dossier (X-Accel-Redirect). */
    public function relativeOf(string $file): string
    {
        return substr($file, strlen($this->directory) + 1);
    }

    /** Type d'un fichier du cache, d'après son extension. */
    public function mimeTypeOf(string $file): string
    {
        return CommonsImagePath::MIME_TYPES[strtolower(pathinfo($file, PATHINFO_EXTENSION))] ?? 'application/octet-stream';
    }

    public function isFresh(string $file): bool
    {
        $mtime = @filemtime($file);

        return $mtime !== false && $mtime + $this->ttlSeconds > time();
    }

    /** Garde l'image (écriture dans un fichier temporaire puis renommage, jamais de fichier à moitié écrit). */
    public function put(CommonsImagePath $image, string $bytes, string $mimeType): string
    {
        $this->prepare();
        $relative = $this->relativePath($image, $mimeType);
        $shard = $this->directory.'/'.dirname($relative);
        if (! is_dir($shard) && ! @mkdir($shard, 0o775) && ! is_dir($shard)) {
            throw new RuntimeException("Dossier du cache des vignettes impossible à créer : {$shard}");
        }
        $temporary = $shard.'/.tmp-'.bin2hex(random_bytes(16));
        if (file_put_contents($temporary, $bytes) !== strlen($bytes) || ! rename($temporary, $this->directory.'/'.$relative)) {
            @unlink($temporary);
            throw new RuntimeException('Vignette impossible à garder dans le cache.');
        }

        return $this->directory.'/'.$relative;
    }

    /**
     * Retire les vignettes périmées, puis les plus anciennes jusqu'à revenir à 90 % de la taille maximale, et
     * les fichiers temporaires de plus d'une heure (écriture interrompue).
     *
     * @return array{removed: int, freed_bytes: int, kept: int, kept_bytes: int}
     */
    public function prune(): array
    {
        $result = ['removed' => 0, 'freed_bytes' => 0, 'kept' => 0, 'kept_bytes' => 0];
        if (! is_file($this->directory.'/'.self::MARKER)) {
            return $result;
        }
        $now = time();
        $entries = [];
        foreach (new FilesystemIterator($this->directory, FilesystemIterator::SKIP_DOTS) as $shard) {
            if (! $shard->isDir() || $shard->isLink() || preg_match(self::SHARD, $shard->getFilename()) !== 1) {
                continue;
            }
            foreach (new FilesystemIterator($shard->getPathname(), FilesystemIterator::SKIP_DOTS) as $file) {
                if (! $file->isFile() || $file->isLink()) {
                    continue;
                }
                $name = $file->getFilename();
                $mtime = (int) $file->getMTime();
                if (preg_match(self::TEMPORARY, $name) === 1) {
                    if ($mtime < $now - 3600) {
                        $this->remove($file->getPathname(), (int) $file->getSize(), $result);
                    }

                    continue;
                }
                if (preg_match(self::ENTRY, $name) !== 1) {
                    continue;
                }
                if ($mtime + $this->ttlSeconds <= $now) {
                    $this->remove($file->getPathname(), (int) $file->getSize(), $result);

                    continue;
                }
                $entries[] = [$mtime, (int) $file->getSize(), $file->getPathname()];
            }
        }

        $total = array_sum(array_column($entries, 1));
        if ($total > $this->maxBytes) {
            usort($entries, fn (array $a, array $b): int => $a[0] <=> $b[0]);
            $target = intdiv($this->maxBytes * 9, 10);
            foreach ($entries as $i => [, $size, $path]) {
                if ($total <= $target) {
                    break;
                }
                $this->remove($path, $size, $result);
                $total -= $size;
                unset($entries[$i]);
            }
        }
        $result['kept'] = count($entries);
        $result['kept_bytes'] = max(0, $total);

        return $result;
    }

    /** Crée le dossier du cache et ses deux marqueurs. */
    private function prepare(): void
    {
        if (! is_dir($this->directory) && ! @mkdir($this->directory, 0o775, true) && ! is_dir($this->directory)) {
            throw new RuntimeException("Dossier du cache des vignettes impossible à créer : {$this->directory}");
        }
        foreach (['.nobackup', self::MARKER] as $marker) {
            if (! is_file($this->directory.'/'.$marker)) {
                file_put_contents($this->directory.'/'.$marker, '');
            }
        }
    }

    /** @param  array{removed: int, freed_bytes: int, kept: int, kept_bytes: int}  $result */
    private function remove(string $path, int $size, array &$result): void
    {
        if (@unlink($path)) {
            $result['removed']++;
            $result['freed_bytes'] += $size;
        }
    }
}

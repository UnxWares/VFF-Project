<?php

namespace App\Services\Accidents\Context;

use App\Models\Media;
use App\Services\Media\CommonsClient;
use App\Services\Media\CommonsMetadata;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Métadonnées d'un fichier de Wikimedia Commons pour une fiche d'accident : auteur, licence, date, adresses
 * de la page, de l'image et de la miniature, relus sur Commons (jamais saisis à la main) et normalisés par
 * CommonsMetadata (mêmes règles de droits que les galeries).
 *
 * Seuls les fichiers du domaine public ou sous licence libre qui permet l'usage commercial sont retenus : VFF
 * est édité par une société. Une licence non commerciale, inconnue ou une permission particulière : refusé.
 * Une numérisation de Gallica ou de la BnF (champ « Credit » de Commons) est aussi refusée, même marquée domaine
 * public : règle Gallica de VFF (métadonnées et liens seulement, aucune image ; la BnF soumet la réutilisation
 * commerciale de ses reproductions à redevance).
 *
 * Cache : storage/app/data/accidents/commons/<md5 du titre>.json (dossier parent marqué .nobackup). Hors ligne,
 * seul le cache est lu ; un fichier absent du cache est signalé, jamais inventé.
 */
final class AccidentImageResolver
{
    public int $requests = 0;

    public function __construct(
        private readonly string $cacheDir,
        private readonly bool $offline = false,
        private readonly ?CommonsClient $client = null,
    ) {}

    public static function defaultCacheDir(): string
    {
        return storage_path('app/data/accidents/commons');
    }

    /**
     * @return array{ok: true, image: array{file: string, page_url: string, image_url: string, thumb_url: ?string, width: ?int, height: ?int, author: string, licence: string, licence_url: ?string, date: ?string}}|array{ok: false, reason: string}
     */
    public function resolve(string $file): array
    {
        $page = $this->page($file);
        if ($page === null) {
            return ['ok' => false, 'reason' => $this->offline ? 'absent du cache (hors ligne)' : 'fichier introuvable sur Commons'];
        }
        $meta = CommonsMetadata::normalize($page);
        $free = $meta['rights_status'] === Media::RIGHTS_PUBLIC_DOMAIN
            || ($meta['rights_status'] === Media::RIGHTS_OPEN_LICENSE && $meta['commercial_ok'] === true);
        if (! $free) {
            return ['ok' => false, 'reason' => 'licence non libre ou inconnue : '.($meta['license'] ?? 'aucune')];
        }
        if (self::fromGallica($page)) {
            return ['ok' => false, 'reason' => 'numérisation de Gallica ou de la BnF (règle Gallica : aucune image)'];
        }
        $image = $meta['display_url'] ?? $meta['thumb_url'];
        if (! is_string($image) || $image === '') {
            return ['ok' => false, 'reason' => 'aucune image affichable'];
        }

        return ['ok' => true, 'image' => [
            'file' => (string) $meta['file_title'],
            'page_url' => (string) $meta['source_url'],
            'image_url' => $image,
            'thumb_url' => $meta['thumb_url'],
            'width' => $meta['width'],
            'height' => $meta['height'],
            // Auteur inconnu : dit tel quel, jamais deviné.
            'author' => mb_substr($meta['author'] ?? 'Auteur inconnu', 0, 300),
            'licence' => mb_substr($meta['license'] ?? 'Public domain', 0, 80),
            'licence_url' => $meta['license_url'],
            'date' => $meta['date_raw'] !== null ? mb_substr((string) $meta['date_raw'], 0, 60) : null,
        ]];
    }

    /** @param  array{title: string, info: array<string, mixed>}  $page */
    public static function fromGallica(array $page): bool
    {
        $ext = $page['info']['extmetadata'] ?? [];
        $haystack = implode(' ', array_map(fn ($k) => is_array($ext[$k] ?? null) ? (string) ($ext[$k]['value'] ?? '') : '', ['Credit', 'ImageDescription', 'Attribution']));

        return preg_match('/gallica|biblioth[eè]que nationale de france|\bBnF\b/iu', $haystack) === 1;
    }

    /** @return array{title: string, info: array<string, mixed>}|null */
    private function page(string $file): ?array
    {
        $path = $this->cacheDir.'/'.md5($file).'.json';
        if (File::exists($path)) {
            $cached = json_decode((string) File::get($path), true);

            return is_array($cached) && isset($cached['title'], $cached['info']) ? $cached : null;
        }
        if ($this->offline) {
            return null;
        }
        try {
            $this->requests++;
            $page = ($this->client ?? new CommonsClient(1000))->fileInfoByTitle($file);
        } catch (Throwable) {
            return null;
        }
        if ($page === null) {
            return null;
        }
        $keep = ['title' => $page['title'], 'info' => $page['info']];
        File::ensureDirectoryExists($this->cacheDir);
        File::put($path, (string) json_encode($keep, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $keep;
    }
}

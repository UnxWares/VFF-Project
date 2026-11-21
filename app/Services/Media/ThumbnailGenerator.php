<?php

namespace App\Services\Media;

use App\Models\Media;
use GdImage;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Miniatures des images déposées par les contributeurs (provider `upload`), créées avec GD.
 *
 * Deux largeurs bornées (WIDTHS) : 320 px pour les vignettes des galeries, de la chronologie et des
 * histoires (`thumb_url`), 1 280 px pour la visionneuse (`display_url`). Une image plus étroite garde
 * sa largeur : rien n'est agrandi. Format WebP quand GD sait l'écrire, sinon JPEG (fond blanc sous
 * la transparence). GD n'écrit aucune métadonnée : les miniatures n'en ont pas, comme l'original
 * nettoyé par ImageSanitizer. L'orientation EXIF gardée dans l'original est appliquée aux pixels.
 *
 * Les chemins sont rangés dans `provider_meta.thumbs` ({"320": "...", "1280": "..."}) ; MediaResource
 * en tire les adresses. Sans GD (ancienne image PHP), rien n'est créé et l'original reste servi.
 */
final class ThumbnailGenerator
{
    /** Largeurs produites (pixels) : vignette, visionneuse. */
    public const WIDTHS = [320, 1280];

    /** Dossier des miniatures sur le disque public. */
    public const DIR = 'media/contributions/thumbs';

    /** Au-delà de ce nombre de pixels, l'image n'est pas décodée (mémoire). 12 000 × 12 000 au plus. */
    private const MAX_PIXELS = 144_000_000;

    private const WEBP_QUALITY = 80;

    private const JPEG_QUALITY = 82;

    public function __construct(private ImageSanitizer $images) {}

    /** Vrai si GD est chargé avec la lecture de JPEG, PNG et WebP. */
    public static function available(): bool
    {
        return extension_loaded('gd')
            && (imagetypes() & (IMG_JPG | IMG_PNG | IMG_WEBP)) === (IMG_JPG | IMG_PNG | IMG_WEBP);
    }

    /** Format des miniatures : WebP si GD sait l'écrire, sinon JPEG. */
    public static function format(): string
    {
        return function_exists('imagewebp') ? 'webp' : 'jpeg';
    }

    /**
     * Crée les miniatures d'un média déposé et les enregistre dans `provider_meta.thumbs`.
     * Renvoie les chemins créés (largeur => chemin), vide si GD manque ou si l'image est illisible.
     *
     * @return array<int, string>
     */
    public function forMedia(Media $media): array
    {
        if ($media->provider !== Media::PROVIDER_UPLOAD || (string) $media->file_path === '' || ! self::available()) {
            return [];
        }
        $disk = Storage::disk('public');
        $bytes = $disk->exists($media->file_path) ? $disk->get($media->file_path) : null;
        if ($bytes === null) {
            return [];
        }
        $thumbs = $this->render($bytes);
        if ($thumbs === []) {
            return [];
        }

        $base = self::DIR.'/'.pathinfo($media->file_path, PATHINFO_FILENAME);
        $extension = self::format() === 'webp' ? 'webp' : 'jpg';
        $paths = [];
        foreach ($thumbs as $width => $data) {
            $path = "{$base}-{$width}.{$extension}";
            if (! $disk->put($path, $data)) {
                return [];
            }
            $paths[$width] = $path;
        }

        $meta = $media->provider_meta ?? [];
        $meta['thumbs'] = $paths;
        $media->forceFill(['provider_meta' => $meta])->saveQuietly();

        return $paths;
    }

    /**
     * Miniatures d'une image (largeur => octets encodés), vide si l'image ne peut pas être décodée.
     *
     * @return array<int, string>
     */
    public function render(string $bytes): array
    {
        if (! self::available()) {
            return [];
        }
        try {
            $orientation = $this->images->sanitize($bytes)->orientation;
        } catch (UnsupportedImage) {
            return [];
        }
        $size = @getimagesizefromstring($bytes);
        if ($size === false || $size[0] * $size[1] > self::MAX_PIXELS) {
            return [];
        }
        $source = @imagecreatefromstring($bytes);
        if (! $source instanceof GdImage) {
            return [];
        }

        try {
            $source = self::orient($source, $orientation);
            $out = [];
            foreach (self::WIDTHS as $width) {
                $out[$width] = self::encode(self::resize($source, $width));
            }

            return $out;
        } catch (Throwable $e) {
            report($e);

            return [];
        }
    }

    /** Applique une orientation EXIF (1 à 8) aux pixels. */
    public static function orient(GdImage $image, ?int $orientation): GdImage
    {
        if (in_array($orientation, [2, 5, 7], true)) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        }
        if ($orientation === 4) {
            imageflip($image, IMG_FLIP_VERTICAL);
        }
        // imagerotate() tourne dans le sens inverse des aiguilles d'une montre.
        $angle = match ($orientation) {
            3 => 180,
            5, 8 => 90,
            6, 7 => 270,
            default => 0,
        };
        if ($angle === 0) {
            return $image;
        }
        $rotated = imagerotate($image, $angle, 0);

        return $rotated instanceof GdImage ? $rotated : $image;
    }

    /** Copie réduite à `$width` pixels de large au plus (jamais agrandie), proportions gardées. */
    public static function resize(GdImage $source, int $width): GdImage
    {
        $w = imagesx($source);
        $h = imagesy($source);
        $targetW = max(1, min($width, $w));
        $targetH = max(1, (int) round($h * $targetW / $w));

        $out = imagecreatetruecolor($targetW, $targetH);
        if (self::format() === 'webp') {
            imagealphablending($out, false);
            imagesavealpha($out, true);
            imagefill($out, 0, 0, (int) imagecolorallocatealpha($out, 0, 0, 0, 127));
        } else {
            imagefill($out, 0, 0, (int) imagecolorallocate($out, 255, 255, 255));
        }
        imagecopyresampled($out, $source, 0, 0, 0, 0, $targetW, $targetH, $w, $h);

        return $out;
    }

    private static function encode(GdImage $image): string
    {
        ob_start();
        $ok = self::format() === 'webp'
            ? imagewebp($image, null, self::WEBP_QUALITY)
            : imagejpeg($image, null, self::JPEG_QUALITY);
        $data = (string) ob_get_clean();
        if (! $ok || $data === '') {
            throw new RuntimeException('Encodage de la miniature impossible');
        }

        return $data;
    }
}

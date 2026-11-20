<?php

namespace App\Services\Media;

/**
 * Image nettoyée par ImageSanitizer : les octets à enregistrer, son type réel, ses dimensions
 * telles qu'elles sont stockées dans le fichier, l'orientation EXIF gardée (JPEG) et la liste des
 * métadonnées retirées (`exif`, `gps`, `xmp`, `iptc`, `comment`, `text`, `other`).
 */
final readonly class SanitizedImage
{
    /** @param  list<string>  $removed */
    public function __construct(
        public string $bytes,
        public string $format,
        public string $mime,
        public int $width,
        public int $height,
        public ?int $orientation,
        public array $removed,
    ) {}

    /** Extension du fichier enregistré. */
    public function extension(): string
    {
        return $this->format === 'jpeg' ? 'jpg' : $this->format;
    }

    /** Largeur affichée : une orientation EXIF de 5 à 8 fait pivoter l'image d'un quart de tour. */
    public function displayWidth(): int
    {
        return $this->rotated() ? $this->height : $this->width;
    }

    public function displayHeight(): int
    {
        return $this->rotated() ? $this->width : $this->height;
    }

    public function hadLocation(): bool
    {
        return in_array('gps', $this->removed, true);
    }

    private function rotated(): bool
    {
        return $this->orientation !== null && $this->orientation >= 5;
    }
}

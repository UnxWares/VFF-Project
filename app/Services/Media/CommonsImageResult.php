<?php

declare(strict_types=1);

namespace App\Services\Media;

/** Réponse de CommonsImageRelay : un fichier du cache à servir, ou la raison de l'échec. */
final readonly class CommonsImageResult
{
    /**
     * @param  string|null  $file  chemin complet du fichier du cache
     * @param  string|null  $relativePath  le même, relatif au dossier du cache (X-Accel-Redirect)
     * @param  int  $retryAfter  secondes à attendre (Busy)
     */
    private function __construct(
        public ?string $file,
        public ?string $relativePath,
        public ?string $mimeType,
        public ?CommonsImageFailure $failure,
        public int $retryAfter,
    ) {}

    public static function found(string $file, string $relativePath, string $mimeType): self
    {
        return new self($file, $relativePath, $mimeType, null, 0);
    }

    public static function failed(CommonsImageFailure $failure, int $retryAfter = 0): self
    {
        return new self(null, null, null, $failure, $retryAfter);
    }
}

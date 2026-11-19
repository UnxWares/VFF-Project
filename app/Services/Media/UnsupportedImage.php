<?php

namespace App\Services\Media;

/**
 * Fichier refusé par ImageSanitizer, avec une raison stable (traduite par l'appelant, jamais le
 * détail technique) :
 *   - `type` : ni JPEG, ni PNG, ni WebP d'après ses premiers octets ;
 *   - `corrupt` : structure illisible (fichier tronqué, segment de longueur impossible) ;
 *   - `metadata` : des métadonnées restent après le nettoyage (le fichier n'est pas gardé).
 */
final class UnsupportedImage extends \RuntimeException
{
    public const TYPE = 'type';

    public const CORRUPT = 'corrupt';

    public const METADATA = 'metadata';

    public function __construct(public readonly string $reason)
    {
        parent::__construct("Image refusée : {$reason}");
    }
}

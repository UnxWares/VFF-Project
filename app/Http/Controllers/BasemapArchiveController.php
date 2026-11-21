<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Basemap\BasemapArchive;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Archive PMTiles du fond de carte libre (GET /basemap/{archive}), lue par plages d'octets : la réponse à une
 * requête `Range: bytes=début-fin` est une réponse partielle 206 (BinaryFileResponse s'en charge, If-Range
 * compris). Route hors du groupe web : ni session ni cookie, la même réponse pour tous.
 *
 * L'adresse porte la date des données : la réponse se garde longtemps (config maps.basemap.max_age). L'ETag
 * est tiré du nom, de la taille et de la date du fichier, sans relire ses 6 Go. En production, le serveur
 * web peut servir ce fichier lui-même (docs/FOND-DE-CARTE.md) ; cette route reste le repli.
 */
final class BasemapArchiveController
{
    public function __invoke(string $archive): BinaryFileResponse
    {
        $basemap = BasemapArchive::fromConfig();
        abort_unless($basemap->serves($archive), 404);

        $path = (string) $basemap->path();
        $etag = hash('xxh128', $archive.'|'.filesize($path).'|'.filemtime($path));

        $response = new BinaryFileResponse($path, 200, [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ], true, null, false, true);
        $response->setEtag($etag);
        $response->setPublic();
        $response->setMaxAge($basemap->maxAge);

        return $response;
    }
}

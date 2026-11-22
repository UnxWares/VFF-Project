<?php

declare(strict_types=1);

namespace App\Support\Media;

/**
 * Chemin d'une image de Wikimedia Commons que le relais de VFF accepte de servir (CommonsImageController),
 * relatif à `https://upload.wikimedia.org/wikipedia/commons/`.
 *
 * Liste blanche stricte, seules deux formes passent :
 *   - vignette : `thumb/{h}/{hh}/{Fichier}/{préfixe}{largeur}px-{nom}`, largeur parmi WIDTHS (celles que VFF
 *     demande), préfixe facultatif `lossy-`, `lossless-`, `page{n}-` (TIFF, PDF), nom égal au fichier, au
 *     fichier suivi d'une extension d'image (SVG rendu en PNG, TIFF en JPEG), ou `thumbnail.{ext}` (nom trop
 *     long, abrégé par Commons) ;
 *   - original d'une image matricielle (JPEG, PNG, GIF, WebP) : `{h}/{hh}/{Fichier}`, comme les petites images
 *     que Commons ne réduit pas. Jamais un original SVG, TIFF ou PDF.
 * `{h}/{hh}` doivent être le début de l'empreinte MD5 du nom du fichier, comme sur Commons : on ne peut pas
 * composer un chemin au hasard. Chaque segment est décodé une seule fois : encodage malformé, double encodage
 * (`%252F`), barre oblique ou contre-oblique encodée, caractère de contrôle, `.` et `..` sont refusés.
 *
 * L'hôte n'est jamais lu dans la requête : l'image se lit toujours sur UPSTREAM_BASE, en HTTPS.
 */
final readonly class CommonsImagePath
{
    public const UPSTREAM_BASE = 'https://upload.wikimedia.org/wikipedia/commons/';

    /** Hôtes de Wikimedia dont VFF reprend les adresses de vignettes (même arborescence). */
    public const HOSTS = ['upload.wikimedia.org', 'thumb.wikimedia.org'];

    /**
     * Largeurs de vignette que VFF utilise : 500 (galeries, CommonsClient::THUMB_WIDTH), 960 et 1 280
     * (visionneuse, CommonsMetadata), 330 (matériel roulant des fiches, Material::THUMB_WIDTH), 250 et 568
     * (quelques images importées).
     */
    public const WIDTHS = [250, 330, 500, 568, 960, 1280];

    /** Types servis, par extension : uniquement des images matricielles, jamais de SVG. */
    public const MIME_TYPES = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
    ];

    /** Fichiers d'origine dont Commons produit des vignettes matricielles que VFF affiche. */
    private const THUMBNAIL_SOURCES = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'tif', 'tiff', 'svg', 'pdf', 'djvu'];

    private const MAX_RAW_LENGTH = 1024;

    /** Caractères admis dans un segment brut (RFC 3986 « pchar »), échappements `%XX` compris. */
    private const RAW_SEGMENT = '/^(?:[A-Za-z0-9._~!$&\'()*+,;=:@-]|%[0-9A-Fa-f]{2})+$/';

    /**
     * @param  string  $path  chemin canonique, chaque segment encodé par rawurlencode
     * @param  string  $extension  extension de l'image servie (clé de MIME_TYPES)
     * @param  int|null  $width  largeur demandée d'une vignette, null pour un original
     */
    private function __construct(
        public string $path,
        public string $extension,
        public ?int $width,
    ) {}

    /** Chemin brut d'une requête (encodé comme dans l'adresse), ou null s'il sort de la liste blanche. */
    public static function parse(string $raw): ?self
    {
        if ($raw === '' || strlen($raw) > self::MAX_RAW_LENGTH) {
            return null;
        }
        $segments = [];
        foreach (explode('/', $raw) as $segment) {
            $decoded = self::decodeSegment($segment);
            if ($decoded === null) {
                return null;
            }
            $segments[] = $decoded;
        }

        return match (true) {
            count($segments) === 5 && $segments[0] === 'thumb' => self::thumbnail(...array_slice($segments, 1)),
            count($segments) === 3 => self::original(...$segments),
            default => null,
        };
    }

    /**
     * Adresse complète d'une image de Commons (`https://upload.wikimedia.org/wikipedia/commons/…` ou
     * `https://thumb.wikimedia.org/…`), ou null pour toute autre adresse.
     */
    public static function fromUrl(string $url): ?self
    {
        $parts = parse_url($url);
        if (! is_array($parts) || ($parts['scheme'] ?? null) !== 'https' || ! in_array($parts['host'] ?? null, self::HOSTS, true)
            || isset($parts['port']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            return null;
        }
        $path = $parts['path'] ?? '';
        $prefix = '/wikipedia/commons/';

        return str_starts_with($path, $prefix) ? self::parse(substr($path, strlen($prefix))) : null;
    }

    /** Adresse de l'image sur Commons, toujours sur upload.wikimedia.org. */
    public function upstreamUrl(): string
    {
        return self::UPSTREAM_BASE.$this->path;
    }

    /** Clé du cache : empreinte SHA-256 du chemin canonique (64 caractères hexadécimaux). */
    public function cacheKey(): string
    {
        return hash('sha256', $this->path);
    }

    public function mimeType(): string
    {
        return self::MIME_TYPES[$this->extension];
    }

    /** Segment décodé une fois, ou null s'il est malformé ou piégé. */
    private static function decodeSegment(string $segment): ?string
    {
        if (preg_match(self::RAW_SEGMENT, $segment) !== 1) {
            return null;
        }
        $decoded = rawurldecode($segment);
        if ($decoded === '.' || $decoded === '..' || ! mb_check_encoding($decoded, 'UTF-8')
            || preg_match('/[\/\\\\\p{Cc}]|%[0-9A-Fa-f]{2}/u', $decoded) !== 0) {
            return null;
        }

        return $decoded;
    }

    private static function thumbnail(string $h, string $hh, string $file, string $name): ?self
    {
        $source = self::extension($file);
        if (! self::hashed($h, $hh, $file) || ! in_array($source, self::THUMBNAIL_SOURCES, true)) {
            return null;
        }
        if (preg_match('/^(?:(?:lossy|lossless)-)?(?:page[1-9][0-9]{0,3}-)?([1-9][0-9]{1,3})px-(.+)$/', $name, $m) !== 1) {
            return null;
        }
        $width = (int) $m[1];
        $rest = $m[2];
        $extension = self::extension($rest);
        if (! in_array($width, self::WIDTHS, true) || ! isset(self::MIME_TYPES[$extension])) {
            return null;
        }
        $suffix = substr($rest, 0, -strlen($extension) - 1);
        if ($rest !== $file && $suffix !== $file && $suffix !== 'thumbnail') {
            return null;
        }

        return new self(self::encode(['thumb', $h, $hh, $file, $name]), $extension, $width);
    }

    private static function original(string $h, string $hh, string $file): ?self
    {
        $extension = self::extension($file);
        if (! self::hashed($h, $hh, $file) || ! isset(self::MIME_TYPES[$extension])) {
            return null;
        }

        return new self(self::encode([$h, $hh, $file]), $extension, null);
    }

    /** `{h}/{hh}` : premier caractère et deux premiers caractères du MD5 du nom (règle de Commons). */
    private static function hashed(string $h, string $hh, string $file): bool
    {
        $md5 = md5($file);

        return $h === $md5[0] && $hh === substr($md5, 0, 2) && preg_match('/^.+\.[A-Za-z0-9]{2,5}$/su', $file) === 1;
    }

    private static function extension(string $name): string
    {
        $dot = strrpos($name, '.');

        return $dot === false ? '' : strtolower(substr($name, $dot + 1));
    }

    /** @param  list<string>  $segments */
    private static function encode(array $segments): string
    {
        return implode('/', array_map(rawurlencode(...), $segments));
    }
}

<?php

namespace App\Services\Media;

/**
 * Contrôle et nettoyage d'une image envoyée par un contributeur, avant tout enregistrement.
 *
 * L'image PHP du projet n'a ni GD ni Imagick : le fichier est lu dans sa structure (segments JPEG,
 * blocs PNG et WebP), sans décoder les pixels, qui sont recopiés tels quels.
 *
 *   - type réel : lu dans les premiers octets (signature), jamais dans le nom du fichier ni dans le
 *     type annoncé par le navigateur, puis confirmé par getimagesizefromstring() ;
 *   - métadonnées retirées : EXIF (position GPS, appareil, numéro de série, dates), XMP, IPTC,
 *     commentaires, blocs de texte, et tout ce qui suit la fin de l'image. Restent seulement les
 *     segments nécessaires à l'affichage : profil de couleur ICC, en-têtes JFIF et Adobe, tables de
 *     l'image, transparence, gamma, animation APNG ;
 *   - orientation (JPEG) : réécrite seule dans un EXIF minimal, pour qu'une photo prise en portrait
 *     ne s'affiche pas couchée ;
 *   - contrôle final : le fichier nettoyé est relu ; s'il reste une métadonnée, il est refusé.
 *
 * Formats acceptés : JPEG, PNG, WebP.
 */
final class ImageSanitizer
{
    /** Type MIME de chaque format accepté. */
    public const MIMES = ['jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];

    private const PNG_SIGNATURE = "\x89PNG\r\n\x1A\n";

    /** Blocs PNG gardés : l'image, ses couleurs, sa transparence, sa densité, l'animation APNG. */
    private const PNG_KEEP = ['IHDR', 'PLTE', 'IDAT', 'IEND', 'tRNS', 'gAMA', 'cHRM', 'sRGB', 'iCCP', 'sBIT', 'bKGD', 'pHYs', 'acTL', 'fcTL', 'fdAT'];

    /** Étiquettes EXIF lues avant le retrait : orientation, pointeur vers les données GPS. */
    private const TAG_ORIENTATION = 0x0112;

    private const TAG_GPS = 0x8825;

    /**
     * Format d'après la signature du fichier, null sinon.
     *
     * @return 'jpeg'|'png'|'webp'|null
     */
    public static function detect(string $bytes): ?string
    {
        return match (true) {
            str_starts_with($bytes, "\xFF\xD8\xFF") => 'jpeg',
            str_starts_with($bytes, self::PNG_SIGNATURE) => 'png',
            strlen($bytes) >= 12 && str_starts_with($bytes, 'RIFF') && substr($bytes, 8, 4) === 'WEBP' => 'webp',
            default => null,
        };
    }

    /**
     * Image nettoyée, prête à être enregistrée.
     *
     * @throws UnsupportedImage type non accepté, fichier illisible ou métadonnées impossibles à retirer
     */
    public function sanitize(string $bytes): SanitizedImage
    {
        $format = self::detect($bytes) ?? throw new UnsupportedImage(UnsupportedImage::TYPE);
        [$clean, $orientation, $removed] = match ($format) {
            'jpeg' => self::stripJpeg($bytes),
            'png' => self::stripPng($bytes),
            'webp' => self::stripWebp($bytes),
        };

        if (self::metadataLeft($clean, $format, $orientation)) {
            throw new UnsupportedImage(UnsupportedImage::METADATA);
        }
        $size = @getimagesizefromstring($clean);
        if ($size === false || $size['mime'] !== self::MIMES[$format] || $size[0] < 1 || $size[1] < 1) {
            throw new UnsupportedImage(UnsupportedImage::CORRUPT);
        }

        return new SanitizedImage($clean, $format, self::MIMES[$format], (int) $size[0], (int) $size[1], $orientation, $removed);
    }

    /**
     * Vrai s'il reste dans le fichier une métadonnée qu'ImageSanitizer retire (contrôle final, et
     * vérification dans les tests). L'EXIF minimal qui ne porte que l'orientation est admis.
     */
    public static function metadataLeft(string $bytes, string $format, ?int $orientation = null): bool
    {
        try {
            return match ($format) {
                'jpeg' => array_any(
                    self::jpegSegments($bytes),
                    fn (array $s): bool => self::jpegMetadataKind($s[0], substr($s[1], 4)) !== null
                        && ($orientation === null || $s[1] !== self::orientationExif($orientation)),
                ),
                'png' => array_any(self::pngChunks($bytes), fn (array $c): bool => ! in_array($c[0], self::PNG_KEEP, true)),
                'webp' => array_any(self::webpChunks($bytes), fn (array $c): bool => in_array($c[0], ['EXIF', 'XMP '], true)),
                default => true,
            };
        } catch (UnsupportedImage) {
            return true;
        }
    }

    // ─── JPEG ───────────────────────────────────────────────────────────────

    /**
     * @return array{0: string, 1: ?int, 2: list<string>} fichier nettoyé, orientation gardée, métadonnées retirées
     */
    private static function stripJpeg(string $bytes): array
    {
        $out = "\xFF\xD8";
        $afterJfif = null;
        $orientation = null;
        $removed = [];
        foreach (self::jpegSegments($bytes) as [$marker, $segment]) {
            $payload = substr($segment, 4);
            $kind = self::jpegMetadataKind($marker, $payload);
            if ($kind === null) {
                $out .= $segment;
                // L'en-tête JFIF doit suivre immédiatement le début du fichier : l'EXIF minimal se place après lui.
                if ($marker === 0xE0 && strlen($out) === 2 + strlen($segment)) {
                    $afterJfif = strlen($out);
                }

                continue;
            }
            $removed[] = $kind;
            if ($kind === 'exif') {
                [$found, $gps] = self::exifTags(substr($payload, 6));
                $orientation ??= $found;
                if ($gps) {
                    $removed[] = 'gps';
                }
            }
            if ($kind === 'xmp' && str_contains($payload, 'GPSLatitude')) {
                $removed[] = 'gps';
            }
        }

        // Orientation 1 (« normale ») : rien à réécrire.
        if ($orientation === 1) {
            $orientation = null;
        }
        if ($orientation !== null) {
            $at = $afterJfif ?? 2;
            $out = substr($out, 0, $at).self::orientationExif($orientation).substr($out, $at);
        }

        return [$out, $orientation, array_values(array_unique($removed))];
    }

    /**
     * Segments d'un JPEG dans l'ordre, chacun avec son marqueur : [marqueur, octets du segment]. Un
     * segment SOS emporte les données compressées qui le suivent, jusqu'au marqueur suivant (un
     * JPEG progressif en a plusieurs). Tout ce qui suit la fin de l'image (EOI) est ignoré.
     *
     * @return list<array{0: int, 1: string}>
     *
     * @throws UnsupportedImage structure illisible ou fichier tronqué
     */
    private static function jpegSegments(string $bytes): array
    {
        $len = strlen($bytes);
        if ($len < 4 || ! str_starts_with($bytes, "\xFF\xD8")) {
            throw new UnsupportedImage(UnsupportedImage::CORRUPT);
        }
        $segments = [];
        $pos = 2;
        while (true) {
            if ($pos >= $len || $bytes[$pos] !== "\xFF") {
                throw new UnsupportedImage(UnsupportedImage::CORRUPT);
            }
            // Octets de remplissage (0xFF répétés) avant un marqueur.
            while ($pos < $len && $bytes[$pos] === "\xFF") {
                $pos++;
            }
            if ($pos >= $len) {
                throw new UnsupportedImage(UnsupportedImage::CORRUPT);
            }
            $marker = ord($bytes[$pos++]);
            if ($marker === 0xD9) {
                $segments[] = [$marker, "\xFF\xD9"];

                return $segments;
            }
            if ($marker === 0x00) {
                throw new UnsupportedImage(UnsupportedImage::CORRUPT);
            }
            // Marqueurs sans longueur : TEM, reprises RST0 à RST7.
            if ($marker === 0x01 || ($marker >= 0xD0 && $marker <= 0xD7)) {
                $segments[] = [$marker, "\xFF".chr($marker)];

                continue;
            }
            if ($pos + 2 > $len) {
                throw new UnsupportedImage(UnsupportedImage::CORRUPT);
            }
            $length = unpack('n', $bytes, $pos)[1];
            if ($length < 2 || $pos + $length > $len) {
                throw new UnsupportedImage(UnsupportedImage::CORRUPT);
            }
            $segment = "\xFF".chr($marker).substr($bytes, $pos, $length);
            $pos += $length;
            if ($marker === 0xDA) {
                $end = self::entropyEnd($bytes, $pos);
                $segment .= substr($bytes, $pos, $end - $pos);
                $pos = $end;
            }
            $segments[] = [$marker, $segment];
        }
    }

    /**
     * Fin des données compressées qui suivent un SOS : le premier 0xFF qui n'est ni un octet
     * bourré (FF 00), ni une reprise (FF D0 à FF D7), ni un remplissage (FF FF).
     *
     * @throws UnsupportedImage aucun marqueur après les données (fichier tronqué)
     */
    private static function entropyEnd(string $bytes, int $from): int
    {
        $len = strlen($bytes);
        $at = $from;
        while (true) {
            $ff = strpos($bytes, "\xFF", $at);
            if ($ff === false || $ff + 1 >= $len) {
                throw new UnsupportedImage(UnsupportedImage::CORRUPT);
            }
            $next = ord($bytes[$ff + 1]);
            if ($next === 0x00 || $next === 0xFF || ($next >= 0xD0 && $next <= 0xD7)) {
                $at = $ff + 1;

                continue;
            }

            return $ff;
        }
    }

    /** Nature d'un segment de métadonnées (à retirer), null pour un segment à garder. */
    private static function jpegMetadataKind(int $marker, string $payload): ?string
    {
        return match (true) {
            $marker === 0xE1 && str_starts_with($payload, "Exif\0") => 'exif',
            $marker === 0xE1 && str_starts_with($payload, 'http://ns.adobe.com/') => 'xmp',
            $marker === 0xED => 'iptc',
            $marker === 0xFE => 'comment',
            $marker === 0xE0 => str_starts_with($payload, "JFIF\0") ? null : 'other',
            $marker === 0xE2 => str_starts_with($payload, "ICC_PROFILE\0") ? null : 'other',
            $marker === 0xEE => str_starts_with($payload, 'Adobe') ? null : 'other',
            $marker >= 0xE1 && $marker <= 0xEF => 'other',
            default => null,
        };
    }

    /**
     * Orientation (1 à 8) et présence de données GPS dans le premier répertoire (IFD0) d'un bloc
     * EXIF au format TIFF. Une structure illisible ne donne rien (le bloc est retiré quand même).
     *
     * @return array{0: ?int, 1: bool}
     */
    private static function exifTags(string $tiff): array
    {
        $littleEndian = match (substr($tiff, 0, 2)) {
            'II' => true,
            'MM' => false,
            default => null,
        };
        $size = strlen($tiff);
        if ($littleEndian === null || $size < 8) {
            return [null, false];
        }
        $u16 = fn (int $at): ?int => $at >= 0 && $at + 2 <= $size ? unpack($littleEndian ? 'v' : 'n', $tiff, $at)[1] : null;
        $u32 = fn (int $at): ?int => $at >= 0 && $at + 4 <= $size ? unpack($littleEndian ? 'V' : 'N', $tiff, $at)[1] : null;
        $ifd = $u32(4);
        $count = $u16(2) === 42 && $ifd !== null ? $u16($ifd) : null;
        if ($count === null) {
            return [null, false];
        }

        $orientation = null;
        $gps = false;
        for ($i = 0; $i < min($count, 512); $i++) {
            $entry = $ifd + 2 + 12 * $i;
            $tag = $u16($entry);
            if ($tag === null) {
                break;
            }
            if ($tag === self::TAG_ORIENTATION) {
                $value = $u16($entry + 8);
                $orientation = $value !== null && $value >= 1 && $value <= 8 ? $value : null;
            }
            $gps = $gps || $tag === self::TAG_GPS;
        }

        return [$orientation, $gps];
    }

    /** Segment APP1 EXIF minimal : une seule étiquette, l'orientation (valeur courte, ordre Motorola). */
    private static function orientationExif(int $orientation): string
    {
        $tiff = 'MM'.pack('n', 42).pack('N', 8)
            .pack('n', 1)
            .pack('nnN', self::TAG_ORIENTATION, 3, 1).pack('n', $orientation)."\0\0"
            .pack('N', 0);
        $payload = "Exif\0\0".$tiff;

        return "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;
    }

    // ─── PNG ────────────────────────────────────────────────────────────────

    /** @return array{0: string, 1: null, 2: list<string>} fichier nettoyé, sans orientation, métadonnées retirées */
    private static function stripPng(string $bytes): array
    {
        $chunks = self::pngChunks($bytes);
        if ($chunks[0][0] !== 'IHDR') {
            throw new UnsupportedImage(UnsupportedImage::CORRUPT);
        }
        $out = self::PNG_SIGNATURE;
        $removed = [];
        foreach ($chunks as [$type, $chunk]) {
            if (in_array($type, self::PNG_KEEP, true)) {
                $out .= $chunk;

                continue;
            }
            // Bloc critique inconnu (initiale majuscule) : l'image ne se lirait pas sans lui.
            if (ctype_upper($type[0])) {
                throw new UnsupportedImage(UnsupportedImage::CORRUPT);
            }
            $data = substr($chunk, 8, -4);
            $kind = match (true) {
                $type === 'eXIf' => 'exif',
                $type === 'iTXt' && str_starts_with($data, "XML:com.adobe.xmp\0") => 'xmp',
                in_array($type, ['tEXt', 'zTXt', 'iTXt'], true) => 'text',
                default => 'other',
            };
            $removed[] = $kind;
            if (($kind === 'exif' && self::exifTags($data)[1]) || ($kind === 'xmp' && str_contains($data, 'GPSLatitude'))) {
                $removed[] = 'gps';
            }
        }

        return [$out, null, array_values(array_unique($removed))];
    }

    /**
     * Blocs d'un PNG jusqu'à IEND : [type, bloc complet (longueur, type, données, CRC)].
     *
     * @return non-empty-list<array{0: string, 1: string}>
     *
     * @throws UnsupportedImage signature absente, bloc tronqué ou IEND manquant
     */
    private static function pngChunks(string $bytes): array
    {
        if (! str_starts_with($bytes, self::PNG_SIGNATURE)) {
            throw new UnsupportedImage(UnsupportedImage::CORRUPT);
        }
        $len = strlen($bytes);
        $pos = 8;
        $chunks = [];
        while ($pos + 12 <= $len) {
            $size = unpack('N', $bytes, $pos)[1];
            $type = substr($bytes, $pos + 4, 4);
            if (preg_match('/^[A-Za-z]{4}$/', $type) !== 1 || $pos + 12 + $size > $len) {
                throw new UnsupportedImage(UnsupportedImage::CORRUPT);
            }
            $chunks[] = [$type, substr($bytes, $pos, 12 + $size)];
            $pos += 12 + $size;
            if ($type === 'IEND') {
                return $chunks;
            }
        }

        throw new UnsupportedImage(UnsupportedImage::CORRUPT);
    }

    // ─── WebP ───────────────────────────────────────────────────────────────

    /** @return array{0: string, 1: null, 2: list<string>} fichier nettoyé, sans orientation, métadonnées retirées */
    private static function stripWebp(string $bytes): array
    {
        $body = '';
        $removed = [];
        foreach (self::webpChunks($bytes) as [$fourcc, $chunk]) {
            if ($fourcc === 'EXIF' || $fourcc === 'XMP ') {
                $data = substr($chunk, 8);
                $removed[] = $fourcc === 'EXIF' ? 'exif' : 'xmp';
                $tiff = str_starts_with($data, "Exif\0") ? substr($data, 6) : $data;
                if (($fourcc === 'EXIF' && self::exifTags($tiff)[1]) || ($fourcc === 'XMP ' && str_contains($data, 'GPSLatitude'))) {
                    $removed[] = 'gps';
                }

                continue;
            }
            if ($fourcc === 'VP8X' && strlen($chunk) > 8) {
                // Drapeaux du format étendu : plus d'EXIF (0x08) ni de XMP (0x04) annoncés.
                $chunk[8] = chr(ord($chunk[8]) & ~0x0C);
            }
            $body .= $chunk;
        }

        return ['RIFF'.pack('V', 4 + strlen($body)).'WEBP'.$body, null, array_values(array_unique($removed))];
    }

    /**
     * Blocs d'un WebP (conteneur RIFF) : [code de quatre lettres, bloc complet avec son octet de
     * bourrage]. Ce qui dépasse la taille annoncée par l'en-tête RIFF est ignoré.
     *
     * @return non-empty-list<array{0: string, 1: string}>
     *
     * @throws UnsupportedImage en-tête absent ou bloc tronqué
     */
    private static function webpChunks(string $bytes): array
    {
        $len = strlen($bytes);
        if ($len < 20 || ! str_starts_with($bytes, 'RIFF') || substr($bytes, 8, 4) !== 'WEBP') {
            throw new UnsupportedImage(UnsupportedImage::CORRUPT);
        }
        $end = min($len, 8 + unpack('V', $bytes, 4)[1]);
        $pos = 12;
        $chunks = [];
        while ($pos + 8 <= $end) {
            $size = unpack('V', $bytes, $pos + 4)[1];
            if ($pos + 8 + $size > $end) {
                throw new UnsupportedImage(UnsupportedImage::CORRUPT);
            }
            $total = 8 + $size + ($size % 2);
            $chunks[] = [substr($bytes, $pos, 4), substr($bytes, $pos, min($total, $end - $pos))];
            $pos += $total;
        }
        if ($chunks === []) {
            throw new UnsupportedImage(UnsupportedImage::CORRUPT);
        }

        return $chunks;
    }
}

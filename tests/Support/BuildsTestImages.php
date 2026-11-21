<?php

namespace Tests\Support;

use Illuminate\Http\UploadedFile;

/**
 * Images de test construites octet par octet (sans décoder de pixels) : JPEG, PNG et
 * WebP aux dimensions voulues, chargés de métadonnées à retirer (EXIF avec position GPS et
 * orientation, XMP avec GPS, commentaire, blocs de texte, données après la fin de l'image).
 * realJpeg() dessine au contraire une vraie photo avec GD, pour les miniatures.
 *
 * uploaded() écrit un vrai fichier temporaire : son type est lu dans le contenu (finfo), comme pour
 * un envoi réel, et non déduit de son nom comme avec UploadedFile::fake(). Les fichiers créés sont
 * effacés par deleteTestImages(), à appeler dans tearDown() après parent::tearDown().
 */
trait BuildsTestImages
{
    /** @var list<string> */
    private array $testImagePaths = [];

    /** Latitude écrite dans les métadonnées des images de test (à rechercher dans les octets). */
    protected const TEST_GPS_MARK = 'GPSLatitude';

    protected function jpegWithLocation(int $width = 800, int $height = 600, int $orientation = 6): string
    {
        // IFD0 : orientation et pointeur vers un répertoire GPS (latitude 45° N).
        $tiff = 'II'.pack('v', 42).pack('V', 8)
            .pack('v', 2)
            .pack('vvV', 0x0112, 3, 1).pack('v', $orientation)."\0\0"
            .pack('vvV', 0x8825, 4, 1).pack('V', 38)
            .pack('V', 0)
            .pack('v', 1).pack('vvV', 0x0002, 5, 3).pack('V', 56)
            .pack('V', 0)
            .pack('VV', 45, 1).pack('VV', 30, 1).pack('VV', 0, 1);

        return "\xFF\xD8"
            .self::jpegSegment(0xE0, "JFIF\0\x01\x01\0\0\x01\0\x01\0\0")
            .self::jpegSegment(0xE1, "Exif\0\0".$tiff)
            .self::jpegSegment(0xE1, "http://ns.adobe.com/xap/1.0/\0<x:xmpmeta><exif:GPSLatitude>45,30N</exif:GPSLatitude></x:xmpmeta>")
            .self::jpegSegment(0xED, "Photoshop 3.0\08BIM\x04\x04\0\0\0\0\0\x0Cauteur:Jean")
            .self::jpegSegment(0xFE, 'Appareil de Jean, numéro 123456')
            .self::jpegBody($width, $height)
            .'MPF-GPSLatitude-apres-la-fin';
    }

    /**
     * Vraie photo JPEG dessinée avec GD (décodable, pour les miniatures) : quart haut gauche rouge,
     * reste bleu, avec un EXIF qui porte l'orientation et une position GPS.
     */
    protected function realJpeg(int $width = 1600, int $height = 1200, int $orientation = 1): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 0, 0, 255));
        imagefilledrectangle($image, 0, 0, intdiv($width, 2) - 1, intdiv($height, 2) - 1, (int) imagecolorallocate($image, 255, 0, 0));
        ob_start();
        imagejpeg($image, null, 90);
        $jpeg = (string) ob_get_clean();
        $tiff = 'II'.pack('v', 42).pack('V', 8)
            .pack('v', 2)
            .pack('vvV', 0x0112, 3, 1).pack('v', $orientation)."\0\0"
            .pack('vvV', 0x8825, 4, 1).pack('V', 38)
            .pack('V', 0)
            .pack('v', 1).pack('vvV', 0x0002, 5, 3).pack('V', 56)
            .pack('V', 0)
            .pack('VV', 45, 1).pack('VV', 30, 1).pack('VV', 0, 1);

        return "\xFF\xD8".self::jpegSegment(0xE1, "Exif\0\0".$tiff).substr($jpeg, 2);
    }

    protected function cleanJpeg(int $width = 800, int $height = 600, int $paddingBytes = 0): string
    {
        return "\xFF\xD8".self::jpegSegment(0xE0, "JFIF\0\x01\x01\0\0\x01\0\x01\0\0").self::jpegBody($width, $height, $paddingBytes);
    }

    protected function pngWithMetadata(int $width = 800, int $height = 600): string
    {
        $exif = 'MM'.pack('n', 42).pack('N', 8).pack('n', 1).pack('nnN', 0x8825, 4, 1).pack('N', 26).pack('N', 0);
        $rows = str_repeat("\0".str_repeat("\x80\x60\x40", $width), $height);

        return "\x89PNG\r\n\x1A\n"
            .self::pngChunk('IHDR', pack('NN', $width, $height)."\x08\x02\0\0\0")
            .self::pngChunk('eXIf', $exif)
            .self::pngChunk('tEXt', "Author\0Jean, ".self::TEST_GPS_MARK.' 45.5')
            .self::pngChunk('iTXt', "XML:com.adobe.xmp\0\0\0\0\0<x:xmpmeta><exif:GPSLatitude>45,30N</exif:GPSLatitude></x:xmpmeta>")
            .self::pngChunk('IDAT', (string) gzcompress($rows))
            .self::pngChunk('IEND', '');
    }

    protected function webpWithMetadata(int $width = 800, int $height = 600): string
    {
        // VP8X : drapeaux EXIF (0x08) et XMP (0x04), taille du canevas moins un sur 24 bits.
        $vp8x = chr(0x0C)."\0\0\0".substr(pack('V', $width - 1), 0, 3).substr(pack('V', $height - 1), 0, 3);
        $vp8l = "\x2F".pack('V', ($width - 1) | (($height - 1) << 14)).str_repeat("\0", 11);
        $exif = 'II'.pack('v', 42).pack('V', 8).pack('v', 1).pack('vvV', 0x8825, 4, 1).pack('V', 26).pack('V', 0);
        $body = self::riffChunk('VP8X', $vp8x)
            .self::riffChunk('VP8L', $vp8l)
            .self::riffChunk('EXIF', $exif)
            .self::riffChunk('XMP ', '<x:xmpmeta><exif:GPSLatitude>45,30N</exif:GPSLatitude></x:xmpmeta>');

        return 'RIFF'.pack('V', 4 + strlen($body)).'WEBP'.$body;
    }

    /** Fichier envoyé, écrit sur le disque : son type sera lu dans son contenu. */
    protected function uploaded(string $bytes, string $name = 'photo.jpg', string $declaredMime = 'image/jpeg'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'vff-img-');
        file_put_contents($path, $bytes);
        $this->testImagePaths[] = $path;

        return new UploadedFile($path, $name, $declaredMime, null, true);
    }

    protected function deleteTestImages(): void
    {
        foreach ($this->testImagePaths as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        $this->testImagePaths = [];
    }

    private static function jpegSegment(int $marker, string $payload): string
    {
        return "\xFF".chr($marker).pack('n', strlen($payload) + 2).$payload;
    }

    /** Tables, en-tête de l'image (SOF0) et données compressées avec octet bourré et reprise, puis fin. */
    private static function jpegBody(int $width, int $height, int $paddingBytes = 0): string
    {
        return self::jpegSegment(0xDB, "\0".str_repeat("\x01", 64))
            .self::jpegSegment(0xC0, "\x08".pack('nn', $height, $width)."\x01\x01\x11\0")
            .self::jpegSegment(0xC4, "\0".str_repeat("\0", 15)."\0")
            .self::jpegSegment(0xDA, "\x01\x01\0\0\x3F\0")
            ."\x12\x34\xFF\x00\x56\xFF\xD0\x78".str_repeat("\x12", $paddingBytes)
            ."\xFF\xD9";
    }

    private static function pngChunk(string $type, string $data): string
    {
        return pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
    }

    private static function riffChunk(string $fourcc, string $data): string
    {
        return $fourcc.pack('V', strlen($data)).$data.(strlen($data) % 2 === 1 ? "\0" : '');
    }
}

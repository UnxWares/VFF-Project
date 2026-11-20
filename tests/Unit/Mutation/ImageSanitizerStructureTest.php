<?php

namespace Tests\Unit\Mutation;

use App\Services\Media\ImageSanitizer;
use App\Services\Media\SanitizedImage;
use App\Services\Media\UnsupportedImage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BuildsTestImages;
use Tests\TestCase;

/**
 * Lecture octet par octet des images déposées (ImageSanitizer), cas par cas : chaque segment JPEG,
 * bloc PNG et bloc WebP de métadonnées reconnu, l'EXIF minimal réécrit pour l'orientation, les
 * fichiers tronqués refusés sans avertissement PHP. Complète tests/Unit/Media/ImageSanitizerTest.php,
 * écrit pour les tests de mutation (tests/Mutation/Critical).
 *
 * La classe de test de Laravel change tout avertissement PHP (lecture hors d'une chaîne) en
 * exception : un fichier tronqué doit être refusé proprement (UnsupportedImage), jamais par accident.
 */
class ImageSanitizerStructureTest extends TestCase
{
    use BuildsTestImages;

    private const SOI = "\xFF\xD8";

    private const EOI = "\xFF\xD9";

    private const JFIF = "JFIF\0\x01\x01\0\0\x01\0\x01\0\0";

    /**
     * EXIF minimal attendu pour l'orientation 6 (TIFF 6.0, ordre Motorola) : en-tête « MM », 42,
     * répertoire à l'octet 8, une entrée (0x0112, type SHORT, une valeur, 6), pas de répertoire suivant.
     */
    private const ORIENTATION_6_EXIF = "\xFF\xE1\x00\x22Exif\0\0"
        ."MM\x00\x2A\x00\x00\x00\x08"
        ."\x00\x01"
        ."\x01\x12\x00\x03\x00\x00\x00\x01\x00\x06\x00\x00"
        ."\x00\x00\x00\x00";

    private function sanitize(string $bytes): SanitizedImage
    {
        return (new ImageSanitizer)->sanitize($bytes);
    }

    private function refusal(string $bytes): ?string
    {
        try {
            $this->sanitize($bytes);

            return null;
        } catch (UnsupportedImage $e) {
            return $e->reason;
        }
    }

    // ─── Fabrication ────────────────────────────────────────────────────────

    /**
     * Bloc TIFF d'un EXIF : un répertoire avec les entrées [étiquette, type, nombre, valeur].
     *
     * @param  list<array{int, int, int, int}>  $entries
     */
    private static function tiff(array $entries, bool $littleEndian = true, int $magic = 42, string $order = ''): string
    {
        [$u16, $u32] = $littleEndian ? ['v', 'V'] : ['n', 'N'];
        $tiff = ($order !== '' ? $order : ($littleEndian ? 'II' : 'MM')).pack($u16, $magic).pack($u32, 8).pack($u16, count($entries));
        foreach ($entries as [$tag, $type, $count, $value]) {
            $tiff .= pack($u16, $tag).pack($u16, $type).pack($u32, $count)
                .($type === 3 ? pack($u16, $value)."\0\0" : pack($u32, $value));
        }

        return $tiff.pack($u32, 0);
    }

    private static function orientation(int $value): array
    {
        return [0x0112, 3, 1, $value];
    }

    private static function gps(): array
    {
        return [0x8825, 4, 1, 26];
    }

    private static function exif(string $tiff): string
    {
        return self::jpegSegment(0xE1, "Exif\0\0".$tiff);
    }

    /** JPEG 4 × 3 : début, segments donnés (en-tête JFIF par défaut), image. */
    private function jpeg(string $segments, bool $jfif = true): string
    {
        return self::SOI.($jfif ? self::jpegSegment(0xE0, self::JFIF) : '').$segments.self::jpegBody(4, 3);
    }

    // ─── Type et dimensions ─────────────────────────────────────────────────

    public function test_webp_needs_both_the_riff_header_and_the_webp_code(): void
    {
        $this->assertNull(ImageSanitizer::detect('ABCD'.pack('V', 4).'WEBP'));
        $this->assertNull(ImageSanitizer::detect('RIFF'.pack('V', 4).'WAVE'));
        $this->assertNull(ImageSanitizer::detect('RIFF'.pack('V', 4).'WEB'));
        $this->assertSame('webp', ImageSanitizer::detect('RIFF'.pack('V', 4).'WEBP'));
    }

    public function test_an_image_without_width_or_height_is_refused(): void
    {
        $this->assertSame(UnsupportedImage::CORRUPT, $this->refusal(self::SOI.self::jpegBody(0, 3)));
        $this->assertSame(UnsupportedImage::CORRUPT, $this->refusal(self::SOI.self::jpegBody(4, 0)));

        $tiny = $this->sanitize(self::SOI.self::jpegBody(1, 1));
        $this->assertSame([1, 1], [$tiny->width, $tiny->height]);
    }

    public function test_metadata_check_of_an_unknown_format_fails_safe(): void
    {
        $this->assertTrue(ImageSanitizer::metadataLeft($this->cleanJpeg(), 'gif'));
        $this->assertTrue(ImageSanitizer::metadataLeft($this->webpWithMetadata(), 'webp'));
        $this->assertFalse(ImageSanitizer::metadataLeft(self::SOI.self::EOI, 'jpeg'), 'plus petit JPEG lisible');
    }

    // ─── JPEG : orientation réécrite ────────────────────────────────────────

    public function test_the_orientation_is_rewritten_in_a_minimal_exif_after_jfif(): void
    {
        $image = $this->sanitize($this->jpeg(self::exif(self::tiff([self::orientation(6), self::gps()]))));

        $this->assertSame($this->jpeg(self::ORIENTATION_6_EXIF), $image->bytes);
        $this->assertSame(6, $image->orientation);
        $this->assertSame(['exif', 'gps'], $image->removed);
    }

    public function test_without_jfif_header_the_orientation_follows_the_start_of_the_file(): void
    {
        $image = $this->sanitize($this->jpeg(self::exif(self::tiff([self::orientation(6)])), jfif: false));

        $this->assertSame($this->jpeg(self::ORIENTATION_6_EXIF, jfif: false), $image->bytes);
    }

    public function test_a_cleaned_file_comes_out_the_same_when_cleaned_again(): void
    {
        $once = $this->sanitize($this->jpeg(self::exif(self::tiff([self::orientation(6)], littleEndian: false))));
        $twice = $this->sanitize($once->bytes);

        $this->assertSame(6, $once->orientation, 'EXIF en ordre Motorola lu');
        $this->assertSame($once->bytes, $twice->bytes);
        $this->assertSame([6, ['exif']], [$twice->orientation, $twice->removed]);
    }

    public function test_the_first_orientation_found_is_kept(): void
    {
        $first = $this->sanitize($this->jpeg(self::exif(self::tiff([self::orientation(3)])).self::exif(self::tiff([]))));
        $later = $this->sanitize($this->jpeg(self::exif(self::tiff([])).self::exif(self::tiff([self::orientation(8)]))));

        $this->assertSame(3, $first->orientation);
        $this->assertSame(8, $later->orientation);
        $this->assertSame(['exif'], $first->removed);
    }

    /** @return iterable<string, array{string, ?int}> */
    public static function orientations(): iterable
    {
        yield 'valeur 8, la plus grande' => [self::tiff([self::orientation(8)]), 8];
        yield 'valeur 0, hors norme' => [self::tiff([self::orientation(0)]), null];
        yield 'valeur 9, hors norme' => [self::tiff([self::orientation(9)]), null];
        yield 'ordre des octets inconnu' => [self::tiff([self::orientation(6)], littleEndian: false, order: 'XX'), null];
        yield 'nombre magique autre que 42' => [self::tiff([self::orientation(6)], magic: 43), null];
        yield 'entrée après les autres' => [self::tiff([[0x0100, 3, 1, 4], [0x0101, 3, 1, 3], self::orientation(5)]), 5];
        yield 'fichier arrêté juste après la valeur' => [substr(self::tiff([self::orientation(6)]), 0, 20), 6];
        yield 'fichier arrêté au milieu de la valeur' => [substr(self::tiff([self::orientation(6)]), 0, 19), null];
    }

    #[DataProvider('orientations')]
    public function test_orientation_values(string $tiff, ?int $orientation): void
    {
        $image = $this->sanitize($this->jpeg(self::exif($tiff)));

        $this->assertSame($orientation, $image->orientation);
        $this->assertSame($orientation !== null, str_contains($image->bytes, 'Exif'));
        $this->assertSame(['exif'], $image->removed);
    }

    public function test_only_the_first_512_entries_are_read(): void
    {
        $filler = array_fill(0, 600, [0x0100, 3, 1, 4]);
        $at511 = $filler;
        $at511[511] = self::orientation(6);
        $at512 = $filler;
        $at512[512] = self::orientation(6);

        $this->assertSame(6, $this->sanitize($this->jpeg(self::exif(self::tiff($at511))))->orientation);
        $this->assertNull($this->sanitize($this->jpeg(self::exif(self::tiff($at512))))->orientation);
    }

    // ─── JPEG : segments retirés ou gardés ──────────────────────────────────

    /** @return iterable<string, array{string, list<string>}> */
    public static function jpegSegments(): iterable
    {
        yield 'EXIF sans GPS' => [self::exif(self::tiff([self::orientation(1)])), ['exif']];
        yield 'EXIF avec GPS en deuxième entrée' => [self::exif(self::tiff([self::orientation(1), self::gps()])), ['exif', 'gps']];
        yield 'XMP sans position' => [self::jpegSegment(0xE1, "http://ns.adobe.com/xap/1.0/\0<x:xmpmeta/>"), ['xmp']];
        yield 'commentaire qui cite GPSLatitude' => [self::jpegSegment(0xFE, 'GPSLatitude inconnue'), ['comment']];
        yield 'commentaire vide' => [self::jpegSegment(0xFE, ''), ['comment']];
        yield 'APP1 inconnu' => [self::jpegSegment(0xE1, "MPF\0données"), ['other']];
        yield 'APP0 autre que JFIF' => [self::jpegSegment(0xE0, "JFXX\0\x10vignette"), ['other']];
        yield 'APP2 autre que ICC' => [self::jpegSegment(0xE2, 'http://ns.adobe.com/xmp/extension/'), ['other']];
        yield 'APP13 IPTC' => [self::jpegSegment(0xED, "Photoshop 3.0\0"), ['iptc']];
        yield 'APP14 autre qu\'Adobe' => [self::jpegSegment(0xEE, 'Ducky'), ['other']];
        yield 'APP15' => [self::jpegSegment(0xEF, 'privé'), ['other']];
        yield 'doublons' => [self::jpegSegment(0xFE, 'un').self::jpegSegment(0xFE, 'deux').self::exif(self::tiff([])), ['comment', 'exif']];
    }

    /** @param  list<string>  $removed */
    #[DataProvider('jpegSegments')]
    public function test_jpeg_metadata_segments(string $segments, array $removed): void
    {
        $image = $this->sanitize($this->jpeg($segments));

        $this->assertSame($removed, $image->removed);
        $this->assertSame($this->jpeg(''), $image->bytes);
    }

    public function test_colour_profile_and_adobe_segments_are_kept(): void
    {
        $kept = self::jpegSegment(0xE2, "ICC_PROFILE\0\x01\x01profil").self::jpegSegment(0xEE, "Adobe\0\x64\0\0\0\0\x01");
        $source = $this->jpeg($kept);

        $image = $this->sanitize($source);

        $this->assertSame($source, $image->bytes);
        $this->assertSame([], $image->removed);
    }

    // ─── JPEG : structure ───────────────────────────────────────────────────

    public function test_fill_bytes_and_markers_without_length_are_read(): void
    {
        $markers = self::SOI."\xFF\xFF".self::jpegSegment(0xE0, self::JFIF)."\xFF\x01\xFF\xD0\xFF\xD7".self::jpegBody(4, 3);

        $this->assertFalse(ImageSanitizer::metadataLeft($markers, 'jpeg'));
        // Sans en-tête d'image lisible avant eux, la taille est introuvable : fichier refusé.
        $this->assertSame(UnsupportedImage::CORRUPT, $this->refusal($markers));
        $this->assertTrue(ImageSanitizer::metadataLeft(self::SOI."\xFF\x00\x00\x04ab".self::EOI, 'jpeg'), 'marqueur 0x00 illisible');
        $this->assertTrue(ImageSanitizer::metadataLeft(self::SOI."\xD9\x00", 'jpeg'), 'marqueur sans 0xFF devant');
    }

    public function test_compressed_data_keeps_its_restarts_and_fill_bytes(): void
    {
        $source = self::SOI.self::jpegSegment(0xE0, self::JFIF)
            .self::jpegSegment(0xDB, "\0".str_repeat("\x01", 64))
            .self::jpegSegment(0xC0, "\x08".pack('nn', 3, 4)."\x01\x01\x11\0")
            // Reprise isolée après l'en-tête de l'image : recopiée telle quelle.
            ."\xFF\xD0"
            .self::jpegSegment(0xDA, "\x01\x01\0\0\x3F\0")
            ."\x12\xFF\xD7\x34\xFF\x00\x56\xFF".self::EOI;

        $this->assertSame($source, $this->sanitize($source)->bytes);
    }

    /** @return iterable<string, array{string}> */
    public static function truncatedJpegs(): iterable
    {
        $sos = "\xFF\xDA\x00\x08\x01\x01\0\0\x3F\0";

        yield 'segment qui finit avec le fichier' => [self::SOI."\xFF\xFE\x00\x04ab"];
        yield 'remplissage en fin de fichier' => [self::SOI."\xFF\xFF"];
        yield 'longueur coupée' => [self::SOI."\xFF\xFE\x00"];
        yield 'données compressées finies par 0xFF' => [self::SOI.$sos."\x12\xFF"];
        yield 'données compressées sans marqueur' => [self::SOI.$sos."\x12\x34"];
        yield 'début de balayage plus long que le fichier' => [self::SOI."\xFF\xDA\x00\x40\x01\x01"];
    }

    #[DataProvider('truncatedJpegs')]
    public function test_truncated_jpegs_are_refused(string $bytes): void
    {
        $this->assertSame(UnsupportedImage::CORRUPT, $this->refusal($bytes));
        $this->assertTrue(ImageSanitizer::metadataLeft($bytes, 'jpeg'));
    }

    // ─── PNG ────────────────────────────────────────────────────────────────

    /** PNG 2 × 2, avec des blocs ajoutés après IHDR. */
    private static function png(string $chunks = '', string $end = ''): string
    {
        return "\x89PNG\r\n\x1A\n"
            .self::pngChunk('IHDR', pack('NN', 2, 2)."\x08\x02\0\0\0")
            .$chunks
            .self::pngChunk('IDAT', (string) gzcompress(str_repeat("\0".str_repeat("\x80\x60\x40", 2), 2)))
            .($end !== '' ? $end : self::pngChunk('IEND', ''));
    }

    /** @return iterable<string, array{string, list<string>}> */
    public static function pngChunks(): iterable
    {
        yield 'eXIf avec GPS' => [self::pngChunk('eXIf', self::tiff([self::gps()], littleEndian: false)), ['exif', 'gps']];
        yield 'eXIf avec orientation, sans GPS' => [self::pngChunk('eXIf', self::tiff([self::orientation(6)])), ['exif']];
        yield 'iTXt ordinaire' => [self::pngChunk('iTXt', "Comment\0\0\0\0\0bonjour"), ['text']];
        yield 'tEXt nommé comme un XMP' => [self::pngChunk('tEXt', "XML:com.adobe.xmp\0<x/>"), ['text']];
        yield 'tEXt qui cite GPSLatitude' => [self::pngChunk('tEXt', "Comment\0GPSLatitude"), ['text']];
        yield 'XMP sans position' => [self::pngChunk('iTXt', "XML:com.adobe.xmp\0\0\0\0\0<x:xmpmeta/>"), ['xmp']];
        yield 'XMP fini par GPSLatitude' => [self::pngChunk('iTXt', "XML:com.adobe.xmp\0\0\0\0\0GPSLatitude"), ['xmp', 'gps']];
        yield 'bloc auxiliaire inconnu' => [self::pngChunk('prVt', 'privé'), ['other']];
        yield 'doublons' => [self::pngChunk('tEXt', "A\0un").self::pngChunk('tEXt', "B\0deux").self::pngChunk('eXIf', self::tiff([])), ['text', 'exif']];
    }

    /** @param  list<string>  $removed */
    #[DataProvider('pngChunks')]
    public function test_png_metadata_chunks(string $chunks, array $removed): void
    {
        $image = $this->sanitize(self::png($chunks));

        $this->assertSame($removed, $image->removed);
        $this->assertSame(self::png(), $image->bytes);
    }

    /** @return iterable<string, array{string}> */
    public static function brokenPngs(): iterable
    {
        yield 'IEND annonçant un octet absent' => [self::png(end: pack('N', 1).'IEND'.pack('N', crc32('IEND')))];
        yield 'IEND sans le dernier octet du CRC' => [substr(self::png(), 0, -1)];
        yield 'type de bloc avec un chiffre' => [self::png(self::pngChunk('ab1c', 'x'))];
    }

    #[DataProvider('brokenPngs')]
    public function test_broken_pngs_are_refused(string $bytes): void
    {
        $this->assertSame(UnsupportedImage::CORRUPT, $this->refusal($bytes));
        $this->assertTrue(ImageSanitizer::metadataLeft($bytes, 'png'));
    }

    // ─── WebP ───────────────────────────────────────────────────────────────

    /** Bloc VP8L d'une image 4 × 3. */
    private static function vp8l(): string
    {
        return self::riffChunk('VP8L', "\x2F".pack('V', 3 | (2 << 14)).str_repeat("\0", 11));
    }

    /** WebP étendu 4 × 3 : VP8X (drapeaux donnés), image, blocs ajoutés ; `$extra` est compté dans la taille RIFF. */
    private static function webp(int $flags = 0, string $chunks = '', string $extra = ''): string
    {
        $body = self::riffChunk('VP8X', chr($flags)."\0\0\0".substr(pack('V', 3), 0, 3).substr(pack('V', 2), 0, 3))
            .self::vp8l().$chunks.$extra;

        return 'RIFF'.pack('V', 4 + strlen($body)).'WEBP'.$body;
    }

    /** @return iterable<string, array{string, list<string>}> */
    public static function webpChunks(): iterable
    {
        yield 'EXIF avec GPS' => [self::riffChunk('EXIF', self::tiff([self::gps()])), ['exif', 'gps']];
        yield 'EXIF précédé de « Exif »' => [self::riffChunk('EXIF', "Exif\0\0".self::tiff([self::gps()])), ['exif', 'gps']];
        yield 'EXIF avec orientation, sans GPS' => [self::riffChunk('EXIF', self::tiff([self::orientation(6)])), ['exif']];
        yield 'EXIF qui contient le mot GPSLatitude, sans GPS' => [self::riffChunk('EXIF', self::tiff([]).'GPSLatitude'), ['exif']];
        yield 'XMP sans position' => [self::riffChunk('XMP ', '<x:xmpmeta/>'), ['xmp']];
        yield 'XMP de longueur impaire puis EXIF' => [self::riffChunk('XMP ', '<x/>a').self::riffChunk('EXIF', self::tiff([])), ['xmp', 'exif']];
        yield 'EXIF vide en dernier' => [self::riffChunk('EXIF', ''), ['exif']];
        yield 'doublons' => [self::riffChunk('EXIF', self::tiff([])).self::riffChunk('EXIF', self::tiff([])).self::riffChunk('XMP ', '<x/>'), ['exif', 'xmp']];
    }

    /** @param  list<string>  $removed */
    #[DataProvider('webpChunks')]
    public function test_webp_metadata_chunks(string $chunks, array $removed): void
    {
        $image = $this->sanitize(self::webp(chunks: $chunks));

        $this->assertSame($removed, $image->removed);
        $this->assertSame(self::webp(), $image->bytes);
    }

    public function test_only_the_exif_and_xmp_flags_are_cleared(): void
    {
        // Réservé (0x01), animation (0x02), XMP (0x04), EXIF (0x08), transparence (0x10), ICC (0x20) :
        // les bits autres qu'EXIF et XMP sont recopiés tels quels.
        $image = $this->sanitize(self::webp(0x3F, self::riffChunk('EXIF', self::tiff([]))));

        $this->assertSame(0x33, ord($image->bytes[20]));
        $this->assertStringContainsString(self::vp8l(), $image->bytes, 'les pixels restent tels quels');
    }

    public function test_bytes_outside_the_riff_size_are_dropped(): void
    {
        $base = self::webp();

        $trailing = $this->sanitize($base.self::riffChunk('EXIF', self::tiff([self::gps()])));
        $this->assertSame([$base, []], [$trailing->bytes, $trailing->removed]);

        // Sept octets dans la taille RIFF : trop peu pour un bloc, ignorés.
        $this->assertSame($base, $this->sanitize(self::webp(extra: 'ABCDEFG'))->bytes);
    }

    public function test_an_odd_last_chunk_without_padding_stays_within_the_riff_size(): void
    {
        $last = 'ABCD'.pack('V', 1).'x';
        $source = self::webp(extra: $last).'Z';

        $this->assertStringEndsWith('WEBP', substr($this->sanitize($source)->bytes, 0, 12));
        $this->assertStringEndsWith($last, $this->sanitize($source)->bytes);
    }

    /** @return iterable<string, array{string, bool}> */
    public static function webpContainers(): iterable
    {
        yield 'plus petit WebP lisible (20 octets)' => ['RIFF'.pack('V', 12).'WEBPABCD'.pack('V', 0), false];
        yield 'RIFX au lieu de RIFF' => ['RIFX'.pack('V', 12).'WEBPABCD'.pack('V', 0), true];
        yield 'WEBX au lieu de WEBP' => ['RIFF'.pack('V', 12).'WEBXABCD'.pack('V', 0), true];
        yield 'bloc qui dépasse d\'un octet' => ['RIFF'.pack('V', 13).'WEBPABCD'.pack('V', 2).'x', true];
    }

    #[DataProvider('webpContainers')]
    public function test_webp_containers(string $bytes, bool $refused): void
    {
        $this->assertSame($refused, ImageSanitizer::metadataLeft($bytes, 'webp'));
    }

    public function test_an_empty_extended_header_has_no_flags_to_clear(): void
    {
        $body = 'VP8X'.pack('V', 0).self::vp8l();
        $source = 'RIFF'.pack('V', 4 + strlen($body)).'WEBP'.$body;

        $this->assertSame($source, $this->sanitize($source)->bytes);
    }
}

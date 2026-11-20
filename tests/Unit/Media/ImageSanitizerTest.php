<?php

namespace Tests\Unit\Media;

use App\Services\Media\ImageSanitizer;
use App\Services\Media\UnsupportedImage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\BuildsTestImages;

/**
 * Nettoyage des images déposées (ImageSanitizer) : type lu dans le contenu, métadonnées retirées
 * (EXIF et position GPS, XMP, IPTC, commentaires, blocs de texte, données après la fin), orientation
 * gardée seule, dimensions lues, fichiers illisibles refusés.
 */
class ImageSanitizerTest extends TestCase
{
    use BuildsTestImages;

    private ImageSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizer = new ImageSanitizer;
    }

    public function test_jpeg_location_and_identifying_metadata_are_removed(): void
    {
        $source = $this->jpegWithLocation(800, 600, 6);
        $this->assertTrue(ImageSanitizer::metadataLeft($source, 'jpeg'));

        $image = $this->sanitizer->sanitize($source);

        $this->assertSame('jpeg', $image->format);
        $this->assertSame('image/jpeg', $image->mime);
        $this->assertSame('jpg', $image->extension());
        $this->assertStringNotContainsString(self::TEST_GPS_MARK, $image->bytes);
        $this->assertStringNotContainsString('Jean', $image->bytes);
        $this->assertStringNotContainsString('123456', $image->bytes);
        $this->assertStringNotContainsString('http://ns.adobe.com', $image->bytes);
        $this->assertStringEndsWith("\xFF\xD9", $image->bytes, 'rien après la fin de l’image');
        $this->assertFalse(ImageSanitizer::metadataLeft($image->bytes, 'jpeg', $image->orientation));
        $this->assertEqualsCanonicalizing(['exif', 'gps', 'xmp', 'iptc', 'comment'], $image->removed);
        $this->assertTrue($image->hadLocation());
    }

    public function test_jpeg_orientation_is_kept_alone_and_dimensions_follow_it(): void
    {
        $image = $this->sanitizer->sanitize($this->jpegWithLocation(800, 600, 6));

        $this->assertSame(6, $image->orientation);
        $this->assertSame([800, 600], [$image->width, $image->height]);
        // Orientation 6 : un quart de tour, l'image s'affiche en portrait.
        $this->assertSame([600, 800], [$image->displayWidth(), $image->displayHeight()]);
        // Un seul EXIF, juste après l'en-tête JFIF, avec la seule étiquette d'orientation.
        $this->assertSame(1, substr_count($image->bytes, "Exif\0\0"));
        $this->assertSame(strlen("\xFF\xD8") + 2 + 16, strpos($image->bytes, "\xFF\xE1"));
        $this->assertSame(800, getimagesizefromstring($image->bytes)[0]);
    }

    public function test_normal_orientation_leaves_no_exif_at_all(): void
    {
        $image = $this->sanitizer->sanitize($this->jpegWithLocation(640, 480, 1));

        $this->assertNull($image->orientation);
        $this->assertStringNotContainsString('Exif', $image->bytes);
        $this->assertSame([640, 480], [$image->displayWidth(), $image->displayHeight()]);
    }

    public function test_clean_jpeg_comes_out_unchanged(): void
    {
        $source = $this->cleanJpeg(1024, 768);

        $image = $this->sanitizer->sanitize($source);

        $this->assertSame($source, $image->bytes);
        $this->assertSame([], $image->removed);
        $this->assertFalse($image->hadLocation());
    }

    public function test_png_text_exif_and_xmp_chunks_are_removed(): void
    {
        $image = $this->sanitizer->sanitize($this->pngWithMetadata(640, 480));

        $this->assertSame('image/png', $image->mime);
        $this->assertSame([640, 480], [$image->width, $image->height]);
        $this->assertStringNotContainsString(self::TEST_GPS_MARK, $image->bytes);
        $this->assertStringNotContainsString('eXIf', $image->bytes);
        $this->assertStringNotContainsString('tEXt', $image->bytes);
        $this->assertStringNotContainsString('Jean', $image->bytes);
        $this->assertEqualsCanonicalizing(['exif', 'gps', 'text', 'xmp'], $image->removed);
        $this->assertFalse(ImageSanitizer::metadataLeft($image->bytes, 'png'));
        $this->assertStringContainsString('IDAT', $image->bytes, 'les pixels restent');
    }

    public function test_webp_exif_and_xmp_chunks_are_removed_and_flags_cleared(): void
    {
        $image = $this->sanitizer->sanitize($this->webpWithMetadata(640, 480));

        $this->assertSame('image/webp', $image->mime);
        $this->assertSame([640, 480], [$image->width, $image->height]);
        $this->assertStringNotContainsString(self::TEST_GPS_MARK, $image->bytes);
        $this->assertStringNotContainsString('EXIF', $image->bytes);
        $this->assertStringNotContainsString('XMP ', $image->bytes);
        // Drapeaux EXIF et XMP du bloc VP8X remis à zéro, taille RIFF recalculée.
        $this->assertSame(0, ord($image->bytes[20]) & 0x0C);
        $this->assertSame(strlen($image->bytes) - 8, unpack('V', $image->bytes, 4)[1]);
        $this->assertEqualsCanonicalizing(['exif', 'gps', 'xmp'], $image->removed);
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function refusedFiles(): array
    {
        return [
            'texte nommé .jpg' => ['Ceci n’est pas une image', UnsupportedImage::TYPE],
            'GIF' => ["GIF89a\x01\0\x01\0\0\0\0;", UnsupportedImage::TYPE],
            'SVG' => ['<svg xmlns="http://www.w3.org/2000/svg"/>', UnsupportedImage::TYPE],
            'JPEG tronqué' => ["\xFF\xD8\xFF\xE0\x00\x10JFIF", UnsupportedImage::CORRUPT],
            'JPEG sans fin d’image' => ["\xFF\xD8\xFF\xDA\x00\x08\x01\x01\0\0\x3F\0\x12\x34", UnsupportedImage::CORRUPT],
            'PNG sans IEND' => ["\x89PNG\r\n\x1A\n\0\0\0\x0DIHDR".str_repeat("\0", 17), UnsupportedImage::CORRUPT],
            'WebP vide' => ['RIFF'.pack('V', 4).'WEBP', UnsupportedImage::CORRUPT],
        ];
    }

    #[DataProvider('refusedFiles')]
    public function test_unreadable_or_foreign_files_are_refused(string $bytes, string $reason): void
    {
        try {
            $this->sanitizer->sanitize($bytes);
            $this->fail('Fichier accepté à tort');
        } catch (UnsupportedImage $e) {
            $this->assertSame($reason, $e->reason);
        }
    }

    public function test_a_png_with_an_unknown_critical_chunk_is_refused(): void
    {
        $png = $this->pngWithMetadata(500, 500);
        $broken = substr($png, 0, -12).self::pngChunk('ABCD', 'inconnu').substr($png, -12);

        $this->expectException(UnsupportedImage::class);
        $this->sanitizer->sanitize($broken);
    }

    public function test_type_comes_from_the_content_only(): void
    {
        $this->assertSame('jpeg', ImageSanitizer::detect($this->cleanJpeg()));
        $this->assertSame('png', ImageSanitizer::detect($this->pngWithMetadata(400, 400)));
        $this->assertSame('webp', ImageSanitizer::detect($this->webpWithMetadata()));
        $this->assertNull(ImageSanitizer::detect('%PDF-1.7'));
    }
}

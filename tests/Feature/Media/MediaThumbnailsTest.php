<?php

namespace Tests\Feature\Media;

use App\Http\Resources\MediaResource;
use App\Models\Media;
use App\Services\Media\ImageSanitizer;
use App\Services\Media\ThumbnailGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BuildsTestImages;
use Tests\TestCase;

/**
 * Miniatures des images déposées (ThumbnailGenerator, vff:media-thumbnails) : largeur bornée sans
 * agrandissement, orientation EXIF appliquée, aucune métadonnée, adresses servies par l'API, repli
 * sur l'original sans miniature.
 */
class MediaThumbnailsTest extends TestCase
{
    use BuildsTestImages;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! ThumbnailGenerator::available()) {
            $this->markTestSkipped('GD (JPEG, PNG, WebP) absent de cette installation de PHP.');
        }
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        try {
            parent::tearDown();
        } finally {
            $this->deleteTestImages();
        }
    }

    /** @return array{0: int, 1: int, 2: string} largeur, hauteur, type */
    private static function dimensions(string $bytes): array
    {
        $size = getimagesizefromstring($bytes);
        self::assertIsArray($size);

        return [$size[0], $size[1], $size['mime']];
    }

    private function uploadMedia(string $bytes, string $path = 'media/contributions/9-abc.jpg'): Media
    {
        Storage::disk('public')->put($path, $bytes);

        return Media::factory()->create(['provider' => Media::PROVIDER_UPLOAD, 'type' => 'photo', 'file_path' => $path, 'provider_meta' => ['source' => 'contribution:9']]);
    }

    public function test_widths_are_bounded_and_small_images_are_never_enlarged(): void
    {
        $thumbs = app(ThumbnailGenerator::class)->render($this->realJpeg(1600, 1200));
        $this->assertSame([[320, 240, 'image/webp'], [1280, 960, 'image/webp']], array_map(self::dimensions(...), array_values($thumbs)));

        $small = app(ThumbnailGenerator::class)->render($this->realJpeg(500, 400));
        $this->assertSame([[320, 256, 'image/webp'], [500, 400, 'image/webp']], array_map(self::dimensions(...), array_values($small)));
    }

    public function test_the_exif_orientation_is_applied_to_the_pixels(): void
    {
        // Orientation 6 : l'image doit tourner d'un quart de tour à droite, le quart rouge passe en haut à droite.
        $webp = app(ThumbnailGenerator::class)->render($this->realJpeg(1600, 1200, 6))[320];
        [$w, $h] = self::dimensions($webp);
        $this->assertSame([320, 427], [$w, $h]);
        $image = imagecreatefromstring($webp);
        $this->assertNotFalse($image);
        $topRight = imagecolorsforindex($image, imagecolorat($image, $w - 10, 10));
        $topLeft = imagecolorsforindex($image, imagecolorat($image, 10, 10));
        $this->assertGreaterThan(200, $topRight['red']);
        $this->assertGreaterThan(200, $topLeft['blue']);
    }

    public function test_thumbnails_carry_no_metadata(): void
    {
        foreach (app(ThumbnailGenerator::class)->render($this->realJpeg(1600, 1200, 6)) as $bytes) {
            $this->assertStringNotContainsString(self::TEST_GPS_MARK, $bytes);
            $this->assertFalse(ImageSanitizer::metadataLeft($bytes, 'webp'));
        }
    }

    public function test_an_unreadable_image_gets_no_thumbnail_and_keeps_its_original_url(): void
    {
        $media = $this->uploadMedia('pas une image');

        $this->assertSame([], app(ThumbnailGenerator::class)->forMedia($media));
        $data = MediaResource::make($media->fresh())->resolve();
        $this->assertSame(Storage::disk('public')->url('media/contributions/9-abc.jpg'), $data['thumb_url']);
        $this->assertSame($data['thumb_url'], $data['display_url']);
    }

    public function test_the_command_backfills_published_images_once(): void
    {
        $media = $this->uploadMedia($this->realJpeg(1600, 1200));
        $missing = Media::factory()->create(['provider' => Media::PROVIDER_UPLOAD, 'type' => 'photo', 'file_path' => 'media/contributions/10-absent.jpg']);
        $commons = Media::factory()->create(['provider' => Media::PROVIDER_COMMONS, 'type' => 'photo', 'file_path' => null, 'external_id' => '42', 'source_url' => 'https://commons.wikimedia.org/wiki/File:Gare.jpg']);

        $this->artisan('vff:media-thumbnails')->expectsOutputToContain('1 image(s) traitée(s), 1 sans miniature')->assertFailed();

        $thumbs = $media->fresh()->provider_meta['thumbs'];
        $this->assertSame(['media/contributions/thumbs/9-abc-320.webp', 'media/contributions/thumbs/9-abc-1280.webp'], array_values($thumbs));
        Storage::disk('public')->assertExists(array_values($thumbs));
        $this->assertSame('contribution:9', $media->fresh()->provider_meta['source'], 'la provenance est gardée');
        $this->assertArrayNotHasKey('thumbs', $missing->fresh()->provider_meta ?? []);
        $this->assertArrayNotHasKey('thumbs', $commons->fresh()->provider_meta ?? []);

        $data = MediaResource::make($media->fresh())->resolve();
        $this->assertSame(Storage::disk('public')->url($thumbs[320]), $data['thumb_url']);
        $this->assertSame(Storage::disk('public')->url($thumbs[1280]), $data['display_url']);

        // Second passage : l'image déjà traitée n'est pas refaite, seule l'image absente reste.
        $this->artisan('vff:media-thumbnails')->expectsOutputToContain('0 image(s) traitée(s), 1 sans miniature');
        $this->artisan('vff:media-thumbnails --force')->expectsOutputToContain('1 image(s) traitée(s), 1 sans miniature');
    }
}

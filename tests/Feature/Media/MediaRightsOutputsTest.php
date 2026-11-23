<?php

namespace Tests\Feature\Media;

use App\Models\Line;
use App\Models\Media;
use App\Services\HomeArchives;
use App\Services\Media\CommonsAllowlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Sorties des médias que les réglages `show_non_commercial` et `show_uncertain` doivent aussi masquer : page des
 * crédits, réserve de l'accueil, liste du relais de Commons ; et la règle des dérivés (ND).
 */
class MediaRightsOutputsTest extends TestCase
{
    use RefreshDatabase;

    private Line $line;

    private int $n = 1;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->line = Line::factory()->create();
    }

    /** Adresse de vignette valide (le début du MD5 du nom fait le dossier, comme sur Commons). */
    private static function thumb(string $file, int $width): string
    {
        $md5 = md5($file);

        return "https://upload.wikimedia.org/wikipedia/commons/thumb/{$md5[0]}/".substr($md5, 0, 2).'/'.rawurlencode($file)."/{$width}px-".rawurlencode($file);
    }

    /** @param  array<string, mixed>  $over */
    private function image(array $over = []): Media
    {
        $n = $this->n++;

        return Media::factory()->create($over + [
            'mediable_type' => Line::class, 'mediable_id' => $this->line->id, 'type' => 'photo', 'provider' => Media::PROVIDER_COMMONS,
            'external_id' => "r{$n}", 'file_path' => null, 'is_featured' => true, 'depicted_year' => 1960, 'width' => 1600, 'height' => 1000,
            'caption' => "Vue {$n}", 'author' => 'Auteur', 'license' => 'CC BY-SA 4.0', 'license_url' => 'https://creativecommons.org/licenses/by-sa/4.0',
            'source_url' => "https://commons.wikimedia.org/wiki/File:V{$n}.jpg", 'commercial_ok' => true, 'derivatives_ok' => true,
            'thumb_url' => self::thumb("Rights_{$n}.jpg", 500),
            'display_url' => self::thumb("Rights_{$n}.jpg", 1280),
        ]);
    }

    public function test_the_credits_page_counts_only_displayable_media(): void
    {
        $this->image();
        $this->image(['commercial_ok' => false, 'license' => 'CC BY-NC 4.0']);
        $this->image(['commercial_ok' => null, 'license' => null]);

        $stats = fn () => $this->get('/credits/media')->assertOk()->original->getData()['page']['props'];
        $shown = $stats();
        $this->assertSame(3, $shown['stats']['total']);
        config(['vff.media.show_non_commercial' => false]);
        $hidden = $stats();
        $this->assertSame(2, $hidden['stats']['total']);
        $this->assertSame(2, $hidden['stats']['photo_count']);
        $this->assertNotContains('CC BY-NC 4.0', array_column($hidden['byLicense'], 'license'));
        $this->assertContains('CC BY-NC 4.0', array_column($shown['byLicense'], 'license'));
    }

    public function test_the_home_media_count_follows_a_change_of_the_setting_without_waiting_for_the_cache(): void
    {
        $this->image();
        $this->image(['commercial_ok' => false]);
        $count = fn () => $this->get('/')->assertOk()->original->getData()['page']['props']['mediaCount'];

        $this->assertSame(2, $count());
        config(['vff.media.show_non_commercial' => false]);
        $this->assertSame(1, $count(), 'le réglage est dans la clé du cache');
        config(['vff.media.show_non_commercial' => true]);
        $this->assertSame(2, $count());
    }

    public function test_the_home_reserve_leaves_out_non_commercial_images(): void
    {
        $ok = $this->image();
        $nc = $this->image(['commercial_ok' => false]);
        $ids = fn () => array_column(app(HomeArchives::class)->pool(), 'id');

        $this->assertEqualsCanonicalizing([$ok->id, $nc->id], $ids());
        config(['vff.media.show_non_commercial' => false]);
        $this->assertSame([$ok->id], $ids());
        config(['vff.media.show_non_commercial' => true]);
        $this->assertEqualsCanonicalizing([$ok->id, $nc->id], $ids());
    }

    public function test_the_commons_relay_list_leaves_out_hidden_media(): void
    {
        $this->image();
        $this->image(['commercial_ok' => false]);
        config(['commons_proxy.enabled' => true]);
        $list = app(CommonsAllowlist::class);

        $this->artisan('vff:commons-allowlist', ['--refresh' => true])->assertSuccessful();
        $this->assertTrue($list->contains('Rights_1.jpg'));
        $this->assertTrue($list->contains('Rights_2.jpg'));

        config(['vff.media.show_non_commercial' => false]);
        $this->artisan('vff:commons-allowlist', ['--refresh' => true])->assertSuccessful();
        $this->assertTrue($list->contains('Rights_1.jpg'));
        $this->assertFalse($list->contains('Rights_2.jpg'), 'média NC masqué : jamais relayé');
    }

    public function test_only_a_no_derivatives_licence_forbids_cropping(): void
    {
        $this->assertTrue($this->image(['derivatives_ok' => true])->isCroppable());
        $this->assertTrue($this->image(['derivatives_ok' => null])->isCroppable(), 'inconnu : recadrage permis, comme l\'affichage');
        $this->assertFalse($this->image(['derivatives_ok' => false])->isCroppable());
    }

    public function test_settings_default_to_showing_everything_and_read_the_config(): void
    {
        $this->assertTrue(Media::showsNonCommercial());
        $this->assertTrue(Media::showsUncertain());
        $this->assertSame('TRUE', Media::displayableSql('m'));
        config(['vff.media.show_non_commercial' => false]);
        $this->assertSame('m.commercial_ok IS DISTINCT FROM FALSE', Media::displayableSql('m'));
        config(['vff.media.show_non_commercial' => 0, 'vff.media.show_uncertain' => '0']);
        $this->assertFalse(Media::showsNonCommercial());
        $this->assertFalse(Media::showsUncertain());
    }
}

<?php

namespace Tests\Feature\Media;

use App\Http\Controllers\CommonsImageController;
use App\Models\Accident;
use App\Models\Material;
use App\Models\Media;
use App\Models\User;
use App\Services\Media\CommonsAllowlist;
use App\Services\Media\CommonsFileResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Le relais des vignettes de Commons ne sert que les fichiers que VFF cite (CommonsAllowlist, reconstruite
 * par vff:commons-allowlist --refresh) : un nom de bonne forme mais non cité est refusé sans requête sortante.
 */
class CommonsAllowlistTest extends TestCase
{
    use RefreshDatabase;

    private string $cacheDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cacheDir = sys_get_temp_dir().'/vff-commons-allow-'.bin2hex(random_bytes(6));
        $dir = $this->cacheDir;
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($dir));
        config([
            'commons_proxy.enabled' => true,
            'commons_proxy.cache_path' => $dir,
            'commons_proxy.accel_redirect' => '',
        ]);
        Cache::flush();
        Http::preventStrayRequests();
    }

    /** Adresse de vignette valide (le début du MD5 du nom fait le dossier, comme sur Commons). */
    private static function thumb(string $file, int $width = 500, string $host = 'thumb'): string
    {
        $md5 = md5($file);

        return "https://{$host}.wikimedia.org/wikipedia/commons/thumb/{$md5[0]}/".substr($md5, 0, 2).'/'.rawurlencode($file)."/{$width}px-".rawurlencode($file);
    }

    private static function relayPath(string $file, int $width = 500): string
    {
        return CommonsImageController::PREFIX.substr(self::thumb($file, $width), strlen('https://thumb.wikimedia.org/wikipedia/commons/'));
    }

    private static function jpeg(): string
    {
        $image = imagecreatetruecolor(500, 300);
        ob_start();
        imagejpeg($image);

        return (string) ob_get_clean();
    }

    public function test_cited_file_is_served_and_other_well_formed_file_is_refused_without_outgoing_request(): void
    {
        Media::factory()->create(['thumb_url' => self::thumb('Gare_cited.jpg'), 'display_url' => self::thumb('Gare_cited.jpg', 1280)]);
        $this->artisan('vff:commons-allowlist', ['--refresh' => true])->assertSuccessful();

        Http::fake(['https://upload.wikimedia.org/*' => Http::response(self::jpeg(), 200, ['Content-Type' => 'image/jpeg'])]);
        $this->get(self::relayPath('Gare_cited.jpg'))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        // Une autre largeur du même fichier est servie aussi (la clé est le fichier).
        $this->get(self::relayPath('Gare_cited.jpg', 1280))->assertOk();
        Http::assertSentCount(2);

        Http::fake();
        $this->get(self::relayPath('Gare_not_cited.jpg'))->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_refused_file_is_not_looked_up_in_the_disk_cache_nor_on_commons(): void
    {
        $this->artisan('vff:commons-allowlist', ['--refresh' => true, '--dry-run' => true])->assertSuccessful();
        Media::factory()->create(['thumb_url' => self::thumb('Un_fichier.jpg'), 'display_url' => self::thumb('Un_fichier.jpg', 1280)]);
        $this->artisan('vff:commons-allowlist', ['--refresh' => true])->assertSuccessful();

        $this->get(self::relayPath('Autre_fichier.jpg'))->assertNotFound()->assertHeader('Cache-Control', 'max-age=300, public');
        Http::assertNothingSent();
        $this->assertDirectoryDoesNotExist($this->cacheDir);
    }

    public function test_list_never_built_answers_503_and_not_a_cacheable_404(): void
    {
        $this->get(self::relayPath('Gare_cited.jpg'))->assertStatus(503)->assertHeader('Retry-After', '60')->assertHeader('Cache-Control', 'no-store, private');
        Http::assertNothingSent();
    }

    public function test_list_is_rebuilt_after_an_image_is_added(): void
    {
        Media::factory()->create(['thumb_url' => self::thumb('Premiere.jpg'), 'display_url' => self::thumb('Premiere.jpg', 1280)]);
        $this->artisan('vff:commons-allowlist', ['--refresh' => true])->assertSuccessful();
        $allowlist = app(CommonsAllowlist::class);
        $this->assertTrue($allowlist->contains('Premiere.jpg'));
        $this->assertFalse($allowlist->contains('Seconde.jpg'));
        $before = $allowlist->meta()['count'];

        Media::factory()->create(['thumb_url' => self::thumb('Seconde.jpg'), 'display_url' => self::thumb('Seconde.jpg', 1280)]);
        $this->assertFalse($allowlist->contains('Seconde.jpg'));
        $this->artisan('vff:commons-allowlist', ['--refresh' => true])->assertSuccessful();

        $this->assertTrue($allowlist->contains('Seconde.jpg'));
        $this->assertTrue($allowlist->contains('Premiere.jpg'));
        $this->assertSame($before + 1, $allowlist->meta()['count']);
    }

    public function test_every_source_feeds_the_list(): void
    {
        Media::factory()->create(['thumb_url' => self::thumb('Media.jpg'), 'display_url' => self::thumb('Media.jpg', 1280)]);
        Material::factory()->create(['image' => ['thumb_url' => self::thumb('Materiel.jpg'), 'display_url' => self::thumb('Materiel.jpg', 1280)]]);
        $accident = Accident::factory()->create();
        DB::table('accident_images')->insert([
            'accident_id' => $accident->id, 'position' => 1, 'file' => 'Accident.jpg', 'page_url' => 'https://commons.wikimedia.org/wiki/File:Accident.jpg',
            'image_url' => self::thumb('Accident.jpg', 1280, 'upload'), 'thumb_url' => self::thumb('Accident.jpg'), 'author' => 'A', 'licence' => 'CC0',
            'caption' => json_encode(['fr' => 'c']), 'retrieved_at' => now(),
        ]);
        DB::table('contributions')->insert([
            'user_id' => User::factory()->create()->id, 'type' => 'media', 'action' => 'create', 'status' => 'pending',
            'payload' => json_encode(['commons' => ['thumb_url' => self::thumb('Propose.jpg')]]), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $meta = app(CommonsAllowlist::class)->rebuild();

        $allowlist = app(CommonsAllowlist::class);
        $this->assertTrue($allowlist->contains('Media.jpg'));
        $this->assertTrue($allowlist->contains('Materiel.jpg'));
        $this->assertTrue($allowlist->contains('Accident.jpg'));
        $this->assertTrue($allowlist->contains('Propose.jpg'), 'proposition en attente');
        $this->assertTrue($allowlist->contains('Morez_viaduc_des_Crottes01_2014-09-06.jpg'), 'image du journal');
        $this->assertGreaterThanOrEqual(5, $meta['count']);
        $this->assertSame(['media', 'accident_images', 'materials', 'station_heritage', 'journal', 'contributions'], array_keys($meta['sources']));
    }

    public function test_dry_run_counts_without_writing_the_list(): void
    {
        Media::factory()->create(['thumb_url' => self::thumb('Compte.jpg'), 'display_url' => self::thumb('Compte.jpg', 1280)]);

        $this->artisan('vff:commons-allowlist', ['--refresh' => true, '--dry-run' => true])->expectsOutputToContain('rien écrit')->assertSuccessful();

        $this->assertFalse(app(CommonsAllowlist::class)->isBuilt());
        $this->assertFalse(app(CommonsAllowlist::class)->contains('Compte.jpg'));
    }

    public function test_status_without_option_reports_the_list(): void
    {
        $this->artisan('vff:commons-allowlist')->expectsOutputToContain('jamais construite')->assertSuccessful();
        app(CommonsAllowlist::class)->rebuild();
        $this->artisan('vff:commons-allowlist')->expectsOutputToContain('fichier(s), construite le')->assertSuccessful();
    }

    public function test_file_resolved_for_a_contribution_preview_is_allowed_at_once(): void
    {
        app(CommonsAllowlist::class)->rebuild();
        $this->assertFalse(app(CommonsAllowlist::class)->contains('Apercu.jpg'));
        Cache::put('commons-file:'.sha1('File:Apercu.jpg'), [
            'thumb_url' => self::thumb('Apercu.jpg'), 'display_url' => self::thumb('Apercu.jpg', 1280),
        ], 3600);

        (new CommonsFileResolver)->lookup('File:Apercu.jpg');

        $this->assertTrue(app(CommonsAllowlist::class)->contains('Apercu.jpg'));
    }

    public function test_lookup_allows_nothing_while_the_relay_is_disabled(): void
    {
        config(['commons_proxy.enabled' => false]);
        app(CommonsAllowlist::class)->rebuild();
        Cache::put('commons-file:'.sha1('File:Apercu.jpg'), ['thumb_url' => self::thumb('Apercu.jpg')], 3600);

        (new CommonsFileResolver)->lookup('File:Apercu.jpg');

        $this->assertFalse(app(CommonsAllowlist::class)->contains('Apercu.jpg'));
    }

    public function test_names_use_underscores_and_unrelated_urls_are_ignored(): void
    {
        Media::factory()->create(['thumb_url' => self::thumb('Avec espace.jpg'), 'display_url' => 'https://example.org/x.jpg']);
        app(CommonsAllowlist::class)->rebuild();

        $allowlist = app(CommonsAllowlist::class);
        $this->assertTrue($allowlist->contains('Avec_espace.jpg'));
        $this->assertFalse($allowlist->contains(''));
        $this->assertFalse($allowlist->contains('x.jpg'));
    }
}

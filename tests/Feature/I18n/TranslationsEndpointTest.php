<?php

namespace Tests\Feature\I18n;

use App\Http\Controllers\TranslationsController;
use App\Support\Translations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Dictionnaire de l'interface servi à part (GET /i18n/{locale}/{empreinte}.json) : même contenu que les
 * chaînes autrefois recopiées dans chaque page (Translations::load), adresse à empreinte gardée un an,
 * réponse identique pour tous (ni session ni cookie), compressée à la demande.
 */
class TranslationsEndpointTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string}> */
    public static function locales(): array
    {
        return ['fr' => ['fr'], 'en' => ['en'], 'de' => ['de']];
    }

    #[DataProvider('locales')]
    public function test_the_dictionary_is_exactly_the_former_prop(string $locale): void
    {
        $url = $this->withCookie('locale', $locale)->get('/donate')->viewData('page')['props']['translations_url'];
        $this->assertSame(Translations::url($locale), $url);

        $response = $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/json; charset=utf-8');

        // Mêmes clés, mêmes textes, même ordre que l'ancienne prop `translations`.
        $this->assertSame(Translations::load($locale), json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR));
        $this->assertSame(Translations::payload($locale)['json'], $response->getContent());
    }

    public function test_the_right_fingerprint_is_kept_a_year_and_shared_by_everyone(): void
    {
        $response = $this->get(Translations::url('en'))->assertOk();

        $this->assertStringContainsString('max-age='.TranslationsController::MAX_AGE, (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('immutable', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('public', (string) $response->headers->get('Cache-Control'));
        $this->assertSame([], $response->headers->getCookies(), 'ni session ni cookie : réponse commune');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_an_old_fingerprint_gets_the_current_dictionary_without_long_cache(): void
    {
        $response = $this->get('/i18n/de/000000000000.json')->assertOk();

        $this->assertSame(Translations::payload('de')['json'], $response->getContent());
        $this->assertSame('no-cache, private', $response->headers->get('Cache-Control'));
    }

    public function test_gzip_body_is_the_same_dictionary_and_etag_revalidates(): void
    {
        $url = Translations::url('fr');
        $gz = $this->get($url, ['Accept-Encoding' => 'gzip, br'])->assertOk()->assertHeader('Content-Encoding', 'gzip');
        $this->assertSame(Translations::payload('fr')['json'], gzdecode((string) $gz->getContent()));

        $plain = $this->get($url)->assertOk();
        $this->assertNotSame($gz->headers->get('ETag'), $plain->headers->get('ETag'));

        $this->get($url, ['If-None-Match' => (string) $plain->headers->get('ETag')])->assertStatus(304);
    }

    public function test_unknown_locale_or_malformed_fingerprint_is_404(): void
    {
        $this->get('/i18n/xx/0123456789ab.json')->assertNotFound();
        $this->get('/i18n/fr/nimportequoi.json')->assertNotFound();
    }

    public function test_the_fingerprint_follows_the_content(): void
    {
        $lang = storage_path('framework/testing/lang-endpoint-'.getmypid());
        try {
            File::ensureDirectoryExists($lang.'/fr');
            File::put($lang.'/fr/nav.php', "<?php return ['maps' => 'Cartes'];");
            $this->app->useLangPath($lang);
            config(['app.debug' => false]);
            Cache::flush();
            $before = Translations::url('fr');

            File::put($lang.'/fr/nav.php', "<?php return ['maps' => 'Cartes et plans'];");
            Cache::flush();
            $after = Translations::url('fr');

            $this->assertNotSame($before, $after);
            $this->assertSame(['nav.maps' => 'Cartes et plans'], $this->get($after)->assertOk()->json());
        } finally {
            File::deleteDirectory($lang);
        }
    }
}

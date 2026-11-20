<?php

namespace Tests\Feature\Web;

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Pages d'erreur du site : page Inertia « Error » (en-tête et pied de page du site), code HTTP
 * conservé, langue du visiteur, JSON laissé aux clients de l'API, page de secours sans dépendance.
 */
class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Mode production : pas de page de débogage pour les erreurs de code.
        config(['app.debug' => false]);

        // Comme PublicPagesTest : sans en-tête Accept-Language, le client de test n'a pas de langue.
        $this->withHeader('Accept-Language', 'fr-FR,fr;q=0.9');

        Route::middleware('web')->group(function (): void {
            Route::get('/_test/abort/{code}', fn (int $code) => abort($code))->whereNumber('code');
            Route::get('/_test/forbidden-detail', fn () => abort(403, 'Réservé aux validateurs.'));
            Route::get('/_test/expired', fn () => throw new TokenMismatchException('CSRF token mismatch.'));
            Route::get('/_test/throttled', fn () => throw new ThrottleRequestsException('Too Many Attempts.', null, ['Retry-After' => 42]));
            Route::get('/_test/boom', fn () => throw new \RuntimeException('secret interne'));
        });
    }

    /** @return array<int|string, array{0: string, 1: int}> */
    public static function errorRoutes(): array
    {
        return [
            '403' => ['/_test/abort/403', 403],
            '404 URL inconnue' => ['/page-inexistante', 404],
            '404 fiche inconnue' => ['/lines/ligne-qui-nexiste-pas', 404],
            '419 session expirée' => ['/_test/expired', 419],
            '429 limite de débit' => ['/_test/throttled', 429],
            '500 erreur de code' => ['/_test/boom', 500],
            '503 maintenance' => ['/_test/abort/503', 503],
        ];
    }

    #[DataProvider('errorRoutes')]
    public function test_error_is_a_site_page_with_the_right_http_status(string $uri, int $status): void
    {
        $response = $this->get($uri);

        $response->assertStatus($status)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Error')
                ->where('status', $status)
                ->where('locale', 'fr')
                ->has('translations'));
        $this->assertStringNotContainsString('secret interne', $response->getContent());
    }

    public function test_method_not_allowed_is_a_site_page_and_keeps_the_allow_header(): void
    {
        $response = $this->post('/api');

        $response->assertStatus(405)
            ->assertHeader('Allow')
            ->assertInertia(fn (Assert $page) => $page->component('Error')->where('status', 405));
    }

    public function test_throttle_error_exposes_the_wait_time(): void
    {
        $this->get('/_test/throttled')
            ->assertStatus(429)
            ->assertHeader('Retry-After', '42')
            ->assertInertia(fn (Assert $page) => $page->where('retry_after', 42));
    }

    public function test_forbidden_detail_is_shown_but_the_framework_default_is_not(): void
    {
        $this->get('/_test/forbidden-detail')
            ->assertForbidden()
            ->assertInertia(fn (Assert $page) => $page->where('detail', 'Réservé aux validateurs.'));

        $this->get('/_test/abort/403')
            ->assertForbidden()
            ->assertInertia(fn (Assert $page) => $page->where('detail', null));
    }

    /** @return array<string, array{0: string}> */
    public static function languages(): array
    {
        return ['en' => ['en'], 'de' => ['de'], 'fr' => ['fr']];
    }

    #[DataProvider('languages')]
    public function test_unknown_url_follows_the_language_of_the_visitor(string $language): void
    {
        $this->withHeader('Accept-Language', $language)
            ->get('/page-inexistante')
            ->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page
                ->where('locale', $language)
                ->where('translations', fn ($translations) => ($translations['errors.404.title'] ?? '') !== ''));
    }

    public function test_expired_session_follows_the_language_cookie(): void
    {
        $this->withCookie('locale', 'de')
            ->get('/_test/expired')
            ->assertStatus(419)
            ->assertInertia(fn (Assert $page) => $page->where('locale', 'de'));
    }

    public function test_inertia_visit_gets_the_error_page_as_an_ordinary_inertia_response(): void
    {
        $response = $this->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(Request::create('/')),
            'Accept' => 'text/html, application/xhtml+xml',
        ])->get('/_test/abort/403');

        $response->assertForbidden()->assertHeader('X-Inertia', 'true');
        $this->assertSame('Error', $response->json('component'));
        $this->assertSame(403, $response->json('props.status'));
    }

    public function test_json_clients_keep_a_json_error(): void
    {
        $this->getJson('/page-inexistante')->assertNotFound()->assertJsonStructure(['message']);
        $this->getJson('/api/v1/inexistant')->assertNotFound()->assertJsonStructure(['message']);
        $this->get('/api/v1/inexistant')->assertNotFound()->assertHeaderMissing('X-Inertia');
        $this->getJson('/api/v1/lines/ligne-inconnue/pk?lat=48&lng=-1')->assertNotFound();
    }

    public function test_pages_without_a_dedicated_view_use_the_standalone_fallback(): void
    {
        $html = $this->withHeader('Accept-Language', 'en')->get('/_test/abort/401')->assertStatus(401)->getContent();

        $this->assertStringContainsString('Something went wrong', $html);
        $this->assertStringContainsString('Error 401', $html);
        $this->assertStringContainsString('<html lang="en">', $html);
        $this->assertStringNotContainsString('data-page=', $html);
    }

    #[DataProvider('languages')]
    public function test_fallback_view_is_translated_and_standalone(string $language): void
    {
        app()->setLocale($language);

        foreach ([404, 419, 500, 503] as $status) {
            $html = view('errors.fallback', ['status' => $status, 'detail' => null])->render();

            $this->assertStringContainsString(__("errors.$status.title"), $html);
            $this->assertStringNotContainsString('<script src', $html);
            $this->assertStringNotContainsString('http://', $html);
            $this->assertStringNotContainsString('https://', $html);
        }
    }

    public function test_every_error_text_exists_in_the_three_languages(): void
    {
        $reference = array_keys(trans('errors', [], 'fr'));

        foreach (['fr', 'en', 'de'] as $locale) {
            $texts = trans('errors', [], $locale);
            $this->assertSame($reference, array_keys($texts), "errors.php ({$locale}) : clés différentes");
            foreach ([403, 404, 405, 419, 429, 500, 503, 'default'] as $status) {
                $this->assertNotEmpty($texts[$status]['title'] ?? null, "{$locale} {$status} : titre manquant");
                $this->assertNotEmpty($texts[$status]['text'] ?? null, "{$locale} {$status} : texte manquant");
            }
            $this->assertStringNotContainsString('—', (string) file_get_contents(lang_path("{$locale}/errors.php")));
        }
    }
}

<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Gestion des erreurs de bootstrap/app.php : erreurs de code de l'API en JSON sans fuite, 403 et 429 de
 * l'API gardés en JSON avec leurs en-têtes, page de débogage conservée en mode debug, langue du profil.
 */
class ErrorHandlingJsonAndLocaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.debug' => false]);
        $this->withHeader('Accept-Language', 'fr-FR,fr;q=0.9');

        Route::middleware('api')->prefix('api')->group(function (): void {
            Route::get('/_test/boom', fn () => throw new \RuntimeException('secret interne'));
            Route::get('/_test/forbidden', fn () => abort(403, 'Réservé.'));
            Route::get('/_test/throttled', fn () => throw new ThrottleRequestsException('Too Many Attempts.', null, ['Retry-After' => 7]));
        });
        Route::middleware('web')->group(function (): void {
            Route::get('/_test/boom', fn () => throw new \RuntimeException('secret interne'));
            Route::get('/_test/forbidden', fn () => abort(403));
        });
    }

    public function test_a_code_error_of_the_api_is_json_and_leaks_nothing(): void
    {
        $response = $this->get('/api/_test/boom');

        $response->assertStatus(500);
        $this->assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));
        $this->assertStringNotContainsString('secret interne', (string) $response->getContent());
        $this->assertArrayNotHasKey('trace', (array) $response->json());
    }

    public function test_api_403_and_429_stay_json_and_429_keeps_its_wait_time(): void
    {
        $forbidden = $this->get('/api/_test/forbidden');
        $forbidden->assertForbidden()->assertJson(['message' => 'Réservé.']);

        $throttled = $this->get('/api/_test/throttled');
        $throttled->assertStatus(429);
        $this->assertSame('7', $throttled->headers->get('Retry-After'));
        $this->assertStringContainsString('application/json', (string) $throttled->headers->get('Content-Type'));
    }

    public function test_graphql_errors_are_never_turned_into_a_site_page(): void
    {
        $response = $this->get('/graphql');

        $this->assertStringNotContainsString('text/html', (string) $response->headers->get('Content-Type'));
    }

    public function test_debug_mode_keeps_the_debug_page_for_a_code_error_but_not_for_a_missing_page(): void
    {
        config(['app.debug' => true]);

        $boom = $this->get('/_test/boom');
        $boom->assertStatus(500);
        $this->assertStringContainsString('secret interne', (string) $boom->getContent());

        $this->get('/cette-page-n-existe-pas')->assertNotFound()->assertInertia(fn (Assert $page) => $page->component('Error')->where('status', 404));
    }

    public function test_a_signed_in_visitors_profile_language_decides_the_error_page_language(): void
    {
        $user = User::factory()->create(['locale_preference' => 'de']);

        $this->actingAs($user)->withHeader('Accept-Language', 'fr-FR,fr;q=0.9')->get('/_test/forbidden')
            ->assertForbidden()
            ->assertInertia(fn (Assert $page) => $page->component('Error')->where('status', 403)->where('locale', 'de')->where('detail', null));
    }

    public function test_an_unsupported_cookie_language_is_ignored(): void
    {
        $this->withUnencryptedCookie('locale', 'xx')->get('/_test/forbidden')
            ->assertForbidden()
            ->assertInertia(fn (Assert $page) => $page->component('Error')->where('locale', fn ($l) => in_array($l, ['fr', 'en', 'de'], true)));
    }
}

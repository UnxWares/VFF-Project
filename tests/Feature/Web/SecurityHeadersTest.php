<?php

namespace Tests\Feature\Web;

use App\Http\Middleware\AllowIframeEmbed;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\ActsAsRoles;
use Tests\TestCase;

/**
 * En-têtes de sécurité et CSRF.
 *
 * Bug corrigé : AllowIframeEmbed affirmait que « les autres routes restent
 * verrouillées par défaut », mais rien ne posait X-Frame-Options : toutes les
 * pages (connexion, modération et ses boutons « approuver ») étaient
 * intégrables dans un site tiers (clickjacking). SecurityHeaders les pose
 * désormais sur le groupe web, sauf /embed/*.
 */
class SecurityHeadersTest extends TestCase
{
    use ActsAsRoles;
    use RefreshDatabase;

    /** @return array<string, array{0: string}> */
    public static function framedPages(): array
    {
        return [
            'accueil' => ['/'],
            'carte' => ['/maps'],
            'connexion' => ['/login'],
            'contribuer' => ['/contribute'],
            'doc API' => ['/api'],
        ];
    }

    #[DataProvider('framedPages')]
    public function test_regular_pages_cannot_be_framed_by_other_sites(string $uri): void
    {
        $this->get($uri)
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeaderMissing('Content-Security-Policy');
    }

    public function test_moderation_page_cannot_be_framed(): void
    {
        $this->actingAs($this->member('validator'))->get('/validate')
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_redirects_and_errors_also_carry_the_headers(): void
    {
        $this->get('/profile')->assertRedirect('/login')->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $this->get('/lines/inexistante')->assertNotFound()->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_embed_map_is_frameable_everywhere(): void
    {
        $this->get('/embed/maps')
            ->assertOk()
            ->assertHeaderMissing('X-Frame-Options')
            ->assertHeader('Content-Security-Policy', 'frame-ancestors *')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    /** @return array<string, array{0: ?string, 1: string}> */
    public static function existingPolicies(): array
    {
        return [
            'aucune politique' => [null, 'frame-ancestors *'],
            'politique sans frame-ancestors' => ["default-src 'self'", "default-src 'self'; frame-ancestors *"],
            'frame-ancestors restrictif remplacé' => ["default-src 'self'; frame-ancestors 'none'; img-src *", "default-src 'self'; frame-ancestors *; img-src *"],
            'casse ignorée' => ["Frame-Ancestors 'self'", 'frame-ancestors *'],
        ];
    }

    #[DataProvider('existingPolicies')]
    public function test_allow_iframe_embed_rewrites_only_frame_ancestors(?string $csp, string $expected): void
    {
        $middleware = new AllowIframeEmbed;
        $response = $middleware->handle(Request::create('/embed/maps'), function () use ($csp) {
            $r = new Response('ok');
            $r->headers->set('X-Frame-Options', 'DENY');
            if ($csp !== null) {
                $r->headers->set('Content-Security-Policy', $csp);
            }

            return $r;
        });

        $this->assertFalse($response->headers->has('X-Frame-Options'));
        $this->assertSame($expected, $response->headers->get('Content-Security-Policy'));
        $this->assertSame('ok', $response->getContent());
    }

    public function test_csrf_middleware_guards_the_web_group(): void
    {
        $web = app('router')->getMiddlewareGroups()['web'];

        // Laravel 13 : PreventRequestForgery (jeton + en-tête Sec-Fetch-Site).
        $this->assertContains(PreventRequestForgery::class, $web);
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function stateChangingForms(): array
    {
        return [
            'connexion' => ['post', '/login'],
            'inscription' => ['post', '/register'],
            'mot de passe oublié' => ['post', '/forgot-password'],
            'réinitialisation' => ['post', '/reset-password'],
            'déconnexion' => ['post', '/logout'],
            'profil' => ['put', '/profile'],
            'contribution PK' => ['post', '/contribute/edit-segment'],
            'contribution tracé' => ['post', '/contribute/trace'],
            'approbation' => ['post', '/validate/1/approve'],
            'refus' => ['post', '/validate/1/reject'],
        ];
    }

    #[DataProvider('stateChangingForms')]
    public function test_forms_without_a_csrf_token_are_rejected_with_419(string $method, string $uri): void
    {
        $this->enforceCsrf();

        $this->actingAs($this->member('validator'))->{$method}($uri, ['email' => 'a@example.org'])->assertStatus(419);
    }

    public function test_a_valid_csrf_token_is_accepted(): void
    {
        $this->enforceCsrf();
        $user = $this->member(null, ['name' => 'Avant']);

        $this->actingAs($user)->withSession(['_token' => 'jeton-de-session'])
            ->put('/profile', ['_token' => 'jeton-de-session', 'name' => 'Après', 'locale_preference' => 'fr'])
            ->assertSessionHasNoErrors()
            ->assertRedirect();
        $this->assertSame('Après', $user->fresh()->name);

        $this->actingAs($user)->withSession(['_token' => 'jeton-de-session'])
            ->put('/profile', ['_token' => 'autre-jeton', 'name' => 'Pirate', 'locale_preference' => 'fr'])
            ->assertStatus(419);
        $this->assertSame('Après', $user->fresh()->name);
    }

    public function test_read_only_requests_need_no_token(): void
    {
        $this->enforceCsrf();

        $this->get('/login')->assertOk();
        $this->get('/locale/en')->assertRedirect('/');
    }

    public function test_cross_site_form_posts_are_rejected_and_same_origin_ones_accepted(): void
    {
        $this->enforceCsrf();
        $user = $this->member(null, ['name' => 'Avant']);

        $this->actingAs($user)->withHeader('Sec-Fetch-Site', 'cross-site')
            ->put('/profile', ['name' => 'Pirate', 'locale_preference' => 'fr'])
            ->assertStatus(419);
        $this->actingAs($user)->withHeader('Sec-Fetch-Site', 'same-site')
            ->put('/profile', ['name' => 'Pirate', 'locale_preference' => 'fr'])
            ->assertStatus(419);
        $this->assertSame('Avant', $user->fresh()->name);

        $this->actingAs($user)->withHeader('Sec-Fetch-Site', 'same-origin')
            ->put('/profile', ['name' => 'Même origine', 'locale_preference' => 'fr'])
            ->assertRedirect();
        $this->assertSame('Même origine', $user->fresh()->name);
    }

    public function test_csrf_failure_response_also_carries_security_headers(): void
    {
        $this->enforceCsrf();

        $this->post('/login', ['email' => 'a@example.org', 'password' => 'x'])
            ->assertStatus(419)
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    /**
     * PreventRequestForgery laisse tout passer quand l'application tourne sous
     * PHPUnit : on le remplace par une instance qui applique la règle réelle.
     */
    private function enforceCsrf(): void
    {
        $this->app->instance(PreventRequestForgery::class, new class($this->app, $this->app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
    }

    public function test_every_web_route_is_covered_by_the_security_headers(): void
    {
        $this->assertTrue(app(Kernel::class)->hasMiddleware(SecurityHeaders::class));
        $this->assertTrue(Route::has('embed.maps'));
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function responseFamilies(): array
    {
        return [
            'page web' => ['GET', '/'],
            'erreur 404 web' => ['GET', '/lines/inexistante'],
            'REST' => ['GET', '/api/v1/lines'],
            'REST introuvable' => ['GET', '/api/v1/nexiste-pas'],
            'GraphQL' => ['GET', '/graphql'],
            'MCP' => ['GET', '/mcp'],
            'dictionnaire' => ['GET', '/i18n/fr/000000000000.json'],
            'fond de carte' => ['GET', '/basemap/inexistant.pmtiles'],
            'tracé de ligne' => ['GET', '/lines/inexistante/portions/abcdef.json'],
        ];
    }

    #[DataProvider('responseFamilies')]
    public function test_every_response_family_carries_the_baseline_headers(string $method, string $uri): void
    {
        $response = $this->call($method, $uri);

        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'), $uri);
        $this->assertSame('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'), $uri);
        $this->assertSame(SecurityHeaders::PERMISSIONS_POLICY, $response->headers->get('Permissions-Policy'), $uri);
        $this->assertSame('SAMEORIGIN', $response->headers->get('X-Frame-Options'), $uri);
    }

    public function test_hsts_is_sent_over_https_and_in_production_only(): void
    {
        $this->get('/')->assertHeaderMissing('Strict-Transport-Security');
        $this->get('https://localhost/api/v1/lines')->assertHeader('Strict-Transport-Security', SecurityHeaders::HSTS);

        $this->app['env'] = 'production';
        $this->get('/')->assertHeader('Strict-Transport-Security', SecurityHeaders::HSTS);
    }

    public function test_the_permissions_policy_keeps_geolocation_for_the_map_only(): void
    {
        $policy = SecurityHeaders::PERMISSIONS_POLICY;
        $this->assertStringContainsString('geolocation=(self)', $policy);
        $this->assertStringContainsString('camera=()', $policy);
        $this->assertStringContainsString('microphone=()', $policy);
    }

    public function test_session_and_csrf_cookies_are_http_only_same_site_and_secure_in_production(): void
    {
        $cookies = collect($this->get('/login')->headers->getCookies())->keyBy(fn ($c) => $c->getName());
        $this->assertTrue($cookies[config('session.cookie')]->isHttpOnly());
        $this->assertSame('lax', $cookies[config('session.cookie')]->getSameSite());
        $this->assertSame('lax', $cookies['XSRF-TOKEN']->getSameSite());

        $config = file_get_contents(base_path('config/session.php'));
        $this->assertStringContainsString("env('APP_ENV') === 'production'", (string) $config);
    }
}

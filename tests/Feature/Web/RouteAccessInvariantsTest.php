<?php

namespace Tests\Feature\Web;

use App\Models\Contribution;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ActsAsRoles;
use Tests\TestCase;

/**
 * Invariants d'accès, vérifiés sur TOUTES les routes déclarées (une nouvelle route est contrôlée
 * sans qu'on pense à lui écrire un test) :
 *   - toute route web qui écrit (POST, PUT, PATCH, DELETE) exige une connexion, sauf les formulaires
 *     réservés aux visiteurs non connectés (connexion, inscription, mot de passe) et les formulaires
 *     publics déclarés ici, qui doivent alors être limités en fréquence (throttle) ;
 *   - un visiteur non connecté est renvoyé vers la connexion (302, ou 401 en JSON) sur toute route
 *     qui exige une connexion, jamais une page ou une erreur ;
 *   - la validation des contributions est réservée aux validateurs et aux administrateurs.
 */
class RouteAccessInvariantsTest extends TestCase
{
    use ActsAsRoles;
    use RefreshDatabase;

    /** Routes d'écriture hors de la connexion : service MCP et GraphQL ont leur propre authentification. */
    private const OWN_AUTHENTICATION_PREFIXES = ['mcp', 'graphql'];

    /**
     * Écritures publiques voulues, avec limite de débit obligatoire : signalement d'une erreur depuis une
     * fiche, rapports de violation de la CSP envoyés par les navigateurs (CspReportController).
     */
    private const PUBLIC_THROTTLED_WRITES = ['POST /reports', 'POST /api/csp-report'];

    /** @return list<RouteDefinition> */
    private function routes(): array
    {
        return array_values(array_filter(
            Route::getRoutes()->getRoutes(),
            fn (RouteDefinition $r) => ! str_starts_with($r->uri(), '_') && ! str_starts_with($r->uri(), 'up'),
        ));
    }

    /** @param  list<string>  $classes */
    private function hasMiddleware(RouteDefinition $route, array $classes): bool
    {
        foreach ($route->gatherMiddleware() as $middleware) {
            $name = is_string($middleware) ? strtok($middleware, ':') : null;
            if ($name !== null && in_array($name, $classes, true)) {
                return true;
            }
        }

        return false;
    }

    /** URI d'une route avec des valeurs qui respectent ses contraintes (whereIn « a|b », nombre, sinon « 1 »). */
    private function concreteUri(RouteDefinition $route): string
    {
        $uri = (string) preg_replace_callback('/\{(\w+)\??(?::\w+)?\}/', function (array $m) use ($route): string {
            $where = $route->wheres[$m[1]] ?? null;

            return is_string($where) && preg_match('/^[\w|-]+$/', $where) && ! ctype_digit(str_replace('|', '', $where))
                ? explode('|', $where)[0]
                : '1';
        }, $route->uri());

        return '/'.ltrim($uri, '/');
    }

    private function isWrite(RouteDefinition $route): bool
    {
        return (bool) array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']);
    }

    private function ownsAuthentication(RouteDefinition $route): bool
    {
        foreach (self::OWN_AUTHENTICATION_PREFIXES as $prefix) {
            if ($route->uri() === $prefix || str_starts_with($route->uri(), $prefix.'/')) {
                return true;
            }
        }

        return false;
    }

    public function test_every_writing_web_route_requires_a_login_or_is_a_guest_only_form(): void
    {
        $open = [];
        $checked = 0;
        foreach ($this->routes() as $route) {
            if (! $this->isWrite($route) || $this->ownsAuthentication($route)) {
                continue;
            }
            $checked++;
            $signature = implode('|', array_diff($route->methods(), ['HEAD'])).' /'.$route->uri();
            if (in_array($signature, self::PUBLIC_THROTTLED_WRITES, true)) {
                $this->assertTrue($this->hasMiddleware($route, ['throttle']), "{$signature} doit être limitée en fréquence");

                continue;
            }
            if (! $this->hasMiddleware($route, [Authenticate::class, RedirectIfAuthenticated::class, 'auth', 'guest'])) {
                $open[] = $signature;
            }
        }

        $this->assertGreaterThan(5, $checked, 'des routes d\'écriture doivent être trouvées');
        $this->assertSame([], $open, "Routes d'écriture ouvertes à tous :\n".implode("\n", $open));
    }

    public function test_guest_only_forms_are_the_authentication_ones(): void
    {
        $guestOnly = [];
        foreach ($this->routes() as $route) {
            if ($this->isWrite($route) && $this->hasMiddleware($route, [RedirectIfAuthenticated::class, 'guest'])) {
                $guestOnly[] = $route->uri();
            }
        }
        sort($guestOnly);

        $this->assertSame(['forgot-password', 'login', 'register', 'reset-password'], $guestOnly);
    }

    public function test_a_guest_is_turned_away_from_every_protected_route(): void
    {
        $failures = [];
        $checked = 0;
        foreach ($this->routes() as $route) {
            if (! $this->hasMiddleware($route, [Authenticate::class, 'auth'])) {
                continue;
            }
            $checked++;
            $method = in_array('GET', $route->methods(), true) ? 'GET' : $route->methods()[0];
            $uri = $this->concreteUri($route);

            $web = $this->call($method, $uri);
            if (! str_starts_with((string) $web->headers->get('Location'), url('/login')) || $web->getStatusCode() !== 302) {
                $failures[] = "{$method} {$uri} : un visiteur reçoit {$web->getStatusCode()} au lieu d'une redirection vers /login";
            }
            $json = $this->json($method, $uri);
            if ($json->getStatusCode() !== 401) {
                $failures[] = "{$method} {$uri} : en JSON, un visiteur reçoit {$json->getStatusCode()} au lieu de 401";
            }
        }

        $this->assertGreaterThan(10, $checked, 'des routes protégées doivent être trouvées');
        $this->assertSame([], $failures, implode("\n", $failures));
    }

    /** @return array<string, array{string, string}> */
    public static function validationRoutes(): array
    {
        return [
            'liste' => ['GET', '/validate'],
            'approbation' => ['POST', '/validate/{id}/approve'],
            'rejet' => ['POST', '/validate/{id}/reject'],
        ];
    }

    #[DataProvider('validationRoutes')]
    public function test_validation_is_forbidden_to_visitors_and_contributors(string $method, string $uri): void
    {
        $contribution = Contribution::factory()->create();
        $uri = str_replace('{id}', (string) $contribution->id, $uri);

        foreach (['visitor', 'contributor'] as $role) {
            $this->actingAs($this->member($role))->call($method, $uri)->assertForbidden();
        }
        $this->assertDatabaseHas('contributions', ['id' => $contribution->id, 'status' => 'pending']);
    }

    public function test_the_validation_list_opens_to_validators_and_admins(): void
    {
        foreach (['validator', 'admin'] as $role) {
            $this->actingAs($this->member($role))->get('/validate')->assertOk();
        }
    }
}

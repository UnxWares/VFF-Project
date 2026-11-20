<?php

namespace Tests\Unit\Static;

use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Les routes et pages référencées dans le code existent (lecture statique des
 * sources ; l'application démarre pour charger les routes, sans base) :
 *
 *   - routes nommées des contrôleurs, des fichiers de routes et des vues
 *     (`route('x')`, `redirect()->route('x')`, `to_route('x')`) ;
 *   - pages Inertia (`Inertia::render('Lines/Show')` → resources/js/Pages/Lines/Show.svelte) ;
 *   - URL internes du front (`href="/lines/{slug}"`, `router.post('/logout')`,
 *     `fetch(`/api/v1/route?…`)`, `form.put('/profile')`), méthode HTTP comprise
 *     quand l'appel la fixe.
 */
class RoutesUsageTest extends TestCase
{
    /** Chemins servis hors routeur Laravel (fichiers publics, build Vite). */
    private const STATIC_PREFIXES = ['//', '/build/', '/storage/', '/images/', '/fonts/', '/favicon', '/vendor/'];

    public function test_named_routes_used_in_php_and_views_exist(): void
    {
        $missing = [];
        $files = [
            ...SourceText::files('app', ['.php']),
            ...SourceText::files('routes', ['.php']),
            ...SourceText::files('resources/views', ['.php']),
        ];
        // `route('x')` mais pas `$request->route('param')` (paramètre de la route courante).
        $pattern = '/(?:(?<![\w>:$])route|\bto_route|redirect\(\)\s*->\s*route|\b(?:Redirect|URL)::route)\(\s*[\'"]([\w.-]+)[\'"]/';

        foreach ($files as $file) {
            $source = SourceText::read($file);
            preg_match_all($pattern, $source, $matches, PREG_OFFSET_CAPTURE);
            foreach ($matches[1] as [$name, $offset]) {
                if (! Route::has($name)) {
                    $missing[] = "{$file}:".SourceText::lineOf($source, $offset)." : route « {$name} » inconnue";
                }
            }
        }

        $this->assertSame([], $missing, implode("\n", $missing));
    }

    public function test_inertia_pages_exist(): void
    {
        $missing = [];
        foreach ([...SourceText::files('app', ['.php']), ...SourceText::files('routes', ['.php'])] as $file) {
            $source = SourceText::read($file);
            preg_match_all('/(?:Inertia::render|\binertia)\(\s*[\'"]([\w\/-]+)[\'"]/', $source, $matches, PREG_OFFSET_CAPTURE);
            foreach ($matches[1] as [$page, $offset]) {
                if (! is_file(resource_path("js/Pages/{$page}.svelte"))) {
                    $missing[] = "{$file}:".SourceText::lineOf($source, $offset)." : page Inertia « {$page} » sans resources/js/Pages/{$page}.svelte";
                }
            }
        }

        $this->assertSame([], $missing, implode("\n", $missing));
    }

    public function test_front_internal_urls_match_a_route(): void
    {
        $routes = array_map(
            fn (RouteDefinition $r) => ['regex' => $this->uriRegex($r->uri()), 'methods' => $r->methods()],
            Route::getRoutes()->getRoutes(),
        );

        $unknown = [];
        foreach (SourceText::files('resources/js', ['.svelte', '.js']) as $file) {
            $raw = SourceText::read($file);
            $code = str_ends_with($file, '.svelte') ? SourceText::svelte($raw) : SourceText::javascript($raw);

            foreach ($this->urls($code) as [$method, $url, $offset]) {
                $path = $this->normalize($url);
                if ($path === null) {
                    continue;
                }
                $ok = false;
                foreach ($routes as $route) {
                    if (preg_match($route['regex'], $path) && ($method === null || in_array($method, $route['methods'], true))) {
                        $ok = true;
                        break;
                    }
                }
                if (! $ok) {
                    $unknown[] = "{$file}:".SourceText::lineOf($code, $offset).' : '.($method ?? 'ANY')." {$url} ne correspond à aucune route";
                }
            }
        }

        $this->assertSame([], $unknown, implode("\n", $unknown));
    }

    /**
     * URL littérales commençant par « / » et méthode HTTP (null = indéterminée).
     *
     * @return list<array{0: ?string, 1: string, 2: int}>
     */
    private function urls(string $code): array
    {
        $string = '(?:"(\/[^"]*)"|\'(\/[^\']*)\'|`(\/[^`]*)`)';
        $patterns = [
            // Liens : href="/x", href={`/x/${y}`}, action="/x"
            '/\b(?:href|action)=\{?\s*'.$string.'/' => 'GET',
            '/\brouter\.(get|visit|post|put|patch|delete)\(\s*'.$string.'/' => 1,
            '/\b\$?form\.(get|post|put|patch|delete)\(\s*'.$string.'/' => 1,
            '/\baxios\.(get|post|put|patch|delete)\(\s*'.$string.'/' => 1,
            '/\bfetch\(\s*'.$string.'/' => null,
            // Documentation de l'API (ApiDocs.svelte) : path: '/api/v1/…'
            '/\bpath:\s*'.$string.'/' => 'GET',
        ];

        $found = [];
        foreach ($patterns as $pattern => $method) {
            preg_match_all($pattern, $code, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL);
            foreach ($matches as $m) {
                $verb = $method === 1 ? strtoupper($m[1][0]) : $method;
                $verb = $verb === 'VISIT' ? 'GET' : $verb;
                $url = array_values(array_filter(array_slice($m, $method === 1 ? 2 : 1), fn ($g) => $g[0] !== null))[0] ?? null;
                if ($url !== null) {
                    $found[] = [$verb, $url[0], $url[1]];
                }
            }
        }

        return $found;
    }

    /** Chemin comparable aux routes : expressions → « 1 », sans requête ni ancre. */
    private function normalize(string $url): ?string
    {
        foreach (self::STATIC_PREFIXES as $prefix) {
            if (str_starts_with($url, $prefix)) {
                return null;
            }
        }
        // `${expr}` (gabarit JS), `{expr}` (Svelte) et `{param}` (doc de l'API) : valeur quelconque.
        do {
            $url = (string) preg_replace('/\$?\{[^{}]*\}/', '1', $url, -1, $count);
        } while ($count > 0);
        $url = (string) preg_replace('/[?#].*$/s', '', $url);

        return $url === '/' ? '/' : rtrim($url, '/');
    }

    private function uriRegex(string $uri): string
    {
        $uri = '/'.ltrim($uri, '/');
        $parts = preg_split('/(\{[^}]+\})/', $uri, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $regex = '';
        foreach ($parts as $part) {
            if (! str_starts_with($part, '{')) {
                $regex .= preg_quote($part, '#');
            } elseif (str_ends_with($part, '?}') && str_ends_with($regex, '/')) {
                // Paramètre optionnel : « /x/{y?} » accepte aussi « /x ».
                $regex = substr($regex, 0, -1).'(?:/[^/]+)?';
            } else {
                $regex .= '[^/]+';
            }
        }

        return '#^'.$regex.'$#';
    }
}

<?php

namespace Tests\Feature\Security;

use App\Support\Security\ContentSecurityPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Politique de sécurité du contenu (décision du 20/11/2026) : en observation (Report-Only) sur tout
 * le site, nonce par requête pour les scripts, exceptions de la carte, des images de Wikimedia, de
 * Magidoc, de Vite en développement et des pages intégrables ; rapports reçus sans donnée
 * personnelle, avec une limite de débit.
 */
class ContentSecurityPolicyTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, string> directive => valeurs */
    private static function directives(string $header): array
    {
        $out = [];
        foreach (explode(';', $header) as $part) {
            $words = preg_split('/\s+/', trim($part)) ?: [];
            if ($words !== [] && $words[0] !== '') {
                $out[array_shift($words)] = implode(' ', $words);
            }
        }

        return $out;
    }

    public function test_pages_carry_a_report_only_policy_with_the_nonce_of_their_inline_scripts(): void
    {
        $response = $this->get('/whoarewe')->assertOk();

        $this->assertFalse($response->headers->has('Content-Security-Policy'), 'rien de bloquant tant que la politique est en observation');
        $csp = self::directives((string) $response->headers->get('Content-Security-Policy-Report-Only'));
        $this->assertMatchesRegularExpression("/^'self' 'nonce-[A-Za-z0-9+\\/=]+'$/", $csp['script-src']);
        preg_match("/'nonce-([^']+)'/", $csp['script-src'], $m);
        $this->assertStringContainsString('<script data-theme-init nonce="'.$m[1].'">', (string) $response->getContent());

        $this->assertSame("'self'", $csp['default-src']);
        $this->assertStringContainsString('https://tile.openstreetmap.org', $csp['connect-src']);
        $this->assertStringContainsString('https://tiles.openfreemap.org', $csp['connect-src']);
        $this->assertStringContainsString('https://upload.wikimedia.org', $csp['img-src']);
        $this->assertSame("'self' blob:", $csp['worker-src']);
        $this->assertSame("'none'", $csp['object-src']);
        $this->assertSame("'self'", $csp['frame-ancestors']);
        $this->assertSame(ContentSecurityPolicy::REPORT_PATH, $csp['report-uri']);
        $this->assertSame('csp', $csp['report-to']);
        $this->assertSame('csp="'.url('/api/csp-report').'"', $response->headers->get('Reporting-Endpoints'));
    }

    public function test_the_policy_becomes_blocking_when_enforced(): void
    {
        config(['csp.enforce' => true]);

        $response = $this->get('/whoarewe')->assertOk();

        $this->assertTrue($response->headers->has('Content-Security-Policy'));
        $this->assertFalse($response->headers->has('Content-Security-Policy-Report-Only'));
    }

    /**
     * Décision du 22/11/2026 : politique bloquante dès la mise en ligne. Chaque page principale reçoit
     * l'en-tête bloquant, jamais les deux, avec ce que ses ressources exigent (fond de carte PMTiles lu
     * sur le site, workers de MapLibre en blob:, aucun script ni style en ligne non déclaré).
     */
    public function test_main_pages_are_all_covered_by_the_blocking_policy(): void
    {
        config(['csp.enforce' => true]);

        foreach (['/', '/maps', '/lines', '/stations', '/accidents', '/materials', '/journal', '/api', '/docs/api', '/docs/swagger', '/docs/graphql', '/graphiql', '/contribute', '/embed/maps'] as $url) {
            $response = $this->get($url);
            $this->assertTrue($response->isSuccessful() || $response->isRedirection(), $url);
            if ($response->isRedirection()) {
                continue;
            }
            $this->assertFalse($response->headers->has('Content-Security-Policy-Report-Only'), $url);
            $csp = self::directives((string) $response->headers->get('Content-Security-Policy'));
            $this->assertArrayHasKey('connect-src', $csp, $url);
            $this->assertStringContainsString("'self'", $csp['connect-src'], $url.' : l\'archive PMTiles est lue sur le site');
            $this->assertStringContainsString('blob:', $csp['worker-src'], $url);
            $this->assertStringContainsString('blob:', $csp['img-src'], $url);
            $this->assertStringNotContainsString("'unsafe-eval'", $csp['script-src'], $url);
            $this->assertStringNotContainsString('*', $csp['script-src'], $url);
        }
    }

    public function test_api_documentation_pages_are_covered(): void
    {
        foreach (['/docs/api', '/docs/swagger'] as $url) {
            $csp = self::directives((string) $this->get($url)->assertOk()->headers->get('Content-Security-Policy-Report-Only'));
            $this->assertStringContainsString("'nonce-", $csp['script-src'], $url);
        }
        // Magidoc : pages statiques générées, sans script ni style en ligne (empreintes pour les attributs `style`).
        $csp = self::directives((string) $this->get('/docs/graphql')->assertOk()->headers->get('Content-Security-Policy-Report-Only'));
        $this->assertSame("'self'", $csp['script-src']);
        $this->assertSame("'self'", $csp['style-src']);
        $this->assertStringStartsWith("'unsafe-hashes' 'sha256-", $csp['style-src-attr']);
        $this->assertStringNotContainsString('unsafe-inline', implode(' ', $csp));
    }

    /** Pages Magidoc générées : un script en ligne, un gestionnaire `on…=` ou un <style> casserait la politique. */
    public function test_generated_graphql_pages_have_no_inline_script_nor_undeclared_inline_style(): void
    {
        $declared = json_decode((string) file_get_contents(resource_path('docs/graphql/csp-style-hashes.json')), true);
        $this->assertIsArray($declared);
        $this->assertNotEmpty($declared);

        $pages = glob(public_path('docs/graphql').'/{*,*/*}.html', GLOB_BRACE) ?: [];
        $this->assertGreaterThan(200, count($pages), 'référence GraphQL non générée (make docs-graphql)');
        foreach ($pages as $page) {
            $html = (string) file_get_contents($page);
            $name = basename(dirname($page)).'/'.basename($page);
            $this->assertSame(0, preg_match('~<script(?![^>]*\bsrc=)[^>]*>~', $html), "script en ligne dans $name");
            $this->assertSame(0, preg_match('~<style[\s>]~', $html), "balise <style> dans $name");
            $this->assertSame(0, preg_match('~\son[a-z]+="~', $html), "gestionnaire en ligne dans $name");
            preg_match_all('~\sstyle="([^"]*)"~', $html, $styles);
            foreach ($styles[1] as $value) {
                $hash = 'sha256-'.base64_encode(hash('sha256', html_entity_decode($value, ENT_QUOTES), true));
                $this->assertContains($hash, $declared, "attribut style sans empreinte dans $name : $value");
            }
        }
    }

    /** Les en-têtes nginx recopient la politique de Laravel pour /docs/graphql (hors rapports de violation). */
    public function test_nginx_policy_for_graphql_matches_laravel(): void
    {
        $conf = (string) file_get_contents(base_path('deploy/nginx/vff-project.org.conf'));
        $this->assertSame(1, preg_match('#location \^~ /docs/graphql \{.*?Content-Security-Policy(?:-Report-Only)? "([^"]*)"#s', $conf, $m));

        $laravel = array_filter(
            self::directives(ContentSecurityPolicy::build(Request::create('/docs/graphql'), 'x')),
            fn (string $name): bool => ! in_array($name, ['report-uri', 'report-to'], true),
            ARRAY_FILTER_USE_KEY,
        );
        $nginx = self::directives($m[1]);
        // Laravel y ajoute le serveur Vite en développement : seules les directives de la page comptent.
        foreach (['script-src', 'style-src', 'style-src-attr', 'default-src', 'object-src', 'frame-ancestors'] as $name) {
            $this->assertSame($laravel[$name], $nginx[$name], $name);
        }
        $this->assertStringNotContainsString('unsafe-inline', $m[1]);
    }

    public function test_embeddable_pages_may_be_framed_anywhere(): void
    {
        $response = $this->get('/embed/maps');

        $this->assertSame('*', self::directives((string) $response->headers->get('Content-Security-Policy-Report-Only'))['frame-ancestors']);
    }

    public function test_embeddable_pages_get_the_full_blocking_policy_with_frame_ancestors_open(): void
    {
        config(['csp.enforce' => true]);

        $header = (string) $this->get('/embed/maps')->assertOk()->headers->get('Content-Security-Policy');
        $csp = self::directives($header);

        $this->assertSame('*', $csp['frame-ancestors']);
        $this->assertStringContainsString("'nonce-", $csp['script-src'], 'pas la seule directive frame-ancestors');
        $this->assertSame(1, substr_count($header, 'frame-ancestors'));
    }

    public function test_json_responses_carry_no_policy(): void
    {
        $this->getJson('/api/v1/eras')->assertOk()->assertHeaderMissing('Content-Security-Policy-Report-Only');
    }

    public function test_the_vite_development_server_is_allowed_while_it_runs(): void
    {
        $hot = (string) tempnam(sys_get_temp_dir(), 'vite-hot');
        file_put_contents($hot, 'http://127.0.0.1:5173');
        config(['csp.hot_file' => $hot]);
        $this->app['env'] = 'local';
        try {
            $csp = self::directives((string) $this->get('/whoarewe')->headers->get('Content-Security-Policy-Report-Only'));
        } finally {
            unlink($hot);
        }

        $this->assertStringContainsString('http://127.0.0.1:5173', $csp['script-src']);
        $this->assertStringContainsString('ws://127.0.0.1:5173', $csp['connect-src']);

        // Adresse IPv6 (non admise par la syntaxe CSP) : le schéma seul.
        file_put_contents($hot, 'http://[::1]:5173');
        try {
            $csp = self::directives((string) $this->get('/whoarewe')->headers->get('Content-Security-Policy-Report-Only'));
        } finally {
            unlink($hot);
        }
        $this->assertStringContainsString(' http:', $csp['script-src']);
        $this->assertStringContainsString(' ws:', $csp['connect-src']);
    }

    public function test_reports_are_logged_without_personal_data(): void
    {
        $logger = new class
        {
            /** @var list<array<string, mixed>> */
            public array $logged = [];

            /** @param  array<string, mixed>  $context */
            public function warning(string $message, array $context): void
            {
                $this->logged[] = $context;
            }
        };
        Log::shouldReceive('channel')->with('csp')->andReturn($logger);

        $this->call('POST', '/api/csp-report', server: ['CONTENT_TYPE' => 'application/csp-report', 'REMOTE_ADDR' => '203.0.113.7'], content: (string) json_encode(['csp-report' => [
            'document-uri' => 'https://vff-project.org/reset-password/abc123?email=marie%40example.org#x',
            'violated-directive' => 'script-src-elem',
            'effective-directive' => 'script-src-elem',
            'blocked-uri' => 'https://evil.example.net/x.js?session=secret',
            'source-file' => 'https://vff-project.org/build/app.js?v=1',
            'line-number' => 12,
            'column-number' => 4,
            'disposition' => 'report',
            'script-sample' => 'alert(document.cookie)',
        ]]))->assertNoContent();

        $this->call('POST', '/api/csp-report', server: ['CONTENT_TYPE' => 'application/reports+json'], content: (string) json_encode([
            ['type' => 'csp-violation', 'url' => 'https://vff-project.org/maps', 'body' => [
                'documentURL' => 'https://vff-project.org/maps?era=1930', 'effectiveDirective' => 'img-src',
                'blockedURL' => 'data', 'disposition' => 'report', 'sample' => 'secret',
            ]],
            ['type' => 'deprecation', 'body' => []],
        ]))->assertNoContent();

        $this->assertSame([
            ['document' => 'https://vff-project.org/reset-password/abc123', 'directive' => 'script-src-elem', 'blocked' => 'https://evil.example.net', 'source' => 'https://vff-project.org/build/app.js', 'line' => 12, 'column' => 4, 'disposition' => 'report'],
            ['document' => 'https://vff-project.org/maps', 'directive' => 'img-src', 'blocked' => 'data', 'source' => null, 'line' => null, 'column' => null, 'disposition' => 'report'],
        ], $logger->logged);
        $this->assertStringNotContainsString('203.0.113.7', (string) json_encode($logger->logged));
    }

    public function test_reports_are_rate_limited_and_oversized_bodies_ignored(): void
    {
        config(['csp.reports_per_minute' => 2]);
        $body = (string) json_encode(['csp-report' => ['effective-directive' => 'img-src', 'blocked-uri' => 'data']]);

        $this->call('POST', '/api/csp-report', content: $body)->assertNoContent();
        $this->call('POST', '/api/csp-report', content: $body)->assertNoContent();
        $this->call('POST', '/api/csp-report', content: $body)->assertStatus(429);

        config(['csp.reports_per_minute' => 100]);
        $this->call('POST', '/api/csp-report', server: ['REMOTE_ADDR' => '198.51.100.9'], content: str_repeat('x', 20000))->assertNoContent();
    }
}

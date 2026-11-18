<?php

namespace Tests\Feature\Api;

use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ScalarType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nuwave\Lighthouse\Schema\SchemaBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Documentation de l'API.
 *
 * REST : Scalar (/docs/api, vue resources/views/vendor/scramble/docs.blade.php), Swagger UI
 * (/docs/swagger), spécification OpenAPI /docs/api.json générée par Scramble.
 * GraphQL : référence statique Magidoc (/docs/graphql), descriptions de graphql/schema.graphql.
 * Stoplight Elements a été retiré : il ne doit plus apparaître nulle part.
 */
class ApiDocsTest extends TestCase
{
    use RefreshDatabase;

    /** Routes publiques de routes/api.php documentées dans la spécification OpenAPI. */
    private const V1_PATHS = [
        '/eras/{year}/lines.geojson',
        '/eras/{year}/stations.geojson',
        '/eras/{year}/tiles/{z}/{x}/{y}.mvt',
        '/eras/{year}/gtfs.zip',
        '/search',
        '/lines/{line}/history',
        '/lines/{line}/pk',
        '/lines/{line}/stops',
        '/lines/{line}/signalling',
        '/lines/{line}/media',
        '/route',
    ];

    /** Réponses binaires (MVT, ZIP) : pas d'exemple JSON attendu. */
    private const BINARY_PATHS = ['/eras/{year}/tiles/{z}/{x}/{y}.mvt', '/eras/{year}/gtfs.zip'];

    /** Types déclarés dans graphql/schema.graphql (hors types ajoutés par Lighthouse). */
    private const GRAPHQL_TYPES = [
        'Query', 'Era', 'Line', 'LineStop', 'LineSignallingRange', 'LineSegment', 'LineEvent',
        'Station', 'Material', 'RouteEdge', 'RouteStop', 'RouteLine', 'Route', 'Date', 'DateTime',
    ];

    public function test_scalar_is_the_main_rest_documentation(): void
    {
        $html = $this->get('/docs/api')->assertOk()->getContent();

        $this->assertStringContainsString('data-spec-url="/docs/api.json"', $html);
        $this->assertStringContainsString('href="/docs/swagger"', $html);
        $this->assertStringContainsString('href="/docs/graphql"', $html);
        $this->assertStringContainsString('href="/graphiql"', $html);
        $this->assertStringNotContainsStringIgnoringCase('stoplight', $html);
    }

    public function test_graphiql_is_served_without_third_party_hosts_and_with_the_documentation_bar(): void
    {
        $html = $this->get('/graphiql')->assertOk()->getContent();

        $this->assertStringContainsString('class="vff-docs-bar"', $html);
        $this->assertStringContainsString('href="/docs/graphql"', $html);
        $this->assertStringContainsString('href="/docs/swagger"', $html);
        $this->assertStringContainsString('/vendor/graphiql/graphiql.js', $html);
        $this->assertStringContainsString('id="graphiql-config"', $html);
        foreach (['jsdelivr', 'githubusercontent', 'unpkg', 'cdnjs'] as $host) {
            $this->assertStringNotContainsString($host, $html);
        }
        foreach (['graphiql.js', 'graphiql.css', 'react.production.min.js', 'react-dom.production.min.js', 'graphiql-plugin-explorer.js', 'graphiql-plugin-explorer.css'] as $file) {
            $this->assertFileExists(public_path("vendor/graphiql/{$file}"));
        }
    }

    public function test_documentation_bar_links_to_the_site_and_the_languages(): void
    {
        foreach (['/docs/api', '/docs/swagger', '/graphiql'] as $uri) {
            $html = $this->withCookie('locale', 'en')->get($uri)->assertOk()->getContent();

            $this->assertStringContainsString('<html lang="en">', $html, $uri);
            $this->assertStringContainsString('href="/"', $html, $uri);
            $this->assertStringContainsString('href="'.route('api.docs').'"', $html, $uri);
            foreach (['fr', 'en', 'de'] as $code) {
                $this->assertStringContainsString('href="/locale/'.$code.'"', $html, $uri);
            }
            $this->assertStringContainsString('data-theme-toggle', $html, $uri);
            $this->assertStringContainsString('href="#vff-docs-app"', $html, $uri);
        }
    }

    public function test_swagger_ui_reads_the_same_specification(): void
    {
        $html = $this->get('/docs/swagger')->assertOk()->getContent();

        $this->assertStringContainsString('data-spec-url="/docs/api.json"', $html);
        $this->assertStringContainsString('href="/docs/api"', $html);
        $this->assertStringNotContainsStringIgnoringCase('stoplight', $html);
    }

    #[DataProvider('locales')]
    public function test_documentation_bar_is_translated(string $locale, string $back): void
    {
        $this->withCookie('locale', $locale)
            ->get('/docs/swagger')
            ->assertOk()
            ->assertSee($back);
    }

    /** @return array<string, array{string, string}> */
    public static function locales(): array
    {
        return [
            'fr' => ['fr', 'Accès à l\'API'],
            'en' => ['en', 'API access'],
            'de' => ['de', 'API-Zugang'],
        ];
    }

    public function test_api_page_no_longer_mentions_stoplight(): void
    {
        $html = $this->get('/api')->assertOk()->getContent();

        $this->assertStringNotContainsStringIgnoringCase('stoplight', $html);
        $this->assertStringNotContainsStringIgnoringCase('stoplight', (string) file_get_contents(resource_path('js/Pages/ApiDocs.svelte')));
        foreach (['fr', 'en', 'de'] as $locale) {
            $this->assertStringNotContainsStringIgnoringCase('stoplight', (string) file_get_contents(lang_path("{$locale}/api.php")));
        }
    }

    public function test_api_page_lists_every_documented_endpoint_and_presents_nothing_as_upcoming(): void
    {
        $page = (string) file_get_contents(resource_path('js/Pages/ApiDocs.svelte'));
        foreach (self::V1_PATHS as $path) {
            $this->assertStringContainsString('/api/v1'.$path, $page, "{$path} absent de la page /api");
        }
        foreach (['/docs/api', '/docs/swagger', '/docs/api.json', '/docs/graphql', '/graphiql'] as $link) {
            $this->assertStringContainsString("'{$link}'", $page, "{$link} : lien absent de la page /api");
        }

        foreach (['fr', 'en', 'de'] as $locale) {
            $texts = (string) file_get_contents(lang_path("{$locale}/api.php"));
            foreach (['Phase 6', 'phase 6', 'prévu', 'planned', 'geplant', 'anticipé', 'Endpoints'] as $stale) {
                $this->assertStringNotContainsString($stale, $texts, "{$locale}/api.php : « {$stale} »");
            }
        }
        $this->get('/api')->assertOk();
    }

    public function test_graphql_reference_is_served(): void
    {
        if (! is_file(public_path('docs/graphql/index.html'))) {
            $this->markTestSkipped('Référence GraphQL non générée (make docs-graphql).');
        }

        // Fichiers servis tels quels (BinaryFileResponse) : on vérifie le fichier choisi.
        $served = fn (string $uri): string => $this->get($uri)->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
            ->baseResponse->getFile()->getPathname();

        $this->assertStringContainsString('/docs/graphql/guide/introduction', (string) file_get_contents($served('/docs/graphql')));
        $this->assertStringContainsString('API GraphQL de VFF', (string) file_get_contents($served('/docs/graphql/guide/introduction')));
        $this->assertStringEndsWith('queries/line.html', $served('/docs/graphql/queries/line'));
        $this->assertStringEndsWith('types/LineSegment.html', $served('/docs/graphql/types/LineSegment'));
        $this->get('/docs/graphql/inexistant')->assertNotFound();
        $this->get('/docs/graphql/..%2F..%2F.env')->assertNotFound();
    }

    public function test_graphql_reference_follows_the_site_theme_and_loads_no_remote_font(): void
    {
        $page = public_path('docs/graphql/guide/introduction.html');
        if (! is_file($page)) {
            $this->markTestSkipped('Référence GraphQL non générée (make docs-graphql).');
        }

        $html = (string) file_get_contents($page);
        $this->assertStringContainsString('<html lang="fr">', $html);
        $this->assertStringContainsString('theme=(dark|light)', $html, 'le thème du cookie du site doit être lu');
        $this->assertStringContainsString('<title>Guide - Introduction - VFF API GraphQL</title>', $html);

        $css = (string) file_get_contents(public_path('docs/graphql/vff.css'));
        $this->assertStringContainsString('lato-latin-400-normal.woff2', $css);
        $this->assertStringNotContainsString('.ttf', $css);
        foreach (glob(public_path('docs/graphql/_app/immutable/assets/*.css')) ?: [] as $file) {
            $this->assertStringNotContainsString('s81c.com', (string) file_get_contents($file));
        }
    }

    public function test_openapi_spec_documents_every_public_v1_endpoint(): void
    {
        $spec = $this->getJson('/docs/api.json')->assertOk()->json();

        $this->assertStringStartsWith('3.', $spec['openapi']);
        $this->assertStringContainsString('31/12', $spec['info']['description']);
        $this->assertSame(self::V1_PATHS, array_values(array_intersect(self::V1_PATHS, array_keys($spec['paths']))));

        foreach (self::V1_PATHS as $path) {
            $operation = $spec['paths'][$path]['get'];
            $this->assertNotEmpty($operation['summary'] ?? null, "{$path} : résumé manquant");
            $this->assertLessThanOrEqual(60, mb_strlen($operation['summary']), "{$path} : résumé trop long");
            $this->assertGreaterThan(200, mb_strlen($operation['description'] ?? ''), "{$path} : description trop courte");
            $this->assertNotEmpty($operation['tags'] ?? [], "{$path} : groupe manquant");

            foreach ($operation['parameters'] ?? [] as $parameter) {
                $this->assertNotEmpty($parameter['description'] ?? null, "{$path} : paramètre {$parameter['name']} sans description");
                $this->assertTrue(
                    isset($parameter['example']) || isset($parameter['schema']['examples']) || isset($parameter['schema']['example']),
                    "{$path} : paramètre {$parameter['name']} sans exemple",
                );
            }

            if (! in_array($path, self::BINARY_PATHS, true)) {
                $schema = $operation['responses']['200']['content']['application/json']['schema'] ?? [];
                $this->assertNotEmpty($schema['examples'] ?? null, "{$path} : exemple de réponse manquant");
            }
        }
    }

    public function test_documentation_texts_have_no_long_dash(): void
    {
        $spec = (string) $this->get('/docs/api.json')->assertOk()->getContent();
        $this->assertStringNotContainsString('—', $spec);
        $this->assertStringNotContainsString('\\u2014', $spec);

        foreach (['fr', 'en', 'de'] as $locale) {
            foreach (['docs', 'api', 'errors'] as $file) {
                $this->assertStringNotContainsString('—', (string) file_get_contents(lang_path("{$locale}/{$file}.php")));
            }
        }
        $this->assertStringNotContainsString('—', (string) file_get_contents(base_path('graphql/schema.graphql')));
    }

    public function test_every_graphql_type_field_and_argument_is_described(): void
    {
        $schema = app(SchemaBuilder::class)->schema();

        foreach (self::GRAPHQL_TYPES as $name) {
            $type = $schema->getType($name);
            $this->assertNotNull($type, "Type GraphQL {$name} absent");
            if ($name !== 'Query') {
                $this->assertNotEmpty($type->description, "Type {$name} sans description");
            }
            if ($type instanceof ScalarType || ! $type instanceof ObjectType) {
                continue;
            }
            foreach ($type->getFields() as $field) {
                /** @var FieldDefinition $field */
                $this->assertNotEmpty($field->description, "Champ {$name}.{$field->name} sans description");
                foreach ($field->args as $arg) {
                    // Arguments ajoutés par @paginate (first, page) : décrits par Lighthouse.
                    $this->assertNotEmpty($arg->description, "Argument {$name}.{$field->name}({$arg->name}) sans description");
                }
            }
        }
    }
}

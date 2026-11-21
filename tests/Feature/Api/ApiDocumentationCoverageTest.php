<?php

namespace Tests\Feature\Api;

use App\Mcp\Servers\VffServer;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\UnionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Laravel\Mcp\Server\Tool;
use Nuwave\Lighthouse\Schema\SchemaBuilder;
use ReflectionProperty;
use Tests\TestCase;

/**
 * Aucune donnée sans documentation : chaque route publique de routes/api.php figure dans la
 * spécification OpenAPI (Scramble) avec résumé, description, paramètres décrits avec exemple et réponse
 * d'exemple ; chaque type, champ, argument, entrée et valeur d'énumération de graphql/schema.graphql est décrit ;
 * chaque outil MCP décrit ses arguments, est traduit dans les trois langues et figure dans docs/MCP.md.
 * (ApiDocsTest garde la page /api, la liste historique des chemins et les codes d'erreur.)
 */
class ApiDocumentationCoverageTest extends TestCase
{
    use RefreshDatabase;

    private const BINARY_SUFFIXES = ['.mvt', '.zip', '/gpx', '/kml'];

    /** @return list<string> chemins de la spécification, sans le préfixe /api/v1 ni les liaisons `:slug` */
    private function v1Paths(): array
    {
        $paths = [];
        foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
            /** @var Route $route */
            if (! str_starts_with($route->uri(), 'api/v1/') || ! in_array('GET', $route->methods(), true)) {
                continue;
            }
            $paths[] = (string) preg_replace('/\{(\w+):\w+\}/', '{$1}', substr($route->uri(), strlen('api/v1')));
        }
        sort($paths);

        return $paths;
    }

    public function test_every_public_rest_route_is_in_the_specification_with_a_complete_description(): void
    {
        $spec = $this->getJson('/docs/api.json')->assertOk()->json();
        $paths = $this->v1Paths();
        $this->assertGreaterThan(55, count($paths));

        foreach ($paths as $path) {
            $this->assertArrayHasKey($path, $spec['paths'], "{$path} absent de la spécification OpenAPI");
            $operation = $spec['paths'][$path]['get'];
            $this->assertNotEmpty($operation['summary'] ?? null, "{$path} : résumé manquant");
            $this->assertLessThanOrEqual(60, mb_strlen($operation['summary']), "{$path} : résumé trop long");
            $this->assertGreaterThan(100, mb_strlen($operation['description'] ?? ''), "{$path} : description trop courte");
            $this->assertNotEmpty($operation['tags'] ?? [], "{$path} : groupe manquant");

            foreach ($operation['parameters'] ?? [] as $parameter) {
                $this->assertNotEmpty($parameter['description'] ?? null, "{$path} : paramètre {$parameter['name']} sans description");
                $this->assertTrue(
                    isset($parameter['example']) || isset($parameter['schema']['examples']) || isset($parameter['schema']['example']),
                    "{$path} : paramètre {$parameter['name']} sans exemple",
                );
            }
            $binary = array_filter(self::BINARY_SUFFIXES, fn (string $suffix): bool => str_ends_with($path, $suffix)) !== [];
            if (! $binary) {
                $schema = $operation['responses']['200']['content']['application/json']['schema'] ?? [];
                $this->assertNotEmpty($schema['examples'] ?? null, "{$path} : exemple de réponse manquant");
            }
            foreach ([401, 403, 429] as $code) {
                $this->assertArrayHasKey((string) $code, $operation['responses'], "{$path} : réponse {$code} non documentée");
            }
        }
    }

    public function test_every_graphql_type_field_argument_input_and_enum_value_is_described(): void
    {
        $schema = app(SchemaBuilder::class)->schema();
        $checked = 0;

        foreach ($schema->getTypeMap() as $name => $type) {
            if (str_starts_with($name, '__') || ! ($type instanceof ObjectType || $type instanceof InputObjectType || $type instanceof EnumType || $type instanceof InterfaceType || $type instanceof UnionType)) {
                continue;
            }
            // Types ajoutés par Lighthouse (pagination, ordre) : décrits par le paquet, pas par VFF.
            if (in_array($name, ['PageInfo', 'OrderByClause', 'SortOrder'], true) || preg_match('/(Paginator|Connection|Edge|PaginatorInfo|OrderByRelation|OrderByClause)$/', $name) === 1) {
                continue;
            }
            $checked++;
            if ($name !== 'Query' && $name !== 'Mutation') {
                $this->assertNotEmpty($type->description, "Type {$name} sans description");
            }
            if ($type instanceof EnumType) {
                foreach ($type->getValues() as $value) {
                    $this->assertNotEmpty($value->description, "Valeur {$name}.{$value->name} sans description");
                }

                continue;
            }
            if ($type instanceof UnionType) {
                continue;
            }
            foreach ($type->getFields() as $field) {
                $this->assertNotEmpty($field->description, "Champ {$name}.{$field->name} sans description");
                foreach ($field->args ?? [] as $arg) {
                    $this->assertNotEmpty($arg->description, "Argument {$name}.{$field->name}({$arg->name}) sans description");
                }
            }
        }
        $this->assertGreaterThan(150, $checked);
    }

    public function test_every_mcp_tool_describes_its_arguments_and_is_documented(): void
    {
        /** @var list<class-string<Tool>> $tools */
        $tools = (new ReflectionProperty(VffServer::class, 'tools'))->getDefaultValue();
        $doc = (string) file_get_contents(base_path('docs/MCP.md'));
        $this->assertNotEmpty($tools);

        foreach ($tools as $class) {
            $tool = app($class);
            $name = $tool->name();
            $this->assertGreaterThan(200, mb_strlen($tool->description()), "{$name} : description trop courte");
            $this->assertStringContainsString("### `{$name}`", $doc, "{$name} : section absente de docs/MCP.md");

            $properties = $tool->toArray()['inputSchema']['properties'] ?? [];
            foreach ($properties as $argument => $definition) {
                if ($argument === 'lang') {
                    continue; // argument commun, décrit dans « Conventions communes »
                }
                $this->assertNotEmpty($definition['description'] ?? null, "{$name} : argument {$argument} sans description");
                // Chaque argument est cité dans la section de l'outil de docs/MCP.md.
                $section = $this->section($doc, $name);
                $this->assertStringContainsString("`{$argument}`", $section, "{$name} : argument {$argument} absent de la section de docs/MCP.md");
            }
        }
    }

    private function section(string $doc, string $tool): string
    {
        $start = strpos($doc, "### `{$tool}`");
        $this->assertNotFalse($start);
        $end = strpos($doc, "\n### ", $start + 5);
        $end2 = strpos($doc, "\n## ", $start + 5);
        $end = min(array_filter([$end, $end2], fn ($e) => $e !== false) ?: [strlen($doc)]);

        return substr($doc, $start, $end - $start);
    }
}

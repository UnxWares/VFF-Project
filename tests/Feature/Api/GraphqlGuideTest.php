<?php

namespace Tests\Feature\Api;

use GraphQL\Language\Parser;
use GraphQL\Type\Definition\Type;
use GraphQL\Validator\DocumentValidator;
use GraphQL\Validator\Rules\QueryComplexity;
use Nuwave\Lighthouse\Schema\SchemaBuilder;
use Nuwave\Lighthouse\Support\Contracts\ProvidesValidationRules;
use Tests\TestCase;

/**
 * Guide de l'API GraphQL (resources/docs/graphql/guide, page /docs/graphql) : chaque requête
 * d'exemple est valide contre le schéma et sous les limites de coût de l'API, les trois langues
 * portent les mêmes exemples, aucun type du schéma n'échappe à la référence générée.
 */
class GraphqlGuideTest extends TestCase
{
    private const LOCALES = ['fr', 'en', 'de'];

    private function guidePath(string $path = ''): string
    {
        return resource_path('docs/graphql/guide'.($path === '' ? '' : '/'.$path));
    }

    /** @return array<string, string> nom de l'exemple => requête */
    private function examples(): array
    {
        $examples = [];
        foreach (glob($this->guidePath('examples/*.graphql')) ?: [] as $file) {
            $examples[basename($file, '.graphql')] = (string) file_get_contents($file);
        }

        return $examples;
    }

    public function test_the_guide_has_between_eight_and_ten_examples(): void
    {
        $this->assertGreaterThanOrEqual(8, count($this->examples()));
        $this->assertLessThanOrEqual(10, count($this->examples()));
    }

    public function test_every_example_is_a_valid_query_within_the_api_limits(): void
    {
        $schema = app(SchemaBuilder::class)->schema();
        $rules = app(ProvidesValidationRules::class)->validationRules();

        foreach ($this->examples() as $name => $query) {
            $variables = is_file($this->guidePath("examples/{$name}.variables.json"))
                ? json_decode((string) file_get_contents($this->guidePath("examples/{$name}.variables.json")), true, flags: JSON_THROW_ON_ERROR)
                : [];
            $complexity = $rules[QueryComplexity::class];
            $this->assertInstanceOf(QueryComplexity::class, $complexity);
            $complexity->setRawVariableValues($variables);
            $errors = DocumentValidator::validate($schema, Parser::parse($query), $rules);
            $this->assertSame([], array_map(fn ($e): string => $e->getMessage(), $errors), "Exemple {$name} invalide");
        }
    }

    public function test_example_variables_match_the_declared_variables(): void
    {
        foreach ($this->examples() as $name => $query) {
            $file = $this->guidePath("examples/{$name}.variables.json");
            $declared = [];
            preg_match_all('/\$(\w+)\s*:/', $query, $m);
            $declared = $m[1];
            if ($declared === []) {
                $this->assertFileDoesNotExist($file, "Exemple {$name} sans variable mais avec un fichier de variables");

                continue;
            }
            $this->assertFileExists($file, "Exemple {$name} : variables manquantes");
            $values = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
            $this->assertEqualsCanonicalizing($declared, array_keys($values), "Exemple {$name} : variables différentes");
        }
    }

    public function test_every_language_places_the_same_examples_and_has_two_pages(): void
    {
        $names = array_keys($this->examples());

        foreach (self::LOCALES as $locale) {
            $files = glob($this->guidePath("{$locale}/*.md")) ?: [];
            $this->assertCount(2, $files, "Guide {$locale} : une introduction et des premiers pas attendus");

            $text = implode("\n", array_map(fn (string $f): string => (string) file_get_contents($f), $files));
            preg_match_all('/\{\{query:([\w-]+)\}\}/', $text, $used);
            $this->assertEqualsCanonicalizing($names, $used[1], "Guide {$locale} : exemples placés différents des fichiers");
            $this->assertStringNotContainsString('—', $text, "Guide {$locale} : tiret long");
            $this->assertStringNotContainsString(' -- ', $text, "Guide {$locale} : double tiret");
        }
    }

    public function test_the_guide_covers_the_expected_topics_in_every_language(): void
    {
        foreach (self::LOCALES as $locale) {
            $text = (string) file_get_contents($this->guidePath(['fr' => 'fr/introduction.md', 'en' => 'en/introduction.md', 'de' => 'de/grundlagen.md'][$locale]));
            foreach (['/graphql', 'Authorization: Bearer', 'X-RateLimit-Remaining', 'Retry-After', 'endCursor', 'batch_too_large', 'query_too_complex', '@deprecated', '/graphiql'] as $needle) {
                $this->assertStringContainsString($needle, $text, "Guide {$locale} : « {$needle} » absent de l'introduction");
            }
        }
    }

    public function test_every_schema_type_is_described_and_documented_in_the_generated_reference(): void
    {
        $schema = app(SchemaBuilder::class)->schema();
        $built = public_path('docs/graphql');
        $missing = [];
        $undocumented = [];

        foreach ($schema->getTypeMap() as $name => $type) {
            if (str_starts_with($name, '__') || Type::isBuiltInScalar($type)) {
                continue;
            }
            if (! ($type->description ?? null)) {
                $missing[] = $name;
            }
            if (is_dir($built) && $name !== 'Mutation' && $name !== 'Subscription' && ! is_file("{$built}/types/{$name}.html") && ! is_file("{$built}/queries/{$name}.html")) {
                // `Query` n'a pas de page de type : ses champs ont chacun leur page.
                if ($name !== 'Query') {
                    $undocumented[] = $name;
                }
            }
        }

        $this->assertSame([], $missing, 'Types du schéma sans description : '.implode(', ', $missing));
        if (! is_dir($built)) {
            $this->markTestIncomplete('Référence GraphQL non générée (make docs-graphql) : pages non vérifiées.');
        }
        $this->assertSame([], $undocumented, 'Types sans page dans la référence : '.implode(', ', $undocumented));
    }

    public function test_the_generated_reference_documents_every_query_and_field_description(): void
    {
        $built = public_path('docs/graphql');
        if (! is_file("{$built}/index.html")) {
            $this->markTestSkipped('Référence GraphQL non générée (make docs-graphql).');
        }
        $schema = app(SchemaBuilder::class)->schema();
        $missing = [];
        foreach ($schema->getQueryType()->getFields() as $field) {
            if (! is_file("{$built}/queries/{$field->name}.html")) {
                $missing[] = $field->name;
            }
            $this->assertNotEmpty($field->description, "Requête {$field->name} sans description");
        }
        $this->assertSame([], $missing, 'Requêtes sans page : '.implode(', ', $missing));
    }

    public function test_the_three_guides_are_served_with_their_language(): void
    {
        $built = public_path('docs/graphql');
        if (! is_file("{$built}/guide/introduction.html")) {
            $this->markTestSkipped('Référence GraphQL non générée (make docs-graphql).');
        }
        foreach (['/docs/graphql/guide/introduction', '/docs/graphql/guide/premiers-pas', '/docs/graphql/guide-en/introduction', '/docs/graphql/guide-en/getting-started', '/docs/graphql/guide-de/grundlagen', '/docs/graphql/guide-de/erste-schritte'] as $uri) {
            $this->get($uri)->assertOk();
        }
    }
}

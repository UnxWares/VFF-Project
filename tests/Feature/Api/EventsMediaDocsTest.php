<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Documentation OpenAPI (Scramble) des points d'accès « événements » et « médias » : résumé, description,
 * paramètres décrits avec exemple, réponses d'exemple, erreurs 404, 422 et 429 documentées.
 */
class EventsMediaDocsTest extends TestCase
{
    use RefreshDatabase;

    /** Chemin → codes de réponse attendus. */
    private const PATHS = [
        '/events' => [200, 422, 429],
        '/events/{event}' => [200, 404, 429],
        '/stations/{station}/events' => [200, 404, 422, 429],
        '/media' => [200, 422, 429],
        '/media/{media}' => [200, 404, 429],
    ];

    /** @var array<string, mixed> */
    private array $spec = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->spec = $this->getJson('/docs/api.json')->assertOk()->json();
    }

    public function test_every_path_is_documented_with_summary_description_and_group(): void
    {
        foreach (self::PATHS as $path => $codes) {
            $operation = $this->spec['paths'][$path]['get'] ?? null;
            $this->assertNotNull($operation, "{$path} absent de la spécification");
            $this->assertLessThanOrEqual(60, mb_strlen($operation['summary']), "{$path} : résumé trop long");
            $this->assertGreaterThan(300, mb_strlen($operation['description'] ?? ''), "{$path} : description trop courte");
            $this->assertNotEmpty($operation['tags'] ?? [], "{$path} : groupe manquant");
            // 401 et 403 : jeton invalide ou d'un compte suspendu, sur chaque opération (ApiAuthDocumentation).
            $this->assertEqualsCanonicalizing([...$codes, 401, 403], array_map('intval', array_keys($operation['responses'])), "{$path} : codes de réponse");
        }
    }

    public function test_every_parameter_has_a_description_and_an_example(): void
    {
        foreach (array_keys(self::PATHS) as $path) {
            $parameters = $this->spec['paths'][$path]['get']['parameters'] ?? [];
            $this->assertNotEmpty($parameters, "{$path} : aucun paramètre");
            foreach ($parameters as $parameter) {
                $this->assertGreaterThan(20, mb_strlen($parameter['description'] ?? ''), "{$path} : paramètre {$parameter['name']} sans description");
                $this->assertTrue(
                    isset($parameter['example']) || isset($parameter['schema']['example']) || isset($parameter['schema']['examples']),
                    "{$path} : paramètre {$parameter['name']} sans exemple",
                );
            }
        }
    }

    public function test_list_parameters_match_the_validated_filters(): void
    {
        $names = fn (string $path): array => array_column($this->spec['paths'][$path]['get']['parameters'], 'name');

        $this->assertEqualsCanonicalizing(['line', 'type', 'source', 'department', 'region', 'from', 'to', 'order', 'per_page', 'cursor'], $names('/events'));
        $this->assertEqualsCanonicalizing(['station', 'type', 'source', 'inferred', 'from', 'to', 'order', 'per_page', 'cursor'], $names('/stations/{station}/events'));
        $this->assertEqualsCanonicalizing(
            ['line', 'station', 'material', 'event', 'type', 'provider', 'license', 'rights_status', 'commercial_ok', 'period', 'from_year', 'to_year', 'featured', 'facets', 'order', 'per_page', 'cursor'],
            $names('/media'),
        );
        // Une fiche ne prend que son identifiant : aucun filtre de liste ne doit s'y glisser.
        $this->assertSame(['media'], $names('/media/{media}'));
        $this->assertSame(['event'], $names('/events/{event}'));
    }

    public function test_every_response_has_an_example(): void
    {
        foreach (self::PATHS as $path => $codes) {
            foreach ($codes as $code) {
                $schema = $this->spec['paths'][$path]['get']['responses'][(string) $code]['content']['application/json']['schema'] ?? [];
                $this->assertNotEmpty($schema['examples'] ?? null, "{$path} {$code} : exemple de réponse manquant");
            }
        }
    }

    public function test_provenance_and_cursor_are_explained(): void
    {
        $events = $this->spec['paths']['/events']['get']['description'];
        foreach (['links.next', 'meta.next_cursor', 'provenance', 'license_status', 'date_precision', 'source_url'] as $term) {
            $this->assertStringContainsString($term, $events, "/events : « {$term} » non expliqué");
        }
        $media = $this->spec['paths']['/media']['get']['description'];
        foreach (['attribution', 'license', 'rights_status', 'source_url', 'links.next', 'depicted_year'] as $term) {
            $this->assertStringContainsString($term, $media, "/media : « {$term} » non expliqué");
        }
        $this->assertStringContainsString('source_quote', $this->spec['paths']['/events/{event}']['get']['description']);
    }

    public function test_the_texts_have_no_long_dash(): void
    {
        $json = json_encode($this->spec['paths'], JSON_UNESCAPED_UNICODE);
        foreach (['—', '–', ' -- '] as $dash) {
            $this->assertStringNotContainsString($dash, (string) $json);
        }
    }
}

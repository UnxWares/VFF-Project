<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * Les réponses de l'API REST sont toujours du JSON, erreurs comprises, quel que soit l'en-tête Accept :
 * un paramètre invalide sans `Accept: application/json` ne redirige plus vers l'accueil. Les pages web
 * gardent leurs pages d'erreur.
 */
class ApiAlwaysJsonTest extends TestCase
{
    use BuildsRailNetwork;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRailNetwork();
    }

    /** @return array<string, array{array<string, string>}> */
    public static function headers(): array
    {
        return [
            'sans Accept' => [[]],
            'Accept: text/html' => [['Accept' => 'text/html']],
            'Accept: application/json' => [['Accept' => 'application/json']],
            'Accept: */*' => [['Accept' => '*/*']],
        ];
    }

    /** @param array<string, string> $headers */
    #[DataProvider('headers')]
    public function test_invalid_parameter_gives_json_422_on_each_family(array $headers): void
    {
        foreach ([
            '/api/v1/lines?per_page=9999' => 'per_page',
            '/api/v1/media?facets=maybe' => 'facets',
            '/api/v1/lines/'.$this->line->slug.'/audit?limit=201' => 'limit',
        ] as $url => $field) {
            $response = $this->withHeaders($headers)->get($url);
            $response->assertStatus(422)->assertJsonValidationErrors([$field]);
            $this->assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));
            $this->assertSame(['message', 'errors'], array_keys($response->json()), $url);
        }
    }

    /** @param array<string, string> $headers */
    #[DataProvider('headers')]
    public function test_unknown_api_urls_and_models_give_json_404(array $headers): void
    {
        foreach (['/api/v1/nope', '/api/v1/lines/does-not-exist', '/api/v1/stations/does-not-exist/audit'] as $url) {
            $response = $this->withHeaders($headers)->get($url);
            $response->assertNotFound()->assertJsonStructure(['message']);
            $this->assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));
        }
    }

    /** @param array<string, string> $headers */
    #[DataProvider('headers')]
    public function test_unauthenticated_user_endpoint_gives_json_401(array $headers): void
    {
        $this->withHeaders($headers)->get('/api/user')->assertUnauthorized()->assertExactJson(['message' => 'Unauthenticated.']);
    }

    public function test_the_error_body_is_identical_with_and_without_the_accept_header(): void
    {
        $with = $this->getJson('/api/v1/lines?per_page=9999');
        $without = $this->get('/api/v1/lines?per_page=9999');

        $this->assertSame($with->getStatusCode(), $without->getStatusCode());
        $this->assertSame($with->json(), $without->json());
    }

    public function test_method_not_allowed_is_json(): void
    {
        $this->post('/api/v1/lines')->assertStatus(405)->assertJsonStructure(['message']);
    }

    public function test_web_pages_keep_their_html_errors(): void
    {
        $response = $this->get('/cette-page-n-existe-pas', ['Accept' => 'text/html']);
        $response->assertNotFound();
        $this->assertStringNotContainsString('application/json', (string) $response->headers->get('Content-Type'));
    }
}

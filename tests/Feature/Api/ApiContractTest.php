<?php

namespace Tests\Feature\Api;

use App\Support\ApiContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nuwave\Lighthouse\Schema\SchemaBuilder;
use Tests\TestCase;

/**
 * Contrat public de l'API : aucun champ, paramètre, code de réponse, valeur d'énumération, outil MCP ni
 * argument de l'instantané `tests/Fixtures/api-contract.json` ne disparaît, et aucun type ne change, sans
 * annonce (champ gardé et marqué déprécié : `deprecated` en OpenAPI, `@deprecated` en GraphQL).
 *
 * Les ajouts ne font rien échouer. Après un ajout : `php artisan vff:api-contract` (bin/php), puis relire le
 * diff du fichier. Un retrait voulu : le déprécier d'abord, l'annoncer dans docs/API-CHANGELOG.md, puis
 * `php artisan vff:api-contract --accept-removals`.
 */
class ApiContractTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, string> */
    private function current(): array
    {
        $rpc = fn (string $method) => $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => (object) []])->assertOk();

        return ApiContract::build(
            (array) $this->getJson('/docs/api.json')->assertOk()->json(),
            app(SchemaBuilder::class)->schema(),
            (array) $rpc('tools/list')->json('result.tools'),
            (array) $rpc('prompts/list')->json('result.prompts'),
        );
    }

    public function test_no_public_field_disappears_or_changes_type_without_deprecation(): void
    {
        $path = base_path(ApiContract::FILE);
        $this->assertFileExists($path, 'Instantané absent : php artisan vff:api-contract');
        $recorded = (array) json_decode((string) file_get_contents($path), true);
        $this->assertGreaterThan(500, count($recorded));

        $breaking = ApiContract::breaking($recorded, $this->current());

        $this->assertSame([], $breaking['removed'], "Retirés sans avoir été dépréciés (gardez-les avec `deprecated` / `@deprecated`, voir docs/API-CHANGELOG.md) :\n".implode("\n", $breaking['removed']));
        $this->assertSame([], $breaking['changed'], "Type changé (changement cassant, à annoncer dans docs/API-CHANGELOG.md puis `php artisan vff:api-contract --accept-removals`) :\n".implode("\n", $breaking['changed']));
    }

    public function test_the_detector_flags_removals_type_changes_and_ignores_deprecated_and_additions(): void
    {
        $recorded = [
            'rest field GET /x 200 a' => 'string',
            'rest field GET /x 200 b' => 'integer',
            'rest field GET /x 200 old' => 'string @deprecated',
            'gql Line.name' => 'String!',
            'gql Line.gone' => 'String',
        ];
        $current = [
            'rest field GET /x 200 a' => 'string',
            'rest field GET /x 200 b' => 'string',
            'rest field GET /x 200 new' => 'string',
            'gql Line.name' => 'String',
        ];

        $breaking = ApiContract::breaking($recorded, $current);

        $this->assertSame(['gql Line.gone'], $breaking['removed']);
        $this->assertSame(['rest field GET /x 200 b : integer => string', 'gql Line.name : String! => String'], $breaking['changed']);
        $this->assertSame(['removed' => [], 'changed' => []], ApiContract::breaking($recorded, $recorded));
    }
}

<?php

namespace Tests\Feature\Materials;

use App\Models\Material;
use App\Services\Materials\NotableUnits;
use App\Support\SourceRights;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\TestCase;

/**
 * Rames remarquables (database/data/material_notable_units.php) : fichier de données complet et sourcé, fiche,
 * API REST, GraphQL, outil MCP get_material et recherche par surnom (« Patrick » : rame 01 du TGV Sud-Est).
 */
class NotableUnitsTest extends TestCase
{
    use MakesGraphQLRequests;
    use RefreshDatabase;

    public function test_data_file_is_complete_and_every_source_is_declared(): void
    {
        $units = app(NotableUnits::class);
        $rights = app(SourceRights::class);
        foreach ($units->sources() as $id => $source) {
            $this->assertNotNull($rights->provider($source['provider']), "{$id} : fournisseur inconnu du registre des droits");
            $this->assertStringStartsWith('https://', $source['url']);
            if ($source['provider'] === 'tiers-cites') {
                $this->assertSame('tiers-cites', $rights->providerForUrl($source['url']), "{$id} : domaine absent de tiers-cites");
            }
        }
        foreach ($units->all() as $slug => $list) {
            foreach ($list as $unit) {
                $this->assertNotSame('', $unit['number']);
                foreach ($unit['facts'] as $fact) {
                    $this->assertArrayHasKey($fact['source'], $units->sources(), "{$slug} : source {$fact['source']} inconnue");
                    $this->assertContains($fact['precision'], ['day', 'month', 'year', null]);
                    $this->assertSame($fact['date'] === null, $fact['precision'] === null, "{$slug} {$fact['kind']} : date et précision vont ensemble");
                    foreach (NotableUnits::LANGS as $lang) {
                        $this->assertNotEmpty($fact['text'][$lang] ?? null, "{$slug} {$fact['kind']} : texte {$lang} manquant");
                        $this->assertDoesNotMatchRegularExpression('/[—–]|--/u', $fact['text'][$lang]);
                    }
                    // Citation courte : une phrase au plus, jamais un paragraphe recopié.
                    $this->assertLessThanOrEqual(80, mb_strlen((string) $fact['quote']));
                }
            }
        }
    }

    public function test_patrick_on_the_sheet_and_in_every_api(): void
    {
        Material::factory()->create(['slug' => 'tgv-sud-est', 'name' => 'TGV Sud-Est', 'type' => 'rame_tgv']);
        Material::factory()->create(['slug' => 'bb-15000', 'name' => 'BB 15000']);

        $this->get('/materials/tgv-sud-est')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('notable_units.0.number', '01')
            ->where('notable_units.0.nickname', 'Patrick')
            ->where('notable_units.0.facts.2.date', '1978-06-01')
            ->where('notable_units.0.facts.2.precision', 'month'));
        $this->get('/materials/bb-15000')->assertInertia(fn (AssertableInertia $page) => $page->where('notable_units', []));

        $unit = $this->getJson('/api/v1/materials/tgv-sud-est')->assertOk()->json('data.notable_units.0');
        $this->assertSame('Patrick', $unit['nickname']);
        $this->assertSame('du prénom du fils de son premier conducteur', $unit['facts'][0]['quote']);
        $this->assertSame('tiers-cites', $unit['facts'][0]['source']['provider']);
        $farewell = array_values(array_filter($unit['facts'], fn (array $f): bool => $f['kind'] === 'farewell_livery'))[0];
        $this->assertSame('France Bleu', $farewell['source']['reported_by']);
        $this->assertNotNull($unit['unknown']);

        $this->graphQL('{ material(slug: "tgv-sud-est") { notable_units(lang: "de") { number nickname unknown facts { kind date date_to precision text quote source { publisher url license_status } } } } }')
            ->assertJsonPath('data.material.notable_units.0.nickname', 'Patrick')
            ->assertJsonPath('data.material.notable_units.0.facts.7.date_to', '2020-03-01')
            ->assertJsonPath('data.material.notable_units.0.facts.0.text', 'Spitzname „Patrick“, nach dem Vornamen des Sohnes ihres ersten Triebfahrzeugführers.');

        $mcp = $this->mcp('get_material', ['material' => 'tgv-sud-est'])['material']['notable_units'][0];
        $this->assertSame('01', $mcp['number']);
        // Décision Q22 : aucune citation dans les réponses MCP.
        $this->assertArrayNotHasKey('quote', $mcp['facts'][0]);
    }

    public function test_search_by_nickname_finds_the_series(): void
    {
        Material::factory()->create(['slug' => 'tgv-sud-est', 'name' => 'TGV Sud-Est', 'type' => 'rame_tgv']);
        Material::factory()->create(['slug' => 'z-patrick-test', 'name' => 'Autre série']);

        foreach (['Patrick', 'TGV Patrick', 'patrick', 'Patr'] as $q) {
            $hit = $this->getJson('/api/v1/search?q='.rawurlencode($q))->assertOk()->json('materials.0');
            $this->assertSame('tgv-sud-est', $hit['slug'], $q);
            $this->assertSame(['number' => '01', 'nickname' => 'Patrick'], $hit['notable_unit']);
        }
        $this->assertSame([], $this->getJson('/api/v1/search?q=pa')->json('materials'));

        $this->graphQL('{ search(query: "TGV Patrick") { materials { slug notable_unit { number nickname } } } }')
            ->assertJsonPath('data.search.materials.0.slug', 'tgv-sud-est')
            ->assertJsonPath('data.search.materials.0.notable_unit.number', '01');
    }

    /** @return array<string, mixed> */
    private function mcp(string $tool, array $arguments): array
    {
        $result = $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => $tool, 'arguments' => (object) $arguments]])
            ->assertOk()->json('result');
        $this->assertFalse((bool) $result['isError'], $result['content'][0]['text']);

        return json_decode($result['content'][0]['text'], true);
    }
}

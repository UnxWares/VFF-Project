<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Support\WikipediaExcerpts;
use App\Models\Era;
use App\Models\Line;
use App\Models\LineEvent;
use App\Models\LineMaterial;
use App\Models\LineMaterialPeriod;
use App\Models\Material;
use App\Models\Station;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Invariant de la décision Q22 : aucun outil du serveur MCP ne renvoie une phrase recopiée de
 * Wikipédia (CC BY-SA 4.0), ni une clé `quote`, `excerpt`, `source_quote` ou `source_excerpt`.
 *
 * Chaque table qui garde une phrase d'article reçoit un texte témoin unique : description de la
 * ligne, citation d'un fait daté, phrase d'un fait de signalisation, preuve d'un lien de matériel
 * et phrase d'une période de circulation. Tous les outils de `tools/list` sont appelés avec les
 * arguments de ce mini-jeu de données : un nouvel outil est contrôlé sans modifier ce test. Les
 * outils qui servent ces données doivent répondre sans erreur, pour que le test ne soit pas vide
 * de sens.
 */
class VffMcpWikipediaExcerptInvariantTest extends TestCase
{
    use RefreshDatabase;

    /** Textes témoins, un par table qui garde une phrase d'article. */
    private const SENTINELS = [
        'lines.description' => 'Témoin description : la ligne de Lunéville à Saint-Dié longe la Meurthe.',
        'line_events.source_quote' => 'Témoin chronologie : la section est ouverte le 26 juillet 1864.',
        'line_signalling_events.source_excerpt' => 'Témoin signalisation : le block automatique est mis en service.',
        'line_materials.evidence' => 'Témoin matériel : des rames Corail tractées par des BB 15000.',
        'line_material_periods.source_excerpt' => 'Témoin période : les BB 15000 assurent les trains dès 1971.',
    ];

    /** Outils qui servent ces données : ils doivent répondre sans erreur. */
    private const SERVING_TOOLS = ['get_line', 'line_events', 'network_events', 'signalling_history', 'line_materials', 'get_material', 'search'];

    protected function setUp(): void
    {
        parent::setUp();
        Era::factory()->create(['year' => 1930, 'label' => 'Années 1930']);
        Era::factory()->current()->create(['year' => 2030, 'label' => 'Aujourd\'hui']);
        $line = Line::factory()->create(['slug' => 'rfn-067000', 'code' => '067000', 'name' => 'Ligne de Lunéville à Saint-Dié',
            'wikipedia_title' => 'Ligne de Lunéville à Saint-Dié', 'description' => self::SENTINELS['lines.description']]);
        Station::factory()->create(['slug' => 'luneville', 'name' => 'Lunéville', 'city' => 'Lunéville',
            'geom' => Point::makeGeodetic(48.59, 6.50), 'status' => 'active']);

        $revision = 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Lun%C3%A9ville_%C3%A0_Saint-Di%C3%A9&oldid=239088070';
        LineEvent::factory()->create(['line_id' => $line->id, 'type' => 'opening', 'event_date' => '1864-07-26', 'date_precision' => 'day',
            'description' => 'Ouverture de Lunéville à Saint-Dié', 'source' => 'wikipedia', 'source_url' => $revision,
            'source_quote' => self::SENTINELS['line_events.source_quote']]);
        DB::table('line_signalling_events')->insert([
            'line_id' => $line->id, 'system' => 'BAL', 'role' => 'block', 'event_date' => '1975-01-01', 'date_precision' => 'year',
            'scope' => 'unspecified', 'confidence' => 'high', 'origin' => 'text', 'source' => 'wikipedia',
            'source_title' => 'Ligne de Lunéville à Saint-Dié', 'source_revision' => 239088070, 'source_url' => $revision,
            'source_excerpt' => self::SENTINELS['line_signalling_events.source_excerpt'], 'fingerprint' => sha1('bal-1975'),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $material = Material::factory()->fromWikidata()->create(['slug' => 'bb-15000', 'name' => 'BB 15000',
            'type' => 'locomotive_electrique', 'in_service_from' => 1971]);
        $link = LineMaterial::create([
            'line_id' => $line->id, 'material_id' => $material->id, 'source' => 'wikipedia', 'confidence' => 'high', 'source_url' => $revision,
            'evidence' => [['kind' => 'wikipedia_line_article', 'article' => 'Ligne de Lunéville à Saint-Dié', 'revid' => 239088070,
                'section' => 'Matériel roulant', 'quote' => self::SENTINELS['line_materials.evidence'], 'url' => $revision]],
        ]);
        LineMaterialPeriod::create([
            'line_material_id' => $link->id, 'start_date' => '1971-01-01', 'start_precision' => 'year', 'confidence' => 'high',
            'origin' => 'line_article', 'source' => 'wikipedia', 'source_title' => 'Ligne de Lunéville à Saint-Dié',
            'source_revision' => 239088070, 'source_url' => $revision,
            'source_excerpt' => self::SENTINELS['line_material_periods.source_excerpt'], 'fingerprint' => sha1('bb-15000-1971'),
        ]);
    }

    public function test_no_mcp_tool_returns_a_wikipedia_sentence(): void
    {
        $tools = $this->mcp('tools/list')->json('result.tools');
        $this->assertGreaterThan(20, count($tools), 'tous les outils sont parcourus');

        $values = ['line' => 'rfn-067000', 'station' => 'luneville', 'material' => 'bb-15000', 'accident' => 'inconnu',
            'query' => 'Lunéville', 'q' => 'Lunéville', 'year' => 2030, 'era' => 2030, 'compare_year' => 1930,
            'from_year' => 1800, 'to_year' => 2030, 'lat' => 48.59, 'lng' => 6.5, 'lang' => 'fr'];
        // Arguments dont le sens dépend de l'outil : années pour compare_eras, gares pour historical_route.
        $overrides = ['compare_eras' => ['from' => 1930, 'to' => 2030], 'historical_route' => ['from' => 'luneville', 'to' => 'luneville']];

        $served = [];
        foreach ($tools as $tool) {
            $name = $tool['name'];
            $properties = array_keys($tool['inputSchema']['properties'] ?? []);
            $arguments = array_intersect_key(($overrides[$name] ?? []) + $values, array_flip($properties));
            $result = $this->mcp('tools/call', ['name' => $name, 'arguments' => (object) $arguments])->json('result');
            $text = (string) $result['content'][0]['text'];

            foreach (self::SENTINELS as $table => $sentence) {
                $this->assertStringNotContainsString($sentence, $text, "MCP {$name} : phrase de Wikipédia ({$table})");
            }
            $data = json_decode($text, true);
            $this->assertSame([], is_array($data) ? self::excerptKeys($data) : [], "MCP {$name} : clé d'extrait");
            if (! $result['isError']) {
                $served[] = $name;
            }
        }
        $this->assertSame([], array_values(array_diff(self::SERVING_TOOLS, $served)), 'les outils qui servent ces données répondent');
    }

    public function test_the_rest_api_keeps_its_cited_sentences(): void
    {
        // Le garde-fou ne concerne que le serveur MCP : l'API REST cite la phrase avec sa licence.
        $this->getJson('/api/v1/materials/bb-15000')->assertOk()
            ->assertJsonPath('data.lines.0.evidence.0.quote', self::SENTINELS['line_materials.evidence']);
    }

    /**
     * Chemins des clés d'extrait présentes dans une réponse décodée.
     *
     * @param  array<array-key, mixed>  $data
     * @return list<string>
     */
    private static function excerptKeys(array $data, string $path = ''): array
    {
        $found = [];
        foreach ($data as $key => $value) {
            if (is_string($key) && in_array($key, WikipediaExcerpts::KEYS, true)) {
                $found[] = $path.$key;
            }
            if (is_array($value)) {
                $found = [...$found, ...self::excerptKeys($value, $path.$key.'.')];
            }
        }

        return $found;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return TestResponse<Response>
     */
    private function mcp(string $method, array $params = []): TestResponse
    {
        return $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => (object) $params])->assertOk();
    }
}

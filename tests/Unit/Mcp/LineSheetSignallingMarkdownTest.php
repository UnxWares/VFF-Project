<?php

namespace Tests\Unit\Mcp;

use App\Mcp\Resources\LineSheetResource;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

/** Fiche Markdown du MCP : mises en service datées de la signalisation, rôle, portion et source. */
class LineSheetSignallingMarkdownTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function sheet(array $history): array
    {
        return [
            'name' => 'Ligne de A à B', 'code' => '752000', 'max_speed_kmh' => null, 'electrification' => null,
            'length' => ['trace_km' => 10.0, 'in_service_today_km' => 10.0],
            'signalling' => ['block_systems_km' => [], 'history' => $history],
            'facts' => [], 'phases' => [], 'events' => [],
        ];
    }

    public function test_dated_facts_keep_their_role_portion_and_source(): void
    {
        App::setLocale('fr');
        $md = LineSheetResource::markdown(self::sheet([
            ['system' => 'TVM-430', 'role' => 'cab', 'date' => '1994-07-03', 'date_precision' => 'day', 'scope' => 'section',
                'section' => ['from' => 'Saint-Quentin-Fallavier', 'to' => 'Valence', 'pk_start_m' => 423442, 'pk_end_m' => 493427],
                'source' => ['provider' => 'manual', 'title' => 'X', 'url' => 'https://a.test/1', 'documents' => [['publisher' => 'SNCF Réseau', 'title' => 'X', 'url' => 'https://a.test/1', 'published' => null]]]],
            ['system' => 'KVB', 'role' => 'speed_control', 'date' => '1990-01-01', 'date_precision' => 'year', 'scope' => 'whole_line', 'section' => null,
                'source' => ['provider' => 'wikipedia', 'title' => 'Ligne', 'url' => 'https://fr.wikipedia.org/w/index.php?oldid=1', 'documents' => []]],
        ]), ['notice' => '', 'sources' => []], 'https://vff.test/lines/x');

        $this->assertStringContainsString('## Signalisation et protection au fil du temps', $md);
        $this->assertStringContainsString('3 juillet 1994** : Signalisation en cabine', $md);
        $this->assertStringContainsString('TVM-430, De Saint-Quentin-Fallavier à Valence (PK 423+442 à 493+427) [SNCF Réseau](https://a.test/1)', $md);
        // Le KVB est un contrôle de vitesse, jamais un cantonnement ; année seule, sans jour inventé.
        $this->assertStringContainsString('**1990** : Contrôle de vitesse', $md);
        $this->assertStringContainsString('KVB, Sur toute la ligne [Wikipédia](https://fr.wikipedia.org/w/index.php?oldid=1)', $md);
    }

    public function test_no_section_without_history(): void
    {
        $md = LineSheetResource::markdown(self::sheet([]), ['notice' => '', 'sources' => []], 'https://vff.test/lines/x');
        $this->assertStringNotContainsString('## Signalisation et protection', $md);
    }
}

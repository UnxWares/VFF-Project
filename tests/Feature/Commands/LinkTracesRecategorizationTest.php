<?php

namespace Tests\Feature\Commands;

use App\Console\Commands\LinkTraces;
use App\Console\Commands\SeedOffNetworkLines;
use App\Models\Line;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * vff:link-traces, étape 4 (recatégorisation d'après le calque source) : les lignes créées
 * par vff:seed-off-network-lines gardent la catégorie de la liste hors RFN sourcée.
 */
class LinkTracesRecategorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_rule_protects_only_off_network_seed(): void
    {
        $this->assertFalse(LinkTraces::recategorizable(SeedOffNetworkLines::CREATED_BY));
        $this->assertTrue(LinkTraces::recategorizable(null));
        $this->assertTrue(LinkTraces::recategorizable('vff:link-traces'));
        $this->assertTrue(LinkTraces::recategorizable('vff:import-lignes-oubliees'));
    }

    public function test_protected_ids_are_read_from_alt_names(): void
    {
        $seeded = Line::factory()->create(['network' => 'particulier',
            'alt_names' => ['created_by' => SeedOffNetworkLines::CREATED_BY]]);
        $plain = Line::factory()->create(['network' => 'secondaire', 'alt_names' => ['created_by' => 'vff:link-traces']]);
        $bare = Line::factory()->create(['network' => 'principal']);

        $this->assertSame([$seeded->id], LinkTraces::protectedFromRecategorization([$seeded->id, $plain->id, $bare->id]));
        $this->assertSame([], LinkTraces::protectedFromRecategorization([]));
    }
}

<?php

namespace Tests\Feature\Web;

use App\Models\Line;
use App\Models\LineMaterial;
use App\Models\Material;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Fiche /materials/{slug} : les propriétés historiques de la page restent les mêmes, les
 * caractéristiques sourcées et la preuve des liens sont ajoutées pour la fiche enrichie.
 */
class MaterialSheetPropsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_sheet_keeps_its_props_and_adds_sourced_fields_and_evidence(): void
    {
        $material = Material::factory()->fromWikidata()->create(['slug' => 'bb-15000', 'name' => 'BB 15000', 'type' => 'locomotive_electrique']);
        $lines = Line::factory()->count(22)->create();
        foreach ($lines as $i => $line) {
            LineMaterial::create([
                'line_id' => $line->id, 'material_id' => $material->id, 'source' => 'wikipedia', 'confidence' => 'medium',
                'source_url' => "https://fr.wikipedia.org/w/index.php?oldid={$i}",
                'evidence' => [['kind' => 'wikipedia_line_article', 'section' => 'Exploitation', 'quote' => "Phrase {$i}.", 'url' => "https://fr.wikipedia.org/w/index.php?oldid={$i}"]],
            ]);
        }

        $this->get('/materials/bb-15000')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Materials/Show')
                ->where('material.slug', 'bb-15000')
                ->where('material.type', 'locomotive_electrique')
                ->where('material.type_label', 'Locomotive électrique')
                ->where('material.max_speed_kmh', 160)
                ->where('material.image.license', 'CC BY-SA 4.0')
                ->has('material.field_sources')
                ->has('lines', 22)
                ->where('lines_total', 22)
                ->has('lines.0', fn (Assert $line) => $line
                    ->hasAll(['slug', 'name', 'code', 'source_url', 'kind', 'article'])
                    ->where('confidence', 'medium')
                    ->where('section', 'Exploitation')
                    ->whereType('quote', 'string')));
    }
}

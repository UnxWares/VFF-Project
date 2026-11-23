<?php

namespace Tests\Feature\Materials;

use App\Models\Line;
use App\Models\LineMaterial;
use App\Models\Material;
use App\Services\Materials\MaterialRelationNotes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Faits sourcés sur les relations (database/data/material_relation_notes.php, MaterialRelationNotes) et
 * section « Matériel roulant » de la fiche ligne : relations sans date, faits, vignette et crédit de la photo,
 * servis par la page, l'API REST, GraphQL et le serveur MCP. Le Régiolis au B100 sur Paris - Granville sert
 * d'exemple réel.
 */
class MaterialRelationNotesTest extends TestCase
{
    use RefreshDatabase;

    private Line $line;

    private Material $regiolis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->line = Line::factory()->create(['code' => '405000', 'name' => 'Ligne d\'Argentan à Granville', 'slug' => 'rfn-405000']);
        $this->regiolis = Material::factory()->create([
            'slug' => 'regiolis', 'name' => 'Régiolis', 'wikidata_id' => 'Q432769', 'type' => 'bimode', 'in_service_from' => 2013, 'in_service_to' => null, 'service_status' => 'in_service',
            'image' => [
                'provider' => 'commons', 'file' => 'File:B84549 à Montreuil-Bellay (2) par Cramos.JPG',
                'thumb_url' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/a0/B84549.JPG/500px-B84549.JPG',
                'display_url' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/a0/B84549.JPG/1280px-B84549.JPG',
                'source_url' => 'https://commons.wikimedia.org/wiki/File:B84549.JPG', 'width' => 4000, 'height' => 3000,
                'author' => 'Cramos', 'license' => 'CC BY-SA 4.0', 'license_url' => 'https://creativecommons.org/licenses/by-sa/4.0',
                'rights_status' => 'open_license',
            ],
        ]);
    }

    /** @param list<array{label: string, from: string, to: string, kind?: string}> $routes */
    private function link(Material $material, array $routes): LineMaterial
    {
        return LineMaterial::query()->create([
            'line_id' => $this->line->id, 'material_id' => $material->id, 'source' => 'wikipedia', 'confidence' => 'medium',
            'evidence' => array_map(fn (array $r): array => [
                'kind' => 'wikipedia_material_article', 'article' => $material->name, 'revid' => 1, 'section' => 'Dessertes', 'quote' => $r['label'],
                'route' => ['kind' => $r['kind'] ?? 'relation', 'label' => $r['label'], 'from' => ['id' => null, 'name' => $r['from']], 'to' => ['id' => null, 'name' => $r['to']], 'service' => 'TER'],
            ], $routes),
        ]);
    }

    public function test_the_b100_note_goes_to_the_paris_granville_relation_only(): void
    {
        $link = $this->link($this->regiolis, [
            ['label' => 'Paris-Montparnasse - Granville', 'from' => 'Paris-Montparnasse-Pasteur', 'to' => 'Granville', 'kind' => 'itinerary'],
            ['label' => 'Paris - Pontorson-Mont-Saint-Michel', 'from' => 'Paris-Vaugirard', 'to' => 'Pontorson-Mont-St-Michel'],
        ]);

        $relations = collect($link->relations())->keyBy('label');
        $note = $relations['Paris-Montparnasse - Granville']['notes'][0];
        $this->assertSame(['fuel', 'B100', 'rapeseed', '2021-04-01', 'month', '2023-01-17'],
            [$note['kind'], $note['fuel'], $note['feedstock'], $note['since'], $note['since_precision'], $note['reported']]);
        $this->assertSame('tiers-cites', $note['sources'][0]['provider'], 'communiqué Oleo100 déclaré dans source_rights.php');
        $this->assertSame('tiers-cites', $note['sources'][1]['provider'], 'Banque des Territoires déclarée');
        $this->assertSame([], $relations['Paris - Pontorson-Mont-Saint-Michel']['notes'], 'la source ne parle que de Paris - Granville');
    }

    public function test_a_note_needs_the_series_the_line_and_both_ends(): void
    {
        $other = Material::factory()->create(['slug' => 'x-72500', 'name' => 'X 72500', 'wikidata_id' => 'Q3570526']);
        $notes = new MaterialRelationNotes([[
            'material' => 'Q432769', 'relation' => ['Paris', 'Granville'], 'lines' => ['405000'], 'kind' => 'fuel', 'fuel' => 'B100',
            'sources' => [['title' => 'T', 'publisher' => 'P', 'date' => '2023-01-17', 'url' => 'https://oleo100.com/x.pdf']],
        ]]);
        $paris = ['from' => ['name' => 'Paris-Montparnasse'], 'to' => ['name' => 'Granville']];

        $this->assertCount(1, $notes->for($this->line->id, $this->regiolis->id, $paris));
        $this->assertCount(1, $notes->for($this->line->id, $this->regiolis->id, ['from' => ['name' => 'Granville'], 'to' => ['name' => 'Paris']]), 'dans les deux sens');
        $this->assertSame([], $notes->for($this->line->id, $other->id, $paris), 'autre série');
        $this->assertSame([], $notes->for($this->line->id + 1, $this->regiolis->id, $paris), 'autre ligne');
        $this->assertSame([], $notes->for($this->line->id, $this->regiolis->id, ['from' => ['name' => 'Parisot'], 'to' => ['name' => 'Granville']]), '« Paris » ne désigne pas Parisot');
    }

    public function test_the_line_sheet_shows_undated_relations_notes_and_the_photo_everywhere(): void
    {
        $this->link($this->regiolis, [['label' => 'Paris-Montparnasse - Granville', 'from' => 'Paris-Montparnasse-Pasteur', 'to' => 'Granville', 'kind' => 'itinerary']]);
        $x2800 = Material::factory()->create(['slug' => 'x-2800', 'name' => 'X 2800', 'wikidata_id' => 'Q3570509', 'image' => null, 'in_service_from' => 1957, 'in_service_to' => 1993, 'service_status' => null]);
        $this->link($x2800, [['label' => 'Argentan - Granville', 'from' => 'Argentan', 'to' => 'Granville']]);

        // Page : relations sans période, fait sourcé, vignette de 330 px et crédit ; pas de photo : null.
        $this->withCookie('locale', 'fr')->getWithDeferredProps('/lines/rfn-405000')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('materials.0.name', 'Régiolis')
                ->where('materials.0.relations.0.label', 'Paris-Montparnasse - Granville')
                ->where('materials.0.relations.0.notes.0.fuel', 'B100')
                ->where('materials.0.image.url', 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/a0/B84549.JPG/330px-B84549.JPG')
                ->where('materials.0.image.width', 330)
                ->where('materials.0.image.height', 248)
                ->where('materials.0.image.author', 'Cramos')
                ->where('materials.1.relations.0.label', 'Argentan - Granville')
                ->where('materials.1.relations.0.periods', [])
                ->where('materials.1.image', null)
                ->etc());

        // API REST : fiche de la ligne et liste du matériel de la ligne.
        $this->getJson('/api/v1/lines/rfn-405000')->assertOk()
            ->assertJsonPath('data.materials.0.relations.0.notes.0.since', '2021-04-01')
            ->assertJsonPath('data.materials.0.image.license', 'CC BY-SA 4.0');
        $this->getJson('/api/v1/lines/rfn-405000/materials')->assertOk()
            ->assertJsonPath('data.0.relations.0.notes.0.feedstock', 'rapeseed')
            ->assertJsonPath('data.0.material.image.source_url', 'https://commons.wikimedia.org/wiki/File:B84549.JPG');

        // GraphQL : notes des relations, relations et vignette de la fiche.
        $this->postJson('/graphql', ['query' => '{ line(slug: "rfn-405000") { sheet { materials { name relations { label notes { fuel since sources { url provider } } } image { url author license } } } } }'])
            ->assertOk()
            ->assertJsonPath('data.line.sheet.materials.0.relations.0.notes.0.fuel', 'B100')
            ->assertJsonPath('data.line.sheet.materials.0.relations.0.notes.0.sources.0.provider', 'tiers-cites')
            ->assertJsonPath('data.line.sheet.materials.0.image.author', 'Cramos')
            ->assertJsonPath('data.line.sheet.materials.1.image', null);

        // Serveur MCP : `line_materials` donne les notes et la vignette.
        $result = $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'line_materials', 'arguments' => ['line' => 'rfn-405000']]])
            ->assertOk()->json('result');
        /** @var list<array<string, mixed>> $served */
        $served = json_decode($result['content'][0]['text'], true)['materials'];
        $data = array_combine(array_column(array_column($served, 'material'), 'name'), $served);
        $this->assertSame('B100', $data['Régiolis']['relations'][0]['notes'][0]['fuel']);
        $this->assertSame('Cramos', $data['Régiolis']['material']['image']['author']);

        // Fiche de la série : la note suit la relation.
        $this->withCookie('locale', 'fr')->get('/materials/regiolis')->assertOk()
            ->assertInertia(fn ($page) => $page->where('lines.0.relations.0.notes.0.fuel', 'B100')->etc());
    }

    public function test_a_thumbnail_keeps_small_originals_and_fixes_its_size(): void
    {
        $this->assertNull(Material::thumbnail(null));
        $this->assertNull(Material::thumbnail(['thumb_url' => '']));
        $small = Material::thumbnail(['thumb_url' => 'https://upload.wikimedia.org/a/320px-x.jpg', 'width' => 320, 'height' => 160]);
        $this->assertSame(['https://upload.wikimedia.org/a/320px-x.jpg', 320, 160], [$small['url'], $small['width'], $small['height']]);
        $unknown = Material::thumbnail(['thumb_url' => 'https://upload.wikimedia.org/a/500px-x.jpg']);
        $this->assertSame([330, 220, null], [$unknown['width'], $unknown['height'], $unknown['author']], 'proportions 3:2 sans dimensions connues');

        // Photo à écarter : trop petite, plan, dessin, vectorielle, relue et écartée ; vue de profil : entière.
        $this->assertNull(Material::thumbnail(['thumb_url' => 'https://x/a.jpg', 'file' => 'File:SNCF CC 25000-drawing.jpg', 'width' => 300, 'height' => 106]));
        $this->assertNull(Material::thumbnail(['thumb_url' => 'https://x/a.jpg', 'file' => 'File:X 5800-plan.jpg', 'width' => 833, 'height' => 304]));
        $this->assertNull(Material::thumbnail(['thumb_url' => 'https://x/a.jpg', 'file' => 'File:P1190526 - Intérieur de la voiture.JPG', 'width' => 1920, 'height' => 2560]));
        $this->assertNull(Material::thumbnail(['thumb_url' => 'https://x/a.jpg', 'file' => 'File:BB 1002.svg', 'width' => 1059, 'height' => 473]));
        $this->assertNull(Material::thumbnail(['thumb_url' => 'https://x/a.jpg', 'file' => 'File:X 76500 Obernai.jpg', 'width' => 2000, 'height' => 1500]));
        $this->assertNull(Material::thumbnail(['thumb_url' => 'https://x/a.jpg', 'file' => 'File:Petite.jpg', 'width' => 250, 'height' => 180]));
        $wide = Material::thumbnail(['thumb_url' => 'https://x/500px-a.jpg', 'file' => 'File:SNCF CC 80001.jpg', 'width' => 583, 'height' => 199]);
        $this->assertSame('contain', $wide['fit'], 'profil de la CC 80001 : montré entier, pas un bout de caisse');
        $this->assertSame('cover', Material::thumbnail(['thumb_url' => 'https://x/500px-a.jpg', 'file' => 'File:Planche-à-voile BB 15000.jpg', 'width' => 1500, 'height' => 1000])['fit'],
            '« Planche » n\'est pas « plan »');
    }
}

<?php

namespace Tests\Feature\Api;

use App\Enums\AccidentKind;
use App\Models\Accident;
use App\Models\AccidentLine;
use App\Models\Line;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Heurts de personnes : gardés en base pour le recoupement des sources, jamais publiés (décision
 * du 19/11/2026, sujet délicat sans intérêt encyclopédique pour les lignes). Aucune porte
 * publique ne les montre : API REST et GeoJSON, GraphQL, pages, fiche de ligne, filtres.
 */
class HiddenAccidentsTest extends TestCase
{
    use RefreshDatabase;

    private Line $line;

    protected function setUp(): void
    {
        parent::setUp();
        $this->line = Line::factory()->create(['slug' => 'rfn-420000', 'code' => '420000', 'name' => 'Ligne de Paris-Montparnasse à Brest']);
        $shown = Accident::factory()->on('2006-11-09')->create(['slug' => '2006-11-09-chaville', 'kind' => 'derailment', 'deaths' => 0]);
        $hidden = Accident::factory()->on('2006-11-10')->create(['slug' => '2006-11-10-chaville', 'kind' => 'person_struck', 'deaths' => 1]);
        foreach ([$shown, $hidden] as $accident) {
            AccidentLine::query()->forceCreate(['accident_id' => $accident->id, 'line_id' => $this->line->id, 'is_primary' => true,
                'method' => 'proximity', 'confidence' => 'high', 'score' => 0.9]);
        }
    }

    public function test_the_hidden_kinds_are_the_person_struck_ones(): void
    {
        $this->assertSame(['person_struck'], AccidentKind::HIDDEN);
        $this->assertSame(2, Accident::query()->withoutGlobalScopes()->count(), 'la fiche reste en base');
        $this->assertSame(1, Accident::query()->count());
    }

    public function test_rest_list_record_and_geojson_do_not_show_it(): void
    {
        $this->getJson('/api/v1/accidents')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.slug', '2006-11-09-chaville');
        $this->getJson('/api/v1/accidents?kind=person_struck')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/accidents/2006-11-10-chaville')->assertNotFound();
        $this->getJson('/api/v1/accidents/2006-11-09-chaville')->assertOk();
        $this->getJson('/api/v1/accidents.geojson')->assertOk()->assertJsonCount(1, 'features');
    }

    public function test_graphql_does_not_show_it(): void
    {
        $response = $this->postJson('/graphql', ['query' => '{
            accidents(first: 10) { paginatorInfo { total } data { slug } }
            accident(slug: "2006-11-10-chaville") { slug }
            line(slug: "rfn-420000") { accidents { slug } }
        }'])->assertOk();

        $this->assertNull($response->json('errors'));
        $this->assertSame(1, $response->json('data.accidents.paginatorInfo.total'));
        $this->assertNull($response->json('data.accident'));
        $this->assertSame([['slug' => '2006-11-09-chaville']], $response->json('data.line.accidents'));
    }

    public function test_pages_filters_and_line_section_do_not_show_it(): void
    {
        $this->get('/accidents')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('accidents.total', 1)
            ->missing('facets.kinds.person_struck')
            ->where('facets.lines.0.count', 1)
            ->where('facets.decades', [['decade' => 2000, 'count' => 1]]));
        $this->get('/accidents/2006-11-10-chaville')->assertNotFound();
        $this->get('/lines/rfn-420000')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('accidents', 1)
            ->where('accidents.0.slug', '2006-11-09-chaville'));
    }
}

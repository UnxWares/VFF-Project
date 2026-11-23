<?php

namespace Tests\Feature\Api;

use App\Enums\AccidentKind;
use App\Models\Accident;
use App\Models\AccidentLine;
use App\Models\AccidentSource;
use App\Models\Line;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Heurts de personnes (`person_struck`) : montrés depuis la décision du 22/11/2026, mais seulement s'ils reposent sur une
 * enquête officielle (source `bea-tt`) ou sur un contexte sourcé relu ; sinon ils restent en base pour le recoupement des
 * sources et aucune porte publique ne les montre : API REST et GeoJSON, GraphQL, pages, fiche de ligne, filtres.
 * Une fiche alias (doublon fusionné) n'est jamais montrée mais son ancienne adresse redirige vers la fiche retenue.
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

    private function addBeaTtSource(Accident $accident): void
    {
        AccidentSource::query()->forceCreate([
            'accident_id' => $accident->id, 'provider' => 'bea-tt', 'external_id' => 'bea-'.$accident->id, 'role' => 'report',
            'title' => 'Rapport', 'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/rapport-'.$accident->id, 'licence' => 'Licence Ouverte 2.0',
            'retrieved_at' => now(),
        ]);
    }

    public function test_person_struck_is_a_known_nature_published_only_with_a_sourced_basis(): void
    {
        $this->assertSame(['person_struck'], AccidentKind::SOURCED_ONLY);
        $this->assertContains('person_struck', AccidentKind::values());
        $this->assertSame(2, Accident::query()->withoutGlobalScopes()->count(), 'la fiche reste en base');
        $this->assertSame(1, Accident::query()->count());
    }

    public function test_a_person_struck_record_with_a_bea_tt_source_or_a_context_is_published(): void
    {
        $bea = Accident::query()->withoutGlobalScopes()->where('slug', '2006-11-10-chaville')->sole();
        $this->addBeaTtSource($bea);
        $this->assertSame(2, Accident::query()->count());

        $other = Accident::factory()->on('1910-09-20')->create(['slug' => '1910-09-20-breteuil', 'kind' => 'person_struck']);
        $this->assertSame(2, Accident::query()->count(), 'une liste seule ne suffit pas');
        $other->forceFill(['context_checked_on' => '2026-11-22'])->save();
        $this->assertSame(3, Accident::query()->count());

        $this->getJson('/api/v1/accidents?kind=person_struck')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/accidents/1910-09-20-breteuil')->assertOk()->assertJsonPath('data.kind', 'person_struck');
        $this->getJson('/api/v1/accidents.geojson')->assertOk();
        $this->get('/accidents')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('accidents.total', 3)
            ->where('facets.kinds.person_struck', 2));
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
        $this->getWithDeferredProps('/lines/rfn-420000')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('accidents', 1)
            ->where('accidents.0.slug', '2006-11-09-chaville'));
    }

    public function test_the_old_address_of_a_merged_duplicate_redirects_to_the_kept_record(): void
    {
        $kept = Accident::query()->where('slug', '2006-11-09-chaville')->sole();
        Accident::factory()->on('2006-11-11')->create(['slug' => '2006-11-11-doublon', 'kind' => 'derailment', 'alias_of_id' => $kept->id]);

        $this->get('/accidents/2006-11-11-doublon')->assertStatus(301)->assertRedirect('/accidents/2006-11-09-chaville');
        $this->getJson('/api/v1/accidents/2006-11-11-doublon')->assertStatus(301)
            ->assertRedirect('/api/v1/accidents/2006-11-09-chaville');
        $this->get('/accidents/2006-11-11-inconnu')->assertNotFound();
        $this->getJson('/api/v1/accidents/2006-11-11-inconnu')->assertNotFound();
        // Fiche retenue non publiée : pas de redirection vers une page absente.
        $hidden = Accident::query()->withoutGlobalScopes()->where('slug', '2006-11-10-chaville')->sole();
        Accident::factory()->on('2006-11-12')->create(['slug' => '2006-11-12-doublon', 'kind' => 'derailment', 'alias_of_id' => $hidden->id]);
        $this->get('/accidents/2006-11-12-doublon')->assertNotFound();
        $this->assertNull(Accident::redirectSlugFor('2006-11-09-chaville'));
    }
}

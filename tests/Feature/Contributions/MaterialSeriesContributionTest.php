<?php

namespace Tests\Feature\Contributions;

use App\Models\Contribution;
use App\Models\Material;
use App\Models\User;
use App\Services\Contributions\MaterialSeriesContributions;
use App\Services\Materials\MaterialImporter;
use App\Services\Materials\MaterialTractionRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ActsAsRoles;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * Proposer une série de matériel (/contribute/series) : nouvelle série ou correction, identifiant
 * Wikidata vérifié (élément inconnu refusé, élément fusionné suivi), doublons refusés avec
 * l'invitation à corriger la fiche, publication avec la provenance de chaque champ, respectée par
 * l'import de Wikidata.
 */
class MaterialSeriesContributionTest extends TestCase
{
    use ActsAsRoles;
    use BuildsRailNetwork;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRailNetwork();
        $this->withCookie('locale', 'fr');
        Http::preventStrayRequests();
        Http::fake(['www.wikidata.org/*' => function (Request $request) {
            $id = (string) ($request->data()['ids'] ?? '');

            return match ($id) {
                'Q3574398' => Http::response(['entities' => ['Q3574398' => [
                    'id' => 'Q3574398',
                    'labels' => ['fr' => ['value' => 'Z 1200']],
                    'descriptions' => ['fr' => ['value' => 'série d’automotrices électriques']],
                    'claims' => ['P31' => [['mainsnak' => ['datavalue' => ['value' => ['id' => 'Q811704']]]]]],
                ]]]),
                // Élément fusionné : wbgetentities suit la redirection vers l'élément final.
                'Q99000001' => Http::response(['entities' => ['Q3574398' => ['id' => 'Q3574398', 'labels' => [], 'claims' => ['P31' => [['mainsnak' => ['datavalue' => ['value' => ['id' => 'Q811704']]]]]]]]]),
                'Q4000000' => Http::response(['error' => ['code' => 'unknown-error', 'info' => 'Serveur occupé']], 200),
                default => Http::response(['error' => ['code' => 'no-such-entity', 'info' => "Could not find an entity with the ID \"{$id}\"."]]),
            };
        }]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function newSeries(array $overrides = []): array
    {
        return [
            'mode' => 'new',
            'name' => 'Z 1200',
            'type' => 'automotrice',
            'electrification' => ['dc_750'],
            'gauge_mm' => 1435,
            'builder' => 'Blanc-Misseron, Thomson-Houston',
            'in_service_from' => 1913,
            'in_service_to' => 1976,
            'wikidata_id' => 'https://www.wikidata.org/wiki/Q3574398',
            'source_kind' => 'web',
            'source_title' => 'Z 1200, Wikipédia',
            'source_url' => 'https://fr.wikipedia.org/wiki/Z_1200',
            ...$overrides,
        ];
    }

    private function submit(User $author, array $data): Contribution
    {
        $this->actingAs($author)->post('/contribute/series', $data)->assertRedirect()->assertSessionHasNoErrors();

        return Contribution::query()->latest('id')->firstOrFail();
    }

    private function publish(Contribution $contribution): void
    {
        $this->actingAs($this->member('validator'))->post("/validate/{$contribution->id}/approve")->assertSessionHas('success');
        $this->actingAs($this->member('validator'))->post("/validate/{$contribution->id}/approve")->assertSessionHas('success');
    }

    /** Le message d'erreur du champ (session de la dernière requête) vérifie la condition. */
    private function assertErrorMatches(string $field, \Closure $check): void
    {
        $message = (string) session('errors')?->first($field);
        $this->assertTrue($check($message), "Message du champ {$field} : {$message}");
    }

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get('/contribute/series')->assertRedirect('/login');
        $this->post('/contribute/series', $this->newSeries())->assertRedirect('/login');
        $this->assertSame(0, Contribution::count());
    }

    public function test_the_correction_form_loads_the_current_values(): void
    {
        $this->material->update(['gauge_mm' => 1435, 'electrification' => [['label' => 'électrification ferroviaire 1500 V continu', 'wikidata_id' => 'Q21253457']]]);

        $this->actingAs($this->member())->get("/contribute/series?material={$this->material->slug}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Contribute/ProposeSeries')
                ->where('material.slug', $this->material->slug)
                ->where('material.electrification', ['dc_1500'])
                ->where('material.gauges', [1435])
                ->where('systems', array_keys(MaterialSeriesContributions::ELECTRIFICATION)));
    }

    /** @return array<string, array{0: array<string, mixed>, 1: string}> */
    public static function invalidProposals(): array
    {
        return [
            'mode inconnu' => [['mode' => 'fusion'], 'mode'],
            'sans nom' => [['name' => ''], 'name'],
            'sans catégorie' => [['type' => ''], 'type'],
            'catégorie inconnue' => [['type' => 'soucoupe'], 'type'],
            'système inconnu' => [['electrification' => ['dc_1200']], 'electrification.0'],
            'écartement hors liste' => [['gauge_mm' => 1067], 'gauge_mm'],
            'année trop ancienne' => [['in_service_from' => 1700], 'in_service_from'],
            'retrait avant la mise en service' => [['in_service_to' => 1900], 'in_service_to'],
            'identifiant illisible' => [['wikidata_id' => 'Z1200'], 'wikidata_id'],
            'source sans titre' => [['source_title' => ''], 'source_title'],
        ];
    }

    /** @param  array<string, mixed>  $overrides */
    #[DataProvider('invalidProposals')]
    public function test_an_invalid_proposal_is_refused_with_the_field_in_error(array $overrides, string $field): void
    {
        $this->actingAs($this->member())->from('/contribute/series')
            ->post('/contribute/series', $this->newSeries($overrides))
            ->assertSessionHasErrors($field);
        $this->assertSame(0, Contribution::count());
    }

    public function test_an_unknown_wikidata_item_is_refused(): void
    {
        $this->actingAs($this->member())->from('/contribute/series')
            ->post('/contribute/series', $this->newSeries(['wikidata_id' => 'Q999999999']))
            ->assertSessionHasErrors('wikidata_id');
        $this->assertErrorMatches('wikidata_id', fn (string $m) => str_contains($m, 'Q999999999 n’existe pas'));
        $this->assertSame(0, Contribution::count());
    }

    public function test_wikidata_unreachable_is_said_plainly(): void
    {
        $this->actingAs($this->member())->from('/contribute/series')
            ->post('/contribute/series', $this->newSeries(['wikidata_id' => 'Q4000000']))
            ->assertSessionHasErrors('wikidata_id');
        $this->assertErrorMatches('wikidata_id', fn (string $m) => str_contains($m, 'ne répond pas'));
    }

    public function test_a_merged_item_takes_its_final_identifier(): void
    {
        $contribution = $this->submit($this->member(), $this->newSeries(['wikidata_id' => 'Q99000001']));

        $this->assertSame('Q3574398', $contribution->payload['values']['wikidata_id']);
    }

    public function test_a_series_already_in_vff_is_refused_with_an_invitation_to_correct_it(): void
    {
        Material::factory()->create(['slug' => 'z-1200', 'name' => 'Z 1200 (Paris-Orsay)', 'wikidata_id' => 'Q3574398']);

        $this->actingAs($this->member())->from('/contribute/series')
            ->post('/contribute/series', $this->newSeries(['name' => 'Automotrices des Moulineaux']))
            ->assertSessionHasErrors('wikidata_id');
        $this->assertErrorMatches('wikidata_id', fn (string $m) => str_contains($m, 'Z 1200 (Paris-Orsay)') && str_contains($m, 'correction'));

        $this->actingAs($this->member())->from('/contribute/series')
            ->post('/contribute/series', $this->newSeries(['name' => 'z 1200', 'wikidata_id' => '']))
            ->assertSessionHasErrors('name');
        $this->assertErrorMatches('name', fn (string $m) => str_contains($m, 'correction'));

        $this->actingAs($this->member())->getJson('/contribute/series/wikidata?qid=Q3574398')
            ->assertOk()
            ->assertJsonPath('item.label', 'Z 1200')
            ->assertJsonPath('existing.slug', 'z-1200');
        $this->assertSame(0, Contribution::count());
    }

    public function test_a_new_series_is_published_with_the_provenance_of_each_field(): void
    {
        $contribution = $this->submit($this->member(), $this->newSeries());
        $this->assertNull($contribution->target_id);
        $this->assertSame('create', $contribution->action->value);

        $this->publish($contribution);

        $contribution->refresh();
        $material = Material::query()->where('wikidata_id', 'Q3574398')->firstOrFail();
        $this->assertSame([Material::class, $material->id], [$contribution->target_type, (int) $contribution->target_id]);
        $this->assertSame(['z-1200', 'Z 1200', 'automotrice', 'contrib'], [$material->slug, $material->name, $material->getRawOriginal('type'), $material->source]);
        $this->assertSame([1913, 1976, 1435], [$material->in_service_from, $material->in_service_to, $material->gauge_mm]);
        $this->assertSame(['Blanc-Misseron', 'Thomson-Houston'], $material->builderNames());
        $this->assertSame([750], MaterialTractionRule::voltages($material->electrification), 'libellé lu par la règle d’électrification');
        $this->assertEquals([['label' => 'écartement standard', 'mm' => 1435, 'wikidata_id' => 'Q1999572']], $material->gauges);
        $this->assertEquals(['source' => 'contrib', 'ref' => 'contribution:'.$contribution->id, 'url' => 'https://fr.wikipedia.org/wiki/Z_1200'], $material->provenance['gauge_mm']);
        $this->assertArrayNotHasKey('wikidata_id', $material->provenance);

        $this->get("/materials/{$material->slug}")->assertOk();
    }

    public function test_a_correction_keeps_only_the_changed_fields_and_survives_the_wikidata_import(): void
    {
        $this->material->update(['wikidata_id' => 'Q3574398', 'in_service_from' => 1920, 'gauge_mm' => 1435, 'provenance' => ['in_service_from' => ['source' => 'wikidata', 'ref' => 'Q3574398#P729']]]);

        $this->actingAs($this->member())->from('/contribute/series')
            ->post('/contribute/series', ['mode' => 'correction', 'material_slug' => $this->material->slug, 'in_service_from' => 1920, 'source_kind' => 'print', 'source_title' => 'Le matériel moteur de la SNCF, p. 210'])
            ->assertSessionHasErrors('changes');
        $this->assertErrorMatches('changes', fn (string $m) => str_contains($m, 'Aucune valeur'));

        $contribution = $this->submit($this->member(), [
            'mode' => 'correction',
            'material_slug' => $this->material->slug,
            'name' => $this->material->name,
            'in_service_from' => 1913,
            'gauge_mm' => 1435,
            'source_kind' => 'print',
            'source_title' => 'Le matériel moteur de la SNCF, p. 210',
        ]);
        $this->assertSame('update', $contribution->action->value);
        $this->assertEquals(['in_service_from' => ['before' => 1920, 'after' => 1913]], $contribution->payload['changes']);

        $this->publish($contribution);

        $this->material->refresh();
        $this->assertSame(1913, $this->material->in_service_from);
        $this->assertEquals(['source' => 'contrib', 'ref' => 'contribution:'.$contribution->id], $this->material->provenance['in_service_from']);

        // L'import de Wikidata ne remplace pas une valeur validée.
        app(MaterialImporter::class)->import([[
            'wikidata_id' => 'Q3574398', 'name' => $this->material->name, 'type' => 'automotrice', 'in_service_from' => 1920,
            'provenance' => ['in_service_from' => ['source' => 'wikidata', 'ref' => 'Q3574398#P729']],
        ]]);
        $this->assertSame(1913, $this->material->fresh()->in_service_from);
    }

    public function test_a_correction_cannot_take_the_identifier_of_another_series(): void
    {
        Material::factory()->create(['slug' => 'autre', 'name' => 'Autre série', 'wikidata_id' => 'Q3574398']);

        $this->actingAs($this->member())->from('/contribute/series')
            ->post('/contribute/series', ['mode' => 'correction', 'material_slug' => $this->material->slug, 'wikidata_id' => 'Q3574398', 'source_kind' => 'print', 'source_title' => 'Référence'])
            ->assertSessionHasErrors('wikidata_id');
        $this->assertErrorMatches('wikidata_id', fn (string $m) => str_contains($m, 'Autre série'));
    }

    public function test_a_name_taken_meanwhile_fails_cleanly(): void
    {
        $contribution = $this->submit($this->member(), $this->newSeries(['wikidata_id' => '']));
        $this->actingAs($this->member('validator'))->post("/validate/{$contribution->id}/approve");
        Material::factory()->create(['slug' => 'z-1200', 'name' => 'Z 1200']);

        $this->actingAs($this->member('validator'))->post("/validate/{$contribution->id}/approve")
            ->assertSessionHas('error');
        $this->assertSame('approved', $contribution->fresh()->status->value);
        $this->assertSame(1, Material::query()->where('name', 'Z 1200')->count());
    }
}

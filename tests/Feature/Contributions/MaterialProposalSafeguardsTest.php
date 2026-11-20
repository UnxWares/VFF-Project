<?php

namespace Tests\Feature\Contributions;

use App\Enums\ContributionStatus;
use App\Models\Contribution;
use App\Models\Line;
use App\Models\LineMaterial;
use App\Models\LineMaterialPeriod;
use App\Models\LineSegment;
use App\Models\Material;
use App\Models\User;
use App\Services\Contributions\MaterialSeriesContributions;
use App\Services\Materials\MaterialGaugeRule;
use App\Services\Materials\MaterialImporter;
use App\Services\Materials\MaterialPeriodCollector;
use App\Services\Materials\MaterialPeriodExtractor;
use App\Services\Materials\SourceCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ActsAsRoles;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * Garde-fous des propositions de matériel relevés à la relecture du 20/11/2026 : une correction de
 * série ne casse pas les liens de la série en silence, la phrase qui lève une règle doit nommer la
 * série et sa valeur sans négation, Wikidata n'est appelé que si l'identifiant change (jamais
 * remplacé à l'insu de l'auteur), tous les écartements connus et les effacements sont acceptés, la
 * publication compare l'« avant » à l'état actuel, une période publiée ne l'est pas deux fois, une
 * exclusion relue bloque la proposition, et l'import des périodes ne protège que les contributions.
 */
class MaterialProposalSafeguardsTest extends TestCase
{
    use ActsAsRoles;
    use BuildsRailNetwork;
    use RefreshDatabase;

    private const DC_750 = ['label' => 'électrification ferroviaire 750 V continu', 'wikidata_id' => 'Q25857994'];

    private const P31_SERIES = ['P31' => [['mainsnak' => ['datavalue' => ['value' => ['id' => 'Q811704']]]]]];

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRailNetwork();
        $this->withCookie('locale', 'fr');
        Http::preventStrayRequests();
        Http::fake([
            'www.wikidata.org/*' => function (Request $request) {
                $id = (string) ($request->data()['ids'] ?? '');

                return match ($id) {
                    'Q3574398' => Http::response(['entities' => ['Q3574398' => ['id' => 'Q3574398', 'labels' => ['fr' => ['value' => 'Z 1200']], 'claims' => self::P31_SERIES]]]),
                    // Élément fusionné dans Q3574398.
                    'Q99000001' => Http::response(['entities' => ['Q3574398' => ['id' => 'Q3574398', 'labels' => [], 'claims' => self::P31_SERIES]]]),
                    // Une ville : sa nature ne mène pas au matériel roulant.
                    'Q90' => Http::response(['entities' => ['Q90' => ['id' => 'Q90', 'labels' => ['fr' => ['value' => 'Paris']], 'claims' => ['P31' => [['mainsnak' => ['datavalue' => ['value' => ['id' => 'Q515']]]]]]]]]),
                    // Wikidata en panne.
                    'Q4000000' => Http::response(['error' => ['code' => 'unknown-error', 'info' => 'Serveur occupé']], 200),
                    default => Http::response(['error' => ['code' => 'no-such-entity', 'info' => 'none']]),
                };
            },
            'query.wikidata.org/*' => Http::response(['head' => [], 'boolean' => false]),
        ]);
    }

    private function publish(Contribution $contribution): void
    {
        $this->actingAs($this->member('validator'))->post("/validate/{$contribution->id}/approve")->assertSessionHas('success');
        $this->actingAs($this->member('validator'))->post("/validate/{$contribution->id}/approve")->assertSessionHas('success');
    }

    /** Deuxième approbation qui doit échouer : rend le message d'erreur montré au validateur. */
    private function publishFails(Contribution $contribution): string
    {
        $this->actingAs($this->member('validator'))->post("/validate/{$contribution->id}/approve")->assertSessionHas('success');
        $this->actingAs($this->member('validator'))->post("/validate/{$contribution->id}/approve")->assertSessionHas('error');
        $this->assertSame('approved', $contribution->fresh()->status->value);

        return (string) session('error');
    }

    /** @param array<string, mixed> $overrides */
    private function submitSeries(Material $material, array $overrides, ?User $author = null): Contribution
    {
        $this->actingAs($author ?? $this->member())->post('/contribute/series', [
            'mode' => 'correction', 'material_slug' => $material->slug, 'source_kind' => 'print', 'source_title' => 'Le matériel moteur, p. 3',
            ...$overrides,
        ])->assertRedirect()->assertSessionHasNoErrors();

        return Contribution::query()->latest('id')->firstOrFail();
    }

    /** @param array<string, mixed> $overrides */
    private function link(array $overrides = []): array
    {
        return [
            'line_slug' => $this->line->slug, 'circulation' => 'regular',
            'source_kind' => 'web', 'source_title' => 'Une page', 'source_url' => 'https://example.org/a',
            ...$overrides,
        ];
    }

    private function moulineaux(): Line
    {
        $line = Line::factory()->create(['code' => '972000', 'slug' => 'rfn-972000', 'name' => 'Ligne de Puteaux à Issy-Plaine']);
        foreach ([$this->currentEra, $this->pastEra] as $era) {
            LineSegment::factory()->create(['line_id' => $line->id, 'era_id' => $era->id, 'electrified' => false, 'voltage_v' => null, 'electrification_source' => 'sncf', 'length_meters' => 6_000]);
        }

        return $line;
    }

    // ─── 1. Correction d'une série et liens de la série ─────────────────────

    public function test_a_gauge_correction_that_would_break_links_is_shown_then_refused(): void
    {
        $series = Material::factory()->create(['slug' => 'bb-test', 'name' => 'BB Test', 'type' => 'locomotive_diesel', 'gauge_mm' => 1435]);
        $this->actingAs($this->member())->post('/contribute/material', $this->link(['material_slug' => $series->slug]))->assertSessionHasNoErrors();
        $this->publish(Contribution::query()->latest('id')->firstOrFail());

        // Le contributeur voit l'effet avant l'envoi.
        $this->actingAs($this->member())->getJson('/contribute/series/impact?material=bb-test&gauges[]=1000')
            ->assertOk()
            ->assertJsonCount(1, 'links')
            ->assertJsonPath('links.0.line_slug', $this->line->slug)
            ->assertJsonPath('links.0.source', 'contrib')
            ->assertJsonPath('links.0.rule', 'gauge');
        $this->actingAs($this->member())->getJson('/contribute/series/impact?material=bb-test&gauges[]=1435')
            ->assertOk()->assertJsonCount(0, 'links');

        $correction = $this->submitSeries($series, ['gauges' => [1000]]);
        $this->assertCount(1, $correction->payload['impact']);

        // Les validateurs aussi.
        $this->actingAs($this->member('validator'))->get('/validate')
            ->assertInertia(fn (Assert $page) => $page
                ->where('contributions.0.series_context.impact.0.rule', 'gauge')
                ->where('contributions.0.series_context.stale', []));

        $error = $this->publishFails($correction);
        $this->assertStringContainsString('rendrait des liens contraires', $error);
        $this->assertStringContainsString('série à 1 000 mm', $error);
        $this->assertSame(1435, $series->fresh()->gauge_mm);
        $this->assertSame([], MaterialGaugeRule::fromDatabase()->storedConflicts(), 'l’invariant reste vert');
    }

    public function test_an_electrification_correction_that_breaks_nothing_is_published(): void
    {
        $series = Material::factory()->create(['slug' => 'z-test', 'name' => 'Z Test', 'type' => 'automotrice', 'gauge_mm' => 1435, 'electrification' => null, 'in_service_from' => null, 'in_service_to' => null]);
        $line = Line::factory()->create(['code' => '970000', 'slug' => 'rfn-970000', 'name' => 'Ligne en 1 500 V']);
        LineSegment::factory()->create(['line_id' => $line->id, 'era_id' => $this->currentEra->id, 'electrified' => true, 'voltage_v' => 1500, 'electrification_source' => 'sncf', 'length_meters' => 10_000]);
        LineMaterial::query()->create(['line_id' => $line->id, 'material_id' => $series->id, 'source' => 'wikipedia', 'confidence' => 'high', 'evidence' => []]);

        // La ligne est électrifiée en 1 500 V : 1 500 V passe, 25 kV seul casserait le lien.
        $this->actingAs($this->member())->getJson('/contribute/series/impact?material=z-test&electrification[]=ac_25k')
            ->assertOk()->assertJsonPath('links.0.rule', 'traction');
        $correction = $this->submitSeries($series, ['electrification' => ['dc_1500']]);
        $this->assertSame([], $correction->payload['impact']);
        $this->publish($correction);
        $this->assertSame(['dc_1500'], MaterialSeriesContributions::systemKeys($series->fresh()->electrification));
    }

    // ─── 2. Phrase qui lève une règle ───────────────────────────────────────

    public function test_a_quote_must_name_the_series_and_its_value_without_negation(): void
    {
        $line = $this->moulineaux();
        $series = Material::factory()->create(['slug' => 'z-test', 'name' => 'Z 1200', 'designations' => ['Z1200'], 'type' => 'automotrice', 'gauge_mm' => 1435,
            'electrification' => [self::DC_750], 'in_service_from' => 1913, 'in_service_to' => 1976]);
        $post = fn (string $quote) => $this->actingAs($this->member())->from('/contribute/material')
            ->post('/contribute/material', $this->link(['line_slug' => $line->slug, 'material_slug' => $series->slug, 'quote' => $quote]));

        $post('Jamais en 750 V ici.')->assertSessionHasErrors('quote');
        $this->assertStringContainsString('nommer la série', (string) session('errors')->first('quote'));
        $post('Les Z 1200 n’ont jamais roulé en 750 V sur cette ligne.')->assertSessionHasErrors('quote');
        $this->assertStringContainsString('négation', (string) session('errors')->first('quote'));
        $post('The Z 1200 units never ran on 750 V here.')->assertSessionHasErrors('quote');
        $post('Die Z 1200 fuhren hier nicht mit 750 V.')->assertSessionHasErrors('quote');
        // Longueur mesurée après la réduction des espaces.
        $post('750      V  ok')->assertSessionHasErrors('quote');
        $this->assertStringContainsString('de 10 à 600 caractères', (string) session('errors')->first('quote'));
        $this->assertSame(0, Contribution::count());

        $post('The Z1200 units worked the line on the 750 V third rail until 1965.')->assertSessionHasNoErrors();
        $this->assertSame('traction', Contribution::query()->latest('id')->firstOrFail()->payload['exception']['rule']);
    }

    public function test_a_gauge_named_in_english_or_german_lifts_the_rule(): void
    {
        $series = Material::factory()->create(['slug' => 'x-test', 'name' => 'X 200', 'type' => 'autorail', 'gauge_mm' => 1000]);

        $this->actingAs($this->member())->post('/contribute/material', $this->link(['material_slug' => $series->slug, 'quote' => 'A metre gauge track was laid for the X 200 railcars.']))
            ->assertSessionHasNoErrors();
        $this->assertSame('gauge', Contribution::query()->latest('id')->firstOrFail()->payload['exception']['rule']);
        $this->actingAs($this->member())->from('/contribute/material')
            ->post('/contribute/material', $this->link(['material_slug' => $series->slug, 'quote' => 'Für die X 200 wurde kein Meterspurgleis verlegt.', 'start_precision' => 'year', 'start_date' => '1950']))
            ->assertSessionHasErrors('quote');
    }

    // ─── 3. Identifiant Wikidata d'une série corrigée ───────────────────────

    public function test_an_unchanged_identifier_does_not_call_wikidata(): void
    {
        $series = Material::factory()->create(['slug' => 'q-test', 'name' => 'Q Test', 'wikidata_id' => 'Q4000000']);

        $correction = $this->submitSeries($series, ['name' => 'Q Test bis', 'wikidata_id' => 'Q4000000']);

        $this->assertSame(['name'], array_keys($correction->payload['changes']));
        Http::assertNothingSent();
    }

    public function test_a_merged_identifier_never_changes_without_the_author_asking(): void
    {
        $merged = Material::factory()->create(['slug' => 'q-merged', 'name' => 'Q Merged', 'wikidata_id' => 'Q99000001']);
        $correction = $this->submitSeries($merged, ['name' => 'Q Merged bis', 'wikidata_id' => 'Q99000001']);
        $this->assertArrayNotHasKey('wikidata_id', $correction->payload['changes']);

        // L'auteur saisit l'identifiant final : c'est sa demande, elle est acceptée.
        $explicit = $this->submitSeries($merged, ['wikidata_id' => 'Q3574398', 'name' => 'Q Merged ter']);
        $this->assertEquals(['before' => 'Q99000001', 'after' => 'Q3574398'], $explicit->payload['changes']['wikidata_id']);

        // Une série sans identifiant : l'élément fusionné est refusé avec son identifiant final.
        $plain = Material::factory()->create(['slug' => 'q-plain', 'name' => 'Q Plain', 'wikidata_id' => null]);
        $this->actingAs($this->member())->from('/contribute/series')
            ->post('/contribute/series', ['mode' => 'correction', 'material_slug' => $plain->slug, 'wikidata_id' => 'Q99000001', 'source_kind' => 'print', 'source_title' => 'Référence'])
            ->assertSessionHasErrors('wikidata_id');
        $this->assertStringContainsString('fusionné dans Q3574398', (string) session('errors')->first('wikidata_id'));

        $this->actingAs($this->member())->getJson('/contribute/series/wikidata?qid=Q99000001')
            ->assertOk()->assertJsonPath('requested', 'Q99000001')->assertJsonPath('item.id', 'Q3574398');
    }

    public function test_an_item_that_is_not_a_series_is_refused_under_its_own_key(): void
    {
        $this->actingAs($this->member())->from('/contribute/series')
            ->post('/contribute/series', ['mode' => 'new', 'name' => 'Paris', 'type' => 'autorail', 'wikidata_id' => 'Q90', 'source_kind' => 'print', 'source_title' => 'Référence'])
            ->assertSessionHasErrors('wikidata_id');
        $this->assertStringContainsString('ne désigne pas une série', (string) session('errors')->first('wikidata_id'));

        $this->actingAs($this->member())->getJson('/contribute/series/wikidata?qid=Q90')
            ->assertUnprocessable()->assertJsonValidationErrors('qid')->assertJsonMissingValidationErrors('wikidata_id');
        $this->actingAs($this->member())->getJson('/contribute/series/wikidata?qid=Z12')
            ->assertUnprocessable()->assertJsonValidationErrors('qid');
        $this->assertSame(0, Contribution::count());
    }

    // ─── 4. Écartements et effacements ──────────────────────────────────────

    public function test_every_known_gauge_and_multi_gauge_series_survive_a_correction(): void
    {
        $x15p = Material::factory()->create(['slug' => 'sl-x15p', 'name' => 'X15p', 'gauge_mm' => 891, 'gauges' => [['mm' => 891, 'label' => 'voie étroite 891 mm', 'wikidata_id' => 'Q17146992']]]);
        $traxx = Material::factory()->create(['slug' => 'traxx-t', 'name' => 'Traxx T', 'gauge_mm' => null, 'gauges' => [
            ['mm' => 1435, 'label' => 'écartement standard', 'wikidata_id' => 'Q1999572'], ['mm' => 1668, 'label' => 'voie ibérique', 'wikidata_id' => 'Q3319112']]]);

        $this->actingAs($this->member())->get("/contribute/series?material={$traxx->slug}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('material.gauges', [1435, 1668])
                ->where('gauges', fn ($gauges) => in_array(891, $gauges->all(), true)));

        // Le formulaire prérempli renvoie l'écartement actuel : il passe, et ne change rien.
        $correction = $this->submitSeries($x15p, ['name' => 'X15p (SL)', 'gauges' => [891]]);
        $this->assertSame(['name'], array_keys($correction->payload['changes']));

        $correction = $this->submitSeries($traxx, ['name' => 'Traxx (Alstom)', 'gauges' => [1668, 1435]]);
        $this->assertSame(['name'], array_keys($correction->payload['changes']));
        $this->publish($correction);
        $this->assertCount(2, $traxx->fresh()->gauges);

        // Un écartement que la base ne connaît pas reste refusé, avec un message traduit.
        $this->actingAs($this->member())->from('/contribute/series')
            ->post('/contribute/series', ['mode' => 'correction', 'material_slug' => $x15p->slug, 'gauges' => [1067], 'source_kind' => 'print', 'source_title' => 'Référence'])
            ->assertSessionHasErrors('gauges.0');
        $this->assertSame('Choisissez des écartements de la liste.', session('errors')->first('gauges.0'));
    }

    public function test_a_wrong_value_can_be_cleared_and_stays_cleared(): void
    {
        $series = Material::factory()->create(['slug' => 'faux', 'name' => 'Faux', 'wikidata_id' => 'Q3574398', 'builder' => 'Constructeur faux', 'in_service_to' => 1990,
            'provenance' => ['builder' => ['source' => 'wikidata', 'ref' => 'Q3574398#P176']]]);

        $correction = $this->submitSeries($series, ['clear' => ['builder', 'in_service_to']]);
        $this->assertEquals([
            'builder' => ['before' => 'Constructeur faux', 'after' => null],
            'in_service_to' => ['before' => 1990, 'after' => null],
        ], $correction->payload['changes']);

        $this->actingAs($this->member())->from('/contribute/series')
            ->post('/contribute/series', ['mode' => 'correction', 'material_slug' => $series->slug, 'builder' => 'Alsthom', 'clear' => ['builder'], 'source_kind' => 'print', 'source_title' => 'Référence'])
            ->assertSessionHasErrors('clear');

        $this->publish($correction);
        $this->assertNull($series->fresh()->builder);
        $this->assertNull($series->fresh()->in_service_to);

        app(MaterialImporter::class)->import([[
            'wikidata_id' => 'Q3574398', 'name' => 'Faux', 'type' => 'automotrice', 'builder' => 'Constructeur faux',
            'provenance' => ['builder' => ['source' => 'wikidata', 'ref' => 'Q3574398#P176']],
        ]]);
        $this->assertNull($series->fresh()->builder, 'l’import ne remplit pas de nouveau une valeur effacée par une contribution');
    }

    // ─── 5. Publication sur l'état actuel ───────────────────────────────────

    public function test_a_correction_made_on_an_old_value_fails_as_stale(): void
    {
        $series = Material::factory()->create(['slug' => 's-test', 'name' => 'S Test']);
        $a = $this->submitSeries($series, ['name' => 'S Test A']);
        $b = $this->submitSeries($series, ['name' => 'S Test B']);
        $this->publish($a);

        $this->actingAs($this->member('validator'))->get('/validate')
            ->assertInertia(fn (Assert $page) => $page->where('contributions.0.series_context.stale.0', ['field' => 'name', 'now' => 'S Test A']));
        $error = $this->publishFails($b);
        $this->assertStringContainsString('a changé depuis l’envoi', $error);
        $this->assertSame('S Test A', $series->fresh()->name);
    }

    public function test_two_corrections_cannot_put_withdrawal_before_entry_into_service(): void
    {
        $series = Material::factory()->create(['slug' => 'y-test', 'name' => 'Y Test', 'in_service_from' => 1950, 'in_service_to' => null]);
        $a = $this->submitSeries($series, ['in_service_from' => 1995]);
        $b = $this->submitSeries($series, ['in_service_to' => 1980]);
        $this->publish($b);

        $this->assertStringContainsString('le retrait viendrait avant la mise en service', $this->publishFails($a));
        $this->assertSame([1950, 1980], [$series->fresh()->in_service_from, $series->fresh()->in_service_to]);
    }

    // ─── 6. Période déjà publiée ────────────────────────────────────────────

    public function test_a_published_period_is_refused_and_publication_is_idempotent(): void
    {
        $series = Material::factory()->create(['slug' => 'bb-dup', 'name' => 'BB Dup', 'type' => 'locomotive_diesel', 'gauge_mm' => 1435]);
        $data = $this->link(['material_slug' => $series->slug, 'start_precision' => 'year', 'start_date' => '1958', 'end_precision' => 'year', 'end_date' => '1988']);
        $this->actingAs($this->member())->post('/contribute/material', $data)->assertSessionHasNoErrors();
        $first = Contribution::query()->latest('id')->firstOrFail();
        $this->publish($first);

        $this->actingAs($this->member())->from('/contribute/material')->post('/contribute/material', $data)->assertSessionHasErrors('material_slug');
        $this->assertStringContainsString('déjà publiée', (string) session('errors')->first('material_slug'));

        // Une copie de la même proposition, envoyée avant la publication de la première.
        $twin = $first->replicate();
        $twin->status = ContributionStatus::Pending;
        $twin->payload = array_diff_key($first->payload, ['applied' => true]);
        $twin->save();

        $this->publish($twin);
        $this->assertSame(1, LineMaterialPeriod::query()->where('source', 'contrib')->count());
        $this->assertSame($first->fresh()->payload['applied']['period_id'], $twin->fresh()->payload['applied']['period_id']);
    }

    // ─── 8. Exclusion relue ─────────────────────────────────────────────────

    public function test_a_pair_excluded_after_review_is_refused(): void
    {
        // Entrée réelle de database/data/line_material_exclusions.php : BB 9300 sur Valence - Moirans.
        $line = Line::factory()->create(['code' => '908000', 'slug' => 'rfn-908000', 'name' => 'Ligne de Valence à Moirans']);
        $bb9300 = Material::factory()->create(['slug' => 'bb-9300', 'name' => 'BB 9300', 'type' => 'locomotive_diesel', 'wikidata_id' => 'Q751355']);

        $this->actingAs($this->member())->getJson("/contribute/material/check?line={$line->slug}&material={$bb9300->slug}")
            ->assertOk()->assertJsonPath('excluded', fn (?string $r) => str_contains((string) $r, '1 500 V'));
        $this->actingAs($this->member())->from('/contribute/material')
            ->post('/contribute/material', $this->link(['line_slug' => $line->slug, 'material_slug' => $bb9300->slug, 'quote' => 'Les BB 9300 ont circulé sur la ligne.']))
            ->assertSessionHasErrors('material_slug');
        $this->assertStringContainsString('écarté après relecture', (string) session('errors')->first('material_slug'));
        $this->assertSame(0, Contribution::count());
    }

    // ─── Import des périodes ────────────────────────────────────────────────

    public function test_the_period_import_cleans_every_source_but_contributions(): void
    {
        $link = LineMaterial::query()->create(['line_id' => $this->line->id, 'material_id' => $this->material->id, 'source' => 'wikipedia', 'confidence' => 'high', 'evidence' => []]);
        foreach (['wikipedia', 'archives', 'contrib'] as $source) {
            LineMaterialPeriod::query()->create(['line_material_id' => $link->id, 'fingerprint' => "f-{$source}", 'start_date' => '1960-01-01', 'start_precision' => 'year',
                'confidence' => 'medium', 'origin' => 'line_article', 'source' => $source]);
        }

        $stats = (new MaterialPeriodCollector(new SourceCache(sys_get_temp_dir()), app(MaterialPeriodExtractor::class)))->write([]);

        $this->assertSame(2, $stats['removed']);
        $this->assertSame(['contrib'], LineMaterialPeriod::query()->pluck('source')->all());
    }
}

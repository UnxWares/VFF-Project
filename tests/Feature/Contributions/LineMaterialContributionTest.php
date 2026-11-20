<?php

namespace Tests\Feature\Contributions;

use App\Enums\NotificationKind;
use App\Models\Contribution;
use App\Models\Line;
use App\Models\LineMaterial;
use App\Models\LineMaterialPeriod;
use App\Models\LineSegment;
use App\Models\Material;
use App\Models\User;
use App\Services\Contributions\LineMaterialContributions;
use App\Services\DataInvariants;
use App\Services\Materials\MaterialPeriodCollector;
use App\Services\Materials\MaterialPeriodExtractor;
use App\Services\Materials\SourceCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ActsAsRoles;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * Proposer un matériel sur une ligne (/contribute/material) : accès, règles de saisie, règles
 * d'écartement et d'électrification (refus expliqué, exception prouvée par une phrase de la source),
 * doublons, relecture à deux, publication dans line_materials et line_material_periods avec la
 * provenance `contribution:<id>`, sans relancer d'import.
 */
class LineMaterialContributionTest extends TestCase
{
    use ActsAsRoles;
    use BuildsRailNetwork;
    use RefreshDatabase;

    private const DC_750 = ['label' => 'électrification ferroviaire 750 V continu', 'wikidata_id' => 'Q25857994'];

    private Material $series;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRailNetwork();
        $this->withCookie('locale', 'fr');
        $this->series = Material::factory()->create(['slug' => 'bb-9200-test', 'name' => 'BB 9200', 'type' => 'locomotive_electrique', 'gauge_mm' => 1435, 'electrification' => null]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function proposal(array $overrides = []): array
    {
        return [
            'line_slug' => $this->line->slug,
            'material_slug' => $this->series->slug,
            'circulation' => 'regular',
            'start_precision' => 'year',
            'start_date' => '1958',
            'end_precision' => 'month',
            'end_date' => '1988-05',
            'source_kind' => 'document',
            'source_title' => 'Livret Chaix d’été 1960, p. 112',
            'source_url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k0000001',
            'source_license' => 'public_domain',
            'notes' => 'Tableau des roulements, page 112.',
            ...$overrides,
        ];
    }

    private function submit(User $author, array $overrides = []): Contribution
    {
        $this->actingAs($author)->post('/contribute/material', $this->proposal($overrides))->assertRedirect()->assertSessionHasNoErrors();

        return Contribution::query()->latest('id')->firstOrFail();
    }

    private function publish(Contribution $contribution): void
    {
        $this->actingAs($this->member('validator'))->post("/validate/{$contribution->id}/approve")->assertSessionHas('success');
        $this->actingAs($this->member('validator'))->post("/validate/{$contribution->id}/approve")->assertSessionHas('success');
    }

    /** Ligne des Moulineaux : troisième rail en 750 V que VFF ne connaît pas (tronçons « non électrifiés », source SNCF). */
    private function moulineaux(): Line
    {
        $line = Line::factory()->create(['code' => '972000', 'slug' => 'rfn-972000', 'name' => 'Ligne de Puteaux à Issy-Plaine']);
        LineSegment::factory()->create(['line_id' => $line->id, 'era_id' => $this->currentEra->id, 'electrified' => false, 'voltage_v' => null, 'electrification_source' => 'sncf', 'length_meters' => 6_000]);
        LineSegment::factory()->create(['line_id' => $line->id, 'era_id' => $this->pastEra->id, 'electrified' => false, 'voltage_v' => null, 'electrification_source' => 'sncf', 'length_meters' => 6_000]);

        return $line;
    }

    private function z1200(): Material
    {
        return Material::factory()->create(['slug' => 'z-1200-test', 'name' => 'Z 1200', 'type' => 'automotrice', 'electrification' => [self::DC_750], 'gauge_mm' => 1435, 'in_service_from' => 1913, 'in_service_to' => 1976]);
    }

    // ─── Accès ──────────────────────────────────────────────────────────────

    /** Le message d'erreur du champ (session de la dernière requête) vérifie la condition. */
    private function assertErrorMatches(string $field, \Closure $check): void
    {
        $message = (string) session('errors')?->first($field);
        $this->assertTrue($check($message), "Message du champ {$field} : {$message}");
    }

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get('/contribute/material')->assertRedirect('/login');
        $this->post('/contribute/material', $this->proposal())->assertRedirect('/login');
        $this->get('/contribute/material/check?line=a&material=b')->assertRedirect('/login');
        $this->assertSame(0, Contribution::count());
    }

    public function test_a_member_sees_the_form_with_the_line_and_the_series_preselected(): void
    {
        $this->actingAs($this->member())
            ->get("/contribute/material?line={$this->line->slug}&material={$this->series->slug}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Contribute/ProposeLineMaterial')
                ->where('preselectedLine.slug', $this->line->slug)
                ->where('preselectedMaterial', ['slug' => $this->series->slug, 'name' => 'BB 9200', 'type' => 'locomotive_electrique'])
                ->where('circulations', ['regular', 'occasional', 'trial', 'heritage'])
                ->where('sourceKinds', ['web', 'document', 'print']));
    }

    public function test_a_suspended_account_cannot_propose(): void
    {
        $suspended = $this->member('contributor', ['suspended_at' => now()]);

        $this->actingAs($suspended)->get('/contribute/material')->assertForbidden();
        $this->actingAs($suspended)->post('/contribute/material', $this->proposal())->assertForbidden();
        $this->assertSame(0, Contribution::count());
    }

    // ─── Règles de saisie ───────────────────────────────────────────────────

    /** @return array<string, array{0: array<string, mixed>, 1: string}> */
    public static function invalidProposals(): array
    {
        return [
            'ligne inconnue' => [['line_slug' => 'ligne-inexistante'], 'line_slug'],
            'série inconnue' => [['material_slug' => 'serie-inexistante'], 'material_slug'],
            'circulation inconnue' => [['circulation' => 'fantome'], 'circulation'],
            'date sans précision' => [['start_precision' => ''], 'start_precision'],
            'précision sans date' => [['end_date' => ''], 'end_date'],
            'date impossible' => [['start_precision' => 'day', 'start_date' => '1958-02-30'], 'start_date'],
            'fin avant le début' => [['start_date' => '1990'], 'end_date'],
            'portion sans date' => [['start_precision' => '', 'start_date' => '', 'end_precision' => '', 'end_date' => '', 'section_from' => 'Paris', 'section_to' => 'Versailles'], 'start_date'],
            'portion sans fin' => [['section_from' => 'Paris'], 'section_to'],
            'portion aux extrémités identiques' => [['section_from' => 'Paris', 'section_to' => 'Paris'], 'section_to'],
            'source sans titre' => [['source_title' => ''], 'source_title'],
            'page web sans adresse' => [['source_kind' => 'web', 'source_url' => ''], 'source_url'],
            'document sans licence' => [['source_license' => ''], 'source_license'],
            'phrase trop courte' => [['quote' => 'Oui.'], 'quote'],
        ];
    }

    /** @param  array<string, mixed>  $overrides */
    #[DataProvider('invalidProposals')]
    public function test_an_invalid_proposal_is_refused_with_the_field_in_error(array $overrides, string $field): void
    {
        $this->actingAs($this->member())
            ->from('/contribute/material')
            ->post('/contribute/material', $this->proposal($overrides))
            ->assertRedirect('/contribute/material')
            ->assertSessionHasErrors($field);
        $this->assertSame(0, Contribution::count());
    }

    public function test_a_proposal_is_saved_pending_and_announced_to_the_validators(): void
    {
        $validator = $this->member('validator');
        $contribution = $this->submit($this->member(), ['section_from' => 'Paris-Saint-Lazare', 'section_to' => '  Saint-Cloud ']);

        $this->assertSame(LineMaterialContributions::TYPE, $contribution->type);
        $this->assertSame('pending', $contribution->status->value);
        $this->assertSame([Line::class, $this->line->id], [$contribution->target_type, (int) $contribution->target_id]);
        $p = $contribution->payload;
        $this->assertSame([$this->series->id, 'BB 9200', 'regular'], [$p['material_id'], $p['material_name'], $p['circulation']]);
        $this->assertEquals([
            'start' => ['date' => '1958-01-01', 'precision' => 'year'],
            'end' => ['date' => '1988-05-01', 'precision' => 'month'],
            'from' => 'Paris-Saint-Lazare',
            'to' => 'Saint-Cloud',
        ], $p['period']);
        $this->assertNull($p['exception']);
        $this->assertSame(1, $validator->notifications()->where('data->kind', NotificationKind::AwaitingReview->value)->count());
        $this->assertSame(0, LineMaterial::query()->where('source', 'contrib')->count(), 'rien de publié avant la relecture');
    }

    // ─── Règles d'écartement et d'électrification ───────────────────────────

    public function test_a_series_of_another_gauge_is_refused_with_an_explanation(): void
    {
        $metre = Material::factory()->create(['slug' => 'x-200-test', 'name' => 'X 200', 'type' => 'autorail', 'gauge_mm' => 1000]);

        $this->actingAs($this->member())->from('/contribute/material')
            ->post('/contribute/material', $this->proposal(['material_slug' => $metre->slug]))
            ->assertSessionHasErrors('quote');
        $this->assertErrorMatches('quote', fn (string $m) => str_contains($m, 'X 200') && str_contains($m, "1\u{202F}000 mm") && str_contains($m, 'nomme l’écartement'));

        $this->actingAs($this->member())->from('/contribute/material')
            ->post('/contribute/material', $this->proposal(['material_slug' => $metre->slug, 'quote' => 'Les autorails ont circulé sur la ligne pendant les travaux.']))
            ->assertSessionHasErrors('quote');
        $this->assertErrorMatches('quote', fn (string $m) => str_contains($m, 'ne nomme pas l’écartement'));
        $this->assertSame(0, Contribution::count());
    }

    public function test_a_sentence_naming_the_gauge_lets_the_link_through_and_flags_it_for_review(): void
    {
        $metre = Material::factory()->create(['slug' => 'x-200-test', 'name' => 'X 200', 'type' => 'autorail', 'gauge_mm' => 1000]);

        $contribution = $this->submit($this->member(), ['material_slug' => $metre->slug, 'quote' => 'Une file de rails à voie métrique a été posée pour les autorails X 200 jusqu’en 1988.']);

        $this->assertSame('gauge', $contribution->payload['exception']['rule']);
        $this->assertStringContainsString('série à 1 000 mm', $contribution->payload['exception']['reason']);
        $this->actingAs($this->member('validator'))->get('/validate')
            ->assertInertia(fn (Assert $page) => $page
                ->where('contributions.0.payload.exception.rule', 'gauge')
                ->where('contributions.0.material_context.conflict_now', null));
    }

    public function test_an_electric_series_on_a_line_never_electrified_for_it_is_refused(): void
    {
        $line = $this->moulineaux();
        $z1200 = $this->z1200();

        $this->actingAs($this->member())->from('/contribute/material')
            ->post('/contribute/material', $this->proposal(['line_slug' => $line->slug, 'material_slug' => $z1200->slug]))
            ->assertSessionHasErrors('quote');
        $this->assertErrorMatches('quote', fn (string $m) => str_contains($m, "750\u{00A0}V") && str_contains($m, 'n’a jamais été électrifiée') && str_contains($m, 'nomme la tension'));
        $this->assertSame(0, Contribution::count());
    }

    public function test_the_sourced_exception_of_the_z_1200_on_the_moulineaux_line_is_accepted(): void
    {
        $line = $this->moulineaux();
        $z1200 = $this->z1200();
        $author = $this->member();

        $contribution = $this->submit($author, [
            'line_slug' => $line->slug,
            'material_slug' => $z1200->slug,
            'start_precision' => '', 'start_date' => '',
            'end_precision' => 'year', 'end_date' => '1965',
            'source_kind' => 'web',
            'source_title' => 'Ligne de Puteaux à Issy-Plaine, Wikipédia',
            'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Puteaux_%C3%A0_Issy-Plaine&oldid=241186256',
            'quote' => 'La ligne est électrifiée par troisième rail en 750 V ; tous les mouvements sont assurés par les automotrices Z 1200.',
        ]);
        $this->assertSame('traction', $contribution->payload['exception']['rule']);

        $this->publish($contribution);

        $link = LineMaterial::query()->where(['line_id' => $line->id, 'material_id' => $z1200->id, 'source' => 'contrib'])->firstOrFail();
        $this->assertSame('high', $link->confidence);
        $this->assertSame(0, (new DataInvariants(['line_materials.traction_conflict']))->run()[0]['count'], 'la phrase citée nomme la tension : l’invariant reste vert');
    }

    public function test_an_exception_for_trials_only_is_refused_for_an_electric_series(): void
    {
        $line = $this->moulineaux();
        $z1200 = $this->z1200();

        $this->actingAs($this->member())->from('/contribute/material')
            ->post('/contribute/material', $this->proposal(['line_slug' => $line->slug, 'material_slug' => $z1200->slug, 'circulation' => 'trial', 'quote' => 'Des essais en 750 V ont eu lieu sur la ligne.']))
            ->assertSessionHasErrors('quote');
        $this->assertErrorMatches('quote', fn (string $m) => str_contains($m, 'service régulier'));
    }

    public function test_the_check_endpoint_explains_the_conflict_and_the_known_link(): void
    {
        $line = $this->moulineaux();
        $z1200 = $this->z1200();

        $this->actingAs($this->member())->getJson("/contribute/material/check?line={$line->slug}&material={$z1200->slug}")
            ->assertOk()
            ->assertJsonPath('conflict.rule', 'traction')
            ->assertJsonPath('existing', null);

        $this->actingAs($this->member())->getJson("/contribute/material/check?line={$this->line->slug}&material={$this->material->slug}")
            ->assertOk()
            ->assertJsonPath('conflict', null)
            ->assertJsonPath('existing.source', null);
    }

    // ─── Doublons ───────────────────────────────────────────────────────────

    public function test_a_link_already_sure_needs_a_period(): void
    {
        LineMaterial::query()->create(['line_id' => $this->line->id, 'material_id' => $this->series->id, 'source' => 'wikipedia', 'confidence' => 'high', 'evidence' => []]);
        $undated = ['start_precision' => '', 'start_date' => '', 'end_precision' => '', 'end_date' => ''];

        $this->actingAs($this->member())->from('/contribute/material')
            ->post('/contribute/material', $this->proposal($undated))
            ->assertSessionHasErrors('material_slug');
        $this->assertErrorMatches('material_slug', fn (string $m) => str_contains($m, 'déjà dans VFF'));

        $contribution = $this->submit($this->member());
        $this->assertSame('wikipedia', $contribution->payload['existing_link']['source']);
    }

    public function test_the_same_pending_proposal_is_refused(): void
    {
        $this->submit($this->member());

        $this->actingAs($this->member())->from('/contribute/material')
            ->post('/contribute/material', $this->proposal())
            ->assertSessionHasErrors('material_slug');
        $this->assertSame(1, Contribution::count());
    }

    // ─── Publication ────────────────────────────────────────────────────────

    public function test_two_approvals_publish_the_link_and_its_period_with_their_provenance(): void
    {
        $contribution = $this->submit($this->member(), ['quote' => 'Les BB 9200 assurent les rapides de la ligne à partir de 1958.']);
        $segmentsBefore = DB::table('line_segments')->orderBy('id')->get()->toJson();

        $this->publish($contribution);

        $contribution->refresh();
        $this->assertSame('applied', $contribution->status->value);
        $link = LineMaterial::query()->findOrFail($contribution->payload['applied']['line_material_id']);
        $this->assertSame(['contrib', 'high'], [$link->source, $link->confidence]);
        $this->assertSame('https://gallica.bnf.fr/ark:/12148/bpt6k0000001', $link->source_url);
        $this->assertEquals([[
            'kind' => 'contribution',
            'contribution' => 'contribution:'.$contribution->id,
            'title' => 'Livret Chaix d’été 1960, p. 112',
            'source_kind' => 'document',
            'license' => 'public_domain',
            'quote' => 'Les BB 9200 assurent les rapides de la ligne à partir de 1958.',
            'doubt' => null,
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k0000001',
        ]], $link->evidence);

        $period = LineMaterialPeriod::query()->where('fingerprint', 'contribution:'.$contribution->id)->firstOrFail();
        $this->assertSame($contribution->payload['applied']['period_id'], $period->id);
        $this->assertSame(['1958-01-01', 'year', '1988-05-01', 'month'], [$period->start_date->toDateString(), $period->start_precision, $period->end_date->toDateString(), $period->end_precision]);
        $this->assertSame(['medium', 'contribution', 'contrib'], [$period->confidence, $period->origin, $period->source]);
        $this->assertSame('Livret Chaix d’été 1960, p. 112 (licence : Public domain)', $period->source_title);
        $this->assertSame($segmentsBefore, DB::table('line_segments')->orderBy('id')->get()->toJson(), 'aucun tronçon recalculé');

        // Fiche de la série : la ligne, une seule fois, avec la période publiée.
        $this->get("/materials/{$this->series->slug}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('lines', 1)
                ->where('lines.0.kind', 'contribution')
                ->where('lines.0.source_title', 'Livret Chaix d’été 1960, p. 112')
                ->has('lines.0.periods', 1));
    }

    public function test_publishing_twice_keeps_a_single_proof_and_period(): void
    {
        $contribution = $this->submit($this->member());
        $links = app(LineMaterialContributions::class);

        $first = $links->apply($contribution);
        $second = $links->apply($contribution);

        $this->assertSame($first, $second);
        $this->assertCount(1, LineMaterial::query()->findOrFail($first['line_material_id'])->evidence);
        $this->assertSame(1, LineMaterialPeriod::query()->where('source', 'contrib')->count());
    }

    public function test_an_occasional_run_is_published_to_be_confirmed(): void
    {
        $contribution = $this->submit($this->member(), ['circulation' => 'occasional', 'source_kind' => 'print', 'source_url' => '', 'source_license' => '']);

        $this->publish($contribution);

        $link = LineMaterial::query()->where('source', 'contrib')->firstOrFail();
        $this->assertSame('low', $link->confidence);
        $this->assertSame('one_off', $link->evidence[0]['doubt']);
        $period = LineMaterialPeriod::query()->where('source', 'contrib')->firstOrFail();
        $this->assertSame('low', $period->confidence, 'période d’une circulation douteuse : jamais montrée');
        $this->assertNull($period->source_url, 'ouvrage papier : la référence suffit');
    }

    public function test_a_link_that_became_incompatible_fails_cleanly(): void
    {
        $contribution = $this->submit($this->member());
        $this->actingAs($this->member('validator'))->post("/validate/{$contribution->id}/approve");
        // La fiche de la série change entre-temps : elle est dite à voie métrique.
        $this->series->update(['gauge_mm' => 1000]);

        $this->actingAs($this->member('validator'))->post("/validate/{$contribution->id}/approve")
            ->assertSessionHas('error', fn (string $message) => str_contains($message, 'écartement'));

        $this->assertSame('approved', $contribution->fresh()->status->value);
        $this->assertSame(0, LineMaterial::query()->where('source', 'contrib')->count());
    }

    public function test_the_period_import_keeps_contributed_periods(): void
    {
        $contribution = $this->submit($this->member());
        $this->publish($contribution);

        $stats = (new MaterialPeriodCollector(new SourceCache(sys_get_temp_dir()), app(MaterialPeriodExtractor::class)))->write([]);

        $this->assertSame(0, $stats['removed']);
        $this->assertSame(1, LineMaterialPeriod::query()->where('source', 'contrib')->count());
    }

    public function test_the_author_sees_the_proposal_in_their_contributions(): void
    {
        $author = $this->member();
        $contribution = $this->submit($author);

        $this->actingAs($author)->get('/contribute/mine')
            ->assertInertia(fn (Assert $page) => $page
                ->where('contributions.0.id', $contribution->id)
                ->where('contributions.0.type', 'line_material')
                ->where('contributions.0.payload.material_name', 'BB 9200'));
    }

    public function test_the_line_page_lists_a_series_once_when_two_sources_link_it(): void
    {
        LineMaterial::query()->create(['line_id' => $this->line->id, 'material_id' => $this->material->id, 'source' => 'contrib', 'confidence' => 'high', 'evidence' => []]);

        $this->get("/lines/{$this->line->slug}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('materials', fn ($materials): bool => count(array_filter(
                (array) json_decode((string) json_encode($materials), true),
                fn (array $m): bool => $m['slug'] === $this->material->slug,
            )) === 1));
        $this->assertSame(2, LineMaterial::query()->where('material_id', $this->material->id)->count());
    }
}

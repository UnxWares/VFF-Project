<?php

namespace Tests\Feature\GraphQL;

use App\Models\Line;
use App\Models\LineMaterial;
use App\Models\LineSegment;
use App\Models\Material;
use App\Models\User;
use App\Services\Api\ApiTokens;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\ActsAsRoles;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * Mutation `submitContribution` étendue au matériel (`input.line_material`) et aux séries
 * (`input.series`), avec les règles du site, puis publication par `validateContribution`.
 */
class MaterialContributionMutationsTest extends TestCase
{
    use ActsAsRoles;
    use BuildsRailNetwork;
    use MakesGraphQLRequests;
    use RefreshDatabase;

    private const SUBMIT = 'mutation ($input: ContributionInput!) {
        submitContribution(input: $input) {
            id type action status payload
            line { slug }
            material { slug name }
        }
    }';

    private const VALIDATE = 'mutation ($id: ID!) {
        validateContribution(id: $id, decision: APPROVE) { outcome contribution { status payload material { slug } } }
    }';

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRailNetwork();
        config(['api.rate_limits.anonymous' => 300, 'api.rate_limits.member' => 1200]);
        Http::preventStrayRequests();
        Http::fake(['www.wikidata.org/*' => Http::response(['error' => ['code' => 'no-such-entity', 'info' => 'Could not find an entity.']])]);
    }

    private function token(User $user): string
    {
        return app(ApiTokens::class)->create($user, 'Script', true)->plainTextToken;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return TestResponse<Response>
     */
    private function submit(string $token, array $input): TestResponse
    {
        return $this->withToken($token)->graphQL(self::SUBMIT, ['input' => $input]);
    }

    /** @return TestResponse<Response> */
    private function approveTwice(int|string $id): TestResponse
    {
        $this->withToken($this->token($this->member('validator')))->graphQL(self::VALIDATE, ['id' => (string) $id]);

        return $this->withToken($this->token($this->member('validator')))->graphQL(self::VALIDATE, ['id' => (string) $id]);
    }

    public function test_rolling_stock_on_a_line_is_proposed_then_published(): void
    {
        $series = Material::factory()->create(['slug' => 'bb-9200-test', 'name' => 'BB 9200', 'type' => 'locomotive_electrique']);

        $response = $this->submit($this->token($this->member()), ['line_material' => [
            'line_slug' => $this->line->slug,
            'material_slug' => $series->slug,
            'circulation' => 'regular',
            'start_precision' => 'year',
            'start_date' => '1958',
            'source_kind' => 'print',
            'source_title' => 'La Vie du Rail, n° 640, p. 8',
        ]])
            ->assertJsonPath('data.submitContribution.type', 'LINE_MATERIAL')
            ->assertJsonPath('data.submitContribution.action', 'CREATE')
            ->assertJsonPath('data.submitContribution.status', 'PENDING')
            ->assertJsonPath('data.submitContribution.line.slug', $this->line->slug)
            ->assertJsonPath('data.submitContribution.material.slug', $series->slug)
            ->assertJsonPath('data.submitContribution.payload.period.start.date', '1958-01-01');

        $this->approveTwice($response->json('data.submitContribution.id'))
            ->assertJsonPath('data.validateContribution.outcome', 'APPLIED');
        $this->assertSame('contrib', LineMaterial::query()->where('material_id', $series->id)->value('source'));

        $this->graphQL('{ line(slug: "'.$this->line->slug.'") { material_links { source evidence { kind contribution title source_kind } periods { origin source source_url } } } }')
            ->assertJsonFragment(['kind' => 'contribution', 'title' => 'La Vie du Rail, n° 640, p. 8', 'source_kind' => 'print'])
            ->assertJsonFragment(['origin' => 'contribution', 'source' => 'contrib', 'source_url' => null]);
    }

    public function test_an_incompatible_link_gives_the_explanation_on_the_quote_field(): void
    {
        $line = Line::factory()->create(['code' => '972000', 'slug' => 'rfn-972000', 'name' => 'Ligne de Puteaux à Issy-Plaine']);
        LineSegment::factory()->create(['line_id' => $line->id, 'era_id' => $this->currentEra->id, 'electrified' => false, 'voltage_v' => null, 'electrification_source' => 'sncf', 'length_meters' => 6_000]);
        $z1200 = Material::factory()->create(['slug' => 'z-1200-test', 'name' => 'Z 1200', 'type' => 'automotrice', 'electrification' => [['label' => 'électrification ferroviaire 750 V continu', 'wikidata_id' => 'Q25857994']], 'in_service_from' => null, 'in_service_to' => null]);

        $response = $this->submit($this->token($this->member()), ['line_material' => [
            'line_slug' => $line->slug,
            'material_slug' => $z1200->slug,
            'circulation' => 'regular',
            'source_kind' => 'print',
            'source_title' => 'Les automotrices de la ligne des Moulineaux',
        ]]);

        $message = $response->json('errors.0.extensions.validation')['input.line_material.quote'][0] ?? '';
        $this->assertStringContainsString('750', $message);
        $this->assertStringContainsString('voltage', $message, 'messages de l’API en anglais');
    }

    public function test_an_unknown_series_and_an_unknown_wikidata_item_are_input_errors(): void
    {
        $token = $this->token($this->member());

        $this->submit($token, ['line_material' => ['line_slug' => $this->line->slug, 'material_slug' => 'inconnue', 'circulation' => 'regular', 'source_kind' => 'print', 'source_title' => 'Référence']])
            ->assertJsonPath('errors.0.extensions.validation', fn (array $v) => isset($v['input.line_material.material_slug']));

        $this->submit($token, ['series' => ['mode' => 'new', 'name' => 'X 9999', 'type' => 'autorail', 'wikidata_id' => 'Q123456789', 'source_kind' => 'print', 'source_title' => 'Référence']])
            ->assertJsonPath('errors.0.extensions.validation', fn (array $v) => str_contains($v['input.series.wikidata_id'][0] ?? '', 'does not exist'));
    }

    public function test_a_new_series_is_proposed_then_published(): void
    {
        $response = $this->submit($this->token($this->member()), ['series' => [
            'mode' => 'new',
            'name' => 'X 9999',
            'type' => 'autorail',
            'gauge_mm' => 1000,
            'in_service_from' => 1950,
            'source_kind' => 'web',
            'source_title' => 'Fiche de la série',
            'source_url' => 'https://example.org/x9999',
        ]])
            ->assertJsonPath('data.submitContribution.type', 'MATERIAL_SERIES')
            ->assertJsonPath('data.submitContribution.material', null)
            ->assertJsonPath('data.submitContribution.payload.values.gauge_mm', 1000);

        $this->approveTwice($response->json('data.submitContribution.id'))
            ->assertJsonPath('data.validateContribution.outcome', 'APPLIED')
            ->assertJsonPath('data.validateContribution.contribution.material.slug', 'x-9999');
        $this->assertSame('contrib', Material::query()->where('slug', 'x-9999')->value('source'));
    }

    public function test_exactly_one_kind_of_proposal_is_accepted(): void
    {
        $this->submit($this->token($this->member()), [
            'line_material' => ['line_slug' => $this->line->slug, 'material_slug' => $this->material->slug, 'circulation' => 'regular', 'source_kind' => 'print', 'source_title' => 'Référence'],
            'series' => ['mode' => 'new', 'name' => 'X', 'source_kind' => 'print', 'source_title' => 'Référence'],
        ])->assertGraphQLValidationError('input', 'Provide exactly one of "segment", "trace", "event", "media", "line_material" or "series".');
    }
}

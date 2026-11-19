<?php

namespace Tests\Feature\GraphQL;

use App\Enums\AuditAction;
use App\Enums\NotificationKind;
use App\Enums\WebhookEvent;
use App\Models\AuditEvent;
use App\Models\Contribution;
use App\Models\Era;
use App\Models\Line;
use App\Models\LineSegment;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Services\Api\ApiTokens;
use App\Services\TraceContributionService;
use App\Services\Webhooks\HostResolver;
use App\Services\Webhooks\WebhookSubscriptions;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\ActsAsRoles;
use Tests\Support\BuildsRailNetwork;
use Tests\Support\FakeHostResolver;
use Tests\TestCase;

/**
 * Mutations GraphQL de contribution (submitContribution, validateContribution) : authentification
 * (jeton avec la capacité `contribute` ou session du site avec contrôle CSRF), règles de
 * l'espace de validation du site (deux approbations, motif de refus, pas de relecture de sa propre
 * contribution, compte suspendu refusé), effets (réputation, notifications, journal d'audit,
 * webhooks) et erreurs documentées (`extensions.code`, `extensions.validation`).
 */
class ContributionMutationsTest extends TestCase
{
    use ActsAsRoles;
    use BuildsRailNetwork;
    use MakesGraphQLRequests;
    use RefreshDatabase;

    private const SUBMIT = 'mutation ($input: ContributionInput!) {
        submitContribution(input: $input) {
            id type action status approvals approvals_required review_notes created_at reviewed_at payload
            line { slug code }
        }
    }';

    private const VALIDATE = 'mutation ($id: ID!, $decision: ContributionDecision!, $reason: String) {
        validateContribution(id: $id, decision: $decision, reason: $reason) {
            outcome
            contribution { id status approvals approvals_required review_notes reviewed_at }
        }
    }';

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRailNetwork();
        config(['api.rate_limits.anonymous' => 300, 'api.rate_limits.member' => 1200]);
    }

    // ─── Outils ─────────────────────────────────────────────────────────────

    private function token(User $user, bool $contribute = true): string
    {
        return app(ApiTokens::class)->create($user, 'Script', $contribute)->plainTextToken;
    }

    /** @return array<string, mixed> */
    private function segment(array $overrides = []): array
    {
        return array_replace_recursive([
            'line_code' => '999000',
            'era_year' => 2030,
            'pk_start_m' => 0,
            'pk_end_m' => 5000,
            'changes' => ['status' => 'abandoned', 'max_speed_kmh' => 80],
            'sources' => ['Arrêté préfectoral du 12/03/1998'],
            'notes' => 'Section fermée au trafic.',
        ], $overrides);
    }

    /** @return array<string, mixed> */
    private function trace(): array
    {
        return [
            'kind' => 'new_line',
            'new_line_name' => 'Ligne de Saint-Sever à Hagetmau',
            'network' => 'secondaire',
            'trace_source' => 'IGN, carte 1950 (SCAN 50 historique)',
            'portions' => [[
                'key' => 'p1',
                'label' => 'Saint-Sever - Audignon',
                'coordinates' => [[-0.5687, 43.7570], [-0.5900, 43.7300], [-0.6012, 43.7125]],
                'events' => [
                    ['type' => 'opening', 'precision' => 'day', 'date' => '1890-06-15', 'source' => 'Journal officiel du 16/06/1890'],
                    ['type' => 'closure_passenger', 'precision' => 'year', 'date' => '1938', 'source' => 'Chaix 1938'],
                ],
            ]],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return TestResponse<Response>
     */
    private function submitWithToken(string $token, array $input): TestResponse
    {
        return $this->withToken($token)->graphQL(self::SUBMIT, ['input' => $input]);
    }

    /** @return TestResponse<Response> */
    private function validateWithToken(string $token, int|string $id, string $decision = 'APPROVE', ?string $reason = null): TestResponse
    {
        return $this->withToken($token)->graphQL(self::VALIDATE, ['id' => (string) $id, 'decision' => $decision, 'reason' => $reason]);
    }

    /** Requête envoyée avec la session du site (cookie de session présent, compte connecté). */
    private function withSiteSession(User $user): static
    {
        return $this->actingAs($user)->withCredentials()->withCookie((string) config('session.cookie'), 'session-de-test');
    }

    private function pending(User $author, string $status = 'pending', array $payload = []): Contribution
    {
        return Contribution::factory()->create([
            'user_id' => $author->id,
            'type' => 'line_segment',
            'action' => 'update',
            'target_type' => Line::class,
            'target_id' => $this->line->id,
            'status' => $status,
            'payload' => [
                'line_code' => '999 000', 'line_name' => $this->line->name, 'era_year' => 2030,
                'pk_start_m' => 0, 'pk_end_m' => 5000, 'changes' => ['status' => 'abandoned'],
                'sources' => ['Arrêté'], 'notes' => null, ...$payload,
            ],
        ]);
    }

    /** @param  TestResponse<Response>  $response */
    private function assertErrorCode(TestResponse $response, string $code, string $field): void
    {
        $response->assertOk()
            ->assertJsonPath("data.$field", null)
            ->assertJsonPath('errors.0.extensions.code', $code);
    }

    // ─── Schéma ─────────────────────────────────────────────────────────────

    public function test_the_schema_has_two_mutations_and_no_subscription(): void
    {
        $this->graphQL('{ __schema { mutationType { name fields { name } } subscriptionType { name } } }')
            ->assertGraphQLErrorFree()
            ->assertExactJson(['data' => ['__schema' => [
                'mutationType' => ['name' => 'Mutation', 'fields' => [['name' => 'submitContribution'], ['name' => 'validateContribution']]],
                'subscriptionType' => null,
            ]]]);
    }

    public function test_every_mutation_argument_and_input_field_is_described(): void
    {
        $types = ['Mutation', 'ContributionInput', 'SegmentContributionInput', 'SegmentChangesInput', 'TraceContributionInput',
            'TracePortionInput', 'TraceEventInput', 'TraceReplacesInput', 'ContributionDecision', 'ContributionType',
            'ContributionAction', 'ContributionStatus', 'ContributionReviewOutcome', 'Contribution', 'ContributionReview'];
        foreach ($types as $type) {
            $data = $this->graphQL('query ($t: String!) { __type(name: $t) {
                description
                fields { name description args { name description } }
                inputFields { name description }
                enumValues { name description }
            } }', ['t' => $type])->assertGraphQLErrorFree()->json('data.__type');

            $this->assertNotEmpty($data['description'], "$type sans description");
            foreach ([...($data['fields'] ?? []), ...($data['inputFields'] ?? []), ...($data['enumValues'] ?? [])] as $field) {
                $this->assertNotEmpty($field['description'], "$type.{$field['name']} sans description");
                foreach ($field['args'] ?? [] as $arg) {
                    $this->assertNotEmpty($arg['description'], "$type.{$field['name']}({$arg['name']}) sans description");
                }
            }
        }
    }

    // ─── Authentification ──────────────────────────────────────────────────

    public function test_an_anonymous_caller_is_refused(): void
    {
        $this->assertErrorCode($this->graphQL(self::SUBMIT, ['input' => ['segment' => $this->segment()]]), 'unauthenticated', 'submitContribution');
        $this->assertErrorCode($this->graphQL(self::VALIDATE, ['id' => '1', 'decision' => 'APPROVE']), 'unauthenticated', 'validateContribution');
        $this->assertSame(0, Contribution::count());
    }

    public function test_a_read_only_token_gets_insufficient_scope(): void
    {
        $token = $this->token($this->member('validator'), contribute: false);

        $this->assertErrorCode($this->submitWithToken($token, ['segment' => $this->segment()]), 'insufficient_scope', 'submitContribution');
        $this->assertErrorCode($this->validateWithToken($token, $this->pending($this->member('contributor'))->id), 'insufficient_scope', 'validateContribution');
        $this->assertSame(1, Contribution::count());
        // Le même jeton lit toujours l'API.
        $this->withToken($token)->graphQL('{ eras { year } }')->assertGraphQLErrorFree();
    }

    public function test_invalid_and_suspended_tokens_are_refused_before_graphql(): void
    {
        $this->withToken('1|vff_inconnu')->graphQL(self::SUBMIT, ['input' => ['segment' => $this->segment()]])->assertUnauthorized();

        $user = $this->member('contributor');
        $token = $this->token($user);
        $user->forceFill(['suspended_at' => now()])->save();
        $this->withToken($token)->graphQL(self::SUBMIT, ['input' => ['segment' => $this->segment()]])
            ->assertForbidden()->assertJsonPath('error', 'account_suspended');
        $this->assertSame(0, Contribution::count());
    }

    public function test_a_contribute_token_submits_with_the_member_rate_limit(): void
    {
        $author = $this->member('contributor');

        $response = $this->submitWithToken($this->token($author), ['segment' => $this->segment()])
            ->assertGraphQLErrorFree()
            ->assertHeader('X-RateLimit-Limit', '1200')
            ->assertHeader('X-RateLimit-Remaining', '1199')
            ->assertJsonPath('data.submitContribution.type', 'LINE_SEGMENT')
            ->assertJsonPath('data.submitContribution.action', 'UPDATE')
            ->assertJsonPath('data.submitContribution.status', 'PENDING')
            ->assertJsonPath('data.submitContribution.approvals', 0)
            ->assertJsonPath('data.submitContribution.approvals_required', 2)
            ->assertJsonPath('data.submitContribution.reviewed_at', null)
            ->assertJsonPath('data.submitContribution.line.code', '999 000')
            ->assertJsonPath('data.submitContribution.payload.line_code', '999 000')
            ->assertJsonPath('data.submitContribution.payload.changes', ['status' => 'abandoned', 'max_speed_kmh' => 80]);

        $contribution = Contribution::sole();
        $this->assertSame((string) $contribution->id, $response->json('data.submitContribution.id'));
        $this->assertSame($author->id, $contribution->user_id);
        $this->assertSame('pending', $contribution->status->value);
        // Mêmes données que le formulaire du site (ordre des clés libre : colonne JSON).
        $this->assertEquals([
            'line_code' => '999 000', 'line_name' => $this->line->name, 'era_year' => 2030,
            'pk_start_m' => 0, 'pk_end_m' => 5000, 'changes' => ['status' => 'abandoned', 'max_speed_kmh' => 80],
            'sources' => ['Arrêté préfectoral du 12/03/1998'], 'notes' => 'Section fermée au trafic.',
        ], $contribution->payload);
    }

    public function test_the_site_session_needs_a_csrf_token_or_a_same_origin_request(): void
    {
        $author = $this->member('contributor');

        $this->assertErrorCode(
            $this->withSiteSession($author)->graphQL(self::SUBMIT, ['input' => ['segment' => $this->segment()]]),
            'csrf_token_mismatch',
            'submitContribution',
        );
        $this->assertErrorCode(
            $this->withSiteSession($author)->withHeader('X-CSRF-TOKEN', 'faux')->graphQL(self::SUBMIT, ['input' => ['segment' => $this->segment()]]),
            'csrf_token_mismatch',
            'submitContribution',
        );
        $this->assertErrorCode(
            $this->withSiteSession($author)->withHeader('Sec-Fetch-Site', 'cross-site')->graphQL(self::SUBMIT, ['input' => ['segment' => $this->segment()]]),
            'csrf_token_mismatch',
            'submitContribution',
        );
        $this->assertSame(0, Contribution::count());

        $this->withSiteSession($author)->withSession(['_token' => 'jeton-csrf-de-test'])
            ->withHeader('X-CSRF-TOKEN', 'jeton-csrf-de-test')
            ->graphQL(self::SUBMIT, ['input' => ['segment' => $this->segment()]])
            ->assertGraphQLErrorFree()
            // Une session ne relève pas la limite de débit : compteur anonyme de l'adresse IP.
            ->assertHeader('X-RateLimit-Limit', '300');

        $this->withSiteSession($author)->withHeader('Sec-Fetch-Site', 'same-origin')
            ->graphQL(self::SUBMIT, ['input' => ['segment' => $this->segment()]])
            ->assertGraphQLErrorFree();

        $this->assertSame(2, Contribution::where('user_id', $author->id)->count());
    }

    public function test_a_logged_in_user_without_the_session_cookie_is_anonymous(): void
    {
        $this->assertErrorCode(
            $this->actingAs($this->member('contributor'))->withHeader('Sec-Fetch-Site', 'same-origin')
                ->graphQL(self::SUBMIT, ['input' => ['segment' => $this->segment()]]),
            'unauthenticated',
            'submitContribution',
        );
    }

    public function test_read_queries_do_not_open_a_session(): void
    {
        $response = $this->graphQL('{ eras { year } }')->assertGraphQLErrorFree();
        $this->assertNull($response->headers->get('Set-Cookie'));
    }

    public function test_a_suspended_session_account_can_neither_submit_nor_review(): void
    {
        $until = now()->addWeek();
        $user = $this->member('validator', ['suspended_at' => now(), 'suspended_until' => $until]);

        $response = $this->withSiteSession($user)->withHeader('Sec-Fetch-Site', 'same-origin')
            ->graphQL(self::SUBMIT, ['input' => ['segment' => $this->segment()]]);
        $this->assertErrorCode($response, 'account_suspended', 'submitContribution');
        $response->assertJsonPath('errors.0.extensions.suspended_until', $until->toIso8601String());

        $c = $this->pending($this->member('contributor'));
        $this->assertErrorCode(
            $this->withSiteSession($user)->withHeader('Sec-Fetch-Site', 'same-origin')
                ->graphQL(self::VALIDATE, ['id' => (string) $c->id, 'decision' => 'APPROVE']),
            'account_suspended',
            'validateContribution',
        );
        $this->assertSame(1, Contribution::count());
        $this->assertSame(0, DB::table('contribution_approvals')->count());
    }

    // ─── submitContribution : saisie ───────────────────────────────────────

    public function test_exactly_one_kind_of_contribution_is_required(): void
    {
        $token = $this->token($this->member('contributor'));

        foreach ([[], ['segment' => $this->segment(), 'trace' => $this->trace()]] as $input) {
            $this->submitWithToken($token, $input)
                ->assertGraphQLValidationError('input', 'Provide exactly one of "segment", "trace", "event" or "media".');
        }
        $this->assertSame(0, Contribution::count());
    }

    /** @return array<string, array{0: array<string, mixed>, 1: string}> */
    public static function invalidSegments(): array
    {
        return [
            'sans source' => [['sources' => []], 'input.segment.sources'],
            'PK de fin avant le début' => [['pk_start_m' => 5000, 'pk_end_m' => 100], 'input.segment.pk_end_m'],
            'PK négatif' => [['pk_start_m' => -1], 'input.segment.pk_start_m'],
            'état inconnu' => [['changes' => ['status' => 'ferme']], 'input.segment.changes.status'],
            'trafic inconnu' => [['changes' => ['traffic_type' => 'tgv']], 'input.segment.changes.traffic_type'],
            'vitesse hors bornes' => [['changes' => ['max_speed_kmh' => 601]], 'input.segment.changes.max_speed_kmh'],
            'tension hors bornes' => [['changes' => ['voltage_v' => 60000]], 'input.segment.changes.voltage_v'],
            'remarques trop longues' => [['notes' => str_repeat('a', 2001)], 'input.segment.notes'],
            'ligne inconnue' => [['line_code' => '000001'], 'input.segment.line_code'],
            'ère inconnue' => [['era_year' => 1850], 'input.segment.era_year'],
        ];
    }

    /** @param  array<string, mixed>  $overrides */
    #[DataProvider('invalidSegments')]
    public function test_segment_input_is_validated_like_the_site(array $overrides, string $field): void
    {
        $segment = array_replace($this->segment(), $overrides);
        if (isset($overrides['changes'])) {
            $segment['changes'] = $overrides['changes'];
        }

        $this->submitWithToken($this->token($this->member('contributor')), ['segment' => $segment])
            ->assertGraphQLValidationKeys([$field]);
        $this->assertSame(0, Contribution::count());
    }

    public function test_a_segment_without_any_real_change_is_refused_in_english(): void
    {
        $this->submitWithToken($this->token($this->member('contributor')), ['segment' => array_replace($this->segment(), ['changes' => ['status' => null]])])
            ->assertGraphQLValidationError('input.segment.changes', __('editor.segment.errors.no_change', locale: 'en'));

        $this->submitWithToken($this->token($this->member('contributor')), ['segment' => array_replace($this->segment(), ['sources' => []])])
            ->assertGraphQLValidationError('input.segment.sources', __('editor.segment.errors.source_required', locale: 'en'));
        $this->assertSame(0, Contribution::count());
    }

    public function test_a_false_or_zero_value_is_a_real_change(): void
    {
        $this->submitWithToken($this->token($this->member('contributor')), ['segment' => array_replace($this->segment(), ['changes' => ['electrified' => false, 'max_speed_kmh' => 0]])])
            ->assertGraphQLErrorFree()
            ->assertJsonPath('data.submitContribution.payload.changes', ['electrified' => false, 'max_speed_kmh' => 0]);
    }

    public function test_a_trace_is_submitted_like_in_the_editor(): void
    {
        $author = $this->member('contributor');

        $this->submitWithToken($this->token($author), ['trace' => $this->trace()])
            ->assertGraphQLErrorFree()
            ->assertJsonPath('data.submitContribution.type', 'LINE_TRACE')
            ->assertJsonPath('data.submitContribution.action', 'CREATE')
            ->assertJsonPath('data.submitContribution.line', null)
            ->assertJsonPath('data.submitContribution.payload.kind', 'new_line')
            ->assertJsonPath('data.submitContribution.payload.portions.0.events.1.date', '1938-01-01');

        $contribution = Contribution::sole();
        $this->assertSame('line_trace', $contribution->type);
        $this->assertSame($author->id, $contribution->user_id);
        $this->assertGreaterThan(3000, $contribution->payload['portions'][0]['length_m']);
    }

    public function test_a_trace_correction_targets_the_line(): void
    {
        $trace = [...$this->trace(), 'kind' => 'correction', 'line_slug' => $this->line->slug];
        unset($trace['new_line_name']);

        $this->submitWithToken($this->token($this->member('contributor')), ['trace' => $trace])
            ->assertGraphQLErrorFree()
            ->assertJsonPath('data.submitContribution.action', 'UPDATE')
            ->assertJsonPath('data.submitContribution.line.slug', $this->line->slug);
    }

    public function test_trace_input_is_validated_like_the_editor(): void
    {
        $token = $this->token($this->member('contributor'));

        $trace = $this->trace();
        $trace['portions'][0]['events'][0]['date'] = '1890';  // précision « jour » attendue
        $this->submitWithToken($token, ['trace' => $trace])
            ->assertGraphQLValidationError('input.trace.portions.0.events.0.date', __('editor.errors.bad_date', locale: 'en'));

        $trace = $this->trace();
        $trace['portions'][0]['events'][0]['source'] = 'JO';  // 3 caractères au moins
        $this->submitWithToken($token, ['trace' => $trace])->assertGraphQLValidationError('input.trace.portions.0.events.0.source', __('editor.errors.source_required', locale: 'en'));

        $trace = $this->trace();
        $trace['portions'][0]['coordinates'] = [[-0.5687, 43.7570]];
        $this->submitWithToken($token, ['trace' => $trace])->assertGraphQLValidationKeys(['input.trace.portions.0.coordinates']);

        $trace = [...$this->trace(), 'kind' => 'correction', 'line_slug' => 'ligne-inconnue'];
        $this->submitWithToken($token, ['trace' => $trace])->assertGraphQLValidationKeys(['input.trace.line_slug']);

        $this->assertSame(0, Contribution::count());
    }

    public function test_a_submission_notifies_every_reviewer_but_its_author(): void
    {
        $author = $this->member('validator');
        $other = $this->member('validator');
        $contributor = $this->member('contributor');

        $this->submitWithToken($this->token($author), ['segment' => $this->segment()])->assertGraphQLErrorFree();

        $this->assertSame(0, $author->notifications()->count());
        $this->assertSame(0, $contributor->notifications()->count());
        $this->assertSame(NotificationKind::AwaitingReview->value, $other->notifications()->sole()->data['kind']);
    }

    // ─── validateContribution ──────────────────────────────────────────────

    #[DataProvider('nonValidatorRoles')]
    public function test_review_is_forbidden_below_validator(string $role): void
    {
        $c = $this->pending($this->member('contributor'));

        $this->assertErrorCode($this->validateWithToken($this->token($this->member($role)), $c->id), 'forbidden', 'validateContribution');
        $this->assertSame('pending', $c->fresh()->status->value);
    }

    public function test_two_independent_approvals_publish_like_the_site(): void
    {
        $author = $this->member('contributor');
        $c = $this->pending($author, payload: ['changes' => ['status' => 'abandoned', 'max_speed_kmh' => 60]]);
        $segment = LineSegment::where('line_id', $this->line->id)->where('era_id', $this->currentEra->id)->sole();
        [$v1, $v2] = [$this->member('validator'), $this->member('admin')];
        [$t1, $t2] = [$this->token($v1), $this->token($v2)];

        $this->validateWithToken($t1, $c->id)
            ->assertGraphQLErrorFree()
            ->assertJsonPath('data.validateContribution.outcome', 'APPROVED')
            ->assertJsonPath('data.validateContribution.contribution.status', 'APPROVED')
            ->assertJsonPath('data.validateContribution.contribution.approvals', 1);
        $this->assertSame('in_service', $segment->fresh()->status->value);

        $this->assertErrorCode($this->validateWithToken($t1, $c->id), 'already_approved', 'validateContribution');
        $this->assertSame(1, DB::table('contribution_approvals')->count());

        $this->validateWithToken($t2, $c->id)
            ->assertGraphQLErrorFree()
            ->assertJsonPath('data.validateContribution.outcome', 'APPLIED')
            ->assertJsonPath('data.validateContribution.contribution.status', 'APPLIED')
            ->assertJsonPath('data.validateContribution.contribution.approvals', 2);

        $c->refresh();
        $this->assertSame($v2->id, $c->reviewed_by);
        $this->assertSame('abandoned', $segment->fresh()->status->value);
        $this->assertSame(60, $segment->fresh()->max_speed_kmh);

        // Journal d'audit : une approbation puis la publication, au nom des validateurs.
        $events = AuditEvent::query()->where('contribution_id', $c->id)->orderBy('id')->get();
        $this->assertSame([AuditAction::ContributionApproved, AuditAction::ContributionApplied], $events->pluck('action')->all());
        $this->assertSame([$v1->id, $v2->id], $events->pluck('actor_id')->all());

        // Réputation recalculée : une contribution acceptée pour l'auteur, une relecture par validateur.
        $this->assertSame(1, $author->fresh()->reputation_applied);
        $this->assertSame(1, $v1->fresh()->reputation_reviews);
        $this->assertSame(1, $v2->fresh()->reputation_reviews);

        // Notifications à l'auteur : approbation partielle, puis publication.
        $kinds = $author->notifications()->orderBy('created_at')->get()->pluck('data.kind')->all();
        $this->assertEqualsCanonicalizing([NotificationKind::Approved->value, NotificationKind::Applied->value], $kinds);

        $this->assertErrorCode($this->validateWithToken($this->token($this->member('validator')), $c->id), 'already_closed', 'validateContribution');
    }

    public function test_publication_fires_the_contribution_applied_webhook(): void
    {
        Queue::fake();
        $this->app->instance(HostResolver::class, new FakeHostResolver(['hooks.example.org' => ['93.184.215.14']]));
        app(WebhookSubscriptions::class)->create($this->member('contributor'), 'https://hooks.example.org/vff', [WebhookEvent::ContributionApplied]);
        $c = $this->pending($this->member('contributor'), 'approved');
        DB::table('contribution_approvals')->insert(['contribution_id' => $c->id, 'user_id' => $this->member('validator')->id, 'approved_at' => now()]);

        $this->validateWithToken($this->token($this->member('validator')), $c->id)
            ->assertGraphQLErrorFree()
            ->assertJsonPath('data.validateContribution.outcome', 'APPLIED');

        $this->assertSame('contribution.applied', WebhookDelivery::sole()->payload['type']);
    }

    public function test_a_validator_cannot_review_their_own_contribution(): void
    {
        $validator = $this->member('validator');
        $c = $this->pending($validator);
        $token = $this->token($validator);

        $this->assertErrorCode($this->validateWithToken($token, $c->id), 'own_contribution', 'validateContribution');
        $this->assertErrorCode($this->validateWithToken($token, $c->id, 'REJECT', 'Source introuvable.'), 'own_contribution', 'validateContribution');
        $this->assertSame('pending', $c->fresh()->status->value);
        $this->assertSame(0, DB::table('contribution_approvals')->count());
    }

    public function test_a_rejection_needs_a_reason_sent_to_the_author(): void
    {
        $author = $this->member('contributor');
        $validator = $this->member('validator');
        $c = $this->pending($author);
        $token = $this->token($validator);

        foreach ([null, '', '   ', 'Non'] as $reason) {
            $this->validateWithToken($token, $c->id, 'REJECT', $reason)
                ->assertGraphQLValidationError('reason', __('editor.validate.errors.reason_required', ['min' => 5], 'en'));
        }
        $this->validateWithToken($token, $c->id, 'REJECT', str_repeat('a', 1001))
            ->assertGraphQLValidationError('reason', __('editor.validate.errors.reason_max', ['max' => 1000], 'en'));
        $this->assertSame('pending', $c->fresh()->status->value);

        $this->validateWithToken($token, $c->id, 'REJECT', '  La source citée ne mentionne pas cette section.  ')
            ->assertGraphQLErrorFree()
            ->assertJsonPath('data.validateContribution.outcome', 'REJECTED')
            ->assertJsonPath('data.validateContribution.contribution.status', 'REJECTED')
            ->assertJsonPath('data.validateContribution.contribution.review_notes', 'La source citée ne mentionne pas cette section.');

        $c->refresh();
        $this->assertSame($validator->id, $c->reviewed_by);
        $this->assertSame(1, $author->fresh()->reputation_rejected);
        $this->assertSame(1, $validator->fresh()->reputation_reviews);
        $this->assertSame(NotificationKind::Rejected->value, $author->notifications()->sole()->data['kind']);
        $audit = AuditEvent::query()->where('contribution_id', $c->id)->sole();
        $this->assertSame(AuditAction::ContributionRejected, $audit->action);
        $this->assertEquals(['type' => 'line_segment', 'previous_status' => 'pending', 'with_reason' => true], $audit->context);

        $this->assertErrorCode($this->validateWithToken($token, $c->id, 'REJECT', 'Deuxième refus.'), 'already_closed', 'validateContribution');
        $this->assertErrorCode($this->validateWithToken($token, $c->id), 'already_closed', 'validateContribution');
    }

    public function test_an_applied_contribution_cannot_be_rejected(): void
    {
        $c = $this->pending($this->member('contributor'), 'applied');

        $this->assertErrorCode($this->validateWithToken($this->token($this->member('validator')), $c->id, 'REJECT', 'Trop tard pour refuser.'), 'already_closed', 'validateContribution');
        $this->assertSame('applied', $c->fresh()->status->value);
    }

    /** @return array<string, array{0: string}> */
    public static function unknownIds(): array
    {
        return ['inconnu' => ['999999'], 'non numérique' => ['abc'], 'zéro' => ['0'], 'négatif' => ['-3']];
    }

    #[DataProvider('unknownIds')]
    public function test_an_unknown_contribution_is_not_found(string $id): void
    {
        $token = $this->token($this->member('validator'));

        $this->assertErrorCode($this->validateWithToken($token, $id), 'not_found', 'validateContribution');
        $this->assertErrorCode($this->validateWithToken($token, $id, 'REJECT', 'Motif suffisant.'), 'not_found', 'validateContribution');
    }

    public function test_an_application_failure_is_reported_and_rolled_back(): void
    {
        Era::factory()->create(['year' => 1900]);
        $c = $this->pending($this->member('contributor'), 'approved', ['era_year' => 1900]);
        DB::table('contribution_approvals')->insert(['contribution_id' => $c->id, 'user_id' => $this->member('validator')->id, 'approved_at' => now()]);

        $response = $this->validateWithToken($this->token($this->member('validator')), $c->id);
        $this->assertErrorCode($response, 'application_failed', 'validateContribution');
        // Raison stable, jamais le message de l'erreur (qui reste dans le journal).
        $response->assertJsonPath('errors.0.extensions.reason', 'no_geometry');
        $this->assertStringNotContainsString('Pas de géométrie', (string) $response->getContent());

        $this->assertSame('approved', $c->fresh()->status->value);
        $this->assertSame(1, DB::table('contribution_approvals')->count());
    }

    public function test_an_unexpected_publication_error_never_reaches_the_caller(): void
    {
        // Erreur SQL pendant la publication d'un tracé : QueryException hérite de RuntimeException.
        $this->app->instance(TraceContributionService::class, new class extends TraceContributionService
        {
            public function apply(Contribution $contribution): array
            {
                throw new QueryException('pgsql', 'insert into lines (name, secret_column) values (?, ?)', ['Ligne témoin', 'valeur-privée'],
                    new \PDOException('SQLSTATE[23505]: Unique violation: 7 ERROR:  duplicate key value violates unique constraint "lines_slug_unique"'));
            }
        });
        $c = Contribution::factory()->create([
            'user_id' => $this->member('contributor')->id, 'type' => TraceContributionService::TYPE, 'action' => 'create',
            'status' => 'approved', 'payload' => ['kind' => 'new_line', 'new_line_name' => 'Ligne témoin', 'portions' => []],
        ]);
        DB::table('contribution_approvals')->insert(['contribution_id' => $c->id, 'user_id' => $this->member('validator')->id, 'approved_at' => now()]);

        $response = $this->validateWithToken($this->token($this->member('admin')), $c->id);
        $this->assertErrorCode($response, 'application_failed', 'validateContribution');
        $response->assertJsonPath('errors.0.extensions.reason', 'internal_error');
        foreach (['SQLSTATE', 'insert into', 'secret_column', 'valeur-privée', 'lines_slug_unique'] as $leak) {
            $this->assertStringNotContainsString($leak, (string) $response->getContent());
        }
        $this->assertSame('approved', $c->fresh()->status->value);
        $this->assertSame(1, DB::table('contribution_approvals')->count(), 'approbation annulée avec la transaction');
    }

    public function test_a_validator_reviews_through_the_site_session(): void
    {
        $c = $this->pending($this->member('contributor'));

        $this->withSiteSession($this->member('validator'))->withHeader('Sec-Fetch-Site', 'same-origin')
            ->graphQL(self::VALIDATE, ['id' => (string) $c->id, 'decision' => 'APPROVE'])
            ->assertGraphQLErrorFree()
            ->assertJsonPath('data.validateContribution.outcome', 'APPROVED');
    }

    /** Opération à `$n` alias de submitContribution (a0, a1…), même proposition pour chacun. */
    private static function aliasedSubmissions(int $n): string
    {
        $fields = implode("\n", array_map(fn (int $i): string => "a{$i}: submitContribution(input: \$input) { id }", range(0, $n - 1)));

        return "mutation (\$input: ContributionInput!) {\n{$fields}\n}";
    }

    public function test_each_aliased_mutation_counts_as_one_request(): void
    {
        $token = $this->token($this->member('contributor'));

        $this->withToken($token)->graphQL(self::aliasedSubmissions(3), ['input' => ['segment' => $this->segment()]])
            ->assertGraphQLErrorFree()
            ->assertHeader('X-RateLimit-Limit', '1200')
            ->assertHeader('X-RateLimit-Remaining', '1197');
        $this->assertSame(3, Contribution::count());

        // Lot de deux opérations dans un seul POST : deux mutations, deux requêtes.
        $this->withToken($token)->postJson('/graphql', [
            ['query' => self::SUBMIT, 'variables' => ['input' => ['segment' => $this->segment()]]],
            ['query' => self::SUBMIT, 'variables' => ['input' => ['segment' => $this->segment()]]],
        ])->assertOk()->assertHeader('X-RateLimit-Remaining', '1195');
        $this->assertSame(5, Contribution::count());
    }

    public function test_mutations_beyond_the_rate_limit_are_refused_without_writing(): void
    {
        config(['api.rate_limits.member' => 3]);
        $token = $this->token($this->member('contributor'));

        $response = $this->withToken($token)->graphQL(self::aliasedSubmissions(5), ['input' => ['segment' => $this->segment()]])->assertOk();
        // La requête HTTP compte la première mutation : la 4e dépasse la limite et n'est pas faite. Les
        // champs de mutation sont non nuls : son erreur annule `data` entier, et la 5e n'est pas exécutée.
        $this->assertSame([['a3']], array_column($response->json('errors'), 'path'));
        $this->assertSame('too_many_requests', $response->json('errors.0.extensions.code'));
        $this->assertGreaterThan(0, $response->json('errors.0.extensions.retry_after'));
        $response->assertJsonPath('data', null);
        $this->assertSame(3, Contribution::count());

        // Limite atteinte : la requête suivante est refusée avant GraphQL.
        $this->submitWithToken($token, ['segment' => $this->segment()])->assertStatus(429);
        $this->assertDatabaseCount('contributions', 3);
    }

    public function test_mutations_are_refused_over_get(): void
    {
        $token = $this->token($this->member('contributor'));
        $query = rawurlencode('mutation { submitContribution(input: {}) { id } }');

        $this->withToken($token)->getJson("/graphql?query=$query")
            ->assertOk()
            ->assertJsonPath('data.submitContribution', null)
            ->assertJsonPath('errors.0.extensions.code', 'post_required');
        $this->assertSame(0, Contribution::count());
    }
}

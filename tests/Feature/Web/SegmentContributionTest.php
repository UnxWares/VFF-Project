<?php

namespace Tests\Feature\Web;

use App\Enums\SegmentStatus;
use App\Enums\TrafficType;
use App\Models\Contribution;
use App\Models\Era;
use App\Models\Line;
use App\Models\LineSegment;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ActsAsRoles;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * Contributions « segment par PK » (formulaire edit-segment) et modération :
 * accès par rôle, validation, isolement des contributions d'autrui, double
 * approbation indépendante, application limitée à la portion de PK visée.
 */
class SegmentContributionTest extends TestCase
{
    use ActsAsRoles;
    use BuildsRailNetwork;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRailNetwork();
        $this->withCookie('locale', 'fr');
    }

    /** @param  array<string, mixed>  $overrides */
    private function payload(array $overrides = []): array
    {
        return array_replace_recursive([
            'line_code' => '999000',
            'era_year' => 2030,
            'pk_start_m' => 0,
            'pk_end_m' => 5000,
            'changes' => ['status' => 'abandoned', 'max_speed_kmh' => 80, 'electrified' => null],
            'sources' => ['Arrêté préfectoral du 12/03/1998'],
            'notes' => 'Section fermée au trafic.',
        ], $overrides);
    }

    private function contribution(int $userId, string $status = 'pending', array $attributes = []): Contribution
    {
        return Contribution::factory()->create([
            'user_id' => $userId,
            'type' => 'line_segment',
            'action' => 'update',
            'target_type' => Line::class,
            'target_id' => $this->line->id,
            'status' => $status,
            'payload' => [
                'line_code' => '999 000', 'line_name' => $this->line->name, 'era_year' => 2030,
                'pk_start_m' => 0, 'pk_end_m' => 5000, 'changes' => ['status' => 'abandoned'], 'sources' => [], 'notes' => null,
            ],
            ...$attributes,
        ]);
    }

    /** Deuxième tronçon 2030 dans le prolongement (Versailles → ouest), pour viser une portion de PK. */
    private function addWesternSegment(): LineSegment
    {
        return LineSegment::factory()->create([
            'line_id' => $this->line->id,
            'era_id' => $this->currentEra->id,
            'geom' => LineString::make([Point::makeGeodetic(48.8091, 2.1347), Point::makeGeodetic(48.8000, 2.0000)]),
            'status' => 'in_service',
            'traffic_type' => 'passenger',
            'max_speed_kmh' => 120,
            'is_heritage' => false,
        ]);
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function memberRoutes(): array
    {
        return [
            'mes contributions' => ['get', '/contribute/mine'],
            'formulaire PK' => ['get', '/contribute/edit-segment'],
            'envoi PK' => ['post', '/contribute/edit-segment'],
            'remerciements' => ['get', '/contribute/thanks/1'],
            'modération' => ['get', '/validate'],
            'approbation' => ['post', '/validate/1/approve'],
            'refus' => ['post', '/validate/1/reject'],
        ];
    }

    #[DataProvider('memberRoutes')]
    public function test_guests_are_redirected_to_login(string $method, string $uri): void
    {
        $this->{$method}($uri)->assertRedirect('/login');
        $this->{$method.'Json'}($uri)->assertUnauthorized();
    }

    public function test_edit_form_preselects_the_line_whatever_the_spacing_of_its_code(): void
    {
        $this->actingAs($this->member())
            ->get('/contribute/edit-segment?line=999000')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Contribute/EditSegment')
                ->where('preselectedLine', ['slug' => $this->line->slug, 'code' => '999 000', 'name' => $this->line->name])
                ->where('eras', [['year' => 1960, 'is_current' => false], ['year' => 2030, 'is_current' => true]])
                ->where('statuses', array_map(fn ($c) => $c->value, SegmentStatus::cases()))
                ->where('trafficTypes', array_map(fn ($c) => $c->value, TrafficType::cases())));
    }

    public function test_edit_form_without_or_with_unknown_line(): void
    {
        $user = $this->member();

        $this->actingAs($user)->get('/contribute/edit-segment')
            ->assertInertia(fn (Assert $page) => $page->where('preselectedLine', null));
        $this->actingAs($user)->get('/contribute/edit-segment?line='.urlencode("999' OR 1=1 --"))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('preselectedLine', null));
    }

    public function test_any_member_can_propose_and_the_payload_is_normalised(): void
    {
        $author = $this->member();

        $response = $this->actingAs($author)->post('/contribute/edit-segment', $this->payload([
            'line_code' => ' 999 000 ',
            // Champs privilégiés : ignorés (pas d'affectation de masse).
            'status' => 'applied',
            'user_id' => 12345,
            'reviewed_by' => $author->id,
        ]));

        $c = Contribution::sole();
        $response->assertRedirect(route('contribute.thanks', ['id' => $c->id]));
        $this->assertSame($author->id, $c->user_id);
        $this->assertSame('pending', $c->status->value);
        $this->assertSame('update', $c->action->value);
        $this->assertSame('line_segment', $c->type);
        $this->assertSame(Line::class, $c->target_type);
        $this->assertSame($this->line->id, $c->target_id);
        $this->assertNull($c->reviewed_by);
        // jsonb ne conserve pas l'ordre des clés : comparaison triée, types stricts.
        $payload = $c->payload;
        ksort($payload);
        $expected = [
            'line_code' => '999 000',
            'line_name' => $this->line->name,
            'era_year' => 2030,
            'pk_start_m' => 0,
            'pk_end_m' => 5000,
            // null retirés : seuls les changements explicites sont proposés.
            'changes' => ['status' => 'abandoned', 'max_speed_kmh' => 80],
            'sources' => ['Arrêté préfectoral du 12/03/1998'],
            'notes' => 'Section fermée au trafic.',
        ];
        ksort($expected);
        $this->assertSame($expected, $payload);
    }

    /** @return array<string, array{0: array<string, mixed>, 1: array<int, string>}> */
    public static function invalidSubmissions(): array
    {
        return [
            'PK fin avant début' => [['pk_start_m' => 5000, 'pk_end_m' => 5000], ['pk_end_m']],
            'PK négatif' => [['pk_start_m' => -1], ['pk_start_m']],
            'PK trop grand' => [['pk_end_m' => 10_000_000], ['pk_end_m']],
            'ère non entière' => [['era_year' => 'années 30'], ['era_year']],
            'tension absurde' => [['changes' => ['voltage_v' => 60000]], ['changes.voltage_v']],
            'vitesse absurde' => [['changes' => ['max_speed_kmh' => 601]], ['changes.max_speed_kmh']],
            'électrifiée non booléen' => [['changes' => ['electrified' => 'peut-être']], ['changes.electrified']],
            'statut hors énumération' => [['changes' => ['status' => 'detruite']], ['changes.status']],
            'trafic hors énumération' => [['changes' => ['traffic_type' => 'passengers']], ['changes.traffic_type']],
            'source trop longue' => [['sources' => [str_repeat('s', 501)]], ['sources.0']],
            'notes trop longues' => [['notes' => str_repeat('n', 2001)], ['notes']],
        ];
    }

    #[DataProvider('invalidSubmissions')]
    public function test_submission_validation(array $overrides, array $errors): void
    {
        $this->actingAs($this->member())
            ->post('/contribute/edit-segment', $this->payload($overrides))
            ->assertSessionHasErrors($errors);

        $this->assertSame(0, Contribution::count());
    }

    public function test_required_fields(): void
    {
        $this->actingAs($this->member())
            ->post('/contribute/edit-segment', [])
            ->assertSessionHasErrors(['line_code', 'era_year', 'pk_start_m', 'pk_end_m', 'changes']);
    }

    public function test_every_enum_value_is_accepted(): void
    {
        $user = $this->member();
        foreach (SegmentStatus::cases() as $status) {
            $this->actingAs($user)->post('/contribute/edit-segment', $this->payload(['changes' => ['status' => $status->value]]))
                ->assertSessionHasNoErrors();
        }
        foreach (TrafficType::cases() as $traffic) {
            $this->actingAs($user)->post('/contribute/edit-segment', $this->payload(['changes' => ['traffic_type' => $traffic->value]]))
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(count(SegmentStatus::cases()) + count(TrafficType::cases()), Contribution::count());
    }

    public function test_unknown_line_or_era_is_404(): void
    {
        $user = $this->member();

        $this->actingAs($user)->post('/contribute/edit-segment', $this->payload(['line_code' => '123456']))->assertNotFound();
        $this->actingAs($user)->post('/contribute/edit-segment', $this->payload(['era_year' => 1955]))->assertNotFound();
        $this->assertSame(0, Contribution::count());
    }

    public function test_thanks_page_is_visible_to_its_author_and_to_moderators_only(): void
    {
        $author = $this->member('contributor');
        $c = $this->contribution($author->id);

        $this->actingAs($author)->get("/contribute/thanks/{$c->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Contribute/Thanks')
                ->where('contribution.id', $c->id)
                ->where('contribution.type', 'line_segment')
                ->where('contribution.payload.line_code', '999 000'));

        $this->actingAs($this->member('contributor'))->get("/contribute/thanks/{$c->id}")->assertNotFound();
        $this->actingAs($this->member())->get("/contribute/thanks/{$c->id}")->assertNotFound();
        $this->actingAs($this->member('validator'))->get("/contribute/thanks/{$c->id}")->assertOk();
        $this->actingAs($this->member('admin'))->get("/contribute/thanks/{$c->id}")->assertOk();
        $this->actingAs($author)->get('/contribute/thanks/999999')->assertNotFound();
    }

    /** @return array<string, array{0: string}> */
    public static function nonNumericIds(): array
    {
        return [
            'lettres' => ['abc'],
            'injection' => ['1 OR 1=1'],
            'négatif' => ['-1'],
            'décimal' => ['1.5'],
        ];
    }

    #[DataProvider('nonNumericIds')]
    public function test_non_numeric_ids_are_404_not_500(string $id): void
    {
        $validator = $this->member('validator');

        $this->actingAs($validator)->get('/contribute/thanks/'.rawurlencode($id))->assertNotFound();
        $this->actingAs($validator)->post('/validate/'.rawurlencode($id).'/approve')->assertNotFound();
        $this->actingAs($validator)->post('/validate/'.rawurlencode($id).'/reject')->assertNotFound();
    }

    public function test_mine_lists_only_my_contributions_newest_first(): void
    {
        $me = $this->member('contributor');
        $other = $this->member('contributor');
        $old = $this->contribution($me->id, 'rejected', ['created_at' => now()->subDays(3), 'review_notes' => 'Source insuffisante']);
        $recent = $this->contribution($me->id, 'approved', ['created_at' => now()->subDay()]);
        $this->contribution($other->id);
        DB::table('contribution_approvals')->insert([
            'contribution_id' => $recent->id, 'user_id' => $this->member('validator')->id, 'approved_at' => now(),
        ]);

        $this->actingAs($me)->get('/contribute/mine')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Contribute/Mine')
                ->has('contributions', 2)
                ->where('contributions.0.id', $recent->id)
                ->where('contributions.0.status', 'approved')
                ->where('contributions.0.approval_count', 1)
                ->where('contributions.0.created_at', $recent->created_at->format('Y-m-d H:i'))
                ->where('contributions.1.id', $old->id)
                ->where('contributions.1.status', 'rejected')
                ->where('contributions.1.review_notes', 'Source insuffisante')
                ->where('contributions.1.approval_count', 0));
    }

    #[DataProvider('nonValidatorRoles')]
    public function test_moderation_is_forbidden_below_validator(string $role): void
    {
        $user = $this->member($role);
        $c = $this->contribution($this->member('contributor')->id);

        $this->actingAs($user)->get('/validate')->assertForbidden();
        $this->actingAs($user)->post("/validate/{$c->id}/approve")->assertForbidden();
        $this->actingAs($user)->post("/validate/{$c->id}/reject")->assertForbidden();

        $this->assertSame('pending', $c->fresh()->status->value);
        $this->assertSame(0, DB::table('contribution_approvals')->count());
    }

    #[DataProvider('validatorRoles')]
    public function test_moderation_list_shows_open_contributions_only(string $role): void
    {
        $moderator = $this->member($role);
        $author = $this->member('contributor', ['name' => 'Auteur']);
        $pending = $this->contribution($author->id, 'pending', ['created_at' => now()->subHours(2)]);
        $partial = $this->contribution($moderator->id, 'approved', ['created_at' => now()->subHour()]);
        $this->contribution($author->id, 'rejected');
        $this->contribution($author->id, 'applied');
        Contribution::factory()->create(['user_id' => $author->id, 'type' => 'station', 'status' => 'pending']);

        $this->actingAs($moderator)->get('/validate')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Validate/Index')
                ->where('threshold', 2)
                ->has('contributions', 2)
                ->where('contributions.0.id', $pending->id)
                ->where('contributions.0.user.name', 'Auteur')
                ->where('contributions.0.is_mine', false)
                ->where('contributions.0.approval_count', 0)
                ->where('contributions.0.approved_by_me', false)
                ->where('contributions.0.target', [
                    'slug' => $this->line->slug, 'name' => $this->line->name, 'code' => '999 000', 'network' => null,
                ])
                ->missing('contributions.0.nearby')
                ->where('contributions.1.id', $partial->id)
                ->where('contributions.1.status', 'approved')
                ->where('contributions.1.is_mine', true));
    }

    public function test_two_independent_approvals_apply_the_change_to_the_targeted_pk_range_only(): void
    {
        $western = $this->addWesternSegment();
        $eastern = LineSegment::where('line_id', $this->line->id)->where('era_id', $this->currentEra->id)
            ->where('id', '<>', $western->id)->sole();
        $past = LineSegment::where('line_id', $this->line->id)->where('era_id', $this->pastEra->id)->sole();
        $c = $this->contribution($this->member('contributor')->id, 'pending', ['payload' => [
            'line_code' => '999 000', 'line_name' => $this->line->name, 'era_year' => 2030,
            'pk_start_m' => 0, 'pk_end_m' => 5000,
            'changes' => ['status' => 'abandoned', 'max_speed_kmh' => 60, 'unknown_column' => 'x'],
            'sources' => [], 'notes' => null,
        ]]);
        [$v1, $v2] = [$this->member('validator'), $this->member('admin')];

        $this->actingAs($v1)->from('/validate')->post("/validate/{$c->id}/approve")
            ->assertRedirect('/validate')
            ->assertSessionHas('success', '1/2 approbation(s) : en attente d’un second validateur.');
        $this->assertSame('approved', $c->fresh()->status->value);
        $this->assertSame('in_service', $eastern->fresh()->status->value);

        $this->actingAs($v1)->post("/validate/{$c->id}/approve")
            ->assertSessionHas('error', 'Vous avez déjà approuvé cette contribution.');
        $this->assertSame(1, DB::table('contribution_approvals')->count());

        $this->actingAs($v2)->post("/validate/{$c->id}/approve")
            ->assertSessionHas('success', '2/2 approbations : contribution publiée.');

        $c->refresh();
        $this->assertSame('applied', $c->status->value);
        $this->assertSame($v2->id, $c->reviewed_by);
        $this->assertNotNull($c->reviewed_at);

        // Seule la portion PK 0-5 km (tronçon est, ère 2030) change ; colonne inconnue ignorée.
        $this->assertSame('abandoned', $eastern->fresh()->status->value);
        $this->assertSame(60, $eastern->fresh()->max_speed_kmh);
        $this->assertSame('in_service', $western->fresh()->status->value);
        $this->assertSame(120, $western->fresh()->max_speed_kmh);
        $this->assertSame('in_service', $past->fresh()->status->value);

        $this->actingAs($this->member('validator'))->post("/validate/{$c->id}/approve")
            ->assertSessionHas('error', 'Cette contribution a déjà été traitée.');
    }

    public function test_application_failure_is_reported_and_rolled_back(): void
    {
        // Ère sans géométrie pour la ligne : l'application échoue proprement.
        Era::factory()->create(['year' => 1900]);
        $c = $this->contribution($this->member('contributor')->id, 'approved', ['payload' => [
            'line_code' => '999 000', 'line_name' => $this->line->name, 'era_year' => 1900,
            'pk_start_m' => 0, 'pk_end_m' => 5000, 'changes' => ['status' => 'abandoned'], 'sources' => [], 'notes' => null,
        ]]);
        DB::table('contribution_approvals')->insert(['contribution_id' => $c->id, 'user_id' => $this->member('validator')->id, 'approved_at' => now()]);

        $this->actingAs($this->member('validator'))->post("/validate/{$c->id}/approve")
            ->assertSessionHas('error', 'Échec de la publication : Pas de géométrie pour 999 000 ère 1900');

        $this->assertSame('approved', $c->fresh()->status->value);
        $this->assertSame(1, DB::table('contribution_approvals')->count(), 'approbation annulée avec la transaction');
    }

    public function test_author_cannot_approve_their_own_contribution(): void
    {
        $validator = $this->member('validator');
        $c = $this->contribution($validator->id);

        $this->actingAs($validator)->post("/validate/{$c->id}/approve")
            ->assertSessionHas('error', 'Vous ne pouvez pas approuver votre propre contribution.');
        $this->assertSame(0, DB::table('contribution_approvals')->count());
    }

    public function test_rejected_contribution_cannot_be_approved(): void
    {
        $c = $this->contribution($this->member()->id, 'rejected');

        $this->actingAs($this->member('validator'))->post("/validate/{$c->id}/approve")
            ->assertSessionHas('error', 'Cette contribution a déjà été traitée.');
        $this->assertSame('rejected', $c->fresh()->status->value);
    }

    public function test_reject_records_the_reviewer_and_notes(): void
    {
        $c = $this->contribution($this->member()->id);
        $validator = $this->member('validator');

        $this->actingAs($validator)->post("/validate/{$c->id}/reject", ['notes' => 'Aucune source vérifiable.'])
            ->assertSessionHas('success', 'Contribution refusée.');

        $c->refresh();
        $this->assertSame('rejected', $c->status->value);
        $this->assertSame($validator->id, $c->reviewed_by);
        $this->assertSame('Aucune source vérifiable.', $c->review_notes);
        $this->assertNotNull($c->reviewed_at);
    }

    public function test_applied_contribution_cannot_be_rejected(): void
    {
        $c = $this->contribution($this->member()->id, 'applied');

        $this->actingAs($this->member('validator'))->post("/validate/{$c->id}/reject")
            ->assertSessionHas('error', 'Cette contribution a déjà été traitée.');
        $this->assertSame('applied', $c->fresh()->status->value);
    }

    public function test_reject_notes_are_limited(): void
    {
        $c = $this->contribution($this->member()->id);

        $this->actingAs($this->member('validator'))->post("/validate/{$c->id}/reject", ['notes' => str_repeat('x', 1001)])
            ->assertSessionHasErrors('notes');
        $this->assertSame('pending', $c->fresh()->status->value);
    }

    public function test_unknown_contribution_is_404_for_moderators(): void
    {
        $validator = $this->member('validator');

        $this->actingAs($validator)->post('/validate/999999/approve')->assertNotFound();
        $this->actingAs($validator)->post('/validate/999999/reject')->assertNotFound();
    }
}

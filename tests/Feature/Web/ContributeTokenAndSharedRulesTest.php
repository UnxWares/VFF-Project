<?php

namespace Tests\Feature\Web;

use App\Enums\AuditAction;
use App\Http\Controllers\ApiTokenController;
use App\Models\AuditEvent;
use App\Models\Contribution;
use App\Models\Line;
use App\Services\Api\ApiTokens;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\PersonalAccessToken;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ActsAsRoles;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * Côté site, ce qu'ont changé les mutations GraphQL de contribution :
 *   - capacité facultative `contribute` des jetons personnels, cochée dans le profil (décochée par défaut) ;
 *   - règles de relecture partagées avec l'API (ContributionReview) : le refus de sa propre
 *     contribution, que l'interface ne proposait déjà pas, est aussi refusé par le serveur.
 */
class ContributeTokenAndSharedRulesTest extends TestCase
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

    // ─── Jetons : capacité « contribute » ──────────────────────────────────

    public function test_a_token_is_read_only_unless_the_box_is_ticked(): void
    {
        $user = $this->member('contributor');

        $this->actingAs($user)->post('/profile/api-tokens', ['token_name' => 'Lecture'])->assertSessionHasNoErrors();
        $this->actingAs($user)->post('/profile/api-tokens', ['token_name' => 'Lecture aussi', 'contribute' => '0'])->assertSessionHasNoErrors();
        $this->actingAs($user)->post('/profile/api-tokens', ['token_name' => 'Robot', 'contribute' => '1'])->assertSessionHasNoErrors();

        $abilities = PersonalAccessToken::query()->orderBy('id')->pluck('abilities', 'name')->all();
        $this->assertSame(['Lecture' => ['read'], 'Lecture aussi' => ['read'], 'Robot' => ['read', 'contribute']], $abilities);

        $this->actingAs($user)->get('/profile')->assertInertia(fn (Assert $page) => $page
            ->where('api_access.tokens.0.name', 'Robot')
            ->where('api_access.tokens.0.contribute', true)
            ->where('api_access.tokens.1.contribute', false)
            ->where('api_access.tokens.2.contribute', false));
    }

    public function test_the_contribute_choice_must_be_a_boolean(): void
    {
        $user = $this->member('contributor');

        $this->actingAs($user)->post('/profile/api-tokens', ['token_name' => 'Robot', 'contribute' => 'tout'])
            ->assertSessionHasErrors('contribute');
        $this->assertSame(0, PersonalAccessToken::count());
    }

    public function test_only_configured_abilities_can_be_granted(): void
    {
        $this->assertSame(['read'], ApiTokens::abilities());
        $this->assertSame(['read', 'contribute'], ApiTokens::abilities(['contribute', 'admin', 'contribute']));

        config(['api.tokens.optional_abilities' => []]);
        $this->assertSame(['read'], ApiTokens::abilities(['contribute']));
    }

    public function test_the_audit_log_notes_the_contribute_ability_only_when_granted(): void
    {
        $user = $this->member('contributor');
        $this->actingAs($user)->post('/profile/api-tokens', ['token_name' => 'Lecture']);
        $this->actingAs($user)->post('/profile/api-tokens', ['token_name' => 'Robot', 'contribute' => true]);

        [$read, $contribute] = PersonalAccessToken::query()->orderBy('id')->pluck('id')->all();
        $contexts = AuditEvent::query()->where('action', AuditAction::ApiTokenCreated)->orderBy('id')->pluck('context')->all();
        $this->assertEquals([['token_id' => $read], ['token_id' => $contribute, 'contribute' => true]], $contexts);
    }

    public function test_the_new_token_secret_is_still_shown_once(): void
    {
        $user = $this->member('contributor');
        $this->actingAs($user)->post('/profile/api-tokens', ['token_name' => 'Robot', 'contribute' => true])
            ->assertSessionHas(ApiTokenController::FLASH);
    }

    // ─── Relecture sur le site : règles partagées ──────────────────────────

    private function contribution(int $userId, string $status = 'pending'): Contribution
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
                'pk_start_m' => 0, 'pk_end_m' => 5000, 'changes' => ['status' => 'abandoned'], 'sources' => ['Arrêté'], 'notes' => null,
            ],
        ]);
    }

    public function test_a_validator_cannot_reject_their_own_contribution_on_the_site(): void
    {
        $validator = $this->member('validator');
        $c = $this->contribution($validator->id);

        $this->actingAs($validator)->from('/validate')->post("/validate/{$c->id}/reject", ['notes' => 'Je retire ma proposition.'])
            ->assertRedirect('/validate')
            ->assertSessionHas('error', 'Vous ne pouvez pas refuser votre propre contribution : un autre validateur doit la relire.');

        $this->assertSame('pending', $c->fresh()->status->value);
        $this->assertNull($c->fresh()->review_notes);
        $this->assertSame(0, AuditEvent::count());
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function translatedOwnReject(): array
    {
        return [
            'anglais' => ['en', 'You cannot reject your own contribution: another validator must review it.'],
            'allemand' => ['de', 'Sie können Ihren eigenen Beitrag nicht ablehnen: Ein anderer Prüfer muss ihn sichten.'],
        ];
    }

    #[DataProvider('translatedOwnReject')]
    public function test_the_own_rejection_message_is_translated(string $locale, string $message): void
    {
        $validator = $this->member('validator', ['locale_preference' => $locale]);
        $c = $this->contribution($validator->id);

        $this->withCookie('locale', $locale)->actingAs($validator)->post("/validate/{$c->id}/reject", ['notes' => 'Je retire ma proposition.'])
            ->assertSessionHas('error', $message);
    }

    public function test_site_approval_still_publishes_after_two_reviews(): void
    {
        $c = $this->contribution($this->member('contributor')->id);

        $this->actingAs($this->member('validator'))->post("/validate/{$c->id}/approve")
            ->assertSessionHas('success', __('editor.validate.flash.partial', ['count' => 1, 'threshold' => 2]));
        $this->actingAs($this->member('admin'))->post("/validate/{$c->id}/approve")
            ->assertSessionHas('success', __('editor.validate.flash.applied', ['count' => 2, 'threshold' => 2]));

        $this->assertSame('applied', $c->fresh()->status->value);
        $this->assertSame(2, DB::table('contribution_approvals')->count());
    }
}

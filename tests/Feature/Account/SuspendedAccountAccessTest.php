<?php

namespace Tests\Feature\Account;

use App\Enums\ContributionStatus;
use App\Enums\ErrorReportStatus;
use App\Enums\ErrorReportSubject;
use App\Models\Contribution;
use App\Models\ErrorReport;
use App\Models\Line;
use App\Models\User;
use App\Services\Account\AccountDataExport;
use App\Services\ErrorReports\ErrorReportFormToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ActsAsRoles;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * Un compte suspendu ne peut ni proposer une contribution, ni relire, ni envoyer un signalement
 * (403 traduit), mais garde l'accès à ses données : profil, notifications, export, suppression.
 */
class SuspendedAccountAccessTest extends TestCase
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

    private function suspended(string $role = 'contributor', array $attributes = []): User
    {
        return $this->member($role, [
            'suspended_at' => now()->subDay(),
            'suspension_reason' => 'Contributions sans source.',
            ...$attributes,
        ]);
    }

    /** @return array<string, mixed> */
    private function segmentPayload(): array
    {
        return [
            'line_code' => '999000', 'era_year' => 2030, 'pk_start_m' => 0, 'pk_end_m' => 5000,
            'changes' => ['status' => 'abandoned'], 'sources' => ['Arrêté'],
        ];
    }

    private function pending(): Contribution
    {
        return Contribution::factory()->create(['type' => 'line_segment', 'status' => ContributionStatus::Pending->value]);
    }

    /** @return array<string, mixed> */
    private function reportPayload(): array
    {
        $this->travel(-10)->seconds();
        $token = app(ErrorReportFormToken::class)->issue();
        $this->travelBack();

        return [
            'subject_type' => 'line',
            'subject_slug' => Line::query()->value('slug'),
            'description' => 'La ligne a été électrifiée en 1967, pas en 1966.',
            'form_token' => $token,
        ];
    }

    /** @return array<string, array{0: string, 1: string, 2: string}> */
    public static function contributorRoutes(): array
    {
        return [
            'formulaire de portion' => ['GET', '/contribute/edit-segment', 'contributor'],
            'envoi d’une portion' => ['POST', '/contribute/edit-segment', 'contributor'],
            'éditeur de tracé' => ['GET', '/contribute/trace', 'contributor'],
            'envoi d’un tracé' => ['POST', '/contribute/trace', 'contributor'],
            'accrochage du tracé' => ['GET', '/contribute/trace/reference?bbox=2.1,48.78,2.35,48.9&zoom=12', 'contributor'],
        ];
    }

    #[DataProvider('contributorRoutes')]
    public function test_a_suspended_member_cannot_contribute(string $method, string $uri, string $role): void
    {
        $this->actingAs($this->suspended($role))->call($method, $uri, $method === 'POST' ? $this->segmentPayload() : [])
            ->assertForbidden()
            ->assertInertia(fn (Assert $page) => $page->component('Error')->where('status', 403)->where('detail', __('moderation.errors.suspended')));

        $this->assertSame(0, Contribution::count());
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function reviewerRoutes(): array
    {
        return [
            'liste à relire' => ['GET', '/validate'],
            'approbation' => ['POST', '/validate/{c}/approve'],
            'refus' => ['POST', '/validate/{c}/reject'],
            'retrait' => ['DELETE', '/validate/{c}'],
            'signalements' => ['GET', '/validate/reports'],
            'comptes' => ['GET', '/validate/accounts'],
        ];
    }

    #[DataProvider('reviewerRoutes')]
    public function test_a_suspended_reviewer_cannot_review_or_moderate(string $method, string $uri): void
    {
        $contribution = $this->pending();
        $uri = str_replace('{c}', (string) $contribution->id, $uri);

        $this->actingAs($this->suspended('admin'))->call($method, $uri, ['notes' => 'Source sans rapport avec la ligne', 'reason' => 'Contenu hors sujet, retiré.'])
            ->assertForbidden();

        $this->assertSame(ContributionStatus::Pending, $contribution->fresh()->status);
        $this->assertModelExists($contribution);
    }

    public function test_a_suspended_validator_cannot_handle_a_report(): void
    {
        $line = Line::query()->firstOrFail();
        $report = ErrorReport::create([
            'subject_type' => ErrorReportSubject::Line,
            'subject_id' => $line->id,
            'subject_slug' => $line->slug,
            'subject_label' => $line->name,
            'description' => 'La ligne a été électrifiée en 1967, pas en 1966.',
            'locale' => 'fr',
            'status' => ErrorReportStatus::Open,
        ]);

        $this->actingAs($this->suspended('validator'))
            ->patch("/validate/reports/{$report->id}", ['status' => 'resolved'])
            ->assertForbidden();
        $this->actingAs($this->suspended('validator'))->get("/validate/reports/{$report->id}")->assertForbidden();

        $this->assertTrue($report->fresh()->status->isOpen());
    }

    public function test_a_suspended_member_cannot_send_a_report_but_a_visitor_can(): void
    {
        $this->actingAs($this->suspended())->post('/reports', $this->reportPayload())
            ->assertForbidden()
            ->assertInertia(fn (Assert $page) => $page->component('Error')->where('status', 403)->where('detail', __('moderation.errors.suspended')));
        $this->assertSame(0, ErrorReport::count());

        auth()->logout();
        $this->post('/reports', $this->reportPayload())->assertSessionHasNoErrors();
        $this->assertSame(1, ErrorReport::count());
    }

    public function test_the_refusal_is_a_translated_inertia_error_page(): void
    {
        $user = $this->suspended('contributor', ['locale_preference' => 'en']);

        $this->withCookie('locale', 'en')->actingAs($user)->get('/contribute/edit-segment')
            ->assertForbidden()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Error')
                ->where('status', 403)
                ->where('detail', __('moderation.errors.suspended', [], 'en')));

        $this->assertStringContainsString('Your account is suspended', __('moderation.errors.suspended', [], 'en'));
        $this->assertStringContainsString('Ihr Konto ist gesperrt', __('moderation.errors.suspended', [], 'de'));
    }

    public function test_the_refusal_names_the_last_day_of_a_dated_suspension(): void
    {
        $user = $this->suspended('contributor', ['suspended_until' => now()->setDate(2026, 12, 24)->endOfDay()]);

        $this->actingAs($user)->get('/contribute/edit-segment')
            ->assertForbidden()
            ->assertInertia(fn (Assert $page) => $page->where('detail', __('moderation.errors.suspended_until', ['date' => '24 décembre 2026'])));
    }

    public function test_the_refusal_is_json_for_a_json_client(): void
    {
        $this->actingAs($this->suspended())->postJson('/contribute/edit-segment', $this->segmentPayload())
            ->assertForbidden()
            ->assertJsonPath('message', __('moderation.errors.suspended'));
    }

    public function test_an_expired_suspension_gives_the_rights_back(): void
    {
        $user = $this->suspended('contributor', ['suspended_until' => now()->subMinute()]);

        $this->actingAs($user)->get('/contribute/edit-segment')->assertOk();
        $this->actingAs($user)->post('/reports', $this->reportPayload())->assertSessionHasNoErrors();
    }

    public function test_a_dated_suspension_ends_by_itself_when_the_date_passes(): void
    {
        $user = $this->suspended('contributor', ['suspended_until' => now()->addHours(2)]);

        $this->actingAs($user)->get('/contribute/edit-segment')->assertForbidden();
        $this->travel(3)->hours();
        $this->actingAs($user->fresh())->get('/contribute/edit-segment')->assertOk();
    }

    public function test_an_unsuspended_member_and_a_visitor_are_not_affected(): void
    {
        $this->actingAs($this->member('contributor'))->get('/contribute/edit-segment')->assertOk();
        $this->actingAs($this->member('admin'))->get('/validate/accounts')->assertOk();
        auth()->logout();
        $this->get('/contribute/edit-segment')->assertRedirect('/login');
    }

    // ---- Ce qui reste ouvert : les données du compte ------------------------------------------

    public function test_a_suspended_member_keeps_access_to_their_data_and_rights(): void
    {
        $user = $this->suspended('contributor', ['password' => 'secret-password']);
        $mine = Contribution::factory()->for($user)->create(['status' => ContributionStatus::Pending->value]);

        $this->actingAs($user)->get('/profile')->assertOk();
        $this->actingAs($user)->get('/contribute/mine')->assertOk();
        $this->actingAs($user)->get('/notifications')->assertOk();
        $this->actingAs($user)->get('/profile/data')->assertOk()->assertJsonPath('contributions.0.id', $mine->id);
        $this->actingAs($user)->put('/profile', ['name' => 'Nouveau nom', 'bio' => '', 'locale_preference' => 'fr'])->assertSessionHasNoErrors();
        $this->assertSame('Nouveau nom', $user->fresh()->name);
        $this->actingAs($user)->post('/notifications/read-all')->assertSessionHas('success');
        // Pages publiques : lecture comme tout le monde.
        $this->actingAs($user)->get('/lines')->assertOk();

        $this->actingAs($user)->delete('/profile', ['password' => 'secret-password'])->assertRedirect('/');
        $this->assertModelMissing($user);
    }

    public function test_the_export_contains_the_suspension_without_the_name_of_the_admin(): void
    {
        $admin = $this->member('admin', ['name' => 'Aline Administratrice']);
        $user = $this->suspended('contributor', ['suspended_by' => $admin->id, 'suspended_until' => now()->setDate(2030, 1, 15)->endOfDay()]);

        $response = $this->actingAs($user)->get('/profile/data')->assertOk();

        $suspension = $response->json('suspension');
        $this->assertTrue($suspension['active']);
        $this->assertSame('Contributions sans source.', $suspension['reason']);
        $this->assertStringStartsWith('2030-01-15T23:59:59', $suspension['ends_at']);
        $this->assertNotNull($suspension['suspended_at']);
        $this->assertStringNotContainsString('Aline', (string) $response->getContent());
        $this->assertStringNotContainsString((string) $admin->id.',', json_encode($suspension));
    }

    public function test_the_export_marks_an_expired_suspension_inactive_and_has_null_when_never_suspended(): void
    {
        $expired = $this->suspended('contributor', ['suspended_until' => now()->subDay()]);
        $never = $this->member('contributor');

        $this->assertFalse(app(AccountDataExport::class)->for($expired)['suspension']['active']);
        $this->assertNull(app(AccountDataExport::class)->for($never)['suspension']);
    }

    public function test_the_profile_shows_the_suspension_to_its_owner_only(): void
    {
        $user = $this->suspended('contributor', ['suspended_until' => now()->addWeek()]);

        $this->actingAs($user)->get('/profile')->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Profile')
            ->where('suspension.reason', 'Contributions sans source.')
            ->whereNot('suspension.until', null)
            ->has('suspension.since'));
        $this->actingAs($this->member('contributor'))->get('/profile')->assertInertia(fn (Assert $page) => $page
            ->where('suspension', null));
    }

    public function test_the_shared_user_tells_the_pages_when_the_account_is_suspended(): void
    {
        $this->actingAs($this->suspended())->get('/profile')->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.suspended', true));
        $this->actingAs($this->suspended('contributor', ['suspended_until' => now()->subDay()]))->get('/profile')
            ->assertInertia(fn (Assert $page) => $page->where('auth.user.suspended', false));
        $this->actingAs($this->member('contributor'))->get('/profile')->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.suspended', false));
    }

    public function test_the_moderation_texts_exist_in_the_three_languages(): void
    {
        foreach (['fr', 'en', 'de'] as $locale) {
            foreach (['errors.suspended', 'errors.self', 'errors.last_admin', 'notification.suspended.title', 'admin.submit', 'report.suspended', 'profile.title'] as $key) {
                $text = __("moderation.{$key}", [], $locale);
                $this->assertNotSame("moderation.{$key}", $text, "{$locale} {$key}");
                $this->assertNotSame('', $text);
            }
        }
    }
}

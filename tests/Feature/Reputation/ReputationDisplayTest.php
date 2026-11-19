<?php

namespace Tests\Feature\Reputation;

use App\Enums\ContributionStatus;
use App\Models\Contribution;
use App\Models\User;
use App\Services\Account\AccountDataExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\Finder\Finder;
use Tests\Support\ActsAsRoles;
use Tests\TestCase;

/**
 * Où la réputation s'affiche (profil, espace de validation, page d'aide), et où elle ne figure
 * jamais : compte suspendu sans niveau, compte supprimé sans nom, export des données, API publique.
 */
class ReputationDisplayTest extends TestCase
{
    use ActsAsRoles;
    use RefreshDatabase;

    private function author(array $counters = []): User
    {
        return $this->member('contributor', [
            'reputation_applied' => 6, 'reputation_rejected' => 1, 'reputation_reviews' => 0, ...$counters,
        ]);
    }

    private function suspend(User $user): User
    {
        $user->forceFill(['suspended_at' => now()->subDay(), 'suspension_reason' => 'Contributions répétées sans source.'])->saveQuietly();

        return $user->refresh();
    }

    // ---- Page d'aide ---------------------------------------------------------------------------

    public function test_the_help_page_is_public_and_shows_the_configured_thresholds(): void
    {
        $this->get('/contribute/reputation')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Contribute/Reputation')
                ->where('levels', config('reputation.levels')));
    }

    public function test_the_help_page_follows_a_threshold_changed_in_the_configuration(): void
    {
        config(['reputation.levels.regular.applied' => 7]);

        $this->get('/contribute/reputation')
            ->assertInertia(fn (Assert $page) => $page->where('levels.regular.applied', 7));
    }

    // ---- Profil ----------------------------------------------------------------------------------

    public function test_the_profile_shows_figures_level_and_next_step(): void
    {
        $user = $this->author();

        $this->actingAs($user)->get('/profile')->assertInertia(fn (Assert $page) => $page
            ->where('reputation.applied', 6)
            ->where('reputation.rejected', 1)
            ->where('reputation.acceptance_rate', 85)
            ->where('reputation.level', 'regular')
            ->where('reputation.next.level', 'experienced'));
    }

    public function test_a_suspended_account_keeps_its_figures_but_shows_no_level(): void
    {
        $user = $this->suspend($this->author());

        $this->actingAs($user)->get('/profile')->assertInertia(fn (Assert $page) => $page
            ->where('reputation.applied', 6)
            ->where('reputation.level', null)
            ->where('reputation.next', null));
    }

    public function test_the_level_comes_back_when_the_suspension_is_over(): void
    {
        $user = $this->suspend($this->author());
        $user->forceFill(['suspended_until' => now()->subHour()])->saveQuietly();

        $this->actingAs($user)->get('/profile')->assertInertia(fn (Assert $page) => $page->where('reputation.level', 'regular'));
    }

    // ---- Espace de validation ------------------------------------------------------------------

    private function pendingBy(?User $author): void
    {
        Contribution::factory()->create([
            'user_id' => $author?->id, 'type' => 'line_segment', 'status' => ContributionStatus::Pending->value,
        ]);
    }

    public function test_validators_see_the_figures_and_level_of_the_author(): void
    {
        $this->pendingBy($this->author());

        $this->actingAs($this->member('validator'))->get('/validate')->assertInertia(fn (Assert $page) => $page
            ->where('contributions.0.user.reputation', [
                'applied' => 6, 'rejected' => 1, 'reviews' => 0, 'acceptance_rate' => 85, 'level' => 'regular',
            ]));
    }

    public function test_validators_see_no_level_for_a_suspended_author(): void
    {
        $this->pendingBy($this->suspend($this->author()));

        $this->actingAs($this->member('validator'))->get('/validate')->assertInertia(fn (Assert $page) => $page
            ->where('contributions.0.user.reputation.level', null)
            ->where('contributions.0.user.reputation.applied', 6));
    }

    public function test_a_deleted_author_leaves_no_name_and_no_reputation(): void
    {
        $this->pendingBy(null);

        $response = $this->actingAs($this->member('validator'))->get('/validate');

        $response->assertInertia(fn (Assert $page) => $page
            ->where('contributions.0.user', ['name' => __('account.anonymous_contributor'), 'reputation' => null]));
    }

    public function test_the_validation_list_never_exposes_what_is_missing_for_the_next_level(): void
    {
        $this->pendingBy($this->author());

        $this->actingAs($this->member('validator'))->get('/validate')->assertInertia(fn (Assert $page) => $page
            ->missing('contributions.0.user.reputation.next'));
    }

    // ---- Export et vie privée ------------------------------------------------------------------

    public function test_the_export_contains_the_reputation(): void
    {
        $data = app(AccountDataExport::class)->for($this->author(['reputation_reviews' => 3]));

        $this->assertSame([
            'applied_contributions' => 6, 'rejected_contributions' => 1, 'reviews_made' => 3,
            'acceptance_rate' => 85, 'level' => 'regular', 'level_hidden_by_suspension' => false,
        ], $data['reputation']);
        $this->assertArrayNotHasKey('reputation', $data['profile'], 'plus de champ unique dans le profil');
    }

    public function test_the_export_of_a_suspended_account_has_the_figures_but_no_level(): void
    {
        $data = app(AccountDataExport::class)->for($this->suspend($this->author()));

        $this->assertSame(6, $data['reputation']['applied_contributions']);
        $this->assertNull($data['reputation']['level']);
        $this->assertTrue($data['reputation']['level_hidden_by_suspension']);
    }

    public function test_the_download_route_serves_the_reputation(): void
    {
        $user = $this->author();

        $this->actingAs($user)->get('/profile/data')->assertOk()
            ->assertJsonPath('reputation.applied_contributions', 6)
            ->assertJsonPath('reputation.level', 'regular');
    }

    public function test_the_privacy_policy_describes_the_reputation_in_the_three_languages(): void
    {
        $expected = [
            'fr' => ['réputation', 'export de vos données', 'ni transmis par l’API'],
            'en' => ['reputation', 'export of your data', 'never published or provided through the API'],
            'de' => ['Reputation', 'Export Ihrer Daten', 'nie veröffentlicht oder über die API weitergegeben'],
        ];
        foreach ($expected as $locale => $fragments) {
            $text = __('privacy.contributions.paragraph', [], $locale);
            $this->assertStringContainsString('/contribute/reputation', $text, $locale);
            foreach ($fragments as $fragment) {
                $this->assertStringContainsString($fragment, $text, "{$locale} : {$fragment}");
            }
        }
    }

    public function test_reputation_is_never_part_of_the_public_api(): void
    {
        $files = (new Finder)->files()->in([
            app_path('Http/Controllers/Api'), app_path('Http/Resources'), app_path('Mcp'), base_path('graphql'),
        ])->ignoreUnreadableDirs()->name(['*.php', '*.graphql']);

        foreach ($files as $file) {
            $this->assertStringNotContainsStringIgnoringCase('reputation', $file->getContents(), $file->getRelativePathname());
        }

        $this->get('/docs/api.json')->assertOk();
        $this->assertStringNotContainsStringIgnoringCase('reputation', (string) $this->get('/docs/api.json')->getContent());
    }
}

<?php

namespace Tests\Feature\Reputation;

use App\Enums\ContributionStatus;
use App\Models\Contribution;
use App\Models\User;
use App\Services\Reputation\ReputationCalculator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Calcul de la réputation : compteurs refaits à partir des contributions et des approbations,
 * rejouables (même résultat à chaque fois), sans effet sur `updated_at`, avec --dry-run sans écriture.
 */
class ReputationCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private function contribution(?User $author, ContributionStatus $status, ?User $reviewer = null): Contribution
    {
        return Contribution::factory()->create([
            'user_id' => $author?->id,
            'type' => 'line_segment',
            'status' => $status->value,
            'reviewed_by' => $reviewer?->id,
            'reviewed_at' => $reviewer === null ? null : now(),
        ]);
    }

    private function approve(Contribution $contribution, User $validator): void
    {
        DB::table('contribution_approvals')->insert([
            'contribution_id' => $contribution->id, 'user_id' => $validator->id, 'approved_at' => now(),
        ]);
    }

    /** @return array{int, int, int} */
    private function counters(User $user): array
    {
        $user->refresh();

        return [$user->reputation_applied, $user->reputation_rejected, $user->reputation_reviews];
    }

    public function test_a_new_account_starts_at_zero(): void
    {
        $this->assertSame([0, 0, 0], $this->counters(User::factory()->create()));
    }

    public function test_counters_count_applied_rejected_and_reviews(): void
    {
        $author = User::factory()->contributor()->create();
        $validator = User::factory()->validator()->create();
        $other = User::factory()->validator()->create();

        $applied = $this->contribution($author, ContributionStatus::Applied, $other);
        $this->approve($applied, $validator);
        $this->approve($applied, $other);
        $this->contribution($author, ContributionStatus::Applied, $validator);
        $this->contribution($author, ContributionStatus::Rejected, $validator);
        $this->contribution($author, ContributionStatus::Pending);
        $partial = $this->contribution($author, ContributionStatus::Approved);
        $this->approve($partial, $validator);

        (new ReputationCalculator)->recalculate([$author->id, $validator->id, $other->id]);

        $this->assertSame([2, 1, 0], $this->counters($author), 'en attente et approuvée une fois ne comptent pas');
        // $validator : 2 approbations données + 1 refus décidé ; $other : 1 approbation.
        $this->assertSame([0, 0, 3], $this->counters($validator));
        $this->assertSame([0, 0, 1], $this->counters($other));
    }

    public function test_the_migration_fills_the_counters_of_existing_accounts(): void
    {
        $author = User::factory()->contributor()->create();
        $validator = User::factory()->validator()->create();
        $this->contribution($author, ContributionStatus::Applied, $validator);
        $this->contribution($author, ContributionStatus::Rejected, $validator);
        // État d'avant la migration : l'ancien champ, sans les compteurs (annulé avec la transaction du test).
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['reputation_applied', 'reputation_rejected', 'reputation_reviews']);
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->integer('reputation')->default(0);
        });

        (require database_path('migrations/2026_11_24_010000_replace_reputation_score_with_counters.php'))->up();

        $this->assertSame([1, 1, 0], $this->counters($author), 'aucun membre ne reste à zéro après le déploiement');
        $this->assertSame([0, 0, 1], $this->counters($validator));
    }

    public function test_reviewing_your_own_contribution_never_counts(): void
    {
        $validator = User::factory()->validator()->create();
        $mine = $this->contribution($validator, ContributionStatus::Rejected, $validator);
        $this->approve($mine, $validator);

        (new ReputationCalculator)->recalculate([$validator->id]);

        $this->assertSame([0, 1, 0], $this->counters($validator));
    }

    public function test_approvals_of_a_contribution_whose_author_deleted_the_account_still_count(): void
    {
        $validator = User::factory()->validator()->create();
        $this->approve($this->contribution(null, ContributionStatus::Applied, $validator), $validator);
        $this->contribution(null, ContributionStatus::Rejected, $validator);

        (new ReputationCalculator)->recalculate([$validator->id]);

        $this->assertSame([0, 0, 2], $this->counters($validator));
    }

    public function test_recalculation_is_idempotent_and_repairs_any_drift(): void
    {
        $author = User::factory()->contributor()->create();
        $this->contribution($author, ContributionStatus::Applied, User::factory()->validator()->create());
        $calculator = new ReputationCalculator;

        // Compteur faussé à la main : le recalcul le corrige, sans partir de la valeur stockée.
        $author->forceFill(['reputation_applied' => 99, 'reputation_rejected' => 7])->saveQuietly();
        $this->assertSame(1, $calculator->recalculate([$author->id]));
        $this->assertSame([1, 0, 0], $this->counters($author));

        // Rejoué : rien ne change, rien n'est écrit.
        $this->assertSame(0, $calculator->recalculate([$author->id]));
        $this->assertSame(0, $calculator->recalculate([$author->id, $author->id]));
        $this->assertSame([1, 0, 0], $this->counters($author));
    }

    public function test_recalculation_does_not_touch_updated_at_nor_other_columns(): void
    {
        $author = User::factory()->contributor()->create(['name' => 'Jeanne', 'updated_at' => now()->subYear()]);
        $before = $author->fresh()->updated_at;
        $this->contribution($author, ContributionStatus::Applied, User::factory()->validator()->create());

        (new ReputationCalculator)->recalculate([$author->id]);

        $fresh = $author->fresh();
        $this->assertEquals($before, $fresh->updated_at);
        $this->assertSame('Jeanne', $fresh->name);
    }

    public function test_unknown_and_null_ids_are_ignored(): void
    {
        $this->assertSame(0, (new ReputationCalculator)->recalculate([null, 424242]));
        $this->assertSame(0, (new ReputationCalculator)->recalculate([]));
    }

    public function test_recalculate_all_rewrites_every_account_and_reports_the_changes(): void
    {
        $validator = User::factory()->validator()->create();
        $a = User::factory()->contributor()->create();
        $b = User::factory()->contributor()->create();
        User::factory()->create();
        $this->contribution($a, ContributionStatus::Applied, $validator);
        $this->contribution($b, ContributionStatus::Rejected, $validator);
        $calculator = new ReputationCalculator;

        $this->assertSame(['checked' => 4, 'changed' => 3], $calculator->recalculateAll());
        $this->assertSame([1, 0, 0], $this->counters($a));
        $this->assertSame([0, 1, 0], $this->counters($b));
        $this->assertSame([0, 0, 1], $this->counters($validator), 'un refus décidé, pas la publication (déjà comptée par ses approbations)');

        $this->assertSame(['checked' => 4, 'changed' => 0], $calculator->recalculateAll(), 'rejoué : plus rien à changer');
    }

    public function test_dry_run_counts_the_accounts_that_would_change_without_writing(): void
    {
        $a = User::factory()->contributor()->create();
        $this->contribution($a, ContributionStatus::Applied, User::factory()->validator()->create());

        $this->assertSame(['checked' => 2, 'changed' => 1], (new ReputationCalculator)->recalculateAll(dryRun: true));
        $this->assertSame([0, 0, 0], $this->counters($a));
    }

    public function test_the_command_recalculates_and_supports_dry_run(): void
    {
        $a = User::factory()->contributor()->create();
        $this->contribution($a, ContributionStatus::Applied);

        $this->artisan('vff:recalculate-reputation', ['--dry-run' => true])
            ->expectsOutputToContain('1 à mettre à jour')
            ->assertSuccessful();
        $this->assertSame([0, 0, 0], $this->counters($a));

        $this->artisan('vff:recalculate-reputation')->expectsOutputToContain('1 mis à jour')->assertSuccessful();
        $this->assertSame([1, 0, 0], $this->counters($a));

        $this->artisan('vff:recalculate-reputation')->expectsOutputToContain('0 mis à jour')->assertSuccessful();
    }
}

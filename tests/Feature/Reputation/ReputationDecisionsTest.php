<?php

namespace Tests\Feature\Reputation;

use App\Enums\ContributionStatus;
use App\Models\Contribution;
use App\Models\Line;
use App\Models\User;
use App\Services\Reputation\ReputationCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\ActsAsRoles;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * La réputation se recalcule à chaque décision de validation : approbation partielle, publication,
 * refus, retrait par un administrateur et suppression d'un compte.
 */
class ReputationDecisionsTest extends TestCase
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

    private function pending(User $author, string $status = 'pending'): Contribution
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
                'pk_start_m' => 0, 'pk_end_m' => 5000, 'changes' => ['status' => 'abandoned'], 'sources' => [], 'notes' => null,
            ],
        ]);
    }

    /** @return array{int, int, int} */
    private function counters(User $user): array
    {
        $user->refresh();

        return [$user->reputation_applied, $user->reputation_rejected, $user->reputation_reviews];
    }

    public function test_a_partial_approval_counts_a_review_for_the_validator_only(): void
    {
        $author = $this->member('contributor');
        $validator = $this->member('validator');
        $contribution = $this->pending($author);

        $this->actingAs($validator)->post("/validate/{$contribution->id}/approve")->assertSessionHas('success');

        $this->assertSame([0, 0, 1], $this->counters($validator));
        $this->assertSame([0, 0, 0], $this->counters($author), 'pas encore publiée');
    }

    public function test_the_second_approval_publishes_and_counts_for_the_author_and_both_validators(): void
    {
        $author = $this->member('contributor');
        [$first, $second] = [$this->member('validator'), $this->member('admin')];
        $contribution = $this->pending($author);

        $this->actingAs($first)->post("/validate/{$contribution->id}/approve");
        $this->actingAs($second)->post("/validate/{$contribution->id}/approve")->assertSessionHas('success');

        $this->assertSame('applied', $contribution->fresh()->status->value);
        $this->assertSame([1, 0, 0], $this->counters($author));
        $this->assertSame([0, 0, 1], $this->counters($first));
        $this->assertSame([0, 0, 1], $this->counters($second));
    }

    public function test_a_double_approval_attempt_does_not_count_twice(): void
    {
        $validator = $this->member('validator');
        $contribution = $this->pending($this->member('contributor'));

        $this->actingAs($validator)->post("/validate/{$contribution->id}/approve");
        $this->actingAs($validator)->post("/validate/{$contribution->id}/approve")->assertSessionHas('error');

        $this->assertSame([0, 0, 1], $this->counters($validator));
    }

    public function test_approving_your_own_contribution_is_refused_and_counts_nothing(): void
    {
        $validator = $this->member('validator');
        $contribution = $this->pending($validator);

        $this->actingAs($validator)->post("/validate/{$contribution->id}/approve")->assertSessionHas('error');

        $this->assertSame([0, 0, 0], $this->counters($validator));
    }

    public function test_a_failed_application_is_rolled_back_with_no_change_to_the_counters(): void
    {
        $author = $this->member('contributor');
        [$first, $second] = [$this->member('validator'), $this->member('admin')];
        // Ère sans tracé pour la ligne : l'application échoue à la seconde approbation.
        $contribution = $this->pending($author);
        $contribution->update(['payload' => [...$contribution->payload, 'era_year' => 1960, 'pk_start_m' => 0, 'pk_end_m' => 5000]]);
        DB::table('line_segments')->where('era_id', $this->pastEra->id)->delete();

        $this->actingAs($first)->post("/validate/{$contribution->id}/approve");
        $this->actingAs($second)->post("/validate/{$contribution->id}/approve")->assertSessionHas('error');

        $this->assertSame([0, 0, 1], $this->counters($first));
        $this->assertSame([0, 0, 0], $this->counters($second), 'approbation annulée avec la transaction');
        $this->assertSame([0, 0, 0], $this->counters($author));
    }

    public function test_a_rejection_counts_for_the_author_and_the_validator(): void
    {
        $author = $this->member('contributor');
        $validator = $this->member('validator');
        $contribution = $this->pending($author);

        $this->actingAs($validator)
            ->post("/validate/{$contribution->id}/reject", ['notes' => 'Source sans rapport avec la ligne.'])
            ->assertSessionHas('success');

        $this->assertSame([0, 1, 0], $this->counters($author));
        $this->assertSame([0, 0, 1], $this->counters($validator));

        // Une seconde tentative sur une contribution close ne compte pas une deuxième fois.
        $this->actingAs($validator)
            ->post("/validate/{$contribution->id}/reject", ['notes' => 'Source sans rapport avec la ligne.'])
            ->assertSessionHas('error');
        $this->assertSame([0, 1, 0], $this->counters($author));
        $this->assertSame([0, 0, 1], $this->counters($validator));
    }

    public function test_removal_by_an_administrator_leaves_neither_a_refusal_nor_the_approvals(): void
    {
        $author = $this->member('contributor');
        $validator = $this->member('validator');
        $admin = $this->member('admin');
        $contribution = $this->pending($author);

        $this->actingAs($validator)->post("/validate/{$contribution->id}/approve");
        $this->assertSame([0, 0, 1], $this->counters($validator));

        $this->actingAs($admin)->delete("/validate/{$contribution->id}", ['reason' => 'Contient des données personnelles.'])
            ->assertSessionHas('success');

        $this->assertSame([0, 0, 0], $this->counters($validator), 'ses approbations partent avec la contribution');
        $this->assertSame([0, 0, 0], $this->counters($author));
    }

    public function test_removal_of_a_rejected_contribution_takes_the_refusal_off_the_counters(): void
    {
        $author = $this->member('contributor');
        $validator = $this->member('validator');
        $contribution = $this->pending($author);

        $this->actingAs($validator)->post("/validate/{$contribution->id}/reject", ['notes' => 'Doublon d’une autre contribution.']);
        $this->assertSame([0, 1, 0], $this->counters($author));

        $this->actingAs($this->member('admin'))->delete("/validate/{$contribution->id}", ['reason' => 'Contribution en double, retirée.']);

        $this->assertSame([0, 0, 0], $this->counters($author));
        $this->assertSame([0, 0, 0], $this->counters($validator));
    }

    public function test_deleting_an_author_account_recalculates_the_reviewers_whose_approvals_disappear(): void
    {
        $author = $this->member('contributor', ['password' => 'secret-password']);
        $validator = $this->member('validator');
        $contribution = $this->pending($author);
        $published = $this->pending($author);

        $this->actingAs($validator)->post("/validate/{$contribution->id}/approve");
        DB::table('contribution_approvals')->insert(['contribution_id' => $published->id, 'user_id' => $validator->id]);
        $published->update(['status' => ContributionStatus::Applied->value]);
        (new ReputationCalculator)->recalculate([$validator->id]);
        $this->assertSame([0, 0, 2], $this->counters($validator));

        $this->actingAs($author)->delete('/profile', ['password' => 'secret-password'])->assertRedirect(route('home'));

        $this->assertModelMissing($author);
        // L'approbation de la contribution en attente disparaît avec elle ; celle de la publiée reste, sans auteur.
        $this->assertSame([0, 0, 1], $this->counters($validator));
    }

    public function test_deleting_an_author_account_recalculates_the_validators_who_rejected_their_contributions(): void
    {
        $author = $this->member('contributor', ['password' => 'secret-password']);
        $validator = $this->member('validator');
        foreach ([$this->pending($author), $this->pending($author)] as $contribution) {
            $this->actingAs($validator)->post("/validate/{$contribution->id}/reject", ['notes' => 'Source sans rapport avec la ligne.']);
        }
        $this->assertSame([0, 0, 2], $this->counters($validator));

        $this->actingAs($author)->delete('/profile', ['password' => 'secret-password'])->assertRedirect(route('home'));

        $this->assertSame([0, 0, 0], $this->counters($validator), 'les refus partent avec les contributions supprimées');
    }

    public function test_deleting_a_reviewer_account_changes_nobody_else(): void
    {
        $author = $this->member('contributor');
        $validator = $this->member('validator', ['password' => 'secret-password']);
        $contribution = $this->pending($author);
        $this->actingAs($validator)->post("/validate/{$contribution->id}/reject", ['notes' => 'Source sans rapport avec la ligne.']);
        $this->assertSame([0, 1, 0], $this->counters($author));

        $this->actingAs($validator)->delete('/profile', ['password' => 'secret-password'])->assertRedirect(route('home'));

        $this->assertSame([0, 1, 0], $this->counters($author));
    }
}

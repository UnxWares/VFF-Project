<?php

namespace Tests\Feature\Account;

use App\Enums\AuditAction;
use App\Enums\ContributionStatus;
use App\Models\AuditEvent;
use App\Models\Contribution;
use App\Models\User;
use App\Notifications\ContributionRemoved;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Journal d'audit des décisions de modération (approbation, refus, retrait) et retrait d'une
 * contribution par un administrateur avec information de l'auteur (CGU).
 */
class ModerationAuditTest extends TestCase
{
    use RefreshDatabase;

    private function pending(?User $author = null): Contribution
    {
        return Contribution::factory()->for($author ?? User::factory()->contributor()->create())->create([
            'type' => 'line_segment',
            'status' => ContributionStatus::Pending->value,
            'payload' => ['line_code' => '590000', 'notes' => 'Texte libre de l’auteur'],
        ]);
    }

    public function test_a_partial_approval_is_logged(): void
    {
        $validator = User::factory()->validator()->create();
        $contribution = $this->pending();

        $this->actingAs($validator)->post("/validate/{$contribution->id}/approve")->assertSessionHas('success');

        $event = AuditEvent::sole();
        $this->assertSame(AuditAction::ContributionApproved, $event->action);
        $this->assertSame($validator->id, $event->actor_id);
        $this->assertSame($contribution->id, $event->contribution_id);
        $this->assertEquals(['type' => 'line_segment', 'approvals' => 1, 'threshold' => 2], $event->context, 'Contexte du journal (ordre des clés jsonb indifférent)');
    }

    public function test_a_failed_approval_is_not_logged(): void
    {
        $validator = User::factory()->validator()->create();
        $contribution = $this->pending($validator);

        $this->actingAs($validator)->post("/validate/{$contribution->id}/approve")->assertSessionHas('error');

        $this->assertSame(0, AuditEvent::count());
    }

    public function test_a_rejection_is_logged_without_its_free_text(): void
    {
        $validator = User::factory()->validator()->create();
        $contribution = $this->pending();

        $this->actingAs($validator)
            ->post("/validate/{$contribution->id}/reject", ['notes' => 'Source sans rapport avec la ligne'])
            ->assertSessionHas('success');

        $event = AuditEvent::sole();
        $this->assertSame(AuditAction::ContributionRejected, $event->action);
        $this->assertSame($validator->id, $event->actor_id);
        $this->assertEquals(['type' => 'line_segment', 'previous_status' => 'pending', 'with_reason' => true], $event->context, 'Contexte du journal (ordre des clés jsonb indifférent)');
        $this->assertStringNotContainsString('Source sans rapport', json_encode($event->getAttributes()));
        $this->assertSame('Source sans rapport avec la ligne', $contribution->fresh()->review_notes);
    }

    public function test_only_administrators_can_remove_a_contribution(): void
    {
        $contribution = $this->pending();

        $this->delete("/validate/{$contribution->id}", ['reason' => 'Publicité sans rapport'])->assertRedirect('/login');
        $this->actingAs(User::factory()->validator()->create())
            ->delete("/validate/{$contribution->id}", ['reason' => 'Publicité sans rapport'])
            ->assertForbidden();

        $this->assertModelExists($contribution);
    }

    public function test_removal_requires_a_reason(): void
    {
        $contribution = $this->pending();

        $this->actingAs(User::factory()->admin()->create())
            ->delete("/validate/{$contribution->id}", ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->assertModelExists($contribution);
    }

    public function test_removal_deletes_the_contribution_informs_the_author_and_is_logged(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $author = User::factory()->contributor()->create();
        $contribution = $this->pending($author);
        $id = $contribution->id;

        $this->actingAs($admin)
            ->delete("/validate/{$id}", ['reason' => 'Contient l’adresse personnelle d’un riverain.'])
            ->assertSessionHas('success', __('account.flash.contribution_removed'));

        $this->assertModelMissing($contribution);
        Notification::assertSentTo($author, ContributionRemoved::class,
            fn (ContributionRemoved $n) => $n->contributionId === $id && $n->reason === 'Contient l’adresse personnelle d’un riverain.');

        $event = AuditEvent::sole();
        $this->assertSame(AuditAction::ContributionRemoved, $event->action);
        $this->assertSame($admin->id, $event->actor_id);
        $this->assertNull($event->contribution_id);
        $this->assertEquals(['removed_contribution_id' => $id, 'type' => 'line_segment', 'status' => 'pending'], $event->context, 'Contexte du journal (ordre des clés jsonb indifférent)');
    }

    public function test_a_published_contribution_cannot_be_removed(): void
    {
        Notification::fake();
        $contribution = $this->pending();
        $contribution->update(['status' => ContributionStatus::Applied->value]);

        $this->actingAs(User::factory()->admin()->create())
            ->delete("/validate/{$contribution->id}", ['reason' => 'Publicité sans rapport'])
            ->assertSessionHas('error', __('account.errors.removal_published'));

        $this->assertModelExists($contribution);
        Notification::assertNothingSent();
        $this->assertSame(0, AuditEvent::count());
    }

    public function test_removal_email_gives_the_reason_and_the_appeal_route(): void
    {
        $author = User::factory()->make(['name' => 'Jeanne']);
        $this->app->setLocale('fr');

        $mail = (new ContributionRemoved(42, now()->setDate(2026, 11, 2), 'Doublon de la contribution 41.'))->toMail($author);

        $this->assertSame('Votre contribution n° 42 a été retirée', $mail->subject);
        $this->assertSame('Bonjour Jeanne,', $mail->greeting);
        $this->assertContains('Motif : Doublon de la contribution 41.', $mail->introLines);
        $this->assertStringContainsString('2 novembre 2026', implode(' ', $mail->introLines));
        $this->assertStringContainsString('contact@vff-project.org', implode(' ', $mail->introLines));
    }

    public function test_entries_naming_a_person_are_pruned_after_twelve_months(): void
    {
        $validator = User::factory()->validator()->create();
        $old = AuditEvent::create(['action' => AuditAction::ContributionRejected, 'actor_id' => $validator->id, 'context' => []]);
        $old->forceFill(['created_at' => now()->subMonths(13)])->save();
        $recent = AuditEvent::create(['action' => AuditAction::ContributionRejected, 'actor_id' => $validator->id, 'context' => []]);
        $anonymous = AuditEvent::create(['action' => AuditAction::AccountDeleted, 'context' => ['reason' => 'owner']]);
        $anonymous->forceFill(['created_at' => now()->subYears(3)])->save();

        $this->artisan('model:prune', ['--model' => [AuditEvent::class]])->assertSuccessful();

        $this->assertModelMissing($old);
        $this->assertModelExists($recent);
        $this->assertModelExists($anonymous);
    }
}

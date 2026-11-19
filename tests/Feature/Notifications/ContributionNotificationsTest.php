<?php

namespace Tests\Feature\Notifications;

use App\Enums\NotificationKind;
use App\Models\Contribution;
use App\Models\DatabaseNotification;
use App\Models\Era;
use App\Models\Line;
use App\Models\User;
use App\Notifications\ContributionApplied;
use App\Notifications\ContributionApproved;
use App\Notifications\ContributionAwaitingReview;
use App\Notifications\ContributionRejected;
use App\Notifications\ContributionRemoved;
use App\Services\Notifications\ContributionNotifier;
use App\Services\TraceContributionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\Support\ActsAsRoles;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * Notifications envoyées à chaque étape d'une contribution (site et courriel) : validateurs à
 * l'arrivée, auteur à la première approbation, à la publication, au refus et au retrait.
 */
class ContributionNotificationsTest extends TestCase
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

    private function contribution(User $author, string $status = 'pending', array $payload = []): Contribution
    {
        return Contribution::factory()->create([
            'user_id' => $author->id,
            'type' => 'line_segment',
            'action' => 'update',
            'target_type' => Line::class,
            'target_id' => $this->line->id,
            'status' => $status,
            'payload' => [
                'line_code' => '999 000', 'line_name' => 'Paris - Versailles', 'era_year' => 2030,
                'pk_start_m' => 0, 'pk_end_m' => 5000, 'changes' => ['status' => 'abandoned'], 'sources' => [], 'notes' => null,
                ...$payload,
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function soleData(User $user): array
    {
        return $user->notifications()->sole()->data;
    }

    public function test_a_submission_notifies_every_reviewer_but_its_author(): void
    {
        $author = $this->member('validator', ['name' => 'Jeanne Martin']);
        $other = $this->member('validator');
        $admin = $this->member('admin');
        $contributor = $this->member('contributor');

        $this->actingAs($author)->post('/contribute/edit-segment', [
            'line_code' => '999000', 'era_year' => 2030, 'pk_start_m' => 0, 'pk_end_m' => 5000,
            'changes' => ['status' => 'abandoned'], 'sources' => ['Arrêté'],
        ])->assertRedirect();

        $id = Contribution::sole()->id;
        $this->assertSame(0, $author->notifications()->count(), 'l’auteur ne relit pas sa propre contribution');
        $this->assertSame(0, $contributor->notifications()->count());
        foreach ([$other, $admin] as $reviewer) {
            $data = $this->soleData($reviewer);
            $this->assertSame(NotificationKind::AwaitingReview->value, $data['kind']);
            $this->assertSame($id, $data['contribution_id']);
            $this->assertSame("La contribution n° {$id} attend votre relecture", $data['title']);
            $this->assertSame("/validate#contribution-{$id}", $data['url']);
            $this->assertStringContainsString('999 000', $data['body']);
            $this->assertStringNotContainsString('Jeanne', json_encode($data, JSON_UNESCAPED_UNICODE), 'aucun nom de membre');
        }
    }

    public function test_a_trace_submission_names_the_proposed_line(): void
    {
        Notification::fake();
        $reviewer = $this->member('validator');
        $trace = Contribution::factory()->create([
            'user_id' => $this->member('contributor')->id,
            'type' => TraceContributionService::TYPE,
            'payload' => ['kind' => 'new_line', 'line_name' => 'Ligne de Saint-Yrieix', 'line_code' => null],
        ]);

        app(ContributionNotifier::class)->submitted($trace);

        Notification::assertSentTo($reviewer, ContributionAwaitingReview::class,
            fn (ContributionAwaitingReview $n) => $n->contributionId === $trace->id && $n->subject === 'Ligne de Saint-Yrieix');
    }

    public function test_first_approval_notifies_the_author_in_their_language(): void
    {
        $author = $this->member('contributor', ['locale_preference' => 'en']);
        $c = $this->contribution($author);

        $this->actingAs($this->member('validator', ['name' => 'Paul Durand']))->post("/validate/{$c->id}/approve")->assertSessionHas('success');

        $data = $this->soleData($author);
        $this->assertSame(NotificationKind::Approved->value, $data['kind']);
        $this->assertSame("Your contribution no. {$c->id} has received its first approval", $data['title']);
        $this->assertSame('Line: Paris - Versailles (999 000). It will be published once a second reviewer has approved it.', $data['body']);
        $this->assertSame("/contribute/mine#contribution-{$c->id}", $data['url']);
        $this->assertStringNotContainsString('Paul', json_encode($data));
    }

    public function test_second_approval_notifies_the_author_of_the_publication(): void
    {
        Notification::fake();
        $author = $this->member('contributor');
        $c = $this->contribution($author);

        $this->actingAs($this->member('validator'))->post("/validate/{$c->id}/approve");
        $this->actingAs($this->member('admin'))->post("/validate/{$c->id}/approve")->assertSessionHas('success');

        $this->assertSame('applied', $c->fresh()->status->value);
        Notification::assertSentToTimes($author, ContributionApproved::class, 1);
        Notification::assertSentTo($author, ContributionApplied::class, fn (ContributionApplied $n, array $channels) => $n->contributionId === $c->id && $channels === ['database', 'mail']);
    }

    public function test_the_applied_text_is_stored_in_german_for_a_german_profile(): void
    {
        $author = $this->member('contributor', ['locale_preference' => 'de']);
        $c = $this->contribution($author, 'approved');
        DB::table('contribution_approvals')->insert(['contribution_id' => $c->id, 'user_id' => $this->member('validator')->id, 'approved_at' => now()]);

        $this->actingAs($this->member('admin'))->post("/validate/{$c->id}/approve")->assertSessionHas('success');

        $this->assertSame("Ihr Beitrag Nr. {$c->id} ist veröffentlicht", $this->soleData($author)['title']);
    }

    public function test_a_failed_application_notifies_nobody(): void
    {
        Notification::fake();
        Era::factory()->create(['year' => 1900]);
        $author = $this->member('contributor');
        $c = $this->contribution($author, 'approved', ['era_year' => 1900]);
        DB::table('contribution_approvals')->insert(['contribution_id' => $c->id, 'user_id' => $this->member('validator')->id, 'approved_at' => now()]);

        $this->actingAs($this->member('validator'))->post("/validate/{$c->id}/approve")->assertSessionHas('error');

        Notification::assertNothingSent();
    }

    public function test_a_rejection_gives_the_reason(): void
    {
        $author = $this->member('contributor');
        $c = $this->contribution($author);

        $this->actingAs($this->member('validator'))->post("/validate/{$c->id}/reject", ['notes' => 'Source introuvable.'])->assertSessionHas('success');

        $data = $this->soleData($author);
        $this->assertSame(NotificationKind::Rejected->value, $data['kind']);
        $this->assertSame("Votre contribution n° {$c->id} n’a pas été retenue", $data['title']);
        $this->assertSame('Source introuvable.', $data['reason']);
    }

    public function test_a_rejection_without_reason_says_so(): void
    {
        $author = $this->member('contributor');
        $c = $this->contribution($author);

        $this->actingAs($this->member('validator'))->post("/validate/{$c->id}/reject", ['notes' => '  '])->assertSessionHas('success');

        $data = $this->soleData($author);
        $this->assertNull($data['reason']);
        $this->assertStringContainsString('sans donner de motif', $data['body']);
    }

    public function test_a_rejected_mail_carries_the_reason_and_the_link(): void
    {
        $author = User::factory()->make(['name' => 'Jeanne']);
        $this->app->setLocale('fr');

        $mail = (new ContributionRejected(7, 'Paris - Versailles (999 000)', 'Doublon.'))->toMail($author);

        $this->assertSame('Votre contribution n° 7 n’a pas été retenue', $mail->subject);
        $this->assertContains('Motif : Doublon.', $mail->introLines);
        $this->assertStringEndsWith('/contribute/mine#contribution-7', $mail->actionUrl);
    }

    public function test_a_removal_is_stored_on_the_site_with_its_reason(): void
    {
        $author = $this->member('contributor');
        $c = $this->contribution($author);

        $this->actingAs($this->member('admin'))->delete("/validate/{$c->id}", ['reason' => 'Contient des données personnelles.'])->assertSessionHas('success');

        $data = $this->soleData($author);
        $this->assertSame(NotificationKind::Removed->value, $data['kind']);
        $this->assertSame("Votre contribution n° {$c->id} a été retirée", $data['title']);
        $this->assertSame('Contient des données personnelles.', $data['reason']);
        $this->assertSame('/use-conditions', $data['url']);
        $this->assertStringContainsString('contact@vff-project.org', $data['body']);
    }

    public function test_a_mail_failure_keeps_the_site_notification_and_the_decision(): void
    {
        config(['mail.default' => 'messagerie-absente']);
        $author = $this->member('contributor');
        $c = $this->contribution($author);

        $this->actingAs($this->member('validator'))->post("/validate/{$c->id}/reject", ['notes' => 'Hors sujet.'])->assertSessionHas('success');

        $this->assertSame('rejected', $c->fresh()->status->value);
        $this->assertSame(1, $author->notifications()->count());
    }

    public function test_notifications_use_the_application_model(): void
    {
        $author = $this->member('contributor');
        $author->notify(new ContributionRemoved(3, now(), 'Doublon.'));

        $this->assertInstanceOf(DatabaseNotification::class, $author->notifications()->sole());
    }
}

<?php

namespace Tests\Feature\Account;

use App\Enums\AuditAction;
use App\Enums\LegalDocument;
use App\Models\AuditEvent;
use App\Models\User;
use App\Notifications\LegalDocumentChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Information des membres avant un changement des CGU (15 jours) ou de la politique de confidentialité. */
class NotifyLegalChangeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 19)->setTime(10, 0));
        Notification::fake();
    }

    public function test_simulation_by_default(): void
    {
        User::factory()->count(3)->create();

        $this->artisan('vff:notify-legal-change', ['document' => 'use-conditions', '--effective' => '2026-12-04'])
            ->expectsOutputToContain('Simulation : 3 membre(s)')
            ->assertSuccessful();

        Notification::assertNothingSent();
        $this->assertSame(0, AuditEvent::count());
    }

    public function test_terms_of_use_need_fifteen_days_notice(): void
    {
        User::factory()->create();

        $this->artisan('vff:notify-legal-change', ['document' => 'use-conditions', '--effective' => '2026-12-03', '--send' => true])
            ->expectsOutputToContain('2026-12-04 au plus tôt')
            ->assertExitCode(2);

        Notification::assertNothingSent();
    }

    public function test_invalid_arguments_are_refused(): void
    {
        $this->artisan('vff:notify-legal-change', ['document' => 'cgv', '--effective' => '2027-01-01'])->assertExitCode(2);
        $this->artisan('vff:notify-legal-change', ['document' => 'privacy', '--effective' => '2027-02-30'])->assertExitCode(2);
        $this->artisan('vff:notify-legal-change', ['document' => 'privacy'])->assertExitCode(2);
    }

    public function test_sending_notifies_every_member_and_is_logged(): void
    {
        $members = User::factory()->count(2)->create();

        $this->artisan('vff:notify-legal-change', ['document' => 'use-conditions', '--effective' => '2026-12-04', '--send' => true])
            ->expectsOutputToContain('2 membre(s) prévenu(s)')
            ->assertSuccessful();

        Notification::assertSentTo($members, LegalDocumentChanged::class,
            fn (LegalDocumentChanged $n) => $n->document === LegalDocument::UseConditions && $n->effectiveDate->format('Y-m-d') === '2026-12-04');

        $event = AuditEvent::sole();
        $this->assertSame(AuditAction::MembersNotified, $event->action);
        $this->assertEquals(['document' => 'use-conditions', 'effective_date' => '2026-12-04', 'sent' => 2, 'failed' => 0], $event->context, 'Contexte du journal (ordre des clés jsonb indifférent)');
    }

    public function test_privacy_policy_change_can_take_effect_today(): void
    {
        User::factory()->create();

        $this->artisan('vff:notify-legal-change', ['document' => 'privacy', '--effective' => '2026-11-19', '--send' => true])
            ->assertSuccessful();

        Notification::assertCount(1);
    }

    public function test_email_content_per_document(): void
    {
        $user = User::factory()->make(['name' => 'Ada']);
        $this->app->setLocale('en');

        $terms = (new LegalDocumentChanged(LegalDocument::UseConditions, now()->setDate(2026, 12, 4)))->toMail($user);
        $this->assertSame('Changes to the French Railway Lines terms of use', $terms->subject);
        $this->assertSame(route('use-conditions'), $terms->actionUrl);
        $this->assertStringContainsString('4 December 2026', implode(' ', $terms->introLines));
        $this->assertStringContainsString('means you accept it', implode(' ', $terms->outroLines));

        $privacy = (new LegalDocumentChanged(LegalDocument::Privacy, now()))->toMail($user);
        $this->assertSame(route('privacy'), $privacy->actionUrl);
        $this->assertStringNotContainsString('means you accept it', implode(' ', $privacy->outroLines));
    }
}

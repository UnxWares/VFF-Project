<?php

namespace Tests\Feature\Account;

use App\Enums\LegalDocument;
use App\Enums\NotificationKind;
use App\Models\User;
use App\Notifications\LegalDocumentChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Changement des textes légaux : notification sur le site en plus du courriel (sans doublon quand
 * la commande est relancée après des courriels en échec), et version en vigueur de chaque texte
 * lue dans config/legal.php.
 */
class LegalChangeOnSiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 19)->setTime(10, 0));
    }

    public function test_members_are_told_on_the_site_in_their_language(): void
    {
        $fr = User::factory()->create(['locale_preference' => 'fr']);
        $de = User::factory()->create(['locale_preference' => 'de']);

        $this->artisan('vff:notify-legal-change', ['document' => 'use-conditions', '--effective' => '2026-12-04', '--send' => true])
            ->expectsOutputToContain('2 membre(s) prévenu(s)')
            ->assertSuccessful();

        $data = $fr->notifications()->sole()->data;
        $this->assertSame(NotificationKind::LegalDocumentChanged->value, $data['kind']);
        $this->assertSame('use-conditions', $data['document']);
        $this->assertSame('2026-12-04', $data['effective_date']);
        $this->assertSame('/use-conditions', $data['url']);
        $this->assertStringContainsString('4 décembre 2026', $data['body']);
        $this->assertStringContainsString('4. Dezember 2026', $de->notifications()->sole()->data['body']);

        // Visible sur la page des notifications, et le lien mène au texte.
        $this->actingAs($fr)->get('/notifications')->assertInertia(fn (Assert $page) => $page
            ->where('notifications.data.0.kind', NotificationKind::LegalDocumentChanged->value)
            ->where('notifications.data.0.has_link', true));
        $this->actingAs($fr)->get('/notifications/'.$fr->notifications()->sole()->id)->assertRedirect('/use-conditions');
    }

    public function test_a_second_run_sends_nothing_again_to_members_already_mailed(): void
    {
        config(['mail.default' => 'array']);
        $user = User::factory()->create();
        $args = ['document' => 'privacy', '--effective' => '2026-11-19', '--send' => true];
        $mails = fn (): int => $this->sentMails();

        $this->artisan('vff:notify-legal-change', $args)->assertSuccessful();
        $this->assertSame(1, $mails());
        $this->assertNotNull($user->notifications()->sole()->data['mailed_at'] ?? null, 'le courriel parti est noté');
        $this->artisan('vff:notify-legal-change', $args)
            ->expectsOutputToContain('1 membre(s) déjà prévenu(s)')
            ->assertSuccessful();
        $this->assertSame(1, $mails(), 'aucun second courriel');
        $this->assertSame(1, $user->notifications()->count());

        // Autre changement (autre date) : nouvelle notification.
        $this->artisan('vff:notify-legal-change', [...$args, '--effective' => '2027-01-15'])->assertSuccessful();
        $this->assertSame(2, $user->notifications()->count());
    }

    public function test_a_failed_email_keeps_the_site_notification_and_a_retry_does_not_double_it(): void
    {
        $user = User::factory()->create();
        $args = ['document' => 'privacy', '--effective' => '2026-11-19', '--send' => true];
        config([
            'mail.default' => 'unreachable',
            'mail.mailers.unreachable' => ['transport' => 'smtp', 'host' => '127.0.0.1', 'port' => 1, 'timeout' => 2],
        ]);

        $this->artisan('vff:notify-legal-change', $args)->assertFailed();
        $this->assertSame(1, $user->notifications()->count());

        config(['mail.default' => 'array']);
        $other = User::factory()->create();
        $other->notifications()->create([
            'id' => (string) Str::uuid(), 'type' => LegalDocumentChanged::class,
            'data' => ['kind' => NotificationKind::LegalDocumentChanged->value, 'document' => 'privacy', 'effective_date' => '2026-11-19', 'mailed_at' => '2026-11-19T09:00:00+00:00'],
        ]);
        // Seul l'échec est repris : le membre déjà prévenu par courriel ne reçoit rien.
        $this->artisan('vff:notify-legal-change', $args)->expectsOutputToContain('1 membre(s) prévenu(s)')->assertSuccessful();
        $this->assertSame(1, $this->sentMails());
        $this->assertDatabaseCount('notifications', 2);
        $this->assertNotNull($user->notifications()->sole()->data['mailed_at'] ?? null);
    }

    /** Courriels envoyés par le transport « array » des tests. */
    private function sentMails(): int
    {
        $transport = app('mail.manager')->mailer('array')->getSymfonyTransport();
        $this->assertInstanceOf(ArrayTransport::class, $transport);

        return $transport->messages()->count();
    }

    public function test_legal_pages_show_the_version_in_force(): void
    {
        config(['legal.versions.privacy' => '2026-11-19', 'legal.versions.use-conditions' => '2026-10-21']);

        $this->get('/privacy')->assertInertia(fn (Assert $page) => $page->component('Privacy')->where('updated', '2026-11-19'));
        $this->get('/use-conditions')->assertInertia(fn (Assert $page) => $page->component('UseConditions')->where('updated', '2026-10-21'));
        $this->assertSame('2026-10-21', LegalDocument::UseConditions->currentVersion());
    }

    public function test_every_legal_document_has_a_valid_version_date(): void
    {
        foreach (LegalDocument::cases() as $document) {
            $version = $document->currentVersion();
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/D', $version, $document->value);
            $this->assertNotFalse(\DateTimeImmutable::createFromFormat('!Y-m-d', $version));
        }
    }
}

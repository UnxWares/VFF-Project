<?php

namespace Tests\Feature\ErrorReports;

use App\Enums\ErrorReportStatus;
use App\Models\ErrorReport;
use App\Models\Line;
use App\Notifications\ErrorReportFollowUp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ActsAsRoles;
use Tests\TestCase;

/**
 * Courriel de suivi à la personne qui a signalé une erreur : mis en file quand l'équipe traite ou
 * rejette le signalement, seulement si elle a laissé son adresse, dans la langue de sa page.
 */
class ErrorReportFollowUpTest extends TestCase
{
    use ActsAsRoles;
    use RefreshDatabase;

    private Line $line;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withCookie('locale', 'fr');
        $this->line = Line::factory()->create(['name' => 'Paris - Le Havre', 'code' => '340000', 'slug' => 'paris-le-havre']);
    }

    /** @param  array<string, mixed>  $attributes */
    private function report(array $attributes = []): ErrorReport
    {
        $report = new ErrorReport([
            'subject_type' => 'line',
            'subject_id' => $this->line->id,
            'subject_slug' => 'paris-le-havre',
            'subject_label' => 'Paris - Le Havre (340000)',
            'description' => 'La ligne a été électrifiée en 1967, pas en 1966.',
            'contact_email' => 'lecteur@exemple.fr',
            'locale' => 'fr',
            ...$attributes,
        ]);
        $report->save();

        return $report;
    }

    /** @return array<string, array{0: string}> */
    public static function outcomes(): array
    {
        return ['traité' => ['resolved'], 'rejeté' => ['rejected']];
    }

    #[DataProvider('outcomes')]
    public function test_the_reporter_is_emailed_when_the_report_is_handled(string $status): void
    {
        Notification::fake();
        $report = $this->report();

        $this->actingAs($this->member('validator'))
            ->patch("/validate/reports/{$report->id}", ['status' => $status, 'note' => 'Date vérifiée.'])
            ->assertSessionHas('success');

        Notification::assertSentOnDemand(
            ErrorReportFollowUp::class,
            function (ErrorReportFollowUp $notification, array $channels, AnonymousNotifiable $notifiable) use ($report, $status): bool {
                return $channels === ['mail']
                    && $notifiable->routes['mail'] === 'lecteur@exemple.fr'
                    && $notification->reportId === $report->id
                    && $notification->outcome === ErrorReportStatus::from($status)
                    && $notification->note === 'Date vérifiée.'
                    && $notification->sheetPath === '/lines/paris-le-havre'
                    && $notification->locale === 'fr';
            },
        );
        Notification::assertSentOnDemandTimes(ErrorReportFollowUp::class, 1);
    }

    public function test_nobody_is_emailed_without_an_address(): void
    {
        Notification::fake();
        $validator = $this->member('validator');
        $withoutAddress = $this->report(['contact_email' => null]);
        $memberReport = $this->report(['contact_email' => null, 'user_id' => $this->member('contributor')->id]);

        $this->actingAs($validator)->patch("/validate/reports/{$withoutAddress->id}", ['status' => 'resolved'])->assertSessionHas('success');
        $this->actingAs($validator)->patch("/validate/reports/{$memberReport->id}", ['status' => 'rejected', 'note' => 'Hors sujet.'])->assertSessionHas('success');

        Notification::assertNothingSent();
    }

    public function test_nothing_is_sent_when_the_decision_fails_or_is_repeated(): void
    {
        Notification::fake();
        $validator = $this->member('validator');
        $report = $this->report();

        // Rejet sans note : refusé, rien n'est enregistré, rien n'est envoyé.
        $this->actingAs($validator)->patch("/validate/reports/{$report->id}", ['status' => 'rejected'])->assertSessionHasErrors('note');
        Notification::assertNothingSent();

        $this->actingAs($validator)->patch("/validate/reports/{$report->id}", ['status' => 'resolved'])->assertSessionHas('success');
        // Double traitement : le second est refusé, la personne n'est pas prévenue deux fois.
        $this->actingAs($this->member('admin'))->patch("/validate/reports/{$report->id}", ['status' => 'rejected', 'note' => 'Doublon.'])->assertSessionHas('error');

        Notification::assertSentOnDemandTimes(ErrorReportFollowUp::class, 1);
    }

    public function test_the_mail_is_queued_not_sent_during_the_request(): void
    {
        Queue::fake();
        $report = $this->report();

        $this->actingAs($this->member('validator'))
            ->patch("/validate/reports/{$report->id}", ['status' => 'resolved'])
            ->assertSessionHas('success');

        Queue::assertPushed(
            SendQueuedNotifications::class,
            fn ($job): bool => $job->notification instanceof ErrorReportFollowUp && $job->channels === ['mail'] && $job->tries === 3,
        );
    }

    public function test_the_follow_up_links_to_the_renamed_sheet_or_omits_the_link_when_it_is_gone(): void
    {
        Notification::fake();
        $validator = $this->member('validator');
        $renamed = $this->report();
        $this->line->update(['slug' => 'paris-saint-lazare-le-havre']);
        $this->actingAs($validator)->patch("/validate/reports/{$renamed->id}", ['status' => 'resolved'])->assertSessionHas('success');

        $gone = $this->report(['subject_id' => 999_999, 'subject_slug' => 'disparue']);
        $this->actingAs($validator)->patch("/validate/reports/{$gone->id}", ['status' => 'resolved'])->assertSessionHas('success');

        Notification::assertSentOnDemand(ErrorReportFollowUp::class, fn (ErrorReportFollowUp $n) => $n->reportId === $renamed->id && $n->sheetPath === '/lines/paris-saint-lazare-le-havre');
        Notification::assertSentOnDemand(ErrorReportFollowUp::class, fn (ErrorReportFollowUp $n) => $n->reportId === $gone->id && $n->sheetPath === null);
    }

    #[DataProvider('mailLocales')]
    public function test_the_mail_is_written_in_the_language_of_the_report(string $stored, string $expected, string $subject): void
    {
        $notification = new ErrorReportFollowUp(12, 'Paris - Le Havre (340000)', ErrorReportStatus::Resolved, 'Date vérifiée.', '/lines/paris-le-havre', $stored);

        $this->assertSame($expected, $notification->locale);
        $mail = $this->render($notification, $expected);
        $this->assertSame($subject, $mail->subject);
    }

    /** @return array<string, array{0: string, 1: string, 2: string}> */
    public static function mailLocales(): array
    {
        return [
            'français' => ['fr', 'fr', 'Votre signalement n° 12 a été traité'],
            'anglais' => ['en', 'en', 'Your report no. 12 has been dealt with'],
            'allemand' => ['de', 'de', 'Ihre Meldung Nr. 12 wurde bearbeitet'],
            'langue inconnue' => ['es', 'fr', 'Votre signalement n° 12 a été traité'],
        ];
    }

    public function test_the_mail_content_is_sober(): void
    {
        $resolved = $this->render(new ErrorReportFollowUp(12, 'Paris - Le Havre (340000)', ErrorReportStatus::Resolved, '  Date corrigée.  ', '/lines/paris-le-havre', 'fr'), 'fr');
        $this->assertSame('Bonjour,', $resolved->greeting);
        $this->assertContains('Vous avez signalé une erreur sur la fiche « Paris - Le Havre (340000) » (signalement n° 12).', $resolved->introLines);
        $this->assertContains('L’équipe l’a traité. Merci de votre aide.', $resolved->introLines);
        $this->assertContains('Note de l’équipe : Date corrigée.', $resolved->introLines);
        $this->assertStringEndsWith('/lines/paris-le-havre', $resolved->actionUrl);
        // Ni le texte du signalement ni l'adresse ne sont recopiés.
        $this->assertStringNotContainsString('1967', json_encode($resolved->introLines, JSON_UNESCAPED_UNICODE));

        $rejected = $this->render(new ErrorReportFollowUp(13, 'Gare de Lyon', ErrorReportStatus::Rejected, null, null, 'en'), 'en');
        $this->assertSame('Your report no. 13 was not accepted', $rejected->subject);
        $this->assertEmpty($rejected->actionUrl);
        $this->assertCount(3, $rejected->introLines, 'sans note ni lien : introduction, suite donnée, origine de l’adresse');
    }

    private function render(ErrorReportFollowUp $notification, string $locale): MailMessage
    {
        $this->app->setLocale($locale);

        return $notification->toMail(new AnonymousNotifiable);
    }
}

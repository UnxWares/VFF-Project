<?php

namespace Tests\Feature\ErrorReports;

use App\Enums\AccountDeletionReason;
use App\Enums\AuditAction;
use App\Enums\ErrorReportStatus;
use App\Enums\NotificationKind;
use App\Models\Accident;
use App\Models\AuditEvent;
use App\Models\ErrorReport;
use App\Models\Line;
use App\Models\Material;
use App\Models\Station;
use App\Services\Account\AccountDataExport;
use App\Services\Account\AccountDeletion;
use App\Services\ErrorReports\ErrorReportFormToken;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ActsAsRoles;
use Tests\TestCase;

/**
 * Signalement d'une erreur depuis une fiche : envoi (visiteur et membre), anti-abus (champ
 * piège, durée minimale, limite de débit), liste et traitement par les validateurs, notification,
 * journal d'audit, export et suppression avec le compte, purge à 12 mois.
 */
class ErrorReportTest extends TestCase
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

    /** Jeton émis il y a dix secondes : un humain qui a pris le temps d'écrire. */
    private function token(): string
    {
        $this->travel(-10)->seconds();
        $token = app(ErrorReportFormToken::class)->issue();
        $this->travelBack();

        return $token;
    }

    /** @param  array<string, mixed>  $overrides */
    private function payload(array $overrides = []): array
    {
        return [
            'subject_type' => 'line',
            'subject_slug' => 'paris-le-havre',
            'description' => 'La ligne a été électrifiée en 1967, pas en 1966.',
            'source_url' => 'https://fr.wikipedia.org/wiki/Ligne_de_Paris-Saint-Lazare_au_Havre',
            'contact_email' => 'lecteur@exemple.fr',
            'form_token' => $this->token(),
            ...$overrides,
        ];
    }

    public function test_the_token_endpoint_returns_a_fresh_uncached_token(): void
    {
        $response = $this->getJson('/reports/token')->assertOk()->assertJsonStructure(['token']);

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertTrue(app(ErrorReportFormToken::class)->accepts(null) === false);
    }

    public function test_a_visitor_can_report_an_error(): void
    {
        $validator = $this->member('validator');

        $this->from('/lines/paris-le-havre')->post('/reports', $this->payload())
            ->assertRedirect('/lines/paris-le-havre')
            ->assertSessionHasNoErrors();

        $report = ErrorReport::sole();
        $this->assertSame('line', $report->subject_type->value);
        $this->assertSame($this->line->id, $report->subject_id);
        $this->assertSame('Paris - Le Havre (340000)', $report->subject_label);
        $this->assertSame('lecteur@exemple.fr', $report->contact_email);
        $this->assertNull($report->user_id);
        $this->assertSame(ErrorReportStatus::Open, $report->status);
        $this->assertSame('fr', $report->locale);

        // Notification sur le site, sans le texte ni l'adresse de la personne.
        $notification = $validator->notifications()->sole();
        $this->assertSame(NotificationKind::ErrorReported->value, $notification->data['kind']);
        $this->assertSame("/validate/reports#report-{$report->id}", $notification->data['url']);
        $this->assertStringContainsString('Paris - Le Havre (340000)', $notification->data['body']);
        $this->assertStringNotContainsString('lecteur@exemple.fr', json_encode($notification->data));
        $this->assertStringNotContainsString('1967', json_encode($notification->data));

        $event = AuditEvent::sole();
        $this->assertSame(AuditAction::ErrorReportReceived, $event->action);
        $this->assertNull($event->actor_id);
        $this->assertSame($report->id, $event->context['report_id']);
        $this->assertStringNotContainsString('lecteur@exemple.fr', json_encode($event->context));
    }

    public function test_a_member_report_is_attached_to_the_account_and_not_notified_to_themselves(): void
    {
        $member = $this->member('validator');
        $other = $this->member('admin');

        $this->actingAs($member)->post('/reports', $this->payload(['contact_email' => '', 'source_url' => '']))
            ->assertSessionHasNoErrors();

        $report = ErrorReport::sole();
        $this->assertSame($member->id, $report->user_id);
        $this->assertNull($report->contact_email);
        $this->assertNull($report->source_url);
        $this->assertSame(0, $member->notifications()->count());
        $this->assertSame(1, $other->notifications()->count());
        $this->assertSame($member->id, AuditEvent::sole()->actor_id);
    }

    /** @return array<string, array{0: string, 1: class-string}> */
    public static function subjects(): array
    {
        return [
            'gare' => ['station', Station::class],
            'matériel' => ['material', Material::class],
            'accident' => ['accident', Accident::class],
        ];
    }

    /** @param  class-string  $model */
    #[DataProvider('subjects')]
    public function test_every_kind_of_sheet_can_be_reported(string $type, string $model): void
    {
        $subject = $model::factory()->create();

        $this->post('/reports', $this->payload(['subject_type' => $type, 'subject_slug' => $subject->slug]))
            ->assertSessionHasNoErrors();

        $report = ErrorReport::sole();
        $this->assertSame($subject->id, $report->subject_id);
        $this->assertNotSame('', $report->subject_label);
    }

    public function test_the_honeypot_is_silently_ignored(): void
    {
        $this->post('/reports', $this->payload(['homepage' => 'https://spam.example']))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(0, ErrorReport::count());
        $this->assertSame(0, AuditEvent::count());
    }

    public function test_a_form_sent_too_quickly_or_without_token_is_refused(): void
    {
        $fresh = app(ErrorReportFormToken::class)->issue();

        $this->post('/reports', $this->payload(['form_token' => $fresh]))->assertSessionHasErrors('form');
        $this->post('/reports', $this->payload(['form_token' => null]))->assertSessionHasErrors('form');
        $this->post('/reports', $this->payload(['form_token' => 'forgé']))->assertSessionHasErrors('form');

        // Jeton périmé (plus de deux heures).
        $this->travel(-3)->hours();
        $old = app(ErrorReportFormToken::class)->issue();
        $this->travelBack();
        $this->post('/reports', $this->payload(['form_token' => $old]))->assertSessionHasErrors('form');

        $this->assertSame(0, ErrorReport::count());
    }

    public function test_invalid_fields_are_refused(): void
    {
        $this->post('/reports', $this->payload(['description' => 'Faux']))->assertSessionHasErrors('description');
        $this->post('/reports', $this->payload(['description' => str_repeat('x', 2001)]))->assertSessionHasErrors('description');
        $this->post('/reports', $this->payload(['source_url' => 'javascript:alert(1)']))->assertSessionHasErrors('source_url');
        $this->post('/reports', $this->payload(['contact_email' => 'pas une adresse']))->assertSessionHasErrors('contact_email');
        $this->post('/reports', $this->payload(['subject_type' => 'media']))->assertSessionHasErrors('subject_type');
        $this->post('/reports', $this->payload(['subject_slug' => 'ligne-inconnue']))->assertSessionHasErrors('form');

        $this->assertSame(0, ErrorReport::count());
    }

    public function test_reports_are_rate_limited(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->post('/reports', $this->payload())->assertSessionHasNoErrors();
        }
        $this->post('/reports', $this->payload())
            ->assertRedirect()
            ->assertSessionHasErrors(['form' => __('reports.errors.too_many')]);

        $this->assertSame(6, ErrorReport::count());
    }

    public function test_guests_and_non_validators_cannot_see_the_list(): void
    {
        $this->get('/validate/reports')->assertRedirect('/login');
        $this->actingAs($this->member('contributor'))->get('/validate/reports')->assertForbidden();
        $this->actingAs($this->member('visitor'))->get('/validate/reports')->assertForbidden();

        $report = $this->report();
        $this->actingAs($this->member('contributor'))
            ->patch("/validate/reports/{$report->id}", ['status' => 'resolved'])
            ->assertForbidden();
        $this->assertTrue($report->fresh()->status->isOpen());
    }

    #[DataProvider('validatorRoles')]
    public function test_validators_see_reports_filtered_by_status(string $role): void
    {
        $open = $this->report();
        $this->report(['status' => 'resolved', 'handled_at' => now(), 'resolution_note' => 'Corrigé.']);

        $this->actingAs($this->member($role))->get('/validate/reports')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Validate/Reports')
                ->where('status', 'open')
                ->where('counts.open', 1)
                ->where('counts.resolved', 1)
                ->where('counts.rejected', 0)
                ->has('reports.data', 1)
                ->where('reports.data.0.id', $open->id)
                ->where('reports.data.0.subject.url', '/lines/paris-le-havre')
                ->where('reports.data.0.contact_email', 'lecteur@exemple.fr'));

        $this->actingAs($this->member($role))->get('/validate/reports?status=resolved')
            ->assertInertia(fn (Assert $page) => $page
                ->where('status', 'resolved')
                ->has('reports.data', 1)
                ->where('reports.data.0.resolution_note', 'Corrigé.'));
    }

    public function test_the_sheet_link_follows_a_renamed_slug(): void
    {
        $this->report();
        $this->line->update(['slug' => 'paris-saint-lazare-le-havre']);

        $this->actingAs($this->member('validator'))->get('/validate/reports')
            ->assertInertia(fn (Assert $page) => $page->where('reports.data.0.subject.url', '/lines/paris-saint-lazare-le-havre'));
    }

    public function test_a_validator_marks_a_report_as_resolved(): void
    {
        $validator = $this->member('validator');
        $report = $this->report();

        $this->actingAs($validator)
            ->patch("/validate/reports/{$report->id}", ['status' => 'resolved', 'note' => '  Date corrigée.  '])
            ->assertRedirect()
            ->assertSessionHas('success');

        $report->refresh();
        $this->assertSame(ErrorReportStatus::Resolved, $report->status);
        $this->assertSame('Date corrigée.', $report->resolution_note);
        $this->assertSame($validator->id, $report->handled_by);
        $this->assertNotNull($report->handled_at);

        $event = AuditEvent::query()->where('action', AuditAction::ErrorReportResolved->value)->sole();
        $this->assertSame($validator->id, $event->actor_id);
        $this->assertTrue($event->context['with_note']);
        $this->assertStringNotContainsString('Date corrigée', json_encode($event->context));

        // Déjà traité : pas de second traitement.
        $this->actingAs($this->member('admin'))
            ->patch("/validate/reports/{$report->id}", ['status' => 'rejected', 'note' => 'Doublon.'])
            ->assertSessionHas('error');
        $this->assertSame(ErrorReportStatus::Resolved, $report->fresh()->status);
    }

    public function test_rejecting_requires_a_note(): void
    {
        $report = $this->report();
        $admin = $this->member('admin');

        $this->actingAs($admin)->patch("/validate/reports/{$report->id}", ['status' => 'rejected'])
            ->assertSessionHasErrors('note');
        $this->actingAs($admin)->patch("/validate/reports/{$report->id}", ['status' => 'open'])
            ->assertSessionHasErrors('status');
        $this->assertTrue($report->fresh()->status->isOpen());

        $this->actingAs($admin)->patch("/validate/reports/{$report->id}", ['status' => 'rejected', 'note' => 'Hors sujet.'])
            ->assertSessionHasNoErrors();
        $this->assertSame(ErrorReportStatus::Rejected, $report->fresh()->status);
    }

    public function test_handled_reports_are_pruned_after_twelve_months(): void
    {
        $old = $this->report(['status' => 'resolved', 'handled_at' => now()->subMonths(13)]);
        $oldRejected = $this->report(['status' => 'rejected', 'handled_at' => now()->subMonths(13), 'resolution_note' => 'Non.']);
        $recent = $this->report(['status' => 'resolved', 'handled_at' => now()->subMonths(11)]);
        $openOld = $this->report(['created_at' => now()->subYears(2)]);

        $this->artisan('model:prune', ['--model' => [ErrorReport::class]])->assertSuccessful();

        $this->assertNull(ErrorReport::find($old->id));
        $this->assertNull(ErrorReport::find($oldRejected->id));
        $this->assertNotNull(ErrorReport::find($recent->id));
        $this->assertNotNull(ErrorReport::find($openOld->id));
    }

    public function test_the_purge_is_scheduled_in_production_only(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($e) => $e->description === 'vff:prune-error-reports');

        $this->assertNotNull($event);
        $this->assertSame(['production'], $event->environments);
    }

    public function test_member_reports_are_exported_and_deleted_with_the_account(): void
    {
        $member = $this->member('contributor');
        $report = $this->report(['user_id' => $member->id]);
        $handler = $this->member('validator');
        $handled = $this->report(['status' => 'resolved', 'handled_at' => now(), 'handled_by' => $handler->id]);
        $foreign = $this->report();

        $export = app(AccountDataExport::class)->for($member);
        $this->assertCount(1, $export['error_reports']);
        $this->assertSame($report->id, $export['error_reports'][0]['id']);
        $this->assertSame('lecteur@exemple.fr', $export['error_reports'][0]['contact_email']);
        $this->assertSame('paris-le-havre', $export['error_reports'][0]['subject']['slug']);

        app(AccountDeletion::class)->delete($member, AccountDeletionReason::Owner);
        $this->assertNull(ErrorReport::find($report->id));
        $this->assertNotNull(ErrorReport::find($foreign->id));

        // Le compte d'un validateur supprimé : ses traitements restent, sans personne désignée.
        app(AccountDeletion::class)->delete($handler, AccountDeletionReason::Owner);
        $this->assertNull($handled->fresh()->handled_by);
    }

    public function test_the_export_endpoint_includes_reports(): void
    {
        $member = $this->member('contributor');
        $this->report(['user_id' => $member->id]);

        $this->actingAs($member)->get('/profile/data')
            ->assertOk()
            ->assertJsonPath('error_reports.0.subject.type', 'line');
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
        if (isset($attributes['created_at'])) {
            $report->created_at = $attributes['created_at'];
        }
        $report->save();

        return $report;
    }
}

<?php

namespace Tests\Feature\Account;

use App\Enums\ContributionStatus;
use App\Models\Contribution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Droit d'accès et de portabilité : export JSON des données du compte. */
class AccountExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected(): void
    {
        $this->get('/profile/data')->assertRedirect('/login');
    }

    public function test_export_is_a_json_download_with_profile_contributions_and_approvals(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 19));
        $user = User::factory()->validator()->create(['name' => 'Élise Martin', 'bio' => 'Passionnée du PO']);
        $user->forceFill(['terms_accepted_at' => now()->setTime(9, 30), 'terms_version' => '2026-10-21'])->save();
        $mine = Contribution::factory()->for($user)->create([
            'type' => 'line_segment',
            'status' => ContributionStatus::Pending->value,
            'payload' => ['line_code' => '590000', 'notes' => 'Voir Chaix 1938'],
        ]);
        $reviewed = Contribution::factory()->create([
            'status' => ContributionStatus::Rejected->value,
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
            'review_notes' => 'Source manquante',
        ]);
        $approved = Contribution::factory()->create(['status' => ContributionStatus::Approved->value]);
        DB::table('contribution_approvals')->insert(['contribution_id' => $approved->id, 'user_id' => $user->id, 'approved_at' => now()]);
        $user->createToken('mon-script');
        $someoneElse = Contribution::factory()->create();

        $response = $this->actingAs($user)->get('/profile/data');

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertHeader('Content-Disposition', 'attachment; filename="vff-account-data-2026-11-19.json"');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $data = $response->json();
        $this->assertSame('vff-account-export', $data['format']);
        $this->assertSame(1, $data['version']);
        $this->assertSame('Élise Martin', $data['profile']['name']);
        $this->assertSame($user->email, $data['profile']['email']);
        $this->assertSame('Passionnée du PO', $data['profile']['bio']);
        $this->assertSame('validator', $data['profile']['role']);
        $this->assertSame('2026-10-21', $data['profile']['terms_version']);
        $this->assertStringStartsWith('2026-11-19T09:30:00', $data['profile']['terms_accepted_at']);

        $this->assertCount(1, $data['contributions']);
        $this->assertSame($mine->id, $data['contributions'][0]['id']);
        $this->assertSame('Voir Chaix 1938', $data['contributions'][0]['payload']['notes']);
        $this->assertSame([['contribution_id' => $approved->id, 'approved_at' => $data['approvals_given'][0]['approved_at'], 'notes' => null]], $data['approvals_given']);
        $this->assertSame($reviewed->id, $data['reviews_made'][0]['contribution_id']);
        $this->assertSame('Source manquante', $data['reviews_made'][0]['review_notes']);
        $this->assertSame('mon-script', $data['api_tokens'][0]['name']);

        // Rien d'un autre membre, aucun secret.
        $raw = $response->getContent();
        $this->assertNotContains($someoneElse->id, array_column($data['contributions'], 'id'));
        $this->assertStringNotContainsString('password', $raw);
        $this->assertStringNotContainsString('remember_token', $raw);
        $this->assertStringNotContainsString($user->getAuthPassword(), $raw);
        $this->assertStringContainsString('Élise', $raw, 'Unicode non échappé');
    }

    public function test_export_is_rate_limited(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 6; $i++) {
            $this->actingAs($user)->get('/profile/data')->assertOk();
        }

        $this->actingAs($user)->get('/profile/data')->assertStatus(429);
    }
}

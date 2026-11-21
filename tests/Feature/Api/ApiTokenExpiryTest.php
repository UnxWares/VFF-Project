<?php

namespace Tests\Feature\Api;

use App\Enums\AuditAction;
use App\Models\AuditEvent;
use App\Models\User;
use App\Notifications\ApiTokensExpiring;
use App\Services\Api\ApiTokens;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Support\ActsAsRoles;
use Tests\TestCase;

/**
 * Échéance des jetons d'API (décision du 20/11/2026) : un an après la création ou le dernier
 * renouvellement, jeton refusé par l'API une fois échu, rappel par courriel 30 jours avant
 * (vff:remind-expiring-api-tokens, une fois par échéance et par compte), renouvellement d'un clic
 * depuis le profil, échéance posée aux jetons existants par la migration.
 */
class ApiTokenExpiryTest extends TestCase
{
    use ActsAsRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withCookie('locale', 'fr');
    }

    private function token(User $user, string $name = 'Script'): PersonalAccessToken
    {
        return app(ApiTokens::class)->create($user, $name)->accessToken;
    }

    public function test_a_new_token_expires_one_year_after_its_creation(): void
    {
        $this->freezeSecond();
        $token = $this->token($this->member('contributor'));

        $this->assertTrue($token->fresh()->expires_at->equalTo(now()->addDays(365)));
    }

    public function test_an_expired_token_is_refused_by_the_api(): void
    {
        $user = $this->member('contributor');
        $plain = app(ApiTokens::class)->create($user, 'Script')->plainTextToken;

        $this->travel(366)->days();

        $this->withToken($plain)->getJson('/api/v1/eras')->assertUnauthorized()->assertJsonPath('error', 'invalid_token');
    }

    public function test_one_click_renewal_extends_by_a_year_keeps_the_secret_and_is_audited(): void
    {
        $user = $this->member('contributor');
        $new = app(ApiTokens::class)->create($user, 'Script');
        $this->travel(370)->days();
        DB::table('personal_access_tokens')->update(['expiry_reminded_at' => now()]);

        $this->actingAs($user)->post("/profile/api-tokens/{$new->accessToken->id}/renew")
            ->assertRedirect()
            ->assertSessionHas('success', 'Le jeton a été renouvelé pour un an. Les programmes qui l’utilisent n’ont rien à changer.');

        $token = $new->accessToken->fresh();
        $this->assertTrue($token->expires_at->isAfter(now()->addDays(364)));
        $this->assertNull($token->getAttribute('expiry_reminded_at'), 'nouveau rappel possible pour la prochaine échéance');
        $this->withToken($new->plainTextToken)->getJson('/api/v1/eras')->assertOk();
        $this->assertSame(1, AuditEvent::query()->where('action', AuditAction::ApiTokenRenewed->value)->count());
    }

    public function test_nobody_renews_the_token_of_another_account_and_a_suspended_account_cannot_renew(): void
    {
        $owner = $this->member('contributor');
        $token = $this->token($owner);

        $this->actingAs($this->member('contributor'))->post("/profile/api-tokens/{$token->id}/renew")->assertNotFound();
        $this->actingAs($this->member('contributor', ['suspended_at' => now()]))->post("/profile/api-tokens/{$token->id}/renew")->assertForbidden();
        $this->assertFalse(app(ApiTokens::class)->renew($owner->forceFill(['suspended_at' => now()]), $token->id));
    }

    public function test_the_profile_shows_the_expiry_and_flags_tokens_close_to_it(): void
    {
        $user = $this->member('contributor');
        $soon = $this->token($user, 'Bientôt');
        $soon->forceFill(['expires_at' => now()->addDays(10)])->save();
        $past = $this->token($user, 'Échu');
        $past->forceFill(['expires_at' => now()->subDay()])->save();

        $this->actingAs($user)->get('/profile')->assertInertia(fn (Assert $page) => $page
            ->where('api_access.lifetime_days', 365)
            ->where('api_access.tokens.0.name', 'Échu')
            ->where('api_access.tokens.0.expired', true)
            ->where('api_access.tokens.1.name', 'Bientôt')
            ->where('api_access.tokens.1.expired', false)
            ->where('api_access.tokens.1.expires_soon', true)
            ->where('api_access.tokens.1.expires_at', fn (string $iso) => str_starts_with($iso, now()->addDays(10)->toDateString())));
    }

    public function test_the_reminder_is_sent_once_30_days_before_with_one_mail_per_account(): void
    {
        Notification::fake();
        $user = $this->member('contributor');
        $a = $this->token($user, 'Script A');
        $b = $this->token($user, 'Script B');
        $later = $this->token($user, 'Plus tard');
        $a->forceFill(['expires_at' => now()->addDays(29)])->save();
        $b->forceFill(['expires_at' => now()->addDays(5)])->save();
        $later->forceFill(['expires_at' => now()->addDays(45)])->save();
        $suspended = $this->member('contributor');
        $this->token($suspended)->forceFill(['expires_at' => now()->addDays(3)])->save();
        $suspended->forceFill(['suspended_at' => now()])->save();

        $this->artisan('vff:remind-expiring-api-tokens', ['--dry-run' => true])->expectsOutputToContain('2 compte(s) à prévenir, 3 jeton(s)')->assertSuccessful();
        Notification::assertNothingSent();

        $this->artisan('vff:remind-expiring-api-tokens')->expectsOutputToContain('1 compte(s) prévenu(s)')->assertSuccessful();
        Notification::assertSentToTimes($user, ApiTokensExpiring::class, 1);
        Notification::assertSentTo($user, ApiTokensExpiring::class, fn (ApiTokensExpiring $n) => array_column($n->tokens, 'name') === ['Script B', 'Script A']);
        Notification::assertNotSentTo($suspended, ApiTokensExpiring::class);

        // Second passage : rien de nouveau.
        $this->artisan('vff:remind-expiring-api-tokens')->expectsOutputToContain('0 compte(s) prévenu(s)');
        Notification::assertSentToTimes($user, ApiTokensExpiring::class, 1);
    }

    public function test_the_reminder_mail_lists_the_tokens_without_their_secret(): void
    {
        $user = $this->member('contributor');
        $mail = (new ApiTokensExpiring([['name' => 'Script A', 'expires_at' => now()->setDate(2027, 3, 4)]]))->toMail($user);

        $this->assertSame('Votre jeton d’API Voies Ferrées de France expire bientôt', $mail->subject);
        $this->assertStringContainsString('« Script A » : expire le 4 mars 2027.', implode(' ', $mail->introLines));
        $this->assertSame(route('profile.show'), $mail->actionUrl);
    }

    public function test_the_migration_gives_existing_tokens_a_year_from_creation_and_at_least_30_days(): void
    {
        $user = $this->member('contributor');
        $old = $this->token($user, 'Ancien');
        $recent = $this->token($user, 'Récent');
        DB::table('personal_access_tokens')->where('id', $old->id)->update(['expires_at' => null, 'created_at' => now()->subMonths(13)]);
        DB::table('personal_access_tokens')->where('id', $recent->id)->update(['expires_at' => null, 'created_at' => now()->subMonths(2)]);

        $migration = require database_path('migrations/2026_12_03_010000_expire_api_tokens_after_a_year.php');
        $migration->down();
        $this->assertSame(0, DB::table('personal_access_tokens')->whereNotNull('expires_at')->count());
        $migration->up();

        $this->assertEqualsWithDelta(now()->addDays(30)->timestamp, $old->fresh()->expires_at->timestamp, 5);
        $this->assertEqualsWithDelta(now()->subMonths(2)->addYear()->timestamp, $recent->fresh()->expires_at->timestamp, 5);
    }
}

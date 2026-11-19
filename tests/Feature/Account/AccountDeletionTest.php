<?php

namespace Tests\Feature\Account;

use App\Enums\AuditAction;
use App\Enums\ContributionStatus;
use App\Models\AuditEvent;
use App\Models\Contribution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Suppression du compte par son titulaire (politique de confidentialité, « Contributions » et
 * « Vos droits ») : contributions publiées gardées sans auteur, le reste effacé, journal sans
 * donnée personnelle.
 */
class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $state = 'contributor'): User
    {
        return User::factory()->{$state}()->create(['password' => 'secret-password']);
    }

    public function test_guest_cannot_delete_an_account(): void
    {
        $this->delete('/profile', ['password' => 'x'])->assertRedirect('/login');
    }

    public function test_wrong_password_keeps_the_account(): void
    {
        $user = $this->member();

        $this->actingAs($user)->from('/profile')
            ->delete('/profile', ['password' => 'not-the-password'])
            ->assertRedirect('/profile')
            ->assertSessionHasErrors('password');

        $this->assertModelExists($user);
        $this->assertAuthenticatedAs($user);
        $this->assertSame(0, AuditEvent::count());
    }

    public function test_password_is_required(): void
    {
        $user = $this->member();

        $this->actingAs($user)->delete('/profile', [])->assertSessionHasErrors('password');
        $this->assertModelExists($user);
    }

    public function test_deletion_keeps_published_contributions_anonymously_and_erases_the_rest(): void
    {
        $user = $this->member();
        $user->update(['bio' => 'Cheminot retraité', 'avatar_path' => null]);
        $validator = $this->member('validator');

        $applied = Contribution::factory()->for($user)->create(['status' => ContributionStatus::Applied->value, 'type' => 'line_segment']);
        $pending = Contribution::factory()->for($user)->create(['status' => ContributionStatus::Pending->value]);
        $partial = Contribution::factory()->for($user)->create(['status' => ContributionStatus::Approved->value]);
        $rejected = Contribution::factory()->for($user)->create(['status' => ContributionStatus::Rejected->value]);
        $someoneElse = Contribution::factory()->create(['status' => ContributionStatus::Pending->value]);
        DB::table('contribution_approvals')->insert([
            ['contribution_id' => $partial->id, 'user_id' => $validator->id, 'approved_at' => now()],
        ]);

        $user->createToken('script');
        DB::table('password_reset_tokens')->insert(['email' => $user->email, 'token' => 'hash', 'created_at' => now()]);

        $this->actingAs($user)
            ->delete('/profile', ['password' => 'secret-password'])
            ->assertRedirect(route('home'))
            ->assertSessionHas('success', __('account.flash.deleted'));

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('users', ['email' => $user->email]);

        // Publiée : reste, sans auteur.
        $this->assertNull($applied->fresh()->user_id);
        // Non publiées : supprimées, approbations comprises.
        foreach ([$pending, $partial, $rejected] as $gone) {
            $this->assertModelMissing($gone);
        }
        $this->assertSame(0, DB::table('contribution_approvals')->where('contribution_id', $partial->id)->count());
        // Les contributions des autres ne bougent pas.
        $this->assertModelExists($someoneElse);

        $this->assertSame(0, DB::table('personal_access_tokens')->count());
        $this->assertSame(0, DB::table('password_reset_tokens')->count());
    }

    public function test_deletion_is_logged_without_personal_data(): void
    {
        $user = $this->member();
        Contribution::factory()->for($user)->create(['status' => ContributionStatus::Applied->value]);
        $pending = Contribution::factory()->for($user)->create(['status' => ContributionStatus::Pending->value]);
        $rejected = Contribution::factory()->for($user)->create(['status' => ContributionStatus::Rejected->value]);
        // Suppression par le modèle : ses événements sont déclenchés pour chaque contribution.
        $deletedByModel = [];
        Contribution::deleted(function (Contribution $c) use (&$deletedByModel): void {
            $deletedByModel[] = $c->id;
        });

        $this->actingAs($user)->delete('/profile', ['password' => 'secret-password']);

        $event = AuditEvent::sole();
        $this->assertSame(AuditAction::AccountDeleted, $event->action);
        $this->assertNull($event->actor_id);
        $this->assertNull($event->contribution_id);
        $this->assertEquals([
            'reason' => 'owner',
            'role' => 'contributor',
            'contributions_deleted' => 2,
            'deleted_contribution_ids' => [$pending->id, $rejected->id],
            'contributions_anonymised' => 1,
            'approvals_anonymised' => 0,
        ], $event->context);
        $this->assertSame([$pending->id, $rejected->id], $deletedByModel);

        $raw = json_encode($event->getAttributes());
        $this->assertStringNotContainsString($user->email, $raw);
        $this->assertStringNotContainsString($user->name, $raw);
    }

    public function test_approvals_given_by_a_deleted_validator_still_count(): void
    {
        $validator = $this->member('validator');
        $other = $this->member('validator');
        $contribution = Contribution::factory()->create(['status' => ContributionStatus::Approved->value]);
        DB::table('contribution_approvals')->insert(['contribution_id' => $contribution->id, 'user_id' => $validator->id, 'approved_at' => now()]);
        $reviewed = Contribution::factory()->create([
            'status' => ContributionStatus::Rejected->value,
            'reviewed_by' => $validator->id,
            'reviewed_at' => now(),
            'review_notes' => 'Source introuvable',
        ]);

        $this->actingAs($validator)->delete('/profile', ['password' => 'secret-password'])->assertRedirect();

        $this->assertSame(1, DB::table('contribution_approvals')->where('contribution_id', $contribution->id)->count());
        $this->assertNull(DB::table('contribution_approvals')->where('contribution_id', $contribution->id)->value('user_id'));
        $this->assertNull($reviewed->fresh()->reviewed_by);
        $this->assertSame('Source introuvable', $reviewed->fresh()->review_notes);
        $this->assertModelExists($other);
        $this->assertSame(1, AuditEvent::first()->context['approvals_anonymised']);
    }

    public function test_user_id_copied_into_contributed_traces_is_removed(): void
    {
        $user = $this->member();
        $other = $this->member();
        foreach ([[$user, 'c1'], [$other, 'c2']] as [$owner, $ref]) {
            DB::statement(
                "INSERT INTO source_traces (source, source_ref, properties, geom, seen_at, created_at, updated_at)
                 VALUES ('contrib', ?, CAST(? AS jsonb), ST_Multi(ST_GeomFromText('LINESTRING(2 48, 2.1 48.1)', 4326)), NOW(), NOW(), NOW())",
                [$ref, json_encode(['contribution_id' => 7, 'user_id' => $owner->id, 'kind' => 'new_line'])],
            );
        }

        $this->actingAs($user)->delete('/profile', ['password' => 'secret-password']);

        $mine = json_decode(DB::table('source_traces')->where('source_ref', 'c1')->value('properties'), true);
        $theirs = json_decode(DB::table('source_traces')->where('source_ref', 'c2')->value('properties'), true);
        $this->assertArrayNotHasKey('user_id', $mine);
        $this->assertSame(7, $mine['contribution_id']);
        $this->assertSame('new_line', $mine['kind']);
        $this->assertSame($other->id, $theirs['user_id']);
    }

    public function test_uploaded_avatar_file_is_deleted(): void
    {
        Storage::fake('public');
        $path = UploadedFile::fake()->create('avatar.png', 12, 'image/png')->store('avatars', 'public');
        $user = $this->member();
        $user->update(['avatar_path' => '/storage/'.$path]);

        $this->actingAs($user)->delete('/profile', ['password' => 'secret-password']);

        Storage::disk('public')->assertMissing($path);
    }

    public function test_the_last_administrator_cannot_delete_their_account(): void
    {
        $admin = $this->member('admin');

        $this->actingAs($admin)->delete('/profile', ['password' => 'secret-password'])->assertForbidden();
        $this->assertModelExists($admin);

        // Avec un second administrateur, la suppression redevient possible.
        $this->member('admin');
        $this->actingAs($admin)->delete('/profile', ['password' => 'secret-password'])->assertRedirect(route('home'));
        $this->assertModelMissing($admin);
    }

    public function test_validator_list_shows_an_anonymous_author_for_a_deleted_account(): void
    {
        $validator = $this->member('validator');
        Contribution::factory()->create([
            'user_id' => null,
            'type' => 'line_segment',
            'status' => ContributionStatus::Pending->value,
        ]);

        $this->actingAs($validator)->get('/validate')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('contributions.0.user.name', __('account.anonymous_contributor'))
                ->where('contributions.0.user.reputation', null));
    }

    public function test_deletion_is_rate_limited(): void
    {
        $user = $this->member();

        for ($i = 0; $i < 6; $i++) {
            $this->actingAs($user)->delete('/profile', ['password' => 'wrong']);
        }

        $this->actingAs($user)->delete('/profile', ['password' => 'secret-password'])->assertStatus(429);
        $this->assertModelExists($user);
    }
}

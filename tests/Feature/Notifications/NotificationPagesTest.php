<?php

namespace Tests\Feature\Notifications;

use App\Models\DatabaseNotification;
use App\Models\User;
use App\Notifications\ContributionApplied;
use App\Notifications\ContributionRejected;
use App\Services\Account\AccountDataExport;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ActsAsRoles;
use Tests\TestCase;

/**
 * Page /notifications, nombre non lu partagé, lecture, export des données, suppression avec le
 * compte et purge des notifications lues depuis plus de 12 mois.
 */
class NotificationPagesTest extends TestCase
{
    use ActsAsRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withCookie('locale', 'fr');
    }

    private function notified(User $user, int $count = 1): void
    {
        for ($i = 1; $i <= $count; $i++) {
            $user->notify(new ContributionApplied($i, 'Paris - Versailles (999 000)'));
        }
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/notifications')->assertRedirect('/login');
        $this->post('/notifications/read-all')->assertRedirect('/login');
    }

    public function test_the_page_lists_only_my_notifications(): void
    {
        $me = $this->member('contributor');
        $this->notified($me, 2);
        $this->travel(1)->minute();
        $me->notify(new ContributionRejected(9, null, 'Doublon.'));
        $this->notified($this->member('contributor'));

        $this->actingAs($me)->get('/notifications')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Notifications/Index')
            ->has('notifications.data', 3)
            ->where('notifications.data.0.title', 'Votre contribution n° 9 n’a pas été retenue')
            ->where('notifications.data.0.reason', 'Doublon.')
            ->where('notifications.data.0.read', false)
            ->where('notifications.data.0.has_link', true)
            ->missing('notifications.data.0.url')
            ->where('auth.unread_notifications', 3));
    }

    public function test_the_unread_count_is_shared_with_members_only(): void
    {
        $this->get('/lines')->assertInertia(fn (Assert $page) => $page->where('auth.unread_notifications', null));

        $me = $this->member('contributor');
        $this->notified($me, 2);
        $this->actingAs($me)->get('/lines')->assertInertia(fn (Assert $page) => $page->where('auth.unread_notifications', 2));
    }

    public function test_opening_a_notification_marks_it_read_and_follows_its_link(): void
    {
        $me = $this->member('contributor');
        $this->notified($me);
        $notification = $me->notifications()->sole();

        $this->actingAs($me)->get("/notifications/{$notification->id}")->assertRedirect('/contribute/mine#contribution-1');

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_an_external_link_is_never_followed(): void
    {
        $me = $this->member('contributor');
        $this->notified($me);
        $notification = $me->notifications()->sole();
        $notification->forceFill(['data' => [...$notification->data, 'url' => '//exemple.org/']])->save();

        $this->actingAs($me)->get("/notifications/{$notification->id}")->assertRedirect('/notifications');
    }

    public function test_someone_elses_notification_cannot_be_opened(): void
    {
        $other = $this->member('contributor');
        $this->notified($other);
        $notification = $other->notifications()->sole();

        $this->actingAs($this->member('contributor'))->get("/notifications/{$notification->id}")->assertNotFound();
        $this->get('/notifications/pas-un-uuid')->assertNotFound();
        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_mark_all_as_read_touches_only_my_notifications(): void
    {
        $me = $this->member('contributor');
        $other = $this->member('contributor');
        $this->notified($me, 2);
        $this->notified($other);

        $this->actingAs($me)->from('/notifications')->post('/notifications/read-all')
            ->assertRedirect('/notifications')
            ->assertSessionHas('success', __('notifications.flash.all_read'));

        $this->assertSame(0, $me->unreadNotifications()->count());
        $this->assertSame(1, $other->unreadNotifications()->count());
    }

    public function test_notifications_are_part_of_the_data_export(): void
    {
        $me = $this->member('contributor');
        $me->notify(new ContributionRejected(4, null, 'Doublon.'));

        $export = app(AccountDataExport::class)->for($me->fresh());

        $this->assertCount(1, $export['notifications']);
        $this->assertSame('contribution_rejected', $export['notifications'][0]['kind']);
        $this->assertSame(4, $export['notifications'][0]['contribution_id']);
        $this->assertSame('Doublon.', $export['notifications'][0]['reason']);
        $this->assertNull($export['notifications'][0]['read_at']);
    }

    public function test_notifications_are_deleted_with_the_account(): void
    {
        $me = $this->member('contributor');
        $this->notified($me, 2);
        $other = $this->member('contributor');
        $this->notified($other);

        $this->actingAs($me)->delete('/profile', ['password' => 'password'])->assertRedirect();

        $this->assertModelMissing($me);
        $this->assertSame(1, DatabaseNotification::count(), 'seules celles de l’autre membre restent');
    }

    public function test_notifications_read_more_than_twelve_months_ago_are_pruned(): void
    {
        $me = $this->member('contributor');
        $this->notified($me, 3);
        [$old, $recent, $unread] = $me->notifications()->reorder()->orderBy('created_at')->get()->all();
        $old->forceFill(['read_at' => now()->subMonths(13), 'created_at' => now()->subMonths(14)])->save();
        $recent->forceFill(['read_at' => now()->subMonths(11)])->save();
        $unread->forceFill(['created_at' => now()->subYears(3)])->save();

        $this->artisan('model:prune', ['--model' => [DatabaseNotification::class]])->assertSuccessful();

        $this->assertModelMissing($old);
        $this->assertModelExists($recent);
        $this->assertModelExists($unread);
    }

    public function test_the_purge_is_scheduled_for_production_only(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($e) => $e->description === 'vff:prune-notifications');

        $this->assertNotNull($event);
        $this->assertSame(['production'], $event->environments);
        $this->assertFalse($event->runsInEnvironment('local'));
    }
}

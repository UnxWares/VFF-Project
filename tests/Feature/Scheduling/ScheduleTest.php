<?php

namespace Tests\Feature\Scheduling;

use App\Support\Scheduling\SchedulerStatus;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/** Planificateur de production (routes/console.php, docs/PLANIFICATION.md) : tâches, heures, garde-fous, suivi. */
class ScheduleTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{0: string, 1: string, 2: bool}> nom => [expression, commande, production seulement] */
    public static function tasks(): array
    {
        return [
            'vff:refresh-current-era' => ['30 4-23 * * *', 'vff:refresh-current-era', false],
            'vff:prune-inactive-accounts' => ['15 5 * * *', 'vff:prune-inactive-accounts', true],
            'vff:remind-expiring-api-tokens' => ['30 5 * * *', 'vff:remind-expiring-api-tokens', true],
            'vff:prune-audit-events' => ['45 5 * * *', 'model:prune', true],
            'vff:prune-notifications' => ['50 5 * * *', 'model:prune', true],
            'vff:prune-error-reports' => ['55 5 * * *', 'model:prune', true],
            'vff:commons-cache-prune' => ['20 * * * *', 'vff:commons-cache-prune', true],
            'vff:commons-allowlist' => ['*/10 * * * *', 'vff:commons-allowlist --refresh', true],
            'vff:refresh-stale-figures' => ['*/10 * * * *', 'vff:refresh-stale-figures', true],
            'sanctum:prune-expired' => ['5 4 * * *', 'sanctum:prune-expired --hours=24', true],
            'auth:clear-resets' => ['10 4 * * *', 'auth:clear-resets', true],
            'queue:prune-failed' => ['15 4 * * *', 'queue:prune-failed --hours=720', true],
        ];
    }

    private function event(string $name): Event
    {
        $found = collect(app(Schedule::class)->events())->first(fn (Event $e): bool => $e->description === $name);
        $this->assertInstanceOf(Event::class, $found, "Tâche planifiée absente : {$name}");

        return $found;
    }

    #[DataProvider('tasks')]
    public function test_each_task_is_scheduled_at_its_time(string $expression, string $command, bool $productionOnly): void
    {
        $name = array_search([$expression, $command, $productionOnly], self::tasks(), true);
        $event = $this->event((string) $name);

        $this->assertSame($expression, $event->expression);
        $this->assertStringContainsString($command, (string) $event->command);
        $this->assertSame($productionOnly ? ['production'] : [], $this->environmentsOf($event));
    }

    public function test_heartbeat_runs_every_five_minutes_on_every_server(): void
    {
        $event = $this->event('vff:scheduler-heartbeat');

        $this->assertInstanceOf(CallbackEvent::class, $event);
        $this->assertSame('*/5 * * * *', $event->expression);
        $this->assertFalse($event->onOneServer);
    }

    public function test_command_tasks_are_one_server_logged_and_every_task_has_a_name(): void
    {
        foreach (app(Schedule::class)->events() as $event) {
            $this->assertNotEmpty($event->description, 'Toute tâche planifiée porte un nom.');
            if ($event instanceof CallbackEvent) {
                continue;
            }
            $this->assertTrue($event->onOneServer, "{$event->description} : onOneServer");
            $this->assertSame(storage_path('logs/schedule.log'), $event->output, "{$event->description} : journal");
            $this->assertTrue($event->shouldAppendOutput);
        }
    }

    public function test_the_daily_refresh_is_not_restricted_to_production_and_never_overlaps(): void
    {
        $event = $this->event('vff:refresh-current-era');

        $this->assertTrue($event->withoutOverlapping);
        $this->assertSame('Europe/Paris', $event->timezone);
        $this->assertSame(180, $event->expiresAt);
    }

    public function test_the_lock_prevents_two_passes(): void
    {
        $event = $this->event('vff:refresh-current-era');
        Cache::flush();

        $this->assertTrue($event->filtersPass($this->app), 'Premier passage : libre.');
        $this->assertTrue($event->mutex->create($event));
        $this->assertFalse($event->filtersPass($this->app), 'Deuxième passage pendant le premier : refusé.');
        $this->assertFalse($event->mutex->create($event));

        $event->mutex->forget($event);
        $this->assertTrue($event->filtersPass($this->app), 'Verrou libéré : libre.');
    }

    public function test_a_failed_command_is_reported_and_logged(): void
    {
        Exceptions::fake();
        Log::shouldReceive('error')->once();
        $event = $this->event('vff:refresh-current-era');

        // Aucune ère courante : la commande sort en échec, sans rien écrire.
        $this->artisan('vff:refresh-current-era', ['--state-file' => sys_get_temp_dir().'/vff-absent-'.getmypid().'.json'])
            ->expectsOutputToContain('Aucune ère courante')->assertFailed();

        $event->finish($this->app, 1);

        Exceptions::assertReported(fn (RuntimeException $e): bool => str_contains($e->getMessage(), 'vff:refresh-current-era'));
    }

    public function test_a_successful_command_reports_nothing(): void
    {
        Exceptions::fake();
        $event = $this->event('vff:refresh-current-era');

        $event->finish($this->app, 0);

        Exceptions::assertNothingReported();
    }

    public function test_heartbeat_age_and_refresh_lag(): void
    {
        $this->travelTo('2026-11-22 10:00:00');
        $this->assertNull(SchedulerStatus::beatAgeMinutes());

        SchedulerStatus::beat();
        $this->travel(7)->minutes();
        $this->assertSame(7, SchedulerStatus::beatAgeMinutes());

        $state = sys_get_temp_dir().'/vff-state-'.getmypid().'.json';
        $this->assertNull(SchedulerStatus::refreshLagDays($state));
        file_put_contents($state, json_encode(['as_of' => '2026-11-20']));
        $this->assertSame(2, SchedulerStatus::refreshLagDays($state));
        file_put_contents($state, json_encode(['as_of' => '2026-11-22']));
        $this->assertSame(0, SchedulerStatus::refreshLagDays($state));
        @unlink($state);
    }

    public function test_health_flags_a_missing_scheduler_only_when_it_is_expected(): void
    {
        config(['monitoring.health_token' => 'jeton', 'monitoring.scheduler_expected' => false]);
        $this->withToken('jeton')->getJson('/up/details')->assertOk()->assertJsonPath('checks.scheduler.expected', false)
            ->assertJsonPath('checks.scheduler.status', 'ok');

        config(['monitoring.scheduler_expected' => true]);
        $this->withToken('jeton')->getJson('/up/details')->assertOk()->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.scheduler.status', 'degraded');

        SchedulerStatus::beat();
        $this->withToken('jeton')->getJson('/up/details')->assertJsonPath('checks.scheduler.beat_age_minutes', 0);
    }

    /** @return list<string> */
    private function environmentsOf(Event $event): array
    {
        $property = new \ReflectionProperty(Event::class, 'environments');

        return $property->getValue($event);
    }
}

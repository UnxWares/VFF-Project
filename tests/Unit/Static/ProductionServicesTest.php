<?php

namespace Tests\Unit\Static;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Kit de déploiement des processus de production (deploy/systemd/production, docs/DEPLOIEMENT-SERVICES.md) :
 * Reverb, travailleur de file et planificateur tournent sous un utilisateur dédié, durcis, bornés en mémoire et
 * relancés ; les commandes sont celles que le code attend.
 */
class ProductionServicesTest extends TestCase
{
    private static function root(): string
    {
        return dirname(__DIR__, 3);
    }

    private static function unit(string $name): string
    {
        return (string) file_get_contents(self::root().'/deploy/systemd/production/'.$name);
    }

    /** @return array<string, array{string}> */
    public static function services(): array
    {
        return ['reverb' => ['vff-reverb.service'], 'queue' => ['vff-queue.service'], 'schedule' => ['vff-schedule.service']];
    }

    #[DataProvider('services')]
    public function test_each_service_is_dedicated_user_hardened_and_bounded(string $name): void
    {
        $unit = self::unit($name);

        $this->assertMatchesRegularExpression('/^User=vff$/m', $unit);
        $this->assertDoesNotMatchRegularExpression('/^User=root$/m', $unit);
        foreach (['NoNewPrivileges=true', 'ProtectSystem=strict', 'ProtectHome=true', 'PrivateTmp=true', 'ReadWritePaths='] as $directive) {
            $this->assertStringContainsString($directive, $unit);
        }
        $this->assertMatchesRegularExpression('/^MemoryMax=\d+[MG]$/m', $unit);
        $this->assertMatchesRegularExpression('/^WorkingDirectory=\/srv\/vff$/m', $unit);
        $this->assertStringContainsString('SyslogIdentifier=vff-', $unit);
        $this->assertStringContainsString('ExecStart=/usr/bin/php artisan ', $unit);
    }

    public function test_long_running_services_restart_and_the_scheduler_is_oneshot(): void
    {
        foreach (['vff-reverb.service', 'vff-queue.service'] as $name) {
            $this->assertMatchesRegularExpression('/^Restart=always$/m', self::unit($name));
            $this->assertStringContainsString('WantedBy=multi-user.target', self::unit($name));
        }
        $this->assertStringContainsString('Type=oneshot', self::unit('vff-schedule.service'));
    }

    public function test_reverb_uses_its_command_reloads_cleanly_and_allows_enough_connections(): void
    {
        $unit = self::unit('vff-reverb.service');

        $this->assertStringContainsString('artisan reverb:start', $unit);
        $this->assertStringContainsString('ExecReload=/usr/bin/php artisan reverb:restart', $unit);
        $this->assertMatchesRegularExpression('/^LimitNOFILE=(\d+)$/m', $unit);
        preg_match('/^LimitNOFILE=(\d+)$/m', $unit, $m);
        $this->assertGreaterThan(1000, (int) $m[1], 'plus que REVERB_APP_MAX_CONNECTIONS (1000 par défaut)');
    }

    public function test_the_queue_worker_uses_valkey_and_a_timeout_below_retry_after(): void
    {
        $unit = self::unit('vff-queue.service');

        $this->assertStringContainsString('artisan queue:work redis', $unit);
        $this->assertStringContainsString('--max-time=', $unit);
        preg_match('/--timeout=(\d+)/', $unit, $t);
        preg_match('/^TimeoutStopSec=(\d+)$/m', $unit, $stop);
        $this->assertNotEmpty($t);
        $this->assertGreaterThanOrEqual((int) $t[1], (int) $stop[1], 'le travail en cours a le temps de finir à l\'arrêt');
        $this->assertStringContainsString("'retry_after' => 90", (string) file_get_contents(self::root().'/config/queue.php'));
        $this->assertLessThan(90, (int) $t[1]);
    }

    public function test_the_timer_fires_every_minute_the_service_it_names(): void
    {
        $timer = self::unit('vff-schedule.timer');

        $this->assertStringContainsString('OnCalendar=*-*-* *:*:00', $timer);
        $this->assertStringContainsString('Unit=vff-schedule.service', $timer);
        $this->assertStringContainsString('WantedBy=timers.target', $timer);
    }

    public function test_the_target_groups_the_three_processes(): void
    {
        $target = self::unit('vff.target');

        foreach (['vff-reverb.service', 'vff-queue.service', 'vff-schedule.timer'] as $unit) {
            $this->assertStringContainsString($unit, $target);
        }
    }

    public function test_the_verification_script_checks_health_websocket_queue_and_scheduler(): void
    {
        $path = self::root().'/deploy/bin/verify-production.sh';
        $script = (string) file_get_contents($path);

        $this->assertTrue(is_executable($path));
        foreach (['/up/details', 'Upgrade: websocket', 'vff-queue.service', 'vff-schedule', 'scheduler', '"101"'] as $needle) {
            $this->assertStringContainsString($needle, $script);
        }
        $this->assertStringNotContainsString('rm ', $script);
    }

    public function test_the_nginx_proxy_targets_the_port_reverb_listens_on(): void
    {
        $this->assertStringContainsString('proxy_pass http://127.0.0.1:8080;', (string) file_get_contents(self::root().'/deploy/nginx/snippets/vff-reverb.conf'));
        $this->assertStringContainsString('REVERB_SERVER_PORT=8080', (string) file_get_contents(self::root().'/.env.example'));
    }
}

<?php

namespace Tests\Feature\Account;

use Tests\TestCase;

/**
 * « Journaux techniques […] conservés 12 mois au plus » (politique de confidentialité) : le canal
 * quotidien ne garde jamais plus de 365 fichiers, même si LOG_DAILY_DAYS demande davantage, et
 * c'est le canal par défaut de la pile quand LOG_STACK n'est pas défini.
 */
class LogRetentionTest extends TestCase
{
    /** @return array<string, mixed> */
    private function loggingConfig(): array
    {
        return require config_path('logging.php');
    }

    public function test_daily_channel_keeps_twelve_months_at_most(): void
    {
        $days = $this->loggingConfig()['channels']['daily']['days'];

        $this->assertSame('daily', $this->loggingConfig()['channels']['daily']['driver']);
        $this->assertLessThanOrEqual(365, $days);
    }

    public function test_a_longer_setting_is_capped(): void
    {
        putenv('LOG_DAILY_DAYS=1000');
        $_ENV['LOG_DAILY_DAYS'] = '1000';
        $_SERVER['LOG_DAILY_DAYS'] = '1000';

        try {
            $this->assertSame(365, $this->loggingConfig()['channels']['daily']['days']);
        } finally {
            putenv('LOG_DAILY_DAYS');
            unset($_ENV['LOG_DAILY_DAYS'], $_SERVER['LOG_DAILY_DAYS']);
        }
    }

    public function test_stack_defaults_to_the_daily_channel(): void
    {
        $previous = getenv('LOG_STACK');
        putenv('LOG_STACK');
        unset($_ENV['LOG_STACK'], $_SERVER['LOG_STACK']);

        try {
            $this->assertSame(['daily'], $this->loggingConfig()['channels']['stack']['channels']);
        } finally {
            if ($previous !== false) {
                putenv("LOG_STACK={$previous}");
                $_ENV['LOG_STACK'] = $_SERVER['LOG_STACK'] = $previous;
            }
        }
    }

    public function test_example_environment_uses_the_daily_channel(): void
    {
        $example = (string) file_get_contents(base_path('.env.example'));

        $this->assertMatchesRegularExpression('/^LOG_STACK=daily$/m', $example);
        $this->assertMatchesRegularExpression('/^LOG_DAILY_DAYS=(\d+)$/m', $example);
        preg_match('/^LOG_DAILY_DAYS=(\d+)$/m', $example, $m);
        $this->assertLessThanOrEqual(365, (int) $m[1]);
    }
}

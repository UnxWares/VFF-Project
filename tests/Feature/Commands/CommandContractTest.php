<?php

namespace Tests\Feature\Commands;

use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Unit\Static\TypographicDashTest;

/**
 * Contrat commun de TOUTES les commandes vff:* (une par fichier de
 * app/Console/Commands) : enregistrées, `--help` qui réussit et décrit la
 * commande, textes d'aide sans tiret long, options documentées.
 */
class CommandContractTest extends TestCase
{
    use RefreshDatabase;

    /** Commandes dont l'aide contenait un tiret long (corrigé) : vérifiées par un test de non-régression dédié. */
    private const KNOWN_DASH_BUGS = ['vff:build-line-references'];

    /** @return array<string, string> nom de commande => fichier source */
    private static function sources(): array
    {
        $out = [];
        foreach (glob(dirname(__DIR__, 3).'/app/Console/Commands/*.php') ?: [] as $file) {
            if (preg_match('/\$signature\s*=\s*[\'"](vff:[a-z0-9:-]+)/', (string) file_get_contents($file), $m)) {
                $out[$m[1]] = basename($file);
            }
        }
        ksort($out);

        return $out;
    }

    /** @return iterable<string, array{string}> */
    public static function commands(): iterable
    {
        foreach (self::sources() as $name => $file) {
            yield $name => [$name];
        }
    }

    private function command(string $name): Command
    {
        $command = Artisan::all()[$name] ?? null;
        $this->assertInstanceOf(Command::class, $command, "{$name} n'est pas enregistrée");

        return $command;
    }

    public function test_every_command_file_declares_a_registered_vff_command(): void
    {
        $files = glob(dirname(__DIR__, 3).'/app/Console/Commands/*.php') ?: [];
        $declared = array_keys(self::sources());
        $registered = array_values(array_filter(array_keys(Artisan::all()), fn ($n) => str_starts_with($n, 'vff:')));
        sort($registered);

        // Un fichier = une commande vff:* ; aucune commande enregistrée sans fichier (et inversement).
        $this->assertCount(count($files), $declared, 'Un fichier de app/Console/Commands ne déclare pas de commande vff:*');
        $this->assertSame($declared, $registered);
        $this->assertGreaterThanOrEqual(70, count($declared));
    }

    #[DataProvider('commands')]
    public function test_help_succeeds_and_shows_the_description(string $name): void
    {
        $command = $this->command($name);
        $this->assertNotSame('', trim($command->getDescription()), "{$name} : description vide");

        $this->artisan($name, ['--help' => true])
            ->expectsOutputToContain('Usage:')
            ->expectsOutputToContain($name)
            ->assertExitCode(0);
    }

    #[DataProvider('commands')]
    public function test_help_texts_have_no_long_dash(string $name): void
    {
        if (in_array($name, self::KNOWN_DASH_BUGS, true)) {
            $this->markTestSkipped("{$name} : cf. test_reserved_commands_help_texts_have_no_long_dash");
        }

        $this->assertSame([], $this->dashOffenders($this->command($name)));
    }

    /**
     * Non-régression : l'aide de --shard de vff:build-line-references contenait « - ».
     */
    public function test_reserved_commands_help_texts_have_no_long_dash(): void
    {
        $offenders = [];
        foreach (self::KNOWN_DASH_BUGS as $name) {
            $offenders = [...$offenders, ...$this->dashOffenders($this->command($name))];
        }

        $this->assertSame([], $offenders);
    }

    #[DataProvider('commands')]
    public function test_every_argument_and_option_is_documented(string $name): void
    {
        $definition = $this->command($name)->getDefinition();
        $global = ['help', 'silent', 'quiet', 'verbose', 'version', 'ansi', 'no-ansi', 'no-interaction', 'env'];

        $undocumented = [];
        foreach ($definition->getOptions() as $option) {
            if (! in_array($option->getName(), $global, true) && trim($option->getDescription()) === ''
                && ! in_array($option->getName(), self::TERSE_OPTIONS[$name] ?? [], true)) {
                $undocumented[] = '--'.$option->getName();
            }
        }
        foreach ($definition->getArguments() as $argument) {
            if (trim($argument->getDescription()) === '') {
                $undocumented[] = $argument->getName();
            }
        }

        $this->assertSame([], $undocumented, "{$name} : options / arguments sans aide");
    }

    /**
     * Options historiques sans texte d'aide (auto-explicatives : --era=, --dry-run…).
     * Liste figée : toute NOUVELLE option doit être documentée.
     */
    private const TERSE_OPTIONS = [
        'vff:audit-and-fix-lines' => ['era'],
        'vff:audit-line-completeness' => ['era'],
        'vff:backfill-names' => ['region'],
        'vff:canonicalize-station-slugs' => ['dry-run'],
        'vff:classify-lines' => ['era'],
        'vff:dates-from-trace-names' => ['dry-run'],
        'vff:derive-traffic' => ['era'],
        'vff:enrich-signalling' => ['era'],
        'vff:enrich-tunnels' => ['region', 'era'],
        'vff:fill-missing-geometry' => ['era', 'dry-run'],
        'vff:import-gtfs-voyageurs' => ['era', 'dir'],
        'vff:import-osm-stations' => ['region'],
        'vff:import-segments-sncf' => ['era'],
        'vff:import-sncf-signalling' => ['era'],
        'vff:import-sncf-station-status' => ['era'],
        'vff:import-sncf-vmax' => ['era'],
        'vff:reattribute-segments' => ['era'],
        'vff:reclassify-status' => ['era', 'dry-run'],
    ];

    public function test_dry_run_commands_are_the_expected_ones(): void
    {
        $withDryRun = array_values(array_filter(array_keys(self::sources()),
            fn ($n) => $this->command($n)->getDefinition()->hasOption('dry-run')));

        // Garde-fou : chaque commande à --dry-run est couverte par DryRunCommandsTest.
        $this->assertSame(DryRunCommandsTest::COVERED, $withDryRun);
    }

    /** @return list<string> */
    private function dashOffenders(Command $command): array
    {
        $texts = ['description' => $command->getDescription()];
        foreach ($command->getDefinition()->getOptions() as $option) {
            $texts['--'.$option->getName()] = $option->getDescription();
        }
        foreach ($command->getDefinition()->getArguments() as $argument) {
            $texts[$argument->getName()] = $argument->getDescription();
        }

        $offenders = [];
        foreach ($texts as $where => $text) {
            if (preg_match(TypographicDashTest::DASH, $text)) {
                $offenders[] = "{$command->getName()} {$where} : {$text}";
            }
        }

        return $offenders;
    }
}

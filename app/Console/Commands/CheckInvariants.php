<?php

namespace App\Console\Commands;

use App\Services\DataInvariants;
use Illuminate\Console\Command;

/**
 * Invariants des données réelles (carte décennale, gares, textes), en LECTURE
 * SEULE : uniquement des SELECT, dans une transaction READ ONLY annulée à la
 * fin. Détail des invariants : App\Services\DataInvariants.
 *
 *   php artisan vff:check-invariants
 *   php artisan vff:check-invariants --only=segments,stations.active_without_track
 *   php artisan vff:check-invariants --json=storage/app/reports/invariants.json
 *
 * Code de sortie : 1 si au moins un invariant est violé, 0 sinon.
 * Lancé en fin de vff:rebuild-history, sans bloquer la chaîne.
 */
class CheckInvariants extends Command
{
    protected $signature = 'vff:check-invariants
        {--only= : clés ou familles d\'invariants séparées par des virgules (ex. segments,texts.long_dash)}
        {--json= : écrit aussi le rapport dans ce fichier JSON}';

    protected $description = 'Vérifie les invariants des données (lecture seule) : nombre de cas et exemples par invariant violé.';

    public function handle(): int
    {
        $only = $this->option('only') ? array_values(array_filter(array_map('trim', explode(',', $this->option('only'))))) : null;
        if ($only) {
            $unknown = array_filter($only, fn ($o) => ! array_filter(array_keys(DataInvariants::INVARIANTS),
                fn ($k) => $k === $o || str_starts_with($k, rtrim($o, '.').'.')));
            if ($unknown) {
                $this->error('Invariant(s) inconnu(s) : '.implode(', ', $unknown).' (attendu : '.implode(', ', array_keys(DataInvariants::INVARIANTS)).')');

                return self::INVALID;
            }
        }

        $started = microtime(true);
        $this->info('Invariants des données (lecture seule)');
        $results = (new DataInvariants($only))->run(fn (array $r) => $this->render($r));
        $violated = array_values(array_filter($results, fn ($r) => $r['count'] > 0));

        $this->newLine();
        $summary = sprintf('%d invariant(s) violé(s) sur %d (%.1f s).', count($violated), count($results), microtime(true) - $started);
        $violated ? $this->error($summary) : $this->info($summary);

        if ($path = $this->option('json')) {
            $path = str_starts_with($path, '/') ? $path : base_path($path);
            @mkdir(dirname($path), 0775, true);
            file_put_contents($path, json_encode(['generated_at' => now()->toIso8601String(), 'invariants' => $results],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        return $violated ? self::FAILURE : self::SUCCESS;
    }

    /** @param  array{key: string, label: string, count: int, examples: list<string>, seconds: float, error?: string}  $r */
    private function render(array $r): void
    {
        if ($r['count'] === 0) {
            $this->line(sprintf('  <fg=green>✓</> %s <fg=gray>(%.1f s)</>', $r['key'], $r['seconds']));

            return;
        }
        $this->line(sprintf('  <fg=red>✗</> <options=bold>%s</> - %s : <fg=red>%s cas</> <fg=gray>(%.1f s)</>',
            $r['key'], $r['label'], number_format($r['count'], 0, ',', ' '), $r['seconds']));
        if (isset($r['error'])) {
            $this->line("      <fg=red>erreur SQL :</> {$r['error']}");
        }
        foreach ($r['examples'] as $example) {
            $this->line("      · {$example}");
        }
    }
}

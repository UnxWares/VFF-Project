<?php

namespace App\Console\Commands;

use App\Services\ConsistencyChecker;
use Illuminate\Console\Command;

/**
 * Contrôles de cohérence chiffrés des données VFF, par ère (décennie), avec
 * exemples. Détail des contrôles : App\Services\ConsistencyChecker.
 *
 *   php artisan vff:check-consistency                  # toutes les ères, tous les contrôles
 *   php artisan vff:check-consistency --era=1980       # contrôles par ère limités à 1980
 *   php artisan vff:check-consistency --only=gares
 *   php artisan vff:check-consistency --json=storage/app/consistency.json
 *   php artisan vff:check-consistency --fix            # corrige R1 et R2, journalisé
 *
 * --fix n'applique QUE les corrections déterministes des règles métier :
 *   R1 voyageurs seuls hors LGV → mixte (line_segments.traffic_type, lines.type) ;
 *   R2 gare plus active que ses lignes à l'ère → fermée / fret seul
 *      (station_eras, line_stops.effective_status, stations.status à l'ère courante).
 * Chaque modification est journalisée (avant / après / raison) dans
 * storage/logs/consistency/fix-<horodatage>.jsonl ; les contrôles sont relancés
 * après correction (colonnes « avant » / « après »).
 *
 * Code de sortie : 1 s'il reste des constats de priorité 1 (règle métier
 * violée ou erreur visible sur la carte), 0 sinon.
 */
class CheckConsistency extends Command
{
    protected $signature = 'vff:check-consistency
        {--era= : limite les contrôles par ère à cette année}
        {--only= : groupes séparés par des virgules (gares, portions, evenements, categories, completude)}
        {--fix : applique les corrections déterministes des règles R1 et R2 (journalisées)}
        {--json= : écrit le rapport complet (constats + exemples) dans ce fichier JSON}
        {--examples=3 : nombre d\'exemples affichés par constat}';

    protected $description = 'Contrôles de cohérence chiffrés par ère (gares vs lignes, trafic, événements, catégories) ; --fix corrige R1/R2.';

    public function handle(): int
    {
        ini_set('memory_limit', '1G');
        $year = $this->option('era') !== null ? (int) $this->option('era') : null;
        $groups = $this->option('only') ? array_values(array_filter(array_map('trim', explode(',', $this->option('only'))))) : null;
        if ($groups && ($unknown = array_diff($groups, ConsistencyChecker::GROUPS))) {
            $this->error('Groupe(s) inconnu(s) : '.implode(', ', $unknown).' (attendu : '.implode(', ', ConsistencyChecker::GROUPS).')');

            return self::INVALID;
        }

        $checker = new ConsistencyChecker($year, $groups);
        $started = microtime(true);
        $findings = $checker->run();
        $before = null;
        $fixes = null;

        if ($this->option('fix')) {
            $before = $findings;
            $fixes = $this->applyFixes($checker, $groups);
            $findings = $checker->run();
        }

        $this->render($findings, $before);
        foreach ($checker->notes() as $note) {
            $this->line("<fg=gray>  · {$note}</>");
        }
        if ($fixes) {
            $this->newLine();
            $this->info(sprintf('Corrections : R2 %d gare(s)/ère, R1 %d ligne(s) ou tronçon(s). Journal : %s',
                $fixes['R2'], $fixes['R1'], $fixes['log'] ?? 'aucune modification'));
        }
        $this->line(sprintf('<fg=gray>  Durée : %.1f s</>', microtime(true) - $started));

        if ($path = $this->option('json')) {
            $path = str_starts_with($path, '/') ? $path : base_path($path);
            @mkdir(dirname($path), 0775, true);
            file_put_contents($path, json_encode([
                'generated_at' => now()->toIso8601String(),
                'era' => $year,
                'groups' => $groups ?? ConsistencyChecker::GROUPS,
                'fixed' => (bool) $this->option('fix'),
                'fixes' => $fixes,
                'notes' => $checker->notes(),
                'findings' => $findings,
                'before_fix' => $before,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $this->info("Rapport JSON : {$path}");
        }

        $p1 = array_sum(array_map(fn ($f) => $f['priority'] === 1 ? $f['count'] : 0, $findings));

        return $p1 > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  list<string>|null  $groups
     * @return array{R1: int, R2: int, log: ?string}
     */
    private function applyFixes(ConsistencyChecker $checker, ?array $groups): array
    {
        $dir = storage_path('logs/consistency');
        @mkdir($dir, 0775, true);
        $file = $dir.'/fix-'.now()->format('Ymd-His').'.jsonl';
        $written = 0;
        $log = function (array $entry) use ($file, &$written) {
            file_put_contents($file, json_encode(['at' => now()->toIso8601String()] + $entry, JSON_UNESCAPED_UNICODE)."\n", FILE_APPEND);
            $written++;
        };

        $r1 = ($groups === null || in_array('portions', $groups, true)) ? $checker->fixPassengerRule1($log) : 0;
        // R1 ne change pas la desserte (voyageurs et mixte = même niveau), mais
        // on repart d'un cache propre par prudence.
        $checker->forgetLevels();
        $r2 = ($groups === null || in_array('gares', $groups, true)) ? $checker->fixStationsRule2($log) : 0;

        return ['R1' => $r1, 'R2' => $r2, 'log' => $written ? $file : null];
    }

    /**
     * @param  list<array<string, mixed>>  $findings
     * @param  list<array<string, mixed>>|null  $before
     */
    private function render(array $findings, ?array $before): void
    {
        $prev = $before ? array_column($before, 'count', 'key') : null;
        $rows = [];
        foreach ($findings as $f) {
            $row = [
                'P'.$f['priority'],
                $f['group'],
                $f['key'].($f['rule'] ? " ({$f['rule']})" : ''),
                $f['count'],
                $f['km'] ?? '',
                $f['fixable'] ? 'oui' : '',
            ];
            if ($prev !== null) {
                array_splice($row, 3, 0, [$prev[$f['key']] ?? '']);
            }
            $rows[] = $row;
        }
        $headers = ['Prio', 'Groupe', 'Contrôle', 'Nb', 'km', '--fix'];
        if ($prev !== null) {
            array_splice($headers, 3, 1, ['Avant', 'Après']);
        }
        $this->table($headers, $rows);

        foreach ($findings as $f) {
            if (! empty($f['table'])) {
                $this->newLine();
                $this->line('<options=bold>km en service par ère et par catégorie</> (réf. = ordre de grandeur historique, cf. ConsistencyChecker::HISTORICAL_KM)');
                $fmt = fn ($row, $net) => number_format($row[$net] ?? 0, 0, ',', ' ')
                    .(isset($row[$net.'_ref']) ? ' / '.(is_numeric($row[$net.'_ref']) ? number_format($row[$net.'_ref'], 0, ',', ' ') : $row[$net.'_ref']) : '')
                    .(isset($row[$net.'_ratio']) ? sprintf(' (%d %%)', round($row[$net.'_ratio'] * 100)) : '');
                $this->table(['Ère', 'principal / réf.', 'secondaire / réf.', 'militaire', 'particulier'],
                    array_map(fn ($row) => [$row['era'], $fmt($row, 'principal'), $fmt($row, 'secondaire'), $fmt($row, 'militaire'), $fmt($row, 'particulier')], $f['table']));
            }
        }

        $n = max(0, (int) $this->option('examples'));
        foreach ($findings as $f) {
            if ($f['count'] === 0) {
                continue;
            }
            $this->newLine();
            $this->line(sprintf('<options=bold>[P%d] %s</> : %d %s%s', $f['priority'], $f['label'], $f['count'], $f['unit'],
                $f['km'] !== null ? " ({$f['km']} km)" : ''));
            if ($f['by_era']) {
                $this->line('   par ère : '.implode(', ', array_map(fn ($y, $c) => "{$y} : {$c}", array_keys($f['by_era']), $f['by_era'])));
            }
            if ($f['detail']) {
                $this->line("   {$f['detail']}");
            }
            foreach (array_slice($f['examples'], 0, $n) as $ex) {
                $this->line('   - '.$this->describe($ex));
            }
        }
    }

    private function describe(array $ex): string
    {
        $parts = [];
        foreach ($ex as $k => $v) {
            if ($v === null || $v === '' || in_array($k, ['slug'], true)) {
                continue;
            }
            $parts[] = is_bool($v) ? $k.'='.($v ? 'oui' : 'non') : "{$k}={$v}";
        }

        return implode(' ; ', $parts);
    }
}

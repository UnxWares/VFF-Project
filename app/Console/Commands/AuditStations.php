<?php

namespace App\Console\Commands;

use App\Services\StationAudit\PlacementGrade;
use App\Services\StationAudit\StationAuditor;
use App\Services\StationAudit\StationScope;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Audit des gares (docs/AUDIT-GARES.md), passe en lecture seule sur les tables du moteur : note de
 * placement (positions des sources comparées, écart à la voie), notes des dates et des services,
 * contrôles croisés. Résultat dans `station_audits` (une ligne par gare, remplacée à chaque passe) ;
 * --dry-run n'écrit rien et affiche le bilan.
 *
 *   php artisan vff:audit-stations --line=405000 --line=371000
 *   php artisan vff:audit-stations --shard=0/4            (un quart des gares)
 *   php artisan vff:audit-stations --station=argentan --dry-run -v
 *
 * Seuils et définitions : App\Services\StationAudit\PlacementGrader et DateAuditor.
 */
class AuditStations extends Command
{
    protected $signature = 'vff:audit-stations
        {--station=* : Gare (slug ou identifiant), plusieurs possibles}
        {--line=* : Ligne (code à six chiffres, slug ou identifiant) : toutes ses gares}
        {--shard= : Morceau i/n des gares (identifiant modulo n égal à i)}
        {--no-bdtopo : Sans les gares de la BD TOPO (aucun appel à l\'IGN)}
        {--offline : BD TOPO lue seulement dans le cache local}
        {--dry-run : Calcule sans rien écrire dans station_audits}';

    protected $description = 'Audit des gares : note de placement, notes des dates et des services, contrôles croisés → station_audits (lecture seule sur le moteur)';

    public function handle(StationAuditor $auditor): int
    {
        try {
            $ids = StationScope::resolve(
                array_map('strval', (array) $this->option('station')),
                array_map('strval', (array) $this->option('line')),
                $this->option('shard') !== null ? (string) $this->option('shard') : null,
            );
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::INVALID;
        }
        if ($ids === []) {
            $this->warn('Aucune gare visée.');

            return self::SUCCESS;
        }

        $started = microtime(true);
        $bar = $this->output->createProgressBar(count($ids));
        $rows = $auditor->audit($ids, ! $this->option('no-bdtopo'), (bool) $this->option('offline'), ! $this->option('dry-run'),
            fn (int $n) => $bar->advance($n));
        $bar->finish();
        $this->newLine();

        $this->summary($rows);
        if ($this->output->isVerbose()) {
            foreach ($rows as $r) {
                $this->line(sprintf('  #%d %s · %s · voie %s m · ouverture %s · fermeture %s%s',
                    $r['station_id'], $r['placement_grade'], $r['nearest_agreeing_m'] !== null ? "plus proche {$r['nearest_agreeing_m']} m" : 'sans autre source',
                    $r['track_offset_m'] ?? '?', $r['opening_grade'], $r['closure_grade'],
                    $r['checks'] !== [] ? ' · '.implode(', ', array_column($r['checks'], 'code')) : ''));
            }
        }
        $this->line(sprintf('%d gare(s) en %.1f s', count($rows), microtime(true) - $started));
        if ($this->option('dry-run')) {
            $this->warn('--dry-run : rien n\'est écrit dans station_audits');
        }

        return self::SUCCESS;
    }

    /** @param list<array<string, mixed>> $rows */
    private function summary(array $rows): void
    {
        $count = fn (string $key, string $value): int => count(array_filter($rows, fn (array $r): bool => $r[$key] === $value));
        $this->table(['Placement', 'Gares'], array_map(fn (string $g): array => [$g, $count('placement_grade', $g)], PlacementGrade::values()));
        $grades = ['sourced', 'estimated', 'unknown', 'not_applicable'];
        $this->table(['Date', ...$grades], [
            ['ouverture', ...array_map(fn (string $g): int => $count('opening_grade', $g), $grades)],
            ['fermeture', ...array_map(fn (string $g): int => $count('closure_grade', $g), $grades)],
        ]);
        $checks = [];
        foreach ($rows as $r) {
            foreach ($r['checks'] as $c) {
                $checks[$c['code']] = ($checks[$c['code']] ?? 0) + 1;
            }
        }
        arsort($checks);
        if ($checks !== []) {
            $this->table(['Contrôle en échec', 'Gares'], array_map(fn ($k, $v): array => [$k, $v], array_keys($checks), $checks));
        }
    }
}

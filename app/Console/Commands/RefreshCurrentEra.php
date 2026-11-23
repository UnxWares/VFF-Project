<?php

namespace App\Console\Commands;

use App\Events\CurrentEraRefreshed;
use App\Services\NetworkChronology;
use App\Services\NetworkFigures;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Recalcul QUOTIDIEN de l'ère courante (« 2030 » = état du réseau au jour même).
 *
 * L'ère courante est calculée à la date du jour (EraTimeline::stateAt(..., $asOf)) : un
 * événement daté de demain (fermeture, ouverture, réouverture) doit apparaître demain sur
 * la carte, sans reconstruction complète. Cette commande :
 *   1. lit la dernière date traitée (fichier d'état JSON, --state-file) ;
 *   2. cherche les lignes dont un événement (line_events.event_date) tombe dans
 *      ]dernière date traitée ; aujourd'hui], et les gares dont l'ouverture, la fermeture
 *      (stations.opened_at / closed_at) ou un événement (station_events) y tombe ;
 *   3. si rien n'a changé : enregistre la date et sort, sans rien reconstruire ;
 *   4. sinon, sous le verrou commun des reconstructions de tronçons
 *      (storage/app/reports/agents/.era-segments.lock, flock non bloquant) : reconstruit
 *      l'ère courante de ces lignes seulement (vff:build-era-segments --line --era),
 *      leur électrification (vff:enrich-era-segments --line), leurs vitesses SNCF (vff:import-sncf-vmax
 *      --cache-only), puis, pour l'ère courante,
 *      le trafic officiel (vff:classify-lines), les corrections R1/R2
 *      (vff:check-consistency --fix, non bloquant), les gares (vff:build-station-eras),
 *      les voies partagées (vff:build-shared-track) et le graphe (vff:build-network-graph) ;
 *   5. annonce le recalcul aux abonnés webhook (CurrentEraRefreshed, `current_era.refreshed`).
 *
 * Reconstruction complète en cours (verrou pris, processus vff:rebuild-history ou
 * vff:build-era-segments visible, verrou consultatif ou écriture des tronçons dans une
 * autre session PostgreSQL) : la commande REPORTE, sort en succès et ne touche pas au
 * fichier d'état ; le passage suivant reprend toute la fenêtre manquée.
 *
 * Changement d'année depuis le dernier passage : l'année de calcul de l'ère courante
 * change pour TOUTES les lignes (min(ère, année en cours), fermetures récentes) ; la
 * commande le signale et une reconstruction complète est recommandée.
 *
 *   php artisan vff:refresh-current-era
 *   php artisan vff:refresh-current-era --dry-run
 *   php artisan vff:refresh-current-era --since=2026-10-01
 */
class RefreshCurrentEra extends Command
{
    protected $signature = 'vff:refresh-current-era
        {--dry-run : liste les lignes et gares à recalculer, sans rien écrire (ni fichier d\'état)}
        {--since= : dernière date déjà traitée (AAAA-MM-JJ), à la place du fichier d\'état}
        {--state-file= : fichier d\'état (défaut : storage/app/state/current-era-refresh.json)}
        {--lock-file= : verrou commun des reconstructions (défaut : storage/app/reports/agents/.era-segments.lock)}';

    protected $description = 'Recalcule chaque jour l\'ère courante des seules lignes et gares dont un événement est daté depuis le dernier passage.';

    /** Écritures du moteur dans une autre session : reconstruction en cours. */
    private const ENGINE_WRITES = '^\s*(insert\s+into|delete\s+from|update)\s+(line_segments|line_segment_covers|station_eras|station_edges)\M';

    /** Commandes longues dont la présence reporte le recalcul. */
    private const BLOCKING_COMMANDS = ['vff:rebuild-history', 'vff:build-era-segments', 'vff:enrich-era-segments', 'vff:build-line-references'];

    public function handle(): int
    {
        $era = DB::table('eras')->where('is_current', true)->first(['id', 'year']);
        if (! $era) {
            $this->error('Aucune ère courante (eras.is_current).');

            return self::FAILURE;
        }

        $today = now()->toDateString();
        $since = $this->option('since') ?: ($this->readState()['as_of'] ?? null);
        // Premier passage sans état : seuls les événements datés d'aujourd'hui.
        $since = $since ? Carbon::parse($since)->toDateString() : now()->subDay()->toDateString();
        if ($since >= $today) {
            $this->info("Ère courante déjà à jour au {$today}.");

            return self::SUCCESS;
        }

        $lineIds = $this->changedLines($since, $today);
        $stationIds = $this->changedStations($since, $today);
        $this->info(sprintf('Ère %d, événements du %s (exclu) au %s (inclus) : %d ligne(s), %d gare(s).',
            $era->year, $since, $today, count($lineIds), count($stationIds)));
        if (substr($since, 0, 4) !== substr($today, 0, 4)) {
            $this->warn('Changement d\'année depuis le dernier passage : l\'ère courante change pour toutes les lignes, '
                .'reconstruction complète recommandée (vff:rebuild-history --from=build-era-segments).');
        }

        if ($this->option('dry-run')) {
            if ($lineIds) {
                $this->line('  lignes : '.implode(', ', $lineIds));
            }
            if ($stationIds) {
                $this->line('  gares : '.implode(', ', $stationIds));
            }
            $this->line('[DRY-RUN] rien n\'est écrit.');

            return self::SUCCESS;
        }

        if (! $lineIds && ! $stationIds) {
            $this->writeState($today, [], []);
            $this->info('Rien à recalculer.');

            return self::SUCCESS;
        }

        $lock = $this->acquireLock();
        if ($lock === null) {
            return self::SUCCESS;
        }
        try {
            if ($reason = $this->rebuildInProgress()) {
                $this->warn("Recalcul reporté : {$reason}.");

                return self::SUCCESS;
            }
            if (! $this->refresh((int) $era->year, $lineIds, $stationIds)) {
                return self::FAILURE;
            }
            $this->writeState($today, $lineIds, $stationIds);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        Cache::forget('home_stats');
        // Chiffres du réseau (/network, /api/v1/stats/network) : la ligne de l'ère courante vient de changer.
        app(NetworkFigures::class)->refresh();
        // Chronologie (/chronology, /api/v1/chronology, et « Ce jour-là » qui la lit) : elle s'arrête au jour du calcul.
        app(NetworkChronology::class)->refresh();
        // Webhooks `current_era.refreshed` (abonnés de l'API).
        CurrentEraRefreshed::dispatch((int) $era->year, $today, count($lineIds), count($stationIds));
        $this->info('✓ Ère courante recalculée au '.$today.'.');

        return self::SUCCESS;
    }

    /** @return list<int> lignes (avec tracé de référence) dont un événement est daté dans ]since ; today] */
    private function changedLines(string $since, string $today): array
    {
        return DB::table('line_events')->join('line_reference_geometries as r', 'r.line_id', '=', 'line_events.line_id')
            ->where('line_events.event_date', '>', $since)->where('line_events.event_date', '<=', $today)
            ->distinct()->orderBy('line_events.line_id')->pluck('line_events.line_id')->map(fn ($id) => (int) $id)->all();
    }

    /** @return list<int> gares ouvertes ou fermées (dates de la fiche ou événements) dans ]since ; today] */
    private function changedStations(string $since, string $today): array
    {
        $stations = DB::table('stations')
            ->where(fn ($q) => $q->where(fn ($w) => $w->where('closed_at', '>', $since)->where('closed_at', '<=', $today))
                ->orWhere(fn ($w) => $w->where('opened_at', '>', $since)->where('opened_at', '<=', $today)))
            ->pluck('id');
        $events = DB::table('station_events')->where('event_date', '>', $since)->where('event_date', '<=', $today)->pluck('station_id');

        return $stations->merge($events)->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();
    }

    /**
     * @param  list<int>  $lineIds
     * @param  list<int>  $stationIds
     */
    private function refresh(int $year, array $lineIds, array $stationIds): bool
    {
        $era = ['--era' => (string) $year];
        $steps = [];
        if ($lineIds) {
            // --no-enrich : l'électrification et les vitesses sont rejouées ici, étape par étape.
            $steps[] = ['vff:build-era-segments', ['--line' => implode(',', $lineIds), '--no-classify' => true, '--no-enrich' => true] + $era];
            foreach ($lineIds as $id) {
                $steps[] = ['vff:enrich-era-segments', ['--line' => (string) $id]];
            }
            // Vitesses SNCF (cache local seulement, jamais de réseau ; sans cache : rien, succès).
            $steps[] = ['vff:import-sncf-vmax', ['--line' => implode(',', $lineIds), '--cache-only' => true]];
            $steps[] = ['vff:classify-lines', $era];
            $steps[] = ['vff:check-consistency', ['--fix' => true] + $era];
        }
        $steps[] = ['vff:build-station-eras', $era];
        if ($lineIds) {
            $steps[] = ['vff:build-shared-track', $era];
        }
        $steps[] = ['vff:build-network-graph', $era];

        foreach ($steps as [$command, $args]) {
            $this->line("▶ {$command} ".collect($args)->map(fn ($v, $k) => $v === true ? $k : "{$k}={$v}")->implode(' '));
            $code = $this->call($command, $args);
            // check-consistency sort en 1 s'il reste des anomalies : signalées sans bloquer (comme vff:rebuild-history).
            if ($code !== self::SUCCESS && $command !== 'vff:check-consistency') {
                $this->error("✗ Échec à l'étape {$command} : fichier d'état inchangé, le prochain passage reprendra la même fenêtre.");

                return false;
            }
        }

        return true;
    }

    /** @return resource|null verrou exclusif pris, ou null (reconstruction en cours : reporté) */
    private function acquireLock()
    {
        $path = $this->option('lock-file') ?: storage_path('app/reports/agents/.era-segments.lock');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        $handle = fopen($path, 'c');
        if ($handle === false) {
            $this->error("Verrou inaccessible : {$path}");

            return null;
        }
        if (! flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            $this->warn("Recalcul reporté : verrou {$path} pris (reconstruction en cours).");

            return null;
        }

        return $handle;
    }

    /** Raison de reporter (reconstruction en cours hors verrou), ou null. */
    private function rebuildInProgress(): ?string
    {
        // Processus visibles (hôte, ou même conteneur) : vff:rebuild-history et ses étapes longues.
        foreach (glob('/proc/[0-9]*/cmdline') ?: [] as $file) {
            if ((int) basename(dirname($file)) === getmypid()) {
                continue;
            }
            $cmdline = str_replace("\0", ' ', (string) @file_get_contents($file));
            foreach (self::BLOCKING_COMMANDS as $command) {
                if (str_contains($cmdline, 'artisan') && str_contains($cmdline, $command)) {
                    return "processus {$command} en cours";
                }
            }
        }

        // Autres conteneurs (bin/php) : invisibles ici, mais visibles dans PostgreSQL.
        // Verrous consultatifs par ligne (build-era-segments, enrich-era-segments, build-line-references).
        if (DB::selectOne("SELECT EXISTS (SELECT 1 FROM pg_locks l JOIN pg_stat_activity a ON a.pid = l.pid
                WHERE l.locktype = 'advisory' AND l.pid <> pg_backend_pid() AND a.datname = current_database()) AS busy")->busy) {
            return 'verrou consultatif PostgreSQL tenu par une autre session (reconstruction de lignes)';
        }
        if (DB::selectOne("SELECT EXISTS (SELECT 1 FROM pg_stat_activity WHERE datname = current_database()
                AND pid <> pg_backend_pid() AND state <> 'idle' AND query ~* ?) AS busy", [self::ENGINE_WRITES])->busy) {
            return 'écriture des tronçons, gares ou graphe dans une autre session PostgreSQL';
        }

        return null;
    }

    private function statePath(): string
    {
        return $this->option('state-file') ?: storage_path('app/state/current-era-refresh.json');
    }

    /** @return array{as_of?: string} */
    private function readState(): array
    {
        $path = $this->statePath();
        if (! is_file($path)) {
            return [];
        }
        $state = json_decode((string) file_get_contents($path), true);

        return is_array($state) ? $state : [];
    }

    /**
     * @param  list<int>  $lineIds
     * @param  list<int>  $stationIds
     */
    private function writeState(string $asOf, array $lineIds, array $stationIds): void
    {
        $path = $this->statePath();
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        $state = ['as_of' => $asOf, 'ran_at' => now()->toIso8601String(), 'lines' => $lineIds, 'stations' => $stationIds];
        file_put_contents($path.'.tmp', json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n");
        rename($path.'.tmp', $path);
    }
}

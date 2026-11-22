<?php

namespace App\Http\Controllers;

use App\Models\Era;
use App\Support\Scheduling\SchedulerStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * État détaillé de l'application pour un moniteur de disponibilité (Uptime Kuma) : `GET /up/details`.
 *
 * Code 200 si tout va bien ou si seul un seuil est dépassé (`degraded`), 503 si la base de données
 * ou Valkey ne répond pas (`down`), 401 si un jeton est exigé et absent ou faux. Sans jeton
 * configuré (HEALTH_TOKEN), la réponse se limite à `{"status": ...}`. Jamais de nom de machine,
 * de chemin, de message d'erreur ni de donnée de compte : seulement des états, des nombres et la
 * version. Débit limité par adresse IP (limiteur `health`).
 */
class HealthController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $token = config('monitoring.health_token');
        $detailed = is_string($token) && $token !== '';
        if ($detailed) {
            $given = (string) ($request->bearerToken() ?? $request->query('token', ''));
            if (! hash_equals($token, $given)) {
                return $this->json(['status' => 'unauthorized'], 401);
            }
        }

        $checks = [
            'database' => $this->database(),
            'valkey' => $this->valkey(),
            'queue' => $this->queue(),
            'disk' => $this->disk(),
            'era' => $this->era(),
            'scheduler' => $this->scheduler(),
        ];

        $statuses = array_column($checks, 'status');
        $status = in_array('down', $statuses, true) ? 'down' : (in_array('degraded', $statuses, true) ? 'degraded' : 'ok');
        $body = ['status' => $status];
        if ($detailed) {
            $body += ['release' => (string) config('monitoring.release'), 'checks' => $checks];
        }

        return $this->json($body, $status === 'down' ? 503 : 200);
    }

    /** @param array<string, mixed> $body */
    private function json(array $body, int $code): JsonResponse
    {
        return response()->json($body, $code, ['Cache-Control' => 'no-store']);
    }

    /** @return array<string, mixed> */
    private function database(): array
    {
        try {
            $start = hrtime(true);
            DB::select('select 1');

            return ['status' => 'ok', 'ms' => (int) round((hrtime(true) - $start) / 1_000_000)];
        } catch (Throwable) {
            return ['status' => 'down'];
        }
    }

    /** @return array<string, mixed> */
    private function valkey(): array
    {
        $usesIt = config('cache.default') === 'redis' || config('queue.default') === 'redis' || config('session.driver') === 'redis';
        if (! $usesIt) {
            return ['status' => 'ok', 'used' => false];
        }
        try {
            $start = hrtime(true);
            Redis::connection()->ping();

            return ['status' => 'ok', 'used' => true, 'ms' => (int) round((hrtime(true) - $start) / 1_000_000)];
        } catch (Throwable) {
            return ['status' => 'down', 'used' => true];
        }
    }

    /** @return array<string, mixed> */
    private function queue(): array
    {
        $out = ['status' => 'ok', 'driver' => (string) config('queue.default')];
        try {
            if (config('queue.default') !== 'sync') {
                $out['waiting'] = Queue::size();
            }
            $failedTable = (string) config('queue.failed.table', 'failed_jobs');
            if (Schema::hasTable($failedTable)) {
                $out['failed'] = DB::table($failedTable)->count();
            }
        } catch (Throwable) {
            // File ou base injoignable : déjà signalé par les contrôles correspondants.
            return ['status' => 'degraded', 'driver' => $out['driver']];
        }
        if (($out['waiting'] ?? 0) > (int) config('monitoring.queue_max_waiting') || ($out['failed'] ?? 0) > (int) config('monitoring.failed_jobs_max')) {
            $out['status'] = 'degraded';
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function disk(): array
    {
        $free = @disk_free_space(storage_path());
        $total = @disk_total_space(storage_path());
        if ($free === false || $total === false || $total <= 0) {
            return ['status' => 'degraded'];
        }
        $freeMb = (int) floor($free / 1_048_576);

        return [
            'status' => $freeMb < (int) config('monitoring.disk_min_free_mb') ? 'degraded' : 'ok',
            'free_mb' => $freeMb,
            'used_percent' => (int) round(100 * (1 - $free / $total)),
        ];
    }

    /**
     * Âge du dernier calcul de l'ère courante : dernière mise à jour d'un de ses tronçons
     * (mise en cache dix minutes, la table est grande).
     *
     * @return array<string, mixed>
     */
    private function era(): array
    {
        try {
            $last = Cache::remember('health.era-computed-at', 600, function (): ?string {
                $era = Era::query()->where('is_current', true)->first();
                if ($era === null) {
                    return null;
                }
                $at = DB::table('line_segments')->where('era_id', $era->id)->max('updated_at');

                return is_string($at) ? $at : null;
            });
        } catch (Throwable) {
            return ['status' => 'degraded'];
        }
        if ($last === null) {
            return ['status' => 'degraded', 'age_hours' => null];
        }
        $age = (int) floor(Carbon::parse($last)->diffInHours(now(), true));

        return [
            'status' => $age > (int) config('monitoring.era_max_age_hours') ? 'degraded' : 'ok',
            'age_hours' => $age,
        ];
    }

    /**
     * Planificateur (docs/PLANIFICATION.md) : battement de cinq minutes et passage de la nuit du recalcul
     * de l'ère courante. Contrôlé seulement là où le planificateur est attendu (HEALTH_SCHEDULER_EXPECTED,
     * vrai en production) : sur un poste de développement, aucun planificateur ne tourne.
     *
     * @return array<string, mixed>
     */
    private function scheduler(): array
    {
        if (! config('monitoring.scheduler_expected')) {
            return ['status' => 'ok', 'expected' => false];
        }
        try {
            $beat = SchedulerStatus::beatAgeMinutes();
        } catch (Throwable) {
            return ['status' => 'degraded', 'expected' => true];
        }
        $lag = SchedulerStatus::refreshLagDays();
        $stale = $beat === null || $beat > (int) config('monitoring.scheduler_max_age_minutes')
            || $lag === null || $lag > (int) config('monitoring.refresh_max_lag_days');

        return [
            'status' => $stale ? 'degraded' : 'ok',
            'expected' => true,
            'beat_age_minutes' => $beat,
            'refresh_lag_days' => $lag,
        ];
    }
}

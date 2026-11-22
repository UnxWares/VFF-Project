<?php

namespace App\Support\Scheduling;

use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Suivi du planificateur Laravel (docs/PLANIFICATION.md) : battement et signalement des échecs.
 *
 * - `beat()` est appelé par une tâche planifiée toutes les cinq minutes : tant que `schedule:run`
 *   tourne sur le serveur, la clé de cache est fraîche. La page de santé (`GET /up/details`, contrôle
 *   `scheduler`) passe à `degraded` quand le battement est trop ancien ou que le recalcul quotidien
 *   de l'ère courante n'a pas fait son passage la nuit dernière : une nuit manquée se voit.
 * - `failureReporter()` est branché sur `onFailure` de chaque tâche : l'échec est écrit au journal
 *   et remonté comme exception, donc envoyé à GlitchTip ou Bugsink si SENTRY_LARAVEL_DSN est renseigné
 *   (bootstrap/app.php, `Integration::handles`).
 */
final class SchedulerStatus
{
    public const BEAT_KEY = 'health.scheduler-beat';

    /** Dernier passage du recalcul de l'ère courante (écrit par vff:refresh-current-era). */
    public static function refreshStatePath(): string
    {
        return storage_path('app/state/current-era-refresh.json');
    }

    public static function beat(): void
    {
        Cache::forever(self::BEAT_KEY, now()->getTimestamp());
    }

    /** Minutes depuis le dernier battement, ou null s'il n'y en a jamais eu. */
    public static function beatAgeMinutes(): ?int
    {
        $at = Cache::get(self::BEAT_KEY);

        return is_int($at) ? (int) floor((now()->getTimestamp() - $at) / 60) : null;
    }

    /** Jours de retard du recalcul de l'ère courante (0 : fait aujourd'hui), ou null sans fichier d'état. */
    public static function refreshLagDays(?string $path = null): ?int
    {
        $path ??= self::refreshStatePath();
        if (! is_file($path)) {
            return null;
        }
        $state = json_decode((string) file_get_contents($path), true);
        $asOf = is_array($state) ? ($state['as_of'] ?? null) : null;
        if (! is_string($asOf)) {
            return null;
        }
        try {
            return max(0, (int) Carbon::parse($asOf)->startOfDay()->diffInDays(now()->startOfDay(), true));
        } catch (Throwable) {
            return null;
        }
    }

    /** @return Closure(): void */
    public static function failureReporter(string $task): Closure
    {
        return static function () use ($task): void {
            Log::error("Tâche planifiée en échec : {$task} (détail : storage/logs/schedule.log)");
            report(new RuntimeException("Tâche planifiée en échec : {$task}"));
        };
    }
}

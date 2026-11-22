<?php

use App\Models\AuditEvent;
use App\Models\DatabaseNotification;
use App\Models\ErrorReport;
use App\Support\Scheduling\SchedulerStatus;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Tâches planifiées
|--------------------------------------------------------------------------
|
| Déclenchées par `php artisan schedule:run` chaque minute (cron en production,
| timer systemd utilisateur en développement : cf. docs/PLANIFICATION.md et
| deploy/systemd/).
|
| Ère courante (« 2030 » = état du réseau au jour même) : recalcul des seules
| lignes et gares dont un événement est daté depuis le dernier passage. Premier
| essai à 4 h 30, puis toutes les heures jusqu'au soir : un passage déjà fait
| dans la journée sort aussitôt, un passage reporté (reconstruction complète en
| cours, verrou .era-segments.lock pris) est retenté l'heure suivante.
*/

Schedule::command('vff:refresh-current-era')
    ->name('vff:refresh-current-era')
    ->cron('30 4-23 * * *')
    ->timezone('Europe/Paris')
    // Pas de runInBackground : avec bin/php, le conteneur s'arrête à la fin de schedule:run.
    ->withoutOverlapping(180);

/*
| Engagements de la politique de confidentialité (production seulement : aucune tâche planifiée
| sur les postes de développement).
|
| Comptes inutilisés depuis 3 ans : suppression des comptes avertis il y a un mois au moins, puis
| avertissement par courriel de ceux qui atteignent 3 ans d'inactivité dans un mois. Entrées du
| journal d'audit qui désignent une personne : 12 mois, comme les journaux techniques (rotation du
| canal `daily`, config/logging.php).
*/

Schedule::command('vff:prune-inactive-accounts')
    ->name('vff:prune-inactive-accounts')
    ->dailyAt('05:15')
    ->timezone('Europe/Paris')
    ->environments(['production'])
    ->withoutOverlapping(60);

// Jetons d'API : rappel par courriel 30 jours avant leur échéance (un an après leur création ou leur
// dernier renouvellement), un courriel par compte.
Schedule::command('vff:remind-expiring-api-tokens')
    ->name('vff:remind-expiring-api-tokens')
    ->dailyAt('05:30')
    ->timezone('Europe/Paris')
    ->environments(['production'])
    ->withoutOverlapping(60);

Schedule::command('model:prune', ['--model' => [AuditEvent::class]])
    ->name('vff:prune-audit-events')
    ->dailyAt('05:45')
    ->timezone('Europe/Paris')
    ->environments(['production']);

// Notifications du site lues depuis plus de 12 mois (App\Models\DatabaseNotification::prunable).
Schedule::command('model:prune', ['--model' => [DatabaseNotification::class]])
    ->name('vff:prune-notifications')
    ->dailyAt('05:50')
    ->timezone('Europe/Paris')
    ->environments(['production']);

// Signalements d'erreur traités ou rejetés depuis plus de 12 mois (App\Models\ErrorReport::prunable).
Schedule::command('model:prune', ['--model' => [ErrorReport::class]])
    ->name('vff:prune-error-reports')
    ->dailyAt('05:55')
    ->timezone('Europe/Paris')
    ->environments(['production']);

// Cache disque du relais des vignettes de Commons (config commons_proxy) : vignettes périmées et taille
// maximale, toutes les heures, seulement quand le relais est activé.
Schedule::command('vff:commons-cache-prune')
    ->name('vff:commons-cache-prune')
    ->hourlyAt(20)
    ->environments(['production'])
    ->when(fn (): bool => (bool) config('commons_proxy.enabled'))
    ->withoutOverlapping(30);

// Liste des fichiers de Commons cités par VFF, seuls servis par le relais (CommonsAllowlist) : reconstruite
// toutes les dix minutes (lecture seule de la base), pour qu'une image ajoutée soit servie sans attendre.
Schedule::command('vff:commons-allowlist --refresh')
    ->name('vff:commons-allowlist')
    ->everyTenMinutes()
    ->environments(['production'])
    ->when(fn (): bool => (bool) config('commons_proxy.enabled'))
    ->withoutOverlapping(15);

/*
| Battement et entretien général (production seulement, sauf le battement).
|
| Battement : toutes les cinq minutes, il prouve que `schedule:run` tourne ; le contrôle `scheduler` de
| /up/details (HealthController) passe à `degraded` s'il s'arrête ou si le recalcul de l'ère courante a
| manqué une nuit (config/monitoring.php, docs/PLANIFICATION.md).
|
| Entretien : jetons d'API (Sanctum) expirés depuis plus d'un jour (ils expirent un an après leur création
| ou leur renouvellement), jetons de réinitialisation de mot de passe périmés, travaux échoués de plus de
| 30 jours. Les sessions sont dans Valkey (SESSION_DRIVER=redis) et expirent seules ; les livraisons de
| webhooks sont élaguées à chaque envoi (WebhookSubscriptions::pruneLog) ; les erreurs JavaScript du
| navigateur vont dans un journal tournant, pas dans une table.
*/

Schedule::call(SchedulerStatus::beat(...))
    ->name('vff:scheduler-heartbeat')
    ->everyFiveMinutes();

Schedule::command('sanctum:prune-expired', ['--hours' => 24])
    ->name('sanctum:prune-expired')
    ->dailyAt('04:05')
    ->timezone('Europe/Paris')
    ->environments(['production']);

Schedule::command('auth:clear-resets')
    ->name('auth:clear-resets')
    ->dailyAt('04:10')
    ->timezone('Europe/Paris')
    ->environments(['production']);

Schedule::command('queue:prune-failed', ['--hours' => 720])
    ->name('queue:prune-failed')
    ->dailyAt('04:15')
    ->timezone('Europe/Paris')
    ->environments(['production']);

/*
| Toutes les tâches de commande : une seule exécution à la fois sur l'ensemble des serveurs (cache
| partagé, Valkey), sortie ajoutée à storage/logs/schedule.log, échec signalé (journal puis
| exception rapportée, donc GlitchTip ou Bugsink si configuré). Le battement, une tâche de rappel,
| tourne sur chaque serveur.
*/
foreach (Schedule::events() as $event) {
    if ($event instanceof CallbackEvent) {
        continue;
    }
    $task = (string) ($event->description ?? $event->command);
    $event->onOneServer()
        ->appendOutputTo(storage_path('logs/schedule.log'))
        ->onFailure(SchedulerStatus::failureReporter($task));
}

<?php

use App\Models\AuditEvent;
use App\Models\DatabaseNotification;
use App\Models\ErrorReport;
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

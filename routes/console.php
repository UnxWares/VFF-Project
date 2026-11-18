<?php

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

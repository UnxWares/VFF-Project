# Tâches planifiées

Les tâches sont déclarées dans `routes/console.php` (planificateur Laravel). Le
planificateur ne tourne pas tout seul : `php artisan schedule:run` doit être lancé
régulièrement (minuteur systemd en production, `deploy/systemd/production/` ; rien sur le poste de développement).
`php artisan schedule:list` affiche les tâches et leur prochaine échéance.

## Ère courante quotidienne : `vff:refresh-current-era`

L'ère « 2030 » de la carte est l'état du réseau au jour même (le 19/10/2026, elle
montre le 19/10/2026 ; le lendemain, le 20/10/2026). Les ères passées restent au 31/12
de leur année. La commande recalcule chaque jour l'ère courante des seules lignes et
gares dont un événement est daté depuis le dernier passage :

1. fenêtre `]dernière date traitée ; aujourd'hui]`, la dernière date étant lue dans
   `storage/app/state/current-era-refresh.json` (premier passage : la veille) ;
2. lignes : `line_events.event_date` dans la fenêtre (lignes ayant un tracé de référence) ;
   gares : `stations.opened_at`, `stations.closed_at` ou `station_events.event_date`
   dans la fenêtre ;
3. rien de neuf : la date est enregistrée, aucune reconstruction ;
4. sinon, sous le verrou `storage/app/reports/agents/.era-segments.lock` :
   `vff:build-era-segments --line=… --era=<courante> --no-classify`,
   `vff:enrich-era-segments --line=…` (ligne par ligne), puis pour l'ère courante
   `vff:classify-lines`, `vff:check-consistency --fix` (non bloquant),
   `vff:build-station-eras`, `vff:build-shared-track`, `vff:build-network-graph`
   (gares seules : seulement les gares et le graphe).

Reconstruction complète en cours, la commande **reporte** (succès, fichier d'état
inchangé, la fenêtre sera rattrapée au passage suivant) si :

- le verrou `.era-segments.lock` est pris (`flock`) ;
- un processus `vff:rebuild-history`, `vff:build-era-segments`, `vff:enrich-era-segments`
  ou `vff:build-line-references` est visible (même hôte ou même conteneur) ;
- une autre session PostgreSQL de la même base tient un verrou consultatif (verrous par
  ligne du moteur) ou écrit dans `line_segments`, `line_segment_covers`, `station_eras`
  ou `station_edges` (cas des conteneurs `bin/php`, invisibles entre eux).

Planification : `30 4-23 * * *` (Europe/Paris), `withoutOverlapping`. Le premier passage
de 4 h 30 fait le travail ; les passages horaires suivants sortent aussitôt (« déjà à
jour ») ou rattrapent un passage reporté.

```bash
bin/php artisan vff:refresh-current-era --dry-run             # lecture seule
bin/php artisan vff:refresh-current-era --since=2026-10-01    # forcer une fenêtre
```

Changement d'année : l'année de calcul de l'ère courante change pour toutes les lignes ;
la commande l'affiche et recommande `vff:rebuild-history --from=build-era-segments`.

## Toutes les tâches planifiées

| Nom                              | Heure (Europe/Paris)  | Où               | Rôle                                                                                  |
| -------------------------------- | --------------------- | ---------------- | ------------------------------------------------------------------------------------- |
| `vff:scheduler-heartbeat`        | toutes les 5 minutes  | partout          | battement lu par `/up/details` (contrôle `scheduler`)                                 |
| `sanctum:prune-expired`          | 4 h 05                | production       | jetons d'API expirés depuis plus de 24 h (ils expirent un an après leur création)     |
| `auth:clear-resets`              | 4 h 10                | production       | jetons de réinitialisation de mot de passe périmés                                    |
| `queue:prune-failed`             | 4 h 15                | production       | travaux échoués depuis plus de 30 jours                                               |
| `vff:refresh-current-era`        | 4 h 30, puis chaque heure jusqu'à 23 h 30 | partout (rien ne tourne sans `schedule:run`) | recalcul de l'ère courante, voir plus haut |
| `vff:refresh-stale-figures`      | toutes les 10 minutes | production       | recalcule départements, compagnies et couverture si les données ont changé et se sont calmées depuis 10 min ; en fin de `vff:refresh-current-era` aussi (sans attendre) |
| `vff:prune-inactive-accounts`    | 5 h 15                | production       | comptes inutilisés depuis 3 ans                                                       |
| `vff:remind-expiring-api-tokens` | 5 h 30                | production       | rappel 30 jours avant l'échéance d'un jeton                                           |
| `vff:prune-audit-events`         | 5 h 45                | production       | journal d'audit, 12 mois                                                              |
| `vff:prune-notifications`        | 5 h 50                | production       | notifications lues depuis plus de 12 mois                                             |
| `vff:prune-error-reports`        | 5 h 55                | production       | signalements traités depuis plus de 12 mois                                           |
| `vff:commons-cache-prune`        | chaque heure, minute 20 | production, relais activé | cache disque des vignettes de Commons                                   |
| `vff:commons-allowlist`          | toutes les dix minutes | production, relais activé | liste des fichiers de Commons cités, seuls servis par le relais        |

Garde-fous communs (fin de `routes/console.php`) : chaque tâche de commande est en `onOneServer()` (verrou
dans le cache partagé, Valkey), ajoute sa sortie à `storage/logs/schedule.log` et, en cas d'échec,
écrit au journal puis rapporte une exception (`SchedulerStatus::failureReporter`), donc envoyée à
GlitchTip ou Bugsink si `SENTRY_LARAVEL_DSN` est renseigné. Le recalcul de l'ère courante a en plus
`withoutOverlapping(180)`, le verrou `flock` des reconstructions et un fichier d'état.

Ce qui n'a pas de tâche, et pourquoi : le cache de la carte (`map-data:version`) est relu toutes les 60 s
depuis les dates de modification des tables, un recalcul le change tout seul ; les plans du site
(`sitemap:*`) ont leur propre durée de vie ; les sessions sont dans Valkey et expirent seules ; les
livraisons de webhooks sont élaguées à chaque envoi ; il n'y a pas d'export GTFS quotidien ; les erreurs
JavaScript du navigateur vont dans un journal tournant (12 mois), pas dans une table.

Durées : un passage sans événement neuf prend moins d'une seconde. Avec des événements, il reconstruit
l'ère courante de quelques lignes puis les gares, les voies partagées et le graphe de l'ère : de l'ordre
de la minute à quelques minutes (la reconstruction nationale des gares seule dure environ 13 minutes,
rapports d'agents `fiche-contenus.md` et `fermeture-gares.md`, d'où le report sous verrou et le plafond
de 180 minutes de `withoutOverlapping`).

## Suivi : une nuit manquée se voit

Le contrôle `scheduler` de `GET /up/details` (jeton `HEALTH_TOKEN`, voir `docs/SUIVI-ERREURS.md`) passe à
`degraded` (réponse 200, le moniteur de disponibilité l'affiche) si :

- le battement a plus de 15 minutes (`HEALTH_SCHEDULER_MAX_AGE_MINUTES`) ou n'a jamais eu lieu : `schedule:run`
  ne tourne plus ;
- le recalcul de l'ère courante a plus d'un jour de retard (`HEALTH_REFRESH_MAX_LAG_DAYS`, lu dans
  `storage/app/state/current-era-refresh.json`) : une nuit a été manquée.

Le contrôle n'est actif que là où le planificateur est attendu (`HEALTH_SCHEDULER_EXPECTED`, vrai par défaut
quand `APP_ENV=production`). Surveiller dans le moniteur : `checks.scheduler.status`.

## Production : à poser chez UnxWares (installation non faite)

Une seule entrée, qui lance `schedule:run` chaque minute pour l'utilisateur de l'application, avec
`APP_ENV=production`, `CACHE_STORE=redis` (Valkey, verrous et `onOneServer`) et `HEALTH_TOKEN` renseigné : le
service et le minuteur systemd de `deploy/systemd/production/` (durcis), posés par l'étape 11 du parcours
`docs/MISE-EN-PRODUCTION.md`. Jamais un cron en plus : chaque tâche tournerait deux fois (le script de mise en
production le signale). Forme abrégée des deux unités :

```ini
# vff-schedule.service
[Unit]
Description=VFF - planificateur Laravel (schedule:run)
After=network-online.target postgresql.service valkey.service

[Service]
Type=oneshot
User=vff
WorkingDirectory=/srv/vff
ExecStart=/usr/bin/php artisan schedule:run --no-interaction
TimeoutStartSec=3h

# vff-schedule.timer
[Unit]
Description=VFF - planificateur Laravel, chaque minute

[Timer]
OnCalendar=*-*-* *:*:00
AccuracySec=1s
Persistent=false

[Install]
WantedBy=timers.target
```

`systemctl enable --now vff-schedule.timer`, puis `journalctl -u vff-schedule.service` et
`storage/logs/schedule.log`. Un travailleur de file (`queue:work`) est à part (`vff-queue.service`,
`docs/DEPLOIEMENT-SERVICES.md`).
Pour qu'une reconstruction complète ne croise jamais le recalcul quotidien, la lancer sous le verrou commun :

```bash
flock storage/app/reports/agents/.era-segments.lock php artisan vff:rebuild-history
```

Vérifier après l'installation : `php artisan schedule:list` (tâches et heures), puis au bout de six minutes
`curl -H "Authorization: Bearer $HEALTH_TOKEN" https://vff-project.org/up/details` doit montrer
`checks.scheduler.status` à `ok` (le premier passage de 4 h 30 écrit le fichier d'état ; avant lui, le
contrôle reste `degraded`, ce qui est voulu). Sur le poste de développement, rien ne tourne tant que
`schedule:run` n'est pas appelé.

## Développement : timer systemd utilisateur (Podman rootless)

Décision du 20/10/2026 : aucune tâche planifiée permanente sur le poste de développement ; le recalcul quotidien
tourne en production. Fichiers fournis pour mémoire, **non activés** : `deploy/systemd/vff-schedule.service` (un passage de
`bin/php artisan schedule:run`) et `deploy/systemd/vff-schedule.timer` (minute 30 de
chaque heure, de 4 h 30 à 23 h 30). Adapter `WorkingDirectory` et `ExecStart` si le
dépôt n'est pas au même emplacement. La base (`vff-db`) et Valkey (`vff-valkey`) doivent
tourner.

Activation (décision de l'utilisateur) :

```bash
mkdir -p ~/.config/systemd/user
cp deploy/systemd/vff-schedule.service deploy/systemd/vff-schedule.timer ~/.config/systemd/user/
systemctl --user daemon-reload
systemctl --user start vff-schedule.service        # essai immédiat (un passage)
journalctl --user -u vff-schedule.service -n 50
systemctl --user enable --now vff-schedule.timer
systemctl --user list-timers vff-schedule.timer
# Sans session ouverte (machine allumée mais déconnectée) :
loginctl enable-linger "$USER"
```

Désactivation :

```bash
systemctl --user disable --now vff-schedule.timer
rm ~/.config/systemd/user/vff-schedule.{service,timer} && systemctl --user daemon-reload
```

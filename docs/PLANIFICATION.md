# Tâches planifiées

Les tâches sont déclarées dans `routes/console.php` (planificateur Laravel). Le
planificateur ne tourne pas tout seul : `php artisan schedule:run` doit être lancé
régulièrement (cron en production, timer systemd utilisateur en développement).
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

## Production : cron

Une seule entrée cron pour l'utilisateur qui exécute l'application :

```cron
* * * * * cd /srv/vff && php artisan schedule:run >> /dev/null 2>&1
```

Le cache (Valkey) porte le verrou `withoutOverlapping`. Pour qu'une reconstruction
complète ne croise jamais le recalcul quotidien, la lancer sous le verrou commun :

```bash
flock storage/app/reports/agents/.era-segments.lock php artisan vff:rebuild-history
```

## Développement : timer systemd utilisateur (Podman rootless)

Fichiers fournis, **non activés** : `deploy/systemd/vff-schedule.service` (un passage de
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

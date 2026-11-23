# Déploiement : processus de production (Reverb, file, planificateur)

Kit pour le **serveur de production** d'UnxWares. Rien de tout cela n'est installé ni activé sur un poste de
travail (les unités de `deploy/systemd/` sans sous-dossier restent celles du développement). État : prêt, reste
à poser sur le serveur, dans l'ordre du parcours `docs/MISE-EN-PRODUCTION.md` (étapes 10 à 12, script
`deploy/bin/mise-en-production.sh`).

## Fichiers

| Fichier                                         | Rôle                                                                              |
| ----------------------------------------------- | --------------------------------------------------------------------------------- |
| `deploy/systemd/production/vff-reverb.service`  | Laravel Reverb (`reverb:start`), WebSocket sur `127.0.0.1:8080`                    |
| `deploy/systemd/production/vff-queue.service`   | Travailleur de file (`queue:work redis`, Valkey)                                  |
| `deploy/systemd/production/vff-schedule.service`| Un passage de `schedule:run`                                                      |
| `deploy/systemd/production/vff-schedule.timer`  | Déclenche le précédent chaque minute                                              |
| `deploy/systemd/production/vff.target`          | Groupe les trois, pour `systemctl restart vff.target`                             |
| `deploy/bin/verify-production.sh`               | Vérification après déploiement (lecture seule)                                    |
| `deploy/nginx/snippets/vff-reverb.conf`         | Proxy WebSocket (`docs/DEPLOIEMENT-NGINX.md`, section « Temps réel »)             |

Hypothèses à adapter si besoin : code dans `/srv/vff`, PHP dans `/usr/bin/php`, utilisateur et groupe `vff`.

## Pose sur le serveur

```bash
# 1. Utilisateur dédié, sans connexion ni répertoire personnel utile
sudo useradd --system --home-dir /srv/vff --shell /usr/sbin/nologin vff
sudo chown -R vff:vff /srv/vff/storage /srv/vff/bootstrap/cache

# 2. Unités
sudo install -m 0644 deploy/systemd/production/vff-reverb.service deploy/systemd/production/vff-queue.service \
    deploy/systemd/production/vff-schedule.service deploy/systemd/production/vff-schedule.timer \
    deploy/systemd/production/vff.target /etc/systemd/system/
sudo systemd-analyze verify /etc/systemd/system/vff-*.service /etc/systemd/system/vff-schedule.timer
sudo systemctl daemon-reload
sudo systemctl enable --now vff-reverb.service vff-queue.service vff-schedule.timer
```

Les trois unités ont besoin de PHP avec les extensions de l'application, de PostgreSQL et de Valkey joignables
depuis `.env`. Reverb ne démarre utilement qu'avec `REALTIME_ENABLED=true` (voir ci-dessous) ; tant que le temps
réel est désactivé, ne pas activer `vff-reverb.service`.

## Variables d'environnement (`.env` de production)

| Variable                                              | Valeur en production                                                    |
| ----------------------------------------------------- | ----------------------------------------------------------------------- |
| `APP_ENV`, `APP_DEBUG`                                | `production`, `false`                                                   |
| `QUEUE_CONNECTION`, `CACHE_STORE`, `SESSION_DRIVER`   | `redis` (Valkey ; le client est nommé `redis` par Laravel)              |
| `REDIS_CLIENT`, `REDIS_PASSWORD`                      | `phpredis` (extension `php8.5-redis`), `requirepass` de Valkey          |
| `BROADCAST_CONNECTION`, `REALTIME_ENABLED`            | `reverb`, `true`                                                        |
| `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`| valeurs aléatoires propres au serveur (la clé est publique, pas le secret) |
| `REVERB_SERVER_HOST`, `REVERB_SERVER_PORT`            | `127.0.0.1`, `8080` (où Reverb écoute)                                  |
| `REVERB_HOST`, `REVERB_PORT`, `REVERB_SCHEME`         | `127.0.0.1`, `8080`, `http` (où Laravel publie, sans nginx)             |
| `REVERB_ALLOWED_ORIGINS`, `REVERB_APP_MAX_CONNECTIONS`| vide (hôte d'`APP_URL`), `1000` (à relever avec `LimitNOFILE` de l'unité) |
| `REALTIME_CLIENT_HOST`, `_PORT`, `_SCHEME`            | `vff-project.org`, `443`, `https` (adresse publique, celle de nginx)    |
| `HEALTH_TOKEN`, `HEALTH_SCHEDULER_EXPECTED`           | jeton de `/up/details`, vrai par défaut en production                   |

Puis `php artisan config:cache` et activation du proxy nginx : `include /etc/nginx/snippets/vff-reverb.conf;`
dans le bloc `vff-project.org`.

## Durcissement et limites

- Utilisateur `vff`, `NoNewPrivileges`, `ProtectSystem=strict` (système en lecture seule), `ProtectHome`,
  `PrivateTmp`, `PrivateDevices`, noyau et groupes de contrôle protégés, familles d'adresses réduites à
  `AF_UNIX`, `AF_INET`, `AF_INET6`, `UMask=0027`.
- Écriture permise seulement dans `/srv/vff/storage` et `/srv/vff/bootstrap/cache` (`ReadWritePaths`). Si une
  tâche écrit ailleurs (par exemple un dossier de médias), l'ajouter à `ReadWritePaths`.
- Mémoire : Reverb et file `MemoryHigh=384M` / `MemoryMax=512M`, planificateur `2G` / `3G` (le recalcul de
  l'ère courante est le plus gourmand). Descripteurs : Reverb `LimitNOFILE=8192` pour plus de 1000 connexions.
- Redémarrage : `Restart=always`, 3 s d'attente, au plus 10 démarrages en 5 minutes avant arrêt par systemd.
- Journaux : `journalctl -u vff-reverb`, `-u vff-queue`, `-u vff-schedule` (identifiants `vff-reverb`,
  `vff-queue`, `vff-schedule`) ; le planificateur écrit aussi `storage/logs/schedule.log`. Limiter la taille du
  journal systemd (`SystemMaxUse=` dans `journald.conf`) selon le disque.

## Déploiement d'une nouvelle version

Après `composer install --no-dev`, `php artisan migrate --force`, `config:cache`, `route:cache`, `view:cache` :

```bash
php artisan queue:restart          # le travailleur finit son travail en cours, systemd le relance sur le nouveau code
sudo systemctl reload vff-reverb   # ExecReload = reverb:restart, les navigateurs se reconnectent d'eux-mêmes
```

Le planificateur n'a rien à redémarrer : chaque minute, un nouveau processus lit le nouveau code. Une
reconstruction lourde à la main passe sous le verrou commun (`docs/PLANIFICATION.md`).

## Vérification

```bash
HEALTH_TOKEN=… REVERB_APP_KEY=… deploy/bin/verify-production.sh https://vff-project.org
```

Le script (lecture seule, sortie non nulle au premier échec) contrôle : les trois unités actives ; `/up/details`
(`database`, `valkey`, `queue`, `scheduler` à `ok`) ; la poignée de main WebSocket par nginx (101) ; l'API HTTP de
Reverb non exposée (`/apps/…` jamais public) ; pas d'erreur fatale récente de la file ; au moins un passage du
planificateur. Le contrôle `scheduler` ne passe à `ok` qu'après le premier battement (5 minutes au plus).

## Tests

`tests/Unit/Static/ProductionServicesTest.php` lit les unités et vérifie les directives clés (utilisateur
dédié, durcissement, plafond mémoire, redémarrage, `reverb:start`, rechargement propre, `queue:work redis` avec
un délai inférieur à `retry_after`, minuteur chaque minute, script de vérification, port du proxy nginx).
En local, `systemd-analyze verify --user <unité>` ne signale que l'absence de `/usr/bin/php` quand PHP n'est pas
installé sur l'hôte (sans conséquence).

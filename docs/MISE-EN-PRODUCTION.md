# Mise en production : le parcours complet

Ce document est **le** parcours de mise en ligne de VFF sur le serveur d'UnxWares : tout ce qui reste à faire, dans
l'ordre, avec pour chaque étape la commande exacte, le contrôle qui prouve qu'elle a marché et le retour arrière.
Les documents thématiques gardent le détail et les raisons des choix :

| Sujet | Détail |
| --- | --- |
| nginx, en-têtes, PMTiles, relais Commons | `docs/DEPLOIEMENT-NGINX.md` |
| Reverb, file, planificateur (unités systemd) | `docs/DEPLOIEMENT-SERVICES.md` |
| Tâches planifiées, recalcul quotidien de l'ère 2030 | `docs/PLANIFICATION.md` |
| Sauvegarde de la base et restauration | `docs/SAUVEGARDE.md` |
| Kener et GlitchTip | `docs/SUIVI-PRODUCTION.md`, `docs/SUIVI-ERREURS.md` |
| Fond de carte PMTiles | `docs/FOND-DE-CARTE.md` |
| Politique de sécurité du contenu | `docs/SECURITE-CSP.md` |

Le script `deploy/bin/mise-en-production.sh` suit les mêmes étapes, sous les mêmes noms :

```bash
sudo deploy/bin/mise-en-production.sh --verifier             # ne change rien ; liste ce qui manque (code 1 s'il manque)
sudo deploy/bin/mise-en-production.sh --appliquer            # pose ce qui peut l'être ; s'arrête à la première erreur
sudo deploy/bin/mise-en-production.sh --verifier --etape dns # une seule étape (liste : --liste)
sudo deploy/bin/mise-en-production.sh --verifier --hors-ligne   # sans requête HTTP ni DNS
```

Il est idempotent (relancé, il ne change que ce qui diffère), ne supprime rien, copie tout fichier de configuration
qu'il remplace dans `/var/backups/vff-config/`, n'installe aucun paquet, ne charge aucune donnée et ne touche pas au
DNS : ces actions-là restent à la main, ci-dessous. Il se lance depuis le dépôt cloné sur le serveur.

Règle de prudence (incident du 28/10/2026), valable pour chaque commande de ce document : jamais de
`rsync --delete` (ni aucune option de suppression de rsync), de `rm -r`, de `find -delete`, ni de `mv` en masse vers
un chemin construit à partir d'une variable. Un retour arrière **renomme** ou **désactive**, il ne supprime pas.

## Prérequis

- Un serveur d'UnxWares sous Debian 13, avec un accès `sudo`, les ports 80 et 443 ouverts au public, et rien
  d'autre que SSH (22). PostgreSQL (5432), Valkey (6379) et Reverb (8080) n'écoutent que sur la boucle locale.
- Ses adresses IPv4 et IPv6, notées `SERVEUR_IPV4` et `SERVEUR_IPV6` ci-dessous.
- L'accès au gestionnaire DNS de `vff-project.org` (Infomaniak) et à la messagerie d'UnxWares (Hetzner).
- Une seconde machine (ou au moins un autre hôte) pour la page d'état Kener : une page d'état hébergée sur le
  serveur qu'elle surveille tombe avec lui.
- Un dépôt S3 hors du serveur pour les sauvegardes chiffrées (décidé le 23/11 : Infomaniak Swiss Backup, compartiment à part, chiffré par restic).
- Le dump de référence de la base et l'archive du fond de carte (étapes 4 et 15), préparés sur le poste.

Hypothèses du kit (à garder, ou à changer partout à la fois) : code dans `/srv/vff`, PHP 8.5 dans `/usr/bin/php`,
compte `vff` pour les processus, `vffdeploy` pour le code, `vffbackup` pour la sauvegarde.

## 1. Prérequis logiciels (`prerequis`)

PHP 8.5 vient du dépôt `packages.sury.org`, PostgreSQL 18 de `apt.postgresql.org` (Debian 13 fournit PHP 8.4 et
PostgreSQL 17), Node 26 de nodejs.org ou NodeSource ; Valkey est dans Debian.

```bash
sudo apt install nginx certbot git curl unzip restic bind9-dnsutils poppler-utils \
    php8.5-fpm php8.5-cli php8.5-pgsql php8.5-intl php8.5-zip php8.5-bcmath php8.5-gd php8.5-mbstring \
    php8.5-xml php8.5-curl php8.5-redis php8.5-opcache \
    postgresql-18 postgresql-18-postgis-3 postgresql-client-18 valkey-server
# Composer 2 (https://getcomposer.org/download/), Node 26 puis : sudo corepack enable pnpm
```

- Contrôle : `sudo deploy/bin/mise-en-production.sh --verifier --etape prerequis` sans ligne `MANQUE`.
- Retour arrière : aucun (paquets seulement).

## 2. Comptes (`comptes`)

```bash
sudo useradd --create-home --shell /bin/bash vffdeploy          # propriétaire du code, fait les git pull
sudo deploy/bin/mise-en-production.sh --appliquer --etape comptes
```

Le script crée `vff` et `vffbackup` (comptes système, sans connexion) et met `vffbackup`, `www-data` (nginx) et
`vffdeploy` dans le groupe `vff` : l'essai de restauration lit `.env`, nginx lit l'archive PMTiles et les vignettes.

- Contrôle : `id vff vffbackup vffdeploy` ; `id -nG www-data` contient `vff`.
- Retour arrière : `sudo gpasswd -d www-data vff` (et de même pour les autres) ; un compte système inutilisé se
  désactive par `sudo usermod --lock <compte>`.

## 3. PostgreSQL (`postgresql`)

```bash
sudo deploy/bin/mise-en-production.sh --appliquer --etape postgresql
sudo -u postgres psql -c '\password vff'          # mot de passe de l'application, dans DB_PASSWORD de .env
sudo -u postgres psql -c '\password vff_backup'   # mot de passe de la sauvegarde, dans /etc/vff/pgpass
```

Le script crée le rôle `vff`, la base `vff` (propriétaire `vff`), les extensions `postgis`, `pg_trgm` et `unaccent`,
le rôle `vff_backup` (`CREATEDB`, membre de `pg_read_all_data`), et recopie dans `template1` les extensions de la
base : l'essai de restauration crée ses bases depuis `template1` sous `vff_backup`, qui ne peut pas créer PostGIS.
Après le chargement des données (étape 4), le relancer : les extensions venues du dump (`postgis_topology`,
`fuzzystrmatch`, `postgis_tiger_geocoder`) passent à leur tour dans `template1`.

Réglages de `/etc/postgresql/18/main/postgresql.conf` à poser à la main : `listen_addresses = 'localhost'`,
`max_parallel_maintenance_workers = 0` (index parallèles et mémoire partagée, incident du 23/11/2026),
`shared_buffers` au quart de la mémoire, `max_locks_per_transaction = 512` si la base de GlitchTip est sur ce même
serveur. Puis `sudo systemctl restart postgresql`.

- Contrôle : `--verifier --etape postgresql` ; `sudo -u postgres psql -d vff -c '\dx'` liste PostGIS.
- Retour arrière : la base est vide à ce stade ; la renommer (`ALTER DATABASE vff RENAME TO vff_abandon_<date>`)
  plutôt que la supprimer.

## 4. Données (`donnees`) : à la main, jamais par le script

Sur le poste, une fois la chaîne d'applications du jour terminée (lecture seule de `vff`) :

```bash
podman exec vff-db pg_dump -U vff --format=custom --compress=zstd:6 vff > ~/vff-initial.dump
podman exec -i vff-db pg_restore --list < ~/vff-initial.dump > /dev/null && echo dump-lisible
scp ~/vff-initial.dump vffdeploy@<serveur>:/tmp/vff-initial.dump
```

Sur le serveur, dans la base neuve et vide de l'étape 3 (les objets du poste appartiennent déjà au rôle `vff`) :

```bash
sudo -u postgres pg_restore --dbname=vff --no-privileges --exit-on-error --jobs=4 /tmp/vff-initial.dump
sudo deploy/bin/mise-en-production.sh --appliquer --etape postgresql   # extensions du dump vers template1
```

Avant d'ouvrir le site, décider ce qui ne doit pas partir en production : comptes et jetons du poste
(`users`, `personal_access_tokens`), abonnements de webhooks (leur secret est chiffré par l'`APP_KEY` du poste et
devient illisible avec la clé de production).

- Contrôle : `--verifier --etape donnees` affiche le nombre de lignes ; `sudo -u postgres psql -d vff -c 'select count(*) from lines'`.
- Retour arrière : jamais de restauration avec l'option de nettoyage par-dessus une base ; renommer la base
  manquée (`ALTER DATABASE vff RENAME TO vff_essai_<date>`), recréer une base vide (étape 3) et recommencer.

## 5. Valkey (`valkey`)

Dans `/etc/valkey/valkey.conf` :

```
bind 127.0.0.1 -::1
requirepass <openssl rand -hex 32>
maxmemory 1gb
maxmemory-policy noeviction
```

`noeviction` : la file d'attente, les sessions et la liste des vignettes de Commons vivent dans la même instance ;
une éviction perdrait des travaux. `/up/details` signale une file pleine.

```bash
sudo systemctl restart valkey-server
sudo deploy/bin/mise-en-production.sh --appliquer --etape valkey
```

- Contrôle : `REDISCLI_AUTH=<mot de passe> valkey-cli ping` répond `PONG` ; le script vérifie aussi `REDIS_PASSWORD`
  de `.env` (étape 6), donc relancer cette étape après l'étape 6.
- Retour arrière : remettre l'ancien `valkey.conf` (copie faite avant l'édition) et redémarrer.

## 6. Application (`application`)

```bash
sudo install -d -o vffdeploy -g vff -m 2750 /srv/vff
sudo -u vffdeploy git clone https://github.com/UnxWares/VFF-Project.git /srv/vff
sudo -u vffdeploy cp /srv/vff/.env.example /srv/vff/.env
sudo -u vffdeploy php /srv/vff/artisan key:generate     # une seule fois : changer la clé déconnecte tout le monde
sudo -e /srv/vff/.env                                    # valeurs ci-dessous
cd /srv/vff && sudo deploy/bin/mise-en-production.sh --appliquer --etape application
```

Le script installe les dépendances (`composer install --no-dev --optimize-autoloader`, `pnpm install
--frozen-lockfile`, `pnpm run build`) sous `vffdeploy`, donne `storage/` et `bootstrap/cache/` à `vff` (groupe en
lecture pour nginx), crée le lien `public/storage` et met `.env` en 640, groupe `vff`.

Valeurs de `.env` contrôlées par le script (les autres gardent leur défaut de `.env.example`) :

| Variable | Production |
| --- | --- |
| `APP_ENV`, `APP_DEBUG`, `APP_URL` | `production`, `false`, `https://vff-project.org` |
| `LOG_STACK`, `LOG_LEVEL` | `daily,json-errors`, `warning` |
| `DB_PASSWORD` | celui de l'étape 3 |
| `CACHE_STORE`, `QUEUE_CONNECTION`, `SESSION_DRIVER` | `redis` (Valkey ; Laravel nomme ainsi le protocole) |
| `REDIS_CLIENT`, `REDIS_PASSWORD` | `phpredis` (extension `php8.5-redis`, pas la bibliothèque `predis` du poste), `requirepass` de l'étape 5 |
| `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD` | `smtp` et le relais d'envoi de la messagerie d'UnxWares |
| `MAIL_FROM_ADDRESS` | `noreply@vff-project.org` |
| `CSP_ENFORCE` | `true` (politique bloquante, décision du 22/11/2026) |
| Temps réel | étape 12 |
| Fond de carte, Commons | étapes 15 et 16 |
| `HEALTH_TOKEN`, `SENTRY_LARAVEL_DSN`, `APP_VERSION` | étape 17 |

- Contrôle : `--verifier --etape application` sans `MANQUE`.
- Retour arrière : `git -C /srv/vff checkout <commit précédent>` puis relancer l'étape ; `.env` est copié à la main
  avant chaque édition (`sudo -u vffdeploy cp .env .env.avant-<date>`).

## 7. Sauvegarde quotidienne contrôlée (`sauvegarde`)

```bash
sudo deploy/bin/mise-en-production.sh --appliquer --etape sauvegarde   # pose les fichiers, s'arrête : remplir backup.env
sudo -e /etc/vff/backup.env                       # RESTIC_*, AWS_*, HEARTBEAT_URL, RESTORE_HEARTBEAT_URL (étape 17)
sudo install -m 600 -o vffbackup -g vffbackup /dev/null /etc/vff/pgpass
echo '127.0.0.1:5432:*:vff_backup:<mot de passe>' | sudo tee /etc/vff/pgpass >/dev/null
sudo install -m 600 -o vffbackup -g vffbackup /dev/null /etc/vff/restic-password
openssl rand -base64 48 | sudo tee /etc/vff/restic-password >/dev/null   # copie dans le coffre d'UnxWares
sudo deploy/bin/mise-en-production.sh --appliquer --etape sauvegarde   # premier passage, puis les minuteurs
```

Le script copie `deploy/backup/*.sh` dans `/opt/vff/backup/`, crée `/var/backups/vff` (700, `vffbackup`), pose
`vff-backup.*` et `vff-restore-test.*` dans `/etc/systemd/system/`, lance le premier passage s'il n'existe aucune
sauvegarde de moins de 26 h, puis active `vff-backup.timer` (3 h 15) et `vff-restore-test.timer` (premier dimanche du
mois). Il refuse un `backup.env` qui garde une valeur fictive de l'exemple.

- Contrôle : une sauvegarde de moins de 26 h avec son `.sha256` et son `.counts` ; puis l'essai de restauration à
  la main : `sudo systemctl start vff-restore-test.service && journalctl -u vff-restore-test.service -n 40`
  (« essai de restauration RÉUSSI »). Relever durée et taille du premier dump dans `docs/SAUVEGARDE.md`.
- Retour arrière : `sudo systemctl disable --now vff-backup.timer vff-restore-test.timer`. Les sauvegardes restent.

## 8. Migrations (`migrations`)

Après la sauvegarde : un dump existe avant tout changement de schéma. Le script refuse de migrer sans une sauvegarde
contrôlée de moins de 26 h (dump, `.sha256` et `.counts`), même quand l'étape est lancée seule.

```bash
sudo deploy/bin/mise-en-production.sh --appliquer --etape migrations
```

Le script lance `php artisan migrate --force` sous `vff` s'il reste des migrations, puis `php artisan optimize`
(caches de configuration, routes, vues et évènements). Les commandes qui vident une base sont refusées partout
ailleurs que sur une base `*_test` (`App\Support\DatabaseGuard`).

- Contrôle : `sudo -u vff php artisan migrate:status --pending=1` sort avec le code 0.
- Retour arrière : restaurer la sauvegarde de l'étape 7 dans une base neuve et basculer (`docs/SAUVEGARDE.md`,
  section 6) ; jamais `migrate:rollback` en production.

## 9. PHP-FPM (`php-fpm`)

```bash
sudo deploy/bin/mise-en-production.sh --appliquer --etape php-fpm
```

Pose `deploy/php-fpm/vff.conf` dans `/etc/php/8.5/fpm/pool.d/` : pool sous `vff`, socket
`/run/php/php8.5-fpm-vff.sock` (celui de nginx), pas de journal d'accès (la position de « Autour de moi » ne doit pas
y entrer), dépôts de 10 Mo (`upload_max_filesize = 11M`, `post_max_size = 12M`), `zend.exception_ignore_args`. Le
script contrôle `php-fpm8.5 -t` avant de recharger.

- Contrôle : `ls -l /run/php/php8.5-fpm-vff.sock`.
- Retour arrière : la copie précédente est dans `/var/backups/vff-config/` ; `sudo systemctl disable --now
  php8.5-fpm` arrête tout PHP-FPM.

## 10. Travailleur de file et unités (`services`)

```bash
sudo deploy/bin/mise-en-production.sh --appliquer --etape services
```

Pose `deploy/systemd/production/` (`vff-queue.service`, `vff-reverb.service`, `vff-schedule.service`,
`vff-schedule.timer`, `vff.target`), contrôle les unités par `systemd-analyze verify` et démarre
`vff-queue.service`. Durcissement et limites : `docs/DEPLOIEMENT-SERVICES.md`.

- Contrôle : `systemctl status vff-queue` ; `journalctl -u vff-queue -n 20`.
- Retour arrière : `sudo systemctl disable --now vff-queue.service`.

## 11. Planificateur : recalcul quotidien de l'ère 2030 (`planificateur`)

```bash
sudo deploy/bin/mise-en-production.sh --appliquer --etape planificateur
```

Active `vff-schedule.timer`, qui lance `php artisan schedule:run` chaque minute sous `vff`. Le planificateur choisit
lui-même les tâches dues (liste dans `docs/PLANIFICATION.md`) : `vff:refresh-current-era` à 4 h 30 puis chaque heure
jusqu'à 23 h 30, le battement `vff:scheduler-heartbeat` toutes les 5 minutes, l'entretien de la nuit, le relais
Commons. Un seul déclencheur : pas de cron en plus du minuteur (le script le signale).

- Contrôle : `sudo -u vff php artisan schedule:list` ; `sudo -u vff php artisan vff:refresh-current-era --dry-run` ;
  six minutes après, `checks.scheduler.status` vaut `ok` dans `/up/details` (étape 17) une fois le premier passage
  de 4 h 30 fait (avant, `degraded` est voulu).
- Retour arrière : `sudo systemctl disable --now vff-schedule.timer`. Une reconstruction complète à la main passe
  sous le verrou commun : `flock storage/app/reports/agents/.era-segments.lock php artisan vff:rebuild-history`.

## 12. Temps réel : Reverb et abonnements GraphQL (`temps-reel`)

Dans `.env` (`openssl rand -hex 8`, `-hex 16`, `-hex 32` pour les trois clés) :

```
REALTIME_ENABLED=true
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=…
REVERB_APP_KEY=…            # publique, transmise au navigateur
REVERB_APP_SECRET=…         # ne quitte jamais le serveur
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http
REALTIME_CLIENT_HOST=vff-project.org
REALTIME_CLIENT_PORT=443
REALTIME_CLIENT_SCHEME=https
```

```bash
sudo -u vff php /srv/vff/artisan config:cache
sudo deploy/bin/mise-en-production.sh --appliquer --etape temps-reel
```

Le script démarre `vff-reverb.service` (WebSocket sur `127.0.0.1:8080`). Les abonnements GraphQL
(`contributionApplied`, `myContributionChanged`, `reviewQueueChanged`) passent par Reverb
(`LIGHTHOUSE_BROADCASTER=reverb`, défaut) et sont stockés dans Valkey un jour au plus. Le proxy nginx de `/app/` est
activé à l'étape 14, qui décommente l'inclusion de `vff-reverb.conf` quand `REALTIME_ENABLED=true`.

- Contrôle : `ss -ltn 'sport = :8080'` sur `127.0.0.1` seulement ; après l'étape 14, la poignée de main répond 101
  (`deploy/bin/verify-production.sh`).
- Retour arrière : `REALTIME_ENABLED=false`, `config:cache`, `sudo systemctl disable --now vff-reverb.service`, puis
  relancer l'étape `nginx` (l'inclusion redevient un commentaire). Les webhooks `contribution.applied` couvrent le
  besoin en attendant.

## 13. Certificat TLS (`certificat`)

Le DNS doit déjà désigner le serveur (étape 19, enregistrements A et AAAA). Le premier certificat s'obtient par le
site par défaut de nginx (`/var/www/html`), car la configuration de VFF a besoin du certificat pour démarrer :

```bash
sudo certbot certonly --webroot -w /var/www/html -d vff-project.org -d www.vff-project.org \
    --deploy-hook 'systemctl reload nginx'
sudo deploy/bin/mise-en-production.sh --verifier --etape certificat
```

- Contrôle : certificat qui couvre le nom nu et `www`, valable plus de 14 jours, `certbot.timer` actif.
- Retour arrière : `sudo certbot revoke --cert-name vff-project.org` seulement si la clé a fuité ; sinon rien.

## 14. nginx (`nginx`)

```bash
sudo deploy/bin/mise-en-production.sh --appliquer --etape nginx
sudo certbot reconfigure --cert-name vff-project.org --webroot -w /var/www/acme --deploy-hook 'systemctl reload nginx'
```

Le script pose `deploy/nginx/vff-project.org.conf` dans `/etc/nginx/conf.d/` (avec le proxy WebSocket si le temps
réel est activé) et les quatre fichiers de `deploy/nginx/snippets/` dans `/etc/nginx/snippets/`, crée
`/etc/nginx/vff-optional/` et `/var/www/acme`, contrôle `nginx -t` puis recharge. Les renouvellements passent ensuite
par `/var/www/acme`, servi par le bloc du port 80 de VFF. Brotli (facultatif) : `deploy/nginx/optional/brotli.conf.example`.

- Contrôle : `sudo nginx -t` ; `curl -sI https://vff-project.org/up` ; `sudo certbot renew --dry-run`.
- Retour arrière : la configuration précédente est dans `/var/backups/vff-config/` ; la recopier, `nginx -t`,
  `systemctl reload nginx`.

## 15. Fond de carte PMTiles servi par nginx (`fond-de-carte`)

Depuis le poste, l'archive de 6 Go (`docs/FOND-DE-CARTE.md`) ; copie reprise si elle est coupée, sans option de
suppression :

```bash
rsync -a --partial --progress storage/app/data/pmtiles/france-20261120.pmtiles \
    vffdeploy@<serveur>:/srv/vff/storage/app/data/pmtiles/
```

ou, sur le serveur, `scripts/basemap-pmtiles.sh` (conteneur `go-pmtiles`, 10 Gio libres). Dans `.env` :
`VFF_BASEMAP_ARCHIVE=france-20261120.pmtiles`, `VFF_BASEMAP_ACCEL_REDIRECT=/_basemap/` (sinon les 6 Go passent
par PHP), `VFF_BASEMAP_ONLINE=true` si le fond libre doit servir la carte en ligne ; puis `config:cache`.

```bash
sudo deploy/bin/mise-en-production.sh --verifier --etape fond-de-carte
```

- Contrôle : une plage répond 206, l'archive entière 416, `/_basemap/` 404 (location interne).
- Retour arrière : `VFF_BASEMAP_ONLINE=false` (la carte reprend les tuiles OpenStreetMap) ou
  `VFF_BASEMAP_ARCHIVE=` (aucun fond libre), puis `config:cache`.

## 16. Relais des vignettes de Commons (`commons`)

Dans `.env` : `VFF_COMMONS_PROXY=true`, `VFF_COMMONS_PROXY_ACCEL_REDIRECT=/_commons/` ; puis `config:cache`.

```bash
sudo deploy/bin/mise-en-production.sh --appliquer --etape commons
```

Le script crée le cache `storage/app/cache/commons` et construit la liste des fichiers cités
(`php artisan vff:commons-allowlist --refresh`, sans laquelle le relais répond 503). Ensuite le planificateur la
reconstruit toutes les dix minutes et élague le cache chaque heure (`vff:commons-cache-prune`).

- Contrôle : `sudo -u vff php artisan vff:commons-allowlist` (nombre de fichiers, date) ; un fichier cité répond 200
  sans `Set-Cookie`, un fichier non cité 404 (`docs/DEPLOIEMENT-NGINX.md`, « Contrôles au déploiement »).
  Surveiller les 429 de Commons les premiers jours.
- Retour arrière : `VFF_COMMONS_PROXY=false`, `config:cache` : les images reviennent de `upload.wikimedia.org`.

## 17. Suivi : Kener et GlitchTip (`suivi`)

1. **GlitchTip** (erreurs) sur ce serveur, par son fichier compose officiel
   (<https://glitchtip.com/documentation/install>), écoute sur `127.0.0.1:8000`, base `glitchtip` dans le
   PostgreSQL du serveur (`sudo -u postgres createuser --login glitchtip` puis
   `sudo -u postgres createdb --owner=glitchtip glitchtip`), variables `SECRET_KEY`, `GLITCHTIP_DOMAIN`,
   `DATABASE_URL`, `EMAIL_URL`. Créer le projet « VFF », copier son DSN.
2. **Kener** (disponibilité, page d'état) sur l'autre machine, image `rajnandan1/kener`, avec une instance Valkey à
   lui (`REDIS_URL`), `KENER_SECRET_KEY`, `ORIGIN=https://status.vff-project.org`, `VFF_HEALTH_TOKEN`.
3. Certificats puis nginx des deux sous-domaines, sur la machine de chacun : d'abord
   `sudo certbot certonly --webroot -w /var/www/html -d erreurs.vff-project.org --deploy-hook 'systemctl reload nginx'`
   (site par défaut, comme à l'étape 13 ; `status.` sur l'autre machine), puis le bloc de l'outil tiré de
   `deploy/nginx/optional/suivi.conf.example` dans `/etc/nginx/conf.d/`, `sudo nginx -t`, `sudo systemctl reload nginx`,
   et `sudo certbot reconfigure --cert-name erreurs.vff-project.org --webroot -w /var/www/acme`.
4. `.env` de VFF : `HEALTH_TOKEN=$(openssl rand -hex 32)` (même valeur dans `VFF_HEALTH_TOKEN` de Kener),
   `SENTRY_LARAVEL_DSN=<DSN>`, `SENTRY_SAMPLE_RATE=0.5`, `APP_VERSION=$(git -C /srv/vff describe --tags --always)` ;
   puis `config:cache`.
5. Moniteurs de Kener : le tableau de `docs/SUIVI-PRODUCTION.md` (site, santé détaillée, API, GraphQL, tuiles, fond
   de carte, certificat), plus deux moniteurs « push » pour la sauvegarde (26 h) et l'essai de restauration
   (33 jours), dont les adresses vont dans `HEARTBEAT_URL` et `RESTORE_HEARTBEAT_URL` de `/etc/vff/backup.env`.

```bash
sudo deploy/bin/mise-en-production.sh --verifier --etape suivi
sudo -u vff php /srv/vff/artisan tinker --execute="report(new Exception('essai'))"   # doit paraître dans GlitchTip
```

- Contrôle : `/up` 200, `/up/details` 401 sans jeton et `"status":"ok"` avec, `status.` et `erreurs.` répondent.
- Retour arrière : `SENTRY_LARAVEL_DSN=` (aucun envoi), `config:cache` ; Kener est indépendant du site.

## 18. En-têtes de sécurité et surface publique (`entetes`)

```bash
sudo deploy/bin/mise-en-production.sh --verifier --etape entetes
```

Contrôle sur `/` (Laravel) et `/robots.txt` (nginx) : `Strict-Transport-Security`, `X-Content-Type-Options`,
`X-Frame-Options`, `Referrer-Policy` et `Permissions-Policy` une fois chacun ; `Content-Security-Policy` bloquante
(pas `Report-Only`) ; pas de version de nginx ; HTTP et `www` en 301 ; `/.env` en 404 ; l'API HTTP de Reverb
(`/apps/…`) non publique. L'étape lance aussi `deploy/bin/verify-production.sh` (unités, `/up/details`, WebSocket
101, journal de la file, planificateur).

- Retour arrière : selon l'en-tête, `deploy/nginx/snippets/vff-security-headers.conf` ou
  `App\Http\Middleware\SecurityHeaders` ; `CSP_ENFORCE=false` remet la politique en observation seule.

## 19. DNS (`dns`) : posé par Baptiste chez Infomaniak

Noter les valeurs actuelles avant de changer (retour arrière), baisser le TTL à 300 s la veille.

| Nom | Type | Valeur |
| --- | --- | --- |
| `vff-project.org` | A, AAAA | `SERVEUR_IPV4`, `SERVEUR_IPV6` |
| `www.vff-project.org` | CNAME | `vff-project.org.` |
| `erreurs.vff-project.org` | A, AAAA | `SERVEUR_IPV4`, `SERVEUR_IPV6` (GlitchTip) |
| `status.vff-project.org` | A, AAAA | adresses de la machine de Kener |
| `vff-project.org` | **MX** | `10 <serveur de réception de la messagerie d'UnxWares chez Hetzner>.` (**absent aujourd'hui** : `contact@` et `privacy@` ne reçoivent rien) |
| `vff-project.org` | TXT | `v=spf1 mx <mécanisme du relais d'envoi> -all` |
| `<sélecteur>._domainkey.vff-project.org` | TXT | clé DKIM donnée par la messagerie |
| `_dmarc.vff-project.org` | TXT | `v=DMARC1; p=quarantine; rua=mailto:contact@vff-project.org` |
| `vff-project.org` | CAA | `0 issue "letsencrypt.org"` et `0 iodef "mailto:contact@vff-project.org"` |

```bash
SERVEUR_IPV4=<…> SERVEUR_IPV6=<…> sudo -E deploy/bin/mise-en-production.sh --verifier --etape dns
```

- Messagerie (décision du 23/11) : celle d'UnxWares chez Hetzner, avec deux boîtes à créer, `contact@vff-project.org` et `privacy@vff-project.org` ; c'est la condition du courriel des notifications du site.
- Contrôle : chaque ligne `OK` ; un courriel envoyé à `contact@vff-project.org` et un autre à `privacy@vff-project.org` arrivent.
- Retour arrière : remettre les valeurs notées (TTL court : effet en quelques minutes).

## Fin du parcours et versions suivantes

```bash
sudo deploy/bin/mise-en-production.sh --verifier     # « Tout est en place »
```

Puis mentionner la page d'état dans le pied du site (une fois qu'elle fonctionne) et cocher la phase 12 de
`ROADMAP.md`. Pour une nouvelle version : sauvegarde à jour, puis

```bash
sudo -u vffdeploy git -C /srv/vff pull --ff-only
cd /srv/vff && sudo deploy/bin/mise-en-production.sh --appliquer --etape application
sudo deploy/bin/mise-en-production.sh --appliquer --etape migrations
sudo -u vff php artisan queue:restart     # le travailleur finit son travail puis repart sur le nouveau code
sudo systemctl reload vff-reverb          # reverb:restart, les navigateurs se reconnectent
```

Retour arrière d'une version : `git checkout <commit précédent>` et les deux mêmes étapes (sans migration
nouvelle ; avec migration, restauration de la sauvegarde, étape 8).

## Ce qui reste à décider par Baptiste

- La machine de production et celle de Kener (adresses).
- Décidé le 23/11 : la messagerie est celle d'UnxWares chez Hetzner (`contact@`, `privacy@`, étape 19) ; la destination des sauvegardes est Infomaniak Swiss Backup, compartiment à part, clés d'écriture seule (étape 7) ; `VFF_BASEMAP_ONLINE` passe à vrai à la mise en production, avec l'archive auto-hébergée (étape 15) et OpenFreeMap seulement en secours.
- Reste à fournir : le relais d'envoi et les deux boîtes de la messagerie, les clés du compartiment, les comptes, jetons et webhooks du poste à ne pas mettre en production (étape 4).

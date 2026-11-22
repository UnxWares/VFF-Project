# Déploiement : configuration nginx de référence

La configuration de référence du site `vff-project.org` est versionnée dans `deploy/nginx/`. Elle sert
les pages par PHP-FPM, envoie elle-même les fichiers statiques et l'archive PMTiles du fond de carte, et
pose pour ces fichiers les mêmes en-têtes de sécurité que Laravel (`SecurityHeaders`) pour ses réponses.
Elle n'est déployée par personne : c'est un modèle à copier sur le serveur et à adapter.

## Fichiers

| Fichier                                      | Rôle                                                                                   |
| -------------------------------------------- | -------------------------------------------------------------------------------------- |
| `deploy/nginx/vff-project.org.conf`          | Site complet : contexte http (journaux, limites, gzip) et trois blocs `server`         |
| `deploy/nginx/snippets/vff-security-headers.conf` | En-têtes de sécurité des fichiers statiques                                       |
| `deploy/nginx/snippets/vff-php.conf`         | Transmission à PHP-FPM                                                                 |
| `deploy/nginx/snippets/vff-tls.conf`         | Protocoles et chiffrements TLS                                                         |
| `deploy/nginx/snippets/vff-reverb.conf`      | Proxy WebSocket vers Laravel Reverb (temps réel, inclus seulement s'il est activé)     |
| `deploy/nginx/optional/brotli.conf.example`  | Compression brotli, si le module est installé                                          |

Installation : `vff-project.org.conf` dans `/etc/nginx/conf.d/`, `snippets/` dans `/etc/nginx/snippets/`,
puis `nginx -t` et rechargement. Le fichier contient des directives du contexte `http` (`map`,
`log_format`, `limit_req_zone`, `gzip`) : le placer dans `conf.d/` (inclus dans `http`), pas dans un bloc
`server`. Le dossier `/etc/nginx/vff-optional/` doit exister (vide ou non), le site en inclut les `*.conf`.

## Ce qu'il faut adapter

1. **Racine du dépôt** : `/srv/vff` (`root /srv/vff/public` et l'`alias` de `/_basemap/`).
2. **Socket PHP-FPM** : `upstream vff_php`, `unix:/run/php/php8.5-fpm-vff.sock`.
3. **Certificats** : `/etc/letsencrypt/live/vff-project.org/` (le certificat doit couvrir `www.` si le bloc
   de redirection `www` est gardé), et le dossier du défi ACME (`/var/www/acme`).
4. **Archive PMTiles** : l'`alias` doit être le dossier `VFF_BASEMAP_DIRECTORY` (par défaut
   `storage/app/data/pmtiles/`), avec la barre finale.
5. **`.env` de production** : `VFF_BASEMAP_ACCEL_REDIRECT=/_basemap/` (sinon les 6 Go passent par PHP) et
   `VFF_BASEMAP_ONLINE=true` si le fond libre doit servir la carte en ligne.
6. **php.ini** : `upload_max_filesize = 11M` et `post_max_size = 12M` (dépôts d'image de 10 Mo au plus).
7. **Résolveur DNS** de `vff-tls.conf` (agrafage OCSP) : mettre celui du serveur.
8. **Derrière un répartiteur ou un CDN** : `$remote_addr` serait celle du relais. Ajouter `set_real_ip_from`
   et `real_ip_header` (module realip), et déclarer le relais comme proxy de confiance dans Laravel.
9. **Vignettes de Commons** : `VFF_COMMONS_PROXY=true` et `VFF_COMMONS_PROXY_ACCEL_REDIRECT=/_commons/`, et
   l'`alias` de `/_commons/` égal au dossier `VFF_COMMONS_PROXY_CACHE_PATH` (voir la section « Relais des
   vignettes de Commons »).

## Bloc par bloc

### Contexte http

- **`upstream vff_php`** : un point d'entrée unique vers PHP-FPM, connexions gardées ouvertes.
- **Journaux sans adresse complète** (`map $remote_addr $vff_ip_anonyme`, `log_format vff`) : une adresse IPv4
  est écrite `a.b.c.0`, une IPv6 est coupée après deux groupes. Le chemin est écrit sans la chaîne de
  requête (`$uri` et non `$request_uri`), car elle peut contenir un jeton de réinitialisation ou une adresse
  électronique. L'agent utilisateur est gardé. La page de confidentialité annonce « adresse IP, page,
  date, navigateur, 12 mois au plus » : ce journal garde moins, ce qui est cohérent. La conservation reste
  à régler : par exemple `logrotate` mensuel avec `rotate 12` sur `/var/log/nginx/vff-*.log`. La
  limitation de débit utilise l'adresse réelle, en mémoire seulement.
- **`$vff_journaliser`** : aucun journal d'accès pour `/up` et `/up/details` (Uptime Kuma les appelle chaque
  minute). La condition `if=` est portée par `access_log`, donc elle vaut aussi après la redirection
  interne vers `index.php`. Les erreurs de nginx restent dans `vff-error.log`.
- **`limit_req_zone vff_basemap`** : 10 requêtes par seconde et par adresse pour l'archive, filet de sécurité
  devant PHP (les 600 par minute de `VFF_BASEMAP_REQUESTS_PER_MINUTE`).
- **gzip** : texte, JSON, JavaScript, SVG, tuiles vectorielles et glyphes. Pas pour l'archive PMTiles ni les
  images, déjà compressées. **Brotli** : l'image officielle nginx n'a pas le module ; voir
  `optional/brotli.conf.example` (modules à charger dans `nginx.conf`, fichier à copier dans
  `/etc/nginx/vff-optional/`). Sans lui, gzip seul suffit.
- **`server_tokens off`** : pas de numéro de version.

### Serveur HTTP (port 80)

Redirection 301 vers `https://vff-project.org` en gardant le chemin et la requête, sauf
`/.well-known/acme-challenge/` pour le renouvellement des certificats.

### Serveur `www` (port 443)

Redirection 301 vers le nom sans `www`. HTTP/2 s'active par `http2 on;` (nginx 1.25 et plus). HTTP/3 n'est
pas activé (UDP 443 à ouvrir et `listen 443 quic` à ajouter si souhaité).

### Serveur `vff-project.org`

- **Taille des corps** : `client_max_body_size 2m`, soit `api.max_body_bytes` (`API_MAX_BODY_BYTES`). C'est le
  plafond de GraphQL, MCP et l'API ; Laravel répond lui-même 413 avec un message clair. Si cette valeur
  change dans `.env`, changer aussi la ligne nginx. Les dépôts d'image (`/contribute/media` et
  `/contribute/series`, fichier de 10 Mo au plus) ont 12 Mo, dans la location d'origine **et** dans
  `@vff_php_upload` : nginx revérifie la limite dans la location finale après une redirection interne.
- **Fichiers cachés, `.php`, `build/manifest.json`** : 404. Seul `index.php` est atteint, par redirection
  interne (`internal`). `/.well-known/` reste permis.
- **`/up` et `/up/details`** : passent par Laravel. `/up/details` demande le jeton `HEALTH_TOKEN` (en-tête
  `Authorization: Bearer`) et son limiteur `health` ; voir `docs/SUIVI-ERREURS.md`. Le moniteur Uptime Kuma
  de `/up/details` doit envoyer cet en-tête.
- **`/build/assets/`** : fichiers hachés par Vite, donc `public, max-age=31536000, immutable`.
- **`/sw.js`, `/build/sw.js`, `/manifest.webmanifest`** : `no-cache` (revalidation à chaque chargement). Un
  service worker gardé trop longtemps empêcherait les mises à jour de la PWA. L'ETag de nginx rend la
  revalidation presque gratuite (304).
- **`/basemap/fonts/`** : glyphes `.pbf` des cartes, 30 jours.
- **Archive PMTiles** : `GET /basemap/<nom>.pmtiles` passe par Laravel (plages, limites, 416 si pas de
  `Range`), qui répond `X-Accel-Redirect: /_basemap/<nom>`. La location interne `/_basemap/` envoie les
  octets : nginx gère `Range` et `Accept-Ranges` pour un fichier statique, sans compression. Type MIME
  `application/octet-stream` (aucun type officiel), `Cache-Control`, `Content-Type` et `Accept-Ranges`
  viennent de Laravel, conservés lors de la redirection ; les en-têtes de sécurité sont ceux du snippet.
  Les lignes CORS sont en commentaire : à activer seulement si un autre site lit l'archive. Une requête
  directe vers `/_basemap/` renvoie 404 (`internal`).
- **`/media/commons/`** : vignettes de Wikimedia Commons relayées par VFF. La requête passe par Laravel, qui
  répond `X-Accel-Redirect: /_commons/<fichier>` ; la location interne `/_commons/` envoie le fichier du
  cache avec `Content-Security-Policy: default-src 'none'; sandbox` et `Cross-Origin-Resource-Policy:
  same-origin`. Une requête directe vers `/_commons/` renvoie 404 (`internal`).
- **`/storage/`** : fichiers déposés par des membres. En plus des en-têtes communs, `Content-Security-Policy:
  default-src 'none'; sandbox` : ouverts directement, ils ne peuvent rien exécuter.
- **`/docs/graphql`** : pages statiques de Magidoc, envoyées sans Laravel. nginx y pose donc la politique
  de sécurité du contenu, en observation (`Report-Only`), recopiée de celle de Laravel pour cette page
  (aucun `'unsafe-inline'` : scripts en fichiers, attributs `style` par empreinte). **Si `CSP_ENFORCE=true` ou `config/csp.php` change**, mettre à jour
  cette ligne (`docs/SECURITE-CSP.md`) : l'en-tête devient `Content-Security-Policy` en mode bloquant.
- **`/icons/`** : 30 jours. **`/`** (autres fichiers de `public/`, comme `robots.txt`, `favicon.ico`,
  `share/`) : un jour ; sinon la requête passe à Laravel.
- **PHP-FPM** : `location = /index.php` n'ajoute aucun `add_header`. Laravel pose HSTS, `nosniff`,
  `X-Frame-Options` (sauf `/embed/*`, intégrable ailleurs), `Referrer-Policy`, `Permissions-Policy` et la
  CSP des pages : les ajouter ici créerait des doublons. nginx ne met jamais en cache une réponse PHP.

### Pourquoi le snippet d'en-têtes est répété

`add_header` n'est hérité d'un niveau supérieur que si la location n'en déclare aucun. Chaque location
qui envoie un fichier inclut donc `vff-security-headers.conf` (HSTS un an sans `includeSubDomains`,
`nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy`, `Permissions-Policy`), avec `always` pour
qu'ils figurent aussi sur les 304. Si `SecurityHeaders::HSTS` ou `PERMISSIONS_POLICY` change, mettre ce
fichier à jour. Contrôle : `curl -sI https://vff-project.org/robots.txt` et
`curl -sI https://vff-project.org/` doivent montrer les mêmes cinq en-têtes, chacun une seule fois.

## Relais des vignettes de Commons

Sans relais, les fiches, le journal et les galeries chargent leurs images sur `upload.wikimedia.org` et
`thumb.wikimedia.org`. Le navigateur reçoit alors les cookies tiers de Wikimedia (`WMF-Uniq`,
`NetworkProbeLimit`) et Lighthouse retire environ 20 points en « bonnes pratiques ». Avec
`VFF_COMMONS_PROXY=true`, ces images sont servies par VFF à `/media/commons/<chemin>`
(`CommonsImageController`, `config/commons_proxy.php`) : le serveur les lit sur `upload.wikimedia.org` et les
garde sur disque. L'auteur, la licence et le lien vers la page Commons restent affichés.

Ce qui passe : seulement les vignettes aux largeurs utilisées par VFF (250, 330, 500, 568, 960 et 1 280 px)
et les originaux JPEG, PNG, GIF et WebP, avec un chemin dont les dossiers correspondent à l'empreinte MD5 du
nom du fichier, comme sur Commons. L'hôte lu est toujours `upload.wikimedia.org` en HTTPS, aucune adresse ne
vient de la requête et aucune redirection hors de la liste n'est suivie. Le type est vérifié sur l'en-tête et
sur le contenu : celui de l'extension, ou WebP pour une vignette (Commons convertit certaines vignettes JPEG
en WebP, plus légères). Un SVG n'est jamais servi (Commons en donne des vignettes PNG). Pas de session ni de
cookie sur ces réponses.

Réglages (`.env`) :

| Variable                                  | Défaut                     | Rôle                                                      |
| ----------------------------------------- | -------------------------- | --------------------------------------------------------- |
| `VFF_COMMONS_PROXY`                       | `false`                    | Active le relais ; désactivé, `/media/commons/` répond 404 |
| `VFF_COMMONS_PROXY_CACHE_PATH`            | `storage/app/cache/commons` | Dossier du cache (un `.nobackup` y est posé)              |
| `VFF_COMMONS_PROXY_CACHE_MB`              | `2048`                     | Taille maximale du cache                                  |
| `VFF_COMMONS_PROXY_TTL_DAYS`              | `30`                       | Durée de vie d'une vignette, et durée du cache navigateur |
| `VFF_COMMONS_PROXY_REQUESTS_PER_MINUTE`   | `600`                      | Requêtes par minute et par adresse IP                     |
| `VFF_COMMONS_PROXY_UPSTREAM_PER_MINUTE`   | `300`                      | Lectures sur Commons par minute pour tout le serveur      |
| `VFF_COMMONS_PROXY_UPSTREAM_PER_CLIENT`   | `120`                      | Les mêmes lectures, par adresse IP du visiteur            |
| `VFF_COMMONS_PROXY_MAX_BYTES`             | `8388608`                  | Poids maximal d'une image                                 |
| `VFF_COMMONS_PROXY_ACCEL_REDIRECT`        | vide                       | `/_commons/` en production : nginx envoie le fichier      |

Place disque : une vignette de 500 px pèse 65 Ko en moyenne et une de 1 280 px environ 330 Ko (échantillons
de 25 et 9 images, 22/11/2026). Le site affiche environ 71 000 vignettes de 500 px, soit 4,6 Go si toutes
étaient vues, et la visionneuse en ouvre en 1 280 px à la demande. 2 Go suffisent pour les pages les plus
lues ; prévoir 6 Go pour garder presque toutes les galeries.

Limite de Commons : Wikimedia refuse (429) une partie des vignettes absentes de son propre cache quand on
les demande vite (16 refus sur 25 vignettes de 1 280 px lues à 2,5 par seconde, le 22/11/2026 ; aucun sur
25 vignettes de 500 px, déjà calculées). Le relais s'arrête alors de lire Commons pendant le délai demandé
(`Retry-After`, 30 s par défaut, 5 min au plus), sert les copies qu'il a, même périmées, et répond 429 pour
les autres. Tous les visiteurs passent par la même adresse du serveur : surveiller ces 429 les premiers
jours, et baisser `VFF_COMMONS_PROXY_UPSTREAM_PER_MINUTE` s'ils sont fréquents. La tâche planifiée `vff:commons-cache-prune` (toutes les
heures, en production, relais activé) retire les vignettes périmées puis les plus anciennes au-delà de la
taille maximale, jusqu'à revenir à 90 %. Le relais fait aussi ce nettoyage, de temps en temps, après une
réponse. Le cache se reconstruit seul : il n'a pas besoin de sauvegarde.

Le dossier du cache doit être accessible en écriture par PHP-FPM et en lecture par nginx. Après
activation, la politique de sécurité du contenu (`img-src`) ne cite plus `upload.wikimedia.org` ni
`thumb.wikimedia.org` (`ContentSecurityPolicy::imageSources`).

## Temps réel (Laravel Reverb)

Le suivi des contributions en direct (`docs/API.md`, section « Temps réel ») est désactivé tant que la
production n'a pas de serveur temps réel. Pour l'activer :

1. **Processus Reverb** : `php artisan reverb:start` (il écoute `REVERB_SERVER_HOST:REVERB_SERVER_PORT`,
   par défaut `127.0.0.1:8080`), lancé et relancé par le superviseur du serveur (unité systemd ou
   Supervisor), comme le travailleur de file d'attente `php artisan queue:work`, qui envoie les
   événements. Après un déploiement : `php artisan reverb:restart`.
2. **`.env` de production** (modèle dans `.env.example`) :
   - `REALTIME_ENABLED=true` et `BROADCAST_CONNECTION=reverb` ;
   - `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET` : valeurs aléatoires propres au serveur (la clé
     est publique, le secret ne quitte jamais le serveur) ;
   - `REVERB_HOST=127.0.0.1`, `REVERB_PORT=8080`, `REVERB_SCHEME=http` : Laravel publie directement
     auprès de Reverb, sans passer par nginx ;
   - `REALTIME_CLIENT_HOST=vff-project.org`, `REALTIME_CLIENT_PORT=443`, `REALTIME_CLIENT_SCHEME=https` :
     l'adresse qu'ouvre le navigateur, celle de nginx ; la CSP l'admet d'elle-même
     (`wss://vff-project.org`) ;
   - plusieurs serveurs Reverb : `REVERB_SCALING_ENABLED=true`, ils se relaient les messages par Valkey
     (connexion `REDIS_*`).
3. **nginx** : décommenter `include /etc/nginx/snippets/vff-reverb.conf;` dans le bloc `vff-project.org`.
   Seul `/app/` (la connexion WebSocket) est transmis à Reverb ; son API HTTP (`/apps/…`) reste locale.
   Une adresse IP garde au plus 20 connexions WebSocket ouvertes (`limit_conn vff_websocket`, zone déclarée
   en tête du site) : Reverb n'a qu'un plafond global, `REVERB_APP_MAX_CONNECTIONS` : 1000 connexions simultanées
   par défaut (`config/reverb.php`, valeur du `.env.example`), jamais illimité ; à relever si le site a plus de
   1000 lecteurs en direct au même moment.
4. `php artisan config:cache`, puis rechargement de PHP-FPM et de nginx.

Contrôle : `curl --http1.1 -s -o /dev/null -w '%{http_code}\n' -H 'Connection: Upgrade' -H 'Upgrade: websocket' -H
'Sec-WebSocket-Version: 13' -H 'Sec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==' -H 'Origin: https://vff-project.org'
https://vff-project.org/app/<REVERB_APP_KEY>` doit répondre `101`. Reverb répond aussi `101` à une origine
étrangère, puis envoie `pusher:error` (code 4009, `Origin not allowed`) et ferme : le refus se lit dans le premier
message, pas dans le code HTTP. Sur `/validate`, la liste se met à jour quand un autre validateur approuve une
contribution.

Droits et connexions : un canal privé n'est autorisé qu'à l'abonnement. Quand un compte est suspendu, change de
rôle ou est supprimé, le site demande à Reverb de couper ses connexions (`terminate_connections`, par l'API HTTP
locale de Reverb, donc `REVERB_HOST` et `REVERB_PORT` doivent joindre Reverb depuis PHP-FPM). Le navigateur se
reconnecte et redemande ses canaux, que `/broadcasting/auth` refuse alors. Le navigateur s'identifie auprès de Reverb
par `/broadcasting/user-auth` (seul le numéro du compte est signé). Seuls les canaux privés sont autorisés :
`presence-…` et `private-encrypted-…` répondent 403.

## Contrôle de la syntaxe

Fait avec l'image officielle, configuration montée en lecture seule et certificats factices :

```bash
T=$(mktemp -d); touch "$T/.nobackup"; mkdir -p "$T/confd" "$T/vffopt" "$T/tls"
cp deploy/nginx/vff-project.org.conf "$T/confd/"
openssl req -x509 -newkey rsa:2048 -nodes -days 1 -subj /CN=vff-project.org \
  -keyout "$T/tls/privkey.pem" -out "$T/tls/fullchain.pem"
podman run --rm --network=none \
  -v "$T/confd:/etc/nginx/conf.d:ro,Z" -v "$PWD/deploy/nginx/snippets:/etc/nginx/snippets:ro,Z" \
  -v "$T/vffopt:/etc/nginx/vff-optional:ro,Z" -v "$T/tls:/etc/letsencrypt/live/vff-project.org:ro,Z" \
  docker.io/library/nginx:1.30-alpine nginx -t
```

Résultat attendu : `syntax is ok` et `test is successful`, avec l'avertissement `ssl_stapling ignored`
(certificat factice, sans serveur OCSP). Un essai avec un faux `public/` a aussi vérifié les en-têtes, les
caches, les redirections 301 et les journaux (adresse tronquée, `/up` absent, requête retirée du chemin).
Les chemins PHP-FPM et PMTiles n'ont pas pu être essayés sans serveur PHP : à contrôler au déploiement.

## Contrôles au déploiement

```bash
curl -sI https://vff-project.org/build/assets/<un fichier>.js   # immutable + 5 en-têtes de sécurité
curl -sI https://vff-project.org/sw.js                          # no-cache
curl -s -o /dev/null -w '%{http_code}\n' -r 0-16383 https://vff-project.org/basemap/<archive>.pmtiles   # 206
curl -sI https://vff-project.org/up/details                     # 401 sans jeton
curl -sI https://vff-project.org/media/commons/7/79/CFA-Morlaix.jpg   # 200 image/jpeg, sandbox, sans Set-Cookie
curl -s -o /dev/null -w '%{http_code}\n' https://vff-project.org/_commons/x  # 404 (location interne)
curl -s -o /dev/null -w '%{http_code}\n' -X POST -H 'Content-Type: application/json' \
  --data-binary @gros-fichier.json https://vff-project.org/graphql   # 413 au-delà de 2 Mio
```

Planificateur : poser `schedule:run` chaque minute (cron ou minuteur systemd, `docs/PLANIFICATION.md`), puis
vérifier que `checks.scheduler.status` de `/up/details` (avec le jeton) passe à `ok`.

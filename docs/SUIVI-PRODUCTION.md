# Suivi en production : disponibilité (Kener) et erreurs (GlitchTip)

Décision du 22/11/2026 : pas d'Uptime Kuma. La disponibilité est suivie par **Kener** (kener.ing, page d'état
publique), les erreurs par un outil compatible Sentry, **GlitchTip** (recommandation argumentée plus bas, Bugsink
en solution de repli). Ce que l'application fournit pour être surveillée (`/up`, `/up/details`, DSN, erreurs
JavaScript) est décrit dans `docs/SUIVI-ERREURS.md` ; ici, l'hébergement et le branchement. Aucun code de
l'application ne change : tout est déjà en place.

Sources lues le 22/11/2026 : documentation de Kener v4 (<https://kener.ing/docs>, pages Quick Start, Deployment,
Monitors, API monitor, Alerting, Incidents), dépôt <https://github.com/rajnandan1/kener>, documentation de GlitchTip
(<https://glitchtip.com/documentation/install>), documentation de Bugsink et sa comparaison
(<https://www.bugsink.com/blog/bugsink-vs-glitchtip/>, écrite par l'auteur de Bugsink, donc à lire avec recul).

## 1. Kener : disponibilité et page d'état

### Ce qu'est Kener

Page d'état et supervision auto-hébergées, licence MIT, SvelteKit sur Node.js (24.14 ou plus). Image Docker
`rajnandan1/kener` (Docker Hub et GHCR). Il lui faut un **Redis** (`REDIS_URL`) ; la base de Kener est SQLite par
défaut, PostgreSQL ou MySQL sur demande (`DATABASE_URL`). Variables obligatoires : `KENER_SECRET_KEY` (secret long),
`ORIGIN` (adresse publique, sans barre finale) et `REDIS_URL`. Port 3000, `KENER_BASE_PATH` pour un sous-chemin
(`/status`). Sa propre sonde de santé : `/healthcheck` (toujours 200 ; `?strict=1` renvoie 503 si un composant
est en panne, à ne pas employer pour redémarrer automatiquement).

À vérifier à l'installation : la documentation ne dit pas que **Valkey** est pris en charge, seulement Redis. Valkey
parle le protocole Redis, donc le Valkey de VFF ou une instance dédiée devrait convenir ; ne pas partager l'instance
(Kener a ses propres clés) et essayer avant de s'engager.

Emplacement : `status.vff-project.org` (sous-domaine, plus simple qu'un sous-chemin), sur une autre machine ou au
moins un autre conteneur que le site : une page d'état hébergée sur le serveur qu'elle surveille tombe avec lui.

### Les sondes

Un moniteur de type **API** (requête HTTP) par ligne ci-dessous. Champs : `url`, `method`, `headers`, `body`,
`timeout` (10 000 ms par défaut), `eval`. L'évaluation par défaut compte en service un code 2xx, 3xx ou 429 ; une
fonction `eval` reçoit `statusCode`, `responseTime`, `responseRaw` et renvoie `{ status, latency }` avec `status` égal
à `UP`, `DEGRADED`, `DOWN` ou `MAINTENANCE`. Elle tourne dans un bac à sable (10 s, 256 Mo, sans `fetch`). Les valeurs
des en-têtes, du corps et de l'adresse peuvent venir de variables d'environnement de Kener (`$NOM`) et sont masquées
dans l'interface et l'API : le jeton de santé n'apparaît jamais à l'écran. Fréquence : expression cron
(`* * * * *` = chaque minute).

| Sonde | Requête | Fréquence | Ce qui compte comme panne |
| --- | --- | --- | --- |
| Site | `GET https://vff-project.org/up` | 1 min | tout sauf 200 |
| Santé détaillée | `GET https://vff-project.org/up/details`, en-tête `Authorization: Bearer $VFF_HEALTH_TOKEN` | 1 min | 503 ou 401 : panne ; `"status":"degraded"` : dégradé (fonction ci-dessous) |
| API REST | `GET https://vff-project.org/api/v1/lines?per_page=1` | 5 min | tout sauf 200, ou réponse sans `data` |
| GraphQL | `POST https://vff-project.org/graphql`, corps `{"query":"{ __typename }"}`, `Content-Type: application/json` | 5 min | tout sauf 200 |
| Tuiles des tracés | `GET https://vff-project.org/api/v1/eras/2030/tiles/6/32/22.mvt?fields=map` (une tuile qui couvre la France) | 5 min | tout sauf 200 ; dégradé au-delà de 1 500 ms |
| Fond de carte | `GET https://vff-project.org/basemap/france-20261120.pmtiles` avec l'en-tête `Range: bytes=0-16383` | 5 min | tout sauf 206 (l'archive est servie par plages, jamais en entier : une requête sans `Range` reçoit 416) |
| Carte (page) | `GET https://vff-project.org/maps` | 5 min | tout sauf 200 |
| Certificat | moniteur de type SSL sur `vff-project.org` | 1 jour | expiration sous 14 jours |

Évaluation de la santé détaillée (le code 200 vaut pour `ok` et `degraded`, c'est le corps qui tranche) :

```js
(async function (statusCode, responseTime, responseRaw) {
    if (statusCode !== 200) return { status: 'DOWN', latency: responseTime };
    let body = {};
    try { body = JSON.parse(responseRaw); } catch (e) { return { status: 'DOWN', latency: responseTime }; }
    if (body.status === 'ok') return { status: 'UP', latency: responseTime };
    if (body.status === 'degraded') return { status: 'DEGRADED', latency: responseTime };
    return { status: 'DOWN', latency: responseTime };
})
```

(La signature exacte de la fonction est donnée dans la page « API monitor » de la version de Kener installée ; la
recopier de là.) Le nom de l'archive du fond de carte change à chaque mise à jour (`france-AAAAMMJJ.pmtiles`) : mettre
la sonde à jour avec elle. Les sondes sont espacées pour rester très en dessous des limites de VFF (60 demandes par
minute sur `/up/details`, 600 par minute et 512 Mio par heure sur l'archive) ; nginx ne journalise pas `/up`
ni `/up/details`.

Variables côté VFF : `HEALTH_TOKEN` (généré par `openssl rand -hex 32`, le même dans `VFF_HEALTH_TOKEN` côté Kener)
et `APP_VERSION`. Rien d'autre.

### Alertes et incidents

- Alertes de Kener : types STATUS (DOWN ou DEGRADED), LATENCY, UPTIME ; canaux courriel, Slack, Discord et webhook.
  Seuils de déclenchement et de retour (`TRIGGERED`, `RESOLVED`) réglables. À régler : 3 échecs de suite avant alerte
  pour la santé détaillée.
- Incidents : états Investigating, Identified, Monitoring, Resolved ; chaque moniteur touché est marqué DOWN ou
  DEGRADED ; les incidents ouverts s'affichent sur la page d'état publique. Une alerte peut en **ouvrir un
  automatiquement** (option de création d'incident). Création à la main dans `/manage/incidents`.
- API d'incidents : `POST /api/incidents` (titre, état, impact, message) et `POST /api/incidents/{id}/updates`,
  authentification `Authorization: Bearer <clé d'API>`. Attention : la documentation de Kener se contredit sur le
  chemin selon les pages et les versions (`/api/incident`, `/api/incidents`, `/api/v4/incidents`) ; consulter la
  référence REST de la version installée (`/docs/spec/v4/`) avant d'écrire quoi que ce soit.

## 2. Outil d'erreurs : GlitchTip ou Bugsink

L'application envoie déjà ses erreurs au format Sentry (`sentry/sentry-laravel`, inactif sans `SENTRY_LARAVEL_DSN`,
données nettoyées par `SentryScrubber`). Les deux outils acceptent ce protocole : le choix ne touche pas le code.

| | GlitchTip | Bugsink |
| --- | --- | --- |
| Licence | **MIT** (logiciel libre) | **Polyform Shield** (code disponible, non libre : interdit de l'offrir comme service concurrent) |
| Pile | Python/Django, frontend séparé, **PostgreSQL 14+** obligatoire, Valkey ou Redis 7+ facultatif (sans lui, PostgreSQL fait file, cache et sessions), travailleur en arrière-plan intégré à l'image | Un seul conteneur Django ; SQLite par défaut, MySQL ou PostgreSQL possibles |
| Mémoire | recommandé 512 Mo, plancher 256 Mo en tout-en-un ; environ 30 Go de disque pour 1 million d'évènements par mois | annoncé très léger (1,5 million d'évènements par jour sur un petit serveur à 5 euros, selon son auteur) |
| Entretien | plusieurs composants à mettre à jour, migrations parfois plus lourdes, documentation plus brève | mise à jour = migrations et redémarrage |
| Alertes | règles par projet (fréquence), courriel et webhook | courriel, Slack, Mattermost, Discord, Teams, Telegram, webhook JSON |
| Étendue | erreurs, performances, contrôles de disponibilité, journaux | erreurs seulement, volontairement |
| Plan gratuit hébergé | utilisateurs illimités | un seul utilisateur (hébergé) |

Les chiffres de mémoire des deux outils viennent de leurs propres pages et d'articles comparatifs qui ne
s'accordent pas toujours : les mesurer sur le serveur avant de dimensionner.

### Recommandation : GlitchTip

1. **Licence.** VFF choisit le logiciel libre partout (Valkey plutôt que Redis, données ouvertes, ODbL) et passera sous une
   association. Une licence MIT n'y ajoute aucune condition ; Polyform Shield est une licence « source disponible » qui
   limite l'usage et sort Bugsink du libre au sens strict.
2. **Pile connue.** VFF tourne déjà sur PostgreSQL et Valkey : GlitchTip n'apporte aucune technologie nouvelle et ses
   sauvegardes suivent celles de PostgreSQL (restic couvre déjà le reste).
3. **Étendue en réserve.** Il sait aussi surveiller la disponibilité et les performances, au cas où Kener ne
   suffirait pas ; on ne s'en sert pas au départ (pas de doublon avec Kener, traces désactivées côté application).

Contre : plus de composants et plus de mémoire que Bugsink. **Bugsink est le bon choix** si le serveur d'erreurs doit
tenir sur une toute petite machine, ou si personne ne veut entretenir PostgreSQL pour cet usage : un conteneur,
SQLite, et le même DSN. Basculer de l'un à l'autre ne demande que de changer `SENTRY_LARAVEL_DSN`.

### Variables d'environnement

Côté VFF (`.env` de production, puis `php artisan config:cache`) :

| Variable | Valeur |
| --- | --- |
| `SENTRY_LARAVEL_DSN` | `https://<clé>@erreurs.vff-project.org/<projet>` (donné par l'outil, projet « VFF ») |
| `SENTRY_SAMPLE_RATE` | `0.5` (part des erreurs envoyées, de 0 à 1) |
| `APP_VERSION` | étiquette ou court identifiant de commit du déploiement |
| `HEALTH_TOKEN` | jeton de `/up/details` (partagé avec Kener) |

Côté GlitchTip : `SECRET_KEY`, `GLITCHTIP_DOMAIN`, `DATABASE_URL`, `EMAIL_URL` (courriel d'alerte), et au besoin
`VALKEY_URL`, `ALLOWED_HOSTS`, `CSRF_TRUSTED_ORIGINS` ; dans PostgreSQL, `max_locks_per_transaction = 512`.
Côté Kener : `KENER_SECRET_KEY`, `ORIGIN`, `REDIS_URL`, `DATABASE_URL` (facultatif) et `VFF_HEALTH_TOKEN`.

## 3. Kener peut-il afficher un incident ouvert par l'outil d'erreurs ?

**Pas directement.** Ni GlitchTip ni Bugsink ne parlent à Kener, et la documentation de Kener ne décrit aucune
réception de webhook quelconque (seulement des alertes sortantes, des moniteurs « heartbeat » à pousser et l'API
d'incidents). Trois voies, de la plus simple à la plus coûteuse :

1. **Ne pas le faire (recommandé).** Une erreur n'est pas une indisponibilité : un bogue sur une page n'est pas une panne.
   Les erreurs vont au courriel de l'équipe via GlitchTip ; la page publique ne montre que les pannes que les sondes
   voient (une erreur grave qui casse une page finit par se voir dans `/up/details` ou dans la sonde de l'API).
2. **Une sonde sur GlitchTip.** Un moniteur API de Kener interroge l'API de GlitchTip (liste des problèmes non résolus
   du projet, jeton d'API en en-tête, `$VARIABLE`) et sa fonction `eval` renvoie `DEGRADED` au-delà d'un seuil de
   problèmes ouverts. Rien à développer, mais le seuil est grossier et le moniteur apparaît tel quel sur la page
   publique (le placer dans un groupe réservé à l'équipe si Kener le permet).
3. **Un petit relais.** Webhook d'alerte de GlitchTip vers un script qui appelle `POST /api/incidents` de Kener
   (puis `/updates` pour résoudre). Faisable, mais c'est du code à entretenir, sur des chemins d'API que la
   documentation ne fixe pas : à réserver à un besoin réel.

À l'inverse, c'est Kener qui sait ouvrir des incidents tout seul à partir de ses sondes (alerte avec création
d'incident) : c'est la bonne source pour la page publique.

## Mise en route

Ces points sont l'étape 17 du parcours `docs/MISE-EN-PRODUCTION.md`, qui donne les commandes exactes.

1. Installer GlitchTip (avec PostgreSQL) sur le serveur de VFF, sur `erreurs.vff-project.org`, et Kener (avec une
   instance Valkey à lui) sur une autre machine, sur `status.vff-project.org` (blocs nginx des deux :
   `deploy/nginx/optional/suivi.conf.example`).
2. Générer `HEALTH_TOKEN`, le poser côté VFF et côté Kener.
3. Créer les moniteurs du tableau, le canal d'alerte par courriel et la création automatique d'incident sur la
   santé détaillée et le site.
4. Créer le projet GlitchTip « VFF », copier le DSN dans `SENTRY_LARAVEL_DSN`, provoquer `report(new Exception('essai'))`
   depuis `php artisan tinker` : l'erreur doit apparaître.
5. Sauvegardes : base de GlitchTip (PostgreSQL) et base de Kener (SQLite ou PostgreSQL) ; ni l'une ni l'autre ne
   contient de donnée de compte de VFF.
6. Mentionner la page d'état dans le pied du site seulement une fois qu'elle fonctionne.

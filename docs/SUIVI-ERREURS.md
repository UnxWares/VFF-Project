# Suivi des erreurs et de la disponibilité

Ce que l'application fournit pour être surveillée, et comment brancher les outils auto-hébergés d'UnxWares
(GlitchTip ou Bugsink pour les erreurs, Kener pour la disponibilité ; hébergement et sondes dans `docs/SUIVI-PRODUCTION.md`). Rien ne dépend d'eux : sans outil,
l'application écrit ses erreurs dans des journaux et répond à `/up` et `/up/details`.

## Ce qui est en place dans l'application

| Besoin | Mécanisme | Réglage |
| --- | --- | --- |
| Le site répond-il ? | `GET /up` (texte brut « OK », sans ressource extérieure) | aucun |
| Tout va-t-il bien ? | `GET /up/details` (JSON) | `HEALTH_TOKEN` |
| Erreurs du serveur | `sentry/sentry-laravel`, inactif sans adresse DSN (`config/sentry.php`) | `SENTRY_LARAVEL_DSN` |
| Erreurs du serveur en fichier | canal de journal `json-errors` (`storage/logs/errors.json.log`, 30 jours) | `LOG_STACK=daily,json-errors` |
| Erreurs JavaScript du navigateur | `POST /api/client-errors`, journal `client-errors` (JSON, 30 jours) | `CLIENT_ERRORS_PER_MINUTE`, `CLIENT_ERRORS_SAMPLE_RATE` |
| Violations de la CSP | `POST /api/csp-report`, journal `csp` (voir `docs/SECURITE-CSP.md`) | `CSP_REPORT` |

## Page de santé `/up/details`

Réponse JSON, jamais mise en cache (`Cache-Control: no-store`), limitée à 60 demandes par minute et par adresse IP
(`HEALTH_PER_MINUTE`).

- Sans `HEALTH_TOKEN` : `{"status": "ok"}` seulement (ou `degraded`, `down`).
- Avec `HEALTH_TOKEN` : le jeton est exigé, en en-tête `Authorization: Bearer <jeton>` ou en paramètre `?token=`. Absent ou faux :
  401. Présent : `status`, `release` (version déployée, `APP_VERSION`) et `checks`.

Contrôles : `database` (requête `select 1`, durée en ms), `valkey` (PING, seulement si le cache, la file ou la session
l'utilisent), `queue` (travaux en attente et échoués), `disk` (Mo libres et pourcentage utilisé du disque de `storage`),
`era` (âge en heures du dernier calcul de l'ère courante, lu sur la dernière mise à jour de ses tronçons, mis en cache
dix minutes).

Codes utiles au moniteur : **200** si `ok` ou `degraded` (un seuil est dépassé : file de plus de 1000 travaux,
plus de 50 échecs, moins de 1024 Mo libres, calcul de l'ère de plus de 48 h ; réglables par `HEALTH_QUEUE_MAX_WAITING`,
`HEALTH_FAILED_JOBS_MAX`, `HEALTH_DISK_MIN_FREE_MB`, `HEALTH_ERA_MAX_AGE_HOURS`), **503** si la base de données ou Valkey
ne répond pas, **401** si le jeton manque, **429** au-delà du débit. La réponse ne contient jamais de chemin, de nom de
machine, de message d'erreur ni de donnée de compte.

## Brancher Kener

Voir `docs/SUIVI-PRODUCTION.md` (sondes sur `/up`, `/up/details` avec `HEALTH_TOKEN`, l'API, les tuiles et le fond de
carte ; alertes et incidents). Côté VFF : générer un jeton long (`openssl rand -hex 32`), le poser dans `HEALTH_TOKEN`.

## Brancher GlitchTip ou Bugsink (erreurs du serveur)

1. Installer l'outil auto-hébergé (GlitchTip : conteneurs `glitchtip/glitchtip` avec PostgreSQL et Valkey ; Bugsink :
   conteneur unique `bugsink/bugsink`, plus léger). Les deux parlent le protocole Sentry.
2. Créer un projet « VFF » et copier son adresse DSN.
3. Dans `.env` de production : `SENTRY_LARAVEL_DSN=https://<clé>@erreurs.<domaine>/<projet>`, `APP_VERSION=<étiquette ou
   court identifiant de commit>`, `SENTRY_SAMPLE_RATE=0.5` (part des erreurs envoyées, de 0 à 1), puis
   `php artisan config:cache`.
4. Vérifier : provoquer une erreur de test depuis `php artisan tinker` (`report(new Exception('essai'))`), elle doit
   apparaître dans l'outil.
5. Sans adresse DSN, le paquet est inactif : aucune connexion sortante.

En complément, `LOG_STACK=daily,json-errors` écrit chaque erreur en une ligne JSON dans `storage/logs/errors.json.log`,
lisible par n'importe quel collecteur de journaux auto-hébergé (Loki, Vector).

## Erreurs JavaScript du navigateur

`resources/js/lib/clientErrors.js` écoute `error` et `unhandledrejection` et envoie une charge utile bornée à
`POST /api/client-errors`. Cinq envois au plus par page chargée, chaque erreur une seule fois, échantillonnage par
`VITE_CLIENT_ERRORS_SAMPLE_RATE` (défaut 1). Côté serveur : corps de 8 Ko au plus, 10 envois par minute et par adresse IP,
réponse toujours vide (204, ou 429 au-delà du débit). Le contrôleur ne garde que le type, le message (300 caractères,
adresses électroniques et longs jetons remplacés), l'adresse de la page et du script sans requête (un segment de chemin
de 32 caractères ou plus, comme le jeton de `/reset-password/…`, devient `[token]`), la ligne, la colonne (entiers positifs,
sinon vides), dix lignes de pile et la version. Les lignes se lisent dans `storage/logs/client-errors-AAAA-MM-JJ.log`. Si un jour le SDK
navigateur de l'outil est préféré, il suffit de le charger à la place de ce petit guetteur, avec les mêmes règles.

## Ce qui est envoyé, ce qui ne l'est jamais

Envoyé à l'outil de suivi (seulement si l'adresse DSN est renseignée) : type, message et pile de l'erreur, méthode et
chemin de la page (sans paramètres, jeton du chemin remplacé par `[token]`), version, environnement. Les messages d'erreur de base de données sont coupés avant la
requête SQL et ses valeurs.

Jamais envoyé, retiré par `App\Support\Monitoring\SentryScrubber` en plus de `send_default_pii = false` : adresse IP,
compte, cookies, en-têtes, corps et paramètres de la requête, arguments des fonctions de la pile d'appels (un mot de
passe passé à une fonction y figurerait quand `zend.exception_ignore_args` est désactivé), nom de la machine, contexte
du système, étiquettes,
fil d'actions (requêtes SQL, journaux, cache, appels sortants), traces de performance, profils. Les exceptions de routine
(page introuvable, validation, authentification, trop de requêtes) ne sont pas envoyées.

La page de confidentialité (`lang/*/privacy.php`, section « visitors ») le mentionne dans les trois langues. Si la
mention est publiée, la date de version `privacy` de `config/legal.php` se change en même temps (`php artisan
vff:notify-legal-change`).

## Ce qui reste pour l'hébergement

Dans l'ordre, avec les commandes : `docs/MISE-EN-PRODUCTION.md` (étapes 10, 11 et 17).

- Installer Kener et GlitchTip (ou Bugsink) sur l'infrastructure d'UnxWares (ressources, sauvegardes, accès).
- Poser `HEALTH_TOKEN`, `SENTRY_LARAVEL_DSN`, `APP_VERSION` en production et configurer les moniteurs et alertes.
- Choisir l'outil d'alerte (courriel, canal d'équipe auto-hébergé).
- Faire tourner le planificateur et un travailleur de file sur le serveur (sinon `queue` reste à zéro sans signification).

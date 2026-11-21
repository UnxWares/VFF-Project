# Politique de sécurité du contenu (CSP)

Depuis le 20/11/2026, chaque page HTML du site porte une politique de sécurité du contenu **en
observation** : l'en-tête est `Content-Security-Policy-Report-Only`. Le navigateur ne bloque rien.
Quand une page charge une ressource que la politique n'autorise pas, il envoie un rapport à
`/api/csp-report`.

## Où se trouve quoi

| Élément                                              | Fichier                                                                               |
| ---------------------------------------------------- | ------------------------------------------------------------------------------------- |
| Réglages (mode, rapports, origines externes admises) | `config/csp.php`                                                                      |
| Construction de la politique, nonce, exceptions      | `app/Support/Security/ContentSecurityPolicy.php`                                      |
| Pose de l'en-tête (pages du groupe web)              | `app/Http/Middleware/SecurityHeaders.php`                                             |
| Réception des rapports                               | `app/Http/Controllers/CspReportController.php`, route `csp.report` (`routes/api.php`) |
| Journal des rapports                                 | `storage/logs/csp-AAAA-MM-JJ.log` (canal `csp`, 30 jours)                             |
| Tests                                                | `tests/Feature/Security/ContentSecurityPolicyTest.php`                                |

## La politique

- Scripts : ceux du site, et les scripts en ligne qui portent le nonce de la requête. Vite pose le
  nonce sur ses balises ; les scripts en ligne des vues Blade le lisent avec `Vite::cspNonce()`
  (`app.blade.php`, `docs/_head.blade.php`, `errors/fallback.blade.php`). Un nouveau script en ligne
  doit faire de même, sinon il sera signalé, puis bloqué une fois la politique appliquée.
- Exceptions :
    - carte : tuiles OpenStreetMap, style MapTiler (si une clé est configurée), glyphes OpenFreeMap,
      cartes historiques de l'IGN (`connect-src` et `img-src`), workers de MapLibre en `blob:` ;
    - images de Wikimedia Commons et de Gallica (`img-src`) ;
    - Scalar (`/docs/api`) et Swagger UI (`/docs/swagger`) sont servis par Vite : rien de plus que les
      styles en ligne ;
    - Magidoc (`/docs/graphql`) : pages statiques générées avec des scripts en ligne sans nonce, donc
      `'unsafe-inline'` pour les scripts de ces pages seulement ;
    - pages intégrables (`/embed/*`) : `frame-ancestors *` ;
    - développement : dans l'environnement `local`, quand Vite tourne (fichier `public/hot`), son
      adresse et sa connexion WebSocket sont admises (le schéma seul, `http:` et `ws:`, quand
    Vite écoute sur une adresse IPv6 comme `[::1]`, que la syntaxe CSP n'accepte pas).
- Styles en ligne admis partout (`'unsafe-inline'` sur `style-src`) : Svelte, MapLibre, Scalar et
  Swagger UI en posent.
- `object-src 'none'`, `base-uri 'self'`, `form-action 'self'`, `frame-ancestors 'self'`.

En production, le serveur web sert directement les fichiers de `public/` (dont Magidoc, avec
`try_files $uri $uri.html`) sans passer par Laravel : ces fichiers ne reçoivent l'en-tête que si le
serveur web le pose lui-même. Pour Magidoc, reprendre la politique de `/docs/graphql` (celle que
renvoie `curl -sI https://vff-project.org/docs/graphql`) dans la configuration du serveur web.

## Rapports

Les navigateurs envoient deux formats, tous deux acceptés : `application/csp-report` (directive
`report-uri`) et `application/reports+json` (directive `report-to`, en-tête `Reporting-Endpoints`).

Le journal ne garde aucune donnée personnelle : ni adresse IP, ni navigateur, ni compte. L'adresse
de la page et celle du script sont réduites à l'origine et au chemin (une requête peut contenir un
jeton ou une adresse électronique), la ressource bloquée à son origine ou à un mot-clé (`inline`,
`eval`, `data`, `blob`). L'extrait de code joint par le navigateur n'est jamais gardé. La politique
de confidentialité le décrit (section « Si vous consultez le site sans compte »).

Débit : 30 rapports par minute et par adresse IP (`CSP_REPORTS_PER_MINUTE`), réponse 429 au-delà ;
corps de 16 Ko au plus, dix rapports au plus par envoi.

Pour lire les rapports :

```bash
grep -h csp-violation storage/logs/csp-*.log | sed 's/.*csp-violation //' | sort | uniq -c | sort -rn | head -30
```

## Rendre la politique bloquante

1. Laisser la politique en observation en production au moins deux semaines, avec du trafic réel
   (carte, fiches, documentation de l'API, contributions, profil).
2. Relire les rapports regroupés (commande ci-dessus). Pour chaque origine légitime, l'ajouter à la
   directive voulue dans `config/csp.php` (`sources`). Pour un script en ligne du site, lui donner
   le nonce. Les rapports venant d'extensions du navigateur (`chrome-extension`, `moz-extension`)
   sont à ignorer.
3. Lancer `bin/php artisan test tests/Feature/Security/ContentSecurityPolicyTest.php`.
4. Mettre `CSP_ENFORCE=true` dans `.env` puis `php artisan config:cache`. L'en-tête devient
   `Content-Security-Policy`, et les rapports continuent d'arriver pour ce qui est désormais bloqué.
5. Vérifier à la main la carte (fond, cartes historiques, vue « Schéma »), une galerie d'images,
   `/docs/api`, `/docs/swagger`, `/docs/graphql`, `/graphiql` et une page intégrée `/embed/maps`.

Pour revenir en arrière : `CSP_ENFORCE=false`, puis `php artisan config:cache`.

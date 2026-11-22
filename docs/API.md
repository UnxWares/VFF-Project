# Les trois interfaces de données : REST, GraphQL, MCP

VFF publie ses données par trois interfaces en lecture. Elles lisent les mêmes services, donc les mêmes valeurs et les
mêmes noms de champs ; ce document dit où trouver chaque donnée et ce qui change d'une interface à l'autre.

| Interface | Adresse | Documentation | Où elle est décrite dans le code |
|---|---|---|---|
| REST (JSON, GeoJSON, tuiles) | `/api/v1/...` | `/docs/api` (Scalar), `/docs/swagger`, `/docs/api.json` | Docblocks et attributs Scramble des contrôleurs de `app/Http/Controllers/Api/` |
| GraphQL | `POST /graphql` | `/docs/graphql` (Magidoc), `/graphiql` | Descriptions de `graphql/schema.graphql` |
| Serveur MCP | `POST /mcp` | `docs/MCP.md`, page `/api` | `#[Description]` et `arguments()` de `app/Mcp/Tools/` |

Régénérer la référence GraphQL après un changement du schéma : `make docs-graphql`. La spécification OpenAPI se lit
sur `/docs/api.json` : elle est calculée à chaque requête, rien à régénérer.

Les changements qui touchent les utilisateurs de l'API sont datés dans `docs/API-CHANGELOG.md`. Après un ajout public, mettre à jour l'instantané
du contrat avec `bin/php artisan vff:api-contract` (le test `ApiContractTest` échoue si un champ disparaît sans avoir été déprécié).

## Limites communes

- Débit : 300 requêtes par minute sans jeton, 1 200 avec un jeton personnel (`config/api.php`).
- GraphQL : 5 000 points de complexité et 15 niveaux au plus, refus avant exécution (`query_too_complex`,
  `query_too_deep`) ; barème dans la description du type `Query`.
- Lots GraphQL : un `POST /graphql` dont le corps est une liste de requêtes compte 5 opérations au plus
  (`api.graphql_max_batch`, `API_GRAPHQL_MAX_BATCH`). Au-delà : HTTP 400, `extensions.code` `batch_too_large` et
  `extensions.max_batch`. Les mutations d'un lot sont comptées une à une par le limiteur de débit. MCP n'accepte pas
  les lots : une requête JSON-RPC par appel.
- Taille du corps : 2 Mio au plus pour `/graphql` et `/mcp` (`api.max_body_bytes`, `API_MAX_BODY_BYTES`). Au-delà :
  HTTP 413 avant tout traitement, au format GraphQL (`errors[].extensions.code` `body_too_large`, `max_body_bytes`)
  ou JSON-RPC pour MCP (`error.code` -32600). Les deux refus (`App\Http\Middleware\LimitApiRequest`) sont couverts par
  `tests/Feature/GraphQL/RequestLimitsTest.php`. Le serveur web doit laisser passer 2 Mio (`docs/DEPLOIEMENT-NGINX.md`).
- Authentification du site (formulaires web, pas l'API) : connexion 20 requêtes par minute et par adresse IP (en plus
  de 5 par minute pour un couple e-mail et IP), inscription 10 par heure, demande et réinitialisation de mot de passe
  20 par heure ; au-delà HTTP 429 (`AppServiceProvider`, `tests/Feature/Web/AuthIpThrottleTest.php`).
- Pagination : par curseur (`cursor`, `next_cursor`) pour les annuaires, les événements, les médias et le matériel ;
  par page (`page`) pour les accidents et les passages à niveau ; par `limit` et `offset` pour l'audit des tronçons
  (50 par défaut, 200 au plus, la même règle dans les trois interfaces : `App\Services\SegmentAudit\AuditPage`).
  Les listes d'audit du réseau (`App\Services\SegmentAudit\NetworkAudits`) suivent la même règle et rendent `total`, `limit`, `offset`, `items`. Coût GraphQL des pages d'audit : sous-champs multipliés par `limit`.
- Un message de soutien d'une ligne (`lines.advocacy`, encarts de l'accueil) n'est publié par aucune interface :
  c'est une position éditoriale du site, pas une donnée (test `AdvocacyNotPublishedTest`).

## Où trouver chaque donnée

| Donnée | REST | GraphQL | MCP |
|---|---|---|---|
| Audit des tronçons d'une ligne (4 notes, preuves) | `GET /lines/{line}/audit?era&grade&limit&offset` | `Line.audit(era)` : `segments(grade, limit, offset)`, `segments_total(grade)` | `line_audit` (`era`, `grade`, `limit`, `offset`) |
| Audit d'une gare (placement, dates, services) | `GET /stations/{station}/audit` | `Station.audit` | clé `audit` de `get_station` |
| Audit des tronçons de tout le réseau (liste, filtres, tri, total) | `GET /audits/segments?era&grade&check&reason&status&department&region&line&q&sort&limit&offset` | `segmentAudits(...)` : `total`, `items` | `segment_audits` (mêmes paramètres) |
| Audit des gares de tout le réseau (liste, filtres, tri, total) | `GET /audits/stations?grade&check&opening&closure&department&region&line&q&sort&limit&offset` | `stationAudits(...)` : `total`, `items` | `station_audits` (mêmes paramètres) |
| Profil en long | `GET /lines/{line}/profile?max_points` | `Line.profile(max_points)` | `line_profile` (`max_points`) |
| Référentiel d'altitude entre deux PK | `GET /lines/{line}/profile/points?pk_from_m&pk_to_m&max_points` | `LineProfile.samples(pk_from_m, pk_to_m, max_points)` | `line_profile` (`pk_from_m`, `pk_to_m`, résultat `samples`) |
| Altitude à un PK | `GET /lines/{line}/altitude?pk_m` | `LineProfile.altitude_at(pk_m)` | `line_profile` (`pk_m`, résultat `altitude_at_pk`) |
| Crémaillère (`rack`) et déclivité publiée | `profile.rack` | `LineProfile.rack` | `profile.rack` de `line_profile` |
| Contexte sourcé, documents, images et corrections d'un accident | `GET /accidents/{accident}` (`context`) | `Accident.context` | `get_accident` (sans citation) |
| Provenance des champs d'un accident | `provenance` | `Accident.provenance` | `provenance` de `get_accident` |
| Matériel d'une ligne, photo, fin de circulation, relations | `GET /lines/{line}/materials` (`image`, `end`, `relations`) | `Line.material_links` (`end`, `relations`), `LineSheet.materials` | `line_materials` |
| Rames remarquables (TGV 01 « Patrick ») | `notable_units` de `GET /materials/{material}` | `Material.notable_units` | `notable_units` de `get_material`, `notable_unit` de `search` |
| Images mises en avant (accueil) et compteurs | `GET /media?featured&facets` | `media(filter: {featured})`, `mediaFacets` | `media` (`featured`, `facets`) |
| Faits de signalisation que des documents contredisent (correction, raison, citation, documents) | `corrections` de `GET /lines/{line}/signalling-history` | `Line.signalling_corrections` | `corrections` de `signalling_history` |
| Gares d'une ligne dans l'ordre des PK | `GET /lines/{line}/stops` (`pk_label`) | `Line.stops` (`pk_label`) | `get_line` (gares) |

Différences voulues :

- Un champ REST regroupé dans un objet (`place`, `review`) est à plat en GraphQL (`commune`, `department_code`,
  `review_status`, `review_notes`) ; les liens HATEOAS (`links`) n'existent qu'en REST.
- Le serveur MCP raccourcit les réponses trop longues (`truncated`) et retire les citations des textes de Wikipédia et
  des documents sous droits ; les listes de l'audit, du profil et des médias se continuent par `offset` ou `cursor`.
- Les tuiles vectorielles, les exports GPX, KML et GTFS ne sont qu'en REST.
- Les tuiles des ères (`GET /eras/{year}/tiles/{z}/{x}/{y}.mvt`) acceptent `fields=map` : seulement les propriétés que
  lit la carte du site (`App\Enums\TileFields::MAP_PROPERTIES`), booléens écrits seulement quand ils sont vrais, tracés
  aux propriétés identiques réunis en une entité. La carte de `/maps` les demande ainsi : 60 % d'octets en moins sur
  la tuile de vue d'ensemble. Sans le paramètre, la réponse garde toutes ses propriétés ; une autre valeur que `all`
  ou `map` renvoie 422. Liste des propriétés gardées : docblock de `GeoController::tile`, vérifiée contre le code de
  la carte par `tests/Unit/Enums/TileFieldsTest.php`.

## Temps réel

Les changements d'état des contributions peuvent être suivis en direct, par WebSocket (protocole Pusher, serveur
Laravel Reverb auto-hébergé). **C'est désactivé tant que la production n'a pas de serveur temps réel**
(`REALTIME_ENABLED=false`) : aucun événement ne part, `/broadcasting/auth` et `/graphql/subscriptions/auth`
répondent 404 et les abonnements GraphQL reçoivent l'erreur `Unauthorized subscription request`. En attendant, le webhook `contribution.applied` (`/docs/api`,
section « Webhooks ») annonce chaque publication. Mise en service : `docs/DEPLOIEMENT-NGINX.md`, section « Temps réel ».

Quatre événements, émis quel que soit le chemin (site, GraphQL, éditeur de tracé), une fois la transaction validée :

| Événement | Quand |
|---|---|
| `contribution.submitted` | une contribution est proposée (en attente) |
| `contribution.approved` | elle reçoit une première approbation, il en faut deux |
| `contribution.applied` | elle est publiée (même nom que le webhook) |
| `contribution.rejected` | un validateur la refuse |

Charge utile, la même partout, **sans donnée personnelle** (ni auteur, ni validateur, ni motif de refus, ni contenu
de la proposition) :

```json
{
  "event": "contribution.applied",
  "contribution": {"id": 4182, "type": "line_segment", "status": "applied", "approvals": 2, "approvals_required": 2},
  "target": {"resource": "line", "slug": "rfn-570000", "url": "https://vff-project.org/api/v1/lines/rfn-570000"},
  "occurred_at": "2026-11-22T14:05:12+01:00"
}
```

`target` vaut `null` pour une nouvelle ligne ou une nouvelle série pas encore publiée ; `resource` vaut `line`,
`station` ou `material`. Le contenu se relit ensuite par l'API, avec vos droits.

**Canaux Laravel Echo** (`App\Events\ContributionChanged`, autorisation dans `routes/channels.php`) :

| Canal | Accès | Événements |
|---|---|---|
| `contributions` (public) | tout le monde | `contribution.applied` |
| `private-review-queue` | validateurs et administrateurs non suspendus | les quatre |
| `private-users.{id}.contributions` | le compte `{id}` seulement | les quatre, pour ses contributions |

Les canaux privés s'autorisent par `POST /broadcasting/auth` avec la session du site (membre connecté). Avec Laravel
Echo, le nom d'un événement prend un point devant : `.listen('.contribution.applied', …)`. La page `/validate` s'en
sert : la liste se recharge seule (`router.reload`, seulement la liste) quand une contribution est proposée ou
relue, et attend qu'un refus en cours de saisie soit envoyé ou annulé. Laravel Echo et `pusher-js` ne sont
téléchargés que si le temps réel est activé.

**Abonnements GraphQL** (type `Subscription` de `graphql/schema.graphql`, classes de `app/GraphQL/Subscriptions/`) :

| Champ | Accès | Reçoit |
|---|---|---|
| `contributionApplied(resource, slug)` | tout le monde | les publications, éventuellement d'une seule fiche |
| `myContributionChanged` | compte connecté (jeton personnel, capacité `read`, ou session avec CSRF) | les quatre événements de ses contributions |
| `reviewQueueChanged` | validateurs et administrateurs non suspendus | les quatre événements |

1. `POST /graphql` avec `subscription { contributionApplied(slug: "rfn-570000") { event contribution { id status } target { slug url } } }` :
   la réponse porte `data.contributionApplied: null` et le canal de l'abonnement dans
   `extensions.lighthouse_subscriptions.channel` (`private-lighthouse-…`).
2. Connexion Pusher au serveur du site (`wss://vff-project.org/app/<clé publique>`), abonnement à ce canal, autorisé
   par `POST /graphql/subscriptions/auth` (`channel_name`, `socket_id`).
3. Chaque événement arrive sous le nom `lighthouse-subscription`, avec `result.data.contributionApplied`. Les valeurs
   d'énumération sont celles du schéma (`APPLIED`, `LINE_SEGMENT`).

Un abonnement compte pour une requête dans la limite de débit ; un abonné qui ne se manifeste plus est oublié au
bout d'un jour (`LIGHTHOUSE_SUBSCRIPTION_STORAGE_TTL`). Un compte, ou une adresse IP sans compte, ouvre au plus
100 abonnements par jour (`REALTIME_SUBSCRIPTIONS_PER_DAY`) : au-delà, l'erreur porte
`extensions.code = too_many_subscriptions` et `extensions.retry_after` (secondes) ; gardez un abonnement ouvert
plutôt que d'en ouvrir un par page. `POST /graphql/subscriptions/auth` suit la limite de débit de l'API. Le droit à
`reviewQueueChanged` est revérifié à chaque événement : un compte suspendu ou qui n'est plus validateur ne reçoit
plus rien. Les abonnés sont gardés dans Valkey avec le seul numéro du compte (ni cookie, ni adresse IP, ni en-tête). Le serveur MCP ne
diffuse rien en direct : un client MCP relit les contributions par ses outils, ou s'abonne au webhook.

Tests : `tests/Feature/RealtimeTest.php` (diffusion, absence de données personnelles, autorisation des canaux,
abonnements GraphQL), `tests/Feature/RealtimeSecurityTest.php` (revue de sécurité : routes fermées, canaux mal
formés, quota, limites de requête, contexte des abonnés, configuration de Reverb et de nginx) et `tests/js/realtime.test.js` (Echo chargé à la demande, rafraîchissement regroupé).

## Ajouter une donnée

1. Un service ou une ressource qui calcule la valeur une seule fois (`app/Services/`, `app/Http/Resources/`).
2. REST : un contrôleur avec docblock Scramble (résumé de 60 caractères au plus, description, `QueryParameter`
   décrits avec exemple, `ApiResponse` avec corps d'exemple pour 200, 404, 422 et 429).
3. GraphQL : un champ décrit dans `graphql/schema.graphql`, avec `@complexity`, puis `make docs-graphql`.
4. MCP : un outil ou un champ de plus dans un outil existant, avec sa description, et sa section de `docs/MCP.md`.
5. Les mêmes filtres et la même pagination partout, et un test Feature qui compare les trois réponses
   (voir `tests/Feature/Api/ApiParityGapsTest.php` et `AuditPaginationParityTest.php`).

`tests/Feature/Api/ApiDocumentationCoverageTest.php` refuse une route publique sans description OpenAPI, un type ou un
champ GraphQL sans description, un outil MCP dont un argument n'est pas décrit ou n'est pas cité dans `docs/MCP.md`.

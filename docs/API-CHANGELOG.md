# Changements de l'API

Ce journal liste ce qui change pour qui appelle l'API de VFF : REST (`/api/v1`), GraphQL (`/graphql`) et le serveur MCP (`/mcp`).
Les entrées sont datées, la plus récente en premier. Un champ n'est retiré qu'après avoir été marqué déprécié (`deprecated` dans la
spécification OpenAPI, `@deprecated` dans le schéma GraphQL) et annoncé ici. Un test (`ApiContractTest`) compare l'API à un instantané
versionné (`tests/Fixtures/api-contract.json`) et échoue si un champ, un paramètre ou une valeur disparaît sans cette annonce.

## 22/11/2026

Aucun champ n'a été retiré ni renommé depuis le 21/11. Les changements qui peuvent gêner un client sont les suivants.

### À vérifier dans votre client

- GraphQL, `SignallingEventSource.revision` passe de `Int!` à `Int`. REST, `source.revision` de `signalling-history` est `null` pour un fait
  daté par des documents (`source.provider: manual`). Un client typé qui suppose un entier doit accepter `null`.
- GraphQL accepte au plus 5 opérations par lot (liste de requêtes dans un seul POST). Au-delà : HTTP 400, `extensions.code` `batch_too_large`,
  `extensions.max_batch`.
- GraphQL et MCP refusent un corps de plus de 2 Mio (2 097 152 octets) : HTTP 413, `extensions.code` `body_too_large`. Le MCP répond dans ce cas
  par une erreur JSON-RPC `-32600`.
- REST, `page` des listes d'accidents et de passages à niveau est limité à 1 000 000 ; au-delà, HTTP 422 (c'était une erreur 500).
- Nouvelles valeurs possibles : `kind` d'un accident peut valoir `war_action` (fait de guerre), et `toll_source` peut valoir `manual` (fiche rédigée
  à la main d'après un document cité). Le filtre `kind=war_action` existe en REST, en GraphQL et dans l'outil MCP `accidents`.
- `signalling-history` : `origin` peut valoir `document`, et `source.provider` peut valoir `manual`.
- Export GTFS (`/eras/{year}/gtfs.zip`) : `shape_dist_traveled` de `stop_times.txt` est la distance de la gare le long du tracé (même unité que
  `shapes.txt`), plus le point kilométrique ; les tracés sont orientés dans le sens du trajet ; un trajet exige au moins deux gares desservies ;
  les heures sont calculées à 60 km/h ; le flux contient en plus `attributions.txt` et un contact dans `feed_info.txt`.
- Gares d'une fiche de ligne : `status_today` vaut `null` aussi pour une gare absente de la carte d'aujourd'hui ou fermée avant la décennie en
  cours ; `eras` vaut `null` à une ère où la gare n'est pas sur la carte.

### Ajouts

- REST : `GET /journal` et `GET /journal/{slug}` (articles du journal de bord), `GET /lines/{line}/archive-records` (notices des Archives SNCF).
- REST : `GET /eras/{year}/tiles/{z}/{x}/{y}.mvt` accepte `fields=map` (tuile allégée) ; une valeur inconnue donne HTTP 422. Sans `fields`, la tuile ne change pas.
- REST : `profile` d'une ligne ajoute `sourced_grade` (déclivité donnée par une source écrite, `null` sinon) ; `signalling-history` ajoute
  `corrections` et, dans `source`, `verification` et `documents`.
- GraphQL : requêtes `journalArticles` et `journalArticle`, champs `Line.archive_records`, `Line.signalling_corrections`, `LineProfile.sourced_grade`,
  et trois abonnements (`contributionApplied`, `myContributionChanged`, `reviewQueueChanged`), refusés tant que le temps réel est désactivé.
- MCP : outils `journal` et `line_archive_records` ; `line_profile` et `signalling_history` renvoient les nouveaux champs ci-dessus.

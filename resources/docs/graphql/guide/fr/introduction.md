# API GraphQL de VFF

*Français · [English]({{siteRoot}}/guide-en/introduction) · [Deutsch]({{siteRoot}}/guide-de/grundlagen)*

**VFF - Voies Ferrées de France** est l'encyclopédie collaborative du rail français. Son API GraphQL donne accès aux lignes, gares, tronçons, chronologies, signalisation, matériels roulants, accidents et médias, à la comparaison de deux ères et au calcul d'itinéraires historiques. La référence de chaque requête et de chaque type est générée à partir du schéma ; ce guide explique comment s'en servir. Les requêtes d'exemple sont dans [Premiers pas]({{siteRoot}}/guide/premiers-pas).

## Point d'accès

- URL : `POST {{origin}}/graphql`, corps JSON `{"query": "…", "variables": {…}}`, en-tête `Content-Type: application/json`.
- Lecture seule pour les visiteurs anonymes. Les mutations de contribution demandent un compte de membre et ne sont pas traitées dans ce guide (voir le type `Mutation` de la référence).
- Essais en direct, avec complétion et documentation intégrée : [GraphiQL]({{origin}}/graphiql).
- Schéma complet au format SDL : [/docs/schema.graphql]({{origin}}/docs/schema.graphql). L'introspection est ouverte, un client typé peut donc se générer lui-même.
- Un `POST` dont le corps est une liste de requêtes est accepté, dans la limite de 5 opérations.

```bash
curl -X POST {{origin}}/graphql \
  -H "Content-Type: application/json" \
  -d '{"query": "{ stats { lines_count stations_count } }"}'
```

## Authentification et quotas

GraphQL s'appelle sans jeton. Un **jeton personnel**, facultatif, relève la limite de débit : un membre le crée depuis son [profil]({{origin}}/profile) (section « Jetons d'accès à l'API »). Il ne sert qu'à lire, comme l'accès anonyme, et n'est affiché qu'une fois. Il s'envoie dans l'en-tête `Authorization: Bearer <jeton>`.

| Profil | Limite | Compteur |
| --- | --- | --- |
| Sans jeton | 300 requêtes par minute | par adresse IP |
| Jeton d'un membre | 1 200 requêtes par minute | par compte, tous ses jetons ensemble |
| Jeton d'un compte partenaire | 6 000 requêtes par minute | par compte (profil accordé par l'équipe de VFF) |

Le compteur est commun à GraphQL, à l'API REST et au serveur MCP. Chaque réponse porte `X-RateLimit-Limit` (la limite de l'appelant) et `X-RateLimit-Remaining` (requêtes restantes dans la minute).

```javascript
const token = process.env.VFF_TOKEN;
const response = await fetch("{{origin}}/graphql", {
    method: "POST",
    headers: {
        "Content-Type": "application/json",
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: JSON.stringify({ query: "{ stats { lines_count } }" }),
});
console.info(response.status, response.headers.get("X-RateLimit-Remaining"));
console.info((await response.json()).data.stats.lines_count);
```

```python
import os

import requests

headers = {}
if os.environ.get("VFF_TOKEN"):
    headers["Authorization"] = f"Bearer {os.environ['VFF_TOKEN']}"
response = requests.post(
    "{{origin}}/graphql",
    json={"query": "{ stats { lines_count } }"},
    headers=headers,
    timeout=30,
)
print(response.status_code, response.headers["X-RateLimit-Remaining"])
print(response.json()["data"]["stats"]["lines_count"])
```

## Limites de la requête

Ces refus sont décidés avant l'exécution, et le serveur ne lit pas plus de 2 Mio de corps.

| Limite | Valeur | Refus |
| --- | --- | --- |
| Débit | selon le profil ci-dessus | HTTP 429 et en-tête `Retry-After` (secondes à attendre) |
| Complexité | 5 000 points | erreur `query_too_complex` |
| Profondeur | 15 niveaux | erreur `query_too_deep` |
| Lot de requêtes | 5 opérations | HTTP 400, `extensions.code` `batch_too_large` |
| Corps de la requête | 2 Mio | HTTP 413, `extensions.code` `body_too_large` |
| Taille de page | 100 éléments (`first`) | erreur de validation |

Un champ vaut 1 point plus ses sous-champs. Une liste paginée multiplie ses sous-champs par `first`, les champs calculés (`stops`, `signalling`, `pk`, `search`, `stats`…) valent 10 points de plus et les champs lourds (`sheet`, `profile`, `audit` d'une ligne, `route`, `eraComparison`) 250. Le barème complet est dans la description des requêtes de la référence. Demandez les champs dont vous avez besoin, pas toute la fiche, et découpez une grosse requête en plusieurs.

## Ères et unités

Le réseau est décrit par **ères décennales**, de 1830 à 2030. Chaque ère est un instantané au **31/12** de son année : l'ère 1930 montre le réseau au 31/12/1930. L'ère **2030** désigne le réseau **d'aujourd'hui**, recalculé au jour même. Les arguments `era` attendent cette année (`era: 1930`) ; sans `era`, c'est le réseau actuel.

Les dates d'événements restent exactes : `LineEvent.event_date` se lit avec `date_precision` (`day` : jj/mm/aaaa, `month` : mm/aaaa, `year` : aaaa).

Distances et points kilométriques (PK) en **mètres** (`pk_m: 373450` = PK 373+450), vitesses en km/h, tensions en volts, écartements en millimètres, altitudes en mètres NGF, coordonnées WGS 84 en degrés décimaux.

## Pagination

Trois formes, selon la liste. Le tri est fixe ; il s'inverse avec `filter: { order: DESC }` là où il existe.

- **Par curseur** (`lineEvents`, `media`, `materials`, `lineDirectory`, `stationDirectory`, `Line.media`, `Station.media`) : arguments `first` (20 ou 25 par défaut, 100 au plus) et `after`. Les résultats sont dans `edges { node }` et le curseur de la page suivante dans `pageInfo { hasNextPage endCursor }` ; passez `endCursor` dans `after` tant que `hasNextPage` est vrai. Les annuaires rendent `next_cursor`, le même curseur que `meta.next_cursor` de l'API REST.
- **Par page** (`lines`, `stations`, `accidents`, `levelCrossings`) : arguments `first` et `page` (à partir de 1) ; résultats dans `data`, compteurs dans `paginatorInfo` (`total`, `lastPage`, `hasMorePages`).
- **Par `limit` et `offset`** : audits du réseau (`segmentAudits`, `stationAudits`), 50 par défaut et 200 au plus.

Les filtres textuels ignorent la casse et acceptent le joker `%` (`name: "%brest%"`). Une valeur de filtre invalide (type inconnu, date mal formée, ligne inconnue) donne une erreur de validation.

## Erreurs

| Situation | Réponse |
| --- | --- |
| Requête invalide, filtre hors limites | HTTP 200 et tableau `errors` (convention GraphQL) : `message` et, pour une validation, `extensions.validation` détaille chaque champ fautif |
| Identifiant ou slug inconnu (`line(slug: …)`, `lineEvent(id: …)`) | `null`, pas une erreur |
| Limite de débit atteinte | HTTP 429 et `Retry-After` |
| Jeton inconnu, révoqué ou expiré | HTTP 401 `{"error": "invalid_token"}` ; retirez l'en-tête pour revenir à l'accès anonyme |
| Jeton d'un compte suspendu | HTTP 403 `{"error": "account_suspended"}` |
| Requête trop coûteuse, trop profonde, lot ou corps trop gros | voir « Limites de la requête » |

Dans la réponse, `data` peut être partiel quand seule une branche échoue : lisez toujours `errors` avant d'utiliser `data`.

## Versions et dépréciations

L'API n'a pas de numéro de version dans l'URL : le schéma évolue par ajouts. Un champ n'est jamais retiré sans avoir d'abord été marqué `@deprecated` dans le schéma (avec la raison et le champ qui le remplace, affichés dans la référence, qui barre le nom) puis annoncé dans le journal des changements du dépôt. Un test compare l'API à un instantané du contrat et échoue si un champ, un argument ou une valeur disparaît sans cette annonce. Un client doit donc :

- ignorer les champs qu'il ne connaît pas et accepter de nouvelles valeurs pour les champs de type texte qui énumèrent des codes (`kind`, `status`, `provider`…) ;
- traiter comme nuls les champs dont le type n'est pas suivi de `!` ;
- surveiller les avertissements de dépréciation de son outil (GraphiQL et les générateurs de client les affichent).

Les mêmes données existent en REST (`/api/v1`, GeoJSON, tuiles vectorielles, export GTFS) et par le serveur MCP : voir l'[accès à l'API]({{origin}}/api), la référence [Scalar]({{origin}}/docs/api) et la spécification [OpenAPI]({{origin}}/docs/api.json).

## Licences et sources

Données ouvertes : citez « VFF - Voies Ferrées de France » avec un lien vers [vff-project.org](https://vff-project.org). Les données agrègent plusieurs sources dont les licences s'appliquent : SNCF Open Data et OpenStreetMap (ODbL), IGN BD TOPO (Licence Ouverte 2.0), OpenHistoricalMap et Wikidata (CC0), Wikipédia (CC BY-SA). La provenance de chaque tracé (`LineSegment.provenance`), de chaque événement (`LineEvent.provenance`, `source_url`) et de chaque média (`license`, `attribution`) est dans la réponse : reprenez-la quand vous republiez une donnée.

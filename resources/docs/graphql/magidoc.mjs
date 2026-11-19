// Référence statique de l'API GraphQL de VFF, générée par Magidoc dans public/docs/graphql/
// (servie sur /docs/graphql). Les descriptions viennent de graphql/schema.graphql.
//
// Régénérer après toute modification du schéma (make docs-graphql) :
//   1. bin/php artisan lighthouse:print-schema > public/docs/schema.graphql
//      (schéma vu par les clients : directives Lighthouse appliquées, pagination incluse)
//   2. bin/pnpm docs:graphql
//      (télécharge le gabarit Magidoc carbon-multi-page et construit le site statique ;
//      prévoir environ 3 Go de mémoire libre)

const SITE_ROOT = '/docs/graphql';
// Liens vers le reste du site : URL absolues, car le pré-rendu de Magidoc (SvelteKit) refuse
// les liens hors de SITE_ROOT. VFF_DOCS_ORIGIN=http://localhost:8000 pour un essai local.
const ORIGIN = process.env.VFF_DOCS_ORIGIN ?? 'https://vff-project.org';

const introduction = `
# API GraphQL de VFF

**VFF - Voies Ferrées de France** est l'encyclopédie collaborative du rail français. Son API
GraphQL donne accès aux lignes, gares, tronçons, chronologies, signalisation, matériels roulants
et accidents, à la comparaison de deux ères, ainsi qu'au calcul d'itinéraires historiques.

- Point d'accès : \`POST https://vff-project.org/graphql\` (corps JSON \`{"query": "…", "variables": {…}}\`).
- Essais en direct : [GraphiQL](${ORIGIN}/graphiql).
- Lecture seule, sans clé obligatoire : 300 requêtes par minute et par adresse IP sans jeton,
  davantage avec un jeton personnel (voir « Authentification et quotas »).
- Schéma complet au format SDL : [/docs/schema.graphql](${ORIGIN}/docs/schema.graphql).

## Authentification et quotas

GraphQL s'appelle sans jeton. Un **jeton personnel**, facultatif, relève la limite de débit : un
membre le crée depuis son [profil](${ORIGIN}/profile) (section « Jetons d'accès à l'API »). Il ne
sert qu'à lire, comme l'accès anonyme, et n'est affiché qu'une fois. Il s'envoie dans l'en-tête
\`Authorization: Bearer <jeton>\`.

| Profil | Limite | Compteur |
| --- | --- | --- |
| Sans jeton | 300 requêtes par minute | par adresse IP |
| Jeton d'un membre | 1 200 requêtes par minute | par compte, tous ses jetons ensemble |
| Jeton d'un compte partenaire | 6 000 requêtes par minute | par compte (profil accordé par l'équipe de VFF) |

Le compteur est commun à GraphQL, à l'API REST et au serveur MCP. Chaque réponse porte
\`X-RateLimit-Limit\` (la limite de l'appelant) et \`X-RateLimit-Remaining\` (requêtes restantes dans
la minute). Ces refus sont des réponses HTTP, avant toute exécution de la requête GraphQL :

- **HTTP 429** avec \`Retry-After\` (attente en secondes) : limite atteinte ;
- **HTTP 401** (\`{"error": "invalid_token"}\`) : jeton inconnu, révoqué ou expiré ; l'appel n'est
  pas servi en anonyme, retirez l'en-tête pour revenir à l'accès sans jeton ;
- **HTTP 403** (\`{"error": "account_suspended"}\`) : jeton d'un compte suspendu, refusé jusqu'à la
  fin de la suspension.

\`\`\`bash
# Sans jeton
curl -i -X POST https://vff-project.org/graphql \\
  -H "Content-Type: application/json" \\
  -d '{"query": "{ stats { lines_count } }"}'
# Avec un jeton
curl -i -X POST https://vff-project.org/graphql \\
  -H "Authorization: Bearer $VFF_TOKEN" -H "Content-Type: application/json" \\
  -d '{"query": "{ stats { lines_count } }"}'
\`\`\`

\`\`\`javascript
const token = process.env.VFF_TOKEN;
const response = await fetch("https://vff-project.org/graphql", {
    method: "POST",
    headers: {
        "Content-Type": "application/json",
        ...(token ? { Authorization: \`Bearer \${token}\` } : {}),
    },
    body: JSON.stringify({ query: "{ stats { lines_count } }" }),
});
console.info(response.status, response.headers.get("X-RateLimit-Remaining"));
console.info((await response.json()).data.stats.lines_count);
\`\`\`

\`\`\`python
import os

import requests

headers = {}
if os.environ.get("VFF_TOKEN"):
    headers["Authorization"] = f"Bearer {os.environ['VFF_TOKEN']}"
response = requests.post(
    "https://vff-project.org/graphql",
    json={"query": "{ stats { lines_count } }"},
    headers=headers,
    timeout=30,
)
print(response.status_code, response.headers["X-RateLimit-Remaining"])
print(response.json()["data"]["stats"]["lines_count"])
\`\`\`

## Ères

Le réseau est décrit par **ères décennales**, de 1830 à 2030. Chaque ère est un instantané au
**31/12** de son année : l'ère 1930 montre le réseau au 31/12/1930. L'ère **2030** désigne le
réseau **d'aujourd'hui**, recalculé au jour même. Les arguments \`era\` attendent cette année
(\`era: 1930\`) ; sans \`era\`, c'est le réseau actuel.

Les dates d'événements restent exactes : \`LineEvent.event_date\` se lit avec
\`date_precision\` (\`day\` : jj/mm/aaaa, \`month\` : mm/aaaa, \`year\` : aaaa).

## Unités

Distances et points kilométriques (PK) en **mètres** (\`pk_m: 373450\` = PK 373+450), vitesses
en km/h, tensions en volts, écartements en millimètres, coordonnées WGS 84 en degrés décimaux.

## Exemple

\`\`\`graphql
{
  line(slug: "rfn-420000") {
    name
    code
    stops(era: 1930) {
      position
      pk_m
      effective_status
      station { name city }
    }
    events { type event_date date_precision description source_url }
  }
}
\`\`\`

## Listes paginées

\`lines\` et \`stations\` (recherche simple par nom) sont paginées par page : arguments \`first\` (20 par
défaut, 100 au plus) et \`page\` (à partir de 1) ; résultats dans \`data\`, compteurs dans
\`paginatorInfo\` (\`total\`, \`lastPage\`, \`hasMorePages\`…). Pour les annuaires complets, filtrés et
triés comme sur le site, préférez \`lineDirectory\` et \`stationDirectory\` (par curseur, voir plus
bas). \`materials\` se lit par curseur, comme \`lineEvents\` et \`media\`. Les filtres textuels ignorent
la casse et acceptent le joker \`%\` :

\`\`\`graphql
{
  lines(name: "%brest%", first: 10) {
    data { slug name network }
    paginatorInfo { total lastPage }
  }
}
\`\`\`

## Événements, chronologie des gares et médias : pagination par curseur

\`lineEvents\` (événements datés de toutes les lignes) et \`media\` (photos, cartes postales, plans,
documents) sont paginées **par curseur**, à la manière de Relay : arguments \`first\` (25 ou 20 par
défaut, 100 au plus) et \`after\`, résultats dans \`edges { node }\`, curseur de la page suivante dans
\`pageInfo { hasNextPage endCursor }\`. Passez \`endCursor\` dans \`after\` tant que \`hasNextPage\` est
vrai. Le tri est fixe (chronologique pour les événements, par identifiant pour les médias) et
s'inverse avec \`filter: { order: DESC }\`. Les filtres se passent dans l'argument \`filter\` ; une
valeur invalide (type inconnu, date mal formée, ligne inconnue) donne une erreur de validation :
\`errors[0].extensions.validation\` détaille chaque champ fautif.

\`\`\`graphql
{
  lineEvents(first: 5, filter: { department: "29", from: "1930", to: "1939", type: "closure_passenger" }) {
    pageInfo { hasNextPage endCursor }
    edges {
      node {
        type event_date date_precision description source_quote
        provenance { name license license_status }
        source_url
        line { slug name }
      }
    }
  }

  media(first: 3, filter: { station: "brest", rights_status: "public_domain" }) {
    edges { node { title thumb_url attribution license source_url depicted_year date_precision } }
  }

  station(slug: "brest") {
    events { type event_date source is_inferred provenance { provider license } }
  }
}
\`\`\`

Chaque événement garde sa source (\`source\`, \`source_url\`, \`source_quote\`) et la licence de cette
source (\`provenance\`). Chaque média porte son auteur, sa licence, une ligne de crédit prête à
afficher (\`attribution\`) et le lien vers sa page d'origine (\`source_url\`, sur Wikimedia Commons
pour les médias Commons). \`Line.media\` et \`Station.media\` donnent les médias d'une entité, aussi
par curseur.

## Annuaires, fiches complètes, époques et sources

\`lineDirectory\` et \`stationDirectory\` reprennent les annuaires du site (pages /lines et /stations) :
mêmes filtres, même tri, état de chaque ligne à l'ère de référence. Ils se lisent par curseur :
passez \`next_cursor\` dans \`after\` (c'est le même curseur que \`meta.next_cursor\` de l'API REST).
\`facets\` donne les compteurs de la recherche, calculés seulement si vous les demandez.

\`Line.sheet\` et \`Station.sheet\` rendent la fiche complète d'une ligne ou d'une gare, celle des pages
du site : chiffres clés, électrification, signalisation, phases datées, chronique sourcée, gares,
sections nommées pour une ligne ; présence par décennie, lignes desservies, sources et gares
voisines pour une gare. Les parties très imbriquées (phases, chronique, décennies) sont des objets
\`JSON\`, décrits champ par champ dans la référence REST. Le calcul d'une fiche de ligne prend
jusqu'à quelques secondes : demandez-en une à la fois.

Dans la fiche d'une ligne, \`description\` est un extrait de Wikipédia sous CC BY-SA 4.0 : \`description_source\`
donne l'auteur collectif, l'article, le permalien de la version lue, la licence et une mention prête à
copier. Dans celle d'une gare, chaque entrée de \`sources\` garde sa provenance brute : identifiant dans
la source, date de lecture (\`fetched_at\`), attributs reçus tels quels (\`raw\`), méthode de rapprochement.

\`stats\` donne les chiffres d'ensemble du site (lignes, gares, matériels, kilomètres exploités) et la
courbe du réseau à chaque ère ; \`search(query: "brest")\` cherche une ligne, une gare ou un matériel par
son nom ; \`Line.pk(lat:, lng:)\` convertit des coordonnées en point kilométrique sur la ligne.

\`Line.segments(era: 1930)\` donne les tronçons d'une ère avec leur provenance, \`Era.stats\` l'état du
réseau à une époque (longueurs, lignes actives, gares par statut) et \`sources\` le registre des
fournisseurs de données avec leur licence et la mention à reproduire.

\`eraComparison(from: 1930, to: 1980)\` résume ce qui a changé entre deux ères : kilomètres et nombre
de lignes ouvertes (\`opened\`), fermées voie en place (\`closed\`), déposées ou disparues
(\`removed\`) et restées en service (\`kept\`), avec la longueur en service à chaque date
(\`open_km\`). Mêmes chiffres et mêmes noms que \`GET /api/v1/eras/{year}/compare/{other}\`.

\`accidents\` et \`accident(slug:)\` donnent les accidents et événements graves du rail (date et sa
précision, lieu, ligne rattachée et portion, bilan humain et sa source, sources lues avec leur
licence), avec les mêmes filtres que \`GET /api/v1/accidents\` ; \`Line.accidents\` liste ceux d'une
ligne. Une fiche dont \`review_status\` vaut \`to_review\` a un point incertain : à présenter comme
tel.

\`\`\`graphql
{
  lineDirectory(filter: { department: "29", status: "closed", sort: "code" }, first: 5) {
    data { slug name state traffic }
    next_cursor
    facets { total networks { code count } }
  }

  line(slug: "rfn-447000") {
    sheet { key_figures { length_km station_count } today { state } electrification { state } }
    segments(era: 1930) { length_m status provenance { provider license } }
  }

  era(year: 1930) { snapshot_date stats { open_km active_lines stations_by_status { code count } } }

  stats { lines_count stations_count total_km profile { year km lines } }

  search(query: "brest", limit: 3) { lines { slug name } stations { slug city } }

  line(slug: "rfn-420000") {
    pk(lat: 48.10, lng: -1.67) { pk_label snap { distance_from_input_m } }
    sheet { description description_source { author permalink license attribution } }
  }

  station(slug: "brest") { sheet { sources { source ref fetched_at match_method raw } } }

  eraComparison(from: 1930, to: 1980) {
    changes { closed { km lines } removed { km lines } }
    open_km { from to }
  }

  accidents(first: 3, filter: { kind: "derailment", min_deaths: 10, sort: "-deaths" }) {
    data { slug name date date_precision deaths review_status }
  }

  source(provider: "osm") { name licence mention }
}
\`\`\`

## Erreurs

Une requête invalide ou une valeur de filtre hors limites renvoie HTTP 200 avec un tableau \`errors\`
(convention GraphQL) : \`message\` et, pour une validation, \`extensions.validation\`. Au-delà de la
limite de requêtes par minute, HTTP 429 (en-tête \`Retry-After\`) ; un jeton invalide donne HTTP 401
(voir « Authentification et quotas »). Un identifiant inconnu
(\`lineEvent(id: …)\`, \`mediaItem(id: …)\`, \`line(slug: …)\`) donne \`null\`, pas une erreur.

## Itinéraires historiques

\`\`\`graphql
{
  route(from: "rennes", to: "brest", era: 1930) {
    era_year
    distance_m
    lines { line_name length_m }
    stations { name }
  }
}
\`\`\`

## Licences et sources

Données ouvertes : citez « VFF - Voies Ferrées de France » et un lien vers
[vff-project.org](https://vff-project.org). Les données agrègent plusieurs sources dont les
licences s'appliquent : SNCF Open Data et OpenStreetMap (ODbL), IGN BD TOPO (Licence Ouverte 2.0),
OpenHistoricalMap et Wikidata (CC0), Wikipédia (CC BY-SA). Les relevés tiers signalés par
\`source\` = \`archeoferroviaire\` sont hors ODbL : usage non commercial uniquement, crédit et lien
obligatoires. La
provenance de chaque tracé (\`LineSegment.source\`) et de chaque événement
(\`LineEvent.source\`, \`source_url\`) est indiquée.

## API REST

Les mêmes données existent en REST, avec GeoJSON, tuiles vectorielles MVT et export GTFS :
référence [Scalar](${ORIGIN}/docs/api), [Swagger UI](${ORIGIN}/docs/swagger) et spécification
[OpenAPI](${ORIGIN}/docs/api.json).
`;

export default {
    introspection: {
        type: 'sdl',
        paths: ['./public/docs/schema.graphql'],
    },
    website: {
        template: 'carbon-multi-page',
        output: './public/docs/graphql',
        staticAssets: './resources/docs/graphql/assets',
        options: {
            appTitle: 'VFF - API GraphQL',
            // Logo : chemin relatif à siteRoot (Magidoc le préfixe) ; favicon et styles : chemin complet.
            appLogo: '/vff.png',
            appFavicon: `${SITE_ROOT}/favicon.ico`,
            siteRoot: SITE_ROOT,
            siteMeta: {
                description:
                    "Référence de l'API GraphQL de VFF - Voies Ferrées de France : lignes, gares, chronologies, signalisation, itinéraires historiques.",
                'og:title': 'VFF - API GraphQL',
                'theme-color': '#ff6500',
            },
            customStyles: [`${SITE_ROOT}/vff.css`],
            fieldsSorting: 'default',
            argumentsSorting: 'default',
            // Page rangée dans une rubrique (URL /docs/graphql/guide/introduction) : une page au
            // premier niveau est mal servie par `artisan serve` (PATH_INFO du serveur PHP intégré).
            pages: [
                {
                    title: 'Guide',
                    content: [{ title: 'Introduction', content: introduction }],
                },
            ],
            externalLinks: [
                {
                    label: 'GraphiQL',
                    href: `${ORIGIN}/graphiql`,
                    position: 'header',
                    kind: 'GraphQL',
                },
                {
                    label: 'REST (Scalar)',
                    href: `${ORIGIN}/docs/api`,
                    position: 'navigation',
                    group: 'Voir aussi',
                },
                {
                    label: 'REST (Swagger UI)',
                    href: `${ORIGIN}/docs/swagger`,
                    position: 'navigation',
                    group: 'Voir aussi',
                },
                {
                    label: 'OpenAPI (JSON)',
                    href: `${ORIGIN}/docs/api.json`,
                    position: 'navigation',
                    group: 'Voir aussi',
                },
                {
                    label: 'GraphiQL',
                    href: `${ORIGIN}/graphiql`,
                    position: 'navigation',
                    group: 'Voir aussi',
                },
                {
                    label: "Accès à l'API",
                    href: `${ORIGIN}/api`,
                    position: 'navigation',
                    group: 'Voir aussi',
                },
                {
                    label: 'Site VFF',
                    href: `${ORIGIN}/`,
                    position: 'navigation',
                    group: 'Voir aussi',
                },
            ],
        },
    },
};

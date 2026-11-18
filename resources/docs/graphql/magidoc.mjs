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
GraphQL donne accès aux lignes, gares, tronçons, chronologies, signalisation et matériels
roulants, ainsi qu'au calcul d'itinéraires historiques.

- Point d'accès : \`POST https://vff-project.org/graphql\` (corps JSON \`{"query": "…", "variables": {…}}\`).
- Essais en direct : [GraphiQL](${ORIGIN}/graphiql).
- Lecture seule, anonyme, sans clé. Limite : 300 requêtes par minute et par adresse IP.
- Schéma complet au format SDL : [/docs/schema.graphql](${ORIGIN}/docs/schema.graphql).

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

\`lines\`, \`stations\` et \`materials\` sont paginées : arguments \`first\` (20 par défaut, 100 au
plus) et \`page\` (à partir de 1) ; résultats dans \`data\`, compteurs dans \`paginatorInfo\`
(\`total\`, \`lastPage\`, \`hasMorePages\`…). Les filtres textuels ignorent la casse et acceptent le
joker \`%\` :

\`\`\`graphql
{
  lines(name: "%brest%", first: 10) {
    data { slug name network }
    paginatorInfo { total lastPage }
  }
}
\`\`\`

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
                { label: 'GraphiQL', href: `${ORIGIN}/graphiql`, position: 'header', kind: 'GraphQL' },
                { label: 'REST (Scalar)', href: `${ORIGIN}/docs/api`, position: 'navigation', group: 'Voir aussi' },
                { label: 'REST (Swagger UI)', href: `${ORIGIN}/docs/swagger`, position: 'navigation', group: 'Voir aussi' },
                { label: 'OpenAPI (JSON)', href: `${ORIGIN}/docs/api.json`, position: 'navigation', group: 'Voir aussi' },
                { label: 'GraphiQL', href: `${ORIGIN}/graphiql`, position: 'navigation', group: 'Voir aussi' },
                { label: "Accès à l'API", href: `${ORIGIN}/api`, position: 'navigation', group: 'Voir aussi' },
                { label: 'Site VFF', href: `${ORIGIN}/`, position: 'navigation', group: 'Voir aussi' },
            ],
        },
    },
};

// Référence statique de l'API GraphQL de VFF, générée par Magidoc dans public/docs/graphql/
// (servie sur /docs/graphql). Les descriptions viennent de graphql/schema.graphql.
//
// Régénérer après toute modification du schéma (make docs-graphql) :
//   1. bin/php artisan lighthouse:print-schema > public/docs/schema.graphql
//      (schéma vu par les clients : directives Lighthouse appliquées, pagination incluse)
//   2. bin/pnpm docs:graphql
//      Le guide (introduction et premiers pas, en fr, en, de) vient de guide.mjs.
//      (télécharge le gabarit Magidoc carbon-multi-page et construit le site statique ;
//      prévoir environ 3 Go de mémoire libre)

import { guidePages } from './guide.mjs';

const SITE_ROOT = '/docs/graphql';
// Liens vers le reste du site : URL absolues, car le pré-rendu de Magidoc (SvelteKit) refuse
// les liens hors de SITE_ROOT. VFF_DOCS_ORIGIN=http://localhost:8000 pour un essai local.
const ORIGIN = process.env.VFF_DOCS_ORIGIN ?? 'https://vff-project.org';

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
            // Exemples des scalaires que Magidoc ne sait pas inventer. Un fichier (`Upload`) vaut null dans les
            // variables d'une requête multipart : le fichier lui-même part dans la partie désignée par `map`.
            queryGenerationFactories: {
                Upload: null,
            },
            // Guide en trois langues (guide.mjs : textes Markdown et requêtes d'exemple de resources/docs/graphql/guide).
            // Chaque page est rangée dans une rubrique (URL /docs/graphql/guide/introduction, /guide-en/…, /guide-de/…) :
            // une page au premier niveau est mal servie par `artisan serve` (PATH_INFO du serveur PHP intégré).
            pages: guidePages(ORIGIN),
            // Aucun lien en position `header` : le gabarit l'y range dans un bouton placé dans un lien
            // (RGAA 8.2) et l'en-tête dépasse alors 320 px de large (RGAA 10.11). GraphiQL reste dans le
            // panneau « Voir aussi » et dans le guide.
            externalLinks: [
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

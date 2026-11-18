// Console GraphiQL (/graphiql) aux couleurs de VFF. GraphiQL et React sont chargés par des scripts
// classiques depuis public/vendor/graphiql (resources/views/vendor/graphiql/index.blade.php) :
// ce module monte la console, avec la requête d'exemple de VFF et le thème du site.
import '../../css/docs/docs.css';
import '../../css/docs/graphiql.css';
import { isDark, setupThemeToggle } from './theme.js';

/** Globales posées par les scripts classiques de public/vendor/graphiql (React, ReactDOM, GraphiQL). */
/** @type {any} */
const g = window;

const config = JSON.parse(document.getElementById('graphiql-config').textContent);
const container = document.getElementById('graphiql');
const fetcher = g.GraphiQL.createFetcher({
    url: config.url,
    subscriptionUrl: config.subscriptionUrl ?? undefined,
});
const explorer = g.GraphiQLPluginExplorer.explorerPlugin();
const root = g.ReactDOM.createRoot(container);

function render(dark) {
    root.render(
        g.React.createElement(g.GraphiQL, {
            fetcher,
            plugins: [explorer],
            defaultQuery: config.query,
            // Le thème de la console est celui du site, pas un réglage propre à GraphiQL.
            forcedTheme: dark ? 'dark' : 'light',
        }),
    );
}

render(isDark());
setupThemeToggle(render);

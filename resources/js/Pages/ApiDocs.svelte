<script>
    import Layout from '$components/Layout.svelte';
    import PageHero from '$components/PageHero.svelte';
    import SectionBlock from '$components/SectionBlock.svelte';
    import IconCard from '$components/IconCard.svelte';
    import { i18n } from '$lib/i18n.js';

    /**
     * Page « Accès à l'API » : renvoie vers les six documentations (Scalar, Swagger UI, OpenAPI, référence
     * GraphQL, GraphiQL, schéma), rappelle les conditions d'accès, liste les points d'accès REST (les 11 de
     * routes/api.php, avec les noms de paramètres de la spécification) et donne des exemples dont la
     * réponse est celle du serveur. Les documentations sont des pages hors Inertia : liens simples.
     */

    const docs = [
        { key: 'scalar', href: '/docs/api' },
        { key: 'swagger', href: '/docs/swagger' },
        { key: 'openapi', href: '/docs/api.json' },
        { key: 'graphql', href: '/docs/graphql' },
        { key: 'graphiql', href: '/graphiql' },
        { key: 'schema', href: '/docs/schema.graphql' },
    ];

    const access = [{ key: 'readonly' }, { key: 'no_key' }, { key: 'limit' }, { key: 'licence' }];

    const endpoints = [
        { path: '/api/v1/eras/{year}/lines.geojson', key: 'lines_geojson' },
        { path: '/api/v1/eras/{year}/stations.geojson', key: 'stations_geojson' },
        { path: '/api/v1/eras/{year}/tiles/{z}/{x}/{y}.mvt', key: 'tiles' },
        { path: '/api/v1/eras/{year}/gtfs.zip', key: 'gtfs' },
        { path: '/api/v1/search?q={query}', key: 'search' },
        { path: '/api/v1/lines/{line}/history', key: 'history' },
        { path: '/api/v1/lines/{line}/pk?lat={lat}&lng={lng}', key: 'pk' },
        { path: '/api/v1/lines/{line}/stops?era={year}', key: 'stops' },
        { path: '/api/v1/lines/{line}/signalling', key: 'signalling' },
        { path: '/api/v1/lines/{line}/media', key: 'media' },
        { path: '/api/v1/route?from={station}&to={station}&era={year}', key: 'route' },
    ];

    /** Coupe un chemin après chaque barre oblique : une longue adresse passe à la ligne entre deux segments. */
    const segments = (path) => path.split(/(?<=[/?&])/);

    const curlExample = $derived(`# ${$i18n.t('api.example.curl_lines')}
curl https://vff-project.org/api/v1/eras/2030/lines.geojson

# ${$i18n.t('api.example.curl_pk')}
curl "https://vff-project.org/api/v1/lines/rfn-420000/pk?lat=48.10&lng=-1.67"

# ${$i18n.t('api.example.curl_search')}
curl "https://vff-project.org/api/v1/search?q=rennes"`);

    // Réponse réelle du serveur à la deuxième requête (nom de ligne : celui de la base).
    const responseExample = `{
  "line": {
    "slug": "rfn-420000",
    "code": "420000",
    "name": "Ligne de Paris-Montparnasse à Brest"
  },
  "pk_m": 249204,
  "pk_label": "249+204",
  "total_m": 626972,
  "snap": {
    "lat": 48.10357643870743,
    "lng": -1.6702395515287747,
    "distance_from_input_m": 395.12002852
  }
}`;

    const graphqlExample = $derived(`# ${$i18n.t('api.graphql.example_comment')}
{
  line(slug: "rfn-405000") {
    name
    code
    events {
      type
      event_date
    }
  }
}`);

    const graphqlCurl = `curl -X POST https://vff-project.org/graphql \\
  -H "Content-Type: application/json" \\
  -d '{"query": "{ line(slug: \\"rfn-405000\\") { name code } }"}'`;
</script>

<svelte:head>
    <title>{$i18n.t('api.meta_title')} - VFF</title>
    <meta name="description" content={$i18n.t('api.meta_description')} />
</svelte:head>

<Layout compactHeader>
    <main>
        <PageHero title={$i18n.t('api.hero.title')} subtitle={$i18n.t('api.hero.subtitle')} />

        <SectionBlock
            title={$i18n.t('api.docs.title')}
            intro={$i18n.t('api.docs.intro')}
            maxWidth="1180px"
        >
            <ul class="docs-grid">
                {#each docs as d (d.key)}
                    <li>
                        <a class="vff-tile doc" href={d.href}>
                            <span class="doc-text">
                                <strong>{$i18n.t(`api.docs.items.${d.key}.title`)}</strong>
                                <span>{$i18n.t(`api.docs.items.${d.key}.text`)}</span>
                                <code class="doc-path">{d.href}</code>
                            </span>
                        </a>
                    </li>
                {/each}
            </ul>
        </SectionBlock>

        <SectionBlock kind="alt" title={$i18n.t('api.access.title')} maxWidth="1180px">
            <ul class="vff-points">
                {#each access as a (a.key)}
                    <li>
                        <IconCard
                            title={$i18n.t(`api.access.${a.key}.title`)}
                            text={$i18n.t(`api.access.${a.key}.text`)}
                        />
                    </li>
                {/each}
            </ul>
        </SectionBlock>

        <SectionBlock
            title={$i18n.t('api.endpoints.title')}
            intro={$i18n.t('api.endpoints.intro')}
            maxWidth="1180px"
        >
            <ul class="endpoints" aria-label={$i18n.t('api.endpoints.label')}>
                {#each endpoints as e (e.key)}
                    <li>
                        <code class="path"
                            >{#each segments(e.path) as s (s)}{s}<wbr />{/each}</code
                        >
                        <span class="desc">{$i18n.t(`api.endpoints.items.${e.key}`)}</span>
                    </li>
                {/each}
            </ul>
        </SectionBlock>

        <SectionBlock
            kind="alt"
            title={$i18n.t('api.example.title')}
            intro={$i18n.t('api.example.intro')}
            maxWidth="900px"
        >
            <!-- svelte-ignore a11y_no_noninteractive_tabindex (zone défilable : atteignable au clavier) -->
            <pre
                class="code"
                role="region"
                tabindex="0"
                aria-label={$i18n.t('api.example.code_label')}><code>{curlExample}</code></pre>
            <p class="caption">{$i18n.t('api.example.response')}</p>
            <!-- svelte-ignore a11y_no_noninteractive_tabindex (zone défilable : atteignable au clavier) -->
            <pre
                class="code"
                role="region"
                tabindex="0"
                aria-label={$i18n.t('api.example.code_label')}><code>{responseExample}</code></pre>
        </SectionBlock>

        <SectionBlock
            title={$i18n.t('api.graphql.title')}
            intro={$i18n.t('api.graphql.text')}
            maxWidth="900px"
        >
            <!-- svelte-ignore a11y_no_noninteractive_tabindex (zone défilable : atteignable au clavier) -->
            <pre
                class="code"
                role="region"
                tabindex="0"
                aria-label={$i18n.t('api.example.code_label')}><code>{graphqlExample}</code></pre>
            <!-- svelte-ignore a11y_no_noninteractive_tabindex (zone défilable : atteignable au clavier) -->
            <pre
                class="code"
                role="region"
                tabindex="0"
                aria-label={$i18n.t('api.example.code_label')}><code>{graphqlCurl}</code></pre>
            <p class="links">
                <a class="vff-link" href="/docs/graphql"
                    >{$i18n.t('api.docs.items.graphql.title')}</a
                >
                <a class="vff-link" href="/graphiql">{$i18n.t('api.docs.items.graphiql.title')}</a>
            </p>
        </SectionBlock>

        <SectionBlock
            kind="cta"
            title={$i18n.t('api.cta.title')}
            intro={$i18n.t('api.cta.text')}
            maxWidth="640px"
        >
            {#snippet actions()}
                <a
                    href="https://discord.com/invite/6SwTfXBx4h"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="vff-btn primary"
                >
                    <i class="bi bi-discord" aria-hidden="true"></i>
                    {$i18n.t('api.cta.discord')}<i
                        class="bi bi-box-arrow-up-right vff-ext"
                        aria-hidden="true"
                    ></i><span class="vff-sr-only"> ({$i18n.t('common.new_tab')})</span>
                </a>
                <a
                    href="https://github.com/UnxWares/VFF-Project"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="vff-btn secondary"
                >
                    <i class="bi bi-github" aria-hidden="true"></i>
                    {$i18n.t('api.cta.github')}<i
                        class="bi bi-box-arrow-up-right vff-ext"
                        aria-hidden="true"
                    ></i><span class="vff-sr-only"> ({$i18n.t('common.new_tab')})</span>
                </a>
            {/snippet}
        </SectionBlock>
    </main>
</Layout>

<style>
    /* Documentations : entrées cliquables en tuiles, trois colonnes sur ordinateur. */
    .docs-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(min(100%, 340px), 1fr));
        gap: 16px 24px;
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .docs-grid li {
        display: flex;
    }
    .doc {
        display: flex;
        flex: 1;
        gap: 14px;
        align-items: flex-start;
        padding: 18px 20px;
    }
    .doc-text {
        display: flex;
        flex-direction: column;
        gap: 4px;
        min-width: 0;
        font-size: var(--vff-fs-sm);
        line-height: 1.5;
        color: var(--vff-text-muted);
    }
    .doc-text strong {
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-md);
        font-weight: 600;
        color: var(--vff-text);
    }
    .doc-path {
        margin-top: 2px;
        font-family: var(--api-mono);
        font-size: var(--vff-fs-xs);
        color: var(--vff-text-muted);
        overflow-wrap: anywhere;
    }

    /* Points d'accès : chemin à gauche, description alignée sur une même colonne, filets entre les lignes. */
    .endpoints {
        margin: 0;
        padding: 0;
        list-style: none;
        border-top: 1px solid var(--vff-border-strong);
    }
    .endpoints li {
        display: grid;
        grid-template-columns: minmax(0, 30rem) minmax(0, 1fr);
        gap: 8px 32px;
        align-items: baseline;
        padding: 14px 0;
        border-bottom: 1px solid var(--vff-border);
    }
    .path {
        font-family: var(--api-mono);
        font-size: var(--vff-fs-sm);
        line-height: 1.5;
        color: var(--vff-text);
    }
    .desc {
        font-size: var(--vff-fs-md);
        line-height: 1.5;
        color: var(--vff-text-muted);
    }

    .caption {
        max-width: var(--vff-measure);
        margin: var(--vff-space-5) 0 var(--vff-space-3);
        font-size: var(--vff-fs-md);
        line-height: 1.5;
        color: var(--vff-text-muted);
    }
    .code {
        margin: 0 0 var(--vff-space-4);
        padding: 16px 20px;
        overflow-x: auto;
        border: 1px solid var(--vff-border-strong);
        border-radius: var(--vff-radius-md);
        background-color: var(--vff-bg-elevated);
        color: var(--vff-text);
        font-family: var(--api-mono);
        font-size: var(--vff-fs-sm);
        line-height: 1.6;
    }
    .code code {
        display: block;
        font: inherit;
        white-space: pre;
    }
    .links {
        display: flex;
        flex-wrap: wrap;
        gap: 8px 24px;
        margin: var(--vff-space-5) 0 0;
    }

    main {
        --api-mono:
            'SF Mono', 'Cascadia Code', 'JetBrains Mono', Consolas, 'Liberation Mono', monospace;
    }

    @media (max-width: 900px) {
        .endpoints li {
            grid-template-columns: minmax(0, 1fr);
        }
    }
    @media (max-width: 600px) {
        .code {
            padding: 14px 16px;
        }
    }
</style>

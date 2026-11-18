<script>
    import Layout from '$components/Layout.svelte';
    import PageHero from '$components/PageHero.svelte';
    import SectionBlock from '$components/SectionBlock.svelte';
    import { i18n } from '$lib/i18n.js';

    const endpoints = [
        {
            method: 'GET',
            path: '/api/v1/eras/{year}/lines.geojson',
            key: 'lines_geojson',
        },
        {
            method: 'GET',
            path: '/api/v1/eras/{year}/stations.geojson',
            key: 'stations_geojson',
        },
        {
            method: 'GET',
            path: '/api/v1/eras/{year}/tiles/{z}/{x}/{y}.mvt',
            key: 'tiles',
        },
        {
            method: 'GET',
            path: '/api/v1/search?q={query}',
            key: 'search',
        },
        {
            method: 'GET',
            path: '/api/v1/lines/{slug}/history',
            key: 'history',
        },
        {
            method: 'GET',
            path: '/api/v1/lines/{slug}/pk?lat={lat}&lng={lng}',
            key: 'pk',
        },
        {
            method: 'GET',
            path: '/api/v1/lines/{slug}/stops?era={year}',
            key: 'stops',
        },
        {
            method: 'GET',
            path: '/api/v1/route?from={slug}&to={slug}&era={year}',
            key: 'route',
        },
        {
            method: 'GET',
            path: '/api/v1/eras/{year}/gtfs.zip',
            key: 'gtfs',
        },
    ];

    const formats = [
        { key: 'json', icon: 'bi-braces' },
        { key: 'geojson', icon: 'bi-globe-europe-africa' },
        { key: 'mvt', icon: 'bi-grid-3x3' },
    ];

    const curlExample = $derived(`# ${$i18n.t('api.example.curl_lines')}
curl https://vff-project.org/api/v1/eras/2030/lines.geojson

# ${$i18n.t('api.example.curl_pk')}
curl "https://vff-project.org/api/v1/lines/paris-brest/pk?lat=48.10&lng=-1.67"

# ${$i18n.t('api.example.curl_search')}
curl "https://vff-project.org/api/v1/search?q=paris-brest"`);

    const responseExample = `{
  "line": {
    "slug": "paris-brest",
    "code": "420 000",
    "name": "Paris - Brest"
  },
  "pk_m": 365280,
  "pk_label": "365+280",
  "total_m": 622408,
  "snap": {
    "lat": 48.10342,
    "lng": -1.67215,
    "distance_from_input_m": 0.83
  }
}`;
</script>

<svelte:head>
    <title>{$i18n.t('api.meta_title')} - VFF</title>
    <meta name="description" content={$i18n.t('api.meta_description')} />
</svelte:head>

<Layout compactHeader>
    <main>
        <PageHero
            eyebrow={$i18n.t('api.hero.eyebrow')}
            title={$i18n.t('api.hero.title')}
            subtitle={$i18n.t('api.hero.subtitle')}
            status={$i18n.t('api.hero.status')}
        />

        <SectionBlock title={$i18n.t('api.endpoints.title')} maxWidth="900px">
            <ul class="endpoints">
                {#each endpoints as e}
                    <li>
                        <span class="method">{e.method}</span>
                        <code>{e.path}</code>
                        <span class="desc">{$i18n.t(`api.endpoints.items.${e.key}`)}</span>
                    </li>
                {/each}
            </ul>
        </SectionBlock>

        <SectionBlock kind="alt" title={$i18n.t('api.format.title')} maxWidth="900px">
            <div class="formats">
                {#each formats as f}
                    <div class="format">
                        <i class="bi {f.icon}"></i>
                        <span>{$i18n.t(`api.format.${f.key}`)}</span>
                    </div>
                {/each}
            </div>
        </SectionBlock>

        <SectionBlock title={$i18n.t('api.example.title')} maxWidth="900px">
            <p class="example-desc">{$i18n.t('api.example.description')}</p>
            <pre class="code"><code>{curlExample}</code></pre>
            <p class="example-desc">{$i18n.t('api.example.response')}</p>
            <pre class="code"><code>{responseExample}</code></pre>
        </SectionBlock>

        <SectionBlock kind="alt" title={$i18n.t('api.graphql.title')} maxWidth="900px">
            <p class="example-desc">{$i18n.t('api.graphql.text')}</p>
            <div class="docs-links">
                <a href="/graphiql" target="_blank" rel="noopener" class="docs-card">
                    <i class="bi bi-diagram-3"></i>
                    <div>
                        <strong>GraphiQL Explorer</strong>
                        <span>{$i18n.t('api.graphql.explorer_desc')}</span>
                    </div>
                    <i class="bi bi-box-arrow-up-right end"></i>
                </a>
                <a href="/graphql" class="docs-card">
                    <i class="bi bi-braces-asterisk"></i>
                    <div>
                        <strong>POST /graphql</strong>
                        <span>{$i18n.t('api.graphql.endpoint_desc')}</span>
                    </div>
                    <i class="bi bi-arrow-right end"></i>
                </a>
            </div>
            <pre class="code"><code
                    >{`# ${$i18n.t('api.graphql.example_comment')}
{
  line(slug: "rfn-405000") {
    name code type historical_operator
    segments {
      status traffic_type electrified
      signalling train_protection
      era { year }
    }
    events { type event_date description }
  }
}`}</code
                ></pre>
        </SectionBlock>

        <SectionBlock title={$i18n.t('api.openapi.title')} maxWidth="900px">
            <p class="example-desc">
                {$i18n.t('api.openapi.text_before')}
                <a href="https://scramble.dedoc.co" target="_blank" rel="noopener">Scramble</a
                >{$i18n.t('api.openapi.text_middle')}
                <a href="https://scalar.com" target="_blank" rel="noopener">Scalar</a>{$i18n.t(
                    'api.openapi.text_after',
                ) === '.'
                    ? '.'
                    : ` ${$i18n.t('api.openapi.text_after')}`}
            </p>
            <div class="docs-links">
                <a href="/docs/api" target="_blank" rel="noopener" class="docs-card">
                    <i class="bi bi-book"></i>
                    <div>
                        <strong>{$i18n.t('api.openapi.docs')}</strong>
                        <span>{$i18n.t('api.openapi.docs_desc')}</span>
                    </div>
                    <i class="bi bi-box-arrow-up-right end"></i>
                </a>
                <a href="/docs/api.json" target="_blank" rel="noopener" class="docs-card">
                    <i class="bi bi-filetype-json"></i>
                    <div>
                        <strong>{$i18n.t('api.openapi.spec')}</strong>
                        <span>{$i18n.t('api.openapi.spec_desc')}</span>
                    </div>
                    <i class="bi bi-box-arrow-up-right end"></i>
                </a>
            </div>
        </SectionBlock>

        <SectionBlock kind="cta" maxWidth="640px">
            <h2>{$i18n.t('api.cta.title')}</h2>
            <p>{$i18n.t('api.cta.text')}</p>
            <div class="cta-buttons">
                <a
                    href="https://discord.com/invite/6SwTfXBx4h"
                    target="_blank"
                    rel="noopener"
                    class="vff-btn on-cta"
                >
                    <i class="bi bi-discord"></i>
                    {$i18n.t('api.cta.discord')}
                </a>
                <a
                    href="https://github.com/UnxWares/VFF-Project"
                    target="_blank"
                    rel="noopener"
                    class="vff-btn ghost-on-cta"
                >
                    <i class="bi bi-github"></i>
                    {$i18n.t('api.cta.github')}
                </a>
            </div>
        </SectionBlock>
    </main>
</Layout>

<style>
    .endpoints {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    .endpoints li {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 14px 18px;
        background-color: var(--vff-bg-elevated);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        margin-bottom: 10px;
        font-family: var(--vff-font-secondary);
        font-size: 15px;
    }
    .method {
        flex: 0 0 auto;
        font-family: var(--vff-font-display);
        font-size: 12px;
        font-weight: 600;
        padding: 4px 8px;
        background-color: var(--vff-accent);
        color: var(--vff-text-inverse);
        border-radius: var(--vff-radius-sm);
        letter-spacing: 0.05em;
    }
    code {
        font-family: 'SF Mono', 'Cascadia Code', Consolas, monospace;
        color: var(--vff-accent);
        font-size: 14px;
        background-color: var(--vff-bg-muted);
        padding: 2px 8px;
        border-radius: var(--vff-radius-sm);
        flex: 0 0 auto;
    }
    .desc {
        flex: 1;
        color: var(--vff-text-muted);
        font-family: var(--vff-font-body);
    }
    .formats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 16px;
    }
    .format {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 16px 20px;
        background-color: var(--vff-bg);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
    }
    .format i {
        font-size: 22px;
        color: var(--vff-accent);
    }
    .example-desc {
        margin: 16px 0 8px;
        font-size: 16px;
        color: var(--vff-text-muted);
    }
    .code {
        background-color: #1a1f29;
        color: #e8ecec;
        padding: 16px 20px;
        border-radius: var(--vff-radius-md);
        overflow-x: auto;
        margin: 0 0 24px;
        font-family: 'SF Mono', 'Cascadia Code', Consolas, monospace;
        font-size: 13px;
        line-height: 1.55;
    }
    .code code {
        background: transparent;
        color: inherit;
        padding: 0;
        font-size: inherit;
        display: block;
        white-space: pre;
    }
    .cta-buttons {
        display: flex;
        justify-content: center;
        gap: 16px;
        flex-wrap: wrap;
        margin-top: 24px;
    }
    @media (max-width: 600px) {
        .endpoints li {
            flex-direction: column;
            align-items: flex-start;
        }
        .endpoints code {
            flex: 0 1 auto;
            max-width: 100%;
            box-sizing: border-box;
            overflow-wrap: anywhere;
        }
    }
    .docs-links {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 14px;
        margin-top: 20px;
    }
    .docs-card {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 16px 18px;
        background-color: var(--vff-bg-elevated);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        text-decoration: none;
        color: var(--vff-text);
        transition: all var(--vff-transition);
    }
    .docs-card:hover {
        border-color: var(--vff-accent);
        transform: translateY(-2px);
    }
    .docs-card > i:first-child {
        font-size: 22px;
        color: var(--vff-accent);
    }
    .docs-card div {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 2px;
    }
    .docs-card strong {
        font-family: var(--vff-font-display);
        font-size: 15px;
    }
    .docs-card span {
        color: var(--vff-text-muted);
        font-size: 12px;
    }
    .docs-card .end {
        color: var(--vff-text-muted);
    }
</style>

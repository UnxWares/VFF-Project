<script>
    import Layout from '$components/Layout.svelte';
    import PageHero from '$components/PageHero.svelte';
    import SectionBlock from '$components/SectionBlock.svelte';
    import { i18n } from '$lib/i18n.js';

    const endpoints = [
        { key: 'lines', method: 'GET', path: '/api/v1/lines' },
        { key: 'segments', method: 'GET', path: '/api/v1/segments.geojson?era=2030' },
        { key: 'stations', method: 'GET', path: '/api/v1/stations' },
        { key: 'materials', method: 'GET', path: '/api/v1/materials' },
        { key: 'events', method: 'GET', path: '/api/v1/lines/{id}/events' },
        { key: 'media', method: 'GET', path: '/api/v1/media?line={id}' },
    ];

    const formats = [
        { key: 'json', icon: 'bi-braces' },
        { key: 'geojson', icon: 'bi-globe-europe-africa' },
        { key: 'mvt', icon: 'bi-grid-3x3' },
    ];

    const curlExample = `curl https://vff.fr/api/v1/lines?era=2000 \\
  -H "Accept: application/json"`;

    const responseExample = `{
  "data": [
    {
      "id": "ligne-paris-brest",
      "name": "Paris–Brest",
      "era": 2000,
      "status": "in_service",
      "length_km": 622,
      "operator": "SNCF",
      "stations_count": 24
    }
  ],
  "meta": { "total": 1, "page": 1 }
}`;
</script>

<svelte:head>
    <title>{$i18n.t('api.meta_title')} — VFF</title>
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
                        <span class="desc">{$i18n.t(`api.endpoints.${e.key}`)}</span>
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
            <p class="example-desc">Réponse attendue :</p>
            <pre class="code"><code>{responseExample}</code></pre>
        </SectionBlock>

        <SectionBlock kind="cta" maxWidth="640px">
            <h2>{$i18n.t('api.cta.title')}</h2>
            <p>{$i18n.t('api.cta.text')}</p>
            <div class="cta-buttons">
                <a href="https://discord.com/invite/6SwTfXBx4h" target="_blank" rel="noopener" class="vff-btn on-cta">
                    <i class="bi bi-discord"></i> {$i18n.t('api.cta.discord')}
                </a>
                <a href="https://github.com/vff-project" target="_blank" rel="noopener" class="vff-btn ghost-on-cta">
                    <i class="bi bi-github"></i> {$i18n.t('api.cta.github')}
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
    }
</style>

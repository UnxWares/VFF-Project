<script>
    import Layout from '$components/Layout.svelte';
    import PageHero from '$components/PageHero.svelte';
    import SectionBlock from '$components/SectionBlock.svelte';
    import StatStrip from '$components/Sheet/StatStrip.svelte';
    import { i18n } from '$lib/i18n.js';

    let { stats, byLicense = [], byAuthor = [] } = $props();
    const fmt = (n) => Number(n ?? 0).toLocaleString($i18n.language ?? 'fr');
</script>

<svelte:head>
    <title>{$i18n.t('credits.media.meta_title')} - VFF</title>
    <meta name="description" content={$i18n.t('credits.media.meta_description')} />
</svelte:head>

<Layout compactHeader>
    <main>
        <PageHero
            eyebrow={$i18n.t('credits.eyebrow')}
            title={$i18n.t('credits.media.title')}
            subtitle={$i18n.t('credits.media.subtitle')}
        />

        <section class="stats-row">
            <StatStrip
                items={[
                    { value: fmt(stats.total), label: $i18n.t('credits.media.stats.total') },
                    { value: fmt(stats.photo_count), label: $i18n.t('credits.media.stats.photos') },
                    { value: fmt(stats.plan_count), label: $i18n.t('credits.media.stats.plans') },
                    {
                        value: fmt(stats.document_count),
                        label: $i18n.t('credits.media.stats.documents'),
                    },
                ]}
            />
        </section>

        {#if byLicense.length}
            <SectionBlock title={$i18n.t('credits.media.by_license')} maxWidth="700px">
                <ul class="license-list">
                    {#each byLicense as l (l.license)}
                        <li>
                            <span class="license-name"
                                >{l.license ?? $i18n.t('credits.media.no_license')}</span
                            >
                            <span class="license-count"
                                >{$i18n.t(
                                    l.count === 1
                                        ? 'credits.media.media_single'
                                        : 'credits.media.media_plural',
                                    { n: fmt(l.count) },
                                )}</span
                            >
                        </li>
                    {/each}
                </ul>
            </SectionBlock>
        {/if}

        {#if byAuthor.length}
            <SectionBlock kind="alt" title={$i18n.t('credits.media.top_authors')} maxWidth="700px">
                <ul class="author-list">
                    {#each byAuthor as a (a.author)}
                        <li>
                            <i class="bi bi-person-fill" aria-hidden="true"></i>
                            <strong>{a.author ?? $i18n.t('credits.media.anonymous')}</strong>
                            <span
                                >{$i18n.t(
                                    a.count === 1
                                        ? 'credits.media.contribution_single'
                                        : 'credits.media.contribution_plural',
                                    { n: fmt(a.count) },
                                )}</span
                            >
                        </li>
                    {/each}
                </ul>
            </SectionBlock>
        {/if}

        <SectionBlock title={$i18n.t('credits.media.partners_title')} maxWidth="780px">
            <ul class="partners">
                <li>
                    <h3>BNF Gallica</h3>
                    <p>{$i18n.t('credits.media.partners.gallica_text')}</p>
                    <a href="https://gallica.bnf.fr" target="_blank" rel="noopener"
                        >gallica.bnf.fr <i class="bi bi-box-arrow-up-right" aria-hidden="true"
                        ></i></a
                    >
                </li>
                <li>
                    <h3>{$i18n.t('credits.media.partners.commons_title')}</h3>
                    <p>{$i18n.t('credits.media.partners.commons_text')}</p>
                    <a href="https://commons.wikimedia.org" target="_blank" rel="noopener"
                        >commons.wikimedia.org <i
                            class="bi bi-box-arrow-up-right"
                            aria-hidden="true"
                        ></i></a
                    >
                </li>
                <li>
                    <h3>{$i18n.t('credits.media.partners.assoc_title')}</h3>
                    <p>{$i18n.t('credits.media.partners.assoc_text')}</p>
                </li>
                <li>
                    <h3>{$i18n.t('credits.media.partners.contributors_title')}</h3>
                    <p>{$i18n.t('credits.media.partners.contributors_text')}</p>
                    <a href="/contribute">{$i18n.t('credits.media.partners.contributors_link')}</a>
                </li>
            </ul>
        </SectionBlock>

        <SectionBlock kind="cta" maxWidth="640px">
            <h2>{$i18n.t('credits.media.cta.title')}</h2>
            <p>{$i18n.t('credits.media.cta.text')}</p>
            <a href="/contribute" class="vff-btn on-cta ink">
                <i class="bi bi-upload"></i>
                {$i18n.t('credits.media.cta.button')}
            </a>
        </SectionBlock>
    </main>
</Layout>

<style>
    .stats-row {
        max-width: 900px;
        margin: -30px auto 0;
        background-color: var(--vff-bg-elevated);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-lg);
        box-shadow: 0 12px 32px var(--vff-shadow);
        padding: 0 28px;
        display: flex;
        justify-content: center;
        position: relative;
        z-index: 2;
    }
    @media (max-width: 700px) {
        .stats-row {
            margin: -30px 16px 0;
            padding: 0 16px;
            display: block;
        }
    }

    .license-list,
    .author-list,
    .partners {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    .license-list li {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 16px;
        border-bottom: 1px solid var(--vff-border);
    }
    .license-list li:last-child {
        border-bottom: 0;
    }
    .license-name {
        font-family: var(--vff-font-display);
        font-size: 14px;
    }
    .license-count {
        background-color: var(--vff-accent-soft);
        color: var(--vff-accent-ink);
        font-family: var(--vff-font-display);
        font-size: 12px;
        font-weight: 600;
        padding: 4px 10px;
        border-radius: var(--vff-radius-pill);
    }

    .author-list li {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 14px;
        border-bottom: 1px solid var(--vff-border);
    }
    .author-list li:last-child {
        border-bottom: 0;
    }
    .author-list i {
        color: var(--vff-accent-ink);
        font-size: 18px;
    }
    .author-list strong {
        flex: 1;
    }
    .author-list span {
        color: var(--vff-text-muted);
        font-size: 13px;
    }

    .partners {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 18px;
    }
    .partners li {
        padding: 16px 18px;
        background-color: var(--vff-bg-elevated);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
    }
    .partners h3 {
        margin: 0 0 6px;
        font-family: var(--vff-font-display);
        font-size: 16px;
        color: var(--vff-accent-ink);
    }
    .partners h3:after {
        display: none;
    }
    .partners p {
        margin: 0 0 8px;
        font-size: 13px;
        line-height: 1.5;
        color: var(--vff-text);
    }
    .partners a {
        color: var(--vff-accent-ink);
        font-size: 13px;
        font-family: var(--vff-font-display);
        text-decoration: none;
    }
    .partners a:hover {
        text-decoration: underline;
    }
</style>

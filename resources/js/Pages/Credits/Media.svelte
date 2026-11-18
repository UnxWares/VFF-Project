<script>
    import Layout from '$components/Layout.svelte';
    import PageHero from '$components/PageHero.svelte';
    import SectionBlock from '$components/SectionBlock.svelte';
    import StatStrip from '$components/Sheet/StatStrip.svelte';
    import { i18n } from '$lib/i18n.js';
    import { groupLicenses, licenseUrl, mergeAuthors } from '$lib/mediaCredits.js';
    import { Link } from '@inertiajs/svelte';

    let { stats, byLicense = [], byAuthor = [] } = $props();

    const lang = $derived($i18n.language ?? 'fr');
    const fmt = (n) => Number(n ?? 0).toLocaleString(lang);

    const otherCount = $derived(
        Math.max(
            0,
            (stats.total ?? 0) -
                (stats.photo_count ?? 0) -
                (stats.plan_count ?? 0) -
                (stats.document_count ?? 0),
        ),
    );
    const statItems = $derived([
        { value: fmt(stats.total), label: $i18n.t('credits.media.stats.total') },
        { value: fmt(stats.photo_count), label: $i18n.t('credits.media.stats.photos') },
        { value: fmt(stats.plan_count), label: $i18n.t('credits.media.stats.plans') },
        { value: fmt(stats.document_count), label: $i18n.t('credits.media.stats.documents') },
        ...(otherCount > 0
            ? [{ value: fmt(otherCount), label: $i18n.t('credits.media.stats.other') }]
            : []),
    ]);

    // Tri : par nombre de médias (défaut) ou par nom.
    let licenseSort = $state('count');
    let authorSort = $state('count');
    const collator = $derived(new Intl.Collator(lang, { sensitivity: 'base' }));

    const licenses = $derived(
        groupLicenses(byLicense).map((l) => ({
            ...l,
            label: l.labelKey ? $i18n.t(`credits.media.licenses.${l.labelKey}`) : l.name,
            url: licenseUrl(l.name, lang),
        })),
    );
    const sortedLicenses = $derived(
        licenseSort === 'name'
            ? [...licenses].sort((a, b) => collator.compare(a.label, b.label))
            : licenses,
    );
    const authors = $derived(
        mergeAuthors(byAuthor).map((a) => ({
            ...a,
            label: a.author ?? $i18n.t('credits.media.anonymous'),
        })),
    );
    const sortedAuthors = $derived(
        authorSort === 'name'
            ? [...authors].sort((a, b) => collator.compare(a.label, b.label))
            : authors,
    );

    const sources = [
        {
            titleKey: 'credits.media.partners.gallica_title',
            textKey: 'credits.media.partners.gallica_text',
            href: 'https://gallica.bnf.fr',
            host: 'gallica.bnf.fr',
        },
        {
            titleKey: 'credits.media.partners.commons_title',
            textKey: 'credits.media.partners.commons_text',
            href: 'https://commons.wikimedia.org',
            host: 'commons.wikimedia.org',
        },
        {
            titleKey: 'credits.media.partners.assoc_title',
            textKey: 'credits.media.partners.assoc_text',
        },
        {
            titleKey: 'credits.media.partners.contributors_title',
            textKey: 'credits.media.partners.contributors_text',
            internal: '/contribute',
            linkKey: 'credits.media.partners.contributors_link',
        },
    ];
</script>

<svelte:head>
    <title>{$i18n.t('credits.media.meta_title')} - VFF</title>
    <meta name="description" content={$i18n.t('credits.media.meta_description')} />
</svelte:head>

{#snippet sortControl(current, set)}
    <div class="sort" role="group" aria-label={$i18n.t('credits.media.sort_label')}>
        <span class="sort-label" aria-hidden="true">{$i18n.t('credits.media.sort_label')}</span>
        <button
            type="button"
            class="vff-btn quiet small"
            aria-pressed={current === 'count'}
            onclick={() => set('count')}
        >
            {$i18n.t('credits.media.sort_count')}
        </button>
        <button
            type="button"
            class="vff-btn quiet small"
            aria-pressed={current === 'name'}
            onclick={() => set('name')}
        >
            {$i18n.t('credits.media.sort_name')}
        </button>
    </div>
{/snippet}

<Layout compactHeader>
    <main>
        <PageHero
            title={$i18n.t('credits.media.title')}
            subtitle={$i18n.t('credits.media.subtitle')}
        />

        <div class="stats-band">
            <div class="vff-container">
                <StatStrip items={statItems} label={$i18n.t('credits.media.stats_label')} />
            </div>
        </div>

        <div class="vff-section">
            <div class="vff-container lists">
                {#if licenses.length}
                    <section aria-labelledby="by-license-title">
                        <h2 class="vff-section-title" id="by-license-title">
                            {$i18n.t('credits.media.by_license')}
                        </h2>
                        <p class="list-intro">{$i18n.t('credits.media.by_license_intro')}</p>
                        {@render sortControl(licenseSort, (v) => (licenseSort = v))}
                        <div class="vff-table-wrap">
                            <table class="vff-table">
                                <caption class="vff-sr-only"
                                    >{$i18n.t('credits.media.by_license')}</caption
                                >
                                <thead>
                                    <tr>
                                        <th scope="col">{$i18n.t('credits.media.col_license')}</th>
                                        <th scope="col" class="num"
                                            >{$i18n.t('credits.media.col_media')}</th
                                        >
                                    </tr>
                                </thead>
                                <tbody>
                                    {#each sortedLicenses as l (l.id)}
                                        <tr>
                                            <th scope="row" class="name">
                                                {#if l.url}
                                                    <a
                                                        href={l.url}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        >{l.label}<i
                                                            class="bi bi-box-arrow-up-right vff-ext"
                                                            aria-hidden="true"
                                                        ></i><span class="vff-sr-only">
                                                            ({$i18n.t('common.new_tab')})</span
                                                        ></a
                                                    >
                                                {:else}
                                                    {l.label}
                                                {/if}
                                            </th>
                                            <td class="num">{fmt(l.count)}</td>
                                        </tr>
                                    {/each}
                                </tbody>
                            </table>
                        </div>
                    </section>
                {/if}

                {#if authors.length}
                    <section aria-labelledby="top-authors-title">
                        <h2 class="vff-section-title" id="top-authors-title">
                            {$i18n.t('credits.media.top_authors')}
                        </h2>
                        <p class="list-intro">{$i18n.t('credits.media.top_authors_intro')}</p>
                        {@render sortControl(authorSort, (v) => (authorSort = v))}
                        <div class="vff-table-wrap">
                            <table class="vff-table">
                                <caption class="vff-sr-only"
                                    >{$i18n.t('credits.media.top_authors')}</caption
                                >
                                <thead>
                                    <tr>
                                        <th scope="col">{$i18n.t('credits.media.col_author')}</th>
                                        <th scope="col" class="num"
                                            >{$i18n.t('credits.media.col_media')}</th
                                        >
                                    </tr>
                                </thead>
                                <tbody>
                                    {#each sortedAuthors as a (a.label)}
                                        <tr>
                                            <th scope="row" class="name" translate="no"
                                                >{a.label}</th
                                            >
                                            <td class="num">{fmt(a.count)}</td>
                                        </tr>
                                    {/each}
                                </tbody>
                            </table>
                        </div>
                    </section>
                {/if}
            </div>
        </div>

        <SectionBlock kind="alt" title={$i18n.t('credits.media.partners_title')} maxWidth="1180px">
            <ul class="sources">
                {#each sources as s (s.titleKey)}
                    <li>
                        <h3>{$i18n.t(s.titleKey)}</h3>
                        <p>{$i18n.t(s.textKey)}</p>
                        {#if s.href}
                            <a
                                href={s.href}
                                target="_blank"
                                rel="noopener noreferrer"
                                class="vff-link"
                                >{s.host}<i
                                    class="bi bi-box-arrow-up-right vff-ext"
                                    aria-hidden="true"
                                ></i><span class="vff-sr-only">
                                    ({$i18n.t('common.new_tab')})</span
                                ></a
                            >
                        {:else if s.internal}
                            <Link href={s.internal} class="vff-link">{$i18n.t(s.linkKey)}</Link>
                        {/if}
                    </li>
                {/each}
            </ul>
        </SectionBlock>

        <SectionBlock
            kind="cta"
            title={$i18n.t('credits.media.cta.title')}
            intro={$i18n.t('credits.media.cta.text')}
        >
            {#snippet actions()}
                <Link href="/contribute" class="vff-btn primary">
                    {$i18n.t('credits.media.cta.button')}
                </Link>
            {/snippet}
        </SectionBlock>
    </main>
</Layout>

<style>
    .stats-band {
        border-bottom: 1px solid var(--vff-border);
    }
    .lists {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: var(--vff-section-space) 64px;
        align-items: start;
    }
    /* Titre, chapeau, tri et tableau des deux colonnes sur les mêmes lignes de grille. */
    .lists > section {
        display: grid;
        grid-row: span 4;
        grid-template-rows: subgrid;
        row-gap: 0;
        min-width: 0;
    }
    .list-intro {
        max-width: var(--vff-measure);
        margin: 0 0 var(--vff-space-4);
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-md);
        line-height: 1.55;
        color: var(--vff-text-muted);
    }
    .sort {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: var(--vff-space-1) var(--vff-space-2);
        margin-bottom: var(--vff-space-3);
    }
    .sort-label {
        margin-right: var(--vff-space-1);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
    .sort .vff-btn[aria-pressed='true'] {
        background-color: var(--vff-bg-accent);
        border-color: var(--vff-accent-ink);
        color: var(--vff-text);
    }
    .name {
        font-weight: 600;
        overflow-wrap: anywhere;
    }
    .name a {
        display: inline-flex;
        align-items: center;
        min-height: 24px;
        color: inherit;
    }
    .vff-table tbody th {
        color: var(--vff-text);
        font-weight: 600;
    }

    .sources {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: 24px 32px;
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .sources li {
        padding-top: 14px;
        border-top: 1px solid var(--vff-border-strong);
    }
    .sources h3 {
        margin: 0 0 var(--vff-space-2);
    }
    .sources p {
        margin: 0 0 var(--vff-space-2);
        font-size: var(--vff-fs-md);
        line-height: 1.6;
        color: var(--vff-text-muted);
    }

    @media (max-width: 900px) {
        .lists {
            grid-template-columns: minmax(0, 1fr);
        }
    }
</style>

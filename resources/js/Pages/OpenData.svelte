<script>
    /**
     * Page « Données ouvertes » (/open-data) : jeux produits à la main par vff:export-open-data
     * (OpenDataController lit le catalogue), fichiers servis en statique sous /downloads/open-data/.
     */
    import Layout from '$components/Layout.svelte';
    import PageHero from '$components/PageHero.svelte';
    import SectionBlock from '$components/SectionBlock.svelte';
    import SeoHead from '$components/SeoHead.svelte';
    import { i18n } from '$lib/i18n.js';
    import { formatBytes, networkCounts, withheldRows } from '$lib/openData.js';
    import { breadcrumbSchema, datasetSchema } from '$lib/structuredData.js';
    import { Link } from '@inertiajs/svelte';

    let { datasets = [], credits = [], withheld = {}, licence } = $props();

    const lang = $derived($i18n.language ?? 'fr');
    const size = (bytes) => formatBytes(bytes, lang);
    const day = (iso) =>
        iso
            ? new Date(`${iso}T00:00:00Z`).toLocaleDateString(lang, {
                  day: 'numeric',
                  month: 'long',
                  year: 'numeric',
                  timeZone: 'UTC',
              })
            : '';

    const thematic = $derived(datasets.filter((d) => d.kind !== 'network'));
    const network = $derived(datasets.filter((d) => d.kind === 'network'));
    const rights = $derived(withheldRows(withheld));
    const decade = (d) => (d.era === 2030 ? $i18n.t('open_data.network.current') : String(d.era));
    const itemLabel = (key) =>
        key.startsWith('materials.')
            ? $i18n.t('open_data.rights.items.other', { field: key.slice('materials.'.length) })
            : $i18n.t(`open_data.rights.items.${key.replaceAll('.', '_')}`);
</script>

<svelte:head>
    <title>{$i18n.t('open_data.meta_title')} - VFF</title>
</svelte:head>

<!-- Partage et données structurées : le jeu de données ouvert (licence ODbL) et le fil d'Ariane. -->
<SeoHead
    title={$i18n.t('open_data.meta_title')}
    description={$i18n.t('open_data.meta_description')}
    path="/open-data"
    jsonLd={(origin) => [
        datasetSchema({
            origin,
            datasets,
            licence,
            name: $i18n.t('open_data.meta_title'),
            description: $i18n.t('open_data.meta_description'),
            lang,
            keywords: $i18n.t('open_data.json_ld.keywords'),
            siteName: $i18n.t('common.app_full'),
            partName: (d) =>
                d.kind === 'network'
                    ? `${$i18n.t('open_data.network.title')} ${decade(d)}`
                    : $i18n.t(`open_data.kinds.${d.kind}.title`),
            partDescription: (d) =>
                d.kind === 'network'
                    ? $i18n.t('open_data.network.intro')
                    : $i18n.t(`open_data.kinds.${d.kind}.text`),
        }),
        breadcrumbSchema(origin, [
            { name: $i18n.t('common.app_full'), path: '/' },
            { name: $i18n.t('open_data.meta_title'), path: '/open-data' },
        ]),
    ]}
/>

{#snippet download(d, name)}
    <a class="vff-btn secondary small" href={d.archive.url} download>
        <span aria-hidden="true">{$i18n.t('open_data.zip')}</span>
        <span class="vff-sr-only"
            >{$i18n.t('open_data.zip_label', { name, size: size(d.archive.bytes) })}</span
        >
    </a>
    <details class="files">
        <summary>{$i18n.t('open_data.files')}</summary>
        <ul>
            {#each d.files as f (f.name)}
                <li>
                    <a class="vff-link" href={f.url} download translate="no">{f.name}</a>
                    <span class="muted">{size(f.bytes)}</span>
                </li>
            {/each}
        </ul>
    </details>
{/snippet}

<Layout compactHeader>
    <main>
        <PageHero title={$i18n.t('open_data.title')} subtitle={$i18n.t('open_data.subtitle')} />

        <SectionBlock title={$i18n.t('open_data.licence.title')} maxWidth="720px">
            <div class="prose">
                <p>
                    {$i18n.t('open_data.licence.text')}
                    <a class="vff-link" href={licence.url} target="_blank" rel="noopener noreferrer"
                        >{$i18n.t('open_data.licence.licence_link')}<span class="vff-sr-only">
                            ({$i18n.t('common.new_tab')})</span
                        ></a
                    >
                </p>
                <p>{$i18n.t('open_data.licence.attribution')}</p>
                <p>{$i18n.t('open_data.licence.share_alike')}</p>
                <p>
                    {$i18n.t('open_data.licence.api')}
                    <Link href="/api" class="vff-link">{$i18n.t('open_data.licence.api_link')}</Link
                    >
                </p>
            </div>
        </SectionBlock>

        {#if datasets.length === 0}
            <SectionBlock
                kind="alt"
                title={$i18n.t('open_data.empty.title')}
                intro={$i18n.t('open_data.empty.text')}
                maxWidth="720px"
            />
        {:else}
            {#if thematic.length}
                <SectionBlock
                    kind="alt"
                    title={$i18n.t('open_data.thematic.title')}
                    intro={$i18n.t('open_data.thematic.intro')}
                    maxWidth="1180px"
                >
                    <div class="vff-table-wrap">
                        <table class="vff-table stack">
                            <caption class="vff-sr-only"
                                >{$i18n.t('open_data.thematic.title')}</caption
                            >
                            <thead>
                                <tr>
                                    <th scope="col">{$i18n.t('open_data.cols.dataset')}</th>
                                    <th scope="col">{$i18n.t('open_data.cols.date')}</th>
                                    <th scope="col" class="num">{$i18n.t('open_data.cols.size')}</th
                                    >
                                    <th scope="col">{$i18n.t('open_data.cols.download')}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {#each thematic as d (d.id)}
                                    {@const title = $i18n.t(`open_data.kinds.${d.kind}.title`)}
                                    <tr>
                                        <th scope="row">
                                            <span class="name">{title}</span>
                                            <span class="muted text"
                                                >{$i18n.t(`open_data.kinds.${d.kind}.text`)}</span
                                            >
                                            <span class="muted"
                                                >{$i18n.t('open_data.rows', {
                                                    count:
                                                        d.files.find((f) => f.name.endsWith('.csv'))
                                                            ?.rows ?? 0,
                                                })}</span
                                            >
                                        </th>
                                        <td class="nowrap">{day(d.generated_on)}</td>
                                        <td class="num nowrap">{size(d.archive.bytes)}</td>
                                        <td>{@render download(d, title)}</td>
                                    </tr>
                                {/each}
                            </tbody>
                        </table>
                    </div>
                </SectionBlock>
            {/if}

            {#if network.length}
                <SectionBlock
                    title={$i18n.t('open_data.network.title')}
                    intro={$i18n.t('open_data.network.intro')}
                    maxWidth="1180px"
                >
                    <div class="vff-table-wrap">
                        <table class="vff-table stack">
                            <caption class="vff-sr-only"
                                >{$i18n.t('open_data.network.title')}</caption
                            >
                            <thead>
                                <tr>
                                    <th scope="col">{$i18n.t('open_data.cols.decade')}</th>
                                    <th scope="col">{$i18n.t('open_data.cols.contents')}</th>
                                    <th scope="col">{$i18n.t('open_data.cols.date')}</th>
                                    <th scope="col" class="num">{$i18n.t('open_data.cols.size')}</th
                                    >
                                    <th scope="col">{$i18n.t('open_data.cols.download')}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {#each network as d (d.id)}
                                    {@const counts = networkCounts(d.files)}
                                    <tr>
                                        <th scope="row" class="name nowrap">{decade(d)}</th>
                                        <td>
                                            {$i18n.t('open_data.lines', { count: counts.lines })},
                                            {$i18n.t('open_data.sections', {
                                                count: counts.sections,
                                            })},
                                            {$i18n.t('open_data.stations', {
                                                count: counts.stations,
                                            })}
                                        </td>
                                        <td class="nowrap">{day(d.generated_on)}</td>
                                        <td class="num nowrap">{size(d.archive.bytes)}</td>
                                        <td>{@render download(d, decade(d))}</td>
                                    </tr>
                                {/each}
                            </tbody>
                        </table>
                    </div>
                </SectionBlock>
            {/if}

            <SectionBlock
                kind="alt"
                title={$i18n.t('open_data.rights.title')}
                intro={$i18n.t('open_data.rights.intro')}
                maxWidth="900px"
            >
                {#if rights.length}
                    <div class="vff-table-wrap">
                        <table class="vff-table">
                            <caption class="vff-sr-only"
                                >{$i18n.t('open_data.rights.title')}</caption
                            >
                            <thead>
                                <tr>
                                    <th scope="col">{$i18n.t('open_data.rights.col_item')}</th>
                                    <th scope="col" class="num"
                                        >{$i18n.t('open_data.rights.col_replaced')}</th
                                    >
                                    <th scope="col" class="num"
                                        >{$i18n.t('open_data.rights.col_removed')}</th
                                    >
                                </tr>
                            </thead>
                            <tbody>
                                {#each rights as r (r.key)}
                                    <tr>
                                        <th scope="row">{itemLabel(r.key)}</th>
                                        <td class="num">{r.replaced.toLocaleString(lang)}</td>
                                        <td class="num">{r.removed.toLocaleString(lang)}</td>
                                    </tr>
                                {/each}
                            </tbody>
                        </table>
                    </div>
                {/if}
            </SectionBlock>

            {#if credits.length}
                <SectionBlock
                    title={$i18n.t('open_data.credits.title')}
                    intro={$i18n.t('open_data.credits.intro')}
                    maxWidth="900px"
                >
                    <ul class="credits">
                        {#each credits as c (c.key)}
                            <li>
                                {#if c.url}
                                    <a
                                        class="vff-link"
                                        href={c.url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        >{c.name}<span class="vff-sr-only">
                                            ({$i18n.t('common.new_tab')})</span
                                        ></a
                                    >
                                {:else}
                                    {c.name}
                                {/if}
                                <span class="muted">{c.licence}</span>
                            </li>
                        {/each}
                    </ul>
                </SectionBlock>
            {/if}
        {/if}
    </main>
</Layout>

<style>
    .prose p {
        max-width: var(--vff-measure);
        margin: 0 0 var(--vff-space-3);
        font-size: var(--vff-fs-body);
        line-height: 1.6;
        text-wrap: pretty;
    }
    .name {
        display: block;
        font-weight: 600;
        color: var(--vff-text);
    }
    .muted {
        color: var(--vff-text-muted);
        font-size: var(--vff-fs-sm);
    }
    th .muted {
        display: block;
        font-weight: 400;
    }
    th .text {
        max-width: 48ch;
        margin: 2px 0;
        font-size: var(--vff-fs-md);
        line-height: 1.5;
    }
    .nowrap {
        white-space: nowrap;
    }
    .files {
        margin-top: var(--vff-space-2);
    }
    .files summary {
        cursor: pointer;
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
    .files ul {
        margin: var(--vff-space-2) 0 0;
        padding: 0;
        list-style: none;
    }
    .files li {
        display: flex;
        flex-wrap: wrap;
        gap: 0 var(--vff-space-2);
        align-items: baseline;
        min-height: 24px;
    }
    .credits {
        display: grid;
        gap: var(--vff-space-2);
        margin: 0;
        padding: 0;
        list-style: none;
        font-size: var(--vff-fs-body);
    }
    .credits li {
        display: flex;
        flex-wrap: wrap;
        gap: 0 var(--vff-space-2);
        align-items: baseline;
        padding-bottom: var(--vff-space-2);
        border-bottom: 1px solid var(--vff-border);
    }
    /* Petit écran : chaque jeu devient un bloc (titre, date, taille, téléchargement) au lieu d'une ligne à faire défiler. */
    @media (max-width: 640px) {
        .stack thead {
            position: absolute;
            width: 1px;
            height: 1px;
            overflow: hidden;
            clip-path: inset(50%);
            white-space: nowrap;
        }
        .stack tr {
            display: block;
            padding: var(--vff-space-3) var(--vff-space-3);
            border-bottom: 1px solid var(--vff-border);
        }
        .vff-table.stack tbody th,
        .vff-table.stack tbody td {
            display: block;
            padding: 2px 0;
            border: 0;
            text-align: left;
        }
        .stack td.num {
            text-align: left;
        }
    }
</style>

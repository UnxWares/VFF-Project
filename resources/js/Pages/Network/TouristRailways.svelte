<script>
    /**
     * « Chemins de fer touristiques et vélorails » : chaque exploitation en service aujourd'hui (train à vapeur,
     * autorail, tramway, crémaillère, vélorail) avec son exploitant, son écartement, sa longueur, son année de début,
     * sa région, ses liens et la source de chaque fait ; les voies vertes sur ancienne voie dans une section à part.
     * Les données viennent de App\Services\TouristRailways (mêmes valeurs que GET /api/v1/tourist-railways) ;
     * recherche, type, région et tri se font dans le navigateur, la carte suit les filtres.
     */
    import Layout from '$components/Layout.svelte';
    import PageHero from '$components/PageHero.svelte';
    import SectionBlock from '$components/SectionBlock.svelte';
    import SeoHead from '$components/SeoHead.svelte';
    import DirectoryFilters from '$components/Directory/DirectoryFilters.svelte';
    import DirectoryTable from '$components/Directory/DirectoryTable.svelte';
    import EmptyState from '$components/ui/EmptyState.svelte';
    import TouristMap from '$components/Network/TouristMap.svelte';
    import { i18n } from '$lib/i18n.js';
    import { NBSP, formatNumber } from '$lib/typo.js';
    import {
        countByType,
        filterGreenways,
        filterRailways,
        kmOf,
        sortRailways,
        wikidataUrl,
    } from '$lib/touristRailways.js';

    /** @type {{ data: { railways: Array<any>, greenways: Array<any>, sources: Record<string, any>, regions: Array<string>, types: Array<string> } }} */
    let { data } = $props();

    const PAGE = 25;

    /** @param {string} key @param {Record<string, string|number>} [vars] */
    const t = (key, vars) => String($i18n.t(`tourist.${key}`, vars));
    const lang = $derived($i18n.language ?? 'fr');
    const n = (v) => formatNumber(v, lang);
    const km = (meters) =>
        t('km', { value: formatNumber(kmOf(meters), lang, { maximumFractionDigits: 1 }) });

    let q = $state('');
    let type = $state('');
    let region = $state('');
    let sort = $state(/** @type {'name' | 'length' | 'since'} */ ('name'));
    let shown = $state(PAGE);

    const filtered = $derived(
        sortRailways(filterRailways(data.railways, { q, type, region }), sort),
    );
    const greenways = $derived(filterGreenways(data.greenways, { q, region }));
    const counts = $derived(countByType(data.railways, data.types));
    const hasFilters = $derived(!!(q || type || region || sort !== 'name'));
    const trains = $derived(data.railways.filter((r) => r.type !== 'velorail').length);
    const velorails = $derived(data.railways.length - trains);
    const withLength = $derived(data.railways.filter((r) => r.length_m).length);
    const withLine = $derived(data.railways.filter((r) => r.lines.length).length);
    const totalKm = $derived(data.railways.reduce((sum, r) => sum + (r.length_m ?? 0), 0));

    function reset() {
        q = '';
        type = '';
        region = '';
        sort = 'name';
        shown = PAGE;
    }

    const gaugeLabel = (mm) => {
        const name = { 1435: 'gauge_1435', 1000: 'gauge_1000' }[mm];
        return name ? `${n(mm)}${NBSP}mm (${t(name)})` : `${n(mm)}${NBSP}mm`;
    };
    const gauges = (r) => (r.gauges_mm?.length ? r.gauges_mm : r.gauge_mm ? [r.gauge_mm] : []);

    const srcLabel = (code) => t(`src.${String(code).replaceAll('-', '_')}`);
    /** Adresse de la source d'un fait : article de l'exploitation, fiche Wikidata, sinon adresse du registre. */
    function srcUrl(code, r) {
        if (code === 'wp-article') return r.wikipedia_url;
        if (code === 'official-site') return r.website;
        if (code === 'wikidata') return wikidataUrl(r.wikidata) ?? data.sources.wikidata?.url;
        if (code === 'vff') return r.lines[0] ? `/lines/${r.lines[0].slug}` : null;
        return data.sources[code]?.url ?? null;
    }
    const srcShort = (code) => t(`src_short.${String(code).replaceAll('-', '_')}`);
    /** Faits regroupés par source : [code, [faits]] dans l'ordre des faits. */
    function sourceGroups(r) {
        /** @type {Record<string, string[]>} */
        const groups = {};
        for (const f of FACTS) {
            const code = r.sources?.[f];
            if (code) (groups[code] ??= []).push(f);
        }
        return Object.entries(groups);
    }
    const FACTS = ['type', 'gauge', 'region', 'length', 'start_year', 'operator', 'website'];
    const factsOf = (r) => FACTS.filter((f) => r.sources?.[f]);
</script>

<SeoHead title={t('meta_title')} description={t('meta_description')} path="/tourist-railways" />
<svelte:head>
    <title>{t('meta_title')} - VFF</title>
</svelte:head>

<Layout compactHeader>
    <main>
        <PageHero
            title={t('hero_title')}
            subtitle={t('hero_subtitle', {
                trains: n(trains),
                velorails: n(velorails),
                km: n(Math.round(totalKm / 1000)),
            })}
        />

        <div class="vff-container directory">
            <DirectoryFilters
                label={t('filters_title')}
                results={t('results', { count: n(filtered.length) })}
                canReset={hasFilters}
                resetLabel={t('reset')}
                onreset={reset}
            >
                <div class="vff-field wide">
                    <label class="vff-label" for="tr-q">{t('search_label')}</label>
                    <input
                        id="tr-q"
                        class="vff-input"
                        type="search"
                        autocomplete="off"
                        enterkeyhint="search"
                        placeholder={t('search_hint')}
                        bind:value={q}
                        oninput={() => (shown = PAGE)}
                    />
                </div>
                <div class="vff-field">
                    <label class="vff-label" for="tr-type">{t('filter_type')}</label>
                    <select id="tr-type" class="vff-select" bind:value={type}>
                        <option value="">{t('all_types')}</option>
                        {#each data.types as tp (tp)}
                            <option value={tp}>{t(`types.${tp}`)} ({n(counts[tp])})</option>
                        {/each}
                    </select>
                </div>
                <div class="vff-field">
                    <label class="vff-label" for="tr-region">{t('filter_region')}</label>
                    <select id="tr-region" class="vff-select" bind:value={region}>
                        <option value="">{t('all_regions')}</option>
                        {#each data.regions as rg (rg)}
                            <option value={rg}>{rg}</option>
                        {/each}
                    </select>
                </div>
                <div class="vff-field">
                    <label class="vff-label" for="tr-sort">{t('sort_label')}</label>
                    <select id="tr-sort" class="vff-select" bind:value={sort}>
                        <option value="name">{t('sort_name')}</option>
                        <option value="length">{t('sort_length')}</option>
                        <option value="since">{t('sort_since')}</option>
                    </select>
                </div>
            </DirectoryFilters>

            <TouristMap railways={filtered} />
            <p class="note">{t('map_note')}</p>

            {#if filtered.length}
                <DirectoryTable label={t('table_label')} caption={t('table_caption')}>
                    <thead>
                        <tr>
                            <th scope="col">{t('col_name')}</th>
                            <th scope="col">{t('col_type')}</th>
                            <th scope="col">{t('col_gauge')}</th>
                            <th scope="col">{t('col_length')}</th>
                            <th scope="col">{t('col_since')}</th>
                            <th scope="col">{t('col_region')}</th>
                            <th scope="col">{t('col_links')}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {#each filtered as r (r.id)}
                            <tr id={r.id}>
                                <td class="name">
                                    <span class="rw-name">{r.name}</span>
                                    {#if r.operator}
                                        <span class="operator"
                                            >{t('operator', { name: r.operator })}</span
                                        >
                                    {/if}
                                </td>
                                <td data-label={t('col_type')}>{t(`types.${r.type}`)}</td>
                                <td data-label={t('col_gauge')}>
                                    {#if gauges(r).length}
                                        {gauges(r).map(gaugeLabel).join(' ; ')}
                                    {:else}<span class="muted">{t('unknown')}</span>{/if}
                                </td>
                                <td data-label={t('col_length')}>
                                    {#if r.length_m}{km(r.length_m)}{:else}<span class="muted"
                                            >{t('unknown')}</span
                                        >{/if}
                                </td>
                                <td data-label={t('col_since')}>
                                    {#if r.start_year}{r.start_year}{:else}<span class="muted"
                                            >{t('unknown')}</span
                                        >{/if}
                                </td>
                                <td data-label={t('col_region')}>
                                    {#if r.regions.length}{r.regions.join(', ')}{:else}<span
                                            class="muted">{t('unknown')}</span
                                        >{/if}
                                </td>
                                <td data-label={t('col_links')}>
                                    <ul class="links">
                                        {#each r.lines as l (l.slug)}
                                            <li><a href="/lines/{l.slug}">{l.name}</a></li>
                                        {/each}
                                        {#if r.website}
                                            <li>
                                                <a href={r.website} rel="noopener"
                                                    >{t('website')}<span class="vff-sr-only">
                                                        : {r.name}</span
                                                    ></a
                                                >
                                            </li>
                                        {/if}
                                        {#if !r.lines.length && !r.website}
                                            <li class="muted">{t('no_links')}</li>
                                        {/if}
                                    </ul>
                                    {#if factsOf(r).length}
                                        <details class="sources">
                                            <summary>{t('sources')}</summary>
                                            <ul>
                                                {#each sourceGroups(r) as [code, facts] (code)}
                                                    {@const url = srcUrl(code, r)}
                                                    <li>
                                                        {#if url}<a href={url}>{srcShort(code)}</a
                                                            >{:else}{srcShort(code)}{/if}<span
                                                            class="muted"
                                                            >{t('colon')}{facts
                                                                .map((f) =>
                                                                    t(`fact.${f}`).toLowerCase(),
                                                                )
                                                                .join(', ')}</span
                                                        >
                                                    </li>
                                                {/each}
                                            </ul>
                                        </details>
                                    {/if}
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </DirectoryTable>
            {:else}
                <EmptyState icon="bi-search" title={t('empty_title')} text={t('empty_text')} />
            {/if}
        </div>

        <SectionBlock
            kind="alt"
            title={t('greenways_title')}
            intro={t('greenways_intro')}
            maxWidth="1180px"
        >
            {#if greenways.length}
                <p class="count" role="status">
                    {t('greenways_results', { count: n(greenways.length) })}
                </p>
                <DirectoryTable label={t('gw_table_label')} caption={t('gw_table_caption')}>
                    <thead>
                        <tr>
                            <th scope="col">{t('gw_col_name')}</th>
                            <th scope="col">{t('gw_col_length')}</th>
                            <th scope="col">{t('col_region')}</th>
                            <th scope="col">{t('gw_col_line')}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {#each greenways.slice(0, shown) as g (`${g.line.slug}-${g.name}`)}
                            <tr>
                                <td class="name">
                                    <span class="rw-name">{g.name}</span>
                                    <a class="operator" href={g.osm_url} rel="noopener"
                                        >{t('gw_osm')}<span class="vff-sr-only">
                                            : {g.name}</span
                                        ></a
                                    >
                                </td>
                                <td data-label={t('gw_col_length')}>{km(g.length_m)}</td>
                                <td data-label={t('col_region')}>
                                    {#if g.regions.length}{g.regions.join(', ')}{:else}<span
                                            class="muted">{t('unknown')}</span
                                        >{/if}
                                </td>
                                <td data-label={t('gw_col_line')}>
                                    <a href="/lines/{g.line.slug}">{g.line.name}</a>
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </DirectoryTable>
                {#if greenways.length > shown}
                    <p class="more">
                        <button
                            type="button"
                            class="vff-btn secondary"
                            onclick={() => (shown += PAGE)}
                        >
                            {t('show_more', { count: n(greenways.length - shown) })}
                        </button>
                    </p>
                {/if}
            {:else}
                <p class="note">{t('gw_empty')}</p>
            {/if}
            <p class="note">{t('greenways_source')}</p>
        </SectionBlock>

        <SectionBlock title={t('method_title')} maxWidth="1180px">
            <p class="method">
                {t('method_coverage', {
                    total: n(data.railways.length),
                    lines: n(withLine),
                    lengths: n(withLength),
                })}
            </p>
            <p class="method">{t('method_types')}</p>
            <ul class="method-sources">
                {#each Object.entries(data.sources) as [code, s] (code)}
                    {#if !['wp-article', 'wp-line', 'correction', 'official-site'].includes(code)}
                        <li>
                            {#if s.url}<a href={s.url}>{srcLabel(code)}</a>{:else}{srcLabel(
                                    code,
                                )}{/if}{#if s.licence}
                                ({s.licence}){/if}
                        </li>
                    {/if}
                {/each}
            </ul>
            <p class="method">
                <a href="/api/v1/tourist-railways">{t('api_link')}</a>
            </p>
        </SectionBlock>
    </main>
</Layout>

<style>
    main {
        background-color: var(--vff-bg);
    }
    .directory {
        display: flex;
        flex-direction: column;
        gap: var(--vff-block-space);
        padding-top: var(--vff-block-space);
        padding-bottom: var(--vff-block-space);
    }
    .note,
    .method {
        max-width: var(--vff-measure);
        margin: 0;
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-body);
        line-height: 1.6;
        color: var(--vff-text-muted);
    }
    .directory > .note {
        margin-top: calc(-1 * var(--vff-space-3));
    }
    .method {
        color: var(--vff-text);
        margin-bottom: var(--vff-space-4);
    }
    .method-sources {
        max-width: var(--vff-measure);
        margin: 0 0 var(--vff-space-4);
        padding-left: 1.2em;
        font-size: var(--vff-fs-body);
        line-height: 1.6;
    }
    .count {
        margin: 0 0 var(--vff-space-4);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
    .more {
        margin: var(--vff-space-5) 0 0;
    }
    .name {
        min-width: 13rem;
        overflow-wrap: anywhere;
    }
    @media (max-width: 600px) {
        .name {
            min-width: 0;
        }
    }
    .rw-name {
        display: block;
        font-family: var(--vff-font-display);
        font-size: var(--vff-fs-md);
        line-height: 1.3;
        color: var(--vff-text);
    }
    .operator {
        display: block;
        margin-top: 2px;
        font-size: var(--vff-fs-xs);
        color: var(--vff-text-muted);
    }
    a.operator {
        width: fit-content;
        padding-block: 4px;
    }
    .muted {
        color: var(--vff-text-muted);
    }
    .links {
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .links li + li {
        margin-top: var(--vff-space-2);
    }
    .links a,
    .sources a {
        display: inline-block;
        padding-block: 3px;
    }
    .sources {
        margin-top: var(--vff-space-2);
        font-size: var(--vff-fs-xs);
    }
    .sources summary {
        width: fit-content;
        padding-block: 4px;
        color: var(--vff-text-muted);
        cursor: pointer;
    }
    .sources ul {
        margin: var(--vff-space-2) 0 0;
        padding: 0;
        list-style: none;
    }
    .sources li {
        margin-bottom: var(--vff-space-2);
    }
</style>

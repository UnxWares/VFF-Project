<script>
    /**
     * « Patrimoine ferroviaire protégé » : les bâtiments voyageurs, ouvrages de gare et ouvrages d'art classés ou inscrits
     * aux monuments historiques, ou labellisés « Architecture contemporaine remarquable », avec la date de protection,
     * l'architecte, la construction, le style, la commune, une photo libre, la notice Mérimée et la fiche VFF.
     * Les données viennent de App\Services\Heritage\HeritageIndex (mêmes valeurs que GET /api/v1/heritage) ;
     * recherche, filtres, tri et vue par architecte se font dans le navigateur, la carte suit les filtres.
     */
    import Layout from '$components/Layout.svelte';
    import PageHero from '$components/PageHero.svelte';
    import SectionBlock from '$components/SectionBlock.svelte';
    import SeoHead from '$components/SeoHead.svelte';
    import DirectoryFilters from '$components/Directory/DirectoryFilters.svelte';
    import EmptyState from '$components/ui/EmptyState.svelte';
    import HeritageMap from '$components/Heritage/HeritageMap.svelte';
    import { i18n } from '$lib/i18n.js';
    import { formatNumber } from '$lib/typo.js';
    import {
        builtRange,
        countBy,
        filterHeritage,
        groupByArchitect,
        groupId,
        sortHeritage,
    } from '$lib/heritage.js';

    /**
     * @type {{ data: { items: Array<any>, facets: { protections: Array<{key: string, count: number}>, objects: Array<{key: string, count: number}>, eras: Array<{key: string, count: number}>, styles: Array<{name: string, count: number}>, regions: Array<{name: string, count: number}>, architects: Array<{name: string, count: number}> }, attribution: string } }}
     */
    let { data } = $props();

    const PAGE = 24;
    const GROUPS = 12;

    /** @param {string} key @param {Record<string, string|number>} [vars] */
    const t = (key, vars) => String($i18n.t(`heritage.${key}`, vars));
    const lang = $derived($i18n.language ?? 'fr');
    const n = (v) => formatNumber(v, lang);

    // Recherche reprise de l'adresse (`/heritage?q=Gare de Limoges-Bénédictins`) : lien « Voir dans le patrimoine » des fiches de gare.
    let q = $state(
        typeof window === 'undefined'
            ? ''
            : (new URLSearchParams(window.location.search).get('q') ?? ''),
    );
    let protection = $state('');
    let object = $state('');
    let style = $state('');
    let architect = $state('');
    let region = $state('');
    let era = $state('');
    let sort = $state(/** @type {'name' | 'protection_date' | 'built'} */ ('name'));
    let view = $state(/** @type {'list' | 'architect'} */ ('list'));
    let shown = $state(PAGE);
    let shownGroups = $state(GROUPS);

    const filtered = $derived(
        sortHeritage(
            filterHeritage(data.items, { q, protection, object, style, architect, region, era }),
            sort,
        ),
    );
    const groups = $derived(groupByArchitect(filtered));
    const hasFilters = $derived(
        !!(q || protection || object || style || architect || region || era || sort !== 'name'),
    );
    const counts = $derived({
        protection: countBy(
            data.items,
            'protection',
            data.facets.protections.map((p) => p.key),
        ),
        object: countBy(
            data.items,
            'object',
            data.facets.objects.map((o) => o.key),
        ),
        era: countBy(
            data.items,
            'era',
            data.facets.eras.map((e) => e.key),
        ),
    });
    const buildings = $derived(
        data.items.filter((i) => i.object === 'building' || i.object === 'works').length,
    );
    const structures = $derived(data.items.length - buildings);

    function reset() {
        q = protection = object = style = architect = region = era = '';
        sort = 'name';
        shown = PAGE;
        shownGroups = GROUPS;
    }
    function changed() {
        shown = PAGE;
        shownGroups = GROUPS;
    }

    const dateLabel = (iso) =>
        new Date(`${iso}T12:00:00`).toLocaleDateString(lang, {
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        });
    const place = (i) =>
        i.commune
            ? t(i.station ? 'commune' : 'commune_nearest', {
                  commune: i.commune,
                  region: i.region ?? '',
              })
            : (i.region ?? null);
    function builtText(i) {
        const range = builtRange(i.built);
        if (range) {
            return range.length === 1
                ? t('built', { year: range[0] })
                : t('built_range', { from: range[0], to: range[1] });
        }
        return null;
    }
</script>

<SeoHead title={t('meta_title')} description={t('meta_description')} path="/heritage" />
<svelte:head>
    <title>{t('meta_title')} - VFF</title>
</svelte:head>

{#snippet card(i)}
    <li id={i.id} class="monument">
        {#if i.image}
            <figure class="photo">
                <img src={i.image.url} alt={i.name} loading="lazy" decoding="async" />
                <figcaption>
                    {#if i.image.author}{t('photo_credit', {
                            author: i.image.author,
                            license: i.image.license ?? '',
                        })}{:else}{t('photo_credit_anon', { license: i.image.license ?? '' })}{/if}
                    {#if i.image.source_url}
                        <a href={i.image.source_url} rel="noopener"
                            >{t('photo_source')}<span class="vff-sr-only"> : {i.name}</span></a
                        >
                    {/if}
                </figcaption>
            </figure>
        {/if}
        <div class="body">
            <h3 class="name">{i.name || t('unnamed')}</h3>
            <p class="badges">
                <span class={['badge', i.protection]}>{t(`protections.${i.protection}`)}</span>
                <span class="kind">{t(`objects.${i.object}`)}</span>
            </p>
            <ul class="facts">
                {#if i.protection !== 'acr'}
                    <li>
                        {#if i.protection_date}{t('protected_on', {
                                date: dateLabel(i.protection_date),
                            })}{:else}{t('protected_unknown')}{/if}{#if i.scope === 'partial'},
                            {t('partial')}{/if}
                    </li>
                {:else if i.protection_date}
                    <li>{t('protected_on', { date: dateLabel(i.protection_date) })}</li>
                {/if}
                {#if place(i)}<li>{place(i)}</li>{/if}
                {#if i.architects.length}
                    <li>
                        {t(i.architects.length > 1 ? 'architects_many' : 'architects', {
                            names: i.architects.join(', '),
                        })}
                    </li>
                {/if}
                {#if builtText(i)}
                    <li>{builtText(i)}</li>
                {:else if i.built.period}
                    <li>{t('built_period', { period: i.built.period })}</li>
                {/if}
                {#if i.style}<li>{t('style', { style: i.style })}</li>{/if}
            </ul>
            <p class="links">
                {#if i.station}
                    <a href="/stations/{i.station.slug}#heritage"
                        >{t('station_link')}<span class="vff-sr-only"> : {i.name}</span></a
                    >
                {:else if i.line}
                    <a href="/lines/{i.line.slug}"
                        >{t('line_link')}<span class="vff-sr-only"> : {i.name}</span></a
                    >
                {/if}
                {#if i.reference?.url}
                    <a href={i.reference.url} rel="noopener"
                        >{t(
                            i.reference.id.startsWith('Q') ? 'notice_link_wikidata' : 'notice_link',
                        )}<span class="vff-sr-only"> : {i.name}</span></a
                    >
                {/if}
            </p>
        </div>
    </li>
{/snippet}

<Layout compactHeader>
    <main>
        <PageHero
            title={t('hero_title')}
            subtitle={t('hero_subtitle', {
                total: n(data.items.length),
                architects: n(data.facets.architects.length),
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
                    <label class="vff-label" for="hr-q">{t('search_label')}</label>
                    <input
                        id="hr-q"
                        class="vff-input"
                        type="search"
                        autocomplete="off"
                        enterkeyhint="search"
                        placeholder={t('search_hint')}
                        bind:value={q}
                        oninput={changed}
                    />
                </div>
                <div class="vff-field">
                    <label class="vff-label" for="hr-protection">{t('filter_protection')}</label>
                    <select
                        id="hr-protection"
                        class="vff-select"
                        bind:value={protection}
                        onchange={changed}
                    >
                        <option value="">{t('all_protections')}</option>
                        {#each data.facets.protections as p (p.key)}
                            <option value={p.key}
                                >{t(`protections.${p.key}`)} ({n(counts.protection[p.key])})</option
                            >
                        {/each}
                    </select>
                </div>
                <div class="vff-field">
                    <label class="vff-label" for="hr-object">{t('filter_object')}</label>
                    <select
                        id="hr-object"
                        class="vff-select"
                        bind:value={object}
                        onchange={changed}
                    >
                        <option value="">{t('all_objects')}</option>
                        {#each data.facets.objects as o (o.key)}
                            <option value={o.key}
                                >{t(`objects.${o.key}`)} ({n(counts.object[o.key])})</option
                            >
                        {/each}
                    </select>
                </div>
                {#if data.facets.styles.length}
                    <div class="vff-field">
                        <label class="vff-label" for="hr-style">{t('filter_style')}</label>
                        <select
                            id="hr-style"
                            class="vff-select"
                            bind:value={style}
                            onchange={changed}
                        >
                            <option value="">{t('all_styles')}</option>
                            {#each data.facets.styles as s (s.name)}
                                <option value={s.name}>{s.name} ({n(s.count)})</option>
                            {/each}
                        </select>
                    </div>
                {/if}
                <div class="vff-field">
                    <label class="vff-label" for="hr-architect">{t('filter_architect')}</label>
                    <select
                        id="hr-architect"
                        class="vff-select"
                        bind:value={architect}
                        onchange={changed}
                    >
                        <option value="">{t('all_architects')}</option>
                        {#each data.facets.architects as a (a.name)}
                            <option value={a.name}>{a.name} ({n(a.count)})</option>
                        {/each}
                    </select>
                </div>
                <div class="vff-field">
                    <label class="vff-label" for="hr-region">{t('filter_region')}</label>
                    <select
                        id="hr-region"
                        class="vff-select"
                        bind:value={region}
                        onchange={changed}
                    >
                        <option value="">{t('all_regions')}</option>
                        {#each data.facets.regions as r (r.name)}
                            <option value={r.name}>{r.name} ({n(r.count)})</option>
                        {/each}
                    </select>
                </div>
                <div class="vff-field">
                    <label class="vff-label" for="hr-era">{t('filter_era')}</label>
                    <select id="hr-era" class="vff-select" bind:value={era} onchange={changed}>
                        <option value="">{t('all_eras')}</option>
                        {#each data.facets.eras as e (e.key)}
                            <option value={e.key}
                                >{t(`eras.${e.key}`)} ({n(counts.era[e.key])})</option
                            >
                        {/each}
                    </select>
                </div>
                <div class="vff-field">
                    <label class="vff-label" for="hr-sort">{t('sort_label')}</label>
                    <select id="hr-sort" class="vff-select" bind:value={sort}>
                        <option value="name">{t('sort_name')}</option>
                        <option value="protection_date">{t('sort_protection_date')}</option>
                        <option value="built">{t('sort_built')}</option>
                    </select>
                </div>
            </DirectoryFilters>

            <HeritageMap items={filtered} />
            <p class="note">{t('map_note')}</p>

            <div class="views" role="group" aria-label={t('view_label')}>
                <button
                    type="button"
                    class={['vff-btn', view === 'list' ? 'primary' : 'secondary']}
                    aria-pressed={view === 'list'}
                    onclick={() => (view = 'list')}>{t('view_list')}</button
                >
                <button
                    type="button"
                    class={['vff-btn', view === 'architect' ? 'primary' : 'secondary']}
                    aria-pressed={view === 'architect'}
                    onclick={() => (view = 'architect')}>{t('view_architect')}</button
                >
            </div>

            {#if !filtered.length}
                <EmptyState icon="bi-search" title={t('empty_title')} text={t('empty_text')} />
            {:else if view === 'list'}
                <h2 class="vff-sr-only">{t('list_label')}</h2>
                <ul class="monuments">
                    {#each filtered.slice(0, shown) as i (i.id)}
                        {@render card(i)}
                    {/each}
                </ul>
                {#if filtered.length > shown}
                    <p class="more">
                        <button
                            type="button"
                            class="vff-btn secondary"
                            onclick={() => (shown += PAGE)}
                        >
                            {t('show_more', { count: n(filtered.length - shown) })}
                        </button>
                    </p>
                {/if}
            {:else}
                <p class="note">{t('group_intro')}</p>
                {#each groups.slice(0, shownGroups) as g (g.name ?? '')}
                    <section
                        class="group"
                        aria-labelledby={g.name ? groupId(g.name) : 'architecte-none'}
                    >
                        <h2 id={g.name ? groupId(g.name) : 'architecte-none'} class="group-title">
                            {g.name ?? t('group_none')}
                            <span class="group-count"
                                >{t('group_count', { count: n(g.items.length) })}</span
                            >
                        </h2>
                        <ul class="monuments">
                            {#each g.items as i (i.id)}
                                {@render card(i)}
                            {/each}
                        </ul>
                    </section>
                {/each}
                {#if groups.length > shownGroups}
                    <p class="more">
                        <button
                            type="button"
                            class="vff-btn secondary"
                            onclick={() => (shownGroups += GROUPS)}
                        >
                            {t('show_more', { count: n(groups.length - shownGroups) })}
                        </button>
                    </p>
                {/if}
            {/if}
        </div>

        <SectionBlock kind="alt" title={t('method_title')} maxWidth="1180px">
            <p class="method">
                {t('method_coverage', { buildings: n(buildings), structures: n(structures) })}
            </p>
            <p class="method">{t('method_sources')}</p>
            <p class="method">{t('method_limits')}</p>
            <p class="method">
                <a href="/api/v1/heritage">{t('api_link')}</a>
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
    .views {
        display: flex;
        flex-wrap: wrap;
        gap: var(--vff-space-3);
    }
    .more {
        margin: 0;
    }
    .monuments {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-4);
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .monument {
        display: flex;
        gap: var(--vff-space-5);
        padding: var(--vff-space-4);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        background-color: var(--vff-bg-elevated);
        scroll-margin-top: var(--vff-space-6);
    }
    .photo {
        flex: 0 0 220px;
        width: 220px;
        margin: 0;
    }
    .photo img {
        display: block;
        width: 100%;
        aspect-ratio: 4 / 3;
        object-fit: cover;
        border-radius: var(--vff-radius-sm);
        background-color: var(--vff-bg-subtle);
    }
    .photo figcaption {
        margin-top: var(--vff-space-2);
        font-size: var(--vff-fs-xs);
        line-height: 1.4;
        color: var(--vff-text-muted);
        overflow-wrap: anywhere;
    }
    .photo figcaption a {
        display: inline-block;
        padding-block: 4px;
    }
    .body {
        flex: 1 1 0;
        min-width: 0;
    }
    .name {
        margin: 0;
        font-family: var(--vff-font-display);
        font-size: var(--vff-fs-lead);
        line-height: 1.25;
        color: var(--vff-text);
        overflow-wrap: anywhere;
    }
    .badges {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: var(--vff-space-2) var(--vff-space-3);
        margin: var(--vff-space-2) 0 0;
    }
    .badge {
        padding: 2px var(--vff-space-3);
        border: 1px solid var(--vff-border-strong);
        border-radius: var(--vff-radius-pill);
        font-size: var(--vff-fs-xs);
        line-height: 1.4;
        color: var(--vff-text);
    }
    .badge.classified {
        background-color: var(--vff-bg-subtle);
        font-weight: 700;
    }
    .kind {
        font-size: var(--vff-fs-xs);
        color: var(--vff-text-muted);
    }
    .facts {
        margin: var(--vff-space-3) 0 0;
        padding: 0;
        list-style: none;
        font-size: var(--vff-fs-body);
        line-height: 1.5;
        color: var(--vff-text);
    }
    .facts li + li {
        margin-top: var(--vff-space-1);
    }
    .links {
        display: flex;
        flex-wrap: wrap;
        gap: 0 var(--vff-space-5);
        margin: var(--vff-space-3) 0 0;
    }
    .links a {
        display: inline-block;
        padding-block: 6px;
    }
    .group {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-4);
    }
    .group-title {
        margin: var(--vff-space-4) 0 0;
        font-family: var(--vff-font-display);
        font-size: var(--vff-fs-h2);
        line-height: 1.25;
    }
    .group-count {
        display: block;
        margin-top: var(--vff-space-1);
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-sm);
        font-weight: 400;
        color: var(--vff-text-muted);
    }
    @media (max-width: 700px) {
        .monument {
            flex-direction: column;
            gap: var(--vff-space-3);
        }
        .photo {
            flex-basis: auto;
            width: 100%;
            max-width: 420px;
        }
    }
</style>

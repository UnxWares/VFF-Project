<script>
    /**
     * « Matériel préservé » : les lieux (musées, associations, lignes touristiques, sites) qui conservent du matériel
     * roulant, avec leurs pièces : numéro, série, état, protection monument historique et sources. Recherche, type de
     * lieu et pièces protégées se filtrent dans le navigateur. Données : App\Services\Materials\PreservedRollingStock
     * (mêmes valeurs que GET /api/v1/materials/preserved).
     */
    import { Link } from '@inertiajs/svelte';
    import Layout from '$components/Layout.svelte';
    import PageHero from '$components/PageHero.svelte';
    import DirectoryFilters from '$components/Directory/DirectoryFilters.svelte';
    import EmptyState from '$components/ui/EmptyState.svelte';
    import PreservedPiece from '$components/Materials/PreservedPiece.svelte';
    import { i18n } from '$lib/i18n.js';
    import { countByType, filterVenues } from '$lib/preservedStock.js';

    /** @type {{ venues: Array<any>, figures: Record<string, number>, types: Array<string>, sources: Record<string, any> }} */
    let { venues = [], figures = {}, types = [], sources = {} } = $props();

    const lang = $derived($i18n.language ?? 'fr');
    const t = (key, opts) => String($i18n.t(key, opts));
    const fmt = (n) => Number(n ?? 0).toLocaleString(lang);

    const PAGE = 24;

    let q = $state('');
    let type = $state('');
    let protectedOnly = $state(false);
    let shown = $state(PAGE);

    const filtered = $derived(filterVenues(venues, { q, type, protectedOnly }));
    const visible = $derived(filtered.slice(0, shown));
    const counts = $derived(countByType(venues, types));
    const pieceTotal = $derived(filtered.reduce((sum, v) => sum + v.count, 0));
    const hasFilters = $derived(q !== '' || type !== '' || protectedOnly);

    // Un nouveau filtre ramène la liste à sa première page.
    $effect(() => {
        void q;
        void type;
        void protectedOnly;
        shown = PAGE;
    });

    function reset() {
        q = '';
        type = '';
        protectedOnly = false;
    }
</script>

<svelte:head>
    <title>{t('materials_preserved.meta_title')} - VFF</title>
    <meta name="description" content={t('materials_preserved.meta_description')} />
</svelte:head>

<Layout compactHeader>
    <main>
        <PageHero
            title={t('materials_preserved.title')}
            subtitle={t('materials_preserved.subtitle', {
                units: fmt(figures.units),
                series: fmt(figures.series),
                venues: fmt(figures.venues),
                protected: fmt(figures.protected),
            })}
        >
            <p class="back">
                <Link href="/materials" class="vff-link"
                    ><i class="bi bi-arrow-left" aria-hidden="true"></i>
                    {t('materials_preserved.all_materials')}</Link
                >
            </p>
        </PageHero>

        <div class="vff-container page">
            <DirectoryFilters
                label={t('materials_preserved.filters_title')}
                results={t('materials_preserved.results', {
                    count: filtered.length,
                    pieces: fmt(pieceTotal),
                })}
                canReset={hasFilters}
                resetLabel={t('materials_preserved.reset')}
                onreset={reset}
            >
                <div class="vff-field">
                    <label class="vff-label" for="pv-q"
                        >{t('materials_preserved.search_label')}</label
                    >
                    <input
                        id="pv-q"
                        class="vff-input"
                        type="search"
                        autocomplete="off"
                        enterkeyhint="search"
                        placeholder={t('materials_preserved.search_hint')}
                        bind:value={q}
                    />
                </div>
                <div class="vff-field">
                    <label class="vff-label" for="pv-type"
                        >{t('materials_preserved.type_filter')}</label
                    >
                    <select id="pv-type" class="vff-select" bind:value={type}>
                        <option value="">{t('materials_preserved.all_types')}</option>
                        {#each types as ty (ty)}
                            <option value={ty}
                                >{t(`materials_preserved.type.${ty}`)} ({fmt(counts[ty])})</option
                            >
                        {/each}
                    </select>
                </div>
                <label class="check">
                    <input type="checkbox" bind:checked={protectedOnly} />
                    <span>{t('materials_preserved.protected_only')}</span>
                </label>
            </DirectoryFilters>

            {#if filtered.length === 0}
                <EmptyState
                    icon="bi-search"
                    title={t('materials_preserved.empty_title')}
                    text={t('materials_preserved.empty_text')}
                >
                    <button type="button" class="vff-btn secondary small" onclick={reset}>
                        {t('materials_preserved.reset')}
                    </button>
                </EmptyState>
            {:else}
                <ul class="venues">
                    {#each visible as venue (venue.slug)}
                        <li class="venue">
                            <h2 id="venue-{venue.slug}">
                                {venue.name}
                            </h2>
                            <ul class="meta">
                                <li>{t(`materials_preserved.type_one.${venue.type}`)}</li>
                                {#if venue.commune}<li>{venue.commune}</li>{/if}
                                <li>{t('materials_preserved.pieces', { count: venue.count })}</li>
                                {#if venue.tourist_railway}
                                    <li>
                                        <a class="vff-link" href={venue.tourist_railway.url}
                                            >{t('materials_preserved.see_tourist')}</a
                                        >
                                    </li>
                                {/if}
                            </ul>
                            <details open={venue.count <= 4 || q !== ''}>
                                <summary
                                    >{t('materials_preserved.show_pieces')} ({fmt(
                                        venue.count,
                                    )})</summary
                                >
                                <ul class="pieces" aria-labelledby="venue-{venue.slug}">
                                    {#each venue.pieces as piece, i (`${piece.number ?? piece.title}-${i}`)}
                                        <PreservedPiece {piece} showVenue={false} showSeries />
                                    {/each}
                                </ul>
                            </details>
                        </li>
                    {/each}
                </ul>
                {#if filtered.length > shown}
                    <div class="more">
                        <button
                            type="button"
                            class="vff-btn secondary"
                            onclick={() => (shown += PAGE)}
                            >{t('materials_preserved.more_venues')}</button
                        >
                    </div>
                {/if}
            {/if}

            <section class="coverage" aria-labelledby="pv-coverage">
                <h2 id="pv-coverage">{t('materials_preserved.coverage_title')}</h2>
                <p>{t('materials_preserved.coverage_text')}</p>
                <h3>{t('materials_preserved.source_list')}</h3>
                <ul>
                    {#each Object.entries(sources) as [key, s] (key)}
                        <li>
                            {#if s.url}<a
                                    class="vff-link"
                                    href={s.url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    >{s.label}<span class="vff-sr-only">
                                        ({$i18n.t('common.new_tab')})</span
                                    ></a
                                >{:else}{s.label}{/if}
                            ({s.licence})
                        </li>
                    {/each}
                </ul>
            </section>
        </div>
    </main>
</Layout>

<style>
    main {
        background-color: var(--vff-bg);
    }
    .back {
        margin: var(--vff-space-4) 0 0;
    }
    .page {
        display: flex;
        flex-direction: column;
        gap: var(--vff-block-space);
        padding-top: var(--vff-block-space);
        padding-bottom: var(--vff-section-space);
    }
    .check {
        display: flex;
        align-items: center;
        gap: var(--vff-space-3);
        min-height: 44px;
    }
    .check input {
        width: 22px;
        height: 22px;
        flex: none;
    }
    .venues {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-5);
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .venue {
        padding: var(--vff-space-5);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        background-color: var(--vff-bg-elevated);
    }
    .venue h2 {
        margin: 0;
        font-size: var(--vff-fs-h3);
        line-height: 1.3;
        overflow-wrap: anywhere;
    }
    .meta {
        display: flex;
        flex-wrap: wrap;
        column-gap: var(--vff-space-3);
        margin: var(--vff-space-2) 0 var(--vff-space-4);
        padding: 0;
        list-style: none;
        color: var(--vff-text-muted);
    }
    .meta li:not(:last-child)::after {
        content: '·';
        margin-left: var(--vff-space-3);
    }
    summary {
        cursor: pointer;
        min-height: 44px;
        display: flex;
        align-items: center;
        font-weight: 600;
    }
    .pieces {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(min(100%, 24rem), 1fr));
        gap: var(--vff-space-4);
        margin: var(--vff-space-3) 0 0;
        padding: 0;
        list-style: none;
    }
    .more {
        display: flex;
        justify-content: center;
    }
    .coverage {
        max-width: var(--vff-measure);
    }
    .coverage h2 {
        margin: 0 0 var(--vff-space-3);
        font-size: var(--vff-fs-lead);
    }
    .coverage h3 {
        margin: var(--vff-space-5) 0 var(--vff-space-2);
        font-size: var(--vff-fs-body);
    }
    .coverage ul {
        margin: 0;
        padding-left: var(--vff-space-5);
    }
</style>

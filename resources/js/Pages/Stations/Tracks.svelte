<script>
    import Layout from '$components/Layout.svelte';
    import SheetHero from '$components/Sheet/SheetHero.svelte';
    import SeoHead from '$components/SeoHead.svelte';
    import TrackLayout from '$components/Lines/TrackLayout.svelte';
    import StationPlatformsTable from '$components/Stations/StationPlatformsTable.svelte';
    import { i18n } from '$lib/i18n.js';
    import { breadcrumbSchema } from '$lib/structuredData.js';
    import { pkLabel } from '$lib/trackLayout.js';
    import {
        fetchStationTracks,
        hasStationTracks,
        linePlatforms,
        lineTracksHref,
    } from '$lib/stationTracks.js';

    /**
     * Voies d'une gare (`/stations/{slug}/voies`, StationTrackLayoutPageController) : pour chaque ligne qui passe par la
     * gare, le schéma des voies de la ligne autour de la gare (composant TrackLayout de la page d'une ligne, quais
     * dessinés sous les voies), une ligne à la fois quand la gare en a plusieurs ; puis les quais de la gare. Les
     * données sont lues par l'API (GET /api/v1/stations/{station}/track-layout) : la page elle-même ne porte que
     * l'identité de la gare.
     *
     * @type {{ station: { slug: string, name: string, city?: string|null, department_code?: string|null } }}
     */
    let { station } = $props();

    const t = (k, o) => String($i18n.t(k, o));
    const lang = $derived($i18n.language ?? 'fr');
    const sheetHref = $derived(`/stations/${station.slug}`);
    const path = $derived(`${sheetHref}/voies`);

    let status = $state(/** @type {'loading'|'ready'|'error'} */ ('loading'));
    /** @type {any} */
    let data = $state.raw(null);
    let chosen = $state(0);

    async function load(slug) {
        status = 'loading';
        try {
            data = await fetchStationTracks(slug);
            chosen = 0;
            status = 'ready';
        } catch {
            status = 'error';
        }
    }
    $effect(() => {
        load(station.slug);
    });

    const lines = $derived(data?.lines ?? []);
    const block = $derived(lines[chosen] ?? lines[0] ?? null);
    const platforms = $derived(block ? linePlatforms(data, block.line.code) : []);
    const empty = $derived(status === 'ready' && !hasStationTracks(data));
    // Noms des lignes : en français, quelle que soit la langue de la page.
    const nameLang = $derived(lang === 'fr' ? undefined : 'fr');
    // Commune et département ; la commune est omise quand le nom de la gare la contient déjà (« Cognac (16) »).
    const place = $derived.by(() => {
        const dept = station.department_code ? `(${station.department_code})` : '';
        const city =
            station.city && !station.name.toLowerCase().includes(station.city.toLowerCase())
                ? station.city
                : '';
        return [city, dept].filter(Boolean).join(' ') || null;
    });
</script>

<svelte:head>
    <title>{t('tracks.station.meta_title', { name: station.name })} - VFF</title>
</svelte:head>

<SeoHead
    title={t('tracks.station.meta_title', { name: station.name })}
    description={t('tracks.station.meta_description', { name: station.name })}
    {path}
    jsonLd={(origin) => [
        breadcrumbSchema(origin, [
            { name: t('common.app_full'), path: '/' },
            { name: t('nav.stations'), path: '/stations' },
            { name: station.name, path: sheetHref },
            { name: t('tracks.station.title'), path },
        ]),
    ]}
/>

<Layout compactHeader>
    <main>
        <SheetHero
            crumbs={[
                { href: '/stations', label: t('tracks.station.all_stations'), icon: 'bi-list-ul' },
                { href: sheetHref, label: t('tracks.station.back'), icon: 'bi-arrow-left' },
            ]}
            crumbsLabel={t('tracks.station.breadcrumbs')}
            title={t('tracks.station.title')}
            wide
        >
            <p class="station-name">
                {station.name}{#if place}<span class="place">{place}</span>{/if}
            </p>
        </SheetHero>

        <section class="tracks-page" aria-label={t('tracks.station.title')}>
            <p class="intro">{t('tracks.station.intro')}</p>
            {#if status === 'loading'}
                <p class="state" role="status">{t('tracks.station.loading')}</p>
            {:else if status === 'error'}
                <p class="state" role="alert">
                    {t('tracks.station.error')}
                    <button
                        type="button"
                        class="vff-btn secondary"
                        onclick={() => load(station.slug)}>{t('tracks.retry')}</button
                    >
                </p>
            {:else if empty}
                <div class="empty">
                    <p class="lead">{t('tracks.station.empty')}</p>
                    <p>{t('tracks.station.empty_detail')}</p>
                </div>
            {:else}
                {#if lines.length > 1}
                    <!-- Une gare de bifurcation : une ligne à la fois (chaque ligne a ses propres PK). -->
                    <fieldset class="choose">
                        <legend>{t('tracks.station.lines_label')}</legend>
                        {#each lines as b, i (b.line.slug)}
                            <label class={['pick', i === chosen && 'on']}>
                                <input
                                    type="radio"
                                    name="station-line"
                                    value={i}
                                    checked={i === chosen}
                                    onchange={() => (chosen = i)}
                                />
                                {#if b.line.code}<span class="vff-plate">{b.line.code}</span>{/if}
                                <span class="pick-name" lang={nameLang}>{b.line.name}</span>
                            </label>
                        {/each}
                    </fieldset>
                {/if}

                {#if block}
                    <div class="line-head">
                        <p class="line-title">
                            {#if block.line.code && lines.length === 1}<span class="vff-plate"
                                    >{block.line.code}</span
                                >{/if}
                            <a class="vff-link" href="/lines/{block.line.slug}" lang={nameLang}
                                >{block.line.name}</a
                            >
                        </p>
                        <p class="line-meta">
                            {#if block.station_pk_m !== null}<span
                                    >{t('tracks.station.at_pk', {
                                        pk: pkLabel(block.station_pk_m),
                                    })}</span
                                >{/if}
                            <a
                                class="vff-link"
                                href={lineTracksHref(block.line.slug, block.station_pk_m)}
                                aria-label={t('tracks.station.whole_line_label', {
                                    name: block.line.name,
                                })}>{t('tracks.station.whole_line')}</a
                            >
                        </p>
                    </div>
                    {#key block.line.slug}
                        <TrackLayout
                            layout={block.track_layout}
                            slug={block.line.slug}
                            lineName={block.line.name}
                            stationSlug={station.slug}
                            startPk={block.station_pk_m}
                            {platforms}
                            full
                        >
                            {#if data.platforms.length}
                                <section class="quais" aria-labelledby="quais-title">
                                    <h2 id="quais-title">
                                        {t('tracks.station.platforms.title')}
                                    </h2>
                                    <StationPlatformsTable
                                        platforms={data.platforms}
                                        {lang}
                                        {t}
                                        drawing
                                    />
                                    <p class="note">{t('tracks.station.platforms.note')}</p>
                                </section>
                            {/if}
                        </TrackLayout>
                    {/key}
                {:else}
                    <!-- Des quais connus, aucune voie décrite autour de la gare. -->
                    <div class="empty">
                        <p class="lead">{t('tracks.station.empty')}</p>
                        <p>{t('tracks.station.empty_detail')}</p>
                    </div>
                    <section class="quais" aria-labelledby="quais-title">
                        <h2 id="quais-title">{t('tracks.station.platforms.title')}</h2>
                        <StationPlatformsTable platforms={data.platforms} {lang} {t} />
                        <p class="note">{t('tracks.station.platforms.note')}</p>
                    </section>
                {/if}
            {/if}
        </section>
    </main>
</Layout>

<style>
    main {
        padding-bottom: var(--vff-section-space);
        background-color: var(--vff-bg);
    }
    /* Plein écran : toute la largeur utile jusqu'à 2 400 px, comme le schéma d'une ligne. */
    .tracks-page {
        max-width: calc(2400px + 2 * var(--vff-gutter));
        margin: 0 auto;
        padding: var(--vff-space-6) var(--vff-gutter) 0;
    }
    .station-name {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        gap: 0 var(--vff-space-3);
        margin: var(--vff-space-2) 0 var(--vff-space-6);
        color: var(--vff-text-muted);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-lead);
    }
    .place {
        font-size: var(--vff-fs-md);
    }
    .intro {
        max-width: var(--vff-measure);
        margin: 0 0 var(--vff-space-5);
        color: var(--vff-text-muted);
        font-size: var(--vff-fs-md);
        line-height: var(--vff-lh-body);
    }
    .state {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: var(--vff-space-3);
        margin: 0;
        color: var(--vff-text-muted);
    }
    .empty {
        max-width: var(--vff-measure);
        margin-bottom: var(--vff-space-5);
        padding: var(--vff-pad-box);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        background: var(--vff-bg-subtle);
        color: var(--vff-text-muted);
    }
    .empty p {
        margin: 0;
    }
    .empty .lead {
        margin-bottom: var(--vff-space-2);
        color: var(--vff-text);
        font-weight: 700;
    }
    .choose {
        display: flex;
        flex-wrap: wrap;
        gap: var(--vff-space-2);
        min-width: 0;
        margin: 0 0 var(--vff-space-5);
        padding: 0;
        border: 0;
    }
    .choose legend {
        margin-bottom: var(--vff-space-2);
        padding: 0;
        font-family: var(--vff-font-heading);
        font-weight: 700;
    }
    .pick {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        max-width: 100%;
        min-height: 44px;
        padding: 6px 16px 6px 12px;
        border: 1px solid var(--vff-border-strong);
        border-radius: var(--vff-radius-pill);
        background: var(--vff-bg-elevated);
        font-family: var(--vff-font-heading);
        cursor: pointer;
    }
    .pick:hover {
        background: var(--vff-bg-muted);
    }
    .pick.on {
        border-color: var(--vff-accent-strong);
        box-shadow: inset 0 0 0 1px var(--vff-accent-strong);
        font-weight: 700;
    }
    .pick:has(input:focus-visible) {
        outline: var(--vff-focus-width) solid var(--vff-focus);
        outline-offset: var(--vff-focus-offset);
    }
    .pick input {
        width: 18px;
        height: 18px;
        margin: 0;
        accent-color: var(--vff-accent-strong);
    }
    .pick-name {
        min-width: 0;
        overflow-wrap: anywhere;
    }
    .line-head {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        justify-content: space-between;
        gap: var(--vff-space-2) var(--vff-space-5);
        margin-bottom: var(--vff-space-4);
    }
    .line-title,
    .line-meta {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        gap: var(--vff-space-2) var(--vff-space-4);
        margin: 0;
    }
    .line-title {
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-md);
        font-weight: 700;
    }
    .line-meta {
        color: var(--vff-text-muted);
    }
    .quais h2 {
        margin: var(--vff-space-2) 0 var(--vff-space-3);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-h3);
    }
    .quais .note {
        max-width: var(--vff-measure);
        margin: var(--vff-space-3) 0 0;
        color: var(--vff-text-muted);
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-ui);
    }
</style>

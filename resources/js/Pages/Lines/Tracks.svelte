<script>
    import Layout from '$components/Layout.svelte';
    import SheetHero from '$components/Sheet/SheetHero.svelte';
    import SeoHead from '$components/SeoHead.svelte';
    import TrackLayout from '$components/Lines/TrackLayout.svelte';
    import { i18n } from '$lib/i18n.js';
    import { breadcrumbSchema } from '$lib/structuredData.js';
    import { fetchTrackLayout, hasTracks, normalize } from '$lib/trackLayout.js';

    /**
     * Schéma des voies d'une ligne en plein écran (`/lines/{slug}/voies`, LineTrackLayoutPageController) : le
     * dessin sur toute la largeur, le tableau de parcours à droite quand la place le permet. Les données sont
     * lues par l'API (GET /api/v1/lines/{line}/track-layout), comme la section de la fiche : la page elle-même ne
     * porte que l'identité de la ligne.
     *
     * @type {{ line: { slug: string, code: string|null, name: string, name_lang?: string|null } }}
     */
    let { line } = $props();

    const t = (k, o) => String($i18n.t(k, o));
    const lang = $derived($i18n.language ?? 'fr');
    const lineHref = $derived(`/lines/${line.slug}`);
    const path = $derived(`${lineHref}/voies`);
    // Noms des lignes : en français, quelle que soit la langue de la page.
    const nameLang = $derived(line.name_lang ?? (lang === 'fr' ? undefined : 'fr'));

    let status = $state(/** @type {'loading'|'ready'|'error'} */ ('loading'));
    /** @type {any} */
    let layout = $state.raw(null);

    async function load() {
        status = 'loading';
        try {
            layout = await fetchTrackLayout(line.slug);
            status = 'ready';
        } catch {
            status = 'error';
        }
    }
    $effect(() => {
        load();
    });

    const empty = $derived(status === 'ready' && !hasTracks(normalize(layout)));
</script>

<svelte:head>
    <title>{t('tracks.page.meta_title', { name: line.name })} - VFF</title>
</svelte:head>

<SeoHead
    title={t('tracks.page.meta_title', { name: line.name })}
    description={t('tracks.page.meta_description', { name: line.name })}
    {path}
    jsonLd={(origin) => [
        breadcrumbSchema(origin, [
            { name: t('common.app_full'), path: '/' },
            { name: t('nav.lines'), path: '/lines' },
            { name: line.name, path: lineHref },
            { name: t('tracks.page.title'), path },
        ]),
    ]}
/>

<Layout compactHeader>
    <main>
        <SheetHero
            crumbs={[
                { href: '/lines', label: t('tracks.page.all_lines'), icon: 'bi-list-ul' },
                { href: lineHref, label: t('tracks.page.back'), icon: 'bi-arrow-left' },
            ]}
            crumbsLabel={t('tracks.page.breadcrumbs')}
            plate={line.code}
            title={t('tracks.page.title')}
            wide
        >
            <p class="line-name" lang={nameLang}>{line.name}</p>
        </SheetHero>

        <section class="tracks-page" aria-label={t('tracks.page.title')}>
            <p class="intro">{t('tracks.intro')}</p>
            {#if status === 'loading'}
                <p class="state" role="status">{t('tracks.loading')}</p>
            {:else if status === 'error'}
                <p class="state" role="alert">
                    {t('tracks.error')}
                    <button type="button" class="vff-btn secondary" onclick={load}
                        >{t('tracks.retry')}</button
                    >
                </p>
            {:else if empty}
                <div class="empty">
                    <p class="lead">{t('tracks.empty')}</p>
                    <p>{t('tracks.empty_detail')}</p>
                </div>
            {:else}
                <TrackLayout {layout} slug={line.slug} lineName={line.name} full />
            {/if}
        </section>
    </main>
</Layout>

<style>
    main {
        padding-bottom: var(--vff-section-space);
        background-color: var(--vff-bg);
    }
    /* Plein écran : toute la largeur utile jusqu'à 2 400 px, au lieu de la grille de 1 180 px des fiches. */
    .tracks-page {
        max-width: calc(2400px + 2 * var(--vff-gutter));
        margin: 0 auto;
        padding: var(--vff-space-6) var(--vff-gutter) 0;
    }
    .line-name {
        margin: var(--vff-space-2) 0 var(--vff-space-6);
        color: var(--vff-text-muted);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-lead);
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
</style>

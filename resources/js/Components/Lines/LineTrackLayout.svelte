<script>
    import { untrack } from 'svelte';
    import { i18n } from '$lib/i18n.js';
    import SheetSection from '$components/Sheet/SheetSection.svelte';
    import TrackLayout from '$components/Lines/TrackLayout.svelte';
    import { pkLabel } from '$lib/trackLayout.js';
    import { TrackLayoutLoader } from '$lib/trackLayoutLoader.svelte.js';

    /**
     * Section « Schéma des voies » de la fiche ligne (état actuel seulement). Lue à la demande, quand la section
     * approche de l'écran (GET /api/v1/lines/{line}/track-layout) : la fiche ne fait aucune requête de plus. Repliée
     * par défaut : le résumé dit ce que la ligne a (voies, aiguillages, signaux), ou qu'aucune donnée détaillée
     * n'existe ; le schéma complet s'ouvre sur place ou en plein écran (/lines/{slug}/voies).
     *
     * @type {{ slug: string, lineName: string }}
     */
    let { slug, lineName } = $props();

    const t = (k, o) => String($i18n.t(k, o));

    // Aperçu seulement (étendue, points d'exploitation, nombre d'objets : quelques dizaines de Ko) ; les voies sont
    // lues à l'ouverture du schéma, d'un coup ou par portions (TrackLayoutLoader).
    // Un chargeur par ligne (la page peut recevoir une autre ligne sans être recréée).
    let loader = $state.raw(new TrackLayoutLoader(untrack(() => slug)));
    $effect.pre(() => {
        const next = slug;
        if (untrack(() => loader.slug) !== next) loader = new TrackLayoutLoader(next);
    });
    const status = $derived(loader.status);
    let open = $state(false);
    /** @type {HTMLElement|undefined} */
    let sectionEl = $state();

    const load = () => loader.loadOverview();

    // Lu quand la section approche de l'écran (600 px d'avance), une seule fois.
    $effect(() => {
        if (!sectionEl || status !== 'idle') return;
        if (typeof IntersectionObserver === 'undefined') {
            load();
            return;
        }
        const io = new IntersectionObserver(
            (entries) => {
                if (entries.some((e) => e.isIntersecting)) {
                    io.disconnect();
                    load();
                }
            },
            { rootMargin: '600px 0px' },
        );
        io.observe(sectionEl);
        return () => io.disconnect();
    });

    const data = $derived(loader.overview);
    const summary = $derived(
        data
            ? {
                  ops: data.counts?.operational_points ?? data.operational_points?.length ?? 0,
                  switches: data.counts?.switches ?? 0,
                  signals: data.counts?.signals ?? 0,
              }
            : null,
    );
    const fullHref = $derived(`/lines/${encodeURIComponent(slug)}/voies`);
</script>

<SheetSection id="track-layout" title={t('tracks.title')} intro={t('tracks.intro')} bind:sectionEl>
    {#if status === 'idle' || status === 'loading'}
        <p class="state" role="status">
            {#if status === 'loading'}{t('tracks.loading')}{:else}<button
                    type="button"
                    class="vff-btn secondary"
                    onclick={load}>{t('tracks.load')}</button
                >{/if}
        </p>
    {:else if status === 'error'}
        <p class="state" role="alert">
            {t('tracks.error')}
            <button type="button" class="vff-btn secondary" onclick={load}
                >{t('tracks.retry')}</button
            >
        </p>
    {:else if data && (data.counts?.tracks ?? 0) === 0}
        <div class="empty">
            <p class="lead">{t('tracks.empty')}</p>
            <p>{t('tracks.empty_detail')}</p>
        </div>
    {:else if data && summary}
        <p class="summary">
            {t('tracks.summary', {
                ops: t('tracks.count.ops', { count: summary.ops }),
                switches: t('tracks.count.switches', { count: summary.switches }),
                signals: t('tracks.count.signals', { count: summary.signals }),
                from: pkLabel(data.pk.start_m),
                to: pkLabel(data.pk.end_m),
            })}
            <a class="vff-link full" href={fullHref}>{t('tracks.open_full')}</a>
        </p>
        <details class="fold" bind:open>
            <summary>{t('tracks.show')}</summary>
            {#if open}
                <div class="inner">
                    <TrackLayout {loader} {slug} {lineName} />
                </div>
            {/if}
        </details>
    {/if}
</SheetSection>

<style>
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
    .summary {
        max-width: var(--vff-measure);
        margin: 0 0 var(--vff-space-4);
    }
    .full {
        white-space: nowrap;
    }
    .fold summary {
        display: inline-flex;
        align-items: center;
        min-height: 44px;
        padding: 8px 16px;
        border: 1px solid var(--vff-border-control);
        border-radius: var(--vff-radius-sm);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-md);
        cursor: pointer;
    }
    .fold summary:focus-visible {
        outline: var(--vff-focus-width) solid var(--vff-focus);
        outline-offset: var(--vff-focus-offset);
    }
    .fold[open] summary {
        margin-bottom: var(--vff-space-5);
    }
</style>

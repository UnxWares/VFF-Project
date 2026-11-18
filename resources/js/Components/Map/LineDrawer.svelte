<script>
    import { Link } from '@inertiajs/svelte';
    import { i18n } from '$lib/i18n.js';
    import { formatNumber } from '$lib/typo.js';
    import { swatchStyle } from '$lib/railStyle.js';
    import {
        electrificationInfo,
        isTrue,
        statusLabel,
        statusStroke,
        trafficInfo,
    } from './lineInfo.js';

    /**
     * Fiche d'une ligne ouverte au clic sur un tracé (RailMap → onLineClick), posée sur la
     * carte : à droite, à côté des boutons de zoom (sans les couvrir) sur ordinateur, en bas
     * de la carte sur téléphone. Mêmes mots et mêmes couleurs que la légende.
     *
     * Props :
     *   line       : { slug, name, code, status, traffic_type, is_lgv, heritage_kind, network,
     *                  era, max_speed_kmh, electrified, voltage_v } ou null (fermée)
     *   currentEra : décennie « actuelle » (affichée « aujourd'hui », jamais comme une année)
     */
    let { line = $bindable(null), currentEra = 2030 } = $props();

    const uid = $props.id();
    const titleId = `${uid}-title`;
    let panelEl = $state();

    /** Traduction (toujours une chaîne : i18next la type aussi en objet). */
    const t = (/** @type {string} */ key, /** @type {Record<string, unknown>} */ params = {}) =>
        String($i18n.t(key, params));
    const lang = $derived($i18n.language);

    const traffic = $derived(line ? trafficInfo(line, t) : null);
    const stroke = $derived(line ? statusStroke(line.status) : null);
    const electrification = $derived(
        line && isTrue(line.electrified) ? electrificationInfo(line.voltage_v, t, lang) : null,
    );
    const statusTitle = $derived(
        !line || line.era == null
            ? t('maps.drawer.status')
            : line.era === currentEra
              ? t('maps.drawer.status_today')
              : t('maps.drawer.status_at', { year: line.era }),
    );

    // À chaque ouverture (et à chaque nouvelle ligne cliquée), le focus va à la fiche : un
    // lecteur d'écran l'annonce et la touche Tab mène à ses liens.
    function focusOnOpen(node) {
        void line?.slug;
        void line?.era;
        node.focus({ preventScroll: true });
    }

    // Fermeture : le focus revient à la carte s'il était dans la fiche.
    function close() {
        const hadFocus = panelEl?.contains(document.activeElement);
        const canvas = panelEl?.closest('.rail-map-wrapper')?.querySelector('.maplibregl-canvas');
        line = null;
        if (hadFocus) canvas?.focus({ preventScroll: true });
    }

    // Échap ferme la fiche quand le focus est dedans.
    function onKeydown(e) {
        if (e.key === 'Escape' && line && panelEl?.contains(document.activeElement)) close();
    }
</script>

<svelte:window onkeydown={onKeydown} />

{#if line}
    <section
        class="drawer"
        aria-labelledby={titleId}
        tabindex="-1"
        bind:this={panelEl}
        {@attach focusOnOpen}
    >
        <div class="drawer-head">
            <div class="head-text">
                {#if line.code}
                    <span class="vff-plate">{line.code}</span>
                {/if}
                <h3 id={titleId}>
                    {#if line.slug}
                        <Link href={`/lines/${line.slug}`}
                            >{line.name ?? t('maps.drawer.fallback_line_name')}</Link
                        >
                    {:else}
                        {line.name ?? t('maps.drawer.fallback_line_name')}
                    {/if}
                </h3>
            </div>
            <button
                type="button"
                class="close-btn"
                onclick={close}
                aria-label={t('maps.drawer.close')}
                title={t('maps.drawer.close')}
            >
                <i class="bi bi-x-lg" aria-hidden="true"></i>
            </button>
        </div>

        <div class="drawer-body">
            <dl class="props">
                <div class="prop">
                    <dt>{statusTitle}</dt>
                    <dd>
                        {#if stroke}
                            <i
                                class="swatch"
                                style={swatchStyle(stroke.color, stroke.dash)}
                                aria-hidden="true"
                            ></i>
                        {/if}
                        {statusLabel(line.status, t)}
                    </dd>
                </div>
                {#if traffic}
                    <div class="prop">
                        <dt>
                            {line.heritage_kind
                                ? t('maps.drawer.heritage')
                                : t('maps.drawer.traffic')}
                        </dt>
                        <dd>
                            <i class="swatch" style={swatchStyle(traffic.color)} aria-hidden="true"
                            ></i>
                            {traffic.label}
                        </dd>
                    </div>
                {/if}
                {#if line.network}
                    <div class="prop">
                        <dt>{t('maps.drawer.network')}</dt>
                        <dd>{t(`maps.networks.${line.network}`)}</dd>
                    </div>
                {/if}
                {#if line.max_speed_kmh}
                    <div class="prop">
                        <dt>{t('maps.drawer.max_speed')}</dt>
                        <dd class="num">{formatNumber(line.max_speed_kmh, lang)}&nbsp;km/h</dd>
                    </div>
                {/if}
                {#if electrification}
                    <div class="prop">
                        <dt>{t('maps.drawer.electrification')}</dt>
                        <dd>
                            <i
                                class="swatch"
                                style={swatchStyle(electrification.color)}
                                aria-hidden="true"
                            ></i>
                            {electrification.label}
                        </dd>
                    </div>
                {/if}
            </dl>

            {#if line.slug}
                <Link href={`/lines/${line.slug}`} class="vff-btn primary block">
                    {t('maps.drawer.view_full_sheet')}
                </Link>
                <ul class="drawer-links">
                    <li>
                        <Link
                            href={`/contribute/edit-segment?line=${encodeURIComponent(line.code ?? line.slug)}`}
                            class="vff-link"
                        >
                            <i class="bi bi-pencil-square" aria-hidden="true"></i>
                            {t('maps.drawer.suggest_edit')}
                        </Link>
                    </li>
                    <li>
                        <Link
                            href={`/contribute/trace?line=${encodeURIComponent(line.slug)}`}
                            class="vff-link"
                        >
                            <i class="bi bi-bezier2" aria-hidden="true"></i>
                            {t('editor.drawer_link')}
                        </Link>
                    </li>
                </ul>
            {/if}
        </div>
    </section>
{/if}

<style>
    /* Ordinateur : à droite de la carte, à gauche de la colonne des boutons de zoom (52 px). */
    .drawer {
        position: absolute;
        top: 12px;
        right: 52px;
        z-index: 3;
        display: flex;
        flex-direction: column;
        width: min(320px, calc(100% - 64px));
        max-height: calc(100% - 24px);
        border: 1px solid var(--vff-border-strong);
        border-radius: var(--vff-radius-lg);
        background-color: var(--vff-bg-elevated);
        box-shadow: var(--vff-shadow-popover);
        color: var(--vff-text);
        animation: drawer-in 150ms ease-out;
    }
    @keyframes drawer-in {
        from {
            opacity: 0;
        }
        to {
            opacity: 1;
        }
    }

    .drawer-head {
        display: flex;
        align-items: flex-start;
        gap: 8px;
        padding: 14px 8px 12px 16px;
        border-bottom: 1px solid var(--vff-border);
    }
    .head-text {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
        flex: 1;
        min-width: 0;
    }
    h3 {
        margin: 0;
        font-family: var(--vff-font-display);
        font-size: 1.125rem;
        font-weight: 700;
        line-height: var(--vff-lh-heading);
        text-wrap: balance;
        overflow-wrap: anywhere;
    }
    h3 :global(a) {
        color: var(--vff-text);
        text-decoration: none;
    }
    h3 :global(a:hover) {
        color: var(--vff-link);
        text-decoration: underline;
    }
    .close-btn {
        flex-shrink: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        border: 0;
        border-radius: var(--vff-radius-sm);
        background: transparent;
        color: var(--vff-text-muted);
        font-size: 16px;
        cursor: pointer;
        transition:
            background-color var(--vff-transition),
            color var(--vff-transition);
    }
    .close-btn:hover {
        background-color: var(--vff-bg-muted);
        color: var(--vff-text);
    }

    .drawer-body {
        display: flex;
        flex-direction: column;
        gap: 16px;
        min-height: 0;
        padding: 4px 16px 16px;
        overflow-y: auto;
        overscroll-behavior: contain;
    }
    /* Caractéristiques en tableau à filets, comme un livret de ligne. */
    .props {
        margin: 0;
    }
    .prop {
        display: grid;
        grid-template-columns: minmax(0, 7.5rem) minmax(0, 1fr);
        gap: 4px 12px;
        align-items: baseline;
        padding: 10px 0;
        border-bottom: 1px solid var(--vff-border);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-ui);
    }
    .prop dt {
        color: var(--vff-text-muted);
    }
    .prop dd {
        margin: 0;
        color: var(--vff-text);
    }
    .prop dd.num {
        font-variant-numeric: tabular-nums;
    }
    .swatch {
        display: inline-block;
        width: 18px;
        margin-right: 6px;
        border-radius: 2px;
        vertical-align: 0.2em;
    }
    .drawer-links {
        display: flex;
        flex-direction: column;
        gap: 4px;
        margin: -4px 0 0;
        padding: 0;
        list-style: none;
    }

    /* Téléphone : fiche en bas de la carte, sur la moitié de sa hauteur au plus. */
    @media (max-width: 600px) {
        .drawer {
            top: auto;
            right: 0;
            bottom: 0;
            left: 0;
            width: auto;
            max-height: 55%;
            border-width: 1px 0 0;
            border-radius: var(--vff-radius-lg) var(--vff-radius-lg) 0 0;
        }
        .drawer-head {
            padding: 10px 6px 10px 16px;
        }
        .prop {
            padding: 8px 0;
        }
    }
</style>

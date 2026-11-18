<script>
    import { router } from '@inertiajs/svelte';
    import { i18n } from '$lib/i18n.js';

    /**
     * Drawer latéral qui s'affiche au clic sur une ligne dans RailMap.
     * Remplace la popup MapLibre par une vraie fiche UX (slide-in droit).
     *
     * Props :
     *   line : objet { slug, name, status, max_speed_kmh, electrified, voltage_v }
     *          ou null pour fermer
     */

    let { line = $bindable(null) } = $props();

    // Libellé de statut traduit, avec fallback sur le code brut si la clé manque.
    function statusLabel(status) {
        const key = `maps.drawer.status_labels.${status}`;
        const label = $i18n.t(key);
        return label === key ? status : label;
    }

    function close() {
        line = null;
    }

    function navigateToFiche() {
        if (!line?.slug) return;
        router.visit(`/lines/${line.slug}`);
    }

    function onKeydown(e) {
        if (e.key === 'Escape' && line) close();
    }
</script>

<svelte:window onkeydown={onKeydown} />

{#if line}
    <aside class="drawer" role="dialog" aria-modal="false" aria-label={line.name}>
        <div class="drawer-head">
            <div class="head-inner">
                <p class="eyebrow">
                    <i class="bi bi-bezier"></i>
                    {line.code ?? $i18n.t('lines.section.route')}
                </p>
                {#if line.slug}
                    <button type="button" class="title-link" onclick={navigateToFiche}>
                        <h2>{line.name ?? $i18n.t('maps.drawer.fallback_line_name')}</h2>
                    </button>
                {:else}
                    <h2>{line.name ?? $i18n.t('maps.drawer.fallback_line_name')}</h2>
                {/if}
            </div>
            <button
                type="button"
                class="close-btn"
                onclick={close}
                aria-label={$i18n.t('common.close')}
            >
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <div class="drawer-body">
            <dl class="props">
                <div class="prop">
                    <dt>{$i18n.t('maps.drawer.status')}</dt>
                    <dd>
                        <span class="status-pill status-{line.status}">
                            {statusLabel(line.status)}
                        </span>
                    </dd>
                </div>
                {#if line.max_speed_kmh}
                    <div class="prop">
                        <dt>{$i18n.t('maps.drawer.max_speed')}</dt>
                        <dd><strong>{line.max_speed_kmh}</strong> km/h</dd>
                    </div>
                {/if}
                {#if line.electrified === true || line.electrified === 'true'}
                    <div class="prop">
                        <dt>{$i18n.t('maps.drawer.electrification')}</dt>
                        <dd>
                            <i class="bi bi-lightning-charge-fill accent"></i>
                            {#if line.voltage_v}
                                <strong
                                    >{(line.voltage_v / 1000).toLocaleString($i18n.language, {
                                        minimumFractionDigits: 1,
                                        maximumFractionDigits: 1,
                                    })}</strong
                                > kV
                            {:else}
                                {$i18n.t('maps.drawer.electrified_yes')}
                            {/if}
                        </dd>
                    </div>
                {/if}
            </dl>

            {#if line.slug}
                <button type="button" class="vff-btn primary cta" onclick={navigateToFiche}>
                    <i class="bi bi-arrow-right-circle-fill"></i>
                    {$i18n.t('maps.drawer.view_full_sheet')}
                </button>
                <a
                    href={`/contribute/edit-segment?line=${encodeURIComponent(line.code ?? line.slug)}`}
                    class="suggest-link"
                >
                    <i class="bi bi-pencil-square"></i>
                    {$i18n.t('maps.drawer.suggest_edit')}
                </a>
                <a
                    href={`/contribute/trace?line=${encodeURIComponent(line.slug)}`}
                    class="suggest-link"
                >
                    <i class="bi bi-bezier2"></i>
                    {$i18n.t('editor.drawer_link')}
                </a>
            {/if}
        </div>
    </aside>
{/if}

<style>
    .drawer {
        position: absolute;
        top: 14px;
        right: 14px;
        z-index: 50;
        width: 340px;
        max-width: calc(100% - 28px);
        max-height: calc(100% - 28px);
        background-color: var(--vff-bg);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        box-shadow: 0 16px 48px var(--vff-shadow-strong);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        animation: drawer-slide-in 260ms cubic-bezier(0.2, 0.8, 0.2, 1);
    }
    @keyframes drawer-slide-in {
        from {
            transform: translateX(20px);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    .drawer-head {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 16px 18px 14px;
        border-bottom: 1px solid var(--vff-border);
        background: linear-gradient(135deg, var(--vff-accent-soft) 0%, transparent 100%);
    }
    .head-inner {
        flex: 1;
    }
    .eyebrow {
        margin: 0;
        font-family: var(--vff-font-display);
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--vff-accent);
        display: flex;
        align-items: center;
        gap: 6px;
    }
    h2 {
        margin: 4px 0 0;
        font-family: var(--vff-font-display);
        font-size: 18px;
        font-weight: 700;
        line-height: 1.3;
        color: var(--vff-text);
    }
    h2:after {
        display: none;
    }
    .title-link {
        background: transparent;
        border: 0;
        padding: 0;
        text-align: left;
        cursor: pointer;
        transition: color var(--vff-transition);
    }
    .title-link:hover h2 {
        color: var(--vff-accent);
    }

    .close-btn {
        background: transparent;
        border: 0;
        padding: 6px 8px;
        border-radius: var(--vff-radius-sm);
        color: var(--vff-text-muted);
        font-size: 16px;
        cursor: pointer;
        transition: all var(--vff-transition);
    }
    .close-btn:hover {
        background-color: var(--vff-bg-muted);
        color: var(--vff-text);
    }

    .drawer-body {
        padding: 16px 18px 18px;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        gap: 14px;
    }
    .props {
        margin: 0;
        display: grid;
        gap: 12px;
    }
    .prop {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        padding: 10px 14px;
        background-color: var(--vff-bg-elevated);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-sm);
    }
    .prop dt {
        font-family: var(--vff-font-display);
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--vff-text-muted);
        margin: 0;
    }
    .prop dd {
        margin: 0;
        font-size: 14px;
        color: var(--vff-text);
        text-align: right;
    }
    .prop dd strong {
        color: var(--vff-accent);
        font-family: var(--vff-font-display);
        font-size: 16px;
    }
    .prop dd .accent {
        color: var(--vff-accent);
        margin-right: 4px;
    }

    .status-pill {
        font-family: var(--vff-font-display);
        font-size: 12px;
        font-weight: 600;
        padding: 4px 10px;
        border-radius: var(--vff-radius-pill);
        background-color: var(--vff-bg-muted);
        color: var(--vff-text);
    }
    .status-pill.status-in_service {
        background-color: rgba(255, 101, 0, 0.15);
        color: var(--vff-accent);
    }
    .status-pill.status-single_track {
        background-color: rgba(255, 139, 53, 0.18);
        color: #b54a00;
    }
    .status-pill.status-abandoned,
    .status-pill.status-dismantled,
    .status-pill.status-razed {
        background-color: rgba(138, 138, 138, 0.18);
        color: #555;
    }

    .cta {
        border: 0;
        cursor: pointer;
        justify-content: center;
        width: 100%;
    }
    .suggest-link {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-top: 8px;
        font-family: var(--vff-font-display);
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--vff-text-muted);
        text-decoration: none;
        text-align: center;
        justify-content: center;
        padding: 8px 4px;
        border-radius: var(--vff-radius-sm);
        transition: color var(--vff-transition);
    }
    .suggest-link:hover {
        color: var(--vff-accent);
    }

    @media (max-width: 600px) {
        .drawer {
            top: auto;
            bottom: 0;
            left: 0;
            right: 0;
            width: 100%;
            max-width: 100%;
            border-radius: var(--vff-radius-md) var(--vff-radius-md) 0 0;
            max-height: 70%;
            animation: drawer-slide-up 260ms cubic-bezier(0.2, 0.8, 0.2, 1);
        }
        @keyframes drawer-slide-up {
            from {
                transform: translateY(20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
    }
</style>

<script>
    import { i18n } from '$lib/i18n.js';
    import {
        HISTORIC_BASEMAPS,
        applyHistoricBasemap,
        findHistoricBasemap,
        historicAttribution,
    } from '$lib/historicBasemaps.js';

    /**
     * Sélecteur de fond de carte historique IGN + réglage d'opacité.
     *
     * Props :
     *   map     : instance MapLibre (le composant gère lui-même la couche raster)
     *   compact : true → bouton de contrôle MapLibre qui déplie un panneau
     *             (carte publique, modération) ; false → bloc en ligne (éditeur)
     *   value   : clé du fond ('none' | 'ign-etatmajor40' | …), bindable
     *   opacity : 0..1, bindable
     */
    let {
        map = null,
        compact = false,
        value = $bindable('none'),
        opacity = $bindable(0.8),
    } = $props();

    let open = $state(false);
    let toggleEl = $state();
    const current = $derived(findHistoricBasemap(value));
    const uid = $props.id();

    $effect(() => {
        applyHistoricBasemap(map, value, opacity);
    });

    function label(def) {
        const key = def.labelKey;
        const t = $i18n.t(key);
        return t === key ? def.title : t;
    }

    // Panneau dépliant (divulgation) : Échap le ferme et rend le focus au bouton.
    function onPanelKeydown(e) {
        if (e.key === 'Escape') {
            e.stopPropagation();
            open = false;
            toggleEl?.focus();
        }
    }
</script>

{#snippet controls()}
    <div class="row">
        <label class="lbl" for="hb-select-{uid}">{$i18n.t('editor.basemaps.label')}</label>
        <select id="hb-select-{uid}" bind:value>
            <option value="none">{$i18n.t('editor.basemaps.none')}</option>
            {#each HISTORIC_BASEMAPS as def (def.key)}
                <option value={def.key}>{label(def)}</option>
            {/each}
        </select>
    </div>
    {#if current}
        <div class="row">
            <label class="lbl" for="hb-opacity-{uid}"
                >{$i18n.t('editor.basemaps.opacity')}
                <span class="num">{Math.round(opacity * 100)}&nbsp;%</span></label
            >
            <input
                id="hb-opacity-{uid}"
                type="range"
                min="0"
                max="1"
                step="0.05"
                bind:value={opacity}
                aria-valuetext="{Math.round(opacity * 100)} %"
            />
        </div>
        <p class="source">
            {historicAttribution(current)}
            {#if current.minzoom > 5}
                <br />{$i18n.t('editor.basemaps.min_zoom', { zoom: current.minzoom })}
            {/if}
        </p>
    {/if}
{/snippet}

{#if compact}
    <div class="compact" class:active={current}>
        <button
            type="button"
            class="toggle"
            bind:this={toggleEl}
            title={$i18n.t('editor.basemaps.title')}
            aria-label={$i18n.t('editor.basemaps.title')}
            aria-expanded={open}
            aria-controls={open ? `hb-panel-${uid}` : undefined}
            onclick={() => (open = !open)}
        >
            <i class="bi bi-clock-history" aria-hidden="true"></i>
        </button>
        {#if open}
            <!-- svelte-ignore a11y_no_static_element_interactions -->
            <div class="panel" id="hb-panel-{uid}" onkeydown={onPanelKeydown}>
                <p class="title">{$i18n.t('editor.basemaps.title')}</p>
                {@render controls()}
            </div>
        {/if}
    </div>
{:else}
    <div class="inline">
        {@render controls()}
    </div>
{/if}

<style>
    .compact {
        position: relative;
    }
    .toggle {
        width: 29px;
        height: 29px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 15px;
        color: var(--vff-text);
        background: var(--vff-bg-elevated);
        border: 0;
        border-radius: var(--vff-radius-sm);
        cursor: pointer;
    }
    .toggle:hover {
        background: var(--vff-bg-muted);
    }
    .compact.active .toggle {
        color: var(--vff-accent-ink);
    }
    .panel {
        position: absolute;
        top: 0;
        right: calc(100% + 8px);
        width: 270px;
        padding: var(--vff-space-3) var(--vff-space-4);
        background: var(--vff-bg-elevated);
        color: var(--vff-text);
        border: 1px solid var(--vff-border-strong);
        border-radius: var(--vff-radius-md);
        box-shadow: var(--vff-shadow-popover);
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-3);
        text-align: left;
        z-index: var(--vff-z-raised);
    }
    .title {
        margin: 0;
        font-family: var(--vff-font-heading);
        font-weight: 600;
        font-size: var(--vff-fs-sm);
        color: var(--vff-text);
    }
    .inline {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        gap: var(--vff-space-2) var(--vff-space-4);
    }
    .inline .source {
        flex-basis: 100%;
    }
    .row {
        display: flex;
        flex-direction: column;
        gap: 4px;
        min-width: 0;
    }
    .lbl {
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-xs);
        font-weight: 600;
        line-height: var(--vff-lh-ui);
        color: var(--vff-text-muted);
    }
    .num {
        font-variant-numeric: tabular-nums;
        color: var(--vff-text);
    }
    select {
        max-width: 100%;
        min-height: 36px;
        padding: 6px 8px;
        background: var(--vff-bg);
        color: var(--vff-text);
        border: 1px solid var(--vff-border-control);
        border-radius: var(--vff-radius-sm);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
    }
    input[type='range'] {
        accent-color: var(--vff-accent-ink);
        width: 100%;
        min-width: 140px;
        min-height: 24px;
    }
    .source {
        margin: 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-xs);
        line-height: 1.4;
        color: var(--vff-text-muted);
    }
</style>

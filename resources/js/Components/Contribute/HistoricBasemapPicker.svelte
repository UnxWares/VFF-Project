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
     *             (carte publique) ; false → bloc en ligne (éditeur, modération)
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
    const current = $derived(findHistoricBasemap(value));

    $effect(() => {
        applyHistoricBasemap(map, value, opacity);
    });

    function label(def) {
        const key = def.labelKey;
        const t = $i18n.t(key);
        return t === key ? def.title : t;
    }
</script>

{#snippet controls()}
    <label class="row">
        <span class="lbl">{$i18n.t('editor.basemaps.label')}</span>
        <select bind:value aria-label={$i18n.t('editor.basemaps.label')}>
            <option value="none">{$i18n.t('editor.basemaps.none')}</option>
            {#each HISTORIC_BASEMAPS as def (def.key)}
                <option value={def.key}>{label(def)}</option>
            {/each}
        </select>
    </label>
    {#if current}
        <label class="row">
            <span class="lbl"
                >{$i18n.t('editor.basemaps.opacity')} · {Math.round(opacity * 100)} %</span
            >
            <input
                type="range"
                min="0"
                max="1"
                step="0.05"
                bind:value={opacity}
                aria-label={$i18n.t('editor.basemaps.opacity')}
            />
        </label>
        <p class="source">
            {historicAttribution(current)}
            {#if current.minzoom > 5}
                <br /><small>{$i18n.t('editor.basemaps.min_zoom', { zoom: current.minzoom })}</small
                >
            {/if}
        </p>
    {/if}
{/snippet}

{#if compact}
    <div class="compact" class:active={current}>
        <button
            type="button"
            class="toggle"
            title={$i18n.t('editor.basemaps.title')}
            aria-label={$i18n.t('editor.basemaps.title')}
            aria-expanded={open}
            onclick={() => (open = !open)}
        >
            <i class="bi bi-clock-history"></i>
        </button>
        {#if open}
            <div class="panel" role="dialog" aria-label={$i18n.t('editor.basemaps.title')}>
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
        color: #333;
        background: #fff;
        border: 0;
        border-radius: 4px;
        cursor: pointer;
    }
    .compact.active .toggle {
        color: #ff6500;
    }
    .panel {
        position: absolute;
        top: 0;
        right: calc(100% + 8px);
        width: 260px;
        padding: 12px 14px;
        background: var(--vff-bg, #fff);
        color: var(--vff-text, #1a1a1a);
        border: 1px solid var(--vff-border, #ddd);
        border-radius: var(--vff-radius-md, 10px);
        box-shadow: 0 8px 24px var(--vff-shadow-strong, rgba(0, 0, 0, 0.2));
        display: flex;
        flex-direction: column;
        gap: 10px;
        text-align: left;
        z-index: 5;
    }
    .title {
        margin: 0;
        font-family: var(--vff-font-display);
        font-weight: 700;
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--vff-accent, #ff6500);
    }
    .inline {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        gap: 8px 14px;
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
        font-family: var(--vff-font-display);
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--vff-text-muted, #666);
    }
    select {
        max-width: 100%;
        padding: 6px 8px;
        background: var(--vff-bg-elevated, #fff);
        color: var(--vff-text, #1a1a1a);
        border: 1px solid var(--vff-border, #ddd);
        border-radius: var(--vff-radius-sm, 6px);
        font-size: 13px;
    }
    input[type='range'] {
        accent-color: var(--vff-accent, #ff6500);
        width: 100%;
        min-width: 140px;
    }
    .source {
        margin: 0;
        font-size: 11px;
        line-height: 1.4;
        color: var(--vff-text-muted, #666);
    }
</style>

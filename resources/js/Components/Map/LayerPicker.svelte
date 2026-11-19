<script>
    import { MediaQuery } from 'svelte/reactivity';
    import { i18n } from '$lib/i18n.js';
    import {
        DATA_LAYERS,
        NO_ORM,
        ORM_LAYERS,
        TODAY_ONLY_LAYERS,
        layerCount,
        layerLabelKey,
        toggleData,
    } from '$lib/mapLayers.js';

    /**
     * Bouton « Calques » de la barre d'outils de la carte : un panneau déroulant avec deux groupes
     * de choix natifs (fieldset et legend), les calques de VFF à cocher librement et un fond
     * OpenRailwayMap au plus (boutons radio).
     *
     * Clavier : le bouton ouvre et ferme (aria-expanded) ; Échap ferme et rend le focus au bouton ;
     * Tab sort du panneau, qui se ferme alors, sans piège. Un clic hors du panneau le ferme aussi.
     *
     * Props (bindables) :
     *   data : calques de VFF affichés (DATA_LAYERS de lib/mapLayers.js)
     *   orm  : fond OpenRailwayMap ('none' ou un ORM_LAYERS)
     *   tint : teinte d'époque du fond de carte (lib/eraTint.js), coupée par une case
     */
    let { data = $bindable([]), orm = $bindable(NO_ORM), tint = $bindable(true) } = $props();

    const uid = $props.id();
    const panelId = `${uid}-panel`;
    const todayId = `${uid}-today`;
    const tintHintId = `${uid}-tint`;
    // Contraste renforcé demandé par le système : la teinte est toujours coupée.
    const moreContrast = new MediaQuery('(prefers-contrast: more)', false);

    let open = $state(false);
    let root = $state();
    let toggleButton = $state();

    const count = $derived(layerCount({ data, orm }));
    const ormChoices = [NO_ORM, ...ORM_LAYERS];
    const ormLabel = (value) =>
        value === NO_ORM
            ? $i18n.t('maps.overlay.none')
            : $i18n.t(`maps.layers.orm.${value.slice('orm-'.length)}`);

    function close(returnFocus = false) {
        open = false;
        if (returnFocus) toggleButton?.focus();
    }

    function onkeydown(e) {
        if (e.key === 'Escape' && open) {
            e.preventDefault();
            e.stopPropagation();
            close(true);
        }
    }

    // Le focus quitte le composant (Tab après le dernier choix) : le panneau se referme.
    function onfocusout(e) {
        if (open && e.relatedTarget instanceof Node && !root.contains(e.relatedTarget)) close();
    }

    // Clic ou toucher hors du composant : le panneau se referme.
    $effect(() => {
        if (!open) return;
        const onPointer = (e) => {
            if (!root.contains(e.target)) close();
        };
        document.addEventListener('pointerdown', onPointer);
        return () => document.removeEventListener('pointerdown', onPointer);
    });
</script>

<!-- Échap et sortie du focus écoutés sur le conteneur (délégation), pas une commande en soi. -->
<!-- svelte-ignore a11y_no_static_element_interactions -->
<div class="layer-picker" bind:this={root} {onkeydown} {onfocusout}>
    <button
        bind:this={toggleButton}
        type="button"
        class={['layer-toggle', { active: count > 0 }]}
        aria-expanded={open}
        aria-controls={panelId}
        onclick={() => (open = !open)}
    >
        <i class="bi bi-layers" aria-hidden="true"></i>
        <span class="layer-toggle-text">{$i18n.t('maps.layers.button')}</span>
        {#if count > 0}
            <span class="layer-count" aria-hidden="true">{count}</span>
            <span class="vff-sr-only">, {$i18n.t('maps.layers.active', { count })}</span>
        {/if}
        <i class="bi {open ? 'bi-chevron-up' : 'bi-chevron-down'} chevron" aria-hidden="true"></i>
    </button>

    <div class="layer-panel" id={panelId} hidden={!open}>
        <div class="layer-group">
            <fieldset>
                <legend>{$i18n.t('maps.layers.data_legend')}</legend>
                {#each DATA_LAYERS as key (key)}
                    <label class="vff-check layer-option">
                        <input
                            type="checkbox"
                            checked={data.includes(key)}
                            aria-describedby={TODAY_ONLY_LAYERS.includes(key) ? todayId : undefined}
                            onchange={(e) =>
                                (data = toggleData(data, key, e.currentTarget.checked))}
                        />
                        {$i18n.t(layerLabelKey(key))}
                    </label>
                {/each}
                <p class="layer-hint" id={todayId}>{$i18n.t('maps.layers.today_hint')}</p>
            </fieldset>
        </div>

        <div class="layer-group">
            <fieldset>
                <legend>{$i18n.t('maps.layers.orm_legend')}</legend>
                {#each ormChoices as value (value)}
                    <label class="vff-check layer-option">
                        <input type="radio" name={`${uid}-orm`} {value} bind:group={orm} />
                        {ormLabel(value)}
                    </label>
                {/each}
                <p class="layer-hint">{$i18n.t('maps.layers.orm_hint')}</p>
            </fieldset>
        </div>

        <div class="layer-group">
            <fieldset>
                <legend>{$i18n.t('maps.layers.basemap_legend')}</legend>
                <label class="vff-check layer-option">
                    <input
                        type="checkbox"
                        checked={tint && !moreContrast.current}
                        disabled={moreContrast.current}
                        onchange={(e) => (tint = e.currentTarget.checked)}
                        aria-describedby={tintHintId}
                    />
                    {$i18n.t('maps.layers.tint')}
                </label>
                <p class="layer-hint" id={tintHintId}>
                    {$i18n.t(
                        moreContrast.current
                            ? 'maps.layers.tint_hint_contrast'
                            : 'maps.layers.tint_hint',
                    )}
                </p>
            </fieldset>
        </div>
    </div>
</div>

<style>
    .layer-picker {
        position: relative;
        min-width: 0;
    }

    /* Même gabarit qu'un champ de la barre d'outils (44 px, coins de 8 px, contour de contrôle). */
    .layer-toggle {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        min-height: 44px;
        padding: 0 12px;
        border: 1px solid var(--vff-border-control);
        border-radius: var(--vff-radius-sm);
        background-color: var(--vff-bg-elevated);
        color: var(--vff-text);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        font-weight: 600;
        white-space: nowrap;
        cursor: pointer;
        transition:
            background-color var(--vff-transition),
            border-color var(--vff-transition);
    }
    .layer-toggle:hover {
        background-color: var(--vff-bg-muted);
    }
    .layer-toggle.active {
        border-color: var(--vff-accent);
    }
    .layer-toggle .bi-layers {
        color: var(--vff-text-muted);
        font-size: 17px;
    }
    .layer-toggle .chevron {
        color: var(--vff-text-muted);
        font-size: 14px;
    }
    /* Nombre de calques affichés : pastille orange pâle, chiffre en orange foncé (AA). */
    .layer-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 24px;
        height: 24px;
        padding: 0 6px;
        border-radius: var(--vff-radius-pill);
        background-color: var(--vff-bg-accent);
        color: var(--vff-accent-ink);
        font-size: var(--vff-fs-xs);
        font-variant-numeric: tabular-nums;
    }

    /* Panneau sous le bouton, aligné à droite, posé sur la carte ; il défile s'il dépasse. */
    .layer-panel {
        position: absolute;
        top: calc(100% + 8px);
        right: 0;
        z-index: var(--vff-z-overlay);
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-4);
        width: max-content;
        min-width: 16rem;
        max-width: min(22rem, calc(100vw - 2 * var(--vff-gutter)));
        max-height: min(75vh, 40rem);
        padding: var(--vff-space-4) 20px;
        overflow-y: auto;
        overscroll-behavior: contain;
        border: 1px solid var(--vff-border-strong);
        border-radius: var(--vff-radius-md);
        background-color: var(--vff-bg-elevated);
        box-shadow: var(--vff-shadow-popover);
    }
    .layer-panel[hidden] {
        display: none;
    }

    fieldset {
        display: flex;
        flex-direction: column;
        gap: 2px;
        min-width: 0;
        margin: 0;
        padding: 0;
        border: 0;
    }
    /* Filet entre les deux groupes, posé sur un conteneur : la légende d'un fieldset se dessine
       dans sa bordure haute. */
    .layer-group + .layer-group {
        padding-top: var(--vff-space-4);
        border-top: 1px solid var(--vff-border);
    }
    legend {
        margin-bottom: 6px;
        padding: 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        font-weight: 700;
        color: var(--vff-text);
    }
    .layer-option {
        min-height: 36px;
        font-size: var(--vff-fs-md);
    }
    .layer-hint {
        max-width: 18rem;
        margin: 6px 0 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-ui);
        color: var(--vff-text-muted);
    }

    /* Tablette et téléphone : le mot « Calques » reste lu, l'icône et le nombre le montrent. */
    @media (max-width: 600px) {
        .layer-toggle {
            padding: 0 10px;
        }
        .layer-toggle-text {
            position: absolute;
            width: 1px;
            height: 1px;
            overflow: hidden;
            clip-path: inset(50%);
            white-space: nowrap;
        }
        .layer-option {
            min-height: 44px;
        }
    }
</style>

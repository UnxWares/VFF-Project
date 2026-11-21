<script>
    import { i18n } from '$lib/i18n.js';
    import { VIEWS } from '$lib/epochViews.js';

    /**
     * Choix de la vue de la carte : une décennie, deux décennies comparées, toutes les époques, et la
     * vue ferroviaire (plan schématique) quand la page la propose.
     * Boutons radio natifs (une tabulation, flèches pour changer), dessinés en segments.
     *
     * Props : view (bindable) : 'era' | 'compare' | 'stack' | 'schema' ; views : vues proposées
     */
    let { view = $bindable('era'), views = VIEWS } = $props();

    const uid = $props.id();
</script>

<fieldset class="view-switch">
    <legend class="view-label">{$i18n.t('maps.view.label')}</legend>
    <div class="segments">
        {#each views as v (v)}
            <label class="segment">
                <input type="radio" name={`${uid}-view`} value={v} bind:group={view} />
                <span>{$i18n.t(`maps.view.${v}`)}</span>
            </label>
        {/each}
    </div>
</fieldset>

<style>
    .view-switch {
        display: flex;
        align-items: center;
        gap: 8px;
        min-width: 0;
        margin: 0;
        padding: 0;
        border: 0;
    }
    /* Libellé posé à gauche des segments, comme « Calque » (une légende flotte mal sinon). */
    .view-label {
        float: left;
        margin-right: 8px;
        padding: 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        font-weight: 600;
        color: var(--vff-text);
        white-space: nowrap;
    }
    .segments {
        display: flex;
        min-width: 0;
        border: 1px solid var(--vff-border-control);
        border-radius: var(--vff-radius-sm);
        background-color: var(--vff-bg-elevated);
    }
    .segment {
        position: relative;
        display: flex;
        flex: 1 1 auto;
    }
    .segment + .segment {
        border-left: 1px solid var(--vff-border-control);
    }
    .segment input {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        margin: 0;
        opacity: 0;
        cursor: pointer;
    }
    .segment span {
        display: flex;
        flex: 1;
        align-items: center;
        justify-content: center;
        min-height: 40px;
        padding: 4px 12px;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text);
        text-align: center;
        white-space: nowrap;
        transition: background-color var(--vff-transition);
    }
    .segment:first-child span {
        border-radius: calc(var(--vff-radius-sm) - 1px) 0 0 calc(var(--vff-radius-sm) - 1px);
    }
    .segment:last-child span {
        border-radius: 0 calc(var(--vff-radius-sm) - 1px) calc(var(--vff-radius-sm) - 1px) 0;
    }
    .segment:hover span {
        background-color: var(--vff-bg-muted);
    }
    .segment input:checked + span {
        background-color: var(--vff-bg-accent);
        color: var(--vff-accent-ink);
        font-weight: 600;
    }
    .segment input:focus-visible + span {
        outline: var(--vff-focus-width) solid var(--vff-focus);
        outline-offset: calc(-1 * var(--vff-focus-width));
    }
    /* Téléphone : les segments prennent toute la rangée ; le libellé reste lu. */
    @media (max-width: 600px) {
        .view-switch {
            width: 100%;
        }
        .view-label {
            position: absolute;
            width: 1px;
            height: 1px;
            overflow: hidden;
            clip-path: inset(50%);
            white-space: nowrap;
        }
        .segments {
            flex: 1;
        }
        .segment span {
            padding-inline: 6px;
            white-space: normal;
        }
        /* Quatre vues : deux rangées de deux (sur une rangée, « Schéma » sortait du cadre à 320 px
           et « Toutes les époques » passait sur trois lignes). */
        .segments:has(.segment:nth-child(4)) {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .segments:has(.segment:nth-child(4)) .segment:nth-child(odd) {
            border-left: 0;
        }
        .segments:has(.segment:nth-child(4)) .segment:nth-child(n + 3) {
            border-top: 1px solid var(--vff-border-control);
        }
        .segments:has(.segment:nth-child(4)) .segment span {
            border-radius: 0;
        }
        .segments:has(.segment:nth-child(4)) .segment:nth-child(1) span {
            border-top-left-radius: calc(var(--vff-radius-sm) - 1px);
        }
        .segments:has(.segment:nth-child(4)) .segment:nth-child(2) span {
            border-top-right-radius: calc(var(--vff-radius-sm) - 1px);
        }
        .segments:has(.segment:nth-child(4)) .segment:nth-child(3) span {
            border-bottom-left-radius: calc(var(--vff-radius-sm) - 1px);
        }
        .segments:has(.segment:nth-child(4)) .segment:nth-child(4) span {
            border-bottom-right-radius: calc(var(--vff-radius-sm) - 1px);
        }
    }
</style>

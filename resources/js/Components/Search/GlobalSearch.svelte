<script>
    import { i18n } from '$lib/i18n.js';
    import SearchCombobox from './SearchCombobox.svelte';
    import { lightDismiss } from '$lib/lightDismiss.js';

    /**
     * Fenêtre de recherche de tout le site, ouverte par le bouton « Rechercher » de l'en-tête ou
     * par Ctrl K (Cmd K sur Mac). Vrai dialogue modal (<dialog> + showModal()) : focus piégé, Échap
     * ferme, le navigateur rend le focus au bouton ; un clic hors du cadre la ferme aussi
     * (closedby="any", avec le repli lib/lightDismiss.js pour les navigateurs qui l'ignorent).
     * Le champ repart vide à chaque ouverture et propose les rubriques du site.
     */
    /** @type {{ open?: boolean, shortcut?: string }} */
    let { open = $bindable(false), shortcut = 'Ctrl K' } = $props();

    const uid = $props.id();
    const titleId = `${uid}-title`;
    let dialogEl = $state();

    const t = (/** @type {string} */ key, /** @type {Record<string, unknown>} */ params = {}) =>
        String($i18n.t(key, params));

    // Ouverture et fermeture pilotées par `open` : showModal() / close() du navigateur. À
    // l'ouverture, le navigateur donne le focus au champ (attribut autofocus).
    $effect(() => {
        if (!dialogEl) return;
        if (open && !dialogEl.open) dialogEl.showModal();
        else if (!open && dialogEl.open) dialogEl.close();
    });
</script>

<dialog
    bind:this={dialogEl}
    class="vff-search-dialog"
    aria-labelledby={titleId}
    closedby="any"
    onclose={() => (open = false)}
    {@attach lightDismiss}
>
    <div class="dialog-head">
        <h2 id={titleId}>{t('nav.search.title')}</h2>
        <button
            type="button"
            class="close-btn"
            onclick={() => dialogEl.close()}
            aria-label={t('common.close')}
            title={t('common.close')}
        >
            <i class="bi bi-x-lg" aria-hidden="true"></i>
        </button>
    </div>
    <div class="dialog-body">
        {#if open}
            <SearchCombobox
                suggestions
                autofocus
                label={t('nav.search.label')}
                labelHidden
                onnavigate={() => dialogEl.close()}
            />
        {/if}
    </div>
    <p class="dialog-foot">
        <span>{t('nav.search.keys_hint')}</span>
        <span class="shortcut">{t('nav.search.shortcut', { keys: shortcut })}</span>
    </p>
</dialog>

<style>
    /* Fenêtre posée en haut de l'écran (la liste s'allonge vers le bas sans faire bouger le champ). */
    .vff-search-dialog {
        width: min(680px, calc(100vw - 32px));
        max-width: none;
        max-height: calc(100dvh - 2 * min(12vh, 96px));
        margin: min(12vh, 96px) auto auto;
        padding: 0;
        border: 1px solid var(--vff-border-strong);
        border-radius: var(--vff-radius-lg);
        background-color: var(--vff-bg-elevated);
        box-shadow: var(--vff-shadow-popover);
        color: var(--vff-text);
    }
    .vff-search-dialog[open] {
        display: flex;
        flex-direction: column;
    }
    .vff-search-dialog:modal {
        overscroll-behavior: contain;
    }
    .vff-search-dialog::backdrop {
        overflow: hidden;
        overscroll-behavior: contain;
        background-color: rgb(0 0 0 / 0.55);
    }
    .dialog-head {
        display: flex;
        flex-shrink: 0;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 12px 12px 4px 20px;
    }
    h2 {
        margin: 0;
        font-family: var(--vff-font-display);
        font-size: 1.25rem;
        line-height: var(--vff-lh-heading);
    }
    .close-btn {
        display: inline-flex;
        flex-shrink: 0;
        align-items: center;
        justify-content: center;
        width: 44px;
        height: 44px;
        border: 0;
        border-radius: var(--vff-radius-md);
        background: transparent;
        color: var(--vff-text-muted);
        font-size: 18px;
        cursor: pointer;
        transition:
            background-color var(--vff-transition),
            color var(--vff-transition);
    }
    .close-btn:hover {
        background-color: var(--vff-bg-muted);
        color: var(--vff-text);
    }
    .dialog-body {
        display: flex;
        flex: 1 1 auto;
        flex-direction: column;
        min-height: 0;
        padding: 8px 20px 12px;
    }
    .dialog-body > :global(.search-combobox) {
        flex: 1 1 auto;
    }
    .dialog-foot {
        display: flex;
        flex-wrap: wrap;
        flex-shrink: 0;
        justify-content: space-between;
        gap: 4px 16px;
        margin: 0;
        padding: 12px 20px;
        border-top: 1px solid var(--vff-border);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-ui);
        color: var(--vff-text-muted);
    }
    .shortcut {
        white-space: nowrap;
    }
    /* Téléphone : presque tout l'écran, sans les aides clavier. */
    @media (max-width: 600px) {
        .vff-search-dialog {
            width: calc(100vw - 16px);
            max-height: calc(100dvh - 16px);
            margin: 8px auto auto;
        }
        .dialog-head {
            padding-left: 16px;
        }
        .dialog-body {
            padding-inline: 16px;
        }
        .dialog-foot {
            display: none;
        }
    }
</style>

<script>
    /**
     * Message de confirmation du serveur (`flash.success`) affiché en bas de l'écran, pour une
     * page dont le haut ne laisse pas de place à un encart (accueil et son grand en-tête : par
     * exemple « Votre compte a été supprimé » après la suppression du compte).
     *
     *   <FlashToast />
     *
     * La zone `role="status"` existe dès le rendu et le texte n'y entre qu'après le montage :
     * les lecteurs d'écran l'annoncent (une zone créée avec son texte ne l'est pas toujours).
     */
    import { onMount } from 'svelte';
    import { page } from '@inertiajs/svelte';
    import Notice from '$components/ui/Notice.svelte';
    import { i18n } from '$lib/i18n.js';

    let mounted = $state(false);
    let closedMessage = $state(null);

    const message = $derived(page.props.flash?.success ?? null);
    const visible = $derived(mounted && message && message !== closedMessage);

    onMount(() => {
        mounted = true;
    });
</script>

<div class="flash-toast" role="status">
    {#if visible}
        <div class="flash-toast-box">
            <Notice tone="success" live="off">{message}</Notice>
            <button
                type="button"
                class="close"
                aria-label={$i18n.t('common.close')}
                title={$i18n.t('common.close')}
                onclick={() => (closedMessage = message)}
            >
                <i class="bi bi-x-lg" aria-hidden="true"></i>
            </button>
        </div>
    {/if}
</div>

<style>
    .flash-toast {
        position: fixed;
        inset-inline: var(--vff-gutter);
        inset-block-end: max(var(--vff-gutter), env(safe-area-inset-bottom));
        z-index: var(--vff-z-toast);
        max-width: 36rem;
        margin-inline: auto;
        pointer-events: none;
    }
    .flash-toast-box {
        position: relative;
        border-radius: var(--vff-radius-md);
        box-shadow: var(--vff-shadow-popover);
        pointer-events: auto;
    }
    /* Place pour le bouton de fermeture, en haut à droite de l'encart. */
    .flash-toast-box :global(.vff-notice) {
        padding-inline-end: 56px;
    }
    .close {
        position: absolute;
        inset-block-start: 4px;
        inset-inline-end: 4px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 44px;
        height: 44px;
        border: 0;
        border-radius: var(--vff-radius-sm);
        background: transparent;
        color: var(--vff-text-muted);
        cursor: pointer;
        transition:
            background-color var(--vff-transition),
            color var(--vff-transition);
    }
    .close:hover {
        background-color: var(--vff-bg-muted);
        color: var(--vff-text);
    }
</style>

<script>
    import { onMount } from 'svelte';
    import { page } from '@inertiajs/svelte';
    import { i18n } from '$lib/i18n.js';
    import { formatOfflineCopyDate, offlineCopyDate } from '$lib/offlineCopy.js';
    import { partialMaps } from '$lib/partialMaps.svelte.js';
    import { formatLocale } from '$lib/typo.js';

    /**
     * Signal discret en haut du contenu quand la page affichée est une copie gardée par le service worker
     * (resources/js/pwa), lue sans réseau : date de la copie. La zone role="status" existe toujours, vide
     * pour une page venue du réseau. Au montage, le texte n'est posé qu'un instant après la zone (une zone
     * créée avec son texte n'est pas annoncée par les lecteurs d'écran) ; d'une page à l'autre du même
     * gabarit, la zone existe déjà et le texte change sur place. Quand la page montre une carte, celle-ci
     * ne montre que ce qui a déjà été vu sur cet appareil (lib/offlineMap.svelte.js) : une phrase le dit,
     * selon que le site sert un fond de carte libre (`basemap`) ou non.
     */
    const savedAt = $derived(offlineCopyDate(page.props));
    const message = $derived(
        savedAt
            ? $i18n.t('pwa.offline_copy', {
                  date: formatOfflineCopyDate(savedAt, formatLocale($i18n.language)),
              })
            : '',
    );
    const mapMessage = $derived(
        partialMaps.count > 0
            ? $i18n.t(page.props.basemap ? 'pwa.offline_map_basemap' : 'pwa.offline_map_partial')
            : '',
    );

    let ready = $state(false);

    onMount(() => {
        const timer = setTimeout(() => (ready = true), 150);
        return () => clearTimeout(timer);
    });
</script>

<div class="offline-copy" role="status">
    {#if ready && message}
        <p class="offline-copy-text">
            <i class="bi bi-cloud-slash" aria-hidden="true"></i>
            <span>{mapMessage ? `${message} ${mapMessage}` : message}</span>
        </p>
    {/if}
</div>

<style>
    .offline-copy {
        max-width: calc(var(--vff-container) + 2 * var(--vff-gutter));
        margin-inline: auto;
        padding-inline: var(--vff-gutter);
    }
    .offline-copy-text {
        display: flex;
        align-items: flex-start;
        gap: var(--vff-space-2);
        margin: var(--vff-space-4) 0;
        padding: var(--vff-space-3) var(--vff-space-4);
        border: 1px solid var(--vff-border-strong);
        border-radius: var(--vff-radius-md);
        background-color: var(--vff-bg-subtle);
        color: var(--vff-text);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-ui);
    }
    .offline-copy-text .bi {
        flex-shrink: 0;
        color: var(--vff-text-muted);
        line-height: inherit;
    }
    .offline-copy-text span {
        min-width: 0;
    }
</style>

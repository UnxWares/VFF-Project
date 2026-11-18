<script>
    import { onMount } from 'svelte';
    import { i18n } from '$lib/i18n.js';
    import { pageWindow } from '$lib/pagination.js';

    /**
     * Pagination des annuaires (lignes, gares) : précédent, numéros de page autour de la
     * page courante (première et dernière toujours visibles), suivant.
     * `onpage(n)` charge la page n (Inertia, côté page).
     */
    /** @type {{ current: number, last: number, onpage: (n: number) => void, label: string, prev: string, next: string, status: string }} */
    let { current, last, onpage, label, prev, next, status } = $props();

    // Téléphone : une page de chaque côté de la page courante (deux sur ordinateur).
    let narrow = $state(false);
    onMount(() => {
        const mq = matchMedia('(max-width: 600px)');
        const sync = () => (narrow = mq.matches);
        sync();
        mq.addEventListener('change', sync);
        return () => mq.removeEventListener('change', sync);
    });

    const pages = $derived(pageWindow(current, last, narrow ? 1 : 2));
</script>

<nav class="pagination" aria-label={label}>
    <button type="button" class="step" disabled={current <= 1} onclick={() => onpage(current - 1)}>
        <i class="bi bi-chevron-left" aria-hidden="true"></i>
        <span>{prev}</span>
    </button>
    <ol class="pages">
        {#each pages as p (p)}
            {#if typeof p === 'number'}
                <li>
                    <button
                        type="button"
                        class="page"
                        class:current={p === current}
                        aria-current={p === current ? 'page' : undefined}
                        onclick={() => p !== current && onpage(p)}
                        >{p.toLocaleString($i18n.language)}</button
                    >
                </li>
            {:else}
                <li class="gap" aria-hidden="true">…</li>
            {/if}
        {/each}
    </ol>
    <button
        type="button"
        class="step"
        disabled={current >= last}
        onclick={() => onpage(current + 1)}
    >
        <span>{next}</span>
        <i class="bi bi-chevron-right" aria-hidden="true"></i>
    </button>
    <p class="status">{status}</p>
</nav>

<style>
    .pagination {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        align-items: center;
        gap: 8px 6px;
        max-width: 1180px;
        margin: 0 auto;
        padding: 12px 24px 64px;
    }
    .pages {
        display: flex;
        align-items: center;
        gap: 4px;
        list-style: none;
        margin: 0;
        padding: 0;
    }
    .pages li {
        font-size: 14px;
    }
    button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        min-width: 40px;
        height: 40px;
        padding: 0 12px;
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-sm);
        background: var(--vff-bg);
        color: var(--vff-text);
        font-family: var(--vff-font-heading);
        font-size: 14px;
        font-variant-numeric: tabular-nums;
        cursor: pointer;
    }
    button:hover:not(:disabled):not(.current) {
        border-color: var(--vff-accent);
        color: var(--vff-accent-ink);
    }
    button:focus-visible {
        outline: 2px solid var(--vff-accent);
        outline-offset: 2px;
    }
    button:disabled {
        opacity: 0.45;
        cursor: default;
    }
    .page.current {
        border-color: var(--vff-text);
        background: var(--vff-text);
        color: var(--vff-bg);
        font-weight: 600;
        cursor: default;
    }
    .gap {
        padding: 0 4px;
        color: var(--vff-text-muted);
    }
    .status {
        flex-basis: 100%;
        margin: 6px 0 0;
        text-align: center;
        font-family: var(--vff-font-heading);
        font-size: 13px;
        color: var(--vff-text-muted);
    }
    @media (max-width: 600px) {
        .pagination {
            padding: 8px 16px 48px;
        }
        .step span {
            position: absolute;
            width: 1px;
            height: 1px;
            overflow: hidden;
            clip: rect(0 0 0 0);
            white-space: nowrap;
        }
        button {
            min-width: 36px;
            height: 36px;
            padding: 0 8px;
        }
    }
</style>

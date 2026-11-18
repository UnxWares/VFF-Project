<script>
    /**
     * Panneau de recherche et de filtres d'un annuaire (lignes, gares, matériel) : un vrai formulaire
     * (`role="search"`, Entrée applique), les champs écrits par la page dans `children` (classes
     * `.vff-field`, `.vff-label`, `.vff-input`, `.vff-select`, `.vff-check` ; `.wide` = rangée entière),
     * puis le nombre de résultats (annoncé quand il change) et le bouton qui remet tout à zéro.
     *
     * Props : `label` (nom du formulaire), `results` (texte déjà mis en forme, ex. « Résultats : 12 »),
     * `canReset`, `resetLabel`, `onreset`, `onsubmit` (applique tout de suite, sans attendre la saisie).
     */
    /** @type {{ label: string, results: string, canReset?: boolean, resetLabel: string, onreset: () => void, onsubmit?: () => void, children: import('svelte').Snippet }} */
    let { label, results, canReset = false, resetLabel, onreset, onsubmit, children } = $props();
</script>

<form
    class="vff-panel filters"
    role="search"
    aria-label={label}
    onsubmit={(e) => {
        e.preventDefault();
        onsubmit?.();
    }}
>
    <div class="fields">
        {@render children()}
    </div>
    <div class="foot">
        <p class="count" role="status">{results}</p>
        {#if canReset}
            <button type="button" class="vff-btn quiet" onclick={onreset}>
                <i class="bi bi-x-circle" aria-hidden="true"></i>
                {resetLabel}
            </button>
        {/if}
    </div>
</form>

<style>
    .filters {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-4);
        padding: var(--vff-space-5) var(--vff-space-5) var(--vff-space-4);
    }
    .fields {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(min(100%, 220px), 1fr));
        gap: var(--vff-space-4) var(--vff-space-5);
        align-items: end;
    }
    .fields > :global(.wide) {
        grid-column: 1 / -1;
    }
    .foot {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: var(--vff-space-2) var(--vff-space-4);
        padding-top: var(--vff-space-3);
        border-top: 1px solid var(--vff-border);
    }
    .count {
        margin: 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        font-variant-numeric: tabular-nums;
        color: var(--vff-text-muted);
    }
    @media (max-width: 600px) {
        .filters {
            padding: var(--vff-space-4);
        }
        .fields {
            grid-template-columns: 1fr;
        }
    }
</style>

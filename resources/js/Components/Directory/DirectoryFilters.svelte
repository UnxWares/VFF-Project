<script>
    import { onMount, tick } from 'svelte';
    import { i18n } from '$lib/i18n.js';
    import { countActiveSettings, revealedFocusTarget } from '$lib/directoryFilters.js';

    /**
     * Panneau de recherche et de filtres d'un annuaire (lignes, gares, matériel) : un vrai formulaire
     * dans l'élément `<search>` (Entrée applique), les champs écrits par la page dans `children`
     * (classes `.vff-field`, `.vff-label`, `.vff-input`, `.vff-select`, `.vff-check` ; `.wide` =
     * rangée entière), puis le nombre de résultats (annoncé quand il change) et le bouton qui remet
     * tout à zéro.
     *
     * Sur téléphone (600 px et moins), seul le PREMIER champ (la recherche) reste affiché : les
     * autres se replient derrière le bouton « Filtres (n) », n = nombre de réglages qui diffèrent
     * du défaut (lib/directoryFilters.js). Les premiers résultats sont ainsi visibles sans défiler.
     *
     * Props : `label` (nom du formulaire), `results` (texte déjà mis en forme, ex. « Résultats : 12 »),
     * `canReset`, `resetLabel`, `onreset`, `onsubmit` (applique tout de suite, sans attendre la saisie).
     */
    /** @type {{ label: string, results: string, canReset?: boolean, resetLabel: string, onreset: () => void, onsubmit?: () => void, children: import('svelte').Snippet }} */
    let { label, results, canReset = false, resetLabel, onreset, onsubmit, children } = $props();

    const uid = $props.id();
    const fieldsId = `${uid}-fields`;
    let open = $state(false);
    let fieldsEl = $state();
    let active = $state(0);

    /** Réglages actifs parmi les champs repliables (tous sauf le premier, la recherche). */
    function countActive() {
        if (!fieldsEl) return;
        active = countActiveSettings(
            fieldsEl.querySelectorAll(
                ':scope > :not(:first-child) select, :scope > :not(:first-child) input',
            ),
        );
    }

    async function reset() {
        onreset();
        await tick();
        countActive();
    }

    /** Bascule des filtres (téléphone) ; au clavier, le focus suit les champs révélés au-dessus. */
    async function toggle(e) {
        open = !open;
        const detail = e.detail;
        await tick();
        revealedFocusTarget(open, detail, fieldsEl)?.focus();
    }

    onMount(countActive);
</script>

<search aria-label={label}>
    <form
        class={['vff-panel', 'filters', { open }]}
        onsubmit={(e) => {
            e.preventDefault();
            onsubmit?.();
        }}
        onchange={countActive}
        oninput={countActive}
    >
        <div class="fields" id={fieldsId} bind:this={fieldsEl}>
            {@render children()}
        </div>
        <div class="foot">
            <button
                type="button"
                class="vff-btn secondary small filters-toggle"
                aria-expanded={open}
                aria-controls={fieldsId}
                onclick={toggle}
            >
                <i class="bi bi-sliders" aria-hidden="true"></i>
                {$i18n.t('common.filters')}{#if active}<span aria-hidden="true"
                        >&nbsp;({active})</span
                    ><span class="vff-sr-only"
                        >, {$i18n.t('common.filters_active', { count: active })}</span
                    >{/if}
                <i class="bi bi-chevron-down chevron" aria-hidden="true"></i>
            </button>
            <p class="count" role="status">{results}</p>
            {#if canReset}
                <button type="button" class="vff-btn quiet" onclick={reset}>
                    <i class="bi bi-x-circle" aria-hidden="true"></i>
                    {resetLabel}
                </button>
            {/if}
        </div>
    </form>
</search>

<style>
    .filters {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-4);
        padding: var(--vff-space-5) var(--vff-space-5) var(--vff-space-4);
    }
    /* 260 px au moins par colonne : « Tous les départements » tient en entier, et le grand écran ne
       crée pas une cinquième colonne vide qui rétrécit les listes (2 560 px). */
    .fields {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(min(100%, 260px), 1fr));
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
    /* Bouton « Filtres » : téléphone seulement. */
    .filters-toggle {
        display: none;
    }
    .chevron {
        font-size: 12px;
        transition: transform var(--vff-transition);
    }
    .filters-toggle[aria-expanded='true'] .chevron {
        transform: rotate(180deg);
    }
    @media (max-width: 600px) {
        .filters {
            gap: var(--vff-space-3);
            padding: var(--vff-space-4);
        }
        .fields {
            grid-template-columns: 1fr;
        }
        /* Filtres repliés : seule la recherche (premier champ) reste. */
        .filters:not(.open) .fields > :global(:not(:first-child)) {
            display: none;
        }
        .foot {
            justify-content: flex-start;
            padding-top: var(--vff-space-3);
        }
        .filters-toggle {
            display: inline-flex;
        }
        .count {
            margin-left: auto;
        }
    }
</style>

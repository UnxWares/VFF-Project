<script>
    /**
     * Tableau d'un annuaire : grand écran = vrai tableau (`.vff-table`, défilement horizontal au
     * clavier si l'écran est étroit) ; téléphone (600 px et moins) = une fiche par ligne, la première
     * cellule (`td.name`) en titre et chaque autre cellule précédée de son intitulé (`data-label`).
     * Aucune colonne n'est masquée : tout ce qu'on voit sur grand écran se lit sur téléphone.
     *
     * La page écrit `<thead>` et `<tbody>` dans `children` ; les cellules portent `data-label` et, pour
     * la cellule vide d'une ligne, la classe `blank` (masquée sur téléphone).
     *
     * Props : `label` (nom de la zone défilante), `caption` (légende lue par les lecteurs d'écran).
     */
    /** @type {{ label: string, caption: string, children: import('svelte').Snippet }} */
    let { label, caption, children } = $props();
</script>

<!-- Zone défilante : focalisable pour pouvoir la faire défiler au clavier (guide, section 5.8). -->
<!-- svelte-ignore a11y_no_noninteractive_tabindex -->
<div class="vff-table-wrap directory-table" role="region" aria-label={label} tabindex="0">
    <table class="vff-table">
        <caption class="vff-sr-only">{caption}</caption>
        {@render children()}
    </table>
</div>

<style>
    .directory-table :global(.vff-table) {
        min-width: 640px;
    }
    .directory-table :global(.vff-table td),
    .directory-table :global(.vff-table th) {
        padding: 12px 16px;
        vertical-align: top;
    }
    .directory-table :global(.vff-table tbody tr) {
        transition: background-color var(--vff-transition);
    }
    .directory-table :global(.vff-table tbody tr:hover) {
        background-color: var(--vff-bg-accent);
    }
    .directory-table :global(.vff-table tbody tr:focus-within) {
        background-color: var(--vff-bg-accent);
    }

    @media (max-width: 600px) {
        .directory-table {
            overflow: visible;
            border: 0;
            border-radius: 0;
        }
        .directory-table :global(.vff-table),
        .directory-table :global(.vff-table tbody) {
            display: block;
            min-width: 0;
        }
        .directory-table :global(.vff-table thead) {
            position: absolute;
            width: 1px;
            height: 1px;
            overflow: hidden;
            clip-path: inset(50%);
        }
        .directory-table :global(.vff-table tbody tr) {
            display: grid;
            gap: 6px;
            padding: var(--vff-space-4) 0;
            border-bottom: 1px solid var(--vff-border);
        }
        .directory-table :global(.vff-table tbody tr:first-child) {
            padding-top: 0;
        }
        .directory-table :global(.vff-table tbody tr:hover) {
            background-color: transparent;
        }
        .directory-table :global(.vff-table td),
        .directory-table :global(.vff-table th) {
            display: block;
            padding: 0;
            border: 0;
            text-align: left;
        }
        .directory-table :global(.vff-table td.name) {
            order: -1;
            font-size: var(--vff-fs-md);
        }
        .directory-table :global(.vff-table td[data-label]:not(.name)::before) {
            content: attr(data-label);
            display: inline-block;
            min-width: 9rem;
            margin-right: var(--vff-space-2);
            font-size: var(--vff-fs-xs);
            color: var(--vff-text-muted);
        }
        .directory-table :global(.vff-table td.blank) {
            display: none;
        }
        .directory-table :global(.vff-table td.num) {
            text-align: left;
        }
    }
</style>

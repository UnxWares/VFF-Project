<script>
    /**
     * Tableau de données d'un graphique, dans un bloc dépliable : en-têtes de colonnes et de lignes,
     * légende. `columns` [{ label, num }] (la première colonne porte les en-têtes de lignes),
     * `rows` : listes de cellules déjà mises en forme.
     */
    /** @type {{ summary: string, caption: string, columns: Array<{ label: string, num?: boolean }>, rows: Array<Array<string|number>> }} */
    let { summary, caption, columns, rows } = $props();
</script>

<details class="figures">
    <summary>{summary}</summary>
    <!-- Tableau large : le bloc défile au clavier (tabindex, consigne de .vff-table-wrap). -->
    <!-- svelte-ignore a11y_no_noninteractive_tabindex -->
    <div class="vff-table-wrap" tabindex="0" role="region" aria-label={caption}>
        <table class="vff-table">
            <caption class="vff-sr-only">{caption}</caption>
            <thead>
                <tr>
                    {#each columns as c, i (i)}
                        <th scope="col" class={{ num: c.num }}>{c.label}</th>
                    {/each}
                </tr>
            </thead>
            <tbody>
                {#each rows as cells, r (r)}
                    <tr>
                        {#each cells as cell, i (i)}
                            {#if i === 0}
                                <th scope="row">{cell}</th>
                            {:else}
                                <td class={{ num: columns[i]?.num }}>{cell}</td>
                            {/if}
                        {/each}
                    </tr>
                {/each}
            </tbody>
        </table>
    </div>
</details>

<style>
    .figures {
        margin-top: var(--vff-space-5);
    }
    .figures summary {
        display: list-item;
        width: fit-content;
        min-height: 24px;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: 24px;
        color: var(--vff-link);
        text-decoration: underline;
        text-underline-offset: 0.18em;
        cursor: pointer;
    }
    .figures .vff-table-wrap {
        margin-top: var(--vff-space-3);
    }
    @media (max-width: 600px) {
        .figures summary {
            padding-block: 10px;
        }
    }
</style>

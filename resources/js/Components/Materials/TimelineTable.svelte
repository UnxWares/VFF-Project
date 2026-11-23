<script>
    import { Link } from '@inertiajs/svelte';
    import { i18n } from '$lib/i18n.js';
    import { sortTable } from '$lib/materialTimeline.js';

    /**
     * Alternative en tableau de la frise : toutes les séries (datées ou non), triables par colonne.
     * L'en-tête de la colonne triée porte `aria-sort` ; le bouton de tri annonce son effet.
     *
     * Props : `rows`, `year` (année choisie ou null : ajoute une colonne « état »).
     */
    /** @type {{ rows: object[], year: number|null, stateOf?: (row: object) => string }} */
    let { rows, year, stateOf = () => 'no' } = $props();

    const lang = $derived($i18n.language ?? 'fr');
    const t = (key, opts) => String($i18n.t(key, opts));
    const fmt = (n) => Number(n).toLocaleString(lang);

    const COLUMNS = ['name', 'group', 'from', 'to', 'units'];
    let key = $state('from');
    let dir = $state('asc');
    const sorted = $derived(sortTable(rows, key, dir));

    function sortBy(column) {
        if (key === column) dir = dir === 'asc' ? 'desc' : 'asc';
        else {
            key = column;
            dir = 'asc';
        }
    }
    const ariaSort = (column) =>
        key === column ? (dir === 'asc' ? 'ascending' : 'descending') : undefined;
    const unknown = $derived(t('materials_timeline.table.unknown'));
    const statusOf = (row) => t(`materials_timeline.status.${row.service_status ?? 'unknown'}`);
</script>

<!-- svelte-ignore a11y_no_noninteractive_tabindex (région défilante : le tableau déborde sur téléphone) -->
<div class="wrap" role="region" aria-label={t('materials_timeline.table.caption')} tabindex="0">
    <table>
        <caption class="vff-sr-only">{t('materials_timeline.table.caption')}</caption>
        <thead>
            <tr>
                {#each COLUMNS as column (column)}
                    <th scope="col" aria-sort={ariaSort(column)} class={column}>
                        <button type="button" onclick={() => sortBy(column)}>
                            {t(`materials_timeline.table.${column}`)}
                            <span class="vff-sr-only"
                                >{t('materials_timeline.table.sort_by', {
                                    column: t(`materials_timeline.table.${column}`),
                                })}{key === column
                                    ? `, ${t(`materials_timeline.table.sorted_${dir}`)}`
                                    : ''}</span
                            >
                            <i
                                class={[
                                    'bi',
                                    key === column
                                        ? dir === 'asc'
                                            ? 'bi-sort-up'
                                            : 'bi-sort-down'
                                        : 'bi-arrow-down-up',
                                ]}
                                aria-hidden="true"
                            ></i>
                        </button>
                    </th>
                {/each}
                <th scope="col">{t('materials_timeline.table.status')}</th>
                {#if year !== null}
                    <th scope="col">{t('materials_timeline.year_label')} {year}</th>
                {/if}
            </tr>
        </thead>
        <tbody>
            {#each sorted as row (row.slug)}
                <tr>
                    <th scope="row" class="name">
                        <Link href="/materials/{row.slug}">{row.name}</Link>
                    </th>
                    <td>{t(`materials_timeline.groups_short.${row.group}`)}</td>
                    <td class="num">{row.in_service_from ?? unknown}</td>
                    <td class="num">{row.in_service_to ?? unknown}</td>
                    <td class="num">{row.units_built === null ? unknown : fmt(row.units_built)}</td>
                    <td>{statusOf(row)}</td>
                    {#if year !== null}
                        <td
                            >{stateOf(row) === 'no'
                                ? ''
                                : t(`materials_timeline.state.${stateOf(row)}`)}</td
                        >
                    {/if}
                </tr>
            {/each}
        </tbody>
    </table>
</div>

<style>
    .wrap {
        overflow-x: auto;
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        background-color: var(--vff-bg-elevated);
    }
    table {
        width: 100%;
        border-collapse: collapse;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
    }
    th,
    td {
        padding: var(--vff-space-2) var(--vff-space-3);
        text-align: left;
        vertical-align: top;
        border-bottom: 1px solid var(--vff-border);
    }
    thead th {
        background-color: var(--vff-bg-subtle);
        white-space: nowrap;
    }
    th[scope='row'] {
        font-weight: 600;
    }
    .num {
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }
    th button {
        display: inline-flex;
        align-items: center;
        gap: var(--vff-space-2);
        min-height: 44px;
        padding: 0;
        border: 0;
        background: none;
        color: inherit;
        font: inherit;
        font-weight: 700;
        cursor: pointer;
    }
    th button:focus-visible {
        outline: var(--vff-focus-width) solid var(--vff-focus);
        outline-offset: 2px;
    }
    tbody tr:last-child > * {
        border-bottom: 0;
    }
</style>

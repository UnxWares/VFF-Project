<script>
    /**
     * Tableau de tous les départements : kilomètres en service en 1930 et aujourd'hui, part du réseau de 1930
     * fermée, gares ouvertes ; triable par colonne (boutons dans les en-têtes, `aria-sort`), filtrable par nom, code
     * ou région. Les chiffres viennent de DepartmentFigures (cache, vff:compute-department-figures).
     */
    import { Link } from '@inertiajs/svelte';
    import Layout from '$components/Layout.svelte';
    import PageHero from '$components/PageHero.svelte';
    import SectionBlock from '$components/SectionBlock.svelte';
    import SeoHead from '$components/SeoHead.svelte';
    import EmptyState from '$components/ui/EmptyState.svelte';
    import { i18n } from '$lib/i18n.js';
    import { formatNumber, formatLocale } from '$lib/typo.js';
    import { filterDepartments, sortDepartments } from '$lib/departmentSheet.js';
    import { percentOf } from '$lib/networkFigures.js';

    /** @type {{ figures: null | { computed_at: string, reference_year: number, current_year: number, departments: Array<any> } }} */
    let { figures } = $props();

    const t = (key, vars) => String($i18n.t(`departments.index.${key}`, vars));
    const lang = $derived($i18n.language ?? 'fr');
    const n = (v) => formatNumber(v, lang);
    const year = $derived(figures?.reference_year ?? 1930);
    const computedOn = $derived(
        figures
            ? new Date(figures.computed_at).toLocaleDateString(formatLocale(lang), {
                  day: 'numeric',
                  month: 'long',
                  year: 'numeric',
              })
            : '',
    );

    let query = $state('');
    let sortKey = $state('code');
    /** @type {'asc' | 'desc'} */
    let sortDir = $state('asc');

    const columns = $derived([
        { key: 'name', label: t('col_department'), num: false },
        { key: 'region', label: t('col_region'), num: false },
        { key: 'km_reference', label: t('col_km_reference', { year }), num: true },
        { key: 'km_today', label: t('col_km_today'), num: true },
        { key: 'closed_share', label: t('col_closed_share'), num: true },
        { key: 'stations_reference', label: t('col_stations_reference', { year }), num: true },
        { key: 'stations_today', label: t('col_stations_today'), num: true },
    ]);

    const rows = $derived(
        sortDepartments(
            filterDepartments(figures?.departments ?? [], query),
            sortKey,
            sortDir,
            lang,
        ),
    );

    /** Premier clic : ordre naturel de la colonne (texte croissant, nombres décroissants) ; second clic : inverse. */
    function sortBy(key, num) {
        if (sortKey === key) {
            sortDir = sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            sortKey = key;
            sortDir = num ? 'desc' : 'asc';
        }
    }
    const ariaSort = (key) =>
        sortKey === key ? (sortDir === 'asc' ? 'ascending' : 'descending') : 'none';
    const NBSP = '\u00A0';
    const share = (v) => (v === null || v === undefined ? '-' : `${percentOf(v)}${NBSP}%`);
</script>

<SeoHead title={t('meta_title')} description={t('meta_description')} path="/departments" />
<svelte:head>
    <title>{t('meta_title')} - VFF</title>
</svelte:head>

<Layout compactHeader>
    <main>
        <PageHero
            title={t('title')}
            subtitle={t('subtitle')}
            status={figures ? t('computed', { date: computedOn }) : null}
        />

        <SectionBlock title={t('table_title')} maxWidth="1180px">
            {#if figures}
                <div class="vff-field search">
                    <label class="vff-label" for="dep-q">{t('filter')}</label>
                    <input
                        id="dep-q"
                        class="vff-input"
                        type="search"
                        autocomplete="off"
                        enterkeyhint="search"
                        placeholder={t('filter_hint')}
                        bind:value={query}
                    />
                </div>
                <p class="count" role="status">{t('count', { count: n(rows.length) })}</p>

                <!-- Tableau large : le bloc défile au clavier (tabindex, consigne de .vff-table-wrap). -->
                <!-- svelte-ignore a11y_no_noninteractive_tabindex -->
                <div
                    class="vff-table-wrap"
                    tabindex="0"
                    role="region"
                    aria-label={t('table_caption')}
                >
                    <table class="vff-table">
                        <caption class="vff-sr-only">{t('table_caption')}</caption>
                        <thead>
                            <tr>
                                {#each columns as c (c.key)}
                                    <th
                                        scope="col"
                                        class={{ num: c.num }}
                                        aria-sort={ariaSort(c.key)}
                                    >
                                        <button
                                            type="button"
                                            class="sort"
                                            onclick={() => sortBy(c.key, c.num)}
                                            title={t('sort_by', { column: c.label })}
                                        >
                                            {c.label}
                                            <span class="arrow" aria-hidden="true"
                                                >{sortKey === c.key
                                                    ? sortDir === 'asc'
                                                        ? '▲'
                                                        : '▼'
                                                    : ''}</span
                                            >
                                        </button>
                                    </th>
                                {/each}
                            </tr>
                        </thead>
                        <tbody>
                            {#each rows as d (d.code)}
                                <tr>
                                    <th scope="row">
                                        <Link href="/departments/{d.slug}" class="vff-link"
                                            >{d.name}</Link
                                        >
                                        <span class="code">{d.code}</span>
                                    </th>
                                    <td>{d.region}</td>
                                    <td class="num">{n(d.km_reference)}</td>
                                    <td class="num">{n(d.km_today)}</td>
                                    <td class="num">{share(d.closed_share)}</td>
                                    <td class="num">{n(d.stations_reference)}</td>
                                    <td class="num">{n(d.stations_today)}</td>
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                </div>
                {#if rows.length === 0}
                    <EmptyState icon="bi-search" title={t('no_match')} />
                {/if}
            {:else}
                <EmptyState icon="bi-hourglass-split" title={t('not_computed')} />
            {/if}
        </SectionBlock>

        {#if figures}
            <SectionBlock kind="alt" title={t('notes_title')} maxWidth="1180px">
                <ul class="notes">
                    <li>{t('note_km')}</li>
                    <li>{t('note_share', { year })}</li>
                    <li>{t('note_stations')}</li>
                </ul>
                <p class="link">
                    <Link href="/network" class="vff-link">{t('network_link')}</Link>
                </p>
            </SectionBlock>
        {/if}
    </main>
</Layout>

<style>
    .search {
        max-width: 420px;
        margin-bottom: var(--vff-space-3);
    }
    .count {
        margin: 0 0 var(--vff-space-3);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
    .sort {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        min-height: 44px;
        padding: 0;
        border: 0;
        background: none;
        font: inherit;
        font-weight: 600;
        color: inherit;
        cursor: pointer;
        text-align: inherit;
    }
    .sort:hover {
        color: var(--vff-text);
    }
    .arrow {
        display: inline-block;
        min-width: 1em;
        font-size: 0.7em;
    }
    th[scope='row'] {
        font-weight: 600;
        white-space: nowrap;
    }
    .code {
        margin-left: 8px;
        font-weight: 400;
        font-variant-numeric: tabular-nums;
        color: var(--vff-text-muted);
    }
    .notes {
        max-width: var(--vff-measure);
        margin: 0;
        padding-left: 1.2em;
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-body);
        line-height: 1.6;
    }
    .notes li {
        margin-bottom: var(--vff-space-2);
    }
    .link {
        margin: var(--vff-space-4) 0 0;
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-body);
    }
</style>

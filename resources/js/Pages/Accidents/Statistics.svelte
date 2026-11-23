<script>
    /**
     * « Les accidents en chiffres » : accidents et victimes par décennie, par nature, part des passages à
     * niveau, bilans les plus lourds, note de méthode. Les agrégats viennent d'AccidentStatistics (cache) ;
     * les filtres (période, nature, fiches à vérifier) sont dans l'adresse, donc partageables. Chaque
     * graphique est doublé d'un tableau ; les bilans inconnus ne sont jamais estimés.
     */
    import { Link, router } from '@inertiajs/svelte';
    import Layout from '$components/Layout.svelte';
    import PageHero from '$components/PageHero.svelte';
    import SectionBlock from '$components/SectionBlock.svelte';
    import SeoHead from '$components/SeoHead.svelte';
    import EmptyState from '$components/ui/EmptyState.svelte';
    import NetworkChart from '$components/Network/NetworkChart.svelte';
    import FiguresTable from '$components/Network/FiguresTable.svelte';
    import { i18n } from '$lib/i18n.js';
    import { accidentDate, tollText } from '$lib/accidentText.js';
    import { decadeOf, formatNumber, formatLocale, NBSP } from '$lib/typo.js';

    /** @type {{ statistics: any, filters: { from: number|null, to: number|null, kind: string|null, review: string|null }, unfiltered_total: number }} */
    let { statistics, filters, unfiltered_total } = $props();

    const KINDS = [
        'collision',
        'derailment',
        'level_crossing',
        'fire',
        'explosion',
        'structure_failure',
        'attack',
        'war_action',
        'person_struck',
        'runaway',
        'incident',
        'other',
    ];
    const PATH = '/accidents/statistics';

    /** @param {string} key @param {Record<string, string|number>} [vars] */
    const t = (key, vars) => String($i18n.t(`accident_stats.${key}`, vars));
    const lang = $derived($i18n.language ?? 'fr');
    const n = (v) => formatNumber(v, lang);
    const pct = (share) =>
        `${formatNumber(Math.round(share * 1000) / 10, lang, { maximumFractionDigits: 1 })}${lang === 'en' ? '' : NBSP}%`;

    // Les champs partent des filtres reçus puis évoluent avec la saisie : valeur initiale voulue.
    // svelte-ignore state_referenced_locally
    let from = $state(filters.from ? String(filters.from) : '');
    // svelte-ignore state_referenced_locally
    let to = $state(filters.to ? String(filters.to) : '');
    // svelte-ignore state_referenced_locally
    let kind = $state(filters.kind ?? '');
    // svelte-ignore state_referenced_locally
    let review = $state(filters.review ?? '');

    const hasFilters = $derived(!!(filters.from || filters.to || filters.kind || filters.review));

    function params() {
        const p = { from, to, kind, review };
        Object.keys(p).forEach((k) => (p[k] === '' || p[k] == null) && delete p[k]);

        return p;
    }
    function apply() {
        router.get(PATH, params(), { preserveScroll: true });
    }
    function clearFilters() {
        from = to = kind = review = '';
        router.get(PATH, {}, { preserveScroll: true });
    }

    const totals = $derived(statistics.totals);
    const decades = $derived(statistics.decades);
    const kinds = $derived(statistics.kinds);
    const deadliest = $derived(statistics.deadliest);
    const hasData = $derived(statistics.totals.accidents > 0 || kinds.length > 0);
    const computedOn = $derived(
        new Date(statistics.computed_at).toLocaleDateString(formatLocale(lang), {
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        }),
    );
    const day = (iso) => accidentDate(iso, 'day', lang);
    const decadeLabel = (d) =>
        String($i18n.t('accidents.index.decade_option', { decade: decadeOf(d.decade, lang) }));
    const kindLabel = (k) => String($i18n.t(`accidents.kinds.${k}`));
    const nameOf = (a) => a.names?.[lang] ?? a.name;
    const place = (a) =>
        a.commune ? (a.department_code ? `${a.commune} (${a.department_code})` : a.commune) : '';

    // 1. Accidents par décennie : fiches confirmées et fiches à vérifier distinguées.
    const decadeSeries = $derived([
        { label: t('s_confirmed'), tone: 'a' },
        { label: t('s_review'), tone: 'c' },
    ]);
    const decadeRows = $derived(
        decades.map((d) => ({
            key: d.decade,
            label: decadeLabel(d),
            values: [d.accidents - d.to_review, d.to_review],
            value: t('decade_value', { count: d.accidents, value: n(d.accidents) }),
            sr: t('decade_sr', {
                label: decadeLabel(d),
                count: d.accidents,
                value: n(d.accidents),
                review: n(d.to_review),
            }),
        })),
    );

    // 2. Morts par décennie (bilans connus seulement).
    const deathSeries = $derived([{ label: t('s_deaths'), tone: 'd' }]);
    const deathRows = $derived(
        decades.map((d) => ({
            key: d.decade,
            label: decadeLabel(d),
            values: [d.deaths],
            value: t('deaths_value', { count: d.deaths, value: n(d.deaths) }),
            sr: t('deaths_sr', {
                label: decadeLabel(d),
                count: d.deaths,
                value: n(d.deaths),
                known: n(d.deaths_known),
            }),
        })),
    );

    // 3. Par nature.
    const kindSeries = $derived([{ label: t('col_accidents'), tone: 'b' }]);
    const kindRows = $derived(
        kinds.map((k) => ({
            key: k.kind,
            label: kindLabel(k.kind),
            values: [k.accidents],
            value: t('kind_value', { count: k.accidents, value: n(k.accidents) }),
            sr: t('kind_sr', {
                label: kindLabel(k.kind),
                count: k.accidents,
                value: n(k.accidents),
                deaths: n(k.deaths),
            }),
        })),
    );

    // 4. Part des passages à niveau (sur toutes les natures).
    const lcSeries = $derived([{ label: t('s_level_crossing'), tone: 'e' }]);
    const lcDecades = $derived(decades.filter((d) => d.all_kinds > 0));
    const lcText = (d) =>
        d.level_crossing === 0
            ? t('lc_none')
            : t('lc_value', {
                  share: pct(d.level_crossing_share),
                  count: n(d.level_crossing),
                  total: n(d.all_kinds),
              });
    const lcRows = $derived(
        lcDecades.map((d) => ({
            key: d.decade,
            label: decadeLabel(d),
            values: [Math.round(d.level_crossing_share * 1000) / 10],
            value: lcText(d),
            sr: t('lc_sr', {
                label: decadeLabel(d),
                count: n(d.level_crossing),
                total: n(d.all_kinds),
                share: pct(d.level_crossing_share),
            }),
        })),
    );

    const methods = ['scope', 'coverage', 'toll', 'review', 'sources'];
</script>

<SeoHead title={t('meta_title')} description={t('meta_description')} path={PATH} />
<svelte:head>
    <title>{t('meta_title')} - VFF</title>
</svelte:head>

<Layout compactHeader>
    <main>
        <PageHero
            title={t('hero_title')}
            subtitle={t('hero_subtitle')}
            status={t('computed', { date: computedOn })}
        />

        <SectionBlock title={t('filters_title')} maxWidth="1180px">
            <search aria-label={t('filters_title')}>
                <form
                    class="vff-panel filters"
                    method="get"
                    action={PATH}
                    onsubmit={(e) => {
                        e.preventDefault();
                        apply();
                    }}
                >
                    <div class="vff-field">
                        <label class="vff-label" for="stats-from">{t('filter_from')}</label>
                        <input
                            id="stats-from"
                            class="vff-input"
                            type="number"
                            name="from"
                            inputmode="numeric"
                            min="1800"
                            max="2100"
                            placeholder="1833"
                            bind:value={from}
                        />
                    </div>
                    <div class="vff-field">
                        <label class="vff-label" for="stats-to">{t('filter_to')}</label>
                        <input
                            id="stats-to"
                            class="vff-input"
                            type="number"
                            name="to"
                            inputmode="numeric"
                            min="1800"
                            max="2100"
                            placeholder="2026"
                            bind:value={to}
                        />
                    </div>
                    <div class="vff-field">
                        <label class="vff-label" for="stats-kind">{t('filter_kind')}</label>
                        <select id="stats-kind" class="vff-select" name="kind" bind:value={kind}>
                            <option value="">{t('all_kinds')}</option>
                            {#each KINDS as k (k)}
                                <option value={k}>{kindLabel(k)}</option>
                            {/each}
                        </select>
                    </div>
                    <div class="vff-field">
                        <label class="vff-label" for="stats-review">{t('filter_review')}</label>
                        <select
                            id="stats-review"
                            class="vff-select"
                            name="review"
                            bind:value={review}
                        >
                            <option value="">{t('review_all')}</option>
                            <option value="ok">{t('review_ok')}</option>
                        </select>
                    </div>
                    <div class="actions">
                        <button type="submit" class="vff-btn primary">{t('apply')}</button>
                        {#if hasFilters}
                            <button type="button" class="vff-btn secondary" onclick={clearFilters}
                                >{t('clear')}</button
                            >
                        {/if}
                    </div>
                </form>
            </search>
            <p class="note" aria-live="polite">
                {t('filtered_count', {
                    count: totals.accidents,
                    value: n(totals.accidents),
                    total: n(unfiltered_total),
                })}
            </p>
            <p class="note">{t('share_hint')} {t('kind_scope')}</p>
        </SectionBlock>

        {#if !hasData}
            <SectionBlock maxWidth="1180px">
                <EmptyState icon="bi-search" title={t('empty_title')} text={t('empty_text')}>
                    <button type="button" class="vff-btn secondary small" onclick={clearFilters}
                        >{t('clear')}</button
                    >
                </EmptyState>
            </SectionBlock>
        {:else}
            <SectionBlock kind="alt" title={t('key_title')} maxWidth="1180px">
                <dl class="key">
                    <div>
                        <dt>{t('key_accidents')}</dt>
                        <dd>{n(totals.accidents)}</dd>
                        <dd class="detail">
                            {t('key_accidents_detail', { count: n(totals.to_review) })}
                        </dd>
                    </div>
                    <div>
                        <dt>{t('key_deaths')}</dt>
                        <dd>{n(totals.deaths)}</dd>
                        <dd class="detail">
                            {t('key_deaths_detail', { count: n(totals.deaths_known) })}
                        </dd>
                    </div>
                    <div>
                        <dt>{t('key_injuries')}</dt>
                        <dd>{n(totals.injuries)}</dd>
                        <dd class="detail">
                            {t('key_injuries_detail', { count: n(totals.injuries_known) })}
                        </dd>
                    </div>
                    <div>
                        <dt>{t('key_period')}</dt>
                        <dd>
                            {totals.first_date ? totals.first_date.slice(0, 4) : ''}
                            {totals.last_date
                                ? `${NBSP}-${NBSP}${totals.last_date.slice(0, 4)}`
                                : ''}
                        </dd>
                        <dd class="detail">
                            {totals.first_date && totals.last_date
                                ? t('key_period_detail', {
                                      first: day(totals.first_date),
                                      last: day(totals.last_date),
                                  })
                                : ''}
                        </dd>
                    </div>
                </dl>
                <p class="note">{t('reading')}</p>
            </SectionBlock>

            <SectionBlock title={t('decades_title')} intro={t('decades_intro')} maxWidth="1180px">
                <NetworkChart
                    rows={decadeRows}
                    series={decadeSeries}
                    caption={t('decades_caption')}
                    reserve={9}
                />
                <FiguresTable
                    summary={t('table_toggle')}
                    caption={t('decades_table')}
                    columns={[
                        { label: t('col_decade') },
                        { label: t('col_accidents'), num: true },
                        { label: t('col_confirmed'), num: true },
                        { label: t('col_review'), num: true },
                    ]}
                    rows={decades.map((d) => [
                        decadeLabel(d),
                        n(d.accidents),
                        n(d.accidents - d.to_review),
                        n(d.to_review),
                    ])}
                />
            </SectionBlock>

            <SectionBlock
                kind="alt"
                title={t('deaths_title')}
                intro={t('deaths_intro')}
                maxWidth="1180px"
            >
                <NetworkChart
                    rows={deathRows}
                    series={deathSeries}
                    caption={t('deaths_caption')}
                    reserve={9}
                />
                <FiguresTable
                    summary={t('table_toggle')}
                    caption={t('deaths_table')}
                    columns={[
                        { label: t('col_decade') },
                        { label: t('col_deaths'), num: true },
                        { label: t('col_deaths_known'), num: true },
                        { label: t('col_injuries'), num: true },
                        { label: t('col_injuries_known'), num: true },
                    ]}
                    rows={decades.map((d) => [
                        decadeLabel(d),
                        n(d.deaths),
                        n(d.deaths_known),
                        n(d.injuries),
                        n(d.injuries_known),
                    ])}
                />
            </SectionBlock>

            <SectionBlock title={t('kinds_title')} intro={t('kinds_intro')} maxWidth="1180px">
                <NetworkChart
                    rows={kindRows}
                    series={kindSeries}
                    caption={t('kinds_caption')}
                    reserve={9}
                />
                <FiguresTable
                    summary={t('table_toggle')}
                    caption={t('kinds_table')}
                    columns={[
                        { label: t('col_kind') },
                        { label: t('col_accidents'), num: true },
                        { label: t('col_deaths'), num: true },
                        { label: t('col_deaths_known'), num: true },
                    ]}
                    rows={kinds.map((k) => [
                        kindLabel(k.kind),
                        n(k.accidents),
                        n(k.deaths),
                        n(k.deaths_known),
                    ])}
                />
            </SectionBlock>

            <SectionBlock kind="alt" title={t('lc_title')} intro={t('lc_intro')} maxWidth="1180px">
                <NetworkChart
                    rows={lcRows}
                    series={lcSeries}
                    caption={t('lc_caption')}
                    reserve={17}
                />
                <FiguresTable
                    summary={t('table_toggle')}
                    caption={t('lc_table')}
                    columns={[
                        { label: t('col_decade') },
                        { label: t('col_all_kinds'), num: true },
                        { label: t('col_level_crossing'), num: true },
                        { label: t('col_share'), num: true },
                    ]}
                    rows={lcDecades.map((d) => [
                        decadeLabel(d),
                        n(d.all_kinds),
                        n(d.level_crossing),
                        pct(d.level_crossing_share),
                    ])}
                />
                <p class="note">{t('lc_note')}</p>
            </SectionBlock>

            <SectionBlock
                title={t('deadliest_title')}
                intro={t('deadliest_intro')}
                maxWidth="1180px"
            >
                {#if deadliest.length}
                    <!-- Tableau large : le bloc défile au clavier (tabindex, consigne de .vff-table-wrap). -->
                    <!-- svelte-ignore a11y_no_noninteractive_tabindex -->
                    <div
                        class="vff-table-wrap"
                        tabindex="0"
                        role="region"
                        aria-label={t('deadliest_table')}
                    >
                        <table class="vff-table">
                            <caption class="vff-sr-only">{t('deadliest_table')}</caption>
                            <thead>
                                <tr>
                                    <th scope="col">{t('col_date')}</th>
                                    <th scope="col">{t('col_accident')}</th>
                                    <th scope="col">{t('col_kind')}</th>
                                    <th scope="col" class="num">{t('col_toll')}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {#each deadliest as a (a.slug)}
                                    <tr>
                                        <td class="when"
                                            ><time datetime={a.date}>{day(a.date)}</time></td
                                        >
                                        <td>
                                            <Link class="vff-link" href="/accidents/{a.slug}"
                                                >{nameOf(a)}</Link
                                            >
                                            {#if place(a)}<span class="where">{place(a)}</span>{/if}
                                            {#if a.review_status === 'to_review'}
                                                <span class="vff-badge warning"
                                                    >{t('review_badge')}</span
                                                >
                                            {/if}
                                        </td>
                                        <td>{kindLabel(a.kind)}</td>
                                        <td class="num"
                                            >{tollText(a.deaths, a.injuries, $i18n.t)}</td
                                        >
                                    </tr>
                                {/each}
                            </tbody>
                        </table>
                    </div>
                {/if}
            </SectionBlock>

            <SectionBlock
                kind="alt"
                title={t('method_title')}
                intro={t('method_intro')}
                maxWidth="1180px"
            >
                <ul class="defs">
                    {#each methods as m (m)}
                        <li>
                            <h3>{t(`m_${m}_term`)}</h3>
                            <p>{t(`m_${m}_text`)}</p>
                        </li>
                    {/each}
                </ul>
                <p class="note">{t('method_dates')}</p>
                <p class="note">
                    <Link href="/accidents" class="vff-link">{t('link_list')}</Link>
                </p>
                <p class="note">
                    <Link href="/network" class="vff-link">{t('link_network')}</Link>
                </p>
                <p class="note">
                    {t('api_text')}
                    <a href="/api/v1/accidents/statistics" class="vff-link"
                        >/api/v1/accidents/statistics</a
                    >
                </p>
            </SectionBlock>
        {/if}
    </main>
</Layout>

<style>
    .filters {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        align-items: end;
        gap: var(--vff-space-4);
    }
    .actions {
        grid-column: 1 / -1;
        display: flex;
        flex-wrap: wrap;
        gap: var(--vff-space-3);
    }
    .key {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: var(--vff-space-5) var(--vff-space-6);
        margin: 0;
        padding: 0;
    }
    .key > div {
        min-width: 0;
        padding-top: var(--vff-space-3);
        border-top: 1px solid var(--vff-border-strong);
    }
    .key dt {
        margin-bottom: 6px;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: 1.4;
        color: var(--vff-text-muted);
    }
    .key dd {
        margin: 0;
        font-family: var(--vff-font-display);
        font-size: var(--vff-fs-figure);
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        line-height: 1.15;
        color: var(--vff-text);
    }
    .key dd.detail {
        margin-top: 4px;
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-md);
        font-weight: 400;
        line-height: 1.5;
        color: var(--vff-text-muted);
    }
    .note {
        max-width: var(--vff-measure);
        margin: var(--vff-space-5) 0 0;
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-body);
        line-height: 1.6;
        color: var(--vff-text-muted);
    }
    .when {
        white-space: nowrap;
        font-variant-numeric: tabular-nums;
    }
    .num {
        text-align: end;
        font-variant-numeric: tabular-nums;
    }
    .where {
        display: block;
        font-size: var(--vff-fs-xs);
        color: var(--vff-text-muted);
    }
    .defs {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: var(--vff-space-5) var(--vff-space-6);
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .defs li {
        min-width: 0;
        margin: 0;
        padding-top: var(--vff-space-3);
        border-top: 1px solid var(--vff-border-strong);
    }
    .defs h3 {
        margin: 0 0 6px;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-h3);
        font-weight: 600;
        line-height: 1.3;
    }
    .defs p {
        margin: 0;
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-body);
        line-height: 1.6;
        color: var(--vff-text);
    }
    @media (max-width: 900px) {
        .filters,
        .key {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .defs {
            grid-template-columns: minmax(0, 1fr);
        }
    }
    @media (max-width: 520px) {
        .filters,
        .key {
            grid-template-columns: minmax(0, 1fr);
        }
        .when {
            white-space: normal;
        }
    }
</style>

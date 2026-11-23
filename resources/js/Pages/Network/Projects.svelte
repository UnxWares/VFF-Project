<script>
    /**
     * « Projets : réouvertures et lignes nouvelles » : chaque projet ferroviaire français pas encore en service
     * (réouverture voyageurs, ligne nouvelle, LGV, RER métropolitain, électrification) avec son état, la date de chaque
     * étape franchie, ses porteurs, la fiche de ligne quand elle existe et la source de chaque fait daté. Une date
     * future est toujours « prévue », avec celui qui l'annonce. Les données viennent de App\Services\RailProjects (mêmes
     * valeurs que GET /api/v1/projects) ; recherche, état, type et région se font dans le navigateur.
     */
    import Layout from '$components/Layout.svelte';
    import PageHero from '$components/PageHero.svelte';
    import SectionBlock from '$components/SectionBlock.svelte';
    import SeoHead from '$components/SeoHead.svelte';
    import DirectoryFilters from '$components/Directory/DirectoryFilters.svelte';
    import EmptyState from '$components/ui/EmptyState.svelte';
    import { i18n } from '$lib/i18n.js';
    import { formatNumber } from '$lib/typo.js';
    import {
        countBy,
        countByRegion,
        filterProjects,
        formatStageDate,
        plannedCommissioning,
    } from '$lib/railProjects.js';

    /** @type {{ data: { projects: Array<any>, regions: Array<string>, kinds: Array<string>, states: Array<string>, checked: string|null } }} */
    let { data } = $props();

    /** @param {string} key @param {Record<string, string|number>} [vars] */
    const t = (key, vars) => String($i18n.t(`projects.${key}`, vars));
    const lang = $derived($i18n.language ?? 'fr');
    const n = (v) => formatNumber(v, lang);
    const day = (date) => formatStageDate(date, lang);

    let q = $state('');
    let stateFilter = $state('');
    let kind = $state('');
    let region = $state('');

    const filtered = $derived(
        filterProjects(data.projects, { q, state: stateFilter, kind, region }),
    );
    const stateCounts = $derived(countBy(data.projects, 'state', data.states));
    const kindCounts = $derived(countBy(data.projects, 'kind', data.kinds));
    const regionCounts = $derived(countByRegion(data.projects));
    const hasFilters = $derived(!!(q || stateFilter || kind || region));
    const checkedOn = $derived(data.checked ? day(data.checked) : null);

    function reset() {
        q = '';
        stateFilter = '';
        kind = '';
        region = '';
    }

    /** Étape suivante prévue, dite avec sa source : jamais une date sèche. */
    function nextLine(p) {
        const plan = plannedCommissioning(p, lang);
        if (plan) return t('planned_line', { date: plan.date, source: plan.source });
        const next = p.next_stage;
        if (next) {
            return t('planned_line_next', {
                stage: t(`stages.${next.kind}`).toLowerCase(),
                date: day(next.date),
                source: next.source.label,
            });
        }
        return t('no_planned');
    }
</script>

<SeoHead title={t('meta_title')} description={t('meta_description')} path="/projects" />
<svelte:head>
    <title>{t('meta_title')} - VFF</title>
</svelte:head>

<Layout compactHeader>
    <main>
        <PageHero
            title={t('hero_title')}
            subtitle={t('hero_subtitle', { count: n(data.projects.length) })}
        />

        <div class="vff-container directory">
            <DirectoryFilters
                label={t('filters_title')}
                results={t('results', { count: n(filtered.length) })}
                canReset={hasFilters}
                resetLabel={t('reset')}
                onreset={reset}
            >
                <div class="vff-field wide">
                    <label class="vff-label" for="pj-q">{t('search_label')}</label>
                    <input
                        id="pj-q"
                        class="vff-input"
                        type="search"
                        autocomplete="off"
                        enterkeyhint="search"
                        placeholder={t('search_hint')}
                        bind:value={q}
                    />
                </div>
                <div class="vff-field">
                    <label class="vff-label" for="pj-state">{t('filter_state')}</label>
                    <select id="pj-state" class="vff-select" bind:value={stateFilter}>
                        <option value="">{t('all_states')}</option>
                        {#each data.states as s (s)}
                            <option value={s}>{t(`states.${s}`)} ({n(stateCounts[s])})</option>
                        {/each}
                    </select>
                </div>
                <div class="vff-field">
                    <label class="vff-label" for="pj-kind">{t('filter_kind')}</label>
                    <select id="pj-kind" class="vff-select" bind:value={kind}>
                        <option value="">{t('all_kinds')}</option>
                        {#each data.kinds as k (k)}
                            <option value={k}>{t(`kinds.${k}`)} ({n(kindCounts[k])})</option>
                        {/each}
                    </select>
                </div>
                <div class="vff-field">
                    <label class="vff-label" for="pj-region">{t('filter_region')}</label>
                    <select id="pj-region" class="vff-select" bind:value={region}>
                        <option value="">{t('all_regions')}</option>
                        {#each data.regions as rg (rg)}
                            <option value={rg}>{rg} ({n(regionCounts[rg])})</option>
                        {/each}
                    </select>
                </div>
            </DirectoryFilters>

            {#if filtered.length}
                <ul class="projects" aria-label={t('list_label')}>
                    {#each filtered as p (p.id)}
                        <li class="project vff-panel" id={p.id}>
                            <div class="head">
                                <h2>{p.name}</h2>
                                <p class="badges">
                                    <span
                                        class="vff-badge {p.state === 'suspended'
                                            ? 'warning'
                                            : 'accent'}"
                                        title={t(`state_help.${p.state}`)}
                                        >{t(`states.${p.state}`)}</span
                                    >
                                    <span class="vff-badge muted">{t(`kinds.${p.kind}`)}</span>
                                </p>
                            </div>

                            <p class="summary" lang="fr">{p.summary}</p>
                            {#if lang !== 'fr'}<p class="lang-note">
                                    {t('summary_lang_note')}
                                </p>{/if}

                            <p class="next">{nextLine(p)}</p>

                            <dl class="facts">
                                <div>
                                    <dt>{t('regions')}</dt>
                                    <dd>{p.regions.join(', ')}</dd>
                                </div>
                                <div>
                                    <dt>{t('porteurs')}</dt>
                                    <dd>
                                        {p.porteurs.map((x) => x.name).join(', ')}
                                    </dd>
                                </div>
                                {#if p.lines.length}
                                    <div>
                                        <dt>{t('lines')}</dt>
                                        <dd>
                                            <ul class="lines">
                                                {#each p.lines as l (l.slug)}
                                                    <li><a href="/lines/{l.slug}">{l.name}</a></li>
                                                {/each}
                                            </ul>
                                        </dd>
                                    </div>
                                {/if}
                            </dl>

                            <h3>{t('stages_title')}</h3>
                            {#if p.stages.length}
                                <ol class="stages">
                                    {#each p.stages as s, i (i)}
                                        <li class:planned={!s.done}>
                                            <span class="when">{day(s.date)}</span>
                                            <span class="what">
                                                <span class="stage-name"
                                                    >{t(`stages.${s.kind}`)}{#if s.note}
                                                        <span class="note">
                                                            ({s.note})</span
                                                        >{/if}</span
                                                >
                                                <span
                                                    class="vff-badge {s.done ? 'success' : 'muted'}"
                                                    >{s.done
                                                        ? t('stage_done')
                                                        : t('stage_planned')}</span
                                                >
                                                <a href={s.source.url} rel="noopener"
                                                    >{s.source.label}</a
                                                >{#if s.source.published}<span class="muted">
                                                        ({t('source_published', {
                                                            date: day(s.source.published),
                                                        })})</span
                                                    >{/if}
                                            </span>
                                        </li>
                                    {/each}
                                </ol>
                            {:else}
                                <p class="muted">{t('no_stages')}</p>
                            {/if}
                        </li>
                    {/each}
                </ul>
            {:else}
                <EmptyState icon="bi-search" title={t('empty_title')} text={t('empty_text')} />
            {/if}
        </div>

        <SectionBlock kind="alt" title={t('method_title')} maxWidth="1180px">
            <p class="method">{t('method_dates')}</p>
            <p class="method">{t('method_state')}</p>
            <p class="method">{t('method_sources')}</p>
            <p class="method">{t('method_scope')}</p>
            {#if checkedOn}<p class="method">{t('checked', { date: checkedOn })}</p>{/if}
            <p class="method"><a href="/api/v1/projects">{t('api_link')}</a></p>
        </SectionBlock>
    </main>
</Layout>

<style>
    main {
        background-color: var(--vff-bg);
    }
    .directory {
        display: flex;
        flex-direction: column;
        gap: var(--vff-block-space);
        padding-top: var(--vff-block-space);
        padding-bottom: var(--vff-block-space);
    }
    .projects {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-5);
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .project {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-3);
        min-width: 0;
    }
    .head {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        justify-content: space-between;
        gap: var(--vff-space-2) var(--vff-space-4);
    }
    h2 {
        margin: 0;
        font-family: var(--vff-font-display);
        font-size: var(--vff-fs-h3);
        line-height: 1.3;
        overflow-wrap: anywhere;
    }
    h3 {
        margin: var(--vff-space-2) 0 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-md);
        color: var(--vff-text-muted);
    }
    .badges {
        display: flex;
        flex-wrap: wrap;
        gap: var(--vff-space-2);
        margin: 0;
    }
    /* Le badge est nowrap par défaut : « Wiedereröffnung für den Personenverkehr » dépassait 320 px. */
    .badges :global(.vff-badge) {
        max-width: 100%;
        white-space: normal;
    }
    .summary,
    .next,
    .method {
        max-width: var(--vff-measure);
        margin: 0;
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-body);
        line-height: 1.6;
    }
    .next {
        font-weight: 600;
    }
    .lang-note {
        margin: 0;
        font-size: var(--vff-fs-xs);
        color: var(--vff-text-muted);
    }
    .method {
        margin-bottom: var(--vff-space-4);
    }
    .facts {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(16rem, 1fr));
        gap: var(--vff-space-3) var(--vff-space-5);
        margin: 0;
    }
    .facts dt {
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-xs);
        color: var(--vff-text-muted);
    }
    .facts dd {
        margin: 0;
        font-size: var(--vff-fs-sm);
        overflow-wrap: anywhere;
    }
    .lines {
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .lines a {
        display: inline-block;
        padding-block: 3px;
    }
    .stages {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-3);
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .stages li {
        display: grid;
        grid-template-columns: 11rem 1fr;
        gap: var(--vff-space-1) var(--vff-space-4);
        font-size: var(--vff-fs-sm);
    }
    .when {
        font-family: var(--vff-font-heading);
        font-weight: 600;
    }
    .planned .when {
        color: var(--vff-text-muted);
    }
    .what {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        gap: var(--vff-space-1) var(--vff-space-3);
        min-width: 0;
    }
    .stage-name {
        overflow-wrap: anywhere;
    }
    .note,
    .muted {
        color: var(--vff-text-muted);
    }
    .stage-name .note {
        margin-inline-start: 0.3em;
    }
    .what a {
        display: inline-block;
        padding-block: 3px;
        overflow-wrap: anywhere;
    }
    @media (max-width: 600px) {
        .stages li {
            grid-template-columns: 1fr;
        }
    }
</style>

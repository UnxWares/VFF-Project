<script>
    /**
     * « Ce jour-là » : pour un jour du calendrier (aujourd'hui par défaut), ce qui s'est passé ce jour-là les
     * années précédentes : lignes et gares ouvertes ou fermées, électrifications, mises en service, accidents,
     * chacun avec son année, sa source et sa fiche. Seules des dates connues au jour sont présentées. Les données
     * viennent de OnThisDay (cache par jour). Le choix d'une date est un formulaire qui marche sans JavaScript.
     */
    import { Link } from '@inertiajs/svelte';
    import Layout from '$components/Layout.svelte';
    import PageHero from '$components/PageHero.svelte';
    import SectionBlock from '$components/SectionBlock.svelte';
    import SeoHead from '$components/SeoHead.svelte';
    import { i18n } from '$lib/i18n.js';
    import { formatNumber } from '$lib/typo.js';
    import { dayLabel, daysInMonth, keyLabel, monthName, onThisDayHref } from '$lib/onThisDay.js';

    /** @type {{ day: { key: string, month: number, day: number, is_today: boolean, previous: string, next: string }, facts: any[], accidents: { shown: boolean, count: number }, neighbours: { previous: string|null, next: string|null }, coverage: { days: number, days_with_facts: number, facts: number }, today: string }} */
    let { day, facts, accidents, neighbours, coverage, today } = $props();

    /** @param {string} key @param {Record<string, string|number>} [vars] */
    const t = (key, vars) => String($i18n.t(`on_this_day.${key}`, vars));
    const lang = $derived($i18n.language ?? 'fr');
    const n = (v) => formatNumber(v, lang);
    const count = (key, value) => t(key, { count: value, value: n(value) });

    const label = $derived(dayLabel(day.month, day.day, lang));
    const withAccidents = $derived(accidents.shown);
    const href = (key) => onThisDayHref(key, { accidents: withAccidents });

    // Choix d'une date : le mois choisi décide des jours proposés (le jour reste dans le mois).
    let pickMonth = $state(1);
    let pickDay = $state(1);
    $effect.pre(() => {
        pickMonth = day.month;
        pickDay = day.day;
    });
    const pickDays = $derived(Array.from({ length: daysInMonth(pickMonth) }, (_, i) => i + 1));
    $effect(() => {
        if (pickDay > daysInMonth(pickMonth)) pickDay = daysInMonth(pickMonth);
    });

    /** Libellé d'un fait : sa nature, et le trafic quand il précise la fermeture ou l'ouverture. */
    function kindLabel(f) {
        const base = t(`kinds.${f.subject}.${f.kind}`);
        return f.traffic ? `${base} ${t(`traffic.${f.traffic}`)}` : base;
    }

    const yearsAgo = (f) => (f.years_ago === 0 ? t('this_year') : count('years_ago', f.years_ago));
</script>

<SeoHead
    title={t('meta_title')}
    description={t('meta_description')}
    path={`/on-this-day/${day.key}`}
/>
<svelte:head>
    <title>{t('meta_title')}, {label} - VFF</title>
</svelte:head>

<Layout compactHeader>
    <main>
        <PageHero title={t('hero_title')} subtitle={t('hero_subtitle')} />

        <SectionBlock title={label} maxWidth="1180px">
            <p class="today">
                {#if day.is_today}
                    <span class="vff-badge muted">{t('today_badge')}</span>
                {/if}
                <span class="count">{count('count', facts.length)}</span>
            </p>

            <nav class="day-nav" aria-label={t('nav_label')}>
                <Link href={href(day.previous)} class="vff-btn secondary" preserveScroll>
                    <span aria-hidden="true">&larr;</span>
                    {t('prev_day', { date: keyLabel(day.previous, lang) })}
                </Link>
                <Link href={href(day.next)} class="vff-btn secondary" preserveScroll>
                    {t('next_day', { date: keyLabel(day.next, lang) })}
                    <span aria-hidden="true">&rarr;</span>
                </Link>
                {#if !day.is_today}
                    <Link href={href(today)} class="vff-btn secondary" preserveScroll
                        >{t('go_today')}</Link
                    >
                {/if}
            </nav>

            <form class="pick" method="get" action="/on-this-day">
                <fieldset>
                    <legend>{t('pick_legend')}</legend>
                    <div class="row">
                        <div class="vff-field">
                            <label class="vff-label" for="otd-day">{t('day_label')}</label>
                            <select id="otd-day" name="day" class="vff-select" bind:value={pickDay}>
                                {#each pickDays as d (d)}
                                    <option value={d}>{d}</option>
                                {/each}
                            </select>
                        </div>
                        <div class="vff-field">
                            <label class="vff-label" for="otd-month">{t('month_label')}</label>
                            <select
                                id="otd-month"
                                name="month"
                                class="vff-select"
                                bind:value={pickMonth}
                            >
                                {#each Array.from({ length: 12 }, (_, i) => i + 1) as m (m)}
                                    <option value={m}>{monthName(m, lang)}</option>
                                {/each}
                            </select>
                        </div>
                        {#if !withAccidents}
                            <input type="hidden" name="accidents" value="0" />
                        {/if}
                        <button type="submit" class="vff-btn primary">{t('go')}</button>
                    </div>
                </fieldset>
            </form>
        </SectionBlock>

        <SectionBlock
            kind="alt"
            title={t('facts_title', { date: label })}
            maxWidth="1180px"
            id="facts"
        >
            {#if facts.length === 0}
                <p class="empty">{t('empty', { date: label })}</p>
                <p class="hint">{t('empty_hint')}</p>
                <p class="nearest">
                    {#if neighbours.previous}
                        <Link href={href(neighbours.previous)} class="vff-link"
                            >{t('nearest_prev', {
                                date: keyLabel(neighbours.previous, lang),
                            })}</Link
                        >
                    {/if}
                    {#if neighbours.next}
                        <Link href={href(neighbours.next)} class="vff-link"
                            >{t('nearest_next', { date: keyLabel(neighbours.next, lang) })}</Link
                        >
                    {/if}
                </p>
            {:else}
                <ol class="facts">
                    {#each facts as f (f.id)}
                        <li class="fact">
                            <div class="when">
                                <span class="year">{f.year}</span>
                                <span class="ago">{yearsAgo(f)}</span>
                            </div>
                            <div class="what">
                                <p class="kind">{kindLabel(f)}</p>
                                <p class="head">
                                    {#if f.path}
                                        <Link href={f.path} class="vff-link">{f.title}</Link>
                                    {:else}
                                        {f.title}
                                    {/if}
                                </p>
                                {#if f.system || f.detail}
                                    <p class="detail">
                                        {[f.system, f.detail].filter(Boolean).join(', ')}
                                    </p>
                                {/if}
                                {#if f.text}
                                    <p class="detail">{f.text}</p>
                                {/if}
                                {#if f.deaths !== null}
                                    <p class="detail">{count('deaths', f.deaths)}</p>
                                {/if}
                                {#if f.sources.length > 0}
                                    <p class="src">
                                        {count('source', f.sources.length)}
                                        {#each f.sources as s, i (s.provider)}
                                            <span class="s"
                                                >{#if s.url}<a
                                                        href={s.url}
                                                        class="vff-link"
                                                        rel="noopener external"
                                                        >{s.name}<span class="vff-sr-only">
                                                            {t('external')}</span
                                                        ></a
                                                    >{:else}{s.name}{/if}{i < f.sources.length - 1
                                                    ? ','
                                                    : ''}</span
                                            >{/each}
                                    </p>
                                {/if}
                            </div>
                        </li>
                    {/each}
                </ol>
            {/if}

            {#if accidents.count > 0 || !withAccidents}
                <p class="note accidents">
                    {t('accidents_note')}
                    {#if !withAccidents}
                        {count('accidents_hidden', accidents.count)}
                    {/if}
                    <Link
                        href={onThisDayHref(day.key, { accidents: !withAccidents })}
                        class="vff-link"
                        preserveScroll
                        >{withAccidents ? t('accidents_hide') : t('accidents_show')}</Link
                    >
                </p>
            {/if}
        </SectionBlock>

        <SectionBlock
            title={t('method_title')}
            intro={t('coverage', {
                with: n(coverage.days_with_facts),
                days: n(coverage.days),
                facts: n(coverage.facts),
            })}
            maxWidth="900px"
        >
            <ul class="method">
                {#each ['day', 'sources', 'stations', 'accidents', 'cache'] as m (m)}
                    <li>{t(`method.${m}`)}</li>
                {/each}
            </ul>
            <p class="note">
                {t('api_text')}
                <a href="/api/v1/on-this-day/{day.key}" class="vff-link"
                    >/api/v1/on-this-day/{day.key}</a
                >
            </p>
            <p class="note">
                <Link href="/chronology" class="vff-link">{t('link_chronology')}</Link>
            </p>
            <p class="note">
                <Link href="/network" class="vff-link">{t('link_network')}</Link>
            </p>
        </SectionBlock>
    </main>
</Layout>

<style>
    .today {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: var(--vff-space-3);
        margin: 0 0 var(--vff-space-4);
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-body);
        color: var(--vff-text-muted);
    }
    .day-nav {
        display: flex;
        flex-wrap: wrap;
        gap: var(--vff-space-3);
        margin: 0 0 var(--vff-space-6);
    }
    .pick fieldset {
        min-width: 0;
        margin: 0;
        padding: 0;
        border: 0;
    }
    .pick legend {
        margin-bottom: var(--vff-space-2);
        padding: 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-md);
        font-weight: 600;
        color: var(--vff-text);
    }
    .row {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        gap: var(--vff-space-4);
    }
    .row .vff-field {
        min-width: min(100%, 160px);
        margin: 0;
    }
    .empty,
    .hint,
    .nearest,
    .note {
        max-width: var(--vff-measure);
        margin: 0 0 var(--vff-space-4);
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-body);
        line-height: 1.6;
        color: var(--vff-text-muted);
    }
    .empty {
        color: var(--vff-text);
    }
    .nearest {
        display: flex;
        flex-wrap: wrap;
        gap: var(--vff-space-2) var(--vff-space-5);
    }
    .accidents {
        margin-top: var(--vff-space-5);
    }
    .facts {
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .fact {
        display: grid;
        grid-template-columns: 9.5em minmax(0, 1fr);
        gap: var(--vff-space-3);
        padding-block: var(--vff-space-4);
        border-top: 1px solid var(--vff-border);
    }
    .fact:first-child {
        border-top: 0;
        padding-top: 0;
    }
    .when {
        display: flex;
        flex-direction: column;
        gap: 2px;
        font-family: var(--vff-font-body);
        font-variant-numeric: tabular-nums;
    }
    .year {
        font-family: var(--vff-font-display);
        font-size: var(--vff-fs-h2);
        font-weight: 700;
        line-height: 1.1;
        color: var(--vff-text);
    }
    .ago {
        font-size: var(--vff-fs-md);
        color: var(--vff-text-muted);
    }
    .what p {
        margin: 0 0 4px;
        font-family: var(--vff-font-body);
        line-height: 1.55;
    }
    .kind {
        font-size: var(--vff-fs-md);
        color: var(--vff-text-muted);
    }
    .head {
        font-size: var(--vff-fs-body);
        font-weight: 600;
        overflow-wrap: anywhere;
    }
    .detail {
        font-size: var(--vff-fs-md);
        color: var(--vff-text);
    }
    .src {
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
    /* Un nom de source et sa virgule restent ensemble ; les noms passent à la ligne entre eux. */
    .src .s {
        display: inline-block;
        margin-inline-end: 0.3em;
    }
    .method {
        max-width: var(--vff-measure);
        margin: 0 0 var(--vff-space-4);
        padding-left: 1.2em;
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-body);
        line-height: 1.6;
    }
    .method li {
        margin-bottom: var(--vff-space-2);
        text-wrap: pretty;
    }
    @media (max-width: 600px) {
        .fact {
            grid-template-columns: minmax(0, 1fr);
            gap: var(--vff-space-2);
        }
        .when {
            flex-direction: row;
            align-items: baseline;
            gap: var(--vff-space-3);
        }
    }
</style>

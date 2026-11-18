<script>
    import { formatStationDate } from '$lib/stationDates.js';
    import Layout from '$components/Layout.svelte';
    import StationEraStrip from '$components/Stations/StationEraStrip.svelte';
    import StationMiniMap from '$components/Stations/StationMiniMap.svelte';
    import SheetHero from '$components/Sheet/SheetHero.svelte';
    import SheetSection from '$components/Sheet/SheetSection.svelte';
    import StatStrip from '$components/Sheet/StatStrip.svelte';
    import {
        STATION_STATUS_COLOR,
        STATION_STATUS_ICON,
    } from '$components/Stations/stationStatus.js';
    import { i18n } from '$lib/i18n.js';

    let { station, eras = [], events = [], lines = [], sources = [], neighbors = [] } = $props();

    const EVENT_ICON = {
        opening: 'bi-door-open-fill',
        reopening: 'bi-arrow-repeat',
        closure_passenger: 'bi-person-x-fill',
        closure_freight: 'bi-box-seam',
        closure: 'bi-door-closed-fill',
        demolition: 'bi-bricks',
    };

    /** Date selon sa précision (jour, mois, année, décennie, après / avant, intervalle « entre 1951 et 1960 »). */
    function formatDate(iso, precision = 'day', after = null) {
        return formatStationDate(
            iso,
            precision,
            after,
            (key, opts) => String($i18n.t(key, opts)),
            $i18n.language ?? 'fr',
        );
    }

    /** [1900, 1910, 1920, 1980] → « 1900-1920, 1980 ». */
    function eraRanges(years) {
        const out = [];
        let start = null;
        let prev = null;
        for (const y of years) {
            if (start === null) {
                start = prev = y;
            } else if (y === prev + 10) {
                prev = y;
            } else {
                out.push(start === prev ? `${start}` : `${start}-${prev}`);
                start = prev = y;
            }
        }
        if (start !== null) out.push(start === prev ? `${start}` : `${start}-${prev}`);

        return out.join(', ');
    }

    const sourceName = (s) => $i18n.t(`stations.sources.names.${s}`, { defaultValue: s });
    const openedInferred = $derived((station.provenance?.opened_at ?? '').startsWith('inferred'));
    const statusColor = $derived(
        STATION_STATUS_COLOR[station.status] ?? STATION_STATUS_COLOR.declassified,
    );
    const lang = $derived($i18n.language ?? 'fr');
    const statItems = $derived.by(() => {
        const t = (k, o) => String($i18n.t(k, o));
        const items = [
            { value: lines.length.toLocaleString(lang), label: t('stations.stats.lines') },
            { value: sources.length.toLocaleString(lang), label: t('stations.stats.sources') },
        ];
        if (station.opened_at)
            items.push({
                value: formatDate(station.opened_at, station.opened_precision),
                label: t('stations.stats.opened'),
                note: openedInferred ? `(${t('stations.inferred_short')})` : undefined,
                noteHint: openedInferred ? t('stations.events.inferred_hint') : undefined,
            });
        if (station.closed_at)
            items.push({
                value: formatDate(
                    station.closed_at,
                    station.closed_precision,
                    station.closed_after,
                ),
                label: t('stations.stats.closed'),
            });
        if (station.uic_code)
            items.push({ value: station.uic_code, label: t('stations.stats.uic') });
        return items;
    });
    /** « Bagnoles-de-l'Orne (61) » : commune et département. */
    const place = $derived(
        station.city
            ? `${station.city}${station.department_code ? ` (${station.department_code})` : ''}`
            : null,
    );

    const provenanceRows = $derived(
        Object.entries(station.provenance ?? {}).filter(([k]) =>
            [
                'name',
                'geom',
                'opened_at',
                'closed_at',
                'status',
                'type',
                'uic_code',
                'wikidata_id',
            ].includes(k),
        ),
    );
</script>

<svelte:head>
    <title>{station.name} - VFF</title>
    <meta name="description" content={$i18n.t('stations.meta_show', { name: station.name })} />
</svelte:head>

<Layout compactHeader>
    <main>
        <SheetHero
            crumbs={[
                {
                    href: '/stations',
                    label: $i18n.t('stations.back_to_list'),
                    icon: 'bi-arrow-left',
                },
                { href: '/maps', label: $i18n.t('stations.back_to_map'), icon: 'bi-map' },
            ]}
            crumbsLabel={$i18n.t('stations.breadcrumbs')}
            plate={$i18n.t(`stations.types.${station.type}`)}
            plateIcon="bi-pin-map-fill"
            eyebrow={place ? [place] : []}
            title={station.name}
            stats={heroStats}
        >
            <p class="status" style="--status: {statusColor}">
                <i
                    class="bi {STATION_STATUS_ICON[station.status] ?? 'bi-circle'}"
                    aria-hidden="true"
                ></i>
                {$i18n.t(`stations.statuses.${station.status}`)}
            </p>
            {#if station.alt_names?.length}
                <p class="alt-names">
                    {$i18n.t('stations.also_known_as_label')}
                    {#each station.alt_names as a, i (i)}
                        <span
                            >{a.name}{#if a.from || a.to}&nbsp;({a.from ?? '…'}-{a.to ??
                                    '…'}){/if}</span
                        >{i < station.alt_names.length - 1 ? ', ' : ''}
                    {/each}
                </p>
            {/if}
        </SheetHero>

        {#snippet heroStats()}
            <StatStrip items={statItems} label={$i18n.t('stations.stats.title')} />
        {/snippet}

        <SheetSection
            id="eras"
            title={$i18n.t('stations.sections.eras')}
            intro={$i18n.t('stations.sections.eras_hint')}
        >
            <StationEraStrip {eras} />
        </SheetSection>

        <SheetSection>
            <div class="two-col">
                <div>
                    <h2>{$i18n.t('stations.sections.history')}</h2>
                    {#if events.length}
                        <ol class="timeline">
                            {#each events as e, i (i)}
                                <li class:inferred={e.inferred}>
                                    <i class="bi {EVENT_ICON[e.type] ?? 'bi-dot'}"></i>
                                    <div>
                                        <strong>{formatDate(e.date, e.precision)}</strong>
                                        <span class="ev-type"
                                            >{$i18n.t(`stations.events.${e.type}`)}</span
                                        >
                                        {#if e.note}<p class="ev-note" lang={e.note_lang}>
                                                {e.note}
                                            </p>{/if}
                                        <small class="ev-source">
                                            {#if e.inferred}
                                                <abbr
                                                    title={$i18n.t('stations.events.inferred_hint')}
                                                    >{$i18n.t('stations.events.inferred')}</abbr
                                                > ·
                                            {/if}
                                            {#if e.source_url}
                                                <a
                                                    href={e.source_url}
                                                    target="_blank"
                                                    rel="noopener">{sourceName(e.source)}</a
                                                >
                                            {:else}
                                                {sourceName(e.source)}
                                            {/if}
                                        </small>
                                    </div>
                                </li>
                            {/each}
                        </ol>
                    {:else}
                        <p class="empty">{$i18n.t('stations.events.none')}</p>
                    {/if}
                </div>
                <div>
                    <h2>{$i18n.t('stations.sections.map')}</h2>
                    <StationMiniMap {station} {neighbors} height="360px" />
                </div>
            </div>
        </SheetSection>

        <SheetSection title={$i18n.t('stations.sections.lines')} id="lines" count={lines.length}>
            {#if lines.length}
                <ul class="lines-list">
                    {#each lines as l (l.slug)}
                        <li>
                            <a
                                class="line-card"
                                href="/lines/{l.slug}"
                                class:historical={l.last_year}
                            >
                                <i class="bi bi-bezier"></i>
                                <div>
                                    <strong>{l.name}</strong>
                                    <div class="line-meta">
                                        {#if l.code}<span class="code">{l.code}</span>{/if}
                                        {#if l.pk_m !== null}
                                            <span
                                                title={$i18n.t(
                                                    `stations.lines.pk_sources.${l.pk_source}`,
                                                    { defaultValue: l.pk_source ?? '' },
                                                )}
                                            >
                                                {$i18n.t('stations.lines.pk', {
                                                    pk: (l.pk_m / 1000)
                                                        .toFixed(3)
                                                        .replace('.', ','),
                                                })}
                                            </span>
                                        {/if}
                                        {#if l.first_year && l.last_year}
                                            <span class="badge"
                                                >{$i18n.t('stations.lines.period_from_to', {
                                                    from: l.first_year,
                                                    to: l.last_year,
                                                })}</span
                                            >
                                        {:else if l.first_year}
                                            <span class="badge"
                                                >{$i18n.t('stations.lines.period_from', {
                                                    from: l.first_year,
                                                })}</span
                                            >
                                        {:else if l.last_year}
                                            <span class="badge"
                                                >{$i18n.t('stations.lines.period_to', {
                                                    to: l.last_year,
                                                })}</span
                                            >
                                        {/if}
                                    </div>
                                    {#if l.era_years.length}
                                        <small class="eras-served"
                                            >{$i18n.t('stations.lines.stop_in_eras', {
                                                list: eraRanges(l.era_years),
                                            })}</small
                                        >
                                    {/if}
                                    <small class="method"
                                        >{$i18n.t(`stations.lines.methods.${l.method}`, {
                                            defaultValue: l.method,
                                        })}</small
                                    >
                                </div>
                                <i class="bi bi-arrow-right end"></i>
                            </a>
                        </li>
                    {/each}
                </ul>
            {:else}
                <p class="empty">{$i18n.t('stations.lines.none')}</p>
            {/if}
        </SheetSection>

        <SheetSection title={$i18n.t('stations.sections.sources')} id="sources">
            {#if station.position_flagged}
                <p class="warning">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    {$i18n.t('stations.sources.position_flag', { m: station.position_spread_m })}
                    {$i18n.t('stations.sources.position_from', {
                        source: sourceName(station.position_source),
                    })}
                </p>
            {/if}
            <ul class="sources">
                {#each sources as s, i (i)}
                    <li>
                        <div class="src-head">
                            <strong>{sourceName(s.source)}</strong>
                            {#if $i18n.exists(`stations.sources.licences.${s.source}`)}
                                <span class="licence"
                                    >{$i18n.t(`stations.sources.licences.${s.source}`)}</span
                                >
                            {/if}
                            {#if s.url}<a
                                    href={s.url}
                                    target="_blank"
                                    rel="noopener"
                                    class="src-link"
                                    >{s.ref} <i class="bi bi-box-arrow-up-right"></i></a
                                >{/if}
                        </div>
                        <div class="src-meta">
                            <span class="src-name">{s.name}</span>
                            {#if s.lifecycle && s.lifecycle !== 'unknown'}<span
                                    >· {$i18n.t(`stations.sources.lifecycle.${s.lifecycle}`)}</span
                                >{/if}
                            {#if s.opened_on}<span
                                    >· {$i18n.t('stations.opened_in', {
                                        year: s.opened_on.slice(0, 4),
                                    })}</span
                                >{/if}
                            {#if s.closed_on}<span
                                    >· {$i18n.t('stations.closed_in', {
                                        year: s.closed_on.slice(0, 4),
                                    })}</span
                                >{/if}
                            {#if s.geocoding === 'commune' && s.precision_m}<span
                                    >· {$i18n.t('stations.sources.geocoded_by_commune', {
                                        m: s.precision_m,
                                    })}</span
                                >
                            {:else if s.geocoding && s.geocoding !== 'station' && s.geocoding !== 'name_match' && s.pk_m}<span
                                    >· {s.precision_m
                                        ? $i18n.t('stations.sources.geocoded_by_pk_precision', {
                                              pk: (s.pk_m / 1000).toFixed(1).replace('.', ','),
                                              m: s.precision_m,
                                          })
                                        : $i18n.t('stations.sources.geocoded_by_pk', {
                                              pk: (s.pk_m / 1000).toFixed(1).replace('.', ','),
                                          })}</span
                                >{/if}
                        </div>
                        <div class="src-meta">
                            <span
                                >{$i18n.t(`stations.sources.match.${s.match_method}`, {
                                    defaultValue: s.match_method ?? '',
                                })}</span
                            >
                            {#if s.distance_m > 0}<span class:far={s.distance_m > 100}
                                    >· {$i18n.t('stations.sources.distance', {
                                        m: s.distance_m,
                                    })}</span
                                >{/if}
                        </div>
                    </li>
                {/each}
            </ul>
            {#if provenanceRows.length}
                <h3 class="sub">{$i18n.t('stations.sources.provenance')}</h3>
                <dl class="provenance">
                    {#each provenanceRows as [attr, src] (attr)}
                        <dt>{$i18n.t(`stations.sources.attributes.${attr}`)}</dt>
                        <dd>
                            {sourceName(src)}
                            {#if attr === 'geom' && station.provenance?.geom_precision_m && $i18n.exists(`stations.sources.geom_precision.${station.provenance?.geom_method}`)}
                                <span class="precision"
                                    >({$i18n.t(
                                        `stations.sources.geom_precision.${station.provenance.geom_method}`,
                                        { m: station.provenance.geom_precision_m },
                                    )})</span
                                >
                            {/if}
                        </dd>
                    {/each}
                </dl>
            {/if}
        </SheetSection>

        {#if station.description}
            <SheetSection title={$i18n.t('stations.sections.about')} id="about">
                <p class="description">{station.description}</p>
            </SheetSection>
        {/if}

        {#if neighbors.length}
            <SheetSection title={$i18n.t('stations.sections.neighbors')} id="neighbors">
                <div class="neighbor-grid">
                    {#each neighbors as n (n.slug)}
                        <a class="neighbor-card" href="/stations/{n.slug}">
                            <i
                                class="dot"
                                style="--dot: {STATION_STATUS_COLOR[n.status] ??
                                    STATION_STATUS_COLOR.declassified}"
                            ></i>
                            <div>
                                <strong>{n.name}</strong>
                                {#if n.city}<span>{n.city}</span>{/if}
                                <small
                                    >{$i18n.t('stations.neighbors_distance', {
                                        km: (n.distance_m / 1000).toFixed(1),
                                    })}</small
                                >
                            </div>
                        </a>
                    {/each}
                </div>
            </SheetSection>
        {/if}
    </main>
</Layout>

<style>
    main {
        background-color: var(--vff-bg);
    }

    /* En-tête (SheetHero) : statut et autres noms */
    .status {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin: 6px 0 0;
        font-family: var(--vff-font-heading);
        font-size: 15px;
        font-weight: 600;
        color: var(--vff-text);
    }
    .status i {
        display: grid;
        place-items: center;
        width: 26px;
        height: 26px;
        border-radius: 50%;
        border: 2px solid var(--status);
        color: var(--status);
        font-size: 13px;
        background: var(--vff-bg);
    }
    .alt-names {
        margin: 10px 0 0;
        font-size: 15px;
        line-height: 1.5;
        color: var(--vff-text-muted);
    }
    .alt-names span {
        color: var(--vff-text);
    }
    abbr {
        text-decoration: none;
        cursor: help;
        font-weight: 400;
        color: var(--vff-text-muted);
        font-size: 12px;
    }
    .two-col h2 {
        margin: 0 0 16px;
        font-family: var(--vff-font-display);
        font-size: 28px;
        line-height: 1.2;
        color: var(--vff-text);
    }
    .two-col h2:after {
        display: none;
    }
    .sub {
        margin: 28px 0 10px;
        font-family: var(--vff-font-heading);
        font-size: 18px;
        font-weight: 600;
    }

    .two-col {
        display: grid;
        grid-template-columns: 1fr 1.1fr;
        gap: 28px;
    }

    .timeline {
        list-style: none;
        margin: 0;
        padding: 0 0 0 4px;
        border-left: 2px solid var(--vff-border);
    }
    .timeline li {
        position: relative;
        display: flex;
        gap: 12px;
        padding: 0 0 18px 18px;
    }
    .timeline li > i {
        position: absolute;
        left: -13px;
        top: 0;
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background-color: var(--vff-bg-elevated);
        border: 2px solid var(--vff-accent);
        color: var(--vff-accent);
        font-size: 12px;
    }
    .timeline li.inferred > i {
        border-style: dashed;
        color: var(--vff-text-muted);
        border-color: var(--vff-text-muted);
    }
    .timeline strong {
        font-family: var(--vff-font-display);
        font-size: 15px;
        margin-right: 8px;
    }
    .ev-type {
        font-size: 14px;
    }
    .ev-note {
        margin: 4px 0 0;
        font-size: 13px;
        color: var(--vff-text);
    }
    .ev-source {
        display: block;
        margin-top: 2px;
        font-size: 12px;
        color: var(--vff-text-muted);
    }
    @media (max-width: 768px) {
        .ev-source a {
            display: inline-block;
            padding: 5px 0;
            margin: -5px 0;
        }
    }
    .ev-source a {
        color: var(--vff-accent);
    }

    .lines-list li,
    .sources li {
        text-align: left;
        font-size: 14px;
    }
    .lines-list {
        list-style: none;
        padding: 0;
        margin: 0;
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 10px;
    }
    .line-card {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 14px;
        background-color: var(--vff-bg-elevated);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        text-decoration: none;
        color: var(--vff-text);
        transition: all var(--vff-transition);
        height: 100%;
        box-sizing: border-box;
    }
    .line-card:hover {
        border-color: var(--vff-accent);
        transform: translateX(2px);
    }
    .line-card.historical {
        border-style: dashed;
    }
    .line-card > i:first-child {
        font-size: 18px;
        color: var(--vff-accent);
        flex-shrink: 0;
    }
    .line-card div {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 3px;
        min-width: 0;
        text-align: left;
    }
    .line-card strong {
        font-family: var(--vff-font-display);
        font-size: 15px;
    }
    .line-meta {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        font-size: 12px;
        color: var(--vff-text-muted);
    }
    .code {
        font-family: var(--vff-font-secondary, monospace);
        color: var(--vff-accent);
    }
    .badge {
        font-family: var(--vff-font-display);
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding: 2px 8px;
        border-radius: var(--vff-radius-pill);
        background-color: rgba(0, 0, 0, 0.07);
    }
    .eras-served,
    .method {
        font-size: 12px;
        color: var(--vff-text-muted);
    }
    .line-card .end {
        color: var(--vff-text-muted);
    }

    .warning {
        display: flex;
        gap: 8px;
        align-items: flex-start;
        padding: 10px 14px;
        border-radius: var(--vff-radius-md);
        background-color: color-mix(in srgb, #f0a500 14%, transparent);
        color: var(--vff-text);
        font-size: 14px;
    }
    .warning i {
        color: #d98a00;
        margin-top: 2px;
    }
    .sources {
        list-style: none;
        padding: 0;
        margin: 0;
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 10px;
    }
    .sources li {
        padding: 12px 14px;
        background-color: var(--vff-bg-elevated);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .src-head {
        display: flex;
        gap: 8px;
        align-items: baseline;
        flex-wrap: wrap;
    }
    .src-head strong {
        font-family: var(--vff-font-display);
        font-size: 14px;
    }
    .licence {
        font-size: 11px;
        color: var(--vff-text-muted);
    }
    .src-link {
        margin-left: auto;
        font-size: 12px;
        color: var(--vff-accent);
        font-family: var(--vff-font-secondary, monospace);
    }
    .src-meta {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
        font-size: 12px;
        color: var(--vff-text-muted);
    }
    .src-name {
        font-style: italic;
        color: var(--vff-text);
    }
    .src-meta .far {
        color: #c06a00;
        font-weight: 600;
    }
    .provenance {
        display: grid;
        grid-template-columns: max-content 1fr;
        gap: 4px 16px;
        margin: 0;
        font-size: 14px;
    }
    .provenance dt {
        color: var(--vff-text-muted);
    }
    .provenance dd {
        margin: 0;
    }
    .provenance .precision {
        color: var(--vff-text-muted);
    }

    .empty {
        font-style: italic;
        color: var(--vff-text-muted);
    }
    .description {
        font-size: 16px;
        line-height: 1.65;
    }

    .neighbor-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 12px;
    }
    .neighbor-card {
        display: flex;
        gap: 12px;
        align-items: flex-start;
        padding: 12px 14px;
        background-color: var(--vff-bg-elevated);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        text-decoration: none;
        color: var(--vff-text);
        transition: all var(--vff-transition);
    }
    .neighbor-card:hover {
        border-color: var(--vff-accent);
        transform: translateY(-2px);
    }
    .neighbor-card div {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }
    .neighbor-card strong {
        font-family: var(--vff-font-display);
        font-size: 14px;
    }
    .neighbor-card span {
        color: var(--vff-text-muted);
        font-size: 12px;
    }
    .neighbor-card small {
        color: var(--vff-accent);
        font-family: var(--vff-font-display);
        font-size: 11px;
    }
    .dot {
        display: inline-block;
        width: 12px;
        height: 12px;
        margin-top: 3px;
        border-radius: 50%;
        border: 3px solid var(--dot);
        background-color: var(--vff-bg-elevated);
        flex-shrink: 0;
    }

    @media (max-width: 800px) {
        .two-col {
            grid-template-columns: 1fr;
        }
        section {
            padding: 32px 16px;
        }
        .stats-row {
            margin: -30px 16px 0;
            gap: 18px;
        }
        .lines-list,
        .sources {
            grid-template-columns: 1fr;
        }
    }
</style>

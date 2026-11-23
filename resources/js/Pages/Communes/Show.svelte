<script>
    import { formatStationDate, formatStationOpening } from '$lib/stationDates.js';
    import { formatNumber } from '$lib/typo.js';
    import Layout from '$components/Layout.svelte';
    import SheetHero from '$components/Sheet/SheetHero.svelte';
    import SheetSection from '$components/Sheet/SheetSection.svelte';
    import SheetToc from '$components/Sheet/SheetToc.svelte';
    import StatStrip from '$components/Sheet/StatStrip.svelte';
    import CommuneChronology from '$components/Communes/CommuneChronology.svelte';
    import CommuneMap from '$components/Communes/CommuneMap.svelte';
    import EmptyState from '$components/ui/EmptyState.svelte';
    import SeoHead from '$components/SeoHead.svelte';
    import { breadcrumbSchema, communeSchema } from '$lib/structuredData.js';
    import {
        STATION_STATUS_COLOR,
        STATION_STATUS_ICON,
    } from '$components/Stations/stationStatus.js';
    import { CHRONOLOGY_LIMIT } from '$lib/communeSheet.js';
    import { i18n } from '$lib/i18n.js';

    /** Props de CommuneController::show (App\Services\Communes\CommuneSheet). */
    let {
        commune,
        current_era,
        counts,
        stations = [],
        lines = [],
        chronology = [],
        neighbors = [],
        // Carte : prop différée (CommuneController::show), absente au premier affichage.
        map,
    } = $props();

    const lang = $derived($i18n.language ?? 'fr');
    const t = (key, opts) => String($i18n.t(key, opts));
    const NBSP = ' ';
    /** Espace insécable entre un nombre et son unité (« 5,2 km »). */
    const nb = (s) => s.replace(/(\d) (?=km(?![\p{L}]))/gu, `$1${NBSP}`);

    const title = $derived(t('communes.title', { name: commune.name }));
    /** « Charente (16) » : département et code, ou le code seul. */
    const department = $derived(
        commune.department_name
            ? `${commune.department_name} (${commune.department_code})`
            : (commune.department_code ?? ''),
    );
    const description = $derived(t('communes.meta', { name: commune.name, department }));
    const eyebrow = $derived(
        [
            department,
            commune.population
                ? t('communes.population', { count: formatNumber(commune.population, lang) })
                : null,
        ].filter(Boolean),
    );

    const TOC = ['stations', 'lines', 'history', 'neighbors'];
    const tocItems = $derived(
        TOC.filter((id) => id !== 'neighbors' || neighbors.length).map((id) => ({
            id,
            label: t(`communes.toc.${id}`),
        })),
    );

    const statItems = $derived([
        { value: formatNumber(counts.stations, lang), label: t('communes.stats.stations') },
        {
            value: formatNumber(counts.stations_open, lang),
            label: t('communes.stats.stations_open'),
        },
        { value: formatNumber(counts.lines, lang), label: t('communes.stats.lines') },
        { value: formatNumber(counts.lines_open, lang), label: t('communes.stats.lines_open') },
    ]);

    const formatDate = (d) => formatStationDate(d.date, d.precision, null, t, lang);

    /** Dates d'une gare : « ouverte en 1852 », « fermée 31 décembre 2011 ». */
    function stationDates(s) {
        const parts = [];
        if (s.opened_at) {
            parts.push(
                t('communes.stations.opened', {
                    date: formatStationOpening(
                        s.opened_at,
                        s.opened_precision,
                        s.opened_after,
                        t,
                        lang,
                    ),
                }),
            );
        }
        if (s.closed_at) {
            parts.push(
                t('communes.stations.closed', {
                    date: formatStationDate(
                        s.closed_at,
                        s.closed_precision,
                        s.closed_after,
                        t,
                        lang,
                    ),
                }),
            );
        }
        return parts.length ? parts.join(', ') : t('communes.stations.dates_unknown');
    }

    /** Ligne : ses dates d'ouverture, de fermeture et d'électrification (celles des événements de sa section ici). */
    function lineFacts(l) {
        const facts = [];
        if (l.opened) facts.push(t('communes.lines.opened', { date: formatDate(l.opened) }));
        if (l.closed) {
            const key = l.closed.scope === 'all' ? 'closed' : `closed_${l.closed.scope}`;
            facts.push(t(`communes.lines.${key}`, { date: formatDate(l.closed) }));
        }
        if (l.electrified)
            facts.push(t('communes.lines.electrified', { date: formatDate(l.electrified) }));
        return facts;
    }

    /** Période en service sur les cartes décennales (la dernière, 2030, se lit « depuis »). */
    function linePeriod(l) {
        if (l.first_year == null) return null;
        if (l.last_year === current_era) {
            return l.first_year === current_era
                ? null
                : t('communes.lines.period_since', { from: l.first_year });
        }
        return l.first_year === l.last_year
            ? t('communes.lines.period_single', { year: l.first_year })
            : t('communes.lines.period', { from: l.first_year, to: l.last_year });
    }

    const lineLength = (l) =>
        nb(
            t(l.km_year === current_era ? 'communes.lines.km' : 'communes.lines.km_past', {
                km: formatNumber(l.km, lang, {
                    minimumFractionDigits: 1,
                    maximumFractionDigits: 1,
                }),
                year: l.km_year,
            }),
        );
</script>

<svelte:head>
    <title>{title} - VFF</title>
</svelte:head>

<SeoHead
    {title}
    {description}
    path={`/communes/${commune.slug}`}
    type="article"
    jsonLd={(origin) => [
        communeSchema({ origin, commune, stations }),
        breadcrumbSchema(origin, [
            { name: $i18n.t('common.app_full'), path: '/' },
            { name: commune.name, path: `/communes/${commune.slug}` },
        ]),
    ]}
/>

{#snippet statusMark(status)}
    <span
        class="mark"
        style:--status={STATION_STATUS_COLOR[status] ?? STATION_STATUS_COLOR.declassified}
        aria-hidden="true"
    >
        <i class="bi {STATION_STATUS_ICON[status] ?? 'bi-circle'}"></i>
    </span>
{/snippet}

<Layout compactHeader>
    <main>
        <SheetHero
            crumbs={[{ href: '/maps', label: t('communes.back_to_map'), icon: 'bi-map' }]}
            crumbsLabel={t('communes.breadcrumbs')}
            plate={t('communes.plate')}
            plateIcon="bi-geo-alt-fill"
            {eyebrow}
            {title}
            stats={heroStats}
        >
            {#if commune.department_code && commune.department_name}
                <p class="former">
                    <a class="vff-link" href="/departments/{commune.department_code}"
                        >{t('departments.commune_link', { name: commune.department_name })}</a
                    >
                </p>
            {/if}
            {#if commune.former_names.length}
                <p class="former">
                    {t('communes.former_names', {
                        list: new Intl.ListFormat(lang, {
                            style: 'long',
                            type: 'conjunction',
                        }).format(commune.former_names),
                    })}
                </p>
            {/if}
        </SheetHero>

        {#snippet heroStats()}
            <StatStrip items={statItems} label={t('communes.stats.title')} />
        {/snippet}

        <SheetToc items={tocItems} label={t('communes.toc.label')} />

        <SheetSection
            id="stations"
            title={t('communes.sections.stations')}
            count={stations.length}
            intro={t('communes.sections.stations_hint')}
        >
            <div class={['stations-map', { solo: !stations.length && !lines.length }]}>
                <div class="stations-col">
                    {#if stations.length}
                        <ul class="stations-list">
                            {#each stations as s (s.slug)}
                                <li class="station-row">
                                    {@render statusMark(s.status)}
                                    <div class="station-id">
                                        <a class="station-name" href="/stations/{s.slug}">
                                            <span class="vff-sr-only"
                                                >{t(`stations.statuses.${s.status}`, {
                                                    defaultValue: '',
                                                })}.
                                            </span>{s.name}
                                        </a>
                                        <p class="station-meta">
                                            {t(`stations.statuses.${s.status}`, {
                                                defaultValue: '',
                                            })}
                                            {#if s.type === 'halte'}<span class="vff-badge muted"
                                                    >{t('communes.stations.type_halte')}</span
                                                >{/if}
                                        </p>
                                        <p class="station-dates">{stationDates(s)}</p>
                                    </div>
                                </li>
                            {/each}
                        </ul>
                    {:else}
                        <EmptyState icon="bi-pin-map" title={t('communes.stations.none')} />
                    {/if}
                </div>
                <div class="map-col">
                    <div class="map-frame" aria-busy={!map}>
                        {#if map}
                            <CommuneMap {map} name={commune.name} />
                        {/if}
                    </div>
                    <p class="map-note">
                        {t('communes.map.summary', {
                            stations: counts.stations,
                            lines: counts.lines,
                        })}
                    </p>
                    <ul class="legend" aria-label={t('communes.map.legend')}>
                        <li>
                            <span class="swatch open" aria-hidden="true"></span>{t(
                                'communes.map.legend_open',
                            )}
                        </li>
                        <li>
                            <span class="swatch closed" aria-hidden="true"></span>{t(
                                'communes.map.legend_closed',
                            )}
                        </li>
                        <li>
                            <span class="swatch station" aria-hidden="true"></span>{t(
                                'communes.map.legend_station',
                            )}
                        </li>
                    </ul>
                </div>
            </div>
        </SheetSection>

        <SheetSection
            id="lines"
            title={t('communes.sections.lines')}
            count={lines.length}
            intro={t('communes.sections.lines_hint')}
        >
            {#if lines.length}
                <ul class="lines-list">
                    {#each lines as l (l.slug)}
                        {@const facts = lineFacts(l)}
                        {@const period = linePeriod(l)}
                        <li class="line-row">
                            <div class="line-id">
                                <a class="line-name" href="/lines/{l.slug}">{l.name}</a>
                                <p class="line-meta">
                                    {#if l.code}<span class="vff-plate">{l.code}</span>{/if}
                                    {#if l.is_lgv}<span class="vff-badge muted"
                                            >{t('communes.lines.lgv')}</span
                                        >{/if}
                                    <span class="km">{lineLength(l)}</span>
                                </p>
                            </div>
                            <p class={['state', l.state]}>
                                {t(`communes.lines.state.${l.state}`)}
                                {#if l.track}<span class="track"
                                        >{t(`communes.lines.track.${l.track}`)}</span
                                    >{/if}
                            </p>
                            <p class="facts">
                                {#each facts as f, i (i)}<span>{f}</span>{/each}
                                {#if period}<span class="period">{period}</span>{/if}
                            </p>
                        </li>
                    {/each}
                </ul>
            {:else}
                <EmptyState icon="bi-bezier2" title={t('communes.lines.none')} />
            {/if}
        </SheetSection>

        <SheetSection
            id="history"
            title={t('communes.sections.history')}
            intro={t('communes.sections.history_hint')}
        >
            <CommuneChronology entries={chronology} />
            {#if chronology.length >= CHRONOLOGY_LIMIT}
                <p class="map-note">
                    {t('communes.chronology.truncated', { count: CHRONOLOGY_LIMIT })}
                </p>
            {/if}
        </SheetSection>

        {#if neighbors.length}
            <SheetSection
                id="neighbors"
                title={t('communes.sections.neighbors')}
                intro={t('communes.sections.neighbors_hint')}
            >
                <ul class="neighbors">
                    {#each neighbors as n (n.insee)}
                        <li><a href="/communes/{n.slug}">{n.name}</a></li>
                    {/each}
                </ul>
            </SheetSection>
        {/if}
    </main>
</Layout>

<style>
    main {
        background-color: var(--vff-bg);
        padding-bottom: var(--vff-section-space);
    }
    .former {
        max-width: var(--vff-measure);
        margin: 10px 0 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: 1.5;
        color: var(--vff-text-muted);
    }

    /* Gares à gauche, carte à droite : l'essentiel se voit sans défiler. */
    .stations-map {
        display: grid;
        grid-template-columns: minmax(0, 5fr) minmax(0, 7fr);
        gap: var(--vff-space-5) var(--vff-space-6);
        align-items: start;
    }
    .stations-map.solo {
        grid-template-columns: minmax(0, 1fr);
    }
    .map-col {
        position: sticky;
        top: var(--vff-space-5);
    }
    .map-frame {
        --map-h: 460px;
        height: var(--map-h);
    }
    .map-note {
        max-width: var(--vff-measure);
        margin: var(--vff-space-3) 0 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: 1.5;
        color: var(--vff-text-muted);
    }
    .legend {
        display: flex;
        flex-wrap: wrap;
        gap: 6px 20px;
        margin: var(--vff-space-2) 0 0;
        padding: 0;
        list-style: none;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text);
    }
    .legend li {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .swatch {
        display: inline-block;
        flex-shrink: 0;
        width: 28px;
        height: 0;
        border-top: 4px solid #ff6500;
        border-radius: 2px;
    }
    .swatch.closed {
        border-top: 3px dashed #78716c;
    }
    .swatch.station {
        width: 14px;
        height: 14px;
        box-sizing: border-box;
        border: 3px solid #ff6500;
        border-radius: 50%;
        background-color: #fff;
    }

    /* Gares : une rangée par gare, filets légers. */
    .stations-list {
        margin: 0;
        padding: 0;
        list-style: none;
        border-top: 1px solid var(--vff-border-strong);
    }
    .station-row {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr);
        align-items: start;
        gap: 14px;
        padding: var(--vff-space-3) 0;
        border-bottom: 1px solid var(--vff-border);
    }
    .station-row p {
        margin: 0;
    }
    .mark {
        display: inline-grid;
        flex-shrink: 0;
        place-items: center;
        width: 26px;
        height: 26px;
        margin-top: 2px;
        border: 2px solid var(--status);
        border-radius: 50%;
        background-color: var(--vff-bg-elevated);
        color: var(--vff-text);
        font-size: 12px;
        line-height: 1;
    }
    .station-id {
        display: grid;
        gap: 2px;
        min-width: 0;
    }
    .station-name {
        justify-self: start;
        font-family: var(--vff-font-display);
        font-size: var(--vff-fs-h3);
        font-weight: 700;
        line-height: 1.4;
        color: var(--vff-text);
        text-wrap: balance;
    }
    .station-meta,
    .station-dates {
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: 1.5;
        color: var(--vff-text-muted);
    }
    .station-dates {
        color: var(--vff-text);
    }

    /* Lignes : tableau à filets, comme la liste des lignes d'une gare. */
    .lines-list {
        margin: 0;
        padding: 0;
        list-style: none;
        border-top: 1px solid var(--vff-border-strong);
    }
    .line-row {
        display: grid;
        grid-template-columns: minmax(0, 1.5fr) minmax(0, 0.8fr) minmax(0, 1.3fr);
        gap: 6px 32px;
        align-items: start;
        padding: var(--vff-space-4) 0;
        border-bottom: 1px solid var(--vff-border);
    }
    .line-row p {
        margin: 0;
    }
    .line-id {
        display: grid;
        gap: 6px;
        min-width: 0;
    }
    .line-name {
        justify-self: start;
        font-family: var(--vff-font-display);
        font-size: var(--vff-fs-h3);
        font-weight: 700;
        line-height: 1.4;
        color: var(--vff-text);
        text-wrap: balance;
    }
    .line-meta {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 4px 12px;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
    .km {
        font-variant-numeric: tabular-nums;
    }
    .state {
        display: grid;
        gap: 2px;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-md);
        font-weight: 600;
        line-height: 1.4;
        color: var(--vff-text);
    }
    .state.closed {
        color: var(--vff-text-muted);
    }
    .track {
        font-size: var(--vff-fs-sm);
        font-weight: 400;
        color: var(--vff-text-muted);
    }
    .facts {
        display: grid;
        gap: 2px;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: 1.5;
        color: var(--vff-text);
    }
    .period {
        color: var(--vff-text-muted);
    }

    /* Communes voisines : liste de liens en colonnes, cibles de 44 px. */
    .neighbors {
        columns: 220px;
        column-gap: var(--vff-space-6);
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .neighbors li {
        break-inside: avoid;
    }
    .neighbors a {
        display: inline-flex;
        align-items: center;
        min-height: 44px;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-md);
        font-weight: 600;
    }

    @media (max-width: 900px) {
        .stations-map {
            grid-template-columns: minmax(0, 1fr);
        }
        .map-col {
            position: static;
            order: -1;
        }
        .map-frame {
            --map-h: 380px;
        }
        .line-row {
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
        }
        .facts {
            grid-column: 1 / -1;
        }
    }
    @media (max-width: 600px) {
        .map-frame {
            --map-h: 320px;
        }
        .line-row {
            grid-template-columns: minmax(0, 1fr);
        }
    }
</style>

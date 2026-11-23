<script>
    import { formatStationDate, formatStationOpening } from '$lib/stationDates.js';
    import { servicePeriodText } from '$lib/servicePeriod.js';
    import Layout from '$components/Layout.svelte';
    import StationEraStrip from '$components/Stations/StationEraStrip.svelte';
    import StationMiniMap from '$components/Stations/StationMiniMap.svelte';
    import SheetHero from '$components/Sheet/SheetHero.svelte';
    import SheetSection from '$components/Sheet/SheetSection.svelte';
    import ReportError from '$components/Sheet/ReportError.svelte';
    import StatStrip from '$components/Sheet/StatStrip.svelte';
    import SheetToc from '$components/Sheet/SheetToc.svelte';
    import SheetGallery from '$components/Sheet/SheetGallery.svelte';
    import StationAerialViews from '$components/Stations/StationAerialViews.svelte';
    import StationAccessibility from '$components/Stations/StationAccessibility.svelte';
    import StationAttendance from '$components/Stations/StationAttendance.svelte';
    import StationTimetable from '$components/Stations/StationTimetable.svelte';
    import StationChronology from '$components/Stations/StationChronology.svelte';
    import StationHeritage from '$components/Stations/StationHeritage.svelte';
    import Notice from '$components/ui/Notice.svelte';
    import EmptyState from '$components/ui/EmptyState.svelte';
    import SeoHead from '$components/SeoHead.svelte';
    import { breadcrumbSchema, stationSchema } from '$lib/structuredData.js';
    import {
        STATION_STATUS_COLOR,
        STATION_STATUS_ICON,
    } from '$components/Stations/stationStatus.js';
    import { i18n } from '$lib/i18n.js';

    /** Props de StationController::show (images : `media_count` seul, la galerie les charge par l'API). */
    let {
        station,
        eras = [],
        lines = [],
        sources = [],
        neighbors = [],
        media_count = 0,
        aerial_views = [],
        chronology = [],
        attendance = null,
        heritage = null,
    } = $props();

    /** Autres noms de la fiche ; le nom actuel daté (renamed) n'est que dans la chronologie. */
    const altNames = $derived((station.alt_names ?? []).filter((a) => !a.renamed));
    const lang = $derived($i18n.language ?? 'fr');
    const t = (key, opts) => String($i18n.t(key, opts));

    // Sommaire collant : SheetToc n'affiche que les sections présentes.
    const TOC = [
        'about',
        'heritage',
        'eras',
        'history',
        'lines',
        'timetable',
        'accessibility',
        'attendance',
        'location',
        'gallery',
        'aerial',
        'sources',
    ];
    const tocItems = $derived(TOC.map((id) => ({ id, label: t(`stations.toc.${id}`) })));
    /** Espace insécable entre un nombre et son unité (« 5,2 km », « à 74 m ») et après « PK ». */
    const nb = (s) => s.replace(/(\d) (?=(?:km|m)(?![\p{L}]))/gu, '$1 ').replace(/^PK /, 'PK ');

    /** Date selon sa précision (jour, mois, année, décennie, après / avant, intervalle « entre 1951 et 1960 »). */
    function formatDate(iso, precision = 'day', after = null) {
        return formatStationDate(iso, precision, after, t, lang);
    }

    /** Date d'ouverture : une ouverture encadrée (précision range) se lit « entre 1847 et 1860 ». */
    function formatOpening(iso, precision = 'day', after = null) {
        return formatStationOpening(iso, precision, after, t, lang);
    }

    /** PK en kilomètres, décimales selon la langue : « 402,659 » (fr), « 402.659 » (en). */
    const formatPk = (m, digits = 3) =>
        (m / 1000).toLocaleString(lang, {
            minimumFractionDigits: digits,
            maximumFractionDigits: digits,
        });

    const currentYear = $derived(eras.find((e) => e.is_current)?.year ?? null);

    /** [1900, 1910, 1920, 1980, 2030] → « de 1900 à 1920 et de 1980 à aujourd'hui » (2030 = aujourd'hui). */
    function eraRanges(years) {
        const ranges = [];
        let start = null;
        let prev = null;
        for (const y of years) {
            if (start === null) {
                start = prev = y;
            } else if (y === prev + 10) {
                prev = y;
            } else {
                ranges.push([start, prev]);
                start = prev = y;
            }
        }
        if (start !== null) ranges.push([start, prev]);

        const parts = ranges.map(([from, to]) => {
            const today = to === currentYear;
            if (from === to) {
                return today
                    ? t('stations.lines.era.today')
                    : t('stations.lines.era.single', { year: from });
            }
            return today
                ? t('stations.lines.era.to_today', { from })
                : t('stations.lines.era.range', { from, to });
        });

        return new Intl.ListFormat(lang, { style: 'long', type: 'conjunction' }).format(parts);
    }

    const sourceName = (s) => t(`stations.sources.names.${s}`, { defaultValue: s });
    /** Lien d'une source : l'identifiant s'il est court et lisible (code UIC, Q123, n456), sinon « Voir ». */
    const refLabel = (ref) =>
        ref && /^[A-Za-z0-9]{1,14}$/.test(ref) ? ref : t('stations.sources.open_link');

    const openedInferred = $derived((station.provenance?.opened_at ?? '').startsWith('inferred'));
    const statusColor = $derived(
        STATION_STATUS_COLOR[station.status] ?? STATION_STATUS_COLOR.declassified,
    );

    /** Chiffres clés : des quantités et des dates, ni identifiant (code UIC) ni métadonnée (nombre de sources). */
    const statItems = $derived.by(() => {
        /** @type {Array<{ value?: string, text?: string, label: string, note?: string, noteHint?: string }>} */
        const items = [
            { value: lines.length.toLocaleString(lang), label: t('stations.stats.lines') },
        ];
        // Une année seule se lit en grand chiffre ; une date longue ou un intervalle, en phrase courte.
        const entry = (formatted, label, extra = {}) =>
            /^\d{4}$/.test(formatted)
                ? { value: formatted, label, ...extra }
                : { text: formatted, label, ...extra };
        if (station.opened_at)
            items.push(
                entry(
                    formatOpening(
                        station.opened_at,
                        station.opened_precision,
                        station.opened_after,
                    ),
                    t('stations.stats.opened'),
                    {
                        note: openedInferred ? `(${t('stations.inferred_short')})` : undefined,
                        noteHint: openedInferred ? t('stations.events.inferred_hint') : undefined,
                    },
                ),
            );
        if (station.closed_at)
            items.push(
                entry(
                    formatDate(station.closed_at, station.closed_precision, station.closed_after),
                    t('stations.stats.closed'),
                ),
            );
        return items;
    });

    /** « Bagnoles-de-l'Orne (61) » : commune et département. */
    const place = $derived(
        station.city
            ? `${station.city}${station.department_code ? ` (${station.department_code})` : ''}`
            : null,
    );
    const heroEyebrow = $derived(
        [place, station.uic_code ? `${t('stations.stats.uic')} ${station.uic_code}` : null].filter(
            Boolean,
        ),
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
    /** Coordonnées lisibles (la mini-carte n'est pas lisible sans la vue) : « 45,8344° N », « 1,2630° E ». */
    const deg = (v, pos, neg) =>
        `${Math.abs(v).toLocaleString(lang, { minimumFractionDigits: 4, maximumFractionDigits: 4 })}° ${v >= 0 ? pos : neg}`;
    const latText = $derived(deg(station.lat, 'N', 'S'));
    const lngText = $derived(deg(station.lng, 'E', 'W'));
    const osmUrl = $derived(
        `https://www.openstreetmap.org/?mlat=${station.lat}&mlon=${station.lng}#map=15/${station.lat}/${station.lng}`,
    );

    /** Commune d'une gare voisine : omise quand son nom la contient déjà. */
    const showCity = (n) => n.city && !n.name.toLowerCase().includes(n.city.toLowerCase());
</script>

<svelte:head>
    <title>{station.name} - VFF</title>
</svelte:head>

<!-- Partage et données structurées : la gare (position, commune, code UIC) et le fil d'Ariane. -->
<SeoHead
    title={station.name}
    description={$i18n.t('stations.meta_show', { name: station.name })}
    path={`/stations/${station.slug}`}
    type="article"
    jsonLd={(origin) => [
        // Pas d'extrait ni de dates dans les données structurées : leurs sources ne sont pas toutes ouvertes (le serveur en trie).
        stationSchema({ origin, station }),
        breadcrumbSchema(origin, [
            { name: $i18n.t('common.app_full'), path: '/' },
            { name: $i18n.t('nav.stations'), path: '/stations' },
            { name: station.name, path: `/stations/${station.slug}` },
        ]),
    ]}
/>

{#snippet lineNeighbour(n, side)}
    <span class={['line-neighbour', side]}>
        <span class="vff-sr-only">{$i18n.t(`stations.lines.neighbours.${side}`)} : </span>
        {#if side === 'previous'}<span aria-hidden="true">←&nbsp;</span>{/if}<a
            class="neighbour-name"
            href="/stations/{n.slug}">{n.name}</a
        >
        <span class="neighbour-dist"
            >&middot; {nb(
                t('stations.neighbors_distance', { km: formatPk(n.distance_m, 1) }),
            )}</span
        >
        {#if n.status !== 'active'}
            <span class="neighbour-state"
                >({$i18n.t(`stations.lines.neighbours.state.${n.status}`, {
                    defaultValue: '',
                })}{#if n.last_open_era}, <a
                        class="neighbour-era"
                        href="/maps?era={n.last_open_era}"
                        >{$i18n.t('stations.lines.neighbours.see_era')}</a
                    >{/if})</span
            >
        {/if}
        {#if side === 'next'}<span aria-hidden="true">&nbsp;→</span>{/if}
    </span>
{/snippet}

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
            eyebrow={heroEyebrow}
            title={station.name}
            stats={heroStats}
        >
            <p class="status" style:--status={statusColor}>
                {@render statusMark(station.status)}
                {$i18n.t(`stations.statuses.${station.status}`)}
            </p>
            {#if altNames.length}
                <p class="alt-names">
                    {$i18n.t('stations.also_known_as_label')}
                    {#each altNames as a, i (i)}
                        <span class="alt"
                            >{a.name}{#if a.from || a.to}&nbsp;({a.from ?? '…'}-{a.to ??
                                    '…'}){/if}</span
                        >{i < altNames.length - 1 ? ', ' : ''}
                    {/each}
                </p>
            {/if}
        </SheetHero>

        {#snippet heroStats()}
            <StatStrip items={statItems} label={$i18n.t('stations.stats.title')} />
        {/snippet}

        <SheetToc items={tocItems} label={$i18n.t('stations.toc.label')} />

        {#if station.description}
            <SheetSection id="about" title={$i18n.t('stations.sections.about')}>
                <div class="vff-prose">
                    <p lang={station.description_lang ?? undefined}>{station.description}</p>
                </div>
            </SheetSection>
        {/if}

        <StationHeritage {heritage} name={station.name} />

        <SheetSection
            id="eras"
            title={$i18n.t('stations.sections.eras')}
            intro={$i18n.t('stations.sections.eras_hint')}
        >
            <StationEraStrip {eras} />
        </SheetSection>

        <SheetSection
            id="history"
            title={$i18n.t('stations.sections.history')}
            intro={$i18n.t('stations.chronology.intro')}
        >
            <StationChronology entries={chronology} views={aerial_views} name={station.name} />
        </SheetSection>

        <SheetSection title={$i18n.t('stations.sections.lines')} id="lines" count={lines.length}>
            {#if lines.length}
                <ul class="lines-list">
                    {#each lines as l (l.slug)}
                        {@const period = servicePeriodText(l, t, formatDate)}
                        <li class="line-row">
                            <div class="line-id">
                                <a class="line-name" href="/lines/{l.slug}">{l.name}</a>
                                <p class="line-meta">
                                    {#if l.code}<span class="vff-plate">{l.code}</span>{/if}
                                    {#if l.pk_m !== null}
                                        <span class="pk"
                                            >{nb(
                                                t('stations.lines.pk', { pk: formatPk(l.pk_m) }),
                                            )}{#if l.pk_source && l.pk_source !== 'sncf' && $i18n.exists(`stations.lines.pk_flag.${l.pk_source}`)}
                                                &nbsp;({$i18n.t(
                                                    `stations.lines.pk_flag.${l.pk_source}`,
                                                )}){/if}</span
                                        >
                                    {/if}
                                </p>
                            </div>
                            <p class="line-period">
                                {#if period}
                                    {period.text}
                                    {#if period.fromStation}
                                        <span class="period-source"
                                            >{t('stations.lines.years_from_station')}</span
                                        >
                                    {/if}
                                {/if}
                            </p>
                            {#if l.era_years.length && !l.never_served}
                                <p class="line-eras">
                                    {$i18n.t('stations.lines.stop_in_eras', {
                                        list: eraRanges(l.era_years),
                                    })}
                                </p>
                            {/if}
                            {#if l.neighbours}
                                <p class="line-neighbours">
                                    {#if l.neighbours.previous}
                                        {@render lineNeighbour(l.neighbours.previous, 'previous')}
                                    {/if}
                                    {#if l.neighbours.next}
                                        {@render lineNeighbour(l.neighbours.next, 'next')}
                                    {/if}
                                </p>
                            {/if}
                        </li>
                    {/each}
                </ul>
            {:else}
                <EmptyState icon="bi-bezier2" title={$i18n.t('stations.lines.none')} />
            {/if}
        </SheetSection>

        <StationTimetable slug={station.slug} name={station.name} />

        <StationAccessibility slug={station.slug} name={station.name} />

        <StationAttendance {attendance} name={station.name} />

        <SheetSection id="location" title={$i18n.t('stations.sections.map')}>
            <div class={['location', { solo: !neighbors.length }]}>
                <div class="map-col">
                    <div class="map-frame">
                        <StationMiniMap {station} {neighbors} height="100%" />
                    </div>
                    <p class="map-note">
                        <!-- « lon », jamais « lng » : i18next lit `lng` comme la langue voulue. -->
                        {$i18n.t('stations.map.summary', { lat: latText, lon: lngText })}
                        <a href={osmUrl} target="_blank" rel="noopener noreferrer"
                            >{$i18n.t('stations.map.open_osm')}<i
                                class="bi bi-box-arrow-up-right vff-ext"
                                aria-hidden="true"
                            ></i><span class="vff-sr-only"> ({$i18n.t('common.new_tab')})</span></a
                        >
                    </p>
                </div>
                {#if neighbors.length}
                    <div class="near">
                        <h3 id="neighbors">{$i18n.t('stations.sections.neighbors')}</h3>
                        <ul class="near-list" aria-labelledby="neighbors">
                            {#each neighbors as n (n.slug)}
                                <li>
                                    <a class="near-link" href="/stations/{n.slug}">
                                        {@render statusMark(n.status)}
                                        <span class="near-name">
                                            <span class="vff-sr-only"
                                                >{$i18n.t(`stations.statuses.${n.status}`, {
                                                    defaultValue: '',
                                                })}.
                                            </span>{n.name}{#if showCity(n)}<span class="near-city">
                                                    {n.city}</span
                                                >{/if}
                                        </span>
                                        <span class="near-dist"
                                            >{nb(
                                                t('stations.neighbors_distance', {
                                                    km: formatPk(n.distance_m, 1),
                                                }),
                                            )}</span
                                        >
                                    </a>
                                </li>
                            {/each}
                        </ul>
                    </div>
                {/if}
            </div>
        </SheetSection>

        {#if media_count > 0}
            <SheetGallery
                endpoint="/api/v1/stations/{encodeURIComponent(station.slug)}/media"
                intro={t('media.subtitle_station')}
                count={media_count}
            />
        {/if}

        <StationAerialViews views={aerial_views} name={station.name} />

        <SheetSection
            title={$i18n.t('stations.sections.sources')}
            id="sources"
            count={sources.length}
        >
            {#if station.position_flagged}
                <div class="flag">
                    <Notice tone="warning" live="off">
                        {nb(t('stations.sources.position_flag', { m: station.position_spread_m }))}
                        {$i18n.t('stations.sources.position_from', {
                            source: sourceName(station.position_source),
                        })}
                    </Notice>
                </div>
            {/if}
            <ul class="sources">
                {#each sources as s, i (i)}
                    <li class="source">
                        <div class="src-id">
                            <p class="src-name">
                                <strong>{sourceName(s.source)}</strong>
                                {#if $i18n.exists(`stations.sources.licences.${s.source}`)}
                                    <span class="vff-badge"
                                        >{$i18n.t(`stations.sources.licences.${s.source}`)}</span
                                    >
                                {/if}
                            </p>
                            {#if s.url}
                                <p class="src-link">
                                    <a href={s.url} target="_blank" rel="noopener noreferrer"
                                        >{refLabel(s.ref)}<i
                                            class="bi bi-box-arrow-up-right vff-ext"
                                            aria-hidden="true"
                                        ></i><span class="vff-sr-only">
                                            ({$i18n.t('common.new_tab')})</span
                                        ></a
                                    >
                                </p>
                            {/if}
                        </div>
                        <ul class="facts">
                            <li class="fact-name">{s.name}</li>
                            {#if s.lifecycle && s.lifecycle !== 'unknown'}
                                <li>
                                    {$i18n.t('stations.sources.status_in_source', {
                                        state: $i18n.t(`stations.sources.lifecycle.${s.lifecycle}`),
                                    })}
                                </li>
                            {/if}
                            {#if s.opened_on}
                                <li>
                                    {$i18n.t('stations.opened_in', {
                                        year: s.opened_on.slice(0, 4),
                                    })}
                                </li>
                            {/if}
                            {#if s.closed_on}
                                <li>
                                    {$i18n.t('stations.closed_in', {
                                        year: s.closed_on.slice(0, 4),
                                    })}
                                </li>
                            {/if}
                            {#if s.geocoding === 'commune' && s.precision_m}
                                <li>
                                    {nb(
                                        t('stations.sources.geocoded_by_commune', {
                                            m: s.precision_m,
                                        }),
                                    )}
                                </li>
                            {:else if s.geocoding && s.geocoding !== 'station' && s.geocoding !== 'name_match' && s.pk_m}
                                <li>
                                    {nb(
                                        s.precision_m
                                            ? t('stations.sources.geocoded_by_pk_precision', {
                                                  pk: formatPk(Number(s.pk_m), 1),
                                                  m: s.precision_m,
                                              })
                                            : t('stations.sources.geocoded_by_pk', {
                                                  pk: formatPk(Number(s.pk_m), 1),
                                              }),
                                    )}
                                </li>
                            {/if}
                        </ul>
                        <p class="match">
                            {$i18n.t(`stations.sources.match.${s.match_method}`, {
                                defaultValue: s.match_method ?? '',
                            })}
                            {#if s.distance_m > 0}
                                <span class={['dist', { far: s.distance_m > 100 }]}
                                    >{#if s.distance_m > 100}<i
                                            class="bi bi-exclamation-triangle"
                                            aria-hidden="true"
                                        ></i>{/if}{nb(
                                        t('stations.sources.distance', { m: s.distance_m }),
                                    )}{#if s.distance_m > 100}
                                        &nbsp;({$i18n.t('stations.sources.far_hint')}){/if}</span
                                >
                            {/if}
                        </p>
                    </li>
                {/each}
            </ul>

            {#if provenanceRows.length}
                <h3 class="sub">{$i18n.t('stations.sources.provenance')}</h3>
                <dl class="provenance">
                    {#each provenanceRows as [attr, src] (attr)}
                        <div class="prov-row">
                            <dt>{$i18n.t(`stations.sources.attributes.${attr}`)}</dt>
                            <dd>
                                {sourceName(src)}
                                {#if attr === 'geom' && station.provenance?.geom_precision_m && $i18n.exists(`stations.sources.geom_precision.${station.provenance?.geom_method}`)}
                                    <span class="precision"
                                        >({nb(
                                            t(
                                                `stations.sources.geom_precision.${station.provenance.geom_method}`,
                                                { m: station.provenance.geom_precision_m },
                                            ),
                                        )})</span
                                    >
                                {/if}
                            </dd>
                        </div>
                    {/each}
                </dl>
            {/if}
        </SheetSection>

        <ReportError
            type="station"
            slug={station.slug}
            name={station.name}
            proposals={[
                {
                    href: `/contribute/media?station=${station.slug}`,
                    label: t('proposals.sheet.media'),
                },
            ]}
        />
    </main>
</Layout>

<style>
    main {
        background-color: var(--vff-bg);
        padding-bottom: var(--vff-section-space);
    }

    /* En-tête (SheetHero) : statut et autres noms */
    .status {
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 4px 0 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-md);
        font-weight: 600;
        line-height: 1.3;
        color: var(--vff-text);
    }
    /* Pastille de statut : icône (la forme dit l'état) dans un anneau de la couleur de la carte. */
    .mark {
        display: inline-grid;
        flex-shrink: 0;
        place-items: center;
        width: 26px;
        height: 26px;
        border: 2px solid var(--status);
        border-radius: 50%;
        background-color: var(--vff-bg-elevated);
        color: var(--vff-text);
        font-size: 12px;
        line-height: 1;
    }
    .alt-names {
        max-width: var(--vff-measure);
        margin: 10px 0 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: 1.5;
        color: var(--vff-text-muted);
    }
    .alt {
        color: var(--vff-text);
    }

    /* Lignes desservies : lignes de tableau à filets (comme un livret), tout le bloc cliquable. */
    .lines-list {
        margin: 0;
        padding: 0;
        list-style: none;
        border-top: 1px solid var(--vff-border-strong);
    }
    .line-row {
        position: relative;
        display: grid;
        grid-template-columns: minmax(0, 1.5fr) minmax(0, 1fr) minmax(0, 1.2fr);
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
        text-decoration: none;
        text-wrap: balance;
    }
    /* Le lien couvre toute la ligne : la cible est la ligne entière. */
    .line-name::after {
        content: '';
        position: absolute;
        inset: 0;
    }
    .line-row:hover .line-name {
        color: var(--vff-accent-ink);
        text-decoration: underline;
        text-underline-offset: 4px;
    }
    .line-name:focus-visible {
        outline-offset: 0;
    }
    /* Gares voisines sur la ligne : une rangée, précédente à gauche, suivante à droite. Les liens passent
       au-dessus du lien qui couvre toute la ligne. */
    .line-neighbours {
        grid-column: 1 / -1;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        gap: 4px 24px;
        padding-top: 6px;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: 1.5;
        color: var(--vff-text-muted);
    }
    .line-neighbour.next {
        margin-left: auto;
        text-align: right;
    }
    .line-neighbour a {
        position: relative;
        z-index: 1;
        display: inline-block;
        padding: 4px 0;
        color: var(--vff-text);
        text-underline-offset: 3px;
    }
    .line-neighbour a:hover {
        color: var(--vff-accent-ink);
    }
    .neighbour-dist {
        font-variant-numeric: tabular-nums;
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
    .pk {
        font-variant-numeric: tabular-nums;
    }
    .line-period,
    .line-eras {
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: 1.5;
        color: var(--vff-text);
    }
    .line-eras {
        color: var(--vff-text-muted);
    }
    /* Sans colonne « décennies », la période reste à sa place. */
    .line-period {
        grid-column: 2;
    }
    /* Années tirées des dates de la gare (aucun événement daté de la ligne) : précision sous la période. */
    .period-source {
        display: block;
        color: var(--vff-text-muted);
    }

    /* Situation : carte (7/12) et gares voisines (5/12) ; la liste donne sa hauteur à la carte. */
    .location {
        display: grid;
        grid-template-columns: minmax(0, 7fr) minmax(0, 5fr);
        gap: var(--vff-space-5) var(--vff-space-6);
        align-items: start;
    }
    .location.solo {
        grid-template-columns: minmax(0, 1fr);
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
    .near h3 {
        margin: 0 0 var(--vff-space-3);
    }
    .near-list {
        margin: 0;
        padding: 0;
        list-style: none;
        border-top: 1px solid var(--vff-border-strong);
    }
    .near-list li {
        border-bottom: 1px solid var(--vff-border);
    }
    .near-link {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr) auto;
        align-items: center;
        gap: 12px;
        min-height: 44px;
        padding: 8px 0;
        color: var(--vff-text);
        text-decoration: none;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: 1.35;
    }
    .near-name {
        font-weight: 600;
        overflow-wrap: anywhere;
    }
    .near-city {
        margin-left: 8px;
        font-weight: 400;
        color: var(--vff-text-muted);
    }
    .near-dist {
        font-variant-numeric: tabular-nums;
        color: var(--vff-text-muted);
    }
    .near-link:hover .near-name {
        color: var(--vff-accent-ink);
        text-decoration: underline;
        text-underline-offset: 3px;
    }

    /* Sources : une rangée par source (identité, ce qu'elle dit, comment elle a été rapprochée). */
    .flag {
        max-width: 780px;
        margin-bottom: var(--vff-space-5);
    }
    .sources {
        margin: 0;
        padding: 0;
        list-style: none;
        border-top: 1px solid var(--vff-border-strong);
    }
    .source {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1.4fr) minmax(0, 1.1fr);
        gap: 8px 32px;
        align-items: start;
        padding: var(--vff-space-4) 0;
        border-bottom: 1px solid var(--vff-border);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: 1.5;
    }
    .source p {
        margin: 0;
    }
    .src-name {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 6px 10px;
    }
    .src-name strong {
        font-size: var(--vff-fs-md);
    }
    .src-link a {
        display: inline-flex;
        align-items: center;
        min-height: 24px;
    }
    .src-link {
        margin-top: 4px;
        overflow-wrap: anywhere;
        font-variant-numeric: tabular-nums;
    }
    .facts {
        display: grid;
        gap: 2px;
        margin: 0;
        padding: 0;
        list-style: none;
        color: var(--vff-text-muted);
    }
    .facts .fact-name {
        font-weight: 600;
        color: var(--vff-text);
    }
    .match {
        color: var(--vff-text-muted);
    }
    .dist {
        display: block;
        font-variant-numeric: tabular-nums;
    }
    .dist.far {
        font-weight: 600;
        color: var(--vff-warning);
    }
    .dist i {
        margin-right: 6px;
    }
    .sub {
        margin: var(--vff-block-space) 0 var(--vff-space-3);
    }
    .provenance {
        display: grid;
        max-width: 780px;
        margin: 0;
        border-top: 1px solid var(--vff-border-strong);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: 1.5;
    }
    .prov-row {
        display: grid;
        grid-template-columns: minmax(120px, 200px) minmax(0, 1fr);
        gap: 4px 24px;
        padding: 10px 0;
        border-bottom: 1px solid var(--vff-border);
    }
    .provenance dt {
        color: var(--vff-text-muted);
    }
    .provenance dd {
        margin: 0;
    }
    .precision {
        color: var(--vff-text-muted);
    }

    @media (max-width: 900px) {
        .location {
            grid-template-columns: minmax(0, 1fr);
        }
        .map-frame {
            --map-h: 380px;
        }
        .line-row {
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
        }
        .line-eras {
            grid-column: 1 / -1;
        }
        .source {
            grid-template-columns: minmax(0, 1fr) minmax(0, 1.3fr);
        }
        .match {
            grid-column: 1 / -1;
        }
    }
    @media (max-width: 600px) {
        .map-frame {
            --map-h: 320px;
        }
        .line-row,
        .source {
            grid-template-columns: minmax(0, 1fr);
        }
        .line-period {
            grid-column: auto;
        }
        .prov-row {
            grid-template-columns: minmax(0, 1fr);
        }
    }
</style>

<script>
    import { onMount } from 'svelte';
    import SheetSection from '$components/Sheet/SheetSection.svelte';
    import {
        directionPlaces,
        longDate,
        mainFeed,
        placeParts,
        typeCounts,
    } from '$lib/stationTimetable.js';
    import { formatNumber } from '$lib/typo.js';
    import { i18n } from '$lib/i18n.js';

    /**
     * Desserte de la gare un jour ouvrable type (un mardi d'une semaine sans jour férié), d'après les horaires
     * ouverts de SNCF et de Transilien (ODbL) : nombre de trains, par type, premier et dernier train, les deux
     * sens avec leurs destinations directes, jour et version des horaires. Rien n'est affiché quand aucun train
     * de voyageurs ne s'arrête dans la gare ce jour-là (`timetable` nul). Chargée à la demande
     * (`GET /api/v1/stations/{slug}/timetable`) pour ne rien ajouter aux lectures de la fiche.
     */
    /** @type {{ timetable?: null | { service_date: string, trains: number, by_type: Record<string, number>, first: string|null, last: string|null, directions: Array<{ heading: string|null, trains: number, first: string|null, last: string|null, destinations: Array<{ name: string, slug: string|null, trains: number }>, origins: Array<{ name: string, slug: string|null, trains: number }> }>, feeds: Array<{ feed: string, version: string|null, valid_to: string|null, page: string|null }> }, slug: string, name: string }} */
    let { timetable: initial = null, slug, name } = $props();

    // svelte-ignore state_referenced_locally
    let timetable = $state(initial);

    onMount(async () => {
        if (timetable) return;
        try {
            const res = await fetch(`/api/v1/stations/${encodeURIComponent(slug)}/timetable`, {
                headers: { Accept: 'application/json' },
            });
            if (res.ok) timetable = (await res.json()).timetable ?? null;
        } catch {
            // Section facultative : sans réponse, la fiche reste complète.
        }
    });

    const lang = $derived($i18n.language ?? 'fr');
    const t = (key, opts) => String($i18n.t(key, opts));
    const num = (n) => formatNumber(n, lang);
    const list = (items) =>
        new Intl.ListFormat(lang, { style: 'long', type: 'conjunction' }).format(items);

    const types = $derived(typeCounts(timetable?.by_type));
    const feed = $derived(mainFeed(timetable?.feeds));
    const directions = $derived(timetable?.directions ?? []);
    // Deux lignes seulement quand la gare a vraiment deux sens.
    const showDirections = $derived(directions.length > 0);
</script>

{#if timetable && timetable.trains > 0}
    <SheetSection id="timetable" title={t('stations.timetable.title')}>
        <div class="timetable">
            <p class="latest">
                <span class="figure">{num(timetable.trains)}</span>
                <span class="unit"
                    >{t('stations.timetable.trains', { count: timetable.trains })}
                    {t('stations.timetable.day', {
                        date: longDate(timetable.service_date, lang),
                    })}</span
                >
            </p>
            {#if types.length > 0}
                <p class="types">
                    {list(
                        types.map((x) =>
                            t('stations.timetable.type_count', {
                                count: num(x.trains),
                                type: t(`stations.timetable.type.${x.type}`),
                            }),
                        ),
                    )}
                </p>
            {/if}
            {#if timetable.first && timetable.last}
                <p class="range">
                    {t('stations.timetable.first_last', {
                        first: timetable.first,
                        last: timetable.last,
                    })}
                </p>
            {/if}

            {#if showDirections}
                <div class="scroll">
                    <table>
                        <caption class="vff-sr-only"
                            >{t('stations.timetable.caption', { name })}</caption
                        >
                        <thead>
                            <tr>
                                <th scope="col">{t('stations.timetable.direction')}</th>
                                <th scope="col" class="n">{t('stations.timetable.count')}</th>
                                <th scope="col" class="n">{t('stations.timetable.first')}</th>
                                <th scope="col" class="n">{t('stations.timetable.last')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {#each directions as d, i (i)}
                                {@const where = directionPlaces(d)}
                                <tr>
                                    <th scope="row">
                                        {#if where.places.length > 0}
                                            <span class="to"
                                                >{where.arrivals
                                                    ? t('stations.timetable.from')
                                                    : t('stations.timetable.toward')}</span
                                            >
                                            {#each placeParts(where.places, lang) as part, k (k)}
                                                {#if part.place?.slug}<a
                                                        href="/stations/{part.place.slug}"
                                                        >{part.text}</a
                                                    >{:else}{part.text}{/if}{/each}
                                        {:else}
                                            {t('stations.timetable.direction_n', { n: i + 1 })}
                                        {/if}
                                        {#if d.heading}
                                            <span class="heading"
                                                >{t('stations.timetable.heading', {
                                                    cap: t(
                                                        `stations.timetable.compass.${d.heading}`,
                                                    ),
                                                })}</span
                                            >
                                        {/if}
                                    </th>
                                    <td class="n">{num(d.trains)}</td>
                                    <td class="n">{d.first ?? ''}</td>
                                    <td class="n">{d.last ?? ''}</td>
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                </div>
            {/if}

            <p class="note">{t('stations.timetable.note')}</p>
            <p class="source">
                {feed?.version && longDate(feed.version, lang)
                    ? t('stations.timetable.source', {
                          version: longDate(feed.version, lang),
                          until: longDate(feed.valid_to, lang),
                      })
                    : t('stations.timetable.source_until', {
                          until: longDate(feed?.valid_to, lang),
                      })}
                {#if feed?.page}
                    <a href={feed.page} target="_blank" rel="noopener noreferrer license"
                        >{t('stations.timetable.licence')}</a
                    >
                {/if}
            </p>
        </div>
    </SheetSection>
{/if}

<style>
    .timetable {
        max-width: 720px;
    }
    .latest {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        gap: 4px var(--vff-space-3);
        margin: 0;
    }
    .figure {
        font-family: var(--vff-font-display);
        font-size: var(--vff-fs-figure);
        line-height: 1.1;
        color: var(--vff-text);
    }
    .unit {
        font-size: var(--vff-fs-body);
        color: var(--vff-text-muted);
    }
    .types,
    .range {
        margin: var(--vff-space-3) 0 0;
        font-size: var(--vff-fs-body);
        color: var(--vff-text);
    }
    .range {
        margin-top: var(--vff-space-1);
    }
    .scroll {
        overflow-x: auto;
        margin-top: var(--vff-space-4);
    }
    table {
        border-collapse: collapse;
        width: 100%;
        font-size: var(--vff-fs-sm);
    }
    th,
    td {
        padding: var(--vff-space-2) var(--vff-space-4) var(--vff-space-2) 0;
        text-align: left;
        font-weight: 400;
        vertical-align: top;
        border-bottom: 1px solid var(--vff-border);
    }
    thead th {
        color: var(--vff-text-muted);
    }
    tbody th {
        color: var(--vff-text);
    }
    .n {
        text-align: right;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }
    .heading {
        display: block;
        color: var(--vff-text-muted);
    }
    th a,
    .source a {
        color: var(--vff-accent-ink);
    }
    .note,
    .source {
        margin: var(--vff-space-3) 0 0;
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
</style>

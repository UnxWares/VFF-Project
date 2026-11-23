<script>
    import { Link } from '@inertiajs/svelte';
    import { i18n } from '$lib/i18n.js';
    import {
        FETCH_DELAY,
        SERVER_LIMIT,
        isTooWide,
        lineStateOf,
        listingUrl,
        paginate,
        stationStateOf,
        visibleLines,
        visibleStations,
    } from '$lib/mapListing.js';

    /**
     * Lignes et gares de la zone visible à UNE époque, en listes de liens vers les fiches. Demande la liste
     * au serveur à la fin de chaque déplacement (pas à chaque image), garde la précédente à l'écran pendant
     * le chargement et annonce le résultat dans une zone `role="status"`.
     *
     * Props : year (époque), title (titre de la colonne), bbox ([ouest, sud, est, nord] ou null), active
     * (la liste est ouverte : sinon aucune requête), hidden (catégories masquées dans la légende),
     * onlyLine et onlyStation (état choisi, vide : tous), sort ('km' ou 'name'), version (version des
     * données, pour le cache du navigateur), cache (createListingCache, partagé entre colonnes).
     */
    let {
        year,
        title,
        bbox,
        active = true,
        hidden = [],
        onlyLine = '',
        onlyStation = '',
        sort = 'km',
        version = null,
        cache,
    } = $props();

    const uid = $props.id();

    // idle | loading | ready | wide | error
    let status = $state('idle');
    let data = $state.raw(null);
    let linePage = $state(1);
    let stationPage = $state(1);

    $effect(() => {
        if (!active || !bbox) return;
        if (isTooWide(bbox)) {
            status = 'wide';
            data = null;
            return;
        }
        const key = `${year}|${bbox.join(',')}`;
        const known = cache.get(key);
        if (known) {
            data = known;
            status = 'ready';
            return;
        }
        status = 'loading';
        const controller = new AbortController();
        const timer = setTimeout(async () => {
            try {
                const response = await fetch(listingUrl(year, bbox, version), {
                    headers: { Accept: 'application/json' },
                    signal: controller.signal,
                });
                if (!response.ok) throw new Error(String(response.status));
                const body = await response.json();
                cache.set(key, body);
                data = body;
                status = 'ready';
            } catch (error) {
                if (error?.name !== 'AbortError') status = 'error';
            }
        }, FETCH_DELAY);
        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    });

    const lines = $derived(
        visibleLines([...(data?.lines.items ?? [])], { hidden, only: onlyLine, sort }),
    );
    const stations = $derived(visibleStations(data?.stations.items ?? [], { only: onlyStation }));
    const linesPage = $derived(paginate(lines, linePage));
    const stationsPage = $derived(paginate(stations, stationPage));
    const lineTruncated = $derived(Boolean(data) && data.lines.total > data.lines.items.length);
    const stationTruncated = $derived(
        Boolean(data) && data.stations.total > data.stations.items.length,
    );

    // Un autre filtre, une autre zone ou un autre tri : retour à la première page.
    $effect(() => {
        [hidden, onlyLine, sort, data];
        linePage = 1;
    });
    $effect(() => {
        [onlyStation, data];
        stationPage = 1;
    });

    const number = (n) =>
        n.toLocaleString($i18n.language, { maximumFractionDigits: 1, minimumFractionDigits: 1 });

    const summary = $derived.by(() => {
        if (status === 'wide') return $i18n.t('maps.listing.too_wide');
        if (status === 'error') return $i18n.t('maps.listing.error');
        if (status === 'loading' && !data) return $i18n.t('maps.listing.loading');
        if (!data) return '';
        return $i18n.t('maps.listing.summary', {
            lines: $i18n.t('maps.listing.lines_count', { count: data.lines.total }),
            stations: $i18n.t('maps.listing.stations_count', { count: data.stations.total }),
        });
    });
</script>

{#snippet pager(current, onPrevious, onNext, label)}
    {#if current.pages > 1}
        <nav class="pager" aria-label={label}>
            <button
                type="button"
                class="vff-btn secondary small"
                disabled={current.page <= 1}
                onclick={onPrevious}
            >
                {$i18n.t('maps.listing.previous')}
            </button>
            <span>{$i18n.t('maps.listing.page', { page: current.page, pages: current.pages })}</span
            >
            <button
                type="button"
                class="vff-btn secondary small"
                disabled={current.page >= current.pages}
                onclick={onNext}
            >
                {$i18n.t('maps.listing.next')}
            </button>
        </nav>
    {/if}
{/snippet}

<section class="column" aria-labelledby="{uid}-title" aria-busy={status === 'loading'}>
    <h3 id="{uid}-title">{title}</h3>
    <p class="summary" role="status" class:muted={status === 'loading'}>{summary}</p>

    {#if data && status !== 'wide'}
        <div class="block">
            <h4 id="{uid}-lines">
                {$i18n.t('maps.listing.lines')} ({lines.length})
            </h4>
            {#if lines.length === 0}
                <p class="empty">{$i18n.t('maps.listing.no_lines')}</p>
            {:else}
                <ul aria-labelledby="{uid}-lines">
                    {#each linesPage.rows as line (`${line.slug}|${line.state}`)}
                        {@const state = lineStateOf(line.state)}
                        <li>
                            <Link href="/lines/{line.slug}">{line.name}</Link>
                            <span class="meta">
                                <i class="dot" style:background={state.color} aria-hidden="true"
                                ></i>
                                {$i18n.t(state.labelKey)}
                                <span class="sep" aria-hidden="true">·</span>
                                <span class="km">{number(line.km)} km</span>
                            </span>
                        </li>
                    {/each}
                </ul>
                {@render pager(
                    linesPage,
                    () => (linePage = linesPage.page - 1),
                    () => (linePage = linesPage.page + 1),
                    $i18n.t('maps.listing.pager_lines'),
                )}
            {/if}
            {#if lineTruncated}
                <p class="hint">
                    {$i18n.t('maps.listing.truncated', {
                        shown: SERVER_LIMIT,
                        total: data.lines.total,
                    })}
                </p>
            {/if}
        </div>

        <div class="block">
            <h4 id="{uid}-stations">
                {$i18n.t('maps.listing.stations')} ({stations.length})
            </h4>
            {#if stations.length === 0}
                <p class="empty">{$i18n.t('maps.listing.no_stations')}</p>
            {:else}
                <ul aria-labelledby="{uid}-stations">
                    {#each stationsPage.rows as station (station.slug)}
                        {@const state = stationStateOf(station.state)}
                        <li>
                            <Link href="/stations/{station.slug}">{station.name}</Link>
                            <span class="meta">
                                <i class="dot" style:background={state.color} aria-hidden="true"
                                ></i>
                                {$i18n.t(state.labelKey)}
                                {#if station.city}
                                    <span class="sep" aria-hidden="true">·</span>
                                    {station.city}
                                {/if}
                            </span>
                        </li>
                    {/each}
                </ul>
                {@render pager(
                    stationsPage,
                    () => (stationPage = stationsPage.page - 1),
                    () => (stationPage = stationsPage.page + 1),
                    $i18n.t('maps.listing.pager_stations'),
                )}
            {/if}
            {#if stationTruncated}
                <p class="hint">
                    {$i18n.t('maps.listing.truncated', {
                        shown: SERVER_LIMIT,
                        total: data.stations.total,
                    })}
                </p>
            {/if}
        </div>
    {/if}
</section>

<style>
    .column {
        min-width: 0;
    }
    h3 {
        margin: 0 0 var(--vff-space-2);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-h3);
        line-height: var(--vff-lh-heading);
    }
    h4 {
        margin: 0 0 var(--vff-space-2);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-md);
        font-weight: 600;
        line-height: var(--vff-lh-ui);
        color: var(--vff-text);
    }
    .summary {
        margin: 0 0 var(--vff-space-4);
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-body);
        line-height: var(--vff-lh-body);
        color: var(--vff-text-muted);
    }
    .summary:empty {
        margin: 0;
    }
    .muted {
        opacity: 0.8;
    }
    .block + .block {
        margin-top: var(--vff-space-5);
    }
    ul {
        display: flex;
        flex-direction: column;
        margin: 0;
        padding: 0;
        list-style: none;
        border-top: 1px solid var(--vff-border);
    }
    li {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 2px;
        padding-block: 10px;
        border-bottom: 1px solid var(--vff-border);
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-body);
        line-height: 1.4;
        overflow-wrap: anywhere;
    }
    li :global(a) {
        min-height: 24px;
        color: var(--vff-link);
        text-underline-offset: 0.2em;
    }
    .meta {
        display: inline-flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 4px 8px;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
    .dot {
        flex: none;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        box-shadow: 0 0 0 1px var(--vff-border-strong);
    }
    .km {
        font-variant-numeric: tabular-nums;
    }
    .empty,
    .hint {
        margin: 0;
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-ui);
        color: var(--vff-text-muted);
    }
    .hint {
        margin-top: var(--vff-space-2);
    }
    .pager {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: var(--vff-space-2) var(--vff-space-4);
        margin-top: var(--vff-space-3);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text);
    }
</style>

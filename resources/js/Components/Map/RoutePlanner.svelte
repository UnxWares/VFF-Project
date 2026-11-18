<script>
    // NB : pas d'élément <header> ici : header.css impose header { height: 200vh } à tous.
    import { onMount, untrack } from 'svelte';
    import { i18n } from '$lib/i18n.js';

    /**
     * Itinéraire historique : « de A à B en <année> » sur le réseau en service
     * à l'époque (GET /api/v1/route). Le parent affiche le tracé sur la carte
     * (onRoute) et aligne la carte sur l'ère retenue. Quand la carte change de
     * décennie alors qu'un itinéraire est affiché, il est recalculé pour cette
     * décennie : on voit le trajet évoluer avec le réseau. Lien partageable :
     * /maps?from=paris&to=brest&year=1930 calcule l'itinéraire à l'ouverture.
     */
    let {
        year = 2030,
        minYear = 1830,
        maxYear = 2030,
        onRoute = () => {},
        onClear = () => {},
    } = $props();

    let fromText = $state('');
    let fromSlug = $state(null);
    let toText = $state('');
    let toSlug = $state(null);
    let routeYear = $state(year);
    let heritage = $state(false);
    let loading = $state(false);
    let error = $state(null);
    let result = $state(null);
    let showStations = $state(false);
    let suggest = $state({ side: null, items: [] });
    let lastPropYear = year;
    let searchTimer = null;
    let controller = null;

    const t = (key, params) => $i18n.t(key, params);
    const km = (m) =>
        (m / 1000).toLocaleString($i18n.language || 'fr', {
            maximumFractionDigits: m < 10000 ? 1 : 0,
        });

    onMount(() => {
        const q = new URL(window.location.href).searchParams;
        if (!q.get('from') || !q.get('to')) return;
        fromText = q.get('from');
        toText = q.get('to');
        const y = Number.parseInt(q.get('year') ?? '', 10);
        if (Number.isFinite(y)) routeYear = y;
        heritage = q.get('heritage') === '1';
        submit();
    });

    // La carte a changé de décennie : suit l'année ; recalcule si un trajet est affiché.
    $effect(() => {
        if (year === lastPropYear) return;
        lastPropYear = year;
        if (result && result.era_year === year) return;
        routeYear = year;
        if (result) untrack(() => submit());
    });

    function onInput(side, value) {
        if (side === 'from') {
            fromText = value;
            fromSlug = null;
        } else {
            toText = value;
            toSlug = null;
        }
        clearTimeout(searchTimer);
        if (value.trim().length < 2) {
            suggest = { side: null, items: [] };
            return;
        }
        searchTimer = setTimeout(async () => {
            try {
                const res = await fetch(`/api/v1/search?q=${encodeURIComponent(value.trim())}`, {
                    headers: { Accept: 'application/json' },
                });
                const json = await res.json();
                suggest = { side, items: (json.stations ?? []).slice(0, 8) };
            } catch {
                suggest = { side: null, items: [] };
            }
        }, 200);
    }

    function pick(side, station) {
        if (side === 'from') {
            fromText = station.name;
            fromSlug = station.slug;
        } else {
            toText = station.name;
            toSlug = station.slug;
        }
        suggest = { side: null, items: [] };
    }

    function swap() {
        [fromText, toText] = [toText, fromText];
        [fromSlug, toSlug] = [toSlug, fromSlug];
    }

    function errorMessage(json) {
        const code = json?.error;
        const params = {
            query: json?.query ?? '',
            station: json?.station?.name ?? '',
            era: json?.era_year ?? routeYear,
        };
        return code && $i18n.exists(`itinerary.errors.${code}`)
            ? t(`itinerary.errors.${code}`, params)
            : t('itinerary.errors.generic');
    }

    async function submit(event) {
        event?.preventDefault();
        const from = fromSlug ?? fromText.trim();
        const to = toSlug ?? toText.trim();
        if (!from || !to) return;
        suggest = { side: null, items: [] };
        controller?.abort();
        controller = new AbortController();
        loading = true;
        error = null;
        const params = new URLSearchParams({ from, to, era: String(routeYear) });
        if (heritage) params.set('heritage', '1');
        try {
            const res = await fetch(`/api/v1/route?${params}`, {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            });
            const json = await res.json();
            if (!res.ok) {
                error = {
                    message: errorMessage(json),
                    candidates: json?.candidates ?? [],
                    side: json?.side,
                };
                result = null;
                onClear();
                return;
            }
            result = json;
            showStations = false;
            onRoute(json);
        } catch (e) {
            if (e?.name === 'AbortError') return;
            error = { message: t('itinerary.errors.generic'), candidates: [] };
        } finally {
            loading = false;
        }
    }

    function chooseCandidate(candidate) {
        pick(error?.side === 'to' ? 'to' : 'from', candidate);
        submit();
    }

    function clear() {
        controller?.abort();
        result = null;
        error = null;
        onClear();
    }

    const warnings = $derived.by(() => {
        if (!result) return [];
        const list = [];
        for (const code of result.warnings ?? []) {
            if (code === 'non_passenger')
                list.push(
                    t('itinerary.warnings.non_passenger', { km: km(result.non_passenger_m) }),
                );
            else if (code === 'gauge_change') {
                for (const g of result.gauge_changes ?? []) {
                    list.push(
                        t('itinerary.warnings.gauge_change', {
                            station: g.station_name,
                            from: g.from_mm,
                            to: g.to_mm,
                        }),
                    );
                }
            } else if (code === 'gap_joins')
                list.push(t('itinerary.warnings.gap_joins', { count: result.gap_joins }));
            else list.push(t(`itinerary.warnings.${code}`));
        }
        if (result.closed_stations_passed > 0) {
            list.push(
                t('itinerary.warnings.closed_stations', { count: result.closed_stations_passed }),
            );
        }
        return list;
    });
</script>

<section class="route-planner" aria-labelledby="route-planner-title">
    <div class="planner-head">
        <h2 id="route-planner-title">
            <i class="bi bi-signpost-split"></i>
            {t('itinerary.title')}
        </h2>
        <p>{t('itinerary.subtitle')}</p>
    </div>

    <form class="route-form" onsubmit={submit}>
        {#each [['from', fromText, 'itinerary.placeholder_from'], ['to', toText, 'itinerary.placeholder_to']] as [side, value, placeholder] (side)}
            <label class="field station-field">
                <span>{t(`itinerary.${side}`)}</span>
                <input
                    type="text"
                    {value}
                    placeholder={t(placeholder)}
                    autocomplete="off"
                    oninput={(e) => onInput(side, e.currentTarget.value)}
                    onkeydown={(e) => e.key === 'Escape' && (suggest = { side: null, items: [] })}
                    required
                />
                {#if suggest.side === side && suggest.items.length}
                    <ul class="suggestions" role="listbox">
                        {#each suggest.items as s (s.slug)}
                            <li>
                                <button type="button" onclick={() => pick(side, s)}>
                                    <strong>{s.name}</strong>{#if s.city}<small>{s.city}</small
                                        >{/if}
                                </button>
                            </li>
                        {/each}
                    </ul>
                {/if}
            </label>
            {#if side === 'from'}
                <button
                    type="button"
                    class="swap"
                    onclick={swap}
                    title={t('itinerary.swap')}
                    aria-label={t('itinerary.swap')}
                >
                    <i class="bi bi-arrow-left-right"></i>
                </button>
            {/if}
        {/each}
        <label class="field year-field">
            <span>{t('itinerary.year')}</span>
            <input
                type="number"
                min={minYear}
                max={maxYear}
                step="1"
                bind:value={routeYear}
                required
            />
        </label>
        <div class="actions">
            <button type="submit" class="vff-btn primary" disabled={loading}>
                {#if loading}<i class="bi bi-hourglass-split"></i>{:else}<i class="bi bi-bezier2"
                    ></i>{/if}
                {t('itinerary.submit')}
            </button>
            {#if result || error}
                <button type="button" class="link-btn" onclick={clear}
                    >{t('itinerary.clear')}</button
                >
            {/if}
        </div>
        <label class="heritage">
            <input type="checkbox" bind:checked={heritage} />
            {t('itinerary.heritage')}
        </label>
    </form>

    {#if loading}
        <p class="status" role="status">{t('itinerary.loading')}</p>
    {/if}

    {#if error}
        <div class="error" role="alert">
            <i class="bi bi-exclamation-triangle"></i>
            <div>
                <p>{error.message}</p>
                {#if error.candidates?.length}
                    <div class="candidates">
                        {#each error.candidates as c (c.slug)}
                            <button type="button" onclick={() => chooseCandidate(c)}
                                >{c.name}</button
                            >
                        {/each}
                    </div>
                {/if}
            </div>
        </div>
    {/if}

    {#if result}
        <div class="result">
            <div class="summary">
                <p class="trip">
                    <strong>{result.from.name}</strong>
                    <i class="bi bi-arrow-right"></i>
                    <strong>{result.to.name}</strong>
                </p>
                <p class="figures">
                    <span class="km">{t('itinerary.distance', { km: km(result.distance_m) })}</span>
                    <span>{t('itinerary.stations_count', { count: result.stations.length })}</span>
                    <span class="era-pill">
                        {result.era_is_current
                            ? t('itinerary.era_current')
                            : t('itinerary.era_network', { era: result.era_year })}
                    </span>
                </p>
                {#if result.requested_year && result.requested_year !== result.era_year && !result.era_is_current}
                    <p class="note">
                        {t('itinerary.era_note', {
                            era: result.era_year,
                            year: result.requested_year,
                        })}
                    </p>
                {/if}
            </div>

            <h3>{t('itinerary.lines_title')}</h3>
            <ol class="legs">
                {#each result.lines as leg, i (i)}
                    <li>
                        <span class="leg-km"
                            >{t('itinerary.distance', { km: km(leg.length_m) })}</span
                        >
                        <span class="leg-body">
                            {#if leg.line_slug}
                                <a href={`/lines/${leg.line_slug}`} title={t('itinerary.line_link')}
                                    >{leg.line_name}</a
                                >
                            {:else}
                                {leg.line_name ?? '-'}
                            {/if}
                            <small
                                >{leg.from_name ?? t('itinerary.junction')}
                                <i class="bi bi-arrow-right-short"></i>
                                {leg.to_name ?? t('itinerary.junction')}</small
                            >
                        </span>
                    </li>
                {/each}
            </ol>

            {#if warnings.length}
                <ul class="warnings">
                    {#each warnings as w, i (i)}
                        <li><i class="bi bi-info-circle"></i> {w}</li>
                    {/each}
                </ul>
            {/if}

            <button type="button" class="link-btn" onclick={() => (showStations = !showStations)}>
                {showStations
                    ? t('itinerary.hide_stations')
                    : t('itinerary.show_stations', { count: result.stations.length })}
            </button>
            {#if showStations}
                <ol class="stations">
                    {#each result.stations as s (s.slug)}
                        <li><a href={`/stations/${s.slug}`}>{s.name}</a></li>
                    {/each}
                </ol>
            {/if}
        </div>
    {/if}
</section>

<style>
    .route-planner {
        margin-top: 20px;
        padding: 20px 22px;
        background-color: var(--vff-bg-elevated);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-lg);
        box-shadow: 0 12px 32px var(--vff-shadow);
        font-family: var(--vff-font-body);
        color: var(--vff-text);
    }
    .planner-head h2 {
        margin: 0 0 4px;
        font-family: var(--vff-font-display);
        font-size: 20px;
    }
    .planner-head h2 i {
        color: var(--vff-accent);
    }
    .planner-head p {
        margin: 0 0 14px;
        color: var(--vff-text-muted);
        font-size: 14px;
    }
    .route-form {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr) 110px auto;
        gap: 10px 12px;
        align-items: end;
    }
    .field {
        display: flex;
        flex-direction: column;
        gap: 4px;
        position: relative;
        font-size: 13px;
        font-weight: 600;
        color: var(--vff-text-muted);
    }
    .field input {
        width: 100%;
        box-sizing: border-box;
        padding: 9px 12px;
        border: 1px solid var(--vff-border-strong);
        border-radius: var(--vff-radius-sm);
        background-color: var(--vff-bg);
        color: var(--vff-text);
        font: inherit;
        font-weight: 400;
        font-size: 15px;
    }
    .field input:focus {
        outline: 2px solid var(--vff-accent);
        outline-offset: 1px;
    }
    .swap {
        height: 40px;
        width: 40px;
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-pill);
        background-color: var(--vff-bg);
        color: var(--vff-text-muted);
        cursor: pointer;
    }
    .swap:hover {
        color: var(--vff-accent);
        border-color: var(--vff-accent);
    }
    .actions {
        display: flex;
        gap: 10px;
        align-items: center;
    }
    .actions .vff-btn {
        border: 0;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 10px 18px;
        white-space: nowrap;
    }
    .actions .vff-btn:disabled {
        opacity: 0.7;
        cursor: wait;
    }
    .heritage {
        grid-column: 1 / -1;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
        color: var(--vff-text-muted);
    }
    .suggestions {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        z-index: 20;
        margin: 4px 0 0;
        padding: 4px;
        list-style: none;
        background-color: var(--vff-bg);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-sm);
        box-shadow: 0 8px 24px var(--vff-shadow-strong);
    }
    .suggestions button {
        display: flex;
        width: 100%;
        justify-content: space-between;
        gap: 8px;
        padding: 7px 9px;
        border: 0;
        background: transparent;
        color: var(--vff-text);
        font: inherit;
        text-align: left;
        cursor: pointer;
        border-radius: var(--vff-radius-sm);
    }
    .suggestions button:hover,
    .suggestions button:focus {
        background-color: var(--vff-accent-soft);
    }
    .suggestions small {
        color: var(--vff-text-muted);
    }
    .status {
        margin: 14px 0 0;
        color: var(--vff-text-muted);
        font-size: 14px;
    }
    .error {
        display: flex;
        gap: 10px;
        margin-top: 14px;
        padding: 10px 14px;
        border-left: 3px solid var(--vff-accent);
        background-color: var(--vff-accent-soft);
        border-radius: var(--vff-radius-sm);
        font-size: 14px;
    }
    .error p {
        margin: 0;
    }
    .candidates {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 8px;
    }
    .candidates button {
        padding: 4px 10px;
        border: 1px solid var(--vff-border-strong);
        border-radius: var(--vff-radius-pill);
        background-color: var(--vff-bg);
        color: var(--vff-text);
        font: inherit;
        font-size: 13px;
        cursor: pointer;
    }
    .candidates button:hover {
        border-color: var(--vff-accent);
        color: var(--vff-accent);
    }
    .result {
        margin-top: 18px;
        padding-top: 16px;
        border-top: 1px solid var(--vff-border);
    }
    .trip {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
        margin: 0;
        font-family: var(--vff-font-heading);
        font-size: 18px;
    }
    .trip i {
        color: var(--vff-accent);
    }
    .figures {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 6px 16px;
        margin: 6px 0 0;
        color: var(--vff-text-muted);
        font-size: 14px;
    }
    .figures .km {
        color: var(--vff-text);
        font-weight: 700;
        font-size: 16px;
    }
    .era-pill {
        padding: 2px 10px;
        border-radius: var(--vff-radius-pill);
        background-color: var(--vff-accent-soft);
        color: var(--vff-accent);
        font-weight: 600;
    }
    .note {
        margin: 6px 0 0;
        font-size: 13px;
        color: var(--vff-text-muted);
    }
    h3 {
        margin: 16px 0 8px;
        font-family: var(--vff-font-heading);
        font-size: 15px;
    }
    .legs,
    .stations {
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .legs li {
        display: grid;
        grid-template-columns: 72px minmax(0, 1fr);
        gap: 10px;
        padding: 6px 0;
        border-bottom: 1px dashed var(--vff-border);
        font-size: 14px;
    }
    .leg-km {
        font-weight: 700;
        text-align: right;
        font-variant-numeric: tabular-nums;
    }
    .leg-body {
        display: flex;
        flex-direction: column;
        min-width: 0;
    }
    .leg-body a {
        color: var(--vff-text);
        text-decoration: none;
        overflow-wrap: anywhere;
    }
    .leg-body a:hover {
        color: var(--vff-accent);
        text-decoration: underline;
    }
    .leg-body small {
        color: var(--vff-text-muted);
    }
    .warnings {
        margin: 12px 0 0;
        padding: 0;
        list-style: none;
        font-size: 13px;
        color: var(--vff-text-muted);
    }
    .warnings li {
        padding: 2px 0;
    }
    .warnings i {
        color: var(--vff-accent);
    }
    .link-btn {
        margin-top: 10px;
        padding: 0;
        border: 0;
        background: none;
        color: var(--vff-accent);
        font: inherit;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
    }
    .actions .link-btn {
        margin-top: 0;
    }
    .stations {
        columns: 3 200px;
        margin-top: 8px;
        font-size: 13px;
    }
    .stations a {
        color: var(--vff-text);
        text-decoration: none;
    }
    .stations a:hover {
        color: var(--vff-accent);
    }
    @media (max-width: 760px) {
        .route-form {
            grid-template-columns: minmax(0, 1fr) auto;
        }
        .station-field {
            grid-column: 1 / -1;
        }
        .swap {
            grid-column: 1 / -1;
            justify-self: center;
            transform: rotate(90deg);
        }
        .year-field {
            grid-column: 1;
        }
        .actions {
            grid-column: 2;
        }
    }
</style>

<script>
    import { Link } from '@inertiajs/svelte';
    import { onMount, tick, untrack } from 'svelte';
    import Field from '$components/ui/Field.svelte';
    import StationInput from './StationInput.svelte';
    import { i18n } from '$lib/i18n.js';
    import { formatLocale } from '$lib/typo.js';

    /**
     * Itinéraire historique : « de A à B en <année> » sur le réseau en service
     * à l'époque (GET /api/v1/route). Le parent affiche le tracé sur la carte
     * (onRoute) et aligne la carte sur l'ère retenue. Quand la carte change de
     * décennie alors qu'un itinéraire est affiché, il est recalculé pour cette
     * décennie : on voit le trajet évoluer avec le réseau. Lien partageable :
     * /maps?from=paris&to=brest&year=1930 calcule l'itinéraire à l'ouverture.
     *
     * La décennie « actuelle » (currentEra, 2030) est le réseau d'aujourd'hui : le champ
     * « Année » affiche alors l'année en cours, que l'API ramène au réseau actuel.
     */
    let {
        year = 2030,
        minYear = 1830,
        maxYear = 2030,
        currentEra = null,
        mapAnchor = null,
        onRoute = () => {},
        onClear = () => {},
    } = $props();

    const uid = $props.id();
    const thisYear = new Date().getFullYear();
    const todayEra = $derived(currentEra ?? maxYear);
    // Année affichée pour une décennie de la carte : l'année en cours pour la décennie actuelle.
    const asInputYear = (y) => (y === todayEra ? thisYear : y);
    const maxInput = $derived(maxYear === todayEra ? Math.max(thisYear, minYear) : maxYear);

    let fromText = $state('');
    let fromSlug = $state(null);
    let toText = $state('');
    let toSlug = $state(null);
    let routeYear = $state(untrack(() => asInputYear(year)));
    let heritage = $state(false);
    let loading = $state(false);
    let error = $state(null);
    let fieldErrors = $state({ from: null, to: null, year: null });
    let result = $state(null);
    let showStations = $state(false);
    let lastPropYear = untrack(() => year);
    let controller = null;

    /** Traduction (toujours une chaîne : i18next la type aussi en objet). */
    const t = (/** @type {string} */ key, /** @type {Record<string, unknown>} */ params = {}) =>
        String($i18n.t(key, params));
    const km = (m) =>
        (m / 1000).toLocaleString(formatLocale($i18n.language), {
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
        routeYear = asInputYear(year);
        if (result) untrack(() => submit());
    });

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

    const yearValid = (value) => {
        const y = Number(value);
        return Number.isInteger(y) && y >= minYear && y <= maxInput;
    };

    // Vérification avant l'envoi, avec nos messages (pas la bulle du navigateur) ; le focus
    // va au premier champ en erreur.
    function validate() {
        fieldErrors = {
            from: fromText.trim() ? null : t('itinerary.errors.from_required'),
            to: toText.trim() ? null : t('itinerary.errors.to_required'),
            year: yearValid(routeYear)
                ? null
                : t('itinerary.errors.year_invalid', { min: minYear, max: maxInput }),
        };
        const first = ['from', 'to', 'year'].find((k) => fieldErrors[k]);
        if (first) tick().then(() => document.getElementById(`${uid}-${first}`)?.focus());
        return !first;
    }

    async function submit(event) {
        event?.preventDefault();
        if (!validate()) return;
        const from = fromSlug ?? fromText.trim();
        const to = toSlug ?? toText.trim();
        controller?.abort();
        controller = new AbortController();
        loading = true;
        error = null;
        const params = new URLSearchParams({
            from,
            to,
            era: String(routeYear),
            ...(heritage && { heritage: '1' }),
        });
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
        if (error?.side === 'to') {
            toText = candidate.name;
            toSlug = candidate.slug;
        } else {
            fromText = candidate.name;
            fromSlug = candidate.slug;
        }
        submit();
    }

    function clear() {
        controller?.abort();
        result = null;
        error = null;
        loading = false;
        onClear();
    }

    // Un message d'erreur disparaît dès que le champ est corrigé.
    const shownErrors = $derived({
        from: fromText.trim() ? null : fieldErrors.from,
        to: toText.trim() ? null : fieldErrors.to,
        year: yearValid(routeYear) ? null : fieldErrors.year,
    });

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

<section class="route-planner" aria-labelledby={`${uid}-title`}>
    <h2 class="vff-section-title" id={`${uid}-title`}>{t('itinerary.title')}</h2>
    <p class="planner-intro">{t('itinerary.subtitle')}</p>

    <div class="vff-panel planner-panel">
        <form class="route-form" novalidate onsubmit={submit}>
            <div class="route-from">
                <StationInput
                    id={`${uid}-from`}
                    label={t('itinerary.from')}
                    placeholder={t('itinerary.placeholder_from')}
                    bind:text={fromText}
                    bind:slug={fromSlug}
                    error={shownErrors.from}
                />
            </div>
            <button
                type="button"
                class="swap"
                onclick={swap}
                title={t('itinerary.swap')}
                aria-label={t('itinerary.swap')}
            >
                <i class="bi bi-arrow-left-right" aria-hidden="true"></i>
            </button>
            <div class="route-to">
                <StationInput
                    id={`${uid}-to`}
                    label={t('itinerary.to')}
                    placeholder={t('itinerary.placeholder_to')}
                    bind:text={toText}
                    bind:slug={toSlug}
                    error={shownErrors.to}
                />
            </div>
            <div class="route-year">
                <Field
                    id={`${uid}-year`}
                    label={t('itinerary.year')}
                    hint={t('itinerary.year_hint', { min: minYear })}
                    error={shownErrors.year}
                >
                    {#snippet children(attrs)}
                        <input
                            {...attrs}
                            class="vff-input"
                            type="number"
                            inputmode="numeric"
                            min={minYear}
                            max={maxInput}
                            step="1"
                            bind:value={routeYear}
                        />
                    {/snippet}
                </Field>
            </div>
            <div class="route-submit">
                <button type="submit" class="vff-btn primary" aria-disabled={loading}>
                    <i class="bi {loading ? 'bi-hourglass-split' : 'bi-bezier2'}" aria-hidden="true"
                    ></i>
                    {t('itinerary.submit')}
                </button>
            </div>
            <label class="vff-check route-heritage">
                <input type="checkbox" bind:checked={heritage} />
                {t('itinerary.heritage')}
            </label>
        </form>

        <p class="planner-status" role="status">{loading ? t('itinerary.loading') : ''}</p>

        <div class="planner-error" role="alert">
            {#if error}
                <div class="vff-notice danger">
                    <i class="bi bi-exclamation-octagon" aria-hidden="true"></i>
                    <div class="vff-notice-body">
                        <p>{error.message}</p>
                        {#if error.candidates?.length}
                            <div class="candidates">
                                {#each error.candidates as c (c.slug)}
                                    <button
                                        type="button"
                                        class="vff-btn secondary small"
                                        onclick={() => chooseCandidate(c)}>{c.name}</button
                                    >
                                {/each}
                            </div>
                        {/if}
                    </div>
                </div>
            {/if}
        </div>

        {#if result}
            <div class="result">
                <div class="summary">
                    <p class="trip">
                        <strong>{result.from.name}</strong>
                        <i class="bi bi-arrow-right" aria-hidden="true"></i>
                        <span class="vff-sr-only">-</span>
                        <strong>{result.to.name}</strong>
                    </p>
                    <p class="figures">
                        <span class="km"
                            >{t('itinerary.distance', { km: km(result.distance_m) })}</span
                        >
                        <span
                            >{t('itinerary.stations_count', {
                                count: result.stations.length,
                            })}</span
                        >
                        <span class="vff-badge accent">
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
                    {#if mapAnchor}
                        <a class="vff-link map-link" href={`#${mapAnchor}`}>
                            <i class="bi bi-map" aria-hidden="true"></i>
                            {t('itinerary.see_on_map')}
                        </a>
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
                                    <Link href={`/lines/${leg.line_slug}`}>{leg.line_name}</Link>
                                {:else}
                                    <span>{leg.line_name ?? '-'}</span>
                                {/if}
                                <small
                                    >{leg.from_name ?? t('itinerary.junction')}
                                    <i class="bi bi-arrow-right-short" aria-hidden="true"></i>
                                    <span class="vff-sr-only">-</span>
                                    {leg.to_name ?? t('itinerary.junction')}</small
                                >
                            </span>
                        </li>
                    {/each}
                </ol>

                {#if warnings.length}
                    <ul class="warnings">
                        {#each warnings as w, i (i)}
                            <li><i class="bi bi-info-circle" aria-hidden="true"></i> {w}</li>
                        {/each}
                    </ul>
                {/if}

                <div class="result-actions">
                    <button
                        type="button"
                        class="vff-btn quiet"
                        aria-expanded={showStations}
                        aria-controls={`${uid}-stations`}
                        onclick={() => (showStations = !showStations)}
                    >
                        {showStations
                            ? t('itinerary.hide_stations')
                            : t('itinerary.show_stations', { count: result.stations.length })}
                    </button>
                    <button type="button" class="vff-btn quiet" onclick={clear}>
                        {t('itinerary.clear')}
                    </button>
                </div>
                <ol class="stations" id={`${uid}-stations`} hidden={!showStations}>
                    {#each result.stations as s (s.slug)}
                        <li><Link href={`/stations/${s.slug}`}>{s.name}</Link></li>
                    {/each}
                </ol>
            </div>
        {/if}
    </div>
</section>

<style>
    .planner-intro {
        max-width: var(--vff-measure);
        margin: 0 0 var(--vff-space-5);
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-body);
        line-height: var(--vff-lh-body);
        color: var(--vff-text-muted);
    }

    /* Ordinateur : départ, inversion, arrivée, année, bouton sur une ligne ; les libellés au
       même niveau, les champs alignés. */
    .route-form {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 44px minmax(0, 1fr) 9.5rem auto;
        grid-template-areas:
            'from swap to year submit'
            'heritage heritage heritage heritage heritage';
        gap: 12px 16px;
        align-items: start;
    }
    .route-from {
        grid-area: from;
    }
    .route-to {
        grid-area: to;
    }
    .route-year {
        grid-area: year;
    }
    .route-submit {
        grid-area: submit;
    }
    .route-heritage {
        grid-area: heritage;
        justify-self: start;
    }
    /* Bouton d'inversion et bouton d'envoi au niveau des champs (sous la ligne des libellés). */
    .swap,
    .route-submit {
        margin-top: calc(var(--vff-fs-sm) * 1.4 + 6px);
    }
    .swap {
        grid-area: swap;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 44px;
        height: 44px;
        border: 1px solid var(--vff-border-control);
        border-radius: var(--vff-radius-sm);
        background-color: var(--vff-bg);
        color: var(--vff-text);
        cursor: pointer;
        transition:
            background-color var(--vff-transition),
            border-color var(--vff-transition);
    }
    .swap:hover {
        border-color: var(--vff-accent-ink);
        background-color: var(--vff-bg-accent);
    }
    .route-year :global(.vff-hint) {
        order: 3;
    }

    .planner-status {
        margin: 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
    .planner-status:not(:empty) {
        margin-top: 16px;
    }
    .planner-error:not(:empty) {
        margin-top: 16px;
    }
    .planner-error p {
        margin: 0;
    }
    .candidates {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 10px;
    }

    .result {
        margin-top: 24px;
        padding-top: 20px;
        border-top: 1px solid var(--vff-border);
    }
    .trip {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
        margin: 0;
        font-family: var(--vff-font-display);
        font-size: 1.25rem;
        line-height: var(--vff-lh-heading);
    }
    .trip .bi {
        color: var(--vff-text-muted);
    }
    .figures {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 6px 16px;
        margin: 8px 0 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
    .figures .km {
        font-size: var(--vff-fs-md);
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        color: var(--vff-text);
    }
    .note {
        margin: 8px 0 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
    .map-link {
        margin-top: 8px;
    }
    h3 {
        margin: 24px 0 8px;
    }
    .legs,
    .stations {
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .legs li {
        display: grid;
        grid-template-columns: 5.5rem minmax(0, 1fr);
        gap: 12px;
        padding: 8px 0;
        border-bottom: 1px solid var(--vff-border);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-ui);
    }
    .leg-km {
        font-weight: 600;
        text-align: right;
        font-variant-numeric: tabular-nums;
    }
    .leg-body {
        display: flex;
        flex-direction: column;
        min-width: 0;
        overflow-wrap: anywhere;
    }
    .leg-body :global(a) {
        display: inline-flex;
        align-items: center;
        min-height: 24px;
    }
    .leg-body small {
        font-size: var(--vff-fs-xs);
        color: var(--vff-text-muted);
    }
    .warnings {
        display: flex;
        flex-direction: column;
        gap: 4px;
        margin: 16px 0 0;
        padding: 0;
        list-style: none;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
    .warnings li {
        font-family: inherit;
    }
    .result-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 4px 16px;
        margin-top: 12px;
        margin-left: -8px;
    }
    .stations {
        columns: 3 14rem;
        column-gap: 32px;
        margin-top: 8px;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: 1.8;
    }
    .stations[hidden] {
        display: none;
    }

    /* Tablette et téléphone : départ, inversion, arrivée l'un sous l'autre ; année et bouton
       côte à côte. */
    @media (max-width: 900px) {
        .route-form {
            grid-template-columns: minmax(0, 1fr) auto;
            grid-template-areas:
                'from from'
                'swap swap'
                'to to'
                'year submit'
                'heritage heritage';
        }
        .swap {
            justify-self: center;
            margin-top: 0;
        }
        .swap .bi {
            transform: rotate(90deg);
        }
    }
    @media (max-width: 600px) {
        .route-form {
            grid-template-columns: minmax(0, 1fr);
            grid-template-areas:
                'from'
                'swap'
                'to'
                'year'
                'submit'
                'heritage';
        }
        .route-submit {
            margin-top: 0;
        }
        .route-submit .vff-btn {
            width: 100%;
        }
        .legs li {
            grid-template-columns: 4.5rem minmax(0, 1fr);
        }
    }
</style>

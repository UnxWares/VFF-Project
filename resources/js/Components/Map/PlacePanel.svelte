<script>
    import { Link } from '@inertiajs/svelte';
    import { i18n } from '$lib/i18n.js';
    import { formatNumber } from '$lib/typo.js';
    import { swatchStyle } from '$lib/railStyle.js';
    import { createListingCache } from '$lib/mapListing.js';
    import {
        chronology,
        hasWithheld,
        lineDistance,
        lineKm,
        placeKey,
        placeUrl,
        switchTarget,
    } from '$lib/placeHistory.js';
    import {
        electrificationInfo,
        isTrue,
        statusLabel,
        statusStroke,
        trafficInfo,
    } from './lineInfo.js';

    /**
     * « Qu'y avait-il ici ? » : panneau posé sur la carte (comme la fiche d'une ligne) qui dit, pour un lieu et
     * l'époque affichée, les lignes proches et leur état à cette date (kilomètre et gare la plus proche), les
     * gares proches et ce qui s'est passé là cette année-là, chaque fait avec sa date, sa source et un lien vers
     * sa fiche. Les données viennent de `GET /api/v1/what-was-here` (le service de l'outil MCP `what_was_here`).
     *
     * Props : point ({ lng, lat }), era (époque affichée), currentEra (époque « aujourd'hui »), pastEra (époque
     * proposée par le lien « voir en 1930 » ; null s'il n'y en a pas), version (version des données),
     * onClose, onSwitchEra (appelé avec l'époque vers laquelle bascule le lien).
     */
    let {
        point,
        era,
        currentEra = 2030,
        pastEra = null,
        version = null,
        onClose,
        onSwitchEra,
    } = $props();

    const NBSP = '\u00a0';
    const SETTLE_MS = 800;
    const uid = $props.id();
    const titleId = `${uid}-title`;
    const cache = createListingCache(24);
    let panelEl = $state();
    // Après un appui long, le doigt est encore posé là où le panneau vient d'apparaître : quand il se lève,
    // il ne doit toucher aucun bouton. Le panneau ignore donc les gestes pendant un court instant.
    let settling = $state(true);
    $effect(() => {
        void point.lng;
        void point.lat;
        settling = true;
        const timer = setTimeout(() => (settling = false), SETTLE_MS);
        return () => clearTimeout(timer);
    });

    const t = (/** @type {string} */ key, /** @type {Record<string, unknown>} */ params = {}) =>
        String($i18n.t(key, params));
    const lang = $derived($i18n.language ?? 'fr');

    // loading | ready | error
    let status = $state('loading');
    let data = $state.raw(null);
    let attempt = $state(0);

    $effect(() => {
        void attempt;
        const key = placeKey(point, era, lang);
        const known = cache.get(key);
        if (known) {
            data = known;
            status = 'ready';
            return;
        }
        status = 'loading';
        const controller = new AbortController();
        (async () => {
            try {
                const response = await fetch(placeUrl(point, era, currentEra, lang, version), {
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
        })();
        return () => controller.abort();
    });

    const isToday = $derived(era === currentEra);
    const target = $derived(switchTarget(era, currentEra, pastEra));
    const lines = $derived(data?.lines ?? []);
    const stations = $derived(data?.stations ?? []);
    const facts = $derived(data ? chronology(data) : []);
    const radius = $derived(data?.point?.radius_m ?? 400);
    const year = $derived(data?.year ?? era);
    const coords = $derived(
        data
            ? `${formatNumber(data.point.lat, lang, { minimumFractionDigits: 4, maximumFractionDigits: 4 })}, ${formatNumber(data.point.lng, lang, { minimumFractionDigits: 4, maximumFractionDigits: 4 })}`
            : '',
    );
    const sources = $derived(data?.attribution?.sources ?? []);

    function trafficOf(line) {
        return trafficInfo(
            { status: line.status, traffic_type: line.traffic, heritage_kind: line.heritage },
            t,
        );
    }
    function electrificationOf(line) {
        return isTrue(line.electrified) ? electrificationInfo(line.voltage_v, t, lang).label : null;
    }
    function sourceName(code) {
        const key = `lines.sources.${code}`;
        const label = String($i18n.t(key));
        return label === key ? code : label;
    }
    const metres = (m) => `${formatNumber(m, lang)}${NBSP}m`;

    // À chaque nouveau lieu, le focus va au panneau : un lecteur d'écran l'annonce, Tab mène aux liens.
    function focusOnOpen(node) {
        void point.lng;
        void point.lat;
        node.focus({ preventScroll: true });
        // Téléphone : la carte dépasse l'écran, le panneau est amené en vue.
        node.scrollIntoView({ block: 'nearest', behavior: 'auto' });
    }

    function close() {
        const hadFocus = panelEl?.contains(document.activeElement);
        const canvas = panelEl?.closest('.rail-map-wrapper')?.querySelector('.maplibregl-canvas');
        onClose();
        if (hadFocus) canvas?.focus({ preventScroll: true });
    }

    function onKeydown(e) {
        if (e.key === 'Escape' && panelEl?.contains(document.activeElement)) close();
    }
</script>

<svelte:window onkeydown={onKeydown} />

<section
    class="place"
    class:settling
    aria-labelledby={titleId}
    aria-busy={status === 'loading'}
    tabindex="-1"
    bind:this={panelEl}
    {@attach focusOnOpen}
>
    <div class="head">
        <div class="head-text">
            <h3 id={titleId}>{t('maps.here.title')}</h3>
            <p class="when">
                {isToday ? t('maps.here.today') : t('maps.here.at_year', { year: era })}
                {#if coords}<span class="where">{t('maps.here.around', { coords })}</span>{/if}
            </p>
        </div>
        <button
            type="button"
            class="close-btn"
            onclick={close}
            aria-label={t('maps.here.close')}
            title={t('maps.here.close')}
        >
            <i class="bi bi-x-lg" aria-hidden="true"></i>
        </button>
    </div>

    <div class="body">
        {#if status === 'loading'}
            <p class="note" role="status">{t('maps.here.loading')}</p>
        {:else if status === 'error'}
            <div role="alert">
                <p class="note">{t('maps.here.error')}</p>
                <button type="button" class="vff-btn secondary small" onclick={() => attempt++}>
                    {t('maps.here.retry')}
                </button>
            </div>
        {:else}
            {#if target !== null && onSwitchEra}
                <button
                    type="button"
                    class="vff-btn secondary small switch"
                    onclick={() => onSwitchEra(target)}
                >
                    <i class="bi bi-clock-history" aria-hidden="true"></i>
                    {target === currentEra
                        ? t('maps.here.see_today')
                        : t('maps.here.see_year', { year: target })}
                </button>
            {/if}

            <section aria-labelledby="{uid}-lines">
                <h4 id="{uid}-lines">{t('maps.here.lines')}</h4>
                {#if lines.length === 0}
                    <p class="note">{t('maps.here.no_lines', { radius, year: era })}</p>
                {:else}
                    <ul class="items">
                        {#each lines as line (line.slug)}
                            {@const stroke = statusStroke(line.status)}
                            {@const traffic = trafficOf(line)}
                            {@const km = lineKm(line)}
                            {@const distance = lineDistance(line)}
                            {@const electrified = electrificationOf(line)}
                            <li>
                                <Link href={`/lines/${line.slug}`} class="name">{line.name}</Link>
                                <p class="state">
                                    {#if stroke}
                                        <i
                                            class="swatch"
                                            style={swatchStyle(stroke.color, stroke.dash)}
                                            aria-hidden="true"
                                        ></i>
                                    {:else if traffic}
                                        <i
                                            class="swatch"
                                            style={swatchStyle(traffic.color)}
                                            aria-hidden="true"
                                        ></i>
                                    {/if}
                                    {statusLabel(line.status, t)}{#if traffic}, {traffic.label}{/if}{#if electrified},
                                        {electrified}{/if}{#if line.max_speed_kmh}, {formatNumber(
                                            line.max_speed_kmh,
                                            lang,
                                        )}&nbsp;km/h{/if}
                                </p>
                                {#if km || distance !== null}
                                    <p class="detail">
                                        {#if km}{km.approximate
                                                ? t('maps.here.pk_approx', { pk: km.text })
                                                : t('maps.here.pk', {
                                                      pk: km.text,
                                                  })}{/if}{#if km && distance !== null},
                                        {/if}{#if distance !== null}{t('maps.here.distance', {
                                                distance: metres(distance),
                                            })}{/if}
                                    </p>
                                {/if}
                                {#if line.nearest_station}
                                    <p class="detail">
                                        {t('maps.here.nearest_station')}
                                        <Link href={`/stations/${line.nearest_station.slug}`}
                                            >{line.nearest_station.name}</Link
                                        >, {t('maps.here.at_distance', {
                                            distance: metres(line.nearest_station.distance_m),
                                        })}
                                    </p>
                                {/if}
                            </li>
                        {/each}
                    </ul>
                    {#if hasWithheld(lines)}
                        <p class="note">{t('maps.here.withheld')}</p>
                    {/if}
                {/if}
            </section>

            <section aria-labelledby="{uid}-stations">
                <h4 id="{uid}-stations">{t('maps.here.stations')}</h4>
                {#if stations.length === 0}
                    <p class="note">{t('maps.here.no_stations', { radius, year: era })}</p>
                {:else}
                    <ul class="items">
                        {#each stations as s (s.slug)}
                            <li>
                                <Link href={`/stations/${s.slug}`} class="name">{s.name}</Link>
                                <p class="state">
                                    {s.status_label ?? '-'}{#if s.uncertain}
                                        ({t('maps.here.uncertain')}){/if}
                                </p>
                                <p class="detail">
                                    {#if s.city && s.city !== s.name}{s.city},
                                    {/if}{t('maps.here.at_distance', {
                                        distance: metres(s.distance_m),
                                    })}
                                </p>
                            </li>
                        {/each}
                    </ul>
                {/if}
            </section>

            <section aria-labelledby="{uid}-facts">
                <h4 id="{uid}-facts">
                    {isToday ? t('maps.here.facts_today') : t('maps.here.facts_year', { year })}
                </h4>
                {#if facts.length === 0}
                    <p class="note">{t('maps.here.no_facts', { radius })}</p>
                {:else}
                    <ul class="items">
                        {#each facts as fact (fact.key)}
                            <li>
                                <p class="state">
                                    <time datetime={fact.date}>{fact.when}</time>
                                    <span class="kind">{fact.label}</span>
                                </p>
                                <p class="detail">
                                    <Link href={fact.href}>{fact.subject}</Link>{#if fact.section}
                                        ({fact.section}){/if}{#if fact.toReview}
                                        ({t('maps.here.to_review')}){/if}
                                </p>
                                {#if fact.text}<p class="detail">{fact.text}</p>{/if}
                                <p class="detail src">
                                    {#if fact.kind === 'accident'}
                                        {t('maps.here.sources_on_sheet')}
                                    {:else if fact.sourceUrl}
                                        {t('maps.here.source')}
                                        <a href={fact.sourceUrl} rel="noopener" target="_blank"
                                            >{sourceName(fact.source)}<i
                                                class="bi bi-box-arrow-up-right vff-ext"
                                                aria-hidden="true"
                                            ></i><span class="vff-sr-only">
                                                ({t('common.new_tab')})</span
                                            ></a
                                        >
                                    {:else}
                                        {t('maps.here.source')}
                                        {sourceName(fact.source)}
                                    {/if}
                                </p>
                            </li>
                        {/each}
                    </ul>
                {/if}
            </section>

            {#if sources.length}
                <details class="licences">
                    <summary>{t('maps.here.licences')}</summary>
                    <ul>
                        {#each sources as src (src.provider)}
                            <li>{src.mention}</li>
                        {/each}
                    </ul>
                </details>
            {/if}
        {/if}
    </div>
</section>

<style>
    /* Ordinateur : à droite de la carte, à gauche de la colonne des boutons de zoom (52 px). */
    .place {
        position: absolute;
        top: 12px;
        right: 52px;
        z-index: 4;
        display: flex;
        flex-direction: column;
        width: min(360px, calc(100% - 64px));
        max-height: calc(100% - 24px);
        border: 1px solid var(--vff-border-strong);
        border-radius: var(--vff-radius-lg);
        background-color: var(--vff-bg-elevated);
        box-shadow: var(--vff-shadow-popover);
        color: var(--vff-text);
        animation: place-in 150ms ease-out;
    }
    .place.settling {
        pointer-events: none;
    }
    @keyframes place-in {
        from {
            opacity: 0;
        }
        to {
            opacity: 1;
        }
    }
    @media (prefers-reduced-motion: reduce) {
        .place {
            animation: none;
        }
    }

    .head {
        display: flex;
        align-items: flex-start;
        gap: 8px;
        padding: 14px 8px 12px 16px;
        border-bottom: 1px solid var(--vff-border);
    }
    .head-text {
        flex: 1;
        min-width: 0;
    }
    h3 {
        margin: 0;
        font-family: var(--vff-font-display);
        font-size: 1.125rem;
        font-weight: 700;
        line-height: var(--vff-lh-heading);
        text-wrap: balance;
    }
    .when {
        margin: 4px 0 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-ui);
        color: var(--vff-text-muted);
    }
    .where {
        display: block;
        font-variant-numeric: tabular-nums;
    }
    .close-btn {
        flex-shrink: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 44px;
        height: 44px;
        border: 0;
        border-radius: var(--vff-radius-sm);
        background: transparent;
        color: var(--vff-text-muted);
        font-size: 16px;
        cursor: pointer;
    }
    .close-btn:hover {
        background-color: var(--vff-bg-muted);
        color: var(--vff-text);
    }

    .body {
        display: flex;
        flex-direction: column;
        gap: 18px;
        min-height: 0;
        padding: 14px 16px 16px;
        overflow-y: auto;
        overscroll-behavior: contain;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-ui);
    }
    .switch {
        align-self: flex-start;
    }
    h4 {
        margin: 0 0 6px;
        font-family: var(--vff-font-display);
        font-size: var(--vff-fs-sm);
        font-weight: 700;
        color: var(--vff-text);
    }
    .items {
        display: flex;
        flex-direction: column;
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .items li {
        padding: 10px 0;
        border-bottom: 1px solid var(--vff-border);
    }
    .items li:last-child {
        border-bottom: 0;
    }
    .items :global(a.name) {
        font-weight: 700;
        overflow-wrap: anywhere;
    }
    .state,
    .detail,
    .note {
        margin: 2px 0 0;
    }
    .detail,
    .note {
        color: var(--vff-text-muted);
    }
    .kind {
        font-weight: 700;
    }
    .swatch {
        display: inline-block;
        width: 18px;
        margin-right: 6px;
        border-radius: 2px;
        vertical-align: 0.2em;
    }
    .detail :global(a),
    .src :global(a) {
        overflow-wrap: anywhere;
    }
    .licences {
        color: var(--vff-text-muted);
    }
    .licences summary {
        cursor: pointer;
        min-height: 44px;
        display: flex;
        align-items: center;
    }
    .licences ul {
        margin: 0;
        padding-left: 1.2em;
    }

    /* Téléphone : panneau en bas de la carte, sur 78 % de sa hauteur au plus. */
    @media (max-width: 600px) {
        .place {
            top: auto;
            right: 0;
            bottom: 0;
            left: 0;
            width: auto;
            border-width: 1px 0 0;
            border-radius: var(--vff-radius-lg) var(--vff-radius-lg) 0 0;
        }
        .head {
            padding: 10px 6px 10px 16px;
        }
        .place {
            max-height: 78%;
        }
        /* Les coordonnées restent lues par les lecteurs d'écran, sans prendre de place. */
        .where {
            position: absolute;
            width: 1px;
            height: 1px;
            overflow: hidden;
            clip-path: inset(50%);
            white-space: nowrap;
        }
    }
</style>

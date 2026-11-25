<script>
    import { flushSync, tick, untrack } from 'svelte';
    import { SvelteSet } from 'svelte/reactivity';
    import { i18n } from '$lib/i18n.js';
    import { formatLocale, formatNumber, NBSP } from '$lib/typo.js';
    import { cleanUrl, linkLabel } from '$lib/linkLabel.js';
    import { formatEventDate } from '$lib/linePhases.js';
    import {
        AXIS,
        ROW_KINDS,
        ZOOM_STEP,
        axisFromZones,
        axisLabel,
        buildAxis,
        detailZones,
        fitZoomFor,
        foldLayout,
        gapSummary,
        hasTracks,
        lineOverview,
        nearestRow,
        normalize,
        objectPks,
        pkLabel,
        routeRows,
        rowTitle,
    } from '$lib/trackLayout.js';
    import TrackDiagram from '$components/Lines/TrackDiagram.svelte';
    import TrackRouteTable from '$components/Lines/TrackRouteTable.svelte';
    import TrackPicto from '$components/Lines/TrackPicto.svelte';
    import TrackLayoutMap from '$components/Lines/TrackLayoutMap.svelte';

    /**
     * Schéma des voies d'une ligne, sur le modèle d'un livret de ligne (RINF Routebook) : zoom (« - », « + »,
     * « Ajuster »), onglets « Carte », « Schéma », « Bande », légende, le dessin (TrackDiagram) et le tableau de
     * parcours (TrackRouteTable), synchronisés : choisir une ligne du tableau centre le dessin, cliquer dans le
     * dessin choisit la ligne la plus proche. Pied : couverture, jeux de données, dates, licences et
     * attributions. Données : réponse de GET /api/v1/lines/{line}/track-layout (lib/trackLayout.js).
     *
     * `full` : page plein écran (/lines/{slug}/voies), tableau à droite quand la place le permet ; sinon (fiche de
     * la ligne) le tableau passe sous le dessin.
     *
     * `axisMode` : `line` (PK de la ligne) ou `route` (livret de parcours, page /routebook : plusieurs lignes bout à
     * bout sur l'axe des kilomètres parcourus, sens de marche ; sans `slug`, donc sans onglet « Carte »).
     *
     * Données : `layout` (réponse déjà lue) ou `loader` (TrackLayoutLoader : aperçu, puis la ligne entière si elle
     * est courte, ou par portions au fil du défilement et du zoom si elle est longue).
     *
     * Grandes gares : les faisceaux et voies de service d'un point d'exploitation sont repliés en un groupe
     * (« 23 voies de service ») que l'on déplie sur place (foldLayout).
     *
     * Page des voies d'une gare (/stations/{slug}/voies) : `platforms` (bords de quai de la ligne, dessinés sous les
     * voies), `stationSlug` (la gare de la page : pas de lien vers elle-même dans le tableau), `startPk` (le dessin
     * s'ouvre centré sur ce PK quand la portion ne tient pas dans la largeur, comme la page d'une ligne ouverte depuis
     * une gare ; la carte montre ce PK tant qu'aucun objet n'est choisi). `children` : contenu posé entre le
     * tableau et le pied des sources (le tableau des quais d'une gare).
     *
     * @type {{ layout?: any, loader?: import('$lib/trackLayoutLoader.svelte.js').TrackLayoutLoader|null,
     *   slug: string|null, lineName: string, full?: boolean, axisMode?: 'line'|'route',
     *   platforms?: Array<any>, stationSlug?: string|null, startPk?: number|null,
     *   children?: import('svelte').Snippet }}
     */
    let {
        layout = null,
        loader = null,
        slug,
        lineName,
        full = false,
        axisMode = 'line',
        platforms = [],
        stationSlug = null,
        startPk = null,
        children,
    } = $props();

    const t = (k, o) => String($i18n.t(k, o));
    const lang = $derived($i18n.language ?? 'fr');

    const raw = $derived(layout ?? loader?.layout ?? null);
    const data = $derived(normalize(raw));
    // Axe en deux temps : les zones de toutes les voies (pour replier les faisceaux), puis l'axe dessiné, dont les zones
    // ne viennent que des voies encore dessinées (une gare repliée reprend la largeur de ses voies visibles), dilaté là
    // où les aiguillages et traversées dessinés sont serrés (gorges de gare), 14 px à l'écran quel que soit le zoom.
    const zonesAxis = $derived(buildAxis(data));
    const allRows = $derived(routeRows(data));
    const tracksById = $derived(new Map(data.tracks.map((tr) => [tr.id, tr])));
    const detailed = $derived(hasTracks(data) || (raw?.counts?.tracks ?? 0) > 0);

    // Faisceaux et voies de service repliés par point d'exploitation ; `expanded` : zones dépliées.
    const expanded = new SvelteSet();
    // Page des voies d'une gare : les voies à quai restent dessinées, même dans une très grande gare.
    const platformTracks = $derived(new Set(platforms.map((p) => p.track).filter(Boolean)));
    const folded = $derived(foldLayout(data, zonesAxis.zones, expanded, platformTracks));
    const drawnZones = $derived(detailZones(folded.data));
    const dense = $derived(objectPks(folded.data));
    const axisAt = (z) => axisFromZones(drawnZones, data.pk, AXIS, dense, z);
    const anyClosed = $derived(folded.groups.some((g) => !g.expanded));
    // Toute une longue ligne à l'écran (rien n'est lu à cette échelle) : le dessin ne garde que la ligne et ses points
    // d'exploitation ; les voies déjà lues d'une partie, illisibles en quelques pixels, reviennent au zoom.
    const overview = $derived(Boolean(loader?.chunked && loader.tooWide));
    const drawn = $derived(overview ? lineOverview(folded.data) : folded.data);
    function toggleGroup(g) {
        if (expanded.has(g.key)) expanded.delete(g.key);
        else expanded.add(g.key);
    }
    function toggleAll() {
        const open = anyClosed;
        expanded.clear();
        if (open) for (const g of folded.groups) expanded.add(g.key);
    }

    // Lecture par portions (longues lignes) : la vue dit ses PK, le chargeur lit ce qui manque (après une courte pause
    // pendant un défilement rapide). Quand des voies arrivent, l'axe change : le PK du milieu reste au milieu.
    $effect(() => {
        const l = loader;
        untrack(() => l?.loadDetail());
    });
    let anchor = /** @type {number|null} */ (null);
    /** @type {ReturnType<typeof setTimeout>|undefined} */
    let viewTimer;
    function onview(from, to, center) {
        anchor = center;
        if (!loader?.chunked) return;
        clearTimeout(viewTimer);
        viewTimer = setTimeout(() => loader.ensure(from, to), 150);
    }
    $effect(() => () => clearTimeout(viewTimer));
    // Des voies arrivent (zones ou gorges changées) : le PK du milieu reste au milieu. Un changement de zoom recentre
    // de lui-même (setZoom).
    $effect(() => {
        drawnZones;
        dense;
        untrack(() => {
            const pk = anchor;
            if (pk === null || !loader?.chunked) return;
            tick().then(() => diagram?.centerOn(pk, false));
        });
    });

    let tab = $state(/** @type {'map'|'schema'|'band'} */ ('schema'));
    // Livret de parcours (sans `slug` : plusieurs lignes bout à bout) : pas d'onglet « Carte » d'une ligne.
    const TABS = $derived(
        /** @type {Array<'map'|'schema'|'band'>} */ (
            slug ? ['map', 'schema', 'band'] : ['schema', 'band']
        ),
    );
    let zoom = $state(1);
    const axis = $derived(axisAt(zoom));
    let printing = $state(false);
    /** @type {string|null} */
    let selected = $state(null);
    /** Genres décochés au départ dans « Afficher » : bornes kilométriques, heurtoirs, passages à niveau, traversées. */
    const HIDDEN_KINDS = {
        milestone: true,
        buffer_stop: true,
        level_crossing: true,
        crossing: true,
    };
    let announce = $state('');

    // Genres d'objets présents, choisis dans le menu « Afficher » ; le tableau et la navigation au clavier les suivent.
    // Les manques de la couverture ne sont pas des lignes du tableau : un bandeau au-dessus les dit.
    const kinds = $derived(
        ROW_KINDS.filter((k) => k !== 'missing' && allRows.some((r) => r.kind === k)),
    );
    /** @type {Record<string, boolean>} */
    let hidden = $state({ ...HIDDEN_KINDS });
    const rows = $derived(allRows.filter((r) => r.kind !== 'missing' && !hidden[r.kind]));
    const shownKinds = $derived(kinds.filter((k) => !hidden[k]).length);
    const selectedRow = $derived(allRows.find((r) => r.key === selected) ?? null);
    const counts = $derived(
        Object.fromEntries(kinds.map((k) => [k, allRows.filter((r) => r.kind === k).length])),
    );

    /** @type {any} */
    let diagram = $state();
    /** @type {any} */
    let table = $state();

    const ZOOM_MAX = 6;
    const zoomMin = () => Math.min(0.5, fitNow());
    function fitNow() {
        const view = diagram?.viewWidth() ?? 0;
        return fitZoomFor((z) => axisAt(z).width, view, 58);
    }

    async function setZoom(next) {
        const pk = selectedRow?.pk_m ?? diagram?.centerPk() ?? data.pk.start_m;
        zoom = Math.min(ZOOM_MAX, Math.max(zoomMin(), next));
        await tick();
        diagram?.centerOn(pk, false);
    }
    const zoomIn = () => setZoom(zoom * ZOOM_STEP);
    const zoomOut = () => setZoom(zoom / ZOOM_STEP);
    const fit = () => setZoom(fitNow());

    const describe = (row) =>
        `${rowTitle(row, t, lang)}, ${
            axisMode === 'route'
                ? t('tracks.route.cursor', { km: axisLabel(row.pk_m, 'route', lang) })
                : t('tracks.cursor_pk', { pk: pkLabel(row.pk_m) })
        }`;

    /** Ligne choisie dans le tableau : le dessin se centre sur son PK. */
    function fromTable(row) {
        selected = row.key;
        if (tab !== 'map') diagram?.centerOn(row.pk_m);
    }

    /** Clic dans le dessin : la ligne la plus proche est choisie, le tableau défile jusqu'à elle. */
    async function fromDiagram(index) {
        const row = rows[index];
        if (!row) return;
        selected = row.key;
        await tick();
        table?.reveal(row.key);
    }

    /** Clavier dans le dessin : objet précédent ou suivant, extrémités, zoom. */
    async function onkey(event) {
        const at = rows.findIndex((r) => r.key === selected);
        const from = at >= 0 ? at : nearestRow(rows, diagram?.centerPk() ?? 0);
        let next = null;
        if (event.key === 'ArrowRight') next = Math.min(rows.length - 1, at < 0 ? from : at + 1);
        else if (event.key === 'ArrowLeft') next = Math.max(0, at < 0 ? from : at - 1);
        else if (event.key === 'Home') next = 0;
        else if (event.key === 'End') next = rows.length - 1;
        else if (event.key === '+' || event.key === '=') zoomIn();
        else if (event.key === '-' || event.key === '_') zoomOut();
        else return;
        event.preventDefault();
        if (next === null || !rows[next]) return;
        const row = rows[next];
        selected = row.key;
        announce = describe(row);
        diagram?.centerOn(row.pk_m);
        await tick();
        table?.reveal(row.key);
    }

    // Onglets : flèches gauche et droite entre les onglets (motif d'onglets de l'ARIA).
    function onTabKey(event) {
        const i = TABS.indexOf(tab);
        let j = null;
        if (event.key === 'ArrowRight') j = (i + 1) % TABS.length;
        else if (event.key === 'ArrowLeft') j = (i + TABS.length - 1) % TABS.length;
        else if (event.key === 'Home') j = 0;
        else if (event.key === 'End') j = TABS.length - 1;
        if (j === null) return;
        event.preventDefault();
        tab = TABS[j];
        /** @type {HTMLElement|null} */ (
            event.currentTarget.parentElement?.querySelector(`#tl-tab-${tab}`)
        )?.focus();
    }

    // Ligne courte : à l'ouverture, le dessin remplit la largeur (jamais en dessous de 100 %).
    // Une longue ligne s'ouvre sur son premier point d'exploitation (pas sur un début de tracé sans rien), ou sur le
    // PK demandé (`startPk` : lien depuis la page des voies d'une gare), au zoom 100 %.
    let fitted = false;
    $effect(() => {
        if (fitted || !diagram) return;
        fitted = true;
        const at = untrack(() => startPk);
        const f = fitNow();
        if (at !== null && Number.isFinite(at) && f <= 1) {
            anchor = at;
            tick().then(() => diagram?.centerOn(at, false));
            return;
        }
        if (f > 1) zoom = Math.min(ZOOM_MAX, f);
        else {
            const first = untrack(() => data.operational_points[0]?.pk_m);
            if (first !== undefined) tick().then(() => diagram?.startAt(first));
        }
    });

    // Impression : toute la ligne dans la largeur de la page, puis le zoom d'avant.
    $effect(() => {
        let before = 1;
        const start = () => {
            before = zoom;
            flushSync(() => {
                printing = true;
                zoom = fitNow();
            });
        };
        const end = () => {
            printing = false;
            zoom = before;
        };
        addEventListener('beforeprint', start);
        addEventListener('afterprint', end);
        return () => {
            removeEventListener('beforeprint', start);
            removeEventListener('afterprint', end);
        };
    });

    const km = (m) => `${formatNumber(m / 1000, lang, { maximumFractionDigits: 1 })}${NBSP}km`;
    /** Noms propres des sources, identiques dans les trois langues. */
    const SOURCE_NAMES = { rinf: 'RINF', osm: 'OpenStreetMap', vff: 'VFF' };
    // Longueur décrite par chaque source (voies) ; une source absente de la ligne n'est pas citée.
    const coverageText = $derived.by(() => {
        const parts = ['rinf', 'osm', 'vff']
            .filter((k) => (data.coverage[`${k}_m`] ?? 0) > 0)
            .map((k) =>
                t('tracks.coverage_part', {
                    source: SOURCE_NAMES[k],
                    km: km(data.coverage[`${k}_m`]),
                }),
            );
        return parts.length
            ? t('tracks.coverage', { list: parts.join(', '), total: km(data.coverage.total_m) })
            : '';
    });
    // Bandeau des manques : une famille par élément (« signaux sur toute la longueur, voies sur 2,1 km »).
    const missingSentence = $derived.by(() => {
        const gaps = gapSummary(data.coverage.missing, data.coverage.total_m);
        if (!gaps.length) return '';
        const list = gaps.map((g) =>
            t(g.all ? 'tracks.gaps.all' : 'tracks.gaps.part', {
                what: t(`tracks.gaps.what.${g.what}`),
                km: km(g.metres),
            }),
        );
        return t('tracks.coverage_missing', { list: list.join(', ') });
    });
    const dateOf = (iso) => formatEventDate(iso, 'day', formatLocale(lang));
    const label = $derived(
        t(
            axisMode === 'route'
                ? `tracks.route.${tab === 'band' ? 'band_label' : 'schema_label'}`
                : tab === 'band'
                  ? 'tracks.band_label'
                  : 'tracks.schema_label',
            {
                from: axisLabel(data.pk.start_m, axisMode, lang),
                to: axisLabel(data.pk.end_m, axisMode, lang),
            },
        ),
    );
    // Lecture par portions : ce qui se passe, en une phrase (rien quand tout est lu).
    const partState = $derived(
        !loader?.chunked
            ? ''
            : loader.failed
              ? t('tracks.parts.failed')
              : loader.tooWide
                ? t('tracks.parts.too_wide')
                : loader.loading
                  ? t('tracks.parts.loading')
                  : '',
    );
    const LEGEND = $derived([
        'travelled',
        'main',
        'service',
        'derived',
        ...(platforms.length ? ['platform'] : []),
        'switch',
        'crossing',
        'buffer_stop',
        'signal',
        'level_crossing',
        'zone',
    ]);
    const legendKey = (k) => (k === 'buffer_stop' ? 'buffer' : k);
</script>

{#snippet ext()}<i class="bi bi-box-arrow-up-right vff-ext" aria-hidden="true"></i><span
        class="vff-sr-only"
    >
        ({t('common.new_tab')})</span
    >{/snippet}

{#if loader && !raw}
    <p class="state" role={loader.detail === 'error' ? 'alert' : 'status'}>
        {#if loader.detail === 'error'}
            {t('tracks.error')}
            <button type="button" class="vff-btn secondary" onclick={() => loader.loadDetail()}
                >{t('tracks.retry')}</button
            >
        {:else}
            {t('tracks.loading')}
        {/if}
    </p>
{:else}
    <div class={['track-layout', full && 'full']}>
        <div class="bar">
            <div class="zoom" role="group" aria-label={t('tracks.toolbar')}>
                <button
                    type="button"
                    class="vff-btn secondary sq"
                    onclick={zoomOut}
                    disabled={tab === 'map'}
                    aria-label={t('tracks.zoom_out')}
                    title={t('tracks.zoom_out')}><span aria-hidden="true">−</span></button
                >
                <button
                    type="button"
                    class="vff-btn secondary sq"
                    onclick={zoomIn}
                    disabled={tab === 'map'}
                    aria-label={t('tracks.zoom_in')}
                    title={t('tracks.zoom_in')}><span aria-hidden="true">+</span></button
                >
                <button
                    type="button"
                    class="vff-btn secondary"
                    onclick={fit}
                    disabled={tab === 'map'}
                    title={t('tracks.fit_label')}>{t('tracks.fit')}</button
                >
                <span class="level" aria-live="polite"
                    >{t('tracks.zoom_level', {
                        percent: formatNumber(zoom, lang, {
                            style: 'percent',
                            maximumFractionDigits: 0,
                        }),
                    })}</span
                >
            </div>
            <div class="tabs" role="tablist" aria-label={t('tracks.tabs_label')}>
                {#each TABS as k (k)}
                    <button
                        type="button"
                        role="tab"
                        id="tl-tab-{k}"
                        aria-selected={tab === k}
                        aria-controls="tl-panel"
                        tabindex={tab === k ? 0 : -1}
                        class={['tab', tab === k && 'on']}
                        onclick={() => (tab = k)}
                        onkeydown={onTabKey}>{t(`tracks.tab.${k}`)}</button
                    >
                {/each}
            </div>
            <div class="actions">
                {#if folded.groups.length}
                    <button
                        type="button"
                        class="vff-btn secondary"
                        onclick={toggleAll}
                        disabled={tab !== 'schema'}
                        >{t(
                            anyClosed ? 'tracks.fold.expand_all' : 'tracks.fold.collapse_all',
                        )}</button
                    >
                {/if}
                <button type="button" class="vff-btn ghost" onclick={() => print()}
                    >{t('tracks.print')}</button
                >
            </div>
        </div>

        <div class="legend">
            <p class="vff-sr-only">{t('tracks.legend.title')}</p>
            <ul>
                {#each LEGEND as k (k)}
                    <li><TrackPicto kind={k} />{t(`tracks.legend.${legendKey(k)}`)}</li>
                {/each}
            </ul>
            <p class="note">
                {t(axisMode === 'route' ? 'tracks.route.note' : 'tracks.legend.note')}
                {#if folded.groups.length}{t('tracks.fold.note')}{/if}
                {t('tracks.legend.merged')}
            </p>
        </div>

        <div class="body">
            <div class="main" id="tl-panel" role="tabpanel" aria-labelledby="tl-tab-{tab}">
                {#if tab === 'map'}
                    <TrackLayoutMap {slug} {lineName} pk={selectedRow?.pk_m ?? startPk ?? null} />
                {:else}
                    {#if tab === 'schema' && !detailed}
                        <p class="no-schema">{t('tracks.no_schema')}</p>
                    {/if}
                    <p class="parts" role="status">{partState}</p>
                    <TrackDiagram
                        bind:this={diagram}
                        data={drawn}
                        groups={overview ? [] : folded.groups}
                        ontoggle={toggleGroup}
                        {onview}
                        {axis}
                        {axisMode}
                        {rows}
                        {zoom}
                        mode={tab}
                        selectedPk={selectedRow?.pk_m ?? null}
                        {printing}
                        {label}
                        helpId="tl-help"
                        {lang}
                        {t}
                        onpick={fromDiagram}
                        {onkey}
                        {platforms}
                    />
                    <p class="help" id="tl-help">{t('tracks.schema_help')}</p>
                {/if}
                <p class="vff-sr-only" aria-live="polite">{announce}</p>
            </div>

            <div class="aside">
                {#if missingSentence}
                    <p class="gaps">{missingSentence}</p>
                {/if}
                <div class="table-bar">
                    {#if kinds.length > 1}
                        <details class="show">
                            <summary class="vff-btn secondary"
                                >{t('tracks.table.show', {
                                    shown: formatNumber(shownKinds, lang),
                                    total: formatNumber(kinds.length, lang),
                                })}</summary
                            >
                            <fieldset class="filters">
                                <legend class="vff-sr-only">{t('tracks.table.filter')}</legend>
                                {#each kinds as k (k)}
                                    <label class="check">
                                        <input
                                            type="checkbox"
                                            checked={!hidden[k]}
                                            onchange={(e) => (hidden[k] = !e.currentTarget.checked)}
                                        />
                                        <TrackPicto kind={k} size={18} />
                                        <span>{t(`tracks.kind.${k}`)}</span>
                                        <span class="n">{formatNumber(counts[k], lang)}</span>
                                    </label>
                                {/each}
                            </fieldset>
                        </details>
                    {/if}
                    <p class="badges-note">{t('tracks.table.badges')}</p>
                </div>
                <TrackRouteTable
                    bind:this={table}
                    {rows}
                    {selected}
                    {lang}
                    {t}
                    {tracksById}
                    {axisMode}
                    {stationSlug}
                    onselect={fromTable}
                />
            </div>
        </div>

        {@render children?.()}

        <footer class="sources">
            <p>
                {t('tracks.today')}
                {coverageText}
            </p>
            {#if data.sources.length}
                <h3>{t('tracks.sources_title')}</h3>
                <ul>
                    {#each data.sources as s (s.key)}
                        <li>
                            <strong>{s.label}</strong>{#if s.dataset}, {s.dataset}{/if}{#if s.fetched_at},
                                {t('tracks.fetched', {
                                    date: dateOf(s.fetched_at),
                                })}{/if}{#if s.licence && s.licence_url},
                                <a
                                    href={cleanUrl(s.licence_url)}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    >{t('tracks.licence', { licence: s.licence })}{@render ext()}</a
                                >{:else if s.licence},
                                {t('tracks.licence', {
                                    licence: s.licence,
                                })}{/if}{#if s.attribution}{` (${s.attribution})`}{/if}{#if s.url},
                                <a href={cleanUrl(s.url)} target="_blank" rel="noopener noreferrer"
                                    >{linkLabel(s.url, 48, lang)}{@render ext()}</a
                                >{/if}
                        </li>
                    {/each}
                </ul>
            {/if}
        </footer>
    </div>
{/if}

<style>
    .state {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: var(--vff-space-3);
        margin: 0;
        color: var(--vff-text-muted);
    }
    .track-layout {
        --tl-zone: color-mix(in srgb, var(--vff-bg-muted) 75%, transparent);
        --tl-platform: color-mix(in srgb, var(--vff-text) 16%, var(--vff-bg-elevated));
        --tl-platform-edge: color-mix(in srgb, var(--vff-text) 45%, transparent);
        --tl-table-h: 28rem;
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-4);
        min-width: 0;
        container-type: inline-size;
    }
    .bar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: var(--vff-space-3) var(--vff-space-5);
    }
    .zoom,
    .actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: var(--vff-space-2);
    }
    .sq {
        min-width: 44px;
        padding-inline: 0;
        font-size: 1.25em;
        line-height: 1;
    }
    .level {
        margin-left: var(--vff-space-2);
        color: var(--vff-text-muted);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        font-variant-numeric: tabular-nums;
    }
    .tabs {
        display: inline-flex;
        gap: 4px;
        padding: 4px;
        border: 1px solid var(--vff-border-strong);
        border-radius: var(--vff-radius-sm);
        background: var(--vff-bg-subtle);
    }
    .tab {
        min-height: 40px;
        padding: 6px 16px;
        border: 0;
        border-radius: 6px;
        background: transparent;
        color: var(--vff-text);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        cursor: pointer;
    }
    .tab:hover {
        background: var(--vff-bg-muted);
    }
    .tab.on {
        background: var(--vff-bg-elevated);
        box-shadow:
            0 0 0 1px var(--vff-border-strong),
            0 1px 2px var(--vff-shadow);
        font-weight: 700;
    }
    .tab:focus-visible {
        outline: var(--vff-focus-width) solid var(--vff-focus);
        outline-offset: 1px;
    }
    .legend ul {
        display: flex;
        flex-wrap: wrap;
        gap: 6px 20px;
        margin: 0;
        padding: 0;
        list-style: none;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-xs);
    }
    .legend li {
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .parts:empty {
        margin: 0;
    }
    .parts {
        margin: 0 0 var(--vff-space-2);
        color: var(--vff-text-muted);
        font-size: var(--vff-fs-sm);
    }
    .note,
    .help,
    .badges-note,
    .no-schema {
        margin: var(--vff-space-2) 0 0;
        color: var(--vff-text-muted);
        font-size: var(--vff-fs-xs);
        line-height: var(--vff-lh-ui);
    }
    .no-schema {
        margin: 0 0 var(--vff-space-3);
        font-size: var(--vff-fs-sm);
    }
    .body {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        gap: var(--vff-space-5);
    }
    .main,
    .aside {
        min-width: 0;
    }
    .aside {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-3);
    }
    .gaps {
        margin: 0;
        padding: 8px 14px;
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-sm);
        background: var(--vff-bg-subtle);
        color: var(--vff-text-muted);
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-ui);
    }
    .table-bar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: var(--vff-space-2) var(--vff-space-4);
    }
    .table-bar .badges-note {
        margin: 0;
    }
    .show {
        position: relative;
    }
    .show summary {
        list-style: none;
        cursor: pointer;
    }
    .show summary::-webkit-details-marker {
        display: none;
    }
    .show summary::after {
        content: '';
        width: 0.45em;
        height: 0.45em;
        margin-left: 0.6em;
        border-right: 2px solid currentColor;
        border-bottom: 2px solid currentColor;
        transform: translateY(-0.15em) rotate(45deg);
    }
    .show[open] summary::after {
        transform: translateY(0.1em) rotate(-135deg);
    }
    .filters {
        position: absolute;
        z-index: 3;
        top: calc(100% + 6px);
        left: 0;
        display: grid;
        gap: 2px;
        width: max-content;
        min-width: min(21rem, calc(100vw - 2 * var(--vff-gutter)));
        max-width: calc(100vw - 2 * var(--vff-gutter));
        margin: 0;
        padding: 8px;
        border: 1px solid var(--vff-border-strong);
        border-radius: var(--vff-radius-md);
        background: var(--vff-bg-elevated);
        box-shadow: var(--vff-shadow-popover);
    }
    .show:not([open]) .filters {
        display: none;
    }
    .check {
        display: flex;
        align-items: center;
        gap: 10px;
        min-height: 40px;
        padding: 4px 8px;
        border-radius: var(--vff-radius-sm);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        cursor: pointer;
    }
    .check:hover {
        background: var(--vff-bg-muted);
    }
    .check:has(input:focus-visible) {
        outline: var(--vff-focus-width) solid var(--vff-focus);
        outline-offset: 0;
    }
    .check input {
        width: 18px;
        height: 18px;
        margin: 0;
        accent-color: var(--vff-accent-strong);
    }
    .check .n {
        margin-left: auto;
        padding-left: 12px;
        color: var(--vff-text-muted);
        font-variant-numeric: tabular-nums;
    }
    .sources {
        padding-top: var(--vff-space-4);
        border-top: 1px solid var(--vff-border);
        color: var(--vff-text-muted);
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-body);
    }
    .sources p {
        max-width: var(--vff-measure);
        margin: 0;
    }
    .sources h3 {
        margin: var(--vff-space-4) 0 var(--vff-space-2);
        color: var(--vff-text);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-md);
    }
    .sources ul {
        margin: 0;
        padding-left: 1.2em;
        /* Jeux de données et adresses longues (graphe de l'ERA) : coupés plutôt que d'élargir la page à 320 px. */
        overflow-wrap: anywhere;
    }
    .sources strong {
        color: var(--vff-text);
    }

    /* Page plein écran : le tableau à droite du dessin quand la place le permet. */
    .full {
        --tl-table-h: calc(100dvh - 18rem);
    }
    @container (min-width: 1500px) {
        .full .body {
            grid-template-columns: minmax(0, 1fr) minmax(32rem, 40rem);
            align-items: start;
        }
    }
    @media (max-width: 600px) {
        .bar {
            justify-content: flex-start;
        }
    }
    @media print {
        .bar,
        .filters,
        .help {
            display: none;
        }
        .body {
            display: block;
        }
        .aside {
            margin-top: var(--vff-space-4);
        }
    }
</style>

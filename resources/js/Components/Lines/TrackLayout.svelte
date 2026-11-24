<script>
    import { flushSync, tick } from 'svelte';
    import { i18n } from '$lib/i18n.js';
    import { formatLocale, formatNumber, NBSP } from '$lib/typo.js';
    import { cleanUrl, linkLabel } from '$lib/linkLabel.js';
    import { formatEventDate } from '$lib/linePhases.js';
    import {
        ROW_KINDS,
        ZOOM_STEP,
        buildAxis,
        fitZoom,
        hasTracks,
        laneRows,
        nearestRow,
        normalize,
        pkLabel,
        missingText,
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
     * @type {{ layout: any, slug: string, lineName: string, full?: boolean }}
     */
    let { layout, slug, lineName, full = false } = $props();

    const t = (k, o) => String($i18n.t(k, o));
    const lang = $derived($i18n.language ?? 'fr');

    const data = $derived(normalize(layout));
    const axis = $derived(buildAxis(data));
    const lanes = $derived(laneRows(data.tracks));
    const allRows = $derived(routeRows(data));
    const tracksById = $derived(new Map(data.tracks.map((tr) => [tr.id, tr])));
    const detailed = $derived(hasTracks(data));

    let tab = $state(/** @type {'map'|'schema'|'band'} */ ('schema'));
    const TABS = /** @type {const} */ (['map', 'schema', 'band']);
    let zoom = $state(1);
    let printing = $state(false);
    /** @type {string|null} */
    let selected = $state(null);
    let announce = $state('');

    // Genres d'objets présents, tous affichés au départ ; le tableau et la navigation au clavier les suivent.
    const kinds = $derived(ROW_KINDS.filter((k) => allRows.some((r) => r.kind === k)));
    /** @type {Record<string, boolean>} */
    let hidden = $state({});
    const rows = $derived(allRows.filter((r) => !hidden[r.kind]));
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
        return fitZoom(axis.width, view, 58);
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
        `${rowTitle(row, t, lang)}, ${t('tracks.cursor_pk', { pk: pkLabel(row.pk_m) })}`;

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
    let fitted = false;
    $effect(() => {
        if (fitted || !diagram) return;
        fitted = true;
        const f = fitNow();
        if (f > 1) zoom = Math.min(ZOOM_MAX, f);
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
    const missingSentence = $derived(
        data.coverage.missing.length
            ? t('tracks.coverage_missing', {
                  list: data.coverage.missing
                      .map((m) => missingText(m, t))
                      // Après les deux-points, en minuscule (les noms allemands gardent leur capitale).
                      .map((text) =>
                          lang === 'de' ? text : text.charAt(0).toLowerCase() + text.slice(1),
                      )
                      .join(', '),
              })
            : '',
    );
    const dateOf = (iso) => formatEventDate(iso, 'day', formatLocale(lang));
    const label = $derived(
        t(tab === 'band' ? 'tracks.band_label' : 'tracks.schema_label', {
            from: pkLabel(data.pk.start_m),
            to: pkLabel(data.pk.end_m),
        }),
    );
    const LEGEND = [
        'travelled',
        'main',
        'service',
        'switch',
        'crossing',
        'buffer_stop',
        'signal',
        'level_crossing',
        'zone',
    ];
    const legendKey = (k) => (k === 'buffer_stop' ? 'buffer' : k);
</script>

{#snippet ext()}<i class="bi bi-box-arrow-up-right vff-ext" aria-hidden="true"></i><span
        class="vff-sr-only"
    >
        ({t('common.new_tab')})</span
    >{/snippet}

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
        <button type="button" class="vff-btn ghost" onclick={() => print()}
            >{t('tracks.print')}</button
        >
    </div>

    <div class="legend">
        <p class="vff-sr-only">{t('tracks.legend.title')}</p>
        <ul>
            {#each LEGEND as k (k)}
                <li><TrackPicto kind={k} />{t(`tracks.legend.${legendKey(k)}`)}</li>
            {/each}
        </ul>
        <p class="note">{t('tracks.legend.note')}</p>
    </div>

    <div class="body">
        <div class="main" id="tl-panel" role="tabpanel" aria-labelledby="tl-tab-{tab}">
            {#if tab === 'map'}
                <TrackLayoutMap {slug} {lineName} pk={selectedRow?.pk_m ?? null} />
            {:else}
                {#if tab === 'schema' && !detailed}
                    <p class="no-schema">{t('tracks.no_schema')}</p>
                {/if}
                <TrackDiagram
                    bind:this={diagram}
                    {data}
                    {axis}
                    {lanes}
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
                />
                <p class="help" id="tl-help">{t('tracks.schema_help')}</p>
            {/if}
            <p class="vff-sr-only" aria-live="polite">{announce}</p>
        </div>

        <div class="aside">
            {#if kinds.length > 1}
                <fieldset class="filters">
                    <legend class="vff-label">{t('tracks.table.filter')}</legend>
                    <div class="checks">
                        {#each kinds as k (k)}
                            <label class="chip">
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
                    </div>
                </fieldset>
            {/if}
            <p class="badges-note">{t('tracks.table.badges')}</p>
            <TrackRouteTable
                bind:this={table}
                {rows}
                {selected}
                {lang}
                {t}
                {tracksById}
                onselect={fromTable}
            />
        </div>
    </div>

    <footer class="sources">
        <p>
            {t('tracks.today')}
            {coverageText}
            {missingSentence}
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

<style>
    .track-layout {
        --tl-zone: color-mix(in srgb, var(--vff-bg-muted) 75%, transparent);
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
    .zoom {
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
    .filters {
        min-width: 0;
        margin: 0;
        padding: 0;
        border: 0;
    }
    .filters legend {
        margin-bottom: 6px;
        padding: 0;
    }
    .checks {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }
    .chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        min-height: 40px;
        padding: 4px 12px 4px 8px;
        border: 1px solid var(--vff-border-strong);
        border-radius: var(--vff-radius-pill);
        background: var(--vff-bg-elevated);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-xs);
        cursor: pointer;
    }
    .chip:has(input:focus-visible) {
        outline: var(--vff-focus-width) solid var(--vff-focus);
        outline-offset: var(--vff-focus-offset);
    }
    .chip input {
        width: 18px;
        height: 18px;
        margin: 0;
        accent-color: var(--vff-accent-strong);
    }
    .chip .n {
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

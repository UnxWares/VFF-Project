<script>
    import { formatNumber, NBSP } from '$lib/typo.js';
    import { formatPermille, linePk, pkLabel, rowTitle } from '$lib/trackLayout.js';
    import TrackPicto from '$components/Lines/TrackPicto.svelte';

    /**
     * Tableau de parcours du schéma des voies : un objet par ligne, trié par PK (km parcourus, vitesse,
     * déclivité, PK de ligne, objet, provenance). C'est l'alternative textuelle du schéma : chaque objet dessiné
     * y figure. Le bouton de l'objet centre le schéma sur lui ; un clic dans le schéma choisit la ligne la plus
     * proche, que le tableau fait défiler dans son propre cadre (jamais la page). Un point d'exploitation rattaché à
     * une gare de VFF (`station_slug`) a un lien vers sa fiche et vers ses voies (/stations/{slug}/voies), sauf la
     * gare de la page (`stationSlug`, page des voies d'une gare).
     *
     * `axisMode` : `route` sur un livret de parcours (sens des signaux dit par rapport au sens de marche, colonne des
     * kilomètres parcourus : plusieurs lignes bout à bout). Sur une ligne, le PK de ligne suffit : la colonne des
     * kilomètres parcourus n'est pas répétée, l'objet a la place d'être lu sur une ligne.
     *
     * @type {{
     *   rows: Array<any>, selected?: string|null, lang: string, t: (key: string, options?: object) => string,
     *   onselect: (row: any) => void, tracksById: Map<string, any>, axisMode?: 'line'|'route',
     *   stationSlug?: string|null,
     * }}
     */
    let {
        rows,
        selected = null,
        lang,
        t,
        onselect,
        tracksById,
        axisMode = 'line',
        stationSlug = null,
    } = $props();

    /** @type {HTMLDivElement|undefined} */
    let wrap = $state();

    const km = (v) => formatNumber(v, lang, { minimumFractionDigits: 1, maximumFractionDigits: 1 });
    const permille = (v) => (v === null ? '' : formatPermille(v, lang));

    const PICTO = { crossing: 'crossing', op: 'op' };

    /** Précision de l'objet, en petit sous le libellé. */
    function detail(row) {
        const it = row.item;
        const parts = [];
        if (row.kind === 'op') {
            parts.push(t(`tracks.op.${it.kind}`, { defaultValue: t('tracks.op.other') }));
            if (it.uopid) parts.push(it.uopid);
        }
        // Vitesse ou déclivité d'une section parallèle à une autre : ses voies.
        if ((row.kind === 'speed' || row.kind === 'gradient') && row.tracks) {
            parts.push(
                row.tracks.length === 1
                    ? t('tracks.row.on_track', { ref: row.tracks[0] })
                    : t('tracks.row.on_tracks', { refs: row.tracks.join(', ') }),
            );
        }
        if (row.kind === 'signal' || row.kind === 'switch' || row.kind === 'buffer_stop') {
            const ref = tracksById.get(it.track ?? it.from_track)?.ref;
            if (ref) parts.push(t('tracks.row.on_track', { ref }));
        }
        if (row.kind === 'signal' && (it.direction === 'up' || it.direction === 'down')) {
            parts.push(
                t(
                    axisMode === 'route'
                        ? `tracks.route.${it.direction}`
                        : `tracks.row.${it.direction}`,
                ),
            );
        }
        return parts.join(' · ');
    }

    function sourceText(prov) {
        const name = t(`tracks.source.${prov.key}`);
        return prov.approx ? `${name}, ${t('tracks.source.approx')}` : name;
    }

    /** Fait défiler le cadre du tableau jusqu'à la ligne (synchronisation depuis le schéma). */
    export function reveal(key) {
        const tr = wrap?.querySelector(`tr[data-key="${CSS.escape(key)}"]`);
        if (!wrap || !(tr instanceof HTMLElement)) return;
        const head = wrap.querySelector('thead')?.getBoundingClientRect().height ?? 0;
        const at =
            tr.getBoundingClientRect().top - wrap.getBoundingClientRect().top + wrap.scrollTop;
        const top = at - head - 8;
        const bottom = at + tr.offsetHeight - wrap.clientHeight + 8;
        if (wrap.scrollTop > top) wrap.scrollTop = top;
        else if (wrap.scrollTop < bottom) wrap.scrollTop = bottom;
    }
</script>

<!-- svelte-ignore a11y_no_noninteractive_tabindex (région défilante, atteinte au clavier) -->
<div
    class="vff-table-wrap route"
    bind:this={wrap}
    role="region"
    tabindex="0"
    aria-label={t('tracks.table.region')}
>
    <table class="vff-table">
        <caption
            >{t(axisMode === 'route' ? 'tracks.route.caption' : 'tracks.table.caption')}</caption
        >
        <thead>
            <tr>
                <!-- Ligne : le PK de ligne ouvre la rangée ; livret : les kilomètres parcourus, le PK de chaque ligne après. -->
                <th scope="col" class="num"
                    >{t(axisMode === 'route' ? 'tracks.table.km' : 'tracks.table.pk')}</th
                >
                <th scope="col" class="num">{t('tracks.table.speed')}</th>
                <th scope="col" class="num">{t('tracks.table.gradient')}</th>
                {#if axisMode === 'route'}<th scope="col" class="num">{t('tracks.table.pk')}</th
                    >{/if}
                <th scope="col">{t('tracks.table.object')}</th>
                <th scope="col">{t('tracks.table.source')}</th>
            </tr>
        </thead>
        <tbody>
            {#each rows as row (row.key)}
                <tr
                    data-key={row.key}
                    class={['k-' + row.kind, row.key === selected && 'selected']}
                    aria-current={row.key === selected ? 'true' : undefined}
                >
                    <td class="num">{axisMode === 'route' ? km(row.km) : pkLabel(linePk(row))}</td>
                    <td class="num">{row.kmh === null ? '' : formatNumber(row.kmh, lang)}</td>
                    <td class="num">{permille(row.permille)}</td>
                    {#if axisMode === 'route'}<td class="num">{pkLabel(linePk(row))}</td>{/if}
                    <th scope="row" class="obj">
                        <div class="obj-in">
                            <button
                                type="button"
                                class="pick"
                                onclick={() => onselect(row)}
                                aria-describedby="tl-center-help"
                            >
                                <TrackPicto kind={PICTO[row.kind] ?? row.kind} />
                                <span class="label">
                                    <span class="name">{rowTitle(row, t, lang)}</span>
                                    {#if detail(row)}<span class="detail">{detail(row)}</span>{/if}
                                </span>
                            </button>
                            {#if row.kind === 'op' && row.item.station_slug && row.item.station_slug !== stationSlug}
                                <!-- Sous le nom de la gare : le nom garde toute la largeur de la colonne. -->
                                <span class="sheet">
                                    <a
                                        class="vff-link"
                                        href="/stations/{encodeURIComponent(row.item.station_slug)}"
                                        aria-label={t('tracks.table.station_label', {
                                            name: row.item.name,
                                        })}>{t('tracks.table.station')}</a
                                    >
                                    <a
                                        class="vff-link"
                                        href="/stations/{encodeURIComponent(
                                            row.item.station_slug,
                                        )}/voies"
                                        aria-label={t('tracks.table.station_tracks_label', {
                                            name: row.item.name,
                                        })}>{t('tracks.table.station_tracks')}</a
                                    >
                                </span>
                            {/if}
                        </div>
                    </th>
                    <td class="src">
                        <span class={['badge', 'b-' + row.prov.key]} title={sourceText(row.prov)}
                            ><span aria-hidden="true">{row.prov.code}</span><span
                                class="vff-sr-only">{sourceText(row.prov)}</span
                            ></span
                        >{#if row.prov.approx}{NBSP}<span
                                class="badge b-approx"
                                aria-hidden="true"
                                title={t('tracks.source.approx')}>≈</span
                            >{/if}
                    </td>
                </tr>
            {:else}
                <tr><td colspan={axisMode === 'route' ? 6 : 5}>{t('tracks.table.none')}</td></tr>
            {/each}
        </tbody>
    </table>
    <span id="tl-center-help" class="vff-sr-only">{t('tracks.table.center')}</span>
</div>

<style>
    .route {
        max-height: var(--tl-table-h, 36rem);
        overflow: auto;
        background: var(--vff-bg-elevated);
        overscroll-behavior: contain;
    }
    .route:focus-visible {
        outline: var(--vff-focus-width) solid var(--vff-focus);
        outline-offset: var(--vff-focus-offset);
    }
    caption {
        padding: 12px 20px 8px;
    }
    thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        box-shadow: inset 0 -1px 0 var(--vff-border);
    }
    .route :is(th, td) {
        padding: 4px 10px;
        vertical-align: middle;
    }
    .route thead th {
        white-space: normal;
        vertical-align: bottom;
    }
    th.obj {
        width: 100%;
        min-width: 15rem;
        font-weight: 400;
    }
    .obj-in {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
    }
    .pick {
        display: flex;
        align-items: center;
        gap: 10px;
        width: 100%;
        min-height: 40px;
        margin: -2px 0;
        padding: 4px 8px;
        border: 0;
        border-radius: var(--vff-radius-sm);
        background: transparent;
        color: inherit;
        font: inherit;
        text-align: left;
        cursor: pointer;
    }
    .pick:hover {
        background: var(--vff-bg-muted);
    }
    .pick:focus-visible {
        outline: var(--vff-focus-width) solid var(--vff-focus);
        outline-offset: 0;
    }
    /* Aligné sur le libellé, sous le pictogramme et sa marge (22 + 10 + 8 px). */
    .sheet {
        display: flex;
        flex-wrap: wrap;
        gap: 0 16px;
        margin: 0 0 4px 40px;
        font-size: var(--vff-fs-xs);
    }
    .label {
        display: flex;
        flex-direction: column;
        min-width: 0;
    }
    .name {
        font-weight: 700;
        line-height: 1.3;
    }
    .detail {
        color: var(--vff-text-muted);
        font-size: var(--vff-fs-xs);
        line-height: 1.3;
        overflow-wrap: anywhere;
    }
    tr.selected > * {
        background: var(--vff-bg-accent);
    }
    tr.selected .pick {
        box-shadow: inset 3px 0 0 var(--vff-accent-strong);
    }
    .k-missing .name {
        color: var(--vff-danger);
    }
    .src {
        white-space: nowrap;
    }
    .badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 28px;
        height: 28px;
        padding: 0 6px;
        border: 1px solid var(--vff-border-strong);
        border-radius: var(--vff-radius-pill);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-xs);
        font-weight: 700;
        line-height: 1;
    }
    .b-rinf {
        background: var(--vff-info-bg);
        color: var(--vff-info);
    }
    .b-osm {
        background: var(--vff-success-bg);
        color: var(--vff-success);
    }
    .b-vff {
        background: var(--vff-bg-muted);
        color: var(--vff-text);
    }
    .b-approx {
        background: var(--vff-warning-bg);
        color: var(--vff-warning);
    }
    .b-missing {
        background: var(--vff-danger-bg);
        color: var(--vff-danger);
    }
    @media print {
        .route {
            max-height: none;
            overflow: visible;
        }
        thead th {
            position: static;
        }
    }
</style>

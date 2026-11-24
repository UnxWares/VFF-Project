<script>
    import { formatNumber } from '$lib/typo.js';
    import {
        CROSSING_SWITCHES,
        ROW_H,
        SERVICE_KINDS,
        centerScroll,
        formatPermille,
        opExtents,
        pathD,
        pkLabel,
        placeBoxes,
        refSpots,
        rowAtX,
        stackLabels,
        textWidth,
        trackPaths,
        valueAt,
    } from '$lib/trackLayout.js';

    /**
     * Dessin du schéma des voies (onglet « Schéma ») ou de la bande seule (onglet « Bande »), dans un cadre qui
     * défile horizontalement : zones grisées et noms des points d'exploitation, voies par rangées, diagonales des
     * aiguillages, traversées, heurtoirs, signaux, passages à niveau, puis la bande des PK, vitesses et
     * déclivités. Le dessin est décoratif pour les lecteurs d'écran : le tableau de parcours donne les mêmes
     * objets (la région le dit). Seule la partie proche de la fenêtre est dessinée, sauf à l'impression.
     *
     * @type {{
     *   data: any, axis: any, lanes: { lanes: number[], row: (lane: number) => number, count: number },
     *   rows: Array<any>, zoom: number, mode?: 'schema'|'band', selectedPk?: number|null, printing?: boolean,
     *   label: string, helpId: string, lang: string, t: (key: string, options?: object) => string,
     *   onpick: (index: number) => void, onkey?: (event: KeyboardEvent) => void,
     * }}
     */
    let {
        data,
        axis,
        lanes,
        rows,
        zoom,
        mode = 'schema',
        selectedPk = null,
        printing = false,
        label,
        helpId,
        lang,
        t,
        onpick,
        onkey,
    } = $props();

    /** Marges horizontales : à gauche, la place des en-têtes de la bande. */
    const PAD_L = 76;
    const PAD_R = 40;
    const NAME_ROW = 24;
    const STRIP_ROW = 30;
    const OBJECT_ROW = 40;

    /** @type {HTMLDivElement|undefined} */
    let frame = $state();
    let view = $state(0);
    let scroll = $state(0);
    /** Abscisse du curseur dans le dessin, ou null. */
    let cursor = $state(/** @type {number|null} */ (null));

    const schema = $derived(mode === 'schema');
    const X = (pk) => PAD_L + axis.x(pk) * zoom;
    // Au moins la largeur du cadre : les noms des bouts de ligne ont la place d'être écrits.
    const width = $derived(Math.max(Math.ceil(axis.width * zoom + PAD_L + PAD_R), view - 2));

    // Partie dessinée : la fenêtre et une fenêtre de chaque côté (tout à l'impression).
    const lo = $derived(printing ? -Infinity : scroll - Math.max(view, 400));
    const hi = $derived(printing ? Infinity : scroll + 2 * Math.max(view, 400));
    const near = (x) => x >= lo && x <= hi;
    const nearSpan = (a, b) => b >= lo && a <= hi;

    // Noms des points d'exploitation, sur trois rangées au plus au-dessus des zones.
    const names = $derived.by(() => {
        const ops = data.operational_points;
        const rowsOf = stackLabels(
            ops.map((op) => ({
                x: X(op.pk_m),
                w: textWidth(op.name, 16) + 14,
                rank: op.kind === 'station' ? 0 : op.kind === 'junction' ? 1 : 2,
            })),
            { maxRows: 3, gap: 10 },
        );
        // Le nom reste dans le dessin : centré sur son point, ramené au bord quand il déborderait.
        return ops.map((op, i) => {
            const half = (textWidth(op.name, 16) + 4) / 2;
            const at = X(op.pk_m);
            return {
                op,
                at,
                x: Math.min(width - 6 - half, Math.max(10 + half, at)),
                row: rowsOf[i],
            };
        });
    });
    const nameRows = $derived(Math.max(1, ...names.map((n) => n.row + 1)));
    const TOP = $derived(10 + nameRows * NAME_ROW + 12);
    const Y = (lane) => TOP + lanes.row(lane) * ROW_H + ROW_H / 2;
    const tracksH = $derived(schema ? lanes.count * ROW_H : OBJECT_ROW);
    const stripTop = $derived(TOP + tracksH + 14);
    const height = $derived(stripTop + 3 * STRIP_ROW + 8);

    const extents = $derived(opExtents(data));
    const zoneStarts = $derived(
        axis.pieces.filter((p) => p.kind === 'zone').map((p) => PAD_L + p.x0 * zoom),
    );

    const paths = $derived(schema ? trackPaths(data, X, Y) : []);
    const tracksById = $derived(new Map(data.tracks.map((tr) => [tr.id, tr])));
    const laneOf = (trackId) => tracksById.get(trackId)?.lane ?? 0;

    /** Voies présentes à un PK : rangées haute et basse (passages à niveau, traversées). */
    function spanAt(pk) {
        const here = data.tracks.filter(
            (tr) => tr.kind !== 'crossover' && tr.pk_from_m <= pk && tr.pk_to_m >= pk,
        );
        if (here.length === 0) return [Y(0), Y(0)];
        const ys = here.map((tr) => Y(tr.lane));
        return [Math.min(...ys), Math.max(...ys)];
    }

    const kindOf = (tr) =>
        tr.kind === 'running' && tr.lane === 0
            ? 'travelled'
            : SERVICE_KINDS.has(tr.kind)
              ? 'service'
              : tr.kind === 'crossover'
                ? 'crossover'
                : 'main';

    const switchMarks = $derived(
        schema
            ? data.switches
                  .map((sw) => {
                      const x = X(sw.pk_m);
                      const ya = Y(laneOf(sw.from_track));
                      const yb = Y(laneOf(sw.to_track));
                      const cross = CROSSING_SWITCHES.has(sw.kind);
                      return { sw, x, y: cross ? (ya + yb) / 2 : ya, cross };
                  })
                  .filter((m) => near(m.x))
            : [],
    );
    const signalMarks = $derived(
        schema
            ? data.signals
                  .map((s) => {
                      const dir = s.direction === 'down' ? -1 : 1;
                      return { s, x: X(s.pk_m), y: Y(laneOf(s.track)), dir };
                  })
                  .filter((m) => near(m.x))
            : [],
    );
    const crossingMarks = $derived(
        data.crossings
            .map((c) => {
                const [y0, y1] = schema ? spanAt(c.pk_m) : [TOP + 20, TOP + 20];
                return { c, x: X(c.pk_m), y0, y1 };
            })
            .filter((m) => near(m.x)),
    );
    const bufferMarks = $derived(
        schema
            ? data.buffer_stops
                  .map((b) => ({ b, x: X(b.pk_m), y: Y(laneOf(b.track)) }))
                  .filter((m) => near(m.x))
            : [],
    );

    // Étiquettes des voies, signaux et aiguillages : sans chevauchement, par priorité.
    const labels = $derived.by(() => {
        if (!schema) return [];
        const out = [];
        for (const p of paths) {
            if (!p.label || !nearSpan(p.label.a, p.label.b)) continue;
            const w = textWidth(p.track.ref, 14) + 4;
            if (p.label.b - p.label.a < w + 10) continue;
            for (const spot of refSpots(p, zoneStarts)) {
                if (!near(spot.x)) continue;
                const x0 = spot.anchor === 'middle' ? spot.x - w / 2 : spot.x;
                out.push({
                    text: p.track.ref,
                    cls: 'ref',
                    x: spot.x,
                    y: p.label.y - 7,
                    anchor: spot.anchor,
                    box: { x0, x1: x0 + w, y0: p.label.y - 19, y1: p.label.y - 4, rank: 0 },
                });
            }
        }
        for (const m of signalMarks) {
            if (!m.s.ref) continue;
            const w = textWidth(m.s.ref, 14);
            const up = m.dir > 0;
            const x = up ? m.x + 10 : m.x - 10;
            const y = up ? m.y - 9 : m.y + 21;
            const x0 = up ? x : x - w;
            out.push({
                text: m.s.ref,
                cls: 'sig-ref',
                x,
                y,
                anchor: up ? 'start' : 'end',
                box: { x0, x1: x0 + w, y0: y - 12, y1: y + 2, rank: 1 },
            });
        }
        for (const m of switchMarks) {
            if (!m.sw.ref) continue;
            const w = textWidth(m.sw.ref, 14);
            out.push({
                text: m.sw.ref,
                cls: 'sw-ref',
                x: m.x,
                y: m.y + 21,
                anchor: 'middle',
                box: { x0: m.x - w / 2, x1: m.x + w / 2, y0: m.y + 9, y1: m.y + 23, rank: 2 },
            });
        }
        // Les symboles eux-mêmes occupent leur place : une étiquette ne les recouvre pas.
        const symbols = [
            ...switchMarks.map((m) => ({
                x0: m.x - 6,
                x1: m.x + 6,
                y0: m.y - 6,
                y1: m.y + 6,
                rank: -1,
            })),
            ...signalMarks.map((m) => ({
                x0: m.x - 2,
                x1: m.x + 2 + 8 * m.dir,
                y0: m.dir > 0 ? m.y - 20 : m.y,
                y1: m.dir > 0 ? m.y : m.y + 20,
                rank: -1,
            })),
        ];
        const shown = placeBoxes([...symbols, ...out.map((l) => l.box)]);
        return out.filter((_, i) => shown[symbols.length + i]);
    });

    // Bande : graduation des PK (gares d'abord), vitesses, déclivités.
    const pkTicks = $derived.by(() => {
        const cands = data.operational_points.map((op) => ({ pk: op.pk_m, rank: 0 }));
        for (const p of axis.pieces) cands.push({ pk: p.from_m, rank: p.kind === 'zone' ? 1 : 2 });
        cands.push({ pk: data.pk.end_m, rank: 1 });
        // Kilomètres ronds, là où la place le permet.
        const step = zoom >= 1 ? 1000 : zoom >= 0.4 ? 5000 : 10000;
        for (let k = Math.ceil(data.pk.start_m / step) * step; k <= data.pk.end_m; k += step) {
            cands.push({ pk: k, rank: 3 });
        }
        // Texte centré sur son trait, sans passer sous les en-têtes de la bande (64 px à gauche).
        const items = cands
            .map((c) => {
                const text = pkLabel(c.pk);
                const half = textWidth(text, 14) / 2;
                const x = X(c.pk);
                return {
                    ...c,
                    x,
                    tx: Math.min(width - 4 - half, Math.max(68 + half, x)),
                    half,
                    text,
                };
            })
            .filter((c) => near(c.x));
        const shown = placeBoxes(
            items.map((c) => ({
                x0: c.tx - c.half,
                x1: c.tx + c.half,
                y0: 0,
                y1: 10,
                rank: c.rank,
            })),
            8,
        );
        return items.filter((_, i) => shown[i]);
    });

    function cells(ranges, text) {
        return ranges
            .map((r) => {
                const x0 = X(r.pk_from_m);
                const x1 = X(r.pk_to_m);
                const label = text(r);
                return { r, x0, x1, label, fits: x1 - x0 >= textWidth(label, 15) + 10 };
            })
            .filter((c) => nearSpan(c.x0, c.x1));
    }
    const permille = (v) => formatPermille(v, lang);
    const speedCells = $derived(cells(data.strip.speeds, (r) => formatNumber(r.kmh, lang)));
    const gradientCells = $derived(cells(data.strip.gradients, (r) => permille(r.permille)));

    // Curseur : PK, vitesse et déclivité du point survolé.
    const cursorPk = $derived(cursor === null ? null : axis.pk((cursor - PAD_L) / zoom));
    const reading = $derived.by(() => {
        if (cursorPk === null) return null;
        const v = valueAt(data.strip.speeds, cursorPk);
        const g = valueAt(data.strip.gradients, cursorPk);
        return {
            pk: t('tracks.cursor_pk', { pk: pkLabel(Math.round(cursorPk)) }),
            speed: v ? `${formatNumber(v.kmh, lang)} km/h` : null,
            gradient: g ? `${permille(g.permille)} ‰` : null,
        };
    });

    function pointerX(event) {
        const box = /** @type {SVGElement} */ (event.currentTarget).getBoundingClientRect();
        return event.clientX - box.left;
    }
    function onmove(event) {
        if (event.pointerType === 'touch') return;
        cursor = Math.min(width - PAD_R, Math.max(PAD_L, pointerX(event)));
    }
    function onclick(event) {
        const i = rowAtX(axis, zoom, PAD_L, rows, pointerX(event));
        if (i >= 0) onpick(i);
    }

    /** Centre la fenêtre sur un PK (synchronisation depuis le tableau). */
    export function centerOn(pk, smooth = true) {
        if (!frame) return;
        const left = centerScroll(X(pk), frame.clientWidth, frame.scrollWidth);
        const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
        frame.scrollTo({ left, behavior: smooth && !reduce ? 'smooth' : 'auto' });
    }

    /** PK au milieu de la fenêtre (pour garder le même endroit en changeant le zoom). */
    export function centerPk() {
        if (!frame) return data.pk.start_m;
        return axis.pk((frame.scrollLeft + frame.clientWidth / 2 - PAD_L) / zoom);
    }

    /** Largeur utile de la fenêtre, pour « Ajuster ». */
    export function viewWidth() {
        return frame?.clientWidth ?? 0;
    }
</script>

<div class={['diagram', !schema && 'band']}>
    <!-- Région défilante atteinte au clavier (flèches : objet précédent ou suivant, + et - : zoom) ; le dessin
         est décrit par le tableau de parcours. -->
    <!-- svelte-ignore a11y_no_noninteractive_tabindex, a11y_no_noninteractive_element_interactions -->
    <div
        class="frame"
        bind:this={frame}
        bind:clientWidth={view}
        onscroll={() => (scroll = frame?.scrollLeft ?? 0)}
        role="region"
        aria-label={label}
        aria-describedby={helpId}
        tabindex="0"
        onkeydown={onkey}
    >
        <svg
            {width}
            {height}
            viewBox="0 0 {width} {height}"
            aria-hidden="true"
            onpointermove={onmove}
            onpointerleave={() => (cursor = null)}
            {onclick}
        >
            <!-- Zones des points d'exploitation et leurs noms. -->
            {#each data.operational_points as op (op.id)}
                {@const e = extents.get(op.id)}
                {#if e && nearSpan(X(e.from_m), X(e.to_m))}
                    <rect
                        class="zone"
                        x={X(e.from_m)}
                        y={TOP - 6}
                        width={Math.max(6, X(e.to_m) - X(e.from_m))}
                        height={tracksH + 12}
                        rx="8"
                    />
                {/if}
            {/each}
            {#each names as n (n.op.id)}
                {#if n.row >= 0 && near(n.x)}
                    <line
                        class="name-tick"
                        x1={n.at}
                        x2={n.at}
                        y1={10 + (n.row + 1) * NAME_ROW + 2}
                        y2={TOP - 6}
                    />
                    <text
                        class={['op-name', n.op.kind, n.op.precision === 'approximate' && 'approx']}
                        x={n.x}
                        y={10 + (n.row + 1) * NAME_ROW - 4}
                        text-anchor="middle">{n.op.name}</text
                    >
                {/if}
            {/each}

            {#if schema}
                <!-- Voies : la voie parcourue en orange, les voies de service en pointillé. -->
                {#each paths as p (p.track.id)}
                    {#if nearSpan(p.points[0][0], p.points.at(-1)[0])}
                        <path
                            class={[
                                'track',
                                kindOf(p.track),
                                p.track.precision === 'approximate' && 'approx',
                            ]}
                            d={pathD(p.points)}
                        />
                    {/if}
                {/each}
                {#each bufferMarks as m (m.b.id ?? m.b.pk_m)}
                    <line class="buffer" x1={m.x} x2={m.x} y1={m.y - 8} y2={m.y + 8} />
                {/each}
                {#each switchMarks as m (m.sw.id)}
                    {#if m.cross}
                        <path
                            class="cross"
                            d="M{m.x - 6} {m.y - 6} L{m.x + 6} {m.y + 6} M{m.x - 6} {m.y +
                                6} L{m.x + 6} {m.y - 6}"
                        />
                    {:else}
                        <circle class="switch" cx={m.x} cy={m.y} r="4.5" />
                    {/if}
                {/each}
                {#each signalMarks as m (m.s.id)}
                    <g class="signal">
                        <line x1={m.x} x2={m.x} y1={m.y} y2={m.y - 13 * m.dir} />
                        <circle cx={m.x + 4 * m.dir} cy={m.y - 13 * m.dir} r="3.8" />
                    </g>
                {/each}
            {/if}

            {#each crossingMarks as m (m.c.id)}
                {#if m.c.kind === 'level_crossing'}
                    <g class="pn">
                        <line x1={m.x - 3} x2={m.x - 3} y1={m.y0 - 10} y2={m.y1 + 10} />
                        <line x1={m.x + 3} x2={m.x + 3} y1={m.y0 - 10} y2={m.y1 + 10} />
                    </g>
                {:else}
                    <path
                        class="cross"
                        d="M{m.x - 7} {m.y0 - 7} L{m.x + 7} {m.y0 + 7} M{m.x - 7} {m.y0 + 7} L{m.x +
                            7} {m.y0 - 7}"
                    />
                {/if}
            {/each}

            {#if !schema}
                <!-- Bande seule : une rangée d'objets (signaux, aiguillages) le long de l'axe. -->
                <line class="axis" x1={PAD_L} x2={width - PAD_R} y1={TOP + 20} y2={TOP + 20} />
                {#each data.signals as s (s.id)}
                    {#if near(X(s.pk_m))}
                        {@const dir = s.direction === 'down' ? -1 : 1}
                        <g class="signal">
                            <line
                                x1={X(s.pk_m)}
                                x2={X(s.pk_m)}
                                y1={TOP + 20}
                                y2={TOP + 20 - 13 * dir}
                            />
                            <circle cx={X(s.pk_m) + 4 * dir} cy={TOP + 20 - 13 * dir} r="3.8" />
                        </g>
                    {/if}
                {/each}
                {#each data.switches as sw (sw.id)}
                    {#if near(X(sw.pk_m))}
                        <circle class="switch" cx={X(sw.pk_m)} cy={TOP + 20} r="4" />
                    {/if}
                {/each}
            {/if}

            {#each labels as l, i (i)}
                <text class={l.cls} x={l.x} y={l.y} text-anchor={l.anchor}>{l.text}</text>
            {/each}

            <!-- Bande : PK, vitesses, déclivités. -->
            <g class="strip">
                <line class="rule" x1={PAD_L} x2={width - PAD_R} y1={stripTop} y2={stripTop} />
                {#each pkTicks as tk (tk.pk)}
                    <line class="tick" x1={tk.x} x2={tk.x} y1={stripTop} y2={stripTop + 6} />
                    <text class="pk" x={tk.tx} y={stripTop + 21} text-anchor="middle"
                        >{tk.text}</text
                    >
                {/each}
                {#each [{ list: speedCells, row: 1, cls: 'speed' }, { list: gradientCells, row: 2, cls: 'gradient' }] as band (band.cls)}
                    {#each band.list as c, i (i)}
                        <rect
                            class={['cell', band.cls, i % 2 && 'alt']}
                            x={c.x0}
                            y={stripTop + band.row * STRIP_ROW + 2}
                            width={Math.max(1, c.x1 - c.x0)}
                            height={STRIP_ROW - 4}
                            rx="4"
                        />
                        {#if c.fits}
                            <text
                                class="val"
                                x={(c.x0 + c.x1) / 2}
                                y={stripTop + band.row * STRIP_ROW + 20}
                                text-anchor="middle">{c.label}</text
                            >
                        {/if}
                    {/each}
                {/each}
            </g>

            {#if selectedPk !== null}
                <line class="selected" x1={X(selectedPk)} x2={X(selectedPk)} y1="4" y2={height} />
            {/if}
            {#if cursor !== null}
                <line class="cursor" x1={cursor} x2={cursor} y1="0" y2={height} />
            {/if}
        </svg>
        {#if reading && cursor !== null}
            <div
                class={['reading', cursor > scroll + view - 200 && 'left']}
                style:--at="{cursor}px"
                aria-hidden="true"
            >
                <strong>{reading.pk}</strong>
                {#if reading.speed}<span>{reading.speed}</span>{/if}
                {#if reading.gradient}<span>{reading.gradient}</span>{/if}
            </div>
        {/if}
    </div>
    <!-- En-têtes de la bande, fixes pendant le défilement. -->
    <div class="heads" aria-hidden="true" style:--strip-top="{stripTop}px">
        <span style:--row="0">{t('tracks.strip.pk')}</span>
        <span style:--row="1">{t('tracks.strip.speed')}</span>
        <span style:--row="2">{t('tracks.strip.gradient')}</span>
        {#if !schema}<span class="objects" style:--top="{TOP + 8}px"
                >{t('tracks.strip.objects')}</span
            >{/if}
    </div>
</div>

<style>
    .diagram {
        position: relative;
        min-width: 0;
    }
    .frame {
        position: relative;
        overflow-x: auto;
        overflow-y: hidden;
        border: 1px solid var(--vff-border-strong);
        border-radius: var(--vff-radius-md);
        background: var(--vff-bg-elevated);
        scrollbar-width: thin;
    }
    .frame:focus-visible {
        outline: var(--vff-focus-width) solid var(--vff-focus);
        outline-offset: var(--vff-focus-offset);
    }
    svg {
        display: block;
        cursor: crosshair;
        font-family: var(--vff-font-heading);
        stroke-linecap: round;
        stroke-linejoin: round;
    }
    .zone {
        fill: var(--tl-zone);
        stroke: var(--vff-border);
    }
    .name-tick {
        stroke: var(--vff-border-strong);
        stroke-width: 1;
    }
    .op-name {
        font-size: 16px;
        font-weight: 600;
        fill: var(--vff-text);
        paint-order: stroke;
        stroke: var(--vff-bg-elevated);
        stroke-width: 4px;
    }
    .op-name.halt,
    .op-name.junction,
    .op-name.passing_loop {
        font-weight: 500;
    }
    .op-name.approx {
        font-style: italic;
    }
    .track {
        fill: none;
        stroke: var(--vff-text);
        stroke-width: 2.4;
    }
    .track.travelled {
        stroke: var(--vff-accent-strong);
        stroke-width: 4;
    }
    .track.service {
        stroke: var(--vff-text-muted);
        stroke-width: 2;
        stroke-dasharray: 6 4;
    }
    .track.crossover {
        stroke-width: 2;
    }
    .track.approx {
        stroke-dasharray: 2 5;
    }
    .switch {
        fill: var(--vff-bg-elevated);
        stroke: var(--vff-text);
        stroke-width: 1.8;
    }
    .cross {
        fill: none;
        stroke: var(--vff-text);
        stroke-width: 2.4;
    }
    .buffer {
        stroke: var(--vff-text);
        stroke-width: 4;
    }
    .signal line {
        stroke: var(--vff-info);
        stroke-width: 2;
    }
    .signal circle {
        fill: var(--vff-info);
    }
    .pn line {
        stroke: var(--vff-text-muted);
        stroke-width: 1.8;
    }
    .axis {
        stroke: var(--vff-text);
        stroke-width: 2.4;
    }
    .ref,
    .sig-ref,
    .sw-ref {
        font-size: 14px;
        paint-order: stroke;
        stroke: var(--vff-bg-elevated);
        stroke-width: 3px;
        font-variant-numeric: tabular-nums;
    }
    .ref {
        font-weight: 700;
        fill: var(--vff-text);
    }
    .sig-ref {
        fill: var(--vff-info);
    }
    .sw-ref {
        fill: var(--vff-text-muted);
    }
    .rule,
    .tick {
        stroke: var(--vff-border-strong);
        stroke-width: 1;
    }
    .pk {
        font-size: 14px;
        fill: var(--vff-text-muted);
        font-variant-numeric: tabular-nums;
    }
    .cell {
        fill: var(--vff-bg-subtle);
        stroke: var(--vff-border-strong);
        stroke-width: 1;
    }
    .cell.alt {
        fill: var(--vff-bg-muted);
    }
    .val {
        font-size: 15px;
        fill: var(--vff-text);
        font-variant-numeric: tabular-nums;
    }
    .selected {
        stroke: var(--vff-accent-strong);
        stroke-width: 2;
        stroke-dasharray: 5 4;
    }
    .cursor {
        stroke: var(--vff-text-muted);
        stroke-width: 1;
        pointer-events: none;
    }
    .reading {
        position: absolute;
        top: 8px;
        left: calc(var(--at) + 12px);
        display: flex;
        flex-direction: column;
        gap: 2px;
        padding: 6px 12px;
        border: 1px solid var(--vff-border-strong);
        border-radius: var(--vff-radius-sm);
        background: var(--vff-bg-elevated);
        box-shadow: var(--vff-shadow-popover);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-xs);
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
        pointer-events: none;
    }
    .reading.left {
        transform: translateX(calc(-100% - 24px));
    }
    .heads {
        position: absolute;
        inset: 1px auto 1px 1px;
        width: 64px;
        border-radius: var(--vff-radius-md) 0 0 var(--vff-radius-md);
        background: linear-gradient(
            to right,
            var(--vff-bg-elevated) 80%,
            color-mix(in srgb, var(--vff-bg-elevated) 0%, transparent)
        );
        pointer-events: none;
    }
    .heads span {
        position: absolute;
        left: 10px;
        top: calc(var(--strip-top) + var(--row) * 30px + 6px);
        font-family: var(--vff-font-heading);
        font-size: 14px;
        font-weight: 600;
        color: var(--vff-text-muted);
    }
    .heads .objects {
        top: var(--top);
    }
    @media print {
        .frame {
            overflow: visible;
            border: 0;
        }
        .frame svg {
            width: 100%;
            height: auto;
        }
        .heads,
        .reading,
        .cursor {
            display: none;
        }
    }
</style>

<script>
    import { i18n } from '$lib/i18n.js';
    import { colon, formatLocale, formatNumber, NBSP } from '$lib/typo.js';
    import SheetSection from '$components/Sheet/SheetSection.svelte';
    import StatStrip from '$components/Sheet/StatStrip.svelte';
    import {
        altitudeDomain,
        areaPath,
        crossesGap,
        GRADE_WINDOW_M,
        gradeAt,
        labelledStations,
        linePath,
        nearestIndex,
        nearestStation,
        rackSentence,
        sourceLang,
        ticks,
    } from '$lib/lineProfile.js';

    /**
     * Section « Profil en long » de la fiche ligne (App\Services\Profiles\LineProfiles) : chiffres
     * du profil, courbe de l'altitude le long du tracé de référence (toutes époques) avec toutes les
     * gares de la ligne, fermées comprises, les tunnels et les trous du tracé (bandes, la courbe s'y
     * interrompt), lecture au survol (distance, PK estimé, altitude, déclivité locale, gare proche),
     * gares atteignables au clavier, puis le tableau des altitudes des gares (alternative au
     * graphique) et la source.
     *
     * @type {{ profile: {
     *   era_year: number|null, step_m: number, length_m: number,
     *   summary: { min_altitude_m: number, max_altitude_m: number, ascent_m: number, descent_m: number,
     *     max_grade_permil: number|null, max_grade_from_m: number|null, max_grade_to_m: number|null,
     *     max_grade_direction: 'up'|'down'|null, max_grade_withheld?: 'terrain_only'|'tunnel'|'interpolated'|'rack'|null },
     *   rack?: { system: string, extent: 'full'|'partial'|null, max_grade_permil: number, braking_only: boolean,
     *     source: { title: string, url: string } } | null,
     *   points: Array<[number, number|null, (number|null)?]>,
     *   structures: Array<{ from_m: number, to_m: number, kind: 'tunnel'|'bridge' }>,
     *   altitude_sources: { bdtopo: number, rge_alti: number, interpolated: number, missing: number },
     *   gaps: Array<{ from_m: number, to_m: number }>,
     *   branches?: Array<{ from_m: number, to_m: number }>,
     *   terrain_ranges?: Array<{ from_m: number, to_m: number }>,
     *   stations: Array<{ slug: string, name: string, distance_m: number, altitude_m: number|null, pk_m: number|null,
     *     status?: string }>,
     *   computed_at: string,
     * } }}
     */
    let { profile } = $props();

    const lang = $derived($i18n.language ?? 'fr');
    const t = (k, o) => String($i18n.t(k, o));
    const metres = (z) => `${formatNumber(Math.round(z), lang)}${NBSP}m`;
    const km = (m, digits = 1) =>
        formatNumber(m / 1000, lang, {
            minimumFractionDigits: digits,
            maximumFractionDigits: digits,
        });
    const permil = (g) =>
        formatNumber(g, lang, { minimumFractionDigits: 0, maximumFractionDigits: 1 });
    const pkLabel = (m) => {
        if (m === null || m === undefined) return '-';
        const a = Math.abs(m);
        return `${m < 0 ? '-' : ''}${Math.floor(a / 1000)}+${String(a % 1000).padStart(3, '0')}`;
    };
    const closed = (st) => st.status === 'declassified';

    const s = $derived(profile.summary);
    // Ligne à crémaillère : déclivité publiée par la source, la mesure du profil n'en dit rien.
    const rack = $derived(profile.rack ?? null);
    const rackText = $derived(rackSentence(rack));
    const branches = $derived(profile.branches ?? []);
    // Gares de la ligne principale (avant le premier embranchement) : départ et arrivée de l'intro.
    const mainStations = $derived(
        branches.length
            ? profile.stations.filter((st) => st.distance_m < branches[0].from_m)
            : profile.stations,
    );
    const first = $derived(mainStations[0] ?? null);
    const last = $derived(mainStations.at(-1) ?? null);
    const intro = $derived(
        first && last && first !== last
            ? t('profile.intro', { from: first.name, to: last.name })
            : t('profile.intro_plain'),
    );
    const statItems = $derived([
        { value: metres(s.min_altitude_m), label: t('profile.min') },
        { value: metres(s.max_altitude_m), label: t('profile.max') },
        { value: metres(s.ascent_m), label: t('profile.ascent') },
        { value: metres(s.descent_m), label: t('profile.descent') },
        {
            value: rack
                ? `${permil(rack.max_grade_permil)}${NBSP}‰`
                : s.max_grade_permil === null
                  ? t('profile.grade_unpublished')
                  : `${permil(s.max_grade_permil)}${NBSP}‰`,
            label: t('profile.grade'),
        },
    ]);
    // Déclivité publiée : son endroit ; sinon, le motif (altitude du terrain seulement, tunnel…).
    const gradeWhere = $derived(
        s.max_grade_permil === null
            ? null
            : t(s.max_grade_direction === 'up' ? 'profile.grade_up' : 'profile.grade_down', {
                  from: km(s.max_grade_from_m),
                  to: km(s.max_grade_to_m),
              }),
    );
    const terrain = $derived(profile.terrain_ranges ?? []);
    const tunnels = $derived(profile.structures.filter((x) => x.kind === 'tunnel'));
    const hasBreaks = $derived(profile.altitude_sources.missing > 0 || profile.gaps.length > 0);
    const hasClosed = $derived(profile.stations.some(closed));
    const computedOn = $derived(
        new Date(profile.computed_at).toLocaleDateString(formatLocale(lang), {
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        }),
    );

    // Géométrie du graphique : largeur mesurée, hauteur fixe ; bande des noms de gares en haut,
    // bande des tunnels sous la courbe, altitudes (avec l'unité) à gauche, kilomètres en bas.
    let width = $state(0);
    const narrow = $derived(width > 0 && width < 600);
    const height = $derived(narrow ? 240 : 300);
    // Marges en px pour des textes de 17 px (altitudes à gauche, kilomètres et légende de l'axe en bas).
    const pad = $derived({ top: 36, right: 12, bottom: 50, left: narrow ? 72 : 84 });
    const plotW = $derived(Math.max(10, width - pad.left - pad.right));
    const plotH = $derived(height - pad.top - pad.bottom);
    const baseY = $derived(pad.top + plotH);
    const domain = $derived(altitudeDomain(profile.points));
    // Échelles : fonctions recréées quand la taille ou les données changent.
    const x = $derived.by(() => (d) => pad.left + (d / Math.max(1, profile.length_m)) * plotW);
    const y = $derived.by(
        () => (z) => pad.top + (1 - (z - domain[0]) / (domain[1] - domain[0])) * plotH,
    );
    const yTicks = $derived(ticks(domain[0], domain[1], narrow ? 3 : 4));
    const xTicks = $derived(
        ticks(0, profile.length_m / 1000, Math.max(3, Math.floor(plotW / 72))).filter(
            (k) => k * 1000 <= profile.length_m,
        ),
    );
    const line = $derived(linePath(profile.points, x, y, profile.gaps));
    const area = $derived(areaPath(profile.points, x, y, baseY, profile.gaps));
    // Largeur d'un nom en Inter 17 px : 9,2 px par caractère en moyenne (7,6 px mesurés à 14 px).
    const named = $derived(
        labelledStations(profile.stations, x, {
            left: 0,
            right: width,
            labelWidth: (name) => name.length * 9.2,
            principal: (st) => !closed(st),
        }),
    );

    // Ligne aux gares serrées (plus d'une tous les 14 px) : points plus petits, pour que la courbe
    // reste lisible ; toutes les gares gardent leur point, leur nom au survol et au clavier.
    const dense = $derived(profile.stations.length > plotW / 14);
    // Gare dans un trou (altitude inconnue) : son point est posé sur la ligne de base.
    const stationY = (st) => (st.altitude_m === null ? baseY : y(st.altitude_m));

    // Lecture au survol (souris, stylet, doigt) : point le plus proche du pointeur ; rien dans une
    // lacune du parcours, où aucune altitude n'est connue.
    let hover = $state(/** @type {number|null} */ (null));
    // Gare lue : sous le pointeur (à 8 px près) ou qui a le focus clavier.
    let station = $state(/** @type {any} */ (null));
    function onPointer(event) {
        const box = event.currentTarget.getBoundingClientRect();
        const d = ((event.clientX - box.left) / box.width) * profile.length_m;
        const i = crossesGap(profile.gaps, d, d) ? -1 : nearestIndex(profile.points, d);
        hover = i >= 0 && profile.points[i][1] !== null ? i : null;
        station = nearestStation(profile.stations, x, x(d));
    }
    function leave() {
        hover = null;
        station = null;
    }
    // Fenêtre de la déclivité locale : 1 km, élargie à quatre intervalles moyens (deux de part et
    // d'autre) quand les points reçus sont plus espacés (profil allégé d'une longue ligne), pour
    // garder au moins trois points.
    const gradeWindow = $derived(
        Math.max(GRADE_WINDOW_M, (4 * profile.length_m) / Math.max(1, profile.points.length - 1)),
    );
    const reading = $derived.by(() => {
        if (hover === null && station === null) return null;
        // Gare au focus clavier sans point survolé : lecture à la gare elle-même.
        const [d, z, pk] =
            hover !== null ? profile.points[hover] : [station.distance_m, station.altitude_m, null];
        return {
            d,
            z,
            pk: pk ?? null,
            grade: z === null ? null : gradeAt(profile.points, d, profile.gaps, gradeWindow),
            tunnel: tunnels.some((tu) => d >= tu.from_m && d <= tu.to_m),
            station,
            left: x(d) > pad.left + plotW / 2,
        };
    });
</script>

<SheetSection id="profile" title={t('profile.title')} {intro}>
    <div class="profile">
        <StatStrip items={statItems} label={t('profile.stats_label')} size="sm" />
        {#if rack && rackText}
            <p class="grade-where">
                {t(rackText.key, { system: rackText.system })}
                {#if rack.braking_only}{t('profile.rack_braking')}{/if}
                {t('profile.rack_not_measured')}
                {t('profile.rack_source')}{colon(lang)}<a
                    href={rack.source.url}
                    rel="external"
                    lang={sourceLang(rack.source.url)}>{rack.source.title}</a
                >.
            </p>
        {:else if gradeWhere}
            <p class="grade-where">{t('profile.grade')}{colon(lang)}{gradeWhere}</p>
        {:else}
            <p class="grade-where">
                {t(`profile.grade_withheld_${s.max_grade_withheld ?? 'none'}`)}
            </p>
        {/if}

        <!-- Nom de la figure : le titre de la section, pas la légende (figcaption) qui ne liste que les motifs. -->
        <figure class="chart" aria-labelledby="profile-title">
            <div class="plot" bind:clientWidth={width}>
                {#if width > 0}
                    <svg
                        viewBox="0 0 {width} {height}"
                        {width}
                        {height}
                        role="group"
                        aria-label={t('profile.chart_label', {
                            min: formatNumber(Math.round(s.min_altitude_m), lang),
                            max: formatNumber(Math.round(s.max_altitude_m), lang),
                            km: km(profile.length_m, 0),
                        })}
                    >
                        <defs>
                            <pattern
                                id="profile-gap"
                                width="8"
                                height="8"
                                patternUnits="userSpaceOnUse"
                                patternTransform="rotate(-45)"
                            >
                                <line x1="0" y1="0" x2="0" y2="8" class="gap-hatch" />
                            </pattern>
                            <pattern
                                id="profile-tunnel"
                                width="6"
                                height="6"
                                patternUnits="userSpaceOnUse"
                                patternTransform="rotate(45)"
                            >
                                <line x1="0" y1="0" x2="0" y2="6" class="hatch" />
                            </pattern>
                        </defs>

                        {#each yTicks as v (v)}
                            <line
                                class="grid"
                                x1={pad.left}
                                x2={pad.left + plotW}
                                y1={y(v)}
                                y2={y(v)}
                            />
                            <text
                                class="tick"
                                x={pad.left - 8}
                                y={y(v)}
                                text-anchor="end"
                                dy="0.35em">{metres(v)}</text
                            >
                        {/each}
                        {#each xTicks as k (k)}
                            <text class="tick" x={x(k * 1000)} y={baseY + 24} text-anchor="middle"
                                >{formatNumber(k, lang)}</text
                            >
                        {/each}
                        <text class="axis" x={pad.left + plotW} y={height - 6} text-anchor="end"
                            >{t('profile.axis_distance')}</text
                        >

                        <!-- Trous du tracé : bande hachurée sur toute la hauteur, aucune courbe dedans. -->
                        {#each profile.gaps as g (g.from_m)}
                            <rect
                                class="gap"
                                x={x(g.from_m)}
                                y={pad.top}
                                width={Math.max(3, x(g.to_m) - x(g.from_m))}
                                height={plotH}
                            />
                        {/each}

                        {#each tunnels as tu (tu.from_m)}
                            <rect
                                class="tunnel"
                                x={x(tu.from_m)}
                                y={baseY + 3}
                                width={Math.max(4, x(tu.to_m) - x(tu.from_m))}
                                height="6"
                                rx="1"
                            />
                        {/each}

                        {#each profile.stations as st (st.slug)}
                            <!-- Trait vers le nom pour les seules gares nommées. -->
                            {#if named.has(st)}
                                <line
                                    class="station-tick"
                                    x1={x(st.distance_m)}
                                    x2={x(st.distance_m)}
                                    y1={pad.top - 6}
                                    y2={stationY(st)}
                                />
                                <text
                                    class="station-name"
                                    x={x(st.distance_m)}
                                    y={pad.top - 12}
                                    text-anchor={named.get(st)}>{st.name}</text
                                >
                            {/if}
                        {/each}

                        <path class="area" d={area} />
                        <path class="line" d={line} />

                        <!-- Parties à l'altitude du terrain (RGE ALTI) : trait pointillé au pied de la courbe. -->
                        {#each terrain as r (r.from_m)}
                            <line
                                class="terrain"
                                x1={x(r.from_m)}
                                x2={Math.max(x(r.from_m) + 3, x(r.to_m))}
                                y1={baseY - 3}
                                y2={baseY - 3}
                            />
                        {/each}

                        {#if reading}
                            <line
                                class="cursor"
                                x1={x(reading.d)}
                                x2={x(reading.d)}
                                y1={pad.top}
                                y2={baseY}
                            />
                            {#if reading.z !== null}
                                <circle
                                    class="cursor-dot"
                                    cx={x(reading.d)}
                                    cy={y(reading.z)}
                                    r="5"
                                />
                            {/if}
                        {/if}

                        <!-- Zone de survol : lecture visuelle seulement, le tableau des gares donne les valeurs. -->
                        <rect
                            role="presentation"
                            class="hit"
                            x={pad.left}
                            y={pad.top}
                            width={plotW}
                            height={plotH}
                            onpointermove={onPointer}
                            onpointerdown={onPointer}
                            onpointerleave={leave}
                        />

                        <!-- Points des gares, au-dessus de la zone de survol : atteignables au clavier,
                             nom, PK et altitude lus au focus et dans le tableau. -->
                        <g aria-label={t('profile.stations_label')} role="list">
                            {#each profile.stations as st (st.slug)}
                                <!-- Focalisable pour lire au clavier le nom d'une gare non nommée à l'écran. -->
                                <!-- svelte-ignore a11y_no_noninteractive_tabindex -->
                                <circle
                                    role="listitem"
                                    tabindex="0"
                                    class={['station-dot', { closed: closed(st) }]}
                                    cx={x(st.distance_m)}
                                    cy={stationY(st)}
                                    r={dense && !named.has(st) ? 2.5 : 3.5}
                                    aria-label={[
                                        st.name,
                                        closed(st) ? t('profile.closed') : null,
                                        st.altitude_m === null ? null : metres(st.altitude_m),
                                    ]
                                        .filter(Boolean)
                                        .join(', ')}
                                    onfocus={() => (station = st)}
                                    onblur={() => (station = null)}
                                />
                            {/each}
                        </g>
                    </svg>

                    {#if reading}
                        <div
                            class={['reading', { left: reading.left }]}
                            style:--at="{x(reading.d)}px"
                            aria-hidden="true"
                        >
                            {#if reading.station}<strong
                                    >{reading.station.name}{#if closed(reading.station)}<span
                                            class="muted"
                                        >
                                            ({t('profile.closed')})</span
                                        >{/if}</strong
                                >{/if}
                            <strong>{t('profile.at_km', { km: km(reading.d) })}</strong>
                            {#if reading.pk !== null}<span
                                    >{t('profile.at_pk', { pk: pkLabel(reading.pk) })}</span
                                >{/if}
                            {#if reading.z !== null}<span>{metres(reading.z)}</span>{/if}
                            {#if reading.grade !== null}<span
                                    >{t('profile.local_grade', {
                                        grade: permil(reading.grade),
                                    })}</span
                                >{/if}
                            {#if reading.tunnel}<span class="muted">{t('profile.in_tunnel')}</span
                                >{/if}
                        </div>
                    {/if}
                {/if}
            </div>
            {#if tunnels.length || profile.gaps.length || hasClosed || branches.length || terrain.length}
                <figcaption>
                    {#if tunnels.length}
                        <span class="legend-item"
                            ><span class="legend-swatch" aria-hidden="true"></span>{t(
                                'profile.tunnel_legend',
                            )}</span
                        >
                    {/if}
                    {#if profile.gaps.length}
                        <span class="legend-item"
                            ><span class="legend-swatch gap-swatch" aria-hidden="true"></span>{t(
                                'profile.gap_legend',
                            )}</span
                        >
                    {/if}
                    {#if terrain.length}
                        <span class="legend-item"
                            ><span class="legend-terrain" aria-hidden="true"></span>{t(
                                'profile.terrain_legend',
                            )}</span
                        >
                    {/if}
                    {#if hasClosed}
                        <span class="legend-item"
                            ><span class="legend-dot" aria-hidden="true"></span>{t(
                                'profile.closed_legend',
                            )}</span
                        >
                    {/if}
                    {#if branches.length}
                        <span class="legend-item">{t('profile.branch_legend')}</span>
                    {/if}
                </figcaption>
            {/if}
        </figure>

        {#if hasBreaks}<p class="note">{t('profile.break_note')}</p>{/if}

        {#if profile.stations.length}
            <details class="list">
                <summary>{t('profile.show_table', { count: profile.stations.length })}</summary>
                <!-- Cadre défilant : focalisable pour défiler au clavier (GUIDE 5.8). -->
                <!-- svelte-ignore a11y_no_noninteractive_tabindex -->
                <div
                    class="vff-table-wrap"
                    role="region"
                    aria-label={t('profile.table_caption')}
                    tabindex="0"
                >
                    <table class="vff-table">
                        <caption class="vff-sr-only">{t('profile.table_caption')}</caption>
                        <thead>
                            <tr>
                                <th scope="col">{t('profile.station')}</th>
                                <th scope="col" class="num">{t('profile.pk')}</th>
                                <th scope="col" class="num">{t('profile.distance')}</th>
                                <th scope="col" class="num">{t('profile.altitude')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {#each profile.stations as st (st.slug)}
                                <tr>
                                    <th scope="row"
                                        >{st.name}{#if closed(st)}<span class="muted">
                                                ({t('profile.closed')})</span
                                            >{/if}</th
                                    >
                                    <td class="num">{pkLabel(st.pk_m)}</td>
                                    <td class="num">{km(st.distance_m)}{NBSP}km</td>
                                    <td class="num"
                                        >{st.altitude_m === null
                                            ? t('profile.unknown')
                                            : metres(st.altitude_m)}</td
                                    >
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                </div>
            </details>
        {/if}

        <p class="source">{t('profile.source', { date: computedOn })}</p>
    </div>
</SheetSection>

<style>
    .profile {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-5);
    }
    .grade-where,
    .note,
    .source {
        margin: 0;
        max-width: var(--vff-measure);
        font-size: var(--vff-fs-sm);
        /* Interligne du texte courant : la phrase de crémaillère tient sur trois ou quatre lignes. */
        line-height: var(--vff-lh-body);
        color: var(--vff-text-muted);
    }
    .grade-where {
        margin-top: calc(-1 * var(--vff-space-3));
    }
    .chart {
        margin: 0;
        padding: var(--vff-space-4) var(--vff-space-4) var(--vff-space-3);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        background: var(--vff-bg-elevated);
        min-width: 0;
    }
    .plot {
        position: relative;
    }
    svg {
        display: block;
        width: 100%;
        height: auto;
        overflow: visible;
        font-family: var(--vff-font-body, Inter, sans-serif);
        touch-action: pan-y;
    }
    .grid {
        stroke: var(--vff-border);
        stroke-width: 1;
    }
    .tick,
    .axis {
        font-family: Inter, sans-serif;
        font-size: 17px;
        font-variant-numeric: tabular-nums;
        fill: var(--vff-text-muted);
    }
    .area {
        fill: var(--vff-accent-soft);
    }
    /* Courbe : orange appuyé (contraste de 3:1 au moins avec le fond dans les deux thèmes). */
    .line {
        fill: none;
        stroke: var(--vff-accent-strong);
        stroke-width: 2;
        stroke-linejoin: round;
        stroke-linecap: round;
    }
    .tunnel {
        fill: url(#profile-tunnel);
        stroke: var(--vff-border-control);
        stroke-width: 1;
    }
    .hatch {
        stroke: var(--vff-border-control);
        stroke-width: 2;
    }
    .station-tick {
        stroke: var(--vff-border-control);
        stroke-width: 1;
        stroke-dasharray: 2 3;
    }
    /* Le survol passe par la zone .hit (gare la plus proche à 8 px) ; le point garde le focus clavier. */
    .station-dot {
        fill: var(--vff-bg-elevated);
        stroke: var(--vff-text);
        stroke-width: 1.5;
        pointer-events: none;
    }
    /* Gare fermée : point plein gris, distinct du point creux des gares ouvertes. */
    .station-dot.closed {
        fill: var(--vff-text-muted);
        stroke: var(--vff-bg-elevated);
    }
    .station-dot:focus-visible {
        outline: none;
        stroke: var(--vff-accent-strong);
        stroke-width: 3;
    }
    .gap {
        fill: url(#profile-gap);
    }
    .terrain {
        stroke: var(--vff-text-muted);
        stroke-width: 2;
        stroke-dasharray: 2 4;
        stroke-linecap: round;
    }
    .legend-terrain {
        width: 24px;
        border-top: 2px dotted var(--vff-text-muted);
    }
    .gap-hatch {
        stroke: var(--vff-border);
        stroke-width: 2;
    }
    .station-name {
        font-family: Inter, sans-serif;
        font-size: 17px;
        fill: var(--vff-text);
    }
    .cursor {
        stroke: var(--vff-text-muted);
        stroke-width: 1;
    }
    .cursor-dot {
        fill: var(--vff-accent-strong);
        stroke: var(--vff-bg-elevated);
        stroke-width: 2;
    }
    .hit {
        fill: transparent;
        cursor: crosshair;
    }
    .reading {
        position: absolute;
        top: 40px;
        left: calc(var(--at) + 12px);
        display: flex;
        flex-direction: column;
        gap: 2px;
        padding: 8px 12px;
        border: 1px solid var(--vff-border-strong);
        border-radius: var(--vff-radius-sm);
        background: var(--vff-bg-elevated);
        box-shadow: var(--vff-shadow-popover);
        font-family: Inter, sans-serif;
        font-size: var(--vff-fs-xs);
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
        pointer-events: none;
    }
    .reading.left {
        transform: translateX(calc(-100% - 24px));
    }
    .reading .muted {
        color: var(--vff-text-muted);
    }
    figcaption {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px 20px;
        margin-top: var(--vff-space-2);
        font-size: var(--vff-fs-xs);
        color: var(--vff-text-muted);
    }
    .legend-item {
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .legend-dot {
        width: 9px;
        height: 9px;
        border-radius: 50%;
        background: var(--vff-text-muted);
    }
    .gap-swatch {
        background: repeating-linear-gradient(-45deg, var(--vff-border) 0 2px, transparent 2px 6px);
        border-color: var(--vff-border);
    }
    th .muted {
        font-weight: 400;
        color: var(--vff-text-muted);
    }
    .legend-swatch {
        flex-shrink: 0;
        width: 24px;
        height: 8px;
        border: 1px solid var(--vff-border-control);
        border-radius: 2px;
        background: repeating-linear-gradient(
            45deg,
            var(--vff-border-control) 0 2px,
            transparent 2px 5px
        );
    }
    .list summary {
        display: inline-flex;
        align-items: center;
        min-height: 44px;
        padding: 8px 16px;
        border: 1px solid var(--vff-border-control);
        border-radius: var(--vff-radius-sm);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-md);
        cursor: pointer;
    }
    .list[open] summary {
        margin-bottom: var(--vff-space-4);
    }
    .list .vff-table-wrap {
        max-height: 28rem;
        overflow: auto;
    }
    @media (max-width: 600px) {
        .chart {
            padding: var(--vff-space-3) var(--vff-space-2) var(--vff-space-2);
        }
    }
</style>

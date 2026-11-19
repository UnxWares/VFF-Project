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
        ticks,
    } from '$lib/lineProfile.js';

    /**
     * Section « Profil en long » de la fiche ligne (App\Services\Profiles\LineProfiles) : chiffres
     * du profil, courbe de l'altitude le long du parcours avec les gares et les tunnels, lecture au
     * survol (distance, altitude, déclivité locale), puis le tableau des altitudes des gares
     * (alternative au graphique pour les lecteurs d'écran et au clavier) et la source.
     *
     * @type {{ profile: {
     *   era_year: number, step_m: number, length_m: number,
     *   summary: { min_altitude_m: number, max_altitude_m: number, ascent_m: number, descent_m: number,
     *     max_grade_permil: number, max_grade_from_m: number, max_grade_to_m: number, max_grade_direction: 'up'|'down' },
     *   points: Array<[number, number|null]>,
     *   structures: Array<{ from_m: number, to_m: number, kind: 'tunnel'|'bridge' }>,
     *   altitude_sources: { bdtopo: number, rge_alti: number, interpolated: number, missing: number },
     *   gaps: Array<{ from_m: number, to_m: number }>,
     *   stations: Array<{ slug: string, name: string, distance_m: number, altitude_m: number|null, pk_m: number|null }>,
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
    const pkLabel = (m) =>
        m === null ? '-' : `${Math.floor(m / 1000)}+${String(m % 1000).padStart(3, '0')}`;

    const s = $derived(profile.summary);
    const first = $derived(profile.stations[0] ?? null);
    const last = $derived(profile.stations.at(-1) ?? null);
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
        { value: `${permil(s.max_grade_permil)}${NBSP}‰`, label: t('profile.grade') },
    ]);
    const gradeWhere = $derived(
        t(s.max_grade_direction === 'up' ? 'profile.grade_up' : 'profile.grade_down', {
            from: km(s.max_grade_from_m),
            to: km(s.max_grade_to_m),
        }),
    );
    const tunnels = $derived(profile.structures.filter((x) => x.kind === 'tunnel'));
    const hasBreaks = $derived(profile.altitude_sources.missing > 0 || profile.gaps.length > 0);
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
    const pad = $derived({ top: 34, right: 12, bottom: 44, left: narrow ? 64 : 76 });
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
        ticks(0, profile.length_m / 1000, Math.max(3, Math.floor(plotW / 64))).filter(
            (k) => k * 1000 <= profile.length_m,
        ),
    );
    const line = $derived(linePath(profile.points, x, y, profile.gaps));
    const area = $derived(areaPath(profile.points, x, y, baseY, profile.gaps));
    // Largeur d'un nom en Inter 14 px : 7,6 px par caractère en moyenne (mesuré sur les noms de gares).
    const named = $derived(
        labelledStations(profile.stations, x, {
            left: 0,
            right: width,
            labelWidth: (name) => name.length * 7.6,
        }),
    );

    // Ligne aux gares serrées (plus d'une tous les 14 px) : seules les gares nommées ont leur point,
    // pour que la courbe reste lisible ; toutes restent dans le tableau.
    const dense = $derived(profile.stations.length > plotW / 14);

    // Lecture au survol (souris, stylet, doigt) : point le plus proche du pointeur ; rien dans une
    // lacune du parcours, où aucune altitude n'est connue.
    let hover = $state(/** @type {number|null} */ (null));
    function onPointer(event) {
        const box = event.currentTarget.getBoundingClientRect();
        const d = ((event.clientX - box.left) / box.width) * profile.length_m;
        const i = crossesGap(profile.gaps, d, d) ? -1 : nearestIndex(profile.points, d);
        hover = i >= 0 && profile.points[i][1] !== null ? i : null;
    }
    // Fenêtre de la déclivité locale : 1 km, élargie à quatre intervalles moyens (deux de part et
    // d'autre) quand les points reçus sont plus espacés (profil allégé d'une longue ligne), pour
    // garder au moins trois points.
    const gradeWindow = $derived(
        Math.max(GRADE_WINDOW_M, (4 * profile.length_m) / Math.max(1, profile.points.length - 1)),
    );
    const reading = $derived.by(() => {
        if (hover === null) return null;
        const [d, z] = profile.points[hover];
        return {
            d,
            z,
            grade: gradeAt(profile.points, d, profile.gaps, gradeWindow),
            tunnel: tunnels.some((tu) => d >= tu.from_m && d <= tu.to_m),
            left: x(d) > pad.left + plotW / 2,
        };
    });
</script>

<SheetSection id="profile" title={t('profile.title')} {intro}>
    <div class="profile">
        <StatStrip items={statItems} label={t('profile.stats_label')} size="sm" />
        <p class="grade-where">{t('profile.grade')}{colon(lang)}{gradeWhere}</p>

        <figure class="chart">
            <div class="plot" bind:clientWidth={width}>
                {#if width > 0}
                    <svg
                        viewBox="0 0 {width} {height}"
                        {width}
                        {height}
                        role="img"
                        aria-label={t('profile.chart_label', {
                            min: formatNumber(Math.round(s.min_altitude_m), lang),
                            max: formatNumber(Math.round(s.max_altitude_m), lang),
                            km: km(profile.length_m, 0),
                        })}
                    >
                        <defs>
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
                            <text class="tick" x={x(k * 1000)} y={baseY + 22} text-anchor="middle"
                                >{formatNumber(k, lang)}</text
                            >
                        {/each}
                        <text class="axis" x={pad.left + plotW} y={height - 4} text-anchor="end"
                            >{t('profile.axis_distance')}</text
                        >

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

                        <path class="area" d={area} />
                        <path class="line" d={line} />

                        {#each profile.stations as st (st.slug)}
                            {#if st.altitude_m !== null}
                                <!-- Trait vers le nom pour les seules gares nommées ; les autres ont leur point. -->
                                {#if named.has(st)}
                                    <line
                                        class="station-tick"
                                        x1={x(st.distance_m)}
                                        x2={x(st.distance_m)}
                                        y1={pad.top - 6}
                                        y2={y(st.altitude_m)}
                                    />
                                {/if}
                                {#if !dense || named.has(st)}
                                    <circle
                                        class="station-dot"
                                        cx={x(st.distance_m)}
                                        cy={y(st.altitude_m)}
                                        r="3.5"
                                    />
                                {/if}
                            {/if}
                            {#if named.has(st)}
                                <text
                                    class="station-name"
                                    x={x(st.distance_m)}
                                    y={pad.top - 12}
                                    text-anchor={named.get(st)}>{st.name}</text
                                >
                            {/if}
                        {/each}

                        {#if reading}
                            <line
                                class="cursor"
                                x1={x(reading.d)}
                                x2={x(reading.d)}
                                y1={pad.top}
                                y2={baseY}
                            />
                            <circle class="cursor-dot" cx={x(reading.d)} cy={y(reading.z)} r="5" />
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
                            onpointerleave={() => (hover = null)}
                        />
                    </svg>

                    {#if reading}
                        <div
                            class={['reading', { left: reading.left }]}
                            style:--at="{x(reading.d)}px"
                            aria-hidden="true"
                        >
                            <strong>{t('profile.at_km', { km: km(reading.d) })}</strong>
                            <span>{metres(reading.z)}</span>
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
            {#if tunnels.length}
                <figcaption>
                    <span class="legend-swatch" aria-hidden="true"></span>{t(
                        'profile.tunnel_legend',
                    )}
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
                                    <th scope="row">{st.name}</th>
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
        font-size: 14px;
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
    .station-dot {
        fill: var(--vff-bg-elevated);
        stroke: var(--vff-text);
        stroke-width: 1.5;
    }
    .station-name {
        font-family: Inter, sans-serif;
        font-size: 14px;
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
        align-items: center;
        gap: 8px;
        margin-top: var(--vff-space-2);
        font-size: var(--vff-fs-xs);
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

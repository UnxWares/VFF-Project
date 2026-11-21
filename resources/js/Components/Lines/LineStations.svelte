<script>
    import { Link } from '@inertiajs/svelte';
    import { i18n } from '$lib/i18n.js';
    import { STATION_STYLES, STATION_CLOSED_COLOR } from '$lib/railStyle.js';
    import { formatStationDate } from '$lib/stationDates.js';
    import { firstOfMonth, formatLocale, semicolon } from '$lib/typo.js';

    /**
     * Toutes les gares de la ligne, dans l'ordre du tracé, même fermées ou sur une
     * portion déposée : PK, commune, dates d'ouverture et de fermeture (à leur
     * précision), état aujourd'hui et un fil des décennies (état de la gare à chaque
     * décennie cartographiée, la même source que la carte et la fiche gare), expliqué par
     * une légende et lu en clair par les lecteurs d'écran (« 1860 à 1870 : ouverte ; … »).
     * Liste longue : deux colonnes sur grand écran, repliée au-delà de COLLAPSE gares.
     */
    let { stations = [], eraYears = [] } = $props();

    const COLLAPSE = 24;
    let expanded = $state(false);

    const lang = $derived($i18n.language ?? 'fr');
    const t = (k, o) => String($i18n.t(k, o));
    const styleOf = (status) => STATION_STYLES.find((s) => s.key === status);
    const color = (status) => styleOf(status)?.color ?? STATION_CLOSED_COLOR;
    const OPEN = ['active', 'tourist', 'no_commercial'];

    const shown = $derived(
        expanded || stations.length <= COLLAPSE + 6 ? stations : stations.slice(0, COLLAPSE),
    );
    // Statuts présents dans les fils (pour la légende).
    const legendKeys = $derived.by(() => {
        const seen = new Set(stations.flatMap((s) => Object.values(s.eras ?? {})));
        return [...STATION_STYLES.map((s) => s.key).filter((k) => seen.has(k))];
    });
    // « pas encore ouverte » est écrit pour le fil lu en clair ; dans la légende, comme les autres états.
    const upperFirst = (text) => text.charAt(0).toLocaleUpperCase(lang) + text.slice(1);
    const todayOf = (s) => s.status_today ?? s.era_status ?? 'declassified';
    // États d'aujourd'hui présents (couleur des ronds sur le fil) et présence d'un terminus.
    const dotKeys = $derived.by(() => {
        const seen = new Set(stations.map(todayOf));
        return STATION_STYLES.map((s) => s.key).filter((k) => seen.has(k));
    });
    const hasTerminal = $derived(stations.some((s) => s.is_terminal));

    /** Ouverture : une date déduite des lignes (provenance « inferred:… ») est dite telle. */
    function openedText(s) {
        const date = dateOf(s.opened_at, s.opened_precision);
        return String(s.opened_source ?? '').startsWith('inferred')
            ? t('lines.stations.opened_inferred', { date })
            : t('lines.stations.opened', { date });
    }

    /** Même libellé que la fiche gare et la carte (précision, intervalle « entre … et … »). */
    function dateOf(iso, precision, after = null) {
        return firstOfMonth(
            formatStationDate(iso, precision ?? 'day', after, $i18n.t, formatLocale(lang)),
            lang,
        );
    }

    /** Fil des décennies en clair, par plages : « 1860 à 1870 : ouverte ; 1880 à 2010 : … ». */
    function lifeText(s) {
        const runs = [];
        eraYears.forEach((y, i) => {
            const st = s.eras?.[y] ?? 'none';
            const label = i === eraYears.length - 1 && y > new Date().getFullYear() ? null : y;
            const last = runs.at(-1);
            if (last && last.st === st) last.to = label ?? t('lines.sheet.stations.today');
            else runs.push({ st, from: label ?? t('lines.sheet.stations.today'), to: null });
        });
        return runs
            .map((r) => {
                const state = t(`lines.stations.status.${r.st}`).toLowerCase();
                return r.to == null
                    ? t('lines.sheet.stations.single', { year: r.from, state })
                    : t('lines.sheet.stations.run', { from: r.from, to: r.to, state });
            })
            .join(semicolon(lang));
    }
</script>

{#if eraYears.length}
    <div class="legend">
        <p class="legend-title">{t('lines.sheet.stations.legend_title')}</p>
        <p class="legend-text">
            {t('lines.sheet.stations.legend_text', { from: eraYears[0] })}
        </p>
        <ul aria-label={t('lines.sheet.stations.legend_title')}>
            {#each legendKeys as k (k)}
                <li>
                    <span class="tick" data-status={k} style:--c={color(k)}></span>{t(
                        `lines.stations.status.${k}`,
                    )}
                </li>
            {/each}
            <li><span class="tick empty"></span>{upperFirst(t('lines.stations.status.none'))}</li>
        </ul>
        <p class="legend-text">{t('lines.sheet.stations.dots_text')}</p>
        <ul aria-label={t('lines.sheet.stations.dots_label')}>
            {#each dotKeys as k (k)}
                <li>
                    <span class="dot" style:--c={color(k)}></span>{t(`lines.stations.status.${k}`)}
                </li>
            {/each}
            {#if hasTerminal}
                <li>
                    <span class="dot terminal" style:--c={color('active')}></span>{t(
                        'lines.stops.terminus',
                    )}
                </li>
            {/if}
        </ul>
    </div>
{/if}

<ol class={['stations', { cols: stations.length > 8 }]} id="stations-list">
    {#each shown as s (s.slug)}
        {@const today = todayOf(s)}
        <li class={{ closed: !OPEN.includes(today), terminal: s.is_terminal }}>
            <span class="pk">{s.pk_label ?? ''}</span>
            <span class={['dot', { terminal: s.is_terminal }]} style:--c={color(today)}></span>
            <div class="info">
                <Link href="/stations/{s.slug}" class="name">{s.name}</Link>
                {#if s.city && s.city !== s.name}<span class="city"
                        >{s.city}{s.department_code ? ` (${s.department_code})` : ''}</span
                    >{/if}
                <span class="dates"
                    >{[
                        s.opened_at ? openedText(s) : null,
                        s.closed_at
                            ? t('lines.stations.closed', {
                                  date: dateOf(s.closed_at, s.closed_precision, s.closed_after),
                              })
                            : null,
                    ]
                        .filter(Boolean)
                        .join(', ')}</span
                >
                {#if eraYears.length}
                    <!-- Texte masqué plutôt qu'aria-label : sans feuille de style, l'état par décennie
                         reste écrit (RGAA 10.2) ; la bande colorée est alors invisible. -->
                    <span class="vff-sr-only"
                        >{t('lines.sheet.stations.life_label', { text: lifeText(s) })}</span
                    >
                    <span class="life" aria-hidden="true">
                        {#each eraYears as y (y)}
                            {@const st = s.eras?.[y]}
                            <span
                                class={['tick', { empty: !st }]}
                                data-status={st}
                                style:--c={st ? color(st) : 'transparent'}
                            ></span>
                        {/each}
                    </span>
                {/if}
            </div>
            <span class="badges">
                {#if s.is_terminal}<span class="vff-badge accent">{t('lines.stops.terminus')}</span
                    >{/if}
                <span class="vff-badge"
                    ><span class="vff-swatch" style:--swatch={color(today)}></span>{t(
                        `lines.stations.status.${today}`,
                    )}</span
                >
            </span>
        </li>
    {/each}
</ol>

{#if stations.length > COLLAPSE + 6}
    <button
        type="button"
        class="vff-btn secondary small more"
        aria-expanded={expanded}
        aria-controls="stations-list"
        onclick={() => (expanded = !expanded)}
    >
        <i class="bi {expanded ? 'bi-chevron-up' : 'bi-chevron-down'}" aria-hidden="true"></i>
        {expanded
            ? t('lines.sheet.stations.show_less')
            : t('lines.sheet.stations.show_all', { count: stations.length })}
    </button>
{/if}

<style>
    .legend {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        gap: 6px 16px;
        margin: 0 0 var(--vff-space-5);
        padding: 12px 16px;
        border-radius: var(--vff-radius-md);
        background: var(--vff-bg-subtle);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-xs);
        color: var(--vff-text-muted);
    }
    .legend p {
        margin: 0;
    }
    .legend-title {
        font-weight: 600;
        color: var(--vff-text);
    }
    .legend ul {
        display: flex;
        flex-wrap: wrap;
        gap: 4px 16px;
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .legend li {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: var(--vff-fs-xs);
    }
    /* Légende : cases un peu plus grandes que celles des gares, pour que le motif se lise. */
    .legend .tick {
        flex: none;
        width: 18px;
        height: 10px;
    }
    .legend .tick[data-status='declassified'] {
        height: 4px;
    }
    .legend .dot {
        margin: 0;
    }
    .stations {
        margin: 0;
        padding: 0;
        list-style: none;
    }
    /* Plus de huit gares : deux colonnes sur grand écran (dans l'ordre du tracé, colonne par colonne). */
    @media (min-width: 1000px) {
        .stations.cols {
            column-count: 2;
            column-gap: 48px;
        }
    }
    /* Colonnes PK et fil : le trait se place par le calcul (milieu de la colonne du rond), il
       reste centré sous le rond quelle que soit la largeur ou le zoom. Rond à 19 px du haut de la
       gare (9 px de marge + 3 px + moitié du rond). */
    .stations {
        --pk-w: 64px;
        --dot-col: 32px;
        --col-gap: 8px;
        --dot-y: 19px;
    }
    .stations > li {
        position: relative;
        display: grid;
        grid-template-columns: var(--pk-w) var(--dot-col) minmax(0, 1fr) auto;
        gap: 0 var(--col-gap);
        align-items: start;
        padding: 9px 0;
        break-inside: avoid;
        font-size: var(--vff-fs-sm);
    }
    /* Fil de la ligne : un tronçon par gare, du point à la gare suivante. */
    .stations > li::before {
        content: '';
        position: absolute;
        top: 0;
        bottom: 0;
        left: calc(var(--pk-w) + var(--col-gap) + var(--dot-col) / 2 - 1px);
        width: 2px;
        background: var(--vff-border-strong);
    }
    /* Le fil commence au premier rond et finit au dernier (sur deux colonnes, il continue d'une
       colonne à l'autre). */
    .stations > li:first-child::before {
        top: var(--dot-y);
    }
    .stations > li:last-child::before {
        bottom: calc(100% - var(--dot-y));
    }
    .pk {
        padding-top: 3px;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-xs);
        font-variant-numeric: tabular-nums;
        text-align: right;
        color: var(--vff-text-muted);
    }
    .dot {
        z-index: 1;
        flex: none;
        justify-self: center;
        box-sizing: border-box;
        width: 14px;
        height: 14px;
        margin-top: 3px;
        border: 3px solid var(--c);
        border-radius: 50%;
        background: var(--vff-bg);
    }
    .dot.terminal {
        width: 18px;
        height: 18px;
        margin-top: 1px;
        background: var(--c);
    }
    .info {
        display: flex;
        flex-direction: column;
        gap: 2px;
        min-width: 0;
    }
    .info :global(.name) {
        align-self: flex-start;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-md);
        font-weight: 600;
        line-height: 1.35;
        color: var(--vff-text);
        text-decoration: underline;
        text-decoration-color: var(--vff-border-strong);
        text-underline-offset: 3px;
    }
    /* Cible de 24 px au moins, sans décaler la liste. */
    .info :global(.name) {
        margin: -2px 0;
        padding: 2px 0;
    }
    .info :global(.name:hover) {
        color: var(--vff-accent-ink);
        text-decoration-color: currentColor;
    }
    .stations > li.closed .info :global(.name) {
        color: var(--vff-text-muted);
    }
    .city,
    .dates {
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-xs);
        color: var(--vff-text-muted);
    }
    .life {
        display: flex;
        align-items: center;
        gap: 2px;
        height: 6px;
        margin-top: 4px;
    }
    /* 9 px par décennie ; sur une colonne étroite (320 px : 21 décennies pour 192 px), les cases
       se resserrent au lieu de sortir de la colonne. */
    .tick {
        flex: 0 1 9px;
        min-width: 5px;
        height: 6px;
        border-radius: 1px;
        background: var(--c);
    }
    /* Case vide : contour de commande (3:1 au moins, RGAA 3.3), pas un filet décoratif. */
    .tick.empty {
        background: transparent;
        box-shadow: inset 0 0 0 1px var(--vff-border-control);
    }
    /* La couleur ne porte pas seule l'état (RGAA 3.1) : ouverte = case pleine, fret seul = deux
       traits superposés, touristique = barres verticales, état non confirmé = case hachurée,
       fermée = case plus basse, absente = case vide. */
    .tick[data-status='no_commercial'] {
        background: linear-gradient(
            var(--c) 0 34%,
            var(--vff-bg-elevated) 34% 66%,
            var(--c) 66% 100%
        );
    }
    .tick[data-status='tourist'] {
        background: repeating-linear-gradient(
            90deg,
            var(--c) 0 2px,
            var(--vff-bg-elevated) 2px 3px
        );
        box-shadow: inset 0 0 0 1px var(--c);
    }
    .tick[data-status='uncertain'] {
        background: repeating-linear-gradient(
            135deg,
            var(--c) 0 2px,
            var(--vff-bg-elevated) 2px 4px
        );
        box-shadow: inset 0 0 0 1px var(--c);
    }
    .tick[data-status='declassified'] {
        height: 3px;
    }
    .badges {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: 6px;
        padding-top: 1px;
    }
    .more {
        margin-top: var(--vff-space-4);
    }
    @media (max-width: 600px) {
        .stations {
            --pk-w: 52px;
            --dot-col: 28px;
        }
        .stations > li {
            grid-template-columns: var(--pk-w) var(--dot-col) minmax(0, 1fr);
        }
        .badges {
            grid-column: 3;
            justify-content: flex-start;
            padding-top: 6px;
        }
    }
</style>

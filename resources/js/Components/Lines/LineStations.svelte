<script>
    import { i18n } from '$lib/i18n.js';
    import { STATION_STYLES, STATION_CLOSED_COLOR } from '$lib/railStyle.js';
    import { formatStationDate } from '$lib/stationDates.js';
    import { colon } from '$lib/typo.js';

    /**
     * Toutes les gares de la ligne, dans l'ordre du tracé, même fermées ou sur une
     * portion déposée : PK, commune, dates d'ouverture et de fermeture (à leur
     * précision), état aujourd'hui et un fil des décennies (état de la gare à chaque
     * décennie cartographiée, la même source que la carte et la fiche gare).
     */
    let { stations = [], eraYears = [] } = $props();

    const lang = $derived($i18n.language ?? 'fr');
    const color = (status) =>
        STATION_STYLES.find((s) => s.key === status)?.color ?? STATION_CLOSED_COLOR;
    const OPEN = ['active', 'tourist', 'no_commercial'];

    /** Même libellé que la fiche gare et la carte (précision, intervalle « entre … et … »). */
    function dateOf(iso, precision, after = null) {
        return formatStationDate(iso, precision ?? 'day', after, $i18n.t, lang);
    }
</script>

<ol class="stations">
    {#each stations as s (s.slug)}
        {@const today = s.status_today ?? s.era_status ?? 'declassified'}
        <li class:closed={!OPEN.includes(today)} class:terminal={s.is_terminal}>
            <span class="pk">{s.pk_label ?? ''}</span>
            <span class="dot" style="--c: {color(today)}"></span>
            <div class="info">
                <a href="/stations/{s.slug}">{s.name}</a>
                {#if s.city && s.city !== s.name}<span class="city"
                        >{s.city}{s.department_code ? ` (${s.department_code})` : ''}</span
                    >{/if}
                <span class="dates"
                    >{[
                        s.opened_at
                            ? $i18n.t('lines.stations.opened', {
                                  date: dateOf(s.opened_at, s.opened_precision),
                              })
                            : null,
                        s.closed_at
                            ? $i18n.t('lines.stations.closed', {
                                  date: dateOf(s.closed_at, s.closed_precision, s.closed_after),
                              })
                            : null,
                    ]
                        .filter(Boolean)
                        .join(', ')}</span
                >
                {#if eraYears.length}
                    <span class="life" aria-label={$i18n.t('lines.stations.life')}>
                        {#each eraYears as y (y)}
                            {@const st = s.eras?.[y]}
                            <span
                                class="tick"
                                class:empty={!st}
                                style="--c: {st ? color(st) : 'transparent'}"
                                title="{y}{colon(lang)}{st
                                    ? $i18n.t(`lines.stations.status.${st}`)
                                    : $i18n.t('lines.stations.status.none')}"
                            ></span>
                        {/each}
                    </span>
                {/if}
            </div>
            <span class="badges">
                {#if s.is_terminal}<span class="badge terminus"
                        >{$i18n.t('lines.stops.terminus')}</span
                    >{/if}
                <span class="badge status" style="--c: {color(today)}"
                    >{$i18n.t(`lines.stations.status.${today}`)}</span
                >
            </span>
        </li>
    {/each}
</ol>

<style>
    .stations {
        list-style: none;
        margin: 0;
        padding: 0;
        position: relative;
    }
    .stations::before {
        content: '';
        position: absolute;
        left: 79px;
        top: 18px;
        bottom: 18px;
        width: 2px;
        background: var(--vff-border-strong);
    }
    li {
        font-size: 14px;
        display: grid;
        grid-template-columns: 64px 32px 1fr auto;
        align-items: start;
        gap: 0 8px;
        padding: 9px 0;
        position: relative;
    }
    .pk {
        font-family: var(--vff-font-heading);
        font-size: 12px;
        color: var(--vff-text-muted);
        text-align: right;
        font-variant-numeric: tabular-nums;
        padding-top: 3px;
    }
    .dot {
        justify-self: center;
        width: 14px;
        height: 14px;
        margin-top: 3px;
        border-radius: 50%;
        border: 3px solid var(--c);
        background: var(--vff-bg);
        z-index: 1;
    }
    li.terminal .dot {
        width: 18px;
        height: 18px;
        margin-top: 1px;
        background: var(--c);
    }
    .info {
        display: flex;
        flex-direction: column;
        gap: 1px;
        min-width: 0;
    }
    .info a {
        font-family: var(--vff-font-heading);
        font-weight: 600;
        font-size: 15px;
        color: var(--vff-text);
        text-decoration: none;
    }
    .info a:hover {
        color: var(--vff-accent);
    }
    /* Cible tactile de 24 px au moins sur téléphone, sans décaler la liste. */
    @media (max-width: 768px) {
        .info a {
            padding: 3px 0;
            margin: -3px 0;
        }
    }
    li.closed .info a {
        color: var(--vff-text-muted);
    }
    .city,
    .dates {
        font-size: 12.5px;
        color: var(--vff-text-muted);
    }
    .life {
        display: flex;
        gap: 2px;
        margin-top: 4px;
    }
    .tick {
        width: 9px;
        height: 5px;
        border-radius: 1px;
        background: var(--c);
    }
    .tick.empty {
        background: var(--vff-bg-muted);
    }
    .badges {
        display: flex;
        gap: 6px;
        align-items: center;
        padding-top: 2px;
    }
    .badge {
        font-family: var(--vff-font-heading);
        font-size: 11.5px;
        font-weight: 600;
        padding: 2px 8px;
        border-radius: var(--vff-radius-pill);
        white-space: nowrap;
    }
    .badge.terminus {
        background: var(--vff-accent);
        color: #fff;
    }
    .badge.status {
        border: 1px solid var(--c);
        color: var(--vff-text);
    }
    @media (max-width: 640px) {
        li {
            grid-template-columns: 48px 28px 1fr;
        }
        .stations::before {
            left: 61px;
        }
        .badges {
            grid-column: 3;
        }
    }
</style>

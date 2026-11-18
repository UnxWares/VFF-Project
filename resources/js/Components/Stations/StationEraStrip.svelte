<script>
    import { i18n } from '$lib/i18n.js';
    import { STATION_STATUS_COLOR } from './stationStatus.js';

    /**
     * Frise des décennies : statut de la gare sur la carte de chaque ère
     * (station_eras). Case vide = la gare n'existe pas à cette date.
     * Chaque case ouvre la carte à l'ère correspondante.
     */
    let { eras = [] } = $props();

    const label = (e) =>
        e.status ? $i18n.t(`stations.statuses.${e.status}`) : $i18n.t('stations.statuses.absent');
    // Typographie : espace avant les deux-points en français seulement.
    const colon = $derived(($i18n.language ?? 'fr') === 'fr' ? ' : ' : ': ');
    /** Infobulle : année, statut et note (déjà dans la langue de la page, cf. GeneratedText). */
    const tip = (e) => `${e.year}${colon}${label(e)}${e.note ? ` (${e.note})` : ''}`;
    // Décennies « incertaines » faute de date de fermeture : explication sous la frise.
    const hasUndated = $derived(eras.some((e) => e.undated));
</script>

<ol class="strip">
    {#each eras as e (e.year)}
        <li>
            <a
                href="/maps?era={e.year}"
                class="cell"
                class:empty={!e.status}
                class:current={e.is_current}
                style="--c: {STATION_STATUS_COLOR[e.status] ?? 'transparent'}"
                title={tip(e)}
                aria-label={tip(e)}
            >
                <span class="bar"></span>
                <span class="year">{e.is_current ? $i18n.t('stations.era_today') : e.year}</span>
            </a>
        </li>
    {/each}
</ol>

<ul class="legend">
    {#each ['active', 'no_commercial', 'uncertain', 'declassified'] as s}
        <li><i style="--c: {STATION_STATUS_COLOR[s]}"></i>{$i18n.t(`stations.statuses.${s}`)}</li>
    {/each}
    <li><i class="none"></i>{$i18n.t('stations.statuses.absent')}</li>
    {#if hasUndated}
        <li class="undated">
            <i class="bi bi-question-circle"></i>{$i18n.t('stations.sections.eras_undated')}
        </li>
    {/if}
</ul>

<style>
    .strip {
        list-style: none;
        margin: 0;
        padding: 0;
        display: grid;
        /* Ordinateur : toutes les décennies sur une ligne (22 cases). */
        grid-auto-flow: column;
        grid-auto-columns: minmax(0, 1fr);
        gap: 4px;
    }
    /* Tablette et téléphone : plusieurs rangées de cases de même largeur. */
    @media (max-width: 900px) {
        .strip {
            grid-auto-flow: row;
            grid-template-columns: repeat(auto-fill, minmax(44px, 1fr));
        }
    }
    .cell {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 6px;
        text-decoration: none;
        color: var(--vff-text-muted);
        padding: 4px 0;
        border-radius: var(--vff-radius-md);
        transition: background-color var(--vff-transition);
    }
    .cell:hover {
        background-color: color-mix(in srgb, var(--vff-accent) 8%, transparent);
    }
    .bar {
        width: 100%;
        height: 26px;
        border-radius: 6px;
        background-color: var(--c);
    }
    .cell.empty .bar {
        background: repeating-linear-gradient(135deg, var(--vff-border) 0 4px, transparent 4px 8px);
        opacity: 0.7;
    }
    .year {
        font-family: var(--vff-font-display);
        font-size: 11px;
        letter-spacing: 0.03em;
    }
    .cell.current .year {
        color: var(--vff-accent-ink);
        font-weight: 700;
    }
    .cell:focus-visible {
        outline: 2px solid var(--vff-accent);
        outline-offset: 2px;
    }

    .legend {
        list-style: none;
        display: flex;
        flex-wrap: wrap;
        gap: 14px;
        padding: 0;
        margin: 12px 0 0;
        font-size: 12px;
        color: var(--vff-text-muted);
    }
    .legend li {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 12px;
        line-height: 1.4;
    }
    .legend i {
        width: 12px;
        height: 12px;
        border-radius: 3px;
        background-color: var(--c);
        display: inline-block;
    }
    .legend i.none {
        background: repeating-linear-gradient(135deg, var(--vff-border) 0 3px, transparent 3px 6px);
    }
    .legend li.undated {
        flex-basis: 100%;
    }
    .legend li.undated i {
        width: auto;
        height: auto;
        background: none;
    }
</style>

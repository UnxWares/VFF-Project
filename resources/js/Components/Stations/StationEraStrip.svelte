<script>
    import { i18n } from '$lib/i18n.js';
    import { STATION_STATUS_COLOR, STATION_STATUS_ICON } from './stationStatus.js';

    /**
     * Frise des décennies : statut de la gare sur la carte de chaque ère (station_eras).
     * Chaque case porte la couleur ET une icône du statut (la couleur ne porte jamais seule
     * l'information) ; case en tirets = la gare n'existe pas à cette date. Chaque case ouvre la
     * carte à l'ère correspondante. Les notes de chaque décennie sont aussi listées sous la frise
     * (le survol n'existe pas au toucher).
     */
    let { eras = [] } = $props();

    const STATUS_ORDER = [
        'active',
        'tourist',
        'no_commercial',
        'uncertain',
        'declassified',
        'gone',
    ];

    const label = (e) =>
        e.status ? $i18n.t(`stations.statuses.${e.status}`) : $i18n.t('stations.statuses.absent');
    // Typographie : espace insécable avant les deux-points en français seulement.
    const colon = $derived(($i18n.language ?? 'fr') === 'fr' ? ' : ' : ': ');
    /**
     * Année affichée dans la case ; la décennie en cours se lit « Auj. » (« Today », « Heute »).
     * Le nom accessible commence par ce texte visible (WCAG 2.5.3), développé s'il est abrégé.
     */
    const yearText = (e) => (e.is_current ? $i18n.t('stations.era_today') : String(e.year));
    function spoken(e) {
        if (!e.is_current) return yearText(e);
        const long = $i18n.t('stations.lines.era.today');
        const short = yearText(e);
        return short.toLowerCase() === long.toLowerCase() ? short : `${short} (${long})`;
    }
    /** Nom accessible d'une case : année, statut et note (déjà dans la langue de la page). */
    const tip = (e) => `${spoken(e)}${colon}${label(e)}${e.note ? ` (${e.note})` : ''}`;

    /** Encre lisible (noire ou blanche) sur une couleur de statut : le meilleur contraste des deux. */
    function inkFor(hex) {
        const m = /^#([0-9a-f]{6})$/i.exec(hex ?? '');
        if (!m) return 'var(--vff-text)';
        const lin = [0, 2, 4].map((i) => {
            const v = parseInt(m[1].slice(i, i + 2), 16) / 255;
            return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4;
        });
        const lum = 0.2126 * lin[0] + 0.7152 * lin[1] + 0.0722 * lin[2];
        return (lum + 0.05) / 0.05 >= 1.05 / (lum + 0.05) ? '#000' : '#fff';
    }

    const inkOf = (status) => inkFor(STATION_STATUS_COLOR[status]);

    // Rangées équilibrées sur tablette (11 cases au plus) et téléphone (7 au plus) : 21 décennies
    // donnent 11 + 10, puis 7 + 7 + 7, jamais une dernière rangée presque vide.
    const columns = (max) => Math.ceil(eras.length / Math.ceil(eras.length / max)) || 1;

    // Légende : seulement les statuts réellement présents dans la frise.
    const legend = $derived(STATUS_ORDER.filter((s) => eras.some((e) => e.status === s)));
    const hasAbsent = $derived(eras.some((e) => !e.status));
    // Décennies « incertaines » faute de date de fermeture : explication sous la frise.
    const hasUndated = $derived(eras.some((e) => e.undated));
    const noted = $derived(eras.filter((e) => e.note));
</script>

<ol
    class="strip"
    style:--n={eras.length || 1}
    style:--cols-tablet={columns(11)}
    style:--cols-phone={columns(7)}
>
    {#each eras as e (e.year)}
        <li>
            <a
                href="/maps?era={e.year}"
                class="cell"
                class:current={e.is_current}
                style:--c={STATION_STATUS_COLOR[e.status] ?? 'transparent'}
                style:--ink={inkOf(e.status)}
                aria-label={tip(e)}
                aria-current={e.is_current ? 'date' : undefined}
            >
                <span class="bar" class:empty={!e.status} aria-hidden="true">
                    {#if e.status}<i class="bi {STATION_STATUS_ICON[e.status] ?? 'bi-circle'}"
                        ></i>{/if}
                </span>
                <span class="year" aria-hidden="true">{yearText(e)}</span>
            </a>
        </li>
    {/each}
</ol>

<ul class="legend">
    {#each legend as s (s)}
        <li>
            <span class="bar sample" style:--c={STATION_STATUS_COLOR[s]} style:--ink={inkOf(s)}
                ><i class="bi {STATION_STATUS_ICON[s] ?? 'bi-circle'}" aria-hidden="true"></i></span
            >{$i18n.t(`stations.statuses.${s}`)}
        </li>
    {/each}
    {#if hasAbsent}
        <li>
            <span class="bar sample empty" aria-hidden="true"></span>{$i18n.t(
                'stations.statuses.absent',
            )}
        </li>
    {/if}
</ul>

{#if hasUndated}
    <p class="undated">
        <i class="bi bi-question-circle" aria-hidden="true"></i>
        {$i18n.t('stations.sections.eras_undated')}
    </p>
{/if}

{#if noted.length}
    <details class="notes">
        <summary>{$i18n.t('stations.sections.eras_notes')}</summary>
        <ul>
            {#each noted as e (e.year)}
                <li>
                    <strong>{yearText(e)}</strong>
                    <span lang={e.note_lang}>{label(e)} ({e.note})</span>
                </li>
            {/each}
        </ul>
    </details>
{/if}

<style>
    .strip {
        list-style: none;
        margin: 0;
        padding: 0;
        display: grid;
        /* Ordinateur : toutes les décennies sur une ligne. */
        grid-template-columns: repeat(var(--n, 21), minmax(0, 1fr));
        gap: 4px;
    }
    /* Tablette et téléphone : rangées de cases de même largeur. */
    @media (max-width: 900px) {
        .strip {
            grid-template-columns: repeat(var(--cols-tablet, 11), minmax(0, 1fr));
            gap: 8px 4px;
        }
    }
    @media (max-width: 600px) {
        .strip {
            grid-template-columns: repeat(var(--cols-phone, 7), minmax(0, 1fr));
        }
    }
    .cell {
        display: flex;
        flex-direction: column;
        align-items: stretch;
        gap: 6px;
        padding: 4px 0;
        border-radius: var(--vff-radius-sm);
        color: var(--vff-text-muted);
        text-decoration: none;
        transition: background-color var(--vff-transition);
    }
    .cell:hover {
        background-color: var(--vff-accent-soft);
    }
    .bar {
        display: grid;
        place-items: center;
        height: 28px;
        border-radius: var(--vff-radius-sm);
        background-color: var(--c);
        color: var(--ink, var(--vff-text));
        font-size: 14px;
    }
    .bar.empty {
        background: none;
        border: 1.5px dashed var(--vff-border-control);
    }
    .year {
        text-align: center;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-xs);
        font-variant-numeric: tabular-nums;
    }
    .cell.current .year {
        color: var(--vff-accent-ink);
        font-weight: 700;
        text-decoration: underline;
        text-underline-offset: 3px;
    }

    .legend {
        display: flex;
        flex-wrap: wrap;
        gap: 8px 24px;
        margin: var(--vff-space-4) 0 0;
        padding: 0;
        list-style: none;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: 1.4;
        color: var(--vff-text-muted);
    }
    .legend li {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .sample {
        flex-shrink: 0;
        width: 28px;
        height: 20px;
        font-size: 12px;
    }
    .undated {
        display: flex;
        gap: 8px;
        max-width: var(--vff-measure);
        margin: var(--vff-space-3) 0 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: 1.5;
        color: var(--vff-text-muted);
    }
    .undated i {
        flex-shrink: 0;
        margin-top: 2px;
    }

    .notes {
        margin-top: var(--vff-space-4);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
    }
    .notes summary {
        display: inline-flex;
        align-items: center;
        min-height: 32px;
        cursor: pointer;
        color: var(--vff-accent-ink);
        font-weight: 600;
        text-decoration: underline;
        text-underline-offset: 3px;
    }
    .notes ul {
        margin: var(--vff-space-2) 0 0;
        padding: 0;
        list-style: none;
        border-top: 1px solid var(--vff-border);
    }
    .notes li {
        display: grid;
        grid-template-columns: 56px minmax(0, 1fr);
        gap: 4px 16px;
        padding: 8px 0;
        border-bottom: 1px solid var(--vff-border);
        line-height: 1.5;
        color: var(--vff-text);
    }
    .notes strong {
        font-variant-numeric: tabular-nums;
    }
</style>

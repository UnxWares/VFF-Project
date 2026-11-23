<script>
    import { formatStationDate, formatStationOpening } from '$lib/stationDates.js';
    import { tollText } from '$lib/accidentText.js';
    import EmptyState from '$components/ui/EmptyState.svelte';
    import { i18n } from '$lib/i18n.js';

    /**
     * Chronologie fusionnée d'une commune (App\Services\Communes\CommuneSheet) : ouvertures et fermetures
     * des gares et des lignes, électrifications, accidents. Chaque entrée porte sa date, sa précision et
     * ce qu'elle concerne (gare, ligne et sections, accident).
     *
     * @type {{ entries?: Array<any> }}
     */
    let { entries = [] } = $props();

    const lang = $derived($i18n.language ?? 'fr');
    const t = (key, opts) => String($i18n.t(key, opts));

    const whenText = (e) =>
        e.kind === 'station_opening'
            ? formatStationOpening(e.date, e.precision, e.after, t, lang)
            : formatStationDate(e.date, e.precision, e.after, t, lang);

    /** Une date encadrée ou une borne n'a pas de jour sûr : pas d'attribut datetime. */
    const machineDate = (e) =>
        e.precision === 'day'
            ? e.date
            : e.precision === 'month'
              ? e.date.slice(0, 7)
              : e.precision === 'year'
                ? e.date.slice(0, 4)
                : undefined;

    const precisionKey = (e) =>
        $i18n.exists(`communes.chronology.precision.${e.precision}`)
            ? `communes.chronology.precision.${e.precision}`
            : 'communes.chronology.precision.year';

    const sectionText = (s) => t('communes.chronology.sections', { from: s.from, to: s.to });

    /** « Déraillement. 3 morts » : nature et bilan. */
    const accidentFacts = (a) =>
        `${t(`accidents.kinds.${a.kind}`)}. ${tollText(a.deaths, a.injuries, $i18n.t)}`;
</script>

{#if entries.length}
    <ol class="timeline">
        {#each entries as e, i (i)}
            <li class={{ accident: e.kind === 'accident' }}>
                <p class="when"><time datetime={machineDate(e)}>{whenText(e)}</time></p>
                <div class="what">
                    {#if e.kind === 'accident'}
                        <p class="ev-type">
                            <a href="/accidents/{e.accident.slug}">{e.accident.name}</a>
                        </p>
                        <p class="ev-note">{accidentFacts(e.accident)}</p>
                    {:else}
                        <p class="ev-type">{t(`communes.chronology.kinds.${e.kind}`)}</p>
                        {#if e.station}
                            <p class="ev-note">
                                <a href="/stations/{e.station.slug}">{e.station.name}</a>
                            </p>
                        {:else if e.line}
                            <p class="ev-note">
                                <a href="/lines/{e.line.slug}">{e.line.name}</a
                                >{#if e.line.code}&nbsp;<span class="code">({e.line.code})</span
                                    >{/if}{#if e.sections.length}
                                    <span class="sections"
                                        >{e.sections.map(sectionText).join(' ; ')}</span
                                    >{/if}
                            </p>
                        {/if}
                    {/if}
                    <p class="ev-source"><span class="precision">{t(precisionKey(e))}</span></p>
                </div>
            </li>
        {/each}
    </ol>
{:else}
    <EmptyState icon="bi-calendar-x" title={t('communes.chronology.none')} />
{/if}

<style>
    /* Même ligne du temps que la fiche d'une gare : date à gauche, fait à droite, point dans la gouttière. */
    .timeline {
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .timeline li {
        position: relative;
        display: grid;
        grid-template-columns: minmax(0, 200px) minmax(0, 1fr);
        gap: 4px 32px;
        padding: 0 0 var(--vff-space-5) 28px;
    }
    .timeline li::before {
        content: '';
        position: absolute;
        top: 7px;
        left: 0;
        width: 12px;
        height: 12px;
        box-sizing: border-box;
        border: 2px solid var(--vff-accent);
        border-radius: 50%;
        background-color: var(--vff-accent);
    }
    .timeline li.accident::before {
        border-color: var(--vff-text);
        background-color: var(--vff-text);
    }
    .timeline li:not(:last-child)::after {
        content: '';
        position: absolute;
        top: 24px;
        bottom: -2px;
        left: 5px;
        width: 2px;
        background-color: var(--vff-border-strong);
    }
    .timeline p {
        margin: 0;
    }
    .when {
        font-family: var(--vff-font-display);
        font-size: var(--vff-fs-h3);
        font-weight: 700;
        line-height: 1.35;
        font-variant-numeric: tabular-nums;
        color: var(--vff-text);
        text-wrap: balance;
    }
    .what {
        min-width: 0;
    }
    .ev-type {
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-md);
        font-weight: 600;
        line-height: 1.5;
        color: var(--vff-text);
    }
    .ev-type a {
        color: inherit;
    }
    .ev-note {
        max-width: var(--vff-measure);
        margin-top: 4px;
        font-size: var(--vff-fs-sm);
        line-height: 1.55;
        color: var(--vff-text);
        text-wrap: pretty;
    }
    .sections {
        display: block;
        color: var(--vff-text-muted);
    }
    .code {
        color: var(--vff-text-muted);
        font-variant-numeric: tabular-nums;
    }
    .ev-source {
        margin-top: 4px;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: 1.5;
        color: var(--vff-text-muted);
    }
    .precision {
        font-weight: 600;
    }

    @media (max-width: 600px) {
        .timeline li {
            grid-template-columns: minmax(0, 1fr);
        }
    }
</style>

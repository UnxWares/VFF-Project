<script>
    import { Link } from '@inertiajs/svelte';
    import { i18n } from '$lib/i18n.js';
    import { TRACE_FORMATS, defaultEra, traceUrl } from '$lib/traceDownload.js';

    /**
     * Téléchargement du tracé de la ligne (fiche ligne) : choix de l'ère, puis un lien par format
     * (GPX, KML, GeoJSON : API `/api/v1/lines/{slug}/gpx`, `/kml`, `/geojson`). Seules les ères dont
     * le tracé a une source redistribuable sont proposées (LineTraceExport::exportableEraYears) ;
     * sans aucune, un message dit que le tracé n'est pas téléchargeable.
     *
     * Props : slug de la ligne, eras (années exportables), currentYear (année de l'ère courante),
     * known (la ligne a un tracé connu, pour dire pourquoi rien n'est proposé).
     *
     * @type {{ slug: string, eras: number[], currentYear: number|null, known?: boolean }}
     */
    let { slug, eras, currentYear, known = true } = $props();

    const uid = $props.id();
    const t = (k, o) => String($i18n.t(k, o));

    let chosen = $state(null);
    const era = $derived(chosen ?? defaultEra(eras, currentYear));
    const eraLabel = (year) => (year === currentYear ? t('lines.decades.today') : String(year));
</script>

{#if eras.length}
    <div class="download" role="group" aria-labelledby={`${uid}-title`}>
        <h3 id={`${uid}-title`}>{t('lines.trace_download.title')}</h3>
        <p class="intro">{t('lines.trace_download.intro')}</p>
        <div class="controls">
            <label class="era" for={`${uid}-era`}>
                <span>{t('lines.trace_download.era')}</span>
                <select
                    id={`${uid}-era`}
                    class="vff-select"
                    onchange={(e) => (chosen = Number(e.currentTarget.value))}
                >
                    {#each eras as year (year)}
                        <option value={year} selected={year === era}>{eraLabel(year)}</option>
                    {/each}
                </select>
            </label>
            <ul class="formats">
                {#each TRACE_FORMATS as format (format.key)}
                    <li>
                        <a class="vff-btn secondary" href={traceUrl(slug, format, era)}>
                            <i class="bi bi-download" aria-hidden="true"></i>
                            {format.label}<span class="vff-sr-only">
                                ({t('lines.trace_download.for_era', { era: eraLabel(era) })})</span
                            >
                        </a>
                    </li>
                {/each}
            </ul>
        </div>
        <p class="note">
            {t('lines.trace_download.note')}
            <Link href="/api" class="vff-link">{t('lines.trace_download.api')}</Link>
        </p>
    </div>
{:else if known}
    <p class="unavailable" role="note">{t('lines.trace_download.unavailable')}</p>
{/if}

<style>
    .download {
        max-width: var(--vff-measure);
        margin-top: var(--vff-block-space);
    }
    h3 {
        margin: 0 0 var(--vff-space-2);
        font-size: var(--vff-fs-h3);
    }
    .intro,
    .note,
    .unavailable {
        margin: 0 0 var(--vff-space-3);
        line-height: 1.6;
    }
    .unavailable {
        margin-top: var(--vff-block-space);
        color: var(--vff-text-muted);
    }
    .note {
        margin: var(--vff-space-3) 0 0;
        color: var(--vff-text-muted);
    }
    .controls {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        gap: var(--vff-space-3) var(--vff-space-4);
    }
    .era {
        display: flex;
        flex-direction: column;
        gap: 6px;
        min-width: 11rem;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        font-weight: 600;
    }
    .formats {
        display: flex;
        flex-wrap: wrap;
        gap: var(--vff-space-2);
        margin: 0;
        padding: 0;
        list-style: none;
    }
</style>

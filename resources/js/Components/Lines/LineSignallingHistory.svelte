<script>
    import { i18n } from '$lib/i18n.js';
    import { formatLocale } from '$lib/typo.js';
    import {
        articleTitles,
        hasDocumentFacts,
        historyRows,
        statusesAt,
        statusText,
    } from '$lib/signallingHistory.js';

    /**
     * Frise « Signalisation au fil du temps » : mises en service de signalisation datées, lues dans
     * l'article Wikipédia de la ligne ou dans les documents relevés par VFF (communiqués, rapports,
     * presse : un lien par document) (LineSignallingHistory::forSheet côté serveur). À placer dans la
     * section « Signalisation » ; à n'afficher que s'il y a au moins un fait. L'état actuel (tranches de
     * SNCF Réseau) reste dans la grille voisine : ces dates ne le corrigent pas.
     *
     * Avec `moment` (phase choisie dans « Le tracé dans le temps », phaseMoment), chaque fait est situé
     * par rapport à cette date : en vigueur, remplacé, pas encore en service, ou incertain quand sa date
     * n'est connue qu'à l'année ou au mois près (statusesAt, même règle que la carte par ère).
     *
     * @type {{ history: Array<any>, moment?: { start: string, end: string } | null, momentLabel?: string | null, momentSection?: string | null }}
     */
    let { history, moment = null, momentLabel = null, momentSection = null } = $props();

    const t = (k, o) => String($i18n.t(k, o));
    const lang = $derived($i18n.language ?? 'fr');
    const rows = $derived(
        historyRows(history, { t, lang: formatLocale(lang), pkWord: t('lines.sheet.tech.pk') }),
    );
    const statuses = $derived(moment ? statusesAt(history, moment) : null);
    const titles = $derived(articleTitles(history));
    const note = $derived(
        titles.length === 0
            ? null
            : t(
                  titles.length === 1
                      ? 'signalling_history.source.note_one'
                      : 'signalling_history.source.note_other',
                  {
                      title: titles.join(' ; '),
                  },
              ),
    );
    const documentsNote = $derived(
        hasDocumentFacts(history) ? t('signalling_history.source.note_documents') : null,
    );
</script>

<h3 class="sub">{t('signalling_history.title')}</h3>
<p class="intro">{t('signalling_history.intro')}</p>
{#if statuses && momentLabel}
    <p class="at" aria-live="polite">
        {t('signalling_history.at.intro', { section: momentSection, date: momentLabel })}
        <a class="vff-link" href="#trace">{t('signalling_history.at.change')}</a>
    </p>
{/if}
<ol class="history">
    {#each rows as r, i (r.key)}
        {@const s = statuses?.[i]}
        <li class:later={s?.status === 'later'}>
            <p class="when"><time datetime={r.iso}>{r.when}</time></p>
            <div class="body">
                <p class="main">
                    <span class="kind">{r.kind}</span>
                    {r.system}
                </p>
                <p class="portion">
                    <span>{r.portion}</span>
                    {#if r.documents.length > 0}
                        {#each r.documents as d (d.url)}
                            <a
                                class="vff-link"
                                href={d.url}
                                target="_blank"
                                rel="noopener noreferrer"
                                >{d.label}<i
                                    class="bi bi-box-arrow-up-right vff-ext"
                                    aria-hidden="true"
                                ></i><span class="vff-sr-only">
                                    ({t('signalling_history.source.document_hint', {
                                        title: d.title,
                                    })})</span
                                ></a
                            >
                        {/each}
                    {:else}
                        <a class="vff-link" href={r.url} target="_blank" rel="noopener noreferrer"
                            >{t('signalling_history.source.link')}<i
                                class="bi bi-box-arrow-up-right vff-ext"
                                aria-hidden="true"
                            ></i><span class="vff-sr-only">
                                ({t('signalling_history.source.hint', { title: r.title })})</span
                            ></a
                        >
                    {/if}
                </p>
                {#if r.note}
                    <p class="origin">{r.note}</p>
                {/if}
                {#if s}
                    <p class="status" class:in-force={s.status === 'in_force'}>
                        {statusText(s, history[i], t, formatLocale(lang))}
                    </p>
                {/if}
            </div>
        </li>
    {/each}
</ol>
{#if note}
    <p class="note">{note}</p>
{/if}
{#if documentsNote}
    <p class="note">{documentsNote}</p>
{/if}

<style>
    .sub {
        margin: var(--vff-block-space) 0 var(--vff-space-2);
        font-size: var(--vff-fs-h3);
    }
    .intro,
    .at,
    .note {
        max-width: var(--vff-measure);
        margin: 0 0 var(--vff-space-4);
        font-size: var(--vff-fs-md);
        line-height: 1.6;
        color: var(--vff-text-muted);
    }
    .note {
        margin: var(--vff-space-4) 0 0;
        font-size: var(--vff-fs-sm);
    }
    .history {
        margin: 0;
        padding: 0;
        list-style: none;
        border-top: 1px solid var(--vff-border);
    }
    .history li {
        display: grid;
        grid-template-columns: minmax(0, 15em) minmax(0, 1fr);
        gap: 4px var(--vff-space-5);
        padding: 12px 0;
        border-bottom: 1px solid var(--vff-border);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
    }
    .history p {
        margin: 0;
    }
    .when {
        font-variant-numeric: tabular-nums;
    }
    .body {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .main {
        font-size: var(--vff-fs-md);
    }
    .kind {
        margin-right: 4px;
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
    .origin,
    .status {
        color: var(--vff-text-muted);
    }
    .status.in-force {
        font-weight: 600;
        color: var(--vff-text);
    }
    .later .when,
    .later .main {
        color: var(--vff-text-muted);
    }
    .portion {
        display: flex;
        flex-wrap: wrap;
        gap: 2px var(--vff-space-4);
        color: var(--vff-text-muted);
    }
    @media (max-width: 600px) {
        .history li {
            grid-template-columns: minmax(0, 1fr);
        }
    }
</style>

<script>
    import { i18n } from '$lib/i18n.js';
    import { formatLocale } from '$lib/typo.js';
    import { articleTitles, historyRows } from '$lib/signallingHistory.js';

    /**
     * Frise « Signalisation au fil du temps » : mises en service de signalisation datées, lues dans
     * l'article Wikipédia de la ligne (LineSignallingHistory::forSheet côté serveur). À placer dans la
     * section « Signalisation » ; à n'afficher que s'il y a au moins un fait. L'état actuel (tranches de
     * SNCF Réseau) reste dans la grille voisine : ces dates ne le corrigent pas.
     *
     * @type {{ history: Array<any> }}
     */
    let { history } = $props();

    const t = (k, o) => String($i18n.t(k, o));
    const lang = $derived($i18n.language ?? 'fr');
    const rows = $derived(
        historyRows(history, { t, lang: formatLocale(lang), pkWord: t('lines.sheet.tech.pk') }),
    );
    const titles = $derived(articleTitles(history));
    const note = $derived(
        t(
            titles.length === 1
                ? 'signalling_history.source.note_one'
                : 'signalling_history.source.note_other',
            {
                title: titles.join(' ; '),
            },
        ),
    );
</script>

<h3 class="sub">{t('signalling_history.title')}</h3>
<p class="intro">{t('signalling_history.intro')}</p>
<ol class="history">
    {#each rows as r (r.key)}
        <li>
            <p class="when"><time datetime={r.iso}>{r.when}</time></p>
            <div class="body">
                <p class="main">
                    <span class="kind">{r.kind}</span>
                    {r.system}
                </p>
                <p class="portion">
                    <span>{r.portion}</span>
                    <a class="vff-link" href={r.url} target="_blank" rel="noopener noreferrer"
                        >{t('signalling_history.source.link')}<i
                            class="bi bi-box-arrow-up-right vff-ext"
                            aria-hidden="true"
                        ></i><span class="vff-sr-only">
                            ({t('signalling_history.source.hint', { title: r.title })})</span
                        ></a
                    >
                </p>
            </div>
        </li>
    {/each}
</ol>
<p class="note">{note}</p>

<style>
    .sub {
        margin: var(--vff-block-space) 0 var(--vff-space-2);
        font-size: var(--vff-fs-h3);
    }
    .intro,
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

<script>
    import { i18n } from '$lib/i18n.js';
    import { formatEventDate } from '$lib/linePhases.js';
    import { formatLocale } from '$lib/typo.js';
    import SheetSection from '$components/Sheet/SheetSection.svelte';

    /**
     * Section « Dans les archives » de la fiche ligne (LineArchives::forSheet côté serveur) : documents
     * numérisés dans Gallica (BnF) dont le titre désigne la ligne, du plus ancien au plus récent, avec un
     * lien vers Gallica, le type, les auteurs (deux au plus) et les cotes. Les notices de même titre, date et type
     * (plusieurs photographies d'un même événement) forment une seule entrée avec des liens « Autres vues » et
     * toutes leurs cotes. Aucune
     * image n'est chargée : seules les métadonnées de la BnF sont reprises. Au-delà de VISIBLE entrées, la
     * suite se déplie (bouton à aria-expanded). Montrée seulement s'il y a au moins un document.
     *
     * @type {{ archives: { total: number, fetched_on: string|null, items: Array<{ title: string,
     *   type: 'monograph'|'map'|'image'|'manuscript', date: string|null, year: number|null,
     *   year_to: number|null, exact: string|null, date_precision: string, authors: Array<string>,
     *   shelfmarks: Array<string>, url: string, more_urls: Array<string> }> } }}
     */
    let { archives } = $props();

    const VISIBLE = 8;
    /** Séparateur des métadonnées d'un document : « Carte ou plan · Alfred de Villemandy · cote V-54984 ». */
    const SEP = ' · ';
    const listId = $props.id();
    let expanded = $state(false);

    const t = (k, o) => String($i18n.t(k, o));
    const lang = $derived($i18n.language ?? 'fr');
    const shown = $derived(expanded ? archives.items : archives.items.slice(0, VISIBLE));
    // Documents cachés, pas entrées : une entrée regroupe parfois plusieurs notices (autres vues).
    const hiddenCount = $derived(
        archives.items
            .slice(VISIBLE)
            .reduce((sum, item) => sum + 1 + (item.more_urls?.length ?? 0), 0),
    );
    // Titres des notices de la BnF : en français, quelle que soit la langue de la page (sur le titre seul :
    // l'aide « s'ouvre dans un nouvel onglet » suit la langue de la page).
    const titleLang = $derived(lang === 'fr' ? undefined : 'fr');

    /**
     * Date à sa précision : « 1860 », « vers 1850 », « avant 1885 » (borne, pas une date approchée),
     * « 1890-1899 », « 1er novembre 1873 », « Sans date ».
     */
    function dateOf(item) {
        const locale = formatLocale(lang);
        switch (item.date_precision) {
            case 'day':
            case 'month':
                return item.exact
                    ? formatEventDate(item.exact, item.date_precision, locale)
                    : String(item.year);
            case 'circa':
                return t('archives.line.circa', { year: item.year });
            case 'before':
                return t('archives.line.before', { year: item.year });
            case 'after':
                return t('archives.line.after', { year: item.year });
            case 'range':
                return item.year_to ? `${item.year}-${item.year_to}` : String(item.year);
            case 'year':
                return String(item.year);
            default:
                return t('archives.line.undated');
        }
    }

    /** « Villemandy, Alfred de » (forme de la notice) → « Alfred de Villemandy » ; une collectivité reste telle quelle. */
    const authorName = (name) => {
        const m = /^([^,]+), ([^,]+)$/.exec(name);
        return m ? `${m[2]} ${m[1]}` : name;
    };
    const listFormat = $derived(
        new Intl.ListFormat(formatLocale(lang), { style: 'long', type: 'conjunction' }),
    );
    /**
     * Métadonnées d'un document : type, auteurs (deux au plus, « Antoine-Rémi Polonceau et Jean-Baptiste
     * Bélanger ») et cotes, qui ne se coupent pas (« GE C-3528 », « cotes YE-54393, YE-54394 »).
     */
    const metaOf = (item) =>
        [
            { text: t(`archives.line.types.${item.type}`), nowrap: false },
            item.authors.length
                ? {
                      text: listFormat.format(item.authors.slice(0, 2).map(authorName)),
                      nowrap: false,
                  }
                : null,
            item.shelfmarks.length
                ? {
                      text: t('archives.line.shelfmark', {
                          shelfmark: item.shelfmarks.join(', '),
                          count: item.shelfmarks.length,
                      }),
                      nowrap: true,
                  }
                : null,
        ].filter(Boolean);

    const sourceNote = $derived(
        t('archives.line.source', {
            date: archives.fetched_on
                ? formatEventDate(archives.fetched_on, 'day', formatLocale(lang))
                : '',
        }),
    );
</script>

{#snippet ext()}<i class="bi bi-box-arrow-up-right vff-ext" aria-hidden="true"></i><span
        class="vff-sr-only"
    >
        ({t('archives.line.gallica_hint')}, {t('common.new_tab')})</span
    >{/snippet}

<SheetSection
    id="archives"
    title={t('archives.line.title')}
    count={archives.total}
    intro={t('archives.line.intro')}
>
    <ol class="archive-list" id={listId}>
        {#each shown as item (item.url)}
            <li>
                <span class="when">{dateOf(item)}</span>
                <div class="body">
                    <a class="doc-title" href={item.url} target="_blank" rel="noopener noreferrer"
                        ><span lang={titleLang}>{item.title}</span>{@render ext()}</a
                    >
                    <span class="meta"
                        >{#each metaOf(item) as part, k (k)}{#if k > 0}{SEP}{/if}<span
                                class={{ nowrap: part.nowrap }}>{part.text}</span
                            >{/each}</span
                    >
                    {#if item.more_urls.length}
                        <span class="meta views"
                            >{t('archives.line.other_views')}
                            {#each item.more_urls as url, i (url)}
                                <a href={url} target="_blank" rel="noopener noreferrer"
                                    ><span aria-hidden="true">{i + 2}</span><span
                                        class="vff-sr-only"
                                        >{t('archives.line.view', { number: i + 2 })}</span
                                    >{@render ext()}</a
                                >
                            {/each}</span
                        >
                    {/if}
                </div>
            </li>
        {/each}
    </ol>
    {#if hiddenCount > 0}
        <div class="actions">
            <button
                type="button"
                class="vff-btn secondary small"
                aria-expanded={expanded}
                aria-controls={listId}
                onclick={() => (expanded = !expanded)}
            >
                {expanded
                    ? t('archives.line.show_less')
                    : t('archives.line.show_more', { count: hiddenCount })}
            </button>
        </div>
    {/if}
    <p class="note">{t('archives.line.method')} {sourceNote}</p>
</SheetSection>

<style>
    /* Registre daté à filets, comme la liste des accidents : date en colonne, le document à sa droite. */
    .archive-list {
        max-width: 860px;
        margin: 0;
        padding: 0;
        list-style: none;
        border-top: 1px solid var(--vff-border);
    }
    .archive-list li {
        display: grid;
        grid-template-columns: 9rem minmax(0, 1fr);
        gap: 4px 24px;
        padding: 14px 0;
        border-bottom: 1px solid var(--vff-border);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-ui);
    }
    .when {
        color: var(--vff-text);
        font-variant-numeric: tabular-nums;
        font-weight: 600;
    }
    .body {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 4px;
        min-width: 0;
    }
    .doc-title {
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-body);
        line-height: var(--vff-lh-body);
        color: var(--vff-link);
        text-underline-offset: 0.18em;
        text-wrap: pretty;
        overflow-wrap: anywhere;
    }
    .doc-title:hover {
        text-decoration-thickness: 2px;
    }
    .meta {
        color: var(--vff-text-muted);
    }
    /* Une cote ne se coupe pas (« GE C-3528 »). */
    .nowrap {
        white-space: nowrap;
    }
    /* Liens « 2 », « 3 »… : cibles de 24 px au moins (WCAG 2.5.8). */
    .views a {
        display: inline-flex;
        align-items: center;
        min-width: 24px;
        min-height: 24px;
        margin-inline-start: 6px;
    }
    .actions {
        margin-top: var(--vff-space-4);
    }
    .note {
        max-width: var(--vff-measure);
        margin: var(--vff-space-5) 0 0;
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-body);
        color: var(--vff-text-muted);
    }
    @media (max-width: 600px) {
        .archive-list li {
            grid-template-columns: minmax(0, 1fr);
        }
    }
</style>

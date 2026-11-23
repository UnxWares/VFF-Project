<script>
    import { onMount } from 'svelte';
    import { i18n } from '$lib/i18n.js';
    import { formatEventDate } from '$lib/linePhases.js';
    import { formatLocale } from '$lib/typo.js';
    import {
        authorLine,
        imprintLine,
        linkKind,
        oldestBnfDate,
        safeUrl,
    } from '$lib/bibliography.js';
    import SheetSection from '$components/Sheet/SheetSection.svelte';

    /**
     * Section « Pour aller plus loin » des fiches de ligne et de gare (App\Services\Bibliography\SheetBibliography) :
     * les ouvrages et articles qui en traitent, du plus récent au plus ancien, repliés derrière un bouton « Afficher
     * les N références ». Chaque entrée : auteurs, titre, revue, éditeur, lieu, année, ISBN et un lien vers la notice du
     * catalogue de la BnF ou vers la référence d'origine (article de Wikipédia, élément Wikidata). Seules les données
     * des notices sont reprises, jamais le contenu d'un ouvrage. Montrée seulement s'il y a au moins une référence.
     * Chargée à la demande (`GET /api/v1/lines/{slug}/bibliography` ou `/stations/{slug}/bibliography`) pour ne
     * rien ajouter aux lectures des fiches.
     *
     * @type {{ slug: string, bibliography?: null | { total: number, items: Array<{ kind: 'book'|'article', title: string,
     *   authors: Array<string>, publisher: string|null, place: string|null, year: number|null,
     *   isbn: string|null, journal: string|null, url: string|null, source: string,
     *   fetched_on: string|null }> }, subject?: 'line'|'station' }}
     */
    let { slug, bibliography: initial = null, subject = 'line' } = $props();

    // svelte-ignore state_referenced_locally
    let bibliography = $state(initial);

    onMount(async () => {
        if (bibliography) return;
        // Adresses écrites en entier : le contrôle des routes (RoutesUsageTest) les retrouve.
        const url =
            subject === 'station'
                ? `/api/v1/stations/${encodeURIComponent(slug)}/bibliography`
                : `/api/v1/lines/${encodeURIComponent(slug)}/bibliography`;
        try {
            const res = await fetch(url, {
                headers: { Accept: 'application/json' },
            });
            if (res.ok) bibliography = await res.json();
        } catch {
            // Section facultative : sans réponse, la fiche reste complète.
        }
    });

    const t = (k, o) => String($i18n.t(k, o));
    const lang = $derived($i18n.language ?? 'fr');
    // Titres, auteurs et éditeurs viennent des notices : en français, quelle que soit la langue de la page.
    const textLang = $derived(lang === 'fr' ? undefined : 'fr');
    const items = $derived(bibliography?.items ?? []);
    const fetchedOn = $derived(oldestBnfDate(items));
    const sourceNote = $derived(
        fetchedOn
            ? t('bibliography.source', {
                  date: formatEventDate(fetchedOn, 'day', formatLocale(lang)),
              })
            : t('bibliography.source_nodate'),
    );
    const detail = (item) =>
        [
            authorLine(item.authors, formatLocale(lang)),
            imprintLine(item, (journal) => t('bibliography.in_journal', { journal })),
            item.isbn ? t('bibliography.isbn', { isbn: item.isbn }) : '',
        ].filter(Boolean);
</script>

{#if bibliography?.total}
    <SheetSection
        id="further-reading"
        title={t('bibliography.title')}
        count={bibliography.total}
        intro={t(subject === 'station' ? 'bibliography.intro_station' : 'bibliography.intro_line')}
    >
        <details class="refs">
            <summary>{t('bibliography.show', { count: items.length })}</summary>
            <ol class="bib-list" aria-label={t('bibliography.list_label')}>
                {#each items as item, i (`${i}|${item.source}|${item.url}`)}
                    {@const href = safeUrl(item.url)}
                    <li>
                        <span class="when"
                            >{item.year ?? t('bibliography.undated')}<span class="vff-sr-only">
                                ({t(`bibliography.kind.${item.kind}`)})</span
                            ></span
                        >
                        <div class="body">
                            <cite lang={textLang}>{item.title}</cite>
                            {#if detail(item).length}
                                <span class="meta" lang={textLang}>{detail(item).join(' · ')}</span>
                            {/if}
                            {#if href}
                                <a class="origin" {href} target="_blank" rel="noopener noreferrer"
                                    >{t(`bibliography.link.${linkKind(item)}`)}<i
                                        class="bi bi-box-arrow-up-right vff-ext"
                                        aria-hidden="true"
                                    ></i><span class="vff-sr-only">
                                        ({t('common.new_tab')})</span
                                    ></a
                                >
                            {/if}
                        </div>
                    </li>
                {/each}
            </ol>
            {#if bibliography.total > items.length}
                <p class="note">
                    {t('bibliography.truncated', {
                        shown: items.length,
                        total: bibliography.total,
                    })}
                </p>
            {/if}
            <p class="note">{sourceNote}</p>
        </details>
    </SheetSection>
{/if}

<style>
    .refs summary {
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
    .refs[open] summary {
        margin-bottom: var(--vff-space-4);
    }
    /* Registre à filets, comme la liste des archives : l'année en colonne, la référence à sa droite. */
    .bib-list {
        max-width: 860px;
        margin: 0;
        padding: 0;
        list-style: none;
        border-top: 1px solid var(--vff-border);
    }
    .bib-list li {
        display: grid;
        grid-template-columns: 6rem minmax(0, 1fr);
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
    cite {
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-body);
        font-style: italic;
        line-height: var(--vff-lh-body);
        color: var(--vff-text);
        text-wrap: pretty;
        overflow-wrap: anywhere;
    }
    .meta {
        color: var(--vff-text-muted);
        overflow-wrap: anywhere;
    }
    .origin {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        min-height: 24px;
        color: var(--vff-link);
        text-underline-offset: 0.18em;
    }
    .origin:hover {
        text-decoration-thickness: 2px;
    }
    .note {
        max-width: var(--vff-measure);
        margin: var(--vff-space-5) 0 0;
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-body);
        color: var(--vff-text-muted);
    }
    @media (max-width: 600px) {
        .bib-list li {
            grid-template-columns: minmax(0, 1fr);
        }
    }
</style>

<script>
    import { Link, router } from '@inertiajs/svelte';
    import { commonsSrc } from '$lib/commonsImage.js';
    import Layout from '$components/Layout.svelte';
    import SheetHero from '$components/Sheet/SheetHero.svelte';
    import SheetToc from '$components/Sheet/SheetToc.svelte';
    import SeoHead from '$components/SeoHead.svelte';
    import Notice from '$components/ui/Notice.svelte';
    import { i18n } from '$lib/i18n.js';
    import { formatJournalDate, isFallback, isSiteLink } from '$lib/journal.js';
    import { breadcrumbSchema } from '$lib/structuredData.js';

    /**
     * Article du journal de bord (`/journal/{slug}`, JournalController::show). Le texte arrive déjà
     * rendu en HTML par le serveur (App\Services\Journal\JournalRenderer : HTML écrit dans le Markdown
     * échappé, liens vers les fiches résolus, liens externes signalés) ; la page pose le bandeau,
     * le sommaire collant (titres de niveau 2), la photo et son crédit, la mention d'engagement
     * quand l'article en a une, les fiches citées et la licence du texte.
     *
     * Traduction absente : le texte français est servi (`article.lang` = fr), signalé en tête et
     * marqué `lang="fr"` pour la synthèse vocale et la césure.
     *
     * @type {{
     *   article: {
     *     slug: string, title: string, date: string, updated: string|null, summary: string,
     *     lang: string, locales: string[], author: string, disclosure: string|null,
     *     image: { src: string, alt: string, author: string, licence: string, licence_url: string|null,
     *       source_url: string, width: number, height: number, caption: string|null }|null,
     *     html: string, toc: Array<{ id: string, label: string }>,
     *     lines: Array<{ code: string, slug: string, name: string }>,
     *     stations: Array<{ slug: string, name: string, city: string|null }>,
     *     reading_minutes: number, license: string, license_url: string, path: string,
     *     json_ld: object,
     *   },
     *   newer: { slug: string, title: string, lang: string }|null,
     *   older: { slug: string, title: string, lang: string }|null,
     * }}
     */
    let { article, newer = null, older = null } = $props();

    const t = (k, o) => String($i18n.t(k, o));
    const lang = $derived($i18n.language ?? 'fr');
    const fallback = $derived(isFallback(article.lang, lang));
    // Attribut lang du texte : seulement quand il diffère de celui de la page.
    const textLang = $derived(fallback ? article.lang : undefined);
    const published = $derived(formatJournalDate(article.date, lang));
    const updated = $derived(article.updated ? formatJournalDate(article.updated, lang) : null);
    const image = $derived(article.image);

    /** Liens du texte vers les pages du site : navigation du site, sans recharger la page. */
    function onProseClick(event) {
        const link = event.target instanceof Element ? event.target.closest('a[href]') : null;
        if (!link) return;
        const attrs = { href: link.getAttribute('href'), target: link.getAttribute('target') };
        if (!isSiteLink(attrs, event)) return;
        event.preventDefault();
        router.visit(attrs.href);
    }
</script>

<svelte:head>
    <title>{article.title} - {t('journal.meta_title')} - VFF</title>
    <link
        rel="alternate"
        type="application/atom+xml"
        title={t('journal.feed.atom_title')}
        href={`/journal/atom.xml?lang=${lang}`}
    />
</svelte:head>

<SeoHead
    title={article.title}
    description={article.summary}
    path={article.path}
    type="article"
    image={image ? { url: image.src, alt: image.alt } : null}
    jsonLd={(origin) => [
        article.json_ld,
        breadcrumbSchema(origin, [
            { name: t('common.app_full'), path: '/' },
            { name: t('journal.index.title'), path: '/journal' },
            { name: article.title, path: article.path },
        ]),
    ]}
/>

<Layout compactHeader>
    <main>
        <SheetHero
            crumbs={[{ href: '/journal', label: t('journal.article.back'), icon: 'bi-arrow-left' }]}
            crumbsLabel={t('journal.article.breadcrumbs')}
            eyebrow={[
                t('journal.article.published', { date: published }) +
                    (updated && article.updated !== article.date
                        ? `, ${t('journal.article.updated', { date: updated })}`
                        : ''),
                t('journal.article.reading', { count: article.reading_minutes }),
            ]}
            title={article.title}
            titleLang={textLang}
        >
            <p class="lead" lang={textLang}>{article.summary}</p>
            <p class="byline">{t('journal.article.by', { author: article.author })}</p>
        </SheetHero>

        {#if article.toc.length > 1}
            <SheetToc items={article.toc} label={t('journal.article.toc')} />
        {/if}

        <div class="layout">
            <article class="body">
                {#if fallback}
                    <Notice tone="info" live="off">{t('journal.article.fallback')}</Notice>
                {/if}
                {#if article.disclosure}
                    <Notice tone="info" live="off" title={t('journal.article.disclosure_title')}>
                        <span lang={textLang}>{article.disclosure}</span>
                    </Notice>
                {/if}
                {#if image}
                    <figure
                        class="vff-figure hero-figure"
                        style:--ratio="{image.width} / {image.height}"
                    >
                        <img
                            src={commonsSrc(image.src)}
                            alt={image.alt}
                            width={image.width}
                            height={image.height}
                            fetchpriority="high"
                            lang={textLang}
                        />
                        <figcaption>
                            {#if image.caption}<span lang={textLang}>{image.caption}</span>
                            {/if}
                            {t('journal.article.image_credit', {
                                author: image.author,
                                // Licence jamais coupée en fin de ligne (« CC BY-SA 4.0 »).
                                licence: image.licence.replaceAll(' ', '\u00a0'),
                            })},
                            <a href={image.source_url} target="_blank" rel="noopener noreferrer"
                                >{t('journal.article.image_source')}<i
                                    class="bi bi-box-arrow-up-right vff-ext"
                                    aria-hidden="true"
                                ></i><span class="vff-sr-only"> ({t('common.new_tab')})</span></a
                            >
                        </figcaption>
                    </figure>
                {/if}

                <!-- Texte rendu et échappé par le serveur (JournalRenderer) : aucun HTML d'auteur n'y passe. -->
                <!-- svelte-ignore a11y_click_events_have_key_events, a11y_no_static_element_interactions -->
                <div class="vff-prose journal-prose" lang={textLang} onclick={onProseClick}>
                    <!-- eslint-disable-next-line svelte/no-at-html-tags -->
                    {@html article.html}
                </div>

                <p class="license">
                    {t('journal.article.license', { license: article.license })}
                    <a href={article.license_url} target="_blank" rel="noopener noreferrer"
                        >{article.license}<i
                            class="bi bi-box-arrow-up-right vff-ext"
                            aria-hidden="true"
                        ></i><span class="vff-sr-only"> ({t('common.new_tab')})</span></a
                    >
                </p>
            </article>

            {#if article.lines.length || article.stations.length}
                <aside class="cited" aria-labelledby="journal-cited">
                    <h2 id="journal-cited">{t('journal.article.cited')}</h2>
                    {#if article.lines.length}
                        <h3>{t('journal.article.cited_lines')}</h3>
                        <ul>
                            {#each article.lines as l (l.slug)}
                                <li>
                                    <Link href={`/lines/${l.slug}`} class="vff-tile"
                                        ><span class="vff-plate">{l.code}</span>
                                        <span lang="fr">{l.name}</span></Link
                                    >
                                </li>
                            {/each}
                        </ul>
                    {/if}
                    {#if article.stations.length}
                        <h3>{t('journal.article.cited_stations')}</h3>
                        <ul class="stations">
                            {#each article.stations as s (s.slug)}
                                <li>
                                    <Link href={`/stations/${s.slug}`} class="vff-link" lang="fr"
                                        >{s.name}</Link
                                    >
                                </li>
                            {/each}
                        </ul>
                    {/if}
                </aside>
            {/if}
        </div>

        {#if newer || older}
            <nav class="neighbours" aria-label={t('journal.article.neighbours')}>
                {#if newer}
                    <Link href={`/journal/${newer.slug}`} class="vff-tile newer" rel="prev">
                        <span class="dir">{t('journal.article.newer')}</span>
                        <span class="name" lang={newer.lang === lang ? undefined : newer.lang}
                            >{newer.title}</span
                        >
                    </Link>
                {/if}
                {#if older}
                    <Link href={`/journal/${older.slug}`} class="vff-tile older" rel="next">
                        <span class="dir">{t('journal.article.older')}</span>
                        <span class="name" lang={older.lang === lang ? undefined : older.lang}
                            >{older.title}</span
                        >
                    </Link>
                {/if}
            </nav>
        {/if}
    </main>
</Layout>

<style>
    .lead {
        max-width: var(--vff-measure);
        margin: var(--vff-space-4) 0 0;
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-lead);
        line-height: 1.5;
        color: var(--vff-text);
        text-wrap: pretty;
    }
    .byline {
        margin: var(--vff-space-3) 0 var(--vff-space-6);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }

    /* Texte à la largeur de lecture, fiches citées dans une colonne à droite (en dessous sur
       tablette et téléphone). Même grille et même bord gauche que le bandeau. */
    .layout {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 300px;
        gap: var(--vff-space-6) 64px;
        align-items: start;
        max-width: calc(var(--vff-container) + 2 * var(--vff-gutter));
        margin: 0 auto;
        padding: var(--vff-block-space) var(--vff-gutter) var(--vff-section-space);
    }
    .body {
        min-width: 0;
    }
    .body > :global(* + *) {
        margin-top: var(--vff-space-5);
    }
    .hero-figure {
        max-width: 100%;
    }
    .hero-figure img {
        width: 100%;
        height: auto;
        aspect-ratio: var(--ratio);
        object-fit: cover;
    }

    /* Texte rendu : titres avec ancre (le sommaire collant les cache sinon), notes en fin de texte,
       tableaux éventuels. Le style de base vient de .vff-prose. */
    .journal-prose {
        hyphens: auto;
    }
    .journal-prose :global(h2) {
        margin: 2em 0 0.6em;
        font-size: var(--vff-fs-h2);
        line-height: 1.2;
        text-wrap: balance;
        scroll-margin-top: 140px;
    }
    .journal-prose :global(h3) {
        margin: 1.6em 0 0.5em;
        font-size: var(--vff-fs-h3);
        scroll-margin-top: 140px;
    }
    .journal-prose :global(:is(h2, h3)) {
        hyphens: manual;
    }
    /* Appels de note : surélevés mais à la taille minimale du site (17 px), jamais plus petits. */
    .journal-prose :global(sup) {
        font-size: var(--vff-fs-xs);
        line-height: 0;
    }
    /* Cible de 24 px au moins (rembourrage), presque sans écarter le chiffre du mot qui précède
       (marges négatives). Souligné : l'appel se distingue du texte voisin autrement que par la couleur
       (2,63:1 seulement, RGAA 10.6). */
    .journal-prose :global(.footnote-ref) {
        padding: 4px 9px;
        margin: -4px -7px;
        text-decoration: underline;
        text-underline-offset: 3px;
        font-variant-numeric: tabular-nums;
    }
    .journal-prose :global(sup[id]),
    .journal-prose :global(.footnote) {
        scroll-margin-top: 140px;
    }
    .journal-prose :global(.footnotes) {
        font-size: var(--vff-fs-sm);
        line-height: 1.5;
        color: var(--vff-text);
    }
    .journal-prose :global(.footnotes ol) {
        padding-left: 1.6em;
    }
    .journal-prose :global(.footnotes li:target) {
        border-radius: var(--vff-radius-xs);
        background-color: var(--vff-bg-accent);
        outline: 4px solid var(--vff-bg-accent);
    }
    .journal-prose :global(.footnote-backref) {
        display: inline-block;
        min-width: 24px;
        text-align: center;
        text-decoration: none;
    }
    .journal-prose :global(.footnotes a:not(.footnote-backref)) {
        overflow-wrap: anywhere;
    }
    .license {
        max-width: var(--vff-measure);
        padding-top: var(--vff-space-5);
        border-top: 1px solid var(--vff-border);
        font-size: var(--vff-fs-sm);
        line-height: 1.5;
        color: var(--vff-text-muted);
    }

    /* Fiches citées : panneau collant sous le sommaire, à droite du texte. */
    .cited {
        position: sticky;
        top: 140px;
        padding: var(--vff-pad-box);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        background-color: var(--vff-bg-elevated);
    }
    .cited h2 {
        margin: 0 0 var(--vff-space-4);
        font-size: var(--vff-fs-h3);
        font-family: var(--vff-font-heading);
    }
    .cited h3 {
        margin: var(--vff-space-5) 0 var(--vff-space-2);
        font-size: var(--vff-fs-sm);
        font-weight: 600;
        color: var(--vff-text-muted);
    }
    .cited h2 + h3 {
        margin-top: 0;
    }
    .cited ul {
        list-style: none;
        margin: 0;
        padding: 0;
    }
    .cited :global(.vff-tile) {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        gap: var(--vff-space-2);
    }
    .stations {
        display: flex;
        flex-wrap: wrap;
        gap: var(--vff-space-2) var(--vff-space-4);
    }

    /* Articles voisins : deux entrées côte à côte, la plus ancienne à droite. */
    .neighbours {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: var(--vff-space-5) 32px;
        max-width: calc(var(--vff-container) + 2 * var(--vff-gutter));
        margin: 0 auto;
        padding: 0 var(--vff-gutter) var(--vff-section-space);
    }
    .neighbours :global(.vff-tile) {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-1);
    }
    .neighbours :global(.older) {
        grid-column: 2;
        text-align: right;
    }
    .dir {
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
    .name {
        font-family: var(--vff-font-heading);
        font-weight: 700;
        font-size: var(--vff-fs-md);
        color: var(--vff-text);
    }

    @media (max-width: 900px) {
        .layout {
            grid-template-columns: minmax(0, 1fr);
        }
        .cited {
            position: static;
            max-width: var(--vff-measure);
        }
    }
    @media (max-width: 600px) {
        .neighbours {
            grid-template-columns: minmax(0, 1fr);
        }
        .neighbours :global(.older) {
            grid-column: auto;
            text-align: left;
        }
    }
</style>

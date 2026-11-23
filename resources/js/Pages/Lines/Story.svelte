<script>
    import { Link } from '@inertiajs/svelte';
    import Layout from '$components/Layout.svelte';
    import SheetHero from '$components/Sheet/SheetHero.svelte';
    import SheetSection from '$components/Sheet/SheetSection.svelte';
    import SeoHead from '$components/SeoHead.svelte';
    import StoryCarousel from '$components/Stories/StoryCarousel.svelte';
    import { i18n } from '$lib/i18n.js';
    import { formatEventDate } from '$lib/linePhases.js';
    import { firstOfMonth, formatLocale, tieUnits } from '$lib/typo.js';
    import { cleanUrl, linkLabel } from '$lib/linkLabel.js';
    import { breadcrumbSchema } from '$lib/structuredData.js';
    import { mediaCaption } from '$lib/mediaCredits.js';
    import {
        documentDate,
        documentMeta,
        gapPhrase,
        groupDocuments,
        storyTitle,
    } from '$lib/eventStory.js';

    /**
     * Histoire d'un événement de ligne (`/lines/{slug}/stories/{event}`, LineStoryController) :
     * l'événement tel que la base le rapporte (texte, citation, sources), un carrousel des images de la
     * ligne datées à quelques années près et les documents de Gallica de la période (liens seulement).
     * Un fait rapporté par plusieurs sources n'a qu'une histoire : texte de la source la plus sûre
     * (`event`), et toutes les sources citées (`sources`, celle de `event` d'abord).
     * Rien n'est rédigé ici : textes de l'événement (App\Services\Stories\EventStories::text), légendes
     * et crédits des médias, notices de la BnF.
     *
     * @type {{ story: {
     *   line: { slug: string, code: string|null, name: string },
     *   event: Record<string, any>,
     *   sources: Array<Record<string, any>>,
     *   text: { description: string|null, description_lang: string|null, correction: string|null,
     *     quote: string|null, quote_lang: string|null, quote_kind: string|null, section_text: string|null },
     *   window: { years: number, from: number, to: number },
     *   images: Array<Record<string, any>>, images_total: number,
     *   documents: Array<Record<string, any>>,
     *   stations: Array<{ name: string, slug: string }>,
     *   attribution: string|null,
     * } }}
     */
    let { story } = $props();

    const lang = $derived($i18n.language ?? 'fr');
    const t = (k, o) => String($i18n.t(k, o));

    const line = $derived(story.line);
    const event = $derived(story.event);
    const sources = $derived(story.sources ?? [event]);
    const text = $derived(story.text);
    const lineHref = $derived(`/lines/${line.slug}`);
    const path = $derived(`/lines/${line.slug}/stories/${event.id}`);
    const title = $derived(storyTitle(event.type, text.section_text, t));
    const dateText = $derived(
        firstOfMonth(formatEventDate(event.date, event.date_precision, formatLocale(lang)), lang),
    );
    // Noms de lignes et titres des notices de la BnF : en français, quelle que soit la page.
    const frLang = $derived(lang === 'fr' ? undefined : 'fr');

    function sourceLabel(code) {
        const key = `lines.sources.${code}`;
        const label = t(key);
        return label === key ? (code ?? t('lines.sources.unknown')) : label;
    }

    // Notices de même titre, date et type : une entrée, avec les autres vues (comme la fiche).
    const docs = $derived(groupDocuments(story.documents));

    const first = $derived(story.images[0] ?? null);
</script>

<svelte:head>
    <title>{title}, {dateText} - {line.name} - VFF</title>
</svelte:head>

<SeoHead
    title={`${title}, ${dateText}`}
    description={t('stories.meta_description', { event: title, date: dateText, line: line.name })}
    {path}
    type="article"
    image={first
        ? { url: first.display_url ?? first.thumb_url, alt: mediaCaption(first, lang) }
        : null}
    jsonLd={(origin) => [
        breadcrumbSchema(origin, [
            { name: t('common.app_full'), path: '/' },
            { name: t('nav.lines'), path: '/lines' },
            { name: line.name, path: lineHref },
            { name: title, path },
        ]),
    ]}
/>

{#snippet source(s)}{sourceLabel(s.source)}{#if s.source_url},
        <a href={cleanUrl(s.source_url)} target="_blank" rel="noopener noreferrer"
            >{linkLabel(s.source_url, 40, lang)}{@render ext()}</a
        >{/if}{#if s.provenance?.license}{` (${s.provenance.license})`}{/if}{/snippet}

{#snippet ext()}<i class="bi bi-box-arrow-up-right vff-ext" aria-hidden="true"></i><span
        class="vff-sr-only"
    >
        ({t('common.new_tab')})</span
    >{/snippet}

<Layout compactHeader>
    <main>
        <SheetHero
            crumbs={[
                { href: '/lines', label: t('stories.all_lines'), icon: 'bi-list-ul' },
                { href: `${lineHref}#chronicle`, label: line.name, icon: 'bi-arrow-left' },
            ]}
            crumbsLabel={t('stories.breadcrumbs')}
            plate={line.code}
            eyebrow={[dateText]}
            {title}
        />

        <SheetSection id="event" title={t('stories.event.title')}>
            <div class="vff-prose event">
                {#if text.description}
                    <p lang={text.description_lang}>
                        {tieUnits(firstOfMonth(text.description, lang))}
                    </p>
                {/if}
                {#if text.quote && text.quote_kind === 'citation'}
                    <p class="data"><q lang={text.quote_lang}>{text.quote}</q></p>
                {:else if text.quote}
                    <p class="data" lang={text.quote_lang}>{tieUnits(text.quote)}</p>
                {/if}
                {#if text.correction}
                    <p class="note" lang={lang === 'fr' ? undefined : 'fr'}>{text.correction}</p>
                {/if}
                {#if sources.length > 1}
                    <p class="note sources-title">{t('stories.event.sources')}</p>
                    <ul class="note sources">
                        {#each sources as s (s.id)}
                            <li>{@render source(s)}</li>
                        {/each}
                    </ul>
                {:else}
                    <p class="note">
                        {t('stories.event.source')}
                        {@render source(event)}
                    </p>
                {/if}
                {#if story.stations.length}
                    <p class="note">
                        {t('stories.event.stations')}
                        {#each story.stations as s, i (s.slug)}<Link href={`/stations/${s.slug}`}
                                >{s.name}</Link
                            >{i < story.stations.length - 1 ? ', ' : ''}{/each}
                    </p>
                {/if}
            </div>
        </SheetSection>

        <SheetSection
            id="images"
            title={t('stories.images.title')}
            count={story.images_total}
            intro={t('stories.images.intro', {
                from: story.window.from,
                to: story.window.to,
                years: story.window.years,
            })}
        >
            <StoryCarousel images={story.images} label={t('stories.carousel.label')} />
            {#if story.images_total > story.images.length}
                <p class="note">
                    {t('stories.images.limited', {
                        shown: story.images.length,
                        total: story.images_total,
                    })}
                </p>
            {/if}
        </SheetSection>

        {#if story.documents.length}
            <SheetSection
                id="documents"
                title={t('stories.documents.title')}
                count={docs.length}
                intro={t('stories.documents.intro', {
                    from: story.window.from,
                    to: story.window.to,
                })}
            >
                <ol class="docs">
                    {#each docs as d (d.gallica_ark)}
                        <li>
                            <span class="when"
                                >{documentDate(d.date, t, lang)},
                                <span class="gap"
                                    >{gapPhrase(
                                        d.gap_years,
                                        t,
                                        false,
                                        d.date?.precision === 'circa',
                                    )}</span
                                ></span
                            >
                            <span class="body">
                                <a href={d.gallica_url} target="_blank" rel="noopener noreferrer"
                                    ><span lang={frLang}>{d.title}</span>{@render ext()}</a
                                >
                                <span class="meta">{documentMeta(d, t)}</span>
                                {#if d.more_urls.length}
                                    <span class="meta"
                                        >{t('archives.line.other_views')}
                                        {#each d.more_urls as url, i (url)}
                                            <a href={url} target="_blank" rel="noopener noreferrer"
                                                ><span aria-hidden="true">{i + 2}</span><span
                                                    class="vff-sr-only"
                                                    >{t('archives.line.view', {
                                                        number: i + 2,
                                                    })}</span
                                                >{@render ext()}</a
                                            >{i < d.more_urls.length - 1 ? ' ' : ''}
                                        {/each}</span
                                    >
                                {/if}
                            </span>
                        </li>
                    {/each}
                </ol>
                {#if story.attribution}<p class="note">{story.attribution}</p>{/if}
            </SheetSection>
        {/if}

        <div class="back">
            <Link class="vff-btn secondary" href={`${lineHref}#chronicle`}>
                <i class="bi bi-arrow-left" aria-hidden="true"></i>
                {t('stories.back')}
            </Link>
        </div>
    </main>
</Layout>

<style>
    .event p {
        margin: 0 0 var(--vff-space-3);
    }
    .event .data {
        color: var(--vff-text);
    }
    .note {
        max-width: var(--vff-measure);
        margin: var(--vff-space-3) 0 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-ui);
        color: var(--vff-text-muted);
        overflow-wrap: anywhere;
    }
    .event .note {
        margin: 0 0 var(--vff-space-2);
    }
    .event .sources-title {
        margin-bottom: var(--vff-space-1);
    }
    .sources {
        padding-left: 1.25rem;
    }

    /* Registre daté à filets, comme « Dans les archives » de la fiche : date et écart à gauche. */
    .docs {
        max-width: 860px;
        margin: 0;
        padding: 0;
        list-style: none;
        border-top: 1px solid var(--vff-border);
    }
    .docs li {
        display: grid;
        grid-template-columns: 11rem minmax(0, 1fr);
        gap: 4px 24px;
        padding: 14px 0;
        border-bottom: 1px solid var(--vff-border);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-ui);
    }
    .when {
        font-weight: 600;
        font-variant-numeric: tabular-nums;
        color: var(--vff-text);
    }
    .gap {
        font-weight: 400;
        color: var(--vff-text-muted);
    }
    .body {
        display: flex;
        flex-direction: column;
        gap: 4px;
        min-width: 0;
    }
    .body a {
        font-size: var(--vff-fs-md);
        overflow-wrap: anywhere;
    }
    /* Titre d'un document sur une ligne : cible d'au moins 24 px de haut (WCAG 2.5.8), comme le reste du site. */
    .body > a {
        min-height: 24px;
    }
    .meta {
        color: var(--vff-text-muted);
    }

    .back {
        max-width: calc(var(--vff-container) + 2 * var(--vff-gutter));
        margin: var(--vff-space-6) auto 0;
        padding: 0 var(--vff-gutter) var(--vff-section-space);
    }

    @media (max-width: 600px) {
        .docs li {
            grid-template-columns: minmax(0, 1fr);
        }
    }
</style>

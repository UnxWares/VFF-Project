<script>
    import { Link, router } from '@inertiajs/svelte';
    import Layout from '$components/Layout.svelte';
    import PageHero from '$components/PageHero.svelte';
    import SeoHead from '$components/SeoHead.svelte';
    import Pagination from '$components/Directory/Pagination.svelte';
    import EmptyState from '$components/ui/EmptyState.svelte';
    import { i18n } from '$lib/i18n.js';
    import { formatJournalDate, isFallback } from '$lib/journal.js';

    /**
     * Liste du journal de bord (`/journal`, JournalController::index) : un article par rangée, du plus
     * récent au plus ancien, avec sa date, son titre, son résumé, les lignes dont il parle et sa
     * photo. Dix articles par page (`?page=`). Un article encore sans traduction est signalé « en
     * français » et son titre et son résumé sont marqués `lang="fr"`.
     *
     * @type {{
     *   articles: Array<{ slug: string, title: string, date: string, summary: string, lang: string,
     *     image: { src: string, alt: string, width: number, height: number }|null,
     *     lines: Array<{ code: string, slug: string, name: string }> }>,
     *   pagination: { current: number, last: number, total: number, per_page: number },
     * }}
     */
    let { articles, pagination } = $props();

    const t = (k, o) => String($i18n.t(k, o));
    const lang = $derived($i18n.language ?? 'fr');

    function goToPage(page) {
        router.get('/journal', page > 1 ? { page } : {}, { preserveScroll: false });
    }
</script>

<svelte:head>
    <title>{t('journal.meta_title')} - VFF</title>
    <link
        rel="alternate"
        type="application/atom+xml"
        title={t('journal.feed.atom_title')}
        href={`/journal/atom.xml?lang=${lang}`}
    />
    <link
        rel="alternate"
        type="application/rss+xml"
        title={t('journal.feed.rss_title')}
        href={`/journal/rss.xml?lang=${lang}`}
    />
</svelte:head>

<SeoHead
    title={t('journal.meta_title')}
    description={t('journal.meta_description')}
    path={pagination.current > 1 ? `/journal?page=${pagination.current}` : '/journal'}
/>

<Layout compactHeader>
    <main>
        <PageHero title={t('journal.index.title')} subtitle={t('journal.index.subtitle')}>
            <p class="feeds">
                {t('journal.index.feeds')}
                <a href={`/journal/atom.xml?lang=${lang}`}>{t('journal.index.atom')}</a>,
                <a href={`/journal/rss.xml?lang=${lang}`}>{t('journal.index.rss')}</a>
            </p>
        </PageHero>

        <div class="list-wrap">
            {#if articles.length}
                <p class="count">{t('journal.index.count', { count: pagination.total })}</p>
                <ol class="articles" aria-label={t('journal.index.list_label')}>
                    {#each articles as a (a.slug)}
                        {@const other = isFallback(a.lang, lang) ? a.lang : undefined}
                        <li>
                            <article class={{ entry: true, 'has-image': a.image }}>
                                <div class="text">
                                    <p class="meta">
                                        <time datetime={a.date}
                                            >{formatJournalDate(a.date, lang)}</time
                                        >
                                        {#if other}<span class="vff-badge muted"
                                                >{t('journal.index.only_fr')}</span
                                            >{/if}
                                    </p>
                                    <h2 lang={other}>
                                        <Link href={`/journal/${a.slug}`}>{a.title}</Link>
                                    </h2>
                                    <p class="summary" lang={other}>{a.summary}</p>
                                    {#if a.lines.length}
                                        <ul class="lines">
                                            {#each a.lines as l (l.slug)}
                                                <li>
                                                    <span class="vff-plate">{l.code}</span>
                                                    <span lang="fr">{l.name}</span>
                                                </li>
                                            {/each}
                                        </ul>
                                    {/if}
                                </div>
                                {#if a.image}
                                    <img
                                        class="thumb"
                                        src={a.image.src}
                                        alt=""
                                        width={a.image.width}
                                        height={a.image.height}
                                        loading="lazy"
                                    />
                                {/if}
                            </article>
                        </li>
                    {/each}
                </ol>
                {#if pagination.last > 1}
                    <Pagination
                        current={pagination.current}
                        last={pagination.last}
                        onpage={goToPage}
                    />
                {/if}
            {:else}
                <EmptyState
                    headingLevel={2}
                    title={t('journal.index.empty_title')}
                    text={t('journal.index.empty_text')}
                >
                    <Link href="/lines" class="vff-btn secondary small"
                        >{t('journal.index.empty_action')}</Link
                    >
                </EmptyState>
            {/if}
        </div>
    </main>
</Layout>

<style>
    .feeds {
        margin: var(--vff-space-4) 0 0;
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
    .list-wrap {
        max-width: calc(var(--vff-container) + 2 * var(--vff-gutter));
        margin: 0 auto;
        padding: var(--vff-block-space) var(--vff-gutter) var(--vff-section-space);
    }
    .count {
        margin: 0 0 var(--vff-space-4);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
        font-variant-numeric: tabular-nums;
    }

    /* Une rangée par article, filet entre les rangées ; photo à droite du texte quand elle existe.
       Le lien du titre couvre toute la rangée (pseudo-élément), le reste reste sélectionnable. */
    .articles {
        list-style: none;
        margin: 0 0 var(--vff-block-space);
        padding: 0;
        border-top: 1px solid var(--vff-border);
    }
    .articles > li {
        border-bottom: 1px solid var(--vff-border);
    }
    .entry {
        position: relative;
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        gap: var(--vff-space-5) 48px;
        align-items: start;
        padding: var(--vff-space-6) 0;
    }
    .entry.has-image {
        grid-template-columns: minmax(0, 1fr) minmax(0, 340px);
    }
    .text {
        max-width: var(--vff-measure);
    }
    .meta {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: var(--vff-space-2) var(--vff-space-3);
        margin: 0 0 var(--vff-space-2);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
        font-variant-numeric: tabular-nums;
    }
    h2 {
        margin: 0 0 var(--vff-space-3);
        font-size: var(--vff-fs-h2);
        line-height: 1.2;
        text-wrap: balance;
    }
    h2 :global(a) {
        color: var(--vff-text);
        text-decoration: none;
        transition: color var(--vff-transition);
    }
    h2 :global(a)::after {
        content: '';
        position: absolute;
        inset: 0;
        border-radius: var(--vff-radius-md);
    }
    .entry:hover h2 :global(a) {
        color: var(--vff-accent-ink);
        text-decoration: underline;
    }
    h2 :global(a:focus-visible) {
        outline: none;
    }
    h2 :global(a:focus-visible)::after {
        outline: var(--vff-focus-width) solid var(--vff-focus);
        outline-offset: var(--vff-focus-offset);
    }
    .summary {
        margin: 0;
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-body);
        line-height: 1.6;
        color: var(--vff-text);
        text-wrap: pretty;
    }
    .lines {
        display: flex;
        flex-wrap: wrap;
        gap: var(--vff-space-2) var(--vff-space-4);
        list-style: none;
        margin: var(--vff-space-4) 0 0;
        padding: 0;
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
    .lines li {
        display: flex;
        align-items: baseline;
        gap: var(--vff-space-2);
    }
    .thumb {
        width: 100%;
        height: auto;
        aspect-ratio: 3 / 2;
        object-fit: cover;
        border-radius: var(--vff-radius-md);
        background-color: var(--vff-bg-muted);
    }

    @media (max-width: 900px) {
        .entry.has-image {
            grid-template-columns: minmax(0, 1fr) minmax(0, 220px);
            gap: var(--vff-space-5) 24px;
        }
    }
    /* Téléphone : la photo passe au-dessus du texte. */
    @media (max-width: 600px) {
        .entry.has-image {
            grid-template-columns: minmax(0, 1fr);
        }
        .thumb {
            order: -1;
        }
    }
</style>

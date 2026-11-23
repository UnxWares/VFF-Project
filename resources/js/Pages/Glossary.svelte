<script>
    /**
     * « Glossaire » : le vocabulaire ferroviaire des fiches (infrastructure, signalisation, traction et
     * électrification, exploitation, histoire administrative, matériel), défini en une ou deux phrases dans la
     * langue de la page. Chaque terme a une ancre (`#bal`) que les sigles des fiches pointent (GlossaryText).
     * Présentation par thème (défaut) ou par ordre alphabétique (`?vue=alpha`, lien : marche sans JavaScript) ;
     * la recherche, côté navigateur, filtre sans tenir compte des accents. Les termes viennent de
     * App\Support\Glossary (resources/content/glossary, lang/{fr,en,de}/glossary_terms.php).
     */
    import { Link } from '@inertiajs/svelte';
    import Layout from '$components/Layout.svelte';
    import PageHero from '$components/PageHero.svelte';
    import SeoHead from '$components/SeoHead.svelte';
    import EmptyState from '$components/ui/EmptyState.svelte';
    import { i18n } from '$lib/i18n.js';
    import { formatNumber } from '$lib/typo.js';
    import { groupTerms, matchesTerm } from '$lib/glossary.js';

    /**
     * @typedef {{ slug: string, name: string, full: string|null, definition: string, theme: string,
     *   abbr: string|null, letter: string, see: Array<{ slug: string, name: string }>,
     *   example: { path: string, name?: string, page?: string }|null }} Term
     */
    /** @type {{ terms: Term[], themes: string[], view: 'theme'|'alpha' }} */
    let { terms, themes, view } = $props();

    /** @param {string} key @param {Record<string, string|number>} [vars] */
    const t = (key, vars) => String($i18n.t(`glossary.${key}`, vars));
    const lang = $derived($i18n.language ?? 'fr');
    const n = (v) => formatNumber(v, lang);

    let q = $state('');
    const shown = $derived(terms.filter((term) => matchesTerm(term, q)));
    const groups = $derived(groupTerms(shown, view, themes));
    const groupTitle = (g) => (view === 'theme' ? t(`themes.${g.key}`) : g.key);
    const groupId = (g) => (view === 'theme' ? `theme-${g.key}` : `lettre-${g.key}`);
    const views = $derived([
        { id: 'theme', href: '/glossary', label: t('view_theme') },
        { id: 'alpha', href: '/glossary?vue=alpha', label: t('view_alpha') },
    ]);
</script>

<SeoHead title={t('meta_title')} description={t('meta_description')} path="/glossary" />
<svelte:head>
    <title>{t('meta_title')} - VFF</title>
</svelte:head>

<Layout compactHeader>
    <main>
        <PageHero title={t('title')} subtitle={t('subtitle', { total: n(terms.length) })} />

        <div class="vff-container glossary">
            <p class="note">{t('intro_note')}</p>

            <div class="tools">
                <nav class="views" aria-label={t('nav_label')}>
                    <ul>
                        {#each views as v (v.id)}
                            <li>
                                <Link
                                    href={v.href}
                                    class="pill"
                                    aria-current={view === v.id ? 'page' : undefined}
                                    preserveScroll
                                    only={['view']}>{v.label}</Link
                                >
                            </li>
                        {/each}
                    </ul>
                </nav>

                <div class="vff-field search">
                    <label class="vff-label" for="glossary-q">{t('search_label')}</label>
                    <input
                        id="glossary-q"
                        class="vff-input"
                        type="search"
                        autocomplete="off"
                        enterkeyhint="search"
                        placeholder={t('search_hint')}
                        bind:value={q}
                    />
                </div>
            </div>

            <nav class="jump" aria-label={view === 'theme' ? t('view_theme') : t('letters_label')}>
                <ul>
                    {#each groups as g (g.key)}
                        <li><a class="pill" href={`#${groupId(g)}`}>{groupTitle(g)}</a></li>
                    {/each}
                </ul>
            </nav>

            <p class="count" role="status">
                {t('results', { shown: n(shown.length), total: n(terms.length) })}
            </p>

            {#each groups as g (g.key)}
                <section id={groupId(g)} aria-labelledby={`${groupId(g)}-title`}>
                    <h2 id={`${groupId(g)}-title`}>{groupTitle(g)}</h2>
                    <dl>
                        {#each g.terms as term (term.slug)}
                            <div class="term">
                                <dt>
                                    <dfn id={term.slug}>
                                        {#if term.abbr && term.full}
                                            <abbr title={term.full}>{term.name}</abbr>
                                        {:else}
                                            {term.name}
                                        {/if}
                                    </dfn>
                                    <a
                                        class="permalink"
                                        href={`#${term.slug}`}
                                        aria-label={t('permalink', { term: term.name })}>#</a
                                    >
                                </dt>
                                <dd class="vff-hyphenate">
                                    {#if term.abbr && term.full}
                                        <span class="full">{term.full}</span>
                                    {/if}
                                    <span class="definition">{term.definition}</span>
                                    {#if term.see.length}
                                        <span class="extra">
                                            {t('see_also')}
                                            {#each term.see as s, i (s.slug)}
                                                <a class="vff-link" href={`#${s.slug}`}>{s.name}</a
                                                >{i < term.see.length - 1 ? ', ' : ''}
                                            {/each}
                                        </span>
                                    {/if}
                                    {#if term.example}
                                        <span class="extra">
                                            {t('example')}&nbsp;:
                                            {#if term.example.path.startsWith('/maps')}
                                                <a class="vff-link" href={term.example.path}
                                                    >{t(`pages.${term.example.page}`)}</a
                                                >
                                            {:else}
                                                <Link class="vff-link" href={term.example.path}
                                                    >{term.example.name ??
                                                        t(`pages.${term.example.page}`)}</Link
                                                >
                                            {/if}
                                        </span>
                                    {/if}
                                </dd>
                            </div>
                        {/each}
                    </dl>
                </section>
            {:else}
                <EmptyState title={t('no_results')} />
            {/each}
        </div>
    </main>
</Layout>

<style>
    main {
        background-color: var(--vff-bg);
    }
    .glossary {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-5);
        padding-top: var(--vff-block-space);
        padding-bottom: var(--vff-block-space);
    }
    .note {
        max-width: var(--vff-measure);
        margin: 0;
        font-size: var(--vff-fs-body);
        line-height: 1.6;
        color: var(--vff-text-muted);
    }
    .tools {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        justify-content: space-between;
        gap: var(--vff-space-4);
    }
    .search {
        flex: 1 1 18rem;
        max-width: 28rem;
    }
    ul {
        display: flex;
        flex-wrap: wrap;
        gap: var(--vff-space-2);
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .glossary :global(.pill),
    .pill {
        display: inline-flex;
        align-items: center;
        min-height: 44px;
        padding: 0 var(--vff-space-4);
        border: 1px solid var(--vff-border-strong);
        border-radius: var(--vff-radius-pill);
        background: var(--vff-bg-elevated);
        color: var(--vff-text);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        text-decoration: none;
    }
    .glossary :global(.pill:hover),
    .pill:hover {
        border-color: var(--vff-accent-strong);
    }
    .glossary :global(.pill[aria-current='page']) {
        background: var(--vff-bg-accent);
        border-color: var(--vff-accent-strong);
        color: var(--vff-text);
        font-weight: 700;
    }
    .count {
        margin: 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
    section {
        scroll-margin-top: var(--vff-header-h, 80px);
    }
    h2 {
        margin: 0 0 var(--vff-space-3);
        font-size: var(--vff-fs-h2);
    }
    dl {
        margin: 0;
    }
    .term {
        display: grid;
        gap: var(--vff-space-2);
        padding: var(--vff-space-4) 0;
        border-top: 1px solid var(--vff-border);
    }
    dt {
        margin: 0;
        font-family: var(--vff-font-display);
        font-size: var(--vff-fs-lead);
        line-height: 1.3;
        overflow-wrap: anywhere;
    }
    dfn {
        font-style: normal;
        font-weight: 700;
        scroll-margin-top: calc(var(--vff-header-h, 80px) + var(--vff-space-4));
    }
    dfn :global(abbr) {
        text-decoration: none;
    }
    dfn:target {
        padding: 0 var(--vff-space-2);
        margin: 0 calc(-1 * var(--vff-space-2));
        border-radius: var(--vff-radius-sm);
        background: var(--vff-accent-soft);
    }
    .permalink {
        margin-left: var(--vff-space-2);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
        text-decoration: none;
    }
    .permalink:hover,
    .permalink:focus-visible {
        color: var(--vff-link);
        text-decoration: underline;
    }
    dd {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-2);
        max-width: var(--vff-measure);
        margin: 0;
        font-size: var(--vff-fs-body);
        line-height: 1.6;
    }
    .full {
        font-style: italic;
        color: var(--vff-text-muted);
    }
    .extra {
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
    @media (min-width: 800px) {
        .term {
            grid-template-columns: minmax(12rem, 16rem) 1fr;
            gap: var(--vff-space-5);
        }
    }
</style>

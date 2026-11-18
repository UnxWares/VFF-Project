<script>
    import Layout from './Layout.svelte';
    import PageHero from './PageHero.svelte';
    import { i18n } from '$lib/i18n.js';

    /**
     * Pages légales (Mentions, Confidentialité, CGU).
     * `namespace` (ex. 'legal', 'privacy', 'use_conditions', 'accessibility') et `sections` : un slug
     * (clés `:namespace.:slug.title` et `:namespace.:slug.paragraph`) ou
     * `{ slug, subSlugs }` (clés `:namespace.:slug.:sub_title` et `:sub_text`).
     * Le texte est du HTML de confiance (fichiers lang/) : paragraphes <p>, listes, tableaux
     * dans un <div class="table-wrap"> ; un texte sans balise de bloc est enveloppé dans un <p>.
     * `updated` : date ISO (AAAA-MM-JJ), écrite dans la langue de la page.
     */
    let { namespace, sections, updated = '2026-05-11' } = $props();

    /** Texte sans balise de bloc : un seul paragraphe. */
    const block = (html) => (/^\s*<(p|ul|ol|div|table|h3)\b/i.test(html) ? html : `<p>${html}</p>`);

    const items = $derived(sections.map((s) => (typeof s === 'string' ? { slug: s } : s)));
    const updatedLabel = $derived(
        new Date(`${updated}T12:00:00`).toLocaleDateString($i18n.language ?? 'fr', {
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        }),
    );
</script>

<svelte:head>
    <title>{$i18n.t(`${namespace}.meta_title`)} - VFF</title>
    <meta name="description" content={$i18n.t(`${namespace}.meta_description`)} />
</svelte:head>

<Layout compactHeader>
    <main>
        <PageHero
            eyebrow={$i18n.t(`${namespace}.hero.eyebrow`)}
            title={$i18n.t(`${namespace}.hero.title`)}
            subtitle={$i18n.t(`${namespace}.hero.updated`, { date: updatedLabel })}
        />

        <div class="legal-content">
            <nav class="toc" aria-labelledby="legal-toc-title">
                <h2 id="legal-toc-title">{$i18n.t('common.toc')}</h2>
                <ol>
                    {#each items as s (s.slug)}
                        <li>
                            <a href={`#${s.slug}`}>{$i18n.t(`${namespace}.${s.slug}.title`)}</a>
                        </li>
                    {/each}
                </ol>
            </nav>
            <article>
                <!-- eslint-disable svelte/no-at-html-tags -->
                {#each items as s (s.slug)}
                    <section id={s.slug}>
                        <h2>{$i18n.t(`${namespace}.${s.slug}.title`)}</h2>
                        {#if s.subSlugs}
                            {#each s.subSlugs as sub (sub)}
                                <h3>{$i18n.t(`${namespace}.${s.slug}.${sub}_title`)}</h3>
                                <div class="legal-text">
                                    {@html block($i18n.t(`${namespace}.${s.slug}.${sub}_text`))}
                                </div>
                            {/each}
                        {:else}
                            <div class="legal-text">
                                {@html block($i18n.t(`${namespace}.${s.slug}.paragraph`))}
                            </div>
                        {/if}
                    </section>
                {/each}
                <!-- eslint-enable svelte/no-at-html-tags -->
            </article>
        </div>
    </main>
</Layout>

<style>
    .legal-content {
        display: grid;
        grid-template-columns: 260px 1fr;
        gap: 48px;
        max-width: 1100px;
        margin: 0 auto;
        padding: 60px 16px;
        align-items: start;
    }
    .toc {
        position: sticky;
        top: 120px;
        background-color: var(--vff-bg-elevated);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        padding: 20px;
    }
    .toc h2 {
        font-size: 15px;
        font-family: var(--vff-font-heading);
        font-weight: 600;
        color: var(--vff-text);
        margin: 0 0 8px;
    }
    .toc h2:after {
        display: none;
    }
    .toc ol {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    .toc li {
        margin: 4px 0;
    }
    .toc a {
        display: block;
        padding: 8px 10px;
        text-decoration: none;
        color: var(--vff-text);
        font-size: 14px;
        border-radius: var(--vff-radius-sm);
        transition:
            background-color var(--vff-transition),
            color var(--vff-transition);
    }
    .toc a:hover {
        background-color: var(--vff-accent-soft);
        color: var(--vff-text);
    }
    article section {
        margin-bottom: 40px;
        scroll-margin-top: 120px;
    }
    article h2 {
        font-size: 26px;
        margin: 0 0 12px;
        color: var(--vff-text);
    }
    article h3 {
        font-size: 18px;
        margin: 16px 0 8px;
        color: var(--vff-text);
    }
    .legal-text {
        display: grid;
        gap: 12px;
        font-size: 17px;
        line-height: 1.65;
        max-width: 72ch;
        color: var(--vff-text);
    }
    .legal-text :global(p),
    .legal-text :global(ul) {
        margin: 0;
    }
    .legal-text :global(ul) {
        padding-left: 1.25em;
        display: grid;
        gap: 6px;
    }
    .legal-text :global(code) {
        font-size: 0.9em;
        padding: 1px 5px;
        border-radius: 4px;
        background-color: var(--vff-accent-soft);
        overflow-wrap: anywhere;
    }
    .legal-text :global(.table-wrap) {
        overflow-x: auto;
    }
    .legal-text :global(table) {
        width: 100%;
        border-collapse: collapse;
        font-size: 15px;
        line-height: 1.5;
    }
    .legal-text :global(th),
    .legal-text :global(td) {
        text-align: left;
        vertical-align: top;
        padding: 8px 10px;
        border-bottom: 1px solid var(--vff-border, rgba(0, 0, 0, 0.12));
    }
    .legal-text :global(th) {
        font-weight: 700;
    }
    article :global(a) {
        color: var(--vff-accent-ink);
        text-decoration: underline;
        text-underline-offset: 3px;
    }
    .toc a:focus-visible {
        outline: 2px solid var(--vff-accent);
        outline-offset: 1px;
    }
    article :global(a:hover) {
        text-decoration: underline;
    }
    @media (max-width: 900px) {
        .legal-content {
            grid-template-columns: 1fr;
        }
        .toc {
            position: relative;
            top: 0;
        }
    }
</style>

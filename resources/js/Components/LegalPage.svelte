<script>
    import Layout from './Layout.svelte';
    import PageHero from './PageHero.svelte';
    import { i18n } from '$lib/i18n.js';
    import { legalTypography } from '$lib/legalTypography.js';
    import { MediaQuery } from 'svelte/reactivity';

    /**
     * Pages légales (Mentions, Confidentialité, CGU, Accessibilité).
     * `namespace` (ex. 'legal', 'privacy', 'use_conditions', 'accessibility') et `sections` : un slug
     * (clés `:namespace.:slug.title` et `:namespace.:slug.paragraph`) ou
     * `{ slug, subSlugs }` (clés `:namespace.:slug.:sub_title` et `:sub_text`).
     * Le texte est du HTML de confiance (fichiers lang/) : paragraphes <p>, listes, tableaux
     * dans un <div class="table-wrap"> ; un texte sans balise de bloc est enveloppé dans un <p>.
     * `updated` : date ISO (AAAA-MM-JJ), écrite dans la langue de la page.
     * Mise en forme seulement : le texte est rendu tel quel (legalTypography ne fait que poser des
     * espaces insécables, garder les codes de licence d'un bloc et annoncer les liens qui s'ouvrent
     * dans un nouvel onglet).
     */
    let { namespace, sections, updated = '2026-05-11' } = $props();

    const lang = $derived($i18n.language ?? 'fr');
    /** Texte sans balise de bloc : un seul paragraphe. */
    const block = (html) =>
        legalTypography(
            /^\s*<(p|ul|ol|div|table|h3)\b/i.test(html) ? html : `<p>${html}</p>`,
            lang,
            String($i18n.t('common.new_tab')),
        );

    const items = $derived(sections.map((s) => (typeof s === 'string' ? { slug: s } : s)));
    const updatedLabel = $derived(
        new Date(`${updated}T12:00:00`).toLocaleDateString(lang, {
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        }),
    );

    // Sommaire : toujours ouvert à partir de 901 px ; replié sous 901 px (il précède sinon un long texte).
    const wideQuery = new MediaQuery('min-width: 901px', true);
    const wide = $derived(wideQuery.current);
    let narrowOpen = $state(false);

    /**
     * Tableaux du texte : défilables au clavier et nommés d'après leur section, avec « Tableau : »
     * devant, pour que leur nom reste distinct de celui de la section elle-même (repères uniques).
     */
    function labelTables(node) {
        const apply = () => {
            for (const wrap of node.querySelectorAll('.table-wrap')) {
                wrap.tabIndex = 0;
                wrap.setAttribute('role', 'region');
                const title = wrap.closest('section')?.querySelector('h2')?.textContent;
                if (title)
                    wrap.setAttribute('aria-label', String($i18n.t('common.table_of', { title })));
            }
        };
        apply();
        const observer = new MutationObserver(apply);
        observer.observe(node, { childList: true, subtree: true });
        return () => observer.disconnect();
    }
</script>

<svelte:head>
    <title>{$i18n.t(`${namespace}.meta_title`)} - VFF</title>
    <meta name="description" content={$i18n.t(`${namespace}.meta_description`)} />
</svelte:head>

<Layout compactHeader>
    <main>
        <PageHero
            title={$i18n.t(`${namespace}.hero.title`)}
            subtitle={$i18n.t(`${namespace}.hero.updated`, { date: updatedLabel })}
        />

        <div class="vff-container legal-content">
            <nav class="toc" aria-label={$i18n.t('common.toc')}>
                <details
                    open={wide || narrowOpen}
                    ontoggle={(event) => {
                        if (!wide) narrowOpen = event.currentTarget.open;
                    }}
                >
                    <summary
                        tabindex={wide ? -1 : 0}
                        onclick={(event) => {
                            if (wide) event.preventDefault();
                        }}
                    >
                        <h2>{$i18n.t('common.toc')}</h2>
                        <i class="bi bi-chevron-down" aria-hidden="true"></i>
                    </summary>
                    <ol>
                        {#each items as s (s.slug)}
                            <li>
                                <a href={`#${s.slug}`}>{$i18n.t(`${namespace}.${s.slug}.title`)}</a>
                            </li>
                        {/each}
                    </ol>
                </details>
            </nav>
            <article {@attach labelTables}>
                <!-- eslint-disable svelte/no-at-html-tags -->
                {#each items as s (s.slug)}
                    <section id={s.slug} aria-labelledby={`${s.slug}-title`}>
                        <h2 id={`${s.slug}-title`}>{$i18n.t(`${namespace}.${s.slug}.title`)}</h2>
                        {#if s.subSlugs}
                            {#each s.subSlugs as sub (sub)}
                                <h3>{$i18n.t(`${namespace}.${s.slug}.${sub}_title`)}</h3>
                                <div class="legal-text vff-hyphenate">
                                    {@html block($i18n.t(`${namespace}.${s.slug}.${sub}_text`))}
                                </div>
                            {/each}
                        {:else}
                            <div class="legal-text vff-hyphenate">
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
        grid-template-columns: 260px minmax(0, 1fr);
        gap: 64px;
        padding-block: var(--vff-section-space);
        align-items: start;
    }

    /* Sommaire : liste à filet, sans boîte. Collé en haut de l'écran à côté d'un long texte. */
    .toc {
        position: sticky;
        top: var(--vff-space-5);
        max-height: calc(100vh - 2 * var(--vff-space-5));
        overflow-y: auto;
        padding-left: var(--vff-space-4);
        border-left: 2px solid var(--vff-border-strong);
    }
    .toc summary {
        min-height: 24px;
        list-style: none;
        cursor: default;
    }
    .toc summary::-webkit-details-marker {
        display: none;
    }
    .toc summary i {
        display: none;
    }
    .toc h2 {
        margin: 0 0 var(--vff-space-2);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        font-weight: 600;
        line-height: var(--vff-lh-ui);
        color: var(--vff-text-muted);
    }
    .toc ol {
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .toc a {
        display: block;
        padding: 6px 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-ui);
        color: var(--vff-text);
        text-decoration: none;
        transition: color var(--vff-transition);
    }
    .toc a:hover {
        color: var(--vff-accent-ink);
        text-decoration: underline;
    }

    article {
        min-width: 0;
    }
    article section {
        scroll-margin-top: var(--vff-space-5);
    }
    article section + section {
        margin-top: var(--vff-section-space);
        padding-top: var(--vff-section-space);
        border-top: 1px solid var(--vff-border);
    }
    article h2 {
        margin: 0 0 var(--vff-space-4);
        font-size: var(--vff-fs-h2);
        line-height: var(--vff-lh-heading);
        text-wrap: balance;
    }
    article h3 {
        margin: var(--vff-space-5) 0 var(--vff-space-2);
    }

    /* Texte légal : même mesure et même rythme que .vff-prose. */
    .legal-text {
        max-width: var(--vff-measure);
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-body);
        line-height: var(--vff-lh-body);
        color: var(--vff-text);
    }
    .legal-text :global(:is(p, ul, ol)) {
        margin: 0 0 1em;
    }
    .legal-text :global(:last-child) {
        margin-bottom: 0;
    }
    .legal-text :global(:is(ul, ol)) {
        padding-left: 1.4em;
    }
    .legal-text :global(li + li) {
        margin-top: 0.35em;
    }
    .legal-text :global(:is(p, li)) {
        font-size: inherit;
        line-height: inherit;
    }
    .legal-text :global(code) {
        padding: 1px 5px;
        border-radius: var(--vff-radius-xs);
        background-color: var(--vff-bg-muted);
        /* Un peu plus petit que le texte, jamais sous 14 px (noms de cookies dans un tableau en 15 px). */
        font-size: max(0.9em, var(--vff-fs-xs));
        overflow-wrap: anywhere;
    }
    .legal-text :global(.nowrap),
    .legal-text :global(a[href^='mailto:']) {
        white-space: nowrap;
    }
    .legal-text :global(a) {
        overflow-wrap: anywhere;
    }

    /* Tableaux : même dessin que .vff-table. */
    .legal-text :global(.table-wrap) {
        max-width: 100%;
        overflow-x: auto;
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
    }
    .legal-text :global(table) {
        width: 100%;
        border-collapse: collapse;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-ui);
    }
    .legal-text :global(:is(th, td)) {
        padding: 10px 12px;
        border-bottom: 1px solid var(--vff-border);
        text-align: left;
        vertical-align: top;
    }
    .legal-text :global(thead th) {
        background-color: var(--vff-bg-subtle);
        font-weight: 600;
        color: var(--vff-text-muted);
        white-space: nowrap;
    }
    .legal-text :global(tbody tr:last-child > *) {
        border-bottom: 0;
    }

    @media (max-width: 900px) {
        .legal-content {
            grid-template-columns: minmax(0, 1fr);
            gap: var(--vff-block-space);
        }
        .toc {
            position: static;
            max-height: none;
            overflow: visible;
            padding: 0;
            border-left: 0;
            border: 1px solid var(--vff-border);
            border-radius: var(--vff-radius-md);
            background-color: var(--vff-bg-subtle);
        }
        .toc summary {
            cursor: pointer;
            padding: 6px 16px;
            min-height: 44px;
            display: flex;
            align-items: center;
        }
        .toc h2 {
            margin: 0;
            font-size: var(--vff-fs-md);
            color: var(--vff-text);
        }
        .toc summary i {
            display: inline-block;
            margin-left: auto;
            transition: transform var(--vff-transition);
        }
        .toc details[open] summary i {
            transform: rotate(180deg);
        }
        .toc ol {
            padding: 0 16px 8px;
        }
        .toc a {
            padding: 10px 0;
        }
    }

    /* Impression : le texte seul, sur une colonne, avec l'adresse des liens externes. */
    @media print {
        .toc {
            display: none;
        }
        .legal-content {
            display: block;
            padding-block: 0;
        }
        article section + section {
            margin-top: 24px;
            padding-top: 24px;
        }
        article section {
            break-inside: avoid-page;
        }
        .legal-text {
            max-width: none;
            font-size: 11pt;
        }
        .legal-text :global(a[href^='http'])::after {
            content: ' (' attr(href) ')';
            font-size: 0.85em;
            overflow-wrap: anywhere;
        }
        :global(.site-header),
        :global(.site-footer),
        :global(.vff-skip-link) {
            display: none;
        }
    }
</style>

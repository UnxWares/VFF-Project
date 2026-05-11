<script>
    import Layout from './Layout.svelte';
    import PageHero from './PageHero.svelte';
    import { i18n } from '$lib/i18n.js';

    /**
     * Wrapper pour les pages légales (Mentions, Confidentialité, CGU).
     * Reçoit `namespace` (ex. 'legal', 'privacy', 'use_conditions') et `sections`
     * (liste de slugs `[ 'editor', 'hosting', 'ip', ... ]`) — chaque slug correspond
     * à une clé `:namespace.:slug.title` et `:namespace.:slug.paragraph` dans les
     * traductions.
     */
    let { namespace, sections, updated = '11 mai 2026' } = $props();
</script>

<svelte:head>
    <title>{$i18n.t(`${namespace}.meta_title`)} — VFF</title>
    <meta name="description" content={$i18n.t(`${namespace}.meta_description`)} />
</svelte:head>

<Layout compactHeader>
    <main>
        <PageHero
            eyebrow={$i18n.t(`${namespace}.hero.eyebrow`)}
            title={$i18n.t(`${namespace}.hero.title`)}
            subtitle={$i18n.t(`${namespace}.hero.updated`, { date: updated })}
        />

        <div class="legal-content">
            <nav class="toc" aria-label="Sommaire">
                <h2>Sommaire</h2>
                <ul>
                    {#each sections as slug}
                        <li><a href={`#${slug}`}>{$i18n.t(`${namespace}.${slug}.title`)}</a></li>
                    {/each}
                </ul>
            </nav>
            <article>
                {#each sections as slug}
                    <section id={slug}>
                        <h2>{$i18n.t(`${namespace}.${slug}.title`)}</h2>
                        <p>{@html $i18n.t(`${namespace}.${slug}.paragraph`)}</p>
                    </section>
                {/each}
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
        font-size: 14px;
        font-family: var(--vff-font-display);
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--vff-text-muted);
        margin: 0 0 12px;
    }
    .toc ul {
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
        transition: background-color var(--vff-transition), color var(--vff-transition);
    }
    .toc a:hover {
        background-color: var(--vff-accent-soft);
        color: var(--vff-accent);
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
    article p {
        font-size: 17px;
        line-height: 1.65;
        text-align: justify;
        color: var(--vff-text);
    }
    article :global(a) {
        color: var(--vff-accent);
        text-decoration: none;
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
        article p {
            text-align: left;
        }
    }
</style>

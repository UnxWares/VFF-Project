<script>
    /**
     * Sommaire de l'encyclopédie, comme celui d'un livret : chaque rubrique, sa description et son
     * compte réel (props `stats` et `mediaCount` de HomeController), reliés par des points de
     * conduite. Une rubrique sans fiche (matériel roulant vide) n'est pas listée ; sans statistiques,
     * les comptes sont omis.
     */
    import { Link } from '@inertiajs/svelte';
    import SectionBlock from '$components/SectionBlock.svelte';
    import { i18n } from '$lib/i18n.js';

    /** @type {{ stats?: { lines_count?: number, stations_count?: number, materials_count?: number, eras_count?: number } | null, mediaCount?: number | null }} */
    let { stats = null, mediaCount = null } = $props();

    const nf = $derived(new Intl.NumberFormat($i18n.language));

    const entries = $derived(
        [
            { key: 'lines', href: '/lines', count: stats?.lines_count },
            { key: 'stations', href: '/stations', count: stats?.stations_count },
            {
                key: 'materials',
                href: '/materials',
                count: stats?.materials_count,
                hideEmpty: true,
            },
            { key: 'map', href: '/maps', count: stats?.eras_count },
            { key: 'media', href: '/credits/media', count: mediaCount },
            { key: 'api', href: '/api', count: null },
        ].filter((e) => !(e.hideEmpty && !e.count)),
    );

    // Nombre, pas chaîne : i18next n'applique le pluriel (_one, _other) qu'à un `count` numérique.
    function countLabel(e) {
        const n = Number(e.count);
        return n
            ? $i18n.t(`home.contents.${e.key}_count`, { count: n, value: nf.format(n) })
            : null;
    }
</script>

<SectionBlock id="sommaire" title={$i18n.t('home.contents.title')} maxWidth="900px">
    <ul class="contents">
        {#each entries as e (e.key)}
            {@const count = countLabel(e)}
            <li>
                <Link href={e.href} class="entry">
                    <span class="entry-title">{$i18n.t(`home.contents.${e.key}_title`)}</span>
                    {#if count}
                        <span class="leader" aria-hidden="true"></span>
                        <span class="count">{count}</span>
                    {/if}
                    <span class="entry-text">{$i18n.t(`home.contents.${e.key}_text`)}</span>
                </Link>
            </li>
        {/each}
    </ul>
    <p class="quiz-link">
        <Link href="/network" class="vff-link">{$i18n.t('network.link_home')}</Link>
    </p>
    <p class="quiz-link">
        <Link href="/quiz" class="vff-link">{$i18n.t('quiz.links.home')}</Link>
    </p>
</SectionBlock>

<style>
    .contents {
        margin: 0;
        padding: 0;
        list-style: none;
        border-top: 1px solid var(--vff-border);
    }
    .contents li {
        margin: 0;
        border-bottom: 1px solid var(--vff-border);
    }
    /* Titre .... compte sur la première ligne, description dessous. */
    .contents :global(.entry) {
        display: grid;
        grid-template-columns: auto minmax(24px, 1fr) auto;
        grid-template-areas:
            'title leader count'
            'text text text';
        align-items: baseline;
        column-gap: var(--vff-space-3);
        padding: var(--vff-space-4) var(--vff-space-3);
        color: var(--vff-text);
        text-decoration: none;
        transition: background-color var(--vff-transition);
    }
    .contents :global(.entry:hover) {
        background-color: var(--vff-bg-subtle);
    }
    .entry-title {
        grid-area: title;
        font-family: var(--vff-font-display);
        font-size: 1.375rem;
        font-weight: 700;
        line-height: 1.3;
    }
    .contents li:hover .entry-title {
        color: var(--vff-link);
        text-decoration: underline;
        text-decoration-thickness: 2px;
        text-underline-offset: 0.18em;
    }
    .leader {
        grid-area: leader;
        align-self: end;
        margin-bottom: 0.45em;
        border-bottom: 2px dotted var(--vff-border-control);
    }
    .count {
        grid-area: count;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-md);
        font-weight: 600;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }
    /* Lien discret vers le quiz, sous le sommaire. */
    .quiz-link {
        margin: var(--vff-space-5) 0 0;
        padding-inline: var(--vff-space-3);
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-md);
        line-height: 1.5;
    }
    .entry-text {
        grid-area: text;
        max-width: var(--vff-measure);
        margin-top: 4px;
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-md);
        line-height: 1.55;
        color: var(--vff-text-muted);
    }
    @media (max-width: 600px) {
        .contents :global(.entry) {
            padding-inline: var(--vff-space-1);
        }
        .entry-title {
            font-size: 1.1875rem;
        }
        .quiz-link {
            padding-inline: var(--vff-space-1);
        }
    }
</style>

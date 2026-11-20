<script>
    /**
     * Accès rapide, juste après le grand en-tête de l'accueil : la recherche de tout le site, puis
     * les entrées directes (Carte, Lignes, Gares, Matériel roulant, API, Contribuer), chacune avec
     * son vrai compte quand il existe (props `stats` de HomeController). Une rangée sobre à filets,
     * lisible sans lire de texte : le nom de la rubrique, puis son compte. Même respiration et même
     * grille que le reste de la page : marges de section, recherche et rangée de la même largeur,
     * cases de même hauteur et de même retrait, sans icône décorative.
     */
    import { Link } from '@inertiajs/svelte';
    import SearchCombobox from '$components/Search/SearchCombobox.svelte';
    import { i18n } from '$lib/i18n.js';
    import { shortcutLabel } from '$lib/siteSearch.js';

    /** @type {{ stats?: { lines_count?: number, stations_count?: number, materials_count?: number, eras_count?: number } | null }} */
    let { stats = null } = $props();

    const nf = $derived(new Intl.NumberFormat($i18n.language));
    const t = (/** @type {string} */ key, /** @type {Record<string, unknown>} */ params = {}) =>
        String($i18n.t(key, params));

    /** Compte « 2 705 fiches » (nombre, pas chaîne : i18next n'applique le pluriel qu'à un nombre). */
    function counted(key, value) {
        const n = Number(value);
        return n > 0 ? t(`home.contents.${key}_count`, { count: n, value: nf.format(n) }) : null;
    }

    const entries = $derived([
        {
            href: '/maps',
            title: t('nav.maps'),
            detail: counted('map', stats?.eras_count) ?? t('home.quick.map_detail'),
        },
        {
            href: '/lines',
            title: t('nav.lines'),
            detail: counted('lines', stats?.lines_count),
        },
        {
            href: '/stations',
            title: t('nav.stations'),
            detail: counted('stations', stats?.stations_count),
        },
        {
            href: '/materials',
            title: t('nav.materials'),
            detail:
                counted('materials', stats?.materials_count) ?? t('home.quick.materials_detail'),
        },
        {
            href: '/api',
            title: t('home.contents.api_title'),
            detail: t('home.quick.api_detail'),
        },
        {
            href: '/contribute',
            title: t('nav.contribute'),
            detail: t('home.quick.contribute_detail'),
        },
    ]);
</script>

<nav class="quick-access" aria-label={t('home.quick.label')}>
    <div class="inner">
        <div class="search">
            <SearchCombobox floating label={t('nav.search.title')} />
            <p class="shortcut">
                {t('home.quick.shortcut_hint', { keys: shortcutLabel() })}
            </p>
        </div>
        <ul class="entries">
            {#each entries as e (e.href)}
                <li>
                    <Link href={e.href} class="entry">
                        <span class="entry-title">{e.title}</span>
                        {#if e.detail}<span class="entry-detail">{e.detail}</span>{/if}
                    </Link>
                </li>
            {/each}
        </ul>
    </div>
</nav>

<style>
    .quick-access {
        padding-block: var(--vff-section-space);
        background-color: var(--vff-bg-subtle);
        border-bottom: 1px solid var(--vff-border);
    }
    .inner {
        display: flex;
        flex-direction: column;
        gap: var(--vff-block-space);
        max-width: calc(var(--vff-container) + 2 * var(--vff-gutter));
        margin: 0 auto;
        padding-inline: var(--vff-gutter);
    }
    .search :global(.field) {
        background-color: var(--vff-bg);
    }
    .shortcut {
        margin: var(--vff-space-2) 0 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }

    /* Six entrées dans un même cadre arrondi, séparées par des filets ; même retrait pour toutes,
       même hauteur sur une rangée. Trois colonnes sous 1 180 px, deux sur téléphone. */
    .entries {
        display: grid;
        grid-template-columns: repeat(6, minmax(0, 1fr));
        margin: 0;
        padding: 0;
        list-style: none;
        overflow: hidden;
        border: 1px solid var(--vff-border-strong);
        border-radius: var(--vff-radius-md);
        background-color: var(--vff-bg-elevated);
    }
    .entries li {
        display: flex;
        margin: 0;
    }
    .entries li + li {
        border-left: 1px solid var(--vff-border);
    }
    .entries :global(.entry) {
        display: flex;
        flex: 1;
        flex-direction: column;
        gap: 6px;
        min-height: 104px;
        padding: 20px;
        color: var(--vff-text);
        text-decoration: none;
        transition: background-color var(--vff-transition);
    }
    .entries :global(.entry:hover) {
        background-color: var(--vff-bg-accent);
    }
    .entry-title {
        font-family: var(--vff-font-display);
        font-size: 1.125rem;
        font-weight: 700;
        line-height: 1.25;
        text-wrap: balance;
    }
    .entries li:hover .entry-title {
        color: var(--vff-link);
        text-decoration: underline;
        text-decoration-thickness: 2px;
        text-underline-offset: 0.18em;
    }
    .entry-detail {
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: 1.4;
        color: var(--vff-text-muted);
        font-variant-numeric: tabular-nums;
    }

    @media (max-width: 1180px) {
        .entries {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
        .entries li:nth-child(3n + 1) {
            border-left: 0;
        }
        .entries li:nth-child(n + 4) {
            border-top: 1px solid var(--vff-border);
        }
    }
    @media (max-width: 900px) {
        .shortcut {
            display: none;
        }
    }
    @media (max-width: 600px) {
        .entries {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .entries li:nth-child(3n + 1) {
            border-left: 1px solid var(--vff-border);
        }
        .entries li:nth-child(2n + 1) {
            border-left: 0;
        }
        .entries li:nth-child(n + 3) {
            border-top: 1px solid var(--vff-border);
        }
        .entries :global(.entry) {
            min-height: 88px;
            padding: 16px;
        }
    }
</style>

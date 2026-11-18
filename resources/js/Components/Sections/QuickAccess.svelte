<script>
    /**
     * Accès rapide, juste après le grand en-tête de l'accueil : la recherche de tout le site, puis
     * les entrées directes (Carte, Lignes, Gares, Matériel roulant, API, Contribuer), chacune avec
     * son vrai compte quand il existe (props `stats` de HomeController). Une rangée sobre à filets,
     * lisible sans lire de texte : le nom de la rubrique, puis son compte.
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
            icon: 'bi-map',
            title: t('nav.maps'),
            detail: counted('map', stats?.eras_count) ?? t('home.quick.map_detail'),
        },
        {
            href: '/lines',
            icon: 'bi-bezier',
            title: t('nav.lines'),
            detail: counted('lines', stats?.lines_count),
        },
        {
            href: '/stations',
            icon: 'bi-pin-map',
            title: t('nav.stations'),
            detail: counted('stations', stats?.stations_count),
        },
        {
            href: '/materials',
            icon: 'bi-train-front',
            title: t('nav.materials'),
            detail:
                counted('materials', stats?.materials_count) ?? t('home.quick.materials_detail'),
        },
        {
            href: '/api',
            icon: 'bi-code-slash',
            title: t('home.contents.api_title'),
            detail: t('home.quick.api_detail'),
        },
        {
            href: '/contribute',
            icon: 'bi-pencil-square',
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
                        <span class="entry-title"
                            ><i class="bi {e.icon}" aria-hidden="true"></i>{e.title}</span
                        >
                        {#if e.detail}<span class="entry-detail">{e.detail}</span>{/if}
                    </Link>
                </li>
            {/each}
        </ul>
    </div>
</nav>

<style>
    .quick-access {
        padding-block: var(--vff-space-6);
        background-color: var(--vff-bg-subtle);
        border-bottom: 1px solid var(--vff-border);
    }
    .inner {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-5);
        max-width: calc(var(--vff-container) + 2 * var(--vff-gutter));
        margin: 0 auto;
        padding-inline: var(--vff-gutter);
    }
    .search {
        max-width: 760px;
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

    /* Six entrées sur une rangée, séparées par des filets ; deux colonnes sur téléphone. */
    .entries {
        display: grid;
        grid-template-columns: repeat(6, minmax(0, 1fr));
        margin: 0;
        padding: 0;
        list-style: none;
        border-block: 1px solid var(--vff-border-strong);
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
        gap: 4px;
        min-height: 88px;
        padding: var(--vff-space-4) var(--vff-space-4) var(--vff-space-4);
        color: var(--vff-text);
        text-decoration: none;
        transition: background-color var(--vff-transition);
    }
    .entries :global(.entry:hover) {
        background-color: var(--vff-bg);
    }
    /* Première colonne : texte sur le bord gauche commun (celui du champ de recherche). */
    .entries li:first-child :global(.entry) {
        padding-left: 0;
    }
    .entry-title {
        display: inline-flex;
        align-items: baseline;
        gap: 8px;
        font-family: var(--vff-font-display);
        font-size: 1.25rem;
        font-weight: 700;
        line-height: 1.25;
    }
    .entry-title .bi {
        flex-shrink: 0;
        font-size: 17px;
        color: var(--vff-accent-ink);
    }
    .entries li:hover .entry-title {
        color: var(--vff-link);
        text-decoration: underline;
        text-decoration-thickness: 2px;
        text-underline-offset: 0.18em;
    }
    .entry-detail {
        font-family: var(--vff-font-heading);
        font-size: 0.9375rem;
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
        .entries li:nth-child(3n + 1) :global(.entry) {
            padding-left: 0;
        }
    }
    @media (max-width: 900px) {
        .shortcut {
            display: none;
        }
    }
    @media (max-width: 600px) {
        .quick-access {
            padding-block: var(--vff-space-5);
        }
        .inner {
            gap: var(--vff-space-4);
        }
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
            min-height: 76px;
            padding: var(--vff-space-3);
        }
        .entries li:nth-child(3n + 1) :global(.entry) {
            padding-left: var(--vff-space-3);
        }
        .entries li:nth-child(2n + 1) :global(.entry) {
            padding-left: 0;
        }
        .entry-title {
            font-size: 1.125rem;
        }
    }
</style>

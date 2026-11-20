<script>
    /**
     * Passage entre les listes de l'espace de validation : contributions à relire (/validate),
     * signalements d'erreur (/validate/reports) et, pour les administrateurs, comptes
     * (/validate/accounts) et graphe du réseau (/validate/graph). La page courante est marquée
     * par `aria-current` et soulignée (jamais par la seule couleur).
     */
    import { Link, page } from '@inertiajs/svelte';
    import { i18n } from '$lib/i18n.js';

    /** @type {{ current: 'contributions'|'reports'|'accounts'|'graph' }} */
    let { current } = $props();

    const isAdmin = $derived(page.props.auth?.user?.role === 'admin');
    const links = $derived([
        { id: 'contributions', href: '/validate', key: 'reports.review.contributions_link' },
        { id: 'reports', href: '/validate/reports', key: 'reports.review.reports_link' },
        ...(isAdmin
            ? [
                  { id: 'accounts', href: '/validate/accounts', key: 'moderation.admin.nav_link' },
                  { id: 'graph', href: '/validate/graph', key: 'graph_admin.nav_link' },
              ]
            : []),
    ]);
</script>

<nav class="review-nav" aria-label={$i18n.t('reports.review.nav_label')}>
    <ul>
        {#each links as l (l.id)}
            <li>
                <Link href={l.href} aria-current={l.id === current ? 'page' : undefined}
                    >{$i18n.t(l.key)}</Link
                >
            </li>
        {/each}
    </ul>
</nav>

<style>
    .review-nav {
        margin-top: var(--vff-space-5);
    }
    ul {
        display: flex;
        flex-wrap: wrap;
        gap: var(--vff-space-2) var(--vff-space-5);
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .review-nav :global(a) {
        display: inline-block;
        padding-block: 6px;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-md);
        color: var(--vff-text-muted);
        text-decoration: none;
        border-bottom: 2px solid transparent;
    }
    .review-nav :global(a:hover) {
        color: var(--vff-text);
        border-bottom-color: var(--vff-border-strong);
    }
    .review-nav :global(a[aria-current='page']) {
        font-weight: 600;
        color: var(--vff-text);
        border-bottom-color: var(--vff-accent);
    }
</style>

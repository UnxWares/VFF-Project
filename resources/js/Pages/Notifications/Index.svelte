<script>
    import { page, router } from '@inertiajs/svelte';
    import Layout from '$components/Layout.svelte';
    import PageHero from '$components/PageHero.svelte';
    import EmptyState from '$components/ui/EmptyState.svelte';
    import Pagination from '$components/Directory/Pagination.svelte';
    import { i18n } from '$lib/i18n.js';
    import { notificationDate, unreadSummary } from '$lib/notifications.js';

    /**
     * Notifications du membre connecté (NotificationController) : suite donnée à ses
     * contributions et, pour les validateurs, contributions qui attendent leur relecture.
     * Le titre mène à /notifications/{id}, qui marque la notification comme lue puis redirige
     * vers la contribution (lien ordinaire : l'ancre #contribution-N de la page visée est suivie).
     */
    let { notifications } = $props();

    const items = $derived(notifications?.data ?? []);
    const unread = $derived(page.props.auth?.unread_notifications ?? 0);
    const tr = (key, opts) => $i18n.t(key, opts);

    let marking = $state(false);

    function markAll() {
        router.post(
            '/notifications/read-all',
            {},
            {
                preserveScroll: true,
                onStart: () => (marking = true),
                onFinish: () => (marking = false),
            },
        );
    }

    function goToPage(n) {
        router.get('/notifications', { page: n });
    }
</script>

<svelte:head><title>{$i18n.t('notifications.meta_title')} - VFF</title></svelte:head>

<Layout compactHeader>
    <main class="notifications-page">
        <PageHero title={$i18n.t('notifications.title')} subtitle={unreadSummary(unread, tr)}>
            {#if unread > 0}
                <div class="hero-actions">
                    <button
                        type="button"
                        class="vff-btn secondary"
                        disabled={marking}
                        onclick={markAll}
                    >
                        {$i18n.t('notifications.mark_all')}
                    </button>
                </div>
            {/if}
        </PageHero>

        <div class="vff-container body">
            {#if items.length === 0}
                <EmptyState
                    icon="bi-inbox"
                    title={$i18n.t('notifications.empty_title')}
                    text={$i18n.t('notifications.empty_text')}
                    headingLevel={2}
                />
            {:else}
                <ol class="list" aria-label={$i18n.t('notifications.list_label')}>
                    {#each items as n (n.id)}
                        <li class={['item', { unread: !n.read }]}>
                            <div class="head">
                                <h2>
                                    {#if n.has_link}
                                        <a href="/notifications/{n.id}">{n.title}</a>
                                    {:else}
                                        {n.title}
                                    {/if}
                                </h2>
                                {#if !n.read}
                                    <span class="vff-badge muted"
                                        >{$i18n.t('notifications.unread')}</span
                                    >
                                {/if}
                            </div>
                            {#if n.body}
                                <p class="text">{n.body}</p>
                            {/if}
                            {#if n.reason}
                                <div class="reason">
                                    <p class="reason-title">{$i18n.t('notifications.reason')}</p>
                                    <p>{n.reason}</p>
                                </div>
                            {/if}
                            {#if n.created_at}
                                <p class="meta">
                                    <time datetime={n.created_at}
                                        >{notificationDate(n.created_at, $i18n.language)}</time
                                    >
                                </p>
                            {/if}
                        </li>
                    {/each}
                </ol>

                {#if notifications.last_page > 1}
                    <div class="pager">
                        <Pagination
                            current={notifications.current_page}
                            last={notifications.last_page}
                            onpage={goToPage}
                        />
                    </div>
                {/if}
            {/if}
        </div>
    </main>
</Layout>

<style>
    .hero-actions {
        margin-top: var(--vff-space-5);
    }
    .body {
        padding-block: var(--vff-section-space);
    }
    .list {
        max-width: 760px;
        margin: 0;
        padding: 0;
        list-style: none;
        border-top: 1px solid var(--vff-border);
    }
    .item {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-2);
        padding-block: var(--vff-space-5);
        border-bottom: 1px solid var(--vff-border);
    }
    .head {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        justify-content: space-between;
        gap: var(--vff-space-2) var(--vff-space-4);
    }
    .head h2 {
        min-width: 0;
        margin: 0;
        font-size: var(--vff-fs-h3);
        font-weight: 500;
        line-height: 1.35;
        text-wrap: balance;
    }
    /* Non lue : titre en gras et étiquette « Non lue » (jamais la seule couleur). */
    .unread .head h2 {
        font-weight: 700;
    }
    /* Lien du titre : zone cliquable d'au moins 24 px de haut (padding sur l'élément en ligne). */
    .head h2 a {
        padding-block: 3px;
        color: var(--vff-text);
        text-decoration: underline;
        text-decoration-color: var(--vff-border-strong);
        text-decoration-thickness: 1px;
        text-underline-offset: 0.2em;
        transition:
            color var(--vff-transition),
            text-decoration-color var(--vff-transition);
    }
    .head h2 a:hover {
        color: var(--vff-accent-ink);
        text-decoration-color: currentColor;
    }
    .text {
        max-width: 68ch;
        margin: 0;
        line-height: 1.6;
        color: var(--vff-text);
    }
    .reason {
        max-width: 68ch;
        padding: var(--vff-space-3) var(--vff-space-4);
        background-color: var(--vff-bg-subtle);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
    }
    .reason p {
        margin: 0;
        overflow-wrap: anywhere;
        white-space: pre-line;
    }
    .reason-title {
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        font-weight: 600;
        color: var(--vff-text-muted);
    }
    .meta {
        margin: 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-ui);
        color: var(--vff-text-muted);
    }
    .pager {
        max-width: 760px;
        margin-top: var(--vff-space-6);
    }
</style>

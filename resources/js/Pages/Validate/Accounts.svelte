<script>
    import { Link, page, router } from '@inertiajs/svelte';
    import Layout from '$components/Layout.svelte';
    import PageHero from '$components/PageHero.svelte';
    import ReviewNav from '$components/Contribute/ReviewNav.svelte';
    import Pagination from '$components/Directory/Pagination.svelte';
    import EmptyState from '$components/ui/EmptyState.svelte';
    import Field from '$components/ui/Field.svelte';
    import Notice from '$components/ui/Notice.svelte';
    import { reportDate } from '$lib/errorReport.js';
    import { i18n } from '$lib/i18n.js';

    /**
     * Comptes (AccountModerationController), pour les administrateurs : recherche, filtre des
     * comptes suspendus, suspension avec motif obligatoire et date de fin facultative, levée avant
     * terme. Un compte qu'on ne peut pas suspendre (le sien, le dernier administrateur) affiche la
     * raison à la place du formulaire.
     */
    let { accounts, filter = 'all', search = '', counts = {} } = $props();

    const FILTERS = ['all', 'suspended'];
    const REASON_MIN = 10;
    const items = $derived(accounts?.data ?? []);
    const flash = $derived(page.props.flash ?? {});
    const errors = $derived(page.props.errors ?? {});
    const lang = $derived($i18n.language);
    /**
     * Traduction en texte simple (les props des composants attendent une chaîne).
     * @param {string} key
     * @param {Record<string, unknown>} [opts]
     * @returns {string}
     */
    const t = (key, opts) => String($i18n.t(key, opts));
    /** Date longue, à l'heure de Paris (la fin d'une suspension est la fin de journée parisienne). */
    const day = (iso) => reportDate(iso, lang, 'Europe/Paris');

    // svelte-ignore state_referenced_locally
    let query = $state(search);
    /** Motifs et dates en cours de saisie, par compte. */
    let reasons = $state({});
    let untils = $state({});
    /** Compte dont la suspension vient d'être envoyée (pour y afficher l'erreur éventuelle). */
    let active = $state(null);
    let processing = $state(false);

    function submitSearch(e) {
        e.preventDefault();
        router.get(
            '/validate/accounts',
            { q: query.trim() || undefined, filter },
            { preserveState: true },
        );
    }

    function suspend(e, account) {
        e.preventDefault();
        active = account.id;
        router.post(
            `/validate/accounts/${account.id}/suspension`,
            { reason: (reasons[account.id] ?? '').trim(), until: untils[account.id] || null },
            {
                preserveScroll: true,
                onStart: () => (processing = true),
                onFinish: () => (processing = false),
                onSuccess: () => {
                    if (!Object.keys(page.props.errors ?? {}).length) {
                        delete reasons[account.id];
                        delete untils[account.id];
                    }
                },
                onError: () => document.getElementById(`reason-${account.id}`)?.focus(),
            },
        );
    }

    function lift(account) {
        router.delete(`/validate/accounts/${account.id}/suspension`, {
            preserveScroll: true,
            onStart: () => (processing = true),
            onFinish: () => (processing = false),
        });
    }

    /** « Suspendu le 19 novembre 2026 jusqu'au 25 novembre 2026 inclus, par Élise ». */
    function suspensionLine(s) {
        const base = [
            t('moderation.admin.suspended_since', { date: day(s.since) }),
            s.until
                ? t('moderation.admin.until', { date: day(s.until) })
                : t('moderation.admin.indefinite'),
        ].join(' ');
        return s.by ? `${base}, ${t('moderation.admin.by', { name: s.by })}` : base;
    }

    function goToPage(n) {
        router.get('/validate/accounts', { q: search || undefined, filter, page: n });
    }

    /** Demain, au format du champ date : la date de fin doit être postérieure à aujourd'hui. */
    function tomorrow() {
        // « en-CA » écrit la date AAAA-MM-JJ, à l'heure locale du navigateur.
        return new Date(Date.now() + 24 * 60 * 60 * 1000).toLocaleDateString('en-CA');
    }
</script>

<svelte:head><title>{t('moderation.admin.meta_title')} - VFF</title></svelte:head>

<Layout compactHeader>
    <main class="accounts-page">
        <PageHero title={t('moderation.admin.title')} subtitle={t('moderation.admin.subtitle')}>
            <ReviewNav current="accounts" />
        </PageHero>

        <div class="vff-container body">
            <!-- Toujours présente : les messages qui y entrent sont annoncés (Notice). -->
            <div class="flash">
                {#if flash.success}<Notice tone="success">{flash.success}</Notice>{/if}
                {#if flash.error}<Notice tone="danger">{flash.error}</Notice>{/if}
            </div>

            <form class="search" role="search" onsubmit={submitSearch}>
                <Field
                    id="account-search"
                    label={t('moderation.admin.search_label')}
                    hint={t('moderation.admin.search_hint')}
                >
                    {#snippet children(attrs)}
                        <input
                            {...attrs}
                            class="vff-input"
                            type="search"
                            name="q"
                            autocomplete="off"
                            maxlength="255"
                            bind:value={query}
                        />
                    {/snippet}
                </Field>
                <button type="submit" class="vff-btn secondary">
                    {t('moderation.admin.search_button')}
                </button>
            </form>

            <nav class="filters" aria-label={t('moderation.admin.filter_label')}>
                <ul>
                    {#each FILTERS as f (f)}
                        <li>
                            <Link
                                href="/validate/accounts?filter={f}{search
                                    ? `&q=${encodeURIComponent(search)}`
                                    : ''}"
                                aria-current={f === filter ? 'page' : undefined}
                                preserveScroll
                            >
                                {t(`moderation.admin.filters.${f}`)}
                                {#if f === 'suspended'}
                                    <span class="count">{counts.suspended ?? 0}</span>
                                {/if}
                            </Link>
                        </li>
                    {/each}
                </ul>
            </nav>

            {#if items.length === 0}
                <EmptyState
                    icon="bi-people"
                    title={t(`moderation.admin.empty.${filter}`)}
                    headingLevel={2}
                />
            {:else}
                <ol class="list" aria-label={t('moderation.admin.list_label')}>
                    {#each items as a (a.id)}
                        <li class="account" id="account-{a.id}">
                            <article aria-labelledby="account-{a.id}-name">
                                <div class="head">
                                    <h2 id="account-{a.id}-name">{a.name}</h2>
                                    <span class="vff-badge muted">{t(`auth.roles.${a.role}`)}</span>
                                    {#if a.suspension}
                                        <span class="vff-badge danger"
                                            >{t('moderation.admin.badge_suspended')}</span
                                        >
                                    {/if}
                                </div>
                                <p class="meta">
                                    <span>
                                        {t('moderation.admin.member_since', {
                                            date: day(a.created_at),
                                        })}
                                    </span>
                                    <span>
                                        {t('moderation.admin.contributions', {
                                            count: a.contributions.toLocaleString(lang),
                                        })}
                                    </span>
                                </p>

                                {#if a.suspension}
                                    <div class="outcome">
                                        <p class="meta">
                                            <span>{suspensionLine(a.suspension)}</span>
                                        </p>
                                        <div class="note">
                                            <p class="note-title">{t('moderation.admin.reason')}</p>
                                            <p>{a.suspension.reason}</p>
                                        </div>
                                        <div class="actions">
                                            <button
                                                type="button"
                                                class="vff-btn secondary"
                                                disabled={processing}
                                                onclick={() => lift(a)}
                                            >
                                                {t('moderation.admin.lift')}<span
                                                    class="vff-sr-only">&nbsp;: {a.name}</span
                                                >
                                            </button>
                                        </div>
                                    </div>
                                {:else if a.can_suspend}
                                    <details class="suspend">
                                        <summary>
                                            {t('moderation.admin.suspend_open')}<span
                                                class="vff-sr-only">&nbsp;: {a.name}</span
                                            >
                                        </summary>
                                        <form
                                            class="handle"
                                            onsubmit={(e) => suspend(e, a)}
                                            novalidate
                                        >
                                            <Field
                                                id="reason-{a.id}"
                                                label={t('moderation.admin.reason_label')}
                                                hint={t('moderation.admin.reason_hint', {
                                                    min: REASON_MIN,
                                                })}
                                                error={active === a.id ? errors.reason : null}
                                            >
                                                {#snippet children(attrs)}
                                                    <textarea
                                                        {...attrs}
                                                        class="vff-textarea"
                                                        rows="4"
                                                        maxlength="2000"
                                                        bind:value={reasons[a.id]}
                                                    ></textarea>
                                                {/snippet}
                                            </Field>
                                            <Field
                                                id="until-{a.id}"
                                                label={t('moderation.admin.until_label')}
                                                hint={t('moderation.admin.until_hint')}
                                                error={active === a.id ? errors.until : null}
                                            >
                                                {#snippet children(attrs)}
                                                    <input
                                                        {...attrs}
                                                        class="vff-input date"
                                                        type="date"
                                                        min={tomorrow()}
                                                        bind:value={untils[a.id]}
                                                    />
                                                {/snippet}
                                            </Field>
                                            <div class="actions">
                                                <button
                                                    type="submit"
                                                    class="vff-btn danger"
                                                    disabled={processing}
                                                >
                                                    {t('moderation.admin.submit')}<span
                                                        class="vff-sr-only">&nbsp;: {a.name}</span
                                                    >
                                                </button>
                                            </div>
                                        </form>
                                    </details>
                                {:else if a.blocked}
                                    <p class="blocked">{a.blocked}</p>
                                {/if}
                            </article>
                        </li>
                    {/each}
                </ol>

                {#if accounts.last_page > 1}
                    <div class="pager">
                        <Pagination
                            current={accounts.current_page}
                            last={accounts.last_page}
                            onpage={goToPage}
                        />
                    </div>
                {/if}
            {/if}
        </div>
    </main>
</Layout>

<style>
    .body {
        padding-block: var(--vff-section-space);
    }
    .flash {
        max-width: 860px;
    }
    .flash > :global(*) {
        margin-bottom: var(--vff-space-5);
    }
    .search {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        gap: var(--vff-space-3);
        max-width: 860px;
        margin-bottom: var(--vff-space-6);
    }
    .search :global(.vff-input) {
        min-width: min(100%, 320px);
    }
    .filters ul {
        display: flex;
        flex-wrap: wrap;
        gap: var(--vff-space-2);
        margin: 0 0 var(--vff-space-6);
        padding: 0;
        list-style: none;
    }
    .filters :global(a) {
        display: inline-flex;
        align-items: center;
        gap: var(--vff-space-2);
        min-height: 40px;
        padding: 6px 16px;
        border: 1px solid var(--vff-border-strong);
        border-radius: var(--vff-radius-pill);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text);
        text-decoration: none;
        transition:
            background-color var(--vff-transition),
            border-color var(--vff-transition);
    }
    .filters :global(a:hover) {
        background-color: var(--vff-bg-subtle);
    }
    /* Filtre actif : contour et graisse plus forts, en plus de aria-current. */
    .filters :global(a[aria-current='page']) {
        border-color: var(--vff-text);
        background-color: var(--vff-bg-subtle);
        font-weight: 600;
    }
    .count {
        font-variant-numeric: tabular-nums;
        color: var(--vff-text-muted);
    }
    .list {
        max-width: 860px;
        margin: 0;
        padding: 0;
        list-style: none;
        border-top: 1px solid var(--vff-border);
    }
    .account {
        padding-block: var(--vff-space-6);
        border-bottom: 1px solid var(--vff-border);
        scroll-margin-top: 80px;
    }
    article {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-4);
    }
    .head {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: var(--vff-space-2) var(--vff-space-3);
    }
    .head h2 {
        min-width: 0;
        margin: 0;
        font-family: var(--vff-font-display);
        font-size: var(--vff-fs-h3);
        line-height: 1.3;
        overflow-wrap: anywhere;
        text-wrap: balance;
    }
    .meta {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: var(--vff-space-1) var(--vff-space-4);
        margin: 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-ui);
        color: var(--vff-text-muted);
    }
    .blocked {
        max-width: 68ch;
        margin: 0;
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
    .suspend summary {
        display: inline-flex;
        align-items: center;
        min-height: 44px;
        padding-inline: var(--vff-space-4);
        border: 1px solid var(--vff-border-control);
        border-radius: var(--vff-radius-sm);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-md);
        cursor: pointer;
    }
    .suspend summary:hover {
        background-color: var(--vff-bg-subtle);
    }
    .handle {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-4);
        max-width: 68ch;
        margin-top: var(--vff-space-3);
        padding: var(--vff-space-4) var(--vff-space-5);
        background-color: var(--vff-bg-subtle);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
    }
    .handle :global(.date) {
        max-width: 14rem;
    }
    .actions {
        display: flex;
        flex-wrap: wrap;
        gap: var(--vff-space-3);
    }
    .outcome {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-3);
    }
    .note {
        max-width: 68ch;
        padding: var(--vff-space-3) var(--vff-space-4);
        background-color: var(--vff-bg-subtle);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
    }
    .note p {
        margin: 0;
        overflow-wrap: anywhere;
        white-space: pre-line;
    }
    .note-title {
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        font-weight: 600;
        color: var(--vff-text-muted);
    }
    .pager {
        max-width: 860px;
        margin-top: var(--vff-space-6);
    }

    @media (max-width: 600px) {
        .handle {
            padding: var(--vff-space-4);
        }
        .actions :global(.vff-btn),
        .search :global(.vff-btn) {
            width: 100%;
        }
    }
</style>

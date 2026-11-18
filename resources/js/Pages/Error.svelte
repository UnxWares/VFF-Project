<script>
    import { Link, page } from '@inertiajs/svelte';
    import Layout from '$components/Layout.svelte';
    import PageHero from '$components/PageHero.svelte';
    import { i18n } from '$lib/i18n.js';

    /**
     * Page d'erreur du site (404, 403, 405, 419, 429, 500, 503), rendue par bootstrap/app.php avec le
     * bon code HTTP. Même en-tête et même pied de page que les autres pages ; le titre dit ce qui
     * s'est passé, le texte dit quoi faire, et le bouton principal est l'action la plus utile :
     * recharger quand un nouvel essai a des chances de réussir, revenir à l'accueil sinon.
     *
     * Props : `status` (code HTTP), `detail` (message précis d'un refus 403, déjà traduit),
     * `retry_after` (secondes d'attente d'une limite de débit, 429).
     */
    /** @type {{ status?: number, detail?: string|null, retry_after?: number|null }} */
    let { status = 500, detail = null, retry_after = null } = $props();

    const KNOWN = [403, 404, 405, 419, 429, 500, 503];
    const key = $derived(KNOWN.includes(status) ? String(status) : 'default');
    /** Un nouvel essai peut réussir : on propose de recharger plutôt que de repartir. */
    const retryable = $derived([419, 429, 500, 503].includes(status));
    /** Les pistes de navigation n'ont de sens que si la page demandée n'existe pas ou est interdite. */
    const lost = $derived(!retryable);
    const guest = $derived(!page.props.auth?.user);

    /** Historique de navigation : lu une fois (la page est rendue côté navigateur seulement). */
    const canGoBack = typeof window !== 'undefined' && window.history.length > 1;

    // Pistes de reprise : de simples liens sous les boutons (pas une grille de tuiles à icône).
    const explore = [
        { href: '/maps', label: 'errors.map' },
        { href: '/lines', label: 'errors.lines' },
        { href: '/stations', label: 'errors.stations' },
    ];
</script>

<svelte:head>
    <title>{$i18n.t(`errors.${key}.title`)} - VFF</title>
    <meta name="robots" content="noindex" />
</svelte:head>

<Layout compactHeader>
    <main class="error-main">
        <PageHero
            eyebrow={status ? $i18n.t('errors.eyebrow', { code: status }) : null}
            title={$i18n.t(`errors.${key}.title`)}
            subtitle={$i18n.t(`errors.${key}.text`)}
        >
            {#if detail}
                <p class="detail">{detail}</p>
            {/if}
            {#if status === 429 && retry_after}
                <p class="detail">{$i18n.t('errors.retry_after', { seconds: retry_after })}</p>
            {/if}
            <div class="actions">
                {#if retryable}
                    <button
                        type="button"
                        class="vff-btn primary"
                        onclick={() => window.location.reload()}
                    >
                        <i class="bi bi-arrow-clockwise" aria-hidden="true"></i>
                        {$i18n.t('errors.reload')}
                    </button>
                    <Link href="/" class="vff-btn secondary">
                        <i class="bi bi-house" aria-hidden="true"></i>
                        {$i18n.t('errors.home')}
                    </Link>
                {:else}
                    <Link href="/" class="vff-btn primary">
                        <i class="bi bi-house" aria-hidden="true"></i>
                        {$i18n.t('errors.home')}
                    </Link>
                    {#if status === 403 && guest}
                        <Link href="/login" class="vff-btn secondary">
                            <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>
                            {$i18n.t('errors.login')}
                        </Link>
                    {/if}
                    {#if canGoBack}
                        <button
                            type="button"
                            class="vff-btn quiet"
                            onclick={() => window.history.back()}
                        >
                            <i class="bi bi-arrow-left" aria-hidden="true"></i>
                            {$i18n.t('errors.back')}
                        </button>
                    {/if}
                {/if}
            </div>
            {#if lost}
                <nav class="explore" aria-labelledby="explore-title">
                    <h2 id="explore-title">{$i18n.t('errors.explore')}</h2>
                    <ul>
                        {#each explore as e (e.href)}
                            <li><Link href={e.href} class="vff-link">{$i18n.t(e.label)}</Link></li>
                        {/each}
                    </ul>
                </nav>
            {/if}
        </PageHero>
    </main>
</Layout>

<style>
    /* Page courte : le bandeau occupe la hauteur disponible et centre son contenu (pas de grand vide
       blanc avant le pied de page). */
    .error-main {
        display: flex;
        flex-direction: column;
        min-height: 60vh;
    }
    .error-main > :global(.page-hero) {
        display: flex;
        flex: 1;
        flex-direction: column;
        justify-content: center;
    }
    /* Marges automatiques dans une colonne flexible : le bloc se rétrécirait ; il garde la grille. */
    .error-main > :global(.page-hero > .inner) {
        width: 100%;
    }
    .detail {
        max-width: var(--vff-measure);
        margin: var(--vff-space-4) 0 0;
        font-size: var(--vff-fs-md);
        color: var(--vff-text);
    }
    .actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: var(--vff-space-3) var(--vff-space-4);
        margin-top: var(--vff-space-5);
    }
    .explore {
        max-width: 720px;
        margin-top: var(--vff-block-space);
        padding-top: var(--vff-space-5);
        border-top: 1px solid var(--vff-border);
    }
    .explore h2 {
        margin: 0 0 var(--vff-space-2);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-md);
        font-weight: 600;
        line-height: var(--vff-lh-ui);
        color: var(--vff-text);
    }
    .explore ul {
        display: flex;
        flex-wrap: wrap;
        gap: var(--vff-space-2) var(--vff-space-5);
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .explore :global(.vff-link) {
        min-height: 44px;
        font-size: var(--vff-fs-md);
    }
</style>

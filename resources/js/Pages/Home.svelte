<script>
    import Layout from '$components/Layout.svelte';
    import WhoAreWe from '$components/Sections/WhoAreWe.svelte';
    import Genesis from '$components/Sections/Genesis.svelte';
    import Maps from '$components/Sections/Maps.svelte';
    import Features from '$components/Sections/Features.svelte';
    import Timeline from '$components/Sections/Timeline.svelte';
    import ArchiveGallery from '$components/Sections/ArchiveGallery.svelte';
    import Stats from '$components/Sections/Stats.svelte';
    import ContributeTeaser from '$components/Sections/ContributeTeaser.svelte';
    import FinalCTA from '$components/Sections/FinalCTA.svelte';
    import { i18n } from '$lib/i18n.js';

    import '$css/index/index.css';

    let { stats = null, threatened = [] } = $props();
</script>

<svelte:head>
    <title>{$i18n.t('home.meta_title')}</title>
    <meta name="description" content={$i18n.t('home.meta_description')} />
</svelte:head>

<Layout>
    <main>
        {#each threatened as t}
            <aside
                class="home-advocacy"
                class:reopening={t.advocacy?.kind === 'reopening'}
                role="alert"
            >
                <i
                    class="bi {t.advocacy?.kind === 'reopening'
                        ? 'bi-megaphone-fill'
                        : 'bi-exclamation-triangle-fill'}"
                ></i>
                <div class="home-advocacy-text">
                    <strong lang={t.advocacy?.lang?.title}
                        >{t.advocacy?.title ??
                            $i18n.t(
                                t.advocacy?.kind === 'reopening'
                                    ? 'home.advocacy.reopening'
                                    : 'home.advocacy.threatened',
                            )}</strong
                    >
                    {#if t.advocacy?.message}<span lang={t.advocacy.lang?.message}
                            >{t.advocacy.message}</span
                        >{/if}
                </div>
                <div class="home-advocacy-actions">
                    <a class="home-advocacy-link" href={`/lines/${t.slug}`}
                        >{$i18n.t('home.advocacy.see_line')}</a
                    >
                    {#if t.advocacy?.cta_url}
                        <a
                            class="home-advocacy-cta"
                            href={t.advocacy.cta_url}
                            target="_blank"
                            rel="noopener"
                            lang={t.advocacy.lang?.cta_label}
                        >
                            {t.advocacy.cta_label || $i18n.t('home.advocacy.act')}
                            <i class="bi bi-box-arrow-up-right"></i>
                        </a>
                    {/if}
                </div>
            </aside>
        {/each}
        <WhoAreWe />
        <Genesis />
        <Maps />
        <Features />
        <Timeline />
        <ArchiveGallery />
        <Stats {stats} />
        <ContributeTeaser />
        <FinalCTA />
    </main>
</Layout>

<style>
    .home-advocacy {
        display: flex;
        align-items: center;
        gap: 1rem;
        max-width: 1100px;
        margin: 1rem auto;
        padding: 0.9rem 1.25rem;
        background: linear-gradient(90deg, #b91c1c, #dc2626);
        color: #fff;
        border-radius: 12px;
        box-shadow: 0 6px 20px rgba(185, 28, 28, 0.25);
    }
    /* Campagne de réouverture : turquoise (cf. REOPENING_COLOR de railStyle.js) */
    .home-advocacy.reopening {
        background: linear-gradient(90deg, #0f766e, #0d9488);
        box-shadow: 0 6px 20px rgba(13, 148, 136, 0.25);
    }
    .home-advocacy > i {
        font-size: 1.5rem;
        flex-shrink: 0;
    }
    .home-advocacy-text {
        flex: 1;
        min-width: 0;
    }
    .home-advocacy-text strong {
        display: block;
    }
    .home-advocacy-text span {
        display: block;
        font-size: 0.85rem;
        opacity: 0.92;
        margin-top: 0.15rem;
    }
    .home-advocacy-actions {
        display: flex;
        gap: 0.5rem;
        flex-shrink: 0;
    }
    .home-advocacy-link,
    .home-advocacy-cta {
        text-decoration: none;
        font-weight: 600;
        padding: 0.5rem 0.9rem;
        border-radius: 999px;
        white-space: nowrap;
        font-size: 0.9rem;
    }
    .home-advocacy-link {
        color: #fff;
        border: 1px solid rgba(255, 255, 255, 0.6);
    }
    .home-advocacy-cta {
        background: #fff;
        color: #b91c1c;
        font-weight: 700;
    }
    @media (max-width: 640px) {
        .home-advocacy {
            flex-direction: column;
            align-items: flex-start;
        }
    }
</style>

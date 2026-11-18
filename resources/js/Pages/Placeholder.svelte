<script>
    import Layout from '$components/Layout.svelte';
    import PageHero from '$components/PageHero.svelte';
    import { Link } from '@inertiajs/svelte';
    import { i18n } from '$lib/i18n.js';

    /** `page` : nom de la rubrique, facultatif (sert d'intitulé de rubrique et de titre d'onglet). */
    let { page = null } = $props();

    const tabTitle = $derived(page ?? $i18n.t('placeholder.meta_title'));
</script>

<svelte:head>
    <title>{tabTitle} - VFF</title>
</svelte:head>

<Layout compactHeader>
    <main>
        <PageHero
            eyebrow={page}
            title={$i18n.t('placeholder.title')}
            subtitle={$i18n.t('placeholder.subtitle')}
        />
        <div class="vff-section">
            <div class="vff-container actions">
                <Link href="/maps" class="vff-btn primary">
                    <i class="bi bi-map" aria-hidden="true"></i>
                    {$i18n.t('placeholder.to_map')}
                </Link>
                <Link href="/lines" class="vff-btn secondary">
                    <i class="bi bi-signpost-2" aria-hidden="true"></i>
                    {$i18n.t('placeholder.to_lines')}
                </Link>
                <Link href="/" class="vff-btn quiet">
                    <i class="bi bi-arrow-left" aria-hidden="true"></i>
                    {$i18n.t('placeholder.back_home')}
                </Link>
            </div>
        </div>
    </main>
</Layout>

<style>
    .actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: var(--vff-space-3);
    }
</style>

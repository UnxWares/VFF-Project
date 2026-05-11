<script>
    import Layout from '$components/Layout.svelte';
    import { Link } from '@inertiajs/svelte';
    import { i18n } from '$lib/i18n.js';

    let { page = 'Page', icon = 'bi-hammer' } = $props();
</script>

<svelte:head>
    <title>{page} — VFF</title>
</svelte:head>

<Layout compactHeader>
    <main class="placeholder">
        <div class="placeholder-inner">
            <i class="bi {icon}"></i>
            <h1>{page}</h1>
            <p>{$i18n.t('common.wip_subtitle')}</p>
            <p class="back">
                <Link href="/"><i class="bi bi-arrow-left-circle"></i> {$i18n.t('common.back_home')}</Link>
            </p>
        </div>
    </main>
</Layout>

<style>
    .placeholder {
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 60vh;
        padding: 60px 20px;
    }
    .placeholder-inner {
        text-align: center;
        max-width: 560px;
    }
    .placeholder-inner :global(i.bi) {
        font-size: 64px;
        color: var(--vff-accent);
        display: block;
        margin-bottom: 16px;
    }
    .placeholder-inner h1 {
        margin: 0 0 12px;
    }
    .placeholder-inner p {
        font-size: 18px;
        margin: 12px 0;
        color: var(--vff-text-muted);
    }
    .back :global(a) {
        text-decoration: none;
        color: var(--vff-accent);
        font-weight: 600;
    }
    .back :global(a:hover) {
        text-decoration: underline;
    }
</style>

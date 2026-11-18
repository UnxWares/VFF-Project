<script>
    import Layout from '$components/Layout.svelte';
    import SheetHero from '$components/Sheet/SheetHero.svelte';
    import SheetSection from '$components/Sheet/SheetSection.svelte';
    import StatStrip from '$components/Sheet/StatStrip.svelte';
    import { i18n } from '$lib/i18n.js';

    let { material, lines = [] } = $props();

    const statItems = $derived(
        [
            {
                value: `${material.in_service_from}-${material.in_service_to ?? $i18n.t('materials.present')}`,
                label: String($i18n.t('materials.period')),
            },
            material.builder
                ? { text: material.builder, label: String($i18n.t('materials.builder')) }
                : null,
            { value: lines.length, label: String($i18n.t('materials.lines')) },
        ].filter(Boolean),
    );
</script>

<svelte:head>
    <title>{material.name} - VFF</title>
    <meta name="description" content={$i18n.t('materials.meta_description')} />
</svelte:head>

<Layout compactHeader>
    <main>
        <SheetHero
            crumbs={[
                { href: '/materials', label: $i18n.t('materials.all'), icon: 'bi-arrow-left' },
            ]}
            crumbsLabel={$i18n.t('materials.breadcrumbs')}
            plate={$i18n.t(`materials.type.${material.type}`, { defaultValue: material.type })}
            title={material.name}
            stats={heroStats}
        />

        {#snippet heroStats()}
            <StatStrip items={statItems} />
        {/snippet}

        <SheetSection id="lines" title={$i18n.t('materials.lines')} count={lines.length}>
            {#if lines.length}
                <div class="grid">
                    {#each lines as l (l.slug)}
                        <a class="line-card" href="/lines/{l.slug}">
                            {#if l.code}<span class="code">{l.code}</span>{/if}
                            <strong>{l.name}</strong>
                        </a>
                    {/each}
                </div>
            {:else}
                <p class="empty">
                    {$i18n.t('materials.no_lines')}
                    <a href="/contribute">{$i18n.t('materials.contribute')}</a>
                </p>
            {/if}
        </SheetSection>
    </main>
</Layout>

<style>
    main {
        background-color: var(--vff-bg);
        padding-bottom: 64px;
    }
    .grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(min(280px, 100%), 1fr));
        gap: 12px;
    }
    .line-card {
        display: flex;
        gap: 12px;
        align-items: baseline;
        padding: 12px 14px;
        background-color: var(--vff-bg-elevated);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        text-decoration: none;
        color: var(--vff-text);
        transition: border-color var(--vff-transition);
    }
    .line-card:hover {
        border-color: var(--vff-accent);
    }
    .line-card:focus-visible {
        outline: 2px solid var(--vff-accent);
        outline-offset: 2px;
    }
    .line-card strong {
        font-family: var(--vff-font-display);
        font-size: 16px;
    }
    .code {
        flex-shrink: 0;
        padding: 1px 6px;
        border: 1.5px solid var(--vff-text);
        border-radius: 4px;
        font-family: var(--vff-font-heading);
        font-size: 12.5px;
        font-weight: 600;
        font-variant-numeric: tabular-nums;
    }
    .empty {
        color: var(--vff-text-muted);
        font-style: italic;
    }
    .empty a {
        color: var(--vff-accent-ink);
    }
</style>

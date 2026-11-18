<script>
    import Layout from '$components/Layout.svelte';
    import SheetHero from '$components/Sheet/SheetHero.svelte';
    import SheetSection from '$components/Sheet/SheetSection.svelte';
    import StatStrip from '$components/Sheet/StatStrip.svelte';
    import EmptyState from '$components/ui/EmptyState.svelte';
    import { i18n } from '$lib/i18n.js';

    let { material, lines = [] } = $props();

    // Période de service : deux années en grand chiffre, « 1950-aujourd'hui » en phrase courte.
    const period = $derived(
        material.in_service_to
            ? `${material.in_service_from}-${material.in_service_to}`
            : `${material.in_service_from}-${$i18n.t('materials.present')}`,
    );
    const statItems = $derived(
        [
            material.in_service_to
                ? { value: period, label: String($i18n.t('materials.period')) }
                : { text: period, label: String($i18n.t('materials.period')) },
            material.builder
                ? { text: material.builder, label: String($i18n.t('materials.builder')) }
                : null,
            {
                value: lines.length.toLocaleString($i18n.language ?? 'fr'),
                label: String($i18n.t('materials.lines')),
            },
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
            <StatStrip items={statItems} label={$i18n.t('materials.key_figures')} />
        {/snippet}

        <SheetSection id="lines" title={$i18n.t('materials.lines')} count={lines.length}>
            {#if lines.length}
                <ul class="grid">
                    {#each lines as l (l.slug)}
                        <li>
                            <a class="vff-tile line-card" href="/lines/{l.slug}">
                                {#if l.code}<span class="vff-plate">{l.code}</span>{/if}
                                <strong>{l.name}</strong>
                            </a>
                        </li>
                    {/each}
                </ul>
            {:else}
                <EmptyState icon="bi-bezier2" title={$i18n.t('materials.no_lines')}>
                    <a class="vff-btn secondary small" href="/contribute"
                        >{$i18n.t('materials.contribute')}</a
                    >
                </EmptyState>
            {/if}
        </SheetSection>
    </main>
</Layout>

<style>
    main {
        background-color: var(--vff-bg);
        padding-bottom: var(--vff-section-space);
    }
    .grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(min(300px, 100%), 1fr));
        gap: var(--vff-space-3) var(--vff-space-5);
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .line-card {
        display: flex;
        gap: 12px;
        align-items: baseline;
        height: 100%;
    }
    .line-card strong {
        min-width: 0;
        font-family: var(--vff-font-display);
        font-size: var(--vff-fs-md);
        line-height: 1.35;
    }
</style>

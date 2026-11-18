<script>
    import Layout from '$components/Layout.svelte';
    import PageHero from '$components/PageHero.svelte';
    import { i18n } from '$lib/i18n.js';

    let { groups = [], total = 0 } = $props();

    const TYPE_ICONS = {
        locomotive_vapeur: 'bi-fire',
        locomotive_diesel: 'bi-fuel-pump-fill',
        locomotive_electrique: 'bi-lightning-charge-fill',
        automotrice: 'bi-train-front-fill',
        autorail: 'bi-train-front',
        rame_tgv: 'bi-speedometer2',
        voiture: 'bi-train-lightrail-front',
        wagon: 'bi-box-seam',
    };
    const typeLabel = (type) => $i18n.t(`materials.types.${type}`, { defaultValue: type });
</script>

<svelte:head>
    <title>{$i18n.t('materials.meta_title')} - VFF</title>
    <meta name="description" content={$i18n.t('materials.meta_description')} />
</svelte:head>

<Layout compactHeader>
    <main>
        <PageHero
            eyebrow={$i18n.t('materials.eyebrow')}
            title={$i18n.t('materials.title')}
            subtitle={total > 0
                ? $i18n.t('materials.subtitle', {
                      total: Number(total).toLocaleString($i18n.language ?? 'fr'),
                  })
                : $i18n.t('materials.subtitle_empty')}
        />

        <section class="materials-page">
            {#each groups as g (g.type)}
                <div class="type-block">
                    <h2>
                        <i class="bi {TYPE_ICONS[g.type] ?? 'bi-train-front'}" aria-hidden="true"
                        ></i>
                        {typeLabel(g.type)}
                        <span class="count">{g.items.length}</span>
                    </h2>
                    <div class="grid">
                        {#each g.items as m (m.slug)}
                            <a class="card" href="/materials/{m.slug}">
                                <strong>{m.name}</strong>
                                {#if m.builder}<span class="builder">{m.builder}</span>{/if}
                                <span class="period">
                                    {m.in_service_from}-{m.in_service_to ??
                                        $i18n.t('materials.present')}
                                </span>
                            </a>
                        {/each}
                    </div>
                </div>
            {:else}
                <div class="empty-state">
                    <i class="bi bi-train-front" aria-hidden="true"></i>
                    <h2>{$i18n.t('materials.empty_title')}</h2>
                    <p>{$i18n.t('materials.empty_text')}</p>
                    <a href="/contribute" class="vff-btn ghost">
                        <i class="bi bi-pencil-square" aria-hidden="true"></i>
                        {$i18n.t('materials.empty_cta')}
                    </a>
                </div>
            {/each}
        </section>
    </main>
</Layout>

<style>
    .materials-page {
        max-width: 1180px;
        margin: 0 auto;
        padding: 8px 24px 64px;
    }
    .type-block {
        margin-bottom: 44px;
    }
    .type-block h2 {
        display: flex;
        align-items: baseline;
        gap: 10px;
        margin: 0 0 16px;
        font-family: var(--vff-font-display);
        font-size: 24px;
        color: var(--vff-text);
    }
    .type-block h2:after {
        display: none;
    }
    .type-block h2 i {
        color: var(--vff-accent);
        font-size: 20px;
    }
    .type-block .count {
        font-family: var(--vff-font-heading);
        font-size: 15px;
        font-weight: 400;
        color: var(--vff-text-muted);
    }
    .grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(min(240px, 100%), 1fr));
        gap: 12px;
    }
    .card {
        display: flex;
        flex-direction: column;
        gap: 4px;
        padding: 14px 16px;
        background-color: var(--vff-bg-elevated);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        text-decoration: none;
        color: var(--vff-text);
        transition: border-color var(--vff-transition);
    }
    .card:hover {
        border-color: var(--vff-accent);
    }
    .card:focus-visible {
        outline: 2px solid var(--vff-accent);
        outline-offset: 2px;
    }
    .card strong {
        font-family: var(--vff-font-display);
        font-size: 16px;
    }
    .builder {
        font-size: 14px;
        color: var(--vff-text-muted);
    }
    .period {
        margin-top: 4px;
        font-family: var(--vff-font-heading);
        font-size: 13px;
        color: var(--vff-text);
        font-variant-numeric: tabular-nums;
    }
    /* Annuaire vide : une invitation à contribuer plutôt qu'une page blanche. */
    .empty-state {
        max-width: 560px;
        margin: 0 auto;
        padding: 36px 28px 40px;
        text-align: center;
        border: 1px dashed var(--vff-border-strong);
        border-radius: var(--vff-radius-lg);
        background: var(--vff-bg-elevated);
    }
    .empty-state > i {
        font-size: 34px;
        color: var(--vff-accent);
    }
    .empty-state h2 {
        margin: 12px 0 8px;
        font-family: var(--vff-font-display);
        font-size: 24px;
        color: var(--vff-text);
    }
    .empty-state h2:after {
        display: none;
    }
    .empty-state p {
        margin: 0 0 20px;
        font-size: 16px;
        line-height: 1.6;
        color: var(--vff-text-muted);
    }
    .empty-state .vff-btn.ghost {
        color: var(--vff-accent-ink);
    }
</style>

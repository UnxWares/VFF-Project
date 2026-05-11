<script>
    import Layout from '$components/Layout.svelte';
    import PageHero from '$components/PageHero.svelte';
    import IconCard from '$components/IconCard.svelte';
    import SectionBlock from '$components/SectionBlock.svelte';
    import { i18n } from '$lib/i18n.js';

    const features = [
        { icon: 'bi-sliders', titleKey: 'maps.features.time_title', textKey: 'maps.features.time_text' },
        { icon: 'bi-bezier', titleKey: 'maps.features.lines_title', textKey: 'maps.features.lines_text' },
        { icon: 'bi-train-freight-front', titleKey: 'maps.features.materials_title', textKey: 'maps.features.materials_text' },
        { icon: 'bi-images', titleKey: 'maps.features.archives_title', textKey: 'maps.features.archives_text' },
    ];

    const eras = [1990, 2000, 2010, 2020, 2030];
    let selectedEra = $state(2030);
</script>

<svelte:head>
    <title>{$i18n.t('maps.meta_title')} — VFF</title>
    <meta name="description" content={$i18n.t('maps.meta_description')} />
</svelte:head>

<Layout compactHeader>
    <main>
        <PageHero
            eyebrow={$i18n.t('maps.hero.eyebrow')}
            title={$i18n.t('maps.hero.title')}
            subtitle={$i18n.t('maps.hero.subtitle')}
            status={$i18n.t('maps.hero.status')}
        />

        <!-- Aperçu mockup (en attendant MapLibre réelle) -->
        <section class="preview">
            <div class="preview-frame">
                <div class="preview-toolbar">
                    <div class="dots">
                        <span></span><span></span><span></span>
                    </div>
                    <span class="url">vff.fr/maps</span>
                </div>
                <div class="preview-map">
                    <div class="placeholder-grid"></div>
                    <div class="placeholder-lines">
                        <svg viewBox="0 0 800 400" preserveAspectRatio="none" aria-hidden="true">
                            <path d="M 50 50 C 200 100, 400 300, 750 200" stroke="var(--vff-accent)" stroke-width="3" fill="none" opacity="0.85"/>
                            <path d="M 100 350 C 300 300, 500 150, 700 100" stroke="var(--vff-accent)" stroke-width="2" fill="none" opacity="0.5" stroke-dasharray="6 6"/>
                            <path d="M 50 200 L 750 220" stroke="var(--vff-accent)" stroke-width="2" fill="none" opacity="0.35" stroke-dasharray="2 4"/>
                            <circle cx="300" cy="180" r="6" fill="var(--vff-accent)"/>
                            <circle cx="550" cy="240" r="6" fill="var(--vff-accent)"/>
                        </svg>
                    </div>
                    <div class="legend">
                        <span><i class="dot solid"></i> En service</span>
                        <span><i class="dot dashed"></i> Voie unique</span>
                        <span><i class="dot dotted"></i> Disparue</span>
                    </div>
                </div>
                <div class="preview-slider">
                    <span class="slider-label">Époque :</span>
                    <div class="slider-track">
                        {#each eras as era}
                            <button
                                type="button"
                                class="era"
                                class:active={selectedEra === era}
                                onclick={() => (selectedEra = era)}
                            >
                                {era}
                            </button>
                        {/each}
                    </div>
                </div>
            </div>
            <p class="preview-caption">
                Aperçu indicatif — la carte interactive réelle arrivera dans la phase 2.
            </p>
        </section>

        <SectionBlock title={$i18n.t('maps.features.title')} maxWidth="1100px">
            <div class="features-grid">
                {#each features as f}
                    <IconCard
                        icon={f.icon}
                        title={$i18n.t(f.titleKey)}
                        text={$i18n.t(f.textKey)}
                    />
                {/each}
            </div>
        </SectionBlock>

        <SectionBlock kind="alt" title={$i18n.t('maps.tech.title')} maxWidth="780px">
            <p class="tech-paragraph">{$i18n.t('maps.tech.paragraph')}</p>
        </SectionBlock>

        <SectionBlock title={$i18n.t('maps.progress.title')} maxWidth="700px">
            <div class="progress-card">
                <i class="bi bi-flag-fill"></i>
                <div>
                    <h3>{$i18n.t('maps.progress.phase')}</h3>
                    <p>
                        {@html $i18n.t('maps.progress.description', {
                            roadmap_url:
                                'https://github.com/vff-project/vff/blob/main/ROADMAP.md',
                        })}
                    </p>
                </div>
            </div>
        </SectionBlock>

        <SectionBlock kind="cta" maxWidth="640px">
            <h2>{$i18n.t('maps.cta.title')}</h2>
            <p>{$i18n.t('maps.cta.text')}</p>
            <a href="https://discord.com/invite/6SwTfXBx4h" target="_blank" rel="noopener" class="vff-btn on-cta">
                <i class="bi bi-discord"></i> {$i18n.t('maps.cta.discord')}
            </a>
        </SectionBlock>
    </main>
</Layout>

<style>
    .preview {
        max-width: 1100px;
        margin: -20px auto 0;
        padding: 0 16px 40px;
        position: relative;
        z-index: 2;
    }
    .preview-frame {
        background-color: var(--vff-bg-elevated);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-lg);
        box-shadow: 0 24px 64px var(--vff-shadow-strong);
        overflow: hidden;
    }
    .preview-toolbar {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 16px;
        background-color: var(--vff-bg-muted);
        border-bottom: 1px solid var(--vff-border);
    }
    .dots {
        display: flex;
        gap: 6px;
    }
    .dots span {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background-color: var(--vff-border-strong);
    }
    .dots span:nth-child(1) { background-color: #ff5f57; }
    .dots span:nth-child(2) { background-color: #ffbd2e; }
    .dots span:nth-child(3) { background-color: #28ca42; }
    .url {
        flex: 1;
        text-align: center;
        font-family: var(--vff-font-secondary);
        font-size: 13px;
        color: var(--vff-text-muted);
        background-color: var(--vff-bg);
        padding: 4px 12px;
        border-radius: var(--vff-radius-sm);
        max-width: 200px;
    }
    .preview-map {
        position: relative;
        aspect-ratio: 16 / 8;
        background: linear-gradient(135deg, #e8ecec 0%, #f5f7f7 100%);
        overflow: hidden;
    }
    .placeholder-grid {
        position: absolute;
        inset: 0;
        background-image:
            linear-gradient(0deg, rgba(100, 100, 100, 0.08) 1px, transparent 1px),
            linear-gradient(90deg, rgba(100, 100, 100, 0.08) 1px, transparent 1px);
        background-size: 40px 40px;
    }
    .placeholder-lines {
        position: absolute;
        inset: 0;
    }
    .placeholder-lines svg {
        width: 100%;
        height: 100%;
    }
    .legend {
        position: absolute;
        bottom: 16px;
        left: 16px;
        background-color: var(--vff-bg);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-sm);
        padding: 8px 12px;
        display: flex;
        flex-direction: column;
        gap: 4px;
        font-size: 12px;
        color: var(--vff-text);
    }
    .legend span {
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .legend .dot {
        width: 16px;
        height: 2px;
        background-color: var(--vff-accent);
    }
    .legend .dot.dashed {
        background-image: linear-gradient(to right, var(--vff-accent) 4px, transparent 4px);
        background-size: 8px 100%;
        background-color: transparent;
    }
    .legend .dot.dotted {
        background-image: linear-gradient(to right, var(--vff-accent) 2px, transparent 2px);
        background-size: 4px 100%;
        background-color: transparent;
        opacity: 0.5;
    }
    .preview-slider {
        padding: 16px;
        display: flex;
        align-items: center;
        gap: 16px;
        background-color: var(--vff-bg-elevated);
        border-top: 1px solid var(--vff-border);
    }
    .slider-label {
        font-family: var(--vff-font-display);
        font-size: 14px;
        color: var(--vff-text-muted);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    .slider-track {
        display: flex;
        gap: 8px;
        flex: 1;
        justify-content: center;
        flex-wrap: wrap;
    }
    .era {
        font-family: var(--vff-font-display);
        font-size: 16px;
        font-weight: 600;
        padding: 8px 16px;
        border-radius: var(--vff-radius-pill);
        background-color: var(--vff-bg-muted);
        color: var(--vff-text-muted);
        transition: all var(--vff-transition);
    }
    .era:hover {
        background-color: var(--vff-accent-soft);
        color: var(--vff-accent);
    }
    .era.active {
        background-color: var(--vff-accent);
        color: var(--vff-text-inverse);
    }
    .preview-caption {
        text-align: center;
        font-size: 13px;
        color: var(--vff-text-muted);
        margin-top: 12px;
        font-style: italic;
    }
    .features-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 20px;
    }
    .tech-paragraph {
        font-size: 17px;
        line-height: 1.65;
        text-align: justify;
        color: var(--vff-text);
    }
    .progress-card {
        display: flex;
        gap: 20px;
        align-items: flex-start;
        background-color: var(--vff-bg-elevated);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        padding: 24px;
    }
    .progress-card i {
        font-size: 32px;
        color: var(--vff-accent);
    }
    .progress-card h3 {
        margin: 0 0 4px;
    }
    .progress-card p {
        margin: 0;
        color: var(--vff-text-muted);
        line-height: 1.5;
    }
    .progress-card :global(p a) {
        color: var(--vff-accent);
        text-decoration: none;
    }
    .progress-card :global(p a:hover) {
        text-decoration: underline;
    }
    /* `.vff-btn` est défini globalement dans general.css. */
    @media (max-width: 600px) {
        .tech-paragraph {
            text-align: left;
        }
        .progress-card {
            flex-direction: column;
        }
    }
</style>

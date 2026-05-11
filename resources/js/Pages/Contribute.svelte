<script>
    import Layout from '$components/Layout.svelte';
    import PageHero from '$components/PageHero.svelte';
    import IconCard from '$components/IconCard.svelte';
    import SectionBlock from '$components/SectionBlock.svelte';
    import { i18n } from '$lib/i18n.js';
    import { scrollReveal } from '$lib/actions/scrollReveal.js';

    const ways = [
        {
            icon: 'bi-pencil-square',
            titleKey: 'contribute.ways.data_title',
            textKey: 'contribute.ways.data_text',
            statusKey: 'contribute.ways.data_status',
        },
        {
            icon: 'bi-images',
            titleKey: 'contribute.ways.media_title',
            textKey: 'contribute.ways.media_text',
            statusKey: 'contribute.ways.media_status',
        },
        {
            icon: 'bi-code-slash',
            titleKey: 'contribute.ways.code_title',
            textKey: 'contribute.ways.code_text',
            statusKey: 'contribute.ways.code_status',
        },
    ];

    const steps = [
        { num: '1', titleKey: 'contribute.process.step1_title', textKey: 'contribute.process.step1_text' },
        { num: '2', titleKey: 'contribute.process.step2_title', textKey: 'contribute.process.step2_text' },
        { num: '3', titleKey: 'contribute.process.step3_title', textKey: 'contribute.process.step3_text' },
    ];

    const rules = [
        { icon: 'bi-bookmark', titleKey: 'contribute.rules.sources_title', textKey: 'contribute.rules.sources_text' },
        { icon: 'bi-file-earmark-check', titleKey: 'contribute.rules.license_title', textKey: 'contribute.rules.license_text' },
        { icon: 'bi-heart', titleKey: 'contribute.rules.respect_title', textKey: 'contribute.rules.respect_text' },
    ];
</script>

<svelte:head>
    <title>{$i18n.t('contribute.meta_title')} — VFF</title>
    <meta name="description" content={$i18n.t('contribute.meta_description')} />
</svelte:head>

<Layout compactHeader>
    <main>
        <PageHero
            eyebrow={$i18n.t('contribute.hero.eyebrow')}
            title={$i18n.t('contribute.hero.title')}
            subtitle={$i18n.t('contribute.hero.subtitle')}
        />

        <SectionBlock maxWidth="1100px">
            <div class="ways-grid" use:scrollReveal={{ threshold: 0.2 }}>
                {#each ways as w}
                    <IconCard
                        icon={w.icon}
                        title={$i18n.t(w.titleKey)}
                        text={$i18n.t(w.textKey)}
                        status={$i18n.t(w.statusKey)}
                    />
                {/each}
            </div>
        </SectionBlock>

        <SectionBlock kind="alt" title={$i18n.t('contribute.process.title')} maxWidth="900px">
            <ol class="steps">
                {#each steps as s}
                    <li>
                        <span class="step-num">{s.num}</span>
                        <div>
                            <h3>{$i18n.t(s.titleKey)}</h3>
                            <p>{$i18n.t(s.textKey)}</p>
                        </div>
                    </li>
                {/each}
            </ol>
        </SectionBlock>

        <SectionBlock title={$i18n.t('contribute.rules.title')} maxWidth="900px">
            <div class="rules-grid">
                {#each rules as r}
                    <IconCard
                        icon={r.icon}
                        title={$i18n.t(r.titleKey)}
                        text={$i18n.t(r.textKey)}
                    />
                {/each}
            </div>
        </SectionBlock>

        <SectionBlock kind="cta" maxWidth="700px">
            <h2>{$i18n.t('contribute.cta.title')}</h2>
            <p>{$i18n.t('contribute.cta.text')}</p>
            <div class="cta-buttons">
                <a href="https://discord.com/invite/6SwTfXBx4h" target="_blank" rel="noopener" class="vff-btn on-cta">
                    <i class="bi bi-discord"></i> {$i18n.t('contribute.cta.discord')}
                </a>
                <a href="https://github.com/vff-project" target="_blank" rel="noopener" class="vff-btn ghost-on-cta">
                    <i class="bi bi-github"></i> {$i18n.t('contribute.cta.github')}
                </a>
            </div>
        </SectionBlock>
    </main>
</Layout>

<style>
    .ways-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 24px;
    }
    .rules-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 20px;
    }
    .steps {
        list-style: none;
        padding: 0;
        margin: 0;
        display: flex;
        flex-direction: column;
        gap: 32px;
    }
    .steps li {
        display: flex;
        align-items: flex-start;
        gap: 24px;
    }
    .step-num {
        flex: 0 0 56px;
        width: 56px;
        height: 56px;
        background-color: var(--vff-accent);
        color: var(--vff-text-inverse);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-family: var(--vff-font-display);
        font-size: 24px;
        font-weight: 600;
    }
    .steps h3 {
        margin: 0 0 4px;
        color: var(--vff-text);
    }
    .steps p {
        margin: 0;
        font-size: 17px;
        line-height: 1.55;
        color: var(--vff-text-muted);
    }
    .cta-buttons {
        display: flex;
        justify-content: center;
        gap: 16px;
        flex-wrap: wrap;
        margin-top: 24px;
    }
    /* `.vff-btn` est défini globalement dans general.css. */
</style>

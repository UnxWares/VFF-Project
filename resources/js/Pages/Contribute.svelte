<script>
    import Layout from '$components/Layout.svelte';
    import PageHero from '$components/PageHero.svelte';
    import IconCard from '$components/IconCard.svelte';
    import SectionBlock from '$components/SectionBlock.svelte';
    import { i18n } from '$lib/i18n.js';
    import { Link, page } from '@inertiajs/svelte';

    const ways = [
        {
            titleKey: 'contribute.ways.data_title',
            textKey: 'contribute.ways.data_text',
            statusKey: 'contribute.ways.data_status',
        },
        {
            titleKey: 'contribute.ways.media_title',
            textKey: 'contribute.ways.media_text',
            statusKey: 'contribute.ways.media_status',
        },
        {
            titleKey: 'contribute.ways.code_title',
            textKey: 'contribute.ways.code_text',
            statusKey: 'contribute.ways.code_status',
        },
    ];

    const steps = [
        { titleKey: 'contribute.process.step1_title', textKey: 'contribute.process.step1_text' },
        { titleKey: 'contribute.process.step2_title', textKey: 'contribute.process.step2_text' },
        { titleKey: 'contribute.process.step3_title', textKey: 'contribute.process.step3_text' },
    ];

    const rules = [
        {
            titleKey: 'contribute.rules.sources_title',
            textKey: 'contribute.rules.sources_text',
        },
        {
            titleKey: 'contribute.rules.license_title',
            textKey: 'contribute.rules.license_text',
            link: true,
        },
        {
            titleKey: 'contribute.rules.respect_title',
            textKey: 'contribute.rules.respect_text',
        },
    ];

    const signedIn = $derived(Boolean(page.props.auth?.user));
</script>

<svelte:head>
    <title>{$i18n.t('contribute.meta_title')} - VFF</title>
    <meta name="description" content={$i18n.t('contribute.meta_description')} />
</svelte:head>

<Layout compactHeader>
    <main>
        <PageHero
            title={$i18n.t('contribute.hero.title')}
            subtitle={$i18n.t('contribute.hero.subtitle')}
        />

        <SectionBlock title={$i18n.t('contribute.ways.title')} maxWidth="1180px">
            <ul class="vff-points">
                {#each ways as w (w.titleKey)}
                    <li>
                        <IconCard
                            title={$i18n.t(w.titleKey)}
                            text={$i18n.t(w.textKey)}
                            status={$i18n.t(w.statusKey)}
                        />
                    </li>
                {/each}
            </ul>
        </SectionBlock>

        <SectionBlock kind="alt" title={$i18n.t('contribute.process.title')} maxWidth="760px">
            <ol class="steps">
                {#each steps as s, i (s.titleKey)}
                    <li>
                        <span class="step-num" aria-hidden="true">{i + 1}</span>
                        <div>
                            <h3>{$i18n.t(s.titleKey)}</h3>
                            <p>{$i18n.t(s.textKey)}</p>
                        </div>
                    </li>
                {/each}
            </ol>
        </SectionBlock>

        <SectionBlock title={$i18n.t('contribute.rules.title')} maxWidth="1180px">
            <ul class="vff-points">
                {#each rules as r (r.titleKey)}
                    <li>
                        <IconCard title={$i18n.t(r.titleKey)} text={$i18n.t(r.textKey)}>
                            {#if r.link}
                                <Link href="/use-conditions" class="vff-link">
                                    {$i18n.t('contribute.rules.license_link')}
                                </Link>
                            {/if}
                        </IconCard>
                    </li>
                {/each}
            </ul>
        </SectionBlock>

        <SectionBlock
            kind="cta"
            title={$i18n.t('contribute.cta.title')}
            intro={$i18n.t('contribute.cta.text')}
        >
            {#snippet actions()}
                {#if signedIn}
                    <Link href="/contribute/edit-segment" class="vff-btn primary">
                        <i class="bi bi-pencil-square" aria-hidden="true"></i>
                        {$i18n.t('contribute.cta.edit_segment')}
                    </Link>
                {:else}
                    <Link href="/register" class="vff-btn primary">
                        <i class="bi bi-person-plus" aria-hidden="true"></i>
                        {$i18n.t('contribute.cta.register')}
                    </Link>
                {/if}
                <a
                    href="https://discord.com/invite/6SwTfXBx4h"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="vff-btn secondary"
                >
                    <i class="bi bi-discord" aria-hidden="true"></i>
                    {$i18n.t('contribute.cta.discord')}<span class="vff-sr-only">
                        ({$i18n.t('common.new_tab')})</span
                    >
                </a>
                <a
                    href="https://github.com/UnxWares/VFF-Project"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="vff-btn secondary"
                >
                    <i class="bi bi-github" aria-hidden="true"></i>
                    {$i18n.t('contribute.cta.github')}<span class="vff-sr-only">
                        ({$i18n.t('common.new_tab')})</span
                    >
                </a>
            {/snippet}
        </SectionBlock>
    </main>
</Layout>

<style>
    /* Étapes : une vraie suite, donc numérotée. Même filet que les points ci-dessus. */
    .steps {
        display: grid;
        gap: var(--vff-space-5);
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .steps li {
        display: grid;
        grid-template-columns: 2.5rem minmax(0, 1fr);
        column-gap: var(--vff-space-4);
        padding-top: 14px;
        border-top: 1px solid var(--vff-border-strong);
    }
    .step-num {
        font-family: var(--vff-font-display);
        font-size: var(--vff-fs-h2);
        font-weight: 700;
        line-height: 1.1;
        font-variant-numeric: tabular-nums;
        color: var(--vff-accent-ink);
    }
    .steps h3 {
        margin: 0 0 var(--vff-space-1);
    }
    .steps p {
        max-width: var(--vff-measure);
        margin: 0;
        font-size: var(--vff-fs-body);
        line-height: 1.6;
        color: var(--vff-text-muted);
    }
    @media (max-width: 600px) {
        .steps li {
            grid-template-columns: 2rem minmax(0, 1fr);
            column-gap: var(--vff-space-3);
        }
    }
</style>

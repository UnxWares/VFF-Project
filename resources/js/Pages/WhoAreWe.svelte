<script>
    import Layout from '$components/Layout.svelte';
    import PageHero from '$components/PageHero.svelte';
    import IconCard from '$components/IconCard.svelte';
    import SectionBlock from '$components/SectionBlock.svelte';
    import { i18n } from '$lib/i18n.js';
    import { Link } from '@inertiajs/svelte';

    const values = [
        {
            icon: 'bi-unlock',
            titleKey: 'whoarewe.values.open_title',
            textKey: 'whoarewe.values.open_text',
        },
        {
            icon: 'bi-shield-check',
            titleKey: 'whoarewe.values.rigorous_title',
            textKey: 'whoarewe.values.rigorous_text',
        },
        {
            icon: 'bi-people',
            titleKey: 'whoarewe.values.collective_title',
            textKey: 'whoarewe.values.collective_text',
        },
        {
            icon: 'bi-hourglass-split',
            titleKey: 'whoarewe.values.durable_title',
            textKey: 'whoarewe.values.durable_text',
        },
    ];

    // `now` : jalon atteint le plus récent (point plein) ; `goal` : objectif, pas encore atteint (point creux).
    const milestones = [
        { dateKey: 'whoarewe.milestones.item1_date', textKey: 'whoarewe.milestones.item1_text' },
        { dateKey: 'whoarewe.milestones.item2_date', textKey: 'whoarewe.milestones.item2_text' },
        {
            dateKey: 'whoarewe.milestones.item3_date',
            textKey: 'whoarewe.milestones.item3_text',
            now: true,
        },
        {
            dateKey: 'whoarewe.milestones.item4_date',
            textKey: 'whoarewe.milestones.item4_text',
            goal: true,
        },
    ];
</script>

<svelte:head>
    <title>{$i18n.t('whoarewe.meta_title')} - VFF</title>
    <meta name="description" content={$i18n.t('whoarewe.meta_description')} />
</svelte:head>

<Layout compactHeader>
    <main>
        <PageHero
            eyebrow={$i18n.t('whoarewe.hero.eyebrow')}
            title={$i18n.t('whoarewe.hero.title')}
            subtitle={$i18n.t('whoarewe.hero.subtitle')}
        />

        <div class="vff-section">
            <div class="vff-container story">
                <div>
                    <h2 class="vff-section-title">
                        {$i18n.t('whoarewe.genesis.title')}
                    </h2>
                    <div class="vff-prose">
                        <p>{$i18n.t('whoarewe.genesis.paragraph')}</p>
                    </div>
                </div>
                <div>
                    <h2 class="vff-section-title">
                        {$i18n.t('whoarewe.vision.title')}
                    </h2>
                    <div class="vff-prose">
                        <p>{$i18n.t('whoarewe.vision.paragraph')}</p>
                    </div>
                </div>
            </div>
        </div>

        <SectionBlock kind="alt" title={$i18n.t('whoarewe.values.title')} maxWidth="1180px">
            <div class="values-grid">
                {#each values as v (v.titleKey)}
                    <IconCard icon={v.icon} title={$i18n.t(v.titleKey)} text={$i18n.t(v.textKey)} />
                {/each}
            </div>
        </SectionBlock>

        <SectionBlock title={$i18n.t('whoarewe.milestones.title')} maxWidth="760px">
            <ol class="timeline">
                {#each milestones as m (m.dateKey)}
                    <li class:now={m.now} class:goal={m.goal}>
                        <span class="date">{$i18n.t(m.dateKey)}</span>
                        <span class="rail" aria-hidden="true"></span>
                        <span class="text">{$i18n.t(m.textKey)}</span>
                    </li>
                {/each}
            </ol>
        </SectionBlock>

        <SectionBlock
            kind="cta"
            title={$i18n.t('whoarewe.cta.title')}
            intro={$i18n.t('whoarewe.cta.text')}
        >
            {#snippet actions()}
                <Link href="/contribute" class="vff-btn primary">
                    <i class="bi bi-geo" aria-hidden="true"></i>
                    {$i18n.t('whoarewe.cta.contribute')}
                </Link>
                <Link href="/donate" class="vff-btn secondary">
                    <i class="bi bi-heart" aria-hidden="true"></i>
                    {$i18n.t('whoarewe.cta.donate')}
                </Link>
            {/snippet}
        </SectionBlock>
    </main>
</Layout>

<style>
    /* Deux colonnes de texte (genèse, objectif) : chacune à la largeur de lecture, bord gauche commun. */
    .story {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: var(--vff-block-space) 64px;
    }
    .values-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 24px 32px;
    }

    /* Frise des jalons : date, rail, texte. Le trait relie chaque point au suivant et s'arrête au dernier. */
    .timeline {
        list-style: none;
        margin: 0;
        padding: 0;
    }
    .timeline li {
        display: grid;
        grid-template-columns: 4.25rem 20px minmax(0, 1fr);
        column-gap: 16px;
        padding-bottom: var(--vff-space-5);
    }
    .timeline li:last-child {
        padding-bottom: 0;
    }
    .date {
        font-family: var(--vff-font-display);
        font-size: var(--vff-fs-h3);
        font-weight: 700;
        line-height: 1.35;
        text-align: right;
        font-variant-numeric: tabular-nums;
        color: var(--vff-text);
    }
    .rail {
        position: relative;
    }
    .rail::before {
        content: '';
        position: absolute;
        top: 7px;
        left: 3px;
        width: 14px;
        height: 14px;
        box-sizing: border-box;
        border: 3px solid var(--vff-border-control);
        border-radius: 50%;
        background-color: var(--vff-border-control);
    }
    .rail::after {
        content: '';
        position: absolute;
        top: 24px;
        bottom: calc(-1 * (var(--vff-space-5) + 7px));
        left: calc(50% - 1px);
        width: 2px;
        background-color: var(--vff-border-strong);
    }
    li:last-child .rail::after {
        display: none;
    }
    li.now .rail::before {
        border-color: var(--vff-accent);
        background-color: var(--vff-accent);
    }
    li.goal .rail::before {
        background-color: var(--vff-bg);
    }
    .text {
        max-width: var(--vff-measure);
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-body);
        line-height: 1.55;
        color: var(--vff-text);
    }

    @media (max-width: 900px) {
        .story {
            grid-template-columns: minmax(0, 1fr);
        }
    }
    @media (max-width: 600px) {
        .timeline li {
            grid-template-columns: 3.25rem 20px minmax(0, 1fr);
            column-gap: 12px;
        }
        .date {
            font-size: var(--vff-fs-md);
            line-height: 1.6;
        }
        .rail::before {
            top: 8px;
        }
        .rail::after {
            top: 25px;
        }
    }
</style>

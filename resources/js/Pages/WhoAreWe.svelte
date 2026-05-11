<script>
    import Layout from '$components/Layout.svelte';
    import PageHero from '$components/PageHero.svelte';
    import IconCard from '$components/IconCard.svelte';
    import SectionBlock from '$components/SectionBlock.svelte';
    import { i18n } from '$lib/i18n.js';
    import { scrollReveal } from '$lib/actions/scrollReveal.js';
    import { Link } from '@inertiajs/svelte';

    const values = [
        { icon: 'bi-unlock', titleKey: 'whoarewe.values.open_title', textKey: 'whoarewe.values.open_text' },
        { icon: 'bi-shield-check', titleKey: 'whoarewe.values.rigorous_title', textKey: 'whoarewe.values.rigorous_text' },
        { icon: 'bi-people', titleKey: 'whoarewe.values.collective_title', textKey: 'whoarewe.values.collective_text' },
        { icon: 'bi-hourglass-split', titleKey: 'whoarewe.values.durable_title', textKey: 'whoarewe.values.durable_text' },
    ];

    const milestones = [
        { dateKey: 'whoarewe.milestones.item1_date', textKey: 'whoarewe.milestones.item1_text' },
        { dateKey: 'whoarewe.milestones.item2_date', textKey: 'whoarewe.milestones.item2_text' },
        { dateKey: 'whoarewe.milestones.item3_date', textKey: 'whoarewe.milestones.item3_text', current: true },
        { dateKey: 'whoarewe.milestones.item4_date', textKey: 'whoarewe.milestones.item4_text' },
        { dateKey: 'whoarewe.milestones.item5_date', textKey: 'whoarewe.milestones.item5_text' },
    ];
</script>

<svelte:head>
    <title>{$i18n.t('whoarewe.meta_title')} — VFF</title>
    <meta name="description" content={$i18n.t('whoarewe.meta_description')} />
</svelte:head>

<Layout compactHeader>
    <main>
        <PageHero
            eyebrow={$i18n.t('whoarewe.hero.eyebrow')}
            title={$i18n.t('whoarewe.hero.title')}
            subtitle={$i18n.t('whoarewe.hero.subtitle')}
        />

        <SectionBlock title={$i18n.t('whoarewe.genesis.title')} maxWidth="720px">
            <p>{$i18n.t('whoarewe.genesis.paragraph')}</p>
        </SectionBlock>

        <SectionBlock kind="alt" title={$i18n.t('whoarewe.vision.title')} maxWidth="720px">
            <p>{$i18n.t('whoarewe.vision.paragraph')}</p>
        </SectionBlock>

        <SectionBlock title={$i18n.t('whoarewe.values.title')} maxWidth="1100px">
            <div class="values-grid" use:scrollReveal={{ threshold: 0.2 }}>
                {#each values as v}
                    <IconCard
                        icon={v.icon}
                        title={$i18n.t(v.titleKey)}
                        text={$i18n.t(v.textKey)}
                    />
                {/each}
            </div>
        </SectionBlock>

        <SectionBlock kind="alt" title={$i18n.t('whoarewe.milestones.title')} maxWidth="780px">
            <ol class="timeline">
                {#each milestones as m}
                    <li class:current={m.current}>
                        <span class="date">{$i18n.t(m.dateKey)}</span>
                        <span class="text">{$i18n.t(m.textKey)}</span>
                    </li>
                {/each}
            </ol>
        </SectionBlock>

        <SectionBlock kind="cta" maxWidth="640px">
            <h2>{$i18n.t('whoarewe.cta.title')}</h2>
            <p>{$i18n.t('whoarewe.cta.text')}</p>
            <div class="cta-buttons">
                <Link href="/contribute" class="vff-btn on-cta">
                    <i class="bi bi-geo"></i> {$i18n.t('whoarewe.cta.contribute')}
                </Link>
                <Link href="/donate" class="vff-btn ghost-on-cta">
                    <i class="bi bi-balloon-heart"></i> {$i18n.t('whoarewe.cta.donate')}
                </Link>
            </div>
        </SectionBlock>
    </main>
</Layout>

<style>
    p {
        font-size: 18px;
        line-height: 1.7;
        text-align: left;
        color: var(--vff-text);
    }
    h2 {
        margin: 0 0 16px;
    }
    .values-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 20px;
    }
    .timeline {
        list-style: none;
        padding: 0;
        margin: 0;
        position: relative;
    }
    .timeline::before {
        content: '';
        position: absolute;
        left: 80px;
        /* La barre verticale s'arrête au centre du premier et du dernier dot
           (li padding-top 16 + dot top 24 + dot demi-hauteur 8 = 40px du haut).
           Évite que des bouts de ligne flottent au-dessus / en-dessous. */
        top: 40px;
        bottom: 40px;
        width: 2px;
        background-color: var(--vff-border-strong);
    }
    .timeline li {
        position: relative;
        display: flex;
        gap: 24px;
        padding: 16px 0;
        align-items: flex-start;
    }
    .timeline li::before {
        content: '';
        position: absolute;
        left: 73px;
        top: 24px;
        width: 16px;
        height: 16px;
        border-radius: 50%;
        background-color: var(--vff-bg);
        border: 3px solid var(--vff-border-strong);
        z-index: 1;
    }
    .timeline li.current::before {
        background-color: var(--vff-accent);
        border-color: var(--vff-accent);
        box-shadow: 0 0 0 4px var(--vff-accent-soft);
    }
    .timeline .date {
        flex: 0 0 64px;
        font-family: var(--vff-font-display);
        font-size: 18px;
        font-weight: 600;
        color: var(--vff-accent);
        text-align: right;
    }
    .timeline .text {
        flex: 1;
        padding-left: 32px;
        font-size: 17px;
        line-height: 1.5;
        color: var(--vff-text);
    }
    .cta-buttons {
        display: flex;
        justify-content: center;
        gap: 16px;
        flex-wrap: wrap;
        margin-top: 24px;
    }
    /* `.vff-btn` est défini globalement dans general.css — on n'ajoute rien ici
       pour éviter les conflits de scope avec les <Link> Inertia. */

    @media (max-width: 600px) {
        p {
            text-align: left;
        }
        .timeline::before {
            left: 16px;
        }
        .timeline li::before {
            left: 9px;
        }
        .timeline .date {
            flex: 0 0 auto;
            text-align: left;
            padding-left: 40px;
        }
        .timeline .text {
            padding-left: 40px;
        }
    }
</style>

<script>
    import Layout from '$components/Layout.svelte';
    import PageHero from '$components/PageHero.svelte';
    import IconCard from '$components/IconCard.svelte';
    import SectionBlock from '$components/SectionBlock.svelte';
    import { i18n } from '$lib/i18n.js';
    import { Link } from '@inertiajs/svelte';

    const why = [
        { icon: 'bi-shield-slash', titleKey: 'donate.why.item1_title', textKey: 'donate.why.item1_text' },
        { icon: 'bi-server', titleKey: 'donate.why.item2_title', textKey: 'donate.why.item2_text' },
        { icon: 'bi-clock', titleKey: 'donate.why.item3_title', textKey: 'donate.why.item3_text' },
    ];

    const tiers = [
        {
            amountKey: 'donate.tiers.tier1_amount',
            labelKey: 'donate.tiers.tier1_label',
            textKey: 'donate.tiers.tier1_text',
            highlight: false,
        },
        {
            amountKey: 'donate.tiers.tier2_amount',
            labelKey: 'donate.tiers.tier2_label',
            textKey: 'donate.tiers.tier2_text',
            highlight: true,
        },
        {
            amountKey: 'donate.tiers.tier3_amount',
            labelKey: 'donate.tiers.tier3_label',
            textKey: 'donate.tiers.tier3_text',
            highlight: false,
        },
    ];

    const methods = [
        { icon: 'bi-stars', textKey: 'donate.methods.patreon' },
        { icon: 'bi-suit-heart', textKey: 'donate.methods.helloasso' },
    ];
</script>

<svelte:head>
    <title>{$i18n.t('donate.meta_title')} — VFF</title>
    <meta name="description" content={$i18n.t('donate.meta_description')} />
</svelte:head>

<Layout compactHeader>
    <main>
        <PageHero
            eyebrow={$i18n.t('donate.hero.eyebrow')}
            title={$i18n.t('donate.hero.title')}
            subtitle={$i18n.t('donate.hero.subtitle')}
        />

        <SectionBlock title={$i18n.t('donate.why.title')} maxWidth="1100px">
            <div class="why-grid">
                {#each why as w}
                    <IconCard
                        icon={w.icon}
                        title={$i18n.t(w.titleKey)}
                        text={$i18n.t(w.textKey)}
                    />
                {/each}
            </div>
        </SectionBlock>

        <SectionBlock kind="alt" title={$i18n.t('donate.tiers.title')} maxWidth="1100px">
            <div class="tiers-grid">
                {#each tiers as t}
                    <div class="tier" class:highlight={t.highlight}>
                        {#if t.highlight}<span class="badge">★</span>{/if}
                        <span class="amount">{$i18n.t(t.amountKey)}</span>
                        <span class="label">{$i18n.t(t.labelKey)}</span>
                        <p>{$i18n.t(t.textKey)}</p>
                        <button type="button" class="tier-cta" disabled aria-label={$i18n.t('common.soon')}>
                            <i class="bi bi-hourglass-split"></i> {$i18n.t('common.soon')}
                        </button>
                    </div>
                {/each}
                <div class="tier custom">
                    <span class="amount">€</span>
                    <span class="label">{$i18n.t('donate.tiers.tier_custom_label')}</span>
                    <p>{$i18n.t('donate.tiers.tier_custom_text')}</p>
                    <button type="button" class="tier-cta" disabled>
                        <i class="bi bi-hourglass-split"></i> {$i18n.t('common.soon')}
                    </button>
                </div>
            </div>
        </SectionBlock>

        <SectionBlock title={$i18n.t('donate.methods.title')} maxWidth="700px">
            <ul class="methods">
                {#each methods as m}
                    <li>
                        <i class="bi {m.icon}"></i>
                        <span>{$i18n.t(m.textKey)}</span>
                        <span class="soon">{$i18n.t('donate.methods.soon')}</span>
                    </li>
                {/each}
            </ul>
        </SectionBlock>

        <SectionBlock kind="alt" title={$i18n.t('donate.transparency.title')} maxWidth="720px">
            <p class="transparency-text">{$i18n.t('donate.transparency.paragraph')}</p>
        </SectionBlock>

        <SectionBlock kind="cta" maxWidth="640px">
            <h2>{$i18n.t('donate.cta.title')}</h2>
            <p>{$i18n.t('donate.cta.text')}</p>
            <Link href="/contribute" class="vff-btn on-cta">
                <i class="bi bi-geo"></i> {$i18n.t('donate.cta.contribute')}
            </Link>
        </SectionBlock>
    </main>
</Layout>

<style>
    .why-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: 24px;
    }
    .tiers-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 20px;
    }
    .tier {
        position: relative;
        background-color: var(--vff-bg);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        padding: 28px 24px;
        text-align: center;
        display: flex;
        flex-direction: column;
        gap: 8px;
        transition: transform var(--vff-transition), border-color var(--vff-transition);
    }
    .tier:hover {
        transform: translateY(-4px);
        border-color: var(--vff-accent);
    }
    .tier.highlight {
        border-color: var(--vff-accent);
        box-shadow: 0 12px 32px var(--vff-shadow-strong);
    }
    .tier .badge {
        position: absolute;
        top: -12px;
        right: 16px;
        background-color: var(--vff-accent);
        color: var(--vff-text-inverse);
        width: 28px;
        height: 28px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        font-weight: 600;
    }
    .tier .amount {
        font-family: var(--vff-font-display);
        font-size: 40px;
        font-weight: 600;
        color: var(--vff-accent);
        line-height: 1;
    }
    .tier .label {
        font-family: var(--vff-font-display);
        font-size: 18px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--vff-text);
    }
    .tier p {
        font-size: 15px;
        color: var(--vff-text-muted);
        margin: 8px 0;
        flex: 1;
    }
    .tier-cta {
        margin-top: auto;
        padding: 10px 20px;
        background-color: var(--vff-bg-muted);
        color: var(--vff-text-muted);
        border-radius: var(--vff-radius-md);
        font-family: var(--vff-font-display);
        font-size: 13px;
        cursor: not-allowed;
        opacity: 0.7;
    }
    .tier.custom .amount {
        opacity: 0.7;
    }
    .methods {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    .methods li {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 16px 20px;
        background-color: var(--vff-bg-elevated);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        margin-bottom: 12px;
    }
    .methods li i {
        font-size: 22px;
        color: var(--vff-accent);
    }
    .methods li span {
        flex: 1;
        color: var(--vff-text);
    }
    .methods li .soon {
        font-size: 12px;
        font-family: var(--vff-font-display);
        text-transform: uppercase;
        background-color: var(--vff-accent-soft);
        color: var(--vff-accent);
        padding: 4px 10px;
        border-radius: var(--vff-radius-pill);
        flex: 0 0 auto;
    }
    .transparency-text,
    .org-text {
        font-size: 17px;
        line-height: 1.6;
        color: var(--vff-text);
        text-align: justify;
    }
    .org-text :global(strong) {
        color: var(--vff-accent);
        font-weight: 600;
    }
    /* `.vff-btn` est défini globalement dans general.css. */
    @media (max-width: 600px) {
        .transparency-text {
            text-align: left;
        }
    }
</style>

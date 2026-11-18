<script>
    import Layout from '$components/Layout.svelte';
    import PageHero from '$components/PageHero.svelte';
    import IconCard from '$components/IconCard.svelte';
    import SectionBlock from '$components/SectionBlock.svelte';
    import { i18n } from '$lib/i18n.js';
    import { Link } from '@inertiajs/svelte';

    const why = [
        { icon: 'bi-server', titleKey: 'donate.why.item1_title', textKey: 'donate.why.item1_text' },
        { icon: 'bi-map', titleKey: 'donate.why.item2_title', textKey: 'donate.why.item2_text' },
        { icon: 'bi-clock', titleKey: 'donate.why.item3_title', textKey: 'donate.why.item3_text' },
    ];
</script>

<svelte:head>
    <title>{$i18n.t('donate.meta_title')} - VFF</title>
    <meta name="description" content={$i18n.t('donate.meta_description')} />
</svelte:head>

<Layout compactHeader>
    <main>
        <PageHero
            eyebrow={$i18n.t('donate.hero.eyebrow')}
            title={$i18n.t('donate.hero.title')}
            subtitle={$i18n.t('donate.hero.subtitle')}
        />

        <SectionBlock title={$i18n.t('donate.status.title')} maxWidth="720px">
            <div class="vff-prose">
                <p>{$i18n.t('donate.status.text')}</p>
                <h3>{$i18n.t('donate.org.title')}</h3>
                <!-- eslint-disable-next-line svelte/no-at-html-tags -->
                <p>{@html $i18n.t('donate.org.paragraph')}</p>
            </div>
        </SectionBlock>

        <SectionBlock
            kind="alt"
            title={$i18n.t('donate.why.title')}
            intro={$i18n.t('donate.why.intro')}
            maxWidth="1180px"
        >
            <div class="why-grid">
                {#each why as w (w.titleKey)}
                    <IconCard icon={w.icon} title={$i18n.t(w.titleKey)} text={$i18n.t(w.textKey)} />
                {/each}
            </div>
        </SectionBlock>

        <SectionBlock title={$i18n.t('donate.transparency.title')} maxWidth="720px">
            <div class="vff-prose">
                <p>{$i18n.t('donate.transparency.paragraph')}</p>
            </div>
        </SectionBlock>

        <SectionBlock
            kind="cta"
            title={$i18n.t('donate.cta.title')}
            intro={$i18n.t('donate.cta.text')}
        >
            {#snippet actions()}
                <Link href="/contribute" class="vff-btn primary">
                    <i class="bi bi-geo" aria-hidden="true"></i>
                    {$i18n.t('donate.cta.contribute')}
                </Link>
            {/snippet}
        </SectionBlock>
    </main>
</Layout>

<style>
    .why-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: 24px 32px;
    }
</style>

<script>
    import Layout from '$components/Layout.svelte';
    import PageHero from '$components/PageHero.svelte';
    import IconCard from '$components/IconCard.svelte';
    import SectionBlock from '$components/SectionBlock.svelte';
    import { i18n } from '$lib/i18n.js';
    import { Link } from '@inertiajs/svelte';

    const why = [
        { titleKey: 'donate.why.item1_title', textKey: 'donate.why.item1_text' },
        { titleKey: 'donate.why.item2_title', textKey: 'donate.why.item2_text' },
        { titleKey: 'donate.why.item3_title', textKey: 'donate.why.item3_text' },
    ];
</script>

<svelte:head>
    <title>{$i18n.t('donate.meta_title')} - VFF</title>
    <meta name="description" content={$i18n.t('donate.meta_description')} />
</svelte:head>

<Layout compactHeader>
    <main>
        <PageHero title={$i18n.t('donate.hero.title')} subtitle={$i18n.t('donate.hero.subtitle')} />

        <!-- Deux colonnes sur grand écran : l'état des dons à gauche, l'organisme à droite (pas de
             moitié de page vide). -->
        <SectionBlock title={$i18n.t('donate.status.title')} maxWidth="1180px">
            <div class="split">
                <div class="vff-prose">
                    <p>{$i18n.t('donate.status.text')}</p>
                </div>
                <div class="vff-prose aside">
                    <h3>{$i18n.t('donate.org.title')}</h3>
                    <!-- eslint-disable-next-line svelte/no-at-html-tags -->
                    <p>{@html $i18n.t('donate.org.paragraph')}</p>
                </div>
            </div>
        </SectionBlock>

        <SectionBlock
            kind="alt"
            title={$i18n.t('donate.why.title')}
            intro={$i18n.t('donate.why.intro')}
            maxWidth="1180px"
        >
            <div class="split wide-start">
                <ul class="vff-points">
                    {#each why as w (w.titleKey)}
                        <li>
                            <IconCard title={$i18n.t(w.titleKey)} text={$i18n.t(w.textKey)} />
                        </li>
                    {/each}
                </ul>
                <section class="vff-prose aside" aria-labelledby="transparency-title">
                    <h3 id="transparency-title">{$i18n.t('donate.transparency.title')}</h3>
                    <p>{$i18n.t('donate.transparency.paragraph')}</p>
                </section>
            </div>
        </SectionBlock>

        <SectionBlock
            kind="cta"
            title={$i18n.t('donate.cta.title')}
            intro={$i18n.t('donate.cta.text')}
        >
            {#snippet actions()}
                <Link href="/contribute" class="vff-btn primary">
                    {$i18n.t('donate.cta.contribute')}
                </Link>
            {/snippet}
        </SectionBlock>
    </main>
</Layout>

<style>
    .split {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: var(--vff-block-space) 64px;
        align-items: start;
    }
    .split.wide-start {
        grid-template-columns: minmax(0, 3fr) minmax(0, 2fr);
    }
    .aside h3 {
        margin: 0 0 var(--vff-space-3);
    }
    .aside p:last-child {
        margin-bottom: 0;
    }
    @media (max-width: 900px) {
        .split,
        .split.wide-start {
            grid-template-columns: minmax(0, 1fr);
        }
    }
</style>

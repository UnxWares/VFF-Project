<script>
    import Layout from '$components/Layout.svelte';
    import WhoAreWe from '$components/Sections/WhoAreWe.svelte';
    import Genesis from '$components/Sections/Genesis.svelte';
    import Maps from '$components/Sections/Maps.svelte';
    import Features from '$components/Sections/Features.svelte';
    import Timeline from '$components/Sections/Timeline.svelte';
    import ArchiveGallery from '$components/Sections/ArchiveGallery.svelte';
    import Stats from '$components/Sections/Stats.svelte';
    import ContributeTeaser from '$components/Sections/ContributeTeaser.svelte';
    import FinalCTA from '$components/Sections/FinalCTA.svelte';
    import { i18n } from '$lib/i18n.js';

    import '$css/index/index.css';
</script>

<svelte:head>
    <title>{$i18n.t('home.meta_title')}</title>
    <meta name="description" content={$i18n.t('home.meta_description')} />
</svelte:head>

<Layout>
    <main>
        <WhoAreWe />
        <Genesis />
        <Maps />
        <Features />
        <Timeline />
        <ArchiveGallery />
        <Stats />
        <ContributeTeaser />
        <FinalCTA />
    </main>
</Layout>

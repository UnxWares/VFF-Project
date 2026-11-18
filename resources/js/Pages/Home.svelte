<script>
    import Layout from '$components/Layout.svelte';
    import WhoAreWe from '$components/Sections/WhoAreWe.svelte';
    import Advocacy from '$components/Sections/Advocacy.svelte';
    import Maps from '$components/Sections/Maps.svelte';
    import Genesis from '$components/Sections/Genesis.svelte';
    import Timeline from '$components/Sections/Timeline.svelte';
    import ArchiveGallery from '$components/Sections/ArchiveGallery.svelte';
    import Features from '$components/Sections/Features.svelte';
    import ContributeTeaser from '$components/Sections/ContributeTeaser.svelte';
    import { i18n } from '$lib/i18n.js';

    import '$css/index/index.css';

    let {
        stats = null,
        threatened = [],
        profile = [],
        archives = [],
        mediaCount = null,
    } = $props();

    // Sept images au plus, rangées par année : la plus ancienne illustre la Genèse, les six autres
    // forment la galerie. Avec moins de sept images, toutes vont à la galerie.
    const genesisImage = $derived(archives.length > 6 ? archives[0] : null);
    const gallery = $derived(genesisImage ? archives.slice(1) : archives);
</script>

<svelte:head>
    <title>{$i18n.t('home.meta_title')}</title>
    <meta name="description" content={$i18n.t('home.meta_description')} />
</svelte:head>

<!-- Ordre : présentation, lignes soutenues, trois portes d'entrée (bande orange), genèse, profil du
     réseau par décennie (l'élément marquant de la page), images d'archives, sommaire, invitation. -->
<Layout>
    <main class="home">
        <WhoAreWe />
        <Advocacy lines={threatened} />
        <Maps />
        <Genesis image={genesisImage} />
        <Timeline {profile} />
        <ArchiveGallery archives={gallery} />
        <Features {stats} {mediaCount} />
        <ContributeTeaser />
    </main>
</Layout>

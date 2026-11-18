<script>
    /**
     * Images d'archives : images de Wikimedia Commons mises en avant dans la base (prop `archives` de
     * HomeController), rangées par année, chacune avec sa légende, sa ligne, son auteur, sa licence
     * et le lien vers sa page Commons (ArchiveFigure). Sans image (base vide), la section n'est pas
     * affichée : jamais de vignette factice.
     */
    import { Link } from '@inertiajs/svelte';
    import SectionBlock from '$components/SectionBlock.svelte';
    import ArchiveFigure from '$components/Sections/ArchiveFigure.svelte';
    import { i18n } from '$lib/i18n.js';

    /** @type {{ archives?: Array<any> }} */
    let { archives = [] } = $props();
</script>

{#if archives.length}
    <SectionBlock
        id="archives-images"
        title={$i18n.t('home.archives.title')}
        intro={$i18n.t('home.archives.intro')}
        maxWidth="1180px"
    >
        <ul class="gallery">
            {#each archives as m (m.id)}
                <li><ArchiveFigure media={m} /></li>
            {/each}
        </ul>

        {#snippet actions()}
            <Link href="/lines" class="vff-btn secondary">
                <i class="bi bi-list-ul" aria-hidden="true"></i>
                {$i18n.t('home.archives.cta')}
            </Link>
        {/snippet}
    </SectionBlock>
{/if}

<style>
    .gallery {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: var(--vff-space-7) var(--vff-space-6);
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .gallery li {
        min-width: 0;
        margin: 0;
    }
    @media (max-width: 900px) {
        .gallery {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
    @media (max-width: 600px) {
        .gallery {
            grid-template-columns: minmax(0, 1fr);
            gap: var(--vff-space-6);
        }
    }
</style>

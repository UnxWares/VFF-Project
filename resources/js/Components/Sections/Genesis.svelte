<script>
    /**
     * Pourquoi VFF existe : texte suivi, lettrine sur le premier paragraphe, signature. Sur
     * ordinateur, une image d'archives ancienne (la plus ancienne du tirage du jour, prop `image`)
     * fait face au texte ; sans image, le texte reste seul à sa largeur de lecture.
     */
    import SectionBlock from '$components/SectionBlock.svelte';
    import ArchiveFigure from '$components/Sections/ArchiveFigure.svelte';
    import { i18n } from '$lib/i18n.js';

    /** @type {{ image?: any }} */
    let { image = null } = $props();
</script>

<SectionBlock
    id="genese"
    title={$i18n.t('home.genesis.title')}
    maxWidth={image ? '1180px' : '720px'}
>
    <div class={['genesis', { 'with-image': image }]}>
        <div class="vff-prose text">
            <p class="opening">{$i18n.t('home.genesis.p1')}</p>
            <p>{$i18n.t('home.genesis.p2')}</p>
            <p class="signature">- {$i18n.t('home.genesis.signature')}</p>
        </div>
        {#if image}
            <div class="image">
                <ArchiveFigure media={image} sizes="(max-width: 900px) calc(100vw - 48px), 520px" />
            </div>
        {/if}
    </div>
</SectionBlock>

<style>
    .genesis.with-image {
        display: grid;
        grid-template-columns: minmax(0, 34.8em) minmax(0, 1fr);
        gap: var(--vff-space-6) var(--vff-space-8);
        align-items: start;
    }
    .text {
        font-size: 1.125rem;
    }
    .text .opening::first-letter {
        float: left;
        margin: 0.08em 0.12em 0 0;
        font-family: var(--vff-font-display);
        font-size: 3.6em;
        font-weight: 700;
        line-height: 0.8;
        color: var(--vff-accent-strong);
    }
    .text .signature {
        margin: var(--vff-space-5) 0 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        text-align: right;
        color: var(--vff-text-muted);
    }
    .image {
        min-width: 0;
        /* L'image s'aligne sur la première ligne du texte (la lettrine dépasse un peu). */
        padding-top: 6px;
    }
    @media (max-width: 900px) {
        .genesis.with-image {
            grid-template-columns: minmax(0, 1fr);
        }
        .image {
            max-width: 560px;
            padding-top: 0;
        }
    }
    @media (max-width: 600px) {
        .text {
            font-size: var(--vff-fs-body);
        }
    }
</style>

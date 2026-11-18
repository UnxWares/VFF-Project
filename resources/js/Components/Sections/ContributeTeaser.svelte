<script>
    /**
     * Invitation de fin d'accueil (la seule bande « cta » de la page) : trois façons de participer,
     * puis les actions (contribuer, don, Discord, GitHub). Remplace l'ancien couple « Rejoignez le
     * mouvement » + bandeau sombre « Embarquons ensemble ».
     */
    import { Link } from '@inertiajs/svelte';
    import SectionBlock from '$components/SectionBlock.svelte';
    import IconCard from '$components/IconCard.svelte';
    import { i18n } from '$lib/i18n.js';

    const ways = [
        { icon: 'bi-pencil-square', key: 'data' },
        { icon: 'bi-images', key: 'media' },
        { icon: 'bi-code-slash', key: 'code' },
    ];
</script>

<SectionBlock
    id="participer"
    kind="cta"
    title={$i18n.t('home.contribute.title')}
    intro={$i18n.t('home.contribute.subtitle')}
    maxWidth="1180px"
>
    <div class="ways">
        {#each ways as w (w.key)}
            <IconCard
                icon={w.icon}
                title={$i18n.t(`home.contribute.${w.key}_title`)}
                text={$i18n.t(`home.contribute.${w.key}_text`)}
            />
        {/each}
    </div>

    {#snippet actions()}
        <Link href="/contribute" class="vff-btn primary">
            <i class="bi bi-pencil-square" aria-hidden="true"></i>
            {$i18n.t('home.contribute.cta')}
        </Link>
        <Link href="/donate" class="vff-btn secondary">
            <i class="bi bi-balloon-heart" aria-hidden="true"></i>
            {$i18n.t('home.contribute.donate')}
        </Link>
        <a
            href="https://discord.com/invite/6SwTfXBx4h"
            target="_blank"
            rel="noopener noreferrer"
            class="vff-btn quiet"
        >
            <i class="bi bi-discord" aria-hidden="true"></i>
            {$i18n.t('home.contribute.discord')}<span class="vff-sr-only">
                ({$i18n.t('common.new_tab')})</span
            >
        </a>
        <a
            href="https://github.com/UnxWares/VFF-Project"
            target="_blank"
            rel="noopener noreferrer"
            class="vff-btn quiet"
        >
            <i class="bi bi-github" aria-hidden="true"></i>
            {$i18n.t('home.contribute.github')}<span class="vff-sr-only">
                ({$i18n.t('common.new_tab')})</span
            >
        </a>
    {/snippet}
</SectionBlock>

<style>
    .ways {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: var(--vff-space-5) var(--vff-space-6);
        /* À remonter dans SectionBlock : en bande cta, la règle `.cta p { margin: 0 }` annule la marge
           basse du chapeau (.intro), le contenu suivant s'y colle. */
        margin-top: var(--vff-block-space);
    }
    /* Les textes de la bande cta passent en couleur de texte (SectionBlock) : les phrases des points
       restent en gris secondaire, comme partout ailleurs. */
    :global(.section-block.cta) .ways :global(.icon-card p.text) {
        max-width: none;
        font-size: var(--vff-fs-md);
        color: var(--vff-text-muted);
    }
    @media (max-width: 600px) {
        .ways {
            grid-template-columns: minmax(0, 1fr);
        }
    }
</style>

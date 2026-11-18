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

    const ways = ['data', 'media', 'code'];
</script>

<SectionBlock
    id="participer"
    kind="cta"
    title={$i18n.t('home.contribute.title')}
    intro={$i18n.t('home.contribute.subtitle')}
    maxWidth="1180px"
>
    <ul class="vff-points ways">
        {#each ways as key (key)}
            <li>
                <IconCard
                    title={$i18n.t(`home.contribute.${key}_title`)}
                    text={$i18n.t(`home.contribute.${key}_text`)}
                />
            </li>
        {/each}
    </ul>

    {#snippet actions()}
        <Link href="/contribute" class="vff-btn primary">
            {$i18n.t('home.contribute.cta')}
        </Link>
        <Link href="/donate" class="vff-btn secondary">
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

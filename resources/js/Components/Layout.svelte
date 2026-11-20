<script>
    import Header from './Header.svelte';
    import Footer from './Footer.svelte';
    import OfflineCopyNotice from './OfflineCopyNotice.svelte';
    import { i18n } from '$lib/i18n.js';

    /**
     * Gabarit de toutes les pages : lien d'évitement, en-tête, contenu de la page (qui fournit son
     * <main> et son unique <h1>), pied de page. La zone #contenu reçoit le focus du lien d'évitement
     * et du bouton du héros de l'accueil ; elle commence par le signal de copie hors ligne (vide en ligne).
     */
    let { children, compactHeader = false } = $props();
</script>

<a class="vff-skip-link" href="#contenu">{$i18n.t('nav.skip_to_content')}</a>
<Header compact={compactHeader} />
<div id="contenu" class="vff-content" tabindex="-1">
    <OfflineCopyNotice />
    {@render children?.()}
</div>
<Footer />

<script>
    import { onDestroy } from 'svelte';
    import { i18n } from '$lib/i18n.js';
    import { settleOnSection } from '$lib/sheetToc.js';

    /**
     * Place des sections d'une fiche (ligne, gare) chargées juste après le premier affichage (props
     * différées d'Inertia, demandées par la page dès son montage) : un bloc sobre, à la largeur et aux
     * marges des sections (SheetSection). Il porte leurs ancres (`ids`) : le sommaire (SheetToc) les
     * montre et un lien #section y mène avant l'arrivée des données ; les sections remplacent le bloc
     * à leur arrivée, celles qui n'ont rien à montrer disparaissent comme avant. Masqué à l'impression
     * (`data-print="hide"`, print.css) : la demande part au montage de la page, bien avant qu'on imprime.
     */
    /** @type {{ ids: string[] }} */
    let { ids } = $props();

    // Arrivée par une adresse #section d'une section différée : le navigateur s'est posé sur l'ancre du bloc ;
    // une fois les sections reçues, la lecture reprend sur la vraie section (même calage que SheetToc).
    onDestroy(() => {
        let id = '';
        try {
            id = decodeURIComponent(window.location.hash.slice(1));
        } catch {
            return;
        }
        if (!ids.includes(id)) return;
        requestAnimationFrame(() => {
            if (document.getElementById(id)) settleOnSection(id);
        });
    });
</script>

<section
    class="sheet-pending"
    aria-label={$i18n.t('common.sheet_loading_label')}
    aria-busy="true"
    data-print="hide"
>
    {#each ids as id (id)}<span {id} class="anchor"></span>{/each}
    <p>{$i18n.t('common.sheet_loading')}</p>
</section>

<style>
    .sheet-pending {
        max-width: calc(var(--vff-container) + 2 * var(--vff-gutter));
        margin: 0 auto;
        padding: var(--vff-section-space) var(--vff-gutter) 0;
    }
    .anchor {
        display: block;
        scroll-margin-top: 80px;
    }
    p {
        display: flex;
        align-items: center;
        min-height: 8rem;
        margin: 0;
        padding: var(--vff-pad-box);
        border-radius: var(--vff-radius-md);
        background: var(--vff-bg-subtle);
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-md);
        line-height: var(--vff-lh-body);
        color: var(--vff-text-muted);
    }
</style>

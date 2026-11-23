<script>
    /**
     * Section « Exemplaires préservés » d'une fiche de série : un encart par exemplaire (numéro, lieu, état,
     * protection, sources) et un lien vers la page de tous les lieux. Données : prop `preserved` de
     * MaterialController::show (database/data/preserved_rolling_stock.php).
     */
    import { Link } from '@inertiajs/svelte';
    import SheetSection from '$components/Sheet/SheetSection.svelte';
    import PreservedPiece from '$components/Materials/PreservedPiece.svelte';
    import { i18n } from '$lib/i18n.js';

    /** @type {{ units: Array<any> }} */
    let { units } = $props();

    const t = (key, opts) => String($i18n.t(key, opts));
</script>

<SheetSection
    id="preserved-units"
    title={t('materials_preserved.section_title')}
    count={units.length}
    intro={t('materials_preserved.section_intro')}
>
    <ul class="pieces">
        {#each units as unit (unit.number)}
            <PreservedPiece piece={unit} />
        {/each}
    </ul>
    <p class="all">
        <Link href="/materials/preserved" class="vff-link"
            >{t('materials_preserved.section_link')}</Link
        >
    </p>
</SheetSection>

<style>
    .pieces {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(min(100%, 26rem), 1fr));
        gap: var(--vff-space-4);
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .all {
        margin: var(--vff-space-5) 0 0;
    }
</style>

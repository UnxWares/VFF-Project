<script>
    import { untrack } from 'svelte';
    import { i18n } from '$lib/i18n.js';
    import SheetSection from '$components/Sheet/SheetSection.svelte';
    import LineRinfFacts from '$components/Lines/LineRinfFacts.svelte';
    import { hasRinf } from '$lib/lineRinf.js';

    /**
     * Section « Registre de l'infrastructure (RINF) » de la fiche ligne (contenu : LineRinfFacts). Lue à la demande,
     * quand son emplacement approche de l'écran (GET /api/v1/lines/{line}/rinf-parameters) : la fiche ne fait aucune
     * requête de plus. Absente (ni titre ni entrée du sommaire) quand le registre ne décrit pas la ligne.
     *
     * @type {{ slug: string }}
     */
    let { slug } = $props();

    const t = (k, o) => String($i18n.t(k, o));

    /** Réponse lue pour une ligne (la page peut recevoir une autre ligne sans être recréée). */
    let loaded = $state.raw({ slug: '', status: 'idle', data: null });
    const status = $derived(loaded.slug === slug ? loaded.status : 'idle');
    const data = $derived(loaded.slug === slug ? loaded.data : null);
    /** @type {HTMLElement|undefined} */
    let sentinel = $state();

    async function load() {
        const target = slug;
        loaded = { slug: target, status: 'loading', data: null };
        try {
            const res = await fetch(`/api/v1/lines/${encodeURIComponent(target)}/rinf-parameters`, {
                headers: { Accept: 'application/json' },
            });
            if (!res.ok) throw new Error(String(res.status));
            const json = await res.json();
            if (target === slug) loaded = { slug: target, status: 'ready', data: json };
        } catch {
            if (target === slug) loaded = { slug: target, status: 'error', data: null };
        }
    }

    // Lue quand l'emplacement de la section approche de l'écran (600 px d'avance), une fois par ligne.
    $effect(() => {
        if (!sentinel || status !== 'idle') return;
        if (typeof IntersectionObserver === 'undefined') {
            untrack(load);
            return;
        }
        const io = new IntersectionObserver(
            (entries) => {
                if (entries.some((e) => e.isIntersecting)) {
                    io.disconnect();
                    load();
                }
            },
            { rootMargin: '600px 0px' },
        );
        io.observe(sentinel);
        return () => io.disconnect();
    });

    const shown = $derived(status === 'ready' && hasRinf(data));
</script>

{#if shown}
    <SheetSection id="rinf" title={t('rinf.title')}>
        <LineRinfFacts {data} />
    </SheetSection>
{:else if status === 'error'}
    <SheetSection id="rinf" title={t('rinf.title')}>
        <p class="state" role="alert">
            {t('rinf.error')}
            <button type="button" class="vff-btn secondary" onclick={load}>{t('rinf.retry')}</button
            >
        </p>
    </SheetSection>
{:else}
    <!-- Emplacement de la section : lu à l'approche ; rien n'est affiché si le registre ne décrit pas la ligne. -->
    <div class="sentinel" bind:this={sentinel} aria-hidden="true"></div>
{/if}

<style>
    .sentinel {
        height: 1px;
    }
    .state {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: var(--vff-space-3);
        margin: 0;
        color: var(--vff-text-muted);
    }
</style>

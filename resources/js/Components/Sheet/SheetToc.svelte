<script>
    import { onMount } from 'svelte';
    import { currentSection, revealScrollLeft } from '$lib/sheetToc.js';

    /**
     * Sommaire collant d'une fiche (ligne, gare), posé sous SheetHero : une rangée d'ancres dessinée
     * comme un schéma de ligne. Chaque section est un arrêt ; la section lue est l'arrêt plein, les
     * sections déjà passées ont le rail orange, les suivantes le rail gris. Il reste en haut de
     * l'écran pendant la lecture et suit le défilement (`aria-current` sur la section lue).
     *
     * `items` : [{ id, label }] dans l'ordre de la page ; seuls les arrêts dont la section existe
     * vraiment (élément #id présent) sont affichés, ce qui suit les sections conditionnelles
     * (photos chargées après coup, sections vides retirées). `label` : nom du sommaire.
     */
    /** @type {{ items: Array<{ id: string, label: string }>, label: string }} */
    let { items, label } = $props();

    // Identifiants des sections présentes (null avant le montage : tous les arrêts, sans saut).
    let present = $state(null);
    let current = $state(null);
    let navEl = $state();
    let listEl = $state();

    const shown = $derived(present ? items.filter((i) => present.includes(i.id)) : items);
    const currentIndex = $derived(shown.findIndex((i) => i.id === current));

    function measurePresence() {
        const ids = items.filter((i) => document.getElementById(i.id)).map((i) => i.id);
        if (!present || ids.join() !== present.join()) present = ids;
    }

    // Section lue : ligne de lecture à 100 px sous le sommaire (une ancre pose la section à 80 px du
    // haut : scroll-margin-top de SheetSection). Calcul dans lib/sheetToc.js.
    function measureCurrent() {
        /** @type {Array<{ id: string, top: number }>} */
        const sections = [];
        for (const item of shown) {
            const el = document.getElementById(item.id);
            if (el) sections.push({ id: item.id, top: el.getBoundingClientRect().top });
        }
        const found = currentSection(sections, {
            line: (navEl?.getBoundingClientRect().bottom ?? 0) + 100,
            atEnd: window.scrollY + window.innerHeight >= document.documentElement.scrollHeight - 4,
            viewportHeight: window.innerHeight,
        });
        if (found !== current) {
            current = found;
            requestAnimationFrame(revealCurrent);
        }
    }

    // L'arrêt courant reste visible dans la rangée quand elle défile (téléphone).
    function revealCurrent() {
        const link = listEl?.querySelector('[aria-current]');
        if (!link) return;
        const left = revealScrollLeft(
            { left: link.offsetLeft, width: link.offsetWidth },
            { scrollLeft: listEl.scrollLeft, clientWidth: listEl.clientWidth },
        );
        if (left === null) return;
        const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        listEl.scrollTo({ left, behavior: reduce ? 'auto' : 'smooth' });
    }

    // Une mesure par image au plus (défilement, redimensionnement, sections ajoutées).
    let frame = 0;
    function schedule() {
        if (frame) return;
        frame = requestAnimationFrame(() => {
            frame = 0;
            measurePresence();
            measureCurrent();
        });
    }

    onMount(() => {
        schedule();
        // Sections ajoutées ou retirées après coup (galerie chargée par l'API, repli d'une liste).
        const observer = new MutationObserver(schedule);
        observer.observe(navEl?.closest('main') ?? document.body, {
            childList: true,
            subtree: true,
        });
        return () => {
            cancelAnimationFrame(frame);
            observer.disconnect();
        };
    });
</script>

<svelte:window onscroll={schedule} onresize={schedule} />

{#if shown.length > 1}
    <nav class="sheet-toc" aria-label={label} bind:this={navEl}>
        <div class="inner">
            <ol bind:this={listEl}>
                {#each shown as item, i (item.id)}
                    <li>
                        <a
                            href="#{item.id}"
                            class={{ passed: currentIndex >= 0 && i < currentIndex }}
                            aria-current={item.id === current ? 'true' : undefined}>{item.label}</a
                        >
                    </li>
                {/each}
            </ol>
        </div>
    </nav>
{/if}

<style>
    .sheet-toc {
        position: sticky;
        top: 0;
        z-index: var(--vff-z-sticky);
        background-color: var(--vff-bg);
        border-bottom: 1px solid var(--vff-border);
    }
    .inner {
        max-width: calc(var(--vff-container) + 2 * var(--vff-gutter));
        margin: 0 auto;
        padding-inline: var(--vff-gutter);
    }
    ol {
        display: flex;
        margin: 0;
        padding: 0 0 2px;
        list-style: none;
        overflow-x: auto;
        overscroll-behavior-x: contain;
        scrollbar-width: thin;
    }
    li {
        flex: none;
        margin: 0;
    }
    /* Un arrêt : le libellé, puis le rail (filet) et l'arrêt (rond) dessous. */
    a {
        position: relative;
        display: block;
        min-height: 44px;
        padding: 12px 18px 22px 0;
        font-family: var(--vff-font-heading);
        font-size: 0.9375rem;
        line-height: 1.35;
        color: var(--vff-text-muted);
        white-space: nowrap;
        text-decoration: none;
        transition: color var(--vff-transition);
    }
    li:last-child a {
        padding-right: 4px;
    }
    a::before {
        content: '';
        position: absolute;
        right: 0;
        bottom: 9px;
        left: 0;
        height: 2px;
        background-color: var(--vff-border-strong);
    }
    li:last-child a::before {
        right: auto;
        width: 12px;
    }
    a::after {
        content: '';
        position: absolute;
        bottom: 5px;
        left: 0;
        box-sizing: border-box;
        width: 10px;
        height: 10px;
        border: 2px solid var(--vff-border-control);
        border-radius: 50%;
        background-color: var(--vff-bg);
    }
    a:hover {
        color: var(--vff-text);
        text-decoration: underline;
        text-underline-offset: 0.2em;
    }
    /* Sections passées : rail et arrêts orange (pleins). */
    a.passed::before {
        background-color: var(--vff-accent);
    }
    a.passed::after {
        border-color: var(--vff-accent);
        background-color: var(--vff-accent);
    }
    /* Section lue : libellé appuyé, arrêt plein et plus gros, cerclé d'encre. */
    a[aria-current='true'] {
        color: var(--vff-text);
        font-weight: 600;
    }
    a[aria-current='true']::after {
        bottom: 3px;
        left: -2px;
        width: 14px;
        height: 14px;
        border-color: var(--vff-accent-ink);
        background-color: var(--vff-accent);
    }
    @media (forced-colors: active) {
        a[aria-current='true'] {
            text-decoration: underline;
        }
    }
    @media (max-width: 600px) {
        a {
            padding-right: 16px;
        }
    }
</style>

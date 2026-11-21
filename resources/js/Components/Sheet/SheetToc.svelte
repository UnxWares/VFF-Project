<script>
    import { onMount } from 'svelte';
    import { currentSection, hiddenEdges, revealScrollLeft } from '$lib/sheetToc.js';

    /**
     * Sommaire collant d'une fiche (ligne, gare), posé sous SheetHero : une rangée d'ancres dessinée
     * comme un schéma de ligne. Chaque section est un arrêt ; la section lue est l'arrêt plein, les
     * sections déjà passées ont le rail orange, les suivantes le rail gris. Il reste en haut de
     * l'écran pendant la lecture et suit le défilement (`aria-current` sur la section lue). L'arrêt
     * est centré sous son libellé (jamais coupé au bord de la rangée) ; quand la rangée défile, un
     * fondu au bord indique les arrêts cachés de ce côté.
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
    // Arrêts cachés à gauche ou à droite de la rangée (fondus).
    let edges = $state({ before: false, after: false });

    function measureEdges() {
        if (!listEl) return;
        const next = hiddenEdges(listEl);
        if (next.before !== edges.before || next.after !== edges.after) edges = next;
    }

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
        // Position dans la rangée (pas offsetLeft : le parent positionné est le bandeau collant, qui
        // ajoute la gouttière et la marge de la page ; sur ordinateur, la rangée glissait au chargement
        // et coupait le premier arrêt).
        const offset =
            link.getBoundingClientRect().left -
            listEl.getBoundingClientRect().left +
            listEl.scrollLeft;
        const left = revealScrollLeft(
            { left: offset, width: link.offsetWidth },
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
            measureEdges();
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
            <ol
                bind:this={listEl}
                class={{ 'fade-before': edges.before, 'fade-after': edges.after }}
                onscroll={measureEdges}
            >
                {#each shown as item, i (item.id)}
                    <li>
                        <a
                            href="#{item.id}"
                            class={{ passed: currentIndex >= 0 && i < currentIndex }}
                            aria-current={item.id === current ? 'true' : undefined}
                            >{item.label}<span class="stop" aria-hidden="true"></span></a
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
    /* Fondus au bord quand des arrêts sont cachés de ce côté (rangée qui défile). */
    ol.fade-after {
        mask-image: linear-gradient(to right, #000 calc(100% - 40px), transparent);
    }
    ol.fade-before {
        mask-image: linear-gradient(to left, #000 calc(100% - 40px), transparent);
    }
    ol.fade-before.fade-after {
        mask-image: linear-gradient(
            to right,
            transparent,
            #000 40px,
            #000 calc(100% - 40px),
            transparent
        );
    }
    /* Un arrêt : le libellé, puis le rail (filet) et l'arrêt (rond) dessous, centré sous le
       libellé. L'écart entre deux libellés est porté par les marges des <li> (rien avant le premier
       ni après le dernier) ; chaque demi-rail déborde de --half jusqu'au milieu de l'écart. */
    li {
        --half: 9px;
        flex: none;
        margin: 0;
        padding-inline: var(--half);
    }
    li:first-child {
        padding-left: 0;
    }
    li:last-child {
        padding-right: 0;
    }
    a {
        position: relative;
        display: block;
        min-height: 44px;
        padding: 12px 0 22px;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: 1.35;
        color: var(--vff-text-muted);
        white-space: nowrap;
        text-decoration: none;
        transition: color var(--vff-transition);
    }
    /* Demi-rails : ::before arrive de l'arrêt précédent, ::after part vers le suivant. */
    a::before,
    a::after {
        content: '';
        position: absolute;
        bottom: 9px;
        height: 2px;
        background-color: var(--vff-border-strong);
    }
    a::before {
        right: 50%;
        left: calc(-1 * var(--half));
    }
    a::after {
        right: calc(-1 * var(--half));
        left: 50%;
    }
    li:first-child a::before,
    li:last-child a::after {
        content: none;
    }
    .stop {
        position: absolute;
        z-index: 1;
        bottom: 5px;
        left: 50%;
        box-sizing: border-box;
        width: 10px;
        height: 10px;
        border: 2px solid var(--vff-border-control);
        border-radius: 50%;
        background-color: var(--vff-bg);
        transform: translateX(-50%);
    }
    a:hover {
        color: var(--vff-text);
        text-decoration: underline;
        text-underline-offset: 0.2em;
    }
    /* Sections passées : rail et arrêts orange (pleins) jusqu'à la section lue. */
    a.passed::before,
    a.passed::after,
    a[aria-current='true']::before {
        background-color: var(--vff-accent);
    }
    a.passed .stop {
        border-color: var(--vff-accent);
        background-color: var(--vff-accent);
    }
    /* Section lue : libellé appuyé, arrêt plein et plus gros, cerclé d'encre. */
    a[aria-current='true'] {
        color: var(--vff-text);
        font-weight: 600;
    }
    a[aria-current='true'] .stop {
        bottom: 3px;
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
        li {
            --half: 8px;
        }
    }
</style>

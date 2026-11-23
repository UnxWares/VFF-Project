<script>
    import { i18n } from '$lib/i18n.js';
    import {
        CURTAIN_MAX,
        CURTAIN_MIN,
        CURTAIN_PAGE_STEP,
        CURTAIN_STEP,
        clampCurtain,
        tagFits,
    } from '$lib/eraCurtain.js';

    /**
     * Rideau posé sur la carte (vue « Comparer », présentation « Rideau ») : un trait vertical et sa
     * poignée, l'époque de gauche et celle de droite écrites de part et d'autre. Il se fait glisser à
     * la souris ou au doigt ; au clavier, c'est un curseur natif (flèches : 1 %, Page précédente et
     * Page suivante : 10 %, Début et Fin : bords), annoncé avec les deux époques.
     *
     * Props : value (bindable, pour cent de la largeur) ; left et right : libellés des deux époques
     * (« 1930 », « Aujourd'hui ») ; leftText et rightText : les mêmes dans une phrase (« aujourd'hui »),
     * lue avec la position ; obstacle : sélecteur d'un panneau posé à gauche de la carte (la légende),
     * que le libellé de gauche ne chevauche pas.
     */
    let {
        value = $bindable(50),
        left = '',
        right = '',
        leftText = left,
        rightText = right,
        obstacle = '.vff-legend',
    } = $props();

    const uid = $props.id();
    // Écart entre le trait et un libellé : moitié de la poignée et une marge.
    const TAG_GAP = 30;

    let frameEl = $state();
    let width = $state(0);
    let leftTagWidth = $state(0);
    let rightTagWidth = $state(0);
    let dragging = $state(false);

    // Bord droit (px depuis la gauche de la carte) du panneau posé à gauche, s'il descend jusqu'à la
    // hauteur des libellés ; 0 sinon. Suivi quand le panneau s'ouvre, se ferme ou change de taille.
    let obstacleRight = $state(0);

    $effect(() => {
        const panel = frameEl?.parentElement?.querySelector(obstacle);
        if (!panel || typeof ResizeObserver === 'undefined') return;
        const measure = () => {
            const frame = frameEl.getBoundingClientRect();
            const box = panel.getBoundingClientRect();
            const middle = frame.top + frame.height / 2;
            obstacleRight =
                box.width > 0 && box.top <= middle + 24 && box.bottom >= middle - 24
                    ? box.right - frame.left
                    : 0;
        };
        measure();
        const observer = new ResizeObserver(measure);
        observer.observe(panel);
        observer.observe(frameEl);
        return () => observer.disconnect();
    });

    const percent = $derived(clampCurtain(value));
    // Un libellé qui ne tient plus entre le trait et le bord (ou la légende) s'efface ; le curseur et
    // la légende gardent les deux époques.
    const showLeft = $derived(
        !width || tagFits('left', percent, width, leftTagWidth, TAG_GAP, obstacleRight),
    );
    const showRight = $derived(!width || tagFits('right', percent, width, rightTagWidth, TAG_GAP));

    function moveTo(clientX) {
        const box = frameEl.getBoundingClientRect();
        if (box.width > 0) value = clampCurtain(((clientX - box.left) / box.width) * 100);
    }

    function onPointerDown(event) {
        if (event.button !== 0) return;
        event.preventDefault();
        event.currentTarget.setPointerCapture(event.pointerId);
        dragging = true;
        moveTo(event.clientX);
    }

    function onPointerMove(event) {
        if (dragging) moveTo(event.clientX);
    }

    function stopDragging() {
        dragging = false;
    }

    // Page précédente / suivante : grand pas, quel que soit le navigateur.
    function onKeyDown(event) {
        if (event.key !== 'PageUp' && event.key !== 'PageDown') return;
        event.preventDefault();
        const step = event.key === 'PageUp' ? CURTAIN_PAGE_STEP : -CURTAIN_PAGE_STEP;
        value = clampCurtain(percent + step);
    }
</script>

<div
    class="era-curtain"
    class:dragging
    bind:this={frameEl}
    bind:clientWidth={width}
    style:--curtain={`${percent}%`}
>
    <!-- Trait et poignée : la souris et le doigt ; le clavier et les lecteurs d'écran passent par
         le curseur ci-dessous, dont la poignée montre le focus. -->
    <div
        class="grip"
        aria-hidden="true"
        onpointerdown={onPointerDown}
        onpointermove={onPointerMove}
        onpointerup={stopDragging}
        onpointercancel={stopDragging}
        onlostpointercapture={stopDragging}
    >
        <span class="rule"></span>
        <span class="knob"><i class="bi bi-arrow-left-right"></i></span>
    </div>
    <span
        class="tag tag-left"
        class:faded={!showLeft}
        bind:clientWidth={leftTagWidth}
        aria-hidden="true">{left}</span
    >
    <span
        class="tag tag-right"
        class:faded={!showRight}
        bind:clientWidth={rightTagWidth}
        aria-hidden="true">{right}</span
    >
    <label class="vff-sr-only" for={`${uid}-range`}>{$i18n.t('maps.curtain.label')}</label>
    <input
        id={`${uid}-range`}
        class="range"
        type="range"
        min={CURTAIN_MIN}
        max={CURTAIN_MAX}
        step={CURTAIN_STEP}
        value={percent}
        aria-valuetext={$i18n.t('maps.curtain.position', {
            left: leftText,
            right: rightText,
            percent,
        })}
        oninput={(e) => (value = clampCurtain(Number(e.currentTarget.value)))}
        onkeydown={onKeyDown}
    />
</div>

<style>
    /* Calque de la taille de la carte, transparent aux gestes : seuls le trait et la poignée se
       saisissent ; la carte se déplace partout ailleurs. Au-dessus de la légende : la poignée reste
       saisissable quand le rideau passe devant elle. */
    .era-curtain {
        position: absolute;
        inset: 0;
        z-index: 3;
        pointer-events: none;
    }
    /* Zone de prise de 32 px autour d'un trait de 3 px. */
    .grip {
        position: absolute;
        top: 0;
        bottom: 0;
        left: var(--curtain);
        width: 32px;
        transform: translateX(-50%);
        cursor: ew-resize;
        touch-action: none;
        pointer-events: auto;
    }
    .rule {
        position: absolute;
        top: 0;
        bottom: 0;
        left: 50%;
        width: 3px;
        transform: translateX(-50%);
        background-color: var(--vff-bg-elevated);
        box-shadow: 0 0 0 1px var(--vff-border-strong);
    }
    .knob {
        position: absolute;
        top: 50%;
        left: 50%;
        display: grid;
        place-items: center;
        width: 44px;
        height: 44px;
        transform: translate(-50%, -50%);
        border: 1px solid var(--vff-border-strong);
        border-radius: var(--vff-radius-pill);
        background-color: var(--vff-bg-elevated);
        box-shadow: var(--vff-shadow-popover);
        color: var(--vff-text);
        font-size: 20px;
        transition: transform 120ms ease;
    }
    .grip:hover .knob,
    .dragging .knob {
        transform: translate(-50%, -50%) scale(1.06);
    }
    /* Focus clavier du curseur : contour sur la poignée. */
    .era-curtain:has(.range:focus-visible) .knob {
        outline: var(--vff-focus-width) solid var(--vff-focus);
        outline-offset: var(--vff-focus-offset);
    }
    /* Époques écrites de part et d'autre de la poignée. */
    .tag {
        position: absolute;
        top: 50%;
        max-width: calc(50% - 40px);
        overflow: hidden;
        padding: 4px 12px;
        transform: translateY(-50%);
        border: 1px solid var(--vff-border-strong);
        border-radius: var(--vff-radius-pill);
        background-color: var(--vff-bg-elevated);
        box-shadow: var(--vff-shadow-popover);
        color: var(--vff-text);
        font-family: var(--vff-font-display);
        font-size: var(--vff-fs-md);
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        line-height: 1.3;
        white-space: nowrap;
        text-overflow: ellipsis;
        transition: opacity 120ms ease;
    }
    .tag-left {
        right: calc(100% - var(--curtain) + 30px);
    }
    .tag-right {
        left: calc(var(--curtain) + 30px);
    }
    .tag.faded {
        opacity: 0;
    }
    /* Curseur natif, invisible mais focalisable, posé sur la poignée (le navigateur fait défiler
       vers lui au focus). */
    .range {
        position: absolute;
        top: 50%;
        left: var(--curtain);
        width: 1px;
        height: 1px;
        margin: 0;
        padding: 0;
        overflow: hidden;
        opacity: 0;
        clip-path: inset(50%);
        pointer-events: none;
    }
    @media (prefers-reduced-motion: reduce) {
        .knob,
        .tag {
            transition: none;
        }
    }
</style>

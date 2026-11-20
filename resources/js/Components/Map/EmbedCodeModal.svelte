<script>
    import Field from '$components/ui/Field.svelte';
    import { i18n } from '$lib/i18n.js';
    import { MAP_MODES } from '$lib/railStyle.js';

    /**
     * Fenêtre « Intégrer la carte » : réglages de la carte intégrable (/embed/maps) et code
     * <iframe> à copier. Vrai dialogue modal (<dialog> + showModal()) : le navigateur piège le
     * focus, ferme à Échap et rend le focus au bouton « Intégrer » ; un clic hors de la fenêtre
     * la ferme aussi (closedby="any", avec repli pour les navigateurs qui l'ignorent).
     *
     * Props : open (bindable), defaultEra (décennie affichée par la carte), eras, currentEra.
     */
    let {
        open = $bindable(false),
        defaultEra = 2030,
        eras = [1990, 2000, 2010, 2020, 2030],
        currentEra = null,
    } = $props();

    const uid = $props.id();
    const titleId = `${uid}-title`;
    let dialogEl = $state();
    let snippetEl = $state();

    let width = $state('100%');
    let height = $state(500);
    let era = $state(2030);
    let theme = $state('auto');
    let mode = $state('traffic');
    let showTimeline = $state(true);
    let showLegend = $state(true);
    let showVanished = $state(false);
    let showAttribution = $state(true);
    // '' | 'copied' | 'failed'
    let copyState = $state('');
    let previewOpen = $state(false);

    /** Traduction (toujours une chaîne : i18next la type aussi en objet). */
    const t = (/** @type {string} */ key, /** @type {Record<string, unknown>} */ params = {}) =>
        String($i18n.t(key, params));
    const ERAS = $derived(eras?.length ? eras : [1990, 2000, 2010, 2020, 2030]);
    const LATEST_ERA = $derived(ERAS[ERAS.length - 1]);
    const todayEra = $derived(currentEra ?? LATEST_ERA);

    // Ouverture et fermeture pilotées par `open` : showModal() / close() du navigateur.
    $effect(() => {
        if (!dialogEl) return;
        if (open && !dialogEl.open) {
            // Repartir de l'époque affichée par la carte.
            era = defaultEra;
            copyState = '';
            dialogEl.showModal();
        } else if (!open && dialogEl.open) {
            dialogEl.close();
        }
    });

    // Fermeture par Échap, par le bouton ou par un clic dehors : `open` suit.
    function onClose() {
        open = false;
        previewOpen = false;
    }

    // Repli de closedby="any" (Safari) : clic pressé ET relâché hors du cadre de la fenêtre.
    const lightDismissNative =
        typeof HTMLDialogElement !== 'undefined' && 'closedBy' in HTMLDialogElement.prototype;
    let pressedOutside = false;
    function outside(e) {
        const r = dialogEl.getBoundingClientRect();
        return (
            e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom
        );
    }
    function onPointerDown(e) {
        if (!lightDismissNative) pressedOutside = outside(e);
    }
    function onPointerUp(e) {
        if (!lightDismissNative && pressedOutside && outside(e)) dialogEl.close();
        pressedOutside = false;
    }

    const baseUrl = $derived(
        typeof window !== 'undefined'
            ? `${window.location.origin}/embed/maps`
            : 'https://vff-project.org/embed/maps',
    );

    const queryString = $derived.by(() => {
        // Seuls les réglages différents du défaut figurent dans l'adresse.
        const params = {
            ...(era !== LATEST_ERA && { era: String(era) }),
            ...(theme !== 'auto' && { theme }),
            ...(mode !== 'traffic' && { mode }),
            ...(!showTimeline && { timeline: '0' }),
            ...(!showLegend && { legend: '0' }),
            ...(showVanished && { showVanished: '1' }),
            ...(!showAttribution && { attribution: '0' }),
        };
        const s = new URLSearchParams(params).toString();
        return s ? `?${s}` : '';
    });

    const embedUrl = $derived(`${baseUrl}${queryString}`);
    const safeHeight = $derived(Math.min(2000, Math.max(200, Number(height) || 500)));
    const safeWidth = $derived(String(width).trim().replace(/["<>]/g, '') || '100%');

    const snippet = $derived(`<iframe
  src="${embedUrl}"
  width="${safeWidth}"
  height="${safeHeight}"
  style="border: 1px solid #c8ced4; border-radius: 12px;"
  title="${t('maps.embed.iframe_title')}"
  allowfullscreen
  loading="lazy"
></iframe>`);

    async function copy() {
        try {
            await navigator.clipboard.writeText(snippet);
            copyState = 'copied';
        } catch {
            // Presse-papiers indisponible (page sans HTTPS, cadre verrouillé) : on sélectionne.
            snippetEl?.focus();
            snippetEl?.select();
            copyState = 'failed';
        }
    }

    function eraLabel(y) {
        return y === todayEra ? t('maps.timeline.today') : String(y);
    }
</script>

<dialog
    bind:this={dialogEl}
    class="embed-dialog"
    aria-labelledby={titleId}
    closedby="any"
    onclose={onClose}
    onpointerdown={onPointerDown}
    onpointerup={onPointerUp}
>
    <div class="dialog-head">
        <h2 id={titleId}>{t('maps.embed.title')}</h2>
        <button
            type="button"
            class="close-btn"
            onclick={() => dialogEl.close()}
            aria-label={t('common.close')}
            title={t('common.close')}
        >
            <i class="bi bi-x-lg" aria-hidden="true"></i>
        </button>
    </div>

    <div class="dialog-body">
        <p class="intro">{t('maps.embed.intro')}</p>

        <div class="form-grid">
            <Field
                id={`${uid}-width`}
                label={t('maps.embed.width')}
                hint={t('maps.embed.width_hint')}
            >
                {#snippet children(attrs)}
                    <input {...attrs} class="vff-input" type="text" bind:value={width} />
                {/snippet}
            </Field>
            <Field id={`${uid}-height`} label={t('maps.embed.height')}>
                {#snippet children(attrs)}
                    <input
                        {...attrs}
                        class="vff-input"
                        type="number"
                        inputmode="numeric"
                        min="200"
                        max="2000"
                        step="10"
                        bind:value={height}
                    />
                {/snippet}
            </Field>
            <Field id={`${uid}-era`} label={t('maps.era_selector')}>
                {#snippet children(attrs)}
                    <select {...attrs} class="vff-select" bind:value={era}>
                        {#each ERAS as y (y)}
                            <option value={y}>{eraLabel(y)}</option>
                        {/each}
                    </select>
                {/snippet}
            </Field>
            <Field id={`${uid}-mode`} label={t('maps.embed.mode')}>
                {#snippet children(attrs)}
                    <select {...attrs} class="vff-select" bind:value={mode}>
                        {#each MAP_MODES as m (m)}
                            <option value={m}>{t(`maps.mode.${m}`)}</option>
                        {/each}
                    </select>
                {/snippet}
            </Field>
            <Field id={`${uid}-theme`} label={t('maps.embed.theme')}>
                {#snippet children(attrs)}
                    <select {...attrs} class="vff-select" bind:value={theme}>
                        <option value="auto">{t('maps.embed.theme_auto')}</option>
                        <option value="light">{t('maps.embed.theme_light')}</option>
                        <option value="dark">{t('maps.embed.theme_dark')}</option>
                    </select>
                {/snippet}
            </Field>
        </div>

        <fieldset class="options">
            <legend class="vff-label">{t('maps.embed.options')}</legend>
            <label class="vff-check">
                <input type="checkbox" bind:checked={showTimeline} />
                {t('maps.embed.show_timeline')}
            </label>
            <label class="vff-check">
                <input type="checkbox" bind:checked={showLegend} />
                {t('maps.embed.show_legend')}
            </label>
            <label class="vff-check">
                <input type="checkbox" bind:checked={showVanished} />
                {t('maps.embed.show_vanished')}
            </label>
            <label class="vff-check">
                <input type="checkbox" bind:checked={showAttribution} />
                {t('maps.embed.show_attribution')}
            </label>
        </fieldset>

        <Field id={`${uid}-snippet`} label={t('maps.embed.snippet_label')}>
            {#snippet children(attrs)}
                <textarea
                    {...attrs}
                    bind:this={snippetEl}
                    class="vff-textarea snippet"
                    readonly
                    rows="9"
                    spellcheck="false"
                    value={snippet}
                ></textarea>
            {/snippet}
        </Field>
        <div class="copy-row">
            <button type="button" class="vff-btn primary" onclick={copy}>
                <i class="bi bi-clipboard" aria-hidden="true"></i>
                {t('maps.embed.copy')}
            </button>
            <p class="copy-status" class:failed={copyState === 'failed'} role="status">
                {#if copyState === 'copied'}<i class="bi bi-check2" aria-hidden="true"></i>
                    {t('maps.embed.copied')}{:else if copyState === 'failed'}{t(
                        'maps.embed.copy_failed',
                    )}{/if}
            </p>
        </div>

        <details class="preview" ontoggle={(e) => (previewOpen = e.currentTarget.open)}>
            <summary>{t('maps.embed.preview')}</summary>
            {#if previewOpen}
                <iframe
                    src={embedUrl}
                    width="100%"
                    height="400"
                    title={t('maps.embed.preview_title')}
                    allowfullscreen
                ></iframe>
            {/if}
        </details>
    </div>
</dialog>

<style>
    .embed-dialog {
        width: min(720px, calc(100vw - 32px));
        max-width: none;
        max-height: calc(100dvh - 32px);
        padding: 0;
        border: 1px solid var(--vff-border-strong);
        border-radius: var(--vff-radius-lg);
        background-color: var(--vff-bg-elevated);
        box-shadow: var(--vff-shadow-popover);
        color: var(--vff-text);
    }
    /* Le défilement reste dans la fenêtre (pas de la page derrière). */
    .embed-dialog:modal {
        overscroll-behavior: contain;
    }
    .embed-dialog::backdrop {
        overflow: hidden;
        overscroll-behavior: contain;
        background-color: rgb(0 0 0 / 0.55);
    }

    .dialog-head {
        position: sticky;
        top: 0;
        z-index: 1;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 16px 16px 16px 24px;
        border-bottom: 1px solid var(--vff-border);
        background-color: var(--vff-bg-elevated);
    }
    h2 {
        margin: 0;
        font-size: 1.375rem;
        text-wrap: balance;
    }
    .close-btn {
        display: inline-flex;
        flex-shrink: 0;
        align-items: center;
        justify-content: center;
        width: 44px;
        height: 44px;
        border: 0;
        border-radius: var(--vff-radius-sm);
        background: transparent;
        color: var(--vff-text-muted);
        font-size: 18px;
        cursor: pointer;
        transition:
            background-color var(--vff-transition),
            color var(--vff-transition);
    }
    .close-btn:hover {
        background-color: var(--vff-bg-muted);
        color: var(--vff-text);
    }

    .dialog-body {
        display: flex;
        flex-direction: column;
        gap: 24px;
        padding: 20px 24px 24px;
    }
    .intro {
        margin: 0;
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-md);
        line-height: 1.5;
        color: var(--vff-text-muted);
    }
    .form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px 24px;
        align-items: start;
    }
    /* L'aide passe sous le champ : les champs de la grille restent alignés. */
    .form-grid :global(.vff-hint) {
        order: 3;
    }
    .options {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 8px 24px;
        margin: 0;
        padding: 0;
        border: 0;
        min-width: 0;
    }
    .options legend {
        margin-bottom: 8px;
        padding: 0;
    }
    .snippet {
        min-height: 0;
        font-family: ui-monospace, 'SFMono-Regular', Menlo, Consolas, monospace;
        font-size: var(--vff-fs-sm);
        line-height: 1.5;
        white-space: pre;
        overflow-x: auto;
        resize: vertical;
    }
    .copy-row {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px 16px;
        margin-top: -12px;
    }
    .copy-status {
        display: flex;
        align-items: center;
        gap: 6px;
        margin: 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        color: var(--vff-success);
    }
    .copy-status.failed {
        color: var(--vff-text);
    }
    .preview {
        padding-top: 16px;
        border-top: 1px solid var(--vff-border);
    }
    .preview summary {
        display: inline-flex;
        align-items: center;
        min-height: 32px;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-md);
        font-weight: 600;
        cursor: pointer;
    }
    .preview iframe {
        display: block;
        margin-top: 12px;
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
    }

    @media (max-width: 600px) {
        .dialog-head {
            padding-left: 16px;
        }
        .dialog-body {
            padding: 16px;
        }
        .form-grid,
        .options {
            grid-template-columns: minmax(0, 1fr);
        }
    }
</style>

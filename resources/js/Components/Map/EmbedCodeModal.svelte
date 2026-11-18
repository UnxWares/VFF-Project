<script>
    import { onDestroy } from 'svelte';
    import { i18n } from '$lib/i18n.js';

    // `eras` : toutes les décennies proposées par la carte (1900 → 2030).
    let {
        open = $bindable(false),
        defaultEra = 2030,
        eras = [1990, 2000, 2010, 2020, 2030],
    } = $props();

    // Verrouille le scroll du body pendant que la modale est ouverte pour
    // que la molette agisse sur le contenu de la modale (pas sur la page).
    $effect(() => {
        if (typeof document === 'undefined') return;
        if (open) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = '';
        }
    });

    onDestroy(() => {
        if (typeof document !== 'undefined') document.body.style.overflow = '';
    });

    let width = $state('100%');
    let height = $state(500);
    let era = $state(defaultEra);
    let theme = $state('auto');
    let showTimeline = $state(true);
    let showLegend = $state(true);
    let showVanished = $state(false);
    let showAttribution = $state(true);
    let copyState = $state('idle'); // 'idle' | 'copied'

    const ERAS = $derived(eras?.length ? eras : [1990, 2000, 2010, 2020, 2030]);
    const LATEST_ERA = $derived(ERAS[ERAS.length - 1]);

    // À chaque ouverture : repartir de l'ère affichée sur la carte (avant, la
    // valeur initiale restait figée sur 2030 quel que soit le choix du visiteur).
    $effect(() => {
        if (open) era = defaultEra;
    });

    const baseUrl = $derived(
        typeof window !== 'undefined'
            ? `${window.location.origin}/embed/maps`
            : 'https://vff-project.org/embed/maps',
    );

    const queryString = $derived.by(() => {
        const params = new URLSearchParams();
        if (era !== LATEST_ERA) params.set('era', String(era));
        if (theme !== 'auto') params.set('theme', theme);
        if (!showTimeline) params.set('timeline', '0');
        if (!showLegend) params.set('legend', '0');
        if (showVanished) params.set('showVanished', '1');
        if (!showAttribution) params.set('attribution', '0');
        const s = params.toString();
        return s ? `?${s}` : '';
    });

    const embedUrl = $derived(`${baseUrl}${queryString}`);

    const snippet = $derived(`<iframe
  src="${embedUrl}"
  width="${width}"
  height="${height}"
  style="border: 1px solid #e2e2e2; border-radius: 12px;"
  title="${$i18n.t('maps.embed.iframe_title')}"
  allowfullscreen
  loading="lazy"
></iframe>`);

    async function copy() {
        try {
            await navigator.clipboard.writeText(snippet);
            copyState = 'copied';
            setTimeout(() => (copyState = 'idle'), 2000);
        } catch (_) {
            // Fallback : sélectionne le texte si clipboard indispo (iframe locked-down).
            const textarea = document.querySelector('.embed-snippet');
            textarea?.select();
        }
    }

    function close() {
        open = false;
    }

    function onBackdropClick(e) {
        if (e.target === e.currentTarget) close();
    }
</script>

{#if open}
    <div class="modal-backdrop" role="presentation" onclick={onBackdropClick}>
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="embed-modal-title">
            <div class="modal-head">
                <h2 id="embed-modal-title">
                    <i class="bi bi-code-slash"></i>
                    {$i18n.t('maps.embed.title')}
                </h2>
                <button
                    type="button"
                    class="close-btn"
                    onclick={close}
                    aria-label={$i18n.t('common.close')}
                >
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <p class="modal-intro">{$i18n.t('maps.embed.intro')}</p>

            <div class="modal-body">
                <div class="form-grid">
                    <label class="field">
                        <span>{$i18n.t('maps.embed.width')}</span>
                        <input type="text" bind:value={width} placeholder="100%" />
                    </label>
                    <label class="field">
                        <span>{$i18n.t('maps.embed.height')} (px)</span>
                        <input type="number" bind:value={height} min="200" max="2000" />
                    </label>
                    <label class="field">
                        <span>{$i18n.t('maps.era_selector')}</span>
                        <select bind:value={era}>
                            {#each ERAS as y}
                                <option value={y}>{y}</option>
                            {/each}
                        </select>
                    </label>
                    <label class="field">
                        <span>{$i18n.t('maps.embed.theme')}</span>
                        <select bind:value={theme}>
                            <option value="auto">{$i18n.t('maps.embed.theme_auto')}</option>
                            <option value="light">{$i18n.t('maps.embed.theme_light')}</option>
                            <option value="dark">{$i18n.t('maps.embed.theme_dark')}</option>
                        </select>
                    </label>
                </div>

                <div class="toggles">
                    <label class="toggle">
                        <input type="checkbox" bind:checked={showTimeline} />
                        <span>{$i18n.t('maps.embed.show_timeline')}</span>
                    </label>
                    <label class="toggle">
                        <input type="checkbox" bind:checked={showLegend} />
                        <span>{$i18n.t('maps.embed.show_legend')}</span>
                    </label>
                    <label class="toggle">
                        <input type="checkbox" bind:checked={showVanished} />
                        <span>{$i18n.t('maps.embed.show_vanished')}</span>
                    </label>
                    <label class="toggle">
                        <input type="checkbox" bind:checked={showAttribution} />
                        <span>{$i18n.t('maps.embed.show_attribution')}</span>
                    </label>
                </div>

                <div class="snippet-block">
                    <label for="embed-snippet">{$i18n.t('maps.embed.snippet_label')}</label>
                    <textarea
                        id="embed-snippet"
                        class="embed-snippet"
                        readonly
                        rows="7"
                        value={snippet}
                    ></textarea>
                    <button type="button" class="vff-btn primary copy-btn" onclick={copy}>
                        <i class="bi {copyState === 'copied' ? 'bi-check-lg' : 'bi-clipboard'}"></i>
                        {copyState === 'copied'
                            ? $i18n.t('maps.embed.copied')
                            : $i18n.t('maps.embed.copy')}
                    </button>
                </div>

                <details class="preview-details">
                    <summary>{$i18n.t('maps.embed.preview')}</summary>
                    <div class="preview-wrapper">
                        <iframe
                            src={embedUrl}
                            width="100%"
                            height="400"
                            style="border: 1px solid var(--vff-border); border-radius: 8px;"
                            title={$i18n.t('maps.embed.preview_title')}
                            allowfullscreen
                            loading="lazy"
                        ></iframe>
                    </div>
                </details>
            </div>
        </div>
    </div>
{/if}

<style>
    .modal-backdrop {
        position: fixed;
        inset: 0;
        background-color: rgba(0, 0, 0, 0.55);
        z-index: 1000;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        backdrop-filter: blur(4px);
        animation: fade-in 200ms ease;
    }
    @keyframes fade-in {
        from {
            opacity: 0;
        }
        to {
            opacity: 1;
        }
    }
    .modal {
        background-color: var(--vff-bg);
        border-radius: var(--vff-radius-lg);
        max-width: 720px;
        width: 100%;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 24px 64px var(--vff-shadow-strong);
        border: 1px solid var(--vff-border);
    }
    .modal-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 18px 22px;
        border-bottom: 1px solid var(--vff-border);
    }
    .modal-head h2 {
        font-family: var(--vff-font-display);
        font-size: 20px;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
        color: var(--vff-text);
    }
    .modal-head h2 i {
        color: var(--vff-accent);
    }
    .close-btn {
        background: transparent;
        border: 0;
        font-size: 18px;
        color: var(--vff-text-muted);
        padding: 6px 10px;
        border-radius: var(--vff-radius-sm);
        cursor: pointer;
        transition: all var(--vff-transition);
    }
    .close-btn:hover {
        background-color: var(--vff-bg-muted);
        color: var(--vff-text);
    }
    .modal-intro {
        margin: 18px 22px 0;
        color: var(--vff-text-muted);
        font-size: 14px;
        line-height: 1.5;
    }
    .modal-body {
        padding: 18px 22px 24px;
    }

    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px;
        margin-bottom: 18px;
    }
    .field {
        display: flex;
        flex-direction: column;
        gap: 4px;
        font-size: 13px;
    }
    .field span {
        font-family: var(--vff-font-display);
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--vff-text-muted);
    }
    .field input,
    .field select {
        background-color: var(--vff-bg-elevated);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-sm);
        padding: 8px 12px;
        font-family: var(--vff-font-body);
        color: var(--vff-text);
        font-size: 14px;
    }
    .field input:focus,
    .field select:focus {
        outline: none;
        border-color: var(--vff-accent);
        box-shadow: 0 0 0 3px var(--vff-accent-soft);
    }

    .toggles {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
        padding: 14px;
        background-color: var(--vff-bg-elevated);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        margin-bottom: 18px;
    }
    .toggle {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
        color: var(--vff-text);
        cursor: pointer;
    }
    .toggle input {
        accent-color: var(--vff-accent);
        width: 16px;
        height: 16px;
    }

    .snippet-block {
        position: relative;
    }
    .snippet-block label {
        display: block;
        font-family: var(--vff-font-display);
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--vff-text-muted);
        margin-bottom: 6px;
    }
    .embed-snippet {
        width: 100%;
        font-family: var(--vff-font-secondary, ui-monospace, monospace);
        font-size: 13px;
        line-height: 1.5;
        padding: 12px 14px;
        background-color: var(--vff-bg-elevated);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        color: var(--vff-text);
        resize: vertical;
        min-height: 130px;
    }
    .copy-btn {
        margin-top: 10px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        border: 0;
    }

    .preview-details {
        margin-top: 20px;
        border-top: 1px solid var(--vff-border);
        padding-top: 18px;
    }
    .preview-details summary {
        font-family: var(--vff-font-display);
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        color: var(--vff-text);
        padding: 4px 0;
    }
    .preview-wrapper {
        margin-top: 12px;
    }

    @media (max-width: 600px) {
        .form-grid,
        .toggles {
            grid-template-columns: 1fr;
        }
    }
</style>

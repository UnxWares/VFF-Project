<script>
    import { tick } from 'svelte';
    import { i18n } from '$lib/i18n.js';
    import {
        mediaHeading,
        mediaLicenseHref,
        mediaLicenseLabel,
        mediaSourceHref,
    } from '$lib/mediaCredits.js';
    import { conciseAlt } from '$lib/altText.js';
    import { focusAfterStep, navDisabled } from '$lib/galleryNav.js';
    import { imageLegend, stepIndex } from '$lib/eventStory.js';

    /**
     * Carrousel des images d'une histoire d'événement (Pages/Lines/Story.svelte), dans la page et non dans
     * une fenêtre : une image à la fois, sa légende datée (« Photo de 1934, quatre ans après ») et son
     * crédit complet (auteur, licence en lien, source), comme la visionneuse des galeries.
     *
     * Accessibilité : boutons Précédente et Suivante (désactivés aux deux bouts, sans retour au début),
     * position annoncée (« Image 2 sur 7 », zone polie), flèches gauche et droite, Début et Fin quand le
     * focus est dans le carrousel, aucun défilement automatique. Le bouton qui vient de servir et se
     * désactive passe le focus à l'autre bouton, ou à l'image (lib/galleryNav.js) : jamais à la page.
     * Vignettes pour choisir une image ; toutes les images en `loading="lazy"`.
     *
     * @type {{ images: Array<Record<string, any>>, label: string }}
     */
    let { images, label } = $props();

    let index = $state(0);
    let previousBtn = $state(null);
    let nextBtn = $state(null);
    let imageLink = $state(null);

    const uid = $props.id();
    const t = (key, opts) => String($i18n.t(key, opts));
    const lang = $derived($i18n.language ?? 'fr');
    const count = $derived(images.length);
    const active = $derived(images[Math.min(index, count - 1)] ?? null);
    const disabled = $derived(navDisabled(index, count, false));

    async function go(delta) {
        const focused = document.activeElement;
        index = stepIndex(index, delta, count);
        await tick();
        // Le bouton qui avait le focus (clic, ou flèche pressée sur lui) vient de se désactiver.
        const target = focusAfterStep(delta, index, count, false);
        if (target && focused === (delta < 0 ? previousBtn : nextBtn)) {
            ({ previous: previousBtn, next: nextBtn, image: imageLink })[target]?.focus();
        }
    }

    /** Clavier, quand le focus est dans le carrousel (attachement : écoute posée sur la zone). */
    function keys(node) {
        const onKey = (e) => {
            if (e.altKey || e.ctrlKey || e.metaKey) return;
            const delta = { ArrowLeft: -1, ArrowRight: 1, Home: -count, End: count }[e.key];
            if (delta === undefined || count < 2) return;
            e.preventDefault();
            go(delta);
        };
        node.addEventListener('keydown', onKey);
        return () => node.removeEventListener('keydown', onKey);
    }

    const sizeOf = (m) =>
        m.width && m.height ? { width: m.width, height: m.height } : { width: 1200, height: 800 };
</script>

{#snippet ext()}<i class="bi bi-box-arrow-up-right vff-ext" aria-hidden="true"></i><span
        class="vff-sr-only"
    >
        ({t('common.new_tab')})</span
    >{/snippet}

{#if active}
    <div
        class="carousel"
        role="region"
        aria-roledescription={t('stories.carousel.roledescription')}
        aria-label={label}
        {@attach keys}
    >
        <figure class="slide" aria-labelledby="{uid}-legend">
            <a
                class="stage"
                bind:this={imageLink}
                href={active.display_url}
                target="_blank"
                rel="noopener noreferrer"
            >
                <img
                    src={active.display_url}
                    alt={conciseAlt(mediaHeading(active))}
                    loading="lazy"
                    decoding="async"
                    {...sizeOf(active)}
                />
                <span class="vff-sr-only">{t('stories.carousel.open')} ({t('common.new_tab')})</span
                >
            </a>
            <figcaption>
                <p class="legend" id="{uid}-legend">{imageLegend(active, t, lang)}</p>
                <p class="title">{mediaHeading(active)}</p>
                <p class="credit">
                    <span class="author">{active.author ?? t('media.author_unknown')}</span>,
                    {#if mediaLicenseHref(active)}<a
                            href={mediaLicenseHref(active)}
                            target="_blank"
                            rel="noopener noreferrer license"
                            >{mediaLicenseLabel(active, t)}{@render ext()}</a
                        >{:else}{mediaLicenseLabel(active, t)}{/if}{#if mediaSourceHref(active)},
                        <a href={mediaSourceHref(active)} target="_blank" rel="noopener noreferrer"
                            >{t('media.via', { provider: active.provider_label })}{@render ext()}</a
                        >{/if}{#if active.contribution_id},
                        {t('media.contributed')}{/if}
                    {#if active.share_alike}<span class="vff-badge muted"
                            >{t('media.share_alike')}</span
                        >{/if}
                    {#if active.commercial_ok === false}<span class="vff-badge warning"
                            >{t('media.no_commercial')}</span
                        >{/if}
                </p>
            </figcaption>
        </figure>

        {#if count > 1}
            <div class="controls">
                <button
                    type="button"
                    class="vff-btn secondary small"
                    bind:this={previousBtn}
                    onclick={() => go(-1)}
                    disabled={disabled.previous}
                    aria-label={t('stories.carousel.previous_label')}
                >
                    <i class="bi bi-chevron-left" aria-hidden="true"></i>
                    {t('stories.carousel.previous')}
                </button>
                <p class="position" aria-live="polite" aria-atomic="true">
                    {t('stories.carousel.position', { current: index + 1, total: count })}
                </p>
                <button
                    type="button"
                    class="vff-btn secondary small"
                    bind:this={nextBtn}
                    onclick={() => go(1)}
                    disabled={disabled.next}
                    aria-label={t('stories.carousel.next_label')}
                >
                    {t('stories.carousel.next')}
                    <i class="bi bi-chevron-right" aria-hidden="true"></i>
                </button>
            </div>

            <ul class="thumbs" aria-label={t('stories.carousel.thumbs')}>
                {#each images as m, i (m.id)}
                    <li>
                        <button
                            type="button"
                            class="thumb"
                            aria-current={i === index ? 'true' : undefined}
                            onclick={() => (index = i)}
                        >
                            <img
                                src={m.thumb_url}
                                alt=""
                                loading="lazy"
                                decoding="async"
                                width="120"
                                height="80"
                            />
                            <span class="year" aria-hidden="true">{m.depicted_year ?? ''}</span>
                            <span class="vff-sr-only"
                                >{t('stories.carousel.choose', {
                                    number: i + 1,
                                    title: imageLegend(m, t, lang),
                                })}</span
                            >
                        </button>
                    </li>
                {/each}
            </ul>
            <p class="keys">{t('stories.carousel.keys')}</p>
        {/if}
    </div>
{/if}

<style>
    .carousel {
        max-width: 960px;
    }
    .slide {
        margin: 0;
    }
    /* Cadre fixe : rien ne saute d'une image à l'autre ; image entière (une carte, une affiche ne se
       recadrent pas), sur le fond neutre des vignettes. */
    .stage {
        display: block;
        aspect-ratio: 3 / 2;
        border-radius: var(--vff-radius-md);
        background-color: var(--vff-bg-muted);
        overflow: hidden;
    }
    .stage img {
        display: block;
        width: 100%;
        height: 100%;
        object-fit: contain;
    }
    .stage:focus-visible {
        outline: var(--vff-focus-width) solid var(--vff-focus);
        outline-offset: var(--vff-focus-offset);
    }
    figcaption {
        display: flex;
        flex-direction: column;
        gap: 4px;
        max-width: var(--vff-measure);
        margin-top: var(--vff-space-3);
        font-family: var(--vff-font-heading);
        line-height: var(--vff-lh-ui);
    }
    figcaption p {
        margin: 0;
    }
    .legend {
        font-size: var(--vff-fs-md);
        font-weight: 600;
        color: var(--vff-text);
    }
    .title {
        font-size: var(--vff-fs-sm);
        color: var(--vff-text);
        overflow-wrap: anywhere;
    }
    .credit {
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
    .credit a {
        color: inherit;
    }
    .credit a:hover {
        color: var(--vff-link);
    }
    .credit .vff-badge {
        margin-left: 4px;
    }

    .controls {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: var(--vff-space-3) var(--vff-space-4);
        margin-top: var(--vff-space-5);
    }
    .position {
        margin: 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        font-variant-numeric: tabular-nums;
        color: var(--vff-text-muted);
    }

    .thumbs {
        display: flex;
        flex-wrap: wrap;
        gap: var(--vff-space-2);
        margin: var(--vff-space-4) 0 0;
        padding: 0;
        list-style: none;
    }
    .thumb {
        position: relative;
        display: block;
        padding: 0;
        border: 2px solid transparent;
        border-radius: var(--vff-radius-sm);
        background: var(--vff-bg-muted);
        cursor: pointer;
        overflow: hidden;
        transition: border-color var(--vff-transition);
    }
    .thumb img {
        display: block;
        width: 72px;
        height: 48px;
        object-fit: cover;
    }
    .thumb .year {
        position: absolute;
        right: 0;
        bottom: 0;
        left: 0;
        padding: 0 4px;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-xs);
        font-variant-numeric: tabular-nums;
        line-height: 1.4;
        text-align: center;
        /* Étiquette sur la photo : fond sombre translucide, texte clair (exception du guide). */
        background: rgb(0 0 0 / 0.62);
        color: rgb(255 255 255);
    }
    .thumb:hover {
        border-color: var(--vff-border-control);
    }
    .thumb[aria-current='true'] {
        border-color: var(--vff-accent);
    }
    .thumb:focus-visible {
        outline: var(--vff-focus-width) solid var(--vff-focus);
        outline-offset: var(--vff-focus-offset);
    }
    .keys {
        margin: var(--vff-space-3) 0 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }

    @media (max-width: 600px) {
        .stage {
            aspect-ratio: 4 / 3;
        }
        .controls .vff-btn {
            flex: 1 1 0;
        }
        .position {
            order: -1;
            flex-basis: 100%;
        }
    }
</style>

<script>
    import { Link } from '@inertiajs/svelte';
    import { i18n } from '$lib/i18n.js';
    import { scrollReveal } from '$lib/actions/scrollReveal.js';

    /**
     * Vignettes placeholder en attendant les vraies archives.
     * Chaque carte rend un SVG abstrait (couleur sourde, motif rail) avec date
     * et légende — donne le ton "fonds d'archive" sans dépendre d'images réelles.
     */
    const cards = [
        { key: 'card1', icon: 'bi-building', tone: 1 },
        { key: 'card2', icon: 'bi-train-front', tone: 2 },
        { key: 'card3', icon: 'bi-tools', tone: 3 },
        { key: 'card4', icon: 'bi-flag', tone: 4 },
        { key: 'card5', icon: 'bi-moon-stars', tone: 5 },
        { key: 'card6', icon: 'bi-tree', tone: 6 },
    ];
</script>

<section id="archives" class="archives-section" use:scrollReveal={{ threshold: 0.15 }}>
    <div class="archives-inner">
        <div class="archives-head">
            <span class="eyebrow">{$i18n.t('home.archives.eyebrow')}</span>
            <h2>{$i18n.t('home.archives.title')}</h2>
            <p class="lead">{$i18n.t('home.archives.subtitle')}</p>
        </div>
        <ul class="gallery">
            {#each cards as card}
                <li class="card tone-{card.tone}">
                    <figure class="thumb">
                        <span class="placeholder-icon"><i class="bi {card.icon}"></i></span>
                        <span class="placeholder-label">{$i18n.t('home.archives.placeholder_label')}</span>
                    </figure>
                    <figcaption>
                        <span class="date">{$i18n.t(`home.archives.${card.key}_date`)}</span>
                        <p>{$i18n.t(`home.archives.${card.key}_caption`)}</p>
                    </figcaption>
                </li>
            {/each}
        </ul>
        <div class="archives-cta">
            <Link href="/maps" class="vff-btn ghost">
                <i class="bi bi-images"></i> {$i18n.t('home.archives.cta')}
            </Link>
        </div>
    </div>
</section>

<style>
    .archives-section {
        padding: 80px 16px;
        background-color: var(--vff-bg-elevated);
    }
    .archives-inner {
        max-width: 1180px;
        margin: 0 auto;
    }
    .archives-head {
        text-align: center;
        max-width: 720px;
        margin: 0 auto 48px;
    }
    .eyebrow {
        display: inline-block;
        font-family: var(--vff-font-display);
        font-size: 14px;
        font-weight: 600;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        color: var(--vff-accent);
        margin-bottom: 12px;
    }
    .archives-head h2 {
        margin: 0 0 16px;
        font-family: var(--vff-font-heading);
        font-size: clamp(28px, 3.5vw, 36px);
        color: var(--vff-text);
    }
    .lead {
        font-family: var(--vff-font-body);
        font-size: 18px;
        line-height: 1.55;
        color: var(--vff-text-muted);
        margin: 0;
    }
    .gallery {
        list-style: none;
        padding: 0;
        margin: 0 0 36px;
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 24px;
    }
    .card {
        margin: 0;
        display: flex;
        flex-direction: column;
        border-radius: 12px;
        overflow: hidden;
        background-color: var(--vff-bg);
        border: 1px solid var(--vff-border);
        transition: transform 250ms ease, box-shadow 250ms ease;
    }
    .card:hover {
        transform: translateY(-4px);
        box-shadow: 0 16px 32px var(--vff-shadow);
    }
    .thumb {
        position: relative;
        margin: 0;
        aspect-ratio: 4 / 3;
        display: flex;
        align-items: center;
        justify-content: center;
        background:
            repeating-linear-gradient(
                45deg,
                rgba(0, 0, 0, 0.04) 0px,
                rgba(0, 0, 0, 0.04) 8px,
                rgba(0, 0, 0, 0.07) 8px,
                rgba(0, 0, 0, 0.07) 16px
            ),
            linear-gradient(135deg, #d8d4cc 0%, #b8b3a8 100%);
        color: #4a4640;
        overflow: hidden;
    }
    .tone-1 .thumb { background: linear-gradient(135deg, #d8d4cc, #a39c91); }
    .tone-2 .thumb { background: linear-gradient(135deg, #c4b89c, #8a7d65); }
    .tone-3 .thumb { background: linear-gradient(135deg, #bdb6a8, #978f80); }
    .tone-4 .thumb { background: linear-gradient(135deg, #d2c4a8, #a89878); }
    .tone-5 .thumb { background: linear-gradient(135deg, #404952, #1f242a); color: #d8d4cc; }
    .tone-6 .thumb { background: linear-gradient(135deg, #a8b89a, #6f8060); color: #2a3a1f; }
    .placeholder-icon {
        font-size: 48px;
        opacity: 0.85;
    }
    .placeholder-label {
        position: absolute;
        top: 12px;
        left: 12px;
        font-family: var(--vff-font-display);
        font-size: 11px;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        background-color: rgba(255, 255, 255, 0.92);
        color: var(--vff-text);
        padding: 4px 8px;
        border-radius: 4px;
    }
    .tone-5 .placeholder-label {
        background-color: rgba(0, 0, 0, 0.55);
        color: #fff;
    }
    figcaption {
        padding: 16px 18px 20px;
    }
    .date {
        display: inline-block;
        font-family: var(--vff-font-display);
        font-size: 13px;
        font-weight: 600;
        color: var(--vff-accent);
        margin-bottom: 6px;
    }
    figcaption p {
        font-family: var(--vff-font-body);
        font-size: 15px;
        line-height: 1.45;
        margin: 0;
        color: var(--vff-text);
    }
    .archives-cta {
        text-align: center;
    }

    @media (max-width: 900px) {
        .gallery {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
    @media (max-width: 520px) {
        .gallery {
            grid-template-columns: 1fr;
        }
    }
    @media (prefers-reduced-motion: reduce) {
        .card {
            transition: none;
        }
    }
</style>

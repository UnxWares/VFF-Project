<script>
    let { eyebrow, title, subtitle, status = null } = $props();
</script>

<section class="page-hero">
    <div class="page-hero-inner">
        {#if eyebrow}
            <span class="eyebrow">{eyebrow}</span>
        {/if}
        <h1>{title}</h1>
        {#if subtitle}
            <p class="subtitle">{subtitle}</p>
        {/if}
        {#if status}
            <span class="status-pill">
                <i class="bi bi-tools"></i> {status}
            </span>
        {/if}
    </div>
</section>

<style>
    .page-hero {
        position: relative;
        padding: 60px 16px 120px;
        text-align: center;
        /* Le dégradé se termine bien avant le bord bas (70 %) pour que les
           30 % inférieurs soient totalement transparents — la transition vers
           la section suivante (blanche) est ainsi invisible, sans liseré. */
        background: linear-gradient(
            180deg,
            var(--vff-accent-soft) 0%,
            transparent 70%
        );
        overflow: hidden;
    }
    .page-hero::before {
        content: '';
        position: absolute;
        inset: 0;
        background-image: radial-gradient(
            circle at 50% 0%,
            var(--vff-accent-soft) 0%,
            transparent 50%
        );
        z-index: 0;
    }
    .page-hero-inner {
        position: relative;
        z-index: 1;
        max-width: 800px;
        margin: 0 auto;
    }
    .eyebrow {
        display: inline-block;
        font-family: var(--vff-font-display);
        font-size: 14px;
        font-weight: 600;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: var(--vff-accent);
        margin-bottom: 16px;
    }
    h1 {
        margin: 0 0 16px;
        font-size: clamp(32px, 5vw, 48px);
    }
    h1::after {
        margin-top: 16px;
    }
    .subtitle {
        font-size: clamp(18px, 2.2vw, 22px);
        color: var(--vff-text-muted);
        max-width: 680px;
        margin: 16px auto 0;
        line-height: 1.5;
    }
    .status-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-top: 24px;
        padding: 6px 14px;
        background-color: var(--vff-bg-elevated);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-pill);
        font-size: 13px;
        font-family: var(--vff-font-display);
        color: var(--vff-text-muted);
    }
    .status-pill i {
        color: var(--vff-accent);
    }
</style>

<script>
    let { icon = 'bi-circle', title, text, status = null } = $props();
</script>

<article class="icon-card">
    <div class="icon">
        <i class="bi {icon}"></i>
    </div>
    <h3>{title}</h3>
    <p>{text}</p>
    {#if status}
        <span class="status">{status}</span>
    {/if}
</article>

<style>
    .icon-card {
        background-color: var(--vff-bg-elevated);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        padding: 28px 24px;
        text-align: left;
        transition: transform var(--vff-transition), box-shadow var(--vff-transition);
        height: 100%;
        display: flex;
        flex-direction: column;
    }
    .icon-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 32px var(--vff-shadow);
    }
    .icon {
        width: 48px;
        height: 48px;
        border-radius: var(--vff-radius-md);
        background-color: var(--vff-accent-soft);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 16px;
    }
    .icon i {
        font-size: 24px;
        color: var(--vff-accent);
    }
    h3 {
        margin: 0 0 8px;
        color: var(--vff-text);
    }
    p {
        margin: 0;
        flex: 1;
        font-size: 16px;
        color: var(--vff-text-muted);
        line-height: 1.55;
    }
    .status {
        display: inline-block;
        margin-top: 16px;
        padding: 4px 10px;
        font-size: 12px;
        font-family: var(--vff-font-display);
        background-color: var(--vff-bg-muted);
        color: var(--vff-text-muted);
        border-radius: var(--vff-radius-pill);
        align-self: flex-start;
    }
</style>

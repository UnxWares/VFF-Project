<script>
    // Carte centrée utilisée par Login / Register / ForgotPassword / ResetPassword.
    // Contenu via `children`. Le titre et le sous-titre sont des props i18n
    // déjà résolues côté appelant pour rester découpées par page.
    let { title, subtitle = '', icon = 'bi-person-circle', children } = $props();
</script>

<section class="auth-shell">
    <div class="auth-card">
        <div class="auth-card-header">
            <i class="bi {icon}"></i>
            <h1>{title}</h1>
            {#if subtitle}
                <p class="subtitle">{subtitle}</p>
            {/if}
        </div>
        <div class="auth-card-body">
            {@render children?.()}
        </div>
    </div>
</section>

<style>
    .auth-shell {
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: calc(100vh - 380px);
        padding: 60px 20px;
        background-color: var(--vff-bg-muted);
        font-family: var(--vff-font-body);
        color: var(--vff-text);
    }
    .auth-card {
        background-color: var(--vff-bg);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-lg);
        box-shadow: 0 12px 32px var(--vff-shadow);
        padding: 40px 36px;
        width: 100%;
        max-width: 460px;
    }
    .auth-card-header {
        text-align: center;
        margin-bottom: 28px;
    }
    .auth-card-header :global(i.bi) {
        font-size: 44px;
        color: var(--vff-accent);
        display: block;
        margin-bottom: 8px;
    }
    .auth-card-header h1 {
        margin: 0;
        font-family: var(--vff-font-display);
        font-size: 28px;
        color: var(--vff-text);
    }
    .auth-card-header .subtitle {
        margin: 8px 0 0;
        color: var(--vff-text-muted);
        font-size: 15px;
        line-height: 1.5;
    }
    .auth-card-body :global(.auth-form) {
        display: flex;
        flex-direction: column;
        gap: 18px;
    }
    .auth-card-body :global(.auth-field) {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .auth-card-body :global(.auth-field label) {
        font-family: var(--vff-font-display);
        font-size: 13px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: var(--vff-text-muted);
    }
    .auth-card-body :global(.auth-field input),
    .auth-card-body :global(.auth-field textarea),
    .auth-card-body :global(.auth-field select) {
        width: 100%;
        box-sizing: border-box;
        padding: 10px 12px;
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-sm);
        background-color: var(--vff-bg);
        color: var(--vff-text);
        font-family: var(--vff-font-body);
        font-size: 15px;
        transition:
            border-color var(--vff-transition),
            box-shadow var(--vff-transition);
    }
    .auth-card-body :global(.auth-field input:focus),
    .auth-card-body :global(.auth-field textarea:focus),
    .auth-card-body :global(.auth-field select:focus) {
        outline: none;
        border-color: var(--vff-accent);
        box-shadow: 0 0 0 3px var(--vff-accent-soft);
    }
    .auth-card-body :global(.auth-field .error) {
        color: #c53030;
        font-size: 13px;
    }
    .auth-card-body :global(.auth-checkbox) {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
        color: var(--vff-text-muted);
    }
    .auth-card-body :global(.auth-terms) {
        margin: 0;
        font-size: 13px;
        line-height: 1.5;
        color: var(--vff-text-muted);
    }
    .auth-card-body :global(.auth-terms a) {
        color: var(--vff-accent-ink);
    }
    .auth-card-body :global(.auth-actions) {
        display: flex;
        flex-direction: column;
        gap: 10px;
        margin-top: 6px;
    }
    .auth-card-body :global(.auth-links) {
        display: flex;
        flex-direction: column;
        gap: 8px;
        margin-top: 18px;
        font-size: 14px;
        text-align: center;
        color: var(--vff-text-muted);
    }
    .auth-card-body :global(.auth-links a) {
        display: inline-block;
        padding: 4px 0;
        color: var(--vff-accent-ink);
        text-decoration: none;
        font-weight: 600;
    }
    .auth-card-body :global(.auth-links a:hover) {
        text-decoration: underline;
    }
    .auth-card-body :global(.auth-flash) {
        background-color: var(--vff-accent-soft);
        color: var(--vff-accent-ink);
        padding: 10px 12px;
        border-radius: var(--vff-radius-sm);
        font-size: 14px;
    }
    @media (max-width: 480px) {
        .auth-card {
            padding: 28px 22px;
        }
    }
</style>

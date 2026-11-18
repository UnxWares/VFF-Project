<script>
    /**
     * Cadre des formulaires de compte (connexion, inscription, mot de passe oublié, nouveau mot de
     * passe) : titre (le h1 de la page), chapeau, puis le formulaire (`children`).
     * Le formulaire peut utiliser Components/ui/Field.svelte (libellé, aide et erreur reliés) ; les
     * anciennes classes .auth-form, .auth-field (label, input, .error), .auth-checkbox, .auth-actions,
     * .auth-links, .auth-terms et .auth-flash restent stylées ici.
     * `icon` : accepté pour compatibilité, plus affiché (pas de grande icône décorative).
     */
    /** @type {{ title: string, subtitle?: string, icon?: string|null, children?: import('svelte').Snippet }} */
    let { title, subtitle = '', children } = $props();
</script>

<section class="auth-shell" aria-labelledby="auth-card-title">
    <div class="auth-card">
        <div class="auth-card-header">
            <h1 id="auth-card-title">{title}</h1>
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
        padding: 48px var(--vff-gutter) 64px;
        font-family: var(--vff-font-body);
        color: var(--vff-text);
    }
    .auth-card {
        width: 100%;
        max-width: 440px;
        margin: 0 auto;
        padding: 32px;
        background-color: var(--vff-bg-elevated);
        border: 1px solid var(--vff-border-strong);
        border-radius: var(--vff-radius-lg);
    }
    .auth-card-header {
        margin-bottom: 24px;
    }
    .auth-card-header h1 {
        margin: 0;
        font-family: var(--vff-font-display);
        font-size: 1.75rem;
        line-height: var(--vff-lh-heading);
        color: var(--vff-text);
        text-wrap: balance;
    }
    .auth-card-header .subtitle {
        margin: 8px 0 0;
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-md);
        line-height: 1.5;
        color: var(--vff-text-muted);
    }

    /* Formulaires écrits par les pages (anciennes classes). */
    .auth-card-body :global(.auth-form) {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }
    .auth-card-body :global(.auth-field) {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .auth-card-body :global(.auth-field label) {
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        font-weight: 600;
        line-height: var(--vff-lh-ui);
        color: var(--vff-text);
    }
    .auth-card-body :global(.auth-field input),
    .auth-card-body :global(.auth-field textarea),
    .auth-card-body :global(.auth-field select) {
        width: 100%;
        min-height: 44px;
        padding: 9px 12px;
        border: 1px solid var(--vff-border-control);
        border-radius: var(--vff-radius-sm);
        background-color: var(--vff-bg);
        color: var(--vff-text);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-md);
    }
    .auth-card-body :global(.auth-field input:focus-visible),
    .auth-card-body :global(.auth-field textarea:focus-visible),
    .auth-card-body :global(.auth-field select:focus-visible) {
        outline: var(--vff-focus-width) solid var(--vff-focus);
        outline-offset: 0;
        border-color: var(--vff-focus);
    }
    .auth-card-body :global(.auth-field [aria-invalid='true']),
    .auth-card-body :global(.auth-field:has(.error) input) {
        border-color: var(--vff-danger);
        box-shadow: inset 4px 0 0 var(--vff-danger);
    }
    .auth-card-body :global(.auth-field .error) {
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        font-weight: 600;
        line-height: var(--vff-lh-ui);
        color: var(--vff-danger);
    }
    .auth-card-body :global(.auth-field .hint) {
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
    .auth-card-body :global(.auth-checkbox) {
        display: flex;
        align-items: center;
        gap: 10px;
        min-height: 24px;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text);
        cursor: pointer;
    }
    .auth-card-body :global(.auth-checkbox input) {
        width: 18px;
        height: 18px;
        margin: 0;
        accent-color: var(--vff-accent-ink);
    }
    .auth-card-body :global(.auth-terms) {
        margin: 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: 1.5;
        color: var(--vff-text-muted);
    }
    .auth-card-body :global(.auth-terms a),
    .auth-card-body :global(.auth-links a) {
        color: var(--vff-link);
        text-decoration: underline;
        text-underline-offset: 0.18em;
    }
    .auth-card-body :global(.auth-actions) {
        display: flex;
        flex-direction: column;
        gap: 10px;
        margin-top: 4px;
    }
    .auth-card-body :global(.auth-actions .vff-btn) {
        width: 100%;
        justify-content: center;
    }
    .auth-card-body :global(.auth-links) {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
        margin-top: 4px;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
    .auth-card-body :global(.auth-links a) {
        display: inline-block;
        padding: 4px 0;
        font-weight: 600;
    }
    .auth-card-body :global(.auth-flash) {
        padding: 12px 14px;
        border: 1px solid currentColor;
        border-left-width: 4px;
        border-radius: var(--vff-radius-sm);
        background-color: var(--vff-success-bg);
        color: var(--vff-success);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-ui);
    }
    @media (max-width: 480px) {
        .auth-shell {
            padding-top: 28px;
        }
        .auth-card {
            padding: 24px 18px;
        }
    }
</style>

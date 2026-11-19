<script>
    import { Link, useForm, router } from '@inertiajs/svelte';
    import Field from '$components/ui/Field.svelte';
    import Notice from '$components/ui/Notice.svelte';
    import { i18n } from '$lib/i18n.js';

    /**
     * Section « Jetons d'accès à l'API » du profil (ApiTokenController, App\Services\Api\ApiTokens) :
     * limite du compte, jetons (nom, création, dernière utilisation, révocation confirmée dans une
     * fenêtre modale native) et création, avec la case facultative « Autoriser les contributions
     * par l'API » (capacité `contribute` des mutations GraphQL, décochée par défaut). Le secret d'un
     * jeton créé n'est montré qu'une fois (`access.new_token`, donnée flash de la session).
     *
     * @type {{ access: {
     *   tokens: { id: number, name: string, contribute: boolean, created_at: string|null, last_used_at: string|null }[],
     *   profile: 'member'|'partner', per_minute: number, anonymous_per_minute: number,
     *   max_tokens: number, can_create: boolean, suspended: boolean,
     *   new_token: { id: number, plain: string }|null
     * } }}
     */
    let { access } = $props();

    const form = useForm({ token_name: '', contribute: false });
    let revokeDialog = $state();
    /** @type {{ id: number, name: string }|null} */
    let toRevoke = $state(null);
    let revoking = $state(false);
    let copyStatus = $state('');

    const number = (n) => n.toLocaleString($i18n.language);
    const longDate = (iso) =>
        new Date(iso).toLocaleDateString($i18n.language, {
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        });

    let blockedReason = $derived(
        access.can_create
            ? null
            : access.suspended
              ? $i18n.t('api_tokens.blocked_suspended')
              : $i18n.t('api_tokens.blocked_max', { max: access.max_tokens }),
    );

    function create(e) {
        e.preventDefault();
        form.clearErrors();
        if (!form.token_name.trim()) {
            form.setError('token_name', $i18n.t('api_tokens.errors.name_required'));
            document.getElementById('token-name')?.focus();
            return;
        }
        form.post('/profile/api-tokens', {
            preserveScroll: true,
            onSuccess: () => form.reset(),
            onError: () => document.getElementById('token-name')?.focus(),
        });
    }

    function askRevoke(token) {
        toRevoke = token;
        revokeDialog.showModal();
    }

    function confirmRevoke(e) {
        e.preventDefault();
        if (!toRevoke) return;
        router.delete(`/profile/api-tokens/${toRevoke.id}`, {
            preserveScroll: true,
            onStart: () => (revoking = true),
            onFinish: () => {
                revoking = false;
                revokeDialog.close();
            },
        });
    }

    async function copy() {
        try {
            await navigator.clipboard.writeText(access.new_token.plain);
            copyStatus = $i18n.t('api_tokens.copied');
        } catch {
            copyStatus = $i18n.t('api_tokens.copy_failed');
        }
    }
</script>

<section class="api-tokens" aria-labelledby="api-tokens-title">
    <h2 id="api-tokens-title" class="vff-section-title">{$i18n.t('api_tokens.title')}</h2>
    <p class="intro">
        {$i18n.t('api_tokens.intro', { anonymous: number(access.anonymous_per_minute) })}
        <Link href="/api#auth">{$i18n.t('api_tokens.docs_link')}</Link>
    </p>

    {#if access.new_token}
        <Notice tone="success" title={$i18n.t('api_tokens.new_title')} live="off">
            <p>{$i18n.t('api_tokens.new_text')}</p>
            <div class="secret">
                <label class="vff-sr-only" for="new-token">{$i18n.t('api_tokens.new_label')}</label>
                <input
                    id="new-token"
                    class="vff-input secret-value"
                    type="text"
                    readonly
                    value={access.new_token.plain}
                    spellcheck="false"
                    autocomplete="off"
                    onfocus={(e) => e.currentTarget.select()}
                />
                <button type="button" class="vff-btn secondary" onclick={copy}>
                    <i class="bi bi-clipboard" aria-hidden="true"></i>
                    {$i18n.t('api_tokens.copy')}
                </button>
            </div>
            <p class="copy-status" aria-live="polite">{copyStatus}</p>
            <p>{$i18n.t('api_tokens.new_usage')}</p>
        </Notice>
    {/if}

    <div class="vff-panel panel">
        <dl class="quota">
            <div>
                <dt>{$i18n.t('api_tokens.quota_label')}</dt>
                <dd>
                    {$i18n.t('api_tokens.quota_value', { count: number(access.per_minute) })}
                    <span class="vff-badge {access.profile === 'partner' ? 'accent' : 'muted'}"
                        >{$i18n.t(`api_tokens.profiles.${access.profile}`)}</span
                    >
                </dd>
            </div>
        </dl>
        <p class="note">{$i18n.t('api_tokens.quota_shared')}</p>

        <div class="list-head">
            <h3 id="api-tokens-list">{$i18n.t('api_tokens.list_title')}</h3>
            <span class="count"
                >{$i18n.t('api_tokens.count', {
                    count: access.tokens.length,
                    max: access.max_tokens,
                })}</span
            >
        </div>
        {#if access.tokens.length === 0}
            <p class="empty">{$i18n.t('api_tokens.empty')}</p>
        {:else}
            <ul class="tokens" aria-labelledby="api-tokens-list">
                {#each access.tokens as token (token.id)}
                    <li>
                        <div class="token-text">
                            <strong>{token.name}</strong>
                            <span class="vff-badge {token.contribute ? 'accent' : 'muted'} scope"
                                >{$i18n.t(
                                    token.contribute
                                        ? 'api_tokens.scope_contribute'
                                        : 'api_tokens.scope_read',
                                )}</span
                            >
                            <span class="meta">
                                {#if token.created_at}
                                    <time datetime={token.created_at}
                                        >{$i18n.t('api_tokens.created_at', {
                                            date: longDate(token.created_at),
                                        })}</time
                                    >
                                {/if}
                                {#if token.last_used_at}
                                    <time datetime={token.last_used_at}
                                        >{$i18n.t('api_tokens.last_used', {
                                            date: longDate(token.last_used_at),
                                        })}</time
                                    >
                                {:else}
                                    <span>{$i18n.t('api_tokens.never_used')}</span>
                                {/if}
                            </span>
                        </div>
                        <button
                            type="button"
                            class="vff-btn danger small"
                            aria-label={$i18n.t('api_tokens.revoke_label', { name: token.name })}
                            onclick={() => askRevoke(token)}
                        >
                            {$i18n.t('api_tokens.revoke')}
                        </button>
                    </li>
                {/each}
            </ul>
        {/if}

        <form class="create" onsubmit={create} novalidate>
            <h3>{$i18n.t('api_tokens.create_title')}</h3>
            <Field
                id="token-name"
                label={$i18n.t('api_tokens.name_label')}
                hint={$i18n.t('api_tokens.name_hint')}
                error={form.errors.token_name}
                required
            >
                {#snippet children(attrs)}
                    <input
                        {...attrs}
                        name="token_name"
                        class="vff-input"
                        type="text"
                        maxlength="60"
                        autocomplete="off"
                        required
                        disabled={!access.can_create}
                        bind:value={form.token_name}
                    />
                {/snippet}
            </Field>
            <div class="vff-field">
                <label class="vff-check">
                    <input
                        id="token-contribute"
                        name="contribute"
                        type="checkbox"
                        aria-describedby="token-contribute-hint"
                        disabled={!access.can_create}
                        bind:checked={form.contribute}
                    />
                    <span>{$i18n.t('api_tokens.contribute_label')}</span>
                </label>
                <p class="note" id="token-contribute-hint">
                    {$i18n.t('api_tokens.contribute_hint')}
                    <Link href="/api#contribute">{$i18n.t('api_tokens.docs_link')}</Link>
                </p>
            </div>
            {#if blockedReason}
                <p id="token-blocked" class="note">{blockedReason}</p>
            {/if}
            <div>
                <button
                    type="submit"
                    class="vff-btn primary"
                    disabled={!access.can_create || form.processing}
                    aria-describedby={blockedReason ? 'token-blocked' : undefined}
                >
                    {$i18n.t('api_tokens.create_button')}
                </button>
            </div>
        </form>
    </div>

    <dialog
        bind:this={revokeDialog}
        class="revoke-dialog"
        aria-labelledby="revoke-title"
        aria-describedby="revoke-text"
        onclose={() => (toRevoke = null)}
    >
        <form onsubmit={confirmRevoke}>
            <h2 id="revoke-title">
                {$i18n.t('api_tokens.revoke_confirm_title', { name: toRevoke?.name ?? '' })}
            </h2>
            <p id="revoke-text">{$i18n.t('api_tokens.revoke_confirm_text')}</p>
            <div class="dialog-actions">
                <button
                    type="button"
                    class="vff-btn secondary"
                    onclick={() => revokeDialog.close()}
                >
                    {$i18n.t('api_tokens.cancel')}
                </button>
                <button type="submit" class="vff-btn danger" disabled={revoking}>
                    {$i18n.t('api_tokens.revoke_confirm_button')}
                </button>
            </div>
        </form>
    </dialog>
</section>

<style>
    .api-tokens {
        min-width: 0;
    }
    .api-tokens > :global(.vff-section-title) {
        margin: 0 0 var(--vff-space-3);
    }
    .intro {
        max-width: var(--vff-measure);
        margin: 0 0 var(--vff-space-5);
        font-size: var(--vff-fs-body);
        line-height: var(--vff-lh-body);
    }
    .api-tokens > :global(.vff-notice) {
        margin-bottom: var(--vff-space-5);
    }
    .api-tokens :global(.vff-notice p) {
        margin: 0 0 var(--vff-space-2);
    }
    .secret {
        display: flex;
        flex-wrap: wrap;
        gap: var(--vff-space-3);
        margin-top: var(--vff-space-3);
    }
    .secret-value {
        flex: 1 1 260px;
        min-width: 0;
        font-family:
            'SF Mono', 'Cascadia Code', 'JetBrains Mono', Consolas, 'Liberation Mono', monospace;
        font-size: var(--vff-fs-sm);
    }
    .copy-status:empty {
        display: none;
    }

    .panel {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-3);
        padding: var(--vff-pad-box);
    }
    .quota {
        margin: 0;
    }
    .quota > div {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: baseline;
        gap: var(--vff-space-2) var(--vff-space-4);
    }
    .quota dt {
        color: var(--vff-text-muted);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
    }
    .quota dd {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        gap: var(--vff-space-2);
        margin: 0;
        font-weight: 600;
        font-variant-numeric: tabular-nums;
    }
    .note,
    .empty {
        max-width: var(--vff-measure);
        margin: 0;
        font-size: var(--vff-fs-sm);
        line-height: 1.5;
        color: var(--vff-text-muted);
    }

    .list-head {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        gap: var(--vff-space-3);
        margin-top: var(--vff-space-3);
        padding-top: var(--vff-space-5);
        border-top: 1px solid var(--vff-border);
    }
    h3 {
        margin: 0;
    }
    .count {
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
        font-variant-numeric: tabular-nums;
    }
    .tokens {
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .tokens li {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: var(--vff-space-2) var(--vff-space-4);
        padding-block: var(--vff-space-3);
        border-bottom: 1px solid var(--vff-border);
    }
    .tokens li:first-child {
        border-top: 1px solid var(--vff-border);
    }
    .token-text {
        display: flex;
        flex-direction: column;
        gap: 2px;
        min-width: 0;
    }
    .token-text strong {
        overflow-wrap: anywhere;
    }
    .token-text .scope {
        align-self: flex-start;
    }
    .meta {
        display: flex;
        flex-wrap: wrap;
        gap: 0 var(--vff-space-4);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }

    .create {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-4);
        margin-top: var(--vff-space-3);
        padding-top: var(--vff-space-5);
        border-top: 1px solid var(--vff-border);
    }

    .revoke-dialog {
        width: min(520px, calc(100vw - 32px));
        max-width: none;
        max-height: calc(100dvh - 32px);
        padding: 0;
        border: 1px solid var(--vff-border-strong);
        border-radius: var(--vff-radius-lg);
        background-color: var(--vff-bg-elevated);
        box-shadow: var(--vff-shadow-popover);
        color: var(--vff-text);
    }
    .revoke-dialog::backdrop {
        background-color: rgb(0 0 0 / 0.55);
    }
    .revoke-dialog form {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-5);
        padding: 28px;
    }
    .revoke-dialog h2 {
        margin: 0;
        font-size: var(--vff-fs-h3);
        font-family: var(--vff-font-display);
        text-wrap: balance;
        overflow-wrap: anywhere;
    }
    .revoke-dialog p {
        margin: 0;
        font-size: var(--vff-fs-md);
        line-height: 1.5;
    }
    .dialog-actions {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: var(--vff-space-3);
    }

    @media (max-width: 600px) {
        .revoke-dialog form {
            padding: 20px 16px;
        }
        .secret :global(.vff-btn),
        .create :global(.vff-btn),
        .dialog-actions :global(.vff-btn) {
            width: 100%;
        }
    }
</style>

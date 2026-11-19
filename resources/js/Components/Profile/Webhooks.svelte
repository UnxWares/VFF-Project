<script>
    import { Link, useForm, router } from '@inertiajs/svelte';
    import Field from '$components/ui/Field.svelte';
    import Notice from '$components/ui/Notice.svelte';
    import { i18n } from '$lib/i18n.js';

    /**
     * Section « Webhooks » du profil (WebhookSubscriptionController, App\Services\Webhooks) :
     * abonnements (adresse, événements suivis, état, dernières livraisons), réactivation, suppression
     * confirmée dans une fenêtre modale native, et ajout. Le secret de signature d'un abonnement créé
     * n'est montré qu'une fois (`webhooks.new_secret`, donnée flash de la session).
     *
     * @type {{ webhooks: {
     *   subscriptions: {
     *     id: number, url: string, events: string[], active: boolean, disabled_at: string|null,
     *     disabled_reason: string|null, consecutive_failures: number, created_at: string|null,
     *     deliveries: { id: number, event_type: string, status: string, attempts: number,
     *       response_status: number|null, error: string|null, created_at: string|null }[]
     *   }[],
     *   events: string[], max: number, disable_after: number, attempts: number,
     *   can_create: boolean, suspended: boolean, new_secret: { id: number, secret: string }|null
     * } }}
     */
    let { webhooks } = $props();

    const form = useForm({ webhook_url: '', webhook_events: [] });
    let deleteDialog = $state();
    /** @type {{ id: number, url: string }|null} */
    let toDelete = $state(null);
    let deleting = $state(false);
    let copyStatus = $state('');

    // « contribution.applied » -> clé de traduction « contribution_applied ».
    const eventKey = (type) => type.replace('.', '_');
    const eventLabel = (type) => $i18n.t(`webhooks.events.${eventKey(type)}.label`);
    const longDate = (iso) =>
        new Date(iso).toLocaleDateString($i18n.language, {
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        });
    const dateTime = (iso) =>
        new Date(iso).toLocaleString($i18n.language, {
            day: 'numeric',
            month: 'short',
            hour: '2-digit',
            minute: '2-digit',
        });

    let blockedReason = $derived(
        webhooks.can_create
            ? null
            : webhooks.suspended
              ? $i18n.t('webhooks.blocked_suspended')
              : $i18n.t('webhooks.blocked_max', { max: webhooks.max }),
    );
    let eventsError = $derived(
        form.errors.webhook_events ??
            Object.entries(form.errors).find(([k]) => k.startsWith('webhook_events.'))?.[1] ??
            null,
    );

    function create(e) {
        e.preventDefault();
        form.clearErrors();
        if (!form.webhook_url.trim()) {
            form.setError('webhook_url', $i18n.t('webhooks.errors.url_required'));
            document.getElementById('webhook-url')?.focus();
            return;
        }
        if (form.webhook_events.length === 0) {
            form.setError('webhook_events', $i18n.t('webhooks.errors.events_required'));
            document.getElementById(`webhook-event-${eventKey(webhooks.events[0])}`)?.focus();
            return;
        }
        form.post('/profile/webhooks', {
            preserveScroll: true,
            onSuccess: () => form.reset(),
            onError: () => document.getElementById('webhook-url')?.focus(),
        });
    }

    function enable(subscription) {
        router.post(`/profile/webhooks/${subscription.id}/enable`, {}, { preserveScroll: true });
    }

    function askDelete(subscription) {
        toDelete = subscription;
        deleteDialog.showModal();
    }

    function confirmDelete(e) {
        e.preventDefault();
        if (!toDelete) return;
        router.delete(`/profile/webhooks/${toDelete.id}`, {
            preserveScroll: true,
            onStart: () => (deleting = true),
            onFinish: () => {
                deleting = false;
                deleteDialog.close();
            },
        });
    }

    async function copy() {
        try {
            await navigator.clipboard.writeText(webhooks.new_secret.secret);
            copyStatus = $i18n.t('webhooks.copied');
        } catch {
            copyStatus = $i18n.t('webhooks.copy_failed');
        }
    }

    function outcome(d) {
        const parts = [$i18n.t(`webhooks.statuses.${d.status}`)];
        if (d.response_status)
            parts.push($i18n.t('webhooks.log_http', { code: d.response_status }));
        if (d.error && d.error !== 'http_status')
            parts.push($i18n.t(`webhooks.delivery_errors.${d.error}`));
        return parts.join(', ');
    }
</script>

<section class="webhooks" aria-labelledby="webhooks-title">
    <h2 id="webhooks-title" class="vff-section-title">{$i18n.t('webhooks.title')}</h2>
    <p class="intro">
        {$i18n.t('webhooks.intro')}
        <Link href="/api#webhooks">{$i18n.t('webhooks.docs_link')}</Link>
    </p>

    {#if webhooks.new_secret}
        <Notice tone="success" title={$i18n.t('webhooks.new_title')} live="off">
            <p>{$i18n.t('webhooks.new_text')}</p>
            <div class="secret">
                <label class="vff-sr-only" for="new-webhook-secret"
                    >{$i18n.t('webhooks.new_label')}</label
                >
                <input
                    id="new-webhook-secret"
                    class="vff-input secret-value"
                    type="text"
                    readonly
                    value={webhooks.new_secret.secret}
                    spellcheck="false"
                    autocomplete="off"
                    onfocus={(e) => e.currentTarget.select()}
                />
                <button type="button" class="vff-btn secondary" onclick={copy}>
                    <i class="bi bi-clipboard" aria-hidden="true"></i>
                    {$i18n.t('webhooks.copy')}
                </button>
            </div>
            <p class="copy-status" aria-live="polite">{copyStatus}</p>
        </Notice>
    {/if}

    <div class="vff-panel panel">
        <div class="list-head">
            <h3 id="webhooks-list">{$i18n.t('webhooks.list_title')}</h3>
            <span class="count"
                >{$i18n.t('webhooks.count', {
                    count: webhooks.subscriptions.length,
                    max: webhooks.max,
                })}</span
            >
        </div>
        {#if webhooks.subscriptions.length === 0}
            <p class="empty">{$i18n.t('webhooks.empty')}</p>
        {:else}
            <ul class="subscriptions" aria-labelledby="webhooks-list">
                {#each webhooks.subscriptions as s (s.id)}
                    <li>
                        <div class="sub-head">
                            <code class="url">{s.url}</code>
                            <span class="vff-badge {s.active ? 'success' : 'muted'}"
                                >{$i18n.t(s.active ? 'webhooks.active' : 'webhooks.disabled')}</span
                            >
                        </div>
                        <p class="meta">
                            {#if s.created_at}
                                <time datetime={s.created_at}
                                    >{$i18n.t('webhooks.created_at', {
                                        date: longDate(s.created_at),
                                    })}</time
                                >
                            {/if}
                            <span
                                >{$i18n.t('webhooks.events_followed', {
                                    list: s.events.map(eventLabel).join(', '),
                                })}</span
                            >
                        </p>
                        {#if !s.active && s.disabled_at}
                            <p class="state warning">
                                {$i18n.t('webhooks.disabled_failures', {
                                    date: longDate(s.disabled_at),
                                    count: s.consecutive_failures,
                                })}
                            </p>
                        {:else if s.consecutive_failures > 0}
                            <p class="state">
                                {$i18n.t('webhooks.failing', {
                                    count: s.consecutive_failures,
                                    max: webhooks.disable_after,
                                })}
                            </p>
                        {/if}

                        <details class="log">
                            <summary>{$i18n.t('webhooks.log_title')}</summary>
                            {#if s.deliveries.length === 0}
                                <p class="empty">{$i18n.t('webhooks.log_empty')}</p>
                            {:else}
                                <ul class="deliveries" aria-label={$i18n.t('webhooks.log_caption')}>
                                    {#each s.deliveries as d (d.id)}
                                        <li class="delivery {d.status}">
                                            {#if d.created_at}
                                                <time datetime={d.created_at}
                                                    >{dateTime(d.created_at)}</time
                                                >
                                            {/if}
                                            <span class="event">{eventLabel(d.event_type)}</span>
                                            <span class="result">
                                                {outcome(d)}
                                                <span class="attempts"
                                                    >{$i18n.t('webhooks.log_attempts', {
                                                        count: d.attempts,
                                                    })}</span
                                                >
                                            </span>
                                        </li>
                                    {/each}
                                </ul>
                            {/if}
                        </details>

                        <div class="sub-actions">
                            {#if !s.active && !webhooks.suspended}
                                <button
                                    type="button"
                                    class="vff-btn secondary small"
                                    aria-label={$i18n.t('webhooks.enable_label', { url: s.url })}
                                    onclick={() => enable(s)}
                                >
                                    {$i18n.t('webhooks.enable')}
                                </button>
                            {/if}
                            <button
                                type="button"
                                class="vff-btn danger small"
                                aria-label={$i18n.t('webhooks.delete_label', { url: s.url })}
                                onclick={() => askDelete(s)}
                            >
                                {$i18n.t('webhooks.delete')}
                            </button>
                        </div>
                    </li>
                {/each}
            </ul>
        {/if}

        <form class="create" onsubmit={create} novalidate>
            <h3>{$i18n.t('webhooks.create_title')}</h3>
            <Field
                id="webhook-url"
                label={$i18n.t('webhooks.url_label')}
                hint={$i18n.t('webhooks.url_hint')}
                error={form.errors.webhook_url}
                required
            >
                {#snippet children(attrs)}
                    <input
                        {...attrs}
                        name="webhook_url"
                        class="vff-input"
                        type="url"
                        inputmode="url"
                        maxlength="2048"
                        placeholder="https://"
                        autocomplete="off"
                        spellcheck="false"
                        required
                        disabled={!webhooks.can_create}
                        bind:value={form.webhook_url}
                    />
                {/snippet}
            </Field>
            <fieldset
                class="events"
                disabled={!webhooks.can_create}
                aria-describedby={eventsError ? 'webhook-events-error' : undefined}
            >
                <legend>{$i18n.t('webhooks.events_legend')}</legend>
                {#each webhooks.events as type (type)}
                    <div class="event-choice">
                        <input
                            id="webhook-event-{eventKey(type)}"
                            type="checkbox"
                            name="webhook_events[]"
                            value={type}
                            aria-describedby="webhook-event-{eventKey(type)}-hint"
                            bind:group={form.webhook_events}
                        />
                        <label for="webhook-event-{eventKey(type)}">
                            <span class="choice-label">{eventLabel(type)}</span>
                            <span class="choice-hint" id="webhook-event-{eventKey(type)}-hint"
                                >{$i18n.t(`webhooks.events.${eventKey(type)}.hint`)}</span
                            >
                        </label>
                    </div>
                {/each}
                <p id="webhook-events-error" class="field-error" aria-live="polite">
                    {eventsError ?? ''}
                </p>
            </fieldset>
            <p class="note">
                {$i18n.t('webhooks.rules', {
                    attempts: webhooks.attempts,
                    max: webhooks.disable_after,
                })}
            </p>
            {#if blockedReason}
                <p id="webhook-blocked" class="note">{blockedReason}</p>
            {/if}
            <div>
                <button
                    type="submit"
                    class="vff-btn primary"
                    disabled={!webhooks.can_create || form.processing}
                    aria-describedby={blockedReason ? 'webhook-blocked' : undefined}
                >
                    {$i18n.t('webhooks.create_button')}
                </button>
            </div>
        </form>
    </div>

    <dialog
        bind:this={deleteDialog}
        class="delete-dialog"
        aria-labelledby="webhook-delete-title"
        aria-describedby="webhook-delete-text"
        onclose={() => (toDelete = null)}
    >
        <form onsubmit={confirmDelete}>
            <h2 id="webhook-delete-title">{$i18n.t('webhooks.delete_confirm_title')}</h2>
            <p id="webhook-delete-text">
                {$i18n.t('webhooks.delete_confirm_text', { url: toDelete?.url ?? '' })}
            </p>
            <div class="dialog-actions">
                <button
                    type="button"
                    class="vff-btn secondary"
                    onclick={() => deleteDialog.close()}
                >
                    {$i18n.t('webhooks.cancel')}
                </button>
                <button type="submit" class="vff-btn danger" disabled={deleting}>
                    {$i18n.t('webhooks.delete_confirm_button')}
                </button>
            </div>
        </form>
    </dialog>
</section>

<style>
    .webhooks {
        min-width: 0;
    }
    .webhooks > :global(.vff-section-title) {
        margin: 0 0 var(--vff-space-3);
    }
    .intro {
        max-width: var(--vff-measure);
        margin: 0 0 var(--vff-space-5);
        font-size: var(--vff-fs-body);
        line-height: var(--vff-lh-body);
    }
    .webhooks > :global(.vff-notice) {
        margin-bottom: var(--vff-space-5);
    }
    .webhooks :global(.vff-notice p) {
        margin: 0 0 var(--vff-space-2);
    }
    .secret {
        display: flex;
        flex-wrap: wrap;
        gap: var(--vff-space-3);
        margin-top: var(--vff-space-3);
    }
    .secret-value,
    .url {
        font-family:
            'SF Mono', 'Cascadia Code', 'JetBrains Mono', Consolas, 'Liberation Mono', monospace;
        font-size: var(--vff-fs-sm);
    }
    .secret-value {
        flex: 1 1 260px;
        min-width: 0;
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
    .list-head {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        gap: var(--vff-space-3);
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
    .note,
    .empty,
    .meta,
    .state {
        max-width: var(--vff-measure);
        margin: 0;
        font-size: var(--vff-fs-sm);
        line-height: 1.5;
        color: var(--vff-text-muted);
    }
    .state.warning {
        color: var(--vff-text);
    }

    .subscriptions,
    .deliveries {
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .subscriptions > li {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-2);
        padding-block: var(--vff-space-4);
        border-bottom: 1px solid var(--vff-border);
    }
    .subscriptions > li:first-child {
        border-top: 1px solid var(--vff-border);
    }
    .subscriptions > li:last-child {
        border-bottom: 0;
    }
    .sub-head {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        justify-content: space-between;
        gap: var(--vff-space-2) var(--vff-space-3);
    }
    .url {
        min-width: 0;
        overflow-wrap: anywhere;
        font-weight: 600;
    }
    .meta {
        display: flex;
        flex-wrap: wrap;
        gap: 0 var(--vff-space-4);
    }

    .log summary {
        width: fit-content;
        padding-block: 12px;
        cursor: pointer;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        font-weight: 600;
    }
    .log[open] summary {
        margin-bottom: 0;
    }
    .delivery {
        display: grid;
        grid-template-columns: minmax(7.5em, auto) minmax(0, 1fr) minmax(0, 1.4fr);
        gap: var(--vff-space-1) var(--vff-space-3);
        padding-block: var(--vff-space-2);
        border-top: 1px solid var(--vff-border);
        font-size: var(--vff-fs-sm);
    }
    .delivery time {
        color: var(--vff-text-muted);
        font-variant-numeric: tabular-nums;
    }
    .delivery.failed .result {
        font-weight: 600;
    }
    .attempts {
        color: var(--vff-text-muted);
        white-space: nowrap;
    }
    .attempts::before {
        content: ' · ';
    }

    .sub-actions {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: var(--vff-space-2);
    }

    .create {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-4);
        margin-top: var(--vff-space-3);
        padding-top: var(--vff-space-5);
        border-top: 1px solid var(--vff-border);
    }
    .events {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-3);
        min-width: 0;
        margin: 0;
        padding: 0;
        border: 0;
    }
    .events legend {
        margin-bottom: var(--vff-space-2);
        padding: 0;
        font-family: var(--vff-font-heading);
        font-weight: 600;
    }
    .event-choice {
        display: flex;
        align-items: flex-start;
        gap: var(--vff-space-3);
    }
    .event-choice input {
        flex: none;
        width: 1.5rem;
        height: 1.5rem;
        margin: 0.15em 0 0;
        accent-color: var(--vff-accent);
    }
    .event-choice label {
        display: flex;
        flex-direction: column;
        gap: 2px;
        min-width: 0;
        cursor: pointer;
    }
    .choice-hint {
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
        line-height: 1.45;
    }
    .field-error {
        margin: 0;
        color: var(--vff-danger);
        font-size: var(--vff-fs-sm);
    }
    .field-error:empty {
        display: none;
    }

    .delete-dialog {
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
    .delete-dialog::backdrop {
        background-color: rgb(0 0 0 / 0.55);
    }
    .delete-dialog form {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-5);
        padding: 28px;
    }
    .delete-dialog h2 {
        margin: 0;
        font-size: var(--vff-fs-h3);
        font-family: var(--vff-font-display);
        text-wrap: balance;
    }
    .delete-dialog p {
        margin: 0;
        font-size: var(--vff-fs-md);
        line-height: 1.5;
        overflow-wrap: anywhere;
    }
    .dialog-actions {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: var(--vff-space-3);
    }

    @media (max-width: 600px) {
        .delivery {
            grid-template-columns: 1fr;
            gap: 2px;
        }
        .delete-dialog form {
            padding: 20px 16px;
        }
        .secret :global(.vff-btn),
        .create :global(.vff-btn),
        .sub-actions :global(.vff-btn),
        .dialog-actions :global(.vff-btn) {
            width: 100%;
        }
    }
</style>

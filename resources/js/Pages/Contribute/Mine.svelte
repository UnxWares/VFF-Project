<script>
    import Layout from '$components/Layout.svelte';
    import { i18n } from '$lib/i18n.js';
    import { formatKm } from '$lib/traceGeometry.js';

    let { contributions = [] } = $props();

    // Classes CSS par statut (les libellés sont traduits via maps.editor.mine.status_labels).
    const STATUS_CLS = {
        pending: 'pending',
        approved: 'partial',
        applied: 'applied',
        rejected: 'rejected',
    };

    function statusLabel(status) {
        const key = `maps.editor.mine.status_labels.${status}`;
        const label = $i18n.t(key);
        return label === key ? status : label;
    }

    function formatPk(m) {
        const km = Math.floor(m / 1000);
        const r = m % 1000;
        return `${km}+${r.toString().padStart(3, '0')}`;
    }
</script>

<svelte:head><title>{$i18n.t('maps.editor.mine.meta_title')}</title></svelte:head>

<Layout compactHeader>
    <main class="page">
        <div class="mine-head">
            <h1>{$i18n.t('maps.editor.mine.title')}</h1>
            <p class="subtitle">{$i18n.t('maps.editor.mine.subtitle')}</p>
            <div class="new">
                <a href="/contribute/trace" class="vff-btn primary">
                    <i class="bi bi-bezier2"></i>
                    {$i18n.t('editor.mine.new_trace')}
                </a>
                <a href="/contribute/edit-segment" class="vff-btn ghost">
                    <i class="bi bi-plus-circle-fill"></i>
                    {$i18n.t('maps.editor.mine.new')}
                </a>
            </div>
        </div>

        {#if contributions.length === 0}
            <div class="empty">
                <i class="bi bi-pencil-square"></i>
                <p>{$i18n.t('maps.editor.mine.empty')}</p>
                <p class="hint">{$i18n.t('maps.editor.mine.empty_hint')}</p>
            </div>
        {:else}
            <div class="list">
                {#each contributions as c}
                    <article class="card">
                        <div class="head">
                            <h2>
                                {c.payload.line_name ?? c.type}
                                <span class="code">{c.payload.line_code ?? ''}</span>
                            </h2>
                            <span class="status status-{STATUS_CLS[c.status]}">
                                {statusLabel(c.status)}
                            </span>
                        </div>

                        {#if c.type === 'line_trace'}
                            <p class="pk">
                                <i class="bi bi-bezier2"></i>
                                {$i18n.t(
                                    c.payload.kind === 'new_line'
                                        ? 'editor.validate.kind_new'
                                        : 'editor.validate.kind_correction',
                                )}
                                · {$i18n.t('editor.validate.portions_count', {
                                    count: c.payload.portions?.length ?? 0,
                                })}
                                · {formatKm(c.payload.length_m ?? 0, $i18n.language)}
                                · {$i18n.t('editor.portions.events_count', {
                                    count: c.payload.events_count ?? 0,
                                })}
                                {#if c.payload.applied?.line_slug}
                                    · <a href="/lines/{c.payload.applied.line_slug}"
                                        >{$i18n.t('editor.mine.view_line')}</a
                                    >
                                {/if}
                            </p>
                        {:else}
                            <p class="pk">
                                <i class="bi bi-rulers"></i>
                                {#if c.payload.pk_start_m != null}
                                    {$i18n.t('maps.editor.mine.portion')}
                                    <code
                                        >PK {formatPk(c.payload.pk_start_m)} → PK {formatPk(
                                            c.payload.pk_end_m,
                                        )}</code
                                    >
                                    · {$i18n.t('maps.editor.mine.era')}
                                    <strong>{c.payload.era_year}</strong>
                                {/if}
                            </p>
                        {/if}

                        <ul class="changes">
                            {#each Object.entries(c.payload.changes ?? {}) as [k, v]}
                                <li><strong>{k}</strong> → <code>{JSON.stringify(v)}</code></li>
                            {/each}
                        </ul>

                        {#if c.status === 'rejected' && c.review_notes}
                            <blockquote class="reject">
                                <strong>{$i18n.t('maps.editor.mine.reject_reason')}</strong>
                                {c.review_notes}
                            </blockquote>
                        {/if}

                        <div class="meta">
                            <span
                                >{$i18n.t('maps.editor.mine.submitted', {
                                    date: c.created_at,
                                })}</span
                            >
                            {#if c.status === 'pending' || c.status === 'approved'}
                                <span
                                    >{$i18n.t('maps.editor.mine.approvals', {
                                        count: c.approval_count,
                                    })}</span
                                >
                            {/if}
                            {#if c.reviewed_at}
                                <span
                                    >{$i18n.t('maps.editor.mine.reviewed', {
                                        date: c.reviewed_at,
                                    })}</span
                                >
                            {/if}
                        </div>
                    </article>
                {/each}
            </div>
        {/if}
    </main>
</Layout>

<style>
    .page {
        max-width: 920px;
        margin: 0 auto;
        padding: 48px 20px;
    }
    .mine-head {
        position: relative;
        margin-bottom: 32px;
    }
    h1 {
        font-family: var(--vff-font-display);
        font-size: 32px;
        margin: 0 0 6px;
    }
    h1:after {
        display: none;
    }
    .subtitle {
        color: var(--vff-text-muted);
        margin: 0 0 18px;
    }
    .new {
        position: absolute;
        top: 0;
        right: 0;
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }
    .new :global(.vff-btn) {
        border: 0;
        cursor: pointer;
    }
    @media (max-width: 720px) {
        .new {
            position: static;
            justify-content: flex-start;
        }
    }
    .empty {
        text-align: center;
        padding: 60px 20px;
        color: var(--vff-text-muted);
    }
    .empty i {
        font-size: 56px;
        display: block;
        margin-bottom: 14px;
        color: var(--vff-accent);
    }
    .empty .hint {
        font-size: 14px;
    }

    .list {
        display: flex;
        flex-direction: column;
        gap: 14px;
    }
    .card {
        background-color: var(--vff-bg-elevated);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        padding: 18px 20px;
    }
    .head {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 12px;
    }
    .head h2 {
        font-family: var(--vff-font-display);
        font-size: 17px;
        margin: 0;
    }
    .head h2:after {
        display: none;
    }
    .head .code {
        color: var(--vff-accent);
        font-family: var(--vff-font-secondary, monospace);
        font-size: 13px;
    }
    .status {
        font-family: var(--vff-font-display);
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding: 4px 10px;
        border-radius: var(--vff-radius-pill);
        flex-shrink: 0;
    }
    .status-pending {
        background-color: rgba(255, 139, 53, 0.18);
        color: #b54a00;
    }
    .status-partial {
        background-color: rgba(43, 123, 229, 0.15);
        color: #2c7be5;
    }
    .status-applied {
        background-color: rgba(34, 197, 94, 0.15);
        color: #16a34a;
    }
    .status-rejected {
        background-color: rgba(197, 48, 48, 0.15);
        color: #c53030;
    }

    .pk {
        margin: 12px 0 6px;
        font-size: 14px;
        color: var(--vff-text-muted);
        display: flex;
        gap: 6px;
        align-items: center;
    }
    .pk i {
        color: var(--vff-accent);
    }
    .pk code {
        background-color: var(--vff-bg-muted);
        padding: 2px 8px;
        border-radius: 4px;
        font-family: var(--vff-font-secondary, monospace);
        color: var(--vff-text);
    }
    .pk strong {
        color: var(--vff-text);
    }
    .pk a {
        color: var(--vff-accent);
    }

    .changes {
        list-style: none;
        padding: 0;
        margin: 10px 0;
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        font-size: 13px;
    }
    .changes li {
        background-color: var(--vff-accent-soft);
        color: var(--vff-accent);
        padding: 3px 10px;
        border-radius: var(--vff-radius-sm);
        font-family: var(--vff-font-display);
    }
    .changes code {
        background-color: transparent;
        color: var(--vff-text);
        font-family: var(--vff-font-secondary, monospace);
        font-size: 12px;
    }

    blockquote.reject {
        margin: 12px 0 6px;
        padding: 8px 12px;
        background-color: rgba(197, 48, 48, 0.08);
        border-left: 3px solid #c53030;
        font-size: 13px;
        font-style: italic;
        color: var(--vff-text);
    }

    .meta {
        display: flex;
        gap: 14px;
        font-size: 12px;
        color: var(--vff-text-muted);
        margin-top: 10px;
        padding-top: 10px;
        border-top: 1px solid var(--vff-border);
    }
</style>

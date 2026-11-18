<script>
    import Layout from '$components/Layout.svelte';
    import { i18n } from '$lib/i18n.js';
    import { colon } from '$lib/typo.js';

    import { formatKm } from '$lib/traceGeometry.js';

    let { contribution } = $props();
    const p = contribution.payload;
    const isTrace = contribution.type === 'line_trace';

    function formatPk(m) {
        const km = Math.floor(m / 1000);
        const r = m % 1000;
        return `${km}+${r.toString().padStart(3, '0')}`;
    }
</script>

<svelte:head><title>{$i18n.t('maps.editor.thanks.meta_title')}</title></svelte:head>

<Layout compactHeader>
    <main class="thanks">
        <div class="card">
            <i class="bi bi-check-circle-fill icon"></i>
            <h1>{$i18n.t('maps.editor.thanks.title')}</h1>
            <p class="subtitle">
                {$i18n.t('maps.editor.thanks.subtitle')}
            </p>

            <dl class="recap">
                <dt>{$i18n.t('maps.editor.thanks.line')}</dt>
                <dd>
                    {p.line_name}
                    {#if p.line_code}<span class="code">({p.line_code})</span>{/if}
                </dd>
                {#if isTrace}
                    <dt>{$i18n.t('editor.thanks.kind')}</dt>
                    <dd>
                        {$i18n.t(
                            p.kind === 'new_line'
                                ? 'editor.validate.kind_new'
                                : 'editor.validate.kind_correction',
                        )} · {$i18n.t(`editor.networks.${p.network}`)}
                    </dd>

                    <dt>{$i18n.t('editor.thanks.portions')}</dt>
                    <dd>
                        {$i18n.t('editor.validate.portions_count', {
                            count: p.portions?.length ?? 0,
                        })} · {formatKm(p.length_m ?? 0, $i18n.language)} · {$i18n.t(
                            'editor.portions.events_count',
                            { count: p.events_count ?? 0 },
                        )}
                    </dd>

                    <dt>{$i18n.t('editor.form.trace_source')}</dt>
                    <dd>{p.trace_source}</dd>

                    {#if p.notes}
                        <dt>{$i18n.t('maps.editor.thanks.notes')}</dt>
                        <dd>{p.notes}</dd>
                    {/if}
                {:else}
                    <dt>{$i18n.t('maps.editor.thanks.era')}</dt>
                    <dd>{p.era_year}</dd>

                    <dt>{$i18n.t('maps.editor.thanks.portion')}</dt>
                    <dd><code>PK {formatPk(p.pk_start_m)} → PK {formatPk(p.pk_end_m)}</code></dd>

                    <dt>{$i18n.t('maps.editor.thanks.changes')}</dt>
                    <dd>
                        <ul class="changes">
                            {#each Object.entries(p.changes ?? {}) as [k, v]}
                                <li>
                                    <strong>{k}</strong>{colon($i18n.language)}<code
                                        >{JSON.stringify(v)}</code
                                    >
                                </li>
                            {/each}
                        </ul>
                    </dd>

                    {#if p.notes}
                        <dt>{$i18n.t('maps.editor.thanks.notes')}</dt>
                        <dd>{p.notes}</dd>
                    {/if}

                    {#if (p.sources ?? []).length}
                        <dt>{$i18n.t('maps.editor.thanks.sources')}</dt>
                        <dd>
                            <ul>
                                {#each p.sources as src}
                                    <li><a href={src} target="_blank" rel="noopener">{src}</a></li>
                                {/each}
                            </ul>
                        </dd>
                    {/if}
                {/if}
            </dl>
            {#if isTrace}<p class="subtitle">{$i18n.t('editor.thanks.moderation')}</p>{/if}

            <div class="actions">
                <a href="/maps" class="vff-btn primary"
                    ><i class="bi bi-map"></i> {$i18n.t('maps.editor.thanks.back_to_map')}</a
                >
                {#if isTrace}
                    <a href="/contribute/trace" class="vff-btn ghost"
                        ><i class="bi bi-bezier2"></i> {$i18n.t('editor.thanks.new_trace')}</a
                    >
                    <a href="/contribute/mine" class="vff-btn ghost"
                        ><i class="bi bi-list-check"></i> {$i18n.t('editor.thanks.mine')}</a
                    >
                {:else}
                    <a href="/contribute/edit-segment" class="vff-btn ghost"
                        ><i class="bi bi-plus"></i> {$i18n.t('maps.editor.thanks.new')}</a
                    >
                {/if}
            </div>
        </div>
    </main>
</Layout>

<style>
    .thanks {
        max-width: 680px;
        margin: 0 auto;
        padding: 60px 20px;
    }
    .card {
        background-color: var(--vff-bg-elevated);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-lg);
        padding: 36px 32px;
        text-align: center;
    }
    .icon {
        font-size: 48px;
        color: var(--vff-accent);
        margin-bottom: 14px;
        display: block;
    }
    h1 {
        font-family: var(--vff-font-display);
        font-size: 28px;
        margin: 0 0 8px;
    }
    h1:after {
        display: none;
    }
    .subtitle {
        color: var(--vff-text-muted);
        font-size: 15px;
        margin: 0 0 28px;
    }
    .recap {
        text-align: left;
        display: grid;
        grid-template-columns: 140px 1fr;
        gap: 8px 16px;
        margin: 0 0 28px;
        font-size: 14px;
    }
    .recap dt {
        font-family: var(--vff-font-display);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-size: 12px;
        color: var(--vff-text-muted);
        margin: 0;
    }
    .recap dd {
        margin: 0;
        color: var(--vff-text);
    }
    .recap code {
        background-color: var(--vff-bg-muted);
        padding: 1px 6px;
        border-radius: 3px;
        font-family: var(--vff-font-secondary, monospace);
    }
    .recap .code {
        color: var(--vff-accent);
        font-family: var(--vff-font-secondary, monospace);
    }
    .changes {
        padding-left: 18px;
        margin: 0;
    }
    .actions {
        display: flex;
        gap: 12px;
        justify-content: center;
    }
    .actions .vff-btn {
        border: 0;
    }
</style>

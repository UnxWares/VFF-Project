<script>
    import { router, page } from '@inertiajs/svelte';
    import Layout from '$components/Layout.svelte';
    import TraceDiffMap from '$components/Contribute/TraceDiffMap.svelte';
    import { i18n } from '$lib/i18n.js';
    import { findHistoricBasemap, historicTitle } from '$lib/historicBasemaps.js';
    import { formatKm, portionColor } from '$lib/traceGeometry.js';

    let { contributions = [], threshold = 2 } = $props();

    const flash = $derived(page.props.flash ?? {});

    function formatPk(m) {
        const km = Math.floor(m / 1000);
        const r = m % 1000;
        return `${km}+${r.toString().padStart(3, '0')}`;
    }

    function approve(c) {
        const key =
            c.approval_count + 1 >= threshold
                ? 'editor.validate.confirm_apply'
                : 'editor.validate.confirm_approve';
        if (!confirm($i18n.t(key))) return;
        router.post(`/validate/${c.id}/approve`, {}, { preserveScroll: true });
    }

    function reject(c) {
        const notes = prompt($i18n.t('editor.validate.reject_prompt'));
        if (notes === null) return;
        router.post(`/validate/${c.id}/reject`, { notes }, { preserveScroll: true });
    }

    function typeLabel(t) {
        const key = `editor.event_types.${t}`;
        const label = $i18n.t(key);
        return label === key ? t : label;
    }

    function formatDate(ev) {
        if (ev.precision === 'year') return ev.date.slice(0, 4);
        if (ev.precision === 'month') return ev.date.slice(0, 7);
        return ev.date;
    }

    function portionName(portions, key) {
        const i = (portions ?? []).findIndex((p) => p.key === key);
        return i < 0 ? key : portions[i].label || `#${i + 1}`;
    }

    function basemapLabel(key) {
        const def = findHistoricBasemap(key);
        return def ? `${def.provider} - ${historicTitle(def)}` : null;
    }
</script>

<svelte:head><title>{$i18n.t('editor.validate.meta_title')} - VFF</title></svelte:head>

<Layout compactHeader>
    <main class="validate-page">
        <div class="page-head">
            <h1>{$i18n.t('editor.validate.title')}</h1>
            <p class="subtitle">
                {$i18n.t('editor.validate.subtitle', { count: contributions.length, threshold })}
            </p>
        </div>

        {#if flash.success}<p class="flash ok">
                <i class="bi bi-check-circle"></i>
                {flash.success}
            </p>{/if}
        {#if flash.error}<p class="flash ko">
                <i class="bi bi-exclamation-octagon"></i>
                {flash.error}
            </p>{/if}

        {#if contributions.length === 0}
            <div class="empty">
                <i class="bi bi-inbox"></i>
                <p>{$i18n.t('editor.validate.empty')}</p>
            </div>
        {:else}
            <div class="list">
                {#each contributions as c (c.id)}
                    <article class="contrib-card">
                        <div class="card-head">
                            <div>
                                <p class="kind">
                                    {#if c.type === 'line_trace'}
                                        <i class="bi bi-bezier2"></i>
                                        {$i18n.t(
                                            c.payload.kind === 'new_line'
                                                ? 'editor.validate.kind_new'
                                                : 'editor.validate.kind_correction',
                                        )}
                                    {:else}
                                        <i class="bi bi-rulers"></i>
                                        {$i18n.t('editor.validate.kind_segment')}
                                    {/if}
                                </p>
                                <h2>
                                    {c.target?.name ?? c.payload.line_name}
                                    {#if c.target?.code ?? c.payload.line_code}<span class="code"
                                            >{c.target?.code ?? c.payload.line_code}</span
                                        >{/if}
                                </h2>
                                <p class="meta">
                                    {$i18n.t('editor.validate.by', {
                                        name: c.user.name,
                                        reputation: c.user.reputation,
                                    })}
                                    · {c.created_at}
                                    ·
                                    <span class="approvals"
                                        >{$i18n.t('editor.validate.approvals', {
                                            count: c.approval_count,
                                            threshold,
                                        })}</span
                                    >
                                </p>
                            </div>
                            {#if c.type === 'line_trace'}
                                <span class="badge"
                                    >{$i18n.t(`editor.networks.${c.payload.network}`)}</span
                                >
                            {:else}
                                <span class="badge">{c.payload.era_year}</span>
                            {/if}
                        </div>

                        <div class="card-body">
                            {#if c.type === 'line_trace'}
                                <TraceDiffMap
                                    portions={c.payload.portions ?? []}
                                    before={c.payload.before}
                                    current={c.current}
                                    basemap={c.payload.basemap}
                                />

                                <dl class="facts">
                                    <div>
                                        <dt>{$i18n.t('editor.validate.length')}</dt>
                                        <dd>
                                            {formatKm(c.payload.length_m ?? 0, $i18n.language)} · {$i18n.t(
                                                'editor.validate.portions_count',
                                                { count: c.payload.portions?.length ?? 0 },
                                            )}
                                        </dd>
                                    </div>
                                    {#if c.payload.kind === 'correction' && c.payload.line_network_before && c.payload.line_network_before !== c.payload.network}
                                        <div>
                                            <dt>{$i18n.t('editor.validate.network')}</dt>
                                            <dd class="changed">
                                                {$i18n.t(
                                                    `editor.networks.${c.payload.line_network_before}`,
                                                )} → {$i18n.t(
                                                    `editor.networks.${c.payload.network}`,
                                                )}
                                            </dd>
                                        </div>
                                    {/if}
                                    <div>
                                        <dt>{$i18n.t('editor.validate.trace_source')}</dt>
                                        <dd>{c.payload.trace_source}</dd>
                                    </div>
                                    {#if basemapLabel(c.payload.basemap)}
                                        <div>
                                            <dt>{$i18n.t('editor.validate.basemap')}</dt>
                                            <dd>{basemapLabel(c.payload.basemap)}</dd>
                                        </div>
                                    {/if}
                                </dl>

                                {#if (c.payload.warnings ?? []).length}
                                    <ul class="warnings">
                                        {#each c.payload.warnings as w, wi (wi)}
                                            <li>
                                                <i class="bi bi-exclamation-triangle"></i>
                                                {$i18n.t(`editor.warnings.${w.code}`, {
                                                    portion: portionName(
                                                        c.payload.portions,
                                                        w.portion,
                                                    ),
                                                })}
                                            </li>
                                        {/each}
                                    </ul>
                                {/if}

                                <table class="events">
                                    <thead>
                                        <tr>
                                            <th>{$i18n.t('editor.validate.portion')}</th>
                                            <th>{$i18n.t('editor.events.type')}</th>
                                            <th>{$i18n.t('editor.events.date')}</th>
                                            <th>{$i18n.t('editor.events.source')}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {#each c.payload.portions ?? [] as p, i (p.key)}
                                            {#if p.events.length === 0}
                                                <tr>
                                                    <td
                                                        ><span
                                                            class="pdot"
                                                            style="--c:{portionColor(i)}"
                                                        ></span>{p.label || `#${i + 1}`}
                                                        <small
                                                            >{formatKm(
                                                                p.length_m,
                                                                $i18n.language,
                                                            )}</small
                                                        ></td
                                                    >
                                                    <td colspan="3" class="muted"
                                                        >{$i18n.t('editor.validate.no_event')}</td
                                                    >
                                                </tr>
                                            {/if}
                                            {#each p.events as ev, j (j)}
                                                <tr>
                                                    {#if j === 0}
                                                        <td rowspan={p.events.length}>
                                                            <span
                                                                class="pdot"
                                                                style="--c:{portionColor(i)}"
                                                            ></span>{p.label || `#${i + 1}`}
                                                            <small
                                                                >{formatKm(
                                                                    p.length_m,
                                                                    $i18n.language,
                                                                )}{#if p.from || p.to}
                                                                    · {p.from ?? '?'} → {p.to ??
                                                                        '?'}{/if}</small
                                                            >
                                                        </td>
                                                    {/if}
                                                    <td>{typeLabel(ev.type)}</td>
                                                    <td class="mono"
                                                        >{formatDate(ev)}
                                                        <small
                                                            >({$i18n.t(
                                                                `editor.precisions.${ev.precision}`,
                                                            )})</small
                                                        ></td
                                                    >
                                                    <td>
                                                        {ev.source}
                                                        {#if ev.source_url}<a
                                                                href={ev.source_url}
                                                                target="_blank"
                                                                rel="noopener nofollow"
                                                                title={ev.source_url}
                                                                aria-label={ev.source_url}
                                                                ><i class="bi bi-box-arrow-up-right"
                                                                ></i></a
                                                            >{/if}
                                                        {#if ev.description}<br /><small
                                                                >{ev.description}</small
                                                            >{/if}
                                                    </td>
                                                </tr>
                                            {/each}
                                        {/each}
                                    </tbody>
                                </table>

                                {#if (c.nearby ?? []).length}
                                    <div class="nearby">
                                        <strong>{$i18n.t('editor.validate.nearby')}</strong>
                                        <ul>
                                            {#each c.nearby as n, ni (ni)}
                                                <li>
                                                    {#if n.slug}<a
                                                            href="/lines/{n.slug}"
                                                            target="_blank"
                                                            rel="noopener">{n.name}</a
                                                        >{:else}{n.name}{/if}
                                                    <small>{n.code ?? ''} · {n.via}</small>
                                                </li>
                                            {/each}
                                        </ul>
                                    </div>
                                {/if}

                                {#if c.payload.notes}<blockquote>{c.payload.notes}</blockquote>{/if}
                            {:else}
                                <p class="pk-line">
                                    <i class="bi bi-rulers"></i>
                                    {$i18n.t('editor.validate.pk_portion')}
                                    <code
                                        >PK {formatPk(c.payload.pk_start_m)} → PK {formatPk(
                                            c.payload.pk_end_m,
                                        )}</code
                                    >
                                    <small
                                        >({(
                                            Math.round(
                                                (c.payload.pk_end_m - c.payload.pk_start_m) / 100,
                                            ) / 10
                                        ).toLocaleString($i18n.language)} km)</small
                                    >
                                </p>
                                <ul class="changes">
                                    {#each Object.entries(c.payload.changes ?? {}) as [k, v] (k)}
                                        <li>
                                            <strong>{k}</strong> → <code>{JSON.stringify(v)}</code>
                                        </li>
                                    {/each}
                                </ul>
                                {#if c.payload.notes}<blockquote>{c.payload.notes}</blockquote>{/if}
                                {#if (c.payload.sources ?? []).length}
                                    <p class="sources">
                                        <strong>{$i18n.t('editor.validate.sources')}</strong>
                                        {#each c.payload.sources as src, i (i)}
                                            <a href={src} target="_blank" rel="noopener nofollow"
                                                >[{i + 1}]</a
                                            >
                                        {/each}
                                    </p>
                                {/if}
                            {/if}
                        </div>

                        <div class="card-actions">
                            {#if c.target?.slug ?? c.payload.line_slug}
                                <a
                                    href="/lines/{c.target?.slug ?? c.payload.line_slug}"
                                    target="_blank"
                                    rel="noopener"
                                    class="vff-btn ghost"
                                >
                                    <i class="bi bi-eye"></i>
                                    {$i18n.t('editor.validate.view_sheet')}
                                </a>
                            {/if}
                            {#if c.is_mine}
                                <span class="own"
                                    ><i class="bi bi-person-check"></i>
                                    {$i18n.t('editor.validate.own')}</span
                                >
                            {:else if c.approved_by_me}
                                <span class="own"
                                    ><i class="bi bi-check2-all"></i>
                                    {$i18n.t('editor.validate.already')}</span
                                >
                            {:else}
                                <button
                                    type="button"
                                    class="vff-btn primary approve"
                                    onclick={() => approve(c)}
                                >
                                    <i class="bi bi-check-circle-fill"></i>
                                    {$i18n.t(
                                        c.approval_count + 1 >= threshold
                                            ? 'editor.validate.approve_apply'
                                            : 'editor.validate.approve',
                                    )}
                                </button>
                            {/if}
                            {#if !c.is_mine}
                                <button
                                    type="button"
                                    class="vff-btn ghost reject"
                                    onclick={() => reject(c)}
                                >
                                    <i class="bi bi-x-circle"></i>
                                    {$i18n.t('editor.validate.reject')}
                                </button>
                            {/if}
                        </div>
                    </article>
                {/each}
            </div>
        {/if}
    </main>
</Layout>

<style>
    .validate-page {
        max-width: 1080px;
        margin: 0 auto;
        padding: 48px 20px;
    }
    .page-head h1 {
        font-family: var(--vff-font-display);
        font-size: 32px;
        margin: 0 0 6px;
    }
    .page-head h1:after {
        display: none;
    }
    .subtitle {
        color: var(--vff-text-muted);
        margin: 0 0 28px;
    }
    .flash {
        padding: 10px 14px;
        border-radius: var(--vff-radius-sm);
        margin: 0 0 18px;
        font-size: 14px;
    }
    .flash.ok {
        background: rgba(22, 163, 74, 0.1);
        border: 1px solid #16a34a;
    }
    .flash.ko {
        background: rgba(197, 48, 48, 0.08);
        border: 1px solid #c53030;
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
    }
    .list {
        display: flex;
        flex-direction: column;
        gap: 22px;
    }
    .contrib-card {
        background-color: var(--vff-bg-elevated);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        overflow: hidden;
    }
    .card-head {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        padding: 18px 22px 14px;
        border-bottom: 1px solid var(--vff-border);
        gap: 16px;
    }
    .kind {
        margin: 0 0 4px;
        font-family: var(--vff-font-display);
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--vff-accent);
    }
    .card-head h2 {
        font-family: var(--vff-font-display);
        font-size: 18px;
        margin: 0;
    }
    .card-head h2:after {
        display: none;
    }
    .code {
        color: var(--vff-accent);
        font-family: var(--vff-font-secondary, monospace);
        font-size: 14px;
        margin-left: 6px;
    }
    .meta {
        margin: 6px 0 0;
        font-size: 13px;
        color: var(--vff-text-muted);
    }
    .approvals {
        font-weight: 600;
        color: var(--vff-text);
    }
    .badge {
        background-color: var(--vff-accent);
        color: var(--vff-text-inverse);
        font-family: var(--vff-font-display);
        font-size: 13px;
        font-weight: 700;
        padding: 4px 12px;
        border-radius: var(--vff-radius-pill);
        flex-shrink: 0;
    }
    .card-body {
        padding: 18px 22px;
        display: flex;
        flex-direction: column;
        gap: 14px;
    }
    .facts {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 8px 18px;
        margin: 0;
        font-size: 14px;
    }
    .facts dt {
        font-family: var(--vff-font-display);
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--vff-text-muted);
    }
    .facts dd {
        margin: 2px 0 0;
    }
    .facts dd.changed {
        color: #b45309;
        font-weight: 600;
    }
    .warnings {
        list-style: none;
        margin: 0;
        padding: 10px 12px;
        background: rgba(180, 83, 9, 0.08);
        border: 1px solid #b45309;
        border-radius: var(--vff-radius-sm);
        font-size: 13px;
        color: #92400e;
    }
    .warnings li {
        font-size: 13px;
        line-height: 1.5;
    }
    .events {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }
    .events th {
        text-align: left;
        font-family: var(--vff-font-display);
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--vff-text-muted);
        border-bottom: 1px solid var(--vff-border);
        padding: 6px 8px;
    }
    .events td {
        vertical-align: top;
        border-bottom: 1px solid var(--vff-border);
        padding: 6px 8px;
    }
    .events small {
        display: block;
        color: var(--vff-text-muted);
    }
    .events .mono small {
        display: inline;
    }
    .events a {
        color: var(--vff-accent);
        margin-left: 4px;
    }
    .pdot {
        display: inline-block;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: var(--c);
        margin-right: 6px;
    }
    .mono {
        font-family: var(--vff-font-secondary, monospace);
        white-space: nowrap;
    }
    .muted {
        color: var(--vff-text-muted);
        font-style: italic;
    }
    .nearby {
        font-size: 13px;
    }
    .nearby ul {
        margin: 4px 0 0;
        padding-left: 18px;
    }
    .nearby a {
        color: var(--vff-accent);
    }
    .nearby small {
        color: var(--vff-text-muted);
        margin-left: 4px;
    }
    .pk-line {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
        margin: 0;
    }
    .pk-line i {
        color: var(--vff-accent);
    }
    .pk-line code {
        background-color: var(--vff-bg-muted);
        padding: 2px 8px;
        border-radius: 4px;
        font-family: var(--vff-font-secondary, monospace);
    }
    .pk-line small {
        color: var(--vff-text-muted);
    }
    .changes {
        list-style: none;
        padding: 0;
        margin: 0;
        display: flex;
        flex-wrap: wrap;
        gap: 6px 14px;
    }
    .changes li {
        background-color: var(--vff-accent-soft);
        color: var(--vff-accent);
        padding: 4px 10px;
        border-radius: var(--vff-radius-sm);
        font-family: var(--vff-font-display);
        font-size: 13px;
    }
    .changes code {
        background-color: transparent;
        color: var(--vff-text);
        font-family: var(--vff-font-secondary, monospace);
        font-size: 12px;
    }
    blockquote {
        margin: 0;
        padding: 10px 14px;
        background-color: var(--vff-bg);
        border-left: 3px solid var(--vff-accent);
        border-radius: var(--vff-radius-sm);
        font-style: italic;
        font-size: 14px;
    }
    .sources {
        font-size: 13px;
        color: var(--vff-text-muted);
        margin: 0;
    }
    .sources a {
        color: var(--vff-accent);
        text-decoration: none;
        margin-right: 4px;
        font-family: var(--vff-font-display);
        font-weight: 700;
    }
    .card-actions {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 14px 22px;
        background-color: var(--vff-bg-muted);
        border-top: 1px solid var(--vff-border);
        flex-wrap: wrap;
    }
    .card-actions .vff-btn {
        border: 0;
        cursor: pointer;
        font-size: 13px;
        padding: 8px 14px;
    }
    .card-actions .reject {
        color: #c53030;
    }
    .card-actions .reject:hover {
        background-color: #fee2e2;
    }
    .own {
        font-size: 13px;
        color: var(--vff-text-muted);
    }
    @media (max-width: 640px) {
        .events thead {
            display: none;
        }
        .events td {
            display: block;
        }
    }
</style>

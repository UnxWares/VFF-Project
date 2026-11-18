<script>
    import { i18n } from '$lib/i18n.js';
    import { cleanUrl, linkLabel } from '$lib/linkLabel.js';

    /**
     * Notes de la fiche : une par source citée (numéros des appels [n] du texte). Les objets
     * cartographiques (OpenHistoricalMap…) sont comptés ; leurs identifiants restent
     * accessibles dans un repli, sans encombrer la note.
     */
    let { notes = [] } = $props();

    const lang = $derived($i18n.language ?? 'fr');
    const t = (k, o) => String($i18n.t(k, o));

    function sourceLabel(code) {
        const key = `lines.sources.${code}`;
        const label = $i18n.t(key);
        return label === key ? (code ?? t('lines.sources.unknown')) : label;
    }
</script>

{#snippet ext()}<i class="bi bi-box-arrow-up-right vff-ext" aria-hidden="true"></i><span
        class="vff-sr-only"
    >
        ({t('common.new_tab')})</span
    >{/snippet}

<ol class="notes">
    {#each notes as n (n.n)}
        <li id="note-{n.n}" value={n.n}>
            <span class="src">{sourceLabel(n.source)}</span>{#if n.url}, <a
                    href={cleanUrl(n.url)}
                    target="_blank"
                    rel="noopener noreferrer">{linkLabel(n.url, 40, lang)}{@render ext()}</a
                >{/if}{#if n.urls?.length}, {t('lines.sources.objects', {
                    count: n.urls.length,
                })}
                <details class="objects">
                    <summary
                        >{t('lines.sheet.notes.objects_show', { count: n.urls.length })}</summary
                    >
                    <p>
                        {#each n.urls as u, i (u)}<a
                                href={u}
                                target="_blank"
                                rel="noopener noreferrer">{u.split('/').pop()}</a
                            >{i < n.urls.length - 1 ? ', ' : ''}{/each}
                    </p>
                </details>
            {/if}
        </li>
    {/each}
</ol>

<style>
    .notes {
        margin: 0;
        padding-left: 2.2em;
        columns: 2 340px;
        column-gap: 48px;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: 1.55;
        color: var(--vff-text);
    }
    .notes li {
        margin-bottom: 6px;
        break-inside: avoid;
        font-size: var(--vff-fs-sm);
        scroll-margin-top: 90px;
    }
    .notes li:target {
        border-radius: var(--vff-radius-sm);
        background: var(--vff-bg-accent);
    }
    .src {
        font-weight: 600;
    }
    a {
        overflow-wrap: anywhere;
    }
    .objects summary {
        display: inline-flex;
        min-height: 24px;
        align-items: center;
        color: var(--vff-link);
        text-decoration: underline;
        text-underline-offset: 0.18em;
        cursor: pointer;
    }
    .objects p {
        margin: 4px 0 0;
        font-size: var(--vff-fs-xs);
        font-variant-numeric: tabular-nums;
    }
</style>

<script>
    import { i18n } from '$lib/i18n.js';
    import { linkLabel } from '$lib/linkLabel.js';
    import { colon } from '$lib/typo.js';

    /** Notes de la fiche : une par source citée (numéros des appels [n] du texte). */
    let { notes = [] } = $props();

    function sourceLabel(code) {
        const key = `lines.sources.${code}`;
        const label = $i18n.t(key);
        return label === key ? (code ?? $i18n.t('lines.sources.unknown')) : label;
    }
</script>

<ol class="notes">
    {#each notes as n (n.n)}
        <li id="note-{n.n}" value={n.n}>
            <span class="src">{sourceLabel(n.source)}</span>{#if n.url}, <a
                    href={n.url}
                    target="_blank"
                    rel="noopener">{linkLabel(n.url, 40, $i18n.language)}</a
                >{/if}{#if n.urls?.length}, {$i18n.t('lines.sources.objects', {
                    count: n.urls.length,
                })}{colon($i18n.language)}{#each n.urls.slice(0, 12) as u, i (u)}<a
                        href={u}
                        target="_blank"
                        rel="noopener">{u.split('/').pop()}</a
                    >{i < Math.min(n.urls.length, 12) - 1
                        ? ', '
                        : ''}{/each}{#if n.urls.length > 12}…{/if}{/if}
        </li>
    {/each}
</ol>

<style>
    .notes {
        margin: 0;
        padding-left: 2.2em;
        columns: 2 320px;
        column-gap: 40px;
        font-size: 13.5px;
        line-height: 1.55;
        color: var(--vff-text);
    }
    .notes li {
        font-size: 13.5px;
        break-inside: avoid;
        margin-bottom: 4px;
        scroll-margin-top: 90px;
    }
    .notes li:target {
        background: var(--vff-accent-soft);
        border-radius: 4px;
    }
    .src {
        font-weight: 700;
    }
    a {
        color: var(--vff-accent-ink);
        word-break: break-word;
    }
</style>

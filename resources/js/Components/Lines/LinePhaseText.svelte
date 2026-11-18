<script>
    import { i18n } from '$lib/i18n.js';
    import { changeVerbKey, formatEventDate, formatKm, summarizeChanges } from '$lib/linePhases.js';

    /**
     * Texte d'une phase, rédigé : ce qui change (longueur, portion, état avant et après),
     * avec un appel de note par source, la manière dont la date est établie, et les
     * désaccords entre sources. Utilisé sous la carte (phase choisie) et dans l'histoire
     * de la ligne (toutes les phases).
     */
    let { phase, eventsById = {}, compact = false } = $props();

    const lang = $derived($i18n.language ?? 'fr');
    // Typographie : espace avant « : » et « ; » en français seulement.
    const colon = $derived(lang === 'fr' ? ' : ' : ': ');
    const semi = $derived(lang === 'fr' ? ' ; ' : '; ');
    const summary = $derived(summarizeChanges(phase));

    function sectionText(e) {
        if (!e) return null;
        if (e.section_from && e.section_to && e.section_from !== e.section_to) {
            return $i18n.t('lines.event_section.between_inline', {
                from: e.section_from,
                to: e.section_to,
            });
        }
        if (e.section_from || e.section_to)
            return $i18n.t('lines.event_section.near_inline', {
                place: e.section_from ?? e.section_to,
            });
        return null;
    }

    function eventLabel(type) {
        const key = `lines.event_types.${type}`;
        const label = $i18n.t(key);
        return label === key ? type : label;
    }

    /**
     * Portions citées par les événements d'un changement : une entrée par portion nommée
     * (6 au plus), les portions sans nom regroupées par source (« 12 portions tracées »).
     */
    function whereItems(evs) {
        const named = new Map();
        const unnamed = new Map();
        for (const e of evs) {
            const text = sectionText(e);
            if (text) {
                const k = `${text}|${e.note}`;
                if (!named.has(k)) named.set(k, { text, note: e.note, source: e.source });
            } else {
                const u = unnamed.get(e.note) ?? {
                    count: 0,
                    note: e.note,
                    source: e.source,
                    type: e.type,
                    scope: e.scope,
                };
                u.count += 1;
                unnamed.set(e.note, u);
            }
        }
        const list = [...named.values()];
        const shown = list.slice(0, 6);
        const items = shown.map((x) => ({ text: x.text, note: x.note, source: x.source }));
        if (list.length > shown.length) {
            items.push({
                text: $i18n.t('lines.phases.more_portions', { count: list.length - shown.length }),
                note: null,
                source: null,
            });
        }
        for (const u of unnamed.values()) {
            const text =
                u.scope === 'whole'
                    ? $i18n.t('lines.phases.whole_line_source')
                    : u.count > 1
                      ? $i18n.t('lines.phases.unnamed_portions', { count: u.count })
                      : '';
            items.push({ text, note: u.note, source: u.source });
        }
        return items;
    }

    /** Événements de la phase contredits par d'autres sources (même fait, autre date) : une ligne par événement. */
    const conflicts = $derived.by(() => {
        const out = [];
        const seen = new Set();
        for (const c of summary.changes) {
            for (const id of c.event_ids) {
                const e = eventsById[id];
                const others = (e?.conflicts_with ?? []).map((o) => eventsById[o]).filter(Boolean);
                if (!others.length || seen.has(id)) continue;
                seen.add(id);
                out.push([e, ...others]);
            }
        }
        return out;
    });
</script>

<div class="phase-text" class:compact>
    <ul class="changes">
        {#each summary.changes as c (`${c.from}>${c.to}`)}
            {@const evs = c.event_ids.map((id) => eventsById[id]).filter(Boolean)}
            <li class:inconsistent={c.inconsistent}>
                <span class="verb"
                    >{$i18n.t(changeVerbKey(c.from, c.to), {
                        km: formatKm(c.length_m, lang),
                        to: $i18n.t(`lines.categories.${c.to}`),
                        from: $i18n.t(`lines.categories.${c.from}`),
                    })}</span
                >{#if evs.length}{@const items = whereItems(evs)}<span class="where"
                        >{#each items as w, n (n)}{w.text
                                ? `${n === 0 ? ', ' : ''}${w.text}`
                                : ''}{#if w.note != null}<sup
                                    ><a href="#note-{w.note}" title={w.source ?? ''}>[{w.note}]</a
                                    ></sup
                                >{/if}{n < items.length - 1 ? semi : ''}{/each}</span
                    >{/if}
                {#if !compact}
                    {#if (c.mode_m?.neighbor ?? 0) >= c.length_m / 4}
                        <span class="how">{$i18n.t('lines.phases.mode_neighbor')}</span>
                    {/if}
                    {#if (c.mode_m?.whole_line ?? 0) >= c.length_m / 4}
                        <span class="how">{$i18n.t('lines.phases.mode_whole_line')}</span>
                    {/if}
                    {#if !c.event_ids.length}
                        <span class="how">{$i18n.t('lines.phases.mode_undated')}</span>
                    {/if}
                {/if}
                {#if c.inconsistent}
                    <span class="warn"
                        ><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                        {$i18n.t('lines.phases.inconsistent')}</span
                    >
                {/if}
            </li>
        {/each}
        {#if summary.minor_m > 0}
            <li class="minor">
                {$i18n.t('lines.phases.minor', { km: formatKm(summary.minor_m, lang) })}
            </li>
        {/if}
    </ul>

    {#if conflicts.length && !compact}
        <div class="conflicts" role="note">
            <i class="bi bi-exclamation-diamond-fill" aria-hidden="true"></i>
            <div>
                <strong>{$i18n.t('lines.chronicle.conflict_title')}</strong>
                {#each conflicts as group (group[0].id)}
                    <p>
                        {#each group as e, n (e.id)}{eventLabel(e.type)}{colon}{formatEventDate(
                                e.date,
                                e.precision,
                                lang,
                            )}<sup><a href="#note-{e.note}">[{e.note}]</a></sup>{n <
                            group.length - 1
                                ? semi
                                : ''}{/each}
                    </p>
                {/each}
            </div>
        </div>
    {/if}
</div>

<style>
    .changes {
        list-style: none;
        margin: 0;
        padding: 0;
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .changes li {
        font-family: var(--vff-font-body);
        font-size: 15px;
        line-height: 1.55;
        color: var(--vff-text);
    }
    .compact .changes li {
        font-size: 14px;
    }
    .verb {
        font-weight: 700;
    }
    .how {
        display: block;
        font-size: 13px;
        color: var(--vff-text-muted);
        font-style: italic;
    }
    .minor {
        font-size: 13px !important;
        color: var(--vff-text-muted) !important;
    }
    .warn {
        display: block;
        font-size: 13px;
        color: #b45309;
    }
    sup a {
        color: var(--vff-accent-ink);
        text-decoration: none;
        font-size: 11px;
        padding-left: 1px;
    }
    sup a:hover {
        text-decoration: underline;
    }
    .conflicts {
        display: grid;
        grid-template-columns: 18px 1fr;
        gap: 8px;
        margin-top: 10px;
        padding: 10px 12px;
        border-left: 3px solid #d97706;
        background: rgba(217, 119, 6, 0.08);
        border-radius: 0 var(--vff-radius-sm) var(--vff-radius-sm) 0;
        font-size: 13.5px;
    }
    .conflicts i {
        color: #d97706;
        margin-top: 2px;
    }
    .conflicts strong {
        font-family: var(--vff-font-heading);
        font-size: 13.5px;
    }
    .conflicts p {
        font-size: 13.5px;
        margin: 2px 0 0;
        color: var(--vff-text);
    }
</style>

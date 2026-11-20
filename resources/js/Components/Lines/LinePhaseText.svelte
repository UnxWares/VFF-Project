<script>
    import { i18n } from '$lib/i18n.js';
    import { changeVerbKey, formatEventDate, formatKm, summarizeChanges } from '$lib/linePhases.js';
    import {
        colon as colonOf,
        firstOfMonth,
        formatLocale,
        semicolon,
        tieUnits,
    } from '$lib/typo.js';

    /**
     * Texte d'une phase, rédigé : ce qui change (longueur, portion, état avant et après),
     * avec un appel de note par source, la manière dont la date est établie, et les
     * désaccords entre sources. Utilisé sous la carte (phase choisie) et dans l'histoire
     * de la ligne (toutes les phases).
     */
    let { phase, eventsById = {}, compact = false } = $props();

    const lang = $derived($i18n.language ?? 'fr');
    // Typographie : espace insécable avant « : » et « ; » en français seulement.
    const colon = $derived(colonOf(lang));
    const semi = $derived(semicolon(lang));
    const km = (m) => tieUnits(formatKm(m, formatLocale(lang)));
    const dateOf = (iso, precision) =>
        firstOfMonth(formatEventDate(iso, precision, formatLocale(lang)), lang);
    const summary = $derived(summarizeChanges(phase));

    function sectionText(e) {
        if (!e) return null;
        // Portion rédigée côté serveur, avec élisions (« d'Ambert à Arlanc »).
        if (e.section_text) return e.section_text;
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
        // Index locaux de la fonction, jamais lus par le rendu : pas de réactivité.
        // eslint-disable-next-line svelte/prefer-svelte-reactivity
        const named = new Map();
        // eslint-disable-next-line svelte/prefer-svelte-reactivity
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
        // Index local du calcul, jamais lu par le rendu : pas de réactivité.
        // eslint-disable-next-line svelte/prefer-svelte-reactivity
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

<div class={['phase-text', { compact }]}>
    <ul class="changes">
        {#each summary.changes as c (`${c.from}>${c.to}`)}
            {@const evs = c.event_ids.map((id) => eventsById[id]).filter(Boolean)}
            <li class={{ inconsistent: c.inconsistent }}>
                <span class="verb"
                    >{$i18n.t(changeVerbKey(c.from, c.to), {
                        km: km(c.length_m),
                        to: $i18n.t(`lines.categories.${c.to}`),
                        from: $i18n.t(`lines.categories.${c.from}`),
                    })}</span
                >{#if evs.length}{@const items = whereItems(evs)}<span class="where"
                        >{#each items as w, n (n)}{w.text
                                ? `${n === 0 ? ', ' : ''}${w.text}`
                                : ''}{#if w.note != null}<sup
                                    ><a href="#note-{w.note}" title={w.source ?? ''}
                                        ><span class="vff-sr-only"
                                            >{`${$i18n.t('common.note_ref')} `}
                                        </span>[{w.note}]</a
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
                {$i18n.t('lines.phases.minor', { km: km(summary.minor_m) })}
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
                        {#each group as e, n (e.id)}{eventLabel(e.type)}{colon}{dateOf(
                                e.date,
                                e.precision,
                            )}<sup
                                ><a href="#note-{e.note}"
                                    ><span class="vff-sr-only"
                                        >{`${$i18n.t('common.note_ref')} `}
                                    </span>[{e.note}]</a
                                ></sup
                            >{n < group.length - 1 ? semi : ''}{/each}
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
        max-width: var(--vff-measure);
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-md);
        line-height: 1.55;
        color: var(--vff-text);
    }
    .compact .changes li {
        font-size: var(--vff-fs-sm);
    }
    .verb {
        font-weight: 700;
    }
    .how {
        display: block;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-xs);
        color: var(--vff-text-muted);
    }
    .changes li.minor {
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
    .warn {
        display: block;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-xs);
        color: var(--vff-warning);
    }
    /* Appel de note : 13 px (minimum lisible), cible élargie sans décaler la ligne. */
    sup {
        line-height: 0;
    }
    /* Appel de note : cible de 24 px (le texte reste en exposant). */
    sup a {
        /* Remplissage en ligne : la zone cliquable grandit sans écarter les lignes du texte
           (« [1] » en 14 px, environ 14 px de large, + 2 × 6 px : 24 px au moins). */
        padding: 5px 6px;
        color: var(--vff-link);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-xs);
        text-decoration: none;
    }
    sup a:hover {
        text-decoration: underline;
    }
    .conflicts {
        display: grid;
        grid-template-columns: 18px 1fr;
        gap: 8px;
        margin-top: 10px;
        padding: 12px 16px;
        border: 1px solid color-mix(in srgb, var(--vff-warning) 28%, transparent);
        background: var(--vff-warning-bg);
        border-radius: var(--vff-radius-md);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
    }
    .conflicts i {
        color: var(--vff-warning);
        margin-top: 2px;
    }
    .conflicts strong {
        font-size: var(--vff-fs-sm);
    }
    .conflicts p {
        font-size: var(--vff-fs-sm);
        margin: 2px 0 0;
        color: var(--vff-text);
    }
</style>

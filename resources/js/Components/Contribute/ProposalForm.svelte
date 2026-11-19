<script>
    /**
     * Gabarit des formulaires de proposition (événement daté, image) : formulaire sans bulles du
     * navigateur (`novalidate`), récapitulatif des erreurs en tête avec un lien vers chaque champ,
     * panneaux (`.group`, fieldset ou div), boutons d'envoi et rappel de la relecture à deux.
     *
     * La page fournit les panneaux (`children`) et les boutons (`actions`). Après un envoi refusé,
     * elle appelle `showErrors()` (composant lié par bind:this) : le récapitulatif prend le focus.
     *
     * Props : `errors` (champ → message), `fieldIds` (champ → id du contrôle à atteindre),
     * `onsubmit`, `children`, `actions`.
     */
    import { tick } from 'svelte';
    import Notice from '$components/ui/Notice.svelte';
    import { i18n } from '$lib/i18n.js';

    /** @type {{ errors: Record<string, string|string[]>, fieldIds: Record<string, string>, onsubmit: (e: SubmitEvent) => void, children: import('svelte').Snippet, actions: import('svelte').Snippet }} */
    let { errors, fieldIds, onsubmit, children, actions } = $props();

    const SUMMARY_ID = 'proposal-errors';
    const entries = $derived(Object.entries(errors));

    /** Met le récapitulatif des erreurs au premier plan (focus), après leur affichage. */
    export async function showErrors() {
        await tick();
        document.getElementById(SUMMARY_ID)?.focus();
    }

    /** @param {string} key */
    function target(key) {
        return fieldIds[key] ?? fieldIds[key.split('.')[0]] ?? Object.values(fieldIds)[0];
    }
</script>

<form class="proposal-form" novalidate {onsubmit}>
    <div class="summary" id={SUMMARY_ID} tabindex="-1">
        {#if entries.length}
            <Notice
                tone="danger"
                live="assertive"
                title={$i18n.t('proposals.errors.summary', { count: entries.length })}
            >
                <ul>
                    {#each entries as [key, message] (key)}
                        <li>
                            <a href="#{target(key)}"
                                >{Array.isArray(message) ? message.join(' ') : message}</a
                            >
                        </li>
                    {/each}
                </ul>
            </Notice>
        {/if}
    </div>

    {@render children()}

    <div class="actions">
        {@render actions()}
        <p class="moderation">
            <i class="bi bi-shield-check" aria-hidden="true"></i>
            {$i18n.t('editor.form.moderation')}
        </p>
    </div>
</form>

<style>
    .proposal-form {
        display: flex;
        flex-direction: column;
        gap: var(--vff-block-space);
        max-width: 760px;
    }
    .summary:empty {
        display: none;
    }
    .summary:focus {
        outline: none;
    }
    .summary:focus-visible {
        outline: var(--vff-focus-width) solid var(--vff-focus);
        outline-offset: var(--vff-focus-offset);
    }
    .summary ul {
        margin: var(--vff-space-2) 0 0;
        padding-left: 1.2em;
    }
    /* Lien en ligne (pas en bloc) : la puce reste sur la première ligne d'un message long. */
    .summary li {
        padding-block: 3px;
    }
    .actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: var(--vff-space-3) var(--vff-space-5);
    }
    .moderation {
        margin: 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }

    /* Panneaux et grilles des pages (ProposeEvent, ProposeMedia). */
    .proposal-form :global {
        .group {
            display: flex;
            flex-direction: column;
            gap: var(--vff-space-4);
            min-width: 0;
            margin: 0;
        }
        .group > legend {
            float: left;
            width: 100%;
            margin: 0 0 var(--vff-space-1);
            padding: 0;
            font-family: var(--vff-font-display);
            font-size: var(--vff-fs-h3);
            font-weight: 700;
            line-height: 1.3;
            color: var(--vff-text);
        }
        .group > legend + * {
            clear: both;
        }
        .intro {
            max-width: var(--vff-measure);
            margin: 0;
            font-family: var(--vff-font-heading);
            font-size: var(--vff-fs-sm);
            line-height: var(--vff-lh-ui);
            color: var(--vff-text-muted);
        }
        .grid-2 {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: var(--vff-space-4) var(--vff-space-5);
        }
        .choices {
            min-width: 0;
            margin: 0;
            padding: 0;
            border: 0;
        }
        .choices > legend {
            margin-bottom: var(--vff-space-2);
            padding: 0;
        }
        .choice-list {
            display: flex;
            flex-direction: column;
            gap: var(--vff-space-2);
        }
        .choice-list.inline {
            flex-direction: row;
            flex-wrap: wrap;
            column-gap: var(--vff-space-5);
        }
    }
    @media (max-width: 600px) {
        .proposal-form :global(.grid-2) {
            grid-template-columns: minmax(0, 1fr);
        }
    }
</style>

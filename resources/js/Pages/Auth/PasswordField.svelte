<script>
    /**
     * Champ de mot de passe des formulaires de compte : libellé, aide (règle du mot de passe, annoncée
     * avant la saisie), erreur reliée au champ (Components/ui/Field.svelte) et bouton « Afficher le
     * mot de passe ». Le bouton est un vrai bouton (clavier, 44 px), son nom change avec l'état.
     * Props : `id`, `name`, `label`, `hint`, `error`, `autocomplete` ('current-password' ou
     * 'new-password'), `value` (liable).
     */
    import Field from '$components/ui/Field.svelte';
    import { i18n } from '$lib/i18n.js';

    /** @type {{ id: string, name?: string, label: string, hint?: string|null, error?: string|string[]|null, autocomplete?: 'current-password'|'new-password', value?: string }} */
    let {
        id,
        name = id,
        label,
        hint = null,
        error = null,
        autocomplete = 'current-password',
        value = $bindable(''),
    } = $props();

    let shown = $state(false);
</script>

<Field {id} {label} {hint} {error} required>
    {#snippet children(attrs)}
        <div class="password-control">
            <input
                {...attrs}
                {name}
                class="vff-input"
                type={shown ? 'text' : 'password'}
                {autocomplete}
                spellcheck="false"
                autocapitalize="none"
                required
                bind:value
            />
            <button
                type="button"
                class="toggle"
                aria-controls={id}
                aria-label={shown ? $i18n.t('auth.hide_password') : $i18n.t('auth.show_password')}
                onclick={() => (shown = !shown)}
            >
                <i class={['bi', shown ? 'bi-eye-slash' : 'bi-eye']} aria-hidden="true"></i>
            </button>
        </div>
    {/snippet}
</Field>

<style>
    .password-control {
        position: relative;
    }
    .password-control :global(.vff-input) {
        padding-right: 52px;
    }
    .toggle {
        position: absolute;
        top: 0;
        right: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 44px;
        height: 100%;
        min-height: 44px;
        padding: 0;
        border: 0;
        border-radius: 0 var(--vff-radius-sm) var(--vff-radius-sm) 0;
        background: transparent;
        color: var(--vff-text-muted);
        font-size: 18px;
        cursor: pointer;
        transition:
            color var(--vff-transition),
            background-color var(--vff-transition);
    }
    .toggle:hover {
        background-color: var(--vff-bg-muted);
        color: var(--vff-text);
    }
    .toggle:focus-visible {
        outline-offset: -2px;
    }
</style>

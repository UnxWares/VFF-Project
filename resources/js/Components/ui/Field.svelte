<script>
    /**
     * Champ de formulaire : libellé, aide facultative, contrôle, erreur. L'aide et l'erreur sont
     * reliées au contrôle par aria-describedby, aria-invalid est posé en cas d'erreur, et l'erreur
     * est annoncée par les lecteurs d'écran quand elle apparaît (zone aria-live).
     *
     * Le contrôle est écrit par la page dans le snippet `children`, qui reçoit les attributs à poser :
     *
     *   <Field id="email" label={$i18n.t('auth.email_label')} error={form.errors.email}>
     *       {#snippet children(attrs)}
     *           <input {...attrs} class="vff-input" type="email" autocomplete="email" bind:value={form.email} />
     *       {/snippet}
     *   </Field>
     *
     * Props : `id` (id du contrôle, obligatoire), `label`, `hint`, `error` (texte ou tableau de
     * textes, tel que form.errors.x), `labelHidden` (libellé lu mais pas affiché : à éviter, seulement
     * si un titre visible nomme déjà le champ).
     */
    /** @type {{ id: string, label: string, hint?: string|null, error?: string|string[]|null, labelHidden?: boolean, children: import('svelte').Snippet<[Record<string, string|undefined>]> }} */
    let { id, label, hint = null, error = null, labelHidden = false, children } = $props();

    const message = $derived(Array.isArray(error) ? error.join(' ') : error || '');
    const hintId = $derived(`${id}-hint`);
    const errorId = $derived(`${id}-error`);
    const attrs = $derived({
        id,
        'aria-describedby':
            [hint ? hintId : null, message ? errorId : null].filter(Boolean).join(' ') || undefined,
        'aria-invalid': message ? 'true' : undefined,
    });
</script>

<div class="vff-field">
    <label class={['vff-label', { 'vff-sr-only': labelHidden }]} for={id}>{label}</label>
    {#if hint}
        <p class="vff-hint" id={hintId}>{hint}</p>
    {/if}
    {@render children(attrs)}
    <p class="vff-error" id={errorId} aria-live="polite">
        {#if message}<i class="bi bi-exclamation-circle" aria-hidden="true"></i><span
                >{message}</span
            >{/if}
    </p>
</div>

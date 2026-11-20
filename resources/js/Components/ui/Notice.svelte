<script>
    /**
     * Message à l'utilisateur : confirmation (« Profil enregistré »), erreur d'envoi, avertissement,
     * information. Couleur, icône ET texte disent le ton (jamais la couleur seule).
     *
     *   <Notice tone="success">{flash}</Notice>
     *   <Notice tone="danger" title={$i18n.t('…')}>…</Notice>
     *
     * `tone` : 'info' | 'success' | 'warning' | 'danger'. `live` : 'polite' (par défaut,
     * role="status"), 'assertive' (role="alert" : seulement pour une erreur qui bloque l'action
     * en cours) ou 'off' (message présent dès le chargement, sans annonce). `icon` remplace l'icône.
     */
    /** @type {{ tone?: 'info'|'success'|'warning'|'danger', title?: string|null, live?: 'polite'|'assertive'|'off', icon?: string|null, children?: import('svelte').Snippet }} */
    let { tone = 'info', title = null, live = 'polite', icon = null, children } = $props();

    const ICONS = {
        info: 'bi-info-circle',
        success: 'bi-check2-circle',
        warning: 'bi-exclamation-triangle',
        danger: 'bi-exclamation-octagon',
    };
    const role = $derived(
        live === 'assertive' ? 'alert' : live === 'polite' ? 'status' : undefined,
    );
</script>

<div class="vff-notice {tone}" {role}>
    <i class="bi {icon ?? ICONS[tone] ?? ICONS.info}" aria-hidden="true"></i>
    <div class="vff-notice-body">
        {#if title}<p class="vff-notice-title">{title}</p>{/if}
        {@render children?.()}
    </div>
</div>

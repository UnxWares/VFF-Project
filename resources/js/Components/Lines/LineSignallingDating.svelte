<script>
    import { i18n } from '$lib/i18n.js';
    import { formatLocale } from '$lib/typo.js';
    import { datingNotice } from '$lib/signallingHistory.js';

    /**
     * Mention d'une ligne classique en service dont aucun document libre consulté ne date la mise en service de
     * la signalisation, avec la date de la dernière vérification (App\Services\Signalling\SignallingDating,
     * calculé côté serveur : `dating.status` vaut `undated`). Rien pour une ligne datée, une LGV ou une ligne fermée.
     *
     * @type {{ dating?: { status: string, checked_at: string } | null }}
     */
    let { dating = null } = $props();

    const t = (k, o) => String($i18n.t(k, o));
    const lang = $derived($i18n.language ?? 'fr');
    const notice = $derived(datingNotice(dating, t, formatLocale(lang)));
</script>

{#if notice}
    <p class="dating">
        {notice.message}
        <span class="checked">{notice.checked}</span>
    </p>
{/if}

<style>
    .dating {
        max-width: var(--vff-measure);
        margin: var(--vff-block-space) 0 0;
        font-size: var(--vff-fs-md);
        line-height: 1.6;
        color: var(--vff-text-muted);
    }
    .checked {
        display: block;
        font-size: var(--vff-fs-sm);
    }
</style>

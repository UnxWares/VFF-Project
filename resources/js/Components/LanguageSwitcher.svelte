<script>
    import { i18n, LOCALES } from '$lib/i18n.js';

    /**
     * Choix de la langue : bouton à aria-expanded qui déroule trois liens GET /locale/{code}.
     * Le serveur pose le cookie `locale` puis renvoie vers la page d'origine (rechargement complet :
     * nouvelles traductions et attribut lang de <html>). Ce sont de vrais liens : ils marchent sans
     * JavaScript, au clavier (Tab) et s'ouvrent dans un nouvel onglet au besoin.
     * Échap ferme la liste et rend le focus au bouton. Sous 1 180 px (panneau de l'en-tête), la liste
     * se déroule dans le flux au lieu de flotter.
     */
    let { id = 'vff-lang-menu' } = $props();

    let open = $state(false);
    let wrapperEl = $state();
    let triggerEl = $state();

    const current = $derived(LOCALES.find((l) => l.code === $i18n.language) ?? LOCALES[0]);
    const label = $derived(`${$i18n.t('nav.lang_switch')} (${current.code.toUpperCase()})`);

    function onkeydown(event) {
        if (event.key === 'Escape' && open) {
            event.stopPropagation();
            open = false;
            triggerEl?.focus();
        }
    }

    function onfocusout(event) {
        if (open && event.relatedTarget && !wrapperEl.contains(event.relatedTarget)) {
            open = false;
        }
    }

    function onDocumentClick(event) {
        if (open && wrapperEl && !wrapperEl.contains(event.target)) {
            open = false;
        }
    }
</script>

<svelte:document onclick={onDocumentClick} />

<!-- svelte-ignore a11y_no_static_element_interactions -->
<div class="lang-switcher" bind:this={wrapperEl} {onkeydown} {onfocusout}>
    <button
        type="button"
        class="lang-trigger"
        bind:this={triggerEl}
        aria-expanded={open}
        aria-controls={id}
        aria-label={label}
        title={$i18n.t('nav.lang_switch')}
        onclick={() => (open = !open)}
    >
        <i class="bi bi-translate" aria-hidden="true"></i>
        <span class="code">{current.code.toUpperCase()}</span>
        <i class="bi bi-chevron-down chevron" aria-hidden="true"></i>
    </button>

    <ul class="lang-menu" {id} hidden={!open}>
        {#each LOCALES as l (l.code)}
            <li>
                <a
                    href={`/locale/${l.code}`}
                    hreflang={l.code}
                    lang={l.code}
                    aria-current={current.code === l.code ? 'true' : undefined}
                >
                    <span class="name">{l.name}</span>
                    {#if current.code === l.code}
                        <i class="bi bi-check2" aria-hidden="true"></i>
                    {/if}
                </a>
            </li>
        {/each}
    </ul>
</div>

<style>
    .lang-switcher {
        position: relative;
        display: inline-block;
    }
    .lang-trigger {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        min-height: 40px;
        padding: 0 8px;
        border: 0;
        border-radius: var(--vff-radius-md);
        background: transparent;
        color: inherit;
        font: inherit;
        line-height: 1;
        cursor: pointer;
    }
    .lang-trigger .bi-translate {
        font-size: 18px;
    }
    .code {
        font-family: var(--vff-font-display);
        font-size: var(--vff-fs-sm);
        font-weight: 600;
        letter-spacing: 0.03em;
    }
    .chevron {
        font-size: 11px;
        transition: transform var(--vff-transition);
    }
    .lang-trigger[aria-expanded='true'] .chevron {
        transform: rotate(180deg);
    }

    .lang-menu {
        position: absolute;
        top: calc(100% + 8px);
        right: 0;
        z-index: var(--vff-z-nav);
        min-width: 180px;
        margin: 0;
        padding: 6px;
        list-style: none;
        background-color: var(--vff-bg-elevated);
        border: 1px solid var(--vff-border-strong);
        border-radius: var(--vff-radius-lg);
        box-shadow: var(--vff-shadow-popover);
    }
    .lang-menu[hidden] {
        display: none;
    }
    .lang-menu li {
        display: block;
        margin: 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
    }
    .lang-menu a {
        display: flex;
        align-items: center;
        gap: 10px;
        min-height: 40px;
        padding: 8px 12px;
        border-radius: var(--vff-radius-sm);
        color: var(--vff-text);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-ui);
        text-decoration: none;
        transition:
            background-color var(--vff-transition),
            color var(--vff-transition);
    }
    .lang-menu a:hover {
        background-color: var(--vff-bg-muted);
        color: var(--vff-accent-ink);
    }
    .lang-menu a[aria-current='true'] {
        color: var(--vff-accent-ink);
        font-weight: 600;
    }
    .lang-menu .name {
        flex: 1;
    }

    /* Panneau de l'en-tête (sous 1 180 px) : bouton pleine largeur, liste dans le flux. */
    @media (max-width: 1180px) {
        .lang-switcher {
            display: block;
        }
        .lang-trigger {
            width: 100%;
            min-height: 44px;
            justify-content: flex-start;
            gap: 10px;
        }
        .chevron {
            margin-left: auto;
        }
        .lang-menu {
            position: static;
            min-width: 0;
            margin: 4px 0 0;
            padding: 4px 0 4px 16px;
            border: 0;
            border-left: 2px solid var(--vff-border);
            border-radius: 0;
            box-shadow: none;
            background: transparent;
        }
        .lang-menu a {
            min-height: 44px;
            font-size: var(--vff-fs-md);
        }
    }
</style>

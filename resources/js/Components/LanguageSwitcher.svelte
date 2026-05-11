<script>
    import { i18n, LOCALES } from '$lib/i18n.js';

    let open = $state(false);
    let wrapperEl = $state();

    function toggle() {
        open = !open;
    }

    function select(code) {
        open = false;
        // Navigue vers le endpoint Laravel GET /locale/{code}.
        // Le serveur pose le cookie `locale` via Laravel (chiffrement standard),
        // puis redirige vers la page d'origine (referer). Au chargement de cette
        // page, SetLocale lit le cookie (déchiffré par EncryptCookies) → app
        // locale set → i18next réinitialisé avec les nouvelles traductions.
        // Approche serveur classique, sans JS cookie ni partial reload Inertia.
        window.location.href = `/locale/${code}`;
    }

    function onClickOutside(event) {
        if (open && wrapperEl && !wrapperEl.contains(event.target)) {
            open = false;
        }
    }

    $effect(() => {
        document.addEventListener('click', onClickOutside);
        return () => document.removeEventListener('click', onClickOutside);
    });

    let current = $derived(
        LOCALES.find((l) => l.code === $i18n.language) ?? LOCALES[0],
    );
</script>

<div class="lang-switcher" bind:this={wrapperEl}>
    <button
        type="button"
        class="lang-trigger"
        aria-label={$i18n.t('nav.lang_switch')}
        aria-haspopup="listbox"
        aria-expanded={open}
        onclick={toggle}
    >
        <span class="flag" aria-hidden="true">{current.flag}</span>
        <span class="code">{current.code.toUpperCase()}</span>
    </button>

    {#if open}
        <ul class="lang-menu" role="listbox">
            {#each LOCALES as l}
                <li>
                    <button
                        type="button"
                        role="option"
                        aria-selected={current.code === l.code}
                        class:active={current.code === l.code}
                        onclick={() => select(l.code)}
                    >
                        <span class="flag" aria-hidden="true">{l.flag}</span>
                        <span class="name">{l.name}</span>
                    </button>
                </li>
            {/each}
        </ul>
    {/if}
</div>

<style>
    .lang-switcher {
        position: relative;
        display: inline-block;
    }

    /* Trigger minimaliste : drapeau + code 2 lettres, fond transparent. */
    .lang-trigger {
        all: unset;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 6px;
        cursor: pointer;
        border-radius: var(--vff-radius-sm);
        font-family: inherit;
        color: inherit;
        transition: color var(--vff-transition), background-color var(--vff-transition);
        line-height: 1;
    }
    .lang-trigger:hover {
        color: var(--vff-accent);
        background-color: var(--vff-accent-soft);
    }
    .lang-trigger:focus-visible {
        outline: 2px solid var(--vff-accent);
        outline-offset: 2px;
    }
    .flag {
        font-size: 17px;
        line-height: 1;
    }
    .code {
        font-family: var(--vff-font-display);
        font-size: 14px;
        font-weight: 600;
        letter-spacing: 0.03em;
    }

    .lang-menu {
        position: absolute;
        top: calc(100% + 6px);
        right: 0;
        min-width: 170px;
        margin: 0;
        padding: 6px;
        list-style: none;
        background-color: var(--vff-bg);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        box-shadow: 0 8px 24px var(--vff-shadow-strong);
        z-index: var(--vff-z-overlay);
    }
    .lang-menu li {
        margin: 0;
    }
    .lang-menu button {
        all: unset;
        display: flex;
        width: 100%;
        box-sizing: border-box;
        align-items: center;
        gap: 10px;
        padding: 8px 12px;
        border-radius: var(--vff-radius-sm);
        font-size: 15px;
        font-family: var(--vff-font-body);
        cursor: pointer;
        color: var(--vff-text);
        transition: background-color var(--vff-transition);
    }
    .lang-menu button:hover {
        background-color: var(--vff-bg-muted);
    }
    .lang-menu button.active {
        color: var(--vff-accent);
        font-weight: 600;
    }
    .lang-menu .name {
        flex: 1;
    }
</style>

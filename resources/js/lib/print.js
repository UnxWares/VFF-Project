// Impression des fiches (et enregistrement en PDF depuis le navigateur).
//
// La mise en page est dans resources/css/print.css (@media print). Ce module fournit ce que le CSS
// ne sait pas faire :
//   - le pied de page : adresse de la fiche, date d'impression, numéro de page (variables CSS lues
//     par les marges de page de print.css) ;
//   - les blocs repliables (details) ouverts à l'impression, puis refermés ;
//   - les images à chargement différé, chargées avant l'impression.
// Rien n'est modifié à l'écran : tout est défait à la fin de l'impression.

/** Chaîne CSS littérale sûre (guillemets, barres obliques et retours à la ligne protégés). */
export function cssString(text) {
    return JSON.stringify(String(text).replace(/[\r\n]+/g, ' '));
}

/** Adresse imprimée : sans fragment (#...), avec la requête (filtres d'une liste). */
export function printAddress(href) {
    try {
        const u = new URL(String(href));
        u.hash = '';
        return u.href;
    } catch {
        return String(href).split('#')[0];
    }
}

/**
 * Variables CSS du pied de page.
 *
 * @param {{ href: string, now: Date, locale: string, t: (key: string, options?: object) => string }} input
 * @returns {Record<string, string>}
 */
export function footerVariables({ href, now, locale, t }) {
    let date;
    try {
        date = new Intl.DateTimeFormat(locale, { dateStyle: 'long' }).format(now);
    } catch {
        date = now.toISOString().slice(0, 10);
    }
    return {
        '--vff-print-address': cssString(printAddress(href)),
        '--vff-print-date': cssString(t('common.print.printed_on', { date })),
        '--vff-print-page': cssString(t('common.print.page')),
        '--vff-print-of': cssString(t('common.print.of')),
    };
}

/** Pose les variables du pied de page sur <html>. */
export function applyFooterVariables(root, input) {
    for (const [name, value] of Object.entries(footerVariables(input))) {
        root.style.setProperty(name, value);
    }
}

/**
 * Prépare le document à l'impression ; retourne la fonction qui le remet comme avant.
 *
 * @param {Document} doc
 */
export function prepareForPrint(doc) {
    const opened = [];
    for (const d of doc.querySelectorAll('details:not([open])')) {
        d.setAttribute('open', '');
        opened.push(d);
    }
    // Listes limitées à l'écran (« Afficher les 173 gares », « autres accidents ») : dépliées pour le papier.
    const expanded = [];
    for (const b of /** @type {NodeListOf<HTMLButtonElement>} */ (
        doc.querySelectorAll('main button[aria-expanded="false"]')
    )) {
        b.click();
        expanded.push(b);
    }
    // Liens vers une autre page du site : pas d'adresse imprimée derrière (print.css), seuls les liens externes en portent.
    const internal = [];
    const origin = doc.location?.origin;
    for (const a of /** @type {NodeListOf<HTMLAnchorElement>} */ (
        doc.querySelectorAll('main a[href^="http"]')
    )) {
        if (origin && a.href.startsWith(origin + '/')) {
            a.setAttribute('data-print-internal', '');
            internal.push(a);
        }
    }
    const eager = [];
    for (const img of /** @type {NodeListOf<HTMLImageElement>} */ (
        doc.querySelectorAll('img[loading="lazy"]')
    )) {
        img.loading = 'eager';
        eager.push(img);
    }
    return () => {
        for (const b of expanded) {
            if (b.isConnected && b.getAttribute('aria-expanded') === 'true') b.click();
        }
        for (const a of internal) a.removeAttribute('data-print-internal');
        for (const d of opened) d.removeAttribute('open');
        for (const img of eager) img.loading = 'lazy';
    };
}

/**
 * Branche l'impression : à la demande du navigateur (Ctrl+P, menu, bouton d'enregistrement en PDF).
 *
 * @param {Window} win
 * @param {(key: string, options?: object) => string} t
 * @param {() => string} getLocale
 * @returns {() => void} la fonction qui débranche
 */
export function installPrint(win, t, getLocale) {
    let restore = null;
    const before = () => {
        applyFooterVariables(win.document.documentElement, {
            href: win.location.href,
            now: new Date(),
            locale: getLocale(),
            t,
        });
        restore?.();
        restore = prepareForPrint(win.document);
    };
    const after = () => {
        restore?.();
        restore = null;
    };
    win.addEventListener('beforeprint', before);
    win.addEventListener('afterprint', after);
    return () => {
        win.removeEventListener('beforeprint', before);
        win.removeEventListener('afterprint', after);
    };
}

// Langue des documentations d'API (RGAA 8.7, WCAG 3.1.2). La spécification OpenAPI de VFF est rédigée en
// français, quelle que soit la langue du site : dans Swagger UI (interface en anglais, page déclarée en
// anglais) comme dans Scalar (interface dans la langue du site), les textes de la spécification portent
// `lang="fr"`. GraphiQL n'existe qu'en anglais : sa zone porte `lang="en"` et ses fenêtres, que React
// ajoute directement au <body> hors de cette zone, sont marquées par markPortalLanguage.

/**
 * Textes de la spécification que Scalar affiche (sélecteurs CSS) : descriptions rédigées en markdown
 * (introduction, opérations, paramètres, propriétés, réponses), titre de l'API, titres des groupes et des
 * opérations (menu et pages), description de chaque réponse dans les onglets. Les libellés propres à
 * Scalar (Introduction, Models, Query Parameters…) restent dans la langue du site et ne sont pas
 * sélectionnés : `a[href^="#tag/"]` ne vise que les entrées du menu issues de la spécification, et les
 * titres de section ne sont pris que dans les groupes d'opérations (`/tag/` dans l'identifiant).
 */
export const SCALAR_SPEC_CONTENT = [
    '.markdown',
    'h1.section-header-label',
    '[id*="/tag/"] .section-header-label',
    '.operation-title',
    'a[href^="#tag/"]',
    '[role="tab"] > span > span:last-child',
].join(', ');

/**
 * Pose `lang` sur chaque élément qui correspond à `selector` dans `root`, maintenant et à mesure que
 * l'interface tierce affiche de nouveaux blocs (dépliage d'une opération, filtre, schémas).
 *
 * @param {Element} root
 * @param {string} selector
 * @param {string} lang
 * @returns {MutationObserver}
 */
export function markContentLanguage(root, selector, lang) {
    const mark = (scope) => {
        if (scope.matches?.(selector)) scope.lang = lang;
        scope.querySelectorAll?.(selector).forEach((el) => {
            el.lang = lang;
        });
    };
    mark(root);
    const observer = new MutationObserver((mutations) => {
        for (const mutation of mutations)
            mutation.addedNodes.forEach((node) => node.nodeType === 1 && mark(node));
    });
    observer.observe(root, { childList: true, subtree: true });

    return observer;
}

/**
 * Pose `lang` sur les éléments que l'interface ajoute directement au <body> après le chargement
 * (fenêtres de dialogue, bulles d'aide, menus : portails de React), qui sortent de la zone déjà
 * déclarée dans la bonne langue. Les éléments présents au chargement (barre du site) ne sont pas touchés.
 *
 * @param {Element} body
 * @param {string} lang
 * @returns {MutationObserver}
 */
export function markPortalLanguage(body, lang) {
    const inert = new Set(['SCRIPT', 'STYLE', 'LINK', 'NOSCRIPT', 'TEMPLATE']);
    const observer = new MutationObserver((mutations) => {
        for (const mutation of mutations)
            mutation.addedNodes.forEach((node) => {
                if (!(node.nodeType === 1)) return;
                const element = /** @type {Element & {lang: string}} */ (node);
                if (!inert.has(element.tagName) && !element.getAttribute('lang'))
                    element.lang = lang;
            });
    });
    observer.observe(body, { childList: true });

    return observer;
}

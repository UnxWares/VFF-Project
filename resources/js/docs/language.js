// Langue des documentations d'API (RGAA 8.7, WCAG 3.1.2). L'interface de Swagger UI est en anglais
// (le paquet n'est pas traduit) alors que la spécification OpenAPI de VFF est rédigée en français :
// la page est déclarée en anglais et les textes de la spécification gardent leur langue.

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

// Blocs de code de Scalar (RGAA 7.3, WCAG 2.1.1) : un bloc plus large que son cadre défile, et doit alors se
// parcourir au clavier. Scalar ne pose ni `tabindex` ni nom : on ajoute les deux, à mesure que l'interface
// affiche de nouveaux exemples. Le rôle `region` donne un sens au bloc focalisable.

const LABELS = {
    fr: 'Exemple de code',
    en: 'Code example',
    de: 'Codebeispiel',
};

/**
 * @param {Element} root
 * @param {string} locale
 * @returns {MutationObserver}
 */
export function makeCodeBlocksFocusable(root, locale) {
    const label = LABELS[locale] ?? LABELS.en;
    const mark = () => {
        root.querySelectorAll('pre > code.hljs, pre > code[class*="language-"]').forEach((code) => {
            if (code.hasAttribute('tabindex')) return;
            const language = [...code.classList]
                .find((c) => c.startsWith('language-'))
                ?.slice('language-'.length);
            code.tabIndex = 0;
            code.setAttribute('role', 'region');
            code.setAttribute('aria-label', language ? `${label} (${language})` : label);
        });
    };
    mark();
    const observer = new MutationObserver(mark);
    observer.observe(root, { childList: true, subtree: true });
    return observer;
}

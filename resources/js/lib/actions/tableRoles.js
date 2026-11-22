// Attachment Svelte : garde la sémantique de tableau quand le CSS change l'`display` des éléments
// (tableaux affichés en cartes sur téléphone). Sans `display: table*`, Firefox et Safari peuvent
// retirer les rôles de tableau, de ligne et de cellule ; on pose alors les rôles ARIA explicites,
// et on les retire dès que l'affichage redevient celui d'un vrai tableau (aucun doublon sur ordinateur).
// Usage : <table {@attach tableRoles}>...</table>

/** `display` natif de chaque balise de tableau. */
const NATIVE_DISPLAY = {
    TABLE: 'table',
    THEAD: 'table-header-group',
    TBODY: 'table-row-group',
    TFOOT: 'table-footer-group',
    TR: 'table-row',
    TH: 'table-cell',
    TD: 'table-cell',
};

const SELECTOR = 'table, thead, tbody, tfoot, tr, th, td';

/**
 * Rôle explicite à poser, ou null si l'élément garde sa sémantique native.
 * @param {string} tag nom de balise en majuscules
 * @param {string} display valeur calculée de `display`
 * @param {{ scope?: string|null, inHead?: boolean }} [context]
 * @returns {string|null}
 */
export function roleFor(tag, display, { scope = null, inHead = false } = {}) {
    const native = NATIVE_DISPLAY[tag];
    if (!native || display === native || display === 'none') return null;
    if (tag === 'TABLE') return 'table';
    if (tag === 'TR') return 'row';
    if (tag === 'TD') return 'cell';
    if (tag === 'TH') {
        if (scope === 'row' || scope === 'rowgroup') return 'rowheader';
        return scope === 'col' || inHead ? 'columnheader' : 'cell';
    }
    return 'rowgroup';
}

/** @param {HTMLTableElement} table */
function apply(table) {
    /** @type {HTMLElement[]} */
    const elements = [table, ...table.querySelectorAll(SELECTOR)].map(
        (el) => /** @type {HTMLElement} */ (el),
    );
    for (const el of elements) {
        const role = roleFor(el.tagName, getComputedStyle(el).display, {
            scope: el.getAttribute('scope'),
            inHead: el.closest('thead') !== null,
        });
        if (role) {
            if (el.getAttribute('role') !== role) el.setAttribute('role', role);
            el.dataset.tableRole = '';
        } else if (el.dataset.tableRole !== undefined) {
            el.removeAttribute('role');
            delete el.dataset.tableRole;
        }
    }
}

/** @param {HTMLTableElement} table */
export function tableRoles(table) {
    let frame = 0;
    const schedule = () => {
        cancelAnimationFrame(frame);
        frame = requestAnimationFrame(() => apply(table));
    };
    schedule();

    const resize = new ResizeObserver(schedule);
    resize.observe(table);
    const mutations = new MutationObserver(schedule);
    mutations.observe(table, { childList: true, subtree: true });
    window.addEventListener('resize', schedule);

    return () => {
        cancelAnimationFrame(frame);
        resize.disconnect();
        mutations.disconnect();
        window.removeEventListener('resize', schedule);
    };
}

// Corrections d'accessibilité (RGAA) de Swagger UI, par son mécanisme de greffons (`wrapComponents`) :
// le paquet swagger-ui-dist n'est pas modifié, seuls trois de ses composants sont enveloppés.
// - BaseLayout : Swagger UI ouvre sa propre zone `<main id="operations">` alors que la page en a déjà
//   une (resources/views/docs/swagger.blade.php, cible du lien d'évitement) : la sienne devient un
//   `div` (RGAA 9.2, une seule zone principale) ;
// - OperationSummaryPath : le chemin de chaque opération était un lien (`a.nostyle`, lien profond)
//   rangé dans le bouton qui déplie l'opération (RGAA 8.2). Le lien devient un simple texte ; l'adresse
//   de la page suit toujours l'opération ouverte (option deepLinking), et chaque opération garde son
//   bouton « copier le chemin » ;
// - FilterContainer : le champ « Filter by tag » n'avait qu'un texte indicatif ; il reçoit une
//   étiquette visible (RGAA 11.1), en anglais comme le reste de l'interface.

/** Identifiant du champ de filtre, relié à son étiquette. */
export const FILTER_INPUT_ID = 'vff-swagger-filter';
/** Texte de l'étiquette du filtre (celui du texte indicatif de Swagger UI). */
export const FILTER_LABEL = 'Filter by tag';

/**
 * Partie de React que Swagger UI fournit dans `system.React` (React n'est pas une dépendance du projet :
 * il est inclus dans swagger-ui-dist) et que ce module utilise.
 *
 * @typedef {object} ReactApi
 * @property {(node: unknown) => boolean} isValidElement
 * @property {(type: any, props?: Record<string, any> | null, ...children: unknown[]) => any} createElement
 * @property {(element: any, props?: Record<string, any>, ...children: unknown[]) => any} cloneElement
 * @property {unknown} Fragment
 */

/**
 * Parcourt un arbre d'éléments React et remplace ceux que `change` transforme. Un élément inchangé
 * (et tout son sous-arbre) est rendu tel quel, sans copie.
 *
 * @param {unknown} node élément, tableau d'éléments ou valeur rendue (texte, null…)
 * @param {(element: any) => unknown} change renvoie le remplaçant, ou l'élément lui-même
 * @param {ReactApi} React
 * @returns {unknown}
 */
export function mapElements(node, change, React) {
    if (Array.isArray(node)) {
        let changed = false;
        const out = node.map((child) => {
            const next = mapElements(child, change, React);
            changed ||= next !== child;
            return next;
        });
        return changed ? out : node;
    }
    if (!React.isValidElement(node)) return node;

    const element = /** @type {any} */ (node);
    const children = element.props.children;
    const nextChildren = children === undefined ? children : mapElements(children, change, React);
    const current =
        nextChildren === children
            ? element
            : React.cloneElement(element, undefined, ...[nextChildren].flat());

    return change(current);
}

/**
 * Change le type d'un élément HTML en gardant ses propriétés, sa clé et ses enfants.
 *
 * @param {any} element
 * @param {string} type
 * @param {ReactApi} React
 */
function retype(element, type, React) {
    const { children, ...props } = element.props;

    return React.createElement(type, { ...props, key: element.key }, children);
}

/**
 * Remplace la zone `<main>` de Swagger UI par un `div` (mêmes attributs).
 *
 * @param {unknown} tree
 * @param {ReactApi} React
 */
export function withoutMain(tree, React) {
    return mapElements(tree, (el) => (el.type === 'main' ? retype(el, 'div', React) : el), React);
}

/**
 * Donne une étiquette visible au champ de filtre des rubriques.
 *
 * @param {unknown} tree
 * @param {ReactApi} React
 */
export function withFilterLabel(tree, React) {
    return mapElements(
        tree,
        (el) => {
            if (
                el.type !== 'input' ||
                !String(el.props.className ?? '').includes('operation-filter-input')
            )
                return el;

            return React.createElement(
                React.Fragment,
                { key: el.key },
                React.createElement(
                    'label',
                    { htmlFor: FILTER_INPUT_ID, className: 'vff-swagger-filter-label' },
                    FILTER_LABEL,
                ),
                React.cloneElement(el, { id: FILTER_INPUT_ID }),
            );
        },
        React,
    );
}

/**
 * Retire le lien profond du chemin d'une opération : l'élément DeepLink (propriétés `path` et `text`)
 * est remplacé par son texte. Rendu avec `enabled: false`, DeepLink garderait un `<a>` sans adresse.
 *
 * @param {unknown} tree
 * @param {ReactApi} React
 */
export function withoutDeepLink(tree, React) {
    return mapElements(
        tree,
        (el) =>
            typeof el.type !== 'string' && 'path' in el.props && 'text' in el.props
                ? React.createElement('span', { key: el.key }, el.props.text)
                : el,
        React,
    );
}

/** @param {unknown} type */
const isClassComponent = (type) =>
    typeof type === 'function' && typeof type.prototype?.render === 'function';

/**
 * Enveloppe (pour `wrapComponents`) qui applique `transform` au rendu du composant d'origine.
 *
 * Swagger UI entoure déjà certains composants d'un garde-fou d'erreurs (greffon SafeRender) : le
 * composant reçu rend alors un seul élément, ErrorBoundary, qui contient le vrai composant. Dans ce cas,
 * le vrai composant est remplacé par une sous-classe qui transforme son rendu (créée une seule fois par
 * composant, pour que React ne le recrée pas à chaque rendu). Aucun nom de composant n'est lu : le code
 * reste juste une fois minifié.
 *
 * @param {(tree: unknown, React: any) => unknown} transform
 * @returns {(Original: any, system: any) => any}
 */
export function transformRender(transform) {
    const subclasses = new WeakMap();
    const transformed = (Base, React) => {
        if (!subclasses.has(Base))
            subclasses.set(
                Base,
                class extends Base {
                    render() {
                        return transform(super.render(), React);
                    }
                },
            );

        return subclasses.get(Base);
    };

    return (Original, system) => {
        const React = system.React;

        return class extends Original {
            render() {
                const tree = super.render();
                if (!React.isValidElement(tree) || !isClassComponent(tree.type))
                    return transform(tree, React);

                // Garde-fou : seuls ses enfants (le vrai composant) sont remplacés, pas lui-même.
                const children = tree.props.children;
                const swapped = mapElements(
                    children,
                    (el) =>
                        isClassComponent(el.type)
                            ? React.createElement(transformed(el.type, React), {
                                  ...el.props,
                                  key: el.key,
                                  ref: el.ref,
                              })
                            : el,
                    React,
                );

                return swapped === children
                    ? tree
                    : React.cloneElement(tree, undefined, ...[swapped].flat());
            }
        };
    };
}

/**
 * Greffon Swagger UI qui applique les trois corrections.
 *
 * @returns {{wrapComponents: Record<string, (Original: any, system: any) => any>}}
 */
export function accessibilityPlugin() {
    return {
        wrapComponents: {
            BaseLayout: transformRender(withoutMain),
            FilterContainer: transformRender(withFilterLabel),
            OperationSummaryPath: transformRender(withoutDeepLink),
        },
    };
}

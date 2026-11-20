// Copies de pages servies hors ligne : le service worker ajoute aux propriétés Inertia de la page la date de
// la copie (`offline_copy.saved_at`), que le composant OfflineCopyNotice affiche. Fonctions pures, testées
// par tests/js/pwa-offline-copy.test.js.
import { OFFLINE_COPY_PROP } from './rules.js';

/** Balise où app.blade.php écrit l'objet page d'Inertia (JSON encodé avec JSON_HEX_TAG). */
const PAGE_SCRIPT = /(<script\b[^>]*\bdata-page="app"[^>]*>)([\s\S]*?)(<\/script>)/;

/** JSON sûr dans une balise <script> : `<` et `>` échappés, comme JSON_HEX_TAG côté serveur. */
function scriptSafeJson(value) {
    return JSON.stringify(value).replace(/</g, '\\u003C').replace(/>/g, '\\u003E');
}

/**
 * Ajoute la date de la copie aux propriétés d'un objet page d'Inertia.
 *
 * @param {{props?: Record<string, unknown>}} page
 * @param {string | null} savedAt
 */
export function markPage(page, savedAt) {
    return { ...page, props: { ...page.props, [OFFLINE_COPY_PROP]: { saved_at: savedAt } } };
}

/** Objet page contenu dans un document HTML, ou null s'il est introuvable ou illisible. */
export function readPageFromHtml(html) {
    const match = PAGE_SCRIPT.exec(html);
    if (!match) return null;
    try {
        return JSON.parse(match[2]);
    } catch {
        return null;
    }
}

/** Remplace l'objet page d'un document HTML ; null si la balise est introuvable. */
export function writePageToHtml(html, page) {
    if (!PAGE_SCRIPT.test(html)) return null;
    // Fonction de remplacement : un « $ » du JSON n'est pas interprété comme motif.
    return html.replace(
        PAGE_SCRIPT,
        (_all, open, _json, close) => open + scriptSafeJson(page) + close,
    );
}

/** Document HTML d'une copie, marqué de sa date ; inchangé si l'objet page est introuvable. */
export function markHtmlCopy(html, savedAt) {
    const page = readPageFromHtml(html);
    return page ? (writePageToHtml(html, markPage(page, savedAt)) ?? html) : html;
}

/**
 * Props « once » sans lesquelles une page ne peut pas s'afficher : tous les textes de l'interface viennent
 * des traductions. Les autres (menus des filtres des accidents) ont une valeur par défaut dans leur page.
 */
export const ESSENTIAL_ONCE_PROPS = ['translations'];

/** Valeur d'une prop à un chemin pointé (« a.b »), comme Inertia nomme les props « once ». */
function getPath(object, path) {
    return path.split('.').reduce((value, key) => (value == null ? undefined : value[key]), object);
}

/** Copie d'un objet où la valeur du chemin pointé est remplacée (objets intermédiaires copiés). */
function setPath(object, path, value) {
    const [key, ...rest] = path.split('.');
    return {
        ...object,
        [key]: rest.length ? setPath(object?.[key] ?? {}, rest.join('.'), value) : value,
    };
}

/**
 * Clés des props « once » qu'un objet page annonce sans les porter. Inertia ne renvoie pas une prop « once »
 * que la page d'où part la visite a déjà (en-tête X-Inertia-Except-Once-Props) : la copie rangée de cette
 * réponse ne l'a donc pas (`translations.fr`, `accidents.facets`).
 *
 * @param {{props?: object, onceProps?: Record<string, {prop: string}>}} page
 * @returns {string[]}
 */
export function missingOnceProps(page) {
    return Object.entries(page.onceProps ?? {})
        .filter(([, once]) => getPath(page.props, once.prop) === undefined)
        .map(([key]) => key);
}

/**
 * La page a-t-elle toutes ses props « once » indispensables (ESSENTIAL_ONCE_PROPS) ? `provided` : clés que
 * le navigateur recollera lui-même, parce que sa page courante les porte.
 *
 * @param {{props?: object, onceProps?: Record<string, {prop: string}>}} page
 * @param {string[]} [provided]
 */
export function hasEssentialOnceProps(page, provided = []) {
    return missingOnceProps(page).every(
        (key) => provided.includes(key) || !ESSENTIAL_ONCE_PROPS.includes(page.onceProps[key].prop),
    );
}

/**
 * Reprend les props « once » qui manquent à une copie dans l'objet page d'une autre copie qui les porte
 * sous la même clé : même langue pour les traductions (`translations.fr`), mêmes menus pour les
 * accidents. Une clé différente (`translations.en`) ne remplace jamais la valeur attendue. L'expiration
 * suit la valeur reprise.
 *
 * @param {{props?: object, onceProps?: Record<string, {prop: string, expiresAt?: number | null}>}} page
 * @param {{props?: object, onceProps?: Record<string, {prop: string, expiresAt?: number | null}>} | null} donor
 */
export function fillOnceProps(page, donor) {
    const missing = missingOnceProps(page);
    if (missing.length === 0 || !donor?.onceProps) return page;

    let props = page.props ?? {};
    const onceProps = { ...page.onceProps };
    for (const key of missing) {
        const given = donor.onceProps[key];
        const value = given ? getPath(donor.props, given.prop) : undefined;
        if (value === undefined) continue;
        props = setPath(props, onceProps[key].prop, value);
        onceProps[key] = { ...onceProps[key], expiresAt: given.expiresAt ?? null };
    }
    return { ...page, props, onceProps };
}

/**
 * Document HTML d'une page connue seulement par sa réponse Inertia : le document d'une autre page déjà
 * rangée sert d'enveloppe (mêmes fichiers du même déploiement), avec l'objet page de la copie. Les props
 * « once » absentes de la copie sont reprises de l'enveloppe quand elle les porte sous la même clé
 * (fillOnceProps). Inertia remet ensuite le titre et la langue de la page. Null si l'enveloppe ne convient
 * pas, ou s'il manque encore une prop indispensable (traductions d'une autre langue) : mieux vaut la page
 * « hors ligne » qu'une page sans texte.
 *
 * @param {string} shellHtml
 * @param {object} page
 * @param {string | null} savedAt
 */
export function htmlFromInertiaCopy(shellHtml, page, savedAt) {
    const complete = fillOnceProps(page, readPageFromHtml(shellHtml));
    if (!hasEssentialOnceProps(complete)) return null;
    return writePageToHtml(shellHtml, markPage(complete, savedAt));
}

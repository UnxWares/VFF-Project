// Bootstrap de l'i18n côté front avec i18next, exposé aux composants par un store Svelte.
//
// Source canonique des traductions : Laravel `lang/{locale}/*.php`, aplaties
// par `App\Support\Translations` et servies dans un fichier JSON par langue
// (`/i18n/{locale}/{empreinte}.json`) dont chaque page donne l'adresse
// (prop `translations_url`, préchargée par app.blade.php). Le navigateur le
// garde en cache : il n'est plus recopié dans chaque page.
//
// Au démarrage puis à chaque navigation Inertia, applyPageTranslations() lit
// ce fichier s'il n'est pas déjà chargé (même adresse : rien à faire), puis
// le donne à i18next (`addResourceBundle`, `changeLanguage`). Les pages
// d'erreur rendues hors du groupe web portent encore les chaînes elles-mêmes
// (prop `translations`), utilisées telles quelles.
//
// Usage dans un composant :
//   import { i18n } from '$lib/i18n.js';
//   <h1>{$i18n.t('home.title')}</h1>
//   <p>{$i18n.t('hello', { name: 'Baptiste' })}</p>

import i18next from 'i18next';
import { writable } from 'svelte/store';

export const SUPPORTED = ['fr', 'en', 'de'];
export const CANONICAL = 'fr';

export const LOCALES = [
    { code: 'fr', name: 'Français' },
    { code: 'en', name: 'English' },
    { code: 'de', name: 'Deutsch' },
];

/**
 * Convertit les placeholders style Laravel `:name` en style i18next `{{name}}`
 * pour rester compatible avec la même source côté back.
 * Un deux-points collé à un mot (`mailto:contact@…`, `10:30`) n'introduit pas
 * de placeholder : il est laissé tel quel (sinon les liens mailto: des pages
 * légales devenaient `mailto{{contact}}@…`).
 */
function normalize(dict) {
    const out = {};
    for (const [key, value] of Object.entries(dict)) {
        out[key] =
            typeof value === 'string'
                ? value.replace(/(^|[^A-Za-z0-9_]):([a-zA-Z_][a-zA-Z0-9_]*)/g, '$1{{$2}}')
                : value;
    }
    return out;
}

let bootstrapped = false;

export function setupI18n({ locale, translations }) {
    const safeLocale = SUPPORTED.includes(locale) ? locale : CANONICAL;
    const resources = {
        [safeLocale]: { translation: normalize(translations ?? {}) },
    };

    if (!bootstrapped) {
        i18next.init({
            lng: safeLocale,
            fallbackLng: CANONICAL,
            supportedLngs: SUPPORTED,
            resources,
            // Un seul espace de noms (`translation`) : « : » fait partie des clés Laravel
            // (ex. stations.sources.names.inferred:line_event), ce n'est pas un séparateur.
            nsSeparator: false,
            interpolation: {
                escapeValue: false, // Svelte échappe déjà ; on autorise le HTML dans certaines clés `legal.*`
            },
            returnEmptyString: false,
            returnNull: false,
        });
        bootstrapped = true;
        return;
    }

    // Recharge après navigation Inertia ou changement de langue (null : chaînes déjà chargées).
    if (translations) {
        i18next.addResourceBundle(
            safeLocale,
            'translation',
            normalize(translations),
            true, // deep merge
            true, // overwrite
        );
    }
    if (i18next.language !== safeLocale) {
        i18next.changeLanguage(safeLocale);
    }
}

/**
 * Store qui republie l'instance i18next à chaque initialisation, ajout de chaînes ou changement de
 * langue : `$i18n.t(…)` se recalcule alors dans les composants. Un objet n'est jamais « égal » pour un
 * store Svelte, donc `set(i18next)` notifie même si l'instance reste la même.
 * (Remplace le paquet svelte-i18next, plus maintenu depuis 2023, qui ne faisait que cela.)
 *
 * @param {import('i18next').i18n} instance
 * @returns {import('svelte/store').Readable<import('i18next').i18n>}
 */
export function createI18nStore(instance) {
    const store = writable(instance);
    const publish = () => store.set(instance);
    for (const event of ['initialized', 'loaded', 'added', 'languageChanged']) {
        instance.on(event, publish);
    }
    return { subscribe: store.subscribe };
}

/** Adresse du dictionnaire déjà donné à i18next (null : aucun, ou chaînes reçues dans la page). */
let loadedUrl = null;

/** Dictionnaires déjà demandés, par adresse : deux appels rapprochés ne lancent qu'une requête. */
const pending = new Map();

/**
 * Lit le dictionnaire d'une adresse (JSON plat « clé » => « texte »). Même mode que la balise
 * `<link rel="preload" as="fetch" crossorigin="anonymous">` de la page, pour que le navigateur
 * réutilise la réponse préchargée. Deux essais : une coupure passagère ne laisse pas la page sans texte.
 *
 * @param {string} url
 * @param {typeof fetch} fetchImpl
 * @returns {Promise<Record<string, string>>}
 */
export function fetchTranslations(url, fetchImpl = fetch) {
    if (!pending.has(url)) {
        const once = () =>
            fetchImpl(url, { credentials: 'same-origin' }).then((response) => {
                if (!response.ok)
                    throw new Error(`Traductions illisibles (${response.status}) : ${url}`);
                return response.json();
            });
        const request = once()
            .catch(once)
            .finally(() => pending.delete(url));
        pending.set(url, request);
    }
    return pending.get(url);
}

/**
 * Donne à i18next les chaînes d'une page Inertia : celles qu'elle porte (`translations`, pages d'erreur),
 * sinon le dictionnaire de son adresse (`translations_url`), lu seulement s'il change.
 * Sans réseau ni copie en cache, i18next garde ce qu'il avait (au pire, les clés s'affichent).
 *
 * @param {{locale?: string, translations?: Record<string, string>, translations_url?: string, [key: string]: unknown}} props
 * @param {typeof fetch} [fetchImpl]
 */
export async function applyPageTranslations(props, fetchImpl = fetch) {
    const { locale, translations, translations_url: url } = props ?? {};
    if (translations) {
        setupI18n({ locale, translations });
        loadedUrl = null;
        return;
    }
    if (!url) {
        setupI18n({ locale, translations: {} });
        return;
    }
    if (url === loadedUrl) {
        setupI18n({ locale, translations: null });
        return;
    }
    try {
        const dict = await fetchTranslations(url, fetchImpl);
        setupI18n({ locale, translations: dict });
        loadedUrl = url;
    } catch (error) {
        console.error(error);
        setupI18n({ locale, translations: {} });
    }
}

export const i18n = createI18nStore(i18next);

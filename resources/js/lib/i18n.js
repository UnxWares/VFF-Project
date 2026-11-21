// Bootstrap de l'i18n côté front avec i18next, exposé aux composants par un store Svelte.
//
// Source canonique des traductions : Laravel `lang/{locale}/*.php`, aplaties
// par `App\Support\Translations` et partagées à Inertia via HandleInertiaRequests.
//
// On initialise i18next au boot avec la locale courante + ses chaînes (pas de
// fetch HTTP supplémentaire). À chaque changement de langue, on POST /locale
// et Inertia recharge le bundle avec les nouvelles chaînes : on les réinjecte
// dans i18next via `addResourceBundle` et on appelle `changeLanguage`.
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

    // Recharge après navigation Inertia ou changement de langue
    i18next.addResourceBundle(
        safeLocale,
        'translation',
        normalize(translations ?? {}),
        true, // deep merge
        true, // overwrite
    );
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

export const i18n = createI18nStore(i18next);

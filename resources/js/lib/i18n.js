// Bootstrap de l'i18n côté front avec i18next + svelte-i18next.
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
import { createI18nStore } from 'svelte-i18next';

export const SUPPORTED = ['fr', 'en', 'de'];
export const CANONICAL = 'fr';

export const LOCALES = [
    { code: 'fr', name: 'Français', flag: '🇫🇷' },
    { code: 'en', name: 'English', flag: '🇬🇧' },
    { code: 'de', name: 'Deutsch', flag: '🇩🇪' },
];

/**
 * Convertit les placeholders style Laravel `:name` en style i18next `{{name}}`
 * pour rester compatible avec la même source côté back.
 */
function normalize(dict) {
    const out = {};
    for (const [key, value] of Object.entries(dict)) {
        out[key] =
            typeof value === 'string'
                ? value.replace(/:([a-zA-Z_][a-zA-Z0-9_]*)/g, '{{$1}}')
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

export const i18n = createI18nStore(i18next);

// Textes de MapLibre (boutons, message « deux doigts »…) dans la langue de la page, pour toutes les cartes :
// `$lib/maplibre.js` donne l'option `locale` à chaque `new Map()`, d'après les clés `maps.controls.*`.
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { describe, expect, it, vi } from 'vitest';
import { setupI18n } from '$lib/i18n.js';
import { mapControlsLocale, withPageLocale } from '$lib/mapControls.js';

vi.mock('maplibre-gl', () => ({
    Map: class {
        constructor(options) {
            this.options = options;
        }
    },
    setWorkerUrl: vi.fn(),
}));
vi.mock('maplibre-gl/dist/maplibre-gl-worker.mjs?url', () => ({ default: '/worker.mjs' }));

const read = (path) =>
    readFileSync(fileURLToPath(new URL(`../../${path}`, import.meta.url)), 'utf8');

/** Textes anglais d'origine de MapLibre, lus dans sa source. */
const englishDefaults = Object.fromEntries(
    [
        ...read('node_modules/maplibre-gl/src/ui/default_locale.ts').matchAll(
            /'([A-Za-z.]+)': '([^']*)'/g,
        ),
    ].map((match) => [match[1], match[2]]),
);

/** Bloc `controls` de `lang/<langue>/maps.php`, sous forme { clé: texte }. */
function controls(lang) {
    const php = read(`lang/${lang}/maps.php`);
    const block = php.slice(php.indexOf("'controls' => ["));
    return Object.fromEntries(
        [...block.matchAll(/^\s+'([a-z_]+)' => '((?:[^'\\]|\\.)*)'/gm)].map((match) => [
            match[1],
            match[2],
        ]),
    );
}

const keys = Object.values(mapControlsLocale((key) => key));

describe('Textes de MapLibre traduits', () => {
    it('couvre tous les textes de MapLibre, sauf les unités de l’échelle (m, km)', () => {
        const covered = Object.keys(mapControlsLocale((key) => key));
        const missing = Object.keys(englishDefaults).filter(
            (key) => !key.startsWith('ScaleControl.') && !covered.includes(key),
        );
        expect(Object.keys(englishDefaults).length).toBeGreaterThan(20);
        expect(missing).toEqual([]);
    });

    it.each(['fr', 'en', 'de'])('a chaque clé maps.controls.* en %s', (lang) => {
        const dict = controls(lang);
        for (const key of keys) {
            const name = key.replace('maps.controls.', '');
            expect(dict[name], `${lang} : ${key}`).toBeTruthy();
        }
    });

    it.each(['fr', 'de'])(
        'n’écrit plus les boutons ni le message « deux doigts » en anglais (%s)',
        (lang) => {
            const dict = controls(lang);
            const locale = mapControlsLocale((key) => dict[key.replace('maps.controls.', '')]);
            for (const id of [
                'CooperativeGesturesHandler.MobileHelpText',
                'CooperativeGesturesHandler.WindowsHelpText',
                'NavigationControl.ZoomIn',
                'NavigationControl.ResetBearing',
                'FullscreenControl.Enter',
                'AttributionControl.ToggleAttribution',
            ]) {
                expect(locale[id], id).not.toBe(englishDefaults[id]);
            }
        },
    );

    it('garde les textes propres à une carte et ses autres options', () => {
        const options = withPageLocale(
            {
                container: 'carte',
                cooperativeGestures: true,
                locale: { 'Map.Title': 'Carte de la ligne' },
            },
            (key) => `[${key}]`,
        );
        expect(options.container).toBe('carte');
        expect(options.cooperativeGestures).toBe(true);
        expect(options.locale['Map.Title']).toBe('Carte de la ligne');
        expect(options.locale['NavigationControl.ZoomIn']).toBe('[maps.controls.zoom_in]');
    });

    it('donne la langue de la page à chaque carte créée par $lib/maplibre.js', async () => {
        setupI18n({
            locale: 'de',
            translations: {
                'maps.controls.gestures_mobile': 'Verschieben Sie die Karte mit zwei Fingern',
                'maps.controls.zoom_in': 'Vergrößern',
            },
        });
        const { default: maplibregl } = await import('$lib/maplibre.js');
        const map = new maplibregl.Map(/** @type {any} */ ({ container: 'x' }));
        const { locale } = /** @type {any} */ (map).options;
        expect(locale['CooperativeGesturesHandler.MobileHelpText']).toBe(
            'Verschieben Sie die Karte mit zwei Fingern',
        );
        expect(locale['NavigationControl.ZoomIn']).toBe('Vergrößern');
        expect(maplibregl.setWorkerUrl).toHaveBeenCalledWith('/worker.mjs');
    });
});

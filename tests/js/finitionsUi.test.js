// Finitions d'interface du 21/11/2026 : nombres des pages d'audit (espace insécable des milliers),
// langue des titres de « Documents et archives » (RGAA 8.7), nom de la figure du profil en long, contrastes
// des crédits et du message « deux doigts » de MapLibre (WCAG 1.4.3).
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { render } from 'svelte/server';
import { describe, expect, it } from 'vitest';
import { setupI18n } from '$lib/i18n.js';
import { referenceLang } from '$lib/accidentContext.js';
import { formatNumber } from '$lib/typo.js';
import AccidentContext from '../../resources/js/Components/Accidents/AccidentContext.svelte';

setupI18n({
    locale: 'en',
    translations: {
        'common.new_tab': 'opens in a new tab',
        'accidents.context.references_title': 'Documents and archives',
    },
});

const read = (path) =>
    readFileSync(fileURLToPath(new URL(`../../${path}`, import.meta.url)), 'utf8');

describe('Nombres des pages d’audit', () => {
    it.each([
        'resources/js/Pages/Validate/AuditSegments.svelte',
        'resources/js/Pages/Validate/AuditStations.svelte',
        'resources/js/Pages/Validate/Graph.svelte',
    ])('%s passe par formatNumber, jamais par toLocaleString', (path) => {
        const code = read(path);
        expect(code).not.toMatch(/toLocaleString\(/);
        expect(code).toMatch(/import \{ formatNumber \} from '\$lib\/typo\.js'/);
    });

    it('sépare les milliers et le signe % par une espace insécable ordinaire', () => {
        expect(formatNumber(12345, 'fr')).toBe('12 345');
        expect(formatNumber(0.125, 'fr', { style: 'percent', maximumFractionDigits: 1 })).toBe(
            '12,5 %',
        );
        expect(formatNumber(12345, 'en')).toBe('12,345');
    });
});

describe('Langue des titres des documents (RGAA 8.7)', () => {
    it('suit l’édition de Wikipédia pour un article d’encyclopédie', () => {
        expect(
            referenceLang({ kind: 'encyclopedia', url: 'https://en.wikipedia.org/wiki/X' }),
        ).toBe('en');
        expect(
            referenceLang({ kind: 'encyclopedia', url: 'https://de.m.wikipedia.org/wiki/X' }),
        ).toBe('de');
        expect(
            referenceLang({ kind: 'encyclopedia', url: 'https://fr.wikipedia.org/w/index.php?x' }),
        ).toBe('fr');
    });

    it('reste en français pour les autres documents, même liés à une page anglaise', () => {
        expect(referenceLang({ kind: 'scholarly', url: 'https://en.wikipedia.org/wiki/X' })).toBe(
            'fr',
        );
        expect(referenceLang({ kind: 'archives', url: 'https://pop.culture.gouv.fr/x' })).toBe(
            'fr',
        );
        expect(referenceLang({})).toBe('fr');
        expect(referenceLang({ lang: 'it', kind: 'scholarly' })).toBe('it');
    });

    it('met la langue sur le titre seul : « (opens in a new tab) » reste dans la langue de la page', () => {
        const ref = (key, kind, url, title) => ({
            key,
            kind,
            url,
            title,
            reliability: 'encyclopedia',
            access: 'open',
            note: null,
        });
        const body = render(AccidentContext, {
            props: {
                part: 'references',
                context: {
                    checked_on: null,
                    sections: [],
                    images: [],
                    corrections: [],
                    references: [
                        ref(
                            'wp-en',
                            'encyclopedia',
                            'https://en.wikipedia.org/wiki/V',
                            'Versailles rail accident',
                        ),
                        ref(
                            'wp-fr',
                            'encyclopedia',
                            'https://fr.wikipedia.org/wiki/M',
                            'Catastrophe de Meudon',
                        ),
                    ],
                },
            },
        }).body;
        expect(body).toMatch(/<span lang="en">Versailles rail accident<\/span>/);
        expect(body).toMatch(/<span lang="fr">Catastrophe de Meudon<\/span>/);
        expect(body).not.toMatch(/<a[^>]*class="ref-title[^"]*"[^>]*lang=/);
    });
});

describe('Profil en long', () => {
    it('nomme la figure par le titre de la section, pas par la légende des motifs', () => {
        const code = read('resources/js/Components/Lines/LineProfile.svelte');
        expect(code).toMatch(/<figure class="chart" aria-labelledby="profile-title">/);
        expect(code).toMatch(/id="profile-title"|SheetSection[\s\S]*id="profile"/);
    });
});

describe('Contrastes des contrôles de MapLibre (WCAG 1.4.3)', () => {
    const css = read('resources/css/general.css');

    it('écrit les crédits en clair sur le cadre sombre en thème sombre', () => {
        expect(css).toMatch(
            /:root\[data-theme='dark'\] \.maplibregl-ctrl-attrib a \{\s*color: var\(--vff-text\);/,
        );
        expect(css).toMatch(
            /:root\[data-theme='dark'\] \.maplibregl-ctrl\.maplibregl-ctrl-attrib,/,
        );
    });

    it('assombrit le voile du message « deux doigts » à 65 % (4,5:1 au moins sur un fond clair)', () => {
        const rule = css.slice(css.indexOf(':root .maplibregl-cooperative-gesture-screen {'));
        expect(rule.slice(0, rule.indexOf('}'))).toMatch(/background: rgb\(0 0 0 \/ 65%\)/);
        // Blanc sur le fond de carte le plus clair (#f2efe9) voilé de noir à 65 %.
        const bg = [0xf2, 0xef, 0xe9].map((c) => c * 0.35);
        const lum = (rgb) =>
            rgb
                .map((v) => v / 255)
                .map((v) => (v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4))
                .reduce((sum, v, i) => sum + v * [0.2126, 0.7152, 0.0722][i], 0);
        expect((1 + 0.05) / (lum(bg) + 0.05)).toBeGreaterThan(4.5);
    });
});

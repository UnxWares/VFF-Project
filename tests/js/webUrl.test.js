// Adresses venues d'une source extérieure rendues en lien (licence Commons, page du fichier) : seules
// http, https et les adresses sans schéma passent ; une autre devient nulle et la licence reste
// affichée sans lien. Même règle que App\Support\WebUrl (tests/Unit/Support/WebUrlTest.php).
import { describe, expect, it } from 'vitest';
import { webHref } from '$lib/webUrl.js';
import { mediaLicenseHref, mediaSourceHref } from '$lib/mediaCredits.js';
import { mediaFacts } from '../../resources/js/Components/Contribute/proposals.js';

const REFUSED = [
    'javascript:alert(1)',
    'JaVaScRiPt:alert(1)',
    '   javascript:alert(1)',
    '\n\tjavascript:alert(1)',
    'java\tscript:alert(1)',
    'data:text/html,<script>alert(1)</script>',
    'vbscript:msgbox(1)',
    '/relative/path',
    'https:',
    'https://',
    '',
    null,
    undefined,
    42,
];

describe('webHref', () => {
    it.each(REFUSED)('refuse %j', (value) => {
        expect(webHref(value)).toBeNull();
    });

    it('garde http, https et les adresses sans schéma, sans les espaces autour', () => {
        expect(webHref('https://creativecommons.org/licenses/by-sa/4.0')).toBe(
            'https://creativecommons.org/licenses/by-sa/4.0',
        );
        expect(webHref('HTTP://example.org/a')).toBe('HTTP://example.org/a');
        expect(webHref('  https://commons.wikimedia.org/wiki/File:Gare_de_Bréauté.jpg ')).toBe(
            'https://commons.wikimedia.org/wiki/File:Gare_de_Bréauté.jpg',
        );
        expect(webHref('//upload.wikimedia.org/thumb/500.jpg')).toBe(
            '//upload.wikimedia.org/thumb/500.jpg',
        );
    });
});

describe('crédits d’une image', () => {
    const media = (license_url, source_url = null) => ({
        license: 'CC BY-SA 4.0',
        rights_status: 'open_license',
        license_url,
        source_url,
    });

    it.each(['javascript:alert(1)', 'JaVaScRiPt:alert(1)', '  javascript:alert(1)', 'data:,x'])(
        'n’offre aucun lien de licence pour %j',
        (url) => {
            expect(mediaLicenseHref(media(url))).toBeNull();
            expect(mediaSourceHref(media(null, url))).toBeNull();
        },
    );

    it('se rabat sur la page de la source, si elle est en https', () => {
        const page = 'https://commons.wikimedia.org/wiki/File:X.jpg';
        expect(mediaLicenseHref(media('javascript:alert(1)', page))).toBe(page);
        expect(mediaLicenseHref(media('https://creativecommons.org/x', page))).toBe(
            'https://creativecommons.org/x',
        );
        expect(mediaSourceHref(media(null, page))).toBe(page);
    });
});

describe('fiche de relecture d’une image de Commons', () => {
    const t = (key) => key;

    it.each(['javascript:alert(1)', 'JaVaScRiPt:alert(1)', '   javascript:alert(1)', 'data:,x'])(
        'garde le nom de la licence sans lien pour %j',
        (url) => {
            const payload = {
                source: 'commons',
                target_type: 'station',
                station_name: 'Nontron',
                commons: {
                    title: 'Gare',
                    license: 'CC BY-SA 4.0',
                    license_url: url,
                    source_url: url,
                },
            };
            const facts = Object.fromEntries(mediaFacts(payload, t, 'fr').map((f) => [f.id, f]));
            expect(facts.license).toMatchObject({ text: 'CC BY-SA 4.0', href: null });
            expect(facts.origin.href).toBeNull();
        },
    );
});

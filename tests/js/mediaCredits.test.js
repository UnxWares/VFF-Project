import { describe, expect, it } from 'vitest';
import {
    groupLicenses,
    licenseUrl,
    mediaHeading,
    mediaLicenseHref,
    mediaLicenseLabel,
    mergeAuthors,
    normalizeLicense,
} from '../../resources/js/lib/mediaCredits.js';

describe('normalizeLicense', () => {
    it('retire le pays et garde la version des licences Creative Commons', () => {
        expect(normalizeLicense('CC BY-SA 2.0 fr')).toEqual({
            id: 'CC BY-SA 2.0',
            name: 'CC BY-SA 2.0',
        });
        expect(normalizeLicense('CC BY 3.0 es').name).toBe('CC BY 3.0');
    });

    it('traduit les valeurs importées en anglais', () => {
        expect(normalizeLicense('Public domain').labelKey).toBe('public_domain');
        expect(normalizeLicense('No restrictions').labelKey).toBe('no_restrictions');
        expect(normalizeLicense('FAL').labelKey).toBe('fal');
        expect(normalizeLicense(null).labelKey).toBe('no_license');
    });

    it('réunit les variantes de GFDL et de GPL', () => {
        expect(normalizeLicense('GFDL 1.2').id).toBe('GFDL');
        expect(normalizeLicense('GPLv2').id).toBe('GPL');
    });

    it('laisse les autres valeurs telles quelles', () => {
        expect(normalizeLicense('Attribution-Swisstopo').name).toBe('Attribution-Swisstopo');
        expect(normalizeLicense('CC0').name).toBe('CC0');
    });
});

describe('groupLicenses', () => {
    it('additionne les variantes et trie par nombre décroissant', () => {
        const rows = [
            { license: 'GFDL 1.2', count: 716 },
            { license: 'GFDL', count: 9 },
            { license: 'CC BY-SA 2.0', count: 1353 },
            { license: 'CC BY-SA 2.0 fr', count: 94 },
        ];
        expect(groupLicenses(rows).map((g) => [g.id, g.count])).toEqual([
            ['CC BY-SA 2.0', 1447],
            ['GFDL', 725],
        ]);
    });

    it('conserve le total', () => {
        const rows = [
            { license: 'Public domain', count: 5 },
            { license: null, count: 2 },
            { license: 'CC0', count: 3 },
        ];
        expect(groupLicenses(rows).reduce((n, g) => n + g.count, 0)).toBe(10);
    });
});

describe('licenseUrl', () => {
    it('donne le texte de la licence dans la langue de la page', () => {
        expect(licenseUrl('CC BY-SA 4.0', 'fr')).toBe(
            'https://creativecommons.org/licenses/by-sa/4.0/deed.fr',
        );
        expect(licenseUrl('CC BY 3.0', 'en')).toBe(
            'https://creativecommons.org/licenses/by/3.0/deed.en',
        );
        expect(licenseUrl('GFDL')).toBeNull();
    });
});

describe('mergeAuthors', () => {
    it('réunit les écritures qui ne diffèrent que par la casse', () => {
        const merged = mergeAuthors([
            { author: 'Poudou99', count: 3418 },
            { author: 'poudou99', count: 431 },
            { author: 'Autre  auteur', count: 10 },
        ]);
        expect(merged[0]).toEqual({ author: 'Poudou99', count: 3849 });
        expect(merged[1].author).toBe('Autre auteur');
    });
});

describe('crédit d’une image publiée', () => {
    const t = (key) => `t:${key}`;

    it('préfère une légende courte au titre du fichier', () => {
        expect(mediaHeading({ title: 'IMG_0042.jpg', caption: 'Gare de Brest en 1930' })).toBe(
            'Gare de Brest en 1930',
        );
        expect(mediaHeading({ title: 'Gare de Brest', caption: 'x'.repeat(161) })).toBe(
            'Gare de Brest',
        );
        expect(mediaHeading({ title: null, caption: null })).toBe('');
    });

    it('affiche la licence de la source, sinon le statut des droits', () => {
        expect(mediaLicenseLabel({ license: 'CC BY 4.0', rights_status: 'open_license' }, t)).toBe(
            'CC BY 4.0',
        );
        expect(mediaLicenseLabel({ license: null, rights_status: 'public_domain' }, t)).toBe(
            't:media.rights.public_domain',
        );
        expect(mediaLicenseLabel({ license: null }, t)).toBe('t:media.rights.unknown');
    });

    it('lie le texte de la licence, sinon la source, sinon rien', () => {
        expect(mediaLicenseHref({ license_url: 'https://lic', source_url: 'https://src' })).toBe(
            'https://lic',
        );
        expect(mediaLicenseHref({ license_url: null, source_url: 'https://src' })).toBe(
            'https://src',
        );
        // Fichier déposé dans le domaine public : ni texte de licence ni page de source.
        expect(mediaLicenseHref({ license_url: null, source_url: null })).toBeNull();
    });
});

// Formulaire « Proposer une image » : position GPS lue dans une photo JPEG (resources/js/lib/exifPosition.js),
// licences regroupées par famille et version, légendes par langue montrées aux validateurs
// (Components/Contribute/proposals.js) et dans la galerie des fiches (lib/mediaCredits.js).
import { describe, expect, it } from 'vitest';
import {
    EXIF_READ_BYTES,
    exifPosition,
    fileExifPosition,
} from '../../resources/js/lib/exifPosition.js';
import { licenseFamilies, mediaFacts } from '../../resources/js/Components/Contribute/proposals.js';
import {
    mediaCaption,
    mediaCaptionLang,
    mediaHeading,
} from '../../resources/js/lib/mediaCredits.js';

/**
 * Octets d'un segment JPEG (marqueur, longueur, contenu).
 * @param {number} marker
 * @param {number[]} body
 */
const segment = (marker, body) => [
    0xff,
    marker,
    (body.length + 2) >> 8,
    (body.length + 2) & 0xff,
    ...body,
];

/**
 * Photo JPEG minimale dont l'EXIF porte une position (degrés, minutes, secondes), comme celle des
 * tests PHP (tests/Support/BuildsTestImages::jpegWithLocation).
 * @param {{ lat: number[], latRef: string, lng: number[], lngRef: string, little?: boolean, withGps?: boolean }} o
 */
function jpeg({ lat, latRef, lng, lngRef, little = true, withGps = true }) {
    const tiff = [];
    const u16 = (v) => (little ? [v & 0xff, v >> 8] : [v >> 8, v & 0xff]);
    const u32 = (v) =>
        little
            ? [v & 0xff, (v >> 8) & 0xff, (v >> 16) & 0xff, v >>> 24]
            : [v >>> 24, (v >> 16) & 0xff, (v >> 8) & 0xff, v & 0xff];
    const entry = (tag, type, count, value) => [...u16(tag), ...u16(type), ...u32(count), ...value];
    const ascii = (c) => [c.charCodeAt(0), 0, 0, 0];
    // En-tête (8), IFD0 à 1 entrée (2 + 12 + 4 = 18) → répertoire GPS à 26 : 4 entrées (2 + 48 + 4 = 54) → rationnels à 80.
    tiff.push(...(little ? [0x49, 0x49] : [0x4d, 0x4d]), ...u16(42), ...u32(8));
    tiff.push(
        ...u16(1),
        ...entry(withGps ? 0x8825 : 0x0112, 4, 1, u32(withGps ? 26 : 1)),
        ...u32(0),
    );
    tiff.push(
        ...u16(4),
        ...entry(0x0001, 2, 2, ascii(latRef)),
        ...entry(0x0002, 5, 3, u32(80)),
        ...entry(0x0003, 2, 2, ascii(lngRef)),
        ...entry(0x0004, 5, 3, u32(104)),
        ...u32(0),
    );
    for (const v of [...lat, ...lng]) tiff.push(...u32(Math.round(v * 100)), ...u32(100));
    const exif = [0x45, 0x78, 0x69, 0x66, 0, 0, ...tiff];
    const bytes = [
        0xff,
        0xd8,
        ...segment(0xe0, [0x4a, 0x46, 0x49, 0x46, 0]),
        ...segment(0xe1, exif),
        0xff,
        0xda,
        0,
        2,
    ];
    return new Uint8Array(bytes).buffer;
}

describe('exifPosition', () => {
    it('lit la latitude et la longitude d’une photo, en degrés décimaux', () => {
        const buffer = jpeg({ lat: [45, 30, 0], latRef: 'N', lng: [0, 39, 41.04], lngRef: 'E' });
        expect(exifPosition(buffer)).toEqual({ lat: 45.5, lng: 0.6614 });
    });

    it('rend négatives les positions au sud et à l’ouest, en ordre d’octets Motorola', () => {
        const buffer = jpeg({
            lat: [33, 52, 4.2],
            latRef: 'S',
            lng: [151, 12, 36],
            lngRef: 'W',
            little: false,
        });
        expect(exifPosition(buffer)).toEqual({ lat: -33.867833, lng: -151.21 });
    });

    it('ne trouve rien sans répertoire GPS, au point 0, 0 ou dans un fichier qui n’est pas un JPEG', () => {
        expect(
            exifPosition(
                jpeg({ lat: [45, 0, 0], latRef: 'N', lng: [1, 0, 0], lngRef: 'E', withGps: false }),
            ),
        ).toBeNull();
        expect(
            exifPosition(jpeg({ lat: [0, 0, 0], latRef: 'N', lng: [0, 0, 0], lngRef: 'E' })),
        ).toBeNull();
        expect(
            exifPosition(new Uint8Array([0x89, 0x50, 0x4e, 0x47, 0, 0, 0, 0]).buffer),
        ).toBeNull();
        expect(exifPosition(new ArrayBuffer(0))).toBeNull();
    });

    it('s’arrête sans erreur sur un EXIF tronqué', () => {
        const full = new Uint8Array(
            jpeg({ lat: [45, 30, 0], latRef: 'N', lng: [0, 39, 41], lngRef: 'E' }),
        );
        for (const size of [3, 12, 30, 60, 100, 130]) {
            expect(() => exifPosition(full.slice(0, size).buffer)).not.toThrow();
        }
        expect(exifPosition(full.slice(0, 60).buffer)).toBeNull();
    });

    it('lit le début d’un fichier choisi, seulement pour un JPEG', async () => {
        const buffer = jpeg({ lat: [48, 50, 40.2], latRef: 'N', lng: [2, 13, 10.56], lngRef: 'E' });
        expect(await fileExifPosition(new Blob([buffer], { type: 'image/jpeg' }))).toEqual({
            lat: 48.8445,
            lng: 2.2196,
        });
        expect(await fileExifPosition(new Blob([buffer], { type: 'image/png' }))).toBeNull();
        expect(EXIF_READ_BYTES).toBeGreaterThanOrEqual(65536);
    });
});

describe('licenseFamilies', () => {
    const licenses = [
        {
            value: 'public_domain',
            name: 'Public domain',
            url: null,
            family: 'public_domain',
            version: null,
        },
        {
            value: 'cc0-1.0',
            name: 'CC0',
            url: 'https://creativecommons.org/publicdomain/zero/1.0/',
            family: 'cc0',
            version: '1.0',
        },
        {
            value: 'cc-by-4.0',
            name: 'CC BY 4.0',
            url: 'https://creativecommons.org/licenses/by/4.0/',
            family: 'cc-by',
            version: '4.0',
        },
        {
            value: 'cc-by-3.0',
            name: 'CC BY 3.0',
            url: 'https://creativecommons.org/licenses/by/3.0/',
            family: 'cc-by',
            version: '3.0',
        },
        {
            value: 'cc-by-sa-4.0',
            name: 'CC BY-SA 4.0',
            url: 'https://creativecommons.org/licenses/by-sa/4.0/',
            family: 'cc-by-sa',
            version: '4.0',
        },
        {
            value: 'cc-by-sa-2.0',
            name: 'CC BY-SA 2.0',
            url: 'https://creativecommons.org/licenses/by-sa/2.0/',
            family: 'cc-by-sa',
            version: '2.0',
        },
    ];

    it('regroupe les versions sous leur famille, dans l’ordre reçu (la plus récente d’abord)', () => {
        const groups = licenseFamilies(licenses);
        expect(groups.map((g) => g.family)).toEqual(['public_domain', 'cc0', 'cc-by', 'cc-by-sa']);
        expect(groups[2].choices.map((c) => c.value)).toEqual(['cc-by-4.0', 'cc-by-3.0']);
        expect(groups[3].choices[1]).toEqual({
            value: 'cc-by-sa-2.0',
            name: 'CC BY-SA 2.0',
            url: 'https://creativecommons.org/licenses/by-sa/2.0/',
            version: '2.0',
        });
    });

    it('garde une licence sans famille connue comme sa propre famille', () => {
        expect(
            licenseFamilies([{ value: 'etalab-2.0', name: 'Licence Ouverte 2.0', url: null }]),
        ).toEqual([
            {
                family: 'etalab-2.0',
                choices: [
                    { value: 'etalab-2.0', name: 'Licence Ouverte 2.0', url: null, version: null },
                ],
            },
        ]);
    });
});

describe('légendes par langue', () => {
    const t = (key) => key;
    const upload = {
        source: 'upload',
        target_type: 'line',
        line_name: 'Ligne de Paris à Versailles',
        title: 'Gare de Saint-Cloud',
        caption: 'Vue prise depuis la passerelle.',
        captions: { fr: 'Vue prise depuis la passerelle.', de: 'Blick von der Fußgängerbrücke.' },
        author: 'Marie Durand',
        license_name: 'CC BY-SA 3.0',
    };

    it('montre aux validateurs chaque légende donnée, avec sa langue', () => {
        const facts = mediaFacts(upload, t, 'fr').filter((f) => f.id.startsWith('caption'));
        expect(facts).toEqual([
            {
                id: 'caption_fr',
                label: 'proposals.review.captions.fr',
                text: 'Vue prise depuis la passerelle.',
                lang: 'fr',
            },
            {
                id: 'caption_de',
                label: 'proposals.review.captions.de',
                text: 'Blick von der Fußgängerbrücke.',
                lang: 'de',
            },
        ]);
    });

    it('garde la légende unique d’une proposition envoyée avant les légendes par langue', () => {
        const { captions, ...old } = upload;
        expect(captions).toBeDefined();
        const facts = mediaFacts(old, t, 'fr').filter((f) => f.id.startsWith('caption'));
        expect(facts).toEqual([
            {
                id: 'caption',
                label: 'proposals.review.caption',
                text: 'Vue prise depuis la passerelle.',
            },
        ]);
    });

    it('affiche dans la galerie la légende de la langue de la page, sinon la principale', () => {
        const media = { title: 'IMG_2041.jpg', caption: upload.caption, captions: upload.captions };
        expect(mediaCaption(media, 'de')).toBe('Blick von der Fußgängerbrücke.');
        expect(mediaCaptionLang(media, 'de')).toBe('de');
        expect(mediaCaption(media, 'en')).toBe('Vue prise depuis la passerelle.');
        expect(mediaCaptionLang(media, 'en')).toBe('fr');
        expect(mediaHeading(media, 'de')).toBe('Blick von der Fußgängerbrücke.');
        expect(mediaHeading(media)).toBe('Vue prise depuis la passerelle.');
        expect(mediaCaptionLang({ caption: 'Légende venue de Commons' }, 'de')).toBeUndefined();
    });
});

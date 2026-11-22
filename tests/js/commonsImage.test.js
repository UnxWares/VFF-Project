import { describe, expect, it, vi } from 'vitest';

const inertia = vi.hoisted(() => ({ page: { props: {} } }));
vi.mock('@inertiajs/svelte', () => inertia);

const { commonsPath, commonsSrc, relayCommonsUrl } =
    await import('../../resources/js/lib/commonsImage.js');

const THUMB =
    'https://thumb.wikimedia.org/wikipedia/commons/thumb/3/36/Gare-Roscoff-CPancienne.jpg/500px-Gare-Roscoff-CPancienne.jpg';
const UPLOAD_THUMB =
    'https://upload.wikimedia.org/wikipedia/commons/thumb/2/2d/Voie_Limoges-Angoul%C3%AAme.jpg/1280px-Voie_Limoges-Angoul%C3%AAme.jpg';
const ORIGINAL = 'https://upload.wikimedia.org/wikipedia/commons/7/79/CFA-Morlaix.jpg';
const PREFIX = '/media/commons/';

describe('relayCommonsUrl', () => {
    it('réécrit une vignette de thumb.wikimedia.org ou upload.wikimedia.org vers le relais', () => {
        expect(relayCommonsUrl(THUMB, PREFIX)).toBe(
            '/media/commons/thumb/3/36/Gare-Roscoff-CPancienne.jpg/500px-Gare-Roscoff-CPancienne.jpg',
        );
        expect(relayCommonsUrl(UPLOAD_THUMB, PREFIX)).toBe(
            '/media/commons/thumb/2/2d/Voie_Limoges-Angoul%C3%AAme.jpg/1280px-Voie_Limoges-Angoul%C3%AAme.jpg',
        );
    });

    it('réécrit un original JPEG, PNG, GIF ou WebP et les vignettes de TIFF, PDF ou SVG', () => {
        expect(relayCommonsUrl(ORIGINAL, PREFIX)).toBe('/media/commons/7/79/CFA-Morlaix.jpg');
        const tif =
            'https://thumb.wikimedia.org/wikipedia/commons/thumb/a/ac/Alter_Bahnhof_Metz.tif/lossy-page1-500px-Alter_Bahnhof_Metz.tif.jpg';
        expect(relayCommonsUrl(tif, PREFIX)).toBe(
            '/media/commons/thumb/a/ac/Alter_Bahnhof_Metz.tif/lossy-page1-500px-Alter_Bahnhof_Metz.tif.jpg',
        );
        const svg =
            'https://upload.wikimedia.org/wikipedia/commons/thumb/1/1a/Plan.svg/500px-Plan.svg.png';
        expect(relayCommonsUrl(svg, PREFIX)).toBe(
            '/media/commons/thumb/1/1a/Plan.svg/500px-Plan.svg.png',
        );
    });

    it('laisse l’adresse telle quelle sans préfixe (relais désactivé)', () => {
        expect(relayCommonsUrl(THUMB, null)).toBe(THUMB);
        expect(relayCommonsUrl(THUMB, undefined)).toBe(THUMB);
        expect(relayCommonsUrl(THUMB, '')).toBe(THUMB);
    });

    it('laisse les valeurs absentes ou non textuelles', () => {
        expect(relayCommonsUrl(null, PREFIX)).toBeNull();
        expect(relayCommonsUrl(undefined, PREFIX)).toBeUndefined();
    });

    it('ne touche ni les médias de VFF, ni Gallica, ni un autre hôte', () => {
        for (const url of [
            '/storage/media/contributions/12-4kq9z2m1x8c7b6v5.jpg',
            'https://gallica.bnf.fr/ark:/12148/btv1b53021154d/f1.highres',
            'https://commons.wikimedia.org/wiki/File:CFA-Morlaix.jpg',
            'https://upload.wikimedia.org.example.org/wikipedia/commons/7/79/CFA-Morlaix.jpg',
            'https://evil.example/wikipedia/commons/7/79/CFA-Morlaix.jpg',
        ]) {
            expect(relayCommonsUrl(url, PREFIX)).toBe(url);
        }
    });

    it('refuse les formes piégées : http, port, requête, fragment, chemin remontant, taille inconnue', () => {
        for (const url of [
            'http://upload.wikimedia.org/wikipedia/commons/7/79/CFA-Morlaix.jpg',
            'https://upload.wikimedia.org:8443/wikipedia/commons/7/79/CFA-Morlaix.jpg',
            `${ORIGINAL}?x=1`,
            `${ORIGINAL}#a`,
            'https://upload.wikimedia.org/wikipedia/commons/../../etc/passwd',
            'https://upload.wikimedia.org/wikipedia/commons/7/79/../CFA-Morlaix.jpg',
            'https://upload.wikimedia.org/wikipedia/commons/thumb/3/36/A.jpg/4000px-A.jpg',
            'https://upload.wikimedia.org/wikipedia/commons/7/79/Plan.svg',
            'https://upload.wikimedia.org/wikipedia/commons/7/80/CFA-Morlaix.jpg',
            'https://upload.wikimedia.org/wikipedia/en/7/79/CFA-Morlaix.jpg',
        ]) {
            expect(commonsPath(url)).toBeNull();
            expect(relayCommonsUrl(url, PREFIX)).toBe(url);
        }
    });
});

describe('commonsSrc', () => {
    it('lit le préfixe partagé par le serveur', () => {
        inertia.page.props = { commons_proxy: PREFIX };
        expect(commonsSrc(ORIGINAL)).toBe('/media/commons/7/79/CFA-Morlaix.jpg');
        inertia.page.props = { commons_proxy: null };
        expect(commonsSrc(ORIGINAL)).toBe(ORIGINAL);
        inertia.page.props = {};
        expect(commonsSrc(ORIGINAL)).toBe(ORIGINAL);
    });
});

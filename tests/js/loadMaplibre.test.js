import { beforeEach, describe, expect, it, vi } from 'vitest';

/** Module neuf à chaque test : le chargement mémorisé ne passe pas d'un test à l'autre. */
let loadMaplibre;
let hasPmtilesProtocol;
beforeEach(async () => {
    vi.resetModules();
    ({ loadMaplibre, hasPmtilesProtocol } = await import('../../resources/js/lib/loadMaplibre.js'));
});

describe('loadMaplibre', () => {
    it('renvoie MapLibre tel que le chargeur le donne', async () => {
        const lib = { Map: class {} };
        await expect(loadMaplibre(async () => lib)).resolves.toBe(lib);
    });

    it('ne télécharge le module qu’une fois pour toutes les cartes', async () => {
        const importer = vi.fn(async () => ({ Map: class {} }));
        const [a, b] = await Promise.all([loadMaplibre(importer), loadMaplibre(importer)]);
        await loadMaplibre(importer);
        expect(a).toBe(b);
        expect(importer).toHaveBeenCalledTimes(1);
    });

    it('retente le téléchargement après un échec', async () => {
        const lib = { Map: class {} };
        const importer = vi
            .fn()
            .mockRejectedValueOnce(new Error('réseau'))
            .mockResolvedValueOnce(lib);
        await expect(loadMaplibre(importer)).rejects.toThrow('réseau');
        await expect(loadMaplibre(importer)).resolves.toBe(lib);
        expect(importer).toHaveBeenCalledTimes(2);
    });

    it('enregistre le protocole pmtiles:// du fond de carte libre', async () => {
        const lib = { Map: class {} };
        const registerPmtilesProtocol = vi.fn(() => true);
        await expect(
            loadMaplibre(
                async () => lib,
                async () => ({ registerPmtilesProtocol }),
            ),
        ).resolves.toBe(lib);
        expect(registerPmtilesProtocol).toHaveBeenCalledWith(lib);
    });

    it('rend MapLibre même si le protocole ne se charge pas', async () => {
        const lib = { Map: class {} };
        await expect(
            loadMaplibre(
                async () => lib,
                async () => Promise.reject(new Error('réseau')),
            ),
        ).resolves.toBe(lib);
    });

    it('dit si le fond de carte libre peut être lu (protocole enregistré)', async () => {
        const lib = { Map: class {} };
        expect(hasPmtilesProtocol()).toBe(false);
        await loadMaplibre(
            async () => lib,
            async () => ({ registerPmtilesProtocol: () => true }),
        );
        expect(hasPmtilesProtocol()).toBe(true);
    });

    it('signale le protocole absent quand son module ne se charge pas', async () => {
        await loadMaplibre(
            async () => ({ Map: class {} }),
            async () => Promise.reject(new Error('réseau')),
        );
        expect(hasPmtilesProtocol()).toBe(false);
    });
});

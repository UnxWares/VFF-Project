import { beforeEach, describe, expect, it, vi } from 'vitest';

/** Module neuf à chaque test : le chargement mémorisé ne passe pas d'un test à l'autre. */
let loadMaplibre;
beforeEach(async () => {
    vi.resetModules();
    ({ loadMaplibre } = await import('../../resources/js/lib/loadMaplibre.js'));
});

describe('loadMaplibre', () => {
    it("renvoie l'export par défaut du module", async () => {
        const lib = { Map: class {} };
        await expect(loadMaplibre(async () => ({ default: lib }))).resolves.toBe(lib);
    });

    it('ne télécharge le module qu’une fois pour toutes les cartes', async () => {
        const importer = vi.fn(async () => ({ default: { Map: class {} } }));
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
            .mockResolvedValueOnce({ default: lib });
        await expect(loadMaplibre(importer)).rejects.toThrow('réseau');
        await expect(loadMaplibre(importer)).resolves.toBe(lib);
        expect(importer).toHaveBeenCalledTimes(2);
    });
});

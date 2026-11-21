import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';

// LinePhaseMap.svelte crée MapLibre (1 Mo, plusieurs tâches longues) : le démarrage est différé
// (lib/whenNear.js) jusqu'à l'arrivée de la carte à l'écran ou à la première interaction. Le composant
// ne se monte pas sans navigateur ni WebGL (environnement Node) : ces tests vérifient l'ordre des appels
// dans son source, que la page de ligne sur téléphone ne doit pas perdre.
const source = readFileSync(new URL('../../resources/js/Components/Lines/LinePhaseMap.svelte', import.meta.url), 'utf8');
const mount = source.slice(source.indexOf('onMount(async'), source.indexOf('map.on(\'load\''));

const at = (needle, text = mount) => {
    const index = text.indexOf(needle);
    expect(index, `« ${needle} » absent`).toBeGreaterThanOrEqual(0);
    return index;
};

describe('LinePhaseMap : démarrage différé de la carte', () => {
    it('importe whenNear et lance la lecture du tracé avant d’attendre la carte', () => {
        expect(source).toContain("import { whenNear } from '$lib/whenNear.js';");
        expect(at('loadLinePortions(portionsUrl)')).toBeLessThan(at('whenNear(mapContainer)'));
    });

    it('n’appelle loadMaplibre qu’après l’attente de la carte', () => {
        expect(at('await near.promise')).toBeLessThan(at('loadMaplibre()'));
        // Un seul chargement de MapLibre dans le démarrage.
        expect(mount.split('loadMaplibre()')).toHaveLength(2);
    });

    it('revérifie que le composant n’est pas détruit après chaque attente', () => {
        const afterNear = mount.slice(at('await near.promise'));
        expect(afterNear.indexOf('if (destroyed) return;')).toBeLessThan(afterNear.indexOf('loadMaplibre()'));
        expect(afterNear).toContain('if (destroyed || !mapContainer) return;');
        expect(at('if (destroyed || !mapContainer) return;')).toBeGreaterThan(at('loadMaplibre()'));
    });

    it('ne crée la carte qu’une fois le tracé et MapLibre disponibles, et signale l’échec du tracé', () => {
        expect(at('Promise.all([loadMaplibre(), lineworksRequest])')).toBeLessThan(at('if (!lineworks)'));
        expect(at('failed = true')).toBeLessThan(at('new maplibregl.Map'));
        expect(mount).toContain('loadLinePortions(portionsUrl).catch(() => null)');
    });

    it('annule l’attente et marque le composant détruit à la destruction', () => {
        const destroy = source.slice(source.indexOf('onDestroy(() => {'));
        expect(destroy.slice(0, destroy.indexOf('});'))).toMatch(/destroyed = true;[\s\S]*near\?\.cancel\(\);/);
    });

    it('ne charge MapLibre nulle part ailleurs que dans le démarrage différé', () => {
        expect(source.split('loadMaplibre(')).toHaveLength(2); // un seul appel (l'import n'a pas de parenthèse)
        expect(source).not.toMatch(/import\s+maplibregl|from 'maplibre-gl'/);
    });
});

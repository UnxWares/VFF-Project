// Protocole `pmtiles://` de MapLibre : lit l'archive du fond de carte libre (lib/basemap.js) par plages
// d'octets. Le module officiel `pmtiles` s'enregistre par `addProtocol`, présent dans MapLibre 5 et 6 avec
// la même signature (requête, AbortController) => Promise<{ data }>.
import { Protocol } from 'pmtiles';

/** Instances de MapLibre où le protocole est déjà enregistré (une seule fois par instance). */
const registered = new WeakSet();

/**
 * Enregistre le protocole `pmtiles://` sur MapLibre (sans effet la seconde fois, ou sur un objet qui n'a
 * pas `addProtocol`).
 *
 * @param {{ addProtocol?: (name: string, load: (...args: any[]) => Promise<any>) => void } | null | undefined} maplibre
 * @returns {boolean} vrai si le protocole est disponible après l'appel
 */
export function registerPmtilesProtocol(maplibre) {
    if (!maplibre || typeof maplibre.addProtocol !== 'function') return false;
    if (registered.has(maplibre)) return true;
    const protocol = new Protocol();
    maplibre.addProtocol('pmtiles', protocol.tile);
    registered.add(maplibre);
    return true;
}

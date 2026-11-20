import { STATION_STYLES } from '$lib/railStyle.js';

// Couleurs des statuts de gare = celles de la couche `vff-stations-points` de
// la carte (railStyle.js STATION_STYLES, palette unique) : orange = voyageurs,
// vert = touristique, bleu = fret seul, ardoise = incertaine, gris = fermée ;
// gris foncé = disparue.
/** @type {Record<string, string>} statut → couleur */
export const STATION_STATUS_COLOR = {
    ...Object.fromEntries(STATION_STYLES.map((s) => [s.key, s.color])),
    gone: '#5f5f5f',
    // Gare projetée jamais ouverte, point technique (jamais ouvert au public) : hors carte.
    planned: '#8a7fb8',
    technical: '#7a8088',
    // Hors du réseau ferré (tramway, funiculaire, fiche erronée) : hors carte.
    excluded: '#7a8088',
};

export const STATION_STATUS_ICON = {
    active: 'bi-people-fill',
    tourist: 'bi-camera-fill',
    no_commercial: 'bi-box-seam-fill',
    uncertain: 'bi-question-circle',
    declassified: 'bi-slash-circle',
    gone: 'bi-hourglass-bottom',
    planned: 'bi-pencil-square',
    technical: 'bi-gear-fill',
    excluded: 'bi-dash-circle',
};

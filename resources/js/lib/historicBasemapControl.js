// Contrôle MapLibre (bouton + panneau) qui monte le sélecteur Svelte des fonds
// de carte historiques IGN. Utilisé par RailMap (carte publique, embed).
// Fichier séparé de historicBasemaps.js pour éviter un import circulaire
// (le composant Svelte importe lui-même historicBasemaps.js).

import { mount, unmount } from 'svelte';
import HistoricBasemapPicker from '$components/Contribute/HistoricBasemapPicker.svelte';

export { HISTORIC_OVERLAY_DEFS } from './historicBasemaps.js';

export class HistoricBasemapControl {
    constructor(options = {}) {
        this.options = options;
    }

    onAdd(map) {
        this._container = document.createElement('div');
        this._container.className = 'maplibregl-ctrl maplibregl-ctrl-group vff-historic-ctrl';
        this._component = mount(HistoricBasemapPicker, {
            target: this._container,
            props: { map, compact: true, ...this.options },
        });
        return this._container;
    }

    onRemove() {
        if (this._component) unmount(this._component);
        this._container?.remove();
        this._component = null;
    }
}

export function addHistoricBasemapControl(map, position = 'top-right', options = {}) {
    const ctrl = new HistoricBasemapControl(options);
    map.addControl(ctrl, position);
    return ctrl;
}

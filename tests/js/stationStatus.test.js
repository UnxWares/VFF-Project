// Statuts des gares dans l'interface : chaque statut a une couleur et une icône, et les couleurs
// des statuts de la carte sont celles de la légende (railStyle.js), jamais une copie qui dérive.
import { describe, expect, it } from 'vitest';
import {
    STATION_STATUS_COLOR,
    STATION_STATUS_ICON,
} from '../../resources/js/Components/Stations/stationStatus.js';
import { STATION_STYLES } from '$lib/railStyle.js';

describe('statuts des gares', () => {
    it('reprennent exactement la palette de la carte pour les statuts cartographiés', () => {
        for (const style of STATION_STYLES) {
            expect(STATION_STATUS_COLOR[style.key]).toBe(style.color);
        }
    });

    it('ajoutent les statuts hors carte : disparue, projetée, technique, exclue', () => {
        for (const key of ['gone', 'planned', 'technical', 'excluded']) {
            expect(STATION_STATUS_COLOR[key]).toBeTruthy();
        }
    });

    it('chaque statut coloré a une icône, et réciproquement', () => {
        expect(Object.keys(STATION_STATUS_ICON).sort()).toEqual(
            Object.keys(STATION_STATUS_COLOR).sort(),
        );
    });

    it('les couleurs sont des codes hexadécimaux valides', () => {
        for (const [key, color] of Object.entries(STATION_STATUS_COLOR)) {
            expect(color, key).toMatch(/^#[0-9a-f]{6}$/i);
        }
    });

    it('les icônes sont des classes bootstrap-icons distinctes d’un statut à l’autre', () => {
        const icons = Object.values(STATION_STATUS_ICON);
        for (const icon of icons) expect(icon).toMatch(/^bi-[a-z0-9-]+$/);
        expect(new Set(icons).size).toBe(icons.length);
    });

    it('une gare fermée n’a jamais la couleur d’une gare ouverte', () => {
        const open = ['active', 'tourist', 'no_commercial'].map((k) => STATION_STATUS_COLOR[k]);
        expect(open).not.toContain(STATION_STATUS_COLOR.declassified);
        expect(open).not.toContain(STATION_STATUS_COLOR.gone);
        expect(open).not.toContain(STATION_STATUS_COLOR.uncertain);
    });
});

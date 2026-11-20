// Textes de la fiche latérale et de la bulle de la carte (Components/Map/lineInfo.js) : mêmes
// catégories et mêmes couleurs que la légende et que les tracés (railStyle.js).
import { describe, expect, it } from 'vitest';
import {
    electrificationColorExpr,
    HERITAGE_COLOR,
    LGV_COLOR,
    STATUS_COLOR,
    TRAFFIC_COLOR,
} from '$lib/railStyle.js';
import {
    electrificationInfo,
    electrificationKey,
    escapeHtml,
    isActive,
    isTrue,
    statusLabel,
    statusStroke,
    trafficInfo,
} from '../../resources/js/Components/Map/lineInfo.js';
import { colorOf } from './support/maplibre.js';

const LABELS = {
    'maps.electrification.ac25': '25 kV 50 Hz (alternatif)',
    'maps.electrification.dc1500': '1,5 kV continu',
    'maps.electrification.dc3000': '3 kV continu',
    'maps.electrification.ac15': '15 kV 16,7 Hz (alternatif)',
    'maps.electrification.low': '1 000 V et moins (troisième rail)',
    'maps.electrification.other': 'Autre tension',
    'maps.electrification.unknown_voltage': 'Électrifiée, tension inconnue',
    'maps.traffic.lgv': 'Ligne à grande vitesse (LGV)',
    'maps.traffic.mixed': 'Réseau classique : voyageurs et fret',
    'maps.traffic.freight': 'Réseau classique : fret seul',
    'maps.traffic.unknown': 'Réseau classique : trafic non renseigné',
    'maps.heritage_kind.velorail': 'Vélorail',
    'maps.status.abandoned': 'Fermée (voie en place)',
    'maps.status.in_service': 'En service',
};
const t = (key) => LABELS[key] ?? key;
const NBSP = ' ';

describe('électrification', () => {
    it('classe les tensions comme la carte', () => {
        expect(electrificationKey(25000)).toBe('ac25');
        expect(electrificationKey('1500')).toBe('dc1500');
        expect(electrificationKey(3000)).toBe('dc3000');
        expect(electrificationKey(15000)).toBe('ac15');
        expect(electrificationKey(750)).toBe('low');
        expect(electrificationKey(12000)).toBe('other');
        expect(electrificationKey(null)).toBe('unknown_voltage');
        expect(electrificationKey(0)).toBe('unknown_voltage');
    });

    it('donne la couleur du tracé en mode électrification', () => {
        for (const v of [25000, 1500, 3000, 15000, 750, 12000, 0]) {
            const expected = colorOf(electrificationColorExpr(), {
                electrified: true,
                voltage_v: v,
            });
            expect(electrificationInfo(v, t, 'fr').color).toBe(expected);
        }
    });

    it('écrit une tension courante avec le libellé de la légende, jamais « 25.0 kV »', () => {
        expect(electrificationInfo(25000, t, 'fr').label).toBe('25 kV 50 Hz (alternatif)');
        expect(electrificationInfo(1500, t, 'en').label).toBe('1,5 kV continu');
    });

    it('formate les autres tensions dans la langue, avec une espace insécable', () => {
        expect(electrificationInfo(750, t, 'fr').label).toBe(`750${NBSP}V`);
        expect(electrificationInfo(12000, t, 'fr').label).toBe(`12${NBSP}kV`);
        expect(electrificationInfo(6600, t, 'fr').label).toBe(`6,6${NBSP}kV`);
        expect(electrificationInfo(6600, t, 'en').label).toBe(`6.6${NBSP}kV`);
    });
});

describe('trafic et statut', () => {
    it('reconnaît une portion en service', () => {
        expect(isActive('in_service')).toBe(true);
        expect(isActive('single_track')).toBe(true);
        expect(isActive('abandoned')).toBe(false);
    });

    it('lit les booléens des tuiles, même en texte', () => {
        expect(isTrue(true)).toBe(true);
        expect(isTrue('true')).toBe(true);
        expect(isTrue('false')).toBe(false);
        expect(isTrue(null)).toBe(false);
    });

    it('donne le trafic et la couleur de la légende', () => {
        expect(trafficInfo({ status: 'in_service', traffic_type: 'mixed' }, t)).toEqual({
            label: LABELS['maps.traffic.mixed'],
            color: TRAFFIC_COLOR.mixed,
        });
        expect(trafficInfo({ status: 'in_service', traffic_type: 'passenger' }, t).color).toBe(
            LGV_COLOR,
        );
        expect(
            trafficInfo({ status: 'in_service', is_lgv: true, traffic_type: 'freight' }, t).color,
        ).toBe(LGV_COLOR);
        expect(trafficInfo({ status: 'single_track', traffic_type: null }, t).label).toBe(
            LABELS['maps.traffic.unknown'],
        );
    });

    it("préfère l'exploitation associative, même sur une voie fermée (vélorail)", () => {
        expect(trafficInfo({ status: 'abandoned', heritage_kind: 'velorail' }, t)).toEqual({
            label: 'Vélorail',
            color: HERITAGE_COLOR,
        });
    });

    it("n'invente pas de trafic sur une ligne fermée", () => {
        expect(trafficInfo({ status: 'abandoned', traffic_type: 'mixed' }, t)).toBeNull();
    });

    it('emploie les mots de la légende pour le statut', () => {
        expect(statusLabel('abandoned', t)).toBe('Fermée (voie en place)');
        expect(statusLabel('inconnu', t)).toBe('inconnu');
        expect(statusLabel(null, t)).toBe('-');
    });

    it('donne le trait des statuts non actifs seulement', () => {
        expect(statusStroke('abandoned').color).toBe(STATUS_COLOR.abandoned);
        expect(statusStroke('abandoned').dash).not.toBeNull();
        expect(statusStroke('in_service')).toBeNull();
    });
});

describe('bulle de la carte', () => {
    it('échappe les noms venant de la base', () => {
        expect(escapeHtml('<img src=x onerror="a">')).toBe(
            '&lt;img src=x onerror=&quot;a&quot;&gt;',
        );
        expect(escapeHtml("Ligne d'Arles & Fontvieille")).toBe(
            'Ligne d&#39;Arles &amp; Fontvieille',
        );
        expect(escapeHtml(null)).toBe('');
    });
});

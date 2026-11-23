import { describe, expect, it } from 'vitest';
import {
    eventKey,
    extent,
    fillKind,
    lanes,
    MAX_LANES,
    span,
    systemKey,
    voltageText,
} from '$lib/lineElectrification.js';

const fmt = (n) => String(n).replace('.', ',');

describe('lineElectrification', () => {
    it('écrit les tensions en V ou en kV avec une espace insécable', () => {
        expect(voltageText(1500, fmt)).toBe('1500 V');
        expect(voltageText(25000, fmt)).toBe('25 kV');
        expect(voltageText(null, fmt)).toBeNull();
    });

    it('associe le courant à sa clé de traduction', () => {
        expect(systemKey('dc')).toBe('electrification.system.dc');
        expect(systemKey('third_rail')).toBe('electrification.system.third_rail');
        expect(systemKey('unknown')).toBeNull();
        expect(systemKey(undefined)).toBeNull();
    });

    it('choisit le libellé selon la nature du fait et la tension citée', () => {
        expect(eventKey({ kind: 'electrification', voltage_v: null })).toBe(
            'electrification.kind.electrification',
        );
        expect(eventKey({ kind: 'electrification', voltage_v: 1500 })).toBe(
            'electrification.kind.electrification_volt',
        );
        expect(eventKey({ kind: 'conversion', voltage_v: 1500 })).toBe(
            'electrification.kind.conversion_volt',
        );
        expect(eventKey({ kind: 'deelectrification', voltage_v: null })).toBe(
            'electrification.kind.deelectrification',
        );
        expect(eventKey({ kind: 'inconnu', voltage_v: null })).toBe(
            'electrification.kind.electrification',
        );
    });

    it('ne trace que les portions datées placées sur la ligne, hors désélectrification', () => {
        const section = (a, b) => ({ pk_start_m: a, pk_end_m: b, from: 'A', to: 'B' });
        const history = [
            {
                kind: 'electrification',
                date: '1937-01-01',
                scope: 'section',
                section: section(0, 5000),
            },
            { kind: 'electrification', date: '1938-01-01', scope: 'whole_line', section: null },
            {
                kind: 'deelectrification',
                date: '1990-01-01',
                scope: 'section',
                section: section(0, 5000),
            },
            {
                kind: 'electrification',
                date: '1950-01-01',
                scope: 'section',
                section: section(null, null),
            },
            {
                kind: 'electrification',
                date: '1951-01-01',
                scope: 'section',
                section: section(9000, 8000),
            },
            {
                kind: 'conversion',
                date: '1960-01-01',
                scope: 'section',
                section: section(6000, 9000),
            },
        ];
        expect(lanes(history)).toEqual([
            { index: 0, pk_start_m: 0, pk_end_m: 5000 },
            { index: 5, pk_start_m: 6000, pk_end_m: 9000 },
        ]);
    });

    it('limite le nombre de voies', () => {
        const history = Array.from({ length: 30 }, (_, i) => ({
            kind: 'electrification',
            date: `19${30 + i}-01-01`,
            scope: 'section',
            section: { pk_start_m: i * 100, pk_end_m: i * 100 + 50 },
        }));
        expect(lanes(history)).toHaveLength(MAX_LANES);
    });

    it("borne l'axe sur les tranches et les portions", () => {
        expect(
            extent([{ pk_start_m: 1000, pk_end_m: 4000 }], [{ pk_start_m: 500, pk_end_m: 900 }]),
        ).toEqual([500, 4000]);
        expect(extent([], [])).toEqual([0, 1]);
    });

    it('donne la première et la dernière date sans compter les dépose de caténaire', () => {
        expect(span([])).toBeNull();
        expect(
            span([
                { kind: 'electrification', date: '1952-05-26' },
                { kind: 'deelectrification', date: '1992-01-01' },
                { kind: 'electrification', date: '1937-01-01' },
            ]),
        ).toEqual({ first: '1937-01-01', last: '1952-05-26' });
    });

    it('distingue les courants par le motif, pas par la couleur seule', () => {
        expect(fillKind('dc')).toBe('solid');
        expect(fillKind('ac')).toBe('hatch');
        expect(fillKind('third_rail')).toBe('dots');
        expect(fillKind('unknown')).toBe('solid');
    });
});

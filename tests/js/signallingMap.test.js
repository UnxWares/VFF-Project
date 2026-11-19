import { describe, expect, it } from 'vitest';
import {
    SIGNALLING_CASING_LAYER_ID,
    SIGNALLING_DASH_GROUPS,
    SIGNALLING_FAMILIES,
    SIGNALLING_LAYER_IDS,
    protectionCodes,
    signallingLayers,
    signallingPopupHtml,
    signallingSystems,
    signallingTilesUrl,
    signallingWithGaps,
    systemsLabel,
} from '$lib/signallingMap.js';
import { colorOf, compile, matches, spec } from './support/maplibre.js';

const t = (key) => key;

describe('familles et couches', () => {
    it('cinq familles de cantonnement, aucune pour le contrôle de vitesse', () => {
        expect(SIGNALLING_FAMILIES.map((f) => f.key)).toEqual([
            'automatic_block',
            'restricted_block',
            'manual_block',
            'telephone',
            'cab',
        ]);
        expect(SIGNALLING_FAMILIES.map((f) => f.key).join(' ')).not.toMatch(/kvb/i);
    });

    it('une couche par motif de trait, chaque famille dans une seule', () => {
        const all = SIGNALLING_DASH_GROUPS.flatMap((g) => g.keys);
        expect(all.sort()).toEqual(SIGNALLING_FAMILIES.map((f) => f.key).sort());
        expect(SIGNALLING_DASH_GROUPS).toHaveLength(3);
        expect(SIGNALLING_LAYER_IDS).toHaveLength(4);
    });

    it('le trait double la couleur : tirets et pointillés pour les blocks manuels et téléphoniques', () => {
        const dash = Object.fromEntries(SIGNALLING_FAMILIES.map((f) => [f.key, f.dash]));
        expect(dash.manual_block).not.toBeNull();
        expect(dash.telephone).not.toBeNull();
        expect(dash.manual_block).not.toEqual(dash.telephone);
    });

    it('expressions valides, couleur et filtre de chaque famille', () => {
        const layers = signallingLayers();
        for (const layer of layers) {
            for (const [prop, value] of Object.entries(layer.paint)) {
                // Motif de trait : un tableau constant (pas une expression), comme le veut MapLibre.
                if (prop === 'line-dasharray') {
                    expect(value.every((n) => typeof n === 'number' && n > 0)).toBe(true);
                    continue;
                }
                compile(value, spec('paint_line', prop));
            }
        }
        const strokes = layers.filter((l) => l.id !== SIGNALLING_CASING_LAYER_ID);
        for (const f of SIGNALLING_FAMILIES) {
            const layer = strokes.find((l) => l.filter && matches(l.filter, { family: f.key }));
            expect(layer, f.key).toBeDefined();
            expect(colorOf(layer.paint['line-color'], { family: f.key })).toBe(f.color);
            expect(
                strokes.filter((l) => l.filter && matches(l.filter, { family: f.key })),
            ).toHaveLength(1);
        }
    });

    it('le liseré a le même filtre que les traits : pas de trait blanc seul pour une famille inconnue', () => {
        const layers = signallingLayers();
        const casing = layers.find((l) => l.id === SIGNALLING_CASING_LAYER_ID);
        const strokes = layers.filter((l) => l.id !== SIGNALLING_CASING_LAYER_ID);
        for (const family of [...SIGNALLING_FAMILIES.map((f) => f.key), 'unknown', undefined, '']) {
            const drawn = strokes.some((l) => matches(l.filter, { family }));
            expect(matches(casing.filter, { family }), String(family)).toBe(drawn);
        }
        expect(matches(casing.filter, { family: 'unknown' })).toBe(false);
    });

    it('adresse des tuiles de l’ère', () => {
        expect(signallingTilesUrl('http://x', 2030)).toBe(
            'http://x/api/v1/eras/2030/signalling/tiles/{z}/{x}/{y}.mvt',
        );
    });
});

describe('protectionCodes', () => {
    it('lit la liste des tuiles, sans répéter la signalisation en cabine', () => {
        expect(protectionCodes({ signalling: 'TVM-430', train_protection: 'TVM-430' })).toEqual([]);
        expect(protectionCodes({ signalling: 'BAL', train_protection: 'KVB' })).toEqual(['KVB']);
        expect(protectionCodes({ signalling: 'ETCS_L2', train_protection: 'ERTMS_L2' })).toEqual([
            'ERTMS_L2',
        ]);
    });

    it('ajoute le KVB quand seule la case kvb_equipped le dit', () => {
        expect(
            protectionCodes({ signalling: 'BAPR', train_protection: '', kvb_equipped: 'true' }),
        ).toEqual(['KVB']);
        expect(
            protectionCodes({ signalling: 'BAPR', train_protection: '', kvb_equipped: false }),
        ).toEqual([]);
    });
});

describe('deux systèmes installés ensemble (ETCS niveau 2 et TVM)', () => {
    const tile = {
        signalling: 'ETCS_L2',
        signalling_systems: 'ETCS_L2,TVM-300',
        train_protection: 'ERTMS_L2,TVM-300',
    };
    const labels = {
        'lines.signalling.and': 'et',
        'lines.signalling.codes.ETCS_L2': 'ETCS niveau 2',
        'lines.signalling.codes.TVM-300': 'TVM 300',
    };
    const tr = (key) => labels[key] ?? key;

    it('lit la liste des tuiles (chaîne) comme celle de la fiche (tableau)', () => {
        expect(signallingSystems(tile)).toEqual(['ETCS_L2', 'TVM-300']);
        expect(
            signallingSystems({
                signalling: 'ETCS_L2',
                signalling_systems: ['ETCS_L2', 'TVM-300'],
            }),
        ).toEqual(['ETCS_L2', 'TVM-300']);
        // Tranche sans liste (avant la colonne) : son seul code.
        expect(signallingSystems({ signalling: 'BAL' })).toEqual(['BAL']);
        expect(
            signallingSystems({ signalling: 'TVM-430', signalling_systems: ['ETCS_L2'] }),
        ).toEqual(['TVM-430', 'ETCS_L2']);
    });

    it('nomme les deux systèmes, et la TVM une seule fois', () => {
        expect(systemsLabel(tile, tr)).toBe('ETCS niveau 2 et TVM 300');
        expect(systemsLabel({ signalling: 'ETCS_L2' }, tr)).toBe('ETCS niveau 2');
        expect(protectionCodes(tile)).toEqual(['ERTMS_L2']);
        const html = signallingPopupHtml(
            { ...tile, family: 'cab', line_name: 'LGV' },
            tr,
            'fr',
            '',
        );
        expect(html).toContain('ETCS niveau');
        expect(html).toContain('TVM');
        expect(html.match(/TVM/g)).toHaveLength(1);
    });
});

describe('signallingPopupHtml', () => {
    it('cantonnement et contrôle de vitesse sur deux lignes distinctes', () => {
        const html = signallingPopupHtml(
            {
                line_name: 'Ligne A',
                family: 'automatic_block',
                signalling: 'BAL',
                train_protection: 'KVB',
                pk_start_m: 8285,
                pk_end_m: 344700,
            },
            t,
            'fr',
            '',
        );
        expect(html).toContain('maps.signalling.block_label');
        expect(html).toContain('maps.signalling.protection_label');
        // Code sans traduction : le code lui-même.
        expect(html).toContain('</span>KVB</li>');
        expect(html).toContain('8+285 - 344+700');
    });

    it('signalisation en cabine sur LGV', () => {
        const html = signallingPopupHtml(
            { line_name: 'LGV', family: 'cab', signalling: 'TVM-430', train_protection: 'TVM-430' },
            t,
            'fr',
            '',
        );
        expect(html).toContain('maps.signalling.cab_label');
        expect(html).not.toContain('maps.signalling.protection_label');
    });
});

describe('signallingWithGaps', () => {
    const r = (a, b) => ({ pk_start_m: a, pk_end_m: b });

    it('intercale un trou là où aucune tranche ne couvre les PK', () => {
        const rows = signallingWithGaps([r(0, 1000), r(5000, 9000)]);
        expect(rows).toHaveLength(3);
        expect(rows[1]).toMatchObject({ gap: true, from: '1+000', to: '5+000' });
    });

    it('ignore les écarts de moins de 100 m et les tranches qui se chevauchent', () => {
        expect(signallingWithGaps([r(0, 1000), r(1050, 2000)])).toHaveLength(2);
        expect(signallingWithGaps([r(0, 9000), r(2000, 3000), r(8000, 12000)])).toHaveLength(3);
    });

    it('liste vide : rien', () => {
        expect(signallingWithGaps([])).toEqual([]);
    });
});

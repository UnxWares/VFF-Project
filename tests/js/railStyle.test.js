import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import {
    ACTIVE_STATUSES,
    DRAW_ORDER,
    ELECTRIFICATION_STYLES,
    GAUGE_STYLES,
    HERITAGE_COLOR,
    HERITAGE_STYLES,
    LGV_COLOR,
    MAP_MODES,
    NOT_COVERED,
    REOPENING_COLOR,
    STATION_CLOSED_COLOR,
    STATION_STYLES,
    STATION_UNCERTAIN_COLOR,
    STATION_UNCERTAIN_FILL,
    STATUS_COLOR,
    STATUS_STYLES,
    THREATENED_COLOR,
    TOGGLE_KEYS,
    TRAFFIC_COLOR,
    TRAFFIC_STYLES,
    VELORAIL_DASH,
    electrificationColorExpr,
    gaugeColorExpr,
    isLgvExpr,
    isVelorailExpr,
    modeColorExpr,
    segmentColorExpr,
    stationColorExpr,
    stationFillExpr,
    stationStrokeWidthExpr,
    swatchStyle,
    trafficColorExpr,
} from '$lib/railStyle.js';
import { colorOf, compile, evaluate, matches, spec } from './support/maplibre.js';

const ORANGE = '#ff6500';
const FREIGHT_BLUE = '#0284c7';
const SLATE = '#475569';

const lineColor = spec('paint_line', 'line-color');
const circleColor = spec('paint_circle', 'circle-color');
const circleStrokeColor = spec('paint_circle', 'circle-stroke-color');
const circleStrokeWidth = spec('paint_circle', 'circle-stroke-width');
const lineSortKey = spec('layout_line', 'line-sort-key');

/** Valeurs d'un enum PHP (`case X = 'valeur';`), lues dans le code source du back. */
function phpEnumValues(relativePath) {
    const source = readFileSync(new URL(`../../${relativePath}`, import.meta.url), 'utf8');
    return [...source.matchAll(/^\s*case\s+\w+\s*=\s*'([^']+)'\s*;/gm)].map((m) => m[1]);
}

/** Valeurs d'une constante PHP tableau de chaînes (`public const NOM = ['a', 'b'];`). */
function phpConstStrings(relativePath, name) {
    const source = readFileSync(new URL(`../../${relativePath}`, import.meta.url), 'utf8');
    const m = source.match(new RegExp(`const\\s+${name}\\s*=\\s*\\[([^\\]]*)\\]`));
    if (!m) throw new Error(`Constante ${name} introuvable dans ${relativePath}`);
    return [...m[1].matchAll(/'([^']+)'/g)].map((x) => x[1]);
}

const segment = (props) => ({ is_heritage: false, is_lgv: false, ...props });

describe('expressions MapLibre valides', () => {
    it.each([
        ['segmentColorExpr', segmentColorExpr(), lineColor],
        ['trafficColorExpr', trafficColorExpr(), lineColor],
        ['electrificationColorExpr', electrificationColorExpr(), lineColor],
        ['gaugeColorExpr', gaugeColorExpr(), lineColor],
        ['stationColorExpr', stationColorExpr(), circleStrokeColor],
        ['stationFillExpr', stationFillExpr(), circleColor],
        ['stationStrokeWidthExpr', stationStrokeWidthExpr(), circleStrokeWidth],
        ['DRAW_ORDER', DRAW_ORDER, lineSortKey],
    ])('%s se compile pour sa propriété de style', (_name, expression, propertySpec) => {
        expect(() => compile(expression, propertySpec)).not.toThrow();
    });

    it.each([
        ['isLgvExpr', isLgvExpr()],
        ['isVelorailExpr', isVelorailExpr()],
        ['NOT_COVERED', NOT_COVERED],
    ])('%s est un filtre valide', (_name, filter) => {
        expect(() => matches(filter, {})).not.toThrow();
    });

    it('les fabriques renvoient une nouvelle expression à chaque appel (pas de partage mutable)', () => {
        expect(segmentColorExpr()).not.toBe(segmentColorExpr());
        expect(segmentColorExpr()).toEqual(segmentColorExpr());
    });
});

describe('réseau à grande vitesse', () => {
    it('isLgvExpr : drapeau is_lgv, ou trafic « voyageurs seuls » (produit pour les seules LGV)', () => {
        expect(matches(isLgvExpr(), { is_lgv: true, traffic_type: 'mixed' })).toBe(true);
        expect(matches(isLgvExpr(), { is_lgv: false, traffic_type: 'passenger' })).toBe(true);
        expect(matches(isLgvExpr(), { is_lgv: false, traffic_type: 'mixed' })).toBe(false);
        expect(matches(isLgvExpr(), { traffic_type: 'freight' })).toBe(false);
        expect(matches(isLgvExpr(), {})).toBe(false);
    });

    it('une LGV en service est violette, quel que soit son trafic', () => {
        expect(
            colorOf(
                segmentColorExpr(),
                segment({ status: 'in_service', is_lgv: true, traffic_type: 'mixed' }),
            ),
        ).toBe(LGV_COLOR);
        expect(
            colorOf(
                segmentColorExpr(),
                segment({ status: 'in_service', is_lgv: true, traffic_type: 'freight' }),
            ),
        ).toBe(LGV_COLOR);
        expect(
            colorOf(
                segmentColorExpr(),
                segment({ status: 'in_service', traffic_type: 'passenger' }),
            ),
        ).toBe(LGV_COLOR);
        expect(colorOf(trafficColorExpr(), { is_lgv: true, traffic_type: 'unknown' })).toBe(
            LGV_COLOR,
        );
    });

    it('le violet est réservé aux LGV en mode trafic (lignes, statuts, patrimoine, gares)', () => {
        const others = [
            ...TRAFFIC_STYLES.filter((t) => t.key !== 'lgv').map((t) => t.color),
            ...STATUS_STYLES.map((s) => s.color),
            ...HERITAGE_STYLES.map((h) => h.color),
            ...STATION_STYLES.map((s) => s.color),
            STATION_CLOSED_COLOR,
            STATION_UNCERTAIN_COLOR,
            HERITAGE_COLOR,
            THREATENED_COLOR,
            REOPENING_COLOR,
        ].map((c) => c.toLowerCase());
        expect(others).not.toContain(LGV_COLOR.toLowerCase());
        expect(TRAFFIC_STYLES.find((t) => t.key === 'lgv')).toMatchObject({
            color: LGV_COLOR,
            thick: true,
        });
        expect(TRAFFIC_COLOR.passenger).toBe(LGV_COLOR);
    });

    it('une LGV fermée prend la couleur de son statut, pas le violet', () => {
        expect(
            colorOf(
                segmentColorExpr(),
                segment({ status: 'abandoned', is_lgv: true, traffic_type: 'passenger' }),
            ),
        ).toBe(STATUS_COLOR.abandoned);
    });
});

describe('couleur des portions selon le trafic (réseau classique)', () => {
    it.each(ACTIVE_STATUSES)('%s : mixte orange, fret bleu, inconnu ardoise', (status) => {
        expect(colorOf(segmentColorExpr(), segment({ status, traffic_type: 'mixed' }))).toBe(
            ORANGE,
        );
        expect(colorOf(segmentColorExpr(), segment({ status, traffic_type: 'freight' }))).toBe(
            FREIGHT_BLUE,
        );
        expect(colorOf(segmentColorExpr(), segment({ status, traffic_type: 'unknown' }))).toBe(
            SLATE,
        );
    });

    it('un trafic absent ou non prévu est « inconnu », jamais fret ni orange', () => {
        expect(colorOf(segmentColorExpr(), segment({ status: 'in_service' }))).toBe(SLATE);
        expect(
            colorOf(segmentColorExpr(), segment({ status: 'in_service', traffic_type: 'banane' })),
        ).toBe(SLATE);
        expect(colorOf(trafficColorExpr(), {})).toBe(TRAFFIC_COLOR.unknown);
    });

    it("les couleurs de trafic reprennent l'identité VFF", () => {
        expect(TRAFFIC_COLOR).toMatchObject({
            lgv: LGV_COLOR,
            mixed: ORANGE,
            freight: FREIGHT_BLUE,
            unknown: SLATE,
            passenger: LGV_COLOR,
        });
    });
});

describe('statuts sans trafic', () => {
    it.each(STATUS_STYLES.map((s) => [s.key, s.color]))(
        '%s prend sa propre couleur',
        (status, color) => {
            expect(colorOf(segmentColorExpr(), segment({ status, traffic_type: 'mixed' }))).toBe(
                color.toLowerCase(),
            );
        },
    );

    it("un statut inconnu tombe sur l'ardoise « inconnu »", () => {
        expect(colorOf(segmentColorExpr(), segment({ status: 'statut_inconnu' }))).toBe(
            TRAFFIC_COLOR.unknown,
        );
        expect(colorOf(segmentColorExpr(), segment({}))).toBe(TRAFFIC_COLOR.unknown);
    });

    it("chaque statut de l'enum PHP SegmentStatus a un style (actif ou non)", () => {
        const styled = [...ACTIVE_STATUSES, ...STATUS_STYLES.map((s) => s.key)];
        const statuses = phpEnumValues('app/Enums/SegmentStatus.php');
        expect(statuses.length).toBeGreaterThan(0);
        expect([...styled].sort()).toEqual([...statuses].sort());
    });

    it('les statuts actifs sont ceux de SegmentStatus::isActive et de ConsistencyChecker::ACTIVE_SEGMENTS', () => {
        expect(ACTIVE_STATUSES).toEqual(
            phpConstStrings('app/Services/ConsistencyChecker.php', 'ACTIVE_SEGMENTS'),
        );
    });

    it('trait : fermée en tirets, déposée en pointillés, voie de service pleine', () => {
        const byKey = Object.fromEntries(STATUS_STYLES.map((s) => [s.key, s]));
        expect(byKey.service_track.dash).toBeNull();
        expect(byKey.abandoned.dash).toEqual([2.5, 1.5]);
        expect(byKey.dismantled.dash).toEqual([1, 1.5]);
        // « razed » partage la case de légende des voies déposées.
        expect(byKey.razed.toggle).toBe('dismantled');
        for (const s of STATUS_STYLES) {
            if (s.dash) expect(s.dash.every((v) => v > 0)).toBe(true);
        }
    });
});

describe('patrimoine (exploitations associatives)', () => {
    it('train touristique ou musée (voie en service) : vert, prioritaire sur le trafic et la LGV', () => {
        for (const kind of ['tourist_train', 'museum']) {
            const p = segment({
                status: 'in_service',
                is_heritage: true,
                heritage_kind: kind,
                traffic_type: 'mixed',
            });
            expect(colorOf(segmentColorExpr(), p)).toBe(HERITAGE_COLOR);
            expect(matches(isVelorailExpr(), p)).toBe(false);
        }
        expect(
            colorOf(
                segmentColorExpr(),
                segment({ status: 'in_service', is_heritage: true, is_lgv: true }),
            ),
        ).toBe(HERITAGE_COLOR);
    });

    it('vélorail (fermée + is_heritage) : vert en pointillés, jamais dans la couche rouge des lignes fermées', () => {
        const velorail = segment({
            status: 'abandoned',
            is_heritage: true,
            heritage_kind: 'velorail',
        });
        expect(matches(isVelorailExpr(), velorail)).toBe(true);
        expect(colorOf(segmentColorExpr(), velorail)).toBe(HERITAGE_COLOR);
        expect(colorOf(segmentColorExpr(), velorail)).not.toBe(STATUS_COLOR.abandoned);
    });

    it("une ligne fermée sans association n'est pas un vélorail", () => {
        expect(matches(isVelorailExpr(), segment({ status: 'abandoned' }))).toBe(false);
    });

    it('styles de légende : train plein, vélorail en pointillés VELORAIL_DASH, même vert', () => {
        const byKey = Object.fromEntries(HERITAGE_STYLES.map((h) => [h.key, h]));
        expect(byKey.heritage).toMatchObject({ color: HERITAGE_COLOR, dash: null });
        expect(byKey.velorail).toMatchObject({ color: HERITAGE_COLOR, dash: VELORAIL_DASH });
        expect(HERITAGE_COLOR).not.toBe(REOPENING_COLOR);
    });
});

describe('voies partagées', () => {
    it('NOT_COVERED écarte seulement les portions recouvertes par une ligne plus active', () => {
        expect(matches(NOT_COVERED, { covered: true })).toBe(false);
        expect(matches(NOT_COVERED, { covered: false })).toBe(true);
        expect(matches(NOT_COVERED, {})).toBe(true);
    });

    it('DRAW_ORDER : draw_rank, 0 par défaut', () => {
        expect(evaluate(DRAW_ORDER, lineSortKey, { draw_rank: 5 })).toBe(5);
        expect(evaluate(DRAW_ORDER, lineSortKey, {})).toBe(0);
    });
});

describe('gares', () => {
    const OPEN_STATIONS = phpConstStrings('app/Services/ConsistencyChecker.php', 'OPEN_STATIONS');

    it('mêmes teintes que les lignes : voyageurs orange, fret bleu, touristique vert', () => {
        expect(colorOf(stationColorExpr(), { status: 'active' }, circleStrokeColor)).toBe(ORANGE);
        expect(colorOf(stationColorExpr(), { status: 'no_commercial' }, circleStrokeColor)).toBe(
            FREIGHT_BLUE,
        );
        expect(colorOf(stationColorExpr(), { status: 'tourist' }, circleStrokeColor)).toBe(
            HERITAGE_COLOR,
        );
        expect(colorOf(stationColorExpr(), { status: 'uncertain' }, circleStrokeColor)).toBe(
            STATION_UNCERTAIN_COLOR,
        );
    });

    it('une gare fermée, sans statut ou de statut inconnu est grise, jamais orange', () => {
        for (const props of [
            { status: 'declassified' },
            {},
            { status: null },
            { status: 'demolie' },
        ]) {
            const color = colorOf(stationColorExpr(), props, circleStrokeColor);
            expect(color).toBe(STATION_CLOSED_COLOR);
            expect(color).not.toBe(ORANGE);
        }
    });

    it('chaque statut ouvert de ConsistencyChecker::OPEN_STATIONS a une couleur distincte de celle des gares fermées', () => {
        expect(OPEN_STATIONS.length).toBeGreaterThan(0);
        for (const status of OPEN_STATIONS) {
            expect(STATION_STYLES.map((s) => s.key)).toContain(status);
            expect(colorOf(stationColorExpr(), { status }, circleStrokeColor)).not.toBe(
                STATION_CLOSED_COLOR,
            );
        }
        // Seule une gare voyageurs est orange.
        const orange = STATION_STYLES.filter((s) => s.color.toLowerCase() === ORANGE).map(
            (s) => s.key,
        );
        expect(orange).toEqual(['active']);
    });

    it("une gare incertaine n'est jamais montrée ouverte : contour ardoise, disque grisé, trait plus fin", () => {
        expect(OPEN_STATIONS).not.toContain('uncertain');
        expect(colorOf(stationFillExpr(), { status: 'uncertain' }, circleColor)).toBe(
            STATION_UNCERTAIN_FILL,
        );
        expect(colorOf(stationFillExpr(), { status: 'active' }, circleColor)).toBe('#ffffff');
        expect(evaluate(stationStrokeWidthExpr(), circleStrokeWidth, { status: 'uncertain' })).toBe(
            1.5,
        );
        expect(evaluate(stationStrokeWidthExpr(), circleStrokeWidth, { status: 'active' })).toBe(2);
        expect(STATION_UNCERTAIN_COLOR).not.toBe(STATION_CLOSED_COLOR);
    });

    it('la légende des gares couvre tous les statuts, une seule fois chacun', () => {
        const keys = STATION_STYLES.map((s) => s.key);
        expect(new Set(keys).size).toBe(keys.length);
        expect(keys).toEqual(
            expect.arrayContaining([...OPEN_STATIONS, 'uncertain', 'declassified']),
        );
        for (const s of STATION_STYLES) expect(s.labelKey).toMatch(/^maps\.legend\.station_/);
    });
});

describe('mode électrification', () => {
    const elec = Object.fromEntries(
        ELECTRIFICATION_STYLES.map((s) => [s.key, s.color.toLowerCase()]),
    );
    const colorFor = (props) => colorOf(electrificationColorExpr(), props);

    it.each([
        [25000, 'ac25'],
        [1500, 'dc1500'],
        [3000, 'dc3000'],
        [15000, 'ac15'],
        [750, 'low'],
        [600, 'low'],
        [1000, 'low'],
        [5000, 'other'],
    ])('électrifiée en %i V : %s', (voltage, key) => {
        expect(colorFor({ electrified: true, voltage_v: voltage })).toBe(elec[key]);
    });

    it('tension inconnue, valeurs en chaîne (tuiles MVT) et booléen texte', () => {
        expect(colorFor({ electrified: true })).toBe(elec.unknown_voltage);
        expect(colorFor({ electrified: true, voltage_v: null })).toBe(elec.unknown_voltage);
        expect(colorFor({ electrified: 'true', voltage_v: '25000' })).toBe(elec.ac25);
    });

    it("non électrifiée : « date inconnue » si l'électrification actuelle n'est pas datée, sinon « aucune »", () => {
        expect(colorFor({ electrified: false, electrification_source: 'unknown' })).toBe(
            elec.unknown_date,
        );
        expect(colorFor({ electrified: false, voltage_v: 25000 })).toBe(elec.none);
        expect(colorFor({})).toBe(elec.none);
    });

    it('une couleur et une clé i18n par catégorie', () => {
        const keys = ELECTRIFICATION_STYLES.map((s) => s.key);
        expect(new Set(keys).size).toBe(keys.length);
        for (const s of ELECTRIFICATION_STYLES)
            expect(s.labelKey).toBe(`maps.electrification.${s.key}`);
    });
});

describe('mode écartement', () => {
    const gauge = Object.fromEntries(GAUGE_STYLES.map((s) => [s.key, s.color.toLowerCase()]));

    it.each(['standard', 'metre', 'narrow', 'broad'])('%s', (key) => {
        expect(colorOf(gaugeColorExpr(), { gauge_class: key })).toBe(gauge[key]);
    });

    it('écartement absent ou non prévu : inconnu', () => {
        expect(colorOf(gaugeColorExpr(), {})).toBe(gauge.unknown);
        expect(colorOf(gaugeColorExpr(), { gauge_class: '1067' })).toBe(gauge.unknown);
    });
});

describe("modes d'affichage", () => {
    it('modeColorExpr : trafic = couleur propre de la couche, sinon électrification ou écartement', () => {
        const fallback = ['literal', 'rouge'];
        expect(MAP_MODES).toEqual(['traffic', 'electrification', 'gauge']);
        expect(modeColorExpr('traffic', fallback)).toBe(fallback);
        expect(modeColorExpr('inconnu', fallback)).toBe(fallback);
        expect(modeColorExpr('electrification', fallback)).toEqual(electrificationColorExpr());
        expect(modeColorExpr('gauge', fallback)).toEqual(gaugeColorExpr());
    });
});

describe('légende', () => {
    it('TOGGLE_KEYS : chaque catégorie de trafic, de patrimoine et de statut a une case, sans doublon', () => {
        expect(new Set(TOGGLE_KEYS).size).toBe(TOGGLE_KEYS.length);
        for (const t of TRAFFIC_STYLES) expect(TOGGLE_KEYS).toContain(t.key);
        for (const h of HERITAGE_STYLES) expect(TOGGLE_KEYS).toContain(h.key);
        for (const s of STATUS_STYLES) expect(TOGGLE_KEYS).toContain(s.toggle);
    });

    it('swatchStyle : trait plein, épais pour la LGV, motif de tirets (2 px minimum)', () => {
        expect(swatchStyle('#ff6500')).toBe('background:#ff6500;height:4px;');
        expect(swatchStyle(LGV_COLOR, null, true)).toBe(`background:${LGV_COLOR};height:6px;`);
        expect(swatchStyle('#dc2626', [2.5, 1.5])).toBe(
            'background:repeating-linear-gradient(90deg, #dc2626 0 10px, transparent 10px 16px);height:4px;',
        );
        // Pointillés très fins (vélorail) : jamais de motif de moins de 2 px, invisible à l'écran.
        expect(swatchStyle(HERITAGE_COLOR, [0.1, 0.2])).toContain(
            `${HERITAGE_COLOR} 0 2px, transparent 2px 4px`,
        );
    });

    it('toutes les couleurs sont des hexadécimaux #rrggbb', () => {
        const colors = [
            ...TRAFFIC_STYLES,
            ...STATUS_STYLES,
            ...HERITAGE_STYLES,
            ...STATION_STYLES,
            ...ELECTRIFICATION_STYLES,
            ...GAUGE_STYLES,
        ].map((s) => s.color);
        colors.push(
            STATION_CLOSED_COLOR,
            STATION_UNCERTAIN_COLOR,
            STATION_UNCERTAIN_FILL,
            THREATENED_COLOR,
            REOPENING_COLOR,
        );
        for (const c of colors) expect(c).toMatch(/^#[0-9a-f]{6}$/i);
    });
});

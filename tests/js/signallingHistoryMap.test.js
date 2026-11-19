// Calque « Signalisation » aux époques passées : traits de l'historique daté (tuiles des ères passées),
// style selon la portion connue ou non, bulle avec la date, la portion et la source Wikipédia.
import { describe, expect, it } from 'vitest';
import {
    SIGNALLING_DASH_GROUPS,
    SIGNALLING_UNKNOWN_OPACITY,
    historyPortion,
    signallingLayers,
    signallingPopupHtml,
    signallingTilesUrl,
} from '$lib/signallingMap.js';
import { compile, spec } from './support/maplibre.js';

const t = (key, opts) => (opts ? `${key} ${JSON.stringify(opts)}` : key);

/** Propriétés d'un trait de l'historique, comme les sert SignallingHistoryMap::tile(). */
const feature = (extra = {}) => ({
    history: true,
    signalling: 'BAL',
    family: 'automatic_block',
    role: 'block',
    date: '1962-06-01',
    date_precision: 'month',
    scope: 'section',
    location: 'section',
    section_from: 'Garches',
    section_to: 'Saint-Nom-la-Bretèche',
    pk_start_m: 16714,
    pk_end_m: 29913,
    origin: 'text',
    source_title: 'Ligne de Saint-Cloud à Saint-Nom-la-Bretèche - Forêt-de-Marly',
    source_url: 'https://fr.wikipedia.org/w/index.php?title=X&oldid=240844633',
    sort: 119620601,
    line_slug: 'rfn-974000',
    line_name: 'Ligne de St-Cloud à St-Nom-la-Bretèche-Forêt-de-Marly',
    ...extra,
});

describe('tuiles par ère', () => {
    it('demande les tuiles de l’ère affichée, passée ou courante', () => {
        expect(signallingTilesUrl('https://vff.test', 1990)).toBe(
            'https://vff.test/api/v1/eras/1990/signalling/tiles/{z}/{x}/{y}.mvt',
        );
        expect(signallingTilesUrl('https://vff.test', 2030, '?v=3')).toContain('/eras/2030/');
    });
});

describe('style des traits historiques', () => {
    const lines = signallingLayers().filter((l) =>
        SIGNALLING_DASH_GROUPS.some((g) => g.id === l.id),
    );

    it('les faits récents et les portions placées passent dessus (clé de tri des tuiles)', () => {
        for (const layer of signallingLayers()) {
            expect(layer.layout['line-sort-key']).toEqual(['coalesce', ['get', 'sort'], 0]);
        }
    });

    it('une ligne entière faute de portion connue est plus pâle et plus fine', () => {
        for (const layer of lines) {
            const opacity = compile(
                layer.paint['line-opacity'],
                spec('paint_line', 'line-opacity'),
            );
            expect(
                opacity.evaluate({ zoom: 8 }, { type: 2, properties: { location: 'unknown' } }),
            ).toBe(SIGNALLING_UNKNOWN_OPACITY);
            expect(
                opacity.evaluate({ zoom: 8 }, { type: 2, properties: { location: 'section' } }),
            ).toBe(1);
            // L'état actuel (SNCF Réseau) n'a pas de `location` : trait plein.
            expect(opacity.evaluate({ zoom: 8 }, { type: 2, properties: {} })).toBe(1);
            const width = compile(layer.paint['line-width'], spec('paint_line', 'line-width'));
            expect(
                width.evaluate({ zoom: 9 }, { type: 2, properties: { location: 'unknown' } }),
            ).toBeLessThan(
                width.evaluate({ zoom: 9 }, { type: 2, properties: { location: 'line' } }),
            );
        }
    });
});

describe('historyPortion', () => {
    it('portion placée : lieux de l’article et PK', () => {
        const text = historyPortion(feature(), t);
        expect(text).toContain('signalling_history.section.between');
        expect(text).toContain('16+714');
        expect(text).toContain('29+913');
    });

    it('toute la ligne d’après l’article', () => {
        expect(historyPortion(feature({ location: 'line', scope: 'whole_line' }), t)).toBe(
            'maps.signalling.history.whole_line',
        );
    });

    it('ligne entière, section inconnue ; lieux cités mais non placés', () => {
        expect(
            historyPortion(
                feature({
                    location: 'unknown',
                    scope: 'unspecified',
                    section_from: null,
                    section_to: null,
                }),
                t,
            ),
        ).toBe('maps.signalling.history.unknown');
        expect(historyPortion(feature({ location: 'unknown', pk_start_m: null }), t)).toContain(
            'maps.signalling.history.unplaced',
        );
    });
});

describe('signallingHistoryPopupHtml', () => {
    it('donne le système, la date au mois, la portion et la source CC BY-SA', () => {
        const html = signallingPopupHtml(
            feature(),
            t,
            'fr',
            '<a href="/lines/rfn-974000">fiche</a>',
        );
        expect(html).toContain('maps.signalling.block_label');
        expect(html).toContain('maps.signalling.history.date_label');
        expect(html).toContain('juin 1962');
        expect(html).not.toContain('1 juin');
        expect(html).toContain('oldid=240844633');
        expect(html).toContain('target="_blank"');
        expect(html).toContain('maps.signalling.history.source');
        expect(html).not.toContain('maps.signalling.source');
    });

    it('signalisation en cabine datée de l’ouverture d’une LGV : note et libellé « cabine »', () => {
        const html = signallingPopupHtml(
            feature({
                signalling: 'TVM-430',
                family: 'cab',
                role: 'cab',
                origin: 'opening',
                date: '1993-01-01',
                date_precision: 'year',
                location: 'unknown',
                scope: 'unspecified',
                section_from: null,
                section_to: null,
            }),
            t,
            'en',
            '',
        );
        expect(html).toContain('maps.signalling.cab_label');
        expect(html).toContain('maps.signalling.history.opening');
        expect(html).toContain('1993');
        expect(html).toContain('maps.signalling.history.unknown');
    });

    it('échappe le texte venu des tuiles', () => {
        const html = signallingPopupHtml(
            feature({ line_name: '<b>x</b>', section_from: '<i>' }),
            t,
            'fr',
            '',
        );
        expect(html).not.toContain('<b>x</b>');
        expect(html).not.toContain('<i>');
    });

    it('une tranche actuelle (sans `history`) garde la bulle de SNCF Réseau', () => {
        const html = signallingPopupHtml(
            { signalling: 'BAL', family: 'automatic_block', line_name: 'L' },
            t,
            'fr',
            '',
        );
        expect(html).toContain('maps.signalling.source');
        expect(html).not.toContain('maps.signalling.history');
    });
});

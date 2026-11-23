import { describe, expect, it } from 'vitest';
import {
    dateParts,
    FOLD_AFTER,
    isNotable,
    pkText,
    structureName,
    summaryList,
    visibleItems,
} from '../../resources/js/lib/lineStructures.js';

// Traduction factice : la clé suivie des paramètres, pour vérifier le choix de la clé.
const t = (key, params = {}) =>
    Object.keys(params).length ? `${key}(${Object.values(params).join('|')})` : key;

const item = (over = {}) => ({
    id: 1,
    kind: 'tunnel',
    name: null,
    length_m: 120,
    pk_start_label: null,
    pk_end_label: null,
    built: null,
    opened: null,
    closed: null,
    ...over,
});

describe('lineStructures', () => {
    it('names an unnamed structure by its kind and capitalises a Wikidata label', () => {
        expect(structureName(item(), t)).toBe('structures.line.unnamed.tunnel');
        expect(structureName(item({ name: 'viaduc de Garabit' }), t)).toBe('Viaduc de Garabit');
    });

    it('gives one PK or a range, and nothing without PK', () => {
        expect(pkText(item(), t)).toBeNull();
        expect(pkText(item({ pk_start_label: '12+340', pk_end_label: '12+340' }), t)).toBe(
            'structures.line.pk(12+340)',
        );
        expect(pkText(item({ pk_start_label: '12+340', pk_end_label: '12+905' }), t)).toBe(
            'structures.line.pk_range(12+340|12+905)',
        );
    });

    it('words each date by its precision', () => {
        const parts = dateParts(
            item({
                built: { date: '1880-01-01', precision: 'year' },
                opened: { date: '1888-11-10', precision: 'day' },
            }),
            t,
            'fr-FR',
        );
        expect(parts).toEqual([
            'structures.line.built(1880)',
            'structures.line.opened_day(10 novembre 1888)',
        ]);
    });

    it('folds long lists and keeps named or long structures visible', () => {
        const few = Array.from({ length: FOLD_AFTER }, (_, i) => item({ id: i }));
        expect(visibleItems(few)).toEqual({ items: few, folded: false });

        const many = [
            ...few,
            item({ id: 100, name: 'tunnel du Lioran' }),
            item({ id: 101, length_m: 300 }),
        ];
        const view = visibleItems(many);
        expect(view.folded).toBe(true);
        expect(view.items.map((i) => i.id)).toEqual([100, 101]);
        expect(isNotable(item({ length_m: null }))).toBe(false);
    });

    it('lists the counts by kind, singular or plural, joined for the language', () => {
        const summary = { by_kind: { tunnel: 3, viaduct: 1, bridge: 0 } };
        expect(summaryList(summary, t, 'fr-FR')).toBe(
            'structures.line.count.tunnel_other(3) et structures.line.count.viaduct_one(1)',
        );
    });
});

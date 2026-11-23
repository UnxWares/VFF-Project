// Correction d'un événement daté depuis la chronologie (resources/js/Components/Contribute/proposals.js) :
// préremplissage du formulaire, contrôles de la raison et de la citation courte, faits montrés aux
// validateurs et nature de la proposition.
import { describe, expect, it } from 'vitest';
import {
    correctedEventText,
    correctionDefaults,
    eventErrors,
    eventFacts,
    proposalKindKey,
} from '../../resources/js/Components/Contribute/proposals.js';

const DICT = {
    'editor.event_types.closure_passenger': 'Fermeture aux voyageurs',
    'editor.event_types.opening': 'Ouverture',
    'lines.sources.wikipedia': 'Wikipédia',
    'proposals.event.errors.reason': 'Raison de {min} à {max}',
    'proposals.event.errors.excerpt_length': 'Citation de {max} au plus',
    'proposals.review.event_of': '{type} du {date}',
    'proposals.review.places_range': 'de {from} à {to}',
    'proposals.review.pk_range': 'du PK {start} au PK {end}',
    'proposals.review.corrects': 'Remplace',
    'proposals.review.correction_reason': 'Ce qui est faux',
    'proposals.review.quote': 'Phrase citée',
};
const t = (key, opts = {}) =>
    (DICT[key] ?? key).replace(/\{(\w+)\}/g, (_, k) => String(opts[k] ?? ''));
const NOW = new Date('2026-11-23T12:00:00Z');
const TYPES = ['opening', 'closure_passenger', 'other'];

const wrong = {
    event_id: 41,
    source: 'wikipedia',
    type: 'closure_passenger',
    date: '1969-03-06',
    precision: 'day',
    from: 'Saint-Cloud',
    to: 'Versailles',
    pk_start_m: null,
    pk_end_m: null,
};

describe('préremplissage d’une correction', () => {
    it('reprend le type, la date découpée et la portion entre deux lieux', () => {
        expect(correctionDefaults(wrong, TYPES)).toEqual({
            type: 'closure_passenger',
            precision: 'day',
            year: '1969',
            month: 3,
            day: '6',
            portion: 'places',
            from: 'Saint-Cloud',
            to: 'Versailles',
            pk: { start: { km: '', m: '' }, end: { km: '', m: '' } },
        });
    });

    it('découpe les PK en kilomètres et mètres et garde une année seule', () => {
        const d = correctionDefaults(
            {
                ...wrong,
                precision: 'year',
                date: '1969-01-01',
                from: null,
                to: null,
                pk_start_m: 12345,
                pk_end_m: 20000,
            },
            TYPES,
        );
        expect([d.year, d.month, d.day, d.portion]).toEqual(['1969', '', '', 'pk']);
        expect(d.pk).toEqual({ start: { km: '12', m: '345' }, end: { km: '20', m: '0' } });
    });

    it('retombe sur « autre » pour un type que le formulaire ne propose pas, et sur toute la ligne', () => {
        const d = correctionDefaults({ ...wrong, type: 'mystery', from: null, to: null }, TYPES);
        expect([d.type, d.portion]).toEqual(['other', 'whole']);
    });
});

describe('contrôles d’une correction', () => {
    const valid = {
        line_slug: 'rfn-610000',
        type: 'closure_passenger',
        precision: 'day',
        date: '1972-03-06',
        portion: 'whole',
        source_kind: 'web',
        source_title: 'Gare de Saint-Cloud',
        source_url: 'https://fr.wikipedia.org/wiki/Gare',
        source_license: null,
        corrects_event_id: 41,
        correction_reason: 'La source date le report sur la route.',
        source_excerpt: null,
    };

    it('accepte une correction expliquée', () => {
        expect(eventErrors(valid, t, NOW)).toEqual({});
    });

    it('demande une raison de 10 à 300 caractères', () => {
        expect(eventErrors({ ...valid, correction_reason: 'Faux.' }, t, NOW)).toEqual({
            correction_reason: 'Raison de 10 à 300',
        });
        expect(eventErrors({ ...valid, correction_reason: 'x'.repeat(301) }, t, NOW)).toEqual({
            correction_reason: 'Raison de 10 à 300',
        });
    });

    it('ne demande pas de raison pour un nouvel événement', () => {
        expect(
            eventErrors({ ...valid, corrects_event_id: null, correction_reason: null }, t, NOW),
        ).toEqual({});
    });

    it('limite la citation courte à 300 caractères', () => {
        expect(eventErrors({ ...valid, source_excerpt: 'a'.repeat(301) }, t, NOW)).toEqual({
            source_excerpt: 'Citation de 300 au plus',
        });
    });
});

describe('correction vue par les validateurs', () => {
    const payload = {
        event_type: 'closure_passenger',
        date: '1972-03-06',
        precision: 'day',
        portion: { kind: 'whole' },
        source: { kind: 'web', title: 'Gare', url: 'https://fr.wikipedia.org/wiki/Gare' },
        source_excerpt: 'fermée au service des voyageurs le 6 mars 1972',
        corrects: wrong,
        correction_reason: 'Report sur la route en 1969.',
    };

    it('montre l’événement remplacé, la raison et la phrase citée', () => {
        const facts = Object.fromEntries(eventFacts(payload, t, 'fr').map((f) => [f.id, f.text]));
        expect(facts.quote).toBe('fermée au service des voyageurs le 6 mars 1972');
        expect(facts.reason).toBe('Report sur la route en 1969.');
        expect(facts.corrects).toMatch(
            /^Fermeture aux voyageurs du .*1969, de Saint-Cloud à Versailles \(Wikipédia\)$/,
        );
    });

    it('écrit l’événement remplacé avec ses PK', () => {
        expect(
            correctedEventText(
                { ...wrong, from: null, to: null, pk_start_m: 1000, pk_end_m: 4500, source: null },
                t,
                'fr',
            ),
        ).toMatch(/, du PK 1\+000 au PK 4\+500$/);
    });

    it('nomme la nature de la proposition', () => {
        expect(proposalKindKey({ type: 'line_event', payload })).toBe(
            'proposals.review.kind_event_correction',
        );
        expect(
            proposalKindKey({ type: 'line_event', payload: { ...payload, corrects: null } }),
        ).toBe('proposals.review.kind_event');
    });
});

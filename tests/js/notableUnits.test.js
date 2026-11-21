// Rames remarquables (resources/js/lib/notableUnits.js) : dates des faits à leur précision, périodes, profils.
import { describe, expect, it } from 'vitest';
import { notableDate, notableFactDate, notableProfile } from '$lib/notableUnits.js';

const t = (key, o) => `${o.from} au ${o.to}`;

describe('date d’un fait', () => {
    it('sans précision connue : rien', () => {
        expect(notableDate('2020-03-01', null)).toBeNull();
        expect(notableDate('2020-03-01', 'decade')).toBeNull();
        expect(notableDate('', 'day')).toBeNull();
    });

    it('date partielle complétée au premier du mois ou de l’année', () => {
        expect(notableDate('1978-06', 'month', 'fr')).toBe('juin 1978');
        expect(notableDate('1981', 'year', 'en')).toBe('1981');
        expect(notableDate('1981', 'day', 'fr')).toBe('1er janvier 1981');
    });

    it('anglais britannique et allemand', () => {
        expect(notableDate('2020-02-29', 'day', 'en')).toBe('29 February 2020');
        expect(notableDate('2020-02-29', 'day', 'de')).toBe('29. Februar 2020');
    });

    it('le fuseau de la machine ne change jamais le jour', () => {
        // Date construite en UTC et écrite en UTC : à Tahiti (UTC-10) comme à Auckland, même jour.
        const tz = process.env.TZ;
        try {
            for (const zone of ['Pacific/Tahiti', 'Pacific/Auckland']) {
                process.env.TZ = zone;
                expect(notableDate('2020-03-01', 'day', 'fr')).toBe('1er mars 2020');
                expect(notableDate('1978-01-01', 'month', 'fr')).toBe('janvier 1978');
            }
        } finally {
            if (tz === undefined) delete process.env.TZ;
            else process.env.TZ = tz;
        }
    });
});

describe('période d’un fait', () => {
    it('date seule sans fin', () => {
        expect(notableFactDate({ date: '1978-06-01', precision: 'month' }, t, 'fr')).toBe(
            'juin 1978',
        );
        expect(notableFactDate({ date: null, date_to: null, precision: null }, t)).toBeNull();
    });

    it('deux années différentes : chaque date garde son année', () => {
        const fact = { date: '2019-12-31', date_to: '2020-01-02', precision: 'day' };
        expect(notableFactDate(fact, t, 'fr')).toBe('31 décembre 2019 au 2 janvier 2020');
    });

    it('même année : l’année une seule fois, dans chaque langue', () => {
        const fact = { date: '2020-03-07', date_to: '2020-03-08', precision: 'day' };
        expect(notableFactDate(fact, t, 'en')).toBe('7 March au 8 March 2020');
        expect(notableFactDate(fact, t, 'de')).toBe('7. März au 8. März 2020');
        const months = { date: '1978-06-01', date_to: '1978-09-01', precision: 'month' };
        expect(notableFactDate(months, t, 'fr')).toBe('juin au septembre 1978');
    });

    it('bornes qui s’écrivent de même à cette précision : la date une seule fois', () => {
        expect(
            notableFactDate({ date: '1981-01-01', date_to: '1981-12-31', precision: 'year' }, t),
        ).toBe('1981');
        expect(
            notableFactDate({ date: '1978-06-01', date_to: '1978-06-30', precision: 'month' }, t),
        ).toBe('juin 1978');
    });

    it('début inconnu : la date de fin, jamais « null au … »', () => {
        expect(
            notableFactDate({ date: null, date_to: '2020-03-01', precision: 'day' }, t, 'fr'),
        ).toBe('1er mars 2020');
    });
});

describe('profils dessinés', () => {
    it('seulement pour les rames dessinées', () => {
        expect(notableProfile('tgv-sud-est', '01')).toContain('<svg');
        expect(notableProfile('tgv-sud-est', '02')).toBeNull();
        expect(notableProfile('bb-15000', '01')).toBeNull();
        expect(notableProfile(null, '01')).toBeNull();
        // Clés héritées d'Object : jamais prises pour une série ou une rame.
        expect(notableProfile('toString', '01')).toBeNull();
        expect(notableProfile('tgv-sud-est', 'constructor')).toBeNull();
    });
});

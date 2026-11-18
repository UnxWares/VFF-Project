// Affichage des contributions « portion par PK » (resources/js/Components/Contribute/segmentChanges.js) :
// libellés lisibles au lieu des colonnes et du JSON de la base, PK, ère courante, dates, adresses.
import { describe, expect, it } from 'vitest';
import {
    CURRENT_ERA,
    changeEntries,
    eraLabel,
    formatDay,
    formatPk,
    isHttpUrl,
    readableUrl,
} from '../../resources/js/Components/Contribute/segmentChanges.js';

const DICT = {
    'editor.segment.era_today': '{year} (aujourd’hui)',
    'editor.segment.fields.status': 'Statut',
    'editor.segment.fields.traffic_type': 'Trafic',
    'editor.segment.fields.electrified': 'Électrification',
    'editor.segment.fields.max_speed_kmh': 'Vitesse maximale',
    'editor.segment.fields.is_heritage': 'Usage touristique',
    'editor.segment.statuses.abandoned': 'Fermée (voie en place)',
    'editor.segment.traffics.mixed': 'Mixte (voyageurs et fret)',
    'editor.segment.values.yes': 'Oui',
    'editor.segment.values.no': 'Non',
    'editor.segment.values.electrified_no': 'Non électrifiée',
    'editor.segment.values.speed': '{value} km/h',
};
const t = (key, opts = {}) =>
    (DICT[key] ?? key).replace(/\{(\w+)\}/g, (_, k) => String(opts[k] ?? ''));

describe('formatPk', () => {
    it('écrit kilomètre + mètres sur trois chiffres', () => {
        expect(formatPk(0)).toBe('0+000');
        expect(formatPk(3000)).toBe('3+000');
        expect(formatPk(12345)).toBe('12+345');
        expect(formatPk(1007)).toBe('1+007');
    });
});

describe('eraLabel', () => {
    it("précise que l'ère courante est aujourd'hui", () => {
        expect(eraLabel(CURRENT_ERA, t)).toBe('2030 (aujourd’hui)');
        expect(eraLabel(1950, t)).toBe('1950');
    });
});

describe('changeEntries', () => {
    it('traduit les champs et les valeurs, sans JSON ni nom de colonne', () => {
        const rows = changeEntries(
            {
                is_heritage: true,
                max_speed_kmh: 70,
                status: 'abandoned',
                traffic_type: 'mixed',
                electrified: false,
            },
            t,
            'fr',
        );
        expect(rows.map((r) => r.label)).toEqual([
            'Statut',
            'Trafic',
            'Électrification',
            'Vitesse maximale',
            'Usage touristique',
        ]);
        expect(rows.map((r) => r.value)).toEqual([
            'Fermée (voie en place)',
            'Mixte (voyageurs et fret)',
            'Non électrifiée',
            '70 km/h',
            'Oui',
        ]);
        expect(rows.some((r) => r.value.includes('"'))).toBe(false);
    });

    it('ignore les champs vides et garde un champ inconnu lisible', () => {
        const rows = changeEntries({ status: '', voltage_v: null, other: 'x' }, t, 'fr');
        expect(rows).toEqual([{ field: 'other', label: 'other', value: 'x', before: '' }]);
        expect(changeEntries(null, t, 'fr')).toEqual([]);
    });

    it('garde « Non » pour un booléen faux (changement réel)', () => {
        expect(changeEntries({ is_heritage: false }, t, 'fr')[0].value).toBe('Non');
    });

    it('donne la valeur actuelle de la portion à côté de la valeur proposée', () => {
        const rows = changeEntries(
            { status: 'abandoned', max_speed_kmh: 60, is_heritage: true },
            t,
            'fr',
            { status: ['in_service'], max_speed_kmh: [120], is_heritage: [false] },
        );
        expect(rows.map((r) => [r.field, r.before, r.value])).toEqual([
            ['status', 'in_service', 'Fermée (voie en place)'],
            ['max_speed_kmh', '120 km/h', '60 km/h'],
            ['is_heritage', 'Non', 'Oui'],
        ]);
    });

    it('réunit les valeurs actuelles distinctes quand la portion en mêle plusieurs', () => {
        const [row] = changeEntries({ max_speed_kmh: 60 }, t, 'fr', { max_speed_kmh: [120, 40] });
        expect(row.before).toBe('120 km/h, 40 km/h');
    });

    it('laisse la valeur actuelle vide quand le serveur ne la connaît pas', () => {
        expect(changeEntries({ status: 'abandoned' }, t, 'fr')[0].before).toBe('');
        expect(changeEntries({ status: 'abandoned' }, t, 'fr', null)[0].before).toBe('');
        expect(changeEntries({ status: 'abandoned' }, t, 'fr', { status: [] })[0].before).toBe('');
        expect(
            changeEntries({ status: 'abandoned' }, t, 'fr', { max_speed_kmh: [80] })[0].before,
        ).toBe('');
    });
});

describe('formatDay', () => {
    it('formate la date du serveur dans la langue de la page', () => {
        expect(formatDay('2026-10-20 11:21', 'fr')).toBe('20 octobre 2026');
        expect(formatDay('2026-10-20 11:21', 'en')).toBe('20 October 2026');
        expect(formatDay('2026-10-20 11:21', 'de')).toBe('20. Oktober 2026');
        // Date ISO 8601 avec décalage (Mes contributions) : le jour affiché est celui du serveur.
        expect(formatDay('2026-11-18T23:30:00+01:00', 'fr')).toBe('18 novembre 2026');
        expect(formatDay('2026-11-18T10:21:00+01:00', 'en')).toBe('18 November 2026');
        expect(formatDay('il y a 3 jours', 'fr')).toBe('il y a 3 jours');
        expect(formatDay(null, 'fr')).toBe('');
    });
});

describe('adresses de source', () => {
    it('décode une adresse encodée et reconnaît les liens', () => {
        expect(
            readableUrl(
                'https://fr.wikipedia.org/wiki/Ligne_de_Briouze_%C3%A0_La_Fert%C3%A9-Mac%C3%A9',
            ),
        ).toBe('https://fr.wikipedia.org/wiki/Ligne_de_Briouze_à_La_Ferté-Macé');
        expect(readableUrl('%E0%A4%A')).toBe('%E0%A4%A');
        expect(isHttpUrl('https://example.org/a')).toBe(true);
        expect(isHttpUrl('Indicateur Chaix 1950')).toBe(false);
    });
});

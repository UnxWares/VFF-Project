import { describe, expect, it } from 'vitest';
import {
    accidentDate,
    distanceText,
    providerKey,
    timeText,
    tollText,
    trainText,
} from '../../resources/js/lib/accidentText.js';

/** Traduction factice : renvoie la clé et les paramètres, pour vérifier ce qui est demandé. */
const t = (key, opts = {}) => (opts.count !== undefined ? `${key}:${opts.count}` : key);

describe('accidentDate', () => {
    it('affiche seulement la partie connue de la date', () => {
        expect(accidentDate('2013-07-12', 'day', 'fr')).toBe('12 juillet 2013');
        expect(accidentDate('2013-07-01', 'month', 'fr')).toBe('juillet 2013');
        expect(accidentDate('1891-01-01', 'year', 'fr')).toBe('1891');
    });

    it('met le premier du mois à l’ordinal en français, en anglais britannique ailleurs', () => {
        expect(accidentDate('1922-08-01', 'day', 'fr')).toBe('1er août 1922');
        expect(accidentDate('2013-07-12', 'day', 'en')).toBe('12 July 2013');
        expect(accidentDate('2013-07-12', 'day', 'de')).toBe('12. Juli 2013');
    });
});

describe('tollText', () => {
    it('donne les morts puis les blessés connus', () => {
        expect(tollText(7, 192, t)).toBe('accidents.deaths:7, accidents.injuries:192');
        expect(tollText(43, null, t)).toBe('accidents.deaths:43');
        expect(tollText(0, 3, t)).toBe('accidents.deaths:0, accidents.injuries:3');
    });

    it('dit « inconnu » plutôt que zéro quand aucune source ne donne le bilan', () => {
        expect(tollText(null, null, t)).toBe('accidents.toll_unknown');
    });
});

describe('trainText', () => {
    it('nomme le service, son numéro et le matériel', () => {
        expect(trainText({ label: 'TER', number: '849 902' }, t)).toBe('TER 849 902');
        expect(trainText({ label: 'TGV', material: 'TGV Sud-Est' }, t)).toBe('TGV (TGV Sud-Est)');
    });

    it('traduit la catégorie quand le service n’est pas nommé', () => {
        expect(trainText({ category: 'freight' }, t)).toBe('accidents.train_categories.freight');
    });

    it('se rabat sur le matériel, puis l’exploitant', () => {
        expect(trainText({ material: 'SNCF Z 7300' }, t)).toBe('SNCF Z 7300');
        expect(trainText({ operator: 'SNCF' }, t)).toBe('SNCF');
        expect(trainText({}, t)).toBe('accidents.train_categories.unknown');
    });
});

describe('distanceText', () => {
    it('écrit les mètres sous 1 km et les kilomètres au-delà, unité insécable', () => {
        expect(distanceText(924, 'fr')).toBe('924 m');
        expect(distanceText(1240, 'fr')).toBe('1,2 km');
        expect(distanceText(1240, 'en')).toBe('1.2 km');
        expect(distanceText(null)).toBe('');
    });
});

describe('providerKey', () => {
    it('rend une clé de traduction sans tiret', () => {
        expect(providerKey('bea-tt')).toBe('accidents.providers.bea_tt');
        expect(providerKey('wikidata')).toBe('accidents.providers.wikidata');
    });
});

describe('timeText', () => {
    it('écrit l’heure selon la langue', () => {
        expect(timeText('06:29', 'fr')).toBe('6\u00a0h\u00a029');
        expect(timeText('06:29', 'en')).toBe('06:29');
        expect(timeText('06:29', 'de')).toBe('06:29\u00a0Uhr');
        expect(timeText(null)).toBe('');
    });
});

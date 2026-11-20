// Histoire d'un événement : légende datée de chaque image, écart en toutes lettres, date des documents de
// Gallica, titre de la page, pas du carrousel (lib/eventStory.js).
import { describe, expect, it } from 'vitest';
import {
    authorName,
    documentDate,
    documentMeta,
    groupDocuments,
    gapPhrase,
    imageLegend,
    stepIndex,
    storyTitle,
    yearsWord,
} from '$lib/eventStory.js';

// Traductions françaises de lang/fr/stories.php et archives.php, au format i18next (pluriel par `count`).
const FR = {
    'stories.numbers.2': 'deux',
    'stories.numbers.4': 'quatre',
    'stories.numbers.5': 'cinq',
    'stories.gap.same_year': 'la même année',
    'stories.gap.after_one': 'un an après',
    'stories.gap.after_other': '{{years}} ans après',
    'stories.gap.before_one': 'un an avant',
    'stories.gap.before_other': '{{years}} ans avant',
    'stories.gap.attached': 'image rattachée à l’événement',
    'stories.media_types.photo': 'Photo',
    'stories.media_types.postcard': 'Carte postale',
    'stories.dated': '{{type}} de {{year}}',
    'stories.dated_circa': '{{type}} des environs de {{year}}',
    'stories.undated': '{{type}} sans date',
    'stories.titles.opening': 'Ouverture',
    'stories.title_section': '{{event}} {{section}}',
    'archives.line.circa': 'vers {{year}}',
    'archives.line.undated': 'Sans date',
    'archives.line.types.image': 'Image',
    'archives.line.types.map': 'Carte ou plan',
    'archives.line.shelfmark_one': 'cote {{shelfmark}}',
    'archives.line.shelfmark_other': 'cotes {{shelfmark}}',
};

function t(key, opts = {}) {
    const plural = opts.count !== undefined ? `${key}_${opts.count === 1 ? 'one' : 'other'}` : null;
    const text = (plural && FR[plural]) ?? FR[key] ?? key;
    return text.replace(/\{\{(\w+)\}\}/g, (_, k) => String(opts[k] ?? ''));
}

describe('gapPhrase', () => {
    it('dit l’écart en lettres, avant ou après', () => {
        expect(gapPhrase(4, t)).toBe('quatre ans après');
        expect(gapPhrase(-2, t)).toBe('deux ans avant');
        expect(gapPhrase(1, t)).toBe('un an après');
        expect(gapPhrase(-1, t)).toBe('un an avant');
        expect(gapPhrase(0, t)).toBe('la même année');
    });

    it('écrit en chiffres au-delà de dix ans, et rien pour une image sans date', () => {
        expect(gapPhrase(37, t)).toBe('37 ans après');
        expect(gapPhrase(null, t)).toBeNull();
        expect(gapPhrase(null, t, true)).toBe('image rattachée à l’événement');
    });
});

describe('yearsWord', () => {
    it('lettres de 2 à 10 quand la traduction existe, chiffres sinon', () => {
        expect(yearsWord(5, t)).toBe('cinq');
        expect(yearsWord(3, t)).toBe('3');
        expect(yearsWord(12, t)).toBe('12');
    });
});

describe('imageLegend', () => {
    it('« Photo de 1934, quatre ans après »', () => {
        expect(
            imageLegend(
                { type: 'photo', depicted_year: 1934, date_precision: 'year', gap_years: 4 },
                t,
            ),
        ).toBe('Photo de 1934, quatre ans après');
    });

    it('date approchée, autre nature, image sans date de l’événement', () => {
        expect(
            imageLegend(
                { type: 'postcard', depicted_year: 1905, date_precision: 'circa', gap_years: -2 },
                t,
            ),
        ).toBe('Carte postale des environs de 1905, deux ans avant');
        expect(imageLegend({ type: 'photo', depicted_year: null, attached: true }, t)).toBe(
            'Photo sans date, image rattachée à l’événement',
        );
        expect(imageLegend({ type: 'inconnu', depicted_year: 1843, gap_years: 0 }, t)).toBe(
            'Photo de 1843, la même année',
        );
    });
});

describe('documentDate', () => {
    it('suit la précision de la notice', () => {
        expect(documentDate({ year: 1838, precision: 'year' }, t)).toBe('1838');
        expect(documentDate({ year: 1850, precision: 'circa' }, t)).toBe('vers 1850');
        expect(documentDate({ year: 1840, year_to: 1845, precision: 'range' }, t)).toBe(
            '1840-1845',
        );
        expect(documentDate({ year: 1873, exact: '1873-11-01', precision: 'day' }, t)).toMatch(
            /1873/,
        );
        expect(documentDate({ year: null, precision: 'unknown' }, t)).toBe('Sans date');
    });
});

describe('storyTitle', () => {
    it('nature de l’événement et portion, ou nature seule', () => {
        expect(storyTitle('opening', 'de Vernon-Giverny à Port-Villez', t)).toBe(
            'Ouverture de Vernon-Giverny à Port-Villez',
        );
        expect(storyTitle('opening', null, t)).toBe('Ouverture');
        expect(storyTitle('nouveau_type', null, t)).toBe('nouveau_type');
    });
});

describe('stepIndex', () => {
    it('reste dans les bornes, sans retour au début', () => {
        expect(stepIndex(0, -1, 5)).toBe(0);
        expect(stepIndex(4, 1, 5)).toBe(4);
        expect(stepIndex(2, 1, 5)).toBe(3);
        expect(stepIndex(3, -5, 5)).toBe(0);
        expect(stepIndex(1, 5, 5)).toBe(4);
        expect(stepIndex(0, 1, 0)).toBe(0);
    });
});

describe('groupDocuments', () => {
    it('une entrée par titre, date et type, avec les autres vues et toutes les cotes', () => {
        const photo = (ark, shelfmark) => ({
            gallica_ark: ark,
            gallica_url: `https://gallica.bnf.fr/${ark}`,
            title: 'Tours, 21/6/26, catastrophe de chemin de fer',
            type: 'image',
            date: { label: '21-06-1926' },
            shelfmark,
        });
        const groups = groupDocuments([
            photo('a', 'EI-13 (1338)'),
            photo('b', 'EI-13 (1339)'),
            photo('c', 'EI-13 (1338)'),
            { ...photo('d', null), type: 'map' },
        ]);
        expect(groups).toHaveLength(2);
        expect(groups[0].more_urls).toEqual([
            'https://gallica.bnf.fr/b',
            'https://gallica.bnf.fr/c',
        ]);
        expect(groups[0].shelfmarks).toEqual(['EI-13 (1338)', 'EI-13 (1339)']);
        expect(groups[1].shelfmarks).toEqual([]);
    });
});

describe('documentMeta', () => {
    it('« Image · Agence Rol · cote … », séparateurs espacés', () => {
        expect(
            documentMeta({ type: 'image', authors: ['Agence Rol'], shelfmarks: ['EI-13'] }, t),
        ).toBe('Image · Agence Rol · cote EI-13');
        expect(
            documentMeta(
                { type: 'map', authors: ['Villemandy, Alfred de'], shelfmarks: ['A', 'B'] },
                t,
            ),
        ).toBe('Carte ou plan · Alfred de Villemandy · cotes A, B');
        expect(documentMeta({ type: 'map', authors: [], shelfmarks: [] }, t)).toBe('Carte ou plan');
        expect(authorName('Agence Rol')).toBe('Agence Rol');
    });
});

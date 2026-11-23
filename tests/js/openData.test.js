import { describe, expect, it } from 'vitest';
import { formatBytes, networkCounts, withheldRows } from '../../resources/js/lib/openData.js';

describe('formatBytes', () => {
    it('garde les octets entiers et passe au ko puis au Mo', () => {
        expect(formatBytes(512, 'en')).toBe('512 byte');
        expect(formatBytes(1536, 'en')).toBe('1.5 kB');
        expect(formatBytes(5 * 1024 * 1024, 'en')).toBe('5 MB');
    });

    it('arrondit au-delà de 100 et accepte une valeur absente', () => {
        expect(formatBytes(150 * 1024, 'en')).toBe('150 kB');
        expect(formatBytes(undefined, 'en')).toBe('0 byte');
    });

    it('suit la langue de la page', () => {
        expect(formatBytes(1536, 'fr')).toMatch(/^1,5\s?ko$/u);
    });
});

describe('networkCounts', () => {
    it('lit les lignes des CSV du réseau', () => {
        const files = [
            { name: 'lignes.csv', rows: 3 },
            { name: 'sections.csv', rows: 88 },
            { name: 'gares.csv', rows: 23 },
            { name: 'LISEZMOI.txt', rows: null },
        ];
        expect(networkCounts(files)).toEqual({ lines: 3, sections: 88, stations: 23 });
        expect(networkCounts([])).toEqual({ lines: 0, sections: 0, stations: 0 });
    });
});

describe('withheldRows', () => {
    it('trie par total décroissant et retire les lignes vides', () => {
        expect(
            withheldRows({
                'lines.name': { removed: 1, replaced: 1 },
                'sections.geometry': { removed: 11, replaced: 46 },
                events: { removed: 0, replaced: 0 },
            }),
        ).toEqual([
            { key: 'sections.geometry', removed: 11, replaced: 46 },
            { key: 'lines.name', removed: 1, replaced: 1 },
        ]);
    });
});

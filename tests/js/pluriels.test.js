// Vrais pluriels des pages publiques : plus de « (s) » dans les fichiers de langue, et les clés
// `_one` / `_other` donnent le bon nombre en français, en anglais et en allemand.
import { readFileSync, readdirSync } from 'node:fs';
import i18next from 'i18next';
import { describe, expect, it } from 'vitest';

const LANG = new URL('../../lang/', import.meta.url);

function php(locale, file) {
    const source = readFileSync(new URL(`${locale}/${file}.php`, LANG), 'utf8');
    return Object.fromEntries(
        [...source.matchAll(/'([a-z_]+)'\s*=>\s*'((?:[^'\\]|\\.)*)'/g)].map((m) => [
            m[1],
            m[2].replace(/:([a-z]+)/g, '{{$1}}').replace(/\\'/g, "'"),
        ]),
    );
}

async function t(locale, file, key, count) {
    const i = i18next.createInstance();
    await i.init({
        lng: locale,
        resources: { [locale]: { translation: php(locale, file) } },
        interpolation: { escapeValue: false },
    });
    return i.t(key, { count, shown: String(count) });
}

describe('pluriels', () => {
    it('aucun « (s) » ni « (e) » collé à un mot dans les fichiers de langue', () => {
        for (const locale of ['fr', 'en', 'de']) {
            for (const file of readdirSync(new URL(`${locale}/`, LANG))) {
                const source = readFileSync(new URL(`${locale}/${file}`, LANG), 'utf8');
                expect(
                    source.match(/[\p{L}]\((s|e|n|en|es)\)/gu) ?? [],
                    `${locale}/${file}`,
                ).toEqual([]);
            }
        }
    });

    it('tronçons, coudes, allers-retours et inversions en français', async () => {
        expect(await t('fr', 'audit_segments', 'segments_count', 1)).toBe('1 tronçon');
        expect(await t('fr', 'audit_segments', 'segments_count', 281)).toBe('281 tronçons');
        expect(await t('fr', 'audit_segments', 'kinks', 1)).toBe('1 coude');
        expect(await t('fr', 'audit_segments', 'kinks', 3)).toBe('3 coudes');
        expect(await t('fr', 'audit_segments', 'hairpins', 1)).toBe('1 aller-retour');
        expect(await t('fr', 'audit_segments', 'hairpins', 2)).toBe('2 allers-retours');
        expect(await t('fr', 'audit_segments', 'inversions', 2)).toContain('2 inversions');
    });

    it('sections et gares en anglais, Abschnitte et Bahnhöfe en allemand', async () => {
        expect(await t('en', 'audit_segments', 'segments_count', 1)).toBe('1 section');
        expect(await t('en', 'audit_segments', 'segments_count', 281)).toBe('281 sections');
        expect(await t('en', 'audit_segments', 'inversions', 2)).toContain('2 stations');
        expect(await t('de', 'audit_segments', 'segments_count', 1)).toBe('1 Abschnitt');
        expect(await t('de', 'audit_segments', 'segments_count', 281)).toBe('281 Abschnitte');
        expect(await t('de', 'audit_segments', 'inversions', 1)).toContain('1 Bahnhof nicht');
        expect(await t('de', 'audit_segments', 'inversions', 4)).toContain('4 Bahnhöfe');
    });

    it('autres lignes d’une gare : « autre » ou « autres »', async () => {
        expect(await t('fr', 'stations', 'more_lines', 1)).toBe('+ 1 autre');
        expect(await t('fr', 'stations', 'more_lines', 5)).toBe('+ 5 autres');
    });
});

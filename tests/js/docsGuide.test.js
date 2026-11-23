import { describe, expect, it } from 'vitest';
import { expand, GUIDES, guidePages, orphanFiles } from '../../resources/docs/graphql/guide.mjs';

describe('guide de la référence GraphQL', () => {
    it('remplace les marqueurs et les adresses', () => {
        const out = expand(
            '{{origin}}/graphql\n{{query:07-search}}\n{{variables:07-search}}',
            'https://x.test',
        );
        expect(out).toContain('https://x.test/graphql');
        expect(out).toContain('```graphql\nquery Search');
        expect(out).toContain('```json\n{ "text": "brest" }');
        expect(out).not.toContain('{{');
    });

    it('refuse un exemple inconnu', () => {
        expect(() => expand('{{query:inexistant}}', '')).toThrow();
    });

    it('construit une rubrique de deux pages par langue, sans marqueur restant', () => {
        const pages = guidePages('https://vff-project.org');
        expect(pages).toHaveLength(GUIDES.length);
        for (const guide of pages) {
            expect(guide.content).toHaveLength(2);
            for (const page of guide.content) {
                expect(page.content).not.toMatch(/\{\{[^}]*\}\}/);
                expect(page.content).not.toContain('—');
            }
        }
    });

    it('déclare tous les fichiers du guide', () => {
        expect(orphanFiles()).toEqual([]);
    });

    // Liens relatifs à la racine de la référence (/guide-en/…) : Magidoc ajoute lui-même /docs/graphql.
    it('relie chaque langue aux deux autres', () => {
        const prefixes = { fr: '(/guide/', en: '(/guide-en/', de: '(/guide-de/' };
        guidePages('https://vff-project.org').forEach((guide, i) => {
            const text = guide.content[0].content;
            for (const [locale, prefix] of Object.entries(prefixes)) {
                if (locale !== GUIDES[i].locale) expect(text).toContain(prefix);
            }
        });
    });
});

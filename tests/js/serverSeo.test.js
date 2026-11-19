import { afterEach, describe, expect, it, vi } from 'vitest';
import { dropStaleServerSeo, serverSeoCovers } from '../../resources/js/lib/serverSeo.js';

/** Faux <head> : balises du serveur (data-vff-seo) retirables une à une, comme dans le navigateur. */
function fakeHead({ url, locale }) {
    const make = (attr, selector, matching = []) => ({
        selector,
        attributes: { content: attr, 'data-vff-seo': '' },
        getAttribute(name) {
            return this.attributes[name] ?? null;
        },
        setAttribute(name, value) {
            this.attributes[name] = value;
        },
        removeAttribute(name) {
            delete this.attributes[name];
        },
        matches: (s) => matching.includes(s),
        remove() {
            this.removed = true;
        },
    });
    const tags = [
        make(url, 'meta[property="og:url"][data-vff-seo]'),
        make(locale, 'meta[property="og:locale"][data-vff-seo]'),
        make('Description de la gare de Bondy', '[data-vff-seo]', ['meta[name="description"]']),
        make('https://vff-project.org/stations/bondy', '[data-vff-seo]', ['link[rel="canonical"]']),
        make('x', '[data-vff-seo]'),
    ];
    const head = {
        querySelector: (selector) => tags.find((t) => t.selector === selector) ?? null,
        querySelectorAll: () => tags,
    };
    vi.stubGlobal('document', { head });
    return tags;
}

afterEach(() => vi.unstubAllGlobals());

describe('serverSeoCovers', () => {
    it('reconnaît la page dont le serveur a écrit les balises, dans la même langue', () => {
        fakeHead({ url: 'https://vff-project.org/lines/rfn-610000', locale: 'fr_FR' });
        expect(serverSeoCovers('/lines/rfn-610000', 'fr_FR')).toBe(true);
    });

    it('ne couvre ni une autre page ni une autre langue', () => {
        fakeHead({ url: 'https://vff-project.org/lines/rfn-610000', locale: 'fr_FR' });
        expect(serverSeoCovers('/lines/rfn-570000', 'fr_FR')).toBe(false);
        expect(serverSeoCovers('/lines/rfn-610000', 'en_GB')).toBe(false);
    });

    it('ne couvre rien quand le serveur n’a écrit aucune balise ou qu’il n’y a pas de document', () => {
        vi.stubGlobal('document', { head: { querySelector: () => null } });
        expect(serverSeoCovers('/', 'fr_FR')).toBe(false);
        vi.unstubAllGlobals();
        expect(serverSeoCovers('/', 'fr_FR')).toBe(false);
    });
});

describe('dropStaleServerSeo', () => {
    it('garde les balises de la page courante', () => {
        const tags = fakeHead({ url: 'https://vff-project.org/stations/bondy', locale: 'fr_FR' });
        dropStaleServerSeo('/stations/bondy', 'fr_FR');
        expect(tags.some((t) => t.removed)).toBe(false);
    });

    it('retire toutes les balises du serveur après une navigation ou un changement de langue', () => {
        const tags = fakeHead({ url: 'https://vff-project.org/stations/bondy', locale: 'fr_FR' });
        dropStaleServerSeo('/lines', 'fr_FR');
        expect(tags.every((t) => t.removed)).toBe(true);

        const others = fakeHead({ url: 'https://vff-project.org/stations/bondy', locale: 'fr_FR' });
        dropStaleServerSeo('/stations/bondy', 'de_DE');
        expect(others.every((t) => t.removed)).toBe(true);
    });

    it('garde une description générale, sans la marque du serveur, et retire l’adresse canonique', () => {
        const tags = fakeHead({ url: 'https://vff-project.org/stations/bondy', locale: 'fr_FR' });
        const [, , description, canonical, other] = tags;
        dropStaleServerSeo('/lines', 'fr_FR', 'Encyclopédie collaborative du rail français');

        expect(description.removed).toBeUndefined();
        expect(description.getAttribute('content')).toBe(
            'Encyclopédie collaborative du rail français',
        );
        expect(description.getAttribute('data-vff-seo')).toBeNull();
        expect(canonical.removed).toBe(true);
        expect(other.removed).toBe(true);
    });
});

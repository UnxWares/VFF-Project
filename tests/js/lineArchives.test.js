// Section « Dans les archives » de la fiche ligne (revue du commit bb27386, points 7 et 8) : sur une page en
// anglais ou en allemand, seul le titre de la notice de la BnF est en français (l'aide pour lecteur d'écran
// suit la langue de la page) ; une entrée qui regroupe deux exemplaires montre leurs deux cotes.
import i18next from 'i18next';
import { render } from 'svelte/server';
import { describe, expect, it } from 'vitest';
import { setupI18n } from '$lib/i18n.js';
import LineArchives from '$components/Lines/LineArchives.svelte';

const item = {
    title: "Ode sur l'amiral Dumont d'Urville",
    type: 'monograph',
    date: '1842',
    year: 1842,
    year_to: null,
    exact: null,
    date_precision: 'year',
    authors: [],
    shelfmarks: ['YE-54393', 'YE-54394'],
    url: 'https://gallica.bnf.fr/ark:/12148/a',
    more_urls: ['https://gallica.bnf.fr/ark:/12148/b'],
};
const archives = { total: 2, fetched_on: '2026-11-20', items: [item] };
const translations = {
    'archives.line.shelfmark_one': 'cote :shelfmark',
    'archives.line.shelfmark_other': 'cotes :shelfmark',
    'archives.line.circa': 'vers :year',
    'archives.line.before': 'avant :year',
    'archives.line.after': 'après :year',
    'archives.line.show_more_one': 'Afficher l’autre document',
    'archives.line.show_more_other': 'Afficher les :count autres documents',
};

describe('section des archives', () => {
    it('ne met lang="fr" que sur le titre, pas sur l aide du lien', () => {
        setupI18n({ locale: 'en', translations: {} });
        expect(i18next.language).toBe('en');
        const { body } = render(LineArchives, { props: { archives } });

        expect(body).toContain(`<span lang="fr">${item.title}</span>`);
        // Le lien lui-même ne porte pas de langue : son aide masquée est dans la langue de la page.
        expect(body).not.toMatch(/<a[^>]*lang="fr"/);
    });

    it('montre les deux cotes d une entrée regroupée', () => {
        setupI18n({ locale: 'fr', translations });
        const { body } = render(LineArchives, { props: { archives } });

        expect(body).toContain('cotes YE-54393, YE-54394');
    });

    it('écrit une borne haute « avant 1885 », pas une date approchée', () => {
        setupI18n({ locale: 'fr', translations });
        const bounded = (precision) => ({
            ...item,
            date: 'Avant 1885',
            year: 1885,
            date_precision: precision,
            more_urls: [],
            url: `https://gallica.bnf.fr/ark:/12148/${precision}`,
        });
        const { body } = render(LineArchives, {
            props: { archives: { ...archives, items: [bounded('before'), bounded('after')] } },
        });

        expect(body).toContain('avant 1885');
        expect(body).toContain('après 1885');
        expect(body).not.toContain('vers 1885');
    });

    it('compte les documents cachés, regroupés ou non, dans le bouton', () => {
        setupI18n({ locale: 'fr', translations });
        // 8 entrées visibles, puis deux entrées cachées : l une regroupe trois notices (deux autres vues).
        const entries = Array.from({ length: 10 }, (_, i) => ({
            ...item,
            title: `Document ${i}`,
            url: `https://gallica.bnf.fr/ark:/12148/d${i}`,
            more_urls:
                i === 8
                    ? [
                          'https://gallica.bnf.fr/ark:/12148/v1',
                          'https://gallica.bnf.fr/ark:/12148/v2',
                      ]
                    : [],
        }));
        const { body } = render(LineArchives, {
            props: { archives: { total: 12, fetched_on: '2026-11-20', items: entries } },
        });

        expect(body).toContain('Afficher les 4 autres documents');
    });
});
